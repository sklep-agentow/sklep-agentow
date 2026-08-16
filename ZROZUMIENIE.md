# ZROZUMIENIE — sklep z agentami (podstrona /sklep, EGZAMIN nr 2 od kolegi)

**Start zegara: 2026-08-16 22:11** (treść od Dzidka w czacie).
**Status: PLAN KLEPNIĘTY w wersji egzaminowej (16.08 ~23:45)** — architektura z `ARCHITEKTURA-PROPOZYCJA.md` zaakceptowana kierunkowo przez Dzidka z twardym zakresem:

## ⭐ ZASADA EGZAMINU (Dzidek 17.08 ~00:20): ZERO PYTAŃ DO KOLEGI
Wszystkie niejasności rozstrzygamy SAMI i dokumentujemy w rejestrze decyzji (PLAN-ETAP1.md).
„Kursy" = egzamin DRUGIEGO zdającego, nie nasz temat. Koło ratunkowe (1 telefon) = na prawdziwy zator.

## ⭐ CEL I ZAKRES (decyzja Dzidka 16.08 ~23:45)
**CEL NR 1 = ZDAĆ EGZAMIN u kolegi. Budujemy WSZYSTKO w trybie testowym płatności — zero prawdziwych pieniędzy, zero prawdziwych kont właścicieli.**
Odłożone NA PÓŹNIEJ (po egzaminie, osobna robota): aktywacja prawdziwych kont Stripe u 4 właścicieli · domeny i maile produkcyjne · wdrożenia na 4 żywe strony. Projekt ZOSTAJE multi-site-ready (config per strona), bo to tanie teraz, a wymagane później.

## SUROWE ZDANIA (verbatim, pisownia oryginalna — to jest źródło, nie moje streszczenie)

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

Odpowiedzi Dzidka (czat, 16.08 ~22:20):
> kursy to nie my my agnetów ai 3 najlpeij chyba to co umiemy instrukcjie agenta bota diagramy itp ale nwm cos wymyslimy 4 to dalej bedzie zleceine testowe jak cos egzamin od kolegi to jest drugi czaisz tyle ze to wdrązymy do mojej i kolgi i 2 innych osób strony czaisz taki test jak prawdziwe zlecenie ale kiedys tego uzyjemy na naszej stronie rozumiesz nwm co z pąłtnosciamy trzba zrobic jakies najpopularniejsze ale nwm jak i czy to ma jakis zobowiązania ? 5 my chyba sie nie zajmmujemy tym kontamy na sałą strone bedzie jedno konto kiedys

Z wcześniejszej rozmowy (czat 16.08 wieczór): podstrona `/sklep` na istniejącej stronie · strona NIE na WordPressie, „zwykły HTML" · będzie dostęp do repo i do strony · oddanie JAK MP: paczka + instrukcja dla klienta, wpinane/odpinane · „ma normalnie sprzedawać" · „sprzedaż cyfrowa" · „zlecenie małe, ale trzeba myśleć, żeby głupot nie strzelić".

## FAKTY (mój odczyt — Dzidek potwierdza/prostuje w czacie)

