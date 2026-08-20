=== ChatBot LIVE ===
Contributors: automaticai
Tags: chat, chatbot, faq, kontakt, wsparcie
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.5.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bot na stronę firmową: odpowiada 24/7 na powtarzalne pytania z bazy Q&A, a gdy nie zna odpowiedzi — zbiera kontakt.

== Description ==

ChatBot LIVE odciąża właściciela strony od odpisywania po raz setny na te same
pytania: o cennik, godziny otwarcia, dojazd, terminy, faktury. Bot odpowiada
z **Twojej** bazy pytań i odpowiedzi — nie zgaduje i nie wymyśla.

Gdy pytania nie zna, mówi to wprost i **zbiera kontakt**, żebyś odezwał się
osobiście. Uczciwość jest tu zasadą, nie ozdobą: gość nigdy nie dostaje
zmyślonej odpowiedzi zamiast przyznania się do niewiedzy.

**Co dostajesz po instalacji**

* Widget czatu na stronie (bąbelek + okno) ze stałym przyciskiem
  „zostaw wiadomość" — gość zawsze ma ścieżkę do człowieka.
* Bazę 10 gotowych par pytanie-odpowiedź typowej polskiej firmy usługowej —
  bot odpowiada od pierwszej minuty, a Ty podmieniasz treści na swoje.
* Panel: baza Q&A, zgłoszenia z czatu, historia rozmów, ustawienia.
* **Statystyki i luki** — ile pytań przyszło, na ile bot odpowiedział, jaki to
  procent, ile rozmów skończyło się zebraniem kontaktu. Najważniejsze: lista
  pytań, na które bot **nie znał odpowiedzi**, pogrupowana w tematy, z przyciskiem
  „Dopisz odpowiedź" przy każdym. Bot sam pokazuje, jak go ulepszyć.
* Test bota w panelu — sprawdzasz odpowiedź bez wychodzenia na stronę i widzisz,
  **która para** zadziałała.
* Eksport zgłoszeń i rozmów do CSV (z BOM UTF-8, otwiera się poprawnie w Excelu).

**Tryb AI (opcjonalny)**

Z własnym kluczem API odpowiedzi generuje model językowy — wyłącznie na
podstawie Twojego opisu firmy i aktywnej bazy Q&A, z zakazem zgadywania.
Bez klucza, po wyczerpaniu dobowego limitu albo przy błędzie API bot działa
w pełnoprawnym trybie regułowym. Treść rozmów w trybie regułowym **nie opuszcza
Twojego WordPressa**.

**Prywatność**

Formularz kontaktu ma wymagany checkbox zgody na przetwarzanie danych,
sprawdzany także po stronie serwera — zgłoszenie bez zgody nie trafia do bazy.
Historia rozmów jest kasowana automatycznie po 90 dniach. Adresy IP nie są
przechowywane jawnie (licznik limitów trzyma tylko ich skrót).

Sprawdzone na WordPressie 7.0.4 i PHP 8.4; deklarowane minimum to WordPress 6.0
i PHP 8.1.

== Installation ==

1. W panelu WordPressa wejdź w **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
2. Wskaż plik `chatbot-live.zip`, kliknij **Zainstaluj**, potem **Włącz**.
3. Po włączeniu zobaczysz ekran powitalny z trzema pierwszymi krokami.
4. Wejdź w **ChatBot LIVE → Ustawienia** i podmień tekst powitania oraz tekst
   awaryjny na własne, podaj adres e-mail powiadomień.
5. Wejdź w **ChatBot LIVE → Baza Q&A** i podmień 10 przykładowych wpisów na
   swoje treści (ceny, godziny, adres, terminy).
6. Wejdź na swoją stronę i przetestuj bota na kilku pytaniach.

Pełna instrukcja po polsku jest w pliku `INSTRUKCJA.md` w paczce, a opis
techniczny (schemat bazy, stałe, punkty wejścia) w `DOKUMENTACJA-TECHNICZNA.md`.

== Frequently Asked Questions ==

= Bot nie łapie pytania, choć wpis jest w bazie. Co zrobić? =

Dopisz do wpisu więcej wariantów słów kluczowych. Dopasowanie szuka fragmentów
tekstu, więc warto wpisywać rdzenie: `koszt` łapie „koszt", „koszty"
i „kosztuje", a całe „kosztuje" nie złapie „koszt". Dodaj też formy bez polskich
znaków — `platno` obok `płatno`.

