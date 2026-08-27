#!/bin/bash
# Proba kontrolna bramki `status-bez-warunku` (wersja 2.0.0, python).
# Kazdy mutant psuje DOKLADNIE JEDEN warunek.
#
# Atrapa jest tego samego RODZAJU co produkcja: te same ksztalty zapytan, komentarze
# Z CUDZYSLOWAMI (bo one wlasnie rozjezdzaly parser w wersji 1), zapis pod aliasem, zapis
# przez nazwe tabeli wpisana wprost i zapytanie rozbite na dwie linie. Atrapa ubozsza niz
# produkcja sprawdzalaby inna bramke niz ta, ktora realnie biegnie.
#
# Kody wyjscia: 0 = bramka zlapala kazdego mutanta i przepuscila czysty stan · 1 = przeoczyla
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/bramka-status-bez-warunku.py"
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT INT TERM
PRZEOCZONE=0
PLIK=wtyczki/sklep-agentow/includes/probny.php

zbuduj() {
	local r="$1"
	mkdir -p "$r/wtyczki/sklep-agentow/includes"
	cat > "$r/$PLIK" <<'PHP'
<?php
// Komentarz z cudzyslowem "mail_wyslany" — rozjezdzal parowanie stringow w wersji 1.
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$t  = $wpdb->prefix . 'sklepag_zamowienia';
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_zamowienia SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
$wpdb->query( $wpdb->prepare(
	"UPDATE {$pz} SET status=%s, mail_proby=0,
	 zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
// status-bez-warunku: $status wyliczony wyzej ze stanu poprzedniego, nie jest stala.
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d", $status, $teraz, $id ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status_przed_sporem=%s WHERE id=%d", $przed, $id ) );
PHP
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca>
	local opis="$1" ocz="$2" psuj="$3" r kod=0
	r="$(mktemp -d "$BAZA/przypadek.XXXXXX")"
	zbuduj "$r"
	"$psuj" "$r"
	python3 "$BRAMKA" "$r" > /dev/null 2>&1 || kod=$?
	if [ "$kod" -ne "$ocz" ]; then
		printf '  BLAD %-56s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"
		PRZEOCZONE=$((PRZEOCZONE + 1))
	else
		printf '  OK   %-56s (EXIT=%s)\n' "$opis" "$kod"
	fi
}

nic()             { :; }
psuj_zmienna()    { sed -i '/UPDATE {$pz} SET status=%s, zmieniono=%s WHERE/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_alias()      { sed -i '/UPDATE {$t}/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_wprost()     { sed -i '/UPDATE {$wpdb->prefix}sklepag_zamowienia/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_dwie_linie() { sed -i '/zmieniono=%s WHERE id=%d AND status NOT IN/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_znacznik()   { sed -i '/status-bez-warunku:/d' "$1/$PLIK"; }
psuj_zakres()     { sed -i '/UPDATE/d' "$1/$PLIK"; }

echo "=== PROBA KONTROLNA: bramka status-bez-warunku 2.0.0 ==="
sprawdz "czysty stan przechodzi"                              0 nic
sprawdz "zapis przez zmienna bez warunku"                     1 psuj_zmienna
sprawdz "ten sam zapis pod ALIASEM tabeli"                    1 psuj_alias
sprawdz "nazwa tabeli WPROST, bez przypisania do zmiennej"    1 psuj_wprost
sprawdz "zapytanie rozbite na DWIE LINIE"                     1 psuj_dwie_linie
sprawdz "znacznik zwolnienia USUNIETY"                        1 psuj_znacznik
sprawdz "ZERO zapisow w zakresie = bramka slepa, nie czysta"  1 psuj_zakres

# Zero w raporcie to nie sukces, tylko slepota — sprawdzamy osobno, na liczbie.
r_zakres="$(mktemp -d "$BAZA/zakres.XXXXXX")"; zbuduj "$r_zakres"
ILE=$(python3 "$BRAMKA" "$r_zakres" 2>/dev/null | grep -oE 'STATUS-GUARD: [0-9]+' | grep -oE '[0-9]+')
if [ "${ILE:-0}" -ge 4 ]; then
	printf '  OK   %-56s (zapisow=%s)\n' "czysty stan RAPORTUJE realne zapisy, nie zero" "$ILE"
else
	printf '  BLAD %-56s (zapisow=%s — nie widzi wszystkich ksztaltow)\n' "czysty stan RAPORTUJE realne zapisy, nie zero" "${ILE:-0}"
	PRZEOCZONE=$((PRZEOCZONE + 1))
fi

echo
if [ "$PRZEOCZONE" -eq 0 ]; then
	echo "BRAMKA ROZPOZNAJE KAZDY PODLOZONY ZAPIS — i przepuszcza czysty stan"
	exit 0
fi
echo "PRZEOCZONYCH: $PRZEOCZONE"
exit 1
