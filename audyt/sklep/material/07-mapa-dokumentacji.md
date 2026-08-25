# 07 — MAPA DOKUMENTACJI PROJEKTU (SKLEP) — siódmy typ: „gdzie czego szukać"

> **Po co ten plik:** wzorowany na `audyt/boty/material/07-mapa-dokumentacji.md` — ale
> ⛔ **ten katalog (`audyt/boty/`) dotyczy BOTÓW** (`chatbot-live`, `segregator-maila`) i
> dla audytu SKLEPU jest **wyłącznie wzorem FORMY** tych czterech plików materiału, nie
> źródłem treści o wtyczce `sklep-agentow`. W repo leży dziesiątki plików dokumentacji z
> różnych momentów projektu (przed i po piwocie D16 na WordPressa) — bez tej mapy łatwo
> trafić na plik nieaktualny i wziąć go za dzisiejszy stan.
>
> ⚠️ **Zasada nadrzędna: przy sprzeczności wygrywa KOD, nie dokument.** Każdy plik niżej
> to zapis czyjejś decyzji z konkretnego dnia, a nie gwarancja stanu dzisiejszego.

## 🚪 WEJŚCIE — przeczytaj to najpierw, w tej kolejności

| # | Plik | Co daje | Uwaga |
|---|---|---|---|
| 1 | `audyt/sklep/material/01-zamowienie.md` | surowe zdania zamawiającego + CAŁA MAPA-SPEC + 12 kryteriów GOTOWE + §2/§3 planu | ⛔ nie nasze streszczenie — źródło |
| 2 | `audyt/sklep/material/02-fakty-srodowiska.md` | stack, wersje — ZMIERZONE (nagłówek: „Dwa stosy" — stos warsztatowy vs stos czysty od zera) | ⭐ **Pojawił się W TRAKCIE pisania tej mapy** (koordynatorka pracowała równolegle) — treść NIE sprawdzona przeze mnie poza nagłówkiem, patrz „CZEGO NIE MA" niżej |
| 3 | `wtyczki/sklep-agentow/DOKUMENTACJA-TECHNICZNA.md` | architektura, schemat bazy, punkty wejścia, przepływ zakupu, brama pobrań, stałe konfiguracyjne, instalacja/deinstalacja, granice — i WŁASNA sekcja 10 „MAPA DLA AUDYTORA — gdzie patrzeć najuważniej" | plik ISTNIEJE (sprawdzone: 10 sekcji, nagłówki zweryfikowane) |
| 4 | `dokumentacja/INSTRUKCJA-INSTALACJI.md` | to, co widzi KUPUJĄCY/wdrażający: instalacja, klucze Stripe, webhook, produkty, pierwszy zakup, ustawienia produkcyjne, test-a-produkcja, najczęstsze problemy, odinstalowanie | plik ISTNIEJE (sprawdzone: 12 kroków/sekcji, nagłówki zweryfikowane) |

## ❓ WEDŁUG PYTANIA — „chcę wiedzieć X, czytam Y"

| Chcę wiedzieć | Czytam |
|---|---|
| Schemat bazy | `wtyczki/sklep-agentow/DOKUMENTACJA-TECHNICZNA.md` §3 „Schemat bazy — 7 tabel, prefiks `wp_sklepag_`" (podrozdziały: „Tabela `opinie` — obieg (D32)", „Tabela `zamowienia` — kolumny istotne dla audytu") · `PLAN-PROJEKTU.md` §5 „MODEL DANYCH v3" |
| Punkty wejścia | `DOKUMENTACJA-TECHNICZNA.md` §4 „Punkty wejścia — pełna lista" (rozdzielone: publiczne bez logowania / panel z uprawnieniami administratora / zadanie cykliczne) |
| Przepływ zakupu | `DOKUMENTACJA-TECHNICZNA.md` §5 „Przepływ zakupu — ścieżka szczęśliwa i awaryjna" · `PLAN-PROJEKTU.md` §6 „PRZEPŁYW ZAKUPU v3" |
| Brama pobrań | `DOKUMENTACJA-TECHNICZNA.md` §6 „Brama pobrań" (podrozdział „⚠️ Katalog plików produktów — rzecz, o której trzeba wiedzieć przy wdrożeniu") · `PLAN-PROJEKTU.md` §7 (zasady bezpieczeństwa bramy: upload, limit rozmiaru, losowa nazwa) |
| Opinie | `DOKUMENTACJA-TECHNICZNA.md` §3, podrozdział „Tabela `opinie` — obieg (D32)" · `DECYZJE.md` D32 (pełny mechanizm) · `PLAN-PROJEKTU.md` §3 MUST NOT (zakres) i §8 (RODO opinii) |
| Ustawienia | `DOKUMENTACJA-TECHNICZNA.md` §7 „Stałe konfiguracyjne" · `dokumentacja/INSTRUKCJA-INSTALACJI.md` „Krok 2 — klucze Stripe", „Krok 4 — produkty", „Ustawienia produkcyjne (gdy strona idzie w świat)" |
| Deinstalacja | `DOKUMENTACJA-TECHNICZNA.md` §8 „Instalacja i deinstalacja" · `dokumentacja/INSTRUKCJA-INSTALACJI.md` „Odinstalowanie" · `DECYZJE.md` D22 · `CLAUDE.md` „Rzeczy, które wywalały nas wcześniej" pkt 1 (uninstall = trzy miejsca) |
| readme / CHANGELOG | `wtyczki/sklep-agentow/readme.txt` i `wtyczki/sklep-agentow/CHANGELOG.md` — **OBA ISTNIEJĄ** (sprawdzone 25.08: `readme.txt` ma nagłówki Contributors/Tags/Requires at least/Tested up to; `CHANGELOG.md` deklaruje format Keep a Changelog + Semantic Versioning). ⛔ **Nie mylić** z `audyt/boty/material/07-mapa-dokumentacji.md`, który mówi, że sklep NIE MA CHANGELOG-a — ten zapis dotyczył BOTÓW/wcześniejszego momentu; **dla sklepu dziś jest to NIEAKTUALNE**, plik istnieje i ma treść |
| Testy istniejące w `testy/e2e/` | `axe-3-podstrony.js` (dostępność, axe-core) · `lighthouse-3-podstrony.sh` (wydajność/dostępność/dobre praktyki/SEO, Lighthouse) · `przelot2.js` (przelot E2E zakupu) · `sonda-sticky-pigulka.js` · `szelki-strona-produktu.js` · `README.md` (opis testów). Wynik ich ostatniego użycia i metodologia opisane w `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` (m.in. „Skrypty w repo: `testy/e2e/lighthouse-3-podstrony.sh`, `testy/e2e/axe-3-podstrony.js`") |

⭐ **Skrót dla niecierpliwych:** `DOKUMENTACJA-TECHNICZNA.md` §10 „MAPA DLA AUDYTORA —
gdzie patrzeć najuważniej" to WEWNĘTRZNA mapa TEGO SAMEGO pliku, zrobiona pod audyt —
czytaj ją RAZEM z tym plikiem 07, nie zamiast niego (ten plik 07 mówi też o dokumentach
POZA tą jedną wtyczką: `PLAN-PROJEKTU.md`, `DECYZJE.md`, instrukcja instalacji, testy).

## ⛔ ARCHIWALNE — NIE traktuj jako stanu dzisiejszego

Świat **sprzed piwotu D16** (17.08 wieczór — projekt miał być na Cloudflare Worker + D1 +
R2, frontend statyczny HTML/Next.js; dziś jest **w całości wtyczką WordPressa**).
Sprawdzone nagłówkami/treścią (nie zgaduję z samego tytułu pliku):

- **`PLAN-ETAP1.md`** (40 linii) — CAŁY plik. Ostatnia linia (40) wprost: „architektura:
  statyczna witryna + darmowe zaplecze (Cloudflare) + Stripe Checkout + mail z
  wygasającym linkiem…" — plik z 17.08 ~00:15, czyli SPRZED wieczornego piwotu tego
  samego dnia.
- **`ARCHITEKTURA-PROPOZYCJA.md`** (54 linie) — CAŁY plik ma WŁASNY nagłówek: „⚠️
  HISTORIA — UNIEWAŻNIONE PIWOTEM 17.08 (D16): cały ten dokument opisuje wariant
  «statyczny HTML + Cloudflare», który odpadł (sklep = wtyczka WP, zaplecze w WP)".
- **`DO-PLANU-V2.md`** (199 linii) — wielokrotne odwołania do starej architektury:
  „Worker+D1+R2" (linia 22: „wyłączenie zaplecza (Worker+D1+R2) wg instrukcji"), „Worker
  nie loguje kluczy ani tokenów" (linia 41), „eksport bazy D1 przed każdą migracją
  schematu" (linia 143), „oficjalne przykłady Stripe/Cloudflare" (linia 107).
