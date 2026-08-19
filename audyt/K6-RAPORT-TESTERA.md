# Raport testera instalacji K6 — Sklep Agentów + 2 boty

**Rola:** niezależny tester-instalator, wcielony w właściciela strony, wykonujący
WYŁĄCZNIE `INSTRUKCJA-INSTALACJI.md` krok po kroku, przez przeglądarkę (Playwright/Chromium).
**Środowisko:** WordPress 7.0.2, PHP 8.3.33, świeży WP bez wcześniejszej konfiguracji.
**Start pomiaru:** 02:31:14 · **Koniec pomiaru:** 02:58:12 · **Czas łączny: 26 min 58 s.**

> Zastrzeżenie do czasu: ok. 10–11 minut z tego w Kroku 5 to mój własny czas
> na "oswojenie" hostowanego formularza płatności Stripe skryptem Playwright
> (Stripe rozwija metodę „Card" przez nietypowy overlay wymagający kliknięcia
> myszą w realne współrzędne, a nie zwykłego kliknięcia w element). To problem
> mojej automatyzacji, nie produktu — żywy człowiek klika tam naturalnie i traci
> na tym kilka sekund, nie minut. Skorygowany, realistyczny czas "człowieka
> z myszką" to raczej **15–18 minut** na kroki 1–5, plus dodatkowy narzut
> opisany w bloker #1 poniżej.

---

## Tabela kroków (start/koniec)

