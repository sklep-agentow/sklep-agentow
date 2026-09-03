#!/bin/bash
# dowod-aktualizacji.sh — AKTUALIZACJA W MIEJSCU, nie czysta instalacja.  WERSJA 1.0.0
#
# PO CO TO POWSTAŁO: u klienta aktualizacja zdarza się CZĘŚCIEJ niż czysta
# instalacja — a to zupełnie inna ścieżka kodu. Podmiana plików **nie przechodzi
# przez hook aktywacji**: `register_activation_hook` nie odpala się przy
# aktualizacji, więc wszystko, co wtyczka robi „przy instalacji" (zakładanie
# tabel, dokładanie kolumn, ustawianie opcji startowych), przy aktualizacji musi
# zrobić ktoś inny — zwykle porównanie wersji schematu przy `plugins_loaded`.
# Jeśli tego porównania nie ma albo jest błędne, klient po aktualizacji dostaje
# wtyczkę, która pyta bazę o kolumnę, której u niego nie ma. Czysta instalacja
# nigdy tego nie pokaże, bo u niej hook aktywacji zrobił wszystko.
#
# Skrypt: instaluje STARĄ paczkę, zdejmuje odcisk schematu i zostawia własne
# dane-znaczniki, podmienia pliki na NOWĄ paczkę BEZ deaktywacji, puka na stronę
# (żeby odpalił się kod aktualizacji) i sprawdza pięć rzeczy:
#
#   1. wersja wtyczki po podmianie jest NOWA,
#   2. wersja schematu w bazie POSZŁA W GÓRĘ i równa się tej z nowego kodu,
#   3. schemat PRZYBYŁ (nowe tabele albo kolumny) i nic z niego NIE ZNIKŁO,
#   4. STARE DANE PRZETRWAŁY — co do wiersza i co do treści,
#   5. nie poleciał ani jeden notice, warning, deprecated ani fatal.
#
# Użycie:
#   bash narzedzia-qa/dowod-aktualizacji.sh chatbot-live 1.4.9
#   bash narzedzia-qa/dowod-aktualizacji.sh chatbot-live 1.4.9 --zostaw
#
# Starą paczkę bierze z `boty/paczki/`, a gdy jej tam już nie ma (nowe wydanie
# kasuje poprzednią) — WYCIĄGA JĄ Z HISTORII GITA. To celowe: ścieżka
# aktualizacji dotyczy wersji, których w drzewie roboczym już nie ma.
#
# Kody wyjścia: 0 = wszystkie pięć dowodów wyszło
#               1 = choć jeden NIE wyszedł (wypisane, który)
#               2 = nie ma czego sprawdzać (brak paczki, stos nie wstaje) ALBO NIE WIEM (to konto nie widzi Dockera — komunikat zaczyna się od „NIE WIEM”)
#                   — `wymagaj_narzedzi` z lib-stos-probny.sh rozróżnia te dwa przypadki w komunikacie
set -u

WERSJA=1.0.0

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
STOS_SH="$KORZEN/narzedzia-qa/stos.sh"

# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. "$KORZEN/narzedzia-qa/lib-stos-probny.sh"

# --- argumenty ----------------------------------------------------------------

WTYCZKA="${1:-}"
WERSJA_STARA="${2:-}"
[ -n "$WTYCZKA" ] && [ -n "$WERSJA_STARA" ] \
	|| padnij "użycie: bash narzedzia-qa/dowod-aktualizacji.sh <wtyczka> <wersja-stara> [--zostaw]"
[ "${3:-}" = "--zostaw" ] && ZOSTAW=tak

[ -d "wtyczki/$WTYCZKA" ] || padnij "nie ma katalogu wtyczki [wtyczki/$WTYCZKA]"
WERSJA_NOWA="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "wtyczki/$WTYCZKA/$WTYCZKA.php" | head -1)"
[ -n "$WERSJA_NOWA" ] || padnij "nie umiem odczytać wersji z nagłówka wtyczki"
[ "$WERSJA_STARA" != "$WERSJA_NOWA" ] || padnij "wersja stara i nowa to ta sama liczba ($WERSJA_NOWA) — nie ma czego aktualizować"

