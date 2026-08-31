#!/bin/bash
# k7-koniec.sh — KONIEC AUDYTU K7 OGŁASZA SKRYPT, NIE UCZUCIE.                       WERSJA 1.3.6
#
# PO CO: plan K7 v1 mówił „GO/NO-GO = trzy liczby liczone skryptem”, a skryptu nie było
# (krytyka r1: nasz krytyk K1, C2 #15, C3 #11). Bez maszyny koniec rozstrzyga pamięć
# koordynatorki — dokładnie to, czemu plan miał zapobiec. Ten skrypt sprawdza OSIEM
# warunków zamknięcia (PLAN-K7 §2 zasada 5, §8b) rozpisanych na 13 KONTROLI (1a,1b,2,3a–3d,4,5,6,7a,7b,8)
# i wypisuje trzy liczby dla właściciela. Wynik liczy KONTROLE, nie warunki (recenzja #191 D1).
#
# Użycie: bash narzedzia-qa/k7-koniec.sh [--korzen <repo>] [--bez-zamrozenia-mapy]
#   EXIT 0 = wszystkie warunki PASS (GO) · 1 = choć jeden FAIL (NO-GO) · 2 = brak plików/narzędzi
#   --bez-zamrozenia-mapy — warunek 1b (mapa zamrożona) NIE jest wymagany (dozwolone tylko
#   w wariancie „audyt jednoprzebiegowy + człowiek”, PLAN-K7 §8c — musi być wpisane w ZAMROZENIE.md)
# Pliki, które czyta (wszystkie względem korzenia repo):
#   audyt/sklep/material/06-mapa-polowania.md · audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md
#   audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md · audyt/sklep/ZAMROZENIE.md · audyt/sklep/raporty/**/*.json
#   audyt/sklep/PROBY-KONTROLNE.log · tag bank/mutanty-sklep (do 31.08 #238: gałąź audyt/mutanty-sklep):audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WYMAGAJ_ZAMROZENIA_MAPY=1
while [ $# -gt 0 ]; do
	case "$1" in
		--korzen) KORZEN="$2"; shift 2 ;;
		--bez-zamrozenia-mapy) WYMAGAJ_ZAMROZENIA_MAPY=0; shift ;;
		*) echo "k7-koniec: nieznana opcja $1" >&2; exit 2 ;;
	esac
done
cd "$KORZEN" || exit 2
command -v jq >/dev/null || { echo "k7-koniec: brak jq" >&2; exit 2; }
MAPA=audyt/sklep/material/06-mapa-polowania.md
REJESTR=audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md
PRZELOT=audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md
ZAMROZENIE=audyt/sklep/ZAMROZENIE.md
RAPORTY=audyt/sklep/raporty
PROBY=audyt/sklep/PROBY-KONTROLNE.log
SCHEMAT=audyt/sklep/prompty/SCHEMAT-RAPORTU.json
for f in "$MAPA" "$REJESTR" "$PRZELOT" "$ZAMROZENIE" "$SCHEMAT"; do [ -f "$f" ] || { echo "k7-koniec: brak $f" >&2; exit 2; }; done

FAIL=0; PASS=0
pass() { printf '  PASS  %s\n' "$1"; PASS=$((PASS+1)); }
fail() { printf '  FAIL  %s\n' "$1"; FAIL=$((FAIL+1)); }

WERSJA_SKRYPTU=$(sed -n '2s/.*WERSJA \([0-9.]*\).*/\1/p' "$0")   # jedno źródło numeru (recenzja #188 r2 W4)
echo "=== K7-KONIEC ${WERSJA_SKRYPTU:-?} — osiem warunków zamknięcia (rozpisanych na kontrole; wynik liczy kontrole) ==="

