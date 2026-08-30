# Sklep Agentów — instrukcja właściciela

Ta instrukcja jest dla Ciebie — osoby, która prowadzi sklep z panelu WordPressa.
Instalację i konfigurację (odnośniki, klucze Stripe, webhook, pierwszy zakup testowy) opisuje
osobny plik `INSTRUKCJA-INSTALACJI.md`, dołączony do wydania obok paczek. Tutaj jest to, co robisz
**na co dzień**: produkty, zamówienia, maile do kupujących, opinie, dane osobowe, ustawienia.

Sklep sprzedaje pliki cyfrowe (paczki wtyczek) prosto z Twojej strony: kupujący płaci kartą
na stronie Stripe, dostaje mail z linkiem do pobrania, a Ty widzisz zamówienie w panelu.
Nie zakłada kont kupującym, nie ma abonamentu i nie wysyła danych nigdzie poza Stripe.

---

## 1. Panel — co gdzie jest

W kokpicie WordPressa, w menu po lewej, jest pozycja **Sklep Agentów** z pięcioma stronami:

| Strona | Do czego służy |
|---|---|
| **Pulpit** | wskaźniki sprzedaży, lista zamówień, eksport CSV, przycisk „Dociągnij zaległe teraz” |
| **Produkty** | dodawanie i edycja produktów, wgrywanie pliku ZIP, włączanie sprzedaży |
| **Opinie** | moderacja opinii kupujących — pojawiają się na stronie produktu dopiero po przyjęciu |
| **Dziennik** | logowania administratorów i zdarzenia sklepu |
| **Ustawienia** | klucze Stripe, nadawca maili, mail kontaktowy, waluta, dane przy odinstalowaniu |

Szczegóły pojedynczego zamówienia otwierasz z listy na Pulpicie przyciskiem **Szczegóły**.

![Pulpit sklepu — wskaźniki sprzedaży](zrzuty/dashboard-wskazniki.png)

Na górze Pulpitu jest oznaczenie **TRYB TESTOWY** — w tej wersji wpisane na stałe, niezależnie od
tego, jakie klucze Stripe wpiszesz (rozdział 8).

---

## 2. Produkty — jak dodać i wystawić na sprzedaż

**Sklep Agentów → Produkty → Dodaj produkt.**

![Formularz nowego produktu](zrzuty/produkt-formularz.png)

1. **Nazwa** — pod tą nazwą produkt widnieje w sklepie, w mailu po zakupie i w zamówieniach.
   Z nazwy powstaje adres strony produktu: `/sklep/produkt/nazwa-produktu/`.
2. **Opis krótki** — jedno–dwa zdania na liście sklepu.
3. **Opis pełny** — treść strony produktu. Wpisujesz zwykły tekst; sklep nie przyjmuje
   znaczników HTML w opisach (to celowe zabezpieczenie).
4. **Cena i waluta** — cena w pełnych jednostkach (np. `149.00`). Waluta nowego produktu
   bierze się z Ustawień, tu możesz ją zmienić dla tego produktu.
5. **Plik paczki (ZIP)** — to, co kupujący pobiera. Wyłącznie ZIP, do 20 MB — ale nie więcej,
   niż pozwala Twój serwer: formularz pokazuje **mniejszy** z tych dwóch limitów (na zrzucie wyżej
   „do 2 MB”, bo tyle pozwalał serwer, na którym robiono zrzut).
   Sklep sprawdza plik po zapisie (obecność, rozmiar, suma kontrolna) i dopiero wtedy uznaje
   go za **potwierdzony**.
6. **W sprzedaży** — zaznacz „Produkt widoczny i kupowalny w sklepie”.
   ⛔ **Nie da się włączyć sprzedaży bez potwierdzonego pliku** — sklep nie sprzeda czegoś,
   czego nie ma czym wydać. Zapis wróci z komunikatem, dopóki nie wgrasz ZIP-a.

**Co warto wiedzieć o edycji:**

- Każde zamówienie trzyma **kopię danych produktu z chwili zakupu** (nazwa, cena, plik).
  Późniejsza zmiana ceny czy nazwy nie zmienia historii sprzedaży, a nowy plik dostaną tylko
  **nowe** zakupy — stare zamówienia pobierają to, co kupiły.
- Nowy plik dostaje nową, chronioną ścieżkę na serwerze. Pliki produktów nigdy nie leżą pod
  publicznym adresem — wydaje je wyłącznie sklep, po sprawdzeniu linku z maila.
