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
# Bledy PODKLADAMY JAKO NOWY ELEMENT tuz przed </svg>, a nie przez celowanie
# w istniejace wspolrzedne. Pierwsza wersja tej proby podmieniala y="884"
# w stopce — po przesunieciu stopki mutacja przestala wchodzic i proba
# zameldowala "kontrola slepa", choc slepa byla SAMA PROBA. Podkladanie nowego
# elementu nie zalezy od tego, jak dzis wyglada diagram.
wstaw() { # wstaw "<text ...>...</text>"
  local el="$1"
  python3 - "$TMP/p.svg" "$el" <<'PY'
import io, sys
p, el = sys.argv[1], sys.argv[2]
s = io.open(p, encoding='utf-8').read()
io.open(p, 'w', encoding='utf-8').write(s.replace('</svg>', '  ' + el + '\n</svg>'))
PY
}

probuj "tekst wychodzacy w DOL"   'wstaw "<text class=\"t stopka\" x=\"600\" y=\"908\">podlozony blad w dol</text>"'   "dolna"
probuj "tekst wychodzacy w GORE"  'wstaw "<text class=\"t stopka\" x=\"600\" y=\"6\">podlozony blad w gore</text>"'     "gorna"
probuj "tekst wychodzacy w LEWO"  'wstaw "<text class=\"t stopka\" x=\"1\" y=\"500\">podlozony blad w lewo</text>"'     "lewa"
probuj "tekst wychodzacy w PRAWO" 'wstaw "<text class=\"t stopka\" x=\"1300\" y=\"500\">podlozony blad wychodzacy daleko w prawo poza kadr</text>"' "prawa"
probuj "kadr niespojny (height != viewBox)" "sed -i 's/height=\"910\" role/height=\"880\" role/' \"\$TMP/p.svg\"" "kadr niespojny"
probuj "plik bez tekstow (kontrola pusta)" "sed -i 's|<text |<nietekst |g' \"\$TMP/p.svg\"" "ZERO elementow"

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
  echo "KONTROLA WIDZI CZTERY KRAWEDZIE, NIESPOJNY KADR I PUSTY POMIAR"
else
  echo "KONTROLA SLEPA NA $BLEDY KRAWEDZI"
fi
exit $((BLEDY > 0))
