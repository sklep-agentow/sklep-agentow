# DO PLANU v2 — dziedzictwo MP + SEO + zabezpieczenia (17.08 ~01:45)

Źródła: `docs/BRAMKI-ANTY-POWTORKA-bledy-kolegi.md` (warsztat; 22 błędy z 4 rund recenzji kolegi
+ 9 przyczyn-matek + druga opinia 2 modeli) · pytanie Dzidka o SEO · „pamiętać o zabezpieczeniach".
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

## B. SEO — DECYZJA (pytanie Dzidka)

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

## C. ZABEZPIECZENIA — przypomnienie Dzidka, stan po krytyce 1

Plan sekcja 6 + rubryka dostaje punkty bezpieczeństwa (triage #3) + z MP dochodzą: matka H,
testy równoległe (race), sekrety w runtime, security-review całości przed oddaniem (skill
`security-review` na diffie paczki). Panel admina: CSP + token krótkiego życia (triage #5).
