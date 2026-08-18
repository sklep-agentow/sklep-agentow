# PLAN WYGLĄDU K5 — witryna sklepu

Status: ✅ KLEPNIĘTY przez Dzidka 19.08 (D27) + wyżarzony ZIMNYM KRYTYKIEM
(2 przynęty rozbrojone: fejk-opinie i CDN → krytyk wiarygodny; 7 uwag przyjętych
i naniesionych niżej; 1 obalona dowodem — patrz §4 art. 38). Wytyczna Dzidka 18.08: „cała strona kolegi i moja
jest ładnie zrobiona i ma swój klimat" → sklep MUSI wyglądać jak część Automatic AI.
Źródła: pomiar motywu na stacku (computed styles + zrzuty) · zwiad wzorców stron
produktowych (wp-rocket.me, advancedcustomfields.com, gravityforms.com, codecanyon,
EDD, Baymard/NNGroup — destylat niżej §5) · układ strony produktu z planu §4A.

## 1. KLIMAT GOSPODARZA (zmierzony na stacku, nie z oka)

| Token | Wartość | Skąd |
|-------|---------|------|
| tło główne „tusz" | `#08090B` | body motywu |
| tło kart „węgiel" | `#101116` | sekcje motywu |
| tekst „papier" | `#F5F5F5` | body/h1 |
| tekst wtórny „pył" | `#8F929C` | opisy |
| AKCENT „limonka" | `#BFFF38` | logo „AI", CTA-pille, statusy (153 użycia na stronie) |
| linie hairline | `rgba(245,245,245,.08)` | dividery |

Typografia: **dziedziczymy z motywu** (GeistSans przez `inherit`; mono-akcenty
`GeistMono, ui-monospace, monospace`) — zero własnych fontów, zero CDN. Nagłówki
weight 600, duże (48–64 px na podstronach), oddech ogromny.
Detale-klimat (z podstron gospodarza): mono-etykiety `[ SKLEP ]` z `·` separatorem ·
pulsująca kropka statusu · pille CTA (limonka, ciemny tekst, strzałka →) ·
hairline dividery · wielkie outline-napisy w stopce.

⚠️ DECYZJA WPASOWANIA: nasze sekcje niosą WŁASNE ciemne tło w swoim kontenerze —
na stronie gospodarza zlewają się z motywem 1:1, a na CZYSTYM WP (test K6!) sklep
jest spójną ciemną „wyspą" zamiast rozsypanych stylów po cudzym jasnym motywie.

## 2. STRONY I UKŁADY

**A. LISTA SKLEPU `[sklepag_sklep]`** — hero skromny: mono `[ SKLEP ]` + H1
„Boty, które robią robotę." (echo hero gospodarza „AI, które robi realną robotę")
+ 1 zdanie + **baner testowy jako mono-pasek** (koniec z gołym tekstem z kości).
Dalej 2 DUŻE karty (nie ciasny grid): mono-etykieta → nazwa → zdanie-korzyść →
**cena WIDOCZNA na karcie** (antywzorzec EDD: ukrywanie ceny — Baymard) → CTA
„Zobacz szczegóły" (zakup na stronie produktu, bo tam checkbox zgody).

**B. STRONA PRODUKTU `[sklepag_produkt]`** (układ §4A + zwiad; treść ze SZKICÓW botów):
1. **Hero**: mono `[ PRODUKT · 01/02 ]` + nazwa + zdanie-korzyść + cena + CTA-kotwica
   „Kup teraz (płatność testowa)" + ⭐SIGNATURE (niżej).
