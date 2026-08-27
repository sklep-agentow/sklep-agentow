#!/bin/bash
# Proba kontrolna bramki `status-bez-warunku` (3.0.0, tokenizer PHP).
# Kazdy mutant psuje DOKLADNIE JEDEN warunek.
#
# Atrapa jest tego samego RODZAJU co produkcja i zawiera WSZYSTKIE ksztalty, ktore wywracaly
# poprzednie wersje bramki: alias tabeli, nazwe wpisana wprost, zapytanie w dwoch liniach,
# komentarz z cudzyslowem, string APOSTROFOWY z nieparzystym `"` i komentarz KONCOWY z `"`.
# Dwa ostatnie to dokladnie te, przez ktore wersja 2.0.0 przestala widziec dwa realne zapisy
# w ksiegowaniu zwrotow (uwaga KRYTYCZNA, runda 8).
#
# Kody wyjscia: 0 = bramka zlapala kazdego mutanta i przepuscila czysty stan · 1 = przeoczyla
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/bramka-status-bez-warunku.php"
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT INT TERM
PRZEOCZONE=0
PLIK=wtyczki/sklep-agentow/includes/probny.php

zbuduj() {
	local r="$1"
	mkdir -p "$r/wtyczki/sklep-agentow/includes"
	cat > "$r/$PLIK" <<'PHP'
<?php
// Komentarz z cudzyslowem "mail_wyslany" — rozjezdzal parowanie w wersji 1.
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$t  = $wpdb->prefix . 'sklepag_zamowienia';
// String APOSTROFOWY z nieparzystym cudzyslowem — wywracal parowanie w wersji 2.0.0:
$komunikat = 'kliknij „Kup teraz" jeszcze raz';
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) ); // komentarz KONCOWY z " w srodku
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_zamowienia SET status=%s, zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
$wpdb->query( $wpdb->prepare(
	"UPDATE {$pz} SET status=%s, mail_proby=0,
	 zmieniono=%s WHERE id=%d AND status NOT IN (%s)", $s, $teraz, $id, $z ) );
// status-bez-warunku: $status wyliczony wyzej ze stanu poprzedniego, nie jest stala.
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status=%s, zmieniono=%s WHERE id=%d", $status, $teraz, $id ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status_przed_sporem=%s WHERE id=%d", $przed, $id ) );
// Interpolacja BEZ klamer — PHP na to pozwala, wersja 3.0.0 tego nie widziala.
$wpdb->query( $wpdb->prepare( "UPDATE $pz SET status=%s WHERE id=%d AND status NOT IN (%s)", $s, $id, $z ) );
// status-bez-warunku: podrecznikowy update() — swiadomie zwolniony w atrapie.
$wpdb->update( $wpdb->prefix . 'sklepag_zamowienia', array( 'status' => 'mail_wyslany' ), array( 'id' => $id ) );
PHP
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca>
	local opis="$1" ocz="$2" psuj="$3" r kod=0
	r="$(mktemp -d "$BAZA/przypadek.XXXXXX")"
	zbuduj "$r"
	"$psuj" "$r"
	php "$BRAMKA" "$r" > /dev/null 2>&1 || kod=$?
	if [ "$kod" -ne "$ocz" ]; then
		printf '  BLAD %-58s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"
		PRZEOCZONE=$((PRZEOCZONE + 1))
	else
		printf '  OK   %-58s (EXIT=%s)\n' "$opis" "$kod"
	fi
}

nic()             { :; }
psuj_zmienna()    { sed -i '/UPDATE {$pz} SET status=%s, zmieniono=%s WHERE/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_alias()      { sed -i '/UPDATE {$t}/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_wprost()     { sed -i '/UPDATE {$wpdb->prefix}sklepag_zamowienia/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_dwie_linie() { sed -i '/zmieniono=%s WHERE id=%d AND status NOT IN/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_znacznik()   { sed -i '/status-bez-warunku:/d' "$1/$PLIK"; }
psuj_zakres()     { sed -i '/UPDATE/d;/wpdb->update/d' "$1/$PLIK"; }
# Dwie formy, ktorych wersja 3.0.0 nie widziala (uwaga z rundy 9, obie zmierzone sonda).
psuj_bez_klamer() { sed -i '/UPDATE \$pz SET status=/s/ AND status NOT IN (%s)//' "$1/$PLIK"; }
psuj_wpdb_update(){ sed -i '/status-bez-warunku: podrecznikowy/d' "$1/$PLIK"; }

echo "=== PROBA KONTROLNA: bramka status-bez-warunku 3.0.0 (tokenizer PHP) ==="
sprawdz "czysty stan przechodzi"                                0 nic
sprawdz "zapis przez zmienna bez warunku"                       1 psuj_zmienna
sprawdz "ten sam zapis pod ALIASEM tabeli"                      1 psuj_alias
sprawdz "nazwa tabeli WPROST, bez przypisania do zmiennej"      1 psuj_wprost
sprawdz "zapytanie rozbite na DWIE LINIE"                       1 psuj_dwie_linie
sprawdz "znacznik zwolnienia USUNIETY"                          1 psuj_znacznik
sprawdz "ZERO zapisow w zakresie = bramka slepa, nie czysta"    1 psuj_zakres
sprawdz "interpolacja BEZ klamer (UPDATE \$pz SET ...)"          1 psuj_bez_klamer
sprawdz "wpdb->update() ustawiajacy status bez znacznika"       1 psuj_wpdb_update

# ⭐ Kontrola POKRYCIA — sedno uwagi KRYTYCZNEJ z rundy 8. Atrapa ma 5 zapisow statusu
# i wszystkie MUSZA byc widoczne mimo cudzyslowow w stringu apostrofowym i w komentarzu
# koncowym. Wersja 2.0.0 widziala tu mniej i meldowala sukces — dlatego liczba, nie werdykt.
r_pokrycie="$(mktemp -d "$BAZA/pokrycie.XXXXXX")"; zbuduj "$r_pokrycie"
ILE=$(php "$BRAMKA" "$r_pokrycie" 2>/dev/null | grep -oE 'STATUS-GUARD: [0-9]+' | grep -oE '[0-9]+')
if [ "${ILE:-0}" -eq 7 ]; then
	printf '  OK   %-58s (zapisow=%s z 7)\n' "POKRYCIE mimo cudzyslowow w apostrofach i komentarzu" "$ILE"
else
	printf '  BLAD %-58s (zapisow=%s, oczekiwano 7)\n' "POKRYCIE mimo cudzyslowow w apostrofach i komentarzu" "${ILE:-0}"
	PRZEOCZONE=$((PRZEOCZONE + 1))
fi

echo
if [ "$PRZEOCZONE" -eq 0 ]; then
	echo "BRAMKA ROZPOZNAJE KAZDY PODLOZONY ZAPIS — i nie gubi zadnego przez cudzyslowy"
	exit 0
fi
echo "PRZEOCZONYCH: $PRZEOCZONE"
exit 1
