#!/usr/bin/env bash
# STRAŻNIK DK-1 (przyrząd ZAKTUALIZOWANY po naprawie C3, PR #162): licznik pułapki
# i lista zgłoszeń podejrzanych mają mówić o sobie PRAWDĘ.
#   HISTORIA: znalezisko DK-1 pokazało rozjazd 14 do 5 — licznik w opcji chatl_pulapka
#   rósł przy KAŻDYM zadziałaniu (też z pytań, które wiersza nie tworzą), a pasek
#   obiecywał „znajdziesz je na liście". Naprawa C3 USUNĘŁA opcję: zdarzenia leżą
#   w tabeli chatl_zdarzenia z kolumną `sciezka`, a pasek panelu liczy „na liście
#   znajdziesz X" z COUNT(*) wierszy podejrzanych, osobno wymieniając pytania.
#   KONTRAKT PO C3 (front.php): oba zapisy ścieżki zgłoszenia — zdarzenie ORAZ wiersz —
#   siedzą za JEDNYM kubłem `pulapka` (próg CHATL_LIMIT_PULAPKA/min; nad progiem 429
#   bez żadnego zapisu), więc mają się zgadzać CO DO SZTUKI. Pytanie ze złapaną
#   pułapką dopisuje zdarzenie (sciezka=pytanie) i ŻADNEGO wiersza — to nie rozjazd,
#   to projekt; ten test mierzy oba fakty.
# EXIT 0 = zgodne z projektem, EXIT 1 = źródła się rozjeżdżają.
set -u
WP="docker exec strona_wp_wordpress wp"
URL="http://localhost:8890/wp-admin/admin-ajax.php"
MAIL="dk1-proba-audytu@example.invalid"

PROG=$($WP eval 'echo (int) CHATL_LIMIT_PULAPKA;' --allow-root)
LICZ_W='global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zgloszenia WHERE podejrzane = 1");'
LICZ_EP='global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"pulapka\" AND sciezka = \"pytanie\"");'
LICZ_EZ='global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"pulapka\" AND sciezka = \"zgloszenie\"");'
CZYSC_KUBEL='global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE \"pulapka|%\"");'

# Czyste pole: własne zdarzenia pułapki i świeży kubeł (jak sprzątanie pulapka-test).
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"pulapka\"");' --allow-root
$WP eval "$CZYSC_KUBEL" --allow-root
L0=$($WP eval "$LICZ_W" --allow-root); EP0=$($WP eval "$LICZ_EP" --allow-root); EZ0=$($WP eval "$LICZ_EZ" --allow-root)
N=$(curl -s -X POST "$URL" -d "action=chatl_nonce" | sed 's/.*"nonce":"\([^"]*\)".*/\1/')
echo "start: zdarzen pytanie=$EP0 zgloszenie=$EZ0   wierszy podejrzanych=$L0   prog kubla=$PROG/min"

echo ""
echo "== A. 6 PYTAN z wypelniona pulapka: zdarzenia sciezka=pytanie do progu, ZERO wierszy =="
for i in $(seq 1 6); do
  curl -s -o /dev/null -X POST "$URL" -d "action=chatl_pytanie&nonce=$N&sesja=aaaaaaaa&pytanie=dk1-$i&chatl_pole_dodatkowe=bot"
done
EP1=$($WP eval "$LICZ_EP" --allow-root); L1=$($WP eval "$LICZ_W" --allow-root)
OCZ_A=$(( 6 < PROG ? 6 : PROG ))
echo "po A: zdarzenia pytanie +$((EP1-EP0)) (oczekiwane $OCZ_A)   wierszy +$((L1-L0)) (oczekiwane 0)"

echo ""
echo "== B. 8 ZGLOSZEN z wypelniona pulapka (swiezy kubel): zdarzenie i wiersz CO DO SZTUKI =="
$WP eval "$CZYSC_KUBEL" --allow-root
for i in $(seq 1 8); do
  curl -s -o /dev/null -X POST "$URL" \
    -d "action=chatl_zgloszenie&nonce=$N&sesja=aaaaaaaa&email=$MAIL&wiadomosc=dk1-$i&zgoda=1&chatl_pole_dodatkowe=bot"
done
EZ2=$($WP eval "$LICZ_EZ" --allow-root); L2=$($WP eval "$LICZ_W" --allow-root)
OCZ_B=$(( 8 < PROG ? 8 : PROG ))
echo "po B: zdarzenia zgloszenie +$((EZ2-EZ0)) (oczekiwane $OCZ_B)   wierszy +$((L2-L1)) (oczekiwane $OCZ_B)"

echo ""
echo "RAZEM: pasek panelu policzy wiersze podejrzane ($((L2-L0)) nowych) i osobno powie"
echo "       o $((EP1-EP0)) zadzialaniach z pytan — zadnego 'znajdziesz na liscie' bez pokrycia."

# sprzatanie: wiersze probne, wlasne zdarzenia, wlasne kubly
$WP eval "
global \$wpdb;
\$ile = \$wpdb->delete( \$wpdb->prefix . 'chatl_zgloszenia', array( 'email' => '$MAIL' ) );
\$wpdb->query( \"DELETE FROM {\$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = 'pulapka'\" );
\$wpdb->query( \"DELETE FROM {\$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE 'pulapka|%'\" );
echo 'sprzatanie: skasowano ' . (int) \$ile . ' wierszy probnych, zdarzenia i kubly pulapki wyczyszczone' . \"\n\";" --allow-root

BLEDY=0
[ "$((L1-L0))" = "0" ]            || { echo "BLAD: pytania utworzyly wiersze zgloszen"; BLEDY=1; }
[ "$((EP1-EP0))" = "$OCZ_A" ]     || { echo "BLAD: zdarzenia z pytan $((EP1-EP0)) zamiast $OCZ_A"; BLEDY=1; }
[ "$((EZ2-EZ0))" = "$((L2-L1))" ] || { echo "BLAD: sciezka zgloszenia — zdarzen $((EZ2-EZ0)), wierszy $((L2-L1)): ROZJAZD"; BLEDY=1; }
[ "$((L2-L1))" = "$OCZ_B" ]       || { echo "BLAD: wierszy $((L2-L1)) zamiast $OCZ_B (kubel nie dziala jak w projekcie)"; BLEDY=1; }

if [ "$BLEDY" -eq 0 ]; then
  echo "WYNIK: OK — zdarzenia i lista zgodne z projektem co do sztuki."; exit 0
fi
echo "WYNIK: PADA — zrodla prawdy pulapki rozjechane z projektem."
exit 1
