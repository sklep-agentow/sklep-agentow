# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

## 🟡 Kontrola „każde wydanie z readme ma wpis w CHANGELOG” istnieje TYLKO u Segregatora (29.08, z recenzji #208 r5)

Wzorzec dodany w `#208` żyje w `testy/dokumentacja-zgodnosc-segregator.sh`. ChatBot i Sklep go
nie mają — czyli u nich skasowanie opisu wydania po jednej stronie nadal przejdzie na zielono.

To **klasa 8** (naprawa trafia w jeden egzemplarz wzorca, bliźniak zostaje) — ta sama, przez którą
kontrola `DOK=KOD` wracała w tym PR-ze trzy razy, zanim trafiła do wszystkich trzech wtyczek.
Zapisuję ją świadomie zamiast dokładać czwartą rundę zmian do PR-a o paczkach.

Kształt roboty: przenieść pętlę po wersjach z readme (z progiem czytanym z `CHANGELOG.md`,
czyli z INNEGO pliku niż pętla — inaczej wraca tautologia z r4/r5) do
`testy/dokumentacja-zgodnosc.sh` i `testy/dokumentacja-zgodnosc-sklep.sh`, plus po jednym
przypadku w ich próbach kontrolnych.

⛔ Do zrobienia przy najbliższym PR-ze dotykającym bramek dokumentacji — nie pilne dla klienta,
bo dotyczy naszego oprzyrządowania, nie produktu.

---

## 🔴 STRAŻNIK PACZEK ISTNIEJE, ALE NIE STRZELA SAM — wymaga PR-a manifestowego (29.08, z recenzji #208 r1)

`narzedzia-qa/paczka-zgodnosc.sh` wykrywa rozjazd paczki z repo i **pada poprawnie** (kod 1 na
`f310eec` dla obu botów, kod 0 po naprawie). Ale nie ma go w `BRAMKI=()` w `narzedzia-qa/bramki.sh`
ani w `.github/workflows/quality.yml:84`, który uruchamia dokładnie jedną komendę: `bash
narzedzia-qa/bramki.sh`. Czyli wykrycie rozjazdu zależy od tego, czy komuś przyjdzie do głowy
sięgnąć po paczkę.

**To już trzecie wystąpienie tej klasy** (1.5.8/1.3.1 → 1.5.11/1.3.4 → teraz), udokumentowane
w `audyt/boty/material/06-mapa-polowania.md:286`.

Dlaczego nie zrobione od razu: `.github/manifest-bramek.txt` ma własną zasadę wpisaną w nagłówku —
*„każda zmiana = osobny PR, który Dzidek czyta w całości"* oraz *„agent NIE dotyka tego pliku
w PR-ach z kodem"*. A `sprawdz-manifest.sh` ma kontrolę odwrotną: bramka w `BRAMKI=()`, której nie
ma w manifeście, świeci na czerwono. Więc dopisanie jej w PR #208 złamałoby zasadę domu.

