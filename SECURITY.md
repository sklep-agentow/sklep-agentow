# SECURITY.md — Sklep Agentów

> Stan na 2026-09-05. Trzy wtyczki WordPressa: `sklep-agentow` (0.9.40),
> `chatbot-live` (1.5.15), `segregator-maila` (1.3.7). Wersje wspierane: **najnowsze wydanie każdej
> wtyczki** z zakładki Releases — starsze nie dostają poprawek, tylko podniesienie wersji.
>
> <!-- ⛔ Te numery pilnuje `narzedzia-qa/security-wersje.sh`, wpięty w bramkę wydania.
>      Powód (znalezisko D-1, 31.08): plik mówił „wydane 0.9.25; 0.9.26 w przygotowaniu",
>      gdy kod stał na 0.9.28 i istniały tagi v0.9.26/27/28 — czyli dokument, do którego
>      trafia osoba zgłaszająca lukę, wskazywał wydanie sprzed trzech releasów. Nie
>      pilnowało go NIC (zero trafień „SECURITY.md" w narzedzia-qa/ i .github/).
>      Podnosisz wersję wtyczki? Ten wiersz podnosi się razem z nią.
>      Strażnik czyta numery z PROZY WYŻEJ, nie z osobnego znacznika — znacznik
>      byłby drugim miejscem z tymi samymi liczbami, czyli nowym źródłem rozjazdu. -->

## 1. Jak zgłosić lukę

Nie zakładaj publicznego Issue z opisem luki. Napisz na adres właściciela repozytorium
(profil GitHub) albo — jeśli jesteś klientem — kanałem, którym dostałeś wydanie.
Podaj: wersję wtyczki, wersję WordPressa i PHP, kroki odtworzenia, co udało się zrobić
(odczyt cudzych danych, zmiana bez uprawnień, obejście płatności). Potwierdzenie
otrzymania i pierwsza ocena wagi — w ciągu 7 dni. Poprawka luki dotyczącej **pieniędzy,
cudzych danych lub obejścia uprawnień** wychodzi jako osobne wydanie poprawkowe.

## 2. Model zagrożeń — co jest powierzchnią ataku

Zaplecze jest **w całości w WordPressie** (własne tabele z prefiksami `sklepag_`, `chatl_`,
`segm_`; panel w wp-admin; REST API `wp-json/sklepag/v1/…`). Nie ma zewnętrznego serwera,
osobnej bazy ani logowania poza WordPressem. Jedyna usługa zewnętrzna: **Stripe**
(płatności kartą) — wywołania przez `wp_remote_post`, bez SDK.

| Wektor | Pokrycie |
|---|---|
| Podmiana ceny lub produktu w żądaniu zakupu | Cena i produkt brane z bazy po stronie serwera, nie z formularza; snapshot produktu w zamówieniu |
| Fałszywe „opłacono” (podrobiony webhook) | Podpis `Stripe-Signature` liczony HMAC-SHA256 z sekretem webhooka; zły lub brak podpisu = odrzut; okno czasu |
| Dwukrotny webhook / wyścig z dociąganiem zaległych | Jedno zamówienie i jeden mail — przejścia stanu jednorazowe (operacja atomowa w bazie) |
| Pobranie pliku bez zakupu, po terminie, ponad limit | Token pobrania z maila: ważność 72 h, do 10 pobrań, licznik zużywany atomowo przy starcie pobrania (`UPDATE … WHERE pobrania_uzyte < pobrania_limit`); `HEAD` nie zużywa limitu; pliki w katalogu chronionym pod losową nazwą. Wyciek linku z maila = do 10 pobrań przez 72 h — dlatego link jest w mailu do kupującego, nigdzie indziej. Jednorazowy jest osobny token **opinii** |
| Akcja w panelu bez uprawnień / CSRF | Nonce **i** `current_user_can()` parą na każdej mutacji; jawny `permission_callback` na każdej trasie REST |
| SQL injection | `$wpdb->prepare()` do każdego zapytania z wejściem; `esc_like()` przy `LIKE` |
| XSS | Sanityzacja wejścia (konkretne klucze + `wp_unslash`), escapowanie wyjścia (`esc_html`/`esc_attr`/`esc_url`/`wp_kses`) |
| Zgadywanie haseł | Opóźnienie wykładnicze po nieudanych logowaniach (sufit 60 s) + limit prób z jednego adresu; dziennik logowań udanych i nieudanych (IP, przeglądarka) |
| Zalew żądań (spam, boty) | Limity na adres IP (z obsługą proxy/Cloudflare tylko na jawną decyzję właściciela — nagłówkom nie ufa się domyślnie); ChatBot: pole-pułapka + limit czasu; Stripe stawia własną kontrolę (hCaptcha) przy podejrzanym ruchu |
| Wyciek kluczy Stripe | Klucze w ustawieniach WordPressa (baza), nigdy w kodzie ani w repo; `.gitignore` wyklucza `.env*` i `*.pem`; realną zaporą jest skaner `gitleaks` (skanuje historię) i skaner poświadczeń w CI na każdym PR |
| Ślady wewnętrzne w paczce klienta | Skaner śladów (`narzedzia-qa/slady-wewnetrzne.sh`) w CI na każdym PR; zgodność paczki z allowlistą (`narzedzia-qa/paczka-zgodnosc.sh`) to **ręczny punkt listy wydania** (przed `pakuj-dla-klienta.sh`; skrypt pakowania robi własne kontrole zipów, tej bramki nie woła) — nie w CI |

Wymagania środowiska: WordPress 6.7+ (testowane do 7.1), PHP 8.1+ (testowane na 8.4), HTTPS.

## 3. Co MUSI zrobić hosting (poza zasięgiem wtyczki)

Wtyczka WordPressa **nie ustawia nagłówków HTTP** serwera. Na docelowym hostingu trzeba:

- **HTTPS** z przekierowaniem z HTTP (Stripe wymaga; bez tego formularz karty nie ruszy).
- **Nagłówki bezpieczeństwa** (serwer WWW / Cloudflare / wtyczka bezpieczeństwa):
  `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy:
  strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN` (albo `frame-ancestors` w CSP),
  `Permissions-Policy`.
- **Content-Security-Policy — jeśli ją wdrażasz, dopuść Stripe**: strona produktu ładuje
  `https://js.stripe.com` (skrypt) i osadza ramki Stripe:
  `script-src 'self' https://js.stripe.com` · `frame-src https://js.stripe.com
  https://hooks.stripe.com` · `connect-src 'self' https://api.stripe.com`. Wtyczka i WordPress
  drukują też krótkie skrypty inline (konfiguracja formularza) — CSP bez `'unsafe-inline'`
  wymaga nonce'a lub hasha dla nich; bez tego **płatność na stronie przestanie działać**.
  Sprawdź po wdrożeniu CSP testowym zakupem kartą testową.
- **Katalog plików produktów na nginx**: wtyczka kładzie w nim `.htaccess` i `index.php`, ale
  `.htaccess` działa tylko na Apache. Prawdziwą bramą jest PHP (token, termin, limit) i losowa
  nazwa katalogu (`wp-content/uploads/sklepag-chronione-<16 losowych znaków>/`) — na nginx dołóż
  regułę blokującą bezpośredni dostęp, np. w bloku `server`:
  `location ~ ^/wp-content/uploads/sklepag-chronione-[^/]+/ { deny all; return 404; }`
  i sprawdź `curl -I` na pełnej ścieżce pliku (ma być 404), a potem testowy zakup (link z maila
  ma działać — plik podaje PHP, nie serwer).
- **Zapora / WAF**: dopuść publiczny `POST` na `…/wp-json/sklepag/v1/webhook` (autoryzacją
  jest podpis Stripe); rozważ `fail2ban` lub regułę na `wp-login.php` — zmasowany atak z wielu
  adresów to praca zapory, nie wtyczki.
- **Kopie zapasowe bazy** — zamówienia, tokeny pobrań i dziennik żyją w tabelach WordPressa.

## 4. Dane osobowe (RODO)

**Sklep** (zgodnie z szablonem Polityki prywatności, który generuje wtyczka): e-mail kupującego,
dane transakcji (kwota, produkt ze snapshotu, identyfikatory Stripe), nieobowiązkowe dane do faktury
(nazwa, NIP, adres), treść i czas zgody na dostarczenie treści cyfrowej przed upływem terminu
odstąpienia, **adres IP kupującego przy każdym pobraniu pliku** (dziennik zdarzeń, 90 dni), przy
dobrowolnej opinii — **imię i treść opinii, publikowane na stronie produktu**, oraz adres IP
i przeglądarka w dzienniku logowań administratorów. Kupującego można **zanonimizować z panelu**
(historia zamówienia zostaje bez danych osoby); dziennik starszy niż 90 dni jest usuwany automatycznie.

**ChatBot LIVE** (dane gości strony klienta): zgłoszenia kontaktowe (`chatl_zgloszenia`: e-mail
i wiadomość) oraz treść rozmów (`chatl_rozmowy`); historia rozmów kasowana automatycznie po 90 dniach,
adresy IP gości nie są zapisywane; wnioski o eksport i usunięcie danych obsługuje wbudowany kreator
prywatności WordPressa (wtyczka rejestruje eksporter i mechanizm usuwania w `includes/rodo.php`).
⚠️ W opcjonalnym **trybie AI** treść pytań gościa wychodzi do dostawcy modelu — Polityka klienta musi
to wymieniać (szablon ma miejsce „[UZUPEŁNIJ po włączeniu]”).

**Segregator maila** (dane korespondentów klienta): nadawca, temat i treść maili z jego skrzynki
(`segm_maile`, `segm_skrz_ruchy`) — przetwarzane na jego serwerze, retencja 90 dni; hasło IMAP
przechowywane poza bazą (stała w `wp-config.php`), więc nie wychodzi z kopią bazy. ⚠️ W trybie AI
do dostawcy modelu wychodzi PRÓBKA: nadawca (190 znaków), temat (300) i pierwsze 800 znaków treści.

Odinstalowanie każdej wtyczki z opcją czyszczenia usuwa jej tabele, opcje, strony, pliki i zadania cron.
Szablony Regulaminu i Polityki prywatności generuje sklep — właściciel uzupełnia je własnymi danymi
i przegląda z prawnikiem.

## 5. Zależności i łańcuch dostaw

Zero bibliotek PHP w paczce (Stripe przez `wp_remote_post`, poczta przez `wp_mail`). Na froncie
tylko `https://js.stripe.com/…/stripe.js` ładowany bezpośrednio od Stripe (wymóg PCI — skrypt
nie może być kopiowany lokalnie). Narzędzia deweloperskie (PHPCS, PHPStan, Playwright) nie
wchodzą do paczki klienta.

## 6. Reakcja na incydent

1. **Wyciek klucza Stripe** → w panelu Stripe unieważnij klucz (Developers → API keys → Roll),
   wpisz nowy w Ustawieniach wtyczki, sprawdź w Stripe listę ostatnich płatności.
2. **Wyciek sekretu webhooka** → w Stripe utwórz nowy endpoint/sekret, wpisz w Ustawieniach,
   stary usuń; uruchom „Dociągnij zaległe teraz”, żeby domknąć zakupy z okna przerwy.
3. **Podejrzenie przejęcia konta admina** → Dziennik w panelu (logowania z IP i przeglądarką),
   zmiana haseł, przegląd listy administratorów WordPressa.
4. **Wyciek pliku produktu** → wgraj nowy plik w panelu produktu (nowa losowa nazwa w katalogu
   chronionym); nowe zakupy dostają nowy plik, wcześniejsze linki wskazują plik z chwili
   zakupu (snapshot) i wygasają po 72 h.

## 7. Jak to jest sprawdzane

Na każdym PR: bramki jakości z `narzedzia-qa/bramki.sh` (jedyne źródło listy — tu jej celowo
nie przepisujemy; m.in. PHPCS ze standardem WordPress, PHPStan, `gitleaks`, skaner poświadczeń)
i recenzja według
`.github/RECENZENT-BRIEFING.md`. Strażniki mają **próby kontrolne** — testy, które podkładają
znaną wadę (np. limit liczony z adresu proxy zamiast gościa, brak nonce) i sprawdzają, że strażnik
ją widzi (`testy/*-proba-kontrolna.sh`). Odrzut webhooka ze złym lub brakującym podpisem dowodzą raporty
audytu na żywym stosie (`audyt/sklep/raporty/A/L3.json`: podpis `v1=deadbeef` → HTTP 400 „zły podpis”;
`A/L2.json`: znacznik czasu sprzed 400 s przy tolerancji 300 s → HTTP 400; `B/L3.json`: po rotacji sekretu
stary podpis → HTTP 400) — osobnej próby kontrolnej strażnika dla podpisu nie ma (dług w `audyt/CO-WISI.md`). Audyty przed wydaniem:
`audyt/AUDYT-RAPORT.md`. Świadomie niezrobione rzeczy: `DECYZJE.md`; otwarte: `audyt/CO-WISI.md`.
