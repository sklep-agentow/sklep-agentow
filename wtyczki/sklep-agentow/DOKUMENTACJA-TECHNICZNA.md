# Sklep Agentów — dokumentacja techniczna

**Wersja wtyczki: 0.9.25 · wersja schematu bazy: 8 · WordPress 6.7+ · PHP 8.1+**

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
| `includes/front-seo.php` | tytuły, opisy, dane strukturalne, mapa witryny |
| `includes/logowania.php` | dziennik logowań administratorów, opóźnienie i limit prób |
| `includes/admin*.php` | panel: pulpit, produkty, zamówienia, opinie, ustawienia, dziennik |
| `includes/opinie.php` | opinie kupujących: zalążek i token przy mailu, formularz, moderacja |
| `includes/class-sklepag-lista-{zamowien,produktow,logowan,zdarzen}.php` | tabele list w panelu (na `WP_List_Table` z rdzenia): sortowanie, stronicowanie, filtry |
| `includes/class-sklepag-sitemap-produkty.php` | dostawca stron produktów do mapy witryny WordPressa |

---

## 3. Schemat bazy — 7 tabel, prefiks `wp_sklepag_`

⛔ **Wszystkie dane wtyczki żyją we własnych tabelach.** W `wp_options` trzymane są
wyłącznie ustawienia i klucze — nigdy liczniki ani stan zamówień.

| Tabela | Po co jest | Kto zapisuje |
|---|---|---|
| `produkty` | katalog: nazwa, adres, opis, cena, plik, aktywność | panel (CRUD) |
| `sesje_checkout` | ślad rozpoczętych płatności; kolumna `stan` **współrozstrzyga**, czy powtórne kliknięcie zakupu dostaje zapamiętany adres checkoutu, czy nowy | rozpoczęcie zakupu |
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
(RODO) · `mail_tresc` — wysłana treść · `usunieto_dnia` — znacznik anonimizacji ·
`dane_faktury` — nazwa firmy, NIP i adres podane przez kupującego (0.9.25; czyszczone przy anonimizacji) ·
`payment_intent_id` — identyfikator płatności Stripe, jedyne powiązanie zdarzeń o zwrocie
z zamówieniem · `zwrocono_grosze`, `zwrot_czas` — księgowanie zwrotów i obciążeń zwrotnych ·
`status_przed_sporem` — stan sprzed zdarzenia finansowego, żeby powrót nie zmyślał historii
wysyłki (zamówienie bez maila wraca do `mail_blad`, nie do „wysłany"). Mimo nazwy zapisują ją
i czyszczą OBIE ścieżki: obciążenie zwrotne (spór) oraz zwykły zwrot pieniędzy. Punkt powrotu
ustala pierwsze z tych zdarzeń — kolejne go nie nadpisuje.

**Statusy zamówienia:** `oczekuje` → `oplacone` → `mail_w_toku` → `mail_wyslany`;
`mail_blad` przy nieudanej wysyłce (cron ponawia do trzech prób); `zwrocone` po zwrocie
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
| `SKLEPAG_WERSJA` | 0.9.25 | wersja wtyczki (assety, cache) |
| `SKLEPAG_WERSJA_SCHEMATU` | 8 | wersja schematu bazy (migracje) |
| `SKLEPAG_LIMIT_POBRAN` | 10 | ile razy można pobrać plik z jednego wydanego linku (zerowane przy świeżym linku) |
| `SKLEPAG_LINK_GODZIN` | 72 | ważność linku pobrania |
| `SKLEPAG_OPINIA_DNI` | 30 | ważność jednorazowego linku „oceń bota" |
| `SKLEPAG_OPINIA_ZGODA_TRESC` | (zdanie zgody) | treść zgody na publikację opinii z imieniem |

⛔ **Każda z tych liczb pojawia się w treściach dla klienta** (mail, FAQ, strona
produktu) **generowana ze stałej**, nie wpisana ręcznie — dokumentacja nie może
rozjechać się z kodem.

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
