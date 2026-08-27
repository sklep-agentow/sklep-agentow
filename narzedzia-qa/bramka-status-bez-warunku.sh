#!/bin/bash
# bramka-status-bez-warunku.sh — zapis statusu zamówienia MUSI chronić stan poprzedni.  1.0.0
#
# PO CO (recenzje #198, rundy 2, 4 i 5): TRZY RAZY pod rząd ten sam wzorzec — kod wpisywał
# status zamówienia jako stałą, bez pytania, w jakim stanie zamówienie było. Skutki po kolei:
# wygrany spór meldował „mail wysłany" zamówieniu, do którego mail nie doszedł · cofnięty zwrot
# to samo · „Wyślij ponownie" kasowało znacznik zwrotu i wracało zamówienie do liczby sprzedaży.
# Za każdym razem znajdował to recenzent, po jednym egzemplarzu na rundę. Ta bramka pyta o CAŁĄ
# KLASĘ naraz, żeby czwarty egzemplarz nie miał jak powstać.
#
# ZAKRES: bramka sama ustala, które zmienne w danym pliku wskazują na tabelę zamówień
# (szuka przypisań `$x = … 'sklepag_zamowienia'`) i dopiero potem szuka zapisów przez te
# zmienne. Dzięki temu alias nie jest furtką, a tabela opinii nie wpada w zakres.
#
# ZASADA: każdy `UPDATE <tabela zamówień> … SET … status=…` musi albo mieć w tym samym
# zapytaniu warunek na `status` w `WHERE`, albo nieść w promieniu trzech linii wyżej jawny
# znacznik z powodem:   // status-bez-warunku: <dlaczego to bezpieczne>
#
# Wywołanie: bash narzedzia-qa/bramka-status-bez-warunku.sh [ścieżka-repo]
# Kody wyjścia: 0 = każdy zapis chroniony albo jawnie zwolniony · 1 = znaleziono zapis bez ochrony
#               2 = nie ma czego sprawdzać (brak katalogu wtyczki)
set -u

REPO="${1:-.}"
WTYCZKA="$REPO/wtyczki/sklep-agentow"
[ -d "$WTYCZKA" ] || { echo "STATUS-GUARD: brak $WTYCZKA" >&2; exit 2; }

BLAD=0
ZBADANE=0

while IFS= read -r PLIK; do
	# Które zmienne w TYM pliku wskazują na tabelę zamówień? Bez tego bramka widziała wyłącznie
	# nazwę `$pz` i ten sam zapis pod aliasem (`$t`, `$tab`…) przechodziłby, a licznik nadal
	# meldowałby „każdy chroniony" — fałszywe zielone (recenzja #198 runda 6, W1).
	# Wzorzec celowo WĄSKI: przypisanie prefiksu do nazwy tabeli zamówień i nic innego.
	# Szerszy („cokolwiek do średnika") wciągał przypadkowe nazwy z długich zapytań.
	ZMIENNE=$(grep -oE '\$[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*\$wpdb->prefix[[:space:]]*\.[[:space:]]*.sklepag_zamowienia.' "$PLIK" 2>/dev/null \
		| grep -oE '^\$[A-Za-z_][A-Za-z0-9_]*' | sed 's/^\$//' | sort -u | paste -sd'|')
	[ -n "$ZMIENNE" ] || continue
	WZOR="UPDATE [{]\\\$($ZMIENNE)[}][^\"]*SET[^\"]*[^_]status="
	while IFS=: read -r NR TRESC; do
		ZBADANE=$((ZBADANE + 1))
		# Warunek ochronny w tym samym zapytaniu: WHERE … status …
		if printf '%s' "$TRESC" | grep -qE 'WHERE[^"]*\bstatus\b'; then
			continue
		fi
		# Jawny znacznik zwolnienia w promieniu trzech linii wyżej.
		OD=$(( NR > 3 ? NR - 3 : 1 ))
		if sed -n "${OD},${NR}p" "$PLIK" | grep -q 'status-bez-warunku:'; then
			continue
		fi
		printf 'STATUS-GUARD: %s:%s — zapis statusu bez warunku na stan poprzedni.\n' "${PLIK#$REPO/}" "$NR" >&2
		printf '  %s\n' "$(printf '%s' "$TRESC" | sed 's/^[[:space:]]*//' | cut -c1-140)" >&2
		printf '  Dodaj warunek WHERE … AND status NOT IN (…) albo znacznik: // status-bez-warunku: <powód>\n' >&2
		BLAD=1
	done < <(grep -nE "$WZOR" "$PLIK" 2>/dev/null)
done < <(find "$WTYCZKA" -name '*.php' -type f)

# Bramka, która nie znalazła NICZEGO, nie jest bramką zdaną — jest bramką ślepą. Wtyczka
# sklepu ma zapisy statusu z definicji; zero znaczy, że zmienił się kształt kodu albo wzorzec
# przestał pasować, i wtedy zielone światło byłoby kłamstwem.
if [ "$ZBADANE" -eq 0 ]; then
	echo "STATUS-GUARD: nie znalazłam ANI JEDNEGO zapisu statusu w $WTYCZKA — wzorzec przestał pasować albo zmienił się kształt kodu. To nie jest 'czysto'." >&2
	exit 1
fi
if [ "$BLAD" -eq 0 ]; then
	echo "STATUS-GUARD: $ZBADANE zapisów statusu zamówienia — każdy chroniony albo jawnie zwolniony."
fi
exit $BLAD