# 1. MAPA: 0 ⬜ i (domyślnie) zamrożona z niezmienioną liczbą celów.
#    TYLKO ODCZYT (--tylko-licz): bramka końca nie pisze do tego, co audytuje (recenzja #182 r2 D7).
#    Nieznany znacznik po „- " = FAIL z listą linii (cel nie może zniknąć z licznika bez śladu).
LICZNIK_BLAD=$(bash narzedzia-qa/mapa-licznik.sh "$MAPA" --tylko-licz 2>&1 >/dev/null) ; LICZNIK_KOD=$?
LICZNIK=$(bash narzedzia-qa/mapa-licznik.sh "$MAPA" --tylko-licz 2>/dev/null | tail -1)
W_PLIKU=$(grep -m1 '^CELE: ' "$MAPA"); ILE_CELE=$(grep -c '^CELE: ' "$MAPA")
if [ "$ILE_CELE" -ne 1 ]; then
	fail "1a mapa: $ILE_CELE wierszy LICZNIKA „CELE:” (ma być dokładnie 1; #196 W3 — zdublowany wiersz ukrywa datę zamrożenia)"
elif [ "$LICZNIK_KOD" -ne 0 ]; then
	fail "1a mapa: nieznany znacznik celu (mapa-licznik EXIT=$LICZNIK_KOD)"; printf '%s\n' "$LICZNIK_BLAD" | sed 's/^/    /'
elif [ "$W_PLIKU" != "$LICZNIK" ]; then
	fail "1a mapa: LICZNIK w pliku nieaktualny — jest „$W_PLIKU”, policzone „$LICZNIK” (uruchom mapa-licznik.sh)"
else
	NIE=$(printf '%s' "$LICZNIK" | sed -n 's/.*⬜ \([0-9]*\).*/\1/p')
	[ "${NIE:-1}" -eq 0 ] && pass "1a mapa: $LICZNIK" || fail "1a mapa: $LICZNIK (⬜ musi być 0)"
fi
if [ "$WYMAGAJ_ZAMROZENIA_MAPY" -eq 1 ]; then
	if bash narzedzia-qa/mapa-licznik.sh "$MAPA" --sprawdz-zamrozenie >/dev/null 2>&1 && grep -q '^ZAMROZONA: ' "$MAPA"; then
		pass "1b mapa zamrożona, liczba i treść celów niezmienione"
	else
		fail "1b mapa NIE zamrożona albo cele dopisane po zamrożeniu"
	fi
else
	pass "1b zamrożenie mapy niewymagane (--bez-zamrozenia-mapy; wariant jednoprzebiegowy)"
fi

# 2. KALIBRACJA: tabela rozliczenia z gałęzi mutantów; każda kalibrowana soczewka PASS, proby ≤ 2
#    Referencja z FALLBACKIEM (recenzja #184 r2 W1): w świeżym klonie (CI, recenzent) gałąź istnieje TYLKO jako
#    origin/audyt/mutanty-sklep — goły `git show audyt/mutanty-sklep:…` dawał fałszywy FAIL (klasa jak W8).
GALAZ_MUT=""
for ref in audyt/mutanty-sklep origin/audyt/mutanty-sklep bank/mutanty-sklep; do git rev-parse --verify --quiet "$ref^{commit}" >/dev/null && { GALAZ_MUT="$ref"; break; }; done
if [ -z "$GALAZ_MUT" ]; then
	fail "2 kalibracja: brak banku mutantów — ani tag bank/mutanty-sklep, ani gałąź audyt/mutanty-sklep (lokalna/origin/)"
elif ! ROZL_SUROWE=$(git show "$GALAZ_MUT:audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md" 2>/dev/null) || [ -z "$ROZL_SUROWE" ]; then
	fail "2 kalibracja: gałąź $GALAZ_MUT jest, ale brak na niej audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md"