2. **Problem** („powtarzalne pytania zjadają godziny…" — ze szkiców).
3. **Co dostajesz** — funkcje rdzenia, blok per funkcja (wzorzec WP Rocket/ACF).
4. **Jak działa w 3 krokach** — numeracja UZASADNIONA (realna sekwencja:
   instalacja → konfiguracja w kwadrans → bot pracuje).
4b. **„Ile czasu odzyskujesz"** — tytuł sekcji wprost odpowiada S-03 „jak skraca
   czas pracy w firmie" (krytyk #9: obietnica czasowa musi mieć dedykowane
   miejsce, nie chować się w Problemie): karty-dowody z liczbami (§5b.2);
   u Segregatora dodatkowo PRZED/PO z mono-etykietą `[ PRZYKŁAD · DANE DEMO ]`
   (krytyk #12 — bez etykiety czyta się jak zrzut od klienta).
5. **Dla kogo** — 3 segmenty (wzorzec wp-rocket).
6. **Uczciwość zamiast fejk-opinii**: sekcja „Testowy bot — co to znaczy"
   (LIVE = człowiek odpisze; tryb AI za kluczem WŁAŚCICIELA; deklaracja
   prywatności ze szkiców). Zero zmyślonych opinii/liczb sprzedaży —
   opinie i tak wykluczone (D6/Omnibus), a fałszywy trust-badge obniża
   zaufanie (funnelkit).
7. **Cena + zakup**: duża cena, checkbox zgody (art. 38), CTA limonkowy pill;
   obok mono-notka „Stripe · tryb testowy · karta 4242…" (uczciwość=trust).
8. **FAQ** (wymagania, klucz AI, prywatność, droga rozbudowy — ze szkiców).
9. Finalny pasek CTA (wzorzec „skyrocket banner").

**C. DZIĘKUJEMY `[sklepag_dziekujemy]`** — mono status na żywo (REST już jest):
`[ ZAMÓWIENIE · PRZETWARZANE ]` → `[ MAIL WYSŁANY ✓ ]`, limonkowa kropka.

**D. REGULAMIN / POLITYKA (świadoma decyzja — krytyk #8):** szablony prawne
(drafty z aktywacji) renderuje MOTYW gospodarza jak zwykłe strony; sklepowy CSS
ich nie dotyka. Kryterium #11 wymaga ISTNIENIA szablonów z polami sprzedawcy —
istnieją; ich wygląd to wygląd stron motywu (spójny z resztą strony z definicji).

## 3. ⭐ SIGNATURE (jedna zapamiętywalna rzecz)

**„Pasek statusu bota"** w hero produktu — żywy mono status-bar 1:1 w stylu
systemowych pasków gospodarza (`● [ STATUS · ONLINE ] SYSTEM DZIAŁA 24/7`):
ChatBot: `● [ BOT · ONLINE ] ODPOWIADA 24/7` — a człon „PRZETESTUJ: BĄBELEK
W ROGU ↘" renderowany WARUNKOWO, tylko gdy wtyczka chatbot-live jest AKTYWNA
na tej instalacji (krytyk #3: bez warunku napis kłamie po deaktywacji bota;
sprawdzenie po istnieniu funkcji z prefiksu `chatl_` — zero twardej zależności).
Pulsowanie czystym CSS, wyłączane przy prefers-reduced-motion.
Odrzucona alternatywa: interaktywne mini-demo czatu w hero — dubluje żywy widget
obok i kosztuje dzień roboty przy zerowym zysku dowodowym.

## 4. TWARDE WYMOGI (z planu — w każdej sekcji)

WCAG-lite: kontrasty ZMIERZONE (papier/tusz ≈17:1, pył/tusz ≈7:1, limonka/tusz
≈13:1, tusz na limonce ≈13:1 — wszystko ≥AA) · fokus: limonkowy outline 2 px ·
labelki · klawiatura. RWD: karty→kolumna, clamp() na typografii, CTA pełna
szerokość na mobile; test telefonem (kryterium #11). **Cross-browser (krytyk
#11): PR 3 testuje chromium + firefox (playwright) — Edge = silnik Chromium.**
Assety TYLKO na stronach sklepu + bump wersji. Baner „TRYB TESTOWY — płatności
nie są pobierane" na KAŻDEJ stronie sklepu (mono-pasek, nie krzyczący alert).
Motywu NIE dotykamy.
**Art. 38 (uwaga krytyka #10 OBALONA dowodem):** stała `SKLEPAG_ZGODA_TRESC`
w kodzie zakupu zawiera już OBA wymagane elementy — zgodę na natychmiastowe
dostarczenie ORAZ przyjęcie do wiadomości utraty prawa odstąpienia (widoczne
na zrzucie baseline z 19.08); strona produktu używa TEJ SAMEJ stałej.

## 5. WZORCE — destylat zwiadu (pełny wynik w tym pliku, sekcja robocza)

Strona produktu u najlepszych: hero-korzyść+CTA → sekcja per funkcja → social
proof → segmenty „dla kogo" → cennik z CTA przy każdej pozycji → FAQ po cenniku →
finalny baner CTA (wp-rocket, ACF, gravityforms). Karta na liście MUSI mieć cenę
(Baymard). CTA konkretny („Kup teraz”), nie „Submit”. NIE robimy: przekreślonych
promocji (Omnibus/D6), fejk-badge'y gwarancji (funnelkit), przeładowanych tabel
porównawczych (NNGroup).

## 5b. WZORCE LIDERÓW BOTOWYCH (zwiad 19.08: tidio.com, intercom.com, sanebox.com, chatfuel.com)

Jak pokazują produkt w akcji: dymki czatu ZAWSZE w ramce-oknie (tidio — nigdy „gołe"
dymki) · funkcje jako osobne kafle-mockupy (sanebox) · liczby NIGDY same — zawsze
z podpisem-źródłem (sanebox: liczba+avatar klienta). Tidio = ciemny motyw z jednym
akcentem — najbliższy naszemu klimatowi, potwierdza kierunek.

**3 CHWYTY wzięte do budowy (czysty HTML/CSS, zero bibliotek):**
1. **Mockup okna czatu** w sekcji „co dostajesz" ChatBota (wzór tidio): ramka
   z paskiem tytułowym, 2-3 dymki rozmowy (limonkowy = bot), animowane kropki
   „pisze…" przez `@keyframes` (gasną przy prefers-reduced-motion). To STATYCZNA
   wizualizacja — nie koliduje z odrzuceniem interaktywnego demo (żywy bot i tak
   chodzi w bąbelku obok).
2. **Karty-dowody z liczbami** (wzór sanebox), ale UCZCIWIE: bez zmyślonych
   avatarów klientów (nie mamy klientów!) — liczba limonkowa + podpis SKĄD ONA
   JEST („zmierzone na wbudowanej próbce 30 maili", „konfiguracja w kwadrans —
   czas mierzony przy wrogiej sesji"). Pokrycie liczb = kryterium GOTOWE #10.
3. **PRZED/PO dla Segregatora** (nasza przewaga — nikt z 4 liderów tego nie robi
   wprost): grid 2 kolumny — lewo chaos skrzynki (szarości), prawo te same maile
   w kategoriach z limonkowymi tagami; strzałka SVG między nimi.

## 6. KOLEJNOŚĆ BUDOWY (po klepnięciu; sędzia 0-100 PRZED pokazem)

1 PR: fundament CSS (tokeny, mono-etykiety, pille, baner) + lista sklepu →
2 PR: strona produktu (szablon + treść ChatBot + Segregator ze szkiców) →
3 PR: dziękujemy + szlif RWD/WCAG → sędzia wyglądu 0-100 (kryteria dziedziny,
przynęty) → poprawki → ZRZUTY dla Dzidka.

## 7. SEO PODSTAWOWE — DOBRE (Dzidek 19.08: „musi być dobre podstawowe, nie
minimum"; ROZSZERZENIE ponad literę notatki → wpis do DECYZJE + PONAD-SPECYFIKACJE)

WP renderuje serwerowo (Google widzi treść od razu). Zakres (~1 dzień w PR-ach K5;
poprawki po krytyku #4-#7 naniesione):
- **`<title>` + meta description PER PRODUKT** (nie goły tytuł strony „Sklep").
- **Przyjazne adresy**: `/{slug-sklepu}/produkt/{slug}/` — rewrite budowany
  z FAKTYCZNEGO sluga strony sklepu odczytanego z opcji (krytyk #4: hardkod
  „sklep" pęka, gdy WP dopisze „-2" przy kolizji slugów po konwersji);
  flush przy aktywacji; fallback query var zawsze działa.
- **JSON-LD schema.org**: `Product` z **`image`** (krytyk #6: Google WYMAGA
  image dla rich results → robimy 2 statyczne OKŁADKI produktów — ciemne tło,
  limonkowy tytuł, mono-etykieta; służą TEŻ jako og:image) + `FAQPage`
  (semantyka dla crawlerów i AI; krytyk #7: rich results FAQ Google wycofał
  w 2023 dla zwykłych stron — schema zostaje, ale NIE obiecujemy wyników
  rozszerzonych) + `BreadcrumbList` (Sklep → Produkt).
- **OpenGraph + Twitter card** (okładki jw. jako og:image).
- **Canonical** na stronach produktów · **noindex na „Dziękujemy"** (strona
  po transakcji nie powinna być w Google — standard sklepów).
- Semantyka: jeden H1, logiczne H2, alt-y, landmarki (main/nav/footer).
- **Sitemap**: własny `WP_Sitemaps_Provider` dla produktów (krytyk #5: produkty
  to własna tabela, nie CPT — „filtr" nie istnieje; provider to ~40 linii wg
  oficjalnego API). ⚠️ ZBADANE 19.08: sitemapa gospodarza USZKODZONA
  (/sitemap.xml → 301 → /wp-sitemap.xml → 404) — przyczynę zbadać w PR SEO;
  jeśli wyłączona przez motyw celowo, nasz provider czeka gotowy, a decyzja
  o włączeniu = właściciel (motywu nie ruszamy).
- Wydajność = też SEO (Core Web Vitals): assety tylko na stronach sklepu, CSS
  bez bibliotek, wizualizacje CSS-em zamiast ciężkich obrazów — już w planie.
NIE na egzamin (osobna robota PO wdrożeniu na domenę): keyword research, treści
blogowe, linki, Search Console, analityka.

## ✅ KLEPNIĘTE (Dzidek 19.08, D27): klimat 1:1 · signature pasek statusu ·
CTA „Kup teraz (płatność testowa)" · 3 PR-y · SEO podstawowe-dobre.
Treść sprzedażowa: `boty/TRESC-STRON-PRODUKTOW.md` (liczby z pokryciem,
PLACEHOLDER-POMIAR uzupełniane po wrogiej sesji 23.08 — sweep przed K8).
