# Audyt PRZEBIEG 2 — ChatBot LIVE, cięcie PO TERENIE

- `commit_head`: **a2eb00e** (`origin/main` w chwili startu; zamówienie mówiło
  o 3fc884d, ale main przesunął się od tamtej pory — pracowałem na tym, co jest
  na `origin/main`, i to ten SHA odtwarza wyniki niżej).
- Gałąź: `audyt/przebieg2-chatbot`.
- Teren: **wyłącznie** `wtyczki/chatbot-live/`. Segregator i sklep nietknięte.
- Stos: Docker, WordPress 7.0.4 / PHP 8.4, `http://localhost:8890`,
  wtyczka aktywna w wersji 1.5.8, `includes/ai.php` w kontenerze zgodny co do
  sumy MD5 z repozytorium (`352123eb49a62fbbe9e8a8f595743a0d`).

## Czystość pomiaru (praca w ciemno)

⛔ **Nie otworzyłem żadnego z plików objętych zakazem** — ani `audyt/boty/RAPORT-*.md`,
ani `audyt/REJESTR-ZNALEZISK.md`, ani `audyt/boty/WERYFIKACJA-ZNALEZISK.md`,
ani `audyt/boty/material/06-mapa-polowania.md`, ani `TABLICA.md`, ani opisów
PR-ów z prefiksem `audyt/`. Nie czytałem też materiałów dozwolonych (01–05, 07,
ściągi egzaminatora) — cała runda poszła z kodu, dokumentacji produktu i CLAUDE.md.
Pomiar jest nieskażony.

