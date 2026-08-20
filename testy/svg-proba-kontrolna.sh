#!/bin/bash
# Proba kontrolna kontroli ramek tekstow (zasada: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Podklada po jednym celowym bledzie przy KAZDEJ z czterech krawedzi i sprawdza,
# ze testy/svg-pomiar-tekstow.js za kazdym razem PADA. Na koniec sprawdza, ze
# czysty plik przechodzi — inaczej "pada zawsze" tez byloby bezuzyteczne.
#
# Uzycie: testy/svg-proba-kontrolna.sh [plik.svg]
set -u
ZRODLO="${1:-wtyczki/chatbot-live/DIAGRAM-PRZEPLYWU.svg}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
BLEDY=0

# probuj "opis" "sed-podkladajacy-blad" "oczekiwana-krawedz"
probuj() {
  local opis="$1" mutacja="$2" krawedz="$3"
  cp "$ZRODLO" "$TMP/p.svg"
  eval "$mutacja"
  local wyjscie kod
  wyjscie=$(node testy/svg-pomiar-tekstow.js "$TMP/p.svg" 2>&1); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "$krawedz"; then
    printf '  OK   kontrola PADLA na: %-34s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep -A1 'krawedzie:' | head -2 | sed 's/^/         /'
  else
    printf '  BLAD kontrola NIE zlapala: %-31s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy kontrola ramek widzi kazda krawedz ==="
probuj "tekst wychodzacy w DOL"   "sed -i 's/y=\"884\">CHATL_RETENCJA_DNI/y=\"906\">CHATL_RETENCJA_DNI/' \"\$TMP/p.svg\"" "dolna"
probuj "tekst wychodzacy w GORE"  "sed -i 's/x=\"40\" y=\"52\"/x=\"40\" y=\"8\"/' \"\$TMP/p.svg\""                        "gorna"
probuj "tekst wychodzacy w LEWO"  "sed -i 's/class=\"t stopka\" x=\"40\" y=\"776\"/class=\"t stopka\" x=\"2\" y=\"776\"/' \"\$TMP/p.svg\"" "lewa"
probuj "tekst wychodzacy w PRAWO" "sed -i 's/rozpisane w DOKUMENTACJA-TECHNICZNA.md./rozpisane w DOKUMENTACJA-TECHNICZNA.md, rozdzial 5, tabela stalych konfiguracyjnych wtyczki./' \"\$TMP/p.svg\"" "prawa"

echo
echo "=== KONTROLA ODWROTNA: czysty plik ma przechodzic ==="
if node testy/svg-pomiar-tekstow.js "$ZRODLO" > /dev/null 2>&1; then
  echo "  OK   czysty diagram przechodzi (EXIT=0)"
else
  echo "  BLAD czysty diagram NIE przechodzi — kontrola pada zawsze, wiec nic nie znaczy"
  BLEDY=$((BLEDY+1))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "KONTROLA WIDZI WSZYSTKIE CZTERY KRAWEDZIE"
else
  echo "KONTROLA SLEPA NA $BLEDY KRAWEDZI"
fi
exit $((BLEDY > 0))
