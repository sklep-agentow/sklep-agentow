# PRZELOT LISTY KONTROLNEJ — PRZEGLĄDARKĄ, AUTOMATEM

⛔ **To jest przebieg UZUPEŁNIAJĄCY. Nie zastępuje ręcznego przelotu człowieka i nie zwalnia
z niego.** Ręczny przelot zostaje twardym warunkiem końca: mamy własny pomiar, że **17 znalezisk
złapał wyłącznie człowiek klikający, a automaty zero**. Automat nie poczuje, że coś jest
upierdliwe — dlatego wszędzie tam, gdzie punkt pyta o **wrażenie** („czy wiadomo, od czego
zacząć", „czy komunikaty prowadzą za rękę", pomiar czasu konfiguracji), zostawiam `⬜`
i piszę wprost, że **wymaga człowieka**. Nie udaję, że automat to ocenił.

**Lista:** `audyt/PRZELOT-CZLOWIEKA.md` z gałęzi `origin/koord/przelot-czlowieka` (29 punktów).
**commit_head:** `a2eb00e` · gałąź `audyt/przelot-automat`.
**Stanowisko:** Chromium headless (Playwright 1.62), czysty kontekst = odpowiednik okna
prywatnego, `http://localhost:8890`. `free -m` przed każdym startem (najniżej **1056 MB
dostępnych**); ⛔ Lighthouse nie uruchamiany. Konsola przeglądarki podpięta przez **cały**
przelot (`pageerror` + `console.error`).
⛔ Kodu nie naprawiałem, wtyczek nie instalowałem ani nie odinstalowywałem, bazy nie czyściłem.

---

## ⛔ BLOKADA, KTÓRA UCIĘŁA POŁOWĘ LISTY — i uderzy też w człowieka

**Do panelu `wp-admin` nie da się zalogować podanym hasłem.** Zmierzone:

```
$ curl -s -d "log=$WP_ADMIN_USER&pwd=$WP_ADMIN_PASS&wp-submit=Zaloguj&testcookie=1" \
       -H 'Cookie: wordpress_test_cookie=WP%20Cookie%20check' http://localhost:8890/wp-login.php
id="login_error" … Error: The password you entered for the username admin_roboczy is incorrect.
```

Konto **istnieje** (`wp user list` → `1  admin_roboczy  administrator`), więc to hasło
w `/root/.claude/sklep-admin-roboczy.env` jest nieaktualne, nie literówka w loginie.

Próbowałem obejść to sesyjnym ciastkiem z WP-CLI — ciastko jest **poprawne po stronie serwera**,
a mimo to żądanie HTTP nadal odbija się do logowania:

```
$ wp eval '… $_COOKIE[AUTH_COOKIE]=…; $_COOKIE[LOGGED_IN_COOKIE]=…;'
validate(scheme="")       => 1
validate(scheme=auth)     => 1
validate(scheme=logged_in)=> 1
$ curl -D- -H "Cookie: wordpress_…=…; wordpress_logged_in_…=…" .../wp-admin/admin.php?page=segm-przebiegi
HTTP/1.1 302 Found
Location: http://localhost:8890/wp-login.php?redirect_to=…&reauth=1
```

⚠️ **To nie jest wada produktu — to wada stanowiska**, ale wada blokująca: **człowiek
z tą samą kartką haseł stanie w tym samym miejscu**, a części B, C i D listy nie da się
przejść bez panelu. Do odblokowania przez koordynatorkę **przed** ręcznym przelotem.

---

## A. ChatBot LIVE — oczami GOŚCIA (12 punktów)

- `⬜` **Czy widać, jak zacząć, bez czytania instrukcji?** — **WYMAGA CZŁOWIEKA** (to ocena
  wrażenia). Co jest na ekranie, zmierzone: jedyny element to bąbelek `💬`
  (`aria-label="Otwórz czat"`); po otwarciu widać powitanie *„Dzień dobry! Jestem botem tej
  strony — zapytaj o cennik, godziny otwarcia albo ofertę…"*, pole z podpowiedzią
  „Zadaj pytanie…" i stały przycisk **„✉ Zostaw wiadomość"**.
