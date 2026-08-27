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
	done < <(grep -nE 'UPDATE \{\$pz\}[^"]*SET[^"]*[^_]status=' "$PLIK" 2>/dev/null)
done < <(find "$WTYCZKA" -name '*.php' -type f)

if [ "$BLAD" -eq 0 ]; then
	echo "STATUS-GUARD: $ZBADANE zapisów statusu zamówienia — każdy chroniony albo jawnie zwolniony."
fi
exit $BLAD
