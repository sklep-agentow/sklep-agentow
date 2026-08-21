#!/usr/bin/env bash
# STRAŻNIK DK-1: licznik pułapki (opcja chatl_pulapka) i lista zgłoszeń
# podejrzanych (kolumna chatl_zgloszenia.podejrzane) opisują TO SAMO zdarzenie.
# Pasek w panelu mówi: "Pułapka zadziałała N raz(y). Złapane zgłoszenia SĄ
# ZAPISANE — zobaczysz je pod filtrem podejrzane." Ten test sprawdza, czy N
# faktycznie odpowiada temu, co właściciel znajdzie pod filtrem.
# EXIT 0 = zgodne, EXIT 1 = dwa źródła prawdy się rozjeżdżają.
set -u
WP="docker exec strona_wp_wordpress wp"
URL="http://localhost:8890/wp-admin/admin-ajax.php"
MAIL="dk1-proba-audytu@example.invalid"

KOPIA=$($WP eval 'echo json_encode( get_option("chatl_pulapka") );' --allow-root)
LICZ='global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zgloszenia WHERE podejrzane = 1");'
L0=$($WP eval "$LICZ" --allow-root)
C0=$($WP eval 'echo (int) ((array) get_option("chatl_pulapka"))["ile"];' --allow-root)
N=$(curl -s -X POST "$URL" -d "action=chatl_nonce" | sed 's/.*"nonce":"\([^"]*\)".*/\1/')
echo "start: licznik chatl_pulapka.ile=$C0   wierszy podejrzanych=$L0"

echo ""
echo "== A. 6 PYTAN do bota z wypelniona pulapka (zgloszenia nie powstaje w ogole) =="
for i in $(seq 1 6); do
  curl -s -o /dev/null -X POST "$URL" -d "action=chatl_pytanie&nonce=$N&sesja=aaaaaaaa&pytanie=dk1-$i&chatl_pole_dodatkowe=bot"
done
C1=$($WP eval 'echo (int) ((array) get_option("chatl_pulapka"))["ile"];' --allow-root)
L1=$($WP eval "$LICZ" --allow-root)
echo "po A: licznik +$((C1-C0))   wierszy podejrzanych +$((L1-L0))"

echo ""
echo "== B. 8 ZGLOSZEN z wypelniona pulapka (sufit zapisu to CHATL_LIMIT_PULAPKA=5/min) =="
for i in $(seq 1 8); do
  curl -s -o /dev/null -X POST "$URL" \
    -d "action=chatl_zgloszenie&nonce=$N&sesja=aaaaaaaa&email=$MAIL&wiadomosc=dk1-$i&zgoda=1&chatl_pole_dodatkowe=bot"
done
C2=$($WP eval 'echo (int) ((array) get_option("chatl_pulapka"))["ile"];' --allow-root)
L2=$($WP eval "$LICZ" --allow-root)
echo "po B: licznik +$((C2-C1))   wierszy podejrzanych +$((L2-L1))"

echo ""
echo "RAZEM: pasek w panelu powie o $((C2-C0)) zadzialaniach pulapki,"
echo "       a pod filtrem 'podejrzane' wlasciciel znajdzie $((L2-L0)) nowych zgloszen."

# sprzatanie
$WP eval "
global \$wpdb;
\$ile = \$wpdb->delete( \$wpdb->prefix . 'chatl_zgloszenia', array( 'email' => '$MAIL' ) );
if ( '$KOPIA' === 'false' ) { delete_option('chatl_pulapka'); }
else { update_option('chatl_pulapka', json_decode('$KOPIA', true), false); }
echo 'sprzatanie: skasowano ' . (int) \$ile . ' wierszy probnych, chatl_pulapka=' . json_encode(get_option('chatl_pulapka')) . \"\n\";" --allow-root

if [ "$((C2-C0))" = "$((L2-L0))" ]; then
  echo "WYNIK: OK — licznik i lista mowia to samo."; exit 0
fi
echo "WYNIK: PADA — licznik i lista rozjezdzaja sie o $(( (C2-C0) - (L2-L0) ))."
exit 1
