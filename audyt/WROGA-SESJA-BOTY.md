# WROGA SESJA BOTÓW — 19.08 (przyciągnięta z planu 23.08)

Środowisko: CZYSTY WordPress 7.0.2 (obraz `wordpress:7.0.2`, PHP 8.3, MariaDB 10.6,
jednorazowy stack :8891, po sesji zlikwidowany) + `WP_DEBUG` z `WP_DEBUG_LOG`.
Instalacja z ZAWARTOŚCI paczek `boty/paczki/*-1.2.1.zip` — zawartość przed sesją
zweryfikowana `diff -r` z repo (zero różnic, czyli test = test paczki).
Wersje PHP/WP różnią się od stacka docelowego (7.0.4/8.4) — sesja testuje czystość
instalacji i cykl życia, składni na 8.4 pilnują php-lint/phpstan w CI.
Uwaga środowiskowa: `wp plugin install <zip>` w tym obrazie wp-cli (2.12) nie
rozpoznawał lokalnej ścieżki zipa — instalacja = rozpakowana paczka do `plugins/`
(równoważna ręcznemu wgraniu; zawartość ta sama co zip, patrz diff wyżej).

## Wyniki (wszystko MIERZONE, nie deklarowane)

| # | Test | Wynik |
|---|------|-------|
| 1 | Aktywacja obu botów przy WP_DEBUG | ✅ `debug.log` NIE POWSTAŁ (zero notice/błędów) |
| 2 | Seed po aktywacji | ✅ chatl_qa=10 · segm_kategorie=5 · segm_reguly=8 · segm_maile=30 |
| 3 | Widget na froncie (jasny motyw twentytwentyfive) | ✅ odpowiedź z Q&A; fallback „Zostaw wiadomość" + formularz; kolory zmierzone z żywego DOM = paleta strony (limonka #bfff38, tusz #08090b, węgiel #101116); dymek gościa zmierzony pikselowo (ciemny + biały tekst) |
| 4 | Przebieg segregatora (tryb reguły) | ✅ raport id=1, `zjazd=false` |
| 5 | Cykl deaktywacja→aktywacja | ✅ dane przeżyły (10/30 wierszy), seed NIE nadpisał, log czysty |
| 6 | Uninstall DOMYŚLNY (usun_dane=OFF) | 🔴→✅ ZNALEZISKO niżej; po naprawie: tabele zostają (5+5), dane żyją, **klucz API skasowany** (przed=`sk-ant-KONTROLA-*`, po=`false`) |
| 7 | Uninstall PEŁNY (usun_dane=ON) | ✅ tabele chatl+segm=0 · opcje `chatl_%`/`segm_%`=0 (w tym klucze) · crony botów=0 |

## 🔴 ZNALEZISKO (klasa #1 ściągi egzaminatora — catsnboard PR #84)

**Uninstall przy domyślnej ścieżce (usun_dane=OFF) zostawiał `chatl_klucz_api` /
`segm_klucz_api` w bazie** — wczesny `return` stał PRZED kasowaniem sekretu, więc
zasada D22 („poświadczenia kasowane ZAWSZE, niezależnie od opcji") była spełniona
tylko na ścieżce pełnego czyszczenia. Domyślna ścieżka = ta, którą przejdzie każdy.

- **Pomiar przed naprawą:** po uninstallu OFF `chatl_klucz_api=['sk-ant-KONTROLA-chatl']`,
  `segm_klucz_api=['sk-ant-KONTROLA-segm']` — sekrety w bazie po śmierci wtyczki.
- **Naprawa:** `delete_option( '*_klucz_api' )` przeniesione PRZED wczesny return
  (oba uninstall.php); duplikat z sekcji pełnego czyszczenia usunięty (jedna prawda).
- **Pomiar po naprawie:** klucze `false` po uninstallu OFF; test #7 (ON) dalej zielony.
- **Dok=kod:** INSTRUKCJA.md obu botów mówiła „klucz kasowany z opcją" —
  poprawiona na „klucz kasowany przy KAŻDYM odinstalowaniu" (klasa #3 ściągi).
- Paczki 1.2.1 PRZEBUDOWANE po naprawie + powtórka weryfikacji `diff -r` (czysta).

## Kwity środowiskowe (nie wady produktu)

- `Deletion of plugin files failed` — wp-cli nie mógł skasować PLIKÓW wtyczki
  (uprawnienia kopii docker cp); procedura uninstall.php wykonała się w całości
  (pomiar bazy powyżej). Na normalnej instalacji przez wp-admin nie występuje.
- `Constant WP_UNINSTALL_PLUGIN already defined` — warning RDZENIA WP przy
  odinstalowaniu 2 wtyczek jednym poleceniem wp-cli; nie dotyczy naszego kodu.