else
	ROZL=$(printf '%s\n' "$ROZL_SUROWE" | sed 's/\\|/\x01/g')  # osobno od git show: bez pipefail kod potoku = kod seda (recenzja #186 D3)
	# wiersze werdyktów: | audytor | mutant | znalazl | falszywy | werdykt | proby |  → po split '|' proby = pole 7
	# (recenzja #182 r2 W2: $NF czytał PUSTĄ kolumnę za końcowym „|" — warunek był martwy)
	# ILE = liczba RÓŻNYCH soczewek (kolumna 2), nie wierszy — L3 ma dwa mutanty (M3, M4) i liczył się podwójnie (recenzja #188 D8)
	ILE=$(printf '%s\n' "$ROZL" | grep -E '^\| (L[1-5]|L) \|' | awk -F'|' '{gsub(/ /,"",$2); print $2}' | sort -u | wc -l)
	ZLE=$(printf '%s\n' "$ROZL" | grep -E '^\| (L[1-5]|L) \|' | grep -vE '\| PASS \|' | wc -l)
	PROBY_ZLE=$(printf '%s\n' "$ROZL" | grep -E '^\| (L[1-5]|L) \|' | awk -F'|' '{gsub(/ /,"",$7); if ($7+0>2) n++} END{print n+0}')
	if [ "$ILE" -ge 6 ] && [ "$ZLE" -eq 0 ] && [ "$PROBY_ZLE" -eq 0 ]; then pass "2 kalibracja ($GALAZ_MUT): $ILE soczewek PASS, proby ≤ 2"; else fail "2 kalibracja: soczewek=$ILE, bez PASS=$ZLE, proby>2=$PROBY_ZLE"; fi
fi

# 3. REJESTR: 0 KRYTYCZNE/DUŻE otwarte · 0 DO WERYFIKACJI · naprawa_proby ≤ 2 · ZAMKNIETE ma strażnika z dowodem
# Potok w komórce markdown to `\|` — dla awk -F'|' to separator (recenzja #184 W6). Zamieniamy na \x01 PRZED splitem,
# a przy wypisywaniu przywracamy (`tr '\001' '|'`).
WIERSZE=$(grep -E '^\| SK-' "$REJESTR" | sed 's/\\|/\x01/g')
OTWARTE=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /KRYTYCZNA|DUŻA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
DOWER=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$10 ~ /DO WERYFIKACJI/' | wc -l)
PROBY3=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$11+0 > 2' | wc -l)
BEZ_STRAZNIKA=0
while IFS= read -r w; do
	[ -n "$w" ] || continue
	st=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$10); print $10}')
	[ "$st" = "ZAMKNIETE" ] || continue
	plik=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$12); print $12}' | tr '\001' '|')
	sha=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$13); print $13}' | tr '\001' '|')
	if [ -z "$plik" ] || [ "$plik" = "—" ] || [ ! -f "$plik" ] || ! printf '%s' "$sha" | grep -qE '^[0-9a-f]{7,}$'; then BEZ_STRAZNIKA=$((BEZ_STRAZNIKA+1)); fi
done <<< "$WIERSZE"
[ "$OTWARTE" -eq 0 ] && pass "3a rejestr: 0 KRYTYCZNE/DUŻE otwartych" || fail "3a rejestr: $OTWARTE KRYTYCZNE/DUŻE otwartych"
[ "$DOWER" -eq 0 ] && pass "3b rejestr: 0 DO WERYFIKACJI" || fail "3b rejestr: $DOWER czeka na weryfikatora"
[ "$PROBY3" -eq 0 ] && pass "3c rejestr: naprawa_proby ≤ 2 wszędzie" || fail "3c rejestr: $PROBY3 znalezisk z > 2 próbami naprawy (zasada dwóch prób)"
[ "$BEZ_STRAZNIKA" -eq 0 ] && pass "3d rejestr: każde ZAMKNIETE ma strażnika (plik istnieje) + sha padania na starym" || fail "3d rejestr: $BEZ_STRAZNIKA ZAMKNIETE bez strażnika z dowodem"

