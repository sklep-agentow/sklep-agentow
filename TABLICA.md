# 🎯 TABLICA — kto co bierze, co czeka, co blokuje

> Jedno miejsce zamiast dopytywania. **Aktualizuje koordynatorka po każdej zmianie stanu.**
> Stan na: **21.08, 19:05**

## 👥 KTO CO BIERZE TERAZ

| okno tmux | rola | teren | gałąź | stan |
|---|---|---|---|---|
| `praca:1` | **Łamacz Segregatora** (autor ChatBota — cudzy kod) | `segregator-maila` | `audyt/lamacz-segregator` | pracuje |
| `praca:2` | **Łamacz ChatBota** (autor Segregatora — cudzy kod) | `chatbot-live` | `audyt/lamacz-chatbot` | pracuje |
| `praca:3` | **Audytor A6 odwrotny** (kod → dokumentacja) | oba boty | `audyt/a6-odwrotny` | pracuje |
| `praca:4` | **Audytor A4 front** (przeglądarka, dostępność) | oba boty | `audyt/a4-front` | pracuje |
| koordynatorka | scalanie, naprawy z recenzji, bump, tablica | całość | — | — |

⛔ **Zasada obsady:** nikt nie audytuje kodu, który sam pisał. Przy każdej zamianie ról
builder idzie przez `/clear` — świeży kontekst jest tym, czego audytor potrzebuje najbardziej.

## 🔴 POZYCJA NUMER JEDEN — CI NIE URUCHAMIA ŻADNEGO TESTU

Zmierzone 21.08 19:04, z próbą kontrolną:
- `.github/workflows/quality.yml` woła **wyłącznie** `narzedzia-qa/bramki.sh`;
- `bramki.sh` ma 7 bramek (actionlint, manifest, ślady, php-lint, phpcs, phpstan, gitleaks)
  i **żadna nie dotyka katalogu `testy/`**;
- w `testy/` leży **57 plików**. Automatycznie uruchamianych: **zero**.
- Recenzent ma je na liście dozwolonych komend, ale to model, który *może* je odpalić —
  nie bramka, która *musi*.

**Dowód:** PR #135 wniósł **dwa celowo padające testy** (`chatbot-sesje-retencja-rodo-test.php`,
`uninstall-zombie-cron-test.php`, oba EXIT=1, dokumentują realne znaleziska) — a CI dało
zielono i PR został scalony. Te testy leżą teraz na `main` i nic o tym nie mówi.

⛔ To znaczy, że nasza zasada „każdy błąd dostaje strażnika z exit 1 wpięty do CI"
**nie jest spełniona ani razu**. Wszystko jest doradcze.

**Kształt naprawy** (do zrobienia, nie zrobione):
### ✅ ZROBIONE 21.08 19:12 — ósma bramka `znaleziska`

`narzedzia-qa/bramka-znaleziska.sh` + `audyt/REJESTR-ZNALEZISK.md`, wpięte do `bramki.sh`
(próg pełny 7 → **8**, tekstowy 4 → **5**). Bramka sprawdza **księgowość znalezisk**:

- liczba OTWARTYCH wpisana wprost w rejestrze musi zgadzać się z liczbą wierszy —
  dopisanie znaleziska **zatrzymuje bramkę**, dopóki ktoś świadomie nie podniesie liczby;
- każdy test wskazany w rejestrze **musi istnieć** (martwy odsyłacz udaje, że strażnik stoi);
- każdy `audyt/boty/RAPORT-*.md` musi być objęty rejestrem — nowy raport nie wniesie
  znalezisk poza księgowość, czyli dokładnie to, co się nam stało;
- status musi być ze znanego zbioru (OTWARTE / W NAPRAWIE / ZAMKNIĘTE);
- bramka **liczy własne kontrole** i pada, gdy wzorzec tabeli przestał pasować — inaczej
  cicho nie wystartowałaby i świeciła zielono (nasza własna blizna).

**Kalibracja zdana: 4 podstawki na 4 złapane** (rozjazd liczby w nagłówku, test wskazany
na nieistniejący plik, raport spoza rejestru, wymyślony status), kontrola odwrotna na czystym
rejestrze EXIT=0. Bramka nie jest napisem.

