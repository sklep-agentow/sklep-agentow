=== Segregator maila ===
Contributors: automaticai
Tags: email, imap, inbox, automation, rules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rozkłada firmową pocztę do folderów według Twoich reguł. Łączy się po IMAP i domyślnie pracuje na sucho: pokazuje, zanim cokolwiek ruszy.

== Description ==

Segregator maila porządkuje skrzynkę firmową. Ustawiasz reguły w rodzaju „nadawca
zawiera faktury@ → folder Faktury", a wtyczka rozkłada pocztę za Ciebie.

**Najpierw sprawdzasz, potem wpuszczasz na żywą pocztę.** Reguły testujesz na
PRÓBCE maili — wbudowanym demo albo własnym eksporcie CSV. Raport pokazuje
z liczbami, ile poczty automat rozdzieli sam, jaka jest trafność i przy których
mailach się myli. Dopiero potem podłączasz prawdziwą skrzynkę.

= Tryb na sucho jest domyślny =

Po podłączeniu skrzynki wtyczka **niczego nie rusza**. Pokazuje listę: która
wiadomość, od kogo, do którego folderu **by trafiła**. To nie jest obietnica
w regulaminie — w tym trybie folder otwierany jest poleceniem IMAP `EXAMINE`,
czyli tylko do odczytu po stronie serwera poczty. Realne przenoszenie włączasz
świadomie, dwoma krokami.

= Co potrafi =

* Połączenie IMAP: SSL/TLS, STARTTLS albo bez szyfrowania, z przyciskiem
  „Sprawdź połączenie", który naprawdę się loguje i pokazuje liczbę wiadomości.
* Przenoszenie do folderów na serwerze poczty, z zakładaniem brakujących folderów.
* Zadanie cykliczne: co 15 minut, 30 minut, godzinę, 6 godzin albo raz na dobę,
  plus przycisk „Uruchom teraz".
* Historia: co, kiedy i dokąd zostało przeniesione — z cofnięciem ostatniego
  przebiegu.
* Raport na próbce: pokrycie, trafność, imienna lista pomyłek.
* Eksport reguł do filtrów Gmaila (XML) i reguł Outlooka.
* Opcjonalny tryb AI na próbce — wyłącznie po Twojej wyraźnej zgodzie.

= Prywatność =

Treść maili nie opuszcza Twojego serwera: wtyczka łączy się bezpośrednio z Twoim
serwerem poczty i klasyfikuje lokalnie, regułami. Wiadomości pobiera poleceniem
`BODY.PEEK`, które **nie oznacza ich jako przeczytanych**. Próbka, raporty
i historia skrzynki są kasowane automatycznie po 90 dniach.

Na skrzynce IMAP pracują SAME REGUŁY — do dostawcy modelu nie wychodzi stamtąd nic.

Jedyny wyjątek to opcjonalny tryb AI, który działa wyłącznie na PRÓBCE wgranej
przez Ciebie: do dostawcy modelu wychodzą wtedy nadawca (190 znaków), temat
(300 znaków) i treść maila z próbki (pierwsze 800 znaków), identyfikator wiersza
próbki, nazwy Twoich kategorii i nazwa modelu; przy błędzie żądanie jest
ponawiane raz. WordPress dokleja do każdego żądania nagłówek `user-agent`
z adresem Twojej strony i wersją WordPressa. Nie wychodzą załączniki, nagłówki
techniczne maila ani nic ze skrzynki IMAP; adresu IP nie umieszczamy w treści
żądania (samo połączenie ujawnia go dostawcy tak jak każde inne). Nie stanie się to bez Twojego wyraźnego potwierdzenia,
zapisywanego z datą i loginem. Przycisk „Przetestuj klucz API" wysyła wyłącznie
słowo „ping".

= Czego wtyczka nie zrobi =

* Nie odpowiada na maile i niczego nie wysyła.
* Nie kasuje wiadomości.
* Nie obsłuży Microsoft 365 ani Outlook.com — Microsoft wyłączył tam logowanie
  hasłem i wymaga OAuth2.
* Nie pobiera załączników.

== Installation ==

1. **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer** → wskaż plik ZIP →
   **Zainstaluj** → **Włącz**.
2. Po włączeniu zobaczysz ekran powitalny z trzema pierwszymi krokami.
3. Od razu masz gotowe: 5 kategorii, 8 przykładowych reguł i próbkę 30 polskich
   maili demo. Wejdź w **Segregator maila → Przebiegi** i kliknij
   „Przepuść próbkę", żeby zobaczyć raport.
