# Segregator maila — instrukcja użytkownika

Testowy bot porządkujący skrzynkę firmową: rozdziela maile do kategorii według
reguł (jak filtry Gmaila) i pokazuje raport z liczbami — ile roboty zrobiłby
za Ciebie.

**Uczciwie:** wtyczka działa na PRÓBKACH maili (wbudowane demo albo Twój
eksport CSV), nie podpina się do żywej skrzynki — dlatego nazywa się „testowy
bot". Testujesz reguły na próbce ZANIM cokolwiek dotknie prawdziwej poczty
(wzorzec trybu testowego z reguł Exchange). Podpięcie skrzynki (IMAP) to droga
rozbudowy po zakupie.

## 1. Instalacja (2 minuty)

1. **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer** → wskaż
   `segregator-maila.zip` → **Zainstaluj** → **Włącz**.
2. Gotowe — od razu masz: 5 kategorii (Oferty, Faktury, Reklamacje,
   Spam-podejrzane, Reszta), 8 przykładowych reguł i próbkę 30 polskich maili
   demo z etykietami.

Wymagania: WordPress 6.0+ i PHP 8.1+.

## 2. Pierwszy przebieg (1 minuta)

1. W menu bocznym: **Segregator maila → Przebiegi**.
2. Kliknij **„Przepuść próbkę"** (tryb „reguły").
3. Dostajesz raport: mail → kategoria → która reguła zadziałała, trafienia
   per kategoria, **% pokrycia** (ile skrzynki automat rozdziela sam) oraz —
   bo próbka demo ma etykiety — **ACCURACY** (% trafień) z listą pomyłek.
   Raport możesz pobrać jako CSV.

## 3. Reguły pod własną firmę w ~20 minut

1. **Kategorie** → dodaj/zmień kategorie pod swoją skrzynkę.
2. **Reguły** → każda reguła to: *warunek* (nadawca / temat / treść ZAWIERA
   któryś z wariantów po przecinku, np. `faktur, rachunek`) → *kategoria*.
   **Kolejność na liście rozstrzyga: pierwsza pasująca reguła wygrywa** —
   przesuwaj strzałkami ▲▼. Reguły szczegółowe dawaj wyżej, ogólne niżej.
3. **Próbka maili** → wgraj własny eksport z CSV (kolumny: `od, temat, tresc`,
   opcjonalnie `kategoria_oczekiwana`; maks. 500 wierszy; import zastępuje
   próbkę — przycisk obok przywraca demo). Jeśli dodasz kolumnę
   `kategoria_oczekiwana` z ręcznie przypisanymi kategoriami, raport policzy
   accuracy i pokaże, GDZIE reguły się mylą.
4. Kręć pętlę: przebieg → zobacz pomyłki → popraw reguły → przebieg. Mail bez
   żadnej reguły trafia do kubła **„Reszta"** (jak mail zostający w skrzynce
   głównej Gmaila).

## 4. Tryb AI (opcjonalny — hybryda)

Z własnym kluczem API (Anthropic, console.anthropic.com) klasyfikację może
robić prawdziwy model — a najciekawszy jest tryb **„porównaj oba"**:

- raport pokazuje różnice per mail (reguły vs AI), liczbę zgodnych/różnych
  oraz accuracy OBU silników — widać czarno na białym, co AI łapie ponad
  Twoje reguły (np. niuans w treści bez słowa kluczowego),
- model dostaje listę TWOICH kategorii i ma niepewne maile odkładać do „Reszta",
- **limit próbki w trybach AI: 30 maili** (jedno wywołanie API na przebieg —
  tak dobrane, żeby przebieg zawsze mieścił się w limicie czasu żądania
  typowego hostingu); większa próbka = czytelny komunikat i podpowiedź:
  zmniejsz próbkę albo użyj trybu regułowego (ten limitu nie ma),
- **stop-loss:** jedna partia do 30 maili = 1 wywołanie API; dzienny limit
  wywołań (domyślnie 100, miękki — równoległe wywołania mogą go minimalnie
  przekroczyć) z licznikiem w ustawieniach; przy błędzie API, braku klucza
  albo wyczerpanym limicie przebieg automatycznie wykonuje się regułami
  (z komunikatem).

Koszty API ponosi właściciel klucza według cennika dostawcy.

## 5. Eksport filtrów do prawdziwej skrzynki

Kiedy reguły dają na próbce wynik, który Cię zadowala, przenieś je tam, gdzie
naprawdę stoi poczta: **Segregator maila → Eksport filtrów**.

Do wyboru trzy pliki:

- **Gmail — plik XML do zaimportowania.** W Gmailu: *Ustawienia → Zobacz
  wszystkie ustawienia → Filtry i zablokowane adresy → Importuj filtry*.
- **Gmail — zapytania do wklejenia (CSV).** Ta sama treść, ale do ręcznego
  sprawdzenia: wklejasz zapytanie w wyszukiwarkę Gmaila i widzisz, co złapie,
  ZANIM cokolwiek zaimportujesz. Zalecane pierwsze podejście.
