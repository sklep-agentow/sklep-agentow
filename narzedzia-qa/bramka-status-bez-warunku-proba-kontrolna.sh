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
		# Bramka sama ustala, ktore zmienne wskazuja na tabele zamowien — atrapa MUSI miec
		# przypisanie, inaczej zakres wychodzi pusty i wszystko „przechodzi" na zerze.
		echo '$pz = $wpdb->prefix . '"'"'sklepag_zamowienia'"'"';'
		echo '$t  = $wpdb->prefix . '"'"'sklepag_zamowienia'"'"';   // ALIAS tej samej tabeli'
		echo '$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );'
		echo '$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );'
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
# ALIAS tej samej tabeli nie może być furtką (recenzja #198 runda 6, W1): bramka patrzyła
# wyłącznie na nazwę `$pz`, więc ten sam zapis pod `$t` przechodził, a licznik nadal mówił
# „każdy chroniony". Ten mutant psuje warunek TYLKO w linii z aliasem.
psuj_alias()    { sed -i '/UPDATE {$t}/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
# Zakres pusty = wszystko „przechodzi" na zerze. Ten mutant zabiera przypisania tabeli:
# bramka nie ma czego pilnować i MUSI to powiedzieć, a nie zameldować sukces.
psuj_zakres()   { sed -i "/= \$wpdb->prefix . 'sklepag_zamowienia'/d" "$1/$PLIK"; }

echo "=== PROBA KONTROLNA: bramka status-bez-warunku ==="
sprawdz "czysty stan przechodzi"                            0 nic
sprawdz "zapis STATUSU bez warunku na stan poprzedni"       1 psuj_warunek
sprawdz "znacznik zwolnienia USUNIETY"                      1 psuj_znacznik
sprawdz "znacznik DALEJ niz 3 linie od zapisu"              1 psuj_daleko
sprawdz "ten sam zapis pod ALIASEM tabeli"                  1 psuj_alias
sprawdz "ZERO zapisow w zakresie = bramka slepa, nie czysta" 1 psuj_zakres

# Zero zapisów w raporcie to nie sukces, tylko ślepota — sprawdzamy to osobno, na liczbie.
r_zakres="$(mktemp -d "$BAZA/zakres.XXXXXX")"; zbuduj "$r_zakres"
ILE_CZYSTY=$(bash "$BRAMKA" "$r_zakres" 2>/dev/null | grep -oE '^STATUS-GUARD: [0-9]+' | grep -oE '[0-9]+')
if [ "${ILE_CZYSTY:-0}" -ge 3 ]; then
	printf '  OK   %-56s (zapisow=%s)\n' "czysty stan RAPORTUJE realne zapisy, nie zero" "$ILE_CZYSTY"
else
	printf '  BLAD %-56s (zapisow=%s — bramka nic nie widzi)\n' "czysty stan RAPORTUJE realne zapisy, nie zero" "${ILE_CZYSTY:-0}"
	PRZEOCZONE=$((PRZEOCZONE + 1))
fi
echo
if [ "$PRZEOCZONE" -eq 0 ]; then
	echo "BRAMKA ROZPOZNAJE KAZDY PODLOZONY ZAPIS — i przepuszcza czysty stan"
	exit 0
fi
echo "PRZEOCZONYCH: $PRZEOCZONE"
exit 1
