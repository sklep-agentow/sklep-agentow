# KAŻDA LICZBA Z DOKUMENTACJI vs KOD — oba boty (A6/G4)


**Pomiar: 2026-08-21, na zamrożonym stanie `807d72d` (ChatBot 1.5.8, Segregator 1.3.1).**
Powstało, bo audytor A1 napisał wprost, że tej kategorii **nie sprawdził**, a klasa
„dokumentacja kłamie względem kodu" wracała w poprzednim zleceniu **cztery razy**.

⚠️ **Odsyłacze do DOKUMENTACJI podajemy bez numerów linii** — numery przesuwają się
przy każdym dopisku (sprawdzone na własnej skórze: dopisanie jednej sekcji do instrukcji
unieważniło dwanaście adresów w tym pliku). Numery linii zostają tam, gdzie wskazują KOD.

Metoda: każda liczba wyjęta z `INSTRUKCJA.md`, `readme.txt` i `DOKUMENTACJA-TECHNICZNA.md`
obu wtyczek, a potem `grep` **po stałej albo po miejscu użycia** — nie z pamięci
i nie z nagłówka. Kolumna „gdzie w kodzie" to jest to, co `grep` naprawdę zwrócił.

## Czego pilnowały bramki PRZED tym przeglądem — i czego nie

| bramka | zakres |
|---|---|
| `testy/dokumentacja-zgodnosc.sh` | **tylko `DOKUMENTACJA-TECHNICZNA.md` ChatBota** |
| `testy/dokumentacja-zgodnosc-segregator.sh` | dokumentacja techniczna Segregatora |
| `testy/instrukcja-liczby-segregator.sh` | liczby z **instrukcji Segregatora** |

⛔ **Dziura: `INSTRUKCJA.md` i `readme.txt` ChatBota nie były pilnowane przez nic.**
Wszystkie liczby stamtąd sprawdzone niżej ręcznie.

## ChatBot LIVE 1.5.8