PACZKA_NOWA="boty/paczki/$WTYCZKA-$WERSJA_NOWA.zip"
[ -f "$PACZKA_NOWA" ] || padnij "nie ma nowej paczki [$PACZKA_NOWA]"

# --- stara paczka: z katalogu albo z HISTORII GITA ----------------------------

SCIEZKA_STARA="boty/paczki/$WTYCZKA-$WERSJA_STARA.zip"
ROBOCZY="$(mktemp -d)"
if [ -f "$SCIEZKA_STARA" ]; then
	PACZKA_STARA="$SCIEZKA_STARA"
	ZRODLO_STAREJ="katalog boty/paczki"
else
	# Wydanie kasuje poprzednią paczkę, więc stara wersja żyje już tylko
	# w historii. Bierzemy ją z commita SPRZED skasowania.
	_sha="$(git log --format='%H' --diff-filter=D -1 -- "$SCIEZKA_STARA" 2> /dev/null)"
	[ -n "$_sha" ] || padnij "nie ma paczki [$SCIEZKA_STARA] ani w katalogu, ani w historii gita"
	PACZKA_STARA="$ROBOCZY/$WTYCZKA-$WERSJA_STARA.zip"
	git show "$_sha^:$SCIEZKA_STARA" > "$PACZKA_STARA" 2> /dev/null \
		|| padnij "nie umiem wyciągnąć [$SCIEZKA_STARA] z commita $_sha^"
	ZRODLO_STAREJ="historia gita, commit ${_sha:0:7}^"
fi

# --- co wiemy o KAŻDYM z botów (wpisane tutaj, nie zgadywane) -----------------
#
# PREFIKS      — prefiks tabel i opcji wtyczki (bez `wp_`).
# OPCJA_SCHEMAT — opcja, w której wtyczka trzyma numer wersji schematu.
# ZOSTAW_ZNACZNIK — PHP dokładający WŁASNY wiersz z rozpoznawalną treścią.
#                Seed instalatora nie wystarcza: przetrwanie danych, które
#                instalator i tak wpisuje od nowa, niczego nie dowodzi.
# POLICZ_ZNACZNIK — PHP wypisujący `ZNACZNIK:<ile>` — ile razy ten wiersz jest w bazie.
case "$WTYCZKA" in
	chatbot-live)
		PREFIKS=chatl
		OPCJA_SCHEMAT=chatl_wersja_schematu
		ZOSTAW_ZNACZNIK='global $wpdb; $wpdb->insert( $wpdb->prefix . "chatl_qa", array( "pytanie" => "ZNACZNIK-AKTUALIZACJI", "odpowiedz" => "ta tresc ma przetrwac aktualizacje", "aktywne" => 1 ) ); echo "WSTAWIONE:" . (int) $wpdb->insert_id;'
		POLICZ_ZNACZNIK='global $wpdb; echo "ZNACZNIK:" . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_qa WHERE pytanie = %s AND odpowiedz = %s", "ZNACZNIK-AKTUALIZACJI", "ta tresc ma przetrwac aktualizacje" ) );'
		;;
	segregator-maila)
		PREFIKS=segm
		OPCJA_SCHEMAT=segm_wersja_schematu
		ZOSTAW_ZNACZNIK='global $wpdb; $wpdb->insert( $wpdb->prefix . "segm_kategorie", array( "nazwa" => "ZNACZNIK-AKTUALIZACJI", "folder" => "ZNACZNIK/AKTUALIZACJI" ) ); echo "WSTAWIONE:" . (int) $wpdb->insert_id;'
		POLICZ_ZNACZNIK='global $wpdb; echo "ZNACZNIK:" . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s AND folder = %s", "ZNACZNIK-AKTUALIZACJI", "ZNACZNIK/AKTUALIZACJI" ) );'
		;;
	*)
		padnij "nie znam wtyczki [$WTYCZKA] — dopisz ją do tablicy w tym skrypcie, nie obchodź jej"
		;;
