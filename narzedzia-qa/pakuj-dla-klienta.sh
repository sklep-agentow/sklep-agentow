#!/bin/bash
# pakuj-dla-klienta.sh — ZBIORCZA PACZKA DLA KLIENTA: 3 zipy wtyczek + PRZECZYTAJ-MNIE + instrukcja instalacji
# + diagramy PNG + SHA256SUMS.txt, złożone JEDNYM poleceniem.  WERSJA 1.1.0
#
# PO CO TO POWSTAŁO (30.08): zamówienie mówi „zipy 3 wtyczek + sumy kontrolne”, a do dziś każdy zip leżał osobno,
# sum nie było, a co składa się na wydanie, wiedziała tylko głowa. Czego nie da się odtworzyć jednym poleceniem,
# tego nie ma — to samo, co robi `boty/buduj-paczki.sh` dla pojedynczej wtyczki, tylko piętro wyżej.
#
# Wersja KAŻDEJ wtyczki pochodzi z nagłówka jej pliku głównego (nie z argumentu) i zip o tej wersji MUSI istnieć
# w boty/paczki/ — skrypt nie buduje zipów (od tego jest buduj-paczki.sh z zamkiem wydanych paczek), tylko je składa.
#
# 1.1.0 (recenzja PR o README, D3–D5): samokontrola OTWIERA zipy wtyczek (unzip -t, nagłówek Version == wersja z nazwy,
# INSTRUKCJA.md w każdym, PONAD-SPECYFIKACJE.md w sklepie), kontrola śladów wymaga istnienia plików, oba katalogi
# tymczasowe sprzątane trapem także przy porażce.
#
# Użycie: bash narzedzia-qa/pakuj-dla-klienta.sh            → wydanie/sklep-agentow-KLIENT-<wersja sklepu>.zip
#   EXIT 0 = paczka złożona i SAMOKONTROLA przeszła (komplet plików, sumy zgadzają się po rozpakowaniu,
#            brak śladów wewnętrznych w dokumentach) · 1 = samokontrola padła · 2 = brakuje wejścia
set -u
KORZEN="$(cd "$(dirname "$0")/.." && pwd)"; cd "$KORZEN" || exit 2
WYJ="$KORZEN/wydanie"
wersja_z_naglowka() { sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' "wtyczki/$1/$1.php" | head -1; }

ROB="$(mktemp -d)"; KONTROLA="$(mktemp -d)"; trap 'rm -rf "$ROB" "$KONTROLA"' EXIT
mkdir -p "$ROB/paczka/diagramy" "$WYJ"
BRAK=0
for w in sklep-agentow chatbot-live segregator-maila; do
	v="$(wersja_z_naglowka "$w")"
	zip="boty/paczki/$w-$v.zip"
	if [ -z "$v" ] || [ ! -f "$zip" ]; then echo "PAKUJ: brak zipa $zip (wersja z nagłówka: '${v:-?}') — najpierw: bash boty/buduj-paczki.sh $w" >&2; BRAK=1; continue; fi
	cp "$zip" "$ROB/paczka/"
	echo "  + $zip"
done
[ "$BRAK" -eq 0 ] || exit 2
for f in dla-klienta/PRZECZYTAJ-MNIE.txt dokumentacja/INSTRUKCJA-INSTALACJI.md; do
	[ -f "$f" ] || { echo "PAKUJ: brak $f" >&2; exit 2; }
	cp "$f" "$ROB/paczka/"; echo "  + $f"
done
if ! ls dokumentacja/diagramy/*.png > /dev/null 2>&1; then echo "PAKUJ: brak dokumentacja/diagramy/*.png — najpierw: node dokumentacja/render-diagramy.js" >&2; exit 2; fi
cp dokumentacja/diagramy/*.png "$ROB/paczka/diagramy/"; echo "  + dokumentacja/diagramy/ ($(ls dokumentacja/diagramy/*.png | wc -l) PNG)"

WER="$(wersja_z_naglowka sklep-agentow)"
NAZWA="sklep-agentow-KLIENT-$WER"
( cd "$ROB/paczka" && find . -type f ! -name SHA256SUMS.txt | sed 's#^\./##' | LC_ALL=C sort | xargs sha256sum > SHA256SUMS.txt )
( cd "$ROB" && mv paczka "$NAZWA" && rm -f "$WYJ/$NAZWA.zip" && zip -qr "$WYJ/$NAZWA.zip" "$NAZWA" )
echo "PACZKA: wydanie/$NAZWA.zip ($(du -h "$WYJ/$NAZWA.zip" | cut -f1))"

# --- SAMOKONTROLA na ROZPAKOWANEJ paczce (nie na katalogu roboczym) --------------------------------
ZLE=0
unzip -q "$WYJ/$NAZWA.zip" -d "$KONTROLA" || { echo "PAKUJ: nie umiem rozpakować wydanie/$NAZWA.zip — samokontrola niemożliwa" >&2; exit 1; }
cd "$KONTROLA/$NAZWA" || { echo "PAKUJ: w zipie nie ma katalogu $NAZWA" >&2; exit 1; }
spr() { if eval "$2" > /dev/null 2>&1; then echo "  OK   $1"; else echo "  ⛔ NIE WYSZŁO  $1"; ZLE=$((ZLE+1)); fi; }
spr "3 zipy wtyczek w paczce"                      "[ \$(ls *.zip | wc -l) -eq 3 ]"
spr "PRZECZYTAJ-MNIE.txt i INSTRUKCJA-INSTALACJI.md" "[ -f PRZECZYTAJ-MNIE.txt ] && [ -f INSTRUKCJA-INSTALACJI.md ]"
spr "diagramy: 3 PNG"                              "[ \$(ls diagramy/*.png | wc -l) -eq 3 ]"
spr "SHA256SUMS.txt zgadza się po rozpakowaniu"    "sha256sum -c --quiet SHA256SUMS.txt"
spr "sumy obejmują KAŻDY plik paczki"              "[ \$(find . -type f ! -name SHA256SUMS.txt | wc -l) -eq \$(wc -l < SHA256SUMS.txt) ]"
spr "wersja sklepu w nazwie paczki == nagłówek"    "[ -f sklep-agentow-$WER.zip ]"
# ślady wewnętrzne w DOKUMENTACH paczki (zipy wtyczek sprawdza osobno paczka-zgodnosc.sh)
spr "brak śladów wewnętrznych w dokumentach paczki" "[ -f PRZECZYTAJ-MNIE.txt ] && [ -f INSTRUKCJA-INSTALACJI.md ] && ! grep -lE 'localhost:[0-9]+|/root/|scratchpad|PLAN-PROJEKTU|CO-WISI|SCIAGA|SECURITY\.md|CONTRIBUTING|RECENZENT-BRIEFING' PRZECZYTAJ-MNIE.txt INSTRUKCJA-INSTALACJI.md"
# zipy wtyczek: nie tylko nazwa — treść (klasa 10: zip o dobrej nazwie zamrożony na starej treści)
for z in *.zip; do
	w="${z%-*}"; v="${z##*-}"; v="${v%.zip}"
	spr "$z: archiwum całe (unzip -t)"                   "unzip -tq '$z'"
	spr "$z: nagłówek Version w zipie == $v"             "[ \"\$(unzip -p '$z' '$w/$w.php' | sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' | head -1)\" = '$v' ]"
	spr "$z: INSTRUKCJA.md w środku"                     "unzip -l '$z' | grep -q ' $w/INSTRUKCJA.md\$'"
done
spr "sklep: PONAD-SPECYFIKACJE.md w zipie sklepu"        "unzip -l sklep-agentow-$WER.zip | grep -q ' sklep-agentow/PONAD-SPECYFIKACJE.md\$'"
if [ "$ZLE" -eq 0 ]; then echo "PAKUJ: SAMOKONTROLA PRZESZŁA ($(( 7 + 3 * 3 + 1 )) kontroli) — wydanie/$NAZWA.zip gotowe do Releases"; exit 0; fi
echo "PAKUJ: SAMOKONTROLA PADŁA ($ZLE) — paczki NIE wydawać"; exit 1
