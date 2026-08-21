# 02 — FAKTY ŚRODOWISKA (ZMIERZONE, nie z nagłówków)

> Wszystko niżej zmierzone na działającej instancji 2026-08-20. ⛔ Nie przepisuj z nagłówka
> wtyczki ani z dokumentacji — jeśli Twoje znalezisko zależy od wersji, **zmierz ją sam**
> tą samą komendą i wklej surowe wyjście.

## Stack (Docker, ta maszyna)
| Co | Wartość ZMIERZONA | Czym zmierzone |
|---|---|---|
| WordPress | **7.0.4** | `wp core version` |
| PHP | **8.4.21** | `php -r 'echo PHP_VERSION;'` |
| Kontener WP | `strona_wp_wordpress`, port **8890** | `docker ps` |
| Kontener bazy | `strona_wp_db` | — (brak klienta `mysql` w kontenerze WP — do zapytań używaj `wp db query`) |
| Poczta | `sklep_mailpit`, panel **:8025** | — |
| Wtyczki aktywne | `chatbot-live 1.2.1`, `segregator-maila 1.2.1`, `sklep-agentow 0.9.0` | `wp plugin list` |

## ⛔ NA JAKIM KODZIE AUDYTUJESZ — sprawdź to PIERWSZE
Audyt odbywa się na **konkretnym commicie gałęzi `main`**, którego SHA masz w zleceniu.
⛔ **NIE audytuj katalogów roboczych builderów** (`/root/praca/zlecenia/sklep-agentow`,
`…-boty`) — one stoją na gałęziach roboczych i bywają o godziny w tyle.
Sprawdź, zanim cokolwiek zgłosisz:
```bash
git log --oneline -1                 # gdzie jestem
git merge-base --is-ancestor <SHA-audytu> HEAD && echo OK || echo "STARY KOD"
```
**Zmierzone 20.08:** świeży oceniający dostał ode mnie ścieżkę do drzewa buildera
i zgłosił jako KRYTYCZNY brak funkcji, która była na `main` od godziny (4 pliki
kontra 0 na jego gałęzi). Sam to wykrył i uczciwie opisał — ale to była **wada
materiału, nie produktu**. Nie powtórz tego.

## ⛔ NAJWAŻNIEJSZE OSTRZEŻENIE POMIAROWE
**Adres witryny (`siteurl`/`home`) jest PRZESTAWIONY na publiczny tunel:**
`https://linda-refrigerator-potato-objective.trycloudflare.com`
→ **testy po `http://localhost:8890` NIE są miarodajne** (canonical redirect na tunel).
Wszystkie żądania HTTP kieruj na adres tunelu albo najpierw sprawdź `wp option get siteurl`.
Jeśli Twój pomiar wyszedł dziwnie — **pierwsza hipoteza: trafiłeś w localhost**.

## Jak dostać się do środka
```bash
# WP-CLI w kontenerze (jako root wtyczki):
docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html <komenda>
# Jako www-data (tak działa prawdziwy WordPress — RÓŻNICA BYWA ISTOTNA):
docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html <komenda>
# SQL:
docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html db query "SELECT ..."
# Wgranie zmienionej wtyczki na stack (NIE ma wolumenu — trzeba kopiować!):
docker cp wtyczki/<nazwa>/. strona_wp_wordpress:/var/www/html/wp-content/plugins/<nazwa>/
docker exec strona_wp_wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/<nazwa>
```
Hasło administratora stacka: `/root/.claude/sklep-admin-roboczy.env` (600, format
`WP_ADMIN_USER` / `WP_ADMIN_PASS`; ładuj `set -a; . plik; set +a`).

## Klucz AI do testów na żywo
`/root/.claude/sekrety-anthropic.env` (600, poza repo). Model używany przez boty:
`claude-haiku-4-5`. ⛔ **Klucz jest do skasowania po testach** — nie kopiuj go nigdzie,
nie wklejaj do plików w repo, nie zapisuj w znaleziskach.

---

## ⛔ TEGO NIE ZGŁASZAJ (to nie są błędy — to świadome decyzje albo stan poligonu)

1. **Brak podpięcia prawdziwej skrzynki (IMAP/OAuth) w Segregatorze** — świadoma decyzja
   zamawiającego (D19). Bot działa na próbkach CSV. To jest w zamówieniu.
2. **Brak czatu z żywym operatorem / pamięci między wizytami w ChatBocie** — jw., granica
   klepnięta w szkicach.
3. **Brak automatycznego stemmingu polskich odmian** — v1 ma dopasowanie podłańcuchów
   i pole „warianty". Rozbudowa po egzaminie, jawnie zapisana.
4. **Brak pola „priorytet" przy regułach Segregatora** — świadomy wybór (jeden mechanizm
   rozstrzygania: kolejność na liście).
5. **Tryb testowy Stripe w sklepie** — sklep nie jest przedmiotem tego audytu.
6. **Adres witryny ustawiony na tunel** — stan poligonu, opisany wyżej.
7. **`SPRZATANIE-RAPORT.md` w katalogu repo (nieśledzony)** — znany dług, czeka na decyzję.
8. **Wersja wtyczek `1.2.1` w paczkach** — po naprawach idzie bump do `1.2.2`; jeśli
   audytujesz kod po naprawach, rozbieżność paczka↔kod jest znana i zaplanowana.
   ⚠️ ALE: jeśli po bumpie zip nadal ma starą wersję **w chwili oddania** — to jest błąd.

## ⚠️ CZEGO NIE WOLNO ZROBIĆ NA TYM POLIGONIE
- ⛔ Nie kasuj bazy, tabel ani wtyczek. Testy destrukcyjne → na kopii tabeli.
- ⛔ Nie ubijaj procesów `pkill -f` po wzorcu (raz już ubiło cudzy proces `stripe listen`).
- ⛔ Nie zmieniaj kodu produktu. **Audyt NIE naprawia** (zasada Z11 przepisu).
- ⛔ Nie commituj niczego. Twoje wyjście to raport, nie zmiana w repo.
