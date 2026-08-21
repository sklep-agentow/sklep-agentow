# 03 — ZAŁOŻENIA PRZYJĘTE (co jest decyzją, a nie brakiem)

> ⛔ Każda pozycja niżej to **świadoma decyzja z uzasadnieniem**, zapisana w rejestrze
> `DECYZJE.md` albo w szkicach klepniętych przez zamawiającego. Zgłoszenie ich jako
> błędu = szum, który zjada czas weryfikacji. Jeśli uważasz, że decyzja jest ZŁA —
> napisz to osobno, w sekcji „wątpliwości co do decyzji", nie jako znalezisko.

## Granice produktu (klepnięte 18.08)
| # | Założenie | Dlaczego |
|---|---|---|
| Z1 | Segregator NIE łączy się z prawdziwą skrzynką (brak IMAP/OAuth) | decyzja zamawiającego (D19); na egzamin działa na próbkach CSV, prawdziwa skrzynka = droga rozbudowy |
| Z2 | ChatBot NIE ma czatu z żywym operatorem | jw.; „LIVE" = człowiek odpisze na zebrany kontakt (zdanie uczciwości na stronie produktu) |
| Z3 | ChatBot NIE pamięta rozmów między wizytami | jw. |
| Z4 | Dopasowanie po podłańcuchach, bez odmiany polskich słów | v1; warianty wpisuje się ręcznie („cena, ceny, cennik") — bez cichego poszerzania wyceny |
| Z5 | Reguły Segregatora: pierwsza pasująca wygrywa, BEZ pola „priorytet" | dwa mechanizmy rozstrzygania naraz = niejednoznaczność (recenzja PR #27) |
| Z6 | Klucz API jest WŁAŚCICIELA, trzymany w bazie bez szyfrowania | szyfrowanie kluczem leżącym obok w tej samej bazie to teatr; poprawa = stała w `wp-config.php` (jest), nie szyfrowanie |
| Z7 | Drugi dostawca AI (Gemini) NIE wchodzi | 7 głosów krytyków przeciw na 7: nowa integracja tydzień przed terminem = ryzyko regresji bez zysku dla oceny |

## Założenia techniczne
- **Dane botów siedzą we WŁASNYCH tabelach** (`chatl_*`, `segm_*`), nie w `wp_options` —
  to reakcja na uwagę egzaminatora z poprzedniego projektu (klasa #7 ściągi).
- **Domyślny uninstall zostawia dane**; pełne czyszczenie to opcja. ⚠️ ALE klucz API
  musi zniknąć ZAWSZE — to była realna wpadka w poprzednim projekcie (klasa #1).
- **Zero zewnętrznych zależności i CDN.** Wszystko własne, offline.
- **Teksty po polsku wprost w kodzie** (D21), bez systemu tłumaczeń.

## Czego audyt NIE ocenia
- Sklepu (`sklep-agentow`) — ma własny audyt (K7).
- Wyglądu stron produktów w sklepie — to K5, zamknięte.
- Decyzji biznesowych (cena, nazwa, zakres) — to litera zamówienia.
