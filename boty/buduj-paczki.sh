#!/bin/bash
# Buduje paczki produktowe sklepu (zip wtyczki + instrukcja w środku).
#
# ALLOWLIST (recenzja PR #35): do zipa wchodzą WYŁĄCZNIE pliki jawnie
# wymienione poniżej — nowy plik w katalogu wtyczki (env/git/log/bak/cokolwiek)
# ZATRZYMUJE budowę, dopóki nie zostanie świadomie dopisany do listy albo
# usunięty. Ślady wewnętrzne w paczce klienta = klasa 5 ściągi egzaminatora
# (koperta QA u kolegi) — dlatego twarde PADANIE, nie ostrzeżenie.
# Kontrola podwójna: (1) skan katalogu źródłowego przed pakowaniem,
# (2) porównanie faktycznej zawartości zipa z allowlistą po spakowaniu.
#
# Użycie: bash boty/buduj-paczki.sh                 # komplet — ale zamek (niżej) ZATRZYMA bieg na pierwszej paczce, która już leży na origin/main; w praktyce podawaj wtyczkę:
#         bash boty/buduj-paczki.sh sklep-agentow   # tylko wskazane (jedna lub więcej)
# Wybór argumentem doszedł razem z paczką sklepu: zamek wydanych paczek (niżej)
# ODMAWIA przebudowy zipa, który już leży na origin/main — bez wyboru budowa
# sklepu padałaby na zamku CUDZEJ, dawno wydanej paczki bota.
set -euo pipefail

KATALOG_SKRYPTU="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KORZEN="$(dirname "$KATALOG_SKRYPTU")"
WYJSCIE="$KATALOG_SKRYPTU/paczki"

# ⛔ ZAMEK WYDANYCH PACZEK — klasa 10 (24.08: budowa zrzutów przed bumpem dwa razy
# nadpisała wydane 1.5.9/1.3.2 nową treścią pod starym numerem).
# FAIL-CLOSED: gdy nie umiem SPRAWDZIĆ, czy paczka jest wydana — ODMAWIAM, nie buduję.
#   - poza repo gita:      wymaga BUDUJ_POZA_REPO=1 (ustawia piaskownica próby kontrolnej),
#   - origin/main nieznane: ODMOWA,
#   - paczka w origin/main: ODMOWA.
# Świadome obejście każdej odmowy: BUDUJ_NADPISZ_WYDANE=1 (np. naprawa zepsutego zipa).
zamek_wydanej_paczki() {
	local NAZWA; NAZWA=$(basename "$1")
	[ "${BUDUJ_NADPISZ_WYDANE:-0}" = "1" ] && return 0
	if ! git rev-parse --git-dir >/dev/null 2>&1; then
		[ "${BUDUJ_POZA_REPO:-0}" = "1" ] && return 0
		echo "⛔ Nie jestem w repo gita — nie umiem sprawdzić, czy $NAZWA jest wydana. Odmowa (piaskownica: BUDUJ_POZA_REPO=1)." >&2
		exit 1
	fi
	if ! git rev-parse --verify --quiet origin/main >/dev/null; then
		echo "⛔ origin/main nieosiągalny — nie umiem sprawdzić, czy $NAZWA jest wydana. Odmowa." >&2
		exit 1
	fi
	if git cat-file -e "origin/main:boty/paczki/$NAZWA" 2>/dev/null; then
		echo "⛔ $NAZWA jest już WYDANA (origin/main) — podbij wersję wtyczki zamiast nadpisywać." >&2
		exit 1
	fi
}
mkdir -p "$WYJSCIE"

