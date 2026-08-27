#!/bin/bash
# dowody-do-opisu.sh — URUCHAMIA komplet kontroli i drukuje gotowy blok do opisu PR-a.  1.0.0
#
# PO CO (recenzja PR #198, runda 7, W2): skladalam bloki „dowodowe" w opisie recznymi
# podmianami tekstu — i wyszedl z tego cytat przedstawiajacy stan pliku, ktorego plik NIGDY
# nie mial: sha z dzisiejszego przebiegu sklejony z liczba sprzed dwoch rund. Recenzent
# nazwal to wprost: „cytat nie pochodzi z zadnego przebiegu". Mial racje — to fabrykat,
# nawet jesli niezamierzony.
#
# ZASADA: wyjscia do opisu POWSTAJA z uruchomienia, nie z edycji tekstu. Ten skrypt uruchamia
# kontrole po kolei, przechwytuje ICH WLASNE wyjscie i kody, i sklada z tego blok markdown.
# Nic nie jest przepisywane recznie.
#
# ⛔ Skrypt NIE ocenia wyniku i nie ukrywa porazek — kod wyjscia kazdej kontroli lezy w bloku
#    taki, jaki byl. Kontrola, ktora padla, ma to widac w opisie PR-a.
#
# Wywolanie: bash narzedzia-qa/dowody-do-opisu.sh [> plik.md]
# Kod wyjscia: 0 (zawsze) — to narzedzie raportujace, nie bramka.
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 0

HEAD_SHA="$(git rev-parse --short=7 HEAD 2>/dev/null)"
GALAZ="$(git rev-parse --abbrev-ref HEAD 2>/dev/null)"

printf '<!-- WYGENEROWANE: narzedzia-qa/dowody-do-opisu.sh · HEAD %s · %s -->\n' "$HEAD_SHA" "$GALAZ"
printf '\n## Stan kontroli (wyjscia z uruchomienia, nie przepisane)\n\n'
printf 'Gałąź `%s`, HEAD `%s`.\n\n' "$GALAZ" "$HEAD_SHA"
printf '```\n'

pokaz() { # pokaz <opis> <komenda...>
	local opis="$1"; shift
	local wy kod=0
	wy="$("$@" 2>&1)" || kod=$?
	printf '$ %s\n' "$*"
	# Ostatnie trzy linie wystarczaja: kontrole koncza sie werdyktem, nie preambula.
	printf '%s\n' "$wy" | tail -3 | sed 's/^/  /'
	printf '  EXIT=%s   (%s)\n\n' "$kod" "$opis"
}

pokaz "komplet bramek jakosci"        bash narzedzia-qa/bramki.sh
pokaz "paczka zgodna z repozytorium"  bash narzedzia-qa/paczka-zgodnosc.sh sklep-agentow
pokaz "dokumentacja zgodna z kodem"   bash testy/dokumentacja-zgodnosc-sklep.sh
pokaz "bramka klasy zapisu statusu"   python3 narzedzia-qa/bramka-status-bez-warunku.py .
pokaz "proba kontrolna tej bramki"    bash narzedzia-qa/bramka-status-bez-warunku-proba-kontrolna.sh
pokaz "bramka koncowa K7"             bash narzedzia-qa/k7-koniec.sh
pokaz "wersja wtyczki na stosie"      bash narzedzia-qa/stos.sh wtyczki

printf '```\n\n'

# Artefakt prob kontrolnych czytamy Z PLIKU, w calosci naglowka — zadnych recznych liczb.
printf '### Artefakt prob kontrolnych (naglowek pliku, bez edycji)\n\n```\n'
if [ -f audyt/sklep/PROBY-KONTROLNE.log ]; then
	sed -n '/^WYGENEROWANE-PRZEZ:/,/^#$/p' audyt/sklep/PROBY-KONTROLNE.log
	printf '\n(pozycje z EXIT != 0:)\n'
	grep -E '^[A-Za-z0-9_.-]+\.sh EXIT=' audyt/sklep/PROBY-KONTROLNE.log | grep -v 'EXIT=0' || printf 'brak\n'
else
	printf 'BRAK audyt/sklep/PROBY-KONTROLNE.log\n'
fi
printf '```\n'
exit 0
