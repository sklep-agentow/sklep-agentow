# Sklep Agentów — dokumentacja techniczna

**Wersja wtyczki: 0.9.35 · wersja schematu bazy: 10 · WordPress 6.7+ · PHP 8.1+**

> **Po co ten dokument.** To jest **mapa systemu dla audytora i programisty**, nie ulotka.
> Każda liczba i nazwa tutaj jest wzięta z kodu, nie z pamięci — jeśli coś się rozjedzie,
> to jest błąd do zgłoszenia. Instrukcja dla użytkownika jest osobno.

---

## 1. Co robi wtyczka

Sprzedaje pliki cyfrowe (wtyczki-boty) bezpośrednio ze strony na WordPressie:
lista produktów i strony sprzedażowe → płatność kartą przez Stripe Checkout →
mail z linkiem do pobrania → panel zamówień, statystyk i dziennika logowań.

**Zaplecze w całości w WordPressie** — własne tabele, własne strony przez skróty,
zero zewnętrznych bibliotek PHP; jedyny skrypt z sieci to Stripe.js (`js.stripe.com`), ładowany wyłącznie na stronie
produktu i tylko z kluczem publikowalnym (od 0.9.25 — formularz karty musi być ramką Stripe). Jedyna usługa zewnętrzna: **Stripe** (płatność).

---

## 2. Architektura — gdzie co siedzi

| Plik | Odpowiedzialność |
|---|---|
| `sklep-agentow.php` | ładowanie, stałe, skróty, assety frontu, lista produktów, cron |
| `includes/aktywacja.php` | schemat bazy (dbDelta), migracje, zakładanie stron |
| `includes/stripe.php` | komunikacja z API Stripe |
| `includes/zakup.php` | rozpoczęcie zakupu, webhook, status, domykanie zamówień, treść tekstowa maila i wysyłka |
| `includes/mail-html.php` | wersja HTML maila po zakupie (wysyłana razem z tekstem jako multipart) |
| `includes/pobieranie.php` | brama pobrań: token, limit, wygaśnięcie, wydanie pliku |
| `includes/front-produkt.php` | strony produktów, przepisywanie adresów, treści sprzedażowe |
| `includes/front-seo.php` | tytuły, opisy, canonical (własny, gdy motyw wyłączył `rel_canonical` WP), dane strukturalne, mapa witryny |
| `includes/logowania.php` | dziennik logowań administratorów, opóźnienie i limit prób |
| `includes/admin*.php` | panel: pulpit, produkty, zamówienia, opinie, ustawienia, dziennik |
| `includes/opinie.php` | opinie kupujących: zalążek i token przy mailu, formularz, moderacja |
| `includes/prywatnosc.php` | RODO: czysta anonimizacja zamówienia (wołana z panelu i z kreatora), eksporter i kasownik `wp_privacy_personal_data_*`, podpowiedź do polityki prywatności |
| `includes/class-sklepag-lista-{zamowien,produktow,logowan,zdarzen}.php` | tabele list w panelu (na `WP_List_Table` z rdzenia): sortowanie, stronicowanie, filtry |
| `includes/class-sklepag-sitemap-produkty.php` | dostawca stron produktów do mapy witryny WordPressa |

---

## 3. Schemat bazy — 7 tabel, prefiks `wp_sklepag_`

⛔ **Wszystkie dane wtyczki żyją we własnych tabelach.** W `wp_options` trzymane są
wyłącznie ustawienia i klucze — nigdy liczniki ani stan zamówień.