# Pełna, jawna lista plików każdej paczki (ścieżki względem wtyczki/).
ALLOWLIST_CHATBOT=(
	"chatbot-live/chatbot-live.php"
	"chatbot-live/uninstall.php"
	"chatbot-live/LICENSE"
	"chatbot-live/INSTRUKCJA.md"
	"chatbot-live/zrzuty/ustawienia.png"
	"chatbot-live/zrzuty/zgloszenia.png"
	"chatbot-live/zrzuty/statystyki.png"
	"chatbot-live/zrzuty/widget-babelek.png"
	"chatbot-live/zrzuty/widget-okno.png"
	"chatbot-live/zrzuty/widget-formularz.png"
	"chatbot-live/DOKUMENTACJA-TECHNICZNA.md"
	"chatbot-live/DIAGRAM-PRZEPLYWU.svg"
	"chatbot-live/CHANGELOG.md"
	"chatbot-live/readme.txt"
	"chatbot-live/includes/instalator.php"
	"chatbot-live/includes/silnik.php"
	"chatbot-live/includes/ai.php"
	"chatbot-live/includes/rodo.php"
	"chatbot-live/includes/operator.php"
	"chatbot-live/assets/operator.css"
	"chatbot-live/assets/operator.js"
	"chatbot-live/includes/przejecie.php"
	"chatbot-live/includes/front.php"
	"chatbot-live/includes/admin.php"
	"chatbot-live/includes/csv.php"
	"chatbot-live/includes/import.php"
	"chatbot-live/includes/statystyki.php"
	"chatbot-live/includes/wyglad.php"
	"chatbot-live/includes/powitanie.php"
	"chatbot-live/assets/widget.js"
	"chatbot-live/assets/widget.css"
	"chatbot-live/assets/panel-wyglad.js"
)
ALLOWLIST_SEGREGATOR=(
	"segregator-maila/segregator-maila.php"
	"segregator-maila/uninstall.php"
	"segregator-maila/LICENSE"
	"segregator-maila/readme.txt"
	"segregator-maila/CHANGELOG.md"
	"segregator-maila/INSTRUKCJA.md"
	"segregator-maila/zrzuty/skrzynka-imap.png"
	"segregator-maila/zrzuty/reguly.png"
	"segregator-maila/zrzuty/kategorie.png"
	"segregator-maila/zrzuty/probka-demo.png"
	"segregator-maila/zrzuty/przebiegi.png"
	"segregator-maila/zrzuty/ustawienia.png"
	"segregator-maila/DOKUMENTACJA-TECHNICZNA.md"
	"segregator-maila/DIAGRAM-PRZEPLYWU.svg"
	"segregator-maila/includes/instalator.php"
	"segregator-maila/includes/silnik.php"
	"segregator-maila/includes/ai.php"
	"segregator-maila/includes/csv.php"
	"segregator-maila/includes/admin.php"
	"segregator-maila/includes/admin-skrzynka.php"
	"segregator-maila/includes/admin-powitanie.php"
	"segregator-maila/includes/admin-probka.php"
	"segregator-maila/includes/filtry.php"
	"segregator-maila/includes/konfiguracja.php"
	"segregator-maila/includes/class-segm-imap.php"
	"segregator-maila/includes/skrzynka.php"
	"segregator-maila/includes/skrzynka-przebieg.php"
)
# Sklep nie jest produktem na sprzedaż, ale idzie do właściciela tą samą drogą
# (zip wtyczki) i przechodzi tę samą bramkę zamrożenia — dlatego ta sama lista.
ALLOWLIST_SKLEP=(
	"sklep-agentow/sklep-agentow.php"
	"sklep-agentow/uninstall.php"
	"sklep-agentow/LICENSE"
	"sklep-agentow/readme.txt"
	"sklep-agentow/CHANGELOG.md"
	"sklep-agentow/DOKUMENTACJA-TECHNICZNA.md"
	"sklep-agentow/DIAGRAM-PRZEPLYWU.svg"
	"sklep-agentow/INSTRUKCJA.md"
	"sklep-agentow/PONAD-SPECYFIKACJE.md"
	"sklep-agentow/zrzuty/dashboard-wskazniki.png"
	"sklep-agentow/zrzuty/produkt-formularz.png"
	"sklep-agentow/zrzuty/sklep-front.png"
	"sklep-agentow/zrzuty/zamowienie-naglowek.png"
	"sklep-agentow/zrzuty/ustawienia.png"
	"sklep-agentow/includes/aktywacja.php"
	"sklep-agentow/includes/stripe.php"
	"sklep-agentow/includes/zakup.php"
	"sklep-agentow/includes/mail-html.php"
	"sklep-agentow/includes/pobieranie.php"
	"sklep-agentow/includes/logowania.php"
	"sklep-agentow/includes/front-produkt.php"
	"sklep-agentow/includes/front-seo.php"
	"sklep-agentow/includes/opinie.php"
	"sklep-agentow/includes/admin.php"
	"sklep-agentow/includes/admin-dashboard.php"
	"sklep-agentow/includes/admin-produkty.php"
	"sklep-agentow/includes/admin-zamowienie.php"
	"sklep-agentow/includes/admin-opinie.php"
	"sklep-agentow/includes/admin-ustawienia.php"
	"sklep-agentow/includes/admin-dziennik.php"
	"sklep-agentow/includes/class-sklepag-lista-logowan.php"
	"sklep-agentow/includes/class-sklepag-lista-produktow.php"
	"sklep-agentow/includes/class-sklepag-lista-zamowien.php"
	"sklep-agentow/includes/class-sklepag-lista-zdarzen.php"
	"sklep-agentow/includes/class-sklepag-sitemap-produkty.php"
	"sklep-agentow/assets/admin.css"
	"sklep-agentow/assets/front.css"
	"sklep-agentow/assets/front-ruch.js"
	"sklep-agentow/assets/platnosc.js"
	"sklep-agentow/assets/dziekujemy.js"
	"sklep-agentow/assets/okladki/chatbot-live.png"
	"sklep-agentow/assets/okladki/segregator-maila.png"
	"sklep-agentow/assets/okladki/sklep.png"
	"sklep-agentow/assets/produkty/chatbot-live.svg"
	"sklep-agentow/assets/produkty/segregator-maila.svg"
	"sklep-agentow/assets/w-srodku/chatbot-demo-kadr.webm"
	"sklep-agentow/assets/w-srodku/chatbot-demo-plakat.webp"
	"sklep-agentow/assets/w-srodku/chatbot-demo.webm"
	"sklep-agentow/assets/w-srodku/chatbot-live--babelek.webp"
	"sklep-agentow/assets/w-srodku/chatbot-live--statystyki-kadr.webp"
	"sklep-agentow/assets/w-srodku/chatbot-live--statystyki.png"
	"sklep-agentow/assets/w-srodku/chatbot-live--ustawienia-kadr.webp"
	"sklep-agentow/assets/w-srodku/chatbot-live--ustawienia.png"
	"sklep-agentow/assets/w-srodku/chatbot-live--widget-formularz.png"
	"sklep-agentow/assets/w-srodku/chatbot-live--zywy.jpg"
	"sklep-agentow/assets/w-srodku/segregator-demo-kadr.webm"
	"sklep-agentow/assets/w-srodku/segregator-demo-plakat.webp"
	"sklep-agentow/assets/w-srodku/segregator-demo.webm"
	"sklep-agentow/assets/w-srodku/segregator-maila--kategorie-kadr.webp"
	"sklep-agentow/assets/w-srodku/segregator-maila--kategorie.png"
	"sklep-agentow/assets/w-srodku/segregator-maila--probka-demo-kadr.webp"
	"sklep-agentow/assets/w-srodku/segregator-maila--probka-demo.png"
	"sklep-agentow/assets/w-srodku/segregator-maila--reguly-kadr.webp"
	"sklep-agentow/assets/w-srodku/segregator-maila--reguly.png"
	"sklep-agentow/assets/w-srodku/segregator-maila--ustawienia-kadr.webp"
	"sklep-agentow/assets/w-srodku/segregator-maila--ustawienia.png"
	"sklep-agentow/assets/w-srodku/segregator-maila--zywy.jpg"
	"sklep-agentow/assets/w-srodku/segregator-maila--zywy-kadr.webp"
)

