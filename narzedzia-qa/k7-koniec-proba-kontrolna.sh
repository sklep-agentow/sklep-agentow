#!/bin/bash
# k7-koniec-proba-kontrolna.sh — dowód, że k7-koniec.sh nie jest ozdobą.                 WERSJA 1.0.0
#
# Buduje w /tmp PEŁNĄ, ZIELONĄ atrapę stanu końca K7 (mapa, rejestr, przelot, log prób,
# paczka + sha, repo git z tagiem i gałęzią mutantów, raport JSON) i sprawdza, że skrypt
# mówi GO. Potem psuje KAŻDY z ośmiu warunków osobno i sprawdza, że skrypt mówi NO-GO
# i nazywa ten właśnie warunek. Kontrola odwrotna jest pierwsza — inaczej „bramka, która
# wszystko odrzuca” też by zdała.
# Kody: 0 = bramka rozpoznaje każdy podłożony brak i przepuszcza czysty stan · 1 = pomyliła się
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BAZA="$(mktemp -d)"; trap 'rm -rf "$BAZA"' EXIT
BLEDY=0

zbuduj_czysty() {
	local r="$1"; rm -rf "$r"; mkdir -p "$r/narzedzia-qa" "$r/audyt/sklep/material" "$r/audyt/sklep/raporty/A" "$r/boty/paczki" "$r/testy"
	cp "$KORZEN/narzedzia-qa/k7-koniec.sh" "$KORZEN/narzedzia-qa/mapa-licznik.sh" "$r/narzedzia-qa/"
	# paczka + sha
	printf 'atrapa' > "$r/boty/paczki/plik.txt"; ( cd "$r/boty/paczki" && zip -q sklep-agentow-9.9.9.zip plik.txt )
	# repo git z tagiem i gałęzią mutantów
	( cd "$r" && git init -q && git config user.email a@b.c && git config user.name a && git add -A >/dev/null && git commit -qm baza && git tag k7-zamrozenie \
	  && git checkout -qb audyt/mutanty-sklep && printf '# rozliczenie\n| audytor | mutant | znalazl | falszywy | werdykt | proby |\n|---|---|---|---|---|---|\n| L1 | M1 | tak | nie | PASS | 1 |\n| L2 | M2 | tak | nie | PASS | 1 |\n| L3 | M3 | tak | nie | PASS | 2 |\n| L4 | M5 | tak | nie | PASS | 1 |\n| L5 | M6 | tak | nie | PASS | 1 |\n| L | M7 | tak | nie | PASS | 1 |\n' > audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md \
	  && git add -A >/dev/null && git commit -qm mutanty && git checkout -q - )
	local sha; sha=$(git -C "$r" rev-parse --short=7 k7-zamrozenie)
	printf 'PACZKA: boty/paczki/sklep-agentow-9.9.9.zip\nSHA256: %s\nCOMMIT: %s\n' "$(sha256sum "$r/boty/paczki/sklep-agentow-9.9.9.zip" | cut -d' ' -f1)" "$sha" > "$r/audyt/sklep/ZAMROZENIE.md"
	# mapa: 2 cele zamknięte, zamrożona
	printf '# mapa\n- ✅ L1-01 · cel · [A · %s]\n- 🐞 L2-01 · cel · [A · %s]\n\nCELE: 0 · ✅ 0 · 🐞 0 · ⚠️ 0 · ⛔ 0 · ⬜ 0\n' "$sha" "$sha" > "$r/audyt/sklep/material/06-mapa-polowania.md"
	( cd "$r" && bash narzedzia-qa/mapa-licznik.sh audyt/sklep/material/06-mapa-polowania.md --zamroz "$sha" >/dev/null )
	# rejestr: jedno ZAMKNIETE ze strażnikiem, jedna anomalia WYJAŚNIONA
	printf 'x' > "$r/testy/straznik-test.php"
	printf '# rejestr\n| ID | soczewka | runda | waga | klasa | teza | dowod | repro | status | naprawa_proby | straznik | straznik_padl_na_starym | PR |\n|---|---|---|---|---|---|---|---|---|---|---|---|---|\n| SK-L2-001 | L2 | A %s | DUŻA | k | teza | d | r | ZAMKNIETE | 1 | testy/straznik-test.php | %s | 1 |\n\n| ID | soczewka | runda | opis | status | dowod |\n|---|---|---|---|---|---|\n| AN-001 | L3 | A | opis | WYJAŚNIONA | dowod |\n' "$sha" "$sha" > "$r/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"
	printf '# przelot\n- ✅ A1 · ok\n- ✅ B1 · ok\n' > "$r/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"
	for i in $(seq 1 26); do printf 'proba-%02d EXIT=0\n' "$i"; done > "$r/audyt/sklep/PROBY-KONTROLNE.log"
	printf '{"soczewka":"L2","runda":"A","commit_head":"%s","werdykty":[{"cel":"L2-01","werdykt":"SPEŁNIONE","plik":"a.php","linia":3},{"cel":"L2-02","werdykt":"BRAK","proba_kontrolna":"grep -rn x . → 0; próba kontrolna: grep y → 3"}],"znaleziska":[{"teza":"t","waga":"DUŻA","typ":"USTERKA","dowody":[{"plik":"a.php","linia":3,"cytat":"c"}],"mechanizm":"m","skutek":"s","rodzaj_skutku":"zaobserwowany","kroki_reprodukcji":["curl …"],"test_padajacy":"testy/x.php: FAIL"}],"anomalie":[],"czego_nie_bylo_na_mojej_liscie":["cache"],"czego_nie_obejrzalem":[]}\n' "$sha" > "$r/audyt/sklep/raporty/A/L2.json"
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca> <fragment-komunikatu>
	local opis="$1" ocz="$2" psuj="$3" frag="$4" r="$BAZA/proba" wy kod=0
	zbuduj_czysty "$r"; "$psuj" "$r"
	wy="$(bash "$r/narzedzia-qa/k7-koniec.sh" --korzen "$r" 2>&1)" || kod=$?
	if [ "$kod" -ne "$ocz" ]; then printf '  BLAD %-52s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"; printf '%s\n' "$wy" | sed 's/^/         /' | head -20; BLEDY=$((BLEDY+1)); return; fi
	if [ -n "$frag" ] && ! printf '%s' "$wy" | grep -qF "$frag"; then printf '  BLAD %-52s (kod OK, nie nazwala warunku: %s)\n' "$opis" "$frag"; BLEDY=$((BLEDY+1)); return; fi
	printf '  OK   %-52s (EXIT=%s)\n' "$opis" "$kod"
}

