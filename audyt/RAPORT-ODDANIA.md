# RAPORT ODDANIA — Sklep Agentów, próba 1 (2026-08-30, wydanie `v0.9.23`)

> Dla Dzidka i dla oceniającego. Każda liczba ma komendę, którą ją policzono, i commit, na którym
> ją policzono: **`a1b2495`** (`main` po scaleniu `#213` i `#214`). Każda zmiana w repo po tym
> commicie unieważnia sekcję 2 — wtedy przelot i bramkę końcową trzeba puścić od nowa.

## 1. Co oddajemy

Paczka zbiorcza `wydanie/sklep-agentow-KLIENT-0.9.23.zip` (składa `narzedzia-qa/pakuj-dla-klienta.sh` 1.1.0,
samokontrola 17 kontroli na rozpakowanej paczce, w tym otwarcie każdego zipa): `sklep-agentow-0.9.23.zip` ·
`chatbot-live-1.5.13.zip` · `segregator-maila-1.3.6.zip` · `PRZECZYTAJ-MNIE.txt` · `INSTRUKCJA-INSTALACJI.md` ·
`diagramy/` (3 PNG) · `SHA256SUMS.txt`.
W każdej paczce wtyczki: `INSTRUKCJA.md`, `readme.txt`, `CHANGELOG.md`, `DOKUMENTACJA-TECHNICZNA.md`,
`DIAGRAM-PRZEPLYWU.svg`, `LICENSE`; w sklepie dodatkowo `PONAD-SPECYFIKACJE.md` i pięć zrzutów panelu.
Wydanie na GitHubie: tag `v0.9.23`, Release „Latest” — potwierdzenie oczami wpisane w opisie Release
i w `DO-ODDANIA.md` §7 po publikacji.

## 2. Trzy liczby dla właściciela (bramka końcowa `narzedzia-qa/k7-koniec.sh` 1.3.6)

```
$ bash narzedzia-qa/k7-koniec.sh            # main a1b2495, 30.08 12:35
TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0 · kontroli FAIL: 2 (z 13)
K7: NO-GO — 2 z 13 kontroli niespełnionych (8 warunków zamknięcia)                         EXIT=1
```

Jedenaście kontroli PASS: mapa celów `104 · ✅ 79 · 🐞 23 · ⚠️ 2 · ⛔ 0 · ⬜ 0` i zamrożona; rejestr
0 KRYTYCZNE/DUŻE otwartych, 0 DO WERYFIKACJI, każde ZAMKNIĘTE ma strażnika ze sha padania na starym
kodzie; 0 anomalii; przelot człowieka 27/27; paczka zamrożenia sha zgodny; tag `k7-zamrozenie` = `5d3dcf9`;
15 raportów audytorów, 0 odrzuconych werdyktów.

Dwa FAIL-e są **decyzją, nie wadą** — obie zapisane w `audyt/CO-WISI.md` i w `DECYZJE.md`:
- **Kontrola 2 — kalibracja audytorów.** `soczewek=6, bez PASS=2` (L1, L5 nie osiągnęły PASS przy progu
  bramki). Kryterium klienta #12 (≥ 2/3 mutantów złapanych) jest SPEŁNIONE: 7 mutantów, 5 złapanych = 71 %.
  Bramka jest ostrzejsza od rubryki. Decyzja Dzidka 28.08: oddajemy z jawnym zapisem.
  **Co to znaczy dla klienta, po ludzku:** pięć soczewek udowodniło na podłożonych błędach, że naprawdę widzą
  wady. Dwie tego nie udowodniły — więc **to, czego nie znalazły w obszarze dziennika logowań (L1) i zgodności
  dokumentacji z zachowaniem (L5), nie jest dowodem, że tam nic nie ma.** Ich „czysto” waży mniej niż pozostałych.
