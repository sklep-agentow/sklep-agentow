#!/bin/bash
# bramka-recenzji.sh — pilnuje, że recenzja NAPRAWDĘ się odbyła.        WERSJA 1.0.0
#
# PO CO TO POWSTAŁO (zmierzone 20.08, dwa razy tego samego dnia): akcja
# recenzenta potrafi zakończyć się ZIELONO, nie napisawszy ani słowa. Przy
# „niemym zgonie" krok trwa dwadzieścia sekund, artefakt z wyjściem CLI jest
# pusty, a check na liście PR-a wygląda IDENTYCZNIE jak po czystej recenzji.
# Raz doprowadziło to do scalenia PR-a z otwartą uwagą KRYTYCZNĄ.
#
# ⛔ CZEGO TA BRAMKA NIE MOŻE ZROBIĆ: zablokować nas na zawsze. Akcja odmawia
# pracy na KAŻDYM PR-ze zmieniającym plik workflow („Workflow validation
# failed. The workflow file must exist and have identical content to the
# version on the repository's default branch") — to nie awaria, to zasada.
# Taki PR dostaje JAWNY WYJĄTEK, wypisany w logu, nie ciche przepuszczenie.
#
# KRYTERIUM JEST PODWÓJNE i musi być spełnione W CAŁOŚCI:
#   (1) recenzja trwała DŁUŻEJ niż próg (domyślnie 60 s), ORAZ
#   (2) recenzent zostawił pod PR-em komentarz w trakcie tego przebiegu.
# Sam czas nie wystarcza (przebieg może mielić i nic nie napisać), sama
# obecność komentarza też nie (przy `track_progress: true` komentarz-znacznik
# powstaje w pierwszych sekundach, więc widnieje także pod zgonem po 20 s).
#
# WYJĄTEK JEST WĄSKI — wymaga OBU warunków naraz:
#   (a) PR rusza jakikolwiek plik w .github/workflows/, ORAZ
#   (b) CLI recenzenta w ogóle nie wystartowało (brak pliku z wyjściem).
# Sam fakt, że PR dotyka CI, NIE wystarcza. Gdyby wystarczał, prawdziwy niemy
# zgon na PR-ze do CI przechodziłby bokiem — a to jedyne miejsce, gdzie i tak
# nikt nas nie sprawdzi. Warunek (b) jest przy okazji odporny na to, co do 5.09
# było niepewne: czy walidacja workflow patrzy tylko na recenzent.yml, czy na
# wszystkie pliki workflow. ZMIERZONE 5.09 (#290 ruszył quality.yml, recenzent
# zrecenzował normalnie): walidacja patrzy TYLKO na recenzent.yml. Warunek (b)
# zostaje, bo klasyfikacja wychodzi ta sama w obu światach.
#
# Ten sam plik biegnie w CI i na maszynie buildera — źródła danych da się
# podstawić plikami, żeby próba kontrolna nie musiała pukać do GitHuba.
#
# Użycie:
#   RECENZJA_START=<epoch> PR_NUMER=94 REPO=owner/repo TOKEN_GH=... \
#     bash narzedzia-qa/bramka-recenzji.sh
#
# Wejście przez zmienne środowiskowe:
#   RECENZJA_START     (wymagane) epoch w sekundach, znacznik SPRZED kroku recenzji
#   RECENZJA_KONIEC    epoch; domyślnie „teraz"
#   PROG_SEKUND        próg czasu; domyślnie 60
#   PR_NUMER, REPO     numer PR-a i owner/repo — do odpytania o komentarze
#   TOKEN_GH           token do API GitHuba
#   PLIK_WYNIKU_CLI    ścieżka do claude-execution-output.json (sonda „CLI ruszyło")
#   ZMIENIONE_PLIKI    podstawienie listy zmian (jak w bramki.sh)
#   ZRODLO_KOMENTARZY  podstawienie odpowiedzi API: plik JSON w kształcie
#                      /issues/{n}/comments — używane przez próbę kontrolną
#
# Kody wyjścia: 0 = recenzja się odbyła ALBO jej brak jest zgodny z zasadami
#               1 = NIEMY ZGON — check byłby zielony, a recenzji nie ma
#               2 = bramka nie ma z czym pracować (brak wejścia albo narzędzia)
set -u