4. Reguły dopasuj do swojej firmy w zakładce **Reguły**.
5. Skrzynkę podłączysz w zakładce **Skrzynka (IMAP)**. Zacznij od przycisku
   „Sprawdź połączenie", potem puść jeden przebieg na sucho i przeczytaj listę.

Wymagania: WordPress 6.0 lub nowszy, PHP 8.1 lub nowszy. Do połączenia z prawdziwą
skrzynką: funkcja PHP `stream_socket_client` (bywa blokowana na najtańszych
hostingach) i rozszerzenie OpenSSL — wbudowana diagnostyka sprawdzi oba i powie,
o co poprosić hostingodawcę. Microsoft 365 i Outlook.com NIE są obsługiwane
(wymagają OAuth); Gmail i skrzynki hostingowe z hasłem aplikacji działają.
Rozszerzenie PHP `imap`
**nie jest potrzebne** — wtyczka ma własnego klienta IMAP.

== Frequently Asked Questions ==

= Czy wtyczka może skasować albo pogubić moje maile? =

Domyślnie nie rusza ich w ogóle — folder otwiera w trybie tylko do odczytu.
Realne przenoszenie wymaga dwóch świadomych kroków: potwierdzenia i przełączenia
trybu. Wtyczka nigdy nie kasuje wiadomości, tylko przenosi je między folderami,
a ostatni przebieg da się cofnąć jednym przyciskiem.

= Jakie hasło mam podać? =

**Hasło aplikacji, nigdy głównego hasła do konta.** To osobne hasło wydawane
jednemu programowi: unieważnisz je jednym kliknięciem, nie zmieniając hasła do
poczty. U dostawców z logowaniem dwuskładnikowym zwykłe hasło i tak nie zadziała.

= Gdzie trzymane jest hasło? =

Domyślnie w bazie danych Twojego WordPressa, w osobnej opcji bez autoload.
Bezpieczniej jest wpisać je stałą w pliku `wp-config.php`:

`define( 'SEGM_IMAP_HASLO', 'twoje-haslo-aplikacji' );`

Wtedy hasło nie leży w bazie i nie wychodzi z jej kopią zapasową. Stała ma
pierwszeństwo nad polem w panelu. Hasło nigdy nie wraca do formularza i jest
kasowane przy odinstalowaniu wtyczki — zawsze.

= Mam Microsoft 365 albo Outlook.com. Zadziała? =

Nie. Microsoft wyłączył tam logowanie loginem i hasłem; ich serwer przyjmuje
wyłącznie OAuth2, a hasło aplikacji tego nie obejdzie. Wtyczka wykryje to
i powie wprost, zamiast w kółko zgłaszać „błędne hasło". Gmail działa.

= Czy potrzebuję rozszerzenia PHP „imap"? =

Nie. To rozszerzenie zostało wyjęte z PHP i przeniesione do repozytorium PECL —
od PHP 8.4 nie jest już częścią PHP. Wtyczka ma własnego klienta IMAP
i potrzebuje tylko tego, co PHP ma zawsze. Zakładka *Skrzynka (IMAP)* zaczyna
się od sekcji, która sprawdza Twój serwer i mówi po polsku, czy czegoś brakuje.

= Co się dzieje z moimi danymi po odinstalowaniu? =

Hasło do skrzynki, klucz API i zapisane zgody znikają **zawsze**. Reguły,
kategorie, próbka i historia domyślnie zostają, żeby dało się wrócić bez strat;
pełne czyszczenie włączysz opcją w Ustawieniach przed odinstalowaniem.

= Czy mogę zobaczyć, co wtyczka wysyła do serwera poczty? =

Tak. Po kliknięciu „Sprawdź połączenie" panel pokazuje pełny zapis rozmowy
z serwerem. Hasło jest w nim zamaskowane.

== Changelog ==

Pełna historia zmian jest w pliku `CHANGELOG.md` w katalogu wtyczki.

= 1.3.8 =
* Dodane: eksporter i kasownik danych osobowych dla narzedzi prywatnosci WordPressa (Narzedzia -> Eksport / Usun dane osobowe); klucz osoby = adres nadawcy; obejmuje probke, dziennik ruchow (takze Message-ID) i raporty przebiegow. Podpowiedz do Polityki prywatnosci.
* Naprawione: klient poczty ma sufity na rozmiar odpowiedzi serwera (linia 4 MB, dane 1 MB, odpowiedz 8 MB) - zbyt duza odpowiedz konczy sie komunikatem, nie bledem pamieci PHP; hosting z zablokowanymi polaczeniami PHP dostaje zdanie, co zrobic; import konfiguracji dziala na hostingu bez trybu direct; po wniosku RODO cofanie przebiegu dla tej osoby nie szuka juz po Message-ID (droga po identyfikatorze serwera zostaje).

