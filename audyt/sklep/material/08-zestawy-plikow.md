# 08 — ZESTAWY PLIKÓW PER SOCZEWKA (kod sklepu = 8 842 linie; nikt nie dostaje „całego repo")

Ścieżki względem ROZPAKOWANEJ PACZKI (`sklep-agentow/…`, tylko-odczyt) — repo służy wyłącznie do grepów i historii.
Reguła: mutant trafiony przez soczewkę, której nie był przypisany = jej normalne znalezisko (unia); nie zmienia
kalibracji soczewki przypisanej.

| soczewka | pliki | dodatkowo |
|---|---|---|
| **L1** zgodność | `sklep-agentow.php` (shortcode'y, cron, assety), `includes/zakup.php` (mail, S-02), `includes/front-produkt.php` (S-03), `includes/admin-dashboard.php` + `class-sklepag-lista-zamowien.php` (S-04), `includes/admin-produkty.php` + `class-sklepag-lista-produktow.php` (S-08), `includes/logowania.php` + `admin-dziennik.php` + `class-sklepag-lista-logowan.php` (S-09), `includes/admin-ustawienia.php` (S-15), `includes/aktywacja.php` (S-18/19) | Mailpit tylko-odczyt; klikanie na stosie warsztatowym |
| **L2** bezpieczeństwo/RODO | `includes/zakup.php`, `stripe.php`, `pobieranie.php`, `opinie.php`, `logowania.php`, `admin.php`, `admin-produkty.php`, `admin-zamowienie.php`, `admin-opinie.php`, `admin-ustawienia.php`, `admin-dashboard.php`, `admin-dziennik.php`, `uninstall.php` + repo: `.github/workflows/*.yml` | konta `audytor-admin` / `audytor-sub`; curl; SQL; `/security-review` osobno |
| **L3** pieniądze | `includes/zakup.php`, `stripe.php`, `pobieranie.php`, `opinie.php` (token/resend), `admin-zamowienie.php` (resend, anonimizacja), `admin-dashboard.php` (dociągnij, self-heal), `sklep-agentow.php` (cron) | Stripe CLI, Mailpit, `testy/e2e/przelot2.js`, wzór równoległości `testy/nonce-cache-test.js`; awarie w kontenerze |
| **L4** dane | `includes/aktywacja.php`, `uninstall.php`, `includes/zakup.php` (cron + retencja), `admin-ustawienia.php` (opcje), `admin-dashboard.php` (self-heal), `opinie.php` (tabela opinie) | `narzedzia-qa/dowod-aktualizacji.sh`, `k7-stare-wersje.sh`, `wp db query`, `narzedzia-qa/stos.sh`, czysty stos 6.7/8.1 |
| **L5** dokumentacja | dokumenty: `readme.txt`, `CHANGELOG.md`, `DOKUMENTACJA-TECHNICZNA.md`, `dokumentacja/INSTRUKCJA-INSTALACJI.md`, `includes/front-produkt.php` (teksty), `includes/zakup.php` (`sklepag_zbuduj_mail`), `includes/aktywacja.php` (szablony prawne); kod: cały jako źródło grepów | `plugin-check`, `narzedzia-qa/slady-wewnetrzne.sh`, `gitleaks`, inwentarz z `02-fakty` |
| **L6** front | `includes/front-produkt.php`, `front-seo.php`, `assets/front.css`, `assets/front-ruch.js`, `assets/dziekujemy.js` | `testy/e2e/lighthouse-3-podstrony.sh`, `axe-3-podstrony.js`, `szelki-strona-produktu.js`, `sonda-sticky-pigulka.js` |
| **Ł** łamacz | wszystko poza `assets/front.css` | oba stosy; `docker cp`/`sed` w kontenerze; wyrocznia SQL na `zdarzenia` |
