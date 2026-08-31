# 01 — ZAMÓWIENIE (SKLEP Z AGENTAMI) — przepisane ze źródła, NIE streszczone

> ⛔ **To jest jedyne źródło prawdy o tym, co produkt ma robić. Wszystko poza tą listą
> jest poza zakresem.** Zdanie zamykające ten plik (na samym końcu) rozstrzyga kolejność
> ważności wobec kodu i wobec innych dokumentów projektu.
>
> Materiał dotyczy WTYCZKI SKLEP (`sklep-agentow`) oraz — w zakresie, w jakim zamówienie
> mówi o produktach sprzedawanych w sklepie — dwóch botów (`chatbot-live`,
> `segregator-maila`) jako TOWARU sklepu. Pełna litera zamówienia botów (co bot ma robić
> OD ŚRODKA) jest osobno w `audyt/boty/material/01-zamowienie.md` — nie duplikuję jej tu.

## (a) SUROWE ZDANIA KLIENTA (verbatim, pisownia oryginalna)

Źródło: `ZROZUMIENIE.md`.

### a.1 Notatka kolegi + odpowiedzi właściciela + zdania z wcześniejszej rozmowy
(`ZROZUMIENIE.md`, sekcja „## SUROWE ZDANIA", linie 85–102)

Treść zlecenia (notatki z rozmowy z kolegą):
> 1. Sklep z agentami (2x)  Dzidek - sklep - szczegoly:
> 1. Po zakupie, klienta na maila potwierxze, instrukcje,
> 2. Dwa rozne, ceny, co robi - na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie
> 3. Admin panel - widok kiedy za ile kupili w jakim czasie/ dashboard
> Dzidek - 1. ChatBot LIVE
> Dzidem - 2. Segregator maila
> BAZY DANYCH 1. Dane uzytkownikow / platnksci /
> BAZY DANYCH 2. ZAPIS AGENTOW I KURSOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU
> BAZY DANYCH - KTO KIESY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY
> 1. Telefon jako kolo ratunkowe [ do wyk9rzystania kazdy po 1 razie ]

Odpowiedzi właściciela (czat, 16.08 ~22:20):
> kursy to nie my my agnetów ai 3 najlpeij chyba to co umiemy instrukcjie agenta bota diagramy itp ale nwm cos wymyslimy 4 to dalej bedzie zleceine testowe jak cos egzamin od kolegi to jest drugi czaisz tyle ze to wdrązymy do mojej i kolgi i 2 innych osób strony czaisz taki test jak prawdziwe zlecenie ale kiedys tego uzyjemy na naszej stronie rozumiesz nwm co z pąłtnosciamy trzba zrobic jakies najpopularniejsze ale nwm jak i czy to ma jakis zobowiązania ? 5 my chyba sie nie zajmmujemy tym kontamy na sałą strone bedzie jedno konto kiedys

Z wcześniejszej rozmowy (czat 16.08 wieczór): podstrona `/sklep` na istniejącej stronie ·
strona NIE na WordPressie, „zwykły HTML" · będzie dostęp do repo i do strony · oddanie JAK
MP: paczka + instrukcja dla klienta, wpinane/odpinane · „ma normalnie sprzedawać" ·
„sprzedaż cyfrowa" · „zlecenie małe, ale trzeba myśleć, żeby głupot nie strzelić".

### a.2 PIWOT 17.08 wieczór — wyłącznie cytaty verbatim
(`ZROZUMIENIE.md`, sekcja „## 🔄 PIWOT 17.08 WIECZÓR", linie 5–67 — pominięte nasze
komentarze/analizy: opis faktów PW-1…PW-6 poza samymi cytatami w nich zagnieżdżonymi,
„CO PIWOT UNIEWAŻNIA", „CO ZOSTAJE BEZ ZMIAN", „OTWARTE PO PIWOCIE")

Surowe zdania właściciela (czat 17.08 ~22:30-23:00, verbatim):
> „pedzie zmiana planów jak cos ta strona na którą robimy zlecenie bedzie wgrywana na worpresa słuchaj teraz uwaznie"
> „ok wiec tak narazie zmien wszytsko w zlecenie u w planie itp zebyśmy wiedzieli ze to bedą jednak pluginy do worpresa te wtyczki ok ?"
> „tak całą ta strona bedzie przekonwertowana na worpresa czaisz ? wiec te nasze zlecenie ten sklep i 3 pluginy bedą wyczkami do worpresa ogarniasz ?"
> [okienko, zaplecze:] „Wszystko w WordPressie" (wtyczka z własnymi tabelami, panel w wp-admin, Stripe i maile z serwera WP)
> „poukładaj wszytsko ładnie zaraz bedzie jesze inna wiadomośc tez wazna dosyc i nam ułatwi jak cos troche wszytsko"
> „do zlecenia jesze 2 testowe boty są do wystawienie jako produty na tym skielepi"

Notatka zlecenia — wersja aktualna (właściciel, czat 17.08 ~23:20, verbatim; polecenie:
„zapisz to gdzies ładnie to zlecenie i ze to wtyczki do worpresa"):
> sklep - szczegoly:
> 1. Po zakupie, klienta na maila potwierxze, instrukcje,
> 2. Dwa rozne, ceny, co robi - na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie
> 3. Admin panel - widok kiedy za ile kupili w jakim czasie/ dashboard
> Dzidek - 1. ChatBot LIVE
> Dzidem - 2. Segregator maila
> BAZY DANYCH 1. Dane uzytkownikow / platnksci /
> BAZY DANYCH 2. ZAPIS AGENTOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU
> BAZY DANYCH - KTO KIESY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY

Dodatkowe zdania właściciela wyjęte z opisu faktów piwotu (PW-1 i PW-6 — jedyne miejsca w
sekcji PW-1…PW-6, gdzie w środku naszego opisu jest CUDZY cytat w cudzysłowie; reszta
faktów PW to nasza analiza i jest pominięta):
> „i tak my mamy przekonwertowac to na worpresa tę cłą strona zaraz to obgadamy i zmieniamy w naszym planie" (17.08 ~23:40)
> „nic narazie tam nie ruszamy … zeby przepadkiem nic tam nie popchac" (zasada dot. repo strony kolegi, PW-6)

## (b) MAPA-SPEC.md — notatka ↔ nasze rozumienie (CAŁA tabela, obie części)

Źródło: `MAPA-SPEC.md`. Legenda statusów ze źródła (linie 4–7): DOSŁOWNE (litera źródła) ·
INTERPRETACJA (nasza decyzja — wymaga klepnięcia właściciela) · INTERPRETACJA (rozliczona
źródłem) — odczyt nasz, ale POTWIERDZONY późniejszym zdaniem źródła · ODSTĘPSTWO
PRZYJĘTE (świadome, z datą i powodem) · NIE-FUNKCJA (nie jest wymaganiem produktu).
Kolumna „Dowód" wypełniana w trakcie budowy (plik:linia / test) — w źródle w większości
nadal pusta (⬜).

| ID | Cytat ze źródła | Status | Nasze wykonanie | Dowód |
|----|-----------------|--------|-----------------|-------|
| S-01 | „Sklep z agentami (2x)" | DOSŁOWNE | 2 produkty-agenci w sklepie | ⬜ |
| S-02 | „Po zakupie, klienta na maila potwierdze, instrukcje" | DOSŁOWNE | mail po webhooku: potwierdzenie + instrukcje + link | ⬜ |
| S-03 | „Dwa rozne, ceny, co robi - na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie" | DOSŁOWNE | strony produktów z opisem sprzedażowym; „ile czasu oszczędza" poparte TREŚCIĄ paczki | ⬜ |
| S-04 | „Admin panel - widok kiedy za ile kupili w jakim czasie/ dashboard" | INTERPRETACJA — KLEPNIĘTA (właściciel 18.08) | „w jakim czasie" = rozkład zakupów w czasie (lista + sumy w okresach); „czas zakupu" = czas webhooka | ⬜ |
| S-05 | „1. ChatBot LIVE" | DOSŁOWNE | produkt 1 | ⬜ |
| S-06 | „2. Segregator maila" | DOSŁOWNE | produkt 2 | ⬜ |
| S-07 | „BAZY DANYCH 1. Dane uzytkownikow / platnksci" | INTERPRETACJA — KLEPNIĘTA (właściciel 18.08) | dane KUPUJĄCYCH (email) + dane transakcji (kwota, waluta, czas, id sesji, status); kont użytkowników BRAK — właściciel 16.08: „my się nie zajmujemy kontami, na całą stronę jedno konto" | ⬜ |
| S-08 | „ZAPIS AGENTOW I KURSOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU" | ODSTĘPSTWO PRZYJĘTE (17.08) | produkty (agenci) w bazie z pełnym CRUD; KURSY = egzamin DRUGIEGO zdającego (właściciel 17.08 ~00:00), u nas ich nie ma; „usuwanie" = deaktywacja przy istniejących zamówieniach (integralność — wzorzec kartki MP: nie usuwa się rzeczy powiązanej z aktywną sprawą) | ⬜ |
| S-09 | „KTO KIESY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY" | INTERPRETACJA — KLEPNIĘTA (właściciel 18.08) | dziennik logowań: login_wpisany („kto" przy jednym koncie; nieudane próby też), czas, wynik, IP, przeglądarka („konkrety"); ROZSZERZENIE ponad literę: log AKCJI admina (jawnie dopisane) | ⬜ |
| S-10 | „Telefon jako kolo ratunkowe [do wykorzystania kazdy po 1 razie]" | NIE-FUNKCJA | zasada EGZAMINU: 1 telefon do egzaminatora. POTWIERDZONE 2× przez właściciela (17.08 ~00:00 oraz ~06:15, PO diffie reconstruction testu: „na pewno dla mnie był jeden telefon, nic do projektu"). W produkcie NIC nie budujemy | — |
| S-11 | (czat) podstrona `/sklep` na istniejącej stronie | DOSŁOWNE | katalog sklep/ wpinany w stronę | ⬜ |
| S-12 | (czat) „nie na wordpressie, zwykły HTML… na githubie hostowane" | DOSŁOWNE | statyczny hosting; zaplecze osobno | ⬜ |
| S-13 | (czat) „jak te pluginy: przypinamy, odpinamy i nie ma" | DOSŁOWNE | paczka + 1 punkt zaczepienia; odpięcie = czysty diff źródła + wyłączone zaplecze | ⬜ |
| S-14 | (czat) „najważniejsze zdać egzamin; na razie nie robimy na gotowo z prawdziwymi płatnościami" | DOSŁOWNE | całość w trybie testowym; produkcja = jawny rozdział różnic | ⬜ |
| S-15 | (czat) „wdrożymy do mojej i kolegi i 2 innych osób strony" | DOSŁOWNE — DOPRECYZOWANE 17/18.08 | ZMIANA (właściciel, noc 17/18.08): strona = JEDNA WSPÓLNA dla całej czwórki (4 konta adminów przy wdrożeniu), NIE 4 osobne instalacje; wtyczka mimo to konfigurowalna w Ustawieniach (przenośność), test na czystym WP w K6 | ⬜ |
| S-16 | (czat) „termin do 30-tego i 3 próby oddania" | DOSŁOWNE | harmonogram z próbą 1 dnia 27.08 | — |

### 🔄 PIWOT 17.08 WIECZÓR (druga część tabeli — źródło: właściciel, czat, verbatim w ZROZUMIENIE.md §PIWOT)

| ID | Cytat ze źródła | Status | Nasze wykonanie | Dowód |
|----|-----------------|--------|-----------------|-------|
| S-17 | „całą ta strona bedzie przekonwertowana na worpresa" | DOSŁOWNE | platforma docelowa = WordPress; konwersję strony robi NIE-my (założenie do potwierdzenia) | — |
| S-18 | „ten sklep i 3 pluginy bedą wyczkami do worpresa" + „do zlecenia jesze 2 testowe boty są do wystawienie jako produty na tym skielepi" | INTERPRETACJA (rozliczona źródłem) | sklep = wtyczka WP · produkty = 2 testowe boty jako wtyczki WP (ChatBot LIVE, Segregator maila); „3 pluginy" = sklep+2 boty ŁĄCZNIE — potwierdza notatka-wersja-aktualna 17.08: „Dwa rozne" | ⬜ |
| S-19 | [okienko] zaplecze „Wszystko w WordPressie" | DOSŁOWNE | własne tabele w bazie WP + panel w wp-admin + Stripe/maile z serwera WP; zero Cloudflare | ⬜ |
| S-20 | notatka-wersja-aktualna 17.08: „ZAPIS AGENTOW DO BAZY DANYCH..." — BEZ „I KURSOW" | DOSŁOWNE | źródło samo potwierdza K1/S-08: kursów u nas nie ma; status S-08 przestaje być odstępstwem wobec NOWEJ wersji źródła | — |

**Wiersze UNIEWAŻNIONE piwotem:** S-11 (podstrona wpinana → wtyczka WP), S-12 („zwykły
HTML" → WordPress), S-13 w części mechanizmu (idea „przypinamy/odpinamy" ZOSTAJE —
realizacja = instalacja/deinstalacja wtyczki z czystym uninstall). S-01/S-05/S-06: liczba
produktów BEZ ZMIAN = **2** (ChatBot LIVE, Segregator maila — potwierdzone notatką 17.08),
zmienia się POSTAĆ: paczka plików → testowy bot jako wtyczka WP.

✅ **Wiersze INTERPRETACJA S-04, S-07, S-09: KLEPNIĘTE przez Dzidka 18.08** („wszystkie
trzy robimy tak jak się robi i jak robią dobre sklepy") — treść bez zmian mimo piwotu,
plus WYTYCZNA WIĄŻĄCA: dashboard, dziennik i strony sklepu wg WZORCÓW DZIEDZINY
e-commerce zbieranych PRZED budową (rejestr D12). **Audytor zgodności dostaje TEN plik +
surowe źródło — statusy INTERPRETACJA i ODSTĘPSTWO raportuje jako ZNANE, nie jako błędy.**

(Źródło całej sekcji (b): `MAPA-SPEC.md`, treść pełna.)

## (c) 12 KRYTERIÓW „GOTOWE" v3 (binarne)

Źródło: `PLAN-PROJEKTU.md`, „## 12. KRYTERIA „GOTOWE" v3 (binarne; wykonywane NA PACZCE
na świeżej instalacji)", linie 385–431 — DOSŁOWNIE:

1. Instalacja 3 wtyczek na czystym WP wg instrukcji przez NIE-autora ≤30 min, bez
   fatali i notice'ów (WP_DEBUG włączony).
2. Zakup kartą testową: webhook → zamówienie → mail ≤60 s (pomiar skryptem); mail w
   łapaczu SMTP; treść maila widoczna w panelu.
3. Link pobrania: właściwy plik (sha256 = snapshot); po terminie/limicie odmowa; 2
   równoległe pobrania przy ostatnim slocie → przechodzi dokładnie 1; HEAD nie zużywa
   limitu.
4. Webhook 2× = jedno zamówienie i jeden mail; **webhook i reconciliation odpalone
   RÓWNOLEGLE na tym samym zamówieniu = jeden mail i jeden WAŻNY token** (test
   wyścigu); ponowienie po przerwaniu przed mailem DOKAŃCZA (test z wymuszonym
   padnięciem); zły/brak podpisu = odrzut z błędem.
5. Podmieniona cena/produkt w żądaniu = zignorowane (cena z bazy); zakup bez zgody
   niemożliwy; akcje admina bez zalogowania/capability = odmowa; mutacja bez nonce =
   odmowa; seria żądań zakupu z jednego IP ponad próg = odmowa (rate limit zmierzony,
   nie tylko zadeklarowany).
6. Dashboard „jak dobre sklepy": zakup widoczny (czas webhooka, kwota, produkt ze
   SNAPSHOTU), sumy w okresach, eksport CSV; edycja/deaktywacja produktu NIE zmienia
   historii zamówień.
7. CRUD: nowy produkt widoczny w sklepie bez zmian kodu; bez potwierdzonego pliku nie
   da się aktywować; walidacje odrzucają cenę ≤0, pustą nazwę, plik nie-zip oraz plik
   ponad limit rozmiaru; poprawny upload ląduje pod losową nazwą w katalogu chronionym
   i jest POTWIERDZONY po zapisie (obecność + rozmiar).
8. Dziennik: logowania wszystkich adminów (udane i nieudane, IP, przeglądarka) + log
   akcji admina (CRUD/resend/eksport); po kolejnych nieudanych próbach logowania
   następna próba jest wykładniczo opóźniona (pomiar czasu odpowiedzi).
9. Uninstall: domyślnie dane zostają; z włączoną opcją czyszczenia — zero naszych
   tabel/opcji/stron/plików/cronów (diff przed-po pusty).
10. Boty: oba rdzenie DZIAŁAJĄ na czystym WP (ChatBot odpowiada z bazy Q&A i zbiera
    kontakt przy braku odpowiedzi; Segregator segreguje próbkę i pokazuje raport);
    instrukcje kompletne; opisy „ile czasu oszczędza" mają pokrycie w treści; strona
    produktu zawiera KOMPLET z S-03: co robi, jak działa, ile czasu oszczędza, cena
    (+ dla kogo/FAQ z układu §4A). Tryb regułowy (bez klucza) = test OBOWIĄZKOWY; tryb
    AI = test ręczny przy dostępnym kluczu (odpowiedź wyłącznie z informacji firmy +
    zjazd do reguł po odcięciu klucza).
11. WCAG-lite (labelki, klawiatura, fokus, KONTRAST) + RWD (zakup telefonem) +
    Chrome/Edge/Firefox; baner „TRYB TESTOWY" widoczny na stronach sklepu; strony
    Regulamin i Polityka istnieją jako szablony z polami sprzedawcy (zero cudzych
    danych).
12. Kalibracja audytu ≥2/3 mutantów ORAZ strażnicy CI przechodzą własny audyt
    mutacyjny — inaczej audyt nieważny, nie oddajemy.

Rubryka zamrażana po K2 jako `rubryka-GOTOWE.md`; połowa zakresu wtedy jeszcze nie
istnieje (uwaga krytyka), więc zamrożenie ma JAWNĄ ścieżkę zmiany: korekta rubryki po
zamrożeniu WYŁĄCZNIE osobnym PR z wpisem w DECYZJE (jak manifest bramek) — rubryka może
gonić rzeczywistość, ale nigdy po cichu.

⚠️ **Uwaga o kryterium 12 (źródło: `DECYZJE.md` D33, cytat):** dla audytu K7 próg jest
PODNIESIONY — D33 mówi wprost „kalibracja audytorów = 100% mutantów WŁASNEGO zakresu +
zero alarmów na pułapkach + poprawna kwalifikacja (**ostrzej niż kryterium GOTOWE #12
«≥2/3 puli» — 100% zawiera się w ≥2/3**)" i zapowiada, że „PLAN-PROJEKTU §12 wymaga
zmiany progu osobnym wpisem tutaj". Czytaj kryterium 12 razem z D33 — obowiązujący próg
dla K7 to 100%, nie ≥2/3.

## (d) §2 WYMAGANIA i §3 MUST NOT

Źródło: `PLAN-PROJEKTU.md`, linie 23–61 — DOSŁOWNIE.

### 2. WYMAGANIA

**FUNCTIONAL (litera notatki zlecenia — wersja aktualna 17.08):** po zakupie mail
(potwierdzenie + instrukcje) · 2 produkty z cenami i opisem „na zachętę" (co robi, jak
działa, jak skraca czas pracy) · panel admina: kiedy/za ile kupili/w jakim czasie
(dashboard) · baza: dane kupujących/płatności · produkty z pełnym CRUD (zapis przy
dodawaniu/usuwaniu/modyfikowaniu) · dziennik logowań na konto admina (kto, kiedy,
konkrety).
**Interpretacje S-04/S-07/S-09: KLEPNIĘTE przez Dzidka 18.08** z wiążącą wytyczną:
**„tak jak się robi i jak robią dobre sklepy"** → dashboard, dziennik i strony sklepu
projektujemy WG WZORCÓW DZIEDZINY e-commerce (przed budową K3/K5 zbieramy wzorce: jak to
robią dojrzałe sklepy), nie po najmniejszej literze.

