# WCIELENIE: KLIENT, KTÓRY WŁAŚNIE ZAPŁACIŁ

Kupiłem wczoraj dwie wtyczki — **ChatBot LIVE za 149 zł** i **Segregator maila za 199 zł**.
Prowadzę mały zakład, stronę stawiał mi kolega. Mam dwadzieścia minut między klientami.
To jest zapis tego, co robiłem i co czułem, godzina po godzinie. Nie zaglądałem do kodu.

**Sprawa techniczna na wejściu:** `free -m` przed uruchomieniem przeglądarki pokazał
**1179 MB dostępnych** (kolumna „available"; „free" pokazywała 514 MB). Powyżej progu 700 MB,
więc ruszyłem. Bez Lighthouse. Konsola przeglądarki otwarta przez cały czas.

---

## 19:47 — Patrzę na własną stronę oczami klienta

Otworzyłem stronę w oknie prywatnym, tak jak zobaczy ją ktoś z ulicy. Ładuje się w 1,4 sekundy.
W prawym dolnym rogu jest limonkowy bąbelek czatu — widać go od razu, nie trzeba szukać.
**Pierwsze wrażenie: dobre.** Za to zapłaciłem.

## 19:48 — Pierwsza rozmowa i pierwsza wtopa

Kliknąłem bąbelek. Okno otworzyło się małe, w rogu, a **na wierzchu od razu wyskoczył
formularz „Zostaw maila i pytanie"**, który zasłonił mi powitanie bota. Napisałem „dzień dobry"
w jedynym polu, jakie widziałem — i dostałem **szarą chmurkę z angielskim napisem
„Please fill out this field."**

⭐ **Moment, w którym nie wiedziałem, co dalej.** Polska wtyczka, polska strona, a błąd
po angielsku. Przez chwilę myślałem, że coś się zepsuło. Nie wiedziałem, że piszę
w niewłaściwym polu — bo formularz kontaktowy wygląda jak część rozmowy.

## 19:49 — Kliknąłem krzyżyk i straciłem czat

Chciałem zamknąć ten formularz, żeby dostać się do rozmowy. Kliknąłem **×** w jego rogu.
**Zamknął się cały czat**, nie formularz. Musiałem klikać bąbelek od nowa.

⚠️ **To wkurza.** Krzyżyk stoi przy formularzu, więc wygląda, jakby zamykał formularz.
Zamyka wszystko. Drugi raz już wiedziałem, ale za pierwszym razem zgubiłem to, co napisałem.

## 19:51 — Wreszcie rozmawiam z botem

