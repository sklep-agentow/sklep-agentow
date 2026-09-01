# `SK-L-005` — waga i stan naprawy, z dowodem · plus: co widzi licznik ostrzeżeń na Pulpicie

**Kto:** audytor, 1.09 wieczór. **Gałąź:** `audyt/czesciowo-01-09` (do PR #261), kod z `main`
po scaleniu. **Kodu produktu nie tknąłem.**
⛔ **Rejestru sklepu NIE edytowałem** — jego nagłówek mówi wprost: *„Pisze: WYŁĄCZNIE
koordynatorka (audytor oddaje raport, nie edytuje rejestru)"*. Gotowe teksty do wklejenia
są w §4.

## ODPOWIEDZI

| pytanie | odpowiedź |
|---|---|
| Czy waga **ŚREDNIA** jest właściwa? | **TAK — zostawiam ŚREDNIĄ, nie podnoszę do KRYTYCZNEJ.** Ale uzasadnienie w rejestrze jest **oparte na złej podporze** i to trzeba poprawić (§2) |
| Czy to jest dziś naprawione na `main`, czy tylko opisane? | **Jedno i drugie, w dwóch różnych warstwach**: mechanizmu WordPressa naprawić się nie da i nikt tego nie twierdzi; naprawiony jest **kłamiący komentarz** (strażnik przechodzi dziś, `EXIT=0`), a skutek jest **skompensowany trzema niezależnymi drogami**, z których dwie zweryfikowałem w kodzie na `main` (§3) |
| Czy licznik ostrzeżeń na Pulpicie widzi tę awarię? | ⛔ **NIE — i z konstrukcji nie może.** To osobna rzecz niż „24 awarie" z raportu B, które są zamknięte pod `SK-L3-002` (§5) |

---

## 1. CO SIĘ NAPRAWDĘ DZIEJE — mechanizm, bez sporu

Przy odnośnikach „Zwykłe" żądanie na `/wp-json/sklepag/v1/webhook` **nie dociera do wtyczki**:
WordPress oddaje stronę główną motywu z kodem **200**. Stripe uznaje 2xx za doręczenie
i **nie ponawia**. W dzienniku sklepu nie ma nic — bo nasz kod nie został wywołany ani razu.
To zachowanie WordPressa, nie wada wtyczki, i tak jest opisane w nagłówku strażnika.

## 2. WAGA: ŚREDNIA ZOSTAJE — ale uzasadnienie w rejestrze stoi na złej podporze

Wiersz `SK-L-005` uzasadnia zejście z KRYTYCZNEJ na ŚREDNIĄ zdaniem:
> *„reconciliation co 10 min tym samym silnikiem → opóźnienie ≤10 min, nie utrata"*

⛔ **Ta podpora jest najsłabsza z możliwych**, bo „co 10 minut" znaczy **WP-Cron**, a WP-Cron
jest dokładnie tym, co na hostingu nie chodzi — ostrzega przed tym instrukcja klienta
(`INSTRUKCJA.md` rozdz. 3: *„Na części hostingów i na stronach z małym ruchem to nie działa"*),
a K10 zmierzył przypadek, w którym opłacony zakup wisiał **20 minut** właśnie z tego powodu.
Gdyby to była jedyna kompensacja, waga byłaby do podniesienia.

⭐ **Prawdziwą podporą jest coś innego i to trzeba wpisać:** `sklepag_dashboard_self_heal()`
(`includes/admin-dashboard.php:214-221`), wołany **przy każdym renderze Pulpitu**
(`:324`, w `sklepag_admin_dashboard()`), z warunkiem „ostatni bieg starszy niż 10 min".
Komentarz nad funkcją mówi wprost: *„WP-cron na cichej stronie może nie ruszyć wcale"*.
Ta droga **nie zależy od crona** — wystarczy, że właściciel wejdzie na Pulpit.

Do tego dwie rzeczy, które sprawdziłem w kodzie na `main`:
- **reconciliation nie jest zepsuta przez permalinki**: pyta Stripe **wychodząco**
  (`includes/stripe.php:12,46` — `wp_remote_post` na `api.stripe.com`), a ustawienie odnośników
  dotyczy ruchu **przychodzącego**. Silnik domykający jest ten sam co przy webhooku;
- **właściciel nie może nie zobaczyć przyczyny**: `sklepag_ostrzez_permalinki()`
  (`includes/admin.php:33-37`) to `admin_notices` **bez warunku ekranu** — czerwony pas
  na KAŻDEJ stronie wp-admin, ze zdaniem „strona sklepu i webhook płatności NIE DZIAŁAJĄ"
  i linkiem prosto do ustawienia.

**Wniosek:** to jest **opóźnienie dostawy, nie utrata pieniędzy** — zamówienie powstaje przy
najbliższym biegu crona ALBO przy najbliższym wejściu na Pulpit, a przyczyna krzyczy z każdego
ekranu panelu. **ŚREDNIA jest właściwa.**

⚠️ **Resztkowe ryzyko, które i tak trzeba nazwać** (nie zmienia wagi, ale musi być zapisane):
jeżeli WP-Cron nie chodzi **i** właściciel nie otworzy Pulpitu, zamówienie nie powstanie
**dowolnie długo** — kupujący jest obciążony, produktu nie ma, a jedyne, co go informuje, to
strona „Dziękujemy", która po dwóch minutach zamiera na zdaniu „strona odświeża status sama"
(`E7-F1`). Wtedy nie działa też sam sklep (strona produktu bez odnośników), więc okno na taki
zakup jest wąskie — ale nie zerowe, bo produkt ma zapas `?sklepag_produkt=slug`
(`includes/front-produkt.php:87`).

## 3. CZY NAPRAWIONE NA `main`, CZY TYLKO OPISANE — pomiar

| warstwa | stan na `main` | dowód |
|---|---|---|
| **zachowanie WordPressa** (200 zamiast 404) | ⛔ nie do naprawienia po naszej stronie i nikt tego nie obiecuje | nagłówek strażnika mówi to wprost |
| **kłamiący komentarz** (`admin.php:24-25` twierdził 404) | ✅ **naprawione i pilnowane** | `bash testy/komentarz-permalinki-zgodny-z-pomiarem-test.sh` → **`EXIT=0`**: `opis_twierdzi_404=NIE`, `opis_podaje_zmierzone_200=TAK` |
| **kompensacja 1 — cron 10 min** | ✅ jest (`sklep-agentow.php:77,91-92`) | zależna od WP-Cron |
| **kompensacja 2 — self-heal przy wejściu na Pulpit** | ✅ jest (`admin-dashboard.php:214-221,324`) | **niezależna od crona** — to ona dźwiga wagę |
| **kompensacja 3 — czerwony pas o odnośnikach** | ✅ jest (`admin.php:33-37`), bez warunku ekranu | — |

⛔ **Jedno zastrzeżenie do słowa „pilnowane":** strażnik `testy/komentarz-permalinki-zgodny-z-pomiarem-test.sh`
jest **sierotą** — należy do 32 testów, których jedyną automatyczną drogą uruchomienia jest
nieodpalana `narzedzia-qa/linia-bazowa.sh` (`B6-F1`, `I-24`). Dziś przechodzi, bo uruchomiłem
go ręcznie. Nic go nie uruchomi samo.

## 4. GOTOWE TEKSTY DO WKLEJENIA W REJESTR (przez koordynatorkę)

**(a) `SK-L-005`, kolumna z uzasadnieniem — zamiast fragmentu o reconciliation:**
> `KRYTYCZNA→ŚREDNIA` nie dlatego, że „reconciliation co 10 min" (to WP-Cron, czyli dokładnie
> to, co na hostingu nie chodzi — K10), tylko dlatego, że domknięcie ma drogę **niezależną od
> crona**: `sklepag_dashboard_self_heal()` (`admin-dashboard.php:214-221`) woła reconciliation
> przy KAŻDYM wejściu na Pulpit, a reconciliation pyta Stripe wychodząco, więc ustawienie
> odnośników jej nie dotyczy. Przyczynę widać na każdej stronie wp-admin
> (`admin.php:33-37`, `admin_notices` bez warunku ekranu). Ryzyko resztkowe: brak crona
> **i** brak wejścia na Pulpit = zamówienie nie powstaje dowolnie długo. Zweryfikowane 1.09
> na `main`, `audyt/plamy/SK-L-005-WERDYKT.md`.

**(b) Nowa pozycja — licznik ostrzeżeń NIE widzi ciszy webhooka** (uzasadnienie w §5):
> **DROBNA/ŚREDNIA (do decyzji koordynatorki)** · Pulpit ostrzega o **awariach rozmowy** ze
> Stripe (`admin-dashboard.php:411-420`, typy `checkout_blad`, `reconciliation_blad`,
> `webhook_zly_podpis`, próg ≥3/dobę) — ale **nie ma sygnału o CISZY**: gdy webhook nie
> dociera (SK-L-005, zła ścieżka, WAF, blokada POST), nie powstaje ŻADNE zdarzenie, więc
> licznik pokazuje zero i to zero jest prawdziwe. Klasa: „brak zdarzenia to też zdarzenie".
> Kandydat na sygnał: „od N godzin ani jednego webhooka przy niezerowej sprzedaży".

**(c) Do `SK-L3-002` — jego strażnik dziś nie mierzy:**
> ⚠️ 1.09: `audyt/sklep/testy-audytora/A/L3/test-06-pulpit-milczy-o-stripe.sh` uruchomiony na
> stosie kończy się **`EXIT=0` z komunikatem `POMINIETY: klucz Stripe jest ustawiony`** —
> czyli zielony **bez pomiaru**. To klasa 68 (POMINIĘTE meldowane jako zielone) i klasa `I-12`.
> Pominięcie ma dawać kod **2**, nie 0.

## 5. LICZNIK OSTRZEŻEŃ — dwie różne rzeczy, których nie wolno mylić

**„24 awarie i zero sygnału" z raportu B jest JUŻ w rejestrze** — nie jako osobna pozycja, tylko
w `SK-L3-002` (`REJESTR-ZNALEZISK-SKLEP.md:24`), w kolumnie źródeł:
*„**B:** B/L.json zn.2 Ł-02 (`reconciliation_blad` ×24 bez flagi; niezależnie)"*. Status
**ZAMKNIETE**. Sprawdziłem naprawę na `main`: licznik `awarie_stripe`
(`admin-dashboard.php:411-420`) liczy m.in. **`reconciliation_blad`** z ostatniej doby i przy
**≥3** wyświetla ostrzeżenie z linkiem do dziennika. Czyli tamte 24 awarie **dziś by się
zapaliły**. To pytanie jest zamknięte poprawnie.

⛔ **Ale awaria z `SK-L-005` to inna klasa i licznik jej NIE zobaczy — nigdy.** Cisza webhooka
nie produkuje żadnego z trzech zdarzeń, na które on patrzy:
- `webhook_zly_podpis` — nie, bo weryfikacja podpisu w ogóle się nie odbywa;
- `checkout_blad` — nie, bo tworzenie sesji **działa** (ruch wychodzący);
- `reconciliation_blad` — nie, bo reconciliation **kończy się sukcesem** (to ona ratuje zakup).

Licznik pokazuje `0` i to zero **jest prawdziwe** — po prostu mierzy awarie rozmowy, a nie jej
brak. Sygnałem zastępczym jest pas o odnośnikach, ale on mówi o **przyczynie**, nie o skutku,
i tylko dla tej jednej przyczyny: przy webhooku zablokowanym przez WAF albo złej ścieżce
w Dashboardzie Stripe **nie ma żadnego sygnału**. To jest treść propozycji (b) z §4.

## 6. CZEGO NIE ZMIERZYŁEM

- **Nie przestawiałem odnośników na „Zwykłe" na wspólnym stosie** — po dzisiejszym
  doświadczeniu z podmienioną wtyczką (`GOLDEN-258-WERYFIKACJA.md` §3) nie ruszam ustawień
  witryny, na której pracują inni. Mechanizm 200-zamiast-404 biorę z pomiaru zapisanego
  w rejestrze (`audyt/sklep/testy-audytora/B/L/l04-…-test.sh`, exit 1), nie z własnego przebiegu.
- **Nie mierzyłem, po jakim czasie Stripe przestaje próbować** — nie było potrzeby: przy 2xx
  nie próbuje w ogóle, i to jest w rejestrze.
- **Nie sprawdzałem zachowania przy WAF blokującym POST** — to druga przyczyna tej samej ciszy,
  wymieniona w propozycji (b) jako hipoteza, nie jako pomiar.
