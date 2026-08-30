# SECURITY.md — Sklep Agentów

> Stan na 2026-08-30. Trzy wtyczki WordPressa: `sklep-agentow` (wydane 0.9.25; 0.9.26 w przygotowaniu),
> `chatbot-live` (1.5.13), `segregator-maila` (1.3.6). Wersje wspierane: **najnowsze wydanie każdej
> wtyczki** z zakładki Releases — starsze nie dostają poprawek, tylko podniesienie wersji.

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
| Ślady wewnętrzne w paczce klienta | Skaner śladów (`narzedzia-qa/slady-wewnetrzne.sh`) i zgodność paczki z allowlistą (`narzedzia-qa/paczka-zgodnosc.sh`) w CI |

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

Zapisywane: e-mail kupującego, nieobowiązkowe dane do faktury (nazwa, NIP, adres), treść
i czas zgody na dostarczenie treści cyfrowej przed upływem terminu odstąpienia, adres IP
i przeglądarka w dzienniku logowań administratorów. Kupującego można **zanonimizować
z panelu** (historia zamówienia zostaje bez danych osoby); dziennik starszy niż 90 dni jest
usuwany automatycznie. Odinstalowanie z opcją czyszczenia usuwa wszystkie tabele, opcje,
strony, pliki i zadania cron wtyczki. Szablony Regulaminu i Polityki prywatności generuje
wtyczka — właściciel uzupełnia je własnymi danymi i przegląda z prawnikiem.

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
`.github/RECENZENT-BRIEFING.md`. Strażniki bezpieczeństwa mają **próby kontrolne** — testy,
które podkładają znaną wadę (np. podrobiony podpis webhooka, limit liczony z adresu proxy)
i sprawdzają, że strażnik ją widzi (`testy/*-proba-kontrolna.sh`). Audyty przed wydaniem:
`audyt/AUDYT-RAPORT.md`. Świadomie niezrobione rzeczy: `DECYZJE.md`; otwarte: `audyt/CO-WISI.md`.
