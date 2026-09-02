# WERDYKTY WOBEC ZAMÓWIENIA KLIENTA — 16 wymagań z pustym kwadratem

**Kto:** audytor, sesja 2026-09-01, po `E7` i `E6`. **Gałąź:** `audyt/czesciowo-01-09`.
**Źródło wymagań:** `audyt/sklep/material/01-zamowienie.md`, sekcja (b) — tabela `S-01…S-20`.
**Kodu produktu nie tknąłem.** Nie naprawiam, nie proponuję poprawek — stawiam werdykty.

## Skąd liczba 16 — policzone komendą, nie z pamięci

```
grep -cE '^\| S-[0-9]+ \|' audyt/sklep/material/01-zamowienie.md              → 20
grep -E  '^\| S-[0-9]+ \|' … | grep -c '⬜ |$'                                 → 16
```
Pusty kwadrat mają: `S-01 S-02 S-03 S-04 S-05 S-06 S-07 S-08 S-09 S-11 S-12 S-13 S-14 S-15 S-18 S-19`.
Poza zestawem (kolumna „Dowód" = `—`, świadomie): `S-10` (NIE-FUNKCJA), `S-16` (termin),
`S-17` (konwersja strony robiona nie przez nas), `S-20` (potwierdzenie źródła do `S-08`).

## Werdykty — pięć możliwych, użyte cztery

| werdykt | ile | które |
|---|---|---|
| **SPEŁNIONE** | 10 | `S-01 S-02 S-04 S-05 S-06 S-07 S-09 S-14 S-18 S-19` |
| **SPEŁNIONE INACZEJ** | 5 | `S-03 S-08 S-11 S-12 S-13` |
| **BRAK** | 0 | — |
| **NIE SPRAWDZIŁEM** | 0 | — |
| **NIE DA SIĘ SPRAWDZIĆ** | 1 | `S-15` (część wdrożeniowa; część kodowa opisana w sekcji) |

⛔ **Czego te werdykty NIE mówią.** „SPEŁNIONE" znaczy: **otworzyłem plik, przeczytałem to
miejsce i podaję linię**. Nie znaczy „przetestowane na żywym stosie" — gdzie stos był użyty,
piszę to wprost w sekcji. Trzy wymagania mają dopisek o tym, czego pomiar NIE objął.

⚠️ Dwa werdykty `SPEŁNIONE INACZEJ` (`S-08`, `S-11`/`S-12`/`S-13`) to **odstępstwa, które
sam dokument zamówienia deklaruje** (status `ODSTĘPSTWO PRZYJĘTE` / „wiersze unieważnione
piwotem") — raportuję je jako ZNANE, zgodnie z instrukcją w §(b) źródła. **Jedyny
`SPEŁNIONE INACZEJ`, który jest NOWY i jest znaleziskiem, to `S-03`.**

---

## S-01 — „Sklep z agentami (2x)"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Silnik sklepu jest ogólny (produkty w tabeli `sklepag_produkty`,
`includes/aktywacja.php:21-43`), a treść sprzedażowa **obu agentów** siedzi w kodzie pod
kluczami slugów: `includes/front-produkt.php:176` (`'chatbot-live'`) i `:357`
(`'segregator-maila'`) — jedna tablica `$tresci`, zwracana w `:520`.
Na żywym stosie oba produkty istnieją i są w sprzedaży (odczyt bazy, nie deklaracja):
```
#1 ChatBot LIVE [chatbot-live] 14900 PLN aktywny=1
#5 Segregator maila [segregator-maila] 19900 PLN aktywny=1
```

**JAK CZŁOWIEK TEGO UŻYWA.** Kupujący: strona **`/sklep/`** (strona WP „Sklep" ze skrótem
`[sklepag_sklep]`, zakładana przy aktywacji — `includes/aktywacja.php:200`), na niej kafle obu
produktów i przycisk **„Zobacz szczegóły"**. Właściciel: **Kokpit → Sklep Agentów → Produkty**.

**UWAGA (nie zmienia werdyktu).** Wtyczka **nie zakłada** tych dwóch produktów sama — w
`aktywacja.php` nie ma żadnego `INSERT` do `produkty`; wiersze zakłada właściciel wg
`dokumentacja/INSTRUKCJA-INSTALACJI.md`, Krok 4. Konsekwencję tego opisuje `S-03`.

---

## S-02 — „Po zakupie, klienta na maila potwierdzenie, instrukcje"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Mail składany w `includes/zakup.php:1273` (`sklepag_zbuduj_mail`) i wysyłany
w `:1055` przez `sklepag_wyslij_mail_sklepu` (`:1167`), po domknięciu zakupu z webhooka.
Wersja graficzna: `includes/mail-html.php:38`. Zawiera **potwierdzenie** (numer, produkt, data,
kwota, potwierdzenie zgody z chwilą — `zakup.php:1281-1288`), **instrukcję** („INSTRUKCJA
W SKRÓCIE", trzy kroki — `zakup.php:1314`) i **link do pobrania** (`zakup.php:1312`).

**JAK CZŁOWIEK TEGO UŻYWA.** Kupujący: skrzynka pocztowa, temat „Twój zakup: … —
potwierdzenie i pobranie", przycisk **„Pobierz …"** w mailu. Właściciel: **Pulpit → Szczegóły
zamówienia → „Treść wysłanego maila"** + przycisk **„Wyślij ponownie (świeży link)"**
(podgląd treści i przycisk ponownej wysyłki: `includes/admin-zamowienie.php:346-357`).

**POMIAR (ponad odczyt kodu).** Prawdziwy mail zdjęty dziś z łapacza `sklep_mailpit`: niesie
komplet — numer `#9902289`, produkt, datę, kwotę, potwierdzenie zgody z 01.09.2026 04:56,
link, instrukcję w 3 krokach, gwarancję, link do opinii, stopkę sprzedawcy.

---

## S-03 — „Dwa rozne, ceny, co robi — na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie"

**WERDYKT: SPEŁNIONE INACZEJ.** ⭐ **To jedyny nowy rozjazd z tego przeglądu.**

**DOWÓD, że treść JEST.** Strona produktu składa dokładnie te sekcje, których żąda punkt —
`includes/front-produkt.php`: `:858` **PROBLEM**, `:863` **CO DOSTAJESZ** („co robi"),
`:877` **JAK DZIAŁA**, `:899` **ILE CZASU ODZYSKUJESZ** („jak skraca czas pracy"),
`:919` RĘCZNIE KONTRA BOT, `:930` DLA KOGO, `:972` **CENA**, `:1007` FAQ.

**DOWÓD, dlaczego „INACZEJ".** Cała ta treść jest przypięta **do napisu**, nie do produktu:
```
174: function sklepag_tresc_produktu( $slug ) {
175:   $tresci = array( 'chatbot-live' => …, 'segregator-maila' => … );
520:   return isset( $tresci[ $slug ] ) ? $tresci[ $slug ] : null;
```
a `slug` powstaje z **nazwy produktu wpisanej przez właściciela** w panelu. Każde miejsce,
które buduje te sekcje, jest osłonięte warunkiem `if ( $t && … )` (np. `:830`, `:979`, `:993`,
`:1000`) — więc gdy slug nie trafi w jeden z dwóch napisów, strona produktu **po cichu** schodzi
do samego `opis_pelny` z formularza: bez „jak działa", bez „ile czasu odzyskujesz", bez FAQ
i bez gwarancji. Nie ma ostrzeżenia ani w panelu, ani na stronie.

Zachodzi to w trzech realnych sytuacjach: właściciel nazwie produkt inaczej („Chatbot LIVE 2.0",
„ChatBot Live"), zmieni nazwę istniejącego produktu, albo doda **trzeci** produkt (kryterium
GOTOWE #7 wprost tego wymaga: „nowy produkt widoczny w sklepie bez zmian kodu" — będzie
widoczny, ale bez treści, której żąda `S-03`).

**JAK CZŁOWIEK TEGO UŻYWA.** Kupujący: **`/sklep/` → „Zobacz szczegóły"** → strona produktu.
Właściciel: **Sklep Agentów → Produkty → Dodaj produkt**, pole **Nazwa** — i to właśnie ono,
niewidocznie dla niego, rozstrzyga, czy strona sprzedażowa się pojawi.

**Czym to NIE jest.** Nie jest brakiem — dla dwóch zamówionych produktów treść jest kompletna
i bogatsza, niż żąda punkt. Jest to spełnienie **warunkowe**, którego warunku nikt kupującemu
ani właścicielowi nie komunikuje.

---

## S-04 — „Admin panel — widok kiedy za ile kupili w jakim czasie / dashboard"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** `includes/admin-dashboard.php`: wybór okresu `dzis | 7dni | 30dni` (`:102-105`),
granice okresu i okresu poprzedniego (`:115-138`), karty KPI liczone jednym zapytaniem
— przychód po odjęciu zwrotów, liczba zamówień, liczba kupujących (`:157`), porównanie
procentowe do poprzedniego okresu (`:189-194`), **rozkład zakupów w czasie** jako słupki
(`:622`, `aria-label` „Przychód wg godzin/dni"), **Eksport CSV z aktualnych filtrów**
(`:258-270` obsługa z `check_admin_referer`, `:454-460` przycisk).
Lista zamówień — `includes/class-sklepag-lista-zamowien.php:43-53`: `Nr · Data · Kupujący ·
Produkt · Kwota · Pobrania · Status · Akcje`, czyli „kiedy" (Data), „za ile" (Kwota),
„co" (Produkt ze snapshotu).

**JAK CZŁOWIEK TEGO UŻYWA.** **Kokpit → Sklep Agentów → Pulpit**
(`includes/admin.php:41,51`): u góry karty KPI z przełącznikiem okresu, pod nimi wykres
słupkowy, niżej tabela zamówień z filtrami i przyciskiem **„Eksport CSV"**, przy każdym
wierszu **„Szczegóły"**.

**Czego NIE zmierzyłem:** nie renderowałem tego ekranu (renderowałem tylko ekran pojedynczego
zamówienia, przy `E7`). Werdykt stoi na odczycie kodu, nie na zrzucie.

---

## S-05 — „1. ChatBot LIVE"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Produkt istnieje jako towar sklepu (baza stosu: `#1 ChatBot LIVE [chatbot-live]
14900 PLN aktywny=1`) i ma pełną treść sprzedażową w `includes/front-produkt.php:176-345`
(etykieta, zdanie-hak, sekcje, FAQ, gwarancja `:345`). Sam bot jest osobną wtyczką WP:
`wtyczki/chatbot-live/chatbot-live.php` (nagłówek `Version: 1.5.13`, `Requires PHP: 8.1`).

**JAK CZŁOWIEK TEGO UŻYWA.** Kupujący: `/sklep/` → kafel **ChatBot LIVE** → „Zobacz
szczegóły" → **„Kup teraz (płatność testowa)"**.

---

## S-06 — „2. Segregator maila"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Baza stosu: `#5 Segregator maila [segregator-maila] 19900 PLN aktywny=1`; treść
sprzedażowa `includes/front-produkt.php:357-505` (gwarancja `:505`). Wtyczka bota:
`wtyczki/segregator-maila/segregator-maila.php` (`Version: 1.3.6`).

**JAK CZŁOWIEK TEGO UŻYWA.** Jak `S-05`, kafel **Segregator maila**.

---

## S-07 — „BAZY DANYCH 1. Dane uzytkownikow / platnksci"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** `includes/aktywacja.php:61-96` — tabela `sklepag_zamowienia` trzyma **dane
kupującego** (`email_kupujacego`, `dane_faktury`) i **dane płatności**: `stripe_session_id`,
`payment_intent_id`, `kwota_grosze`, `waluta`, `status`, `zwrocono_grosze`, `zwrot_czas`,
`status_przed_sporem`, `utworzono`, `zmieniono`. Osobno `sklepag_sesje_checkout` (`:39-48`) —
sesje płatności. Zgodnie z `S-07` **kont kupujących nie ma** — w schemacie nie istnieje żadna
tabela ani kolumna z hasłem/loginem kupującego.

**JAK CZŁOWIEK TEGO UŻYWA.** **Pulpit → wiersz zamówienia → „Szczegóły"**
(`includes/admin-zamowienie.php:329-338`: data zakupu, kupujący, produkt ze snapshotu, dane do
faktury, kwota, sesja Stripe, pobrania) oraz **Eksport CSV** na Pulpicie.

---

## S-08 — „ZAPIS AGENTOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU"

**WERDYKT: SPEŁNIONE INACZEJ** — odstępstwo **zadeklarowane w samym zamówieniu**
(status `ODSTĘPSTWO PRZYJĘTE 17.08`), więc raportuję jako ZNANE, nie jako błąd.

**DOWÓD.** `includes/admin-produkty.php`: **dodawanie** — `:62` przycisk „Dodaj produkt",
`:185` `submit_button`, `:191` `admin_post_sklepag_produkt_zapisz`; **modyfikowanie** — `:140`
ekran „Edytuj produkt #N", ten sam zapis; **„usuwanie" = deaktywacja** — `:403-446`
(`admin_post_sklepag_produkt_przelacz`, `UPDATE … SET aktywny=%d … AND aktywny=%d`, CAS).
Każda z trzech operacji zostawia ślad w dzienniku zdarzeń (`:446`
`produkt_aktywowany` / `produkt_deaktywowany`).

**Dlaczego „INACZEJ".** Litera mówi „USUWANIU"; kod **nie kasuje** wiersza produktu, tylko
zdejmuje go ze sprzedaży — po to, żeby zamówienia historyczne nie straciły odniesienia
(`admin-produkty.php:7`, `:145-148` pokazuje właścicielowi liczbę zamówień produktu i mówi
wprost, że edycja niczego w nich nie zmienia).

**JAK CZŁOWIEK TEGO UŻYWA.** **Kokpit → Sklep Agentów → Produkty**: przycisk **„Dodaj
produkt"**, przy wierszu **„Edytuj"** i przełącznik sprzedaży (**„W sprzedaży"**, `:182`).

---

## S-09 — „KTO KIEDY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Tabela `sklepag_logowania` — `includes/aktywacja.php:111-121`: `czas`,
`login_wpisany` („kto", także przy nieudanej próbie), `wynik`, `ip`, `user_agent`
(„konkrety"). Zapis: `includes/logowania.php:20-22` (haki `wp_login` i `wp_login_failed`) → `:30`
(`sklepag_zapisz_logowanie`); opóźnienie kolejnych prób `:22` (hak `wp_authenticate`).
Rozszerzenie ponad literę, zapowiedziane w `S-09` — **log akcji admina**: tabela
`sklepag_zdarzenia` (`aktywacja.php:99-108`) z kolumną `uzytkownik` wypełnianą loginem
zalogowanego (`includes/zakup.php:332-342`, `sklepag_zdarzenie`); piszą do niej m.in. CRUD produktu
(`admin-produkty.php:446`), ponowna wysyłka (`admin-zamowienie.php:131`) i eksport CSV
(`admin-dashboard.php:258`).

**JAK CZŁOWIEK TEGO UŻYWA.** **Kokpit → Sklep Agentów → Dziennik**
(`includes/admin.php:54`), dwie zakładki: **„Logowania"** i **„Zdarzenia"**
(`includes/admin-dziennik.php:34-39`).

---

## S-11 — (czat) „podstrona `/sklep` na istniejącej stronie"

**WERDYKT: SPEŁNIONE INACZEJ** — wiersz **unieważniony piwotem** wprost w źródle
(„Wiersze UNIEWAŻNIONE piwotem: S-11 … → wtyczka WP").

**DOWÓD.** Zamiast katalogu `sklep/` wpinanego w stronę statyczną wtyczka zakłada **stronę
WordPressa „Sklep"** ze skrótem `[sklepag_sklep]`, opublikowaną —
`includes/aktywacja.php:200`; skrót rejestrowany w `sklep-agentow.php:110`. Adres pozostaje
tym, o co prosił klient: na stosie `http://localhost:8890/sklep/`.

**JAK CZŁOWIEK TEGO UŻYWA.** Gość: link **„Sklep"** w menu strony (dopisanie go do motywu
opisuje `dokumentacja/INSTRUKCJA-INSTALACJI.md`, rozdział „Ustawienia produkcyjne").

---

## S-12 — (czat) „nie na wordpressie, zwykły HTML… na githubie hostowane"

**WERDYKT: SPEŁNIONE INACZEJ** — wiersz **unieważniony piwotem**, cytatem właściciela
z 17.08 („całą ta strona bedzie przekonwertowana na worpresa").

**DOWÓD.** Produkt jest wtyczką WordPressa: `wtyczki/sklep-agentow/sklep-agentow.php`
(nagłówek wtyczki, `Version: 0.9.31`, `Requires at least: 6.7`), zaplecze we własnych tabelach
(`includes/aktywacja.php:21-135`). Żadnego hostingu statycznego ani warstwy pośredniej:
`grep -rni 'cloudflare|vercel|next\.js'` po całej wtyczce → **0 trafień**.

**JAK CZŁOWIEK TEGO UŻYWA.** Nie dotyczy — to wymaganie o platformie, nie o funkcji.

---

## S-13 — (czat) „jak te pluginy: przypinamy, odpinamy i nie ma"

**WERDYKT: SPEŁNIONE INACZEJ** — idea zostaje, mechanizm zmieniony piwotem
(deklarowane w źródle: „realizacja = instalacja/deinstalacja wtyczki z czystym uninstall").

**DOWÓD — „przypinamy".** Aktywacja tworzy komplet: 7 tabel (`includes/aktywacja.php:21-135`),
4 strony (`:187-215`), katalog chroniony, zadanie cykliczne (`sklep-agentow.php:91-92`).
**„odpinamy i nie ma".** `wtyczki/sklep-agentow/uninstall.php`: sekrety Stripe i cron out
**zawsze** (`:16-23`, przed wczesnym `return`), a przy włączonym „Pełnym czyszczeniu" —
7 tabel (`:32`), 4 strony (`:39`), katalog z plikami (`:45`), wszystkie opcje `sklepag_%`
z `esc_like` (`:70`), transienty (`:77-79`), `user_meta` (`:89`).

**JAK CZŁOWIEK TEGO UŻYWA.** **Wtyczki → Sklep Agentów → Wyłącz → Usuń**; wcześniej
**Sklep Agentów → Ustawienia → „Dane przy odinstalowaniu" → „Pełne czyszczenie"**
(`includes/admin-ustawienia.php:388`).

**Czego NIE zmierzyłem:** kryterium GOTOWE #9 żąda **pustego diffu przed-po** na czystej
instalacji. Tego nie uruchamiałem (przyrząd istnieje: `narzedzia-qa/dowod-z-paczki.sh`,
stos `probny`). Werdykt dotyczy symetrii w kodzie, nie zmierzonego diffu.

---

## S-14 — (czat) „najważniejsze zdać egzamin; na razie nie robimy na gotowo z prawdziwymi płatnościami"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Tryb testowy jest wymuszony i widoczny: baner `sklepag_baner_testowy()`
(`sklep-agentow.php:234`) na liście sklepu (`:355`), na stronie produktu
(`includes/front-produkt.php:843`) i na „Dziękujemy" (`sklep-agentow.php:467`); notka przy
przycisku zakupu (`front-produkt.php:631`); FAQ „Co znaczy „tryb testowy”?"
(`sklep-agentow.php:438`); zdanie w mailu (`includes/zakup.php:1310`); oznaczenie w panelu
(`includes/admin-dashboard.php:358`). **Jawny rozdział różnic** wobec produkcji:
`dokumentacja/INSTRUKCJA-INSTALACJI.md`, rozdział „Test a produkcja — co trzeba zmienić,
żeby sprzedawać naprawdę" (9 punktów, w tym lista oznaczeń, które nie znikają same).

**JAK CZŁOWIEK TEGO UŻYWA.** Kupujący widzi pas **„TRYB TESTOWY — płatności nie są
pobierane"** na `/sklep/` i na stronie produktu (potwierdzone w rendrze żywej strony);
właściciel — etykietę **TRYB TESTOWY** przy tytule Pulpitu.

**Powiązanie:** brak wyłącznika tych oznaczeń był osobną pozycją `P-3`. ⭐ Aktualizacja: po
scaleniu `main` w trakcie tego przelotu `P-3` jest **naprawione** i obecne na tej gałęzi
(`wtyczki/sklep-agentow/sklep-agentow.php:234`) — nie dubluję jej tutaj i nie opisuję już
jako otwartej.

---

## S-15 — (czat) „wdrożymy do mojej i kolegi i 2 innych osób strony" → JEDNA wspólna strona, 4 konta adminów

**WERDYKT: NIE DA SIĘ SPRAWDZIĆ** (część wdrożeniowa) — z opisem tego, co **da się**
i co sprawdziłem.

**Czego nie da się sprawdzić w kodzie.** „Jedna wspólna strona i 4 konta adminów przy
wdrożeniu" to fakt o instalacji u klienta, nie o produkcie: żeby postawić werdykt, trzeba
zajrzeć na docelową stronę i policzyć konta z rolą administratora. Z repozytorium tego nie
widać.

**Co sprawdziłem i co z tego wynika (część kodowa: „wtyczka mimo to konfigurowalna,
przenośna").** Wtyczka nie przywiązuje się do żadnego konkretnego konta ani adresu:
uprawnienia idą przez `manage_options` (`includes/admin.php:41-61` — wszystkie ekrany),
a nie przez identyfikator użytkownika; wszystkie dane właściciela są w Ustawieniach
(`includes/admin-ustawienia.php:138-179`: klucze Stripe, nadawca, mail kontaktowy, dane
sprzedawcy, waluta); adresy budowane z `home_url()`/`admin_url()`, nie wpisane na stałe.
Dziennik logowań zapisuje **każdy** login (`login_wpisany`, `aktywacja.php:111-121`), więc
czterech adminów rozróżni.

**JAK CZŁOWIEK TEGO UŻYWA.** **Kokpit → Sklep Agentów → Ustawienia**; konta adminów zakłada
się standardowo w **Kokpit → Użytkownicy → Dodaj** (rdzeń WP, nie nasza wtyczka).

---

## S-18 — „ten sklep i 3 pluginy bedą wtyczkami do worpresa" + „2 testowe boty jako produkty"

**WERDYKT: SPEŁNIONE.**

**DOWÓD.** Trzy wtyczki WordPressa z poprawnymi nagłówkami:
`wtyczki/sklep-agentow/sklep-agentow.php` (`Version: 0.9.31`, `Requires at least: 6.7`,
`Requires PHP: 8.1`), `wtyczki/chatbot-live/chatbot-live.php` (`1.5.13`, `6.0`, `8.1`),
`wtyczki/segregator-maila/segregator-maila.php` (`1.3.6`, `6.0`, `8.1`).
Oba boty są jednocześnie **towarem** sklepu — patrz `S-05`, `S-06` (wiersze w
`sklepag_produkty` na stosie, treść sprzedażowa w `front-produkt.php`).

**JAK CZŁOWIEK TEGO UŻYWA.** **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer** dla każdej
z trzech paczek ZIP (`dokumentacja/INSTRUKCJA-INSTALACJI.md`, Krok 1 i Krok 6).

---

## S-19 — [okienko] zaplecze „Wszystko w WordPressie"

**WERDYKT: SPEŁNIONE.**

**DOWÓD — cztery elementy tego zdania, każdy osobno.**
1. **Własne tabele w bazie WP:** 7 tabel z prefiksem `sklepag_` zakładanych `dbDelta`
   (`includes/aktywacja.php:21-135`).
2. **Panel w wp-admin:** menu i pięć podstron (`includes/admin.php:41-61`).
3. **Stripe z serwera WP, bez SDK:** `includes/stripe.php:12` (`https://api.stripe.com`)
   i `:46` `wp_remote_post` — czyli warstwa HTTP WordPressa.
4. **Maile z serwera WP:** `includes/zakup.php:1186` `wp_mail`, z filtrami nadawcy
   zakładanymi i zdejmowanymi wyłącznie wokół własnej wysyłki (`:1180-1193`).
**„Zero Cloudflare"** — `grep -rni 'cloudflare|vercel|next\.js'` po całej wtyczce: 0 trafień.

**JAK CZŁOWIEK TEGO UŻYWA.** Wszystko z jednego miejsca: **Kokpit → Sklep Agentów**
(Pulpit · Produkty · Opinie · Dziennik · Ustawienia). Poza WordPressem właściciel nie ma się
gdzie logować — nie ma osobnego panelu ani zewnętrznej usługi.

---

## CO Z TEGO WYNIKA — trzy zdania

**Po pierwsze:** szesnaście pustych kwadratów nie znaczyło szesnastu dziur. Piętnaście wymagań
ma pokrycie w kodzie, które da się wskazać palcem (plik i linia), a jedyne, czego nie da się
rozstrzygnąć z repozytorium, to `S-15` — bo to fakt o wdrożeniu, nie o produkcie.

**Po drugie:** jedyny **nowy** rozjazd to `S-03` i jest w miejscu, którego żaden audyt kodu
nie mógł złapać, bo to nie jest błąd w kodzie — treść sprzedażowa, której żąda zamówienie,
jest przypięta do **napisu wpisanego przez właściciela w formularzu**, a nie do produktu.
Wystarczy, że nazwie produkt inaczej albo doda trzeci, i strona sprzedażowa znika bez słowa.

**Po trzecie:** cztery wiersze `SPEŁNIONE INACZEJ` poza `S-03` (`S-08`, `S-11`, `S-12`, `S-13`)
to odstępstwa, które **sam dokument zamówienia już opisuje**. Ich wartość nie polega na tym,
że je znalazłem — polega na tym, że od dziś kwadrat przy nich nie jest pusty i nikt nie musi
ich sprawdzać drugi raz.
