# PLAN NAPRAW — Segregator maila (przed audytem, wersja docelowa 1.2.2)

**Data: 2026-08-20.** Podstawa: 4 niezależne krytyki modeli (runda 1) zweryfikowane w kodzie.
⛔ To jest osobny produkt — ChatBot ma własny plan. Krytycy dali temu botowi najniższe oceny
(15, 20, 25, 49 / 100) i **wszyscy czterej wskazali TĘ SAMĄ przyczynę** — patrz niżej.

## KONTEKST (czego krytycy nie wiedzieli)
- To **testowy bot na egzamin**. **Nazwę „Segregator maila" podał ZAMAWIAJĄCY** w notatce
  zlecenia — nie zmieniamy jej, bo to litera zamówienia.
- **Brak podpięcia prawdziwej skrzynki (IMAP/OAuth) to ŚWIADOMA decyzja zamawiającego**
  z 18.08, zapisana w rejestrze (D19): na egzamin działa na próbkach, prawdziwa skrzynka
  = droga rozbudowy po egzaminie.
- Termin: paczka i pierwsza próba oddania **27.08**.

## ⛔ ZBIEŻNY ZARZUT 4/4 KRYTYKÓW — i co z nim robimy
> „Nazywa się segregator, a nie segreguje. Klient kupi myśląc, że posprząta mu skrzynkę,
> a dostanie analizator plików CSV. Brak EFEKTU KOŃCOWEGO."

**Nazwy nie zmieniamy** (zamówienie). **Naprawiamy dwie rzeczy, które są naprawdę winne:**
1. **Obietnicę** — pierwsze zdanie ma mówić prawdę OD RAZU, nie trzy ekrany niżej.
2. **Brak efektu** — dokładamy eksport gotowych filtrów do Gmaila i Outlooka (poniżej C1).
   To daje realny skutek na prawdziwej skrzynce BEZ łamania zakresu.

## STAN OBECNY (zweryfikowany w kodzie)
✅ Reguły z rozstrzyganiem kolejnością · kategorie CRUD · próbka 30 maili PL · import CSV
(nonce + capability + `is_uploaded_file`, plik NIE trafia na dysk — czytany z bufora) ·
raport: pokrycie, trafność, lista pomyłek · eksport wyników CSV · historia przebiegów ·
tryb AI: reguły / AI / porównanie z raportem różnic · escapowanie w raportach (43×) ·
czysty uninstall · phpcs/phpstan czyste.
❌ Braki potwierdzone niżej.

## NAPRAWY — kolejność wg ryzyka

### A. PRAWNE (blokujące oddanie)
| # | Co | Dlaczego | Skąd |
|---|---|---|---|
| A1 | **RETENCJA wgranych maili** — cron czyszczący próbki i przebiegi starsze niż 90 dni (jak w ChatBocie) + przycisk „wyczyść próbkę teraz" + wpis w instrukcji | do bazy trafia PRAWDZIWA korespondencja z adresami — dziś zostaje tam na zawsze, zero crona. **Chatbot ma retencję, ten bot nie ma nic** | 2/4 krytyków, potwierdzone w kodzie (zero trafień) |
| A2 | **Rozdzielenie komunikatu o prywatności**: reguły = nic nie opuszcza serwera; AI = treść maila idzie do dostawcy. W panelu, instrukcji i na stronie produktu | dziś obiecujemy „dane zostają u Ciebie" bez zastrzeżenia | 2/4 krytyków |

### B. UCZCIWOŚĆ PRZEKAZU (największe ryzyko wizerunkowe)
| # | Co | Dlaczego |
|---|---|---|
| B1 | **Nowe pierwsze zdanie** na karcie i stronie produktu — prawda od razu, np. „Sprawdza Twoje reguły segregacji na próbce maili i pokazuje raport z liczbami — zanim wpuścisz je na żywą skrzynkę." Dziś: „rozdziela pocztę do kategorii" (sugeruje żywą skrzynkę) | 4/4 krytyków. To jest ta jedna rzecz, którą wszyscy nazwali ryzykiem zwrotów |
| B2 | **Baner w panelu przy pierwszym uruchomieniu**: „To narzędzie NIE łączy się z Twoją skrzynką. Pracuje na próbce, którą wgrasz." | żeby klient dowiedział się PRZED, nie PO zakupie |

### C. EFEKT KOŃCOWY (odpowiedź na zarzut 4/4 — tanie, duży zysk)
| # | Co | Dlaczego |
|---|---|---|
| C1 | ⭐ **EKSPORT GOTOWYCH FILTRÓW**: po przebiegu przycisk „pobierz filtry do Gmaila" (plik XML do importu w Gmailu) i „pokaż reguły dla Outlooka" (lista krok po kroku) | zamienia „analizator, który nic nie robi" w narzędzie z REALNYM skutkiem na prawdziwej skrzynce — bez IMAP, bez łamania zakresu. Pomysł z krytyki nr 4 |
| C2 | **Szablon CSV do pobrania** + instrukcja „jak wyeksportować maile z Gmaila/Outlooka" krok po kroku w panelu | 3/4 krytyków: eksport CSV to bariera dla nietechnicznego właściciela |
| C3 | **Limit rozmiaru wgrywanego pliku** + czytelny komunikat błędu (dziś brak limitu; ryzyko małe, bo tylko administrator, ale porządek) | 1/4 krytyków, potwierdzone |
| C4 | **Drugi dostawca AI: Gemini** + instrukcja darmowego klucza | jw. co w ChatBocie — bariera „własny klucz" znika |

### D. DOKUMENTACJA (po zamrożeniu kodu)
| # | Co |
|---|---|
| D1 | Dokumentacja **techniczna**: schemat 5 tabel, format CSV (kolumny, kodowanie), zaczepy do rozbudowy, wymagania |
| D2 | **Diagram przepływu**: próbka → reguły (kolejność) → kategorie → raport → filtry do skrzynki |

## ⛔ ŚWIADOMIE NIE ROBIMY (idzie do „droga rozbudowy" w instrukcji)
Podpięcie IMAP/OAuth · automatyczne przenoszenie maili w skrzynce · uczenie się z korekt ·
wykrywanie subskrypcji i masowe wypisywanie · obsługa załączników · wiele skrzynek.
**Powód:** zakres klepnięty przez zamawiającego + termin 27.08.

## SZACUNEK CZASU
A: ~1,5 h · B: ~0,5 h · C: ~3,5 h (sam eksport filtrów ~2 h) · D: ~1 h → **razem ~6,5 h**.