# 4. ANOMALIE: czytamy CAŁĄ tabelę sekcji „## Anomalie” (nie tylko wiersze z prefiksem — recenzja #184 W5);
#    każdy wiersz musi mieć ID `AN-NNN` (inaczej FAIL), 0 OTWARTA. Wiersz-zaślepka `| — |` pomijany.
AN_WIERSZE=$(awk '/^## Anomalie/{f=1;next} /^## /{f=0} f && /^\| /' "$REJESTR" | grep -vE '^\| ID \||^\|---|^\| — \|' | sed 's/\\|/\x01/g' || true)
AN_ZLE_ID=$(printf '%s\n' "$AN_WIERSZE" | grep . | grep -vcE '^\| AN-[0-9]{3,} \|' || true)
AN_OTW=$(printf '%s\n' "$AN_WIERSZE" | awk -F'|' '$6 ~ /OTWARTA/' | wc -l)
if [ "$AN_ZLE_ID" -ne 0 ]; then fail "4 anomalie: $AN_ZLE_ID wiersz(y) bez ID AN-NNN (bramka by ich nie widziała)"; elif [ "$AN_OTW" -eq 0 ]; then pass "4 anomalie: 0 OTWARTA"; else fail "4 anomalie: $AN_OTW OTWARTA"; fi

# 5. PRZELOT CZŁOWIEKA: podłoga ≥ 27 punktów (pusty/obcięty plik NIE jest kompletem — recenzja #184 W4),
#    0 ⬜ i 0 😖 (😖 = „coś dziwnego” bez wyjaśnienia → musi stać się 🐞 albo anomalią, zanim ogłosimy koniec).
PRZ_MIN=27
PRZ_ILE=$(grep -cE '^- (⬜|✅|😖|🐞) ' "$PRZELOT")
PRZ_NIE=$(grep -cE '^- ⬜ ' "$PRZELOT"); PRZ_DZIW=$(grep -cE '^- 😖 ' "$PRZELOT")
# LICZNIK przelotu liczy TEN SAM skrypt, który wolno go pisać: mapa-licznik.sh --przelot (recenzja #186 W2)
PRZ_BLAD=$(bash narzedzia-qa/mapa-licznik.sh "$PRZELOT" --przelot --tylko-licz 2>&1 >/dev/null); PRZ_KOD=$?
PRZ_LICZ=$(bash narzedzia-qa/mapa-licznik.sh "$PRZELOT" --przelot --tylko-licz 2>/dev/null | tail -1); PRZ_W_PLIKU=$(grep -m1 '^CELE: ' "$PRZELOT")
if [ "$PRZ_KOD" -ne 0 ]; then fail "5 przelot człowieka: nieznany znacznik (mapa-licznik --przelot EXIT=$PRZ_KOD)"; printf '%s\n' "$PRZ_BLAD" | sed 's/^/    /'
elif [ "$PRZ_W_PLIKU" != "$PRZ_LICZ" ]; then fail "5 przelot człowieka: LICZNIK w pliku nieaktualny — jest „$PRZ_W_PLIKU”, policzone „$PRZ_LICZ” (uruchom: bash narzedzia-qa/mapa-licznik.sh $PRZELOT --przelot)"; elif [ "$PRZ_ILE" -lt "$PRZ_MIN" ]; then fail "5 przelot człowieka: tylko $PRZ_ILE punktów (wymagane ≥ $PRZ_MIN) — lista obcięta?"; elif [ "$PRZ_NIE" -eq 0 ] && [ "$PRZ_DZIW" -eq 0 ]; then pass "5 przelot człowieka: komplet ($PRZ_ILE punktów)"; else fail "5 przelot człowieka: $PRZ_NIE ⬜ + $PRZ_DZIW 😖 z $PRZ_ILE"; fi

# 6. PRÓBY KONTROLNE STRAŻNIKÓW: log MUSI pochodzić z przelotu maszynowego i z TEGO commitu.
#    Recenzja #198 (runda 3, W3): wcześniej ta kontrola tylko grepowała plik pisany ręcznie —
#    log mówił „bramki-proba-kontrolna.sh EXIT=1", a uruchomienie tej próby na HEAD dawało 0.
#    Dowód bez daty ważności to dowód o nieznanym wieku, dlatego porównujemy COMMIT z HEAD.
if [ ! -f "$PROBY" ]; then
	fail "6 próby kontrolne: brak $PROBY"
