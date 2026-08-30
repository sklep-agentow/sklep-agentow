# K9 — instalacja z paczki Release `v0.9.23` na motywie kolegi, „jak klient” (30.08, po wydaniu)

**Po co (pytanie Dzidka po wydaniu):** „czy wpinaliśmy wtyczkę w stronę kolegi lokalnie — strona bez zakładki
Sklep — i wszystko wskoczyło w dobre miejsce?” oraz „co jeszcze da się sprawdzić”. Dotychczasowe dowody były
starsze niż wydany artefakt: K6 (instalacja od zera) na paczce 0.9.18, Plugin Check na 0.9.22, `/security-review`
na diffie do `98e4363`. Ten przebieg mierzy **to, co kolega pobierze**.

## Stanowisko (postawione przez koordynatorkę, nie testera)
- Czysty WordPress 6.9.4 / PHP 8.4 (`narzedzia-qa/lib-stos-probny.sh` → `postaw_stos`, kontener `audytor_wp`, :8892,
  baza `wp_klient_0923`), `WP_DEBUG` włączony.
- **Motyw kolegi z JEGO repo** (`MatthewPlugins/automatic-ai`, klon `--depth 1`, HEAD 28.08 = PR #129,
  katalog `wordpress/wp/theme/automatic-ai`) — nie nasza kopia z 17.08. Porównanie: `header.php` **identyczny**
  z naszą kopią sprzed dopisania linku; `footer.php` różni się jedną klasą CSS w nagłówku sekcji (`leading-[1.08]`
  vs `leading-luzne`), lista „Nawigacja” z FAQ nietknięta. Kotwice z `INSTRUKCJA-INSTALACJI.md` istnieją w obu plikach.
- Paczka: `sklep-agentow-KLIENT-0.9.23.zip` **pobrana z GitHub Releases** (`gh release download`), rozpakowana
  do katalogu testera; sekrety Stripe (tryb testowy) w pliku, którego koordynatorka nie czytała.
- `stripe listen` (istniejący) → `:8892/wp-json/sklepag/v1/webhook`. Bez poczty (celowo — ścieżka „środowisko bez poczty” z instrukcji).

## Tester
Świeży agent (Sonnet, osobny kontekst), bez wglądu w kod: dostał paczkę, instrukcję, panel WP i klucze. Playwright
(chromium) jako przeglądarka; „FTP” = `docker cp`. Zakaz `wp`/SQL (jedno naruszenie: raz `wp option get` przy
fallbacku — zapisał sam). Raport verbatim: `audyt/K9-RAPORT-TESTERA-0.9.23.md`. Zrzuty (4 z 25): `audyt/k9-zrzuty/`.

## Wynik: ŚCIEŻKA Z INSTRUKCJI PRZESZŁA W CAŁOŚCI
| Co | Wynik | Dowód |
|---|---|---|
| Wgranie 3 wtyczek przez panel | panel odmówił (`upload_max_filesize=2M` < 4,5 MB) → **wyjście nr 2 z instrukcji** („rozpakuj do `wp-content/plugins/`”) zadziałało | raport krok 1a–1b |
| Pierwsza aktywacja sklepu | komunikat wtyczki „Brak katalogu chronionego — deaktywuj i aktywuj” (`uploads` należał do `root` — cecha obrazu dockera); `chown` wg „Najczęstsze problemy” → aktywacja OK, katalog chroniony z `.htaccess` | krok 1d–1e |
| Strony „Sklep”, „Dziękujemy”, szkice Regulaminu/Polityki, menu kokpitu | powstały same | `/sklep/` 200 |
| Ustawienia (klucz, sekret webhooka, nadawca, mail kontaktowy, waluta) | nazwy pól **1:1 z instrukcją** | `krok2-ustawienia-po-zapisie.png` |
| **Link „Sklep” w nawigacji motywu kolegi** (header desktop, `tpl-menu-mobilne`, stopka) | fragmenty z instrukcji **pasowały 1:1** (klasy, miejsce po `<li>` z FAQ); `curl /` → 3× `href="/sklep/"` | `k9-zrzuty/krok3-desktop-1440-menu.png`, `krok3-mobile-390-menu-otwarte.png` |
| Dwa produkty z zipów botów | „plik paczki wgrany i POTWIERDZONY (obecność + rozmiar + suma)” | `k9-zrzuty/krok4-sklep-lista-desktop.png` |
| Zakup kartą testową przez prawdziwy Stripe Checkout | 14,1 s od strony produktu do „Dziękujemy”; **Adaptive Pricing** zaproponował EUR +4 % — dokładnie jak ostrzega instrukcja; wybór PLN → 199,00 | `krok5-*.png` |
| Zamówienie w panelu (bez poczty) | status „błąd maila”, **treść maila widoczna w panelu**, link z niej → HTTP 200, `Content-Length 922248`, **SHA-256 = oryginalny `chatbot-live-1.5.13.zip`**; licznik 1/10 | `k9-zrzuty/krok5-szczegoly-zamowienia.png` |
| „Wyślij ponownie” bez poczty | komunikat o nieudanej wysyłce, oś czasu: stary link przywrócony, ten sam token dalej pobiera (200, ten sam SHA) | krok 6 |
| Baner trybu testowego | widoczny także na stronie „Dziękujemy” — zgodne z ostrzeżeniem instrukcji i z długiem w CO-WISI | `krok5-po-platnosci-final.png` |

