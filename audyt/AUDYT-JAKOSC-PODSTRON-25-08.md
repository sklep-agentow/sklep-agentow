# Audyt jakości 3 podstron sklepu — 25.08.2026 (pomiar bazowy, PO rundzie działu stron)

Strony: `/sklep/`, `/sklep/produkt/chatbot-live/`, `/sklep/produkt/segregator-maila/` na main `758d1ba`
(sklep 0.9.12), stos dev `localhost:8890` (WordPress 7.1, motyw Automatic AI). Cel właściciela: narzędzia
≥ 95 w czterech kategoriach + dostępność, bezpieczeństwo, wymogi unijne. Audyt dzielony po PYTANIU
(nie po terenie): wydajność · dostępność · dobre praktyki · SEO · bezpieczeństwo · prawo UE · maile.
Przyrządy: Lighthouse 12.8.2 (Chromium Playwrighta, desktop preset + mobile), axe-core 4.x (WCAG 2.x
A/AA + best-practice, po wolnym scrollu), Playwright, `curl -I`. Skrypty: `lighthouse-3-podstrony.sh`,
`axe-3-podstrony.js` (warsztat; do przeniesienia do `testy/` przy K7).

## 1. Lighthouse — 4 kategorie (desktop / telefon)

| strona | wydajność | dostępność | dobre praktyki | SEO (dev) | SEO z indeksowaniem |
|---|---|---|---|---|---|
| `/sklep/` | 100 / 99 | 100 / 100 | 100 / 100 | 58 / 58 | **92** |
| `/sklep/produkt/chatbot-live/` | 100 / 99 | 100 / 100 | 100 / 100 | 66 / 66 | **100** |
| `/sklep/produkt/segregator-maila/` | 100 / 96 | 100 / 100 | 100 / 100 | 66 / 66 | **100** |

Prozą: wydajność, dostępność i dobre praktyki są na celu na wszystkich trzech stronach. SEO na stosie dev
traci na jednej kontroli `is-crawlable` (waga 4 z 7): opcja WordPressa „Zniechęcaj wyszukiwarki"
(`blog_public=0`) wstawia `<meta name='robots' content='noindex, nofollow'>` — to ustawienie stosu
testowego, nie kod. Pomiar kontrolny z włączonym indeksowaniem (opcja przełączona na czas pomiaru
i przywrócona): produkty **100**, lista **92** — lista nie ma meta description (jedyna realna luka SEO).
Metryki telefonu: LCP 1,7–2,0 s, TBT 60–220 ms, CLS 0. Segregator/telefon 96 przez TBT 220 ms — główny
wątek to praca stylów/układu długiej strony i skrypty gospodarza (`volt.js` 251 ms, widget ChatBota 417 ms
łącznie), nasz `front-ruch.js` ma 16–30 ms skryptowania. Element LCP na stronach produktów = okruszki
(hero wjeżdża kaskadą, więc pierwszym dużym malowaniem jest nawigacja) — 0,97–0,98, bez działań.

## 2. Dostępność — axe-core (WCAG 2.0/2.1 A/AA + best-practice), 6 przebiegów
0 naruszeń na 5 z 6 przebiegów; 1 zgłoszenie „serious color-contrast" na `/sklep/` 390 px dla tagu
`Oferty` w panelu hero. **Fałszywy alarm z animacji**: tag ma pętlę opacity (`sklepag-pn-tag`), axe zmierzył
klatkę w pół przejścia; statycznie tusz #08090B na limonce #BFFF38 ≈ 17:1; powtórka z animacjami i
z `prefers-reduced-motion: reduce` (panel w stanie końcowym) = 0 naruszeń. Wcześniejsze pomiary
(PLAN-WYGLADU-K5 §4): kontrasty AA, fokus 2 px, klawiatura, `aria-live` na „Dziękujemy", reduced-motion.
Szelki obu stron produktów (`testy/e2e/szelki-strona-produktu.js`): lightbox z pułapką fokusu, bez JS =
wszystko widoczne, „ogranicz ruch" = zero ruchu.

## 3. Bezpieczeństwo
- Kod: audyty K1–K6 (`audyt/`), bramki CI 8/8 (phpcs/phpstan/gitleaks), rzemiosło potwierdzone przez
  recenzenta w #178–#180 (esc_*, sanitize, nonce+uprawnienia parą, prepare, permission_callback).