= 1.3.7 =
* Naprawione: reguły „treść zawiera” działają na zwykłej poczcie — wiadomość wieloczęściowa (Gmail, Outlook, Thunderbird) jest rozbierana na części, do reguły idzie tekst rozkodowany kodowaniem tej części, a granice i nagłówki części nie trafiają do dopasowania.
* Naprawione: krótki mail zakodowany base64 nie znika (ostatnia linia nie jest odcinana bezwarunkowo).
* Naprawione: błędna nazwa zestawu znaków od nadawcy (np. „charset=BASE64”) nie zamienia treści w śmieci.
* Naprawione: z części tekstowej nie są już zdejmowane znaczniki HTML — tylko z części HTML, i tylko domykające się.
* Naprawione: załącznik tekstowy (.txt, .csv, .ics) nie udaje treści listu; wiadomość wieloczęściowa bez parametru boundary nie oddaje regule surowego MIME; rozpoznawane nazwy zestawów znaków Shift_JIS i GB2312.

= 1.3.6 =
* Wydanie porządkowe: z komentarzy w kodzie i z dokumentacji usunięte wewnętrzne oznaczenia naszych raportów. Zero zmian w działaniu.

= 1.3.5 =
* Wydanie porządkowe: zgodność z WordPress 7.1 w opisie wtyczki, drobne porządki w komentarzach kodu. Zero zmian w działaniu.

= 1.3.4 =
* Wymagania w readme mówią pełną prawdę: stream_socket_client + OpenSSL dla prawdziwej skrzynki; Microsoft 365/Outlook.com nieobsługiwane (OAuth). Zero zmian w kodzie.

= 1.3.3 =
Instrukcja z 6 zrzutami ekranu (próbka, przebiegi, reguły, kategorie, IMAP, ustawienia) — maszynowo.
Zero zmian w kodzie.

= 1.3.2 =
Wyniki pełnego audytu wprawione w kod: przebieg na żywo zapisuje zamiar PRZED
ruchem poczty (awaria w pół kroku nie kłamie już sukcesem, cofanie liczy tylko
wrócone, UIDVALIDITY pilnuje folderu docelowego, schemat bazy 4), zamek przebiegu
atomowy i z terminem ważności, ekran skrzynki prowadzi za rękę i potwierdza zapis
jako pierwszy pasek, komunikaty po ludzku, „zapisano" dopiero po zmierzonym
zapisie, a deklaracja prywatności mówi wprost: na skrzynce pracują same reguły.

= 1.3.1 =
Instrukcja opisuje wtyczkę, którą kupujący naprawdę dostaje: podłączenie skrzynki
krok po kroku, liczniki przy regule, przeprowadzka reguł plikiem, wybór modelu.
Zero zmian w kodzie.

= 1.3.0 =
* Podłączenie prawdziwej skrzynki po IMAP: połączenie, sprawdzenie połączenia,
  przenoszenie do folderów, zakładanie brakujących folderów.
* Tryb NA SUCHO jako domyślny — realne przenoszenie wymaga dwóch świadomych kroków.
* Zadanie cykliczne z konfigurowalną częstotliwością i przycisk „Uruchom teraz".
* Historia przebiegów i cofnięcie ostatniego przebiegu.
* Eksport reguł do filtrów Gmaila i reguł Outlooka.
* Ekran powitalny po aktywacji, dokumentacja techniczna, diagram przepływu,
  `CHANGELOG.md`, `readme.txt` i pełny tekst licencji.
* Bezpieczeństwo: hasło aplikacji ze stałą w `wp-config.php` mającą pierwszeństwo,
  kasowane przy odinstalowaniu zawsze; retencja 90 dni objęła historię skrzynki.

= 1.2.1 =
* Odinstalowanie kasuje klucz API zawsze, nie tylko przy pełnym czyszczeniu.
* Kod przepuszczony przez WordPress Coding Standards.

= 1.2.0 =
* Domyślny model trybu AI: haiku-4-5, z filtrami do podmiany.
* Twardy limit próbki dla trybu AI: 30 maili na przebieg.

= 1.1.0 =
* Tryb AI i tryb „porównaj oba" — różnice między regułami a modelem.
* Dzienny limit wywołań AI.

= 1.0.0 =
* Silnik regułowy, panel, import i eksport CSV, raport z liczbami, seed demo.