= Skąd mam wiedzieć, czego dopisać do bazy? =

Nie zgaduj — wejdź w **ChatBot LIVE → Statystyki i luki**. Tabela „Czego botowi
brakuje" pokazuje prawdziwe pytania Twoich klientów, na które bot nie umiał
odpowiedzieć, uszeregowane od najczęstszych. Przy każdym jest przycisk, który
otwiera dodawanie pary z już wpisanym pytaniem.

= Czy gość może zalać mnie wiadomościami? =

Nie. Limit to 10 wiadomości na minutę z jednego adresu IP, wspólny dla pytań
i zgłoszeń. Pytanie ma maksymalnie 500 znaków, wiadomość zgłoszenia 2000.

= Czy muszę mieć klucz API, żeby bot działał? =

Nie. Bez klucza bot działa w pełnoprawnym trybie regułowym na Twojej bazie Q&A.
Klucz włącza tryb AI, który jest dodatkiem, nie warunkiem działania.

= Skąd mam wiedzieć, czy tryb AI naprawdę działa? =

Wejdź w **Ustawienia → Tryb AI** i kliknij **„Przetestuj klucz"** — wtyczka wykona
jedno tanie wywołanie i powie wprost, czy klucz działa. Gdyby API zaczęło odrzucać
żądania (zły albo wygasły klucz), w panelu pojawi się czerwony pasek; bot działa
wtedy dalej, ale wyłącznie w trybie regułowym.

= Ile kosztuje mnie tryb AI? =

Tyle, ile wykorzystasz u dostawcy modelu. Wtyczka ma stop-loss: ustawiasz dobowy
limit wywołań (domyślnie 100), a po jego wyczerpaniu bot do północy odpowiada
z reguł. Osobno działa twardszy limit 20 wywołań na dobę z jednego adresu IP.

= Czy dane rozmów wychodzą poza mój serwer? =

W trybie regułowym nie — dopasowanie działa lokalnie. W trybie AI treść pytania
wraz z opisem firmy i bazą Q&A idzie do API dostawcy modelu; napisz o tym
w swojej polityce prywatności.

= Gościom nie działa czat, a w panelu jest czerwone ostrzeżenie o zabezpieczeniu. =

Twoja strona jest podawana gościom z pamięci podręcznej (cache) starszej niż
ważność zabezpieczenia formularza. Wtyczka radzi sobie z tym sama — odświeża
zabezpieczenie i powtarza pytanie — ale jeśli liczba w ostrzeżeniu rośnie,
**wyłącz cache dla strony, na której stoi czat**, albo skróć czas życia cache.

= Statystyki pokazują kreskę zamiast procentu skuteczności. Dlaczego? =

W wybranym okresie nie ma jeszcze ani jednej zmierzonej odpowiedzi — świeża
instalacja albo same rozmowy sprzed aktualizacji do 1.3.0. Kreska, a nie „0%",
bo „nie mierzono" to co innego niż „bot nie odpowiedział ani razu".

= Co się stanie z danymi, gdy odinstaluję wtyczkę? =

Domyślnie **zostają** — możesz odinstalować i wrócić bez strat. Pełne czyszczenie
(baza Q&A, zgłoszenia, historia, ustawienia) włączasz przełącznikiem
w ustawieniach. Klucz API jest kasowany zawsze, niezależnie od tego przełącznika.

== Changelog ==

Pełna historia zmian, z opisem każdej pozycji, jest w pliku `CHANGELOG.md`
w paczce. Poniżej skrót.

= 1.5.2 =
* Nowość: wnioski RODO obsłużysz **wbudowanym kreatorem WordPressa** — wtyczka
  wydaje zgłoszenia z czatu i powiązaną historię rozmowy, a na żądanie je kasuje.
* Gotowy akapit do polityki prywatności.

