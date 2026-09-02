#!/usr/bin/env bash
# Próba kontrolna GOLDEN-a „kupujący dostaje to, co kupił” (zasada D14) — WERSJA 2.0.1
#
# GOLDEN pada na kodzie sprzed naprawy — to już wiemy. Ten skrypt sprawdza coś więcej:
# czy pada na KAŻDEJ z warstw naprawy z osobna. Naprawa ma trzy części (zapis migawki przy
# sesji, odczyt z migawki przy domknięciu, zapas dla sesji sprzed migracji) i każda z nich
# może zostać cofnięta osobno przy przyszłym refaktorze.
#
# 2.0.0 (I-24): mutant idzie do KOPII wtyczki w katalogu tymczasowym, nigdy do pliku w repo,
# i jest wdrażany na STOS PRÓBNY (probny_wp), nie na wspólny stos warsztatu. Wersja 1.x
# wgrywała kod gałęzi na `strona_wp_wordpress` i zostawiała go tam (cofnięta cudza robota,
# zmierzone 1.09) — dlatego nie wolno jej było wpiąć w bramki.
# ⛔ Licznik błędów NIE MOŻE mieszkać w podpowłoce `$( … )`.
#
# Użycie: bash testy/golden-migawka-proba-kontrolna.sh
# Kody wyjścia: 0 = golden widzi każdą warstwę i przepuszcza czysty kod · 1 = któryś mutant przeszedł
# niezauważony albo czysty kod padł · 2 = przyrząd/środowisko.
set -u
# shellcheck source=testy/lib-golden-stos.sh
. "$(dirname "$0")/lib-golden-stos.sh" || exit 2

ZRODLO=wtyczki/sklep-agentow
PLIK_W_ZRODLE=includes/zakup.php
# ⛔ PUŁAPKA ZANIM KATALOG POWSTANIE — inaczej wyjście przez `padnij` na ścieżce
# PRZEWIDZIANEJ (zajęty stos próbny) zostawia katalog `golden-mutant-*` na dysku.
# Zamek bierze `postaw_stos` w `golden_przygotuj_stos` niżej.
ROBOCZY=""
sprzatnij() { [ -n "${ROBOCZY:-}" ] && rm -rf "$ROBOCZY"; zwin_stos; }
trap 'sprzatnij' EXIT INT TERM
ROBOCZY="$(mktemp -d -t golden-mutant-XXXXXX)"

golden_przygotuj_stos

# $1 = opis, $2 = mutacja (sed na $PLIK w kopii), $3 = fragment asercji, która MA paść.
probuj() {
	local opis="$1" mutacja="$2" asercja="$3" kopia="$ROBOCZY/mutant" PLIK przed po wyjscie kod
	rm -rf "$kopia"; mkdir -p "$kopia"; cp -a "$ZRODLO/." "$kopia/"
	PLIK="$kopia/$PLIK_W_ZRODLE"
	przed=$(md5sum "$PLIK" | cut -d' ' -f1)
	eval "$mutacja"
	po=$(md5sum "$PLIK" | cut -d' ' -f1)
	if [ "$przed" = "$po" ]; then
		zglos "mutacja NIE weszła: $opis"; return
	fi
	if ! php -l "$PLIK" > /dev/null 2>&1; then
		zglos "mutacja rozwaliła składnię: $opis"; return
	fi
	golden_wdroz "$kopia" || { zglos "nie udało się wdrożyć mutanta: $opis"; return; }
	wyjscie=$(golden_odpal); kod=$?
	if [ "$kod" -eq 1 ] && printf '%s' "$wyjscie" | grep -q "BLAD .*$asercja"; then
		potwierdz "GOLDEN PADŁ na: $opis (EXIT=$kod, asercja $asercja)"
	else
		zglos "GOLDEN NIE ZŁAPAŁ: $opis (EXIT=$kod)"
		printf '%s\n' "$wyjscie" | grep -E '^  (BLAD|PRZYRZAD)' | sed 's/^/       /'
	fi
}

naglowek "M1: domknięcie znowu czyta TABELĘ PRODUKTÓW (dosłownie stan sprzed naprawy)"
probuj "warunek migawki zawsze fałszywy" \
	"sed -i \"s/if ( '' !== (string) \\\$nasza->produkt_nazwa ) {/if ( false ) {/\" \"\$PLIK\"" \
	"G1 nazwa w zamowieniu"

naglowek "M2: migawka NIE ZAPISUJE nazwy przy tworzeniu sesji"
probuj "przy INSERT sesji nazwa produktu pusta" \
	"sed -i \"s/^\\t\\t\\t(string) \\\$produkt->nazwa,$/\\t\\t\\t'',/\" \"\$PLIK\"" \
	"G1 nazwa w zamowieniu"

naglowek "M3: migawka zapisuje BIEŻĄCY plik zamiast kupionego"
probuj "ścieżka pliku w migawce podmieniona na pustą" \
	"sed -i \"s/^\\t\\t\\t(string) \\\$produkt->plik_sciezka,$/\\t\\t\\t'',/\" \"\$PLIK\"" \
	"G3 PLIK w zamowieniu"

naglowek "M4: zapas dla sesji sprzed migracji usunięty"
probuj "brak odczytu z tabeli produktów dla starej sesji" \
	"sed -i \"s|\\\$produkt = \\\$wpdb->get_row( \\\$wpdb->prepare( \\\"SELECT \\* FROM {\\\$pp} WHERE id = %d\\\", (int) \\\$nasza->produkt_id ) );|\\\$produkt = null;|\" \"\$PLIK\"" \
	"F1 zamowienie z sesji bez migawki"

naglowek "Czysty kod z drzewa — golden MA PRZEJŚĆ"
golden_wdroz "$ZRODLO" || padnij "nie udało się wdrożyć czystego kodu"
wyjscie=$(golden_odpal); kod=$?
if [ "$kod" -eq 0 ]; then
	potwierdz "czysty kod EXIT=0"
else
	zglos "czysty kod EXIT=$kod"
	printf '%s\n' "$wyjscie" | grep -E '^  (BLAD|PRZYRZAD)' | sed 's/^/       /'
fi

naglowek "PODSUMOWANIE"
echo "KALIBRACJA GOLDEN: sprawdzonych mutantów 4, czysty kod 1, BŁĘDY PRÓBY: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
