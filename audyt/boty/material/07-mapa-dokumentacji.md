# 07 — MAPA DOKUMENTACJI PROJEKTU (siódmy typ: „gdzie czego szukać")

> **Po co ten plik:** w repo leży **80 plików dokumentacji**, a `CLAUDE.md` wskazywał osiem.
> Audytor bez tej mapy albo czyta wszystko (nie zdąży), albo trafia na pierwszy lepszy plik
> i bierze go za aktualny. ⛔ Sześć pozostałych typów mówi CO wiedzieć — ten mówi GDZIE.
>
> ⚠️ **Zasada nadrzędna: przy sprzeczności wygrywa KOD, nie dokument.** Każdy plik niżej jest
> zapisem czyjejś decyzji z konkretnego dnia — a nie gwarancją, że tak jest dzisiaj.

## 🚪 WEJŚCIE — przeczytaj to najpierw, w tej kolejności

| # | Plik | Co daje | Uwaga |
|---|---|---|---|
| 1 | `CLAUDE.md` | zasady repo, podział terenu, klasy błędów, które nas wywalały | ładuje się sam każdej sesji |
| 2 | `audyt/boty/material/01-zamowienie.md` | **surowe** zdania zamawiającego | ⛔ nie nasze streszczenie |
| 3 | `audyt/boty/material/02-fakty-srodowiska.md` | stack, wersje — **zmierzone** | dokumentacja SYSTEMOWA |
| 4 | `audyt/boty/MATERIAL-AUDYTU-{CHATBOT,SEGREGATOR}.md` | co audytujesz, z SHA | wejście dla audytora |

## ❓ WEDŁUG PYTANIA — „chcę wiedzieć X, czytam Y"

- **Co produkt ma robić** → `ZROZUMIENIE.md` (surowe zdania + rozdzielenie: fakt klienta / nasza decyzja / otwarte pytanie) · `PLAN-PROJEKTU.md` (§7 bezpieczeństwo, §12 kryteria GOTOWE) · `MAPA-SPEC.md`
- **Czego świadomie NIE robimy** → ⭐ `DECYZJE.md` (D1–D24). ⛔ **Czytaj to przed zgłoszeniem braku funkcji** — połowa „braków" to świadome decyzje z uzasadnieniem.
- **Jak działa środek wtyczki** → `wtyczki/*/DOKUMENTACJA-TECHNICZNA.md` (stałe, tabele, przepływy) · `wtyczki/*/INSTRUKCJA.md` (to, co widzi kupujący)
- **Co już wiemy o błędach** → `audyt/SCIAGA-EGZAMINATORA.md` (klasy od egzaminatora) · `audyt/boty/SLEPE-PLAMY-NASZEGO-AUDYTU.md` (czego nasze audyty NIE łapały) · ⭐ `audyt/PETLA-UCZENIA-AUDYTU.md` (klasy zmierzone u nas + jak nie dopuścić do powtórki)
- **Co i czym zweryfikowano** → `audyt/REJESTR-WERYFIKACJI.md` (kolumna „czym sprawdzone"; wpis bez dowodu = wrażenie, nie fakt)
- **Jakie są reguły branży** → `material/05-reguly-branzy.md` (⛔ z oficjalnych źródeł, nie z głowy)
- **Co jest do zrobienia / co wisi** → `boty/NA-OSTATNI-GUZIK-BOTY.md` · `boty/KOLEJKA-CHATBOT.md` · `audyt/CO-WISI.md`
- **Jak działa nasze QA** → `narzedzia-qa/` (skrypty + `CHANGELOG.md`) · `.github/RECENZENT-BRIEFING.md`

## ⛔ ARCHIWALNE — NIE traktuj jako stanu dzisiejszego

Te pliki opisują świat **sprzed zwrotu D16** (projekt miał być na Cloudflare/Worker/Next.js;
dziś jest **w całości w WordPressie**). Zostawione dla historii decyzji:
`ARCHITEKTURA-PROPOZYCJA.md` · `DO-PLANU-V2.md` · `DLA-OBCYCH-MODELI.md` ·
`DLA-OBCYCH-MODELI-v2.md` · `PLAN-ETAP1.md` · `ZWIAD-WZORCE.md`
⚠️ `CLAUDE.md` mówi o tym wprost: „jeśli gdzieś tak napisano, to świat sprzed zwrotu D16
i jest nieaktualny".

## 📅 RAPORTY Z DATĄ — aktualne tylko wtedy, gdy stan się nie zmienił

⚠️ **Trzecia kategoria, której łatwo nie zauważyć.** To nie są ani dokumenty żywe, ani archiwum:
to zdjęcia stanu z konkretnego dnia. Odwołują się do starej architektury **jako do przeszłości**
— więc trafienia na „Cloudflare/Worker/Next.js" NIE znaczą, że plik jest nieaktualny.

- `SPRZATANIE-RAPORT.md` (18.08) — 14 trafień na słowa starej architektury, ale opisuje
  **dzisiejsze śmieci**: `node_modules/` jako relikt po zwrocie D16. ⭐ Sprawdzone 21.08:
  `node_modules/` **nadal istnieje** (~195 MB), więc raport jest **aktualny**.
- `audyt/POMIAR-CZASU.md`, `audyt/K6-RAPORT-TESTERA.md`, `audyt/DZIEN-1.md` — ta sama zasada.

⛔ **Zanim powołasz się na taki raport — sprawdź, czy jego treść jest jeszcze prawdziwa.**
Sama liczba trafień na słowa kluczowe to **poszlaka, nie dowód**: `CLAUDE.md` ma jedno trafienie
i jest to zdanie OSTRZEGAJĄCE, a `DECYZJE.md` ma pięć, bo **zapisuje sam zwrot D16**.

## 🕳️ CZEGO NIE MA — nie szukaj, zgłoś jako brak

- **Bank mutantów NIE leży w repo** i to jest celowe — patrz `material/README-BANK-MUTANTOW.md`.
- **Sklep (`wtyczki/sklep-agentow`) nie ma `CHANGELOG.md` ani `Stable tag`** — w odróżnieniu
  od obu botów. Znane, do zamknięcia przed jego wydaniem.
- **Dokumentacja funkcjonalna jest niepełna**: z listy dziesięciu (kartka klienta, mapa systemu,
  kontrakty, schemat bazy z właścicielami, goldeny, granice, rejestr znalezisk, maszyna stanów,
  instrukcja środowiska, mapa polowania) mamy część. ⛔ To **znany brak, nie stan docelowy** —
  jeśli Twoje znalezisko zależy od czegoś, czego tu nie ma, napisz „NIE DA SIĘ ORZEC" i dlaczego.