- Odznaczenie „W sprzedaży” chowa produkt ze sklepu, ale nie kasuje ani produktu, ani zamówień.

Lista sklepu (`/sklep/`) i strony produktów powstają same — nie musisz tworzyć ich w WordPressie.

![Lista sklepu](zrzuty/sklep-front.png)

---

## 3. Zamówienia — co widzisz i co możesz zrobić

**Sklep Agentów → Pulpit**, sekcja **Zamówienia**. Listę filtrujesz po statusie i datach,
szukasz po adresie e-mail, a **Eksport CSV** zapisuje dokładnie to, co masz w tej chwili
przefiltrowane.

**Statusy zamówienia:**

| Status | Co znaczy | Co robić |
|---|---|---|
| **oczekuje** | powiadomienie od Stripe przyjęte, płatność jeszcze niepotwierdzona (np. przelew albo BLIK w toku) | nic — poczekaj; gdy wisi długo, „Dociągnij zaległe teraz” |
| **opłacone** | Stripe potwierdził płatność, mail jeszcze nie poszedł | nic — mail wychodzi za chwilę |
| **mail w toku** | sklep właśnie wysyła mail | poczekaj |
| **mail wysłany** | kupujący dostał link do pobrania | nic |
| **błąd maila** | ostatnia próba wysyłki nie powiodła się; sklep ponawia sam do trzech prób, potem zostaje ten status i pasek na Pulpicie | otwórz **Szczegóły → Wyślij ponownie**; jeśli błąd się powtarza — patrz rozdział 4 (SMTP) |
| **zwrócone** | Stripe zwrócił kupującemu **całą** kwotę | nic — sklep odnotował zwrot z powiadomienia Stripe i odjął go od statystyk. Zwrot **częściowy** nie zmienia statusu: kwota schodzi ze statystyk, a zwrot widać w osi czasu zamówienia |
| **obciążenie zwrotne** (spór) | kupujący zakwestionował płatność w banku (chargeback) — kwota zdjęta z Twojego konta do rozstrzygnięcia | odpowiadasz w panelu Stripe; spór wygrany albo wycofany → sklep wraca do poprzedniego statusu; spór przegrany → zamówienie zostaje jako obciążenie zwrotne |

⚠️ Zwrot ani spór **nie odcinają** wcześniej wysłanego linku do pobrania — działa do końca
swojej ważności (72 godziny, 10 pobrań). Jeśli chcesz, żeby zwrot odcinał dostęp od razu,
napisz do nas — to zmiana w sklepie, nie ustawienie.

Pulpit pokazuje na górze czerwony pasek, gdy są zamówienia z nieudaną wysyłką — z linkiem
prosto do nich.

**Szczegóły zamówienia** (przycisk **Szczegóły** przy wierszu):

![Szczegóły zamówienia](zrzuty/zamowienie-naglowek.png)

- data zakupu, kupujący, produkt (kopia z chwili zakupu), kwota, identyfikator sesji Stripe,
- **Pobrania** — ile razy z limitu 10 kupujący pobrał plik i czy link jest jeszcze ważny,
- **Zgoda na dostarczenie treści cyfrowej** — treść i czas zgody, którą kupujący zaznaczył
  przed zapłatą (ważne przy prawie do odstąpienia od umowy),
- **Treść wysłanego maila** — dokładnie to, co dostał kupujący, z przyciskiem **Wyślij ponownie**,
- **Dane osobowe (RODO)** — przycisk anonimizacji (rozdział 6),
- **Oś czasu** — każde zdarzenie tego zamówienia: płatność, wysyłki, pobrania, zwroty, kto co zrobił.
  Wpisy starsze niż **90 dni** znikają same (tak jak cały Dziennik, rozdział 7) — jeśli potrzebujesz
  zapisu na dłużej (np. do sporu), zapisz go wcześniej; kwoty i statusy zamówień zostają na zawsze.

**„Dociągnij zaległe teraz”** (Pulpit, prawy górny róg). Zakup domyka się dwiema drogami:
Stripe wysyła powiadomienie (webhook) natychmiast, a niezależnie od tego sklep co 10 minut
sam dopytuje Stripe o opłacone sesje. Jeśli kupujący mówi „zapłaciłem, a nic nie przyszło”,
ten przycisk robi to dopytanie od ręki.

---

## 4. Mail po zakupie i link do pobrania

Po opłaceniu kupujący dostaje **jeden** mail (sklep pilnuje, żeby nie wysłać dwóch), a w nim:

