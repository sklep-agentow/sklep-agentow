=== Sklep Agentów ===
Contributors: automaticai
Tags: sklep, stripe, pliki cyfrowe, sprzedaz wtyczek, pobieranie
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.9.30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sprzedaż wtyczek-botów AI ze swojej strony: płatność kartą przez Stripe, mail z linkiem do pobrania, panel zamówień — wszystko w Twoim WordPressie.

== Description ==

Sklep Agentów sprzedaje pliki cyfrowe (wtyczki-boty) prosto ze strony na
WordPressie: lista produktów i strony sprzedażowe → płatność kartą przez Stripe
Checkout → mail z linkiem do pobrania → panel zamówień, statystyk i dziennika
logowań. Zamówienia, produkty i pliki zostają w Twojej bazie i na Twoim
serwerze — bez abonamentu i bez pośredników. Jedyna usługa zewnętrzna to Stripe
(płatność): jedyny skrypt z sieci to Stripe.js (js.stripe.com), ładowany wyłącznie
na stronie produktu i tylko gdy w Ustawieniach jest klucz publikowalny — bo formularz
karty musi być ramką Stripe, nie naszym kodem.

**Co dostajesz po instalacji**

* Strony „Sklep" (lista produktów) i „Dziękujemy za zakup" tworzone
  automatycznie, plus dwa szkice do uzupełnienia własnymi danymi: „Regulamin
  sklepu" i „Polityka prywatności sklepu".
* Strony produktów pod adresem `/sklep/produkt/…/` — dla ChatBota LIVE
  i Segregatora maila z wbudowaną prezentacją sprzedażową (sekcje, galeria,
  pytania i odpowiedzi, opinie kupujących).
* Płatność kartą przez **Stripe** — formularz karty na stronie produktu, w kolorach
  strony (ramka Stripe: dane karty nie trafiają na Twój serwer); bez klucza
  publikowalnego albo bez skryptów — strona płatności Stripe. Nieobowiązkowe dane
  do faktury (nazwa, NIP, adres) w mailu i w panelu.
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
  Stripe, nadawca maili, mail kontaktowy właściciela, dane sprzedawcy do stopki
  maila, waluta, zachowanie danych przy odinstalowaniu.
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

Sklep jest wpięty w narzędzia prywatności WordPressa: Narzędzia → Eksportuj dane
osobowe wydaje zamówienia, opinie i sesje płatności kupującego, a Narzędzia →
Usuń dane osobowe anonimizuje je. Dane do faktury (nazwa, NIP, adres) zostają
także po takim wniosku — wymaga tego ustawa o VAT — a narzędzie mówi o tym
wprost w raporcie. Wtyczka dokłada też gotowy akapit do Ustawienia → Prywatność.

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
dołączona do wydania obok paczek (plik `INSTRUKCJA-INSTALACJI.md`); instrukcja
właściciela na co dzień (produkty, zamówienia, maile, opinie, RODO) w `INSTRUKCJA.md`
w paczce, ze zrzutami panelu; opis techniczny (schemat bazy, stałe, punkty wejścia)
w `DOKUMENTACJA-TECHNICZNA.md` w paczce; lista tego, co sklep robi ponad literę
zamówienia — `PONAD-SPECYFIKACJE.md` w paczce.

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

= 0.9.30 =
* Naprawione (RODO): anonimizacja kupującego usuwa jego adres e-mail z dziennika zdarzeń także wtedy, gdy zapisał się tam w postaci zakodowanej. Adres z ukośnikiem przed „@" (dopuszczalny, spotykany wyjątkowo) zostawał w dzienniku, a wtyczka i tak potwierdzała usunięcie — żądanie RODO nie było zrealizowane, choć właściciel miał dowód wykonania. Zamyka znaną granicę opisaną przy 0.9.19.
* Naprawione (RODO): sprawdzenie skutku pyta dokładnie o to, co czyszczenie — dotąd miało własne, równie wąskie zapytanie i nie mogło zobaczyć pominiętego śladu. Wpisy INNYCH kupujących, których adres zawiera nasz jako końcówkę, pozostają nietknięte również w postaci zakodowanej.