- **Kontrola 6 — próby kontrolne strażników.** `6 z 30` prób kończy EXIT≠0 (`audyt/sklep/PROBY-KONTROLNE.log`,
  przelot maszynowy na `a1b2495`, 30.08 12:18; 1 pominięta z powodem w logu). Zmierzone przez wykluczenie
  na czystym `origin/main`: pięć pada identycznie bez naszych zmian (`blad-klucza`, `ip-za-proxy`, `nonce`,
  `przejecie`, `pulapka` — strażniki botów, dwa ślepe w obie strony), szósta (`dowod-z-paczki`, EXIT=2) ma
  zaszytą wersję 1.5.0. Jedna z nich (`przejecie`) jest chwiejna — wynik zależy od stanu wspólnego stosu
  (w innym przelocie przeszła bez zmian w kodzie); siódma (`statystyki`) migocze i w tym przelocie akurat przeszła.
  Wady leżą w NARZĘDZIACH QA, nie w produkcie — **ale skutkiem jest, że zabezpieczenie nonce i obsługa błędu
  klucza w chatbocie są dziś NIEPILNOWANE** (`blad-klucza` i `nonce` padają zawsze): regresji w tych dwóch
  miejscach nie złapie żadna bramka. To dług bezpieczeństwa narzędzi, zapisany tak samo w `CO-WISI.md`.

## 3. Jak to było sprawdzane (i czego to NIE dowodzi)

- Audyt końcowy K7 na **czystym stosie z deklarowanym minimum** `wordpress:6.7-php8.1-apache`
  (`audyt/sklep/ZAMROZENIE.md`), świeże sesje audytorów, jedna soczewka na agenta, kalibracja bankiem
  mutantów (gałąź `audyt/mutanty-sklep`). Naprawy po audycie: 23 cele 🐞, każde zamknięcie ze strażnikiem.
- Przelot człowieka po sklepie **27/27 punktów** (`audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md`) na stosie
  warsztatowym WP 7.1 / PHP 8.4; rdzeń zakupu (zakup → webhook → mail → pobranie, sumy zgodne) powtórzony
  NA PACZCE, na świeżej instalacji (`audyt/K6-INSTALACJA-OD-ZERA.md`).
- Statyka pod PHP 8.1 (`phpstan.neon.dist`: `phpVersion: 80100`, poziom 5), WPCS, gitleaks, skaner śladów
  wewnętrznych i poświadczeń — dziesięć bramek `narzedzia-qa/bramki.sh` w CI na każdym PR; recenzja Claude
  każdego PR-a wg `.github/RECENZENT-BRIEFING.md`. Ostatnie dwa PR-y: `#213` 6 rund (w tym jedna KRYTYCZNA
  naprawiona w kodzie ze strażnikiem — świeży link do pobrania odnawia limit), `#214` 3 rundy.
- Plugin Check WordPress.org na trzech paczkach: 416 pozycji, **1 w kodzie** (naprawiona w 0.9.23), reszta
  to reguły katalogu WP.org i fałszywe alarmy na nazwach tabel — `audyt/PLUGIN-CHECK-30-08.md`.
- Dokumentacja == kod: `testy/dokumentacja-zgodnosc-sklep.sh` (5 liczb, etykiety statusów, odsyłacze;
  próba kontrolna 21/21) — treść zdań instrukcji czytana w całości w sześciu rundach recenzji `#213`.
- ⛔ **Czego nie zrobiliśmy:** instalację od zera i przelot na paczce robiła **autorka**, nie osoba postronna
  (rubryka §12 żąda nie-autora — nie mieliśmy drugiej osoby). Pełny przelot 27 punktów NA PACZCE nie był
  powtarzany — rdzeń tak, reszta mierzy ten sam kod co przelot audytora (decyzja z `DO-ODDANIA.md` §4).
  Przelot wersji 0.9.23 na żywym stosie po naprawie limitu pobrań: trzy strażniki resendu + reconciliation
  (EXIT=0 ×4 na stosie warsztatowym), nie pełny przelot człowieka.
- **Po wydaniu (K9, 30.08):** paczka POBRANA z Release zainstalowana „jak klient” przez świeżego agenta na czystym WP 6.9.4
  z motywem kolegi z jego repo (stan 28.08): instrukcja przeszła w całości — link „Sklep” w 3 miejscach nawigacji, zakup przez
  prawdziwy Stripe Checkout, pobranie z SHA zgodnym, „Wyślij ponownie”; 0 notice’ów PHP na 8 adresach frontu (logger sprawdzony
  podłożonym błędem). Plugin Check na zipie z Release: 3 błędy (było 5). `/security-review` delty po 28.08: zero. `/code-review ultra`
  na `#213`: jeden „nit”. Szczegóły i trzy długi: `audyt/K9-INSTALACJA-Z-RELEASE-0.9.23.md`.

