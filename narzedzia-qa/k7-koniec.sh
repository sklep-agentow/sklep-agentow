#!/bin/bash
# k7-koniec.sh — KONIEC AUDYTU K7 OGŁASZA SKRYPT, NIE UCZUCIE.                       WERSJA 1.0.0
#
# PO CO: plan K7 v1 mówił „GO/NO-GO = trzy liczby liczone skryptem”, a skryptu nie było
# (krytyka r1: nasz krytyk K1, C2 #15, C3 #11). Bez maszyny koniec rozstrzyga pamięć
# koordynatorki — dokładnie to, czemu plan miał zapobiec. Ten skrypt sprawdza OSIEM
# warunków zamknięcia (PLAN-K7 §2 zasada 5, §8b) i wypisuje trzy liczby dla właściciela.
#
# Użycie: bash narzedzia-qa/k7-koniec.sh [--korzen <repo>] [--bez-zamrozenia-mapy]
#   EXIT 0 = wszystkie warunki PASS (GO) · 1 = choć jeden FAIL (NO-GO) · 2 = brak plików/narzędzi
#   --bez-zamrozenia-mapy — warunek 1b (mapa zamrożona) NIE jest wymagany (dozwolone tylko
#   w wariancie „audyt jednoprzebiegowy + człowiek”, PLAN-K7 §8c — musi być wpisane w ZAMROZENIE.md)
# Pliki, które czyta (wszystkie względem korzenia repo):
#   audyt/sklep/material/06-mapa-polowania.md · audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md
#   audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md · audyt/sklep/ZAMROZENIE.md · audyt/sklep/raporty/**/*.json
#   audyt/sklep/PROBY-KONTROLNE.log · gałąź audyt/mutanty-sklep:audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md
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
for f in "$MAPA" "$REJESTR" "$PRZELOT" "$ZAMROZENIE"; do [ -f "$f" ] || { echo "k7-koniec: brak $f" >&2; exit 2; }; done

FAIL=0
pass() { printf '  PASS  %s\n' "$1"; }
fail() { printf '  FAIL  %s\n' "$1"; FAIL=$((FAIL+1)); }

echo "=== K7-KONIEC 1.0.0 — osiem warunków zamknięcia ==="

# 1. MAPA: 0 ⬜ i (domyślnie) zamrożona z niezmienioną liczbą celów
LICZNIK=$(bash narzedzia-qa/mapa-licznik.sh "$MAPA" 2>/dev/null | tail -1)
NIE=$(printf '%s' "$LICZNIK" | sed -n 's/.*⬜ \([0-9]*\).*/\1/p')
[ "${NIE:-1}" -eq 0 ] && pass "1a mapa: $LICZNIK" || fail "1a mapa: $LICZNIK (⬜ musi być 0)"
if [ "$WYMAGAJ_ZAMROZENIA_MAPY" -eq 1 ]; then
	if bash narzedzia-qa/mapa-licznik.sh "$MAPA" --sprawdz-zamrozenie >/dev/null 2>&1 && grep -q '^ZAMROZONA: ' "$MAPA"; then
		pass "1b mapa zamrożona, liczba celów niezmieniona"
	else
		fail "1b mapa NIE zamrożona albo cele dopisane po zamrożeniu"
	fi
else
	pass "1b zamrożenie mapy niewymagane (--bez-zamrozenia-mapy; wariant jednoprzebiegowy)"
fi

# 2. KALIBRACJA: tabela rozliczenia z gałęzi mutantów; każda kalibrowana soczewka PASS, proby ≤ 2
ROZL=$(git show audyt/mutanty-sklep:audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md 2>/dev/null)
if [ -z "$ROZL" ]; then
	fail "2 kalibracja: brak audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md na gałęzi audyt/mutanty-sklep"
else
	# wiersze werdyktów: | L2 | M2 | ... | PASS/FAIL | proby |
	ILE=$(printf '%s\n' "$ROZL" | grep -cE '^\| L[1-5]|^\| L \|' )
	ZLE=$(printf '%s\n' "$ROZL" | grep -E '^\| (L[1-5]|L) \|' | grep -vE '\| PASS \|' | wc -l)
	PROBY_ZLE=$(printf '%s\n' "$ROZL" | grep -E '^\| (L[1-5]|L) \|' | awk -F'|' '{p=$NF; if (p+0>2) n++} END{print n+0}')
	if [ "$ILE" -ge 6 ] && [ "$ZLE" -eq 0 ] && [ "$PROBY_ZLE" -eq 0 ]; then pass "2 kalibracja: $ILE soczewek PASS, proby ≤ 2"; else fail "2 kalibracja: soczewek=$ILE, bez PASS=$ZLE, proby>2=$PROBY_ZLE"; fi
