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

Pięć przebiegów. Desktop (1440 px): pigułka „Kup teraz" zostaje wyśrodkowana także
wtedy, gdy reguły hurtowe `.sklepag-wyspa > *` z `!important` wchodzą do gry —
skrypt `front-ruch.js` opóźniony o 3,5 s (bezpiecznik `sklepag-odslon`) oraz
„ogranicz ruch" włączone po załadowaniu strony. Telefon (390 px): pigułka siedzi
12 px od LEWEJ (z założenia nie jest wyśrodkowana — prawy róg należy do bąbelka
czatu) i w całości w oknie, także z opóźnionym skryptem. Skalibrowana: na kodzie
sprzed napraw przebiegi B i C padały (`transform: none`, środek +120 px), a D i E
padały przez centrowanie bijące mobilne `transform: none` specyficznością
(pigułka wystawała poza lewą krawędź). Kod wyjścia 0 = 5/5 OK, 1 = któryś pada.

```
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/sonda-sticky-pigulka.js
```

Gdy `siteurl` stacku wskazuje inny host niż baza (np. tunel do pokazu), podaj
go w `SKLEPAG_PRZEKIERUJ_HOST=host` — żądania do niego pójdą na bazę, bez pośrednika.

## szelki-strona-produktu.js — szelki strony produktu (oba boty)

Dziesięć kontroli tego, czego zrzut nie pokaże: skrypt zablokowany / opóźniony
(bezpiecznik i kaskada), pigułka sticky, kotwica „Kup teraz", lightbox z pułapką
fokusu (Tab nie wychodzi poza dialog), nagranie w kadrze, chipy w hero, „ogranicz
ruch", bez JavaScriptu, lista `/sklep/` bez elementów strony produktu.
Kod wyjścia 0 = wszystko OK, 1 = któraś pada. Slug jako drugi argument.

```
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/szelki-strona-produktu.js http://localhost:8890 chatbot-live
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/szelki-strona-produktu.js http://localhost:8890 segregator-maila
```