= 1.4.9 =
* **Tryb AI pamięta bieżącą rozmowę.** Gość może dopytywać („jak dojechać" →
  „gdzie zaparkować") i bot wie, o czym mowa. Pamięć obejmuje ostatnie 6 wymian
  — celowo ucięta, bo każda wymiana to tokeny na Twoim rachunku.

= 1.4.8 =
* **Widać, że bot pisze.** Na czas oczekiwania pole i przycisk są zablokowane,
  a w rozmowie pojawia się dymek z kropkami — gość nie klika drugi raz sądząc,
  że czat się zawiesił (każde takie kliknięcie to kolejne płatne wywołanie AI).

= 1.4.6 =
* **Dobowy limit tokenów** — drugi stop-loss obok limitu wywołań. Rachunek
  u dostawcy idzie z tokenów, a przy dużej bazie Q&A jedno pytanie potrafi
  kosztować wielokrotność innego.
* Zużycie tokenów widoczne w Statystykach i w Ustawieniach.

= 1.4.5 =
* Gość widzi informację o zapisie rozmowy **na starcie czatu**, a nie dopiero przy
  formularzu kontaktu — wraz z odnośnikiem do Twojej polityki prywatności.

= 1.4.4 =
* **Wybór modelu w ustawieniach** (lista + własny identyfikator) — do tej pory
  wymagało to pisania filtru w kodzie.
* Domyślny model to alias bez daty, więc wycofanie konkretnej migawki nie wyłącza
  trybu AI.

= 1.4.3 =
* Naprawione: za Cloudflare albo proxy hostingu limity działały na **całą witrynę
  naraz** zamiast na gościa. Nowe ustawienie „Strona za Cloudflare albo proxy"
  (domyślnie wyłączone — włącz tylko, gdy naprawdę tak jest).

= 1.4.2 =
* Naprawione: zły albo wygasły klucz API oznaczał, że bot **po cichu** odpowiada
  wyłącznie z reguł, a właściciel nie miał jak tego zauważyć. Panel pokazuje teraz
  czerwony pasek, a w ustawieniach jest przycisk **„Przetestuj klucz"**.
= 1.4.1 =
* Naprawione: na stronie z cache (LiteSpeed, WP Rocket, W3TC, Cloudflare) czat mógł
  przestać działać **wszystkim gościom naraz**, a właściciel nie miał jak tego
  zauważyć. Widget sam odświeża zabezpieczenie i powtarza pytanie, a panel pokazuje
  ostrzeżenie, jeśli problem się powtarza.

= 1.4.0 =
* **Konfigurowalny wygląd widgetu**: kolor główny, kolor paska nagłówka, kolor
  tekstu na nich i róg strony (prawy albo lewy dolny).
* Podgląd na żywo w panelu i **kontrola kontrastu WCAG 2.1 AA** — wtyczka mówi
  wprost, czy wybrany zestaw jest czytelny, a domyślnie dobiera kolor tekstu sama.
* Ekran powitalny po aktywacji z trzema pierwszymi krokami; zamknięty nie wraca,
  osobno dla każdego konta administratora.
* Pliki `CHANGELOG.md` i `readme.txt` w paczce.
* Domyślny wygląd bez zmian — aktualizacja nie rusza nikomu kolorów.

= 1.3.0 =
* Nowa strona **Statystyki i luki**: skuteczność bota w okresie, zebrane kontakty,
  ruch dzień po dniu.
* **Raport luk** — pytania bez odpowiedzi pogrupowane w tematy, z przyciskiem
  „Dopisz odpowiedź" otwierającym gotowy formularz nowej pary Q&A.
* Schemat bazy 3 → 4 (kolumna `trafiono` i indeks `luki` w historii rozmów).
  Rozmowy sprzed tej wersji nie mają znacznika trafienia i nie są zgadywane wstecz.

= 1.2.5 =
* Dokumentacja techniczna, diagram przepływu i pełny tekst licencji GPL w paczce.

= 1.2.3 =
* Reguła dopasowania ma jedno źródło — panel testowy pokazuje dokładnie to,
  co policzył silnik.

= 1.2.2 =
* Naprawiony cichy ubytek danych: nieudany zapis nie kończy się już
  „Dziękujemy" dla gościa.
* Wymagana zgoda na przetwarzanie danych w formularzu kontaktu, sprawdzana
  po stronie serwera.
* Eksport zgłoszeń i rozmów do CSV.

= 1.2.1 =
* Klucz API kasowany przy odinstalowaniu zawsze, niezależnie od ustawień.

= 1.2.0 =
* Licznik limitów przeniesiony na atomowy zapis w tabeli — przestał przepuszczać
  żądania ponad próg.

= 1.1.0 =
* Tryb AI (hybryda) ze stop-lossem i zjazdem do reguł przy każdym błędzie.

= 1.0.1 =
* Limit 10 wiadomości na minutę z jednego IP, poprawki dostępności (WCAG).

= 1.0.0 =
* Pierwsze wydanie.
