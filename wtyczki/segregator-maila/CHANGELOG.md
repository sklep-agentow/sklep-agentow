# Historia zmian

Wszystkie istotne zmiany w tej wtyczce są zapisywane w tym pliku.

Format według [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie według [SemVer](https://semver.org/lang/pl/).

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

- Poprawki importu CSV z recenzji: separator, długości pól, puste wiersze.

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