## Weryfikacja koordynatorki (po raporcie)
- ⛔ **Zero w logu PHP testera było FAŁSZYWE** — tester sam to oznaczył. Sprawdzone: `wp-config.php` nie miał
  `WP_DEBUG_LOG` (biblioteka stosu podaje go przez `WORDPRESS_CONFIG_EXTRA`, obraz `wordpress:6.9.4` go **nie wpisał**).
  Naprawa na stanowisku + **próba kontrolna**: podłożony `trigger_error` przez mu-plugin → `grep -c` = **1**. Potem
  przejście frontu 0.9.23 na motywie kolegi: `/` 200 · `/sklep/` 200 · 2× strona produktu 200 · `/dziekujemy-za-zakup/` 200 ·
  `/wp-json/sklepag/v1/status` 200 · `?s=x` 301 · 404 → **0 wpisów `PHP (Notice|Warning|Fatal|Deprecated)`** (zero prawdziwe).
  Panel WP w tym pomiarze nie był przechodzony (notice’y panelu pokrywa cel `Ł-08` z K7).
- Po drodze koordynatorka zepsuła stos na ~1 min (`wp config set --raw=false` wpisało ścieżkę bez cudzysłowów → 500);
  naprawione `sed`, `php -l` czysty — zapisane, bo dowód 500 mógłby zmylić czytelnika logu.

## Co z tego wynika (długi → `audyt/CO-WISI.md`)
1. `INSTRUKCJA-INSTALACJI.md`: w menu mobilnym instrukcja mówi „po pozycji 08”, a w pliku FAQ ma numer „07”
   (opóźnienie 0,35 s) — tester trafił, ale numeracja myli. Poprawka tekstu przy 0.9.24 (paczka zbiorcza jest
   już w Release; zmiana = nowe wydanie).
2. Komunikat „Brak katalogu chronionego — deaktywuj i aktywuj” nie mówi o przyczynie (uprawnienia `uploads`);
   sekcja „Najczęstsze problemy” wiąże `chown` tylko z uploadem mediów. Dopisać przy 0.9.24.
3. Narzędzie: `lib-stos-probny.sh` deklaruje `WP_DEBUG_LOG` przez `WORDPRESS_CONFIG_EXTRA`, a obraz 6.9.4 tego nie wpisuje
   → każdy pomiar „0 notice’ów” na tym stosie od tej wersji obrazu był zerem z martwego loggera. Naprawa: `wp config set`
   po `postaw_stos` + próba kontrolna z podłożonym notice’em w bibliotece.

## Czego ten przebieg NIE dowodzi
Tester = agent, nie osoba (jak K1 rubryki). Instalacja botów jako osobnych wtyczek klienta, realna poczta, „Dociągnij
zaległe”, opinie, anonimizacja — poza zakresem (lista w raporcie testera). Motyw kolegi z repo = stan 28.08; jeśli
zmieni header po tej dacie, fragmenty z instrukcji trzeba sprawdzić ponownie.