| Tabela | Po co jest | Kto zapisuje |
|---|---|---|
| `produkty` | katalog: nazwa, adres, opis, cena, plik, aktywność | panel (CRUD) |
| `sesje_checkout` | ślad rozpoczętych płatności; kolumna `stan` **współrozstrzyga**, czy powtórne kliknięcie zakupu dostaje zapamiętany adres checkoutu, czy nowy; kolumny `produkt_*` niosą **migawkę produktu z chwili zapłaty** (patrz niżej) | rozpoczęcie zakupu |
| `zamowienia` | ⭐ **jedyne źródło prawdy o stanie zakupu** | webhook + reconciliation |
| `zdarzenia` | dziennik zdarzeń zamówienia (audyt ścieżki); retencja 90 dni — kasuje krok 4 crona `sklepag_dociagnij_zalegle` (`zakup.php`) | wszystkie moduły zakupu |
| `logowania` | kto i kiedy logował się do panelu, z jakim skutkiem; retencja 90 dni (ten sam cron) | zaczepy logowania WP |
| `rate_limit` | okna limitów żądań | brama zakupu i logowania |
| `opinie` | mechanizm opinii kupujących: zalążek+token przy mailu, treść z formularza, statusy moderacji | mail zakupowy → formularz kupującego → panel (moderacja) |

### Tabela `opinie` — obieg

Jedna opinia na zamówienie (`UNIQUE zamowienie_id`) — weryfikacja zakupu z danych
zamówień, bez kont kupujących. Statusy: `zalazek` (mail z linkiem wysłany, token
sha256 w `token_hash`, ważny `SKLEPAG_OPINIA_DNI` dni) → `oczekuje` (kupujący
wysłał formularz; token spalony — wpis `''`) → `przyjeta` / `odrzucona`
(moderacja w panelu, pomyłkę można cofnąć w obie strony). Na stronie produktu
renderują się WYŁĄCZNIE `przyjeta` (`sklepag_opinie_z_bazy` → `sklepag_render_opinie`).
Retry maila zakupowego i ręczny resend z panelu odświeżają token w stanie `zalazek` (i zerują
`pobrania_uzyte` zamówienia — świeży link = pełny limit)
(resend ZAKŁADA zalążek także zamówieniu, które go nie miało — np. sprzed mechanizmu);
opinii przysłanej żadna ścieżka nie rusza.
Formularz (`admin-post.php?action=sklepag_ocen`) wymaga zgody na publikację imienia
(treść zgody: `SKLEPAG_OPINIA_ZGODA_TRESC`, zapis w `zgoda_tresc`/`zgoda_czas`),
ma pole-pułapkę i limity żądań; twarde sufity długości: imię 100 · tytuł 150 · treść 2000.

