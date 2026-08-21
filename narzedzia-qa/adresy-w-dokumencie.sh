#!/bin/bash
# adresy-w-dokumencie.sh — czy dokument nie kłamie o WŁASNYCH odsyłaczach.  WERSJA 1.0.0
#
# PO CO TO POWSTAŁO: 21.08 trzy razy z rzędu dokument opisujący jakąś klasę błędu
# SAM ją popełniał — m.in. przegląd „co robi kod, czego nie ma w instrukcji" miał
# nieścisłe adresy `plik:linia`, czyli dokładnie to, co tropił. Pisanie O KLASIE
# nie chroni przed KLASĄ: uwaga siedzi na treści, nie na formie. Jedyne, co działa,
# to kontrola MECHANICZNA po napisaniu — ten skrypt.
#
# Co sprawdza dla każdego odsyłacza `sciezka/plik.rozszerzenie:linia[,linia…]`:
#   1. plik istnieje,
#   2. linia istnieje (plik nie jest krótszy),
#   3. ⭐ TREŚĆ SIĘ ZGADZA: jeśli w tym samym wierszu dokumentu stoi nazwa w grawisach
#      (stała, funkcja, hook, slug), to MUSI wystąpić w którejś z cytowanych linii.
#      Bez tego kroku sprawdzalibyśmy tylko, czy plik ma dość linii — a nie, czy
#      wskazują na to, o czym mowa.
#
# Użycie: bash narzedzia-qa/adresy-w-dokumencie.sh <plik.md> [<plik.md> …]
# Kody wyjścia: 0 = każdy odsyłacz prowadzi tam, gdzie obiecuje
#               1 = choć jeden nie prowadzi
#               2 = brak pliku wejściowego
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
[ $# -ge 1 ] || { echo "Użycie: bash narzedzia-qa/adresy-w-dokumencie.sh <plik.md>" >&2; exit 2; }

BLEDY=0
SPRAWDZONE=0
POMINIETE=0
BEZ_NAZWY=0

for DOK in "$@"; do
	[ -f "$DOK" ] || { echo "ADRESY: nie ma pliku [$DOK]" >&2; exit 2; }
	# Tryb SUROWY włącza sam dokument. Rozdział jest po to, żeby narzędzie dało się
	# puścić na CAŁYM repozytorium bez zalewu czerwonego z dokumentów, które nigdy
	# nie obiecywały potwierdzalnych adresów — narzędzie, które krzyczy na wszystko,
	# zostaje wyłączone po tygodniu.
	SUROWO=nie
	grep -q '<!-- adresy: potwierdzalne -->' "$DOK" && SUROWO=tak
	printf '=== %s (tryb: %s) ===\n' "$DOK" "$( [ "$SUROWO" = tak ] && echo SUROWY || echo zwykly )"
	while IFS= read -r wiersz; do
		# Wszystkie odsyłacze z tego wiersza dokumentu.
		odsylacze="$(printf '%s' "$wiersz" | grep -oE '`[a-zA-Z0-9_./-]+\.(php|js|css|txt|md|sh):[0-9]+(,[0-9]+)*`' | tr -d '`')"
		[ -n "$odsylacze" ] || continue
		# Nazwy w grawisach, które NIE są odsyłaczami — kandydaci na treść do potwierdzenia.
		# Nazwy bierzemy ze WSZYSTKICH fragmentów w grawisach, nie tylko z tych, które
		# same w sobie są identyfikatorem: `'ai_limit_dzienny' => 100` też ma nazwę,
		# której trzeba szukać w kodzie. Odsiewamy ścieżki plików.
		nazwy="$(printf '%s' "$wiersz" | grep -oE '`[^`]+`' | tr -d '`' | grep -vE '^[a-zA-Z0-9_./-]+\.(php|js|css|txt|md|sh)(:[0-9,]+)?$' \
			| grep -oE '[A-Za-z_][A-Za-z0-9_]{3,}' | sort -u || true)"
		while IFS= read -r odsylacz; do
			[ -n "$odsylacz" ] || continue
			plik="${odsylacz%%:*}"
			linie="${odsylacz#*:}"
			SPRAWDZONE=$(( SPRAWDZONE + 1 ))
			# Odsyłacz do CUDZEGO repozytorium (poprzednie zlecenia) nie jest błędem
			# tego dokumentu — nie mamy jak go sprawdzić i nie udajemy, że mamy.
			korzen_sciezki="${plik%%/*}"
			if [ ! -f "$plik" ] && [ ! -e "$korzen_sciezki" ]; then
				printf '  ➖ POZA REPO     %s (nie sprawdzam)\n' "$odsylacz"
				SPRAWDZONE=$(( SPRAWDZONE - 1 )); POMINIETE=$(( POMINIETE + 1 )); continue
			fi
			if [ ! -f "$plik" ]; then
				printf '  ⛔ NIE MA PLIKU  %s\n' "$odsylacz"; BLEDY=$(( BLEDY + 1 )); continue
			fi
			ile="$(wc -l < "$plik")"
			tresc=""
			zla_linia=nie
			for nr in ${linie//,/ }; do
				if [ "$nr" -gt "$ile" ]; then
					printf '  ⛔ ZA KROTKI    %s — plik ma %s linii\n' "$odsylacz" "$ile"; zla_linia=tak; continue
				fi
				tresc="$tresc$(sed -n "${nr}p" "$plik")"
			done
			if [ "$zla_linia" = tak ]; then BLEDY=$(( BLEDY + 1 )); continue; fi
			# Do treści cytowanych linii dokładamy nazwę funkcji, WEWNĄTRZ której one
			# stoją. Bez tego wiersz mówiący „gałąź AI endpointu `chatl_ajax_pytanie`"
			# i wskazujący linię z `chatl_zapisz_wymiane(...)` wychodziłby na rozjazd,
			# choć adres jest poprawny — a fałszywy alarm w takim narzędziu kończy się
			# jego wyłączeniem.
			otoczenie=""
			for nr in ${linie//,/ }; do
				otoczenie="$otoczenie$(awk -v do_linii="$nr" '/^[a-zA-Z ]*function [a-zA-Z_]+/ { ostatnia = $0 } NR == do_linii { print ostatnia; exit }' "$plik")"
			done
			# ⛔ ADRES BEZ NAZWY DO POTWIERDZENIA TO ADRES NIESPRAWDZALNY. Bez tego
			# warunku skrypt sprawdzałby tylko, czy plik ma dość linii — i przepuszczał
			# przestawiony numer, byle mieścił się w pliku. Złapane własną próbą:
			# podmiana `ai.php:47` na `:470` przechodziła na zielono, bo wiersz tabeli
			# nie nazywał stałej. Wiersz z adresem MA powiedzieć, czego szukać.
			if [ -z "$nazwy" ]; then
				if [ "$SUROWO" = tak ]; then
					printf '  ⛔ NIESPRAWDZALNY %s — wiersz nie podaje nazwy (stałej, funkcji, hooka) do potwierdzenia\n' "$odsylacz"
					BLEDY=$(( BLEDY + 1 ))
				else
					printf '  ➖ BEZ NAZWY     %s (istnieje, ale nie ma czego potwierdzić)\n' "$odsylacz"
					BEZ_NAZWY=$(( BEZ_NAZWY + 1 ))
				fi
				continue
			fi
			# W dokumencie o LICZBACH potwierdzeniem nie jest nazwa stałej (w instrukcji
			# jej nie ma), tylko sama LICZBA z wiersza. Porównujemy po cyfrach, bo
			# „200 000" w dokumencie i „200 000" w instrukcji mogą mieć inną spację.
			liczby="$(printf '%s' "$wiersz" | grep -oE '[0-9][0-9 ]*[0-9]|[0-9]' | tr -d ' ' | sort -u)"
			tresc_cyfry="$(printf '%s' "$tresc" | tr -cd '0-9')"
			potwierdzone=""
			if [ -n "$liczby" ]; then
				while IFS= read -r l; do
					[ -n "$l" ] && [ ${#l} -ge 2 ] || continue
					case "$tresc_cyfry" in *"$l"*) potwierdzone="liczba $l"; break ;; esac
				done <<< "$liczby"
			fi
			# Liczba JEDNOCYFROWA sama nic nie potwierdza (cyfra trafi się wszędzie),
			# więc przy niej wymagamy jeszcze SŁOWA z wiersza — „6" plus „wymian",
			# „5" plus „minut". Dwa sygnały naraz, zamiast jednego bezwartościowego.
			if [ -z "$potwierdzone" ]; then
				slowa="$(printf '%s' "$wiersz" | grep -oE '[A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż]{4,}' | tr 'A-ZĄĆĘŁŃÓŚŹŻ' 'a-ząćęłńóśźż' | sort -u)"
				tresc_male="$(printf '%s' "$tresc" | tr 'A-ZĄĆĘŁŃÓŚŹŻ' 'a-ząćęłńóśźż')"
				while IFS= read -r l; do
					[ -n "$l" ] && [ ${#l} -eq 1 ] || continue
					case "$tresc_cyfry" in *"$l"*) ;; *) continue ;; esac
					while IFS= read -r w; do
						[ -n "$w" ] || continue
						case "$tresc_male" in *"$w"*) potwierdzone="liczba $l + slowo $w"; break 2 ;; esac
					done <<< "$slowa"
				done <<< "$liczby"
			fi
			if [ -z "$potwierdzone" ] && [ -n "$nazwy" ]; then
				while IFS= read -r n; do
					[ -n "$n" ] || continue
					case "$tresc$otoczenie" in *"$n"*) potwierdzone="$n"; break ;; esac
				done <<< "$nazwy"
				if [ -z "$potwierdzone" ]; then
					printf '  ⛔ INNA TRESC   %s\n' "$odsylacz"
					printf '       dokument obiecuje: %s\n' "$(printf '%s' "$nazwy" | tr '\n' ' ')"
					printf '       a w linii stoi:    %.100s\n' "$(printf '%s' "$tresc" | sed 's/^[[:space:]]*//')"
					BLEDY=$(( BLEDY + 1 )); continue
				fi
			fi
			printf '  ✅ %-52s %.70s\n' "$odsylacz" "$(printf '%s' "$tresc" | sed 's/^[[:space:]]*//')"
		done <<< "$odsylacze"
	done < "$DOK"
done

printf '\nSPRAWDZONYCH ODSYLACZY: %s · BLEDNYCH: %s · BEZ NAZWY: %s · POZA REPO: %s\n' "$SPRAWDZONE" "$BLEDY" "$BEZ_NAZWY" "$POMINIETE"
[ "$BLEDY" -eq 0 ] || { echo "DOKUMENT KLAMIE O WLASNYCH ODSYLACZACH — popraw, zanim go oddasz" >&2; exit 1; }
echo "KAZDY ODSYLACZ PROWADZI TAM, GDZIE OBIECUJE"
exit 0
