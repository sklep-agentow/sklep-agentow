=== Sklep Agentów ===
Contributors: automaticai
Tags: sklep, stripe, pliki cyfrowe, sprzedaz wtyczek, pobieranie
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.9.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sprzedaż wtyczek-botów AI ze swojej strony: płatność kartą przez Stripe, mail z linkiem do pobrania, panel zamówień — wszystko w Twoim WordPressie.

== Description ==

Sklep Agentów sprzedaje pliki cyfrowe (wtyczki-boty) prosto ze strony na
WordPressie: lista produktów i strony sprzedażowe → płatność kartą przez Stripe
Checkout → mail z linkiem do pobrania → panel zamówień, statystyk i dziennika
logowań. Zamówienia, produkty i pliki zostają w Twojej bazie i na Twoim
serwerze — bez abonamentu i bez pośredników. Jedyna usługa zewnętrzna to Stripe
(płatność); wtyczka nie ładuje żadnych bibliotek z sieci.

**Co dostajesz po instalacji**

* Strony „Sklep" (lista produktów) i „Dziękujemy za zakup" tworzone
  automatycznie, plus dwa szkice do uzupełnienia własnymi danymi: „Regulamin
  sklepu" i „Polityka prywatności sklepu".
* Strony produktów pod adresem `/sklep/produkt/…/` — dla ChatBota LIVE
  i Segregatora maila z wbudowaną prezentacją sprzedażową (sekcje, galeria,
  pytania i odpowiedzi, opinie kupujących).
* Płatność kartą przez **Stripe Checkout** — dane karty podaje się na stronie
  płatności Stripe, nie na Twoim serwerze.
* Domykanie zakupu dwiema niezależnymi drogami: powiadomienie od Stripe
  (webhook z podpisem) oraz zadanie cykliczne co 10 minut, które dopytuje Stripe
  o opłacone sesje. Każde przejście stanu zamówienia jest atomowe — kupujący
  dostaje dokładnie jeden mail.
* Mail po zakupie: link do pobrania ważny 72 godziny, do 10 pobrań;
  potwierdzenie zgody na dostarczenie treści cyfrowej (dosłowna treść i data);
  krótka instrukcja; informacja o gwarancji, gdy produkt ją ma; jednorazowy
  link do wystawienia opinii.
* Brama pobrań: plik wydawany wyłącznie przez wtyczkę po sprawdzeniu tokenu,
  terminu i limitu — nigdy z publicznego adresu. Pliki produktów leżą
  w katalogu chronionym o losowej nazwie.
* Panel w kokpicie (menu **Sklep Agentów**): **Pulpit** — wskaźniki sprzedaży
  z porównaniem do poprzedniego okresu, rozkład w czasie, tabela zamówień,
  eksport CSV, przycisk „dociągnij zaległe" · **Produkty** — dodawanie
  i edycja, wgrywanie paczki ZIP, włączenie sprzedaży dopiero po potwierdzonym
  pliku · widok **Zamówienia** — szczegóły, treść wysłanego maila, ponowna
  wysyłka ze świeżym linkiem, anonimizacja danych kupującego · **Opinie** —
  moderacja · **Dziennik** — logowania i zdarzenia · **Ustawienia** — klucze
  Stripe, nadawca maili, mail kontaktowy właściciela, waluta, zachowanie danych
  przy odinstalowaniu.
* Opinie kupujących: jedna opinia na zamówienie, wyłącznie z jednorazowego
  linku w mailu (ważnego 30 dni), publikowana dopiero po ręcznej akceptacji
  w panelu. Bez ocen od anonimowych gości i bez zbiorczej średniej.
* Dziennik logowań administratorów: po nieudanych próbach odpowiedź jest
  wykładniczo opóźniana, liczba prób z jednego adresu ograniczona, wpisy
  starsze niż 90 dni usuwane automatycznie.
* SEO stron sklepu: tytuły i opisy, znaczniki Open Graph, dane strukturalne
  (produkt, okruszki, pytania i odpowiedzi), mapa witryny produktów.
* Kopie danych produktu w zamówieniu — późniejsza zmiana ceny czy nazwy nie
  zmienia historii sprzedaży.

**Czego wtyczka NIE robi**