### Tabela `zamowienia` — kolumny istotne dla audytu
`stripe_session_id` (klucz obcy do Stripe) · `produkt_nazwa_kupiona`,
`produkt_cena_kupiona`, `produkt_plik_kupiony` — **kopie z chwili zakupu**, żeby
późniejsza edycja produktu nie zmieniła historii · `status` (`oczekuje` → `oplacone`
→ `mail_wyslany`) · `token_pobrania`, `link_wygasa`, `pobrania_limit`,
`pobrania_uzyte` — brama pobrań · `zgoda_tresc`, `zgoda_czas` — dowód zgody
(RODO) · `mail_tresc` — wysłana treść (czyszczona przy anonimizacji) · `dor_token_pobrania`, `dor_link_wygasa`,
`dor_mail_tresc`, `dor_opinia_hash` — ślad ostatniej DORĘCZONEJ wysyłki (0.9.28, schemat 9):
zapisywany w tym samym zapytaniu co status `mail_wyslany`, a kompensacja nieudanej rotacji
linku (cron i „Wyślij ponownie") przywraca właśnie ten stan, nie zastany; czyszczony przy
anonimizacji · `usunieto_dnia` — znacznik anonimizacji ·
`dane_faktury` — nazwa firmy, NIP i adres podane przez kupującego (0.9.25; czyszczone przy anonimizacji) ·
`payment_intent_id` — identyfikator płatności Stripe, jedyne powiązanie zdarzeń o zwrocie
z zamówieniem · `zwrocono_grosze`, `zwrot_czas` — księgowanie zwrotów i obciążeń zwrotnych ·
`status_przed_sporem` — stan sprzed zdarzenia finansowego, żeby powrót nie zmyślał historii
wysyłki (zamówienie bez maila wraca do `mail_blad`, nie do „wysłany"). Mimo nazwy zapisują ją
i czyszczą OBIE ścieżki: obciążenie zwrotne (spór) oraz zwykły zwrot pieniędzy. Punkt powrotu
ustala pierwsze z tych zdarzeń — kolejne go nie nadpisuje.

**Statusy zamówienia:** `oczekuje` → `oplacone` → `mail_w_toku` → `mail_wyslany`;
`mail_blad` przy nieudanej wysyłce (cron ponawia do trzech prób; od 0.9.28 zapis `mail_blad`
wymaga dowodu własności tokenu w warunku — pad procesu, któremu wysyłkę odebrano po 5 minutach,
nie nadpisuje statusu doręczonej wysyłki drugiego procesu); `zwrocone` po zwrocie
pełnej kwoty; `sporne` po otwarciu obciążenia zwrotnego. Dwa ostatnie **nie liczą się
do przychodu** na Pulpicie; zwrot częściowy zostawia status bez zmian, a jego kwota schodzi
z sum przez odjęcie `zwrocono_grosze`. Spór wygrany przywraca zamówienie do przychodu.

Mail po zakupie (`sklepag_zbuduj_mail`, tekstowy): produkt i kwota → **potwierdzenie zgody**
(dosłowna `zgoda_tresc` + `zgoda_czas` w czasie lokalnym; dyrektywa 2011/83/UE art. 8 ust. 7
lit. b — potwierdzenie umowy na trwałym nośniku zawiera potwierdzenie zgody z art. 16 lit. m)
→ link pobrania z ważnością i limitem → instrukcja w skrócie → gwarancja z danych produktu
(gdy produkt ją ma) → link do opinii → „odpisz na tego maila". Wysyłka
`sklepag_wyslij_mail_sklepu`: nadawca z ustawień + nagłówek `Reply-To` na „mail kontaktowy
właściciela" (gdy ustawiony). Treść i nagłówki pilnuje osobny test-strażnik (bez realnej wysyłki).

---

## 4. Punkty wejścia — pełna lista

### Publiczne (dostępne bez logowania)
| Punkt | Co robi | Zabezpieczenie |
|---|---|---|
| `admin_post_nopriv_sklepag_kup` | rozpoczyna zakup | nonce + zgoda + limit żądań na adres IP |
| `admin_post_nopriv_sklepag_pobierz` | wydaje kupiony plik | token 64 znaki + limit pobrań + termin ważności |
| `admin_post_nopriv_sklepag_ocen` (GET) | formularz opinii z linku w mailu | token 64 znaki (sha256 w bazie, stałoczasowe) + termin ważności + limit żądań |
| `admin_post_nopriv_sklepag_ocen_zapisz` (POST) | zapis opinii (zalążek → oczekuje) | ten sam token jednorazowy + zgoda + pole-pułapka + limit żądań + sufity długości |
| `POST /wp-json/sklepag/v1/webhook` | powiadomienie od Stripe | **podpis webhooka Stripe** |
| `GET /wp-json/sklepag/v1/status` | status zamówienia dla strony „Dziękujemy" | identyfikator sesji |

⛔ **Endpoint oddaje kolumnę `zamowienia.status` WPROST**, więc `assets/dziekujemy.js` musi znać
**każdy** status, jaki kod potrafi tam zapisać: `oczekuje`, `oplacone`, `mail_w_toku`,
`mail_wyslany`, `mail_blad`, `zwrocone`, `sporne` (plus syntetyczne `brak` z endpointu).
Dwóch ostatnich długo nie znał i kupujący **po zwrocie pieniędzy** czytał „Przetwarzamy Twoją
płatność…". Statusy `mail_wyslany`, `mail_blad`, `zwrocone` i `sporne` są **końcowe** — strona
przestaje wtedy odpytywać. Status spoza listy dostaje neutralne zdanie zapasowe, które
**nie twierdzi niczego o pieniądzach**: nowy status dopisany w przyszłości nie ma prawa wrócić
z tą samą wadą.

**Obsługiwane zdarzenia Stripe.** Sprzedaż: `checkout.session.completed` i
`checkout.session.async_payment_succeeded` (domknięcie zakupu; bramka na `payment_status`,
bo przy metodach asynchronicznych, np. BLIK, pierwsze powiadomienie przychodzi jako
nieopłacone), `checkout.session.async_payment_failed` i `checkout.session.expired`.
Zwroty: `refund.created`, `refund.updated` i `charge.refunded` — Stripe zaleca dziś
`refund.created`, ale `charge.refunded` nadal przychodzi, więc nasłuchujemy obu i
księgujemy tak, żeby dwa powiadomienia o jednym zwrocie nie odjęły pieniędzy dwa razy.
Obciążenia zwrotne: `charge.dispute.*`. Zdarzenia o zwrocie nie niosą identyfikatora
sesji checkout — powiązanie idzie po `payment_intent_id` zapisanym przy zakupie, a dla
zamówień sprzed wersji schematu 6 przez jednorazowe zapytanie do Stripe o sesję tej płatności
(nietrafienie zapamiętywane na dobę, żeby cudze zwroty na wspólnym koncie nie odpytywały
Stripe w kółko). Zapytanie z banku (`warning_needs_response`) NIE zdejmuje pieniędzy — dopiero
pobranie środków albo przegrany spór; zwrot środków i spór wygrany przywracają sprzedaż.
Zwrot ze stanem `failed`/`canceled` wraca do przychodu. Zwroty liczone są po identyfikatorze
zwrotu, więc dwa różne zwroty częściowe o tej samej kwocie sumują się poprawnie.

### Panel (wymagają uprawnień administratora)
`sklepag_produkt_zapisz` · `sklepag_produkt_przelacz` · `sklepag_resend`
`sklepag_anonimizuj` · `sklepag_dociagnij` · `sklepag_eksport_csv` · `sklepag_opinia_moderuj`
— **każdy ma parę: nonce + `current_user_can( 'manage_options' )`**.

### Zadanie cykliczne
`sklepag_dociagnij_zalegle` — co 10 minut; domyka zamówienia, dla których webhook
nie dotarł (druga, niezależna droga domykania).

---

## 5. Przepływ zakupu — ścieżka szczęśliwa i awaryjna

```
gość klika „Kup teraz"
   → walidacja zgody + limit żądań
   → utworzenie sesji Stripe Checkout          [tabela sesje_checkout]
   → (od 0.9.25, gdy jest klucz publikowalny) JSON z client_secret → formularz karty
     na stronie produktu (assets/platnosc.js, elementy Stripe w ramce) → confirm()
   → (zapas: bez klucza/JS) przekierowanie do Stripe (płatność kartą)
   → powrót na stronę „Dziękujemy" (odpytuje /status)

RÓWNOLEGLE, dwa niezależne silniki domykania — TU powstaje wiersz zamówienia:
   A) webhook od Stripe        → sklepag_domknij_zakup(): zamówienie „oczekuje"
                                 [tabela zamowienia] → status „oplacone" → mail z linkiem
   B) cron co 10 min           → to samo, gdy webhook nie dotarł
   UWAGA: sklepag_obsluz_kup() NIE dotyka tabeli zamowienia ani razu. Ten krok nie należy
   do ścieżki liniowej kupującego i często dzieje się ZANIM wróci on na stronę podziękowania.
```

⭐ **Kluczowa zasada: przejęcie zamówienia jest atomowe.** Każde przejście stanu to
jeden `UPDATE` z warunkiem na poprzedni stan — silnik, który przegra wyścig, wycofuje
się **bez wysyłania drugiego maila**. Źródłem prawdy o ZAMÓWIENIU jest wyłącznie `zamowienia.status`.

### Migawka produktu w `sesje_checkout` (schemat 10)

Produkt wolno zmienić **między utworzeniem płatności a jej domknięciem**: właściciel podmienia
plik, zmienia cenę albo wycofuje produkt ze sprzedaży. Do 0.9.30 domknięcie czytało tabelę
produktów **ponownie** i z tego świeżego odczytu zapisywało do zamówienia `produkt_nazwa_kupiona`,
`produkt_cena_kupiona` i `produkt_plik_kupiony` — czyli kolumny „kupiona" zamrażały stan
z chwili **domknięcia**, nie zakupu. Kupujący płacił za jedno, a dostawał drugie.

Od 0.9.31 checkout zapisuje razem z sesją `produkt_nazwa`, `produkt_slug`, `produkt_cena_grosze`,
`produkt_waluta` i `produkt_plik`, a domknięcie bierze produkt **wyłącznie stamtąd**. Ścieżka
pliku wystarczy, bo każde wgranie w panelu dostaje własną, losową nazwę (`produkt-<20 znaków>`) —
podmiana pliku nie nadpisuje starej ścieżki.

⭐ Skutek uboczny, który jest **poprawny**: domknięcie przestaje zależeć od tego, czy produkt
nadal jest `aktywny` i czy w ogóle jeszcze istnieje. `aktywny = 0` blokuje **nowe** zakupy
(checkout pyta o nie), a nie doręczenie zakupu już opłaconego; wcześniej skasowanie produktu
między zapłatą a webhookiem kończyło się `domkniecie_brak_produktu` — pieniądze pobrane,
zamówienie nie powstawało wcale.

⚠️ Sesje utworzone **przed** migracją mają migawkę pustą; dla nich domknięcie sięga po produkt
jak dawniej — zakupy wiszące w locie w chwili aktualizacji nie mogą zostać bez obsługi.

⚠️ `sesje_checkout` nie jest już wyłącznie śladem informacyjnym: od 0.9.18 jej kolumna `stan`
bierze udział w decyzji, czy powtórne kliknięcie przycisku zakupu w oknie idempotencji dostanie
zapamiętany adres checkoutu, czy nowy (`sklepag_sesja_czeka_na_platnosc()`). Warunkiem
NADRZĘDNYM jest tam wiek okna (`SKLEPAG_IDEM_OKNO_S`), bo `stan` przestawia dopiero webhook —
zanim dotrze, sesja opłacona leży w bazie jako `utworzona` i jest nieodróżnialna od czekającej.
Na stan zamówienia ta tabela nadal nie wpływa.

## 6. Brama pobrań

Link z maila zawiera token (64 znaki). Przy każdym pobraniu sprawdzane są **trzy
warunki naraz**: token istnieje, nie minął termin (`SKLEPAG_LINK_GODZIN` = **72 h**),
licznik nie przekroczył limitu (`SKLEPAG_LIMIT_POBRAN` = **10**; licznik jest zerowany przy każdym
wydaniu świeżego linku — resend z panelu i ponowienie przez cron — więc limit jest na link, nie na zakup). Plik wydawany jest strumieniem
przez PHP (`readfile()` z nagłówkami `Content-Disposition` i `X-Robots-Tag: noindex`)
— **nigdy przez publiczny adres**.

### ⚠️ Katalog plików produktów — rzecz, o której trzeba wiedzieć przy wdrożeniu
Pliki leżą w katalogu wewnątrz `uploads` z **losowym sufiksem w nazwie**, zabezpieczonym
plikami `index.php` i `.htaccess`. ⛔ **`.htaccess` działa na Apache'u, a na nginx jest
ignorowany.** Prawdziwą ochroną jest brama w PHP (token, termin, limit) i losowa nazwa
katalogu — ale na nginx **plik jest teoretycznie osiągalny, jeśli ktoś zgadnie pełną
ścieżkę**. Przy wdrożeniu na nginx należy dołożyć regułę blokującą ten katalog
po stronie serwera. To jest świadome ograniczenie, nie przeoczenie.

---

## 7. Stałe konfiguracyjne

| Stała | Wartość | Znaczenie |
|---|---|---|
| `SKLEPAG_WERSJA` | 0.9.35 | wersja wtyczki (assety, cache) |
| `SKLEPAG_WERSJA_SCHEMATU` | 10 | wersja schematu bazy (migracje) |
| `SKLEPAG_LIMIT_POBRAN` | 10 | ile razy można pobrać plik z jednego wydanego linku (zerowane przy świeżym linku) |
| `SKLEPAG_LINK_GODZIN` | 72 | ważność linku pobrania |
| `SKLEPAG_OPINIA_DNI` | 30 | ważność jednorazowego linku „oceń bota" |
| `SKLEPAG_OPINIA_ZGODA_TRESC` | (zdanie zgody) | treść zgody na publikację opinii z imieniem |

⛔ **Każda z tych liczb pojawia się w treściach dla klienta** (mail, FAQ, strona
produktu) **generowana ze stałej**, nie wpisana ręcznie — dokumentacja nie może
rozjechać się z kodem.

### Tryb testowy — jedno źródło prawdy

`sklepag_tryb_testowy()` (`sklep-agentow.php`) orzeka tryb z **prefiksu klucza
sekretnego Stripe** w opcji `sklepag_stripe_klucz_sekretny`. Osobnej opcji trybu
**nie ma i nie ma jej przybyć**: przełącznik, który trzeba pamiętać przestawić,
jest tą samą miną co napis wpisany na sztywno, tylko z dodatkowym krokiem.

| Wartość opcji | Orzeczenie |
|---|---|
| `sk_test_…` | tryb testowy — wolno pisać, że płatność nie jest pobierana |
| `sk_live_…` | sprzedaż na serio — żadnych napisów o trybie testowym |
| pusta / nierozpoznany prefiks | **jak produkcyjny** (fail-closed) |

⛔ **Fail-closed, nie fail-open.** Zdanie „płatności nie są pobierane" to obietnica
wobec kupującego, nie ostrzeżenie: wolno ją wypowiedzieć tylko wtedy, gdy da się
ją udowodnić. Brak klucza właściciel i tak widzi osobno — Pulpit zgłasza wtedy
„Sklep nie przyjmie płatności".

Pytają o to **wszystkie** miejsca, nigdy o obecność napisu: baner
(`sklepag_baner_testowy()`, strona sklepu / produktu / „Dziękujemy"), etykiety CTA
i notka z kartą testową (`includes/front-produkt.php`), obie wersje maila
(`includes/mail-html.php`, `includes/zakup.php`), Pulpit i szczegóły zamówienia,
sekcja Stripe w Ustawieniach.

⛔ `assets/platnosc.js` **nadpisuje etykiety przycisków po renderze** („Otwieram
płatność…", „Przetwarzam…") i odtwarza je z wartości **zapamiętanej z DOM-u przy
starcie** — własny literał w tym pliku cofałby naprawę PHP w przeglądarce.

---

## 8. Instalacja i deinstalacja

**Przy aktywacji:** utworzenie 7 tabel przez `dbDelta`, zapis wersji schematu,
założenie stron „Sklep" i „Dziękujemy", zaplanowanie zadania cyklicznego.

**Przy deaktywacji:** odplanowanie zadania cyklicznego. Dane zostają.

**Przy odinstalowaniu:** domyślnie dane zostają; przy włączonej opcji pełnego
czyszczenia usuwane są tabele, ustawienia i strony. ⛔ **Klucze Stripe i zaplanowane
zadanie cykliczne kasowane są ZAWSZE**, niezależnie od tej opcji (martwy wpis
w cronie bez wtyczki tylko sypie błędami).

**Aktualizacja wtyczki „w miejscu"** (Wtyczki → Aktualizuj teraz) NIE odpala aktywacji.
Migracja schematu i odtworzenie chronionego katalogu na pliki produktów dzieją się same
przy pierwszym wejściu do panelu albo przy zadaniu cyklicznym — deaktywacja i ponowna
aktywacja nie są potrzebne.

**Wycofanie wersji (rollback).** Wgranie starszej wersji wtyczki NIE cofa schematu bazy:
dołożone kolumny zostają i nie przeszkadzają starszemu kodowi, bo mają wartości domyślne.
Cofnięcie poniżej wersji, która wprowadziła daną kolumnę, oznacza jednak, że dane w niej
przestaną być aktualizowane (np. zapis zwrotów). Zalecana kolejność przy wycofaniu:
kopia bazy → wgranie starszej paczki → sprawdzenie Pulpitu. Przy powrocie do nowszej wersji
migracja dopisze brakujące kolumny sama.

**Zadanie cykliczne a `DISABLE_WP_CRON`.** Domykanie zaległych zamówień (co 10 minut) jedzie
na harmonogramie WordPressa. Jeśli w `wp-config.php` stoi `define( 'DISABLE_WP_CRON', true );`
— częste na hostingach z własnym harmonogramem systemowym — zadanie NIE uruchomi się samo
z ruchu na stronie i trzeba mieć wpis w systemowym harmonogramie, np. co 5 minut:
`wget -q -O - https://TWOJA-DOMENA/wp-cron.php?doing_wp_cron >/dev/null 2>&1`.
Bez tego zamówienia opłacone w chwili, gdy powiadomienie od Stripe nie dotarło, poczekają
na mail do najbliższego wejścia do panelu.

---

## 8a. Mechanizm prywatności WordPressa (RODO)

Sklep trzyma dane osobowe kupujących, więc jest wpięty w narzędzia wbudowane —
**Narzędzia → Eksportuj / Usuń dane osobowe** oraz **Ustawienia → Prywatność**.
Wcześniej miał wyłącznie własny przycisk anonimizacji w panelu: kreator WordPressa
składał raport **bez danych ze sklepu**, a obsługa uznawała żądanie za wypełnione.

| Zaczep | Funkcja | Co robi |
|---|---|---|
| `wp_privacy_personal_data_exporters` | `sklepag_rodo_eksporter()` | wydaje zamówienia, opinie i sesje płatności powiązane z adresem; **stronicuje** (`SKLEPAG_RODO_PORCJA` = 100 pozycji na stronę) |
| `wp_privacy_personal_data_erasers` | `sklepag_rodo_kasownik()` | anonimizuje wszystkie zamówienia adresu; `items_retained` + powód, gdy zostają dane do faktury |
| `wp_add_privacy_policy_content()` | `sklepag_polityka_prywatnosci()` | gotowy akapit do polityki prywatności |

**Jedna anonimizacja, dwa wejścia.** `sklepag_anonimizuj_zamowienie( int $id, bool $zachowaj_dane_faktury )`
jest czystą funkcją: zwraca wynik zamiast kończyć proces. Woła ją panelowa skorupa
`sklepag_obsluz_anonimizacje()` (uprawnienia, nonce, `$_GET`, `wp_die`) oraz kasownik kreatora.
Kolejność operacji jest kontraktem: najpierw ślady **wtórne** (dziennik zdarzeń, opinia),
na końcu **źródło** (adres w zamówieniu) — źródło jest kluczem do znalezienia śladów, więc
ginie ostatnie, a każda porażka zostawia je nietknięte, żeby powtórzenie miało z czego
odtworzyć zakres. Potwierdzenie usunięcia idzie wyłącznie za **zmierzonym stanem po zapisie**,
a pomiar jest **tak szeroki jak zapis i zależny od trybu**: obejmuje wszystkie kolumny niosące
dane osobowe (adres, treść maila, ślad `dor_*`, tokeny, dane do faktury) — przy czym
`dane_faktury` mierzona jest na pusto tylko w trybie panelu; w trybie kasownika ta kolumna ma
zostać, więc twardy pomiar na pusto zamieniłby wymóg podatkowy w meldunek o nieudanym zapisie.
Kolumny `link_wygasa`, `dor_link_wygasa` i `zmieniono` są poza pomiarem świadomie: to znaczniki
czasu bez danych osobowych, bezużyteczne bez tokenu, który mierzony jest.

**Dane do faktury zostają po wniosku RODO** (nazwa, NIP, adres) — obowiązek prawny:
art. 6 ust. 1 lit. c RODO w związku z ustawą o VAT (art. 106e ust. 1 pkt 3 wymaga adresu
nabywcy). Kasownik zwraca je w `items_retained` z powodem, a nie po cichu. Przycisk w panelu
działa jak dotąd i czyści **wszystko** — to świadoma decyzja właściciela, nie wniosek.

**Co ZOSTAJE po wniosku o usunięcie — i jest zgłaszane w `items_retained` z powodem:**
1. **Dane do faktury** (nazwa, NIP, adres) — obowiązek prawny, jak wyżej.
2. **Identyfikatory u operatora płatności**: `zamowienia.stripe_session_id`,
   `zamowienia.payment_intent_id` i cały wiersz w `sesje_checkout`. Nie jest to wygoda, tylko
   warunek poprawności obsługi pieniędzy: `zakup.php` odnajduje zamówienie po
   `payment_intent_id` przy zwrocie i przy obciążeniu zwrotnym, a `stripe_session_id` niesie
   idempotencję domknięcia zakupu (powtórzony webhook trafia w istniejące zamówienie zamiast
   tworzyć drugie). Wyczyszczenie ich sprawiłoby, że zwrot zgłoszony PO wniosku RODO nie miałby
   czego znaleźć. Eksporter je **wydaje**, kasownik je **zgłasza** — jedno i drugie, nigdy
   milczenie o jednym z nich.

**Czego eksporter i kasownik NIE obejmują w ogóle:** `logowania` i `rate_limit` (klucz to adres
IP albo login administratora — nie da się ich powiązać z adresem e-mail kupującego) oraz adresów
IP w `zdarzenia`. Wszystkie trzy czyści retencja `SKLEPAG_RETENCJA_DNI` (90) dni. Nie obejmują
też retencji czasowej zamówień — to osobna decyzja właściciela.

⛔ **Reguła, z której to wynika:** cokolwiek eksporter wydaje jako dane osobowe, kasownik musi
albo usunąć, albo zgłosić w `items_retained` z powodem. Milczenie o zachowanej danej jest gorsze
niż jej zachowanie: właściciel odpowiada komunikatem kasownika osobie składającej wniosek.

---

## 9. Granice — czego ta wtyczka NIE robi

Nie zakłada kont kupujących · nie obsługuje płatności cyklicznych ani abonamentów ·
nie zbiera opinii od GOŚCI i nie liczy ocen zbiorczych (formularz opinii działa
wyłącznie z jednorazowego linku w mailu zakupowym — jedna opinia na zamówienie,
publikacja dopiero po ręcznej akceptacji w panelu; bez `aggregateRating` w danych
strukturalnych) · nie robi przecen
ani kuponów · nie dotyka motywu ani cudzych wtyczek · nie wysyła danych nigdzie
poza Stripe.

---

## 10. ⭐ MAPA DLA AUDYTORA — gdzie patrzeć najuważniej

> Ta sekcja istnieje po to, żeby audyt nie zaczynał się od zera. Wskazuje miejsca,
> w których **historycznie powstają błędy w tej klasie systemów** — nie twierdzi,
> że tam są.

| # | Miejsce | Pytanie, które warto zadać |
|---|---|---|
| 1 | dwa silniki domykania (webhook + cron) | czy przy równoległym przejęciu klient dostanie **dokładnie jeden** mail? |
| 2 | brama pobrań | czy da się pobrać po terminie, po przekroczeniu limitu, cudzym tokenem? |
| 3 | kopie danych produktu w zamówieniu | czy edycja produktu zmienia **historyczne** zamówienie? |
| 4 | webhook | co się dzieje przy podpisie niepoprawnym, powtórzonym, spóźnionym? |
| 5 | limit żądań | czy mierzy wymiar, który realnie robi szkodę? co przy awarii zapytania? |
| 6 | anonimizacja (RODO) | czy po anonimizacji da się jeszcze odtworzyć dane osobowe z innych tabel? |
| 7 | wydawanie pliku | czy plik jest osiągalny **z pominięciem** bramy, bezpośrednim adresem? |
| 8 | dziennik logowań | czy opóźnienie prób nie blokuje całego panelu przy salwie żądań? |
| 9 | deinstalacja | czy diff bazy przed instalacją i po odinstalowaniu jest pusty **obiema ścieżkami**? |
| 10 | treści dla klienta | czy każda liczba w mailu, FAQ i na stronie produktu pochodzi ze stałej? |
