#!/bin/bash
# Proba kontrolna bramki `zdarzenia-webhooka.sh` (zasada D14: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Przypadek 4 to DOSLOWNIE klasa, dla ktorej ta bramka powstala: ktos dokłada obsluge
# nowego zdarzenia do kodu i nie rusza instrukcji. Bez tego przypadku reszta proby
# pilnowalaby tylko dzisiejszej literowki.
# Przypadki 6-8 pilnuja czegos innego: zeby bramka NIE UMIALA zameldowac zieleni,
# kiedy niczego nie zmierzyla (pusty wynik to nie czysty stan).
#
# Atrapy budujemy w katalogu tymczasowym POZA repo — bramka bierze sciezke repo
# argumentem, wiec nie trzeba ruszac ani jednego prawdziwego pliku.
#
# Uzycie: bash narzedzia-qa/zdarzenia-webhooka-proba-kontrolna.sh
# Kody wyjscia: 0 = bramka widzi wszystko, co ma widziec · 1 = bramka slepa na cos.
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/zdarzenia-webhooka.sh"
BLEDY=0
ROBOCZY=$(mktemp -d -t zdarzenia-proba-XXXXXX)
trap 'rm -rf "$ROBOCZY"' EXIT INT TERM

# $1 = katalog atrapy, reszta = nazwy zdarzen wstawiane do kodu jako literaly PHP.
zbuduj_kod() {
	local kat="$1"; shift
	mkdir -p "$kat/wtyczki/sklep-agentow/includes"
	{
		printf '<?php\n'
		for N in "$@"; do printf "if ( '%s' === \$typ ) { return true; }\n" "$N"; done
	} > "$kat/wtyczki/sklep-agentow/includes/zakup.php"
}

# $1 = katalog, $2 = nazwa pliku dokumentu, reszta = nazwy wypisywane w dokumencie.
zbuduj_dokument() {
	local kat="$1" plik="$2"; shift 2
	mkdir -p "$kat/$(dirname "$plik")"
	{
		printf 'Zaznacz zdarzenia:\n'
		for N in "$@"; do printf -- '- `%s`\n' "$N"; done
	} > "$kat/$plik"
}

# $1 = opis, $2 = katalog atrapy, $3 = oczekiwany kod wyjscia.
sprawdz() {
	local opis="$1" kat="$2" oczek="$3" kod
	bash "$BRAMKA" "$kat" > /dev/null 2>&1
	kod=$?
	if [ "$kod" -eq "$oczek" ]; then
		printf '  OK   %-62s (kod %s)\n' "$opis" "$kod"
	else
		printf '  PADL %-62s — oczekiwano %s, jest %s\n' "$opis" "$oczek" "$kod"
		BLEDY=$((BLEDY + 1))
	fi
}

TRZY=(checkout.session.completed refund.created charge.refunded)

echo "=== PROBA KONTROLNA: zdarzenia-webhooka.sh ==="

# 1. Stan zgodny MUSI przechodzic — inaczej kazdy nastepny przypadek nic nie znaczy.
A="$ROBOCZY/a"; zbuduj_kod "$A" "${TRZY[@]}"
zbuduj_dokument "$A" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}"
sprawdz "1. lista dokumentu rowna kodowi" "$A" 0

# 2. Dokladnie wada A7-F1: dokument wymienia PODZBIOR.
B="$ROBOCZY/b"; zbuduj_kod "$B" "${TRZY[@]}"
zbuduj_dokument "$B" dokumentacja/INSTRUKCJA-INSTALACJI.md checkout.session.completed
sprawdz "2. dokument gubi zdarzenia obslugiwane w kodzie" "$B" 1

# 3. Druga strona: dokument kaze zaznaczyc cos, czego kod nie obsluguje.
C="$ROBOCZY/c"; zbuduj_kod "$C" "${TRZY[@]}"
zbuduj_dokument "$C" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}" charge.succeeded
sprawdz "3. dokument wymienia zdarzenie spoza kodu" "$C" 1

# 4. ⭐ KLASA, DLA KTOREJ TA BRAMKA POWSTALA: kod dostaje nowe zdarzenie, dokument stoi.
D="$ROBOCZY/d"; zbuduj_kod "$D" "${TRZY[@]}" charge.dispute.created
zbuduj_dokument "$D" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}"
sprawdz "4. NOWE zdarzenie w kodzie, dokument bez zmian" "$D" 1

# 5. Skrot `rodzina.*` w dokumencie technicznym liczy sie jako komplet rodziny.
E="$ROBOCZY/e"; zbuduj_kod "$E" "${TRZY[@]}" charge.dispute.created charge.dispute.closed
zbuduj_dokument "$E" wtyczki/sklep-agentow/DOKUMENTACJA-TECHNICZNA.md "${TRZY[@]}" 'charge.dispute.*'
sprawdz "5. zapis rodzina.* = komplet rodziny" "$E" 0

# 6. Rodzina obslugiwana PREFIKSEM, ale ani jednej konkretnej nazwy w kodzie — bramka nie
#    umie wtedy wypisac, co porownuje, i NIE WOLNO jej zameldowac zgodnosci.
F="$ROBOCZY/f"; zbuduj_kod "$F" "${TRZY[@]}" 'charge.dispute.'
zbuduj_dokument "$F" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}"
sprawdz "6. rodzina bez ani jednej nazwy = blad przyrzadu" "$F" 2

# 7. Zaden dokument nie wymienia zdarzen — to nie „wszystko zgodne", to brak pomiaru.
G="$ROBOCZY/g"; zbuduj_kod "$G" "${TRZY[@]}"
mkdir -p "$G/dokumentacja"; printf 'Instrukcja bez ani jednej nazwy zdarzenia.\n' > "$G/dokumentacja/INSTRUKCJA-INSTALACJI.md"
sprawdz "7. zero dokumentow z lista = blad przyrzadu" "$G" 2

# 8. Brak pliku ze zrodlem prawdy.
H="$ROBOCZY/h"; mkdir -p "$H/dokumentacja"
zbuduj_dokument "$H" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}"
sprawdz "8. brak zakup.php = blad przyrzadu" "$H" 2

# 9. CHANGELOG opisuje WYDANIE, nie dzisiejsza liste — niepelna lista w nim nie psuje wyniku.
I="$ROBOCZY/i"; zbuduj_kod "$I" "${TRZY[@]}"
zbuduj_dokument "$I" dokumentacja/INSTRUKCJA-INSTALACJI.md "${TRZY[@]}"
zbuduj_dokument "$I" wtyczki/sklep-agentow/CHANGELOG.md refund.created
sprawdz "9. niepelna lista w CHANGELOG.md nie liczy sie jako rozjazd" "$I" 0

# 10. Prawdziwe repo — kontrola odwrotna na zywym materiale, nie tylko na atrapach.
sprawdz "10. prawdziwe repo tego projektu jest zgodne" "$KORZEN" 0

echo
echo "BLEDY PROBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
