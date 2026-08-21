# DOWODY DEKLARACJI — boty (20.08.2026, noc)

## 🔴 ZNALEZISKO KRYTYCZNE: tryb AI NIE DZIAŁA w żadnym z botów

**Kiedy:** 20.08.2026 ~02:05, pierwszy raz w historii projektu odpalono tryb AI na ŻYWYM kluczu.
**Dlaczego dopiero teraz:** nie mieliśmy klucza Anthropic — tryb AI był obietnicą bez ani jednego
wywołania na żywo. Dokładnie ta dziura, którą wskazały krytyki (4 modele + 3 naszych krytyków).

**Objaw:** bot odpowiada z bazy reguł także wtedy, gdy klucz jest wpisany i limit niewyczerpany.
Wygląda, jakby działał — bo zjazd do reguł maskuje awarię.

**Przyczyna (plik:linia):**
- `wtyczki/chatbot-live/includes/ai.php:133` — `'output_config' => array( 'effort' => 'low' )`
- `wtyczki/segregator-maila/includes/ai.php:118` — to samo (KLASA, nie egzemplarz)

Model `claude-haiku-4-5-20251001` nie obsługuje tego pola. API odrzuca całe żądanie.

**Surowy dowód — wywołanie funkcji produktu na stacku:**
```
HTTP: 400
BODY: {"type":"error","error":{"type":"invalid_request_error",
       "message":"This model does not support the effort parameter."},
       "request_id":"req_011CeD2J63eEddDzfg5MELid"}
```