fi

# 3. REJESTR: 0 KRYTYCZNE/DUŻE otwarte · 0 DO WERYFIKACJI · naprawa_proby ≤ 2 · ZAMKNIETE ma strażnika z dowodem
WIERSZE=$(grep -E '^\| SK-' "$REJESTR")
OTWARTE=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /KRYTYCZNA|DUŻA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
DOWER=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$10 ~ /DO WERYFIKACJI/' | wc -l)
PROBY3=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$11+0 > 2' | wc -l)
BEZ_STRAZNIKA=0
while IFS= read -r w; do
	[ -n "$w" ] || continue
	st=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$10); print $10}')
	[ "$st" = "ZAMKNIETE" ] || continue
	plik=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$12); print $12}')
	sha=$(printf '%s' "$w" | awk -F'|' '{gsub(/^ +| +$/,"",$13); print $13}')
	if [ -z "$plik" ] || [ "$plik" = "—" ] || [ ! -f "$plik" ] || ! printf '%s' "$sha" | grep -qE '^[0-9a-f]{7,}$'; then BEZ_STRAZNIKA=$((BEZ_STRAZNIKA+1)); fi
done <<< "$WIERSZE"
[ "$OTWARTE" -eq 0 ] && pass "3a rejestr: 0 KRYTYCZNE/DUŻE otwartych" || fail "3a rejestr: $OTWARTE KRYTYCZNE/DUŻE otwartych"
[ "$DOWER" -eq 0 ] && pass "3b rejestr: 0 DO WERYFIKACJI" || fail "3b rejestr: $DOWER czeka na weryfikatora"
[ "$PROBY3" -eq 0 ] && pass "3c rejestr: naprawa_proby ≤ 2 wszędzie" || fail "3c rejestr: $PROBY3 znalezisk z > 2 próbami naprawy (zasada dwóch prób)"
[ "$BEZ_STRAZNIKA" -eq 0 ] && pass "3d rejestr: każde ZAMKNIETE ma strażnika (plik istnieje) + sha padania na starym" || fail "3d rejestr: $BEZ_STRAZNIKA ZAMKNIETE bez strażnika z dowodem"

# 4. ANOMALIE: 0 OTWARTA
AN_OTW=$(grep -E '^\| AN-' "$REJESTR" | awk -F'|' '$6 ~ /OTWARTA/' | wc -l)
[ "$AN_OTW" -eq 0 ] && pass "4 anomalie: 0 OTWARTA" || fail "4 anomalie: $AN_OTW OTWARTA"

# 5. PRZELOT CZŁOWIEKA: 0 ⬜ w liście
PRZ_NIE=$(grep -cE '^- ⬜ ' "$PRZELOT")
[ "$PRZ_NIE" -eq 0 ] && pass "5 przelot człowieka: komplet" || fail "5 przelot człowieka: $PRZ_NIE punktów ⬜"

# 6. PRÓBY KONTROLNE STRAŻNIKÓW: log z jednego przebiegu, 26+ pozycji, wszystkie EXIT=0
if [ -f "$PROBY" ]; then
	ILE_P=$(grep -cE 'EXIT=' "$PROBY"); ZLE_P=$(grep -E 'EXIT=' "$PROBY" | grep -vc 'EXIT=0')
	[ "$ILE_P" -ge 26 ] && [ "$ZLE_P" -eq 0 ] && pass "6 próby kontrolne: $ILE_P/$ILE_P EXIT=0" || fail "6 próby kontrolne: $ILE_P pozycji, $ZLE_P z EXIT≠0 (wymagane ≥26 i 0)"
else
	fail "6 próby kontrolne: brak $PROBY"
fi