| liczba | gdzie w dokumentacji | gdzie w kodzie | werdykt |
|---|---|---|---|
| **100** wywołań AI/dobę (domyślnie) | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/chatbot-live.php:129` `'ai_limit_dzienny' => 100` | ✅ ZGODNE |
| **200 000** tokenów/dobę (domyślnie) | `wtyczki/chatbot-live/INSTRUKCJA.md` | `wtyczki/chatbot-live/chatbot-live.php:145` `'ai_limit_tokenow' => 200000` | ✅ ZGODNE |
| **20** wywołań AI/dobę z jednego IP | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/front.php:27` `CHATL_AI_LIMIT_IP_NA_DOBE = 20` | ✅ ZGODNE |
| **90 dni** retencji rozmów | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/front.php:14` `CHATL_RETENCJA_DNI = 90` | ✅ ZGODNE |
| **6** ostatnich wymian w pamięci rozmowy | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/ai.php:47` `CHATL_AI_PAMIEC_WYMIAN = 6` | ✅ ZGODNE |
| **10** wiadomości na minutę z jednego IP | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/front.php:13` `CHATL_LIMIT_NA_MIN = 10` | ✅ ZGODNE |
| **500** znaków pytania | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/front.php:11` `CHATL_MAX_PYTANIE = 500` | ✅ ZGODNE |
| **2000** znaków wiadomości zgłoszenia | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/front.php:12` `CHATL_MAX_WIADOMOSC = 2000` | ✅ ZGODNE |
| **5 minut** ciszy operatora → rozmowa wraca do bota | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/includes/przejecie.php:25` `CHATL_PRZEJECIE_CISZA = 300` (s) | ✅ ZGODNE |
| **2 sekundy** — próg pułapki na boty | `wtyczki/chatbot-live/INSTRUKCJA.md` | `wtyczki/chatbot-live/includes/front.php:47` `CHATL_PULAPKA_SEKUND = 2` | ✅ ZGODNE |
| **4,5:1** — próg kontrastu WCAG | `wtyczki/chatbot-live/INSTRUKCJA.md` | `wtyczki/chatbot-live/includes/wyglad.php:20` `CHATL_WCAG_PROG = 4.5` | ✅ ZGODNE |
| **7 / 30 / 90 dni** — okresy statystyk | `wtyczki/chatbot-live/INSTRUKCJA.md` | `wtyczki/chatbot-live/includes/statystyki.php:615-617` (trzy `<option>`) | ✅ ZGODNE |
| **10** przykładowych par Q&A po instalacji | `wtyczki/chatbot-live/INSTRUKCJA.md` | zmierzone na CZYSTEJ instalacji z paczki: `baza Q&A: 10 par` (`dowod-zachowania-chatbot.sh`) | ✅ ZGODNE |
| **WP 6.0+ / PHP 8.1+** (wiersz „Wymagania") | `wtyczki/chatbot-live/INSTRUKCJA.md`, `wtyczki/chatbot-live/readme.txt` | `wtyczki/chatbot-live/chatbot-live.php:6,7` `Requires at least: 6.0`, `Requires PHP: 8.1` | ✅ ZGODNE (deklaracja, patrz uwaga niżej) |

## Segregator maila 1.3.1

| liczba | gdzie w dokumentacji | gdzie w kodzie | werdykt |
|---|---|---|---|
| **100** wywołań AI/dobę (domyślnie) | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/segregator-maila.php:319` `'ai_limit_dzienny' => 100` | ✅ ZGODNE |
| **30** maili — twardy limit próbki w trybie AI | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/includes/ai.php:33,44,45` `SEGM_AI_PARTIA = 30`, `SEGM_AI_MAX_PROBKA = 30 × 1` | ✅ ZGODNE |
| **90 dni** retencji | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/segregator-maila.php:26` `SEGM_RETENCJA_DNI = 90` | ✅ ZGODNE |
| **20 sekund** — limit czasu przebiegu | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/skrzynka.php:30` `SEGM_SKRZ_LIMIT_CZASU = 20` | ✅ ZGODNE |
| **15 min / 30 min / godzina / 6 godzin / doba** | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/includes/skrzynka-przebieg.php:24-45` — pięć pozycji + „wyłączone" | ✅ ZGODNE |
| **50 wierszy** na stronę | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/admin.php:145` `SEGM_NA_STRONE = 50` | ✅ ZGODNE |
| **500 wierszy** importu CSV | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/csv.php:12` `SEGM_IMPORT_MAX_WIERSZY = 500` | ✅ ZGODNE |
| **5 MB** na plik CSV | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/csv.php:24` `SEGM_IMPORT_MAX_BAJTOW = 5242880` | ✅ ZGODNE |
| **3 kolumny** minimum w wierszu danych | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/csv.php:194` — `< 3 kolumnami = twarde odrzucenie` | ✅ ZGODNE |
| **512 kB** pliku konfiguracji | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/konfiguracja.php:31` `SEGM_KONFIG_MAX_BAJTOW = 524288` | ✅ ZGODNE |
| **500 kategorii i 500 reguł** w imporcie konfiguracji | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/konfiguracja.php:36` `SEGM_KONFIG_MAX_WPISOW = 500` | ✅ ZGODNE |
| **2000 znaków** wzorca | `wtyczki/segregator-maila/INSTRUKCJA.md` | `wtyczki/segregator-maila/includes/konfiguracja.php:46` `SEGM_KONFIG_MAX_WZORZEC = 2000` | ✅ ZGODNE |
| **5 kategorii** po instalacji | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/includes/instalator.php:151` — pięć nazw w tablicy | ✅ ZGODNE |
| **8 przykładowych reguł** | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/includes/instalator.php:172-183` — osiem wpisów | ✅ ZGODNE |
| **30 maili** w próbce demo | `wtyczki/segregator-maila/INSTRUKCJA.md`, `wtyczki/segregator-maila/readme.txt` | `wtyczki/segregator-maila/includes/instalator.php:216+` — trzydzieści wpisów | ✅ ZGODNE |

## ✅ ZNALEZISKO ZAMKNIĘTE — dokumentacja MILCZAŁA o funkcji, za którą klient płaci

> **STAN: ZAMKNIĘTE w tym samym pull requeście (#133).** `INSTRUKCJA.md:91-93` opisuje już
> import CSV wraz z limitami (2000 wierszy / 2 MB / 15 minut), a `dokumentacja-zgodnosc.sh`
> potwierdza te liczby jako `OK`. Opis poniżej zostaje jako **zapis stanu sprzed naprawy** —
> to on nazwał klasę „dokumentacja milczy o funkcji". ⛔ Nie czytaj go jako otwartej luki.

**Instrukcja ChatBota nie opisywała importu bazy Q&A z pliku CSV** — funkcji wydanej
w 1.5.7 i ogłoszonej w `wtyczki/chatbot-live/readme.txt` jako nowość:

```
$ grep -n "import\|CSV" wtyczki/chatbot-live/INSTRUKCJA.md
284:Na liście zgłoszeń i w historii rozmów jest przycisk **„Pobierz CSV"** — plik
```

Jedyne trafienie dotyczy **eksportu** zgłoszeń. W instrukcji nie ma ani słowa o tym,
że bazę Q&A można wgrać plikiem, ani o jego limitach, które **są w kodzie** i o które
użytkownik uderzy przy pierwszym większym pliku:

| limit w kodzie | gdzie | w instrukcji |
|---|---|---|
| `CHATL_IMPORT_MAX_WIERSZY = 2000` | `wtyczki/chatbot-live/includes/import.php:31` | **brak** |
| `CHATL_IMPORT_MAX_BAJTOW = 2097152` (2 MB) | `wtyczki/chatbot-live/includes/import.php:34` | **brak** |
| `CHATL_IMPORT_MINUT = 15` (ważność podglądu) | `wtyczki/chatbot-live/includes/import.php:37` | **brak** |

**Która strona kłamie:** żadna liczba nie jest nieprawdziwa — **milczy dokumentacja**.
To jest ta sama klasa co „kod urósł, opis nie", tylko w drugą stronę niż zwykle: nie
obiecujemy za dużo, tylko **nie mówimy o tym, co klient kupił**. Do poprawy jest
**instrukcja**, nie kod: klient wgrywający plik z 2500 wierszami dostanie komunikat,
którego nie miał gdzie przewidzieć, a podgląd wygaśnie mu po 15 minutach bez ostrzeżenia.

⛔ Poprawka dotyka pliku w paczce, więc **wymaga wydania 1.5.9** i przebudowy paczki —
to decyzja koordynatorki, nie moja. Zgłaszam, nie robię.

## Uwaga, która nie jest rozjazdem, ale audytor musi ją znać

**`WP 6.0+` i `PHP 8.1+` to deklaracje bez pomiaru.** Zgadzają się między nagłówkiem
wtyczki, `readme.txt` i instrukcją — ale **nikt nigdy nie uruchomił żadnego z botów
na tych dolnych granicach**. Stanowisko ma WP 7.0.4 i PHP 8.4.21. Zgodność trzech
miejsc dowodzi spójności zapisu, nie prawdziwości obietnicy.