Kształt, który ma sens (za recenzją #208 r1) — **nie** samo dopisanie `paczka-zgodnosc` do listy,
bo to wywracałoby każdy PR ruszający kod wtyczki bez wydania. Warunek: *pliki wtyczki zmienione
po commicie budującym aktualny zip **ORAZ** wersja nie podbita* → PAD.

⛔ Do decyzji właściciela: osobny PR manifestowy teraz czy po oddaniu.

---

## 🟡 KLASA 5 (ślady wewnętrzne w paczce) NIE JEST DOMKNIĘTA — 47 trafień rodziny, której skaner nie widzi (29.08, z recenzji #208 r1)

`#208` usunął z paczek jeden ślad (`zasada D22`). Skaner `narzedzia-qa/slady-wewnetrzne.sh`
zna wzorce `\bD[0-9]{1,2}\b` i `\bK[0-9]\b` (linia 77) — i dlatego zobaczył `D22`. **Nie zna**
form `A2-F3`, `P2-Z1`, `DK-5`, `KR1/KR2`, `WK-13` ani zwrotu „wroga sesja".

Pomiar (29.08, komenda do powtórzenia):

```
grep -rnE "audyt (A[0-9]|DK-|P2-|KR[0-9])|\(audyt [A-Z0-9-]+\)|A[0-9]-F[0-9]|DK-[0-9]|P2-Z[0-9]|wrogiej sesji|wroga sesja" \
  wtyczki/chatbot-live/ wtyczki/segregator-maila/ wtyczki/sklep-agentow/ \
  --include=*.php --include=*.js --include=*.txt --include=*.md | wc -l
→ 47   (chatbot-live 34 · segregator-maila 12 · sklep-agentow 1)
```

Trzy z tych plików **jadą do klienta** (są na allowliście `boty/buduj-paczki.sh`):
`wtyczki/chatbot-live/CHANGELOG.md`, `wtyczki/chatbot-live/DOKUMENTACJA-TECHNICZNA.md`,
`wtyczki/segregator-maila/CHANGELOG.md`. Kupujący czyta w historii zmian „audyt A5, KR1/KR2"
i „A2-F3" — numery raportów, których nigdy nie zobaczy.

⚠️ To jest **tekst czytany przez klienta**, czyli kategoria, która wg reguły rund zawsze wraca
do naprawy. Nie zrobione w #208 świadomie: PR nie tyka kodu produktu, a czyszczenie 47 komentarzy
plus rozszerzenie skanera plus przypadki do próby kontrolnej to osobna robota — recenzent #208 r1
wprost dopuścił drogę „nazwany dług zamiast czyszczenia w tym PR-ze".

⛔ Do zrobienia przed oddaniem klientowi: rozszerzyć wzorzec skanera o tę rodzinę, dopisać
przypadki do `narzedzia-qa/paczka-zgodnosc-proba-kontrolna.sh`, wyczyścić trafienia w trzech
plikach jadących do klienta (komentarz ma nieść POWÓD, numer zostaje w PR i commicie).

### STAN PO `#209` (29.08) — CZTERY RODZINY, TRZY RUNDY, I DLACZEGO ZA KAŻDYM RAZEM „ZERO" BYŁO PRZEDWCZESNE

⛔ **Ten wpis był raz nieprawdziwy i to jest część lekcji.** Po rundzie 2 stało tu zdanie
„rodzina odsyłaczy słownych — **wyczyszczona**". Runda 3 wykazała, że w paczce zostaje wtedy
**20 wierszy**, w tym siedem będących odmienioną formą słowa poprawionego w tym samym pliku
i tym samym commicie. Zdanie zostało poprawione dopiero po tej uwadze.

**Co znalazła która runda — i czym mierzona:**

| runda | rodzina | ile | miara |
|---|---|---|---|
| r1 | identyfikatory (`SK-L3-005`, `LS-F1`, `A3-2`, `Do audytu`) | 22 | wzorzec recenzenta |
| r2 | odsyłacze słowne, mianownik (`przegląd na żywo DD.MM`, `sędzia`, `Znalezisko testu`) | 42 | wzorzec recenzenta |
| r2 (moje) | formy poza oboma wzorcami (`feedback`, `recon`, `runda stron`, `ZMIERZONE DD.MM`) | 65 podmian | wzorzec własny — **za wąski** |
| r3 | odmiany polskie (`sędzi`, `sędziego`) + nasze rundy robocze (`Koszyk A`, `Opinie v2`) + nazwy dokumentów (`ZWIAD-STRIPE`, `K3d`, `raport audytora`) | 20 | wzorzec recenzenta |
| r3 (moje) | odsyłacze do `PLAN-PROJEKTU.md` i naszych PR: `Plan §7`, `granica §9`, `§4A`, `§5b.1`, `§5b.3`, `(PR 2)` | 8 | **wyliczenie kandydatów** |

⛔ **Rodzina z ostatniego wiersza nie była na liście żadnego z nas.** Znalazło ją dopiero
wyliczenie po znaku `§` i po kształcie naszych oznaczeń — nie szukanie form, o których się
pamiętało. To jest cała różnica między „mój wzorzec nic nie znajduje" a „nie ma już kandydatów".

**Miara, która ma zostać** (trzy niezależne wyliczenia, wszystkie odtwarzalne):

```
# A — każda linia z datą, BEZ żadnego kontekstu przed datą
grep -rnE '[0-9]{2}\.[0-9]{2}' wtyczki --include=*.php --include=*.md --include=*.js \
     --include=*.txt --include=*.css

# B — rdzenie słowne, NIE całe słowa (polski odmienia: sędzia → sędziego → sędzi)
grep -rniE '(sędzi|zwiad|wzorc[eóa]w? §|Koszyk A|Opinie v2|rundzie stron|feedback|recon |znalezisk|UZUPEŁNIEN|przegląd|audyt [0-9]|raport audytora)' \
     wtyczki --include=*.php --include=*.md --include=*.js --include=*.txt --include=*.css \
  | grep -viE 'przeglądark|reconciliation'

# C — kształt NASZYCH oznaczeń (to wyliczenie znalazło rodzinę, której nikt nie szukał)
grep -rnE '(§[0-9]|\bK[0-9][a-z]?\b|\bA[0-9]-|\bT[0-9]\b|ZWIAD|\bD2[0-9]\b|\bPR [0-9])' \
     wtyczki --include=*.php --include=*.md --include=*.js --include=*.txt --include=*.css
```

Fałszywe trafienia tych wyliczeń są znane i mają zostać: daty w API Stripe (`28.10.2024`),
przykład formatu wpisu (`'25.08.2026'`), `56.01rem`, `50.000`, stała gamma `12.92`, słowa
„przeglądarka" i „reconciliation", `uzupełnienie` jako zwykłe słowo, odsyłacze do **publicznych**
standardów (RFC 3501 §6.4.8, XML 1.0 §2.5) oraz `§N` odsyłające **wewnątrz** dokumentacji
technicznej, którą klient dostaje w paczce.

⛔ **Co ZOSTAJE długiem — i UWAGA, pierwotny opis tego długu był NIETRAFNY.** Napisałam tu
„skaner nie zna żadnej z tych czterech rodzin". Recenzja r4 wykazała, że dla rodziny
`\bK[0-9]\b` to nieprawda: skaner **zna** ten wzorzec od wersji 1.3.0
(`narzedzia-qa/slady-wewnetrzne.sh:78`), a mimo to przepuścił `K8`
w `dokumentacja/INSTRUKCJA-INSTALACJI.md`. Prawdziwa wada była w **ZASIĘGU**, nie we
wzorcach: pętla `for katalog in wtyczki/*/` nigdy nie patrzyła poza katalogi wtyczek,
przy własnym komentarzu stawiającym znak równości między „pliki wtyczek" a „to, co
trafia do klienta". ⭐ To ma znaczenie praktyczne: dopisywanie wzorców niczego by nie naprawiło.

⛔ **Zasięgu NIE rozszerzyłam w tym PR-ze i to jest decyzja z pomiaru, nie zaniechanie.**
Próba rozszerzenia pętli o `dokumentacja/` została **skalibrowana przed scaleniem** i kalibracja
ją odrzuciła — skaner na CZYSTEJ dokumentacji kończy `EXIT=1`:

```
$ SLADY_ZALEGLOSCI=/dev/null bash narzedzia-qa/slady-wewnetrzne.sh dokumentacja dokumentacja
  SLAD  INSTRUKCJA-INSTALACJI.md:101 (adres maszyny dev): Serwery Stripe nie widzą localhosta — użyj oficjalnego Stripe CLI:
  SLAD  INSTRUKCJA-INSTALACJI.md:103 (adres maszyny dev): stripe listen --forward-to http://localhost:PORT/wp-json/sklepag/v1/webhook
SLADY 1.3.0 [dokumentacja]: nowe=2, dlug z listy=0, martwe wpisy=0   → EXIT=1
```

Oba trafienia są **fałszywe**: `localhost` w pliku wtyczki jest śladem, ale w instrukcji
instalacji jest **treścią, której klient potrzebuje** — bez niej nie skonfiguruje webhooka.
Wpięcie tego zasięgu postawiłoby CI na czerwono od pierwszego przebiegu. ⛔ Dopisanie tych
dwóch wierszy do `slady-zaleglosci.txt` **nie wchodzi w grę** — nagłówek tego pliku mówi wprost:
*„ta lista ma się wyłącznie KURCZYĆ; dopisanie nowego śladu, żeby przepchnąć swój PR, jest
obejściem bramki, nie jej użyciem"*.

**CO NAPRAWDĘ ZAMYKA tę wadę:** rozdzielić wzorce per TEREN — dla `wtyczki/` pełny zestaw,
dla `dokumentacja/` węższy (rodzina „numeracja naszych dokumentów”: `K[0-9]`, `D[0-9]{1,2}`,
`§[0-9]`, `S-0[0-9]`), **bez** wzorca „adres maszyny dev”. Dopiero wtedy pętla może objąć
`dokumentacja/` i kontrola odwrotna („czysty tekst przechodzi”) będzie zielona. To zmiana
narzędzia QA, nie produktu — osobny PR.

⛔ **ALE SAMA TA RECEPTA NIE WYSTARCZY — i to jest najważniejsze zdanie tego wpisu.**
Recenzja r5 wykazała dwa ślady w `dokumentacja/INSTRUKCJA-INSTALACJI.md`, których **żaden
wzorzec z tej recepty by nie złapał**:
- „zrzut ekranu dojdzie przy finalizacji instrukcji” — notatka o stanie NASZEJ pracy zapisana
  zwykłym **słowem**, bez jednego oznaczenia (bliźniak skreślonego w tym samym commicie zdania
  „do finalizacji w K8: zrzuty + diagram”);
- „Pełna lista różnic: rozdział «Co oddziela tryb testowy od produkcji» **w dokumentacji
  projektu**” — odsyłacz do `PLAN-PROJEKTU.md:433`, czyli pliku, którego klient nigdy nie
  dostaje, podany **parafrazą** zamiast nazwą. Wzorzec `PLAN-PROJEKTU` jest w skanerze
  (`slady-wewnetrzne.sh:77`) i był bezradny, bo tej nazwy w zdaniu nie ma.

⭐ **Wniosek, który przenosi się na cały projekt:** dla `dokumentacja/` miarą nie jest wzorzec,
tylko **przeczytanie pliku w całości**. To JEDEN plik na 263 wiersze — przeczytanie go kosztuje
minuty, a wzorzec przepuścił tam ślad trzy rundy z rzędu. Wzorce zostają jako **siatka
bezpieczeństwa dla regresji**, nie jako dowód czystości. ⛔ Zasada ogólna: gdy teren da się
przeczytać w całości, czytanie JEST wyliczeniem — wzorzec jest wtedy zgadywaniem, nie pomiarem.

Nadal długiem zostaje też to, że skaner nie zna rodzin: odsyłaczy słownych, naszych rund
roboczych i dat `DD.MM`. Teksty są czyste dziś, ale nic nie broni ich jutro — pierwszy nowy komentarz
z odsyłaczem wjedzie do paczki klienta bez jednego czerwonego światła. To już **trzeci** PR
z rzędu, w którym ten sam dług kosztuje rundę recenzji.
**CO ZAMYKA:** wpiąć te trzy wyliczenia do skanera (każda rodzina osobno, plus lista znanych
fałszywych trafień jako allowlista) i dołożyć po jednym przypadku na rodzinę do
`narzedzia-qa/paczka-zgodnosc-proba-kontrolna.sh`, **żeby próba mierzyła POKRYCIE, nie samo
działanie wzorca** — bo dokładnie ta różnica kosztowała trzy rundy.
⛔ Świadomie NIE robione przed oddaniem: to zmiana narzędzia QA, nie produktu.

---

## 🔴 BANER „TRYB TESTOWY" JEST ZASZYTY W KODZIE — na produkcji skłamie kupującemu (29.08, recenzja #209 r6)

`sklepag_baner_testowy()` (`wtyczki/sklep-agentow/sklep-agentow.php:168`) zwraca **stały HTML,
bez jednego warunku**, a **wszystkie TRZY wywołania są bezwarunkowe**: `sklep-agentow.php:286` (lista),
`includes/front-produkt.php:791` (strona produktu) oraz `sklep-agentow.php:393` — ⛔ **strona
„Dziękujemy za zakup”, czyli ekran widziany PO ZAPŁACIE**. Trzecie miejsce przeoczyłam
w rundzie 6 i wykazała je dopiero recenzja r7: ktoś, kto właśnie zapłacił prawdziwe
pieniądze, przeczytałby tam, że płatności nie są pobierane. Treść banera:

> **TRYB TESTOWY** — płatności nie są pobierane. Przetestujesz cały zakup kartą testową, nic nie płacąc.

⛔ **Po przejściu na klucze `sk_live_…` ten napis zostaje.** Kupujący czyta, że nie płaci —
i płaci. To ścieżka pieniędzy, nie kosmetyka.

**Skąd się wzięło:** instrukcja instalacji obiecywała, że baner „znika sam po przełączeniu na
klucze produkcyjne" — nieprawda o kodzie (klasa 3, wykazana w recenzji `#209` r6). **Tekst
poprawiony na prawdziwy w tym PR-ze**: instrukcja mówi teraz wprost, że baner nie znika sam
i że usunięcie go jest po naszej stronie.

⛔ **KODU NIE RUSZAM PRZED ODDANIEM — decyzja z powodem, nie zaniechanie.** Komentarz przy
funkcji mówi: *„napis bez zmian — testy na nim polegają"*, więc zmiana warunku tuż przed
zamrożeniem grozi regresją w testach, które ten napis sprawdzają. Naprawa tekstu **zamyka klasę
błędu** (dokumentacja przestaje kłamać); wada produktu zostaje tu nazwana.

⛔ **UWAGA — TO NIE JEST JEDNA FUNKCJA. Recepta poniżej była raz za wąska** (recenzja r8/r9:
obejmowała wyłącznie `sklepag_baner_testowy()`, czyli mniej niż połowę wady). Oznaczeń trybu
testowego widzianych przez KUPUJĄCEGO jest **siedem** (liczba rosła w rundach 6-10:
trzy -> sześć -> siedem, za każdym razem bo wypisywałam listę zamiast ją policzyć):

```
sklep-agentow.php:286             baner — lista produktów          ⟵ sklepag_baner_testowy()
includes/front-produkt.php:791    baner — strona produktu          ⟵ sklepag_baner_testowy()
sklep-agentow.php:393             baner — strona „Dziękujemy”      ⟵ sklepag_baner_testowy()
includes/front-produkt.php:593    ⛔ ETYKIETA PRZYCISKU: „Kup teraz (płatność testowa)”
includes/front-produkt.php:594    notka przy przycisku zakupu (karta 4242…)
sklep-agentow.php:365             FAQ „Co znaczy tryb testowy?”
includes/zakup.php:959            ⛔ TREŚĆ MAILA po zakupie („płatność nie została pobrana”)
```

Panel administratora ma własne oznaczenia (`admin-dashboard.php:338`, `admin-zamowienie.php:475`,
`admin-ustawienia.php:107`) — te widzi WYŁĄCZNIE właściciel, więc zostają.

**CO ZAMYKA:** jedno źródło prawdy o trybie — funkcja typu `sklepag_tryb_testowy()` czytająca
prefiks klucza (`sk_test_` / `sk_live_`), i **wszystkie siedem miejsc pyta JEJ**, zamiast każde
osobno trzymać swój napis. Do tego przypadek testowy na OBA tryby dla KAŻDEGO z siedmiu miejsc
(przy kluczu testowym napis JEST, przy produkcyjnym GO NIE MA) — inaczej naprawa mogłaby zabrać
oznaczenia wszystkim i świecić na zielono, a kryterium 11 zamówienia wymaga banera WIDOCZNEGO
w trybie testowym. Bump wersji sklepu + przebudowa paczki.
⚠️ **Do decyzji właściciela: czy robimy to przed oddaniem, czy po.** Przy oddaniu na kluczach
TESTOWYCH baner jest poprawny i egzaminu nie psuje — problem pojawia się dopiero przy pierwszym
prawdziwym wdrożeniu.

---

## 🟡 ODDANE DO K8 — odbior WYDAJNOSCI (perf) na docelowym hostingu · AN-022
- `perf` z Lighthouse NIE zostal odebrany w K7 i to jest decyzja, nie przeoczenie: na tym stosie
  pomiar mierzy OBCIAZENIE NASZEJ MASZYNY, nie jakosc strony. Ta sama komorka (sklep/mobile)
  dawala 100 · 87 · mediane 100 przy load 1,47 · mediane 93 przy load 2,94. Serwer ma 2 vCPU,
  a tryb telefonu spowalnia procesor 4x; sam pomiar dokłada sie do obciazenia.
- ODEBRANE i NIE wymagaja powtorki: `a11y`, `bp`, `seo` — >=96 w kazdej komorce, stabilne.
- ⛔ DO ZROBIENIA PRZY WYDANIU (K8): zmierzyc `perf` na DOCELOWYM hostingu klienta komenda
  `bash testy/e2e/lighthouse-3-podstrony.sh <adres-klienta> <katalog> 95 3`. Dopiero ten wynik
  jest werdyktem dla celu L6-01 — liczba z naszego stosu nie przenosi sie na produkcje.
- Cel L6-01 stoi w mapie jako ⚠️ (anomalia AN-022, status WYJAŚNIONA), NIE jako zamkniety.
- ⛔ WRACA ⇒ kasujesz ten wpis i przestawiasz L6-01 na ✅ z wynikiem z hostingu klienta.


## 🟡 DŁUG ZASTANY — okno idempotencji nie obejmuje JEDNOCZESNEGO podwójnego kliknięcia
(z recenzji #199 r2, DROBNA 5 — NIE regresja z tego PR, mechanizm istnieje od 0.9.0)
- Odczyt okna (`zakup.php` `get_transient`) i jego zapis (`set_transient`) dzieli wyjście do
  sieci: `sklepag_stripe_utworz_sesje()`. Dwa żądania wysłane NAPRAWDĘ jednocześnie przechodzą
  przez odczyt, zanim którekolwiek dojdzie do zapisu — więc oba tworzą własną sesję Stripe.
- Po stronie przeglądarki nic tego nie blokuje: formularz zakupu (`front-produkt.php`) nie ma
  `onsubmit` ani `disabled`, a w `wtyczki/sklep-agentow/assets/*.js` nie ma ani jednego trafienia
  na `disabled|submit` (dowód z braku, podany przez recenzenta).
- Skutek: dwie sesje checkout dla jednego kliknięcia-dubletu. Opłacona zostanie jedna (druga
  wygasa), więc NIE jest to podwójne obciążenie — ale dziennik dostaje sesję-sierotę.
- ⭐ Najtańsze domknięcie: wyłączenie przycisku na czas wysyłki po stronie przeglądarki
  (jedna linia JS) ALBO atomowa rezerwacja okna przed wyjściem do Stripe.
- ⛔ WRACA ⇒ kasujesz ten wpis. Nie blokuje wydania: skutek jest kosmetyczny, nie finansowy.


## 📌 KONTRAKT NA PRZYSZŁOŚĆ (z recenzji #177 r3–r5, 25.08)
- **`sklepag_opinia_moderuj` przepuszcza opinię BEZ wiersza zamówienia** (INNER JOIN →
  null → wolno moderować). Dziś taki stan nie powstaje produkcyjnie (zero ścieżek DELETE
  na zamówieniach — grep recenzji). ⛔ GDY powstanie pierwsza ścieżka kasowania zamówień,
  warunek MUSI przejść na „brak danych = odmowa" (reguła CLAUDE.md) — inaczej kasowanie
  zamówienia otworzy z powrotem moderację opinii zapomnianego klienta.

## 🔧 PRZYRZĄDY (z recenzji #177, DROBNA, 25.08)
- **Wzorzec testowania handlerów admin-post ISTNIEJE w repo** (sprostowanie po recenzji
  #177 r8 — mój wcześniejszy wpis „nie ma harnessu" był FAŁSZYWY, obalony grepem
  recenzji): `segm_u_odpal` + `wp_die_handler` w testach segregatora, `dokad()`
  u chatbota; handler resendu sklepu pokryty tym wzorcem w `opinie-mechanizm-test.php`
  (sekcja C3: nonce + przechwycony redirect + `pre_wp_mail`). ⬜ POKRYĆ tym wzorcem
  pozostałe handlery sklepu (produkt_zapisz/przelacz, anonimizuj, dociagnij,
  eksport_csv, opinia_moderuj) przy następnej rundzie QA.
- **`stos.sh` nie widzi ŻADNEJ tabeli sklepu** — okno odczytu recenzenta nie ma operacji
  zaglądania do `wp_sklepag_*`, więc recenzent nie może sam obejrzeć np. wierszy opinii
  (dostał odmowę przy #177 i oparł się na przebiegu testu). Rozszerzyć okno o bezpieczną
  operację odczytu tabel sklepu (SELECT-only, bez danych osobowych w wyjściu — imię maskować).

- **`dowod-z-paczki-proba-kontrolna.sh` umiera na ZASZYTEJ NA SZTYWNO wersji paczki** —
  szuka `boty/paczki/chatbot-live-1.5.0.zip`, którego w repo nie ma — katalog zawiera
  wyłącznie wersje od 1.5.7 wzwyż (lista rośnie z każdym wydaniem, dlatego nie wymieniam jej tu
  po numerze: zdanie z listą wersji dezaktualizuje się przy najbliższej paczce).
  Kończy **EXIT=2**, czyli „błąd przyrządu”, i tak samo zachowuje się na czystym `origin/main`
  (zmierzone 28.08: log prób z `main`, commit `28533e0`, ta sama pozycja `EXIT=2 0s`).
  ⛔ Znaczenie: nie wiemy dziś, czy `dowod-z-paczki.sh` **potrafi paść** — a to on pilnuje
  paczki jadącej do klienta. Luka dotyczy WIARYGODNOŚCI narzędzia, nie samej paczki:
  zgodność paczki 0.9.20 jest zmierzona osobno (`paczka-zgodnosc.sh` → EXIT=0, 59/59 plików).
  **CO ZAMYKA:** wskazać w próbie paczkę, która w repo istnieje (albo wyliczać najnowszą
  zamiast wpisywać wersję), potem przelot prób od nowa. ⚠️ Naprawa unieważnia świeży log
  prób (warunek 6 `k7-koniec` przeterminowuje dowód po zmianie w `narzedzia-qa/`), więc
  robi się ją RAZEM z pozostałymi pięcioma padającymi próbami chatbota, nie osobno.

## 🔍 K7 — AUDYT SKLEPU W TOKU (od 25.08 wieczór) — plan `audyt/PLAN-K7-AUDYT-SKLEPU.md` v2, krytyka r1 obok
- Faza: PR-0 (paczka 0.9.13 + D33) → materiał `audyt/sklep/material/` → kalibracja → runda A. Stan bieżący
  i decyzje: `/root/.claude/CONTEXT-SERWER.md`. ⛔ Kod zamrożony tagiem `k7-zamrozenie` po scaleniu PR-0.
- Zaległości znalezione PRZY CZYTANIU (cele mapy, NIE naprawiać przed audytem): DOK-TECH §3 „7 tabel" vs §8 „6",
  „#170" w dokumencie klienta, tabela §2 bez `opinie.php`; `REJESTR-WERYFIKACJI.md` martwe wpisy Cloudflare/Vercel;
  zakup gościa wymaga nonce (`zakup.php:66`) → page-cache; brak eksportera/erasera RODO WP (D: dopisać w naprawach);
  zwroty Stripe bez obsługi (decyzja po L3); WP-Cron bez ruchu nie w instrukcji.
- 3 martwe pliki mediów w paczce (1,06 MB) — kasowanie ZGODA Dzidka 25.08, wykonać w rundzie napraw (bump).

## ✅ ZAMKNIĘTE 25.08 (wieczór) — SKLEP: readme.txt + CHANGELOG.md + paczka 0.9.13 (PR-0 K7)
- `paczka-zgodnosc.sh sklep-agentow` EXIT 0 (6 miejsc wersji, zip == repo 59 plików, treść wydania, 0 śladów),
  próba kontrolna bramki EXIT 0, `bramki.sh --tryb pelny` 8/8. `buduj-paczki.sh` z wyborem wtyczki argumentem
  (zamek wydanych zipów botów). Dług z #176 zamknięty.

## 🔄 W TOKU 20.08 (noc) — RUNDA BOTÓW, DWA BUILDERY
⏰ **TERMIN: do 1.09** (Dzidek 20.08 — zmiana z 30.08).
- **builder1** (tmux praca:builder1, drzewo główne, gałąź `boty/runda-finalna`): TYLKO chatbot
  + klasa AI w obu botach. Zrobione: Z1 (fałszywy sukces zapisu, oba boty), **Z0 (tryb AI —
  był MARTWY)**, Z2 (zgoda RODO w formularzu). Dalej: PLAN-NAPRAW-CHATBOT v2, P1 i P2.
- **builder2** (tmux praca:builder2, drzewo `sklep-agentow-boty`, gałąź `boty/segregator-naprawy`):
  TYLKO segregator, punkty 1-8 z BRIEF-BUILDER2. ⛔ nie dotyka `segregator-maila/includes/ai.php`.
- ⛔ **Scalenie gałęzi + bump 1.2.2 + przebudowa paczek = koordynator**, nie buildery.
- ⏸️ **WSTRZYMANE do decyzji**: eksport filtrów Gmail/Outlook (P1.3) — nasz silnik zatrzymuje się
  na pierwszej pasującej regule, Gmail stosuje wszystkie naraz → eksport 1:1 dałby inny wynik
  niż nasz raport. Rekomendacja: eksport z jawnym ostrzeżeniem + lista reguł nieprzekładalnych.
- 🔑 Klucze API (Anthropic, Gemini) w `/root/.claude/sekrety-*.env` — **DO SKASOWANIA po testach**.
- ⚠️ Tunel publiczny AKTYWNY, siteurl stacka przestawiony na adres tunelu (przepis przywrócenia
  w CONTEXT-SERWER.md).


## ⚠️ WISZĄCE OD 19.08 (sesja Gosia-3) — TUNEL PUBLICZNY na czas oglądania Dzidka
- ~~Tunel cloudflared AKTYWNY~~ **NIEAKTUALNE (sprawdzone 30.08: proces `cloudflared` nie żyje, `siteurl`=`http://localhost:8890`)** — wpis zostawiony jako ślad procedury: `https://linda-refrigerator-potato-objective.trycloudflare.com`
  → :8890 (proces w tle sesji; log `/tmp/cf-sklep-8890.log`). **siteurl+home stacka
  PRZESTAWIONE na adres tunelu** — po seansie Dzidka PRZYWRÓCIĆ:
  `docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update siteurl "http://localhost:8890" && docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update home "http://localhost:8890"`
  + ubić tunel (`pkill -f "cloudflared tunnel --url http://127.0.0.1:8890"`).
  ⛔ Do tego czasu testy automatyczne po localhost NIE są miarodajne (canonical
  redirect na tunel); E2E przez tunel: kopia `przelot2-tunel.js` ⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE — do odtworzenia z `testy/e2e/przelot2.js` przez podmianę adresu bazowego na tunel.
- **header.php motywu KOPII zmieniony poza repo** (link „Sklep" w obu nav —
  D28): backup w kontenerze `header.php.przed-sklepem`; dosłowne fragmenty
  w INSTRUKCJA-INSTALACJI (sekcja Ustawienia produkcyjne).

## ✅ ZAMKNIĘTE 19.08 (późny wieczór) — ⭐ K5b3 FINAŁ SZLIFU (PR #61 MERGED) = SZLIF WIZUALNY ZAMKNIĘTY
- Sędzia finalny **84/100** (przynęta „sekcja wideo" rozbrojona): finalny CTA
  listy („Wybierz bota…" + 2 przyciski) · hierarchia linków (DLA KOGO:
  „Więcej o tym bocie") · kadr raportu 1200×470 (czytelność +60%) ·
  BONUS: Sklep dopisany do nav STOPKI (footer.php kopii, backup; instrukcja).
- Zarzut „pusta stopka" 2× ODRZUCONY z twardym dowodem: viewport po WOLNYM
  scrollu = pełna stopka, wszystkie elementy op=1. ⭐ WADA NARZĘDZIA:
  fullPage-screenshot playwrighta NIE odpala reveal-animacji motywu — oceny
  wyglądu odtąd zrzutami VIEWPORTU po wolnym scrollu, nigdy samym fullPage.
- Recenzja #61 czysta (zero uwag krytycznych/ważnych). E2E: mail_wyslany
  (5. pełny przelot dnia). Sklep w nav: nagłówek desktop + mobile + stopka.

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5b2 ILUSTRACJE SVG KART (PR #60 MERGED, D28b)
- Karty listy: WŁASNE ilustracje SVG w brandzie (roboty-bliźniaki, 880×560,
  ~4 KB) — feedback Dzidka „fajne obrazy jak profesjonaliści"; zrzuty żywych
  botów zostały na stronach produktów (podział: lista=marketing, produkt=dowód).
  Generyczny stock/AI-obrazy ODRZUCONE (D28b). Opinie/gwiazdki NIE (D6/Omnibus
  — powiedziane Dzidkowi wprost).
- Recenzent złapał REALNEGO BUGA (modyfikator klasy CSS doklejany do złego
  członu przy dwuczłonowym $klasa) → naprawiony, dowód surowym renderem klas;
  D28b dopisane do rejestru (kod cytował decyzję bez wpisu — klasa #3).
  ⭐ NOWA ZASADA z uwagi krytycznej: opis PR-a MA surowe wyjście komend, nie
  deklaracje. E2E po każdej porcji: mail_wyslany (łącznie 4× dziś).

## ✅ ZAMKNIĘTE 19.08 (popołudnie) — ⭐ K5b WIZUALIA (PR #59 MERGED, sklep 0.9.0, D28)
- **Feedback Dzidka obsłużony**: link „Sklep" w nav (desktop+mobile) · zrzuty
  ŻYWYCH botów na kartach i w sekcji „JAK TO WYGLĄDA" (rama „okna aplikacji") ·
  lista: pasek konkretów + OD ZAKUPU DO DZIAŁANIA + DLA KOGO (jedno źródło
  treści) + PYTANIA (liczby ze stałych = dok=kod). Wideo ODRZUCONE (D28).
- **Sędzia 79/100 (przynęta rozbrojona)**: 2 zgrzyty naprawione (okno 20rem,
  rama okna), zarzut „pustka przed stopką" ODRZUCONY Z DOWODEM (artefakt
  fullPage bez scrolla — reveal stopki gospodarza, opacity [0,0]→[1,1] po
  scrollu; ⚠️ zrzuty full-page odtąd PO przescrollowaniu!), postulat opinii
  ODRZUCONY (D6/Omnibus).
- **E2E ×2 przez tunel: mail_wyslany** · recenzent: 4 drobne uwagi zaadresowane
  (2 kodem, 2 pomiarem: wymiary JPG zmierzone 1:1, kadr segregatora obejrzany) ·
  stack==main diff czysty · paczki botów 1.2.1 NIETKNIĘTE.
- Zwiad wzorców: agenciai.pl = landing 1 szkolenia → osobna domena sprzedażowa
  claudedlafirm.pl (30k px, countdown, opinie) — model NIE nasz; podebrane:
  kroki uruchomienia, konkrety techniczne, FAQ listy.

## ✅ ZAMKNIĘTE 19.08 (noc) — ⭐ K6 INSTALACJA OD ZERA (przyciągnięte z 24.08), sklep 0.8.2
- **NIE-autor = niezależna sesja-tester** (Sonnet, tmux, deny na kod/warsztat —
  decyzja Dzidka: „tester niezależny przez tmux jak buildery"). Pełna ścieżka
  ZALICZONA: instalacja→Stripe→produkty→zakup→boty→deinstalacja; czas 26:58
  brutto / 15-20 min netto (kryterium #1 ≤30 min ✓). Raporty:
  audyt/K6-INSTALACJA-OD-ZERA.md + K6-RAPORT-TESTERA.md (verbatim).
- **4 znaleziska naprawione w PR k6/naprawy-po-tescie:** 🔴 permalinki „Zwykłe"
  na świeżym WP (Krok 0 instrukcji + notice 0.8.2, zmierzony na żywo w obie
  strony) · 🟠 500 na checkout.session.completed = celowy RESUME (wyjaśnienie
  w instrukcji) · 🟡 „Zamówienia"→„Pulpit" · 🔴 boty zostają w bazie po
  deinstalacji wg instrukcji (10 tabel — pomiar zewnętrzny; sekcja
  Odinstalowanie uzupełniona o osobne czyszczenie botów).

## ✅ ZAMKNIĘTE 19.08 (dzień) — DROBNICA recenzji #40/#42 (PR #57)
- **Rate-limit sklepu → LAST_INSERT_ID** (#40): osobny SELECT licznik miał
  mikro-okno wyścigu → wzorzec 1:1 z chatl_rate_limit; komentarz ostrzegawczy
  o fail-open dołożony (uwaga recenzji #57).
- **Test dziennika/opóźnienia** (#42): `testy/dziennik-test.js` na żywym stacku —
  salwa/2^n/reset/retencja/konkrety S-09, próba kontrolna (próg 999 → pada).
- **Indeks okno_do chatbota** nazwany w PONAD-SPECYFIKACJE-BOTY (klasa #6).
- **Zipy produktów na stacku podmienione na 1.2.1** przez PRAWDZIWY CRUD panelu
  (sha bazy == sha zipów, zmierzone; snapshot zam#29 nietknięty — stare zamówienia
  trzymają swój plik, zgodnie z projektem).

## ✅ ZAMKNIĘTE 19.08 (dzień) — ⭐ PAKIET BOTÓW (D25, przyciągnięty z 23.08), boty 1.2.1
- **phpcs na WSZYSTKIE 3 wtyczki:** phpcbf (58 auto-napraw, diff obejrzany — zero zmian
  semantyki) + 3 adnotacje inline z uzasadnieniem + `customSanitizingFunctions`
  (chatl_waliduj_sesje) + sniff doradczy DirectDatabaseQuery wyłączony dla botów JEDNĄ
  deklaracją w ruleset (uzupełnienie D25 — boty w 100% na własnych tabelach; PreparedSQL
  aktywny wszędzie). Zakres bramki potwierdzony PRÓBĄ KONTROLNĄ (podłożony var_dump
  w bocie = czerwone). phpcs EXIT 0, phpstan EXIT 0.
- **Widget chatbota przemalowany na paletę strony** (top-uwaga sędziego K5): limonka
  #bfff38 / tusz #08090b / węgiel #101116, dymki 1:1 z mockupem strony produktu,
  hover-pierścień jak CTA sklepu, obwódka bąbelka pod WCAG 1.4.11 na jasnych stronach;
  zrzuty na stacku gospodarza ORAZ na czystym WP; kolory zmierzone z żywego DOM.
- **WROGA SESJA BOTÓW na czystym WP** (audyt/WROGA-SESJA-BOTY.md): aktywacja bez
  jednego notice (WP_DEBUG_LOG), seedy komplet, cykl deakt→akt bez strat, przebieg
  segregatora działa, pełne czyszczenie = zero tabel/opcji/cronów. 🔴→✅ ZNALEZISKO
  klasy #1 ściągi: klucz API zostawał w bazie przy DOMYŚLNYM uninstallu — naprawione
  (delete przed early-return, oba boty), zmierzone przed/po, INSTRUKCJE dok=kod,
  paczki 1.2.1 przebudowane + powtórka weryfikacji `diff -r` (zip == repo).

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5 WITRYNA KOMPLET, 3 PR-y (#51-#53), wersja 0.8.1
- **#51 fundament+lista** (wyspa z tokenami zmierzonymi z motywu; mono-etykiety,
  pille, baner-pigułka; H1 nasz — motyw nie renderuje własnego, zmierzone) ·
  **#52 strona produktu+SEO** (jedna strona WP + query var; rewrite ze sluga
  z opcji; sekcje §2B komplet: mockup czatu/PRZED-PO/kroki/dowody/uczciwość/FAQ;
  SEO: title/desc/canonical/OG/JSON-LD Product+Breadcrumb+FAQPage/okładki PNG/
  noindex-dziękujemy/sitemap provider [nazwa BEZ myślnika — regex rdzenia];
  hijack metadanych query varem znaleziony recenzją, zamknięty Z POMIAREM;
  PEŁNY przelot E2E po zmianie: mail_wyslany) · **#53 szlif po sędzi** (ikony
  SVG kart, jawne „nie zmyślamy recenzji", FAQ separatory, kropka-glow).
- **Sędzia wyglądu: 73/100 → 85/100** (dwa świeże przebiegi, przynęty rozbrojone
  w OBU; 2 zaniżenia rundy 2 = artefakty zestawu zrzutów, odrzucone z dowodem;
  PEŁNE werdykty obu rund: komentarze w PR #53 — rekomendacja recenzji #54).
- Cross-browser 6/6 (chromium+firefox, zero błędów konsoli) · zagadka sitemap
  ROZWIĄZANA: blog_public=0 wyłącza sitemapy w rdzeniu (nie usterka!) → wpis
  do instrukcji K8: na produkcji wyłączyć „Zniechęcaj wyszukiwarki".
- Na stacku doszedł REALNY produkt „Segregator maila" (zip 1.2.0) — sklep ma
  docelowe 2 produkty.

## ✅ ZAMKNIĘTE 19.08
- **Uwagi recenzji #49 → PR #50 MERGED (main=19dbcc4, 0.6.1):** dok=kod (.bin
  usunięte + sweep całe repo: seed-demo, adnotacja hist. w K2-WROGA-SESJA) ·
  wynik $wpdb->insert/update sprawdzany + sierota sprzątana (dowód na żywo:
  wymuszona awaria INSERT → błąd, nie fałszywy sukces; plików 4→4) · kryterium #6
  W TEŚCIE produkty-test.js (9/9, z próbą kontrolną „edycja zaszła") · ścieżka
  zrzutu przez env. Recenzent potwierdził wszystko 2× + własny grep sweepu.
- **K5 plan wyglądu:** zwiad motywu (paleta/typografia/detale zmierzone) + zwiad
  wzorców stron produktowych (subagent, źródła w PLAN-WYGLADU-K5.md §5) →
  `PLAN-WYGLADU-K5.md` CZEKA NA KLEPNIĘCIE Dzidka przed budową.

## ✅ ZAMKNIĘTE 18.08 (dzień)
- Model AI botów haiku-4-5 (Dzidek) · timeout AI #37 · **oba boty scalone z haiku**
  (#36 chatbot, #37 segregator) · recenzent zna WP+ściągę+Handbook (#38) · hardening
  uninstall wg Handbook (#39) · chatbot atomowy rate-limit (#40) · **paczki 1.2.0
  zweryfikowane (haiku + finalny kod w zipie)** (#35). ZERO otwartych PR.
- **K2 wroga sesja sklepu — 7 ataków obronionych** (audyt/K2-WROGA-SESJA.md).

## ✅ ZAMKNIĘTE 18.08 (wieczór) — ⭐ K3 KOMPLET, 7 PR-ów (#41-#47), wersja 0.5.1
- **#41 bramki CI PHP** (php-lint 8.4 · phpcs WPCS[sklep, boty→23.08 D25] · phpstan L5
  wszystkie wtyczki · gitleaks; manifest +4; E2E przelot2.js uratowany do repo).
- **#42 dziennik logowań** (S-09; opóźnienie wykładnicze ZMIERZONE 0.3→2.3→4.3→8.3 s,
  reset po sukcesie; limit 6/min per IP PRZED sleep — salwa 12: 6×200+6×429 w 0.1 s;
  retencja 90 dni; schemat v3) · **#43 ustawienia** (Settings API; sekrety autoload=no
  TAKŻE przy update-in-place; uninstall kasuje sekrety ZAWSZE — klasa #1 ściągi;
  nadawca wokół naszego wp_mail) · **#44 dashboard** (KPI+delta %, rozkład, tabela,
  CSV, self-heal Z BUDŻETEM CZASU 8/15 s; suma słupków = KPI co do grosza) ·
  **#45 zamówienie** (mail+resend ze świeżym tokenem [stary 403/nowy 200],
  anonimizacja RODO — schemat v4) · **#46 fix ukrytej strony** (bug złapany ZRZUTEM —
  wp eval go nie widział) · **#47 szlif po sędzi wyglądu**.
- **Sędzia wyglądu #1: 66/100** (przynęty rozbrojone — wiarygodny); TOP-braki
  zaadresowane w #47 (pasek statusów, kolumna Akcje, notice, hierarchia KPI,
  zrzuty-dowody); świadome odstępstwa w WZORCE-DASHBOARD-K3 §8. Sędzia #2 w toku.
- Zrzuty panelu (10 szt.) ⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE; odtwarza je `testy/zrzuty-panelu.js` — skrypt jest w repo, więc dowód jest ODTWARZALNY, choć pliki przepadły.

## Blokuje wydanie
- (nic pilnego) — Live-smoke AI na żywym api.anthropic.com w ODWODZIE (~5$ za zgodą
  Dzidka), gdyby próba 1 chciała żywe AI; egzamin trybem regułowym 0 zł.

## Nie zaczęte (kolejność planu)
- **Nazwany dług (recenzja #49, drobna):** przy podmianie zipa na produkcie BEZ
  zamówień stary plik zostaje w katalogu chronionym (nie szkodzi, nie wycieka —
  katalog deny; sprzątanie wymagałoby odróżniania „trzymany snapshotem" od
  „wolny"). Świadomie NIE sprzątamy w v1 → wpis do `PONAD-SPECYFIKACJE.md` w K8.
- **Świadomie NIE ruszone (recenzja #40, #3):** default `$prog` w `chatl_rate_limit`
  — sklep ma default 10, bot nie; jedyne wywołanie w bocie podaje oba argumenty,
  recenzent oznaczył jako nieblokujące. Zostaje bez zmian celowo.
- **Instrukcja wdrożenia (K8): wzmianka fail2ban/WAF** na wp-login.php przy wielu IP
  (D26 — rezydualne ryzyko poza wtyczką).
- K7 25.08 (audyt + mutanty + kalibracja) · K7b 26.08 · K8 27.08 = próba 1. (K6 ✅ 19.08)

## Stan maszyny
- `stripe listen` forwarduje na :8890/webhook (sekret z `/root/.claude/sekrety-sklep.env`).
  Start po padzie: `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- Builder1: tmux praca:1, worktree sklep-agentow-boty, stoi (boty domknięte).

## Odroczenia z #172 (24.08, runda 1 — świadome, nieblokujące)
- **readme.txt Segregatora, wiersz „Wymagania"** — dopisać `stream_socket_client` + OpenSSL
  (dla prawdziwej skrzynki IMAP; FAQ sklepu już to mówi). Paczka 1.3.3 jest WYDANA i pod
  zamkiem — poprawka wchodzi z NASTĘPNYM wydaniem bota, nie osobno.
- **Bramka na LICZBY strony sprzedażowej** (`front-produkt.php`: „6 zrzutów", „10 par",
  „30 maili demo") — dziś prawdziwe (zmierzone w r1 #172), ale bez strażnika zgniją po cichu
  jak każda liczba; wzór: `dokumentacja-zgodnosc*.sh`.
- **Regulamin sklepu (strona DRAFT #120)**: dopisać gwarancję satysfakcji 14 dni (D31)
  i tryb wygaśnięcia aktualizacji przy zwrocie — przy finalizacji regulaminu w K8.

- ⛔ **SZEŚĆ PRÓB KONTROLNYCH NARZĘDZI PADA — dług sprzed PR #203, nie z niego.**
  ⭐ **TRZY POMIARY, KTÓRE ROZSTRZYGAJĄ:** przelot `d28c971` → 6 padających · `15540a9` → **5**
  (`przejecie` przeszła) · przelot na gałęzi celów → znowu **6** (`przejecie` znowu padła).
  Między żadnym z nich nie ruszyłam pliku chatbota. Liczba padających jest więc RUCHOMA,
  a log niesie zawsze stan z ostatniego przebiegu — nie traktuj żadnej z tych liczb jako stałej. Poniższy akapit opisuje pierwszy przelot; różnica między nimi jest sednem
  znaleziska o chwiejności.
  Pierwszy przelot, z commitu `d28c971`: uruchomionych 30,
  `EXIT≠0` w sześciu — `dowod-z-paczki` (EXIT=2, brak `boty/paczki/chatbot-live-1.5.0.zip`),
  `blad-klucza`, `nonce`, `ip-za-proxy`, `przejecie`, `pulapka`. Wszystkie sześć dotyczą
  **chatbota** — pięć ma `CEL=…/plugins/chatbot-live`, szósta wymaga starej paczki chatbota.
  Żadna nie tyka sklepu ani segregatora. [zawężone po recenzji #203 r2: wcześniej pisało tu
  „chatbota i segregatora”, co było nieścisłe]
  ⭐ POMIAR, NIE PRZYPUSZCZENIE: te same dwa strażniki (`blad-klucza`, `nonce`) uruchomione
  w osobnym drzewie roboczym na `origin/main` padają IDENTYCZNIE (`EXIT=1`, komunikat
  „czysty kod NIE przechodzi — strażnik pada zawsze, więc nic nie znaczy”). PR #203 nie zmienia
  ani jednego pliku botów — `git diff --stat origin/main...HEAD -- wtyczki/chatbot-live/ wtyczki/segregator-maila/ boty/`
  jest pusty.
  ⛔ Dwa z nich (`blad-klucza`, `nonce`) to nie ślepota na część wad, tylko **strażnik, który
  pada ZAWSZE** — czyli nie odróżnia kodu z wadą od kodu bez wady i jako bramka nie znaczy nic.
  ⛔ Skutek (recenzja #210): **przez ten dług zabezpieczenie nonce i obsługa błędu klucza w chatbocie są dziś NIEPILNOWANE** — regresji w nich nie złapie żadna bramka. To dług bezpieczeństwa, nie tylko padająca próba.
  To pilne, ale to teren botów (chatbot ma świadomie otwartą klasę 8) — do rozstrzygnięcia
  osobno, nie w PR-ze o przelocie sklepu.
  ⭐ **JEDNA Z NICH JEST CHWIEJNA — zmierzone dwoma przelotami tej samej gałęzi.**
  Przelot z `d28c971`: 6 z EXIT≠0. Przelot z `15540a9`: **5** — `przejecie-proba-kontrolna.sh`
  przeszła, choć między tymi commitami nie ruszyłam ani jednego pliku chatbota (zmiany to
  komentarze, dokumentacja i test sklepu). Czyli jej wynik zależy od stanu wspólnego stosu,
  a nie od kodu, który ma pilnować.
  ⛔ Chwiejny strażnik jest GORSZY od stale czerwonego: stale czerwony zmusza do naprawy,
  chwiejny uczy, żeby uruchomić ponownie i pojechać dalej na zielonym z przypadku.
  Skutek dla domknięcia: `k7-koniec.sh` kontrola 6 zostaje czerwona niezależnie od tego PR-a.

- ⛔ **DWA POLA `required` W PANELU WŁAŚCICIELA NADAL BEZ POLSKIEGO KOMUNIKATU — świadomie nie objęte przez PR #203.**
  `wtyczki/sklep-agentow/includes/admin-produkty.php:168` (Nazwa) i `:171` (Cena) stoją na samym
  `required`, więc puste pole odpowie właścicielowi dymkiem przeglądarki — tym samym, który
  PR #203 usunął z frontu. Naprawa tam nie sięga **z definicji**: `assets/front-ruch.js` ładuje się
  wyłącznie na stronach sklepu (`sklep-agentow.php:118-149`, `is_page( $strona_sklep ) || is_page( $strona_dziekujemy )`),
  a ekrany `wp-admin` żadnego z tych skryptów nie dostają.
  **Dlaczego odłożone, a nie naprawione:** to tekst dla WŁAŚCICIELA (jedna osoba, która i tak zna
  swój sklep), nie dla kupującego. Nasza reguła każe zawsze wracać do tekstu czytanego przez
  KLIENTA — ten nim nie jest. Objęcie panelu wymaga osobnej kolejki assetów dla `wp-admin`,
  czyli zmiany szerszej niż PR o przelocie.
  **Ile naprawdę liczy ta klasa** (wskazane w recenzji #203 r3, przemiot poprawiony po r4):
  `admin-produkty.php:168` (`required`), `:171` (`required` + `type="number"` + `min` + `pattern` — `pattern` dopisany po recenzji #203 r5, wcześniej go gubiłam),
  `admin-ustawienia.php:249` i `:254` (`type="email"`), `:259` (`pattern`).
  Czyli **pięć miejsc, nie dwa** — samo `required` to dopiero część.
  ⛔ We wcześniejszej wersji tego wpisu liczyłam sześć, bo doliczyłam `admin-produkty.php:158`
  — a to KOMENTARZ o polu, nie pole. Sprawdzone `sed -n 158p`. Ta sama klasa, na którą
  łapię się w tym audycie: numer linii przepisany bez otwarcia linii.
  **Co trzeba zrobić, gdy wróci:** wpiąć te same stałe `SKLEPAG_BLAD_*` przez atrybut
  `data-sklepag-blad` i dołożyć tę samą procedurę do assetów panelu. ⛔ To zamknie WYŁĄCZNIE
  brak wartości: procedura ustawia tekst pod warunkiem `pole.validity.valueMissing`, więc
  `typeMismatch` (`type="email"`) i `patternMismatch` (`pattern`) nadal rysowałaby przeglądarka.
  Na froncie to bez znaczenia — cztery objęte pola mogą naruszyć wyłącznie `valueMissing`.
  Zgłoszone przez recenzenta w #203 r2 jako DROBNA 2, przyjęte jako dług z nazwą.

- ⛔⭐ **KONTROLA 2 `k7-koniec.sh` ZOSTAJE CZERWONA ŚWIADOMIE — decyzja właściciela 28.08, nie przeoczenie.**
  Dwie soczewki, `L1` i `L5`, mają w `ROZLICZENIE-MUTANTOW-SKLEP.md` werdykt **FAIL — NIEKALIBROWANA**,
  każda po **dwóch** próbach. `L1` nie złapał M1 (nieudane logowanie na NIEISTNIEJĄCY login nie trafia
  do dziennika), `L5` nie złapał M6 (`HEAD` zużywa slot pobrania, wbrew zamówieniu i readme).
  ⛔ **Trzeciej próby NIE MA i nie będzie** — zasada zapisana w tej samej tabeli brzmi: „jedna poprawka
  promptu/materiału i jeden ponowny przebieg (`proby` ≤ 2); drugi FAIL = soczewka NIEKALIBROWANA”.
  Bramka osobno sprawdza `proby ≤ 2`, więc trzecie podejście i tak by ją zapaliło. Reguła dwóch prób
  istnieje po to, żeby nie kręcić promptem aż audytor trafi przypadkiem — wtedy kalibracja przestaje
  cokolwiek mierzyć.

  ⭐ **ROZJAZD NARZĘDZIA Z RUBRYKĄ KLIENTA — i to jest sedno tej decyzji.**
  Kryterium GOTOWE #12 (`PLAN-PROJEKTU.md` §12) wymaga kalibracji **≥ 2/3 mutantów**. Policzone
  z tabeli rozliczenia: mutantów prawdziwych 7 (M1–M7), złapanych 5 (M2, M3, M4, M5, M7) = **71 %**,
  przy 0 fałszywych alarmach na F1–F4. **Kryterium klienta jest SPEŁNIONE.**
  `k7-koniec.sh` warunek 2 jest OSTRZEJSZY: żąda, żeby KAŻDA soczewka miała PASS. Bramka i rubryka
  mierzą więc dwie różne rzeczy, a bramka jest surowsza od tego, co obiecaliśmy.

  **DECYZJA (Dzidek, 28.08): oddajemy z jawnym zapisem.** Odrzucone świadomie:
  – trzecia próba dla `L1`/`L5` → łamie własną regułę, psuje sens kalibracji;
  – poluzowanie warunku 2 w `k7-koniec.sh` do „≥ 2/3” → obrona robiona na narzędziu w dniu oddania,
    czyli dokładnie wtedy, kiedy najłatwiej sobie coś przyklepać.

  **CO TO ZNACZY DLA KLIENTA, po ludzku:** audyt sprawdził sklep siedmioma niezależnymi soczewkami.
  Pięć z nich udowodniło na podłożonych błędach, że naprawdę widzą wady. Dwie tego nie udowodniły —
  więc **to, czego nie znalazły w obszarze dziennika logowań (`L1`) i zgodności dokumentacji
  z zachowaniem (`L5`), nie jest dowodem, że tam nic nie ma.** Ich „czysto” waży mniej niż pozostałych.
  ⛔ To zdanie ma trafić do raportu oddania. Nie wolno go zastąpić zdaniem „audyt przeszedł”.

  **CO ZAMYKA TĘ POZYCJĘ, gdy wróci:** przepisać prompty `L1` i `L5` wg wniosków z rundy A
  (`L1`: ścieżka WROGA, nie szczęśliwa — logowanie na nieistniejący login; `L5`: obietnica behawioralna
  = wykonanie, nie grep stałej), zbudować NOWY bank mutantów i przebiec kalibrację od zera —
  jako osobne zadanie z własnym licznikiem prób, nie jako trzecią próbę tej samej.

## 🟡 TRZECI NO-GO w `k7-koniec` (kontrola 3d) — ZAMIERZONY, nie regresja (28.08)
`SK-L6-003` ma status `ZAMKNIETE` i **nie ma dowodu kalibracji** — kolumna
`straznik_padl_na_starym` niesie `—` zamiast SHA. To jest poprawka, nie usterka: wcześniej stał tam
`b7fe60e`, a teza tego samego wiersza mówiła, że na `b7fe60e` przyrząd **nie padał** (po cichu
uruchamiał chromium i kończył zerem). Kalibracja jest tu **niewykonalna z natury** — znaleziskiem
był BRAK pomiaru, nie wada produktu, więc nie istnieje commit, na którym strażnik mógłby paść.
⛔ **Odrzucone świadomie:** dorobienie bramce wyjątku na wzór `WYJATKI-WARUNEK-8.txt`. Ten sam ruch
odrzuciliśmy w `#205` przy kalibracji jako obronę robioną na narzędziu w dniu oddania.
**CO ZAMYKA:** albo bramka dostanie jawny rejestr wyjątków (osobny PR, poza dniem oddania), albo
rejestr dostanie czwarty stan dla znalezisk typu „brak pomiaru”, których nie da się skalibrować.

## 🟡 DŁUG Z RECENZJI `#206` i `#207` — drobne, nie zmieniają tego, co dostaje klient (28.08)
- **`#206` DROBNA 6** — kalibracja walidacji nazwy silnika była zadeklarowana, nie pokazana.
  ✅ ZAMKNIĘTE w `#207`: mutant odtwarzający starą logikę (`msedge` → chromium) kończy `EXIT=2`,
  bez mutanta `EXIT=0`.
- **`#206` DROBNA 4** — strażnik z rejestru nie był uruchamiany przez nic. ✅ ZAMKNIĘTE: sędzia
  `testy/rodo-anonimizacja-pomiar-skutku-test.sh` leży w `testy/`, a `narzedzia-qa/linia-bazowa.sh`
  zbiera `testy/*.sh`. Kroki pomocnicze przeniesione do `testy/kroki-rodo/`, żeby linia bazowa
  nie uruchamiała ich osobno (bez sędziego nie sprzątają po sobie).
- ⬜ **`#207` WAŻNA 4, druga połowa** — asercja zgodności kodu ze stosem stoi w teście RODO,
  ale POZOSTAŁE testy na żywym stosie jej nie mają. Do przeglądu przy następnej rundzie QA:
  każdy test mierzący stos powinien najpierw dowieść, że mierzy TEN kod, który jest w repo.
- ⬜ **`#207` DROBNA 5** — `sklepag_obsluz_anonimizacje()` wciąga `mail_tresc` do pamięci
  (`SELECT id, email_kupujacego, mail_tresc`) i nigdy go nie używa ani nie czyści. Dziś nieszkodliwe:
  treść maila nie niesie adresu w innej postaci niż ta czyszczona. Do przeglądu przy następnej
  rundzie QA: albo pole wypada z zapytania, albo zostaje objęte anonimizacją — obecny stan jest
  niejednoznaczny dla czytającego (docblock mówi o tym wprost, żeby nie było wątpliwości).
- ⬜ **Siódmy migoczący strażnik chatbota: `testy/statystyki-proba-kontrolna.sh`.** Kończy `EXIT=1`
  z komunikatem „TEST SLEPY NA 1 PODLOZONYCH BLEDOW” — nie łapie wady „wiersze bez znacznika wliczone
  do skuteczności” w raporcie skuteczności chatbota. ⛔ Zmierzone 28.08 dowodem przez wykluczenie:
  pada **także na czystym `origin/main`** (`EXIT=1`), a PR-y sklepu nie ruszały ani jednego pliku
  chatbota (`git diff --stat origin/main..HEAD -- wtyczki/chatbot-live/` → pusto). Migocze między
  przelotami (raz jest w szóstce, raz w siódemce), stąd rozbieżne liczby w logach — **nie jest to
  regresja żadnego PR-a sklepu**. **CO ZAMYKA:** naprawa razem z pozostałymi strażnikami chatbota
  — to jeden teren, jedna robota, nie sześć osobnych.
- ⬜ **BLIŹNIAK klasy „zapisujemy skutek, nie sprawdziwszy, czy operacja się udała" —
  ścieżka ponownej wysyłki maila** (`wtyczki/sklep-agentow/includes/admin-zamowienie.php`,
  `sklepag_obsluz_resend()`). Zmierzone 28.08 (recenzja `#207`, runda 3): **sześć** wywołań
  `$wpdb->query()` bez sprawdzenia wyniku — zapisy tokenu, `link_wygasa`, `mail_tresc`, statusu
  `mail_blad` i licznika prób oraz tokenu opinii. Skutek zapisywany niezależnie od powodzenia.
  ⛔ To ta sama klasa, którą ten PR naprawił w anonimizacji — czyli klasa 8 briefingu (naprawa
  egzemplarza zamiast klasy). Nazwana w kodzie, przy funkcji.
  **CO ZAMYKA:** przenieść wzorzec z anonimizacji — pomiar STANU po zapisie zamiast kodu powrotu —
  wraz z testem podkładającym awarię każdego z sześciu zapisów osobno. Osobna robota, bo to inna
  ścieżka: ponowna wysyłka i kompensacja po nieudanym mailu mają własne przypadki brzegowe.
- ⬜ **Adres z `/` w części lokalnej zostaje w dzienniku mimo potwierdzenia usunięcia.**
  `wp_json_encode` bez flag zapisuje ukośnik jako `\/`, a anonimizacja szuka SUROWEJ postaci
  adresu (`opis LIKE %adres%`). Adres taki jak `a/b@x.pl` (RFC dopuszcza, w praktyce spotykany
  wyjątkowo) nie zostanie znaleziony — ślad zostanie, a wtyczka potwierdzi usunięcie.
  ⛔ Nazwane świadomie, nie przeoczone (recenzja `#207`, runda 7; recenzent zgodził się na
  nazwanie zamiast naprawy). Granica opisana także w docblocku funkcji.
  **CO ZAMYKA:** szukać obu postaci naraz (surowej i po `wp_json_encode`) oraz dołożyć przypadek
  testowy z adresem zawierającym `/` — z kalibracją, że na dzisiejszym kodzie ślad zostaje.

---

## 🟡 SKANER ŚLADÓW MIJA WZORZEC PRZEZ WIELKOŚĆ LITERY (29.08, recenzja `#209` r6 i r8)

⛔ **Ten wpis powstał z opóźnieniem i to jest część lekcji.** W opisie PR-a rundy 7 napisałam,
że ten dług „zostaje nazwany w `CO-WISI.md`" — a nie było go tutaj. Recenzja r8 sprawdziła to
komendą i wykazała: `grep -c 'wielkość litery' audyt/CO-WISI.md` → **0**. Czyli odhaczyłam
w liście kontrolnej rzecz, której nie zrobiłam. Klasa 3 zastosowana do **własnego opisu**.

**Wada:** `narzedzia-qa/slady-wewnetrzne.sh` zna wzorzec „próba kontrolna", ale mija go, gdy
w tekście stoi z wielkiej litery (`Próba kontrolna`). Egzemplarz w kodzie klienta
(`sklep-agentow/includes/aktywacja.php:230`) usunięty w `#209`; **sam wzorzec nadal jest ślepy**.

**CO ZAMYKA:** dopisać `-i` albo jawne warianty wielkości liter do tej rodziny wzorców
i dołożyć przypadek do próby kontrolnej, który podkłada ślad **z wielkiej litery** — inaczej
próba przechodzi, bo mierzy wyłącznie wariant z małej. To zmiana narzędzia QA, nie produktu.

⛔ **Rodzina pokrewna, wykazana w r8 i szersza niż ta:** odsyłacze do naszych dokumentów
zapisane **słowem, nie oznaczeniem** — `plan v3 par. 4A`, `(Z10)`, `patrz Z0`, `Wymóg z briefu`.
Żaden z trzech wzorców z tego pliku ich nie widzi, bo wszystkie szukają `§`, `K[0-9]`, `D2[0-9]`.
Egzemplarze usunięte w `#209`; wzorce nadal ich nie znają. ⭐ To potwierdza zasadę zapisaną
wyżej: **wzorzec jest siatką na regresję, nie dowodem czystości** — dla plików, które da się
przeczytać, miarą jest przeczytanie.

## 🟡 PLUGIN CHECK NA PACZKACH (30.08) — jedno znalezisko w kodzie, reszta poza zakresem wtyczki dla klienta

Raport i triage: `audyt/PLUGIN-CHECK-30-08.md` (surowe wyniki `audyt/plugin-check/`, narzędzie
`narzedzia-qa/plugin-check-paczki.sh`). 416 pozycji, z czego **jedna dotyczy kodu**:
- ⬜ **`wtyczki/sklep-agentow/includes/admin-opinie.php:16` i `:120`** — `esc_html__( 'Brak uprawnień.' )`
  bez domeny w wtyczce, która nie tłumaczy niczego (brak `Text Domain`, 0 innych `__`). Naprawa:
  `esc_html( 'Brak uprawnień.' )`. Zero wpływu na kupującego i właściciela — string jest po polsku
  i wyświetla się poprawnie. **Wchodzi z pierwszą następną zmianą kodu sklepu** (podbicie wersji,
  paczka, runda) — nie samo przed 1.09.
- Pozostałe: reguły katalogu WP.org (readme po angielsku, `.md` w korzeniu, prefiksy w `uninstall.php`),
  fałszywe alarmy na nazwach tabel oraz nasze własne sanitizery/brama nonce, których Plugin Check
  nie zna, a `phpcs.xml.dist` zna (D25). Świadomie NIE wyciszane — Plugin Check to raport, nie bramka.
- Plugin Check w CI: **świadomie nie** — wykonawca własny na 3,8 GB RAM, akcja stawia drugi WP.

## 🟡 INSTRUKCJA INSTALACJI BEZ ZRZUTÓW (30.08, recenzja PR `#217`)

`dokumentacja/INSTRUKCJA-INSTALACJI.md` jest tekstowa (0 obrazów; K8 planu żądał zrzutów maszynowych). Kroki nazywają ekrany
po nazwie, instrukcja OBSŁUGI sklepu ma 5 zrzutów. **Dług:** przebieg `testy/zrzuty-instrukcje.js` na czystym stosie → 4–6
zrzutów (wgranie wtyczki, aktywacja, Ustawienia sklepu, webhook) — po oddaniu, przy 0.9.24.

## 🟡 PO K9 (instalacja z Release na motywie kolegi, 30.08) — trzy długi na 0.9.24, `audyt/K9-INSTALACJA-Z-RELEASE-0.9.23.md`

1. `dokumentacja/INSTRUKCJA-INSTALACJI.md`, menu mobilne: „po pozycji 08” — w pliku FAQ ma „07” (opóźnienie 0,35 s); tester trafił,
   numeracja myli. Tekst dla klienta, ale paczka zbiorcza już w Release → poprawka = nowe wydanie (0.9.24), nie w miejscu.
2. Komunikat aktywacji „Brak katalogu chronionego — deaktywuj i aktywuj” nie nazywa przyczyny (uprawnienia `wp-content/uploads`);
   „Najczęstsze problemy” wiąże `chown` tylko z uploadem mediów. Dopisać przyczynę w komunikacie i w instrukcji.
3. Narzędzie: `narzedzia-qa/lib-stos-probny.sh` deklaruje `WP_DEBUG_LOG` przez `WORDPRESS_CONFIG_EXTRA`, obraz `wordpress:6.9.4` tego
   NIE wpisuje → „0 notice’ów” na tym stosie było zerem z martwego loggera (tester to wyłapał). Naprawa: `wp config set` po `postaw_stos`
   + próba kontrolna z podłożonym `trigger_error` (K9 zrobiła to ręcznie: 1 trafienie, potem 0 na 8 adresach).
   ⛔ Mechanizm, który to ukrył (recenzja `#218`): `log_bledow()` w tej bibliotece robi `cat debug.log 2>/dev/null … || true` —
   brak pliku i brak loggera dają TEN SAM pusty wynik co „zero błędów”, a konsumenci (`dowod-z-paczki.sh:171-177,195,249`,
   `dowod-aktualizacji.sh:228`, `dowod-zachowania-chatbot.sh:190,247,281`, `dowod-zachowania-segregator.sh:195,238`) piszą
   „(WP_DEBUG włączony)”, czego nie mierzą. Naprawa klasy: `log_bledow()` ma PADAĆ (exit 2), gdy `debug.log` nie istnieje lub
   `WP_DEBUG_LOG` nie jest zdefiniowany, a `postaw_stos` ma kończyć się podłożonym notice’em i `grep -c` = 1.

## 🟡 LISTA STATUSÓW ZAMKNIĘTYCH W TRZECH SKŁADNIACH (30.08, `/code-review ultra` na `#213` — jedyne znalezisko, waga „nit”)

`'zwrocone','sporne'` żyje jako fragment SQL `SKLEPAG_STATUSY_ZAMKNIETE` (`wtyczki/sklep-agentow/sklep-agentow.php:44`), tablica PHP
w bramce 409 (`wtyczki/sklep-agentow/includes/admin-zamowienie.php:63`) i konkatenacja `NOT IN` (`:117`, `:154`); `'mail_wyslany','mail_blad'` — bramka 409 (`:67`)
i widoczność przycisku (`:504`). Scenariusz: nowy status zamknięty dopisany do stałej SQL, zapomniany w tablicy → „Wyślij
ponownie” przechodzi mimo chargebacku. **Dług:** jedna tablica PHP jako źródło, fragment SQL budowany z niej (`implode`),
strażnik porównujący oba. Po oddaniu, przy 0.9.24. Zero blokerów w tej recenzji.

## 🟡 TRZY LICZBY BRAMKI KOŃCOWEJ ŻYJĄ W TRZECH PLIKACH, PRZEPISANE RĘCZNIE (30.08, recenzja PR rubryki, D8)

`AUDYT-RAPORT.md` (korzeń, czyta go hook `oddanie-guard` spoza repo), `rubryka-GOTOWE.md` i `audyt/RAPORT-ODDANIA.md` §2
niosą te same `KRYTYCZNE 0 · DUŻE 0 · FAIL 2 z 13` — nic w repo nie sprawdza, że się zgadzają z wyjściem `k7-koniec.sh`.
**Dług:** jedna kontrola w `narzedzia-qa/k7-koniec.sh` (porównanie nagłówka `AUDYT-RAPORT.md` z policzonymi liczbami,
FAIL przy rozjeździe) + próba kontrolna. Po oddaniu.

## 🟡 BRAMKA ODSYŁACZY STRONY TYTUŁOWEJ: NIEWPIĘTA, BEZ PRÓBY KONTROLNEJ, CZYTA TYLKO README (30.08, recenzja PR oddania r2–r3, W3–W5, D7–D8)

`testy/odsylacze-strony-tytulowej.sh` (36 celów po `#213`) nikt nie woła — do `BRAMKI=` nie wchodzi z zasady
manifestu (osobny PR), więc uruchamiać ręcznie przed wydaniem. Nie ma próby kontrolnej (`*-proba-kontrolna.sh`,
przelot znajduje je po nazwie) — kalibracja „pada 5/36 bez #213” zniknęła ze scaleniem. Czyta treść tylko `README.md`;
`dla-klienta/PRZECZYTAJ-MNIE.txt` sprawdza wyłącznie istnieniem. **Dług:** próba kontrolna (podłożony martwy odsyłacz
→ EXIT=1), pętla po treści PRZECZYTAJ-MNIE, wpięcie przez manifest. Osobno: trzy PNG w `dokumentacja/` są generowane
(`render-diagramy.js`) i nic nie sprawdza zgodności ze źródłem SVG (klasa 10); liczba kontroli w komunikacie
`pakuj-dla-klienta.sh` wpisana ręcznie, nie policzona. Wszystko po oddaniu — narzędzia, nie produkt.

## 🟡 PONOWIENIE PRZEZ CRON NIE MA KOMPENSACJI TOKENU (30.08, recenzja PR instrukcji r6, WAŻNA 1)

`admin-zamowienie.php` po nieudanym resendzie przywraca stary token/termin/licznik, ale zostawia `mail_blad`,
więc po ~2 min `sklepag_domknij_zakup()` (cron) rotuje token; gdy TA wysyłka też pada, gałąź porażki
(`zakup.php`, `status='mail_blad', mail_proby+1`) niczego nie przywraca — kupujący z działającym linkiem
traci go na stałe przy trwałej awarii poczty. Zachowanie sprzed PR-a `#213`. **Decyzja:** w `#213` naprawiony
TEKST (instrukcja i PONAD mówią, że przywrócenie trwa do następnej próby automatycznej, a droga ratunku to
„Wyślij ponownie” po naprawie poczty). **Dług:** kompensacja w cronie (zdjęcie tokenu/terminu/licznika przed
krokiem 4, przywrócenie w gałęzi porażki) + część 4 strażnika `resend-odnawia-limit-pobran-test.php`
(cron z `pre_wp_mail=false` → stary token żyje). Własny PR po oddaniu — zmiana zachowania silnika wysyłki.

## 🟡 SEKCJA F MIERZY PIĘĆ Z DZIEWIĘCIU LICZB INSTRUKCJI + ZRZUTY Z OBCYM MENU (30.08, recenzja PR instrukcji r2, WAŻNA 3 + DROBNA 5)

`testy/dokumentacja-zgodnosc-sklep.sh` §F pilnuje 72 h / 10 pobrań / 30 dni / 20 MB / co 10 min; NIE mierzy
90 dni retencji, progu 7 prób logowania, 3 prób maila, minuty odmowy i „pięciu stron” menu. Nazwa bramki
zawężona do tego, co mierzy (klasa 11). Dwie z niemierzonych liczb były nieprawdziwe w tekście i przeszły
z EXIT=0 — naprawione w tekście ręcznie. **Dług:** cztery asercje (`90`, `6` w `sklepag_rate_limit`, `3`, `60`)
+ przypadki do próby kontrolnej — po oddaniu, wzorzec `stala`/`grep -oP` gotowy w §F.
Osobno: pięć PNG w paczce pokazuje menu z **ChatBot LIVE** i **Segregator maila** oraz plakietkę Plugins ① —
u klienta z samym sklepem tych pozycji nie będzie. Nie blokuje; przy następnym `testy/zrzuty-panelu.js`
robić zrzuty na stosie z JEDNĄ wtyczką.

## 🟡 ZRZUTY PNG W PACZCE SĄ POZA SKANEREM ŚLADÓW (30.08, recenzja PR o instrukcji sklepu, WAŻNA)

`narzedzia-qa/paczka-zgodnosc.sh` melduje „w 66 plikach paczki nie ma śladów wewnętrznych”, ale pięć
z nich to PNG — skaner tekstowy nie czyta obrazów, więc nazwa kontroli jest szersza niż jej zasięg
(klasa 5 i klasa 11 naraz). Zrzuty z 30.08 były robione z widocznym kontem `audytor-admin`, osią czasu
z `admin_roboczy` i adresem sieci dockera; naprawione w `testy/zrzuty-panelu.js` (pasek admina bez
tożsamości, front w niezalogowanym kontekście, kadr bez osi czasu) i sprawdzone OCZAMI.
**Dług:** jedyną kontrolą obrazów jest człowiek. Ewentualny automat: OCR (tesseract) na PNG z paczki
z tą samą listą wzorców co skaner — po oddaniu, jeśli zrzutów przybędzie.