1. **Charakter zlecenia: EGZAMIN nr 2 od kolegi** (pierwszy = MP). Test jak prawdziwe zlecenie, ocenia kolega.
2. **Wdrożenie na 4 strony**: Dzidka, kolegi i 2 innych osób. Kiedyś także nasza przyszła strona. → paczka musi być KONFIGUROWALNA per strona (nazwa, produkty, klucze płatności), nie szyta pod jedną.
3. Podstrona-sklep na istniejącej stronie (statyczny HTML, nie WordPress).
4. **2 produkty-agenty**: ChatBot LIVE, Segregator maila. Każdy: cena + opis sprzedażowy (co robi, jak działa, ile czasu oszczędza w firmie).
5. **Produkt po zakupie = paczka cyfrowa**: „to co umiemy — instrukcje agenta, bota, diagramy itp." Treść dokładna TBD („coś wymyślimy") — dla mechanizmu sklepu wystarcza: produkt to PLIKI do wydania po płatności.
6. Po zakupie kupujący dostaje maila: potwierdzenie + instrukcje.
7. **Panel admina**: dashboard sprzedaży (kiedy, za ile, w jakim czasie) + dziennik logowań admina (kto, kiedy, konkrety).
8. **Baza**: dane kupujących/płatności · produkty z pełnym CRUD · dziennik logowań admina.
9. **Konta kupujących: NIE ROBIMY.** Zakup „z ulicy" (podaje maila). Na całą stronę będzie JEDNO konto — admina.
10. **Kursy: nie nasz towar** — my sprzedajemy agentów AI. [Moje odczytanie rozjazdu z notatką kolegi — patrz K1 niżej.]
11. **Telefon jako koło ratunkowe = ZASADA EGZAMINU, nie funkcja sklepu** (Dzidek, czat ~22:25: „to ostatnie z telefonem to dla mnie jest"): Dzidek ma JEDEN telefon (do kolegi) jako ratunek podczas egzaminu — trzymamy go na prawdziwy zator. W sklepie NIC z tego nie budujemy.
12. Płatności — research ZROBIONY 16.08 wieczór (subagent + moja dogrywka), wynik w P4 niżej.

## ZASADY PRACY Z REPO I DEMO (Dzidek, czat 16.08 ~22:50)

- Repo, do którego dostaniemy link: **TYLKO ODCZYT** — nic tam nie ruszamy, żadnych commitów/branchy/Issues.
- Robota: **klonujemy do NOWEGO repo na koncie klienckim** (art) i tam prowadzimy cykl branch→PR→merge po naszemu.
- **Demo/żywej strony oryginału NIE dotykamy** — po sklonowaniu stawiamy WŁASNE środowisko-kopię (to zresztą wymaga bramka C procesu).

## MUST NOT (czego sklep NIE robi — łapie nadgorliwość)

- NIE budujemy systemu kont/logowania dla kupujących.
- NIE sprzedajemy kursów (nasz towar = agenci); schemat bazy może mieć pole „typ produktu" na przyszłość — patrz K1.
- NIE trzymamy sprzedawanych plików w publicznym repo/hostingu statycznym (R1).
- NIE dotykamy reszty strony klienta poza punktem wpięcia.
- NIE budujemy w sklepie żadnego mechanizmu „telefonu wsparcia" — to była zasada egzaminu, nie funkcja produktu.

## KONFLIKTY ŹRÓDEŁ (proces: konflikt = STOP, pyta człowiek)

- ~~K1. „KURSÓW" w notatce~~ ✅ ROZWIĄZANE (Dzidek 16.08 ~23:55): „kursy" w notatce = zadanie DRUGIEGO ZDAJĄCEGO (inny kolega ma egzamin ze sklepu dla kursów). MY = wyłącznie agenci. Nic pod kursy nie budujemy i nie kombinujemy.

## MOJA OCENA ZAKRESU (dowód, nie przeczucie)

Tekst mały, ale funkcje = mała **aplikacja z zapleczem**: baza + panel z logowaniem + płatności + wysyłka maili + dziennik logowań. Statyczny HTML żadnej z tych 5 rzeczy nie umie — zaplecze musi gdzieś mieszkać. Decyzja GDZIE = po zobaczeniu repo i hostingu (P1). Wymóg „4 strony + wpinane/odpinane" pcha w stronę zaplecza taniego w powieleniu (per strona osobna instancja/konfiguracja).

## RYZYKA

- **R1.** Sprzedaż cyfrowa: pliki produktu NIE MOGĄ leżeć publicznie — wydanie pliku po stronie zaplecza/operatora płatności.
- **R2.** Dane osobowe + płatności = RODO → bramka `bezpieczenstwo-danych-klienta` przed oddaniem. Przy 4 wdrożeniach: każdy właściciel strony = osobny administrator danych (instrukcja musi to mówić).
- **R3.** „Wpinane/odpinane" bez systemu wtyczek = do zaprojektowania: zamknięty katalog + jeden punkt zaczepienia + granica wypięcia obejmuje zaplecze i bazę.
- **R4.** Płatności: stawiam (do sprawdzenia), że operatorzy wymagają firmy/weryfikacji KYC — a wdrażają 4 RÓŻNE osoby, każda potrzebuje WŁASNEGO konta operatora, żeby kasa szła do niej. To może być największe „zobowiązanie" całego zlecenia.
- **R5.** Panel admina = powierzchnia ataku; dziennik logowań w wymaganiach — projektować od razu.
- **R6.** Egzamin od kolegi: ocenia OSOBA TRZECIA — rubryka GOTOWE musi powstać ze scenariuszy egzaminatora (bramka D), nie z naszej architektury.

## OTWARTE PYTANIA

- ~~P1 DOSTĘPY~~ ✅ 16.08 ~23:00: `matthewplugins.github.io` + repo `MatthewPlugins/matthewplugins.github.io` (TYLKO ODCZYT). Zwiad zrobiony sama — wynik i propozycja: `ARCHITEKTURA-PROPOZYCJA.md`. Hosting docelowy: darmowy GitHub Pages (Dzidek: kolega może KIEDYŚ przenieść na płatny — projektujemy przenośnie).
- **P7. KOLEGA = MatthewPlugins?** — potwierdzić, że egzaminator to właściciel tej strony (MP = MatthewPlugins?). [OTWARTE]
- **P8. ARCHITEKTURA** — czeka na klepnięcie Dzidka (`ARCHITEKTURA-PROPOZYCJA.md`). [OTWARTE]
- **K1. KURSY W BAZIE** — potwierdzić odczyt godzący. [OTWARTE]
- ~~P2 KLIENT/KURSY~~ ✅ egzamin nr 2 od kolegi, 4 strony. ~~P3 POSTAĆ PRODUKTU~~ ✅ paczka cyfrowa (pliki). ~~P5 KONTA~~ ✅ nie robimy, jedno konto admina. ~~P6 TELEFON~~ ✅ zasada egzaminu (FAKT 11), nie funkcja.

## P4 PŁATNOŚCI — WYNIK RESEARCHU (16.08 wieczór)

**Potwierdzone w źródłach OFICJALNYCH:**
- **Stripe** (stripe.com/pl/pricing): karta 1,5% + 1 zł · BLIK 1,6% + 1 zł · ZERO opłat startowych i abonamentu · Payment Links (gotowa strona płatności bez kodu).
- **Przelewy24** (FAQ + tabela opłat P24): karty 1,29% + 0,30 zł · brak opłaty rejestracyjnej/abonamentu/integracji · generator linków płatności.
- **Tpay** (cennik PDF, przez wyszukiwarkę): BLIK 1,78% + 0,39 zł · AKTYWACJA 199 zł (promo 49 zł) → odpada dla mikro-skali.
- **Autopay** (oficjalna podstrona „płatności dla działalności nierejestrowanej"): obsługuje osoby BEZ firmy (weryfikacja na PESEL) · aktywacja 199 zł · prowizja 1,19% + 0,34 zł · BLIK · pay-by-link.

**Wg źródeł NIEOFICJALNYCH (blogi/forum — do potwierdzenia przy zakładaniu konta):** Stripe działa też dla osób bez zarejestrowanej firmy (typ „Sole Proprietor/osoba fizyczna"); konto bankowe musi należeć do tej samej osoby.

**Niepotwierdzone nigdzie wprost:** czy P24 przyjmie osobę bez firmy · okresy wypowiedzenia u wszystkich · automatyczne wydawanie PLIKÓW po płatności (żaden operator nie deklaruje tego wprost — wydawanie pliku robi NASZE zaplecze po webhooku płatności).

**REKOMENDACJA: Stripe** — zero kosztów wejścia i abonamentu, BLIK, gotowa strona płatności (Payment Links) = nasza statyczna strona NIE dotyka danych kart (mniejsze RODO/PCI). Rezerwa gdy komuś onboarding Stripe nie przejdzie: Autopay (oficjalnie dla nierejestrowanych, ale 199 zł wejścia; prowizja niższa: 1,19% + 0,34 zł).
**Konsekwencja praktyczna:** każda z 4 osób zakłada WŁASNE konto operatora (kasa idzie na jej rachunek; KYC per osoba — wniosek logiczny, nieznaleziony wprost w FAQ). Status osób (firma / bez firmy) → pytanie do Dzidka.
**Zobowiązania — odpowiedź dla Dzidka:** przy Stripe/P24 brak abonamentu i opłat stałych; płacisz tylko prowizję od udanej sprzedaży. Jedyne realne „zobowiązanie" = przejście weryfikacji tożsamości u operatora + rachunek bankowy na siebie.
**TRYB TESTOWY (pytanie Dzidka 16.08 ~22:40):** budujemy CAŁOŚĆ na kluczach testowych Stripe (karty testowe, zero prawdziwych pieniędzy, bez aktywacji konta/KYC) — pełny przepływ zakupu, webhook, mail, wydanie pliku działa identycznie jak na żywo. Egzamin u kolegi zdajemy na trybie testowym. Przejście na prawdziwe płatności = właściciel strony aktywuje swoje konto Stripe i podmienia klucze w pliku konfiguracyjnym paczki — zero przebudowy. [Szczegół do potwierdzenia przy budowie: symulacja BLIK w trybie testowym.]

## NASTĘPNY RUCH

1. P1 od Dzidka → zwiad repo/hostingu (sama).
2. Wynik researchu płatności → rekomendacja z cenami i zobowiązaniami dla Dzidka.
3. Potem: brief-tabela + decyzja architektury zaplecza (opcje z ceną) → klepnięcie Dzidka → bramki A–E z `PROCES-PROJEKTU.md` → `rozruch-projektu.sh`.
