#!/usr/bin/env bash
# Test PADAJĄCY: szablon Regulaminu (includes/aktywacja.php) NIE wspomina o 14-dniowej
# gwarancji satysfakcji (D31), mimo że front-produkt.php i mail po zakupie ją obiecują.
# PADA na obecnym kodzie (0.9.13); przejdzie, gdy tekst szablonu Regulaminu wspomni
# "gwarancj" (14 dni / zwrot).
set -euo pipefail
PACZKA="${1:-wtyczki/sklep-agentow}"
PLIK="$PACZKA/includes/aktywacja.php"

if [ ! -f "$PLIK" ]; then
  echo "FAIL: nie znaleziono $PLIK"
  exit 2
fi

# Wyodrębnij literał treści dla klucza sklepag_strona_regulamin (linia z 'Regulamin sklepu (SZABLON)').
LINIA_REGULAMINU=$(grep -n "sklepag_strona_regulamin" "$PLIK" | head -1)
echo "Linia badana: $LINIA_REGULAMINU"

if echo "$LINIA_REGULAMINU" | grep -qi "gwaranc"; then
  echo "PASS: szablon Regulaminu wspomina gwarancję."
  exit 0
else
  echo "FAIL: szablon Regulaminu (klucz sklepag_strona_regulamin) NIE zawiera słowa 'gwarancj*'."
  echo "      Front-produkt.php obiecuje: '14 dni gwarancji satysfakcji... zwracamy pieniądze bez pytań' (linie 327, 487)."
  echo "      Mail (sklepag_zbuduj_mail, zakup.php) też warunkowo dołącza akapit GWARANCJA."
  echo "      Regulamin — jedyne miejsce, gdzie kupujący ma prawo znaleźć WARUNKI zwrotu — milczy."
  exit 1
fi