| Krok | Start | Koniec | Czas | Uwaga |
|---|---|---|---|---|
| 0. Lektura briefu/instrukcji | 02:31:14 | 02:32:43 | 1:29 | — |
| 1. Instalacja wtyczki sklepu | 02:32:43 | 02:35:26 | 2:43 | Zawiera sprawdzenie WP/PHP w Zdrowiu witryny |
| 2+3B. Klucze Stripe + webhook (Stripe CLI) | 02:35:26* | ~02:35:11* | ~kilkadziesiąt s | Ustawienia zapisane, webhook z CLI podłączony |
| 4. Produkty (2 boty) + diagnoza permalinks | 02:35:26 | 02:38:39 | 3:13 | Zawiera nieplanowaną diagnozę Permalinks (patrz Bloker #1) |
| 5. Pierwszy testowy zakup | 02:38:39 | 02:51:35 | 12:56 | Z czego ~10-11 min to moja automatyzacja Stripe Checkout, nie produkt |
| 6. Instalacja i test botów | 02:51:35 | 02:54:56 | 3:21 | Chatbot + Segregator — oba działają poprawnie |
| Odinstalowanie | 02:54:56 | 02:58:12 | 3:16 | Pełne czyszczenie + usunięcie 3 wtyczek |

\* Krok 2/3B nie miał osobnego pomiaru startu — wykonany w ciągu okna Kroku 1→4; ujęty w czasie Kroku 1.

---

## Wynik zakupu testowego

**SUKCES.** Produkt "ChatBot LIVE" (49,00 PLN), karta 4242 4242 4242 4242, zwrot na
`/dziekujemy-za-zakup/`. Strona natychmiast pokazała: „TRYB TESTOWY — PŁATNOŚCI NIE SĄ
POBIERANE" oraz „PŁATNOŚĆ POTWIERDZONA, ALE MAIL NIE WYSZEDŁ. WŁAŚCICIEL SKLEPU WIDZI TO
W PANELU I WYŚLE GO RĘCZNIE." — dokładnie zgodnie z ostrzeżeniem instrukcji o środowisku
bez poczty. Zamówienie #1 pojawiło się na Pulpicie sklepu ze statusem „błąd maila";
w szczegółach zamówienia widoczna pełna treść maila (z linkiem do pobrania) oraz działający
przycisk „Wyślij ponownie (świeży link)". Zrzuty: `35-po-platnosci.png`, `36-pulpit-sklepu.png`,
`37-zamowienie-szczegoly.png`.

## Wynik deinstalacji

**SUKCES, zgodnie z opisem.** Po włączeniu „Pełne czyszczenie" w Ustawieniach sklepu i
usunięciu (Wyłącz → Usuń) kolejno: Sklep Agentów, ChatBot LIVE, Segregator maila —
zniknęły: wszystkie strony sklepu (Sklep, Dziękujemy, oba SZABLONY), wpisy w menu
kokpitu, a `/sklep/` zwraca teraz czysty **404**. Zostały tylko domyślne strony WP
(„Privacy Policy", „Sample Page"). Zrzuty: `56-wtyczki-koncowy-stan.png`,
`57-strony-po-odinstalowaniu.png`, `58-sklep-po-odinstalowaniu.png`.

---

## Blokery i niejasności (od najpoważniejszego)

### 🔴 Bloker #1 — Świeży WordPress ma domyślnie permalinki „Zwykłe" (Plain), a instrukcja o tym milczy
Cała instrukcja zakłada ładne adresy (`/sklep/`, `https://TWOJA-DOMENA/wp-json/sklepag/v1/webhook`),
ale **nigdzie nie mówi, że trzeba najpierw ustawić Ustawienia → Bezpośrednie odnośniki na coś
innego niż domyślne „Zwykłe"**. Na świeżym WP (dokładnie taki stan miałem po instalacji) skutki są:
- kliknięcie linku „Sklep" z menu działa (bo to `?page_id=7`), ale wpisanie/otwarcie
  ładnego adresu `/sklep/` **nie pokazuje sklepu** — zalogowany admin dostaje po cichu
  stronę bloga („Hello world!"), niezalogowany gość dostaje **404**. Zero komunikatu
  wskazującego przyczynę.
- endpoint webhooka `/wp-json/sklepag/v1/webhook` z Kroku 3 **zwraca 404**
  (`rest_no_route`) dopóki permalinki są na „Zwykłe" — REST API WordPressa wymaga
  ładnej struktury adresów, żeby ta ścieżka w ogóle zadziałała.

Sekcja „Najczęstsze problemy" ma wpis „Strona produktu daje 404 po przenosinach →
Ustawienia → Bezpośrednie odnośniki → Zapisz", ale to opisuje inny scenariusz („po
przenosinach”), nie świeżą instalację — obcy właściciel klikający `/sklep/` po raz
pierwszy prawdopodobnie **nie skojarzy** swojego problemu z tym wpisem. To realne ryzyko,
że ktoś utknie tu na dłużej niż 30 minut albo pomyśli, że sklep jest zepsuty.
Naprawiłem to sam, ustawiając permalinki na „Post name” (Ustawienia → Bezpośrednie
odnośniki → Zapisz zmiany) — po tej zmianie `/sklep/` działa (200) i webhook zaczyna
odpowiadać poprawnie.
**Rekomendacja:** dodać jawny Krok 0 (przed Krokiem 1): „Ustawienia → Bezpośrednie
odnośniki → wybierz dowolną strukturę inną niż «Zwykłe» (np. «Ładne linki» / nazwa
wpisu) → Zapisz zmiany”.

### 🟠 Bloker #2 — Webhook `checkout.session.completed` (dokładnie zdarzenie z Kroku 3) zwraca HTTP 500
Po naprawieniu permalinków i wykonaniu prawdziwego zakupu, log `stripe listen` pokazał:
```
--> payment_intent.succeeded [...]   <-- [200] POST .../webhook
--> payment_intent.created [...]     <-- [200] POST .../webhook
--> checkout.session.completed [...] <-- [500] POST .../webhook
--> charge.updated [...]             <-- [200] POST .../webhook
```
Zamówienie mimo to powstało (prawdopodobnie sklep łapie płatność po
`payment_intent.succeeded`, nie po `checkout.session.completed`), więc **z perspektywy
kupującego zakup zadziałał**. Ale Stripe uzna dostarczenie webhooka
`checkout.session.completed` za nieudane i będzie je **ponawiał** — realny właściciel,
który zajrzy do Stripe Dashboard → Developers → Webhooks, zobaczy tam czerwone błędy 500
przy dokładnie tym zdarzeniu, które instrukcja każe skonfigurować jako jedyne nasłuchiwane
(„Zdarzenie do nasłuchu: `checkout.session.completed`"). To wygląda niepokojąco, nawet
jeśli sklep faktycznie działa.
**Rekomendacja:** zbadać i naprawić handler `checkout.session.completed` w kodzie wtyczki
(poza zakresem tego testu — zakaz czytania PHP).

### 🟡 Niejasność #3 — W menu nie ma pozycji „Zamówienia", a instrukcja każe jej szukać
Krok 5.4 mówi: „Kokpit → Sklep Agentów → **Zamówienia**" — ale rzeczywiste podmenu to:
Pulpit, Produkty, Dziennik, Ustawienia. Zamówienia są widoczne na stronie **Pulpit**
(razem ze statystykami przychodu). Domyślna pozycja menu i tak jest oczywistym miejscem
docelowym, więc strata czasu jest minimalna (sekundy), ale to niezgodność nazwy z
rzeczywistym UI — dopisuję zgodnie z zasadą brief'u, że każde zgadywanie = znalezisko.
**Rekomendacja:** zmienić tekst instrukcji na „Kokpit → Sklep Agentów → Pulpit" albo
zmienić nazwę podmenu na „Zamówienia" w kodzie.

---

## Pozytywne obserwacje / rzeczy, które działają lepiej niż sugeruje instrukcja

- **Krok 4 da się zrobić jednym przejściem.** Instrukcja sugeruje kolejność (wgraj plik →
  potwierdź → zaznacz sprzedaż → zapisz), co brzmi jak dwa zapisy. W praktyce
  przetestowałem: plik + checkbox „Produkt widoczny i kupowalny” + zapis **za jednym
  razem** działa bez błędu (produkt „Segregator Maila”, zrzut `18-produkt2-po-zapisie.png`).
  Nie trzeba się bać, że to nie zadziała za pierwszym razem.
- **Pola sekretów Stripe po zapisie pokazują odznakę „ustawiony”** zamiast zwykłego pustego
  pola — dobra, uspokajająca informacja zwrotna, której instrukcja nie zapowiada, a bardzo
  pomaga potwierdzić, że klucz faktycznie się zapisał (zrzut `11-ustawienia-zapisane.png`).
- **Strona produktu jest dużo bogatsza niż tylko wpisany opis** — wtyczka wyciąga
  rozbudowaną treść marketingową (sekcje „Problem”, „Co dostajesz”, FAQ, CTA) z samej
  paczki ZIP produktu, mimo że w formularzu wpisałem tylko krótki opis. Miła niespodzianka,
  ale nieopisana w instrukcji — właściciel może się zdziwić, skąd ta treść, i dobrze by było
  o tym wspomnieć jednym zdaniem w Kroku 4.
- **Siatka bezpieczeństwa działa zgodnie z opisem** — po naprawieniu permalinków webhook
  domyka zamówienia praktycznie natychmiast, a mechanizm „dociągnij zaległe” i panel
  zamówienia z podglądem treści maila (dla środowisk bez poczty) działają dokładnie tak,
  jak obiecuje instrukcja.
- **Oba boty (ChatBot LIVE, Segregator maila) działają bez zarzutu od razu po instalacji**,
  z gotowymi danymi demo: ChatBot ma 10 wpisów Q&A i poprawnie odpowiedział na pytanie
  testowe o formy płatności (zrzut `46-chat-odpowiedz.png`); Segregator maila na próbce
  30 maili osiągnął 80% pokrycia regułami i 100% accuracy (zrzut
  `48-segregator-po-przebiegu.png`). Oba nie wymagały żadnej dodatkowej konfiguracji poza
  aktywacją wtyczki.
- **Deinstalacja z „Pełnym czyszczeniem” działa dokładnie jak opisano** — zero śladów po
  usunięciu (strony, menu, opcje, `/sklep/` → 404).

---

## Ocena końcowa: czy obcy właściciel da radę w ≤30 minut?

**Warunkowo tak, ale z realnym ryzykiem utknięcia na permalinkach.**

Kroki 1, 2, 4 (poza permalinkami), 6 i Odinstalowanie są proste, jasno opisane i
w praktyce zajmują tyle, ile obiecuje instrukcja (albo mniej — Krok 4 da się zrobić
szybciej niż sugerowana dwuetapowa procedura). Krok 3B (Stripe CLI) też jest prosty dla
kogoś, kto potrafi wkleić jedną komendę do terminala.

Największym realnym zagrożeniem dla budżetu 30 minut jest **Bloker #1** — świeży
WordPress z domyślnymi permalinkami sprawia, że strona sklepu i webhook milcząco nie
działają, a instrukcja nie ostrzega o tym ani razu wprost (jedyna wzmianka w
„Najczęstszych problemach” dotyczy innego scenariusza i łatwo ją przeoczyć/nie skojarzyć).
Obcy właściciel, który nie zna WordPressa na tyle, by z marszu pomyśleć o Bezpośrednich
odnośnikach, może utknąć tu na kwadrans lub więcej, szukając, dlaczego „sklep nie
działa” — i to jest scenariusz realistyczny dla adresata tej instrukcji (ktoś nietechniczny,
kupujący gotowy produkt). Po dodaniu jednego zdania-kroku o permalinkach na początku
instrukcji, cała reszta procesu mieści się komfortowo w 20 minutach.

**Bloker #2** (500 na `checkout.session.completed`) nie psuje doświadczenia zakupowego
kupującego ani nie wydłuża czasu instalacji — ale jest realną usterką, którą właściciel
może odkryć później w Stripe Dashboard i nabrać niepotrzebnych wątpliwości co do
poprawności integracji.

---

KONIEC-K6
