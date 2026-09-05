# CI-02 — dla każdej bramki: nazwa kontra to, co naprawdę liczy

Cel `CI-02` mapy polowania K7. Pytanie brzmi nie „czy bramka jest zielona”, tylko **czy zielone
znaczy to, co obiecuje jej nazwa**. Bramka o szerokiej nazwie i wąskim zasięgu jest groźniejsza
od jej braku: nikt nie sprawdza terenu, o którym myśli, że jest pilnowany.

Przebieg: 28.08.2026, commit `98133e3`, gałąź `domkniecie-przelotu-0.9.20`.
Źródło: `narzedzia-qa/bramki.sh` (linie 170–245) + konfiguracje `phpcs.xml.dist`, `phpstan.neon.dist`.

---

## Zestawienie

| bramka | co obiecuje nazwa | co MIERZY naprawdę | rozjazd |
|---|---|---|---|
| `actionlint` | workflowy CI są poprawne | `actionlint .github/workflows/*.yml` **+ od #290 shellcheck nad blokami `run:`** (pinowana binarka przez `zapewnij_narzedzie`, więc pomiar jest ten sam lokalnie i w CI) | ⚠️ wzorzec łapie **tylko `*.yml`**; plik `*.yaml` przeszedłby niesprawdzony, tak samo `.github/actions/` |
| `manifest` | strażnik strażników — bramki nie znikają | `sprawdz-manifest.sh`: lista bramek zgadza się z tym, co przebieg naprawdę odpala | ✅ zgodne z nazwą |
| `slady` | w tym, co jedzie do klienta, nie ma naszych śladów wewnętrznych | pętla po `wtyczki/*/` i tylko po nich | ⚠️ `dokumentacja/INSTRUKCJA-INSTALACJI.md` **też jedzie do klienta** (readme odsyła do niej jako „obok paczek”) i NIE jest skanowana |
| `znaleziska` | znaleziska audytu są rozliczone | księgowość rejestru: każde ma test, status, SHA. ⛔ **Nie uruchamia testów** | ✅ rozjazd zadeklarowany wprost w nagłówku `bramka-znaleziska.sh` |
| `status-bez-warunku` | zapis statusu chroni stan poprzedni | `bramka-status-bez-warunku.php:42` → `$repo . '/wtyczki/sklep-agentow'` | ⚠️ nazwa jest ogólna (klasa błędu), zasięg to **jedna wtyczka z trzech** |
| `php-lint` | składnia PHP jest czysta | `find wtyczki -name '*.php'` → 52 pliki | ⚠️ **65 śledzonych plików PHP leży poza `wtyczki/`** i nie jest sprawdzanych |
| `phpcs` | kod trzyma standard | `<file>` = trzy katalogi wtyczek; część sniffów wyłączona dla chatbota i segregatora | ⚠️ ta sama granica co wyżej |
| `phpstan` | statyka przechodzi | `paths: wtyczki/` | ✅ granica **zadeklarowana z powodem** w `phpstan.neon.dist` |
| `gitleaks` | w historii nie ma sekretów | `gitleaks git --log-opts=HEAD` | ⚠️ historia od HEAD, **bez drzewa nieśledzonego** — świadome (recenzja #195 W1), lukę łata `poswiadczenia` |
| `poswiadczenia` | żywe poświadczenia nie wjeżdżają do repo | CAŁE repo: drzewo śledzone i nieśledzone **oraz** historia osiągalnych commitów | ✅ najszerszy zasięg, zgodny z nazwą |

---

## Cztery rozjazdy warte wpisu, z pomiarem

### CI-02-a · trzy bramki PHP nie widzą 65 ze 117 plików PHP tego repo
`git ls-files '*.php'` → **52 w `wtyczki/`, 65 poza** (`testy/*.php`, `audyt/boty/**.php`,
`narzedzia-qa/bramka-status-bez-warunku.php`, sondy audytu). `php-lint`, `phpcs` i `phpstan`
ograniczają się do `wtyczki/`, więc żaden z tych 65 plików nie przechodzi nawet kontroli składni.

⭐ **Zmierzone dziś, żeby nie straszyć bez liczby:** `php -l` puszczony ręcznie po wszystkich 65 →
**0 plików z błędem składni**. Luka jest więc dziś PUSTA. Groźna jest jej natura, nie stan:
plik testu z błędem składni nie zgłosi się sam — test po prostu nie wystartuje, a bramki
świecą na zielono. To ta sama klasa, którą mamy już zapisaną jako „bramka, która cicho nie
startuje, świeci zielono”.

### CI-02-b · `slady` nie skanuje jedynego pliku dla klienta leżącego poza `wtyczki/`
`uruchom_slady()` iteruje wyłącznie po `wtyczki/*/`. `dokumentacja/INSTRUKCJA-INSTALACJI.md`
jedzie do klienta obok paczek (potwierdza to bramka zgodności dokumentacji, sekcja E), ale
leży poza tą pętlą.

⭐ **Zmierzone:** `grep -cEi 'kryteri|plan §|PR #[0-9]|recenzj|SK-L[0-9]|K7|audyt|soczewk|/root/praca'`
na tym pliku → **0 trafień**, przy próbie kontrolnej (`grep -ci instalacj` → 6), więc grep działa
i naprawdę nie ma tam śladów. Luka pusta, ryzyko realne.

⛔ To nie jest odkrycie: nagłówek `bramka-poswiadczenia.sh` sam pisze, że `slady` „skanuje tylko
wtyczki/” — i właśnie dlatego bramka poświadczeń skanuje całe repo. Czyli jedna luka jest już
świadomie załatana dla sekretów, ale **nie dla śladów wewnętrznych**.

### CI-02-c · `status-bez-warunku` ma nazwę klasy, a zasięg jednej wtyczki
Ścieżka jest wpisana na sztywno: `bramka-status-bez-warunku.php:42`. Wzorzec „zapis stanu bez
sprawdzenia stanu poprzedniego” złapano trzy razy w jednym PR-ze sklepu — ale chatbot trzyma
stan rozmowy, a segregator stan skrzynki, i ich ta bramka nie ogląda.

### CI-02-d · `actionlint` przepuści workflow o rozszerzeniu `.yaml`
`*.yml` to wzorzec powłoki, nie „wszystkie workflowy”. GitHub czyta oba rozszerzenia.
Dziś w `.github/workflows/` są dwa pliki i oba mają `.yml`, więc rozjazd jest teoretyczny —
ale kosztuje jeden znak, żeby przestał być możliwy.

---

## Czego ten przegląd NIE sprawdza

- Nie oceniam **jakości** reguł wewnątrz bramek (czy `phpcs` ma właściwy zestaw sniffów,
  czy reguły `gitleaks` łapią nasze formaty kluczy) — to pytanie o skuteczność, nie o zasięg.
  Skuteczność mierzą próby kontrolne (`CI-01`), i one mają własne, osobno zapisane wyniki.
- Nie sprawdzam bramek spoza `bramki.sh` (`k7-koniec.sh`, `paczka-zgodnosc.sh`,
  `dokumentacja-zgodnosc-sklep.sh`) — mają własne cele w mapie.
- Nie zmieniam tu ani jednej bramki. To jest przegląd; rozszerzanie zasięgu ruszałoby CI
  w PR-ze o czym innym, a każde rozszerzenie może zapalić czerwone na starym długu
  i musi mieć własny przebieg.