WERSJA=1.0.0
PROG_SEKUND="${PROG_SEKUND:-60}"
LOGIN_RECENZENTA="claude[bot]"

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2

# --- wejście ------------------------------------------------------------------

if [ -z "${RECENZJA_START:-}" ]; then
	echo "BRAMKA-RECENZJI: brak RECENZJA_START — nie umiem zmierzyć czasu recenzji." >&2
	echo "  To błąd konfiguracji workflow, nie stan repo. Nie zgaduję: kod 2." >&2
	exit 2
fi

RECENZJA_KONIEC="${RECENZJA_KONIEC:-$(date +%s)}"
CZAS=$(( RECENZJA_KONIEC - RECENZJA_START ))

# Zegar, który cofnął się w tył, oznacza, że pomiar jest bezwartościowy.
# Nie udaję wtedy, że wszystko gra.
if [ "$CZAS" -lt 0 ]; then
	echo "BRAMKA-RECENZJI: ujemny czas recenzji ($CZAS s) — znaczniki są nie do użytku." >&2
	exit 2
fi

# --- czy PR rusza plikami workflow --------------------------------------------

lista_zmian() {
	if [ -n "${ZMIENIONE_PLIKI:-}" ]; then
		printf '%s\n' $ZMIENIONE_PLIKI
		return 0
	fi
	local baza=""
	if [ -n "${GITHUB_BASE_REF:-}" ]; then
		baza="origin/$GITHUB_BASE_REF"
	elif git rev-parse --verify -q origin/main > /dev/null; then
		baza="origin/main"
	fi
	[ -n "$baza" ] || return 1
	git diff --name-only "$baza"...HEAD 2> /dev/null
}