esac

wymagaj_narzedzi
trap 'zwin_stos; rm -rf "$ROBOCZY"' EXIT

wgraj() { docker cp "$1" "$KONTENER:/tmp/paczka.zip" > /dev/null && wp plugin install /tmp/paczka.zip --force ${2:-}; }

# ==============================================================================

printf '=== DOWOD AKTUALIZACJI W MIEJSCU %s ===\n' "$WERSJA"
printf '  wtyczka:      %s\n' "$WTYCZKA"
printf '  stara wersja: %s  (źródło: %s)\n' "$WERSJA_STARA" "$ZRODLO_STAREJ"
printf '  nowa wersja:  %s  (%s)\n' "$WERSJA_NOWA" "$PACZKA_NOWA"
printf '  suma starej:  %s\n' "$(sha256sum "$PACZKA_STARA" | cut -d' ' -f1)"
printf '  suma nowej:   %s\n' "$(sha256sum "$PACZKA_NOWA" | cut -d' ' -f1)"

postaw_stos

# ---- STAN PRZED: stara paczka, jej schemat i nasze dane ----
naglowek "PRZED — stara paczka $WERSJA_STARA na czystym WordPressie"
WY="$(wgraj "$PACZKA_STARA" --activate)"
printf '%s' "$WY" | grep -q 'successfully' || padnij "nie udało się zainstalować starej paczki: $WY"
wp plugin is-active "$WTYCZKA" > /dev/null 2>&1 || padnij "stara paczka wgrana, ale nie jest aktywna"
KOD="$(zapukaj_na_strone "$STOS_SH")"
printf '  stara wersja aktywna, strona odpowiada HTTP %s\n' "${KOD:-brak}"

WERSJA_PLIKU_PRZED="$(wp plugin get "$WTYCZKA" --field=version | tr -d '\r')"
SCHEMAT_PRZED="$(opcja "$OPCJA_SCHEMAT" | tr -d '\r')"
SCHEMAT_ODCISK_PRZED="$ROBOCZY/schemat-przed.txt"
odcisk_schematu "$PREFIKS" > "$SCHEMAT_ODCISK_PRZED"
printf '  wersja wtyczki: %s · wersja schematu w bazie: %s\n' "$WERSJA_PLIKU_PRZED" "${SCHEMAT_PRZED:-BRAK}"
printf '  odcisk schematu: %s kolumn w tabelach wp_%s*\n' "$(wc -l < "$SCHEMAT_ODCISK_PRZED")" "$PREFIKS"

WSTAWIONE="$(wp eval "$ZOSTAW_ZNACZNIK" | tr -d '\r')"
printf '  zostawiam wlasny wiersz-znacznik: %s\n' "$WSTAWIONE"
ZNACZNIK_PRZED="$(wp eval "$POLICZ_ZNACZNIK" | sed -n 's/.*ZNACZNIK://p' | tr -d '\r')"
[ "${ZNACZNIK_PRZED:-0}" -ge 1 ] || padnij "nie udało się zostawić wiersza-znacznika — bez niego dowód przetrwania danych jest pusty"

# ---- PODMIANA PLIKÓW BEZ DEAKTYWACJI ----
naglowek "PODMIANA — nowe pliki pod aktywna wtyczka (BEZ hooka aktywacji)"
wyczysc_log
WY="$(wgraj "$PACZKA_NOWA")"
printf '%s\n' "$WY" | sed 's/^/    /'
printf '%s' "$WY" | grep -q 'successfully' || zglos "podmiana plików na nową paczkę nie powiodła się"
wp plugin is-active "$WTYCZKA" > /dev/null 2>&1 \
	&& printf '  wtyczka pozostala AKTYWNA po podmianie (hook aktywacji NIE odpalil)\n' \
	|| zglos "po podmianie plików wtyczka przestała być aktywna — to już nie jest aktualizacja w miejscu"