- **Nagłówki HTTP** (`curl -I`, dev i tunel): brak `Strict-Transport-Security`, `Content-Security-Policy`,
  `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`;
  serwer ujawnia `Server: Apache/2.4.67` i `X-Powered-By: PHP/8.4.21`. To warstwa serwera/motywu gospodarza,
  nie wtyczki sklepu. Zalecenie do K8 (instrukcja dla właściciela/hostingu): HSTS po potwierdzeniu HTTPS
  na całej domenie, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` bez kamery/mikrofonu/geolokalizacji, `X-Frame-Options: SAMEORIGIN` (lub CSP
  `frame-ancestors 'self'`), CSP pełne najpierw w trybie `report-only` (motyw ładuje własne skrypty),
  ukrycie `X-Powered-By` (`expose_php = Off`) i `ServerTokens Prod`.

## 4. Wymogi unijne / prawo
- Regulamin i Polityka sklepu = SZABLONY do uzupełnienia przez właściciela (K8; regulamin musi opisać
  14-dniową gwarancję satysfakcji — D31). Cookies/prywatność = strony gospodarza w stopce.
- Zgoda z art. 38 (treść cyfrowa, utrata prawa odstąpienia): checkbox przy zakupie + zapis w zamówieniu
  (`zgoda_tresc`, `zgoda_czas`) — jest. **Luka: mail po zakupie nie POTWIERDZA tej zgody**, a dyrektywa
  2011/83/UE art. 8 ust. 7 lit. b) każe zawrzeć w potwierdzeniu umowy na trwałym nośniku „the confirmation
  of the consumer's prior express consent and acknowledgment in accordance with point (m) of Article 16"
  (cytat: legislation.gov.uk/eudr/2011/83/article/8, pobrane 25.08). Naprawa: blok w mailu z treścią zgody
  i datą (te same dane, które są w zamówieniu — bez nowego tekstu prawnego).
- Dostępność (EAA 2025 → WCAG 2.1 AA jako cel): stan wyżej — 0 realnych naruszeń w axe, Lighthouse 100.
- Robots: `robots.txt` rdzenia WP poprawny (Disallow `/wp-admin/`); sitemapa produktów działa dopiero
  przy `blog_public=1` (produkcja) — w instrukcji K8.

## 5. Mail po zakupie (treść — nigdy wcześniej nie oceniana)
Jeden mail tekstowy (`zakup.php: sklepag_zbuduj_mail`). Dobrze: jasny temat, kwota z dopiskiem o trybie
testowym, link z ważnością i limitem, 3 kroki instalacji, link do opinii, „odpisz na tego maila".
Luki: (1) brak potwierdzenia zgody art. 38 (pkt 4); (2) brak nagłówka `Reply-To` na „mail kontaktowy
właściciela" z ustawień — odpowiedzi idą na adres nadawcy; (3) brak zdania o 14-dniowej gwarancji
i jak z niej skorzystać (strona produktu ją obiecuje); (4) dane sprzedawcy + odnośnik do regulaminu —
dopiero z K8, gdy regulamin przestanie być szablonem.

## 6. Pozostałe pomiary z tej rundy
- Okno tabletu 700–768 px (przypuszczenie recenzenta #180): kafle fazy 04.1 Segregatora 314–345 px
  szerokości (tabela 1100 px w 314 px = miniatura) → układ `nagranie` przechodzi na jedną kolumnę
  poniżej 56rem; kafle ChatBota (pionowe okno czatu 294 px przy 768 px) czytelne — bez zmian.
- Testy dokumentacji botów (stan zastany, czerwone od fali napraw 24.08): instrukcja/readme ChatBota
  stoją na 1.5.10 (kod 1.5.11) + format CHANGELOG + liczba ścieżek trafienia 4 vs 5; Segregator 1.3.3
  (kod 1.3.4) + liczba wydań 9 vs 10 + „dokumentacja podaje te wersje".

## 7. Runda napraw (jeden PR `fix/audyt-jakosci-podstron`) — klasy, nie egzemplarze
1. Meta description dla `/sklep/` (`front-seo.php`) — z istniejącego leadu listy. Dowód: Lighthouse SEO
   listy 92 → 100 (pomiar z indeksowaniem włączonym na czas pomiaru).
2. Mail po zakupie: potwierdzenie zgody + data · `Reply-To` z ustawień · zdanie o gwarancji (gdy produkt
   ją ma). Dowód: test zachowania maila (treść zawiera zgodę i datę, nagłówek Reply-To) + przelot E2E.
3. Układ `nagranie` — jedna kolumna poniżej 56rem. Dowód: pomiar kafli w 700/768 px.
4. Dokumentacja botów zgodna z kodem (wersje, changelog, liczniki) — oba testy dokumentacji zielone.
5. Nagłówki bezpieczeństwa — zalecenie do K8 (serwer), nie kod wtyczki.
Po naprawach: drugi pomiar Lighthouse + axe (ta sama procedura) jako dowód, nie słowo.

## 8. WYNIK PO NAPRAWACH (25.08, gałąź `fix/audyt-jakosci-podstron`, sklep 0.9.13)
- **SEO** (Lighthouse desktop, indeksowanie włączone na czas pomiaru i przywrócone): lista **92 → 100**,
  ChatBot 100, Segregator 100 — meta description listy renderowana. Na stosie dev z `blog_public=0` wynik
  nadal 58/66 (noindex) — to ustawienie, które na produkcji wyłącza właściciel (K8).
- **Mail po zakupie**: `testy/mail-po-zakupie-test.php` (kontener, `wp eval-file`) **9/9 OK** — treść zgody
  dosłownie z zamówienia + data, blok zgody przed linkiem, gwarancja z danych produktu, stare zamówienie bez
  zgody = mail bez akapitu (bez błędu), `Reply-To` z ustawień / brak przy pustym polu.
- **Tablet** (Playwright): kafle fazy 04.1 Segregatora 700 px → 1 kolumna 644 px, 768 px → 707 px,
  900 px → 2 kolumny 406 px (było 314 / 345 / 406).
- **Testy dokumentacji botów**: ChatBot 0 rozjazdów, Segregator 0 rozjazdów (było 4 + 4) — wzorce w
  skryptach podbite do stanu kodu, CHANGELOG ChatBota z kreską przed `[1.5.10]`.
- **Szelki** obu stron produktów 10/10 · **bramki** 8/8 EXIT 0 · wydajność/dostępność/dobre praktyki bez
  zmian w kodzie stron (CSS tylko dla 56rem) — wartości bazowe z §1 pozostają w mocy.
- Przyrządy audytu w repo: `testy/e2e/lighthouse-3-podstrony.sh`, `testy/e2e/axe-3-podstrony.js`
  (ten sam pomiar do powtórzenia przy K7 i po wydaniu).
