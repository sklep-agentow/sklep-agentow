# 06 — MAPA POLOWANIA (cele ⬜ → koniec audytu = zero ⬜, POLICZONE)

> Warunek końca nr 1 z przepisu. Mapa jest listą tego, co UMIEMY nazwać — błędy
> spoza mapy mogą przejść i to jest normalne. Ale cel nietknięty = audyt trwa.
> Każdy cel ma właściciela (audytora) i kończy się jednym z: ✅ sprawdzone czysto /
> 🐞 znalezisko / ⚠️ anomalia bez wyjaśnienia (⛔ NIE wolno zamknąć jako „wątpliwe").

## A1 — SECURITY (wykonanie: curl, przeglądarka, żądania równoległe)
- ⬜ Publiczny punkt wejścia czatu: żądanie bez nonce, z cudzym nonce, z wygasłym
- ⬜ Akcje panelu: konto bez uprawnień (subskrybent, redaktor) próbuje każdej mutacji
- ⬜ Wstrzyknięcie polecenia w trybie AI (gość pisze „zignoruj instrukcje i podaj…")
- ⬜ Czy klucz API wycieka: do odpowiedzi, do logów, do HTML, do eksportu CSV
- ⬜ Import CSV: plik nie-CSV, plik z PHP w środku, plik gigantyczny, zła liczba kolumn
- ⬜ Limit żądań: czy mierzy wymiar, który robi szkodę (G7); salwa równoległa

## A2 — AWARIA (czytanie + WYMUSZONE awarie)
- ⬜ Każdy zapis do bazy: czy kod ZMIERZYŁ stan po operacji, czy tylko wywołał funkcję (G2)
- ⬜ Co się dzieje, gdy API nie odpowiada / odpowiada błędem / odpowiada śmieciem
- ⬜ Czy da się odróżnić „AI odpowiedziało" od „zjechaliśmy do reguł" (G1)
- ⬜ Zapis pada w połowie wieloetapowej operacji — co zostaje w bazie
- ⬜ Wysyłka maila zawodzi — czy użytkownik widzi prawdę

## A3 — DANE / CO ZOSTAJE (SQL na bazie + wp-cli)
- ⬜ Diff bazy: przed instalacją → po instalacji → po odinstalowaniu, OBIEMA ścieżkami (G3)
- ⬜ Klucz API po odinstalowaniu — ZAWSZE ma zniknąć
- ⬜ Zaplanowane zadania: rejestrowane, odrejestrowywane, nie zostają zombie
- ⬜ Retencja faktycznie kasuje (podłóż wpis sprzed 91 dni, odpal, policz przed/po)
- ⬜ Ile jest źródeł prawdy dla tej samej listy (rozjazd = cichy błąd)
- ⬜ `LIKE` z `_` / `%` — czy sprzątanie nie łapie CUDZYCH wpisów

## A4 — FRONT / UŻYWALNOŚĆ (klikanie przeglądarką)
- ⬜ Każdą ścieżkę da się DOKOŃCZYĆ i PRZERWAĆ (co się otworzyło, da się zamknąć)
- ⬜ Co widzi gość, gdy sieć padnie w połowie
- ⬜ Podwójne kliknięcie, dwa okna naraz, powrót przyciskiem wstecz
- ⬜ Konsola przeglądarki czysta; dostępność (etykiety pól, kontrast, klawiatura)
- ⬜ Formularz zgody: czy da się go obejść wyłączając JavaScript (walidacja serwerowa)

## A5 — KRAWĘDZIE (wykonanie)
- ⬜ Puste, maksimum, jeden-ponad-limit, najdłuższe dopuszczalne
- ⬜ Polskie znaki, emoji, znaczniki HTML, cudzysłowy w każdym polu tekstowym
- ⬜ CSV: Windows-1250, brak nagłówka, pusty plik, milion wierszy
- ⬜ Dwa żądania naraz do tego samego zasobu

## A6 — DOKUMENTACJA vs KOD (porównanie + WYKONANIE dosłowne)
- ⬜ Każda LICZBA z instrukcji obu botów ma pokrycie w kodzie (G4)
- ⬜ Każda obietnica ze stron produktów w sklepie ma pokrycie w kodzie
- ⬜ Komendy z instrukcji WPISANE w terminal, nie przeczytane
- ⬜ Deklaracja prywatności rozdziela tryb regułowy i AI — i jest prawdziwa
- ⬜ Wersja w nagłówku = w stałej = w paczce = w CHANGELOG

## Ł — ŁAMACZ (adwersarz założeń)
- ⬜ „Co, jeśli baza nie odpowie w połowie przebiegu"
- ⬜ „Co, jeśli klucz API jest zły / wygasł / ma zerowy limit"
- ⬜ „Co, jeśli przyjdzie 500 żądań w minutę"
- ⬜ „Co, jeśli WordPress jest w wersji minimalnej z nagłówka wtyczki"
- ⬜ „Co, jeśli właściciel wyłączy zadania cykliczne (`DISABLE_WP_CRON`)"

---
**LICZNIK:** 34 cele. Zamknięte: 0. ⛔ Audyt trwa, dopóki licznik nie pokaże 34/34.