# Dopiero żądanie HTTP uruchamia kod, który ma dogonić schemat.
KOD="$(zapukaj_na_strone "$STOS_SH")"
[ "$KOD" = "200" ] && potwierdz "strona po podmianie odpowiada HTTP 200" \
	|| zglos "strona po podmianie odpowiada HTTP ${KOD:-brak odpowiedzi}"

# ---- DOWOD 1: wersja wtyczki jest nowa ----
naglowek "DOWOD 1 — wersja wtyczki po podmianie"
WERSJA_PLIKU_PO="$(wp plugin get "$WTYCZKA" --field=version | tr -d '\r')"
printf '    %s → %s\n' "$WERSJA_PLIKU_PRZED" "$WERSJA_PLIKU_PO"
[ "$WERSJA_PLIKU_PO" = "$WERSJA_NOWA" ] && potwierdz "wtyczka raportuje wersje $WERSJA_NOWA" \
	|| zglos "po podmianie wtyczka raportuje [$WERSJA_PLIKU_PO], a paczka niesie $WERSJA_NOWA"

# ---- DOWOD 2: wersja schematu poszla w gore ----
naglowek "DOWOD 2 — wersja schematu w bazie poszla w gore"
SCHEMAT_PO="$(opcja "$OPCJA_SCHEMAT" | tr -d '\r')"
SCHEMAT_KODU="$(grep -m1 -o "define( '[A-Z]*_WERSJA_SCHEMATU', '[0-9]*' )" "wtyczki/$WTYCZKA/$WTYCZKA.php" | grep -o "'[0-9]*' )" | tr -cd '0-9')"
printf '    w bazie: %s → %s ; w nowym kodzie: %s\n' "${SCHEMAT_PRZED:-BRAK}" "${SCHEMAT_PO:-BRAK}" "${SCHEMAT_KODU:-BRAK}"
# ⛔ PRAWDZIWYM warunkiem NIE JEST „numer urosl", tylko „baza zgadza sie z kodem".
# Wydanie dokumentacyjne (u nas 1.5.7 → 1.5.8 i 1.3.0 → 1.3.1) NIE ZMIENIA schematu
# i wtedy „nie drgnela" jest poprawnym wynikiem, nie awaria. Pierwsza wersja tego
# dowodu zakladala, ze kazda aktualizacja podnosi schemat — na zamrozonej parze paczek
# meldowala dwie wady produktu, ktorych nie ma. Awaria to: brak wpisu, rozjazd z kodem
# albo numer, ktory poszedl W DOL.
SCHEMAT_ROSNIE=nie
[ -n "$SCHEMAT_PO" ] && [ -n "$SCHEMAT_PRZED" ] && [ "$(( SCHEMAT_PO ))" -gt "$(( SCHEMAT_PRZED ))" ] && SCHEMAT_ROSNIE=tak
if [ -z "$SCHEMAT_PO" ]; then
	zglos "po aktualizacji w bazie NIE MA wpisu $OPCJA_SCHEMAT"
elif [ -n "$SCHEMAT_KODU" ] && [ "$SCHEMAT_PO" != "$SCHEMAT_KODU" ]; then
	zglos "baza mowi schemat $SCHEMAT_PO, a nowy kod oczekuje $SCHEMAT_KODU"
elif [ "$(( SCHEMAT_PO ))" -lt "$(( ${SCHEMAT_PRZED:-0} ))" ]; then
	zglos "wersja schematu poszla W DOL ($SCHEMAT_PRZED → $SCHEMAT_PO)"
elif [ "$SCHEMAT_ROSNIE" = tak ]; then
	potwierdz "wersja schematu $SCHEMAT_PRZED → $SCHEMAT_PO, zgodna z nowym kodem"