## 4. Co jest świadomie niedomknięte (z `audyt/CO-WISI.md`, stan 2026-08-30)

Pełna lista jest w `CO-WISI.md` (14 wpisów). Tu **wszystkie pozycje, które dotykają produktu lub danych
klienta**; pominięte są wyłącznie długi narzędzi warsztatu (skanery, próby kontrolne, rejestr).

1. **Oznaczenia trybu testowego zaszyte w kodzie w 7 miejscach** (trzy banery, cztery inne teksty) widzianych przez kupującego (w tym mail po zakupie
   i ekran po zapłacie). Na kluczach testowych — zgodnie z zamówieniem — jest prawdą; na produkcji skłamałby.
   Instrukcje mówią to wprost i każą zacząć od kontaktu z nami. Naprawa = nowa paczka, PO egzaminie.
2. **Ponowienie wysyłki przez cron nie ma kompensacji tokenu**: po nieudanym „Wyślij ponownie” sklep przywraca
   poprzedni link tylko do następnej automatycznej próby; przy trwałej awarii poczty poprzedni link przepada,
   droga ratunku = „Wyślij ponownie” po naprawie poczty. Instrukcja mówi to wprost. Zachowanie sprzed `#213`.
3. **Strażnik paczek nie strzela sam** — wymaga PR-a manifestowego (decyzja: manifest bramek zmieniany tylko
   osobnym PR-em czytanym przez Dzidka). To samo dotyczy nowej bramki odsyłaczy strony tytułowej (41 celów,
   uruchamiana ręcznie, bez próby kontrolnej).
4. **Skaner śladów wewnętrznych** nie zna trzech rodzin odsyłaczy, mija wzorzec przez wielkość litery i nie
   czyta obrazów (pięć PNG w paczce sklepu sprawdzone oczami) — dziś paczki są czyste, jutro nic ich nie broni automatem.
5. **Okno idempotencji zakupu** nie obejmuje jednoczesnego podwójnego kliknięcia (dług zastany, nieblokujący).
   Tej samej klasy: kompensacja licznika pobrań po nieudanym resendzie bierze wartość ze zdjęcia, nie z bazy.
6. **Kontrola „wydanie z readme ma wpis w CHANGELOG”** istnieje tylko u Segregatora. Bramka sekcji F mierzy
   5 z 9 liczb instrukcji (niemierzone wypisane w jej nagłówku).
7. **Zwrot ani spór nie odcinają wcześniej wysłanego linku** do pobrania (działa do końca 72 h / 10 pobrań) —
   zapisane wprost w instrukcji właściciela; zmiana na życzenie.
8. **Szkice Regulaminu i Polityki prywatności powstają tylko przy pierwszej aktywacji** — sklep zainstalowany
   przed 0.9.23 ma szkic Polityki bez adresu IP przy pobraniu; instrukcja (rozdz. 6) i CHANGELOG mówią, co dopisać.
9. **Anonimizacja może potwierdzić usunięcie, którego nie wykonała w całości**: adres e-mail z `/` w części
   lokalnej zostaje w dzienniku zdarzeń, a wtyczka melduje sukces (przypadek brzegowy, zapisany w `CO-WISI.md`
   z dowodem). Tej samej ścieżki dotyczy: `sklepag_obsluz_resend()` ma sześć zapisów bez sprawdzenia wyniku —
   skutek zapisywany niezależnie od powodzenia (dług nazwany w kodzie i w rejestrze).
10. Pięć pól panelu (produkty, ustawienia) bez polskiego komunikatu walidacji — kosmetyka, zapisana z liniami.
11. Odbiór wydajności na docelowym hostingu (AN-022) — poza naszym stanowiskiem.

## 5. Gdzie leżą dowody

`audyt/sklep/PROBY-KONTROLNE.log` (przelot maszynowy, commit `a1b2495`) · `audyt/sklep/raporty/` ·
`audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` · `audyt/SCIAGA-EGZAMINATORA.md` (56 klas błędów z dowodami) ·
`audyt/PLUGIN-CHECK-30-08.md` · `audyt/CO-WISI.md` · `DECYZJE.md` (32 decyzje z uzasadnieniem) ·
`MAPA-SPEC.md` (wymagania S-01…S-20 ze statusem) · opisy PR `#213` i `#214` (każda runda recenzji z tabelą
uwaga → naprawa → wynik uruchomienia).
