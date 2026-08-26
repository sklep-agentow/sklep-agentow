#!/bin/bash
# L5 — runda B — commit_head 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
# PADA NA OBECNYM KODZIE: audyt/sklep/material/05-reguly-branzy.md cytuje
# audyt/AUDYT-JAKOSC-PODSTRON-25-08.md §4: "Regulamin i Polityka sklepu = SZABLONY
# do uzupełnienia przez właściciela (K8; regulamin musi opisać 14-dniową gwarancję
# satysfakcji — D31)". Strony produktów i mail po zakupie OBIECUJĄ kupującemu
# "14 dni gwarancji satysfakcji: zwrot bez pytań" (front-produkt.php, zakup.php).
# Szablon Regulaminu tworzony przy aktywacji (includes/aktywacja.php,
# sklepag_utworz_strony) NIE wspomina tej gwarancji ani słowem — kupujący czyta
# w mailu/na stronie produktu obietnicę zwrotu, a w jedynym dokumencie prawnym
# sklepu (Regulamin) nie ma o niej mowy.
# Nieniszczący (tylko odczyt pliku źródłowego paczki).
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
PACZKA="${1:-$KORZEN/wtyczki/sklep-agentow}"

# Wyciągamy blok tekstu szablonu Regulaminu z funkcji sklepag_utworz_strony().
REGULAMIN=$(awk '/sklepag_strona_regulamin.*=>.*array/,/\),$/' "$PACZKA/includes/aktywacja.php")

if [ -z "$REGULAMIN" ]; then
	echo "NIE DA SIĘ SPRAWDZIĆ: nie znalazłam bloku sklepag_strona_regulamin w aktywacja.php (zmieniona struktura kodu)."
	exit 2
fi

echo "--- treść szablonu Regulaminu (wyciąg) ---"
echo "$REGULAMIN"
echo "-------------------------------------------"

if echo "$REGULAMIN" | grep -qiE "gwaranc|zwrot"; then
	echo "OK: szablon Regulaminu wspomina gwarancję/zwrot."
	exit 0
fi

echo "PADA: szablon Regulaminu (includes/aktywacja.php) NIE wspomina 14-dniowej gwarancji satysfakcji, mimo że produkt ją obiecuje w mailu i na stronie produktu, i mimo że 05-reguly-branzy.md (źródło: AUDYT-JAKOSC-PODSTRON-25-08.md §4, D31) wprost wymaga, by regulamin ją opisywał."
exit 1