nic()            { :; }
psuj_mapa()      { sed -i 's/^- ✅ L1-01/- ⬜ L1-01/' "$1/audyt/sklep/material/06-mapa-polowania.md"; }
psuj_zamroz()    { printf -- '- ⬜ L9-99 · dopisany po zamrożeniu ·\n' >> "$1/audyt/sklep/material/06-mapa-polowania.md"; sed -i 's/^- ⬜ L9-99/- ✅ L9-99/' "$1/audyt/sklep/material/06-mapa-polowania.md"; }
psuj_kalibracja(){ ( cd "$1" && git checkout -q audyt/mutanty-sklep && sed -i 's/| L3 | M3 | tak | nie | PASS | 2 |/| L3 | M3 | nie | nie | FAIL | 3 |/' audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md && git commit -qam zle && git checkout -q - ); }
psuj_rejestr()   { sed -i 's/| ZAMKNIETE | 1 |/| DO WERYFIKACJI | 1 |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_proby()     { sed -i 's/| ZAMKNIETE | 1 |/| ZAMKNIETE | 3 |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_straznik()  { rm -f "$1/testy/straznik-test.php"; }
psuj_anomalia()  { sed -i 's/| WYJAŚNIONA |/| OTWARTA |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_przelot()   { sed -i 's/^- ✅ B1/- ⬜ B1/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_proby_log() { sed -i 's/proba-07 EXIT=0/proba-07 EXIT=1/' "$1/audyt/sklep/PROBY-KONTROLNE.log"; }
psuj_sha()       { printf 'zmiana' >> "$1/boty/paczki/sklep-agentow-9.9.9.zip"; }
psuj_tag()       { ( cd "$1" && git tag -d k7-zamrozenie >/dev/null && git commit -q --allow-empty -m inny && git tag k7-zamrozenie ); }
psuj_raport_ch() { sed -i 's/"commit_head":"[0-9a-f]*"/"commit_head":"deadbee"/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_sp() { sed -i 's/"plik":"a.php","linia":3}/"linia":0}/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_br() { sed -i 's/"proba_kontrolna":"[^"]*"/"proba_kontrolna":""/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_li() { sed -i 's/"czego_nie_bylo_na_mojej_liscie":\["cache"\]/"czego_nie_bylo_na_mojej_liscie":[]/' "$1/audyt/sklep/raporty/A/L2.json"; }

command -v zip >/dev/null && command -v jq >/dev/null || { echo "PROBA: brak zip/jq" >&2; exit 2; }
echo "=== KONTROLA ODWROTNA: czysty stan konca = GO ==="
sprawdz "komplet warunkow → GO"                          0 nic              "K7: GO"
echo; echo "=== KAZDY WARUNEK PSUTY OSOBNO → NO-GO z nazwa warunku ==="
sprawdz "1a cel ⬜ na mapie"                              1 psuj_mapa        "1a mapa"
sprawdz "1b cel dopisany po zamrozeniu"                   1 psuj_zamroz      "1b mapa"
sprawdz "2 soczewka FAIL / proby>2 w rozliczeniu"         1 psuj_kalibracja  "2 kalibracja"
sprawdz "3b znalezisko DO WERYFIKACJI"                    1 psuj_rejestr     "3b rejestr"
sprawdz "3c naprawa_proby = 3"                            1 psuj_proby       "3c rejestr"
sprawdz "3d ZAMKNIETE bez pliku straznika"                1 psuj_straznik    "3d rejestr"
sprawdz "4 anomalia OTWARTA"                              1 psuj_anomalia    "4 anomalie"
sprawdz "5 przelot z ⬜"                                   1 psuj_przelot     "5 przelot"
sprawdz "6 proba kontrolna EXIT=1 w logu"                 1 psuj_proby_log   "6 próby"
sprawdz "7a paczka zmieniona po zamrozeniu (sha256)"      1 psuj_sha         "7a paczka"
sprawdz "7b tag przesuniety na inny commit"               1 psuj_tag         "7b tag"
sprawdz "8 raport z innym commit_head"                    1 psuj_raport_ch   "8 raporty"
sprawdz "8 SPELNIONE bez plik:linia"                      1 psuj_raport_sp   "8 raporty"
sprawdz "8 BRAK bez proby kontrolnej"                     1 psuj_raport_br   "8 raporty"
sprawdz "8 brak sekcji czego-nie-bylo-na-liscie"          1 psuj_raport_li   "8 raporty"
echo
if [ "$BLEDY" -eq 0 ]; then echo "K7-KONIEC ROZPOZNAJE KAZDY PODLOZONY BRAK — i przepuszcza czysty stan"; exit 0; fi
echo "K7-KONIEC POMYLIL SIE $BLEDY RAZ(Y)"; exit 1
