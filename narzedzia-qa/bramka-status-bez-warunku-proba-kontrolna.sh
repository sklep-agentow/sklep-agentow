#!/bin/bash
# Próba kontrolna bramki `status-bez-warunku` — kontrola, która nie znajdzie PODŁOŻONEGO
# błędu, jest ślepa. Każdy mutant psuje DOKŁADNIE JEDEN warunek.
#
# Atrapa jest tego samego RODZAJU co produkcja: te same kształty zapytań `UPDATE {$pz} … SET
# … status=…`, ta sama forma znacznika zwolnienia. Atrapa uboższa niż produkcja sprawdzałaby
# inną bramkę niż ta, która realnie biegnie.
#
# Kody wyjścia: 0 = bramka złapała każdego mutanta i przepuściła czysty stan · 1 = przeoczyła
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/bramka-status-bez-warunku.sh"
BAZA="$(mktemp -d)"                     # katalog w /tmp; sprzątany jednym poleceniem na wyjściu
trap 'rm -rf "$BAZA"' EXIT INT TERM
PRZEOCZONE=0
PLIK=wtyczki/sklep-agentow/includes/probny.php

zbuduj() {
	local r="$1"
	mkdir -p "$r/wtyczki/sklep-agentow/includes"
	{
		echo '<?php'
		echo '$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );'
		echo '// status-bez-warunku: $status wyliczony wyzej ze stanu poprzedniego, nie jest stala.'
		echo '$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d", $status, $teraz, $id ) );'
		echo '$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status_przed_sporem=%s WHERE id=%d", $przed, $id ) );'
	} > "$r/$PLIK"
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca>
	local opis="$1" ocz="$2" psuj="$3" r kod=0
	r="$(mktemp -d "$BAZA/przypadek.XXXXXX")"
	zbuduj "$r"
	"$psuj" "$r"
	bash "$BRAMKA" "$r" > /dev/null 2>&1 || kod=$?
	if [ "$kod" -ne "$ocz" ]; then
		printf '  BLAD %-56s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"
		PRZEOCZONE=$((PRZEOCZONE + 1))
	else
		printf '  OK   %-56s (EXIT=%s)\n' "$opis" "$kod"
	fi
}

nic()           { :; }
psuj_warunek()  { sed -i 's/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_znacznik() { sed -i '/status-bez-warunku:/d' "$1/$PLIK"; }
psuj_daleko()   { sed -i 's|// status-bez-warunku: .*|// status-bez-warunku: powod\n//\n//\n//\n//|' "$1/$PLIK"; }

echo "=== PROBA KONTROLNA: bramka status-bez-warunku ==="
sprawdz "czysty stan przechodzi"                            0 nic
sprawdz "zapis STATUSU bez warunku na stan poprzedni"       1 psuj_warunek
sprawdz "znacznik zwolnienia USUNIETY"                      1 psuj_znacznik
sprawdz "znacznik DALEJ niz 3 linie od zapisu"              1 psuj_daleko
echo
if [ "$PRZEOCZONE" -eq 0 ]; then
	echo "BRAMKA ROZPOZNAJE KAZDY PODLOZONY ZAPIS — i przepuszcza czysty stan"
	exit 0
fi
echo "PRZEOCZONYCH: $PRZEOCZONE"
exit 1