else
	potwierdz "schemat bez zmian ($SCHEMAT_PO) i zgodny z kodem — to wydanie go nie rusza"
fi

# ---- DOWOD 3: schemat przybyl i nic nie znikelo ----
naglowek "DOWOD 3 — nowe tabele i kolumny istnieja, stare NIE ZNIKLY"
SCHEMAT_ODCISK_PO="$ROBOCZY/schemat-po.txt"
odcisk_schematu "$PREFIKS" > "$SCHEMAT_ODCISK_PO"
UBYLO="$(comm -23 "$SCHEMAT_ODCISK_PRZED" "$SCHEMAT_ODCISK_PO")"
PRZYBYLO="$(comm -13 "$SCHEMAT_ODCISK_PRZED" "$SCHEMAT_ODCISK_PO")"
printf '    kolumn przed: %s · po: %s\n' "$(wc -l < "$SCHEMAT_ODCISK_PRZED")" "$(wc -l < "$SCHEMAT_ODCISK_PO")"
if [ -n "$PRZYBYLO" ]; then
	printf '    PRZYBYLO:\n'; printf '%s\n' "$PRZYBYLO" | sed 's/^/      + /'
	potwierdz "aktualizacja dolozyla schemat ($(printf '%s\n' "$PRZYBYLO" | wc -l) pozycji)"
elif [ "$SCHEMAT_ROSNIE" = tak ]; then
	zglos "schemat NIE DRGNAL, choc wersja schematu urosla ($SCHEMAT_PRZED → $SCHEMAT_PO)"
else
	potwierdz "schemat bez zmian — zgodnie z wersja schematu, ktora tez stoi"
fi
if [ -n "$UBYLO" ]; then
	zglos "ze schematu UBYLO — to utrata miejsca na dane klienta:"
	printf '%s\n' "$UBYLO" | sed 's/^/      - /'
else
	potwierdz "nic ze starego schematu nie znikelo"
fi

# ---- DOWOD 4: stare dane przetrwaly ----
naglowek "DOWOD 4 — stare dane przetrwaly aktualizacje"
ZNACZNIK_PO="$(wp eval "$POLICZ_ZNACZNIK" | sed -n 's/.*ZNACZNIK://p' | tr -d '\r')"
printf '    wiersz-znacznik: %s przed → %s po\n' "$ZNACZNIK_PRZED" "${ZNACZNIK_PO:-0}"
if [ "${ZNACZNIK_PO:-0}" = "$ZNACZNIK_PRZED" ]; then
	potwierdz "wlasny wiersz przetrwal aktualizacje co do tresci"
else
	zglos "wiersz-znacznik NIE PRZETRWAL ($ZNACZNIK_PRZED → ${ZNACZNIK_PO:-0}) — aktualizacja zjada dane klienta"
fi

# ---- DOWOD 5: cisza w logu PHP ----
naglowek "DOWOD 5 — ani jeden notice przy aktualizacji"
BLEDY_PHP="$(log_bledow)"
if [ -n "$BLEDY_PHP" ]; then
	zglos "PHP zglosil bledy podczas podmiany plikow i pierwszego zadania:"
	printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'
else
	potwierdz "ani jeden notice, warning, deprecated ani fatal (WP_DEBUG wlaczony)"
fi

# ==============================================================================

naglowek "WYNIK"
if [ "$BLEDY" -eq 0 ]; then
	printf 'AKTUALIZACJA %s %s → %s PRZESZLA WSZYSTKIE PIEC DOWODOW.\n' "$WTYCZKA" "$WERSJA_STARA" "$WERSJA_NOWA"
	exit 0
fi
printf 'AKTUALIZACJA %s %s → %s NIE PRZESZLA — %s dowod(ow) nie wyszlo.\n' \
	"$WTYCZKA" "$WERSJA_STARA" "$WERSJA_NOWA" "$BLEDY"
exit 1
