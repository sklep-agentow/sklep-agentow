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

## lighthouse-3-podstrony.sh i axe-3-podstrony.js — audyt jakości 3 podstron

Lighthouse (4 kategorie, desktop + telefon, tabela + JSON) i axe-core (WCAG A/AA po wolnym
scrollu, exit 1 przy naruszeniu serious/critical). Pułapki zapisane w nagłówkach skryptów:
`blog_public=0` na stosie dev tnie SEO do 58–66 (noindex) — na czas pomiaru SEO włącz
indeksowanie i przywróć; animowane opacity daje fałszywy kontrast w axe — powtórz z
`--bez-ruchu`. Wyniki bazowe i po naprawach: `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md`.

```
cd /root/praca/zlecenia/sklep-agentow && bash testy/e2e/lighthouse-3-podstrony.sh http://localhost:8890 /tmp/lh 95 3
# 3. argument = PROG (domyslnie 95), 4. = POWTORKI komorki, ktora wypadla ponizej progu.
# ⛔ Wydajnosc na tej maszynie jest szumna (2 vCPU; tryb telefonu spowalnia procesor 4x) — bez
# powtorek dostaniesz werdykt z jednego przebiegu. Uzywaj wartosci NIEPARZYSTEJ >= 3.
# Kategorie a11y/bp/seo sa stabilne; perf na tym stosie NIE jest odbierany (anomalia AN-022).
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/axe-3-podstrony.js http://localhost:8890
```

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