= 0.9.28 =
* Naprawione: po nieudanej ponownej wysyłce maila (awaria poczty) wraca dokładnie ten link, który kupujący ma w skrzynce — wtyczka zapamiętuje stan każdego doręczonego maila i przywraca właśnie jego. Znany wyjątek z 0.9.27 (pad serwera w pół wysyłki + nieudane ponowienie mogły zgasić działający link) jest zamknięty.
* Naprawione: gdy dwa procesy domykały ten sam zakup, panel mógł oznaczyć doręczonego maila jako „błąd maila" — teraz status zapisuje tylko proces, który faktycznie wysyłał.
* Naprawione: anonimizacja kupującego (RODO) usuwa także zapisaną treść maila i zapamiętany ślad doręczonego maila — treść niosła dane do faktury (nazwa firmy, NIP, adres), jeśli kupujący je podał.
* Zmienione: sekcja ceny na stronie produktu ma panel „Po zakupie" (płatność bez konta, kiedy przyjdzie mail z linkiem i na jak długo, aktualizacje i wsparcie); kafle listy produktów wyrównane do krawędzi; schowany testowy przycisk Stripe, który zasłaniał okno czatu.

= 0.9.27 =
* Naprawione: gdy poczta ma awarię przy automatycznym ponowieniu maila, wcześniej wysłany link do pobrania dalej działa (dotąd mógł zgasnąć na stałe). Znany wyjątek: gdy wysyłka urwie się w pół (np. pad serwera w jej trakcie) i ponowienie po niej też zawiedzie, link może przestać działać — ratunkiem jest przycisk „Wyślij ponownie" przy zamówieniu w panelu.
* Naprawione: strona nieistniejącego produktu zwraca kod 404 także dla żądań HEAD (dotąd narzędzia sprawdzające widziały 200).
* Naprawione: gdy na stronie działa wtyczka SEO (Yoast, Rank Math), sklep nie dokłada drugiego znacznika canonical.
* Naprawione: strona nieistniejącego produktu (404) nie dostaje znacznika canonical wskazującego listę sklepu — także przy domyślnych ustawieniach WordPressa.

= 0.9.26 =
* Naprawione: strony sklepu (lista i produkt) dostają znacznik canonical także na motywach, które wyłączają canonical WordPressa — dotąd Google widział stronę produktu pod dwoma adresami jako dwie strony.
* Naprawione: gdy przy instalacji nie powstała strona „Sklep” lub „Dziękujemy”, wtyczka doklejała swoje style, skrypty i opisy do wszystkich stron witryny; teraz bez tych stron nie dokleja niczego do innych stron (sklep pokaże się tylko tam, gdzie sam wstawisz skrót [sklepag_sklep]).

= 0.9.25 =
* Dodane: formularz płatności na stronie produktu, w kolorach strony (Stripe Checkout Sessions w trybie „elements”: e-mail i karta w ramce Stripe, przycisk „Zapłać”, powrót na „Dziękujemy”); wymaga wpisania klucza publikowalnego w Ustawieniach — bez niego działa dotychczasowa strona płatności Stripe.
* Dodane: nieobowiązkowe „Dane do faktury” przy zakupie (nazwa firmy, NIP z sumą kontrolną, adres — komplet albo nic); trafiają do maila, do szczegółów zamówienia i są czyszczone przy anonimizacji. Sklep faktur nie wystawia; podanie NIP-u nie zmienia praw kupującego.
* Zmienione: baza — nowa kolumna dane_faktury (schemat 8, migracja sama); szkic Polityki prywatności wymienia dane do faktury (sklepy zainstalowane wcześniej: dopisz ręcznie).
* Naprawione: „Dociągnij zaległe teraz” mówi, ile zakupów domknął i ile sesji pominął jako młodsze niż 2 minuty (dotąd milczał); porzucone, nieopłacone sesje nie blokują już domknięcia nowszego, opłaconego zakupu (najnowsze sprawdzane najpierw, przerwa po sprawdzeniu).