zbuduj() {
	local WTYCZKA="$1"; shift
	local ALLOWLIST=( "$@" )
	local ZRODLO="$KORZEN/wtyczki/$WTYCZKA"
	[ -d "$ZRODLO" ] || { echo "BLAD: brak katalogu $ZRODLO" >&2; exit 1; }

	# 1) Każdy plik z allowlisty musi istnieć.
	local SCIEZKA
	for SCIEZKA in "${ALLOWLIST[@]}"; do
		[ -f "$KORZEN/wtyczki/$SCIEZKA" ] || { echo "BLAD: brak pliku z allowlisty: $SCIEZKA" >&2; exit 1; }
	done

	# 2) Skan źródła: plik spoza allowlisty = STOP (świadomie dopisz albo usuń).
	local INTRUZI
	INTRUZI=$(cd "$KORZEN/wtyczki" && find "$WTYCZKA" -type f | sort | comm -23 - <(printf '%s\n' "${ALLOWLIST[@]}" | sort))
	if [ -n "$INTRUZI" ]; then
		echo "BLAD: w katalogu $WTYCZKA sa pliki SPOZA allowlisty — dopisz je swiadomie do boty/buduj-paczki.sh albo usun:" >&2
		echo "$INTRUZI" >&2
		exit 1
	fi

	# 3) Sweep treści plików z listy (ślady wewnętrzne: TODO/notatki QA/adresy dev).
	# Znaczniki w GRANICACH SŁOWA (\b...\b): bez tego "HACK" trafiało w "James
	# Hacker" z załącznika GPL w pliku LICENSE i budowa padała na tekście licencji.
	if ( cd "$KORZEN/wtyczki" && grep -niE '\bTODO\b|\bFIXME\b|\bXXX\b:|\bHACK\b|nie dla klienta|notatka (QA|recenzenck)|localhost:8890|strona_wp|sklep_mailpit' "${ALLOWLIST[@]}" ); then
		echo "BLAD: slady wewnetrzne w plikach $WTYCZKA (wyzej)" >&2
		exit 1
	fi

	# 4) Wersja z nagłówka wtyczki — nazwa zipa musi mówić prawdę.
	local WERSJA
	WERSJA=$(grep -oE 'Version: *[0-9.]+' "$ZRODLO/$WTYCZKA.php" | grep -oE '[0-9.]+')
	[ -n "$WERSJA" ] || { echo "BLAD: brak wersji w naglowku $WTYCZKA" >&2; exit 1; }

	# 5) Zip TYLKO z plików allowlisty (katalog wtyczki w korzeniu — format WP).
	local PACZKA="$WYJSCIE/$WTYCZKA-$WERSJA.zip"
	zamek_wydanej_paczki "$PACZKA"
	rm -f "$PACZKA"
	( cd "$KORZEN/wtyczki" && zip -q "$PACZKA" "${ALLOWLIST[@]}" )

	# 6) Kontrola po fakcie: zawartość zipa == allowlist co do pliku.
	local ROZNICA
	ROZNICA=$(diff <(unzip -Z1 "$PACZKA" | grep -v '/$' | sort) <(printf '%s\n' "${ALLOWLIST[@]}" | sort) || true)
	if [ -n "$ROZNICA" ]; then
		echo "BLAD: zawartosc zipa $WTYCZKA rozni sie od allowlisty:" >&2
		echo "$ROZNICA" >&2
		# Sprzątanie WŁASNEGO, świeżo zbudowanego artefaktu po padzie kontroli —
		# zamek wydanych paczek pilnuje wejścia do budowy, nie tego rm.
		rm -f "$PACZKA"
		exit 1
	fi

	# 7) Raport: pełna lista spakowanych plików + licznik BEZ katalogów.
	local ILE_PLIKOW
	ILE_PLIKOW=$(unzip -Z1 "$PACZKA" | grep -cv '/$')
	echo "OK: $PACZKA ($(du -h "$PACZKA" | cut -f1)) — wersja $WERSJA, plikow: $ILE_PLIKOW"
	unzip -Z1 "$PACZKA" | grep -v '/$' | sort | sed 's/^/    /'
}

# Bez argumentów = komplet (zachowanie sprzed wyboru); z argumentami = tylko wskazane.
if [ "$#" -eq 0 ]; then
	set -- chatbot-live segregator-maila sklep-agentow
fi
for WYBRANA in "$@"; do
	case "$WYBRANA" in
		chatbot-live)     zbuduj chatbot-live "${ALLOWLIST_CHATBOT[@]}" ;;
		segregator-maila) zbuduj segregator-maila "${ALLOWLIST_SEGREGATOR[@]}" ;;
		sklep-agentow)    zbuduj sklep-agentow "${ALLOWLIST_SKLEP[@]}" ;;
		*) echo "BLAD: nieznana wtyczka '$WYBRANA' (znam: chatbot-live, segregator-maila, sklep-agentow)" >&2; exit 1 ;;
	esac
done