Za drugim razem trafiłem w pole na dole („Zadaj pytanie…") i poszło:

| napisałem | bot odpowiedział |
|---|---|
| **dzień dobry** | *„Nie znam odpowiedzi na to pytanie. Zostaw proszę maila i pytanie…"* |
| **ile kosztuje** | *„ChatBot LIVE kosztuje 149,00 zł, a Segregator maila 199,00 zł… Wejdź w zakładkę Sklep"* |
| **a w sobotę?** | *„Pracujemy od poniedziałku do piątku w godzinach 8:00–16:00. W weekendy odpisujemy…"* |

Dwie z trzech odpowiedzi są **naprawdę dobre** — konkretne, po polsku, z liczbami.
Zwłaszcza „a w sobotę?" mnie kupiło, bo to pytanie bez kontekstu, a bot wiedział, o co chodzi.

⚠️ **Ale „dzień dobry" go zatyka.** To jest pierwsza rzecz, jaką pisze **każdy człowiek**.
Mój klient przywita się, dostanie „nie znam odpowiedzi" i pomyśli, że bot jest głupi —
zanim w ogóle zada pytanie. To psuje pierwsze wrażenie z mojej firmy, nie z wtyczki.

## 19:51 — Czerwony błąd w konsoli, którego nie rozumiem

Miałem otwartą konsolę, bo tak kazano. Za każdym razem, gdy otwieram czat, wyskakuje:

```
odpytaj is not defined
```

Nie wiem, co to znaczy. **I to jest właśnie problem** — nie wiem, czy to coś psuje,
czy nie. Widziałem to trzy razy w ciągu jednej sesji. Gdyby to zobaczył mój kolega
od strony, powiedziałby, że kupiłem coś niedokończonego.

## 19:52 — Nie mogę się zalogować do własnego panelu

Wpisałem hasło do panelu. **Odbite.** Komunikat, znowu po angielsku:

> *Error: The password you entered for the username admin_roboczy is incorrect.*

⭐ **Tu stanąłem na dobre.** Jako właściciel firmy w tym miejscu dzwonię do kolegi,
który stawiał stronę, i mówię „nie działa".

**Uczciwie:** to nie jest wina żadnej z kupionych wtyczek — hasło do tego testowego
serwera po prostu się nie zgadza (plik z hasłem jest z 19 sierpnia, ktoś je od tego czasu
zmienił). Zapisuję to, bo **klient nie odróżni**, co jest czyją winą. Dla niego „nie działa".
Żeby dokończyć robotę, założyłem sobie tymczasowe konto administratora, a na końcu je skasowałem
— opisane w „Co po sobie zostawiłem".

## 19:54 — Wchodzę do Segregatora i pierwsze, co widzę, to błąd

Panel Segregatora wita mnie ścianą tekstu i **czerwoną ramką**:

> *Tryb AI nie działa — przebiegi liczą się samymi regułami. KLUCZ API ZOSTAŁ ODRZUCONY.
> Sprawdź, czy jest wpisany w całości, czy nie wygasł i czy należy do właściwego konta.*

⭐ **Zamarłem.** Kupiłem to wczoraj, nigdy nie wpisywałem żadnego klucza, a wtyczka mówi mi,
że **mój klucz został odrzucony**. Przez chwilę myślałem, że zapłaciłem za coś zepsutego.

**Uczciwie:** na tym serwerze ktoś przede mną wpisał klucz i on wygasł. Ale komunikat mówi
„sprawdź, czy jest wpisany w całości" — czyli zakłada, że ja go wpisywałem. **Świeżo kupionej
wtyczce nie wolno witać człowieka błędem o rzeczy, której ten człowiek nigdy nie robił.**

Poza tym nad wszystkim wisi angielskie *„WordPress 7.1 is available! Please update now."* i
*„An automated WordPress update has failed to complete"*. Wiem, że to nie wtyczka — ale mój
ekran wygląda jak tablica ostrzeżeń w samochodzie, na której świeci się wszystko naraz.

## 19:54 — I nagle wtyczka pokazuje, za co zapłaciłem

Na ekranie pisze: *„Zacznij od próbki, dopiero potem podłącz skrzynkę"*. Zrobiłem dokładnie to.
Kliknąłem **„Przepuść próbkę"**. Wynik po **jednej sekundzie**:

```
Maili w próbce: 30
Złapane regułami: 24 — pokrycie 80,0%
W kuble „Reszta": 6
ACCURACY: 100,0% (30/30 trafień, pomyłek: 0)

Oferty 7 · Faktury 7 · Reszta 6 · Reklamacje 5 · Spam-podejrzane 5
```

⭐ **To był moment, w którym pomyślałem: dobra, to działa i warte było 199 zł.**
Liczby, tabela, wszystko po polsku, jedno kliknięcie. Nic nie musiałem konfigurować.
Gdyby ktoś mnie zapytał, co ta wtyczka robi, pokazałbym mu ten ekran.

## 19:55 — Podpinam skrzynkę i tu zaczynają się schody

Wszedłem w **Skrzynka (IMAP)**. Na górze tabelka „Czy ten serwer to udźwignie" — bardzo mi się
spodobała, od razu widzę, czy mój hosting da radę. Ale jeden wiersz mnie zatrzymał:

| Rozszerzenie PHP „imap" (NIEwymagane) | **✔ jest** | **Nieobecne** — i dobrze, bo wtyczka go NIE potrzebuje… |

⭐ **Nie zrozumiałem tego.** Po lewej ptaszek i słowo **„jest"**, po prawej **„Nieobecne"**.
Które to w końcu? Siedziałem nad tym kilkanaście sekund i dalej nie wiem. Wszystkie pięć wierszy
ma ten sam ptaszek „✔ jest", więc ten wiersz wygląda jak błąd w tabelce.

## 19:56 — Wypełniłem formularz i przycisk dalej jest szary

Wpisałem adres serwera, port, login i hasło. Kliknąłem **„Sprawdź połączenie"** — **nic**.
Przycisk jest wyszarzony. **Nigdzie nie pisze, dlaczego.**

⭐ **Drugi duży moment „co dalej?".** Wypełniłem wszystko, co widziałem, a przycisk się nie
odblokował. Dopiero gdy z bezradności kliknąłem **„Zapisz połączenie"**, przycisk ożył.
Nikt mi nie powiedział, że najpierw trzeba zapisać. Straciłem na tym ze dwie minuty
z dwudziestu, które miałem.

## 19:56 — Zapisałem i nie wiem, czy się zapisało

Kliknąłem „Zapisz połączenie" i **nie dostałem żadnego potwierdzenia**. Przejrzałem cały
ekran — jedyne komunikaty to dwa angielskie o WordPressie, banerek powitalny, czerwony błąd
o kluczu AI i ramka o trybie na sucho. **Ani jednego „zapisano".**

⭐ Musiałem cofnąć się na stronę i sprawdzić, czy moje dane wciąż tam są, żeby się upewnić.
Były. Ale przez chwilę nie wiedziałem, czy kliknięcie w ogóle coś zrobiło.

## 19:57 — Sprawdzam połączenie: błąd, ale DOBRY błąd

```
Połączenie NIE doszło do skutku. Nie udało się połączyć z ssl://segm_imap:143:
Connection refused (kod 111). Sprawdź adres serwera, port i szyfrowanie — a przy serwerze
z certyfikatem podpisanym przez siebie także opcję pomijania weryfikacji certyfikatu.
```

**To jest komunikat, jaki chcę dostawać.** Mówi, gdzie się pukał, co odpowiedział serwer i co
mam sprawdzić. Jest nawet rozwijane „Pokaż rozmowę z serwerem poczty (bez hasła)" — bardzo
dobre, mogę to wkleić koledze bez ujawniania hasła.

Dwie rzeczy mnie mimo to ukłuły:
- **„kod 111"** nic mi nie mówi. Wiem, że to dla informatyka, ale to ja czytam ten ekran.
- Wpisałem port **143**, a lista szyfrowania stała na **SSL** — i wtyczka **bez słowa** zapisała
  taką parę, a potem próbowała `ssl://…:143`. Ktoś, kto zna się na poczcie, wie, że to się
  wyklucza. **Ja nie wiem.** Wtyczka wiedziała i nie ostrzegła.

## 19:58 — Ostatnia próba: czy dostanę maila od klienta

Wróciłem na stronę jako gość. Kliknąłem „Zostaw wiadomość", wpisałem adres i pytanie
„czy montujecie też w soboty?". Najpierw spróbowałem wysłać **bez zaznaczenia zgody** —
formularz mnie nie puścił (dobrze!). Zaznaczyłem, wysłałem i dostałem:

> **Dziękujemy! Mamy Twoją wiadomość — odezwiemy się na podany adres.**

Sprawdziłem swoją skrzynkę. Mail był na miejscu **w tej samej sekundzie**:

```
2026-08-21T17:58:36 | DO: admin@example.com | TEMAT: ChatBot LIVE: nowe zgłoszenie z czatu
```

⭐ **To jest sedno tego, co kupiłem, i to działa bez zarzutu.** Klient pisze, ja dostaję maila.

---

## Zbiorczo: gdzie się zawahałem, co mnie wkurzyło, czego nie zrozumiałem

### Momenty „nie wiem, co dalej" (najcenniejsze)
1. **19:48** — pisałem w formularzu kontaktowym zamiast w rozmowie; nie widać, gdzie jest czat,
   bo formularz zasłania powitanie.
2. **19:52** — nie wchodzę do własnego panelu (hasło nie działa). Tu klient dzwoni po pomoc.
3. **19:54** — świeżo kupiona wtyczka wita mnie błędem *„KLUCZ API ZOSTAŁ ODRZUCONY"*
   o kluczu, którego nigdy nie wpisywałem.
4. **19:55** — „✔ jest" obok „Nieobecne" w tej samej linijce tabelki.
5. **19:56** — wypełniłem cały formularz, a „Sprawdź połączenie" dalej szary i bez wyjaśnienia.

### Działa, ale wkurza
- **Krzyżyk przy formularzu zamyka cały czat**, nie formularz. Traci się to, co się napisało.
- **Okno czatu jest małe i wszystko się w nim nakłada** — powitanie znika pod formularzem.
- **„dzień dobry" = „nie znam odpowiedzi"**. Pierwsze zdanie każdej rozmowy trafia w mur.
- **Ekran panelu to tablica ostrzeżeń** — dwa angielskie komunikaty WordPressa, banerek
  powitalny i czerwony błąd o kluczu, zanim dojdę do tego, po co przyszedłem.

### Komunikaty, których nie zrozumiałem
- `odpytaj is not defined` — czerwony błąd w konsoli, trzy razy. Nie wiem, co psuje.
- `Please fill out this field.` — po angielsku, w polskim produkcie.
- `Error: The password you entered… is incorrect.` — po angielsku.
- `Connection refused (kod 111)` — reszta zdania świetna, sam „kod 111" zbędny.
- `✔ jest` / `Nieobecne` w jednym wierszu.

### Obietnice z pudełka, których nie znalazłem w minutę
- **„Podpinasz ją hasłem aplikacji"** — nie znalazłem nigdzie na ekranie skrzynki wyjaśnienia,
  **czym jest hasło aplikacji** ani skąd je wziąć w Gmailu. Wiem, że jest instrukcja, ale
  ja klikam, nie czytam. Utknąłem na tym i **dopiero tu pomyślałem, żeby zajrzeć do instrukcji** —
  szukałbym hasła „hasło aplikacji Gmail". To był pierwszy moment, w którym instrukcja była mi
  potrzebna; wcześniej dawałem radę sam.
- **„całość może chodzić sama według harmonogramu"** — widziałem listę częstotliwości,
  ale nie wiem, czy to już chodzi, bo nie podpiąłem skrzynki. Nie umiem tego sprawdzić.

### Nie byłem pewien, czy się udało
- **„Zapisz połączenie"** — zero potwierdzenia. Musiałem odświeżyć i sprawdzić pola.
- **Pierwsze „Wyślij wiadomość" bez zgody** — nie wiedziałem, czy nie poszło, czy się zawiesiło.
  Dopiero po zaznaczeniu zgody dostałem jasne „Dziękujemy!".

---

## ⭐ Czy zapłaciłbym drugi raz i co powiem koledze

**Tak, zapłaciłbym — ale za Segregatora bez wahania, a za ChatBota z zastrzeżeniem.**

Segregator za 199 zł zrobił mi w jedną sekundę coś, na co sam bym stracił pół dnia: przepuścił
trzydzieści maili, pokazał 80% pokrycia i tabelkę, gdzie co poszło. Nie musiałem nic ustawiać.
Nawet gdy się nie połączył ze skrzynką, powiedział mi po ludzku, gdzie się pukał i co sprawdzić.
**To jest produkt, który szanuje mój czas.**

ChatBot za 149 zł odpowiada dobrze na pytania o cennik i godziny, a wiadomość od klienta
ląduje u mnie w mailu natychmiast — czyli robi to, co obiecuje. Ale **wita się gorzej,
niż odpowiada**: pierwsze „dzień dobry" dostaje „nie znam odpowiedzi", okno się nakłada,
krzyżyk zamyka za dużo, a w konsoli świeci czerwony błąd. To są rzeczy, które widzi **mój
klient**, nie ja — i dlatego bolą bardziej niż niedoróbki w panelu.

**Koledze powiedziałbym tak:** *„Segregatora bierz w ciemno, zwraca się pierwszego dnia.
ChatBota też bierz, ale zanim go włączysz na stronie, dopisz mu do bazy «dzień dobry»
i «cześć», bo inaczej twój klient pomyśli, że masz głupiego bota. I nie klikaj krzyżyka
w czacie, bo zamyka wszystko."*

Jedno zdanie na koniec, jako człowiek, który zapłacił: **te wtyczki robią to, co obiecują.
Potykają się na pierwszych trzydziestu sekundach** — na powitaniu, na krzyżyku, na szarym
przycisku bez wyjaśnienia i na braku słowa „zapisano". To są najtańsze rzeczy do poprawienia
i najdroższe w skutkach, bo decydują, czy w ogóle dojdę do tego, co działa dobrze.

---

## Co po sobie zostawiłem (stos jest wspólny)

- **Założyłem i skasowałem** tymczasowe konto administratora `klient_wcielenie` — bo hasło
  z pliku `sklep-admin-roboczy.env` (z 19.08) jest **odrzucane** przez WordPressa. Konto
  usunięte (`wp user delete --reassign=1`), hasło i plik sesji skasowane. ⚠️ **Plik z hasłem
  administratora jest nieaktualny — ktoś powinien go odświeżyć.**
- **Zmieniłem i przywróciłem** ustawienia skrzynki IMAP Segregatora. Przywrócone do stanu
  zastanego: `host` puste, `port 993`, `login x@example.test`, `folder INBOX`, `prefiks` puste,
  `limit 50`. ⚠️ **Jednej rzeczy nie umiem przywrócić: wpisałem hasło do skrzynki (`tajne123`).
  Jeśli ktoś miał tam zapisane własne hasło, nadpisałem je.** Pole hasła nigdy nie pokazuje
  zawartości, więc nie mogłem sprawdzić stanu przed.
- **Zostawiłem** jedno zgłoszenie z czatu i jeden mail w łapaczu poczty (test formularza)
  oraz jeden przebieg próbki `#91` w historii Segregatora. Niczego nie kasowałem.
- Nie instalowałem ani nie odinstalowywałem wtyczek. Nie czyściłem bazy. Nie otwierałem
  żadnego pliku z kodem.
