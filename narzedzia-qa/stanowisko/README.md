# Stanowisko warsztatowe — co jest ustawione i czym to sprawdzić

Stanowisko (`strona_wp_wordpress` na :8890, `sklep_mailpit` na :8025) stoi z definicji
`/root/praca/zlecenia/strona-wp/docker-compose.yml`. Trzy rzeczy poniżej były do 5.09
źródłem POMIARÓW, KTÓRYCH NIE DAŁO SIĘ ZROBIĆ (znaleziska `I-25`, `I-26`, `I-27`).

## 1. WP-Cron (I-25) — spust jest ZEWNĘTRZNY

Przyczyna, nie objaw: `siteurl` to `http://localhost:8890`, a wewnątrz kontenera na 8890
nikt nie słucha (apache stoi na :80). Pętla zwrotna po `wp-cron.php` kończy się odmową
połączenia — **żaden ruch tego nie naprawi**:

    docker exec strona_wp_wordpress sh -c 'curl -s -o /dev/null -w "%{http_code}" --max-time 5 http://localhost:8890/wp-cron.php'
    # 000, curl exit 7

Ustawione:
- `DISABLE_WP_CRON` = true w `wp-config.php` (wolumen `wp_core`) i w `WORDPRESS_CONFIG_EXTRA`
  w compose (dla stosu stawianego od zera);
- `/etc/cron.d/stanowisko-wp-cron` → `/usr/local/sbin/wp-cron-stanowisko.sh` co minutę
  (instaluje `narzedzia-qa/stanowisko/instaluj-spust-crona.sh`, źródło prawdy jest w repo).

Sprawdzenie — liczy zdarzenia z `next_run_relative=now`, czyli zaległe W TEJ CHWILI (oczekiwane **0**);
komenda NIE zna wieku zaległości, więc nie mierzy „starsze niż N minut” (nazwane uczciwie po #289 W5, klasa 11):

    [ "$(docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html cron event list --fields=hook,next_run_relative --format=csv | grep -c ',now$')" = 0 ]

⛔ Nie samo `… | grep -c ',now$'` jako przejdź/padnij: `grep -c` przy ZERZE trafień kończy kodem 1, więc stan zdrowy
dawałby EXIT 1, a chory EXIT 0 (recenzja #293 WAŻNA 1). Porównanie liczby z `0` ma kod 0 = zdrowy, 1 = zaległe.

Próba kontrolna (wada = wyjęty wpis crona): `mv /etc/cron.d/stanowisko-wp-cron /tmp/` →
zaplanuj zdarzenie w przeszłości (`wp cron event schedule proba "-300 seconds"`) → po 140 s
NADAL wisi (FAIL). Po przywróceniu wpisu znika w ≤ 60 s (PASS). Zmierzone 5.09: 140 s z wadą,
54 s bez wady.

⚠️ Gdy wpis crona zniknie, zadania staną CAŁKIEM i po cichu — `DISABLE_WP_CRON` zdejmuje
drugą drogę. Dlatego sprawdzenie wyżej należy do warunków wstępnych pomiarów na tym stosie.

## 2. Limity uploadu (I-26)

Obraz dawał `upload_max_filesize=2M`, `post_max_size=8M`, a paczka klienta waży dziś
4 859 567 B (0.9.40; `ls -l boty/paczki/sklep-agentow-0.9.40.zip`) — droga instalacyjna z `dokumentacja/INSTRUKCJA-INSTALACJI.md` Krok 1
(Kokpit → Wtyczki → Dodaj nową → Wyślij na serwer) nie miała prawa się udać.
Definicja: `strona-wp/wp/php-stanowisko.ini` montowany do `/usr/local/etc/php/conf.d/`.

    docker exec strona_wp_wordpress php -i | grep -E '^upload_max_filesize|^post_max_size'
    # upload_max_filesize => 64M => 64M ; post_max_size => 64M => 64M

## 3. Trwała baza łapacza maili (I-27)

`sklep_mailpit` chodził jako ręczny `docker run` z bazą w `/tmp` WEWNĄTRZ kontenera —
restart kasował dowody po cichu (98 wiadomości z 1.09 przepadło, zanim ktokolwiek zajrzał).
Teraz to usługa w compose: `MP_DATABASE=/data/mailpit.db` + wolumen `mailpit_data`.

    curl -s http://127.0.0.1:8025/api/v1/info   # Database: /data/mailpit.db

Próba kontrolna (5.09): łapacz BEZ wolumenu — 1 wiadomość przed restartem, 0 po (FAIL);
łapacz z wolumenem — 2 przed, 2 po (PASS).

## 4. wp-cli przeżywa odtworzenie kontenera

`wp` leżał wgrany ręcznie w warstwie kontenera — pierwsze `docker compose up` po zmianie
definicji zabrało go razem z warstwą i `stos.sh`/`test-php.sh` przestały działać
(komunikat: executable file not found in $PATH). Teraz montuje się z hosta: `./wp/bin/wp`
(phar wyjęty z obrazu `wordpress:cli-php8.4`).
