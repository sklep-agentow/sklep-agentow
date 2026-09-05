# Historia zmian

Wszystkie istotne zmiany w tej wtyczce są zapisywane w tym pliku.

Format według [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie według [SemVer](https://semver.org/lang/pl/).

## [Niewydane]

Nic jeszcze.

---

## [1.3.8] — 2026-09-05 · schemat bazy bez zmian (4)

### Dodane
- **Eksporter i kasownik danych osobowych** dla narzędzi prywatności WordPressa (Narzędzia → Eksport / Usuń dane
  osobowe): klucz osoby = adres nadawcy; wiadomości z próbki znikają w całości, w dzienniku ruchów i w raportach
  przebiegów (kolumna z wynikami, widoczna w panelu i w eksporcie CSV) zostaje ślad bez nadawcy i tematu.
  Sklep i ChatBot miały to od dawna, Segregator — trzymający cudzą korespondencję w trzech tabelach — nie.
- Podpowiedź do Polityki prywatności (co przetwarzamy, dokąd idzie w trybie AI, jak złożyć wniosek).

---

## [1.3.7] — 2026-09-05 · schemat bazy bez zmian (4)

### Naprawione
- **Reguły „treść zawiera" działają na zwykłej poczcie, nie tylko na próbce demo.**
  Wiadomość wieloczęściowa (czyli każda sformatowana poczta z Gmaila, Outlooka
  i Thunderbirda) jest teraz rozbierana na części: do reguły idzie część tekstowa
  rozkodowana JEJ WŁASNYM `Content-Transfer-Encoding` i `charset`, a granice
  i nagłówki części nie trafiają już do dopasowania. Wcześniej reguła widziała
  surowe MIME, więc milczała na poczcie, w której właściciel czyta swoje słowo.
- **Krótki mail zakodowany base64 nie znika.** Ostatnia linia nie jest już odcinana
  bezwarunkowo — odrzucamy wyłącznie niepełną końcówkę, gdy treść naprawdę urwano.
- **Nazwa zestawu znaków od nadawcy nie zamienia treści w śmieć.** `charset=BASE64`
  i podobne kodowania transportowe nie są już traktowane jak zestaw znaków.
- **Znak `<` w zdaniu nie zjada reszty treści.** „termin <piątek" zostaje w całości;
  znaczniki zdejmujemy tylko z części HTML i tylko te, które naprawdę się domykają.
- **Załącznik tekstowy nie udaje treści listu.** Część oznaczona `Content-Disposition: attachment`
  (plik .txt, .csv, .ics) jest pomijana przy wyborze tekstu, nawet gdy jest `text/plain` — reguła
  czyta treść, którą widzi człowiek, a nie zawartość załącznika.
- **Wiadomość wieloczęściowa bez `boundary`** (wadliwa albo spreparowana) nie oddaje regule surowego MIME.
- Nazwy zestawów znaków `Shift_JIS` i `GB2312` (tak, jak podają je nadawcy) są rozpoznawane obok `SJIS` i `GB18030`.

---

## [1.3.6] — 2026-08-29

### Zmienione
- **Wydanie porządkowe.** Z komentarzy w kodzie i z dokumentacji technicznej usunięte
  wewnętrzne oznaczenia naszych raportów z testów — komentarz niesie powód, dla którego
  kod tak wygląda, a nie numer dokumentu. Zero zmian w działaniu segregatora.

---

## [1.3.5] — 2026-08-28

### Zmienione
- **Wydanie porządkowe.** Zgodność z WordPress 7.1 podana w opisie wtyczki,
  drobne porządki w komentarzach kodu. Zero zmian w działaniu segregatora.

## [1.3.4] — 2026-08-25

### Zmienione
- **readme mówi pełne wymagania prawdziwej skrzynki**: `stream_socket_client`
  + OpenSSL (diagnostyka wtyczki sprawdza oba), oraz wprost: Microsoft 365
  i Outlook.com nieobsługiwane (OAuth). Zero zmian w kodzie.

## [1.3.3] — 2026-08-24

### Dodane
- **Instrukcja z 6 zrzutami ekranu** (próbka, przebiegi z raportem, reguły, kategorie,
  połączenie IMAP, ustawienia) — robionymi maszynowo. Zero zmian w kodzie wtyczki.

---

## [1.3.2] — 2026-08-24 · schemat bazy 3 → 4

### Naprawione
- **Przebieg na żywo zapisuje zamiar PRZED ruchem poczty**: wiersz przebiegu
  i pozycja „w toku" trafiają do bazy, zanim poleci `UID MOVE`; potwierdzenie od serwera
  zmienia ją na „przeniesiono". Gdy baza odmówi zapisu, wiadomość NIE jest ruszana,
  przebieg przerywa się z widocznym błędem, a `przeniesionych` liczy tylko potwierdzone
  ruchy. Wcześniej przy padzie bazy poczta była rozłożona po folderach, historia pusta,
  cofanie martwe, a panel pokazywał sukces.
- **Cofnięcie liczy tylko to, co naprawdę wróciło**: klient IMAP uznaje ruch za
  udany po niepustym `COPYUID` (na serwerze bez UIDPLUS — po porównaniu stanu folderu),
  bo `UID MOVE` nieistniejącego UID-a kończy się „OK" (RFC 3501 §6.4.8). Wiadomość
  przeciągnięta w międzyczasie ręcznie dostaje błąd przy pozycji, przebieg nie jest
  oznaczany jako cofnięty w całości i drugą próbę da się zrobić.
- **UIDVALIDITY folderu docelowego zapisywana przy przenoszeniu i sprawdzana przy
  cofaniu**: folder skasowany i założony na nowo nie podsunie obcej wiadomości
  o tym samym numerze — wtyczka szuka wtedy po `Message-ID`. Schemat bazy 3 → 4
  (kolumna `segm_skrz_ruchy.uidvalidity_do`, podniesienie automatyczne).
- **Zamek operacji atomowy**: `segm_zajmij_blokade()` zajmuje znacznik jednym
  `INSERT IGNORE` i liczy wiersze, zamiast `get_option()`+`add_option()` (które nadpisywało
  cudzy wpis i wpuszczało dwie operacje naraz); stan czyta prosto z bazy. Znacznik
  w obcym kształcie (bez terminu) nie blokuje już wtyczki na zawsze.
- **Zapis klucza API ma wynik**: `segm_zapisz_klucz()` zwraca `bool` zmierzony
  odczytem po zapisie; gdy baza odrzuci zapis, panel mówi o niezapisanym kluczu zamiast
  „Zapisano" (wcześniej „Przetestuj klucz" testował stary klucz i dawał zielone
  potwierdzenie). Znacznik awarii trybu AI, wersja schematu — zapis mierzony, porażka
  zostawia ślad błędu bazy.
- **Puste pole liczbowe nie zjeżdża po cichu do 1**: port i limit skrzynki oraz
  dzienny limit wywołań AI — pole puste, nie-liczba albo poza zakresem zostawia
  poprzednią wartość, a właściciel dostaje ostrzeżenie zamiast „Zapisano".
- **Ekran „Skrzynka (IMAP)" w pierwszych minutach**: szary przycisk
  „Sprawdź połączenie" mówi teraz, że dane trzeba najpierw ZAPISAĆ, i wymienia brakujące
  pola; opis pola hasła mówi, skąd wziąć hasło aplikacji, i odsyła do sekcji instrukcji
  po tytule; potwierdzenie zapisu pokazuje się jako pierwszy pasek wtyczki (wcześniej
  tonęło jako szósty, pod paskami błędu AI i błędu bazy).
- **Zwolnienie zamka kasuje tylko własny stempel** — proces, którego zamek ktoś przejął
  po przekroczeniu ważności, nie zdejmuje już cudzego, świeżego znacznika.
- **Wiersz przebiegu bez podsumowania ostrzega** zamiast mówić „w toku" w nieskończoność:
  gdy proces zginie albo baza odrzuci także końcowy zapis, komunikat mówi wprost, że
  przebieg nie doszedł do końca, a liczby w wierszu mogą być niepełne.
- **Komunikaty po ludzku i prawdziwe**: pasek trybu AI przy
  BRAKU klucza jest żółtym ostrzeżeniem („nie ma klucza; tryb regułowy jest pełnoprawny;
  klucz bierzesz stąd…"), a nie czerwonym „KLUCZ API ZOSTAŁ ODRZUCONY. Sprawdź, czy jest
  wpisany w całości" o kluczu, którego właściciel nigdy nie wpisywał; tabelka zgodności
  serwera nie pokazuje już „✔ jest" obok opisu „Nieobecne"; błędy połączenia zaczynają się
  zdaniem po polsku („Serwer odrzucił połączenie na tym porcie — nikt tam nie odbiera"),
  a kod systemowy zostaje w części „Szczegóły techniczne"; pole wyboru szyfrowania ma
  etykietę dla czytnika ekranu.
- **Deklaracja prywatności mówi wprost, co wychodzi do dostawcy AI**: pełna lista
  składników żądania (nadawca, temat, treść do 800 znaków, identyfikator wiersza próbki,
  nazwy kategorii, nazwa modelu), lista tego, co NIE wychodzi, informacja o jednym
  ponowieniu żądania oraz o tym, że „Przetestuj klucz" wysyła wyłącznie słowo „ping".
  Powiedziane też wprost — w instrukcji, readme i w panelu — że **na prawdziwej skrzynce
  pracują same reguły** i nie wychodzi z niej do dostawcy nic. Panel nie twierdzi już,
  że „wtyczka nie łączy się z żadną skrzynką pocztową" (nieprawda od 1.3.0).

---

## [1.3.1] — 2026-08-21

### Zmienione

- **Instrukcja opisuje wtyczkę, którą kupujący naprawdę dostaje.** Doszło:
  podłączenie skrzynki krok po kroku (od hasła aplikacji po włączenie
  harmonogramu), liczniki „Pasuje"/„Zgarnia" przy regule i liczenie wzorca bez
  zapisywania, praca na pojedynczym mailu, przeprowadzka reguł plikiem JSON,
  klucz API trzymany stałą w `wp-config.php`, „Przetestuj klucz API", pasek
  „tryb AI nie działa" oraz wybór modelu w Ustawieniach. Z „drogi rozbudowy"
  zniknął wybór modelu — jest w produkcie, więc nie może wisieć jako plan.

### Dodane

- **„Przetestuj klucz API”** w Ustawieniach — jedno wywołanie ze słowem „ping”,
  bez ani jednego maila z próbki, z komunikatem po polsku, co dokładnie odrzucił
  dostawca.
- **Pasek „tryb AI nie działa”** na stronach wtyczki. Przebieg przy błędzie API
  nadal zjeżdża do reguł i kończy się wynikiem, ale właściciel dowiaduje się,
  że od jakiegoś czasu liczą u niego same reguły. Pasek gaśnie sam po pierwszym
  udanym wywołaniu.

### Naprawione

- **Domyślny model AI był migawką z datą** (`claude-haiku-4-5-20251001`).
  Migawki bywają wycofywane, a bez serwera aktualizacji każda sprzedana kopia
  straciłaby wtedy tryb AI tego samego dnia. Domyślny jest teraz alias
  `claude-haiku-4-5`, tak jak w ChatBocie; lista podpowiedzi w Ustawieniach
  również bez dat.
- **Strona *Reguły* odpytywała bazę raz na wiersz zamiast raz na całą tabelę.**
  Licznik „Pasuje"/„Zgarnia" przy każdej regule czytał całą próbkę i całą listę
  reguł osobnym zapytaniem — przy dwudziestu regułach czterdzieści zapytań na
  jedno wyświetlenie strony zamiast dwóch, i rosło to liniowo. Dane są dla
  wszystkich wierszy identyczne, więc pobierane są raz przed pętlą. Liczby
  w kolumnach się nie zmieniają — pilnuje tego osobny test.
- **Listy w panelu urywały się bez słowa i bez odnośnika „dalej”.** Przebiegi
  kończyły się na pięćdziesiątym, próbka pokazywała pierwsze 200 z 500, historia
  skrzynki na pięćdziesiątym — do reszty własnych danych nie było jak dojść.
  Wszystkie trzy listy są teraz stronicowane, po 50 wierszy, z informacją
  „pozycje X–Y z Z”. Odnośniki „Zobacz raport", „Szczegóły" i „Edytuj" niosą
  numer strony, a powrotne „wróć" i „Anuluj edycję" go czytają — powrót nie
  zrzuca już na pierwszą stronę.

### Bezpieczeństwo

- **Klucz API można trzymać poza bazą**: stała `SEGM_KLUCZ_API` w `wp-config.php`
  ma pierwszeństwo nad opcją, a panel jej nie nadpisuje. Dotąd klucz Segregatora
  wychodził z każdą kopią zapasową bazy, choć ChatBot miał tę ochronę od dawna.

## [1.3.0] — 2026-08-20

Wydanie, w którym Segregator przestaje pracować wyłącznie na próbce i podłącza się
do prawdziwej skrzynki. Zbiera zmiany scalone do gałęzi głównej po 1.2.1.

### Dodane

- **Podłączenie prawdziwej skrzynki po IMAP**: serwer, port, szyfrowanie (SSL/TLS,
  STARTTLS albo bez), login i hasło aplikacji, oraz przycisk „Sprawdź połączenie",
  który naprawdę się loguje i podaje liczbę wiadomości w folderze źródłowym.
- **Segregacja na serwerze poczty** według tych samych reguł co na próbce:
  przenoszenie do folderów, zakładanie brakujących folderów wraz z poziomem
  nadrzędnym.
- **Tryb NA SUCHO jako domyślny.** Wtyczka pokazuje, co zrobiłaby, i nie rusza ani
  jednej wiadomości. Realne przenoszenie wymaga dwóch świadomych kroków:
  jednorazowego potwierdzenia (zapisywanego z datą i loginem) oraz przełączenia
  trybu. Samo potwierdzenie niczego nie włącza.
- **Zadanie cykliczne** z częstotliwością do wyboru (15 minut, 30 minut, godzina,
  6 godzin, doba) oraz przycisk „Uruchom teraz".
- **Historia przebiegów skrzynki i cofnięcie ostatniego przebiegu** — co, kiedy
  i dokąd zostało przeniesione, z drogą powrotną do folderu źródłowego.
- **Diagnostyka środowiska** w panelu: pięć pozycji ze stanem i wyjaśnieniem, czego
  brakuje — zamiast białego ekranu przy brakującym rozszerzeniu PHP.
- **Eksport reguł do filtrów Gmaila (XML) i reguł Outlooka** wraz z opisem, czego
  eksport NIE przeniesie.
- **Praca na pojedynczym mailu w próbce**: dodanie, poprawienie i usunięcie
  JEDNEGO maila. Dotąd każda operacja na próbce była operacją na całości (import
  CSV, demo, „wyczyść"), więc sprawdzenie jednego przypadku wymagało przebudowania
  pliku CSV.
- **Licznik trafień przy regule**: przy każdej regule dwie liczby z bieżącej
  próbki — do ilu maili pasuje sam wzorzec i ile reguła realnie zgarnia (bo
  pierwsza pasująca wygrywa). Przycisk „Policz na próbce" robi to samo dla
  wzorca jeszcze niezapisanego. Obie liczby liczy ta sama funkcja co przebieg.
- **Eksport i import własnej konfiguracji** (kategorie + reguły) w JSON-ie.
  Przeprowadzka na inny hosting albo klon strony nie oznacza już wpisywania
  dwudziestu reguł od nowa. W pliku NIE MA haseł, klucza API, próbki maili
  ani zapisanych zgód.
- **Wybór modelu AI w ustawieniach** — dotąd zmiana modelu wymagała edycji PHP
  przez filtr. Lista podpowiedzi plus pole na własną nazwę; filtr `segm_ai_model`
  nadal ma ostatnie słowo.
- **Ekran powitalny po aktywacji** — trzy pierwsze kroki i odnośniki; pokazywany raz,
  z możliwością zamknięcia na stałe (zapamiętywane per użytkownik).
- Dokumentacja techniczna (`DOKUMENTACJA-TECHNICZNA.md`) z mapą dla audytora,
  diagram przepływu (`DIAGRAM-PRZEPLYWU.svg`), ten plik oraz `readme.txt`
  w standardzie WordPressa.
- Pełny tekst licencji GPL-2.0 w pliku `LICENSE`.

### Zmienione

- Opis wtyczki, instrukcja i teksty w panelu przepisane pod produkt sprzedawany,
  bez słowa „testowy" tam, gdzie nie dotyczy ono płatności.
- Nagłówek wtyczki uzupełniony o `License`, `License URI` i `Text Domain`; autor
  ujednolicony na „Automatic AI".
- Silnik reguł, klient IMAP i obsługa przebiegu ładują się także poza panelem —
  bez tego zadanie cykliczne (WP-Cron chodzi poza `is_admin()`) nie miałoby ich
  do dyspozycji.
- Wersja schematu bazy 2 → 3: doszły tabele `segm_skrz_przebiegi` i `segm_skrz_ruchy`.

### Naprawione

- **Żaden zapis do bazy nie udaje już sukcesu.** Wynik każdego `insert`/`update`/
  `query` jest sprawdzany; porażka kończy się komunikatem błędu, nie „Zapisano".
- **Import CSV wykrywa kodowanie pliku** — plik zapisany z polskiego Excela
  (Windows-1250) nie wchodzi już jako krzaki na polskich znakach.
- **Import przerywa czytanie po przekroczeniu limitu wierszy** zamiast mielić cały
  plik do końca; doszedł limit rozmiaru pliku i sprawdzenie typu.
- **Wyścig przy czyszczeniu próbki**: przebieg policzony na danych zmienianych
  w trakcie jest teraz blokowany znacznikiem i zgłaszany, zamiast po cichu dawać
  zły wynik.
- **Cofanie przebiegu skrzynki pilnuje, że cofa się WYŁĄCZNIE ostatni przebieg** —
  decyduje stan bazy, nie identyfikator przysłany w formularzu.
- Nieudane ponowne otwarcie folderu źródłowego po założeniu folderu docelowego jest
  zgłaszane przy konkretnej wiadomości, zamiast dawać serię niezrozumiałych odmów.

### Bezpieczeństwo

- **Hasło do skrzynki**: wymagane jest hasło aplikacji, nie główne hasło konta.
  Stała `SEGM_IMAP_HASLO` w `wp-config.php` ma pierwszeństwo nad bazą; w bazie
  opcja jest zapisywana bez autoload. Hasło nigdy nie wraca do formularza,
  a w zapisie rozmowy z serwerem jest maskowane (`LOGIN "login" ****`).
- **Odinstalowanie kasuje ZAWSZE**, niezależnie od opcji „usuń wszystkie dane":
  hasło do skrzynki, klucz API, obie zgody, znacznik operacji i transienty panelu
  (wraz z wierszami `_transient_timeout_*`).
- **Zgoda na wysyłkę próbki do dostawcy modelu** — bez niej tryb AI w ogóle nie
  startuje; zapisywana z datą i loginem, odwoływalna.
- **Retencja 90 dni** objęła także historię segregacji skrzynki — trzyma nadawcę
  i temat cudzych wiadomości, czyli tę samą kategorię danych co próbka.
- **Wstrzyknięcie formuły do arkusza**: pola zaczynające się od `=`, `+`, `-`, `@`
  są przy eksporcie CSV poprzedzane apostrofem.
- Weryfikacja certyfikatu serwera poczty jest domyślnie włączona; jej wyłączenie to
  osobny, opisany przełącznik dla serwerów testowych.

### Znane ograniczenia

- **Microsoft 365 i Outlook.com nie zadziałają** — Microsoft wyłączył tam logowanie
  loginem i hasłem, ich serwer wymaga OAuth2 (sprawdzone: `outlook.office365.com`
  ogłasza `LOGINDISABLED`). Wtyczka wykrywa to i mówi wprost.
- Cofnąć da się wyłącznie ostatni przebieg, nie dowolny wcześniejszy.

## [1.2.1] — 2026-08-19

### Naprawione

- Odinstalowanie kasuje klucz API **zawsze**, a nie tylko przy pełnym czyszczeniu
  danych — poświadczenia to nie są dane użytkowe.

### Zmienione

- Kod obu botów przepuszczony przez WordPress Coding Standards (phpcs).
- Paczki produktowe przebudowane i zweryfikowane na czystej instalacji WordPressa.

## [1.2.0] — 2026-08-18

### Zmienione

- Domyślny model trybu AI: `claude-haiku-4-5-20251001`, z filtrami
  `segm_ai_url` i `segm_ai_model` do podmiany.
- Twardy limit próbki dla trybu AI: 30 maili na przebieg. Większa próbka nie
  uruchamia przebiegu i dostaje jawny komunikat, zamiast urwać się w połowie
  na limicie czasu żądania.

### Naprawione

- Poprawki importu CSV: separator, długości pól, puste wiersze.

## [1.1.0] — 2026-08-18

### Dodane

- Tryb AI: klasyfikacja modelem oraz tryb „porównaj oba", pokazujący różnice
  między regułami a modelem na tej samej próbce.
- Dzienny limit wywołań AI (stop-loss) z licznikiem w bazie.

## [1.0.0] — 2026-08-18

### Dodane

- Silnik regułowy: warunek „pole zawiera wzorzec" → kategoria, kolejność jako
  jedyny mechanizm rozstrzygania (pierwsza pasująca reguła wygrywa), kubeł
  domyślny „Reszta".
- Panel: przebiegi i raporty (pokrycie, accuracy, lista pomyłek), reguły,
  kategorie, próbka maili, ustawienia.
- Import próbki z pliku CSV i eksport raportu przebiegu do CSV.
- Seed demo: 5 kategorii, 8 reguł i 30 polskich maili z etykietami.
