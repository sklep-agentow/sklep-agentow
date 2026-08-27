# Ponowny pomiar punktów przelotu, które wypadły źle (28.08.2026)

Przelot 27 punktów przeszedł agent-audytor 27.08 na kodzie **0.9.17/0.9.18**.
Pięć punktów wypadło wtedy źle: A5, A6, A12, B6, C2. Naprawy poszły w 0.9.19,
więc **dowód audytora dotyczy kodu, którego już nie ma** — przepisanie jego
werdyktów żywcem wpisałoby do dokumentu stan nieaktualny.

Te sondy mierzą te pięć punktów PONOWNIE, na kodzie po naprawach. Wyniki wchodzą
do `audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md` jako werdykt z datą i wersją.

| plik | co mierzy | wynik na 0.9.19 |
|---|---|---|
| `a5-a6-c2-a12-sonda.js` | widoczność przycisku nagrania (A5), język komunikatu zgody (A6), zasięg Tab (C2), pierwsze podejście do pigułki (A12) | A5 naprawione · A6 nadal po angielsku · C2 naprawione |
| `a12-zawsze-zaslonione-i-c2-pulapka.js` | A12 poprawnie: czy JAKIŚ tekst jest ZAWSZE pod pigułką (nie: czy kiedykolwiek na niego nachodzi) | 49 z 49 elementów da się odsłonić — zero treści trwale zasłoniętej |
| `c2-pulapka-fokusu.js` | czy w otwartym pełnym ekranie Tab nie ucieka pod spód | fokus zostaje 15/15 kroków, Esc zamyka |
| `c2-pulapka-fokusu-proba-kontrolna.js` | ⭐ czy powyższy detektor UMIE wykryć ucieczkę | fokus przestawiony poza panel raportuje się jako ucieczka — pomiar, nie ślepota |
| `b6-formularz-trzyma-tresc.js` | czy odrzut walidacji formularza produktu oddaje wpisaną treść | 4 z 4 pól wracają |
| `smoke-frontu-po-zmianie.js` | czy naprawa języka zgody nie zepsuła reszty frontu | zero błędów JS, sekcje się odsłaniają, pełny ekran działa |

⛔ **Pierwsza sonda A12 została ODRZUCONA jako mierząca złą rzecz.** Liczyła każde
geometryczne nachodzenie przyklejonego paska na tekst — a to robi z definicji KAŻDY
pasek `position: fixed`. Mierzyła więc projekt, nie wadę. Właściwe pytanie brzmi:
czy istnieje treść, której NIE DA SIĘ odsłonić na żadnej pozycji przewijania.

Uruchomienie (stos warsztatowy na :8890, wtyczka w wersji do sprawdzenia):
```
cd /root/praca/zlecenia/sklep-agentow
node audyt/sklep/testy-audytora/przelot-recheck/a12-zawsze-zaslonione-i-c2-pulapka.js
node audyt/sklep/testy-audytora/przelot-recheck/c2-pulapka-fokusu-proba-kontrolna.js
set -a; . /root/.claude/sklep-admin-roboczy.env; set +a
node audyt/sklep/testy-audytora/przelot-recheck/b6-formularz-trzyma-tresc.js 2
```

Surowe wyjścia i zrzuty: `/root/praca/k7-dowody-28-08/`.