**PRÓBA KONTROLNA (to samo zapytanie, jedyna różnica = sporne pole):**
```
$ curl ... -d '{"model":"claude-haiku-4-5-20251001","max_tokens":50,...}'          → HTTP:200
$ curl ... -d '{... ,"output_config":{"effort":"low"}, ...}'                        → HTTP:400
```
Klucz działa (osobny test: HTTP 200, odpowiedź modelu „Tak."), kontener ma dostęp do api.anthropic.com
(HTTP 401 na puste żądanie = połączenie jest, brak autoryzacji).

**Co to znaczy dla produktu:** klient wpisuje własny klucz, płaci za wywołania, a bot cicho
odpowiada z reguł. Obietnica „tryb AI" ze strony produktu i instrukcji jest MARTWA.
Klasa błędu: cicha awaria maskowana mechanizmem awaryjnym (nasza przyczyna-matka H).

**Status:** przekazane builderowi jako zadanie Z0 (przed wszystkimi innymi).

---

# P1.2 — WERYFIKACJA DEKLARACJI: ChatBot LIVE

**Kiedy:** 20.08.2026, noc. **Kto:** builder (chatbot-live).
**Po co:** trzy krytyki niezależnie napisały „deklarujecie rzeczy, których nikt nie sprawdził".
Poniżej **surowe wyjścia komend**, nie streszczenia. Wykonane na stacku warsztatowym
(`strona_wp_wordpress`, WordPress 6.9.4 / PHP 8.4 / MariaDB 11.8) na kodzie z gałęzi
`boty/runda-finalna` po naprawach Z0–Z2 i P1–P2.

> **Zabezpieczenie danych:** test czystej deinstalacji kasuje wszystko, co wtyczka
> ma w bazie. Przed testem zrobiono zrzut 5 tabel `wp_chatl_*` (20 210 B) oraz opcji
> (`chatl_ustawienia`, `chatl_klucz_api`, `chatl_wersja_schematu`); po teście stan
> właściciela przywrócono i sprawdzono (potwierdzenie na końcu sekcji).

---

## 1. Czysta deinstalacja — diff PUSTY

Trzy zdjęcia stanu: **przed instalacją** (po pełnym odinstalowaniu), **po instalacji**,
**po odinstalowaniu z zaznaczoną opcją „usuń wszystkie dane"**.

### 1) PRZED INSTALACJĄ
```
$ wp plugin deactivate chatbot-live
Success: Deactivated 1 of 1 plugins.
$ wp plugin uninstall chatbot-live
Success: Uninstalled 1 of 1 plugins.

--- TABELE chatl ---
--- OPCJE chatl ---
--- CRONY chatl ---
--- KATALOG WTYCZKI ---
BRAK
```

### 2) PO INSTALACJI I AKTYWACJI
```
$ wp plugin activate chatbot-live
Success: Activated 1 of 1 plugins.

--- TABELE chatl ---
wp_chatl_ai_uzycie
wp_chatl_qa
wp_chatl_rate_limit
wp_chatl_rozmowy
wp_chatl_zgloszenia
--- OPCJE chatl ---
chatl_wersja_schematu
--- CRONY chatl ---
chatl_czyszczenie_historii	1 day
--- KATALOG WTYCZKI ---
JEST
```
5 tabel, 1 opcja, 1 zdarzenie cron — dokładnie tyle, ile deklaruje dokumentacja.

### 3) PO ODINSTALOWANIU (opcja „usuń wszystkie dane" ZAZNACZONA)

Przed odinstalowaniem celowo dołożono komplet opcji, żeby było co sprawdzać:
```
$ wp option list --search='chatl*' --field=option_name
chatl_blad_maila
chatl_klucz_api
chatl_ustawienia
chatl_wersja_schematu

$ wp plugin deactivate chatbot-live
Success: Deactivated 1 of 1 plugins.
$ wp plugin uninstall chatbot-live
Success: Uninstalled 1 of 1 plugins.

--- TABELE chatl ---
--- OPCJE chatl ---
--- CRONY chatl ---
--- KATALOG WTYCZKI ---
BRAK
```

### DIFF
```
$ diff snap-przed.txt snap-po-deinstalacji.txt
DIFF PUSTY — nic nie zostalo po wtyczce
```
**Wynik: deklaracja prawdziwa.** Po odinstalowaniu z czyszczeniem nie zostaje ani jedna
tabela, opcja czy zdarzenie cron. Klucz API znika **zawsze** (kasowany niezależnie od tej opcji).

---

## 2. Retencja 90 dni — cron JEST rejestrowany, wyrejestrowywany i faktycznie kasuje

### 2a. Cykl życia zdarzenia
```
--- stan przy wtyczce AKTYWNEJ ---
chatl_czyszczenie_historii	2026-08-21 00:36:34	1 day
--- po DEAKTYWACJI ---
Success: Deactivated 1 of 1 plugins.
(brak zdarzen chatl — POPRAWNIE)
--- po PONOWNEJ AKTYWACJI ---
Success: Activated 1 of 1 plugins.
chatl_czyszczenie_historii	1 day
```

### 2b. Czy naprawdę kasuje — podłożony wpis sprzed 91 dni
```
podlozono 2 wpisy
sesja                  utworzono             dni_temu
test-retencja-stary    2026-05-21 00:36:33   91
test-retencja-swiezy   2026-08-20 02:36:33   0

--- PRZED odpaleniem crona ---
wierszy_w_historii
2
--- odpalam zdarzenie recznie ---
$ wp cron event run chatl_czyszczenie_historii
Executed the cron event 'chatl_czyszczenie_historii' in 0.007s.
Success: Executed a total of 1 cron event.
--- PO odpaleniu crona ---
wierszy_w_historii
1
sesja                  dni_temu
test-retencja-swiezy   0
```
**Wynik: deklaracja prawdziwa.** Wpis sprzed 91 dni skasowany, dzisiejszy nietknięty.
(Dodatkowo: strona „Historia rozmów" w panelu woła tę samą funkcję przy wejściu —
samonaprawa na wypadek, gdyby WP-cron nie odpalił z braku ruchu na stronie.)

---

## 3. Zjazd do reguł przy złym kluczu — bot NIE wysypuje się

```
$ wp option update chatl_klucz_api 'sk-ant-KLUCZ-CELOWO-ZLY-000' --autoload=no
tryb AI wlaczony (jest klucz + limit): TAK

$ curl -X POST .../admin-ajax.php -d 'action=chatl_pytanie&...&pytanie=ile+trwa+u+was+wdrozenie'
{"success":true,"data":{"trafiono":true,"odpowiedz":"Standardowy termin realizacji to
3–5 dni roboczych od potwierdzenia zamówienia. Przy większych zleceniach termin
ustalamy indywidualnie."}}
HTTP=200

rola   silnik   tresc
gosc   reguly   ile trwa u was wdrozenie
bot    reguly   Standardowy termin realizacji to 3–5 dni roboczych od potwie

--- wpis w logu WP ---
[20-Aug-2026 00:38:02 UTC] PHP Warning:  chatl_ai_odpowiedz(): wywołanie API nie powiodło
się, bot odpowiada z reguł (HTTP 401: {"type":"error","error":{"type":"authentication_error",
"message":"API key is invalid."},"request_id":null}) in /var/www/html/wp-includes/functions.php
on line 6170
```
**Wynik: deklaracja prawdziwa.** Gość dostaje odpowiedź (HTTP 200), silnik przełącza się na
reguły. **Nowe od Z0:** awaria zostawia ślad w logu WP — bez tego dokładnie ten mechanizm
maskował martwy tryb AI (znalezisko z góry tego pliku).

---

## 4. Limit dzienny AI — po przekroczeniu blokuje wywołania

```
limit dzienny w ustawieniach: 50
zuzyto dzis: 2
tryb AI dostepny: TAK

-- po podlozeniu licznika rownego limitowi --
zuzyto dzis: 50 / 50
tryb AI dostepny: NIE

rola   silnik   tresc
gosc   reguly   ile trwa u was wdrozenie
bot    reguly   Standardowy termin realizacji to 3–5 dni roboczych od p
```
Licznik podłożono wprost do tabeli — udowadnianie limitu przez wykonanie 50 prawdziwych
wywołań byłoby wydawaniem pieniędzy klienta na potwierdzenie arytmetyki.

**Drugi limit (per IP na dobę, P1.4)** — sprawdzony osobno:
```
stala CHATL_AI_LIMIT_IP_NA_DOBE = 20
ostatnie wywolanie PRZEPUSZCZONE: #20
pierwsze ZABLOKOWANE: #21
kubel 10/min dla pytan i zgloszen nietkniety: TAK (dziala)

klucz                                            licznik  okno_do              teraz
ai|20685|f528764d624db129b32c21fbca0cb8d6        25       2026-08-22 00:00:00  2026-08-20 00:22:57
chat|29786422|f528764d624db129b32c21fbca0cb8d6   1        2026-08-20 00:24:00  2026-08-20 00:22:57
```
**Wynik: deklaracja prawdziwa** dla obu limitów. W obu przypadkach gość dostaje odpowiedź
z reguł, a nie błąd — czat nie przestaje działać.

---

## Przywrócenie stanu właściciela po testach

```
$ wp plugin activate chatbot-live
Success: Activated 1 of 1 plugins.
tabele przywrocone z kopii
Success: Updated 'chatl_ustawienia' option.
Success: Updated 'chatl_klucz_api' option.

par_qa      14
zgloszen     4
wymian      52
usun_dane (przywrocone do stanu wlasciciela): 0
adres polityki: https://automaticai.pl/polityka-prywatnosci
klucz API dziala: TAK (...jAAA)
tryb AI dostepny: TAK
autoload klucza: off
chatl_czyszczenie_historii	1 day
```
Po przywróceniu skasowano jeszcze wiersze testowe (sesje `dowod-*`, `test-retencja-*`);
baza Q&A właściciela (14 par) i jego historia rozmów zostały nietknięte.

---

## ✅ DOWÓD: tryb AI ODPOWIEDZIAŁ GOŚCIOWI na ścieżce widgetu (21.08, pomiar wsteczny)

**Pytanie, na które to odpowiada** (padnie przy oddaniu): *czy ktokolwiek widział odpowiedź
z PRAWDZIWEGO modelu na ścieżce gościa, czy cały czas mierzyliśmy atrapy i zjazd do reguł?*

**Odpowiedź: TAK — i to nie jest założenie, tylko rozumowanie przez wykluczenie
oparte na trzech niezależnych pomiarach.**

### 1. Ślad w bazie: 20 odpowiedzi bota z silnikiem `ai`

```
$ wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE silnik=\"ai\"");'
20                                  # w 11 sesjach, 2026-08-20 14:14–15:08
```

Treści to **trzy różne parafrazy tej samej informacji** („Sprzedajemy dwie wtyczki
WordPress: — **ChatBot LIVE** – 149 zł…", „Mamy dwie wtyczki…", „ChatBot LIVE kosztuje
**149,00 zł**, a Segregator maila **199,00 zł**…") plus sentinel „nie wiem" na pytanie
spoza wiedzy firmy. **Atrapa zwraca zawsze ten sam napis** — trzy różne sformułowania
tej samej treści nie mogą z niej pochodzić.

### 2. Wykluczenie atrapy: żaden test nie łączy endpointu z atrapą

Wiersz `rola='bot', silnik='ai'` powstaje **wyłącznie** w gałęzi AI endpointu
`chatl_ajax_pytanie` — `wtyczki/chatbot-live/includes/front.php:461` (sentinel „nie wiem")
i `:471` (odpowiedź modelu). Innego miejsca zapisu w kodzie nie ma.

⭐ **Najkrótszy dowód wykluczenia to PUSTE PRZECIĘCIE dwóch zbiorów** — jedno polecenie,
odtwarzalne wprost:

```
$ grep -rl "pre_http_request" testy/ | xargs grep -lE "chatl_pytanie|chatl_ajax_pytanie"
                                    # (brak trafień — ani jeden plik nie jest w obu zbiorach)
```

Zbiory osobno, dokładnie tak, jak zwracają je te polecenia:

```
$ grep -rl "pre_http_request" testy/        # testy z ATRAPĄ API — 4 pliki
testy/blad-klucza-test.php
testy/pamiec-rozmowy-test.php
testy/tokeny-koszt-test.php
testy/wybor-modelu-test.php
    → wszystkie cztery wołają chatl_ai_odpowiedz() WPROST, żaden nie dotyka endpointu

$ grep -rl "chatl_pytanie" testy/           # testy dotykające ENDPOINTU pytania — 3 pliki
testy/nonce-cache-test.js
testy/przejecie-test.php
testy/wskaznik-pisania-test.js
    → w żadnym z nich nie ma pre_http_request
```

⚠️ **Uwaga o czwartym pliku, żeby ten zapis się nie rozjechał z rzeczywistością.**
`testy/pulapka-test.php` też zawiera napis `chatl_ajax_pytanie`, ale **nie łapie go
powyższy grep** (szuka `chatl_pytanie`, a tam stoi `chatl_ajax_pytanie`) — i słusznie,
bo ten test **nie wywołuje** tego endpointu: czyta jego ŹRÓDŁO wyrażeniem regularnym
(`pulapka-test.php:238`), żeby sprawdzić KOLEJNOŚĆ warunków, a wywołuje endpoint
**zgłoszenia kontaktu** (`chatl_ajax_zgloszenie()`, linia 138). Atrapy również nie ma.
Wniosek się nie zmienia — zmienia się to, że polecenie wyżej daje dokładnie tę listę,
którą tu widzisz.

**Czyli: wiersz `silnik='ai'` nie ma innego źródła niż prawdziwe HTTP 200 od dostawcy.**

### 3. Zużycie liczone DOPIERO po HTTP 200 — i ono też jest niezerowe

`chatl_ai_zuzyj()` woła się po sprawdzeniu kodu 200 (`includes/ai.php`, komentarz
„Kod 200 znaczy, ze dostawca wywolanie WYKONAL i je POLICZYL"), więc tabela zużycia
nie zapisuje ani jednego nieudanego wywołania:

```
$ wp eval '... SELECT * FROM wp_chatl_ai_uzycie'
2026-08-20 | 74 wywołania | 6264 tokenów wejścia  | 217 wyjścia
2026-08-21 | 56 wywołań   | 40696 tokenów wejścia | 1471 wyjścia
```

### 4. To była PRZEGLĄDARKA, nie skrypt

Wszystkie 11 sesji z odpowiedziami AI ma format **UUID**. Identyfikator sesji w tym
formacie generował `crypto.randomUUID()` **w przeglądarce gościa** — w `assets/widget.js`
do PR #95, po którym sesję wydaje serwer (dziś 32 znaki, inny format):

```
$ git log --oneline -S "randomUUID" -- wtyczki/chatbot-live/assets/widget.js
dcb2437 Pozycja A: przejecie rozmowy przez czlowieka (1.5.0, schemat 6) (#95)
7111fff feat: chatbot-live P1 — szkielet + tryb regułowy
```

### Jak to sprawdzić CUDZĄ ręką (wąskie okno recenzenta)

Powyższe liczby wyjęte są `wp eval`-em, do którego recenzent dostępu nie ma. Żeby dowód
dało się powtórzyć bez wiary na słowo, `narzedzia-qa/stos.sh` (tylko-do-odczytu, biała
lista tabel) umie od 1.2.0 liczyć **z warunkiem**:

```
$ bash narzedzia-qa/stos.sh wiersze-gdzie wp_chatl_rozmowy.silnik=ai
=== SELECT COUNT(*) FROM wp_chatl_rozmowy WHERE silnik = ai ===
  wierszy: 20

$ bash narzedzia-qa/stos.sh wiersze wp_chatl_ai_uzycie
  wierszy: 2                       # dwa dni: 2026-08-20 i 2026-08-21
```

⚠️ To są liczby **ze stanu bazy warsztatowej w dniu pomiaru**. Jeśli czytasz to później,
policz jeszcze raz — liczba wierszy `silnik=ai` może tylko rosnąć albo zniknąć razem
z bazą (`wp db reset`), ale **nie może zmaleć** przy uczciwej pracy stanowiska.

### ⛔ CZEGO TEN DOWÓD NIE OBEJMUJE — i to jest ważniejsze niż on sam

**Dowód dotyczy wersji SPRZED zamrożenia.** Od commita zamrożenia 1.5.7
(`3e4858e`, 21.08 05:07) w bazie **nie ma ani jednego wiersza `silnik='ai'`** —
wszystkie dzisiejsze są `reguly`. Dla **audytowanej** wersji 1.5.7 dowodu „AI odpowiada
gościowi" **NIE MA** i nie da się go zrobić bez ważnego klucza (patrz niżej).

### Dlaczego dziś się tego nie powtórzy: klucz stanowiska jest nieważny

Rozpoznanie dziury **D3** z dowodu z paczki (#119) — „ścieżka AI żyje w PHP, ale na
ścieżce gościa nie wchodzi". Zmierzone po kolei, bez zgadywania:

| hipoteza | pomiar | werdykt |
|---|---|---|
| nonce odrzuca żądanie | pełna ścieżka `chatl_nonce` → `chatl_sesja` → `chatl_pytanie` przez `admin-ajax.php`: `success:true`, oba wiersze rozmowy zapisane | ODPADA |
| kubeł limitu na IP zjedzony | `ai\|<doba>\|<md5 IP>` → licznik **1** przy progu `CHATL_AI_LIMIT_IP_NA_DOBE = 20` | ODPADA |
| warunek przed wywołaniem | `chatl_ai_uzycie_dzis()` = **56** ≥ `ai_limit_dzienny` = **50** → `chatl_ai_dostepny()` = false, AI nie jest nawet próbowane | **TRAFIONA** |
| …a po podniesieniu limitu? | nadal reguły → znacznik `chatl_blad_ai` = `{"kod":401,"tresc":"authentication_error … API key is invalid."}` | **DRUGA PRZYCZYNA** |

`chatl_ai_odpowiedz()` wołane **wprost w PHP też zwraca NULL** z tym samym 401 — czyli
teza „wprost działa, przez AJAX nie" dziś się nie potwierdza; klucz stanowiska jest
nieważny dla obu dróg.

**Werdykt D3: wada stanowiska, nie bota.** Produkt zachował się dokładnie tak, jak ma:
spróbował AI, dostał odmowę dostawcy, zjechał do reguł bez wywracania czatu i **zostawił
ślad widoczny dla właściciela** (`chatl_blad_ai` — to naprawa L4 działająca na żywo).
Wyczerpanie limitu dziennego znacznika nie zapala, bo to nie awaria; właściciel widzi to
w Ustawieniach: „Dzisiaj zużyto: 56 / 50 wywołań. Po wyczerpaniu limitu bot do północy
odpowiada z reguł" (`includes/admin.php:887`).

⚠️ **Dopóki na stanowisku siedzi nieważny klucz, KAŻDY test trybu AI mierzy ścieżkę
awaryjną, nie AI.** Ważny klucz to decyzja właściciela zlecenia (płatne API).

### Pułapka pomiarowa, przez którą to zgłoszenie wyglądało na wadę produktu

Zgłoszenie brzmiało „kolumna `silnik` zostaje PUSTA, żaden wiersz bota się nie zapisuje".
Tabela `wp_chatl_rozmowy` ma kolumnę **`rola`**, nie `kto` — a `$wpdb->get_results()`
z nieistniejącą kolumną zwraca **pustą tablicę, nie błąd**. Pusty wynik przy 252 wierszach
w tabeli czyta się wtedy jako „nic się nie zapisuje". Przed ogłoszeniem takiej wady:
`SHOW COLUMNS FROM <tabela>` albo `SELECT *` na jednym wierszu i `$wpdb->last_error`.

---

## Czego ta sekcja NIE obejmuje

- **Segregator maila** — przeszedł do buildera 2 (gałąź `boty/segregator-naprawy`);
  jego deklaracje wymagają osobnego przebiegu tych samych czterech testów.
- **Zachowanie przy WP-cronie wyłączonym** (`DISABLE_WP_CRON`) — nie sprawdzone.
  Wtedy retencję niesie samonaprawa przy wejściu na „Historię rozmów", ale nie zmierzono tego.
- **Skala** — testy na próbce warsztatowej (14 par Q&A, 52 wymiany), nie na dużym ruchu.
