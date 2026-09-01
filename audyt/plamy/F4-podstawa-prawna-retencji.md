# F4 — dodatek · PODSTAWA PRAWNA RETENCJI DANYCH DO FAKTURY
### Czy „Anonimizuj kupującego" ma prawo skasować dane z faktury, skoro polityka trzyma je na obowiązku podatkowym

**Audytor:** sesja audytu białych plam, 2026-09-01 · **Gałąź:** `audyt/biale-plamy-01-09`
**Pytanie od właściciela, przekazane przez koordynatorkę.** Odpowiedź stoi na przepisach
odczytanych z plików, nie z pamięci i nie ze streszczenia pośrednika.

---

## 0. METODA ODCZYTU — do zapisania na przyszłość

⛔ **ISAP nie da się czytać `curl`em.** `https://isap.sejm.gov.pl/isap.nsf/download.xsp/…`
odpowiada **HTTP 302 z nagłówkiem `location` wskazującym na SAMEGO SIEBIE** — czyli pętla
przekierowań. Zmierzone:

```
$ curl -s -D - -o /dev/null -A "Mozilla/5.0" \
    "https://isap.sejm.gov.pl/isap.nsf/download.xsp/WDU20040540535/U/D20040535Lj.pdf"
HTTP/2 302
location: https://isap.sejm.gov.pl/isap.nsf/download.xsp/WDU20040540535/U/D20040535Lj.pdf
```
Z ciasteczkami z `DocDetails.xsp` wychodzi HTTP 200, ale w treści jest **wyzwanie
antybotowe Imperva/Incapsula** (`/_Incapsula_Resource?SWJIYLWA=…`), nie PDF.
`WebFetch` też odbija się z „Too many redirects" — udało mu się przejść **raz**, przy ustawie
o prawach konsumenta (F5), i nie da się na tym polegać.

⭐ **METODA, KTÓRA DZIAŁA POWTARZALNIE — oficjalny wydawca, nie ISAP:**

```bash
# 1. Ustal pozycję Dziennika Ustaw (strona HTML, zwykły curl przechodzi):
curl -sL "https://dziennikustaw.gov.pl/DU/2025/111"     # → tytuł obwieszczenia

# 2. Pobierz PDF. Wzór adresu: D<rok><pozycja na 6 cyfr><01>.pdf
curl -sL -o ordynacja.pdf "https://dziennikustaw.gov.pl/D2025000011101.pdf"

# 3. Wyciągnij tekst z zachowaniem układu kolumn:
pdftotext -enc UTF-8 -layout ordynacja.pdf ordynacja.txt

# 4. Znajdź przepis PO NUMERZE, nie po treści:
grep -n "^ *Art\. 70\." ordynacja.txt
```
`dziennikustaw.gov.pl` to **wydawca urzędowy** — źródło co najmniej tak dobre jak ISAP,
a przechodzi zwykłym `curl`em. ⛔ Zawsze sprawdź nagłówek pobranego PDF-u (`sed -n '1,12p'`):
musi się zgadzać data obwieszczenia i nazwa ustawy, inaczej ściągnąłeś inny akt.

⚠️ **Ograniczenie, którego nie ukrywam:** tekst jednolity jest zamrożony w dniu ogłoszenia.
Ustawa o VAT — t.j. **Dz.U. 2024 poz. 361** (obwieszczenie z 9 lutego 2024, ogłoszone 13.03.2024);
Ordynacja podatkowa — t.j. **Dz.U. 2025 poz. 111** (obwieszczenie z 23 grudnia 2024, ogłoszone
28.01.2025). **Zmian ogłoszonych po tych datach nie weryfikowałem** — przy cytowaniu wobec
klienta trzeba to sprawdzić w ISAP przez przeglądarkę.

---

## 1. Ustawa o podatku od towarów i usług — **art. 112**
*(t.j. Dz.U. 2024 poz. 361, `vat.txt:7806-7808`)*

> „**Art. 112.** Podatnicy są obowiązani przechowywać ewidencje prowadzone dla celów rozliczania
> podatku oraz wszystkie dokumenty, w szczególności faktury, związane z tym rozliczaniem
> **do czasu upływu terminu przedawnienia zobowiązania podatkowego**, z zastrzeżeniem art. 109b
> ust. 7, art. 130d ust. 3, art. 134 ust. 3, art. 138h ust. 3 i art. 138i ust. 8."

