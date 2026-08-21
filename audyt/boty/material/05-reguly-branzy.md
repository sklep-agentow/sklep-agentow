# 05 — REGUŁY BRANŻY (z oficjalnych źródeł, nie z głowy modelu)

> ⛔ Zasada: jeśli powołujesz się na regułę branżową, podaj JEJ ŹRÓDŁO. „Tak się
> robi" bez źródła to opinia, nie reguła — i leci z raportu.

## WordPress — obowiązkowe minimum (podręcznik wtyczek WP)
| Reguła | Jak to sprawdzić w praktyce |
|---|---|
| Każda mutacja danych: **nonce + sprawdzenie uprawnień** | wyślij żądanie bez nonce i z kontem bez uprawnień — musi odmówić |
| Każde zapytanie z wartością od użytkownika: **`$wpdb->prepare()`** | grep po konkatenacji w SQL |
| **`esc_like()`** przy `LIKE` | znak `_` jest wieloznacznikiem SQL — bez tego sprzątanie łapie CUDZE wpisy (realna wpadka poprzedniego projektu) |
| **Sanityzacja wejścia, escapowanie wyjścia** | czat i import CSV to wejście NIEZAUFANE |
| **`uninstall.php` sprząta wszystko, co wtyczka stworzyła** | tabele, opcje, zaplanowane zadania, transienty |
| Zaplanowane zadania **odrejestrowane przy deaktywacji** | inaczej zostają zombie |
| Zapytania do świata zewnętrznego: **timeout < limit PHP hostingu** | uwaga egzaminatora: 100 s przy limicie 30 s = timeout serwera |
| **Licznik limitu zwiększaj DOPIERO po sukcesie** | uwaga egzaminatora: licznik zjadany przy błędzie sieci |

## RODO — minimum dla tych dwóch botów
- **Zbierasz e-mail → musisz powiedzieć: co zbierasz, po co, na jak długo**, i dać
  odnośnik do polityki prywatności. Sam checkbox we frontendzie to za mało —
  **walidacja musi być też po stronie serwera**.
- **Treść wychodząca do dostawcy AI to przetwarzanie u podmiotu trzeciego.**
  W Segregatorze to jest CUDZA korespondencja (ludzi, którzy napisali do właściciela) —
  wymaga wyraźnego potwierdzenia właściciela przed włączeniem trybu AI, nie samego komunikatu.
- **Retencja**: dane osobowe nie mogą leżeć wiecznie „bo tak".
- **Deklaracja musi być prawdziwa.** „Wszystkie dane zostają u Ciebie" jest FAŁSZEM
  w trybie AI — teksty muszą rozdzielać oba tryby.

## Arkusze kalkulacyjne — eksport CSV
- Pole zaczynające się od `=`, `+`, `-`, `@` **wykona się jako formuła** w Excelu
  i Arkuszach Google. Treść pochodzi od gości → trzeba poprzedzić apostrofem.
- Polski Excel zapisuje CSV w **Windows-1250**, nie UTF-8 — import bez wykrycia
  kodowania daje krzaki na polskich znakach.

## Klasyfikacja (raporty Segregatora)
- Podając skuteczność, podaj **na czym liczona** (ile sztuk, jaka próbka).
  „100% trafień" z próbki 30 sztuk to co innego niż „100% skuteczności".