W rejestrze siedzi **9 znalezisk**: F1/F2/F3 (w naprawie przez #136), A3-1, A3-2, KR1, KR2,
KR4, KR6 (otwarte).

### ⛔ CZEGO ŚWIADOMIE NIE ZROBIŁAM — czeka na decyzję Dzidka

1. **Wpis do `.github/manifest-bramek.txt`.** Manifest ma własną zasadę, zapisaną w nim
   samym: *„każda zmiana = osobny PR, który Dzidek czyta w całości; agent NIE dotyka tego
   pliku w PR-ach z kodem"*. Dopóki bramki tam nie ma, działa i zatrzymuje pull requesty,
   ale **nie jest minimum** — da się ją usunąć bez alarmu. Ta sama droga, którą przeszła
   bramka `slady`: najpierw działała, potem wpis uczynił ją nieusuwalną.
2. **Uruchamiania testów przez CI.** Wszystkie 23 skrypty i 18 testów PHP wymagają żywego
   stosu Dockera — psują kod w kontenerze i sprawdzają, czy strażnik pada. Równoległe
   przeloty CI deptałyby sobie po stosie. Wykonanie zostaje przy człowieku i recenzencie.

## 🔧 PULL REQUESTY

| nr | co | stan |
|---|---|---|
| #135 | audyt A3 dane — `chatl_sesje` bez kasowania, zombie cron | ✅ **SCALONY** 18:52 |
| #136 | błąd zapisu przestaje ginąć przy `WP_DEBUG=false` (29 miejsc) | 🔧 naprawiona uwaga krytyczna (`387ee38`), czeka na CI |
| #133 | instrukcja ChatBota: import CSV + przegląd odwrotny | 🔧 naprawione 3 uwagi (`d3d6930`), czeka na CI |
| #137 | audyt A5 krawędzie — 4 znaleziska | ⏳ kontrole zielone, **recenzja do przeczytania** |

⛔ Kolejka CI jest szeregowa — jeden własny wykonawca, jedno zadanie naraz.

## 🔬 AUDYT — 7 soczewek

| soczewka | pytanie | stan |
|---|---|---|
| A1 bezpieczeństwo | czy da się to zaatakować | ✅ kalibracja zdana celująco, przebieg zrobiony |
| A2 awaria | co się dzieje, gdy coś padnie | ✅ raport, 3 znaleziska |
| A3 dane / RODO | co zostaje w bazie | ✅ raport, 2 znaleziska, scalony |
| A4 front | co widzi człowiek | 🔄 `praca:4` |
| A5 krawędzie | puste, maksimum, jeden ponad | ✅ raport #137, 4 znaleziska |
| A6 dok↔kod odwrotny | o czym dokumentacja MILCZY | 🔄 `praca:3` |
| Ł Łamacz | jakie założenie świat złamie | 🔄 `praca:1` + `praca:2` |

Mapa polowania: `audyt/boty/material/06-mapa-polowania.md` — **36 celów**, odhacza koordynatorka.

## 🐞 ZNALEZISKA CZEKAJĄCE NA DECYZJĘ

1. **Segregator wysyła nadawców i tematy cudzych maili do dostawcy AI** — klient przekazuje
   dane osób trzecich, które o tym nie wiedzą. Inna kategoria RODO niż retencja. **Nierozstrzygnięte.**
2. **Zasięg paska błędu bazy** — Segregator pokazuje globalnie, ChatBot tylko na swoich stronach.
   Moja rekomendacja: wyrównać **w górę** (oba globalnie, odrzucalne), nie w dół. Ślad w #136.
3. **`chatl_sesje` rośnie bez końca**, poza kasownikiem RODO (znalezisko A3).
4. **Zombie cron ChatBota** po odinstalowaniu (znalezisko A3).
5. **Blokada przebiegu skrzynki po zabitym procesie** cicho wyłącza cron na zawsze.
6. **`DISABLE_WP_CRON`** u klienta unieważnia obiecaną retencję 90 dni.

## 📦 PO AUDYCIE — kolejność domknięcia
1. Rozstrzygnąć znaleziska, każde potwierdzone dostaje strażnika z exit 1.
2. **Jedno zbiorcze podbicie: ChatBot 1.5.9, Segregator 1.3.2** + przebudowa obu paczek
   + `paczka-zgodnosc.sh` EXIT=0. ⛔ Robi to koordynatorka, jedną operacją, po ostatnim scaleniu.
3. Sklep — Dzidek ma własną listę poprawek, wracamy po botach.
