# ODWROTNY PRZEGLĄD: co produkt ROBI vs co instrukcja OPISUJE — oba boty

**Pomiar: 2026-08-21, stan `807d72d` (ChatBot 1.5.8, Segregator 1.3.1).**

Przegląd liczb (`LICZBY-DOKUMENTACJA-VS-KOD.md`) pytał: *czy liczba w dokumentacji
zgadza się z kodem*. Ten pyta **w drugą stronę**: *czy wszystko, co produkt robi
i co użytkownik widzi, jest w instrukcji w ogóle opisane*. Klasa wyszła przy imporcie
Q&A z CSV — funkcja wydana w 1.5.7, ogłoszona w `readme.txt`, a w instrukcji nie było
o niej **ani słowa**. Dokumentację pisano wcześniej, więc **najświeższe funkcje mają
największą szansę w niej nie być** — kolejność przeglądu szła według `git log`.

Inwentarz brany z kodu, nie z pamięci: pozycje menu (`add_menu_page`/`add_submenu_page`),
akcje AJAX (`wp_ajax_*`), trasy REST (`register_rest_route`), zadania cykliczne
(`wp_schedule_event`) i przyciski w panelu.

## ChatBot LIVE 1.5.8

| funkcja widoczna dla użytkownika | gdzie w kodzie | w instrukcji |
|---|---|---|
| Menu **Baza Q&A** | `includes/admin.php:12` (`add_menu_page`) | ✅ TAK (§2) |
| Menu **Statystyki i luki** | `includes/admin.php` (`chatl-statystyki`) | ✅ TAK (§5) |
| Menu **Zgłoszenia z czatu** | `includes/admin.php` (`chatl-zgloszenia`) | ✅ TAK (§4) |
| Menu **Historia rozmów** | `includes/admin.php` (`chatl-historia`) | ✅ TAK (§4) |
| Menu **Ustawienia** | `includes/admin.php` (`chatl-ustawienia`) | ✅ TAK (§2, §3, §6) |
| Menu **Rozmowy na żywo** (przejęcie) | `includes/operator.php` (`chatl-rozmowy`) | ✅ TAK (§7, „Przejmij rozmowę osobiście") |
| Widget czatu, zbiórka kontaktu | `includes/front.php` (AJAX `chatl_pytanie`, `chatl_zgloszenie`) | ✅ TAK (§1, §4) |
| Tryb AI + dwa stop-lossy | `includes/ai.php` | ✅ TAK (§6) |
| Pamięć bieżącej rozmowy | `includes/ai.php:47` | ✅ TAK (§6, pkt 2a) |
| Pułapka na boty | `includes/front.php:47` | ✅ TAK (§4, „Pułapka na boty") |
| Wygląd widgetu + kontrast WCAG | `includes/wyglad.php` | ✅ TAK (§3) |
| Wnioski RODO (eksporter/kasownik WP) | `includes/rodo.php` | ✅ TAK (§9) |
| Retencja 90 dni (cron) | `chatl_czyszczenie_historii` | ✅ TAK (§4, §9) |
| Trasa REST `chatl/v1/rozmowa` | `includes/przejecie.php:502` | ✅ TAK — pośrednio, przez opis przejęcia |
| **Import bazy Q&A z CSV** (1.5.7) | `includes/import.php` | ⛔ **BYŁO: NIE** → ✅ dopisane (§2a) |
| **Eksport bazy Q&A do CSV** (1.5.7) | `includes/import.php:730+` | ⛔ **BYŁO: NIE** → ✅ dopisane (§2a) |
| **Wskaźnik „bot pisze"** (1.4.8) | `assets/widget.js` | 🐞 **NIE** — nie ma ani w instrukcji, ani w readme |
| **Stronicowanie trzech list panelu** (1.5.6) | `includes/admin.php:67,70` (200/100/25) | 🐞 **NIE** — żaden dokument nie mówi, że listy mają strony |
| **Podstrona „Start" (ekran powitalny)** | `includes/powitanie.php:32` | 🐞 **NIE** w instrukcji (readme wspomina) |
| **Test bota w panelu** | `includes/admin.php` (AJAX `chatl_panel_rozmowa`) | 🐞 **NIE** w instrukcji (readme wspomina) |

**Cztery pozycje bez opisu w instrukcji, wszystkie z ostatnich dni przed zamrożeniem** —
dokładnie tam, gdzie kazała szukać kolejność `git log`:

```
$ git log --oneline --since="2026-08-19" -- wtyczki/chatbot-live
7dc2635 F: import bazy Q&A z pliku CSV z podgladem przed zapisem (1.5.7)   ← dopisane teraz
c86dd80 G: stronicowanie trzech list panelu (1.5.6)                        ← NIEOPISANE
bf2e1c7 D: pulapka na boty (1.5.4)                                          ← opisane
5f06db1 E: wnioski RODO kreatorem WordPressa (1.5.2)                        ← opisane
dcb2437 A: przejecie rozmowy przez czlowieka (1.5.0)                        ← opisane (1.5.8)
c31b54b C: brak sygnalu, ze bot pisze (1.4.8)                               ← NIEOPISANE
```

## Segregator maila 1.3.1 — komplet, zero braków

| funkcja widoczna dla użytkownika | gdzie w kodzie | w instrukcji |
|---|---|---|
| Menu **Przebiegi** | `includes/admin.php` (`segm-przebiegi`) | ✅ TAK |
| Menu **Reguły** | `segm-reguly` | ✅ TAK (§3, 26 wzmianek) |
| Menu **Kategorie** | `segm-kategorie` | ✅ TAK |
| Menu **Próbka maili** | `segm-probka` | ✅ TAK |
| Menu **Skrzynka (IMAP)** | `includes/admin-skrzynka.php` (`segm-skrzynka`) | ✅ TAK |
| Menu **Ustawienia** | `segm-ustawienia` | ✅ TAK |
| Ekran powitalny po aktywacji | `includes/admin-powitanie.php` | ✅ TAK |
| Eksport filtrów Gmail / reguł Outlook | `includes/filtry.php` | ✅ TAK (11 i 8 wzmianek) |
| Import/eksport konfiguracji JSON | `includes/konfiguracja.php` | ✅ TAK |
| „Uruchom teraz" i harmonogram | `includes/skrzynka-przebieg.php` | ✅ TAK |
| **Cofnięcie przebiegu** | `includes/skrzynka-przebieg.php:402` | ✅ TAK |
| „Przetestuj klucz API" | `includes/ai.php` | ✅ TAK |
| Retencja 90 dni (cron) | `segm_czyszczenie_probki` | ✅ TAK |
| Trasy REST | **brak w kodzie** | — (nie ma czego opisywać) |
| Akcje AJAX | **brak w kodzie** | — |

## Co z tego wynika dla następnego wydania

⛔ **Wersji NIE podbijałem** — zgodnie z poleceniem. Dopisanie importu i eksportu CSV
zmienia `INSTRUKCJA.md` i `readme.txt`, czyli **pliki w paczce**, więc:

```
$ bash narzedzia-qa/paczka-zgodnosc.sh chatbot-live
  ROZJAZD  INSTRUKCJA.md — TA SAMA NAZWA, INNA TREŚĆ
  ROZJAZD  readme.txt   — TA SAMA NAZWA, INNA TREŚĆ
PACZKA-ZGODNOSC: 2 rozjazd(ow) — NIE ZAMRAZAJ w tym stanie                 EXIT=1
```

To **nie jest usterka tej zmiany** — to dowód, że bramka zamrożenia działa. Scalenie
wymaga wydania **1.5.9** i przebudowy paczki. Decyzja koordynatorki.

Trzy pozostałe braki (wskaźnik pisania, stronicowanie, „Start"/test bota w instrukcji)
zgłaszam, **nie dopisuję** — żeby jedno wydanie zamknęło je razem, a nie w trzech turach.