⭐ Uwaga na zakres podmiotowy: obowiązek dotyczy **podatnika** i **dokumentów związanych
z jego rozliczaniem** — czyli faktury, którą przedsiębiorca **wystawił**. Nie mówi nic
o danych zebranych „na przyszłość", z których faktury jeszcze nie ma.

## 2. Ordynacja podatkowa — **art. 70 § 1**
*(t.j. Dz.U. 2025 poz. 111, `ordynacja.txt:2771-2772`)*

> „**Art. 70. § 1.** Zobowiązanie podatkowe przedawnia się **z upływem 5 lat, licząc od końca
> roku kalendarzowego, w którym upłynął termin płatności podatku**."

⭐ Czyli okres z art. 112 to **5 lat od końca roku, w którym minął termin płatności podatku** —
w praktyce dla sprzedaży z 2026 r. koniec 2031 r. **To jest liczba, której szablon polityki
prywatności nie podaje** (patrz `F4-F1` w raporcie głównym).

## 3. Ustawa o VAT — **art. 106e ust. 1**, w zakresie danych nabywcy
*(t.j. Dz.U. 2024 poz. 361, `vat.txt:6179-6185`)*

> „**Art. 106e. 1.** Faktura powinna zawierać:
> 1) datę wystawienia;
> 2) kolejny numer nadany w ramach jednej lub więcej serii, który w sposób jednoznaczny
> identyfikuje fakturę;
> **3) imiona i nazwiska lub nazwy podatnika i nabywcy towarów lub usług oraz ich adresy;**
> **4) numer, za pomocą którego podatnik jest zidentyfikowany na potrzeby podatku,
> z zastrzeżeniem pkt 24 lit. a;**
> **5) numer, za pomocą którego nabywca towarów lub usług jest zidentyfikowany na potrzeby
> podatku lub podatku od wartości dodanej, pod którym otrzymał on towary lub usługi,
> z zastrzeżeniem pkt 24 lit. b;** […]"

⭐ To potwierdza, że nazwa, adres i NIP **nabywcy** są obowiązkowym elementem faktury —
czyli po jej wystawieniu żyją w dokumencie objętym art. 112.

## 4. RODO — **art. 17 ust. 3**, zdanie wprowadzające i lit. b)
*(EUR-Lex CELEX `32016R0679`, PDF → `pdftotext`, `rodo.txt:2950-2956`)*

> „**3.** Ust. 1 i 2 nie mają zastosowania, **w zakresie w jakim** przetwarzanie jest niezbędne: […]
> **b)** do wywiązania się z prawnego obowiązku wymagającego przetwarzania na mocy prawa Unii
> lub prawa państwa członkowskiego, któremu podlega administrator, lub do wykonania zadania
> realizowanego w interesie publicznym **lub w ramach sprawowania władzy publicznej powierzonej
> administratorowi**;"

⛔ **DWIE POPRAWKI DO CYTATU PRZEKAZANEGO MI PRZEZ KOORDYNATORKĘ** — obie ważą na odpowiedzi:

1. **Cytat urwany na KOŃCU, nie na początku.** Wersja, którą dostałem, kończy się na
   „lub do wykonania zadania realizowanego w interesie publicznym". W tekście litera b) ma
   jeszcze człon **„lub w ramach sprawowania władzy publicznej powierzonej administratorowi"**.
   Dla naszej sprawy ten człon jest bez znaczenia (sklep nie sprawuje władzy publicznej), ale
   pokazuje, że pośrednik obciął tekst — a przy innym przepisie taki brak zmieniłby sens.
2. ⭐ **Ważniejsza: brakowało zdania wprowadzającego, a ono jest tu najważniejsze.**
   Wyłączenie prawa do usunięcia działa **„w zakresie w jakim przetwarzanie jest niezbędne"** —
   czyli **nie na cały rekord**, tylko na tę część danych i na ten czas, których wymaga
   obowiązek podatkowy. To zdanie rozstrzyga pytanie właściciela, a w przekazanym cytacie go nie było.