- **`audyt/REJESTR-WERYFIKACJI.md`** (43 linie) — **CZĘŚĆ, nie całość** (dokładnie ta
  sama kategoria, o której ostrzega wzór mapy botów). Wpisy datowane **16.08–17.08 do
  ok. 23:45** opisują zwiad Cloudflare/Next.js/GitHub Pages i spike'e wpięcia skryptem
  w DOM strony statycznej — archiwalne, historia procesu. Wpisy **od 18.08**
  (pierwszy: „Globalna blokada push do repo MatthewPlugins działa z całej maszyny";
  dalej: „Stack warsztatowy pod budowę sklepu żyje: WP 7.0.4, PHP 8.4.21…", „Kość-szkielet
  sklepu: aktywacja CZYSTA"…) opisują już weryfikacje na stosie WordPress i **SĄ
  aktualne**.

(`DLA-OBCYCH-MODELI.md`, `DLA-OBCYCH-MODELI-v2.md`, `ZWIAD-WZORCE.md` w katalogu głównym
nie były w zleceniu tego materiału do sprawdzenia — nie orzekam o ich statusie tutaj.)

## 📅 RAPORTY Z DATĄ — osobna kategoria (zdjęcie stanu, nie dokument żywy)

`audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` to zdjęcie stanu z 25.08 (sklep 0.9.12 → 0.9.13)
— aktualne o tyle, o ile main się od tego dnia nie ruszył dalej. Sam plik dokumentuje
DWA pomiary (przed i po naprawach) w JEDNYM dokumencie: czytaj do końca (§8 „WYNIK PO
NAPRAWACH"), nie tylko §1 — inaczej bierzesz stan SPRZED naprawy za stan dzisiejszy.

## 🕳️ CZEGO NIE MA — nie szukaj, zgłoś jako brak (a nie jako „nie znalazłam")

- **⚠️ AKTUALIZACJA W TRAKCIE PISANIA TEJ MAPY (25.08, ta sama sesja):** katalog
  `audyt/sklep/material/` był PUSTY na starcie tej roboty; podczas gdy powstawały te
  cztery pliki (01/03/05/07), koordynatorka RÓWNOLEGLE dopisała do tego samego katalogu
  jeszcze **cztery inne**: `02-fakty-srodowiska.md`, `04-goldeny.md`,
  `06-mapa-polowania.md`, `08-zestawy-plikow.md`. Sprawdziłam WYŁĄCZNIE nagłówki (nie są
  częścią tego zlecenia, więc treści nie recenzuję): `04-goldeny.md` = złote zasady z
  naszych blizn traktowane jak wymaganie kontraktowe (źródła: `CLAUDE.md`,
  `PLAN-PROJEKTU.md` §7, `DECYZJE.md` D22/D24/D26, `SCIAGA-EGZAMINATORA.md` — te same
  źródła co plik `05` tutaj, inny kąt patrzenia); `06-mapa-polowania.md` = checklista
  pokrycia audytu K7 z licznikiem; `08-zestawy-plikow.md` = które pliki kodu należą do
  której „soczewki" audytora (kod sklepu = 8842 linie). **Audytor: te cztery pliki też
  czytaj** — ta mapa (07) o nich nie mówiła w chwili pisania, bo jeszcze nie istniały.
- **Bank mutantów sklepu NIE leży w `audyt/sklep/material/` ani nigdzie w repo
  głównym.** Wg `DECYZJE.md` D33 mechanika banku to osobna gałąź `audyt/mutanty-sklep`
  (tabela rozliczenia COMMITOWANA na tej gałęzi, nie w luźnym pliku) — **audytor NIE
  dostaje tej gałęzi**.
- **Lista przelotu człowieka DLA SKLEPU — powstaje.** Nie mylić z przelotem człowieka
  BOTÓW, który już istnieje i ma wpis w `audyt/REJESTR-ZNALEZISK.md` jako `CZ-1`.
- **Rejestr znalezisk (`audyt/REJESTR-ZNALEZISK.md`) jest WSPÓLNY dla całego projektu —
  sprawdzone 25.08, nie założone z góry.** W chwili pisania tej mapy rejestr ma „Liczba
  znalezisk OTWARTYCH: 1" (`CZ-1` — ciche przycinanie pytań gościa dłuższych niż 500
  znaków w `front.php:504`), ale to znalezisko dotyczy ChatBota (strażnik leży w
  `audyt/boty/testy-audytu/`), **NIE sklepu**. Dla SKLEPU konkretnie rejestr jest
  pusty — `audyt/PLAN-K7-AUDYT-SKLEPU.md` istnieje jako PLAN (wersja 2, po rundzie
  krytyki, klepnięcie 25.08), ale samo wykonanie audytu K7 (wpisywanie znalezisk do
  rejestru) jeszcze nie ruszyło w chwili pisania tej mapy.
- **Dokumentacja funkcjonalna niepełna** — jak w mapie botów: to znany brak PROCESU, nie
  stan docelowy. Jeśli Twoje znalezisko zależy od czegoś, czego nie ma w tej mapie ani w
  żadnym z czterech plików materiału (`01`, `03`, `05`, `07`) — napisz „NIE DA SIĘ
  ORZEC" i dlaczego, zamiast zgadywać.

---

Przy sprzeczności między dokumentem a kodem wygrywa KOD. Katalog `audyt/boty/` **dotyczy
BOTÓW** (`chatbot-live`, `segregator-maila`) — dla audytu SKLEPU jest wyłącznie WZOREM
FORMY tych czterech plików materiału, nie źródłem treści o wtyczce `sklep-agentow`.