- link do pobrania — ważny **72 godziny**, do **10 pobrań**,
- potwierdzenie zgody na dostarczenie treści cyfrowej (dosłowna treść i data),
- krótką instrukcję instalacji wtyczki,
- informację o gwarancji, jeśli produkt ją ma,
- jednorazowy link do wystawienia opinii (ważny 30 dni).

**Wyślij ponownie** (w szczegółach zamówienia) wysyła mail ze **świeżym linkiem** — nowy termin
72 godzin i nowy limit pobrań; poprzedni link przestaje działać. Gdyby wysyłka się nie udała,
sklep przywraca poprzedni link.
Przycisk jest dostępny tylko dla zamówień w stanie „mail wysłany” albo „błąd maila”: dla zwróconych,
spornych i jeszcze niedomkniętych sklep odmawia — link, który kupujący już ma, działa do końca
swojej ważności.

Gdy link wygaśnie albo limit się wyczerpie, kupujący widzi na stronie pobierania Twój
**mail kontaktowy** z Ustawień — żeby napisał do Ciebie, a Ty użyjesz „Wyślij ponownie”.

⛔ **Wysyłką maili rządzi hosting, nie sklep.** Jeśli maile nie wychodzą (status „błąd maila”
albo mail ląduje w spamie), skonfiguruj wysyłkę przez SMTP dowolną wtyczką SMTP i ustaw
w Ustawieniach nadawcę z adresem w Twojej domenie.

---

## 5. Opinie kupujących

**Sklep Agentów → Opinie.** Trzy zakładki: **Oczekujące**, **Przyjęte**, **Odrzucone**.

Opinię może wystawić wyłącznie kupujący — z jednorazowego linku w mailu po zakupie, jedna
opinia na zamówienie, w ciągu 30 dni. Nie ma ocen od anonimowych gości ani zbiorczej średniej.

Nowa opinia trafia do **Oczekujących** i **nie jest widoczna** na stronie produktu, dopóki nie
klikniesz **Przyjmij**. **Odrzuć** chowa ją; możesz ją później przyjąć z zakładki Odrzucone. Strona Opinii pokazuje też, ile maili z linkiem do opinii wysłano bez odpowiedzi.

---

## 6. Dane osobowe (RODO)

Co sklep przechowuje o kupującym: adres e-mail z płatności Stripe, kwotę, czas i treść zgody,
historię pobrań (każde pobranie z adresem internetowym, z którego przyszło — w osi czasu zamówienia). Dane karty obsługuje wyłącznie Stripe — na Twoim serwerze ich nie ma.

**Anonimizacja na żądanie kupującego:** Szczegóły zamówienia → **Dane osobowe (RODO)** →
przycisk anonimizacji. Sklep usuwa adres e-mail z zamówienia, z dziennika zdarzeń i z opinii
tego zamówienia, unieważnia link do pobrania (zanonimizowany kupujący nie ma już dokąd dostać
świeżego linku) i zapisuje datę anonimizacji. **Kwoty i czasy zostają** — statystyki sprzedaży
nie tracą historii. Operacja jest nieodwracalna. Adresów internetowych przy pobraniach anonimizacja
**nie** usuwa — znikają razem z całą osią czasu po 90 dniach (rozdział 7); odpowiadając kupującemu
na pytanie, co sklep o nim ma, wymień też je.

Jeśli usunięcie nie uda się w całości (np. błąd zapisu), sklep **nie udaje**, że się udało:
pokazuje błąd, nie potwierdza usunięcia (dane mogą nadal być w bazie — nie potwierdzaj ich
usunięcia osobie, która o to prosiła), w osi czasu pojawia się zdarzenie błędu, a Ty powtarzasz
operację, aż przejdzie.

Przy instalacji powstały dwa szkice stron: **Regulamin sklepu** i **Polityka prywatności
sklepu**. Uzupełnij je swoimi danymi sprzedawcy i opublikuj, zanim zaczniesz sprzedawać naprawdę.

---

## 7. Dziennik

**Sklep Agentów → Dziennik**, dwie zakładki:

- **Logowania** — udane i nieudane logowania do kokpitu, z adresem, z którego przyszły.
  Po nieudanych próbach sklep wydłuża czas odpowiedzi (do minuty), a od siódmej próby w ciągu
  minuty z tego samego adresu odmawia na minutę — spowalnia zgadywanie hasła, nie blokuje konta
  na stałe.
- **Zdarzenia** — to samo, co osie czasu wszystkich zamówień, w jednym miejscu: płatności,
  wysyłki, pobrania, zwroty, spory, anonimizacje, eksporty CSV.