Nie zakłada kont kupującym, nie obsługuje płatności cyklicznych, przecen ani
kuponów, nie dotyka motywu ani innych wtyczek, nie wysyła danych nigdzie poza
Stripe.

**Prywatność**

Zgoda kupującego (treść i czas) jest zapisywana przy zamówieniu i potwierdzana
w mailu. Dane kupującego można zanonimizować z panelu; historia zamówień
zostaje w Twojej bazie. Dane karty obsługuje wyłącznie Stripe.

Sprawdzone na WordPressie 7.1 i PHP 8.4; deklarowane minimum to WordPress 6.7
i PHP 8.1.

== Installation ==

1. Ustawienia → Bezpośrednie odnośniki: wybierz strukturę inną niż „Zwykłe"
   (bez tego strona sklepu pokazuje bloga, a Stripe nie dociera do webhooka;
   wtyczka pokaże wtedy ostrzeżenie w kokpicie).
2. Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer → `sklep-agentow.zip` →
   Zainstaluj → Włącz. Wtyczka sama tworzy strony sklepu oraz szkice regulaminu
   i polityki prywatności.
3. Sklep Agentów → Ustawienia: wklej klucz sekretny Stripe i sekret webhooka,
   uzupełnij nadawcę maili, mail kontaktowy właściciela i walutę.
4. W Stripe dodaj webhook na adres `https://TWOJA-DOMENA/wp-json/sklepag/v1/webhook`
   dla zdarzenia `checkout.session.completed`.
5. Sklep Agentów → Produkty → Dodaj produkt: nazwa, opisy, cena, waluta, plik
   ZIP; zaznacz „Produkt widoczny i kupowalny w sklepie".
6. Zrób zakup testowy kartą testową Stripe i sprawdź zamówienie na Pulpicie.

Pełna instrukcja krok po kroku (konfiguracja testowa i produkcyjna) jest
dołączona do wydania obok paczek (plik `INSTRUKCJA-INSTALACJI.md`); opis techniczny
(schemat bazy, stałe, punkty wejścia) w `DOKUMENTACJA-TECHNICZNA.md` w paczce.

== Frequently Asked Questions ==

= Zakup „wisi" na stronie Dziękujemy. =

Powiadomienie od Stripe nie dociera — najczęściej przez zły sekret webhooka
(sekret z panelu Stripe i z narzędzia Stripe CLI to dwa różne sekrety). Sklep
i tak domknie zakup w ciągu ok. 10 minut (zadanie cykliczne dopytuje Stripe),
a od ręki zrobi to przycisk „dociągnij zaległe" na Pulpicie.

= Mail z linkiem nie przyszedł. =

Treść maila jest zawsze w panelu przy zamówieniu, razem z przyciskiem „Wyślij
ponownie" (wysyłka ze świeżym linkiem). Wysyłką rządzi hosting — jeśli maile
nie wychodzą, skonfiguruj SMTP dowolną wtyczką SMTP.

= Nie mogę włączyć sprzedaży produktu. =

Produkt musi mieć wgrany i potwierdzony plik ZIP — to celowe: sklep nie sprzeda
czegoś, czego nie ma czym wydać.

= Strona produktu pokazuje 404. =

Ustawienia → Bezpośrednie odnośniki → Zapisz zmiany (odświeżenie reguł
adresów).

= Czy pliki produktów są bezpieczne na serwerze nginx? =

Katalog plików jest chroniony plikiem `.htaccess`, który działa na Apache,
a na nginx jest ignorowany. Prawdziwą ochroną jest brama w PHP (token, termin,
limit) i losowa nazwa katalogu — przy wdrożeniu na nginx dołóż regułę
blokującą ten katalog po stronie serwera.

= Co zostaje po odinstalowaniu? =

Domyślnie dane sprzedaży zostają w bazie (historia zamówień to nie śmieć).
Chcesz zniknąć bez śladu: Ustawienia → „Dane przy odinstalowaniu" → włącz pełne
czyszczenie PRZED usunięciem wtyczki — wtedy znikają tabele, ustawienia, strony
sklepu i pliki chronione. Klucze Stripe i zaplanowane zadanie są usuwane
zawsze, niezależnie od tej opcji.

= Czy kupujący zakłada konto? =

Nie. Zakup jest bez konta: adres e-mail podany na stronie płatności Stripe,
link do pobrania w mailu, jednorazowy link do opinii.

