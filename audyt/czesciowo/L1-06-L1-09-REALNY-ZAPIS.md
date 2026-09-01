# `L1-06` i `L1-09` — CRUD produktów i zapis ustawień **zmierzone realnym żądaniem**, nie grepem

**Kto:** audytor, 1.09 wieczór, ostatnie zadanie przed czyszczeniem kontekstu.
**Gałąź:** `audyt/czesciowo-01-09` (do PR #261). **Kodu produktu nie tknąłem.**
**Powód zlecenia:** oba cele miały werdykt pozytywny postawiony **grepem po HTML formularza** —
czyli nikt nigdy nie wysłał realnego żądania i nikt nie sprawdził, czy zapis DOSZEDŁ do bazy.

## Warunki, na które dostałem zgodę i których dotrzymałem

- **własny prefiks w danych**: każdy wiersz i każda wartość nosiły `AUDYT-L1-234555`;
- **sprzątanie po sobie** — dowód niżej, z porównaniem migawek przed/po;
- ⛔ **`admin-dashboard` nietknięty** (builder1 pracuje na tym samym stanowisku nad eksportem CSV);
  ruszałem wyłącznie ekrany **Produkty** i **Ustawienia**;
- kody wyjścia zamiast słowa „działa".

## Jak mierzyłem — to jest różnica wobec grepa

Prawdziwa sesja administratora: `POST /wp-login.php` (konto `audytor-admin`) → ciasteczka →
`GET` ekranu z formularzem → **wyciągnięty żywy `_wpnonce` z HTML** → `POST` na
`admin-post.php` / `options.php` dokładnie tym, co wysyła przeglądarka
(`multipart/form-data` z plikiem przy produkcie) → **`SELECT` z bazy** potwierdzający skutek.
Żadnego `wp eval` do wywołania handlera — handler wołał WordPress, po HTTP, z uwierzytelnieniem.

## `L1-06` — pełny CRUD produktu: **cztery żądania, cztery potwierdzenia z bazy**

| # | operacja | żądanie | odpowiedź | `SELECT` po |
|---|---|---|---|---|
| 1 | **DODANIE** (z wgraniem pliku) | `POST admin-post.php action=sklepag_produkt_zapisz`, `produkt_id=0`, `nazwa/opis/cena=12.34/waluta/plik` | **`HTTP=302`** → `…&produkt=98&sklepag_ok=plik` | `#98 · nazwa=AUDYT-L1-234555 produkt probny · slug=audyt-l1-234555-produkt-probny · cena_grosze=1234 PLN · aktywny=0 · plik zapisany (15 B, sha `d5108c261ddb…`, **istnieje na dysku: TAK**)` |
| 2 | **EDYCJA** (bez ponownego wgrywania pliku) | ten sam endpoint, `produkt_id=98`, `nazwa=… PO EDYCJI`, `cena=99.99` | **`HTTP=302`** → `…&sklepag_ok=zapisano` | `nazwa=…PO EDYCJI · cena_grosze=9999 · zmieniono=23:46:35 · plik ZACHOWANY` (slug **nie** zmieniony — adres produktu przeżywa zmianę nazwy) |
| 3 | **WŁĄCZENIE SPRZEDAŻY** | `admin-post.php action=sklepag_produkt_przelacz&produkt=98&_wpnonce=…` (link z listy, z żywym nonce) | **`HTTP=302`** → `…&sklepag_ok=przelaczono` | `aktywny=1` |
| 4 | **WYŁĄCZENIE** („usuwanie" wg `S-08`) | jw. | **`HTTP=302`** → `…&sklepag_ok=przelaczono` | `aktywny=0` · **wiersz nadal istnieje: TAK** (zgodnie z odstępstwem: deaktywacja zamiast kasowania) |

⭐ **Dziennik akcji zapisał wszystkie cztery operacje z LOGINEM sprawcy** — to jest dowód dla
`S-09` („log akcji admina"), którego wcześniej nikt nie pokazał z żywego przebiegu:
```
produkt_deaktywowany  #98 AUDYT-L1-234555 produkt PO EDYCJI | kto=audytor-admin | 23:47:07
produkt_aktywowany    #98 …                                | kto=audytor-admin | 23:46:54
produkt_zmieniony     #98 …                                | kto=audytor-admin | 23:46:35
produkt_dodany        #98 AUDYT-L1-234555 produkt probny    | kto=audytor-admin | 23:45:55
```

## `L1-09` — zapis ustawień: **jedno żądanie, jedno potwierdzenie z bazy**

`POST /wp-admin/options.php` z żywym nonce i **kompletem pól formularza** (sekrety Stripe
wysłane puste — kod ma regułę „puste = zachowaj starą wartość", więc to bezpieczne):
```
odpowiedź: HTTP=302 → …page=sklepag-ustawienia&settings-updated=true
SELECT:    sklepag_firma_nazwa = [AUDYT-L1-234555 firma probna]
```
Zapis **doszedł do `wp_options`** — cel `L1-09` ma pierwszy dowód z realnego żądania.

## ⚠️ ZNALEZISKO UBOCZNE — `L1-06-F1`, **MALE**: „plik POTWIERDZONY" nie znaczy „archiwum otwieralne"

Wgrałem plik **15-bajtowy**, który zaczyna się od `PK\003\004`, ale **nie jest poprawnym ZIP-em**
(`file` mówi `data`). Sklep przyjął go bez mrugnięcia i oznaczył jako potwierdzony
(`sklepag_ok=plik`, `plik_sha256` policzone, `plik_rozmiar=15`), czyli produkt był gotowy do
włączenia sprzedaży — co też zrobiłem (krok 3). Kod sprawdza **rozszerzenie, MIME z TREŚCI,
limit rozmiaru i obecność po zapisie** (`admin-produkty.php:328-329`), ale **nie sprawdza, czy
archiwum daje się otworzyć**.

**Dlaczego to nie jest teoria:** ten sam skutek daje **przerwane wgranie** (zerwana sieć,
timeout) — początek pliku jest poprawny, koniec urwany, MIME nadal `application/zip`.
Właściciel widzi „potwierdzony", włącza sprzedaż, a kupujący pobiera archiwum, którego nie da
się rozpakować. **`MALE`, nie wyżej:** wymaga zdarzenia po stronie właściciela, a nie zwykłej
ścieżki; sklep nie traci pieniędzy, traci je klient w reklamacji. ⭐ Naprawa mieści się
w jednej linii przy istniejącej „weryfikacji PO zapisie": `ZipArchive::open( …, CHECKCONS )`.

## Sprzątanie — dowód, że stanowisko wróciło do stanu zastanego

```
plik produktu skasowany: produkt-s1PPBIaUTMb2C2yINE6w (istnieje po: nie)
wiersz produktu skasowany: 1 · wiersze dziennika z prefiksem AUDYT-L1-: 4 · opcja sklepag_firma_nazwa: skasowana
produkty: 2 (przed 2) · zamowienia: 44 (przed 44)
diff migawek opcji przed/po → ✅ IDENTYCZNE
```
⛔ **Jedna rzecz wymagała naprawy po mnie i wypisuję ją, bo to moja pomyłka:** formularz ustawień
wysyła checkbox **tylko gdy jest zaznaczony**, więc mój POST (bez tego pola) **wyzerował**
`sklepag_usun_dane_przy_odinstalowaniu` z `1` na puste. Wyłapała to migawka opcji z hashami
zrobiona PRZED pomiarem; przywróciłem wartość `1` i potwierdziłem zgodność hasha
(`6b86b273ff…`). **Bez tej migawki zmiana przeszłaby niezauważona** — to jest reguła do
zapamiętania przy każdym realnym POST-cie na formularz Settings API.

ℹ️ Licznik zdarzeń pokazuje `2699` wobec `2698` sprzed pomiaru, ale **ten jeden wiersz nie jest
mój**: `#3866 upload_produktu_blad … | kto=admin_roboczy | 19:58:12` — to builder1 pracujący
w tej samej chwili nad limitem uploadu. Zostawiam nietknięty.

## Kody wyjścia — zbiorczo

| pomiar | kod |
|---|---|
| logowanie administratora | `HTTP=200`, ciasteczka sesji ustawione |
| dodanie produktu | **`HTTP=302`** + `SELECT` potwierdza wiersz |
| edycja produktu | **`HTTP=302`** + `SELECT` potwierdza nową nazwę i cenę |
| włączenie sprzedaży | **`HTTP=302`** + `SELECT`: `aktywny=1` |
| wyłączenie sprzedaży | **`HTTP=302`** + `SELECT`: `aktywny=0`, wiersz żyje |
| zapis ustawień | **`HTTP=302 …settings-updated=true`** + `SELECT` potwierdza wartość |
| sprzątanie | `diff` migawek opcji **pusty**, liczby wierszy jak przed |

## Czego NIE zmierzyłem

- **Ekranu Pulpitu i eksportu CSV** — świadomie, bo pracuje tam builder1.
- **Walidacji odrzucających** (cena ≤ 0, pusta nazwa, plik nie-zip, plik ponad limit) — cel
  mówił o zapisie, który DOCHODZI; ścieżki odrzutu zostają niezmierzone realnym żądaniem.
- **Wgrania pliku ponad limit** — `upload_max_filesize` na stanowisku to 2 MB i builder1
  właśnie to zmienia (`I-26`), więc pomiar byłby nieporównywalny.
- **Zachowania przy dwóch równoczesnych zapisach tego samego produktu.**