**NON-FUNCTIONAL:** bezpieczeństwo standardami WP (sekcja 7) · RODO (sekcja 8) ·
WCAG-lite: labelki, kontrast, klawiatura+fokus (EAA) · RWD — zakup z telefonu;
Chrome/Edge/Firefox · wydajność: assety sklepu ładowane TYLKO na stronach sklepu (nie
spowalniamy reszty strony) · zgodność: WordPress 7.0 / PHP 8.4 (wersje stacka
docelowego) · spójny styl kodu (phpcs w CI; zakaz gołych var_dump/error_log w paczce).

**SCHEMA:** sekcja 5.

### 3. MUST NOT (świadomie poza zakresem)

Kont/logowania KUPUJĄCYCH nie ma (konta w WP mają tylko adminowie) · kursów NIE
sprzedajemy · faktur NIE wystawiamy · plików produktów NIE serwujemy bezpośrednio
(tylko brama PHP) · prawdziwych płatności NIE włączamy · przecen NIE MA (Omnibus-ceny —
jawnie nie dotyczy) · opinie WYŁĄCZNIE prawdziwe od zweryfikowanych kupujących, zbierane
jednorazowym linkiem z maila zakupowego i publikowane po ręcznej akceptacji (D6 +
UZUPEŁNIENIE 24.08 + D32) — bez zbierania od gości i bez ocen zbiorczych (Omnibus-opinie
DOTYCZY i jest spełniony weryfikacją zakupu przed publikacją oraz notą przy sekcji) ·
**WooCommerce NIE używamy** (D17 — sklep to nasza wtyczka) · **tłumaczeń/i18n NIE
robimy** (teksty polskie wprost — D21; omija też minę textdomain WP 6.7+) · multisite WP
NIE (jedna zwykła instalacja) · monitoringu zewnętrznego NIE w v1 · botów z PRAWDZIWYM
AI/IMAP NIE na egzamin (D19 — rdzeń demo; jawna droga rozbudowy w instrukcji) ·
**motywu i cudzych wtyczek strony NIE ruszamy** — granica „nie dotykamy reszty strony"
po piwocie: nasze wtyczki żyją we własnych tabelach/opcjach/stronach/shortcode'ach, a
odpięcie = deinstalacja z czystym uninstall.

---

Przy sprzeczności między tym plikiem a kodem wygrywa KOD jako fakt, a rozjazd jest
ZNALEZISKIEM; przy sprzeczności między tym plikiem a innym dokumentem projektu wygrywa
TEN plik.
