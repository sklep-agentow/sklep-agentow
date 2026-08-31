# DO PLANU v2 — dziedzictwo MP + SEO + zabezpieczenia (17.08 ~01:45)

Źródła: `docs/BRAMKI-ANTY-POWTORKA-bledy-kolegi.md` (warsztat; 22 błędy z 4 rund recenzji kolegi
+ 9 przyczyn-matek + druga opinia 2 modeli) · pytanie właściciela o SEO · „pamiętać o zabezpieczeniach".
Wchodzi do PLANU v2 razem z triage krytyki 1 i krytykami obcych modeli.

## A. DZIEDZICTWO MP → SKLEP (przełożone na ten stack, nie kopiowane w ciemno)

**Największa lekcja pliku bramek (sam o sobie to napisał):** zaprojektowane maszyny, których nie
zbudowano, to notatki — błędy wróciły. Dlatego w sklepie każda bramka niżej ma być ZBUDOWANA
w swoim przyroście, nie „po audycie". Wiedza nie blokuje wydania — blokuje TEST.

1. **Matka H (nowa, z 1.3.13): zapisuj skutek dopiero PO pomiarze, nie po wywołaniu.**
   U nas wprost: status `mail_wyslany` TYLKO po odpowiedzi 2xx dostawcy maili · „plik wgrany"
   po weryfikacji obecności w R2 · „zamówienie zapisane" po sprawdzeniu wyniku INSERT.
2. **Matka A + błędy #3/#20 (race): atomowość zamiast sprawdź-potem-zapisz.**
   Idempotencja webhooka = INSERT z UNIQUE na `stripe_session_id` (constraint decyduje, nie „czy
   istnieje?") · licznik pobrań linku = atomowy UPDATE z warunkiem (2 równoległe pobrania przy
   limicie 1 → przechodzi dokładnie 1) → TESTY równoległe od razu w przyroście.