---

## 5. ODPOWIEDŹ NA PYTANIE — z pomiarem produktu, nie z rozważań

### Co produkt robi naprawdę (zmierzone)

| # | Fakt | Dowód |
|---|---|---|
| A | Przycisk „Anonimizuj kupującego" **kasuje `dane_faktury`** (nazwa firmy, NIP, adres) razem z adresem e-mail i treścią maila | `admin-zamowienie.php:374`: `UPDATE … SET email_kupujacego='', dane_faktury='', mail_tresc=NULL, usunieto_dnia=%s, …` |
| B | Szablon Polityki prywatności deklaruje dla tych danych **inną podstawę niż dla reszty**: „dobrowolnie podane dane do faktury (nazwa firmy, NIP, adres — cel: wystawienie faktury, **podstawa: obowiązek prawny z ustawy o VAT**)" | `aktywacja.php:192` |
| C | ⭐ **Sklep faktur NIE WYSTAWIA i nie przechowuje** — zbiera tylko dane i pokazuje je właścicielowi | `INSTRUKCJA.md:143-144`: „**Faktura to Twoja robota, nie sklepu.** Sklep faktur nie wystawia — zbiera dane i pokazuje je w mailu i w szczegółach zamówienia". Próba kontrolna: rdzeń `faktur` w `zakup.php` = **27 trafień**, ale `grep` po `wystawia\|generuj.*faktur\|faktura_pdf` w `includes/*.php` → **0** |
| D | Kod zna art. 106e i cytuje go poprawnie przy walidacji kompletu danych | `zakup.php:125`: „faktura dla firmy wymaga nazwy, NIP-u i adresu nabywcy (ustawa o VAT art. 106e ust. 1 pkt 3)" |
| E | Interfejs **mówi wprost**, że kasuje dane do faktury — w opisie i w `confirm()` | `admin-zamowienie.php:571` i `:573` („adres e-mail, **dane do faktury** i treść maila znikną") |

### Rozstrzygnięcie

**Przycisk ma prawo skasować `dane_faktury` — ale nie zawsze, i produkt nie sprawdza, kiedy.**

1. **Obowiązek z art. 112 dotyczy DOKUMENTU, nie kopii danych wejściowych.** Przechowywać
   trzeba „ewidencje […] oraz wszystkie dokumenty, w szczególności faktury, związane z tym
   rozliczaniem". Fakturę wystawia i trzyma **właściciel w swojej księgowości** (fakt C), a nie
   sklep. Kolumna `dane_faktury` jest **kopią danych, które kupujący wpisał w formularzu** —
   nie jest fakturą ani ewidencją VAT.
2. **Dlatego, gdy faktura JUŻ została wystawiona**, skasowanie kopii w sklepie **nie narusza**
   art. 112: dokument objęty obowiązkiem żyje w księgowości przez 5 lat od końca roku, w którym
   minął termin płatności (art. 70 § 1 Ordynacji). RODO art. 17 ust. 3 lit. b nie broni tu
   kopii — bo wyłączenie działa **„w zakresie w jakim przetwarzanie jest NIEZBĘDNE"**,
   a druga kopia w bazie sklepu niezbędna nie jest.
3. ⛔ **Gdy faktura NIE została jeszcze wystawiona, kasowanie niszczy jedyny ślad żądania.**
   Kupujący ma prawo zażądać faktury w ciągu 3 miesięcy (produkt sam to obiecuje w mailu:
   „Chcesz fakturę? Odpisz na tego maila **w ciągu 3 miesięcy** od zakupu"). Po anonimizacji
   właściciel nie ma nazwy, NIP-u ani adresu nabywcy — czyli **nie jest w stanie wystawić
   faktury z obowiązkowymi elementami z art. 106e ust. 1 pkt 3-5**.
4. ⛔ **I to jest sedno znaleziska: produkt nigdzie nie zadaje pytania, które rozstrzyga sprawę** —
   „czy faktura dla tego zamówienia została już wystawiona?". Sklep tego nie wie (nie wystawia
   faktur, fakt C) i nie pyta. `confirm()` ostrzega o nieodwracalności, ale nie o tym, że kasuje
   dane potrzebne do niewystawionej jeszcze faktury.

### Rozjazd, który właściciel wyczuł — nazwany precyzyjnie

To **nie jest** sprzeczność „RODO kontra podatki". To rozjazd **wewnątrz naszych własnych
dokumentów**: polityka prywatności deklaruje dla danych do faktury podstawę
**„obowiązek prawny z ustawy o VAT"** (fakt B) — a taka podstawa oznacza, że dane
są trzymane, **dopóki obowiązek trwa**. Tymczasem panel oferuje ich skasowanie
**jednym kliknięciem, natychmiast, bez żadnego warunku** (fakt A). Osoba czytająca politykę
i osoba klikająca przycisk dostają dwie różne obietnice o tych samych danych.

---

## 6. Znalezisko

### `F4-F4` — **SRED** · „Anonimizuj kupującego" kasuje dane do faktury bez pytania, czy faktura została wystawiona — a polityka deklaruje dla nich obowiązek prawny

**Objaw:** jedno kliknięcie usuwa nazwę firmy, NIP i adres nabywcy. Jeśli faktura nie została
jeszcze wystawiona, właściciel traci komplet danych, których wymaga art. 106e ust. 1 pkt 3-5,
a kupujący ma na jej zażądanie 3 miesiące (obietnica z maila po zakupie).

**Plik i linia:** `admin-zamowienie.php:374` (kasowanie `dane_faktury`) kontra
`aktywacja.php:192` (deklarowana podstawa „obowiązek prawny z ustawy o VAT").

**Skutek DLA KLIENTA:** właściciel sklepu, wykonując żądanie kupującego z art. 17 RODO,
może jednym kliknięciem odebrać sobie możliwość wykonania obowiązku z ustawy o VAT — i dowie
się o tym dopiero, gdy ten sam kupujący poprosi o fakturę. **Awaria cicha.**

**RODZAJ SKUTKU:** `wyliczony` (konkretna kolumna, konkretny przepis, konkretna sytuacja:
anonimizacja przed wystawieniem faktury). Nie odtworzyłem tego na stanowisku.
**Waga: ŚREDNIA.**

**Propozycja naprawy — najtańsza wersja, bez zmiany zachowania:** rozszerzyć `confirm()`
i opis o zdanie: *„Jeśli kupujący podał dane do faktury, a faktura nie została jeszcze wystawiona
— wystaw ją PRZED anonimizacją; po niej nie będzie z czego."* Wersja mocniejsza: przy zamówieniu
z niepustym `dane_faktury` pokazać dodatkowe pytanie „faktura wystawiona? tak/nie" i przy „nie"
wymagać drugiego potwierdzenia. ⛔ **Nie proponuję zatrzymywania danych do faktury na 5 lat** —
to szłoby wbrew „w zakresie w jakim niezbędne" z art. 17 ust. 3 RODO, skoro dokument objęty
obowiązkiem i tak leży w księgowości właściciela.

**Do rozważenia osobno (nie moja rola):** czy zdanie w polityce „podstawa: obowiązek prawny
z ustawy o VAT" jest trafne dla danych, z których faktury jeszcze nie ma. Przed wystawieniem
faktury bliżej im do zgody albo do umowy niż do obowiązku prawnego. **To kwalifikacja prawna —
oddaję ją prawnikowi, nie rozstrzygam.**

---

## 7. Czego NIE sprawdziłem

- **Czy po t.j. z 2024 (VAT) i 2025 (Ordynacja) doszły zmiany do art. 112, 106e i 70 § 1** —
  teksty jednolite są zamrożone w dniu ogłoszenia, a ISAP-a nie dało się odczytać maszynowo.
- **Ustawy o rachunkowości** (art. 74 — 5 lat na dowody księgowe) — właściciel może jej podlegać
  albo nie; nie pytałem, w jakiej formie prowadzi działalność.
- **Czy kupujący dostaje informację o okresie przechowywania danych do faktury** — nie dostaje,
  bo polityka podaje okres wyłącznie dla dziennika zdarzeń (`F4-F1` w raporcie głównym).
- **Zachowania przy zwrocie i korekcie faktury** — korekta też ma obowiązkowe elementy i też
  wymaga danych nabywcy; nie badałem tej ścieżki.