elif ! grep -q '^WYGENEROWANE-PRZEZ: narzedzia-qa/proby-kontrolne-przelot.sh' "$PROBY"; then
	fail "6 próby kontrolne: $PROBY bez znacznika przelotu — log pisany ręcznie nie jest dowodem (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)"
else
	P_COMMIT=$(sed -n 's/^COMMIT: *//p' "$PROBY" | head -1)
	HEAD_KR=$(git rev-parse --short=7 HEAD 2>/dev/null)
	ILE_P=$(grep -cE '^[A-Za-z0-9_.-]+\.sh EXIT=' "$PROBY")
	ZLE_P=$(grep -E '^[A-Za-z0-9_.-]+\.sh EXIT=' "$PROBY" | grep -vc 'EXIT=0')
	POM_P=$(grep -cE '^POMINIETO ' "$PROBY")
	# DATA WAŻNOŚCI: nie „ten sam commit" (commit zapisujący log sam przesuwa HEAD, więc taki
	# warunek nie dałby się spełnić nigdy), tylko „od przelotu nie zmieniło się nic, czego ten
	# przelot dowodzi". Zmiana w narzędziach, testach albo kodzie wtyczki unieważnia dowód;
	# zmiana w dokumentach audytu — nie.
	if [ -z "$P_COMMIT" ] || ! git merge-base --is-ancestor "$P_COMMIT" HEAD 2>/dev/null; then
		fail "6 próby kontrolne: przelot z commitu ${P_COMMIT:-brak}, którego nie ma w historii HEAD ($HEAD_KR) — dowód z innej linii (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)"
	elif [ -n "$(git diff --name-only "$P_COMMIT" HEAD -- narzedzia-qa testy wtyczki boty/paczki 2>/dev/null)" ]; then
		POTEM=$(git diff --name-only "$P_COMMIT" HEAD -- narzedzia-qa testy wtyczki boty/paczki 2>/dev/null | head -3 | tr '\n' ' ')
		fail "6 próby kontrolne: po przelocie ($P_COMMIT) zmieniło się to, czego on dowodzi — $POTEM… — dowód przeterminowany (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)"
	elif [ "$ILE_P" -lt 26 ]; then
		fail "6 próby kontrolne: uruchomionych tylko $ILE_P (wymagane ≥26) — zakres zawężony, pominiętych $POM_P"
	elif [ "$ZLE_P" -ne 0 ]; then
		fail "6 próby kontrolne: $ZLE_P z $ILE_P ma EXIT≠0 (przelot na $P_COMMIT)"
	else
		pass "6 próby kontrolne: $ILE_P/$ILE_P EXIT=0 na commicie $P_COMMIT (pominiętych: $POM_P)"
	fi
fi

# 7. ZAMROŻENIE: sha256 zipa == zapisany; tag == zapisany SHA; raporty mają ten SHA
ZIP=$(sed -n 's/^PACZKA: *//p' "$ZAMROZENIE" | head -1)
SHA_ZAP=$(sed -n 's/^SHA256: *//p' "$ZAMROZENIE" | head -1)
COMMIT_ZAP=$(sed -n 's/^COMMIT: *//p' "$ZAMROZENIE" | head -1)
if [ -n "$ZIP" ] && [ -f "$ZIP" ] && [ "$(sha256sum "$ZIP" | cut -d' ' -f1)" = "$SHA_ZAP" ]; then pass "7a paczka: sha256 zgodny ($ZIP)"; else fail "7a paczka: brak pliku albo sha256 ≠ ZAMROZENIE.md"; fi
# ^{commit}: tag k7-zamrozenie jest ADNOTOWANY — goły rev-parse dałby sha OBIEKTU TAGU, nie commitu (fałszywy FAIL, W8)
TAG_SHA=$(git rev-parse --short=7 'k7-zamrozenie^{commit}' 2>/dev/null)
[ -n "$TAG_SHA" ] && [ "$TAG_SHA" = "${COMMIT_ZAP:0:7}" ] && pass "7b tag k7-zamrozenie = $TAG_SHA" || fail "7b tag k7-zamrozenie ($TAG_SHA) ≠ COMMIT w ZAMROZENIE.md (${COMMIT_ZAP:0:7})"