== Changelog ==

= 0.9.19 =
* Poprawka: formularz produktu nie gubi już wpisanej treści, gdy zapis wróci z błędem
  (brak paczki, zły plik, zła waluta) — nazwa, opisy i cena zostają na miejscu. Pole
  pliku trzeba wskazać ponownie: przeglądarka nie pozwala wypełnić go za Ciebie.
* Poprawka dostępności: nagranie w galerii „Co jest w środku" ma teraz widoczny przycisk,
  który da się obsłużyć klawiaturą — Tab dochodzi do niego, Enter zatrzymuje i wznawia.
* Instrukcja instalacji: ostrzeżenie o limicie przesyłania plików mniejszym niż paczka
  wtyczki oraz o ustawieniu Adaptive Pricing w Stripe (cena pokazana kupującemu w innej
  walucie, z opłatą za przewalutowanie).

= 0.9.18 =
* Poprawka: po opłaceniu zakupu ponowne kliknięcie przycisku zakupu zaczyna nową
  płatność zamiast pokazywać opłacony już ekran Stripe.
* Zmiana widoczna dla kupującego: okno ochrony przed podwójnym kliknięciem trwa teraz
  5 sekund zamiast minuty. Dwa kliknięcia pod rząd nadal nie kończą się podwójnym
  obciążeniem, ale powrót na stronę produktu po kilkunastu sekundach rozpocznie nową płatność.

= 0.9.17 =
* Ponowna wysyłka maila nie może zmienić stanu zamówienia zwróconego
  ani spornego — ochrona działa teraz na dwóch poziomach.

= 0.9.16 =
* Odrzucony przez bank zwrot nie oznacza zamówienia jako „mail wysłany",
  jeśli mail nigdy nie doszedł — wraca do stanu sprzed zwrotu.
* Obciążenie zwrotne nie ginie po cichu, gdy Stripe nie odpowiada.

= 0.9.15 =
* Odrzucony przez bank zwrot nie odejmuje kwoty drugi raz przy powtórzonym
  powiadomieniu — panel pokazuje prawdziwy przychód.
* Zwrot z cudzej płatności na wspólnym koncie Stripe nie odpytuje Stripe
  w kółko; przy awarii łączności zapytanie idzie raz, nie dwa.

= 0.9.14 =
* Opłacony zakup nie ginie, gdy baza zawiedzie w połowie zapisu.
* „Wyślij ponownie" nie unieważnia działającego linku, gdy mail nie wyjdzie.
* Katalog na pliki produktów odtwarza się sam po aktualizacji wtyczki.
* Wygasły formularz zakupu (strona z pamięci podręcznej) mówi po polsku
  i zostawia ślad w dzienniku — właściciel widzi, że sprzedaż stoi.
* Komunikat o limicie wgrywanego pliku podaje limit, który naprawdę obowiązuje.
* Zwroty i obciążenia zwrotne ze Stripe schodzą z przychodu na Pulpicie.
* Pulpit ostrzega, gdy brakuje klucza Stripe albo sekretu webhooka.
* Opinia bez powiązanego zamówienia nie przejdzie moderacji.
* Deinstalacja kasuje też dane tymczasowe wtyczki.

= 0.9.13 =
* Lista sklepu ma opis meta i znaczniki Open Graph/Twitter.
* Mail po zakupie potwierdza zgodę kupującego z datą, ma nagłówek Reply-To
  z maila kontaktowego właściciela i akapit o gwarancji (gdy produkt ją ma).
* Strona Segregatora: układ z nagraniem w jednej kolumnie na tabletach.

= 0.9.12 =
* Strona produktu Segregatora maila: galeria w trzech fazach, nagranie
  przycięte do panelu, lżejsze media, podgląd pełnoekranowy z pułapką fokusu.

= 0.9.11 =
* Strona produktu ChatBot LIVE: hero z żywym panelem, galeria w trzech fazach,
  podgląd pełnoekranowy, przyklejona pigułka zakupu.

= 0.9.10 =
* Lista sklepu w rodzinie ruchu strony: hero z panelem i galaktyką, karty
  i kroki, wersja lekka dla słabszych komputerów.

Pełna historia zmian: `CHANGELOG.md` w paczce.