# 7. ZAMROŻENIE: sha256 zipa == zapisany; tag == zapisany SHA; raporty mają ten SHA
ZIP=$(sed -n 's/^PACZKA: *//p' "$ZAMROZENIE" | head -1)
SHA_ZAP=$(sed -n 's/^SHA256: *//p' "$ZAMROZENIE" | head -1)
COMMIT_ZAP=$(sed -n 's/^COMMIT: *//p' "$ZAMROZENIE" | head -1)
if [ -n "$ZIP" ] && [ -f "$ZIP" ] && [ "$(sha256sum "$ZIP" | cut -d' ' -f1)" = "$SHA_ZAP" ]; then pass "7a paczka: sha256 zgodny ($ZIP)"; else fail "7a paczka: brak pliku albo sha256 ≠ ZAMROZENIE.md"; fi
TAG_SHA=$(git rev-parse --short=7 k7-zamrozenie 2>/dev/null)
[ -n "$TAG_SHA" ] && [ "$TAG_SHA" = "${COMMIT_ZAP:0:7}" ] && pass "7b tag k7-zamrozenie = $TAG_SHA" || fail "7b tag k7-zamrozenie ($TAG_SHA) ≠ COMMIT w ZAMROZENIE.md (${COMMIT_ZAP:0:7})"

# 8. RAPORTY JSON: parser — SPEŁNIONE/ZGODNE bez plik+linia albo BRAK bez proba_kontrolna = odrzucone; commit_head = zamrożony
if [ -d "$RAPORTY" ] && ls "$RAPORTY"/*/*.json >/dev/null 2>&1; then
	ZLE_R=0; ILE_R=0
	for r in "$RAPORTY"/*/*.json; do
		ILE_R=$((ILE_R+1))
		jq -e . "$r" >/dev/null 2>&1 || { echo "    zły JSON: $r"; ZLE_R=$((ZLE_R+1)); continue; }
		CH=$(jq -r '.commit_head // ""' "$r")
		[ "${CH:0:7}" = "${COMMIT_ZAP:0:7}" ] || { echo "    $r: commit_head=$CH ≠ ${COMMIT_ZAP:0:7}"; ZLE_R=$((ZLE_R+1)); }
		N1=$(jq '[.werdykty[]? | select((.werdykt=="SPEŁNIONE" or .werdykt=="SPEŁNIONE INACZEJ" or .werdykt=="ZGODNE") and ((.plik // "")=="" or (.linia // 0)==0))] | length' "$r")
		N2=$(jq '[.werdykty[]? | select(.werdykt=="BRAK" and ((.proba_kontrolna // "")==""))] | length' "$r")
		N3=$(jq '[.znaleziska[]? | select((.kroki_reprodukcji // []) | length == 0)] | length' "$r")
		N4=$(jq '(.czego_nie_bylo_na_mojej_liscie // []) | length' "$r")
		[ "$N1" -eq 0 ] || { echo "    $r: $N1 werdyktów SPEŁNIONE/ZGODNE bez plik:linia"; ZLE_R=$((ZLE_R+1)); }
		[ "$N2" -eq 0 ] || { echo "    $r: $N2 werdyktów BRAK bez próby kontrolnej"; ZLE_R=$((ZLE_R+1)); }
		[ "$N3" -eq 0 ] || { echo "    $r: $N3 znalezisk bez kroków reprodukcji"; ZLE_R=$((ZLE_R+1)); }
		[ "$N4" -ge 1 ] || { echo "    $r: brak sekcji „czego nie było na mojej liście”"; ZLE_R=$((ZLE_R+1)); }
	done
	[ "$ZLE_R" -eq 0 ] && pass "8 raporty: $ILE_R plików, 0 odrzuconych werdyktów" || fail "8 raporty: $ZLE_R naruszeń w $ILE_R plikach"
else
	fail "8 raporty: brak $RAPORTY/*/*.json"
fi

echo
KRYT=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /KRYTYCZNA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
DUZE=$(printf '%s\n' "$WIERSZE" | awk -F'|' '$5 ~ /DUŻA/ && $10 !~ /ZAMKNIETE|OBALONE|POZA K7/' | wc -l)
echo "TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: $KRYT · DUŻE otwarte: $DUZE · warunków FAIL: $FAIL"
if [ "$FAIL" -eq 0 ]; then echo "K7: GO — wszystkie warunki zamknięcia spełnione"; exit 0; fi
echo "K7: NO-GO — $FAIL warunków niespełnionych"; exit 1