# 8. RAPORTY JSON: parser — SPEŁNIONE/ZGODNE bez plik+linia albo BRAK bez proba_kontrolna = odrzucone; commit_head = zamrożony
#    WYJĄTKI KOORDYNATORKI (1.3.5, recenzja #195 W2): raporty audytorów zostają SUROWE (AN-011), a werdykt, którego dowód
#    jest KOMENDĄ (stos.sh, plugin check, git log, inwentarz grepów — AN-021), nie ma linii pliku. Plik
#    $RAPORTY/WYJATKI-WARUNEK-8.txt: `<raport względem $RAPORTY>|<cel>|<powód>` — każdy wyjątek musi trafiać w REALNE
#    naruszenie (martwy wyjątek = FAIL), jest wypisywany w wyjściu i zostaje w repo do recenzji.
WYJATKI8="$RAPORTY/WYJATKI-WARUNEK-8.txt"
if [ -d "$RAPORTY" ] && ls "$RAPORTY"/*/*.json >/dev/null 2>&1; then
	ZLE_R=0; ILE_R=0
	# Wyjątki: bez komentarzy i pustych, ostatni wiersz także bez znaku nowej linii (#196 W2); dopasowanie DOKŁADNE po kolumnach.
	WYJ8=""; [ -f "$WYJATKI8" ] && WYJ8=$(grep -vE '^[[:space:]]*(#|$)' "$WYJATKI8"; printf x); WYJ8="${WYJ8%x}"
	for r in "$RAPORTY"/*/*.json; do
		ILE_R=$((ILE_R+1))
		jq -e . "$r" >/dev/null 2>&1 || { echo "    zły JSON: $r"; ZLE_R=$((ZLE_R+1)); continue; }
		CH=$(jq -r '.commit_head // ""' "$r")
		[ "${CH:0:7}" = "${COMMIT_ZAP:0:7}" ] || { echo "    $r: commit_head=$CH ≠ ${COMMIT_ZAP:0:7}"; ZLE_R=$((ZLE_R+1)); }
		NAR=$(jq -r '.werdykty[]? | select((.werdykt=="SPEŁNIONE" or .werdykt=="SPEŁNIONE INACZEJ" or .werdykt=="ZGODNE") and ((.plik // "")=="" or (.linia // 0)==0)) | .cel' "$r")
		N1=0; WYJ_UZYTE=""; REL="${r#"$RAPORTY"/}"
		while IFS= read -r cel; do
			[ -n "$cel" ] || continue
			if printf '%s\n' "$WYJ8" | awk -F'|' -v p="$REL" -v c="$cel" '$1==p && $2==c {f=1} END {exit !f}'; then WYJ_UZYTE="$WYJ_UZYTE $cel"; else N1=$((N1+1)); fi
		done <<< "$NAR"
		[ -z "$WYJ_UZYTE" ] || echo "    $r: wyjątki koordynatorki (dowód = komenda):$WYJ_UZYTE"
		N2=$(jq '[.werdykty[]? | select(.werdykt=="BRAK" and ((.proba_kontrolna // "")==""))] | length' "$r")
		N3=$(jq '[.znaleziska[]? | select((.kroki_reprodukcji // []) | length == 0)] | length' "$r")
		N4=$(jq '(.czego_nie_bylo_na_mojej_liscie // []) | length' "$r")
		# Słownik werdyktów i rodzaj_skutku WPROST ze schematu (recenzja #184 r2 W4: parser nie egzekwował enumów)
		N5=$(jq --slurpfile s "$SCHEMAT" '[.werdykty[]? | select(.werdykt as $w | ($s[0].properties.werdykty.items.properties.werdykt.enum | index($w)) == null)] | length' "$r")
		N6=$(jq --slurpfile s "$SCHEMAT" '[.znaleziska[]? | select(.rodzaj_skutku as $w | ($s[0].properties.znaleziska.items.properties.rodzaj_skutku.enum | index($w)) == null)] | length' "$r")
		[ "$N1" -eq 0 ] || { echo "    $r: $N1 werdyktów SPEŁNIONE/ZGODNE bez plik:linia"; ZLE_R=$((ZLE_R+1)); }
		[ "$N2" -eq 0 ] || { echo "    $r: $N2 werdyktów BRAK bez próby kontrolnej"; ZLE_R=$((ZLE_R+1)); }
		[ "$N3" -eq 0 ] || { echo "    $r: $N3 znalezisk bez kroków reprodukcji"; ZLE_R=$((ZLE_R+1)); }
		[ "$N4" -ge 1 ] || { echo "    $r: brak sekcji „czego nie było na mojej liście”"; ZLE_R=$((ZLE_R+1)); }
		[ "$N5" -eq 0 ] || { echo "    $r: $N5 werdyktów SPOZA słownika schematu"; ZLE_R=$((ZLE_R+1)); }
		[ "$N6" -eq 0 ] || { echo "    $r: $N6 znalezisk z rodzaj_skutku SPOZA słownika schematu"; ZLE_R=$((ZLE_R+1)); }
	done
	# MARTWE wyjątki — sprawdzane po WSZYSTKICH wierszach pliku (także celujących w raport, którego nie ma; #196 W2)
	printf '%s\n' "$WYJ8" | while IFS='|' read -r wp wc _; do
		[ -n "$wp" ] || continue
		if [ ! -f "$RAPORTY/$wp" ]; then echo "    $wp: MARTWY wyjątek — raport nie istnieje ($wc)"; continue; fi
		jq -r '.werdykty[]? | select((.werdykt=="SPEŁNIONE" or .werdykt=="SPEŁNIONE INACZEJ" or .werdykt=="ZGODNE") and ((.plik // "")=="" or (.linia // 0)==0)) | .cel' "$RAPORTY/$wp" \
			| grep -qxF -- "$wc" || echo "    $wp: MARTWY wyjątek dla celu $wc (nie narusza warunku 8)"
	done > "${TMPDIR:-/tmp}/k7-martwe-$$.txt"
	if [ -s "${TMPDIR:-/tmp}/k7-martwe-$$.txt" ]; then cat "${TMPDIR:-/tmp}/k7-martwe-$$.txt"; ZLE_R=$((ZLE_R+$(wc -l < "${TMPDIR:-/tmp}/k7-martwe-$$.txt"))); fi
	rm -f "${TMPDIR:-/tmp}/k7-martwe-$$.txt"
	[ "$ZLE_R" -eq 0 ] && pass "8 raporty: $ILE_R plików, 0 odrzuconych werdyktów" || fail "8 raporty: $ZLE_R naruszeń w $ILE_R plikach"
else
	fail "8 raporty: brak $RAPORTY/*/*.json"
fi

echo
KRYT=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /KRYTYCZNA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
DUZE=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /DUŻA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
echo "TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: $KRYT · DUŻE otwarte: $DUZE · kontroli FAIL: $FAIL (z $((PASS+FAIL)))"
if [ "$FAIL" -eq 0 ]; then echo "K7: GO — wszystkie warunki zamknięcia spełnione ($PASS kontroli PASS)"; exit 0; fi
echo "K7: NO-GO — $FAIL z $((PASS+FAIL)) kontroli niespełnionych (8 warunków zamknięcia)"; exit 1