3. **Odpinanie = SYMETRIA SNAPSHOTÓW (druga opinia #3), nie „grep zero śladu".**
   Test: snapshot plików strony-gospodarza PRZED wpięciem → wepnij → odepnij wg instrukcji →
   snapshot PO = identyczny (diff pusty). Osobno: wyłączenie zaplecza (Worker+D1+R2) wg instrukcji.
4. **Testuj PACZKĘ, nie repo (druga opinia #2).** Egzamin/audyt robimy na ZIP-ie, który dostaje
   właściciel: rozpakuj → wdróż DOSŁOWNIE wg instrukcji → dopiero na tym rubryka GOTOWE.
   (Kryterium #7 rubryki już to mówi — tu dochodzi „z paczki, nie z repo".)
5. **Bramki ZACHOWANIA, nie składni (druga opinia #4):** E2E przepływu zakupu klikaczem
   (Playwright na naszej kopii strony: wejdź → kup → sprawdź maila-atrapę → pobierz) jako test CI
   tam, gdzie się da, reszta ręcznie wg scenariusza. [Blizna: „klikacz łapie bugi testów hakowych".]
6. **Checklista „repo oczami kolegi" (matka B)** przed KAŻDYM pokazem: LICENSE w root · README
   aktualny i pisany Z KODU · plakietki CI zielone · PR→Closed widoczne (dowód procesu) · release
   z paczką i opisem PL · ⭐ **kolega zaproszony jako collaborator ZANIM dostanie link** (błąd #16
   z MP: link do prywatnego repo = 404 u niego).
7. **Docs = odbicie kodu (bramka specjalna MP, kolega wytknął 3×):** README i instrukcja pisane
   PO zamrożeniu kodu; obietnice sprawdzane grepem obu stron (`sprawdz-docs-vs-kod`); sekcja
   „czego sklep NIE robi" generowana z MUST NOT; mniej obietnic = mniej kłamstw.
8. **Środowisko przypięte (matka D):** wersje w CI i w instrukcji PINNED (wrangler, node, akcje
   @vN) — spisane z faktów przy budowie, nie z pamięci.
9. **LLM-audytor = generator HIPOTEZ, nigdy bramka (druga opinia #1, złamane przy 1.3.13!):**
   każda uwaga krytyka/recenzenta → test automatyczny albo jawne „świadomie odrzucone". Raport
   LLM nigdy nie zielini oddania.
10. **Sekrety w runtime (druga opinia #8):** Worker nie loguje kluczy ani tokenów (przegląd
    każdego `console.log`/odpowiedzi błędów przed oddaniem); klucz testowy Stripe też traktujemy
    jak sekret (nawyk > wygoda).
11. **Uwaga kolegi #14 (sposób pracy):** uwaga recenzenta = domyślnie może mieć rację; najpierw
    sprawdzam w docs z nastawieniem „może faktycznie lepiej", odpowiadam raz, z dowodem.

## B. SEO — DECYZJA (pytanie właściciela)

**Robimy SEO-MINIMUM produktowe (tanie, wygląda pro, ma sens):** unikalny `<title>` + meta
description per strona sklepu · OpenGraph (ładny podgląd przy udostępnianiu linku) · dane
strukturalne schema.org **Product** (nazwa, cena, waluta — Google umie pokazać cenę w wyniku) ·
czytelne adresy (`/sklep/chatbot-live/`) · wpis do `sitemap.xml` strony-gospodarza (jedna linijka,
część instrukcji wpięcia) · statyczna strona = szybkość za darmo.

**NIE robimy pozycjonowania** (badanie fraz, linki, treści blogowe) — to osobna usługa (skill
`seo-zlecenia`), nie część sklepu; egzaminator ocenia sklep, nie ruch z Google.

⭐ **WAŻNE ODWROTNE SEO: nasza DEMO-kopia strony kolegi MUSI mieć `noindex`** (meta robots +
robots.txt). Bez tego kopia konkuruje w Google z jego oryginałem (duplicate content) — zrobilibyśmy
mu krzywdę samym testowaniem. Do checklisty środowiska-kopii i do wrogiej sesji.

## D. Z RETROSPEKTYWY MP (przeczytana W CAŁOŚCI 17.08 ~02:10) — rzeczy grubsze niż bramki

1. **Liczby MP:** budowa 3,5 dnia · łatanie 12 dni · 131 napraw na 62 funkcje · 23 wydania.
   Budowanie NIE jest wąskim gardłem — pętla po oddaniu jest. Stąd cały nasz etap 1.
2. **Kolejność (7.1):** audyt awaryjny na PIERWSZYM działającym przelocie (u nas: na kości) —
   cztery sesje PO PYTANIU: czy działa · co gdy padnie · wrogie wejście i wyścig · co zostaje
   (zmierzone przy MP: 6 klas na 6 w 18 minut) · naprawa zawsze PO KLASIE (grep całe repo) ·
   dokumentacja PO zamrożeniu kodu · **JEDNO wydanie na koniec, nie trzynaście**.
3. ⭐ **LEKCJA ZAKRESU (5b) — dla nas może najważniejsza:** MP nie puchło od funkcji ponad
   zamówienie, tylko od MAKSYMALNEJ interpretacji każdego wymagania (spec: 4 tabele → zbudowano 16;
   kolumny country/lang, których nic nie wypełnia). U nas egzamin ZAKAZUJE pytań, więc twarda
   zasada: **przy niejasności wybieram interpretację MINIMALNĄ spełniającą literę notatki
   + wpis do rejestru decyzji z kosztem rozszerzenia.** Sklep: 4 tabele z planu i STOP.
4. **Korzeń wszystkiego:** „wiedza u nas istnieje zawsze — nie ma nic, co by ją egzekwowało".
   Przepustkę wystawia WYKONANIE (skrypt z kodem błędu), nie opis. Rubryka GOTOWE = skrypty,
   licznik w raportach liczony maszynowo (nigdy ręcznie — blizna „duże: 0" obok prozy o trzech dużych).
5. **Trzy twarde stopy (7.3)** → do hooka projektu: trzeci powrót tej samej klasy = STOP (zły model
   przyczyny) · audyt, który nie złapał podłożonego mutanta = jego „czysto" nieważne (kalibrator
   JUŻ w narzedzia-qa) · anomalia bez wyjaśnienia = otwarta blokada, nie „wątpliwe".
6. **Cel osiągalny (7.4):** nie „zero błędów" — **„zero POWTÓREK i zero rzeczy poważnych"**.
   Nowa klasa od egzaminatora = normalna praca; ta sama klasa kolejny raz = wpadka.
7. **Klasa „moje pomiary kłamały"** (najdroższa, niewidoczna dla kolegi): każdy pomiar w tym
   projekcie MA PRÓBĘ KONTROLNĄ (przypadek o znanym wyniku) — inaczej to wrażenie, nie pomiar.

## E. Z PRZEPISU-ZLECENIA v2 (przeczytany W CAŁOŚCI 17.08 ~02:40) — czego plan v1 nie miał

1. **ZASADA DRUGA przepisu: gdy bramka nie przechodzi — nie wolno ruszać BRAMKI.** Zakaz zmiany
   progu, osłabienia testu, „odświeżenia raportu". Wolno: naprawić produkt ALBO świadoma decyzja
   z podpisem właściciela. (Blizna 1.3.13: zmieniliśmy miarę, nie produkt.)
2. **Audyt pierwszego przelotu = SZEŚĆ sesji po pytaniu, nie cztery** (A czy działa · B co gdy
   padnie · B2 wrogie wejście i wyścig · C co zostaje · ⭐D czy to jest TO, co zamówił klient —
   każde kryterium z notatki → gdzie w kodzie; każda stała → skąd wzięta · E stara wersja).
   U nas: A–D obowiązkowe; **E (migracja ze starej wersji) = N/D w v1** — sklep nie ma poprzedniej
   wersji; wraca przy pierwszej aktualizacji paczki u właścicieli. Zapisane, nie zgubione.
3. ⭐ **BRAMKA LUDZKA przy wydaniu: automat robi 5–6 zrzutów krytycznych ścieżek** (sklep · Stripe
   testowy · mail z linkiem · panel-dashboard · dziennik logowań · dowód odpięcia), **właściciel ogląda
   i odblokowuje wydanie RĘCZNIE.** To jedyne prawdziwe wyjście z zamkniętej pętli „agent sam
   projektuje testy i sam je zdaje". Nie więcej niż 6 obrazków (bramka obchodzona z braku czasu
   jest gorsza niż żadna).
4. **Warunek zakończenia — lista, nie zegar (wszystkie 4):** zero celów ⬜ policzonych maszynowo ·
   mutanty złapane · zero anomalii bez wyjaśnienia („nie umiem powtórzyć" = zły test) · właściciel
   obejrzał zrzuty i odblokował.
5. **Naprawa klasą >5 plików = porcje po ≤5 z osobnym zielonym CI**; trzecia porcja z regresją =
   STOP, zły model przyczyny.
6. **Pytanie 5 przy pisaniu kodu: „jak zrobiono to samo obok?"** — złapałoby 3 z 6 błędów MP.
   U nas „obok" = oficjalne przykłady Stripe/Cloudflare + wzorzec zipsell, bo repo jest świeże.

## F. Z MAPY-WIEDZY działu audytu (przeczytana 17.08) — do fazy audytu

- Zasady sędziów przy krytykach/audycie: **sędzia nigdy słabszy od szukacza** · **UNIA znalezisk,
  nie głosowanie** (trudny błąd widzi jeden z trzech) · **wygląd raportu ≠ kompletność** ·
  pewność siebie nie koreluje z trafnością → kontrole maszynowe.
- **7 warunków odrzucenia raportu** (walidator; przy MP wdrożone 2/7) — przy naszym audycie
  stosować choć ręcznie: obalenie krótsze niż teza = niesprawdzone · obalenie bez pliku/linii ·
  brak hipotez odrzuconych · płaska waga · >3 „jeżeli" = przegrzanie.
- **Różnorodność przez SOCZEWKI (różne pytania), nie przez powtarzanie** — zmierzone przy MP.
- Test na przytakiwanie (podłóż CZYSTY kod: kto „znajdzie" nieistniejący błąd, odpada jako sędzia)
  — użyć przy kalibracji sesji audytowych sklepu.

## G. STANDARDY WŁAŚCICIELA Z MP (T1–T7 + WCAG-lite) — przełożone na sklep (17.08 ~02:55)

Źródło: `zlecenia/test-kolegi-serwis-wp/ZROZUMIENIE.md` (linie 14–18, 46, 54). właściciel NIE musi
dyktować od nowa — to jest jego stały standard jakości, obowiązuje też tutaj:

1. **Jakość kodu („syntax")**: spójny styl + lint w CI od pierwszego kodu (dla JS: eslint/prettier
   — odpowiednik PHPCS z MP); zero kodu bez przeglądu recenzenta.
2. **Bezpieczeństwo**: pełna sekcja 6 planu (odpowiedniki MP: podpis webhooka zamiast nonce ·
   walidacja i escaping KAŻDEGO wejścia — w tym panelu · limity TYPÓW i ROZMIARU plików przy
   uploadzie produktu · uprawnienia: wszystko adminowe za tokenem).
3. **RODO/privacy** (T5): minimalizacja (tylko email kupującego) · pseudonimizacja zamiast
   kasowania (triage #6) · **retencja**: po X dniach od zakupu email pseudonimizowany
   automatycznie [X do decyzji przy projekcie] · polityka prywatności + regulamin w paczce.
4. ⭐ **WCAG-lite / ARIA (z MP, klepnięte 21.07 — a w planie v1 tego NIE BYŁO):** labelki przy
   polach · kontrast · pełna obsługa klawiaturą + widoczny fokus · atrybuty aria na przyciskach
   i komunikatach · komunikaty błędów czytelne. Dotyczy WITRYNY i PANELU. (EAA — dostępność
   e-commerce to też wymóg prawny UE od 2025.)
5. ⭐ **Przeglądarki + RWD (T7, też brakowało wprost):** Chrome/Edge/Firefox + pełna
   responsywność (zakup z TELEFONU — egzaminator na pewno spróbuje).
6. **„Live" — egzaminator WIDZI maile:** odpowiednik Mailpit z MP dla naszego stacku:
   panel admina pokazuje PODGLĄD treści wysłanego maila przy zamówieniu (dowód wysyłki
   bez grzebania w skrzynce) + mail realny w trybie testowym.
7. **Backup/rollback (T6) po naszemu:** eksport bazy D1 przed każdą migracją schematu;
   skrypt przywrócenia opisany w instrukcji.

## H. Z KARTKI MP (obejrzana str1+str2, 17.08 ~03:05) — styl wymagań kolegi + 3 nowe konkrety

Kolega pisze wymagania w stylu: zakres → relacje bazy z „ZASADAMI INTEGRALNOŚCI" → przebieg
automatyzacji krok po kroku → tabela wymagań technicznych. Nasz plan ma tę samą strukturę ✓.
Z jego zasad integralności wynikają dla sklepu TRZY rzeczy, których plan v1 nie miał:

1. ⭐ **Zamówienie przechowuje SNAPSHOT produktu** (nazwa + cena z CHWILI zakupu), nie tylko
   `produkt_id`. Inaczej edycja/usunięcie produktu w CRUD przekłamuje historię sprzedaży
   w dashboardzie i treść maili. (Kartka MP: „brak możliwości usunięcia produktu powiązanego
   z aktywną sprawą" — nasz odpowiednik: produkt z zamówieniami = DEAKTYWACJA, nigdy twarde
   usunięcie; zamówienia żyją własną kopią danych.) Do modelu danych w v2.
2. **Nieusuwalna historia zdarzeń zamówienia** (kartka: „każda zmiana tworzy nieusuwalny wpis
   w osi czasu") — log zdarzeń append-only: opłacone → mail wysłany/błąd → ponowienie → pobrania.
   Spina się z maszyną stanów (triage #10) — kolega wyraźnie LUBI oś czasu.
3. **Higiena repo przed oddaniem (tematy właściciela: gitignore, .bak):** sweep jak przy MP —
   zero `*.bak/*.orig/*_backup/*.log/*.swp/~` w repo i w PACZCE (lista wzorców ze
   SPRZATANIE-RAPORT.md) + `.gitignore` od pierwszego commita · **zakaz `debugger`/gołych
   `console.log` w kodzie produkcyjnym** (reguła lintera, nie pamięć).

Uwaga do rozważenia dla właściciela: repo MP było PUBLICZNE (dlatego działała twarda branch
protection). Nasze jest prywatne (ochrona = hook lokalny). Jak zechcesz publicznie jak przy MP —
włączę twardą ochronę w minutę; koszt: kod sklepu widoczny dla świata.

## I. Z NOTATEK WŁAŚCICIELA (telefon: „tematy" 9.07 + „Lekcje" 1–24; zrzuty 17.08 ~03:15)

Pokryte już w planie (gdzie): gitignore→H.3 · WCAG/unijne→G.4 · supabase→rozważone i odrzucone
(ARCHITEKTURA) · security headers/CSP→triage #5 · polityka prywatności→sekcja 6 · testy
branchowe/claudowe/ręczne/segmentowe→CI+recenzent+bramka ludzka+E2E · sub-agenty/dispatch/batch→
metoda warsztatu · f12/testy na żywo→klikacz · rekordy odrzucone przez audyt→rejestr znalezisk.

**NOWE z notatek — dopisane do planu v2:**
1. ⭐ **„.bak" po jego myśli** (Lekcja 12 — jego własna definicja, nie plik!): błąd wykryty
   w commicie 12, a wprowadzony w 5 → COFNIJ SIĘ do commita-źródła (git log -S / bisect),
   zrozum, i spisz instrukcję anty-powtórkową. PROCEDURA przy każdej naprawie w sklepie:
   naprawa = (a) fix, (b) commit-źródło znaleziony, (c) lekcja do banku mutantów/bramek.
2. **JSON-LD nazwane wprost** (nota: „JSON.LD żeby podstrona miała SEO") — nasze dane
   strukturalne Product robimy w formacie JSON-LD. Było w B jako schema.org — teraz po imieniu.
3. **Spam-boty** (Lekcja 21): checkout chroni Stripe; panel chroni limit prób; formularzy
   otwartych NIE MAMY (świadomie) — jeśli dojdzie jakikolwiek formularz (np. kontakt), dostaje
   ochronę anty-bot (Turnstile) ZANIM wejdzie. Wpis do MUST NOT/warunków rozszerzeń.
4. **Dokumentacja końcowa dwupoziomowa** (nota 2+6): dla KLIENTA instrukcja prostym językiem
   + ZRZUTY ekranu · dla INFORMATYKA diagram architektury (draw.io/mermaid) — powstaje NA KOŃCU
   projektu, po zamrożeniu (zgodnie z E.: docs po freeze).
5. **Zakaz lorem-ipsum w oddaniu** (Lekcja 24): wszystkie treści witryny PRAWDZIWE (opisy
   agentów piszemy my — decyzja z godziny 0); sweep przed paczką: zero wypełniaczy.
6. **Wymagania w planie v2 rozpisane jak u Ciebie w nocie: FUNCTIONAL / NON-FUNCTIONAL / SCHEMA**
   — funkcjonalne (co robi) · niefunkcjonalne (szybkość, dostępność, bezpieczeństwo, RWD) ·
   schemat danych. Ta sama siatka, którą zapisałeś 9.07.

## C. ZABEZPIECZENIA — przypomnienie właściciela, stan po krytyce 1

Plan sekcja 6 + rubryka dostaje punkty bezpieczeństwa (triage #3) + z MP dochodzą: matka H,
testy równoległe (race), sekrety w runtime, security-review całości przed oddaniem (skill
`security-review` na diffie paczki). Panel admina: CSP + token krótkiego życia (triage #5).
