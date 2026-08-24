# Sklep Agentów — dokumentacja techniczna

**Wersja wtyczki: 0.9.7 · wersja schematu bazy: 4 · WordPress 6.7+ · PHP 8.1+**

> **Po co ten dokument.** To jest **mapa systemu dla audytora i programisty**, nie ulotka.
> Każda liczba i nazwa tutaj jest wzięta z kodu, nie z pamięci — jeśli coś się rozjedzie,
> to jest błąd do zgłoszenia. Instrukcja dla użytkownika jest osobno.

---

## 1. Co robi wtyczka

Sprzedaje pliki cyfrowe (wtyczki-boty) bezpośrednio ze strony na WordPressie:
lista produktów i strony sprzedażowe → płatność kartą przez Stripe Checkout →
mail z linkiem do pobrania → panel zamówień, statystyk i dziennika logowań.

**Zaplecze w całości w WordPressie** — własne tabele, własne strony przez skróty,
zero zewnętrznych bibliotek i zero CDN. Jedyna usługa zewnętrzna: **Stripe** (płatność).

---

## 2. Architektura — gdzie co siedzi

| Plik | Odpowiedzialność |
|---|---|
| `sklep-agentow.php` | ładowanie, stałe, skróty, assety frontu, lista produktów, cron |
| `includes/aktywacja.php` | schemat bazy (dbDelta), migracje, zakładanie stron |
| `includes/stripe.php` | komunikacja z API Stripe |
| `includes/zakup.php` | rozpoczęcie zakupu, webhook, status, domykanie zamówień |
| `includes/pobieranie.php` | brama pobrań: token, limit, wygaśnięcie, wydanie pliku |
| `includes/front-produkt.php` | strony produktów, przepisywanie adresów, treści sprzedażowe |
| `includes/front-seo.php` | tytuły, opisy, dane strukturalne, mapa witryny |
| `includes/logowania.php` | dziennik logowań administratorów, opóźnienie i limit prób |
| `includes/admin*.php` | panel: pulpit, produkty, zamówienia, ustawienia, dziennik |

---

## 3. Schemat bazy — 6 tabel, prefiks `wp_sklepag_`

⛔ **Wszystkie dane wtyczki żyją we własnych tabelach.** W `wp_options` trzymane są
wyłącznie ustawienia i klucze — nigdy liczniki ani stan zamówień.

| Tabela | Po co jest | Kto zapisuje |
|---|---|---|
| `produkty` | katalog: nazwa, adres, opis, cena, plik, aktywność | panel (CRUD) |
| `sesje_checkout` | ślad rozpoczętych płatności — **informacyjnie** | rozpoczęcie zakupu |
| `zamowienia` | ⭐ **jedyne źródło prawdy o stanie zakupu** | webhook + reconciliation |
| `zdarzenia` | dziennik zdarzeń zamówienia (audyt ścieżki) | wszystkie moduły zakupu |
| `logowania` | kto i kiedy logował się do panelu, z jakim skutkiem | zaczepy logowania WP |
| `rate_limit` | okna limitów żądań | brama zakupu i logowania |

### Tabela `zamowienia` — kolumny istotne dla audytu
`stripe_session_id` (klucz obcy do Stripe) · `produkt_nazwa_kupiona`,
`produkt_cena_kupiona`, `produkt_plik_kupiony` — **kopie z chwili zakupu**, żeby
późniejsza edycja produktu nie zmieniła historii · `status` (`oczekuje` → `oplacone`
→ `mail_wyslany`) · `token_pobrania`, `link_wygasa`, `pobrania_limit`,
`pobrania_uzyte` — brama pobrań · `zgoda_tresc`, `zgoda_czas` — dowód zgody
(RODO) · `mail_tresc` — wysłana treść · `usunieto_dnia` — znacznik anonimizacji.

---

## 4. Punkty wejścia — pełna lista

### Publiczne (dostępne bez logowania)
| Punkt | Co robi | Zabezpieczenie |
|---|---|---|
| `admin_post_nopriv_sklepag_kup` | rozpoczyna zakup | nonce + zgoda + limit żądań na adres IP |
| `admin_post_nopriv_sklepag_pobierz` | wydaje kupiony plik | token 64 znaki + limit pobrań + termin ważności |
| `POST /wp-json/sklepag/v1/webhook` | powiadomienie od Stripe | **podpis webhooka Stripe** |
| `GET /wp-json/sklepag/v1/status` | status zamówienia dla strony „Dziękujemy" | identyfikator sesji |

### Panel (wymagają uprawnień administratora)
`sklepag_produkt_zapisz` · `sklepag_produkt_przelacz` · `sklepag_resend`
`sklepag_anonimizuj` · `sklepag_dociagnij` · `sklepag_eksport_csv`
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
   → zamówienie w stanie „oczekuje"            [tabela zamowienia]
   → przekierowanie do Stripe (płatność kartą)
   → powrót na stronę „Dziękujemy" (odpytuje /status)

RÓWNOLEGLE, dwa niezależne silniki domykania:
   A) webhook od Stripe        → status „oplacone" → mail z linkiem
   B) cron co 10 min           → to samo, gdy webhook nie dotarł
```

⭐ **Kluczowa zasada: przejęcie zamówienia jest atomowe.** Każde przejście stanu to
jeden `UPDATE` z warunkiem na poprzedni stan — silnik, który przegra wyścig, wycofuje
się **bez wysyłania drugiego maila**. Źródłem prawdy jest wyłącznie `zamowienia.status`;
`sesje_checkout` jest tylko śladem informacyjnym.

## 6. Brama pobrań

Link z maila zawiera token (64 znaki). Przy każdym pobraniu sprawdzane są **trzy
warunki naraz**: token istnieje, nie minął termin (`SKLEPAG_LINK_GODZIN` = **72 h**),
licznik nie przekroczył limitu (`SKLEPAG_LIMIT_POBRAN` = **10**). Plik wydawany jest strumieniem
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
| `SKLEPAG_WERSJA` | 0.9.7 | wersja wtyczki (assety, cache) |
| `SKLEPAG_WERSJA_SCHEMATU` | 4 | wersja schematu bazy (migracje) |
| `SKLEPAG_LIMIT_POBRAN` | 10 | ile razy można pobrać zakup |
| `SKLEPAG_LINK_GODZIN` | 72 | ważność linku pobrania |

⛔ **Każda z tych liczb pojawia się w treściach dla klienta** (mail, FAQ, strona
produktu) **generowana ze stałej**, nie wpisana ręcznie — dokumentacja nie może
rozjechać się z kodem.

---

## 8. Instalacja i deinstalacja

**Przy aktywacji:** utworzenie 6 tabel przez `dbDelta`, zapis wersji schematu,
założenie stron „Sklep" i „Dziękujemy", zaplanowanie zadania cyklicznego.

**Przy deaktywacji:** odplanowanie zadania cyklicznego. Dane zostają.

**Przy odinstalowaniu:** domyślnie dane zostają; przy włączonej opcji pełnego
czyszczenia usuwane są tabele, ustawienia i strony. ⛔ **Klucze Stripe i zaplanowane
zadanie cykliczne kasowane są ZAWSZE**, niezależnie od tej opcji (martwy wpis
w cronie bez wtyczki tylko sypie błędami — #170).

---

## 9. Granice — czego ta wtyczka NIE robi

Nie zakłada kont kupujących · nie obsługuje płatności cyklicznych ani abonamentów ·
nie prowadzi opinii ani ocen · nie robi przecen ani kuponów · nie dotyka motywu
ani cudzych wtyczek · nie wysyła danych nigdzie poza Stripe.

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