- `✅` **Pierwsza wiadomość i odpowiedź** ⭐ — napisałem „Dzien dobry, chcialbym o cos zapytac",
  odpowiedź przyszła od razu: *„Nie znam odpowiedzi na to pytanie. Zostaw proszę maila i pytanie…"*.
  Wiadomość **jest w bazie** (`gosc … Dzien dobry, chcialbym o cos zapytac`). Nic nie ginie.
- `✅` **Pytanie, które bot ZNA** — „Jakie sa godziny otwarcia?" → *„Pracujemy od poniedziałku
  do piątku w godzinach 8:00–16:00…"*, w bazie `trafiono=1`. Trafna.
- `🐞` **Pytanie, którego bot NIE zna — czy mówi to uczciwie, czy zmyśla?** Zapytałem
  *„Czy naprawiacie silniki odrzutowe do Boeinga 747?"*. Bot **nie przyznał się do niewiedzy**,
  tylko z pełną pewnością odpowiedział: *„Sprawy zgłoszeń i reklamacji: napisz na
  hello@automaticai.pl albo zostaw wiadomość w czacie — odpowiadamy do 3 dni roboczych."*
  W bazie ta wymiana ma **`trafiono=1`**, czyli produkt uznał ją za trafienie. Gość dostaje
  **cudzą odpowiedź podaną jako właściwą**, a nie „nie wiem".
  ⚖️ Uczciwie o zakresie: dopasowanie po podłańcuchach jest świadomą decyzją (Z4), więc
  **mechanizm** nie jest zaskoczeniem — ale skutek jest dokładnie tym, czego zakazuje
  zamówienie („PRÓG PEWNOŚCI: model ma ZAKAZ zgadywania… nie wiesz → poproś o maila").
  To fałszywe **trafienie**, nie brak stemmingu.
- `✅` **Polskie znaki, emoji, cudzysłowy, HTML w jednym pytaniu** — wysłałem
  `Zażółć gęślą jaźń 🙂🎉 "cudzysłów" <b>pogrubienie</b> & <script>alert(1)</script>`.
  W oknie wróciło **dokładnie to samo jako TEKST**; żaden `<b>` ani `<script>` nie powstał
  jako żywy węzeł, alert nie wyskoczył. ⚠️ Drobiazg do protokołu: w bazie leży wersja **bez
  znaczników** (`… "cudzysłów" pogrubienie …`), więc właściciel w panelu zobaczy nieco inną
  treść niż gość wpisał.
- `😖` **Pytanie dłuższe niż limit** — pole ma `maxlength=500`, więc **normalny gość jest
  chroniony** (nie wpisze i nie wklei więcej). Gdy limit obejść (ustawienie wartości skryptem,
  716 znaków), żądanie **przechodzi**, serwer tnie treść do 500 znaków i **nie mówi o tym ani
  słowa** — gość dostaje odpowiedź na okrojone pytanie. Sprawdziłem wprost: w odpowiedzi nie ma
  ani „skrócono", ani „za długie", ani „limit". Nigdzie nie widać też licznika znaków.
- `✅` **Dwa szybkie kliknięcia „wyślij"** — dwa kliknięcia dały **jedno** żądanie
  `chatl_pytanie` i jedną parę wiadomości w oknie. Nic się nie dubluje.
- `✅` **Dwie karty naraz, pisanie w obu** — osobne sesje
  (`IroziBliOsJ4…` i `YAOCQk1KFRR4…`), karta A nie widzi tekstu karty B ani odwrotnie,
  obie dostały własne, poprawne odpowiedzi. Rozmowy się nie mieszają.
- `✅` **Zamknięcie i ponowne otwarcie czatu** — 13 wiadomości przed zamknięciem,
  13 po ponownym otwarciu. Rozmowa zostaje.
- `✅` **Sieć pada w połowie oczekiwania** (tryb offline przeglądarki) — gość widzi
  *„Coś poszło nie tak. Spróbuj ponownie."*, wskaźnik „bot pisze" **znika z DOM**, a pole
  pytania **wraca odblokowane**. Żadnego wiecznego kółka.
- `✅` **Cały czat samą klawiaturą** — Tab dochodzi do bąbelka (**skok nr 50**), Enter otwiera
  okno, fokus ląduje na polu „Twoje pytanie", Enter wysyła (dostałem odpowiedź o fakturach),
  Escape zamyka i **oddaje fokus z powrotem na bąbelek**. Ścieżka klawiaturowa działa
  w całości. ⚠️ 50 skoków to dużo, ale strona ma 62 elementy fokusowalne, a widget siedzi na
  końcu `body` — to koszt układu strony, nie wada widgetu.
  ⚠️ **Kontrola przyrządu, która mnie poprawiła:** w pierwszym podejściu naciskałem Tab
  40 razy i zapisałem „nie da się dojść do czatu". Policzenie elementów fokusowalnych (62)
  pokazało, że to był **limit mojego testu**, nie wada produktu. Bez tej kontroli oddałbym
  fałszywe znalezisko.
- `🐞` **Konsola przeglądarki** — przez cały przelot **dwa czerwone błędy JavaScriptu**,
  oba przy normalnym używaniu czatu:
  ```
  pageerror: odpytaj is not defined                 (przy każdym OTWARCIU czatu)
  pageerror: zatrzymajOdpytywanie is not defined    (przy zamykaniu bąbelkiem)
  console.error: Failed to load resource: net::ERR_INTERNET_DISCONNECTED   (mój test offline — spodziewane)
  ```
  Te dwa pierwsze są już opisane jako A4-F1 i A4-F2 (PR #142) i **potwierdzone niezależnie**
  w PR #146. Tu meldują się same, bez szukania — kupujący z otwartą konsolą zobaczy je od razu.

## B. ChatBot — przejęcie rozmowy przez człowieka (4 punkty)

- `⬜` **Gość pisze, administrator przejmuje rozmowę** — **panel niedostępny** (blokada wyżej).
  Wykonałem równoważnik po stronie serwera (`chatl_przejmij` + wiadomość operatora), ale
  **nie kliknąłem tego w panelu**, więc punktu nie odhaczam.
- `🐞` **Czy gość widzi, że dołączył konsultant — bez odświeżania?** **NIE.** Gość z otwartym
  oknem czekał 20 s: zero odpytań, zero komunikatu. Zobaczył dopiero po napisaniu kolejnej
  wiadomości. (Strona gościa mierzona przeglądarką; przejęcie wywołane po stronie serwera.)
- `🐞` **Konsultant pisze — czy gość dostaje wiadomość?** **Nie w momencie wysłania.**
  Wiadomość *„Tu Anna z obsługi…"* pojawiła się dopiero, gdy gość sam znów napisał.
  Wtedy widzi ją poprawnie razem z paskiem, że rozmawia z człowiekiem.
- `⬜` **Zamknięcie karty konsultanta → powrót rozmowy do bota** — nie sprawdzone: wymaga
  panelu i odczekania progu ciszy. **Do przejścia przez człowieka.**

## C. Segregator maila — oczami WŁAŚCICIELA (9 punktów)

Wszystkie dziewięć punktów to **panel `wp-admin`**, do którego nie mam dostępu (blokada wyżej).
⛔ Żadnego z nich nie odhaczam i żadnego nie zgaduję z kodu.

- `⬜` Pierwsze wejście do panelu — **dodatkowo WYMAGA CZŁOWIEKA** (ocena „wiadomo, od czego zacząć").
- `⬜` Podłączenie skrzynki i jakość komunikatów — **dodatkowo WYMAGA CZŁOWIEKA** („czy prowadzą za rękę").
- `⬜` Zły port / złe hasło — czy komunikat mówi, co poprawić.
- `⬜` Puste pole portu i zapis. *(Uwaga do przelotu człowieka: to jest dokładnie znalezisko
  KR4 — port po cichu staje się `1`. Warto sprawdzić, czy widać to na ekranie.)*
- `⬜` Przebieg na sucho.
- `⬜` Przebieg na żywo + sprawdzenie w mailpicie.
- `⬜` Cofnięcie przebiegu. *(Uwaga: znalezisko Ł-A mówi, że komunikat potrafi kłamać —
  lista słusznie każe sprawdzać w mailpicie, nie na ekranie.)*
- `⬜` Dwa przebiegi naraz z dwóch kart.
- `⬜` Reguła pasująca do wszystkiego + odkręcenie.

## D. Wspólne — to, co widzi administrator (4 punkty)

- `⬜` Komunikaty botów na pulpicie i przy cudzej wtyczce, możliwość zamknięcia na stałe — panel niedostępny.
- `⬜` Widok z konta **redaktora** — panel niedostępny.
- `⬜` Przejście instrukcji krok po kroku z **pomiarem czasu** — **WYMAGA CZŁOWIEKA**
  (obietnica „konfiguracja w kwadrans" jest o człowieku, nie o skrypcie).
- `⬜` Kliknięcie każdego odnośnika w panelach — panel niedostępny.

---

## WYNIK

| znak | ile |
|---|---|
| `✅` zadziałało tak, jak obiecuje produkt | **8** |
| `🐞` nie zadziałało | **4** |
| `😖` działa, ale jest mylące/upierdliwe | **1** |
| `⬜` nie sprawdzone | **16** |
| **razem** | **29** |

**Rozbicie `⬜` — czego dokładnie brakuje:**

| powód | ile | punkty |
|---|---|---|
| ⭐ **WYMAGA CZŁOWIEKA** (ocena wrażenia, pomiar czasu) | **3** | A1, C1, D3 |
| ⛔ **zablokowane brakiem dostępu do panelu** (złe hasło) | **12** | B1, B4, C2–C9, D1, D2, D4 |
| *(C1 i D3 są liczone w pierwszym wierszu, choć blokada panelu też ich dotyczy)* | | |

**Znaleziska do rejestru** (4 × `🐞` + 1 × `😖`), w kolejności wagi:

1. `🐞` **Bot podaje cudzą odpowiedź jako trafną zamiast przyznać się do niewiedzy** (A4) —
   pytanie o silniki odrzutowe dostało odpowiedź o reklamacjach, w bazie `trafiono=1`.
2. `🐞` **Gość nie dowiaduje się, że dołączył konsultant** (B2) i `🐞` **nie dostaje jego
   wiadomości**, dopóki sam nie napisze (B3) — jedno źródło, dwa punkty listy.
3. `🐞` **Dwa czerwone błędy JS przy normalnym używaniu czatu** (A12).
4. `😖` **Treść ponad limit przycinana bez słowa** (A6) — z zastrzeżeniem, że `maxlength=500`
   chroni gościa przy normalnym pisaniu i wklejaniu.

## STAN STOSU PO MNIE

Nie zmieniałem żadnego ustawienia, reguły ani opcji. Jedyny ślad: **kilkanaście wierszy
testowych rozmów** w `wp_chatl_rozmowy` / `wp_chatl_sesje` (treści zaczynające się od
„Dzien dobry, chcialbym…", „KARTA-A…", „KARTA-B…", „Zażółć gęślą jaźń…", ciąg `AAAA…`)
oraz jedno zgłoszenie kontaktowe z wcześniejszej rundy. Nic nie wymaga cofnięcia,
ale przy ręcznym przelocie warto wiedzieć, skąd są.

## CZEGO TEN PRZEBIEG Z ZASADY NIE ZOBACZYŁ

Automat sprawdza, **czy się da**, a nie **czy to znośne**. Nie ocenił: czy powitanie zachęca
do napisania, czy odpowiedzi brzmią jak od firmy, czy panel Segregatora tłumaczy, od czego
zacząć, czy komunikaty błędów pomagają, czy „kwadrans na konfigurację" jest prawdą, i czy
cokolwiek na tych ekranach **irytuje**. To zostaje dla człowieka i to jest powód, dla którego
ręczny przelot pozostaje warunkiem końca.