W **obu** zakładkach — a więc także w osiach czasu zamówień — wpisy starsze niż **90 dni** znikają
same (sprzątanie co 10 minut). Zamówienia, kwoty i statusy zostają.

---

## 8. Ustawienia

**Sklep Agentów → Ustawienia.**

![Ustawienia sklepu](zrzuty/ustawienia.png)

- **Klucz sekretny Stripe** i **Sekret webhooka** — wklejasz raz; sklep przechowuje je bez
  podglądu i nie wraca ich do formularza (widzisz tylko „ustawiony”). Puste pole przy zapisie
  = bez zmian; kasujesz przez „Usuń zapisany sekret”.
- **Nazwa i adres nadawcy** — tylko dla maili sklepu; reszta poczty WordPressa zostaje bez zmian.
  Adres musi być dopuszczony przez Twój hosting (SPF/DKIM), inaczej maile lądują w spamie.
- **Mail kontaktowy właściciela** — pokazywany kupującemu, gdy link wygasł albo limit się wyczerpał.
- **Domyślna waluta nowych produktów.**
- **Dane przy odinstalowaniu** — domyślnie po usunięciu wtyczki dane sprzedaży **zostają**
  w bazie (historia zamówień to nie śmieć). Zaznaczone „Pełne czyszczenie” usuwa przy
  odinstalowaniu tabele, ustawienia, strony sklepu i pliki chronione. Klucze Stripe
  i zaplanowane zadanie sklep usuwa zawsze.

**Tryb testowy a sprzedaż naprawdę.** Dopóki w Ustawieniach są klucze testowe (`sk_test_…`),
płatności nie są pobierane. Oznaczenia trybu testowego — na Pulpicie, na liście sklepu, na stronie
produktu, na stronie po zapłacie i w mailu — są w tej wersji **wpisane na stałe** i nie znikną po
samej zmianie kluczy. Przejście na prawdziwe płatności to osobna decyzja i osobna paczka: pełną
listę kroków i tego, co **nie** znika samo, znajdziesz w `INSTRUKCJA-INSTALACJI.md`, rozdział
„Test a produkcja”. Zaczyna się od jednego kroku: napisz do nas.

---

## 9. Najczęstsze pytania

**Zakup „wisi” na stronie Dziękujemy.** Powiadomienie od Stripe nie dociera — najczęściej
przez zły sekret webhooka (sekret z panelu Stripe i z narzędzia Stripe CLI to dwa różne
sekrety). Sklep i tak domknie zakup w ciągu ok. 10 minut, a od ręki zrobi to „Dociągnij
zaległe teraz” na Pulpicie.

**Mail z linkiem nie przyszedł.** Treść maila jest w szczegółach zamówienia razem
z „Wyślij ponownie”. Jeśli wysyłki padają regularnie — SMTP (rozdział 4).

**Nie mogę włączyć sprzedaży produktu.** Brak potwierdzonego pliku ZIP (rozdział 2).

**Strona produktu pokazuje 404.** Ustawienia → Bezpośrednie odnośniki → Zapisz zmiany
(odświeżenie reguł adresów). Struktura odnośników musi być inna niż „Zwykłe”.

**Kupujący chce zwrotu.** Zwrot robisz w panelu Stripe; sklep sam odnotuje go w zamówieniu
(status „zwrócone”, kwota w osi czasu) i odejmie od statystyk sprzedaży.

**Czy pliki są bezpieczne na serwerze nginx?** Katalog plików chroni `.htaccess`, który
działa na Apache, a na nginx jest ignorowany. Prawdziwą ochroną jest brama w sklepie (token,
termin, limit) i losowa nazwa katalogu — na nginx dołóż regułę blokującą ten katalog po stronie
serwera (opis w `DOKUMENTACJA-TECHNICZNA.md`).

**Czy kupujący zakłada konto?** Nie. Zakup bez konta: e-mail podany na stronie płatności
Stripe, link w mailu, jednorazowy link do opinii.

---

## 10. Odinstalowanie

Wtyczki → Sklep Agentów → Wyłącz → Usuń. Co zostaje, decyduje ustawienie „Dane przy
odinstalowaniu” (rozdział 8). Strony sklepu, produkty i zamówienia zostają, dopóki nie włączysz
pełnego czyszczenia; klucze Stripe i zadanie cykliczne znikają zawsze.

---

Masz pytanie, którego tu nie ma? Napisz do nas — instrukcja rośnie o to, o co pytają właściciele.