Jedyna wiedza o cudzych ustaleniach, jaką miałem, pochodzi z listy czterech
zmian w locie w `WSPOLNE-ZASADY.md` (#133, #135, #136, #137). Trafiłem na jedną
z nich w kodzie i opisuję to niżej jako pozycję **znaną**, nie jako znalezisko.

## Jak ciąłem pracę

Warstwami, w kolejności z zamówienia: wejście publiczne → panel → warstwa AI →
dane i cykl życia. Każde znalezisko po trafieniu przeszukiwałem jako KLASĘ
(sweep po całej wtyczce), nie jako egzemplarz.

**Stan stosu przywrócony.** Wszystkie sondy, które musiały coś zapisać, mają
w wyniku wypisane sprzątanie: opcje `chatl_blad_nonce` i `chatl_pulapka` wróciły
do wartości sprzed próby, wiersze testowe RODO skasowane (potwierdzone licznikiem
`0 / 0 / 0`). Niczego nie instalowałem ani nie odinstalowywałem, bazy nie czyściłem.

---

# ZNALEZISKA

## 🐞 Z1 — „Przetestuj klucz" testuje INNY model niż ten, którym pyta bot, i przy okazji gasi ostrzeżenie o awarii AI

**Warstwa:** 3 (AI). **Plik:** `wtyczki/chatbot-live/includes/ai.php:486`.

`chatl_ai_test_klucza()` buduje żądanie z `apply_filters( 'chatl_ai_model', CHATL_AI_MODEL )`,
czyli ze **stałej produktu**, całkowicie pomijając ustawienie właściciela `ai_model`.
Prawdziwe pytanie gościa idzie przez `chatl_ai_model()` (`ai.php:590`), które
to ustawienie honoruje. Skutek dla właściciela, który wpisał własny model
(pole „własny model" istnieje w ustawieniach i jest reklamowane w instrukcji):

1. bot naprawdę pyta modelem, którego dostawca nie zna → HTTP 404 → cichy zjazd
   do reguł, znacznik `chatl_blad_ai` postawiony, czerwony pasek w panelu;
2. właściciel klika **Przetestuj klucz** — przycisk pyta `claude-haiku-4-5`,
   dostaje 200 i melduje **„Klucz działa"**, w dodatku wypisując nazwę modelu,
   którego wtyczka wcale nie używa;
3. na ścieżce 200 leci `chatl_odnotuj_sukces_ai()` (`ai.php:513`), które
   **kasuje `chatl_blad_ai`** — czyli jedyny pasek mówiący, że tryb AI nie działa.

Diagnostyka nie tylko kłamie, ale **kasuje alarm**. Własny komentarz funkcji
deklaruje wprost: „sprawdzamy klucz, dostępność API i **nazwę modelu**" —
i to jest nieprawda, bo sprawdza nazwę domyślną, nie ustawioną.

### Odtworzenie (nic nie wychodzi na zewnątrz, zero zapisów do bazy poza próbą znacznika, przywróconą)

```
$ docker exec strona_wp_wordpress wp eval-file /tmp/proba-model.php --allow-root
ustawienie wlasciciela (ai_model): model-ktorego-dostawca-nie-zna
chatl_ai_model() [tym pyta bot]:   model-ktorego-dostawca-nie-zna
model w ZWYKLYM wywolaniu bota:    model-ktorego-dostawca-nie-zna
znacznik chatl_blad_ai PRZED:      JEST (pasek widoczny)
model w 'Przetestuj klucz':        claude-haiku-4-5
wynik przycisku:                   OK / Klucz działa — API odpowiedziało poprawnie (model: claude-haiku-4-5).
znacznik chatl_blad_ai PO:         SKASOWANY (pasek znikl)
sprzatanie, chatl_blad_ai wrocil do: array ( 'kiedy' => '2026-08-21 19:29:33', 'kod' => 0, ... )

WYNIK: PADA — przycisk testuje INNY model niz bot.
```

Sonda `audyt/boty/proba-model.php` (⭐ leży W REPO — wjechała w PR #147; wcześniej ten wiersz odsyłał do scratchpada i twierdził, że sondy nie ma) (klucz i ustawienia podstawione filtrami
w pamięci, `pre_http_request` przechwytuje wywołania — do api.anthropic.com nic
nie poszło).

**Przypadek BEZ wady w tej samej próbie:** pierwsze trzy linie pokazują, że
zwykła ścieżka bota **poprawnie** bierze model z ustawień. Wada jest wyłącznie
w przycisku diagnostycznym — naprawa nie ma prawa ruszyć ścieżki gościa.

**Sweep klasy (drugi egzemplarz nie znaleziony):** przeszedłem wszystkie miejsca
budujące żądanie do API — są dwa (`chatl_ai_zapytaj()` i `chatl_ai_test_klucza()`)
i tylko test rozjeżdża się ze ścieżką produkcyjną. Sprawdziłem też odwrotny
kierunek klasy „diagnostyka kasuje alarm": `chatl_odnotuj_wysylke_powiadomienia()`
zdejmuje `chatl_blad_maila` dopiero po **prawdziwej** udanej wysyłce — tam jest OK.

---

## 🐞 Z2 — opcja `chatl_pulapka` przeżywa PEŁNE odinstalowanie, a dokumentacja twierdzi, że różnica jest pusta

**Warstwa:** 4 (cykl życia). **Plik:** `wtyczki/chatbot-live/uninstall.php` (brak
`delete_option`), zapis w `includes/front.php:696`.

Wtyczka zapisuje osiem własnych opcji. `uninstall.php` na ścieżce pełnego
czyszczenia (`usun_dane = 1`) kasuje siedem. Ósma — `chatl_pulapka`, licznik
zadziałań pułapki na boty wraz z datą ostatniego zadziałania — zostaje w
`wp_options` po odinstalowaniu wtyczki. To dokładnie klasa nr 1 z CLAUDE.md
(„uninstall niekompletny — zostają opcje").

Dokumentacja techniczna produktu, rozdział **6.5 Deinstalacja — dwie ścieżki**,
mówi dwie rzeczy, które kod obala:
- „`delete_option` dla **siedmiu** opcji" — a wtyczka zapisuje osiem;
- „różnica stanu tabel, opcji i zdarzeń cron przed instalacją i po odinstalowaniu
  ze ścieżką B — **pusta**". Nie jest pusta.

Do tego trafia klasa nr 3 z CLAUDE.md: dokumentacja kłamie względem kodu.

### Odtworzenie — test PADA na kodzie sprzed naprawy

Sonda `audyt/boty/proba-uninstall.php` (⭐ leży W REPO — wjechała w PR #147) stawia atrapę WordPressa (własne
`get_option`/`delete_option`/`$wpdb`), zapełnia `wp_options` kompletem ośmiu
opcji wtyczki, ustawia `usun_dane = 1`, wykonuje **prawdziwy `uninstall.php`**
z repozytorium i wypisuje, co zostało. Żadna prawdziwa baza nie jest dotykana.

```
$ docker exec strona_wp_wordpress php /tmp/proba-uninstall.php
Opcje wtyczki POZOSTALE w wp_options po pelnym odinstalowaniu:
  - chatl_pulapka = array (  'ile' => 12,  'ostatnia' => '2026-08-21 10:00:00',  'powod' => 'ukryte pole',)
WYNIK: PADA — 1 opcji wtyczki przezylo odinstalowanie.
EXIT=1
```

### Kalibracja — ten sam test PRZECHODZI na kodzie po naprawie

```
$ docker exec ... sed -i "…dopisz delete_option( 'chatl_pulapka' );…" /tmp/uninstall.php && php /tmp/proba-uninstall.php
Opcje wtyczki POZOSTALE w wp_options po pelnym odinstalowaniu:
  (zadnych)
WYNIK: OK
EXIT=0
```

Test rozróżnia oba stany, więc jest dowodem, a nie ozdobą. Pokrywa też przypadek
BEZ wady: siedem pozostałych opcji znika poprawnie i test tego nie zgłasza.

**Sweep klasy:** wypisałem wszystkie klucze przekazywane do
`update_option`/`add_option`/`set_transient`/`update_user_meta` w całej wtyczce
i zestawiłem z `uninstall.php`. Poza `chatl_pulapka` komplet się zgadza:
`chatl_ustawienia`, `chatl_klucz_api`, `chatl_wersja_schematu`, `chatl_blad_ai`,
`chatl_blad_maila`, `chatl_blad_nonce`, `chatl_blad_bazy`,
`chatl_rodo_sesje_wspolne` — kasowane; usermeta `chatl_powitanie_zamkniete` —
kasowana dla wszystkich (`$delete_all = true`); transienty `chatl_rl_*`
i `chatl_import_*` — kasowane z `esc_like()`. Jedyny wyjątek świadomy i
nieszkodliwy: transient `chatl_po_aktywacji` żyje 60 sekund i wygasa sam.
**Sześć tabel z `dbDelta` = sześć `DROP TABLE` — symetria zachowana.**

---

## 🐞 Z3 — limit „10 wiadomości na minutę" NIE obowiązuje na dwóch ścieżkach, na których gość-bot ZAPISUJE do `wp_options`

**Warstwa:** 1 (wejście publiczne). **Pliki:** `includes/front.php:223-231`
(`chatl_wymagaj_nonce()` → `chatl_odnotuj_odrzucony_nonce()`, `front.php:199`)
oraz `includes/front.php:372` (`chatl_odnotuj_pulapke()`, `front.php:696`).

Kubeł limitu (`chatl_limit_ip_ok()`, `front.php:387`) stoi **za** dwoma
bezwarunkowymi zapisami do `wp_options` wykonywanymi na żądanie kogoś
niezalogowanego:

1. **zły nonce** — `chatl_wymagaj_nonce()` jest pierwszą instrukcją obu
   endpointów gościa; przy odrzuceniu robi `get_option` + `update_option`
   na `chatl_blad_nonce` i dopiero potem kończy żądanie. Limitu tu nie ma
   w ogóle;
2. **pole-pułapka** — w `chatl_ajax_pytanie()` pułapka jest sprawdzana
   **przed** limitem (to jest w kodzie decyzją opisaną komentarzem), a
   `chatl_odnotuj_pulapke()` znowu robi `get_option` + `update_option`.

Efekt jest odwrotny do zamierzonego: mechanizm postawiony po to, by **bot nie
zalewał bazy**, daje botowi ścieżkę **tańszą niż uczciwemu gościowi**. Uczciwy
gość jest ucinany na dziesiątym żądaniu; skrypt z zepsutym noncem albo z
wypełnioną pułapką przechodzi bez ograniczeń, a każde jego żądanie to zapis
do `wp_options`. Dotyka to również klasy nr 7 z CLAUDE.md (licznik trzymany
w `wp_options` zamiast we własnej tabeli — a tabela `chatl_rate_limit` z
atomowym inkrementem już w tej wtyczce istnieje).

Instrukcja produktu (`INSTRUKCJA.md`, sekcja FAQ o limitach) mówi wprost:
„limit to 10 wiadomości na minutę z jednego adresu IP (dotyczy pytań i zgłoszeń
łącznie)". Dla obu ścieżek wyżej to nieprawda.

### Odtworzenie — 30 żądań przy progu 10

```
$ for i in $(seq 1 30); do curl -s -o /dev/null -w "%{http_code} " -X POST \
    http://localhost:8890/wp-admin/admin-ajax.php \
    -d "action=chatl_pytanie&nonce=zepsuty&sesja=aaaaaaaa&pytanie=test$i"; done
403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403 403
$ wp eval 'echo (int) ((array) get_option("chatl_blad_nonce"))["ile"];'
licznik chatl_blad_nonce.ile = 30
```

30 z 30 przeszło, ani jednego 429, licznik urósł o 30 → **30 zapisów do
`wp_options`** z żądania niezalogowanego.

```
$ N=$(curl -s -X POST …admin-ajax.php -d "action=chatl_nonce" | …)   # poprawny nonce
pulapka.ile PRZED = 154
$ for i in $(seq 1 30); do curl … -d "action=chatl_pytanie&nonce=$N&sesja=aaaaaaaa&pytanie=test$i&chatl_pole_dodatkowe=bot"; done
200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200 200
pulapka.ile PO   = 184
```

Znowu 30 z 30 i 30 zapisów.

### Próba kontrolna (przypadek BEZ wady) — limiter DZIAŁA na ścieżce zwykłej

```
$ for i in $(seq 1 15); do curl … -d "action=chatl_pytanie&nonce=$N&sesja=aaaaaaaa&pytanie=kontrola$i"; done
403 403 403 403 403 403 403 403 403 403 429 429 429 429 429
```

Dokładnie dziesięć żądań, potem 429 — czyli kubeł jest sprawny, a próba
odróżnia ścieżkę wadliwą od zdrowej. Naprawa nie może zabrać limitu ścieżce
kontrolnej ani wyciszyć samych znaczników (właściciel ma prawo wiedzieć
o odrzucanych nonce'ach) — chodzi o to, by **licznik nie był zapisem na
żądanie anonima bez limitu**.

Stan przywrócony: `chatl_blad_nonce` skasowany (przed próbą nie istniał),
`chatl_pulapka` z powrotem `{"ile":154,"ostatnia":"2026-08-21 19:26:12", …}`.

---

## 🐞 Z4 — paczka RODO przypisuje wiadomości KONSULTANTA i komunikaty systemowe botowi

**Warstwa:** 4 (dane). **Plik:** `wtyczki/chatbot-live/includes/rodo.php:262`.

Eksporter danych osobowych wypisuje autora wiersza jako
`'gosc' === $r['rola'] ? 'Gość' : 'Bot'`. Od czasu wprowadzenia przejęcia rozmowy
przez człowieka w `chatl_rozmowy` są **cztery** role: `gosc`, `bot`, `operator`,
`system`. Dwie ostatnie lądują w paczce jako „Bot".

To nie jest kosmetyka: paczka z kreatora RODO jest dokumentem wydawanym osobie,
która złożyła wniosek z art. 15. Wychodzi z niej, że z gościem rozmawiał wyłącznie
automat, podczas gdy naprawdę odpowiadał mu imiennie wskazany człowiek — a sama
wtyczka w widgecie kładzie nacisk na to, żeby gość WIEDZIAŁ, komu pisze
(`ludzkiPasek`: „Rozmawiasz z człowiekiem…"). Eksport mówi coś przeciwnego niż
okno czatu.

### Odtworzenie

```
$ docker exec strona_wp_wordpress wp eval-file /tmp/proba-rodo.php --allow-root
Paczka RODO wydana wnioskodawcy (grupa 'Historia rozmowy z botem'):
  Kto: Gość | Treść: Dzien dobry, ile kosztuje przeglad?
  Kto: Bot    | Treść: Nie znam odpowiedzi.
  Kto: Bot    | Treść: Do rozmowy dolaczyl konsultant.
  Kto: Bot    | Treść: Tu Anna z serwisu, przeglad to 250 zl.

sprzatanie: pozostalo wierszy rozmow=0 zgloszen=0 sesji=0
```

Wiersz pierwszy pokrywa przypadek BEZ wady: rola `gosc` jest opisana poprawnie,
więc naprawa nie ma prawa jej ruszyć. Dane testowe skasowane (licznik `0/0/0`).

---

# ✅ CO SPRAWDZIŁEM I JEST CZYSTE

Każda pozycja to sprawdzenie z komendą, nie wrażenie z lektury.

| # | Co | Jak sprawdzone | Wynik |
|---|---|---|---|
| C1 | Nonce **i** uprawnienia PARĄ na każdej mutacji panelu | `grep` po `current_user_can` / `check_admin_referer` / `check_ajax_referer` we wszystkich plikach panelu; ręczna kontrola 15 punktów `admin_post_*` i 1 `wp_ajax_*` | ✅ komplet — każdy punkt ma `manage_options` **i** nonce, w tej kolejności |
| C2 | `permission_callback` przy `register_rest_route` | `grep register_rest_route -A10` | ✅ jest jawny (`__return_true`), a kontrola dostępu siedzi w środku: walidacja sesji + sekret z ciastka `HttpOnly` + `hash_equals` + limit na sesję |
| C3 | Funkcja autoryzująca bez „domyślnego otwarcia" | lektura `chatl_sesja_uprawniona()` (`przejecie.php`) | ✅ brak wiersza, brak sekretu, brak ciastka → **odmowa**; nie ma gałęzi „stare sesje przepuszczamy" |
| C4 | `$wpdb->prepare()` przy każdym SQL z wejściem | `grep` wszystkich `wpdb->query/get_var/get_row/get_results/get_col` bez `prepare` | ✅ 12 trafień, wszystkie to SQL **stały**, bez ani jednej wartości z zewnątrz |
| C5 | `LIKE` bez `esc_like()` (klasa nr 2 z CLAUDE.md) | `grep -rn LIKE … \| grep -v esc_like` | ✅ **zero** trafień — oba `LIKE` w `uninstall.php` mają `esc_like()` + `prepare()` |
| C6 | Escape na wyjściu panelu (treści od gości w wp-admin) | `grep` po `echo`/`printf` z `$` z odfiltrowaniem `esc_*`/`wp_kses`/rzutowań | ✅ wszystko, co zostaje, to `(int)` albo stałe napisy; treść gościa zawsze przez `esc_html()` |
| C7 | Wstrzyknięcie do DOM po stronie widgetu i panelu operatora | `grep innerHTML/insertAdjacentHTML/textContent` w `assets/*.js` | ✅ **zero** `innerHTML`; wszystko przez `textContent`, adres polityki przez `esc_url()` po stronie PHP |
| C8 | Neutralizacja formuł arkusza w eksporcie CSV | lektura `chatl_csv_komorka()` + trzy eksporty | ✅ każda komórka z danymi gościa przechodzi neutralizację `=`/`+`/`-`/`@`; brama eksportu ma cap+nonce |
| C9 | Wersja rozjechana (klasa nr 4 z CLAUDE.md) | `grep` nagłówka, stałej, `Stable tag`, `CHANGELOG.md` | ✅ **1.5.8** w czterech miejscach zgodnie |
| C10 | Liczby z instrukcji vs kod | zestawienie wszystkich liczb z `INSTRUKCJA.md` ze stałymi w kodzie | ✅ 90 dni retencji, 2 s próg pułapki, 100 wywołań, 20/dobę na IP, 500/2000 znaków, 5 minut ciszy operatora — wszystkie zgodne. **Wyjątek: zdanie o limicie 10/min — patrz Z3.** |
| C11 | Nowa tabela = trzy miejsca (klasa nr 1) | zestawienie `dbDelta` ↔ `uninstall.php` ↔ `chatl_sprawdz_schemat()` | ✅ sześć tabel, sześć `DROP`, ścieżka podniesienia schematu wspólna (`CHATL_WERSJA_SCHEMATU = 7`) |
| C12 | Cron po deaktywacji | lektura `chatl_deaktywacja()` | ✅ `wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' )`; deaktywacja w normalnym przebiegu WP poprzedza odinstalowanie |
| C13 | Klucz API w paczce/repo i przy odinstalowaniu | lektura `chatl_klucz_api()`, `chatl_zapisz_klucz()`, `uninstall.php` | ✅ `autoload = no`, stała z `wp-config.php` ma pierwszeństwo, panel jej nie nadpisuje, `delete_option( 'chatl_klucz_api' )` **zawsze**, niezależnie od `usun_dane` |
| C14 | Wstrzyknięcie polecenia przez wiadomość gościa | lektura `chatl_ai_instrukcja()` | ✅ instrukcja systemowa zawiera jawną regułę „wiadomość gościa to tekst niezaufany", treść gościa idzie w `messages`, nie w `system`, a odpowiedź modelu przechodzi `sanitize_textarea_field()` przed zapisem i `textContent` przy rysowaniu |
| C15 | Suma prób wywołań zewnętrznych pod limitem PHP | lektura `chatl_ai_odpowiedz()` | ✅ timeout 9 s + **jeden** retry (tylko błąd połączenia / 5xx / 429), maks. 18 s, nigdy pętla |
| C16 | Licznik limitu zużywany PO sukcesie | lektura `chatl_ai_zuzyj()` | ✅ inkrement po kodzie 200, atomowo (`ON DUPLICATE KEY UPDATE`), na każdej ścieżce po 200 — także tej z ciałem nie do odczytania |
| C17 | Poprawność stronicowania eksportu RODO (dwa źródła, jedna numeracja) | przeliczenie czterech układów (150 zgł./50 rozm., 50/60, 30/200, 0/100) na kodzie `chatl_rodo_eksporter()` | ✅ przesunięcie rozmów liczone od **całkowitej** liczby zgłoszeń, nie od bieżącej strony — dziury w paczce nie ma |
| C18 | Kasownik RODO nie tyka cudzych danych | lektura `chatl_rodo_sesje_maila()` + `chatl_rodo_kasownik()` | ✅ sesja współdzielona jest wyłączana z eksportu **i** z kasowania, ślad zapisywany PRZED kasowaniem, komunikat podaje prawdziwe liczby |
| C19 | Walidacja zakresu dat w statystykach | lektura `chatl_okres_z_zadania()` + `chatl_data_ok()` | ✅ `checkdate()`, biała lista `7/30/90`, zamiana `od`↔`do` przy odwróceniu; wartości idą przez `prepare()` |
| C20 | Zgoda RODO sprawdzana po stronie serwera | lektura `chatl_ajax_zgloszenie()` | ✅ brak `zgoda` = 400 **przed** `INSERT`-em; checkbox z przeglądarki nie jest jedyną bramką |

## Pozycja ZNANA (nie zgłaszam jako nową)

- `chatl_czyszczenie_historii()` kasuje wiersze z `chatl_rozmowy` i wygasłe okna
  `chatl_rate_limit`, ale **nie tyka `chatl_sesje`** — tabela sesji rośnie bez
  końca. Trafiłem na to w warstwie 4; wedle `WSPOLNE-ZASADY.md` pokrywa to
  zgłoszenie **#135** (`chatl_sesje` bez żadnej ścieżki kasowania), więc zostawiam
  jako znane. Dokładam jedno spostrzeżenie, którego nie umiem przypisać do #135
  bez zajrzenia w jego opis (a zajrzeć nie wolno): retencja kasuje `chatl_rozmowy`
  po 90 dniach, więc po tym czasie w `chatl_sesje` zostają wiersze **bez ani
  jednej wiadomości** — jeśli naprawa #135 kasuje sesje po `aktywnosc`, warto
  sprawdzić, czy nie tnie sesji wciąż mających rozmowy, i odwrotnie.

## ⚠️ ANOMALIE (nie umiem odtworzyć — nie podaję jako znalezisk)

- **`chatl_przejmij()` zwraca `true` bezwarunkowo** (`przejecie.php`), mimo że
  `chatl_sesja_aktywnosc()` jest dziś **wyłącznie `UPDATE`** (komentarz nad
  wywołaniem — „Sesja mogła jeszcze nie mieć wiersza" — pochodzi z czasów, gdy
  było tam `INSERT … ON DUPLICATE KEY`). Gdyby wiersza sesji nie było, panel
  wypisałby „Rozmowa przejęta", gość dostałby komunikat „dołączył konsultant",
  a bot dalej by odpowiadał. **Nie znalazłem drogi, którą operator dociera do
  sesji bez wiersza** — lista żywych rozmów czyta `FROM chatl_sesje`, a jedyne
  miejsce zakładania sesji zawsze stawia wiersz. Zostawiam jako anomalię:
  nieaktualny komentarz nad kodem, który nie sprawdza wyniku operacji.
- **Komentarz w `rodo.php` twierdzi, że plik ładuje się wyłącznie w panelu**
  („rodo.php ładuje się wyłącznie w panelu (`is_admin()`)"), a `require_once`
  dla `includes/rodo.php` stoi w `chatbot-live.php` w bloku **bezwarunkowym**,
  poza `if ( is_admin() )`. Nie umiem pokazać skutku wykonawczego (funkcje RODO
  odpalają się i tak tylko z kreatora w panelu), więc nie jest to znalezisko —
  ale przesłanka, na której oparto decyzję projektową („przycinanie przy zapisie,
  a nie w cronie, bo cron tej funkcji nie zobaczy"), jest nieprawdziwa.

---

# CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Kategorie, na które trafiłem, a które nie mieszczą się w cięciu „warstwami po
terenie". To materiał na następne soczewki.

1. **Zachowanie pod równoległością.** Limiter jest atomowy (`ON DUPLICATE KEY
   UPDATE` + `LAST_INSERT_ID`), ale liczniki stanu (`chatl_pulapka`,
   `chatl_blad_nonce`, `chatl_blad_bazy`, ślad RODO `chatl_rodo_sesje_wspolne`)
   to `get_option` → `update_option`, czyli **read-modify-write bez blokady**.
   Kod sam to przyznaje przy śladzie RODO. Nie mierzyłem, ile inkrementacji ginie
   pod obciążeniem — soczewka „wyścigi i utrata zapisów" jest pusta.
2. **Fail-open limitera.** `chatl_rate_limit()` przy awarii licznika (`$ile === 0`)
   zwraca `true`, czyli **przepuszcza**. Jest to udokumentowana decyzja, ale nikt
   nie zmierzył, co się dzieje, gdy tabela `chatl_rate_limit` zniknie albo
   przestanie przyjmować zapisy: cała ochrona przed spamem znika po cichu,
   a jedynym śladem jest pasek błędu bazy, który tu **nie jest wołany**.
3. **Zachowanie przy pustym/uszkodzonym schemacie.** `chatl_sprawdz_schemat()`
   podnosi schemat na `plugins_loaded`, ale nikt nie sprawdza wyniku `dbDelta()`.
   Co widzi gość, gdy `chatl_sesje` nie powstanie (np. brak uprawnień do CREATE)?
   Ta ścieżka nie jest przetestowana ani przeze mnie, ani — sądząc z kodu — nigdzie.
4. **Wydajność panelu przy dużym ruchu.** `chatl_zywe_rozmowy()` to jedno zapytanie
   z **trzema skorelowanymi podzapytaniami** na wiersz przy 50 wierszach; brak
   indeksu pokrywającego `(sesja, rola, id)` w `chatl_rozmowy`. Nie mierzyłem
   planu zapytania. Podobnie `chatl_ai_instrukcja()` wciąga **całą** aktywną bazę
   Q&A do każdego pytania — koszt rośnie liniowo z bazą klienta.
5. **Dostępność (WCAG) i zachowanie widgetu w przeglądarce.** Panel liczy kontrast
   kolorów, ale nie sprawdzałem nawigacji klawiaturą, pułapki fokusu w oknie czatu
   ani czytnika ekranu. `free -m` pokazał 130 MB wolnego, więc świadomie nie
   uruchamiałem przeglądarki.
6. **Ścieżka importu CSV od strony pliku.** Otworzyłem `import.php` tylko pod kątem
   uprawnień i superglobali. Nie sprawdzałem, co robi plik z BOM-em, z separatorem
   średnikowym, z pojedynczym wierszem bez nagłówka ani z 5 MB w jednej komórce.
   Zmiana #133 dotyka instrukcji importu — warto zestawić instrukcję z zachowaniem
   parsera.
7. **Zgodność z zasadami katalogu WordPress.org.** Sprawdziłem licencję (GPL-2.0-or-later,
   `LICENSE` obecny) i brak śladów wewnętrznych, ale nie przeszedłem osiemnastu
   wytycznych katalogu — a produkt jest sprzedawany, więc część z nich (nazewnictwo,
   „powered by", zbieranie danych bez zgody) mogłaby mieć znaczenie przy publikacji.
8. **Co się dzieje po stronie gościa, gdy ciastka pierwszej strony są zablokowane.**
   Kod deklaruje ścieżkę ratunkową („widget wykrywa po `kod: sesja`, prosi o nową
   sesję i ponawia raz"), ale nie sprawdziłem jej w prawdziwej przeglądarce z
   wyłączonymi ciastkami. To jest jedyna ścieżka, na której produkt świadomie
   przestaje działać — i nikt jej nie zmierzył.

---

# CZEGO NIE ZDĄŻYŁEM

- Nie przeszedłem `includes/import.php` (744 linie) ani `includes/statystyki.php`
  (820 linii) w całości — tylko punkty wejścia, uprawnienia, SQL i escape.
  Logika parsera CSV i logika raportu luk pozostają niesprawdzone.
- Nie sprawdziłem `includes/wyglad.php` ani `includes/powitanie.php` poza
  uprawnieniami i escapem.
- Nie uruchamiałem przeglądarki (RAM: 130 MB wolnego przy starcie rundy),
  więc żadna warstwa nie ma dowodu „z ekranu".
- Nie zestawiłem `DIAGRAM-PRZEPLYWU.svg` z rzeczywistym przepływem.

Budżet: 55 kroków narzędziowych, zużyte ~40. Zatrzymałem się na pisaniu raportu
i bramkach, nie na wyczerpaniu limitu.
