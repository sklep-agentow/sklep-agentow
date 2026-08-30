#!/bin/bash
# odsylacze-strony-tytulowej.sh — README.md i dla-klienta/PRZECZYTAJ-MNIE.txt odsyłają TYLKO do plików, które istnieją.  WERSJA 1.0.0
#
# PO CO TO POWSTAŁO (30.08, recenzja PR o README dla klienta, KRYTYCZNA+WAŻNA): dwa dokumenty, które klient
# czyta jako PIERWSZE, nie były objęte żadną bramką — README odsyłał do czterech plików, które dostarczał
# dopiero inny PR, i 10 bramek świeciło zielono. `testy/dokumentacja-zgodnosc*.sh` pilnują dokumentów WEWNĄTRZ
# wtyczek; ten skrypt robi to samo piętro wyżej: każda ścieżka w README (obrazki i odsyłacze w nawiasach,
# ścieżki w apostrofach wstecznych wyglądające jak plik repo) musi istnieć w drzewie, a każda nazwa pliku
# obiecana w PRZECZYTAJ-MNIE musi mieć źródło w repo (plik albo pozycja allowlisty paczki).
# Użycie: bash testy/odsylacze-strony-tytulowej.sh     EXIT 0 = wszystkie odsyłacze mają cel · 1 = któryś wisi · 2 = brak wejścia
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$KORZEN" || exit 2
[ -f README.md ] && [ -f dla-klienta/PRZECZYTAJ-MNIE.txt ] || { echo "BRAK README.md albo dla-klienta/PRZECZYTAJ-MNIE.txt" >&2; exit 2; }
ZLE=0; ILE=0
ok()  { ILE=$((ILE+1)); printf '  OK   %s\n' "$1"; }
zle() { ILE=$((ILE+1)); ZLE=$((ZLE+1)); printf '  ⛔ WISI  %s\n' "$1"; }

echo "=== A. README.md: obrazki i odsyłacze w nawiasach → plik istnieje ==="
while read -r p; do
	[ -n "$p" ] || continue
	case "$p" in http*|'#'*) continue ;; esac
	p="${p%%#*}"
	if [ -e "$p" ]; then ok "README → $p"; else zle "README → $p"; fi
done < <(grep -oE '\]\([^)]+\)' README.md | sed -E 's/^\]\(//; s/\)$//' | sort -u)

echo "=== B. README.md: ścieżki repo w apostrofach wstecznych → plik istnieje ==="
while read -r p; do
	[ -n "$p" ] || continue
	if [ -e "$p" ]; then ok "README \`$p\`"; else zle "README \`$p\` (nie ma w drzewie)"; fi
done < <(grep -oE '`(wtyczki|dokumentacja|dla-klienta|testy|narzedzia-qa|audyt|boty)/[^`]+`' README.md | tr -d '`' | grep -vE '[<>*]' | sort -u)

echo "=== C. PRZECZYTAJ-MNIE.txt: obiecane pliki mają źródło ==="
spr_plik() { # <opis> <test>
	if eval "$2" > /dev/null 2>&1; then ok "$1"; else zle "$1"; fi
}
spr_plik "INSTRUKCJA-INSTALACJI.md (do paczki z dokumentacja/)"   "test -f dokumentacja/INSTRUKCJA-INSTALACJI.md"
spr_plik "diagramy/ (render-diagramy.js → dokumentacja/diagramy/)" "ls dokumentacja/diagramy/*.png"
spr_plik "SHA256SUMS.txt (składa pakuj-dla-klienta.sh)"            "grep -q SHA256SUMS.txt narzedzia-qa/pakuj-dla-klienta.sh"
for w in sklep-agentow chatbot-live segregator-maila; do
	spr_plik "$w: INSTRUKCJA.md w repo I na allowliście paczki"      "test -f wtyczki/$w/INSTRUKCJA.md && grep -q \"\\\"$w/INSTRUKCJA.md\\\"\" boty/buduj-paczki.sh"
	for f in readme.txt CHANGELOG.md DOKUMENTACJA-TECHNICZNA.md LICENSE; do
		spr_plik "$w: $f w repo I na allowliście paczki"              "test -f wtyczki/$w/$f && grep -q \"\\\"$w/$f\\\"\" boty/buduj-paczki.sh"
	done
done
spr_plik "sklep-agentow: PONAD-SPECYFIKACJE.md w repo I na allowliście" "test -f wtyczki/sklep-agentow/PONAD-SPECYFIKACJE.md && grep -q '\"sklep-agentow/PONAD-SPECYFIKACJE.md\"' boty/buduj-paczki.sh"

echo
if [ "$ZLE" -eq 0 ]; then echo "ODSYLACZE STRONY TYTULOWEJ: $ILE sprawdzonych, wszystkie mają cel"; exit 0; fi
echo "ODSYLACZE STRONY TYTULOWEJ: $ZLE z $ILE WISI — klient trafi na brak pliku"; exit 1