- **Outlook — instrukcja krok po kroku (TXT).** Outlook nie przyjmuje reguł
  z zewnętrznego pliku (jego własny format `.rwz` otwiera wyłącznie klasyczny
  Outlook, a w wersji przeglądarkowej import w ogóle nie działa), więc
  dostajesz listę do odklikania — w naszej kolejności.

### Czego eksport NIE przeniesie — przeczytaj, zanim zaimportujesz

1. **Kubeł „Reszta" nie jest eksportowalny.** To nie reguła, tylko „żadna
   reguła nie pasowała", a takiego warunku nie ma ani Gmail, ani Outlook.
   Skutek w skrzynce wychodzi jednak ten sam: mail, którego nie złapie żaden
   filtr, zostaje w Skrzynce odbiorczej.
2. **Kolejność.** Nasz silnik zatrzymuje się na PIERWSZEJ pasującej regule;
   Gmail czegoś takiego nie ma. Dlatego w eksporcie **każdy filtr dostaje
   dodatkowy warunek wykluczający wszystkie wcześniejsze reguły** — dzięki temu
   mail wpada najwyżej do jednej kategorii, niezależnie od kolejności
   stosowania filtrów. W **Outlooku** odwzorowanie jest pełne: zaznacz
   w każdej regule **„Zatrzymaj przetwarzanie kolejnych reguł"**.
3. **Fragment kontra całe słowo.** My szukamy fragmentu (`faktur` łapie
   „faktury"), wyszukiwarka skrzynki operuje na słowach. To największe źródło
   różnic — wzorce urwane w połowie słowa dopisz do pełnych form.
4. **Accuracy zostaje u nas.** Skrzynka nie zna kolumny `kategoria_oczekiwana`.

Te same ostrzeżenia są wpisane w KAŻDY wygenerowany plik, żeby nie zgubiły się
po drodze do osoby, która będzie klikać w skrzynce.

## 6. FAQ

**Import CSV nie wchodzi.**
Plik musi mieć kolumny w kolejności `od, temat, tresc[, kategoria_oczekiwana]`,
rozdzielane przecinkami albo średnikami (separator wykrywany automatycznie —
polski Excel przy „Zapisz jako CSV" daje średniki i to działa); pierwszy wiersz
może być nagłówkiem. Gdy wiersz danych ma mniej niż 3 kolumny, cały import jest
odrzucany z komunikatem (próbka zostaje nietknięta). Limit: 500 wierszy
(nadmiar jest pomijany i raportowany).

**Skąd wziąć CSV z mojej skrzynki?**
Większość klientów poczty ma eksport (np. Thunderbird z dodatkiem
ImportExportTools). Wystarczą trzy kolumny — bez załączników.

**Dlaczego mail wpadł do złej kategorii?**
Zobacz w raporcie, KTÓRA reguła go złapała. Najczęściej pomaga: przesunięcie
bardziej szczegółowej reguły wyżej albo doprecyzowanie wariantów.

**Czy accuracy 100% na demo to ściema?**
Nie — reguły demo są po prostu dopasowane do próbki demo (tak wygląda dobrze
skonfigurowany segregator). Na TWOICH mailach zaczniesz niżej i podniesiesz
wynik pętlą z punktu 3.

**Co z danymi po odinstalowaniu?**
Klucz API jest kasowany przy KAŻDYM odinstalowaniu (wtyczka nie zostawia
Twojego sekretu w bazie; po ponownej instalacji wpisz go na nowo). Reszta
domyślnie zostaje. Z opcją „usuń wszystkie dane" kasowane są też kategorie,
reguły, próbka, przebiegi i ustawienia.

## 7. Droga rozbudowy (po zakupie, na życzenie)

- podpięcie prawdziwej skrzynki (IMAP/OAuth) i segregacja na żywo — krok po
  kroku: konto techniczne → pobieranie nagłówków → te same reguły → etykiety,
- reguły z wykluczeniami („zawiera X, ale nie Y"),
- harmonogram automatycznych przebiegów,
- inne modele AI / własny proxy (filtry `segm_ai_url` i `segm_ai_model`).

## 8. Deklaracja prywatności (ważne — RODO)

- **Tryb regułowy:** treść maili NIE opuszcza Twojego WordPressa — segregacja
  działa lokalnie, próbka leży w Twojej bazie danych.
- **Tryby AI:** treść maili z próbki (nadawca, temat, treść) jest wysyłana do
  API dostawcy modelu (Anthropic) w celu klasyfikacji.
- Jeśli wgrywasz próbkę z PRAWDZIWYMI mailami klientów, to są dane osobowe —
  **dopisz przetwarzanie (i ewentualnego dostawcę AI) do swojej polityki
  prywatności**; do testów reguł często wystarczy próbka zanonimizowana.