= 0.9.24 =
* Dodane: mail po zakupie w wersji graficznej (nazwa sklepu w nagłówku, okładka produktu, przycisk „Pobierz”, kolory strony) wysyłany razem z wersją tekstową w jednej wiadomości; dotąd mail był czystym tekstem.
* Dodane: w mailu numer zamówienia (ten sam co w panelu), dopisek o fakturze i stopka z danymi sprzedawcy — nowa sekcja Ustawień „Dane sprzedawcy” (nazwa, adres, NIP ze sprawdzeniem sumy kontrolnej); Pulpit ostrzega, gdy pola są puste (ustawa o prawach konsumenta, art. 21).
* Zmienione: zdanie o zgodzie w mailu bez formy męskiej („Zgoda zaznaczona przy zakupie”).

= 0.9.23 =
* Naprawione: świeży link do pobrania (z „Wyślij ponownie” i z automatycznego ponowienia) ma licznik pobrań od zera (dotąd tylko nowy termin — kupujący z wyczerpanym limitem dostawał nowy link i od razu odmowę).
* Zmienione: szkic Polityki prywatności wymienia adres IP przy pobraniu pliku; sklepy zainstalowane wcześniej mają stary szkic — dopisz zdanie ręcznie, jeśli Polityka jest opublikowana.
* Dodane: instrukcja właściciela (INSTRUKCJA.md ze zrzutami panelu) i jawna lista tego, co sklep robi ponad zamówienie (PONAD-SPECYFIKACJE.md) — oba pliki w paczce.
* Zmienione: przycisk „Wyślij ponownie” pokazuje się tylko przy zamówieniu w stanie „mail wysłany” albo „błąd maila”; przy zwróconym lub spornym sklep mówi wprost, że ponowna wysyłka nie jest dostępna.
* Zmienione: dwa komunikaty „Brak uprawnień.” w moderacji opinii wypisywane wprost, bez funkcji tłumaczenia (wtyczka nie tłumaczy tekstów).

= 0.9.22 =
* Wydanie porządkowe: z komentarzy w kodzie i z dokumentacji usunięte wewnętrzne oznaczenia naszych raportów. Zero zmian w działaniu.

= 0.9.21 =
* Naprawione: anonimizacja na zadanie RODO potwierdzala usuniecie danych, nie sprawdziwszy,
  czy zapis sie udal. Teraz potwierdzenie idzie wylacznie za faktycznym usunieciem, a przy
  niepowodzeniu wlasciciel dostaje ostrzezenie, zeby nie potwierdzac usuniecia osobie,
  ktora zlozyla zadanie.
* Naprawione: komunikat po nieudanym zapisie produktu twierdzil, ze wgrany plik zostal
  usuniety, takze gdy usuniecie sie nie powiodlo. Komunikat opisuje teraz stan faktyczny.
* Naprawione: adres kupujacego mogl zostac w dzienniku zdarzen mimo potwierdzenia usuniecia.
  Potwierdzenie wymaga teraz czystosci w trzech miejscach: adres w zamowieniu, slady w opisach
  zdarzen oraz imie i token przy opinii. Naprawa ma automatyczny test z podlozona awaria zapisu;
  naprawa komunikatu przy nieudanym zapisie produktu testu jeszcze nie ma.
* Znana granica: adres zawierajacy ukosnik przed znakiem "@" (dopuszczalny, ale w praktyce
  spotykany wyjatkowo) moze zostac w dzienniku zdarzen mimo potwierdzenia usuniecia. Jesli
  obslugujesz takie adresy, sprawdz dziennik recznie po anonimizacji.

= 0.9.20 =
* Poprawka: gdy zostawisz puste pole wymagane — zgodę przy zakupie albo imię, treść
  i zgodę w formularzu opinii — sklep mówi o tym PO POLSKU. Wcześniej komunikat
  rysowała przeglądarka i na polskiej stronie potrafił pojawić się po angielsku.
  Same blokady były i są takie same — zmienił się wyłącznie język komunikatu.

= 0.9.19 =
* Poprawka: formularz produktu nie gubi już wpisanej treści, gdy zapis wróci z błędem
  (brak paczki, zły plik, zła waluta) — nazwa, opisy i cena zostają na miejscu. Pole
  pliku trzeba wskazać ponownie: przeglądarka nie pozwala wypełnić go za Ciebie.
* Poprawka: formularz opinii kupującego też nie gubi już napisanej treści — po odrzucie
  (puste imię, brak zgody) imię, tytuł, ocena i treść wracają na formularz. Zgoda na
  publikację wraca NIEZAZNACZONA: to musi być świadomy gest kupującego.
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
