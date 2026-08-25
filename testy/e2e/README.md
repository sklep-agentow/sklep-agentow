# Testy E2E na żywym stacku warsztatowym

Te testy NIE biegają w CI (tam nie ma WP + Stripe) — odpalasz je na maszynie
ze stackiem: WordPress na `http://localhost:8890`, mailpit na `:8025`,
`stripe listen` forwardujący webhooki (sekrety w `/root/.claude/sekrety-sklep.env`,
POZA repo).

## przelot2.js — pełny przelot zakupu kartą testową

Ścieżka: sklep → POST kup (nonce + zgoda) → hostowany Checkout Stripe
(karta 4242… w iframe'ach Elements) → powrót → status przez REST aż do
`mail_wyslany`. Kod wyjścia 0 = przelot domknięty, 2 = mail nie wyszedł, 1 = błąd.

Wymaga (jednorazowo, poza repo — `node_modules/` nie wjeżdża do gita):

```
cd /root/praca/zlecenia/sklep-agentow && npm install --no-save playwright-core
```

Uruchomienie:

```
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/przelot2.js
```

Uwaga: skrypt używa przeglądarki z `/root/.cache/ms-playwright/` (ścieżka
w środku pliku) — na innej maszynie popraw `executablePath`.

## sonda-sticky-pigulka.js — pigułka sticky na stronie produktu (sonda skalibrowana)

Sprawdza, że pigułka „Kup teraz" zostaje wyśrodkowana także wtedy, gdy reguły
hurtowe `.sklepag-wyspa > *` z `!important` wchodzą do gry: skrypt `front-ruch.js`
opóźniony o 3,5 s (bezpiecznik `sklepag-odslon`) oraz „ogranicz ruch" włączone
po załadowaniu strony. Na kodzie sprzed naprawy przebiegi B i C padają
(`transform: none`, środek +120 px) — to jest kalibracja, nie przypadek.
Kod wyjścia 0 = 3/3 OK, 1 = któryś pada.

```
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/sonda-sticky-pigulka.js
```

Gdy `siteurl` stacku wskazuje inny host niż baza (np. tunel do pokazu), podaj
go w `SKLEPAG_PRZEKIERUJ_HOST=host` — żądania do niego pójdą na bazę, bez pośrednika.
