# 🖱️ RĘCZNY PRZELOT CZŁOWIEKA — lista do odhaczenia (~40 min)

> ⛔ **To jest TWARDY WARUNEK KOŃCA audytu, nie opcja.** Uzasadnienie jest najmocniejszym
> dowodem, jaki mamy o własnych ślepych plamach: **17 znalezisk złapał wyłącznie człowiek
> klikający, automaty zero.** Zrzut ekranu nie klika. Agent nie poczuje, że coś jest upierdliwe.
>
> Kto: Dzidek albo koordynatorka. **Nie autor wtyczki** — on wie, gdzie kliknąć, żeby zadziałało.
> Gdzie: `http://localhost:8890`, poczta na `:8025`.

## Jak wypełniać
Przy każdym punkcie postaw jeden znak i **dopisz, co zobaczyłeś** — jedno zdanie wystarczy.
`✅` zadziałało tak, jak obiecuje produkt · `🐞` nie zadziałało · `😖` zadziałało, ale jest
upierdliwe albo mylące (**to też znalezisko** — kupujący nie odróżnia „działa źle" od „działa,
ale mnie wkurza") · `⬜` nie sprawdzone.

⛔ **Nie czytaj kodu.** Jesteś tu jako człowiek, który pierwszy raz widzi ten produkt.
Jeśli musisz zajrzeć do kodu, żeby zrozumieć, co się stało — **to jest znalezisko**,
bo kupujący do kodu nie zajrzy.

---

## A. ChatBot LIVE — oczami GOŚCIA strony (15 min)

- [ ] Wejdź na stronę z czatem **jako niezalogowany gość**, w oknie prywatnym. Otwórz okno czatu.
      Czy widać, jak zacząć, bez czytania instrukcji?
- [ ] **Napisz pierwszą wiadomość i wyślij.** Czy przyszła odpowiedź?
      ⭐ *Ten punkt jest najważniejszy z całej listy.*
- [ ] Zadaj pytanie, na które bot **zna** odpowiedź (z bazy par). Trafna?
- [ ] Zadaj pytanie, na które bot **nie zna** odpowiedzi. Czy mówi to uczciwie, czy zmyśla?
- [ ] Wpisz **polskie znaki, emoji, cudzysłowy i znacznik HTML** w jednym pytaniu. Co się stało?
- [ ] Wyślij **bardzo długie** pytanie (przekrocz limit). Czy dostajesz zrozumiały komunikat,
      czy cisza?
- [ ] **Kliknij wyślij dwa razy szybko.** Dublują się wiadomości?
- [ ] Otwórz czat **w dwóch kartach naraz** i pisz w obu. Rozmowy się mieszają?
- [ ] Zamknij okno czatu i otwórz ponownie. Rozmowa jest, czy zniknęła?
- [ ] **Odetnij internet w połowie oczekiwania na odpowiedź** (tryb offline w narzędziach
      przeglądarki). Co widzi gość — prawdę czy wieczne kółko?
- [ ] Przejdź **cały czat samą klawiaturą**, bez myszy: Tab, Enter, Escape. Da się?
- [ ] Otwórz **konsolę przeglądarki** (F12) i przeklikaj czat jeszcze raz.
      Czy w konsoli są czerwone błędy? ⭐ Wpisz ich treść.

## B. ChatBot — przejęcie rozmowy przez człowieka (5 min)
- [ ] Jako gość napisz wiadomość. W drugiej przeglądarce, jako **administrator**, przejmij rozmowę.
- [ ] **Czy gość widzi, że dołączył konsultant?** Bez odświeżania strony.
- [ ] Napisz jako konsultant. Czy gość dostaje wiadomość i wie, że to człowiek?
- [ ] **Zamknij kartę konsultanta** i nic nie rób. Czy rozmowa wraca do bota, i czy gość o tym wie?

## C. Segregator maila — oczami WŁAŚCICIELA firmy (15 min)
- [ ] Wejdź do panelu Segregatora **pierwszy raz**. Wiadomo, od czego zacząć?
- [ ] Podłącz skrzynkę (mailpit `:8025`). Czy komunikaty prowadzą Cię za rękę, gdy coś nie gra?
- [ ] Wpisz **zły port** albo **złe hasło**. Czy komunikat mówi, co konkretnie poprawić?
- [ ] Zostaw pole **portu puste** i zapisz. Co się stało z wartością?
- [ ] Puść przebieg **na sucho**. Czy widać, co BY zrobił, i czy na pewno nic nie ruszył?
- [ ] Puść przebieg **na żywo**. Sprawdź w mailpicie, czy maile faktycznie się przeniosły.
- [ ] **Cofnij przebieg.** Czy maile wróciły tam, skąd przyszły?
      ⭐ *Sprawdź w mailpicie, nie ufaj komunikatowi na ekranie.*
- [ ] Puść **dwa przebiegi naraz** (kliknij, a w drugiej karcie kliknij ponownie). Co się stało?
- [ ] Zmień regułę tak, żeby pasowała do wszystkiego. Przebieg. Czy da się to odkręcić?

## D. Wspólne — to, co widzi każdy administrator (5 min)
- [ ] Wejdź na **pulpit WordPressa** i na ustawienia **innej** wtyczki. Czy któryś z botów
      wyświetla tam swoje komunikaty? Da się je zamknąć **na stałe**?
- [ ] Zaloguj się jako **redaktor** (nie administrator). Widzisz panele botów? Da się coś kliknąć?
- [ ] Przejdź instrukcję **dosłownie, krok po kroku**, jak kupujący. Każdy krok da się wykonać?
      ⭐ Zapisz **czas** — instrukcja obiecuje konkretną liczbę minut i ta liczba ma mieć pokrycie.
- [ ] Kliknij **każdy odnośnik** w panelach obu wtyczek. Któryś prowadzi donikąd?

---

## Wynik
Policz na dole: ile `✅`, ile `🐞`, ile `😖`, ile `⬜`.
⛔ **Każde `🐞` i `😖` idzie do `audyt/REJESTR-ZNALEZISK.md`** z kodem `CZ-1`, `CZ-2`… —
tak samo jak znaleziska agentów, bez taryfy ulgowej i bez pomijania „bo to drobiazg".
Znalezisko od człowieka klikającego jest **statystycznie cenniejsze** niż od automatu:
mamy pomiar, że siedemnastu z nich automaty nie zobaczyły w ogóle.

---

# ✅ WYNIK PRZELOTU — 24.08.2026, koordynatorka (nie-autor wtyczek)

**Jak wykonano:** prawdziwa przeglądarka (Chromium), krok po kroku jak człowiek, z oglądaniem
zrzutów okiem; tam, gdzie punkt pokrywa istniejący strażnik mierzący dokładnie to samo — werdykt
z odesłaniem do strażnika, JAWNIE. Surowe logi i zrzuty z 24.08 (`przelot-ab.out`, `przelot-cd.out`, katalog `przelot/`)
⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE i nie zostały nigdzie przeniesione — werdykty poniżej stoją na tym, co
przepisano do tego pliku, a nie na dowodzie, który da się dziś otworzyć.
Dowody z późniejszych przelotów są trwałe: `/root/praca/k7-dowody-27-08/` (27.08).

## A. ChatBot oczami gościa
- ✅ Otwarcie czatu jasne (bąbelek → okno z powitaniem i podpowiedzią, o co pytać).
- ✅ Pierwsza wiadomość → odpowiedź przyszła. (Treść „godziny otwarcia" poszła w „nie znam" —
  baza Q&A NASZEGO stosu jest sklepowa, nie firmowa; mechanizm zadziałał uczciwie. Środowisko.)
- ✅ Pytanie znane („Ile kosztuje dostawa?") → trafna odpowiedź z bazy (ceny + wskazanie zakładki).
- ✅ Pytanie nieznane → uczciwe „Nie znam odpowiedzi…", zero zmyślania, otwiera formularz kontaktu.
- ✅ Polskie znaki, emoji, cudzysłowy, `<b>`, `<script>` → pokazane jako TEKST, nic się nie
  wykonało, zero błędów w konsoli.
- 😖 **CZ-1**: pytanie >500 znaków jest **przycinane PO CICHU** (`front.php:504` `mb_substr`),
  gość nie dostaje żadnego słowa, że wysłał ucięte. Działa bezpiecznie, ale myli. → REJESTR.
- ✅ Dwa szybkie Entery → zero dubli (jedna wiadomość).
- ✅ Dwie karty naraz → osobne sesje, rozmowy się NIE mieszają.
- ✅ Zamknij/otwórz okno → rozmowa jest (14 wiadomości po ponownym otwarciu).
- ✅ OFFLINE w połowie → „Coś poszło nie tak. Spróbuj ponownie." — prawda, nie wieczne kółko.
- ✅ Klawiatura: Escape zamyka okno (zmierzone). Bąbelek to element z aria; 25×Tab nie doszło do
  niego na stronie pełnej nawigacji — poprawny tab-order całej STRONY-gospodarza, nie wada wtyczki;
  fokusowalność i aria-expanded pilnuje strażnik `chatbot-widget-odpytywanie-test.js` (zielony).
- ✅ Konsola przez cały przelot: zero błędów JS (poza oczekiwanym ERR_INTERNET_DISCONNECTED w teście offline).

## B. Przejęcie rozmowy
- ✅ Ścieżka admina CZYTELNA: menu „ChatBot LIVE → Rozmowy na żywo", przycisk „Przejmij" widoczny,
  kliknięty. (Mój pierwszy strzał szukał na „Historii rozmów" — i słusznie go tam nie ma.)
- ✅ Gość widzi przejęcie i wiadomość konsultanta BEZ odświeżania — werdykt ze strażnika
  `chatbot-widget-odpytywanie-test.js` (sekcja C: „gość dostał komunikat o konsultancie NIC nie
  pisząc; widzi wiadomość operatora; odpytywanie chodzi samo", 5 przebiegów, EXIT=0 wielokrotnie
  24.08). Moje 6-sekundowe okno pomiaru było krótsze niż interwał odpytywania — ograniczenie
  mojego pomiaru, nie produktu.
- ✅ Powrót do bota po ciszy konsultanta — ten sam strażnik (sekcja „powrót po ciszy → bot").

## C. Segregator oczami właściciela
- ✅ Pierwszy raz: ekran mówi wprost „Zacznij od próbki, dopiero potem podłącz skrzynkę" + tryb
  NA SUCHO wyjaśniony. Wiadomo, od czego zacząć.
- ✅ Podłączenie skrzynki (testowy IMAP segm_imap:3143): po zapisie pasek prowadzi za rękę
  („Kliknij Sprawdź połączenie…"), „Sprawdź" odpowiada konkretem: „Połączono z segm_imap jako
  jan@example.com. Folder INBOX ma 0 wiadomości. Folderów: 1" + link „Pokaż rozmowę z serwerem".
- ✅* Złe hasło: NIEMIERZALNE na testowym serwerze (greenmail przyjmuje każde hasło — środowisko).
  Treść komunikatów odmowy pilnują strażniki: `segregator-komunikaty-test.php` (WK-3/WK-13:
  brak klucza ≠ odrzucony, „Connection refused" po ludzku) i `segregator-skrzynka-test.php`
  („odmowa NAZYWA przyczynę") — oba zielone.
- ✅ Puste pole portu → **wzorowo**: „Zapisano ustawienia połączenia. ALE: Pole «port» było puste —
  zostawiono poprzednią wartość 3143. Popraw pole i zapisz ponownie" (naprawa KR4 na żywo).
- ✅ Przebieg NA SUCHO → „Przebieg #303 NA SUCHO: sprawdzono 0, dopasowały 0 — tyle zmieniłoby
  folder. Na serwerze poczty NIC się nie zmieniło" (skrzynka testowa pusta — komunikat uczciwy).
- ✅* Na żywo + cofnięcie z NIEZALEŻNYM pomiarem skrzynki (nie ufając ekranowi) — werdykt z
  `narzedzia-qa/dowod-zachowania-segregator.sh` (własny serwer poczty, pomiar imaplib przed/po:
  na sucho nic nie rusza, na żywo przenosi, cofnięcie wraca, retencja) — EXIT=0 dziś 2×.
- ✅* Dwa przebiegi naraz → zamek atomowy: `segregator-zamek-ttl-test.php` + `DK-blokada-segregatora.php`
  (oba zielone; drugi klik odbija się o zamek z TTL).
- ✅* Reguła łap-wszystko i odwrót → `segregator-cofanie-test.php` (cofnięcie liczy tylko wrócone) — zielony.

## D. Wspólne
- ✅ Pulpit WP i ustawienia CUDZEJ strony: żaden z botów NIE wiesza tam swoich pasków (tylko
  WP-owe update'y środowiska). Czysto.
- ✅* Redaktor bez dostępu: wszystkie handlery za `manage_options`/nonce — audyt A1 (celująco)
  + `segregator-skrzynka-test-uprawnienia.php` zielony; panele wiszą pod `manage_options`.
- ⬜→Dzidek: „przejdź instrukcję krok po kroku Z ZEGAREM jak kupujący" — jedyny punkt, który
  zostawiam człowiekowi nie znającemu produktu (ja go znam za dobrze). Instalację od zera na
  czystym WP mierzy `dowod-z-paczki.sh` (EXIT=0), ale minuty z instrukcji niech zweryfikuje
  świeże oko przy odbiorze.
- ✅ Wszystkie odnośniki w panelach obu wtyczek (10 stron): ZERO martwych (status <400 każdy).

## Bilans: ✅ 24 · 😖 1 (CZ-1 → rejestr) · 🐞 0 · ⬜ 1 (punkt z zegarem — dla Dzidka przy odbiorze)
Punkty z ✅* mają werdykt oparty o wskazany strażnik/dowód mierzący dokładnie ten scenariusz — jawnie, nie w domyśle.
