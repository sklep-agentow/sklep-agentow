#!/bin/bash
# k7-koniec-proba-kontrolna.sh — dowód, że k7-koniec.sh nie jest ozdobą.                 WERSJA 1.3.1
#
# Buduje w /tmp PEŁNĄ, ZIELONĄ atrapę stanu końca K7 (mapa, rejestr, przelot, log prób,
# paczka + sha, repo git z tagiem i gałęzią mutantów, raport JSON) i sprawdza, że skrypt
# mówi GO. Potem psuje KAŻDY z ośmiu warunków osobno i sprawdza, że skrypt mówi NO-GO
# i nazywa ten właśnie warunek. Kontrola odwrotna jest pierwsza — inaczej „bramka, która
# wszystko odrzuca” też by zdała.
# ZASADA (lekcja recenzji #182 r2 W2): każda próba psuje DOKŁADNIE JEDEN warunek. Pierwsza wersja
# psuła „FAIL" i „proby=3" naraz, więc martwy licznik prób przechodził 16/16. Atrapa = ten sam rodzaj
# co produkcja: tag ADNOTOWANY (git tag -a), bo goły tag nie wykryłby rev-parse bez ^{commit} (W8).
# Kody: 0 = bramka rozpoznaje każdy podłożony brak i przepuszcza czysty stan · 1 = pomyliła się
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BAZA="$(mktemp -d)"; trap 'rm -rf "$BAZA"' EXIT
BLEDY=0

