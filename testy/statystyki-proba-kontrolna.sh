#!/bin/bash
# Proba kontrolna testu statystyk (zasada D14: test bez kalibracji nic nie
# znaczy — trzeba pokazac, ze w ogole POTRAFI paść).
#
# Podklada po jednym celowym bledzie w KAZDEJ z czterech rzeczy, ktore ten
# modul obiecuje, i sprawdza, ze testy/statystyki-test.php za kazdym razem
# PADA na wlasciwym kroku. Na koniec sprawdza, ze czysty kod przechodzi —
# inaczej "pada zawsze" tez byloby bezuzyteczne.
#
# Uzycie: bash testy/statystyki-proba-kontrolna.sh     (z katalogu repo)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_W=$CEL/includes/statystyki.php
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/statystyki-test.php "$KONTENER:/tmp/statystyki-test.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/statystyki-test.php 2>&1
}

# probuj "opis" "sed-podkladajacy-blad" "fragment-kroku-ktory-ma-paść"
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  docker exec "$KONTENER" sed -i "$mutacja" "$PLIK_W"
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   test PADL na: %-46s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD test NIE zlapal: %-42s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy test statystyk widzi podlozone bledy ==="

# 1. Grupowanie przestaje skracac slowa do rdzeni — po polsku to zabija
#    sklejanie odmian ("koszt dostawy" i "ile kosztuje dostawa" rozjezdzaja sie).
probuj "rdzenie wylaczone (porownanie calych slow)" \
  's/mb_substr( \$slowo, 0, max( 1, (int) \$dlugosc ) )/$slowo/' \
  "rdzen skraca odmiane\|rdzeń skraca odmianę"

# 2. Prog scalania podniesiony do 100% — scalaja sie tylko pytania identyczne.
probuj "prog scalania podniesiony do 100%" \
  "s/const CHATL_LUKI_PROG = 50;/const CHATL_LUKI_PROG = 100;/" \
  "5 pytan schodzi do 2 tematow\|5 pytań schodzi do 2 tematów"

# 3. Skutecznosc zaczyna wliczac wiersze sprzed 1.3.0 (te BEZ znacznika) —
#    czyli panel karze wlasciciela za dane, ktorych nikt nie zmierzyl.
probuj "wiersze bez znacznika wliczone do skutecznosci" \
  "s/\$s\['odpowiedziano'\] + \$s\['bez_odpowiedzi'\] );/\$s['odpowiedziano'] + \$s['bez_odpowiedzi'] + \$s['nieoznaczone'] );/" \
  "zwrocona skutecznosc\|zwrócona skuteczność"

# 4. Raport luk czyta wiersze BOTA zamiast wierszy GOSCIA — wtedy wlasciciel
#    dostaje liste wlasnych tekstow awaryjnych zamiast pytan, ktorych nie zna.
probuj "raport luk czyta odpowiedzi bota zamiast pytan" \
  "s/rola = 'gosc' AND trafiono = 0/rola = 'bot' AND trafiono = 0/g" \
  "trafily do 2 tematow\|trafiły do 2 tematów\|nie jest trescia ODPOWIEDZI\|nie jest treścią ODPOWIEDZI"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — test pada zawsze, wiec nic nie znaczy"
  BLEDY=$((BLEDY+1))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "TEST WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY"
else
  echo "TEST SLEPY NA $BLEDY PODLOZONYCH BLEDOW"
fi
exit $((BLEDY > 0))