rusza_workflow() {
	local zmiany plik
	# Nie da się ustalić listy zmian → zakładamy, że NIE rusza. Wątpliwość
	# na korzyść kontroli: brak wiedzy nie może fabrykować wyjątku.
	zmiany="$(lista_zmian)" || return 1
	while IFS= read -r plik; do
		[ -n "$plik" ] || continue
		case "$plik" in
			.github/workflows/*) return 0 ;;
		esac
	done <<< "$zmiany"
	return 1
}

# --- czy CLI recenzenta w ogóle wystartowało ----------------------------------

# Plik z wyjściem CLI powstaje dopiero, gdy CLI ruszy. Przy odmowie wymiany
# tokenu akcja kończy się PRZED uruchomieniem CLI, więc pliku nie ma wcale —
# zmierzone na PR #96 (krok „Zachowaj pełny wynik CLI" wypisał tam
# „No files were found") i na PR #94, gdzie plik był i artefakt się wgrał.
cli_wystartowalo() {
	[ -n "${PLIK_WYNIKU_CLI:-}" ] || return 1
	[ -s "$PLIK_WYNIKU_CLI" ]
}

# --- komentarze recenzenta pod tym PR-em --------------------------------------

# Zwraca liczbę komentarzy recenzenta powstałych OD startu tego przebiegu.
# Stare komentarze z poprzednich recenzji nie mogą uchodzić za dzisiejszą pracę.
liczba_komentarzy() {
	local json
	if [ -n "${ZRODLO_KOMENTARZY:-}" ]; then
		[ -r "$ZRODLO_KOMENTARZY" ] || { echo "BRAMKA-RECENZJI: nie czytam $ZRODLO_KOMENTARZY" >&2; return 2; }
		json="$(cat "$ZRODLO_KOMENTARZY")"
	else
		command -v curl > /dev/null || { echo "BRAMKA-RECENZJI: brak curl" >&2; return 2; }
		[ -n "${PR_NUMER:-}" ] && [ -n "${REPO:-}" ] && [ -n "${TOKEN_GH:-}" ] || {
			echo "BRAMKA-RECENZJI: brak PR_NUMER / REPO / TOKEN_GH — nie odpytam o komentarze" >&2
			return 2
		}
		json="$(curl -sS --fail-with-body \
			-H "Authorization: Bearer $TOKEN_GH" \
			-H "Accept: application/vnd.github+json" \
			"https://api.github.com/repos/$REPO/issues/$PR_NUMER/comments?per_page=100")" || {
			echo "BRAMKA-RECENZJI: API GitHuba nie odpowiedziało — nie zgaduję wyniku" >&2
			return 2
		}
	fi
	command -v jq > /dev/null || { echo "BRAMKA-RECENZJI: brak jq" >&2; return 2; }
	printf '%s' "$json" | jq -r --arg login "$LOGIN_RECENZENTA" '
		[ .[]
		  | select(.user.login == $login)
		  | .created_at
		] | .[]' 2> /dev/null | {
		local ile=0 data epoka
		while IFS= read -r data; do
			[ -n "$data" ] || continue
			epoka="$(date -u -d "$data" +%s 2> /dev/null)" || continue
			[ "$epoka" -ge "$RECENZJA_START" ] && ile=$(( ile + 1 ))
		done
		echo "$ile"
	}
}

# --- werdykt ------------------------------------------------------------------

printf '=== BRAMKA RECENZJI %s — czy recenzja naprawdę się odbyła ===\n' "$WERSJA"
printf '  czas recenzji: %s s (próg: powyżej %s s)\n' "$CZAS" "$PROG_SEKUND"

if rusza_workflow; then RUSZA=tak; else RUSZA=nie; fi
if cli_wystartowalo; then CLI=tak; else CLI=nie; fi
printf '  PR rusza pliki .github/workflows/: %s\n' "$RUSZA"
printf '  CLI recenzenta wystartowało: %s\n' "$CLI"

if [ "$RUSZA" = tak ] && [ "$CLI" = nie ]; then
	cat <<'WYJASNIENIE'

  WYJATEK ZGODNY Z ZASADAMI — recenzji nie ma i tak ma być.
  Ten PR zmienia plik workflow, więc akcja recenzenta odmówiła wymiany tokenu
  jeszcze przed uruchomieniem CLI („Workflow validation failed. The workflow
  file must exist and have identical content to the version on the repository's
  default branch"). To nie jest niemy zgon — to udokumentowane zachowanie akcji.

  ⛔ CO Z TEGO WYNIKA DLA CZŁOWIEKA: tego PR-a NIKT NIE PRZECZYTAŁ maszynowo.
     Musi go przeczytać koordynatorka, na zimno, w całości.

BRAMKA RECENZJI: PRZEPUSZCZAM (jawny wyjątek)
WYJASNIENIE
	exit 0
fi

KOMENTARZE="$(liczba_komentarzy)" || {
	echo "BRAMKA-RECENZJI: nie ustaliłam liczby komentarzy — nie przepuszczam w ciemno." >&2
	exit 2
}
printf '  komentarze recenzenta od startu przebiegu: %s\n' "$KOMENTARZE"

CZAS_OK=nie
KOMENTARZ_OK=nie
[ "$CZAS" -gt "$PROG_SEKUND" ] && CZAS_OK=tak
[ "$KOMENTARZE" -ge 1 ] && KOMENTARZ_OK=tak

printf '\n=== PODSUMOWANIE ===\n'
printf '  warunek 1 — czas powyżej %s s: %s\n' "$PROG_SEKUND" "$CZAS_OK"
printf '  warunek 2 — jest komentarz recenzenta: %s\n' "$KOMENTARZ_OK"

if [ "$CZAS_OK" = tak ] && [ "$KOMENTARZ_OK" = tak ]; then
	printf '\nRECENZJA SIE ODBYLA — oba warunki spelnione.\n'
	exit 0
fi

cat <<WYJASNIENIE

⛔ NIEMY ZGON RECENZENTA — check byłby zielony, a recenzji NIE MA.
   czas: ${CZAS} s (wymagane: powyżej ${PROG_SEKUND} s)
   komentarze recenzenta w tym przebiegu: ${KOMENTARZE} (wymagane: co najmniej 1)

   Ten PR nie został sprawdzony maszynowo. NIE SCALAJ go na podstawie zielonych
   bramek — one mówią tylko o kodzie, nie o recenzji.
   Gdzie szukać przyczyny: artefakt „claude-execution-output" tego przebiegu
   (przy zgonie bywa pusty) oraz log kroku „Recenzja PR".
WYJASNIENIE
exit 1