zbuduj_czysty() {
	local r="$1"; rm -rf "$r"; mkdir -p "$r/narzedzia-qa" "$r/audyt/sklep/material" "$r/audyt/sklep/raporty/A" "$r/boty/paczki" "$r/testy"
	cp "$KORZEN/narzedzia-qa/k7-koniec.sh" "$KORZEN/narzedzia-qa/mapa-licznik.sh" "$r/narzedzia-qa/"
	mkdir -p "$r/audyt/sklep/prompty"; cp "$KORZEN/audyt/sklep/prompty/SCHEMAT-RAPORTU.json" "$r/audyt/sklep/prompty/"
	# paczka + sha
	printf 'atrapa' > "$r/boty/paczki/plik.txt"; ( cd "$r/boty/paczki" && zip -q sklep-agentow-9.9.9.zip plik.txt )
	# repo git z tagiem i gałęzią mutantów
	( cd "$r" && git init -q && git config user.email a@b.c && git config user.name a && git add -A >/dev/null && git commit -qm baza && git tag -a k7-zamrozenie -m zamrozenie \
	  && git checkout -qb audyt/mutanty-sklep && printf '# rozliczenie\n| audytor | mutant | znalazl | falszywy | werdykt | proby |\n|---|---|---|---|---|---|\n| L1 | M1 | tak | nie | PASS | 1 |\n| L2 | M2 | tak | nie | PASS | 1 |\n| L3 | M3 | tak | nie | PASS | 2 |\n| L4 | M5 | tak | nie | PASS | 1 |\n| L5 | M6 | tak | nie | PASS | 1 |\n| L | M7 | tak | nie | PASS | 1 |\n' > audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md \
	  && git add -A >/dev/null && git commit -qm mutanty && git checkout -q - )
	local sha; sha=$(git -C "$r" rev-parse --short=7 'k7-zamrozenie^{commit}')
	printf 'PACZKA: boty/paczki/sklep-agentow-9.9.9.zip\nSHA256: %s\nCOMMIT: %s\n' "$(sha256sum "$r/boty/paczki/sklep-agentow-9.9.9.zip" | cut -d' ' -f1)" "$sha" > "$r/audyt/sklep/ZAMROZENIE.md"
	# mapa: 2 cele zamknięte, zamrożona
	printf '# mapa\n- ✅ L1-01 · cel · [A · %s]\n- 🐞 L2-01 · cel · [A · %s]\n\nCELE: 0 · ✅ 0 · 🐞 0 · ⚠️ 0 · ⛔ 0 · ⬜ 0\n' "$sha" "$sha" > "$r/audyt/sklep/material/06-mapa-polowania.md"
	( cd "$r" && bash narzedzia-qa/mapa-licznik.sh audyt/sklep/material/06-mapa-polowania.md --zamroz "$sha" >/dev/null )
	# rejestr: jedno ZAMKNIETE ze strażnikiem, jedna anomalia WYJAŚNIONA
	printf 'x' > "$r/testy/straznik-test.php"
	printf '# rejestr\n\n## Znaleziska\n| ID | soczewka | runda | waga | klasa | teza | dowod | repro | status | naprawa_proby | straznik | straznik_padl_na_starym | PR |\n|---|---|---|---|---|---|---|---|---|---|---|---|---|\n| SK-L2-001 | L2 | A %s | DUŻA | k | teza | d | r | ZAMKNIETE | 1 | testy/straznik-test.php | %s | 1 |\n\n## Anomalie\n| ID | soczewka | runda | opis | status | dowod |\n|---|---|---|---|---|---|\n| AN-001 | L3 | A | opis | WYJAŚNIONA | dowod |\n' "$sha" "$sha" > "$r/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"
	{ printf '# przelot\n'; for i in $(seq 1 26); do printf -- '- ✅ A%s · ok\n' "$i"; done; printf -- '- ✅ B1 · ok\n'; printf '\n## LICZNIK\nCELE: 27 · ✅ 27 · 😖 0 · 🐞 0 · ⬜ 0\n'; } > "$r/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"
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
psuj_kal_dubel()  { ( cd "$1" && git checkout -q audyt/mutanty-sklep && sed -i 's/| L5 | M6 |/| L3 | M6 |/' audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md && git commit -qam dubel && git checkout -q - ); }  # 6 wierszy, 5 soczewek (#189 W3)
psuj_kalibracja(){ ( cd "$1" && git checkout -q audyt/mutanty-sklep && sed -i 's/| L3 | M3 | tak | nie | PASS | 2 |/| L3 | M3 | nie | nie | FAIL | 2 |/' audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md && git commit -qam zle && git checkout -q - ); }
psuj_kal_potok() { ( cd "$1" && git checkout -q audyt/mutanty-sklep && sed -i 's/| L3 | M3 | tak | nie | PASS | 2 |/| L3 | M3 \\| M3b | tak | nie | PASS | 3 |/' audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md && git commit -qam zle && git checkout -q - ); }
psuj_przelot_znak() { sed -i 's/^- ✅ B1/- ❓ B1/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_kal_proby() { ( cd "$1" && git checkout -q audyt/mutanty-sklep && sed -i 's/| L3 | M3 | tak | nie | PASS | 2 |/| L3 | M3 | tak | nie | PASS | 3 |/' audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md && git commit -qam zle && git checkout -q - ); }
psuj_znacznik()  { sed -i 's/^- ✅ L1-01/- ❓ L1-01/' "$1/audyt/sklep/material/06-mapa-polowania.md"; }
psuj_licznik()   { sed -i 's/^CELE: .*/CELE: 0 · ✅ 0 · 🐞 0 · ⚠️ 0 · ⛔ 0 · ⬜ 0/' "$1/audyt/sklep/material/06-mapa-polowania.md"; }
psuj_rejestr()   { sed -i 's/| ZAMKNIETE | 1 |/| DO WERYFIKACJI | 1 |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_proby()     { sed -i 's/| ZAMKNIETE | 1 |/| ZAMKNIETE | 3 |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_straznik()  { rm -f "$1/testy/straznik-test.php"; }
psuj_anomalia()  { sed -i 's/| WYJAŚNIONA |/| OTWARTA |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
tylko_remote()       { ( cd "$1" && git update-ref refs/remotes/origin/audyt/mutanty-sklep audyt/mutanty-sklep && git branch -q -D audyt/mutanty-sklep ); }
psuj_galaz_brak()    { ( cd "$1" && git branch -q -D audyt/mutanty-sklep ); }
psuj_przelot_licz()  { sed -i 's/^CELE: 27 · ✅ 27/CELE: 26 · ✅ 26/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_raport_enum()   { sed -i 's/"werdykt":"BRAK"/"werdykt":"NIE DA SIĘ ORZEC"/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_skutek() { sed -i 's/"rodzaj_skutku":"zaobserwowany"/"rodzaj_skutku":"wywnioskowany"/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_przelot_pusty() { sed -i '/^- /d; s/^CELE: .*/CELE: 0 · ✅ 0 · 😖 0 · 🐞 0 · ⬜ 0/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_przelot_dziw()  { sed -i 's/^- ✅ B1/- 😖 B1/; s/^CELE: 27 · ✅ 27 · 😖 0/CELE: 27 · ✅ 26 · 😖 1/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_anomalia_id()   { sed -i 's/| AN-001 | L3 | A | opis | WYJAŚNIONA |/| AN-001 | L3 | A | opis | WYJAŚNIONA |\n| A-02 | L3 | A | bez prefiksu | OTWARTA |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_wcieta()        { printf -- '  - ⬜ L9-98 · wcięty punkt ·\n' >> "$1/audyt/sklep/material/06-mapa-polowania.md"; }
potok_w_dowodzie()   { sed -i 's/| ZAMKNIETE | 1 |/| ZAMKNIETE | 1 |/; s/| teza | d | r |/| teza | `git log \\| wc -l` → 3 | r |/' "$1/audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md"; }
psuj_przelot()   { sed -i 's/^- ✅ B1/- ⬜ B1/; s/^CELE: 27 · ✅ 27 · 😖 0 · 🐞 0 · ⬜ 0/CELE: 27 · ✅ 26 · 😖 0 · 🐞 0 · ⬜ 1/' "$1/audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md"; }
psuj_proby_log() { sed -i 's/proba-07 EXIT=0/proba-07 EXIT=1/' "$1/audyt/sklep/PROBY-KONTROLNE.log"; }
psuj_sha()       { printf 'zmiana' >> "$1/boty/paczki/sklep-agentow-9.9.9.zip"; }
psuj_tag()       { ( cd "$1" && git tag -d k7-zamrozenie >/dev/null && git commit -q --allow-empty -m inny && git tag -a k7-zamrozenie -m inny ); }
psuj_raport_ch() { sed -i 's/"commit_head":"[0-9a-f]*"/"commit_head":"deadbee"/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_sp() { sed -i 's/"plik":"a.php","linia":3}/"linia":0}/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_br() { sed -i 's/"proba_kontrolna":"[^"]*"/"proba_kontrolna":""/' "$1/audyt/sklep/raporty/A/L2.json"; }
psuj_raport_li() { sed -i 's/"czego_nie_bylo_na_mojej_liscie":\["cache"\]/"czego_nie_bylo_na_mojej_liscie":[]/' "$1/audyt/sklep/raporty/A/L2.json"; }

command -v zip >/dev/null && command -v jq >/dev/null || { echo "PROBA: brak zip/jq" >&2; exit 2; }
echo "=== KONTROLA ODWROTNA: czysty stan konca = GO ==="
sprawdz "komplet warunkow → GO"                          0 nic              "K7: GO"
echo; echo "=== KAZDY WARUNEK PSUTY OSOBNO → NO-GO z nazwa warunku ==="
sprawdz "1a cel ⬜ na mapie"                              1 psuj_mapa        "1a mapa"
sprawdz "1a nieznany znacznik ❓ na mapie (D7)"           1 psuj_znacznik    "1a mapa: nieznany znacznik"
sprawdz "1a LICZNIK w pliku nieaktualny"                  1 psuj_licznik     "1a mapa: LICZNIK w pliku nieaktualny"
sprawdz "1b cel dopisany po zamrozeniu"                   1 psuj_zamroz      "1b mapa"
sprawdz "2 gałąź mutantów TYLKO jako origin/ → nadal GO (W1)"  0 tylko_remote      "2 kalibracja (origin/audyt/mutanty-sklep)"
sprawdz "2 brak gałęzi mutantów w ogóle"                  1 psuj_galaz_brak    "brak gałęzi audyt/mutanty-sklep"
sprawdz "5 LICZNIK przelotu nieaktualny (D2)"             1 psuj_przelot_licz  "5 przelot człowieka: LICZNIK"
sprawdz "8 werdykt spoza słownika schematu (W4)"          1 psuj_raport_enum   "SPOZA słownika"
sprawdz "8 rodzaj_skutku spoza słownika (W4)"             1 psuj_raport_skutek "rodzaj_skutku SPOZA"
sprawdz "2 proby=3 w wierszu z \\| w komórce (W2 #186)"      1 psuj_kal_potok     "proby>2=1"
sprawdz "5 przelot z nieznanym znacznikiem ❓"             1 psuj_przelot_znak  "5 przelot człowieka: nieznany znacznik"
sprawdz "2 soczewka FAIL (proby=2 — tylko werdykt)"       1 psuj_kalibracja  "2 kalibracja"
sprawdz "2 proby=3 przy PASS (W2 — tylko licznik prob)"   1 psuj_kal_proby   "proby>2=1"
sprawdz "2 dubel soczewki: 6 wierszy, 5 soczewek (#189 W3)" 1 psuj_kal_dubel   "soczewek=5"
sprawdz "3b znalezisko DO WERYFIKACJI"                    1 psuj_rejestr     "3b rejestr"
sprawdz "3c naprawa_proby = 3"                            1 psuj_proby       "3c rejestr"
sprawdz "3d ZAMKNIETE bez pliku straznika"                1 psuj_straznik    "3d rejestr"
sprawdz "4 anomalia OTWARTA"                              1 psuj_anomalia    "4 anomalie"
sprawdz "5 przelot z ⬜"                                   1 psuj_przelot     "5 przelot"
sprawdz "5 przelot OBCIĘTY do zera punktów (W4)"          1 psuj_przelot_pusty "lista obcięta"
sprawdz "5 przelot z 😖 bez wyjaśnienia"                   1 psuj_przelot_dziw  "1 😖"
sprawdz "4 anomalia OTWARTA z ID bez AN- (W5)"             1 psuj_anomalia_id   "bez ID AN-NNN"
sprawdz "1a wcięty punkt mapy (D1)"                        1 psuj_wcieta        "1a mapa: nieznany znacznik"
sprawdz "3 potok \\| w kolumnie dowodu → nadal GO (W6)"     0 potok_w_dowodzie   "K7: GO"
sprawdz "6 proba kontrolna EXIT=1 w logu"                 1 psuj_proby_log   "6 próby"
sprawdz "7a paczka zmieniona po zamrozeniu (sha256)"      1 psuj_sha         "7a paczka"
sprawdz "7b tag przesuniety na inny commit"               1 psuj_tag         "7b tag"
sprawdz "8 raport z innym commit_head"                    1 psuj_raport_ch   "8 raporty"
sprawdz "8 SPELNIONE bez plik:linia"                      1 psuj_raport_sp   "8 raporty"
sprawdz "8 BRAK bez proby kontrolnej"                     1 psuj_raport_br   "8 raporty"
sprawdz "8 brak sekcji czego-nie-bylo-na-liscie"          1 psuj_raport_li   "8 raporty"
echo; echo "=== D7: BRAMKA NIE PISZE DO AUDYTOWANEJ MAPY ==="
r="$BAZA/proba"; zbuduj_czysty "$r"; psuj_licznik "$r"
PRZED=$(sha256sum "$r/audyt/sklep/material/06-mapa-polowania.md" | cut -d' ' -f1)
bash "$r/narzedzia-qa/k7-koniec.sh" --korzen "$r" >/dev/null 2>&1 || true
PO=$(sha256sum "$r/audyt/sklep/material/06-mapa-polowania.md" | cut -d' ' -f1)
if [ "$PRZED" = "$PO" ]; then printf '  OK   %-52s\n' "mapa bajt w bajt ta sama po przebiegu bramki"; else printf '  BLAD %-52s\n' "k7-koniec.sh PRZEPISAL mape (D7)"; BLEDY=$((BLEDY+1)); fi
echo
if [ "$BLEDY" -eq 0 ]; then echo "K7-KONIEC ROZPOZNAJE KAZDY PODLOZONY BRAK — i przepuszcza czysty stan"; exit 0; fi
echo "K7-KONIEC POMYLIL SIE $BLEDY RAZ(Y)"; exit 1
