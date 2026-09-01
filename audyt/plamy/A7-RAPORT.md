# A7 — KOD PRODUKTU NASZĄ SIATKĄ

**Kto:** audytor A7, sesja 2026-09-01. **Gałąź:** `audyt/a7-kod-produktu`, odbita ze świeżego
`main` = `d8ed544`. **Zakres:** `wtyczki/` (3 wtyczki, 54 pliki PHP, 28 729 linii kodu i stylów).
**Czego NIE tykałem:** kodu produktu — ani jednej linii. Naprawia builder w osobnej sesji.

⛔ **Przebieg „w ciemno".** Zgodnie z uwagą metodyczną z `PRZEKAZANIE-NASTEPNEJ-SESJI.md`
NIE czytałem siedmiu raportów poprzedniej sesji (`A4b`, `A6`, `C12`, `C13`, `E5`, `F4`, `F5`).
Czytałem wyłącznie: ściągę 93 klas, trzy rejestry znalezisk, werdykt kolegi i `DECYZJE.md` —
czyli materiał, którego zadaniem jest zapobiec duplikatom, a nie podpowiedzieć wynik.

⭐ **Wagi nadane tymi samymi kryteriami co dotąd**, bez podnoszenia pod cel właściciela:
KRYT = bezpieczeństwo / pieniądze / dane osobowe / tekst czytany przez kupującego ·
DUZE = mylące zielone · SRED = realna dziura procesu · MALE = niespójność.
Jedno znalezisko wagi realnej, dwa MALE, osiem terenów czysto. Kwoty do wyrobienia nie miałem.

---

## 0. Jak dzieliłem teren i dlaczego tak

Nie czytałem kodu „od początku do końca". Podzieliłem `wtyczki/` na dziewięć terenów, do każdego
dobrałem JEDNO pytanie i przyrząd, który na nie odpowiada **zestawieniem po całości** — zanim
otworzyłem pierwszy plik (metoda `jak-audytowac` §3, krok 3). Skrypty przyrządów leżą
w katalogu roboczym sesji; każdy pomiar niżej ma wklejone wyjście albo komendę do powtórzenia.

| # | Teren | Pytanie | Wynik |
|---|---|---|---|
| T1 | 63 wejścia (AJAX / admin-post / REST) | czy nonce i uprawnienie stoją PARĄ | ✅ czysto |
| T2 | 240 zapytań SQL | czy każde wejście idzie przez `prepare()`; czy `LIKE` ma `esc_like()` | ✅ czysto |
| T3 | punkty REST + podpis Stripe | czy funkcja autoryzująca ma domyślne otwarcie | ✅ czysto |
| T4 | 493 funkcje produktu | które istnieją, ale nikt ich nie woła | ⚠️ 3 sieroty (`A7-F3`) |
| T5 | 76 strażników zamkniętych znalezisk | czy istnieją i czy wciąż trzymają się kodu | ✅ czysto |
| T6 | wywołania zewnętrzne (Stripe, AI, IMAP) | czy suma prób × limit czasu < limit PHP | ✅ czysto |
| T7 | retencja i strefy czasu | czy klasa `KR6` rozlała się na sklep | ✅ czysto |
| T8 | statyka pod właściwą wersję PHP | czy mierzymy tę wersję, na której produkt chodzi | ⚠️ `A7-F2` |
| T9 | **konfiguracja zewnętrzna, której kod nie widzi** | co produkt bierze ze Stripe'a, a instrukcja o tym milczy | 🔴 **`A7-F1`** |
| T10 | 420 instrukcji `echo`/`print` ze zmienną | czy cokolwiek niezescapowanego dochodzi do wyjścia | ✅ czysto (domknięte po korekcie przyrządu) |

⭐ Teren T9 wyszedł z pytania, które przy infrastrukturze dało trzy znaleziska w jeden wieczór:
**„czego nasza ochrona NIE MOŻE zobaczyć, nawet gdy działa poprawnie"**. Przeniesione na produkt
brzmi: *od czego zależy ten kod, a czego nie ma jak sprawdzić*. Odpowiedź — od tego, jakie
zdarzenia właściciel zaznaczył w Dashboardzie Stripe. I to jest jedyne miejsce, w którym
znalazłem realną wadę.

---

## 1. ZNALEZISKA

### 🔴 `A7-F1` — **KRYT** · instrukcja każe włączyć 2 zdarzenia Stripe, kod potrzebuje jedenastu; zwroty i obciążenia zwrotne NIE DOCHODZĄ do sklepu, a Pulpit liczy je dalej jako przychód

**ZNALEZISKO.** Jedyne miejsce, w którym mówimy właścicielowi, jakie zdarzenia zaznaczyć przy
zakładaniu webhooka (`dokumentacja/INSTRUKCJA-INSTALACJI.md`, Krok 3), wymienia **dwa** zdarzenia
i każe zaznaczyć „**OBA**". Kod `sklepag_webhook()` obsługuje **jedenaście** nazw zdarzeń
w czterech rodzinach, plus prefiks całej rodziny sporu. Właściciel, który wykona instrukcję dosłownie, dostaje sklep, do którego
**nigdy nie dotrze informacja o zwrocie ani o obciążeniu zwrotnym** — a Pulpit liczy przychód
jako `kwota_grosze − zwrocono_grosze`, więc pieniądze oddane kupującemu zostają w statystyce
sprzedaży na zawsze.

**DOWODY.**

Kod nasłuchuje (`wtyczki/sklep-agentow/includes/zakup.php`):
```
351:	if ( in_array( $typ, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
360:	if ( 'checkout.session.async_payment_failed' === $typ || 'checkout.session.expired' === $typ ) {
369:	if ( 'refund.created' === $typ || 'refund.updated' === $typ || 'charge.refunded' === $typ ) {
385:	if ( 0 === strpos( $typ, 'charge.dispute.' ) ) {
```
Rodzina sporu wchodzi prefiksem (`:385`), a cztery jej nazwy są rozróżniane wprost
w `sklepag_zaksieguj_spor()` (`zakup.php:738-742`): `charge.dispute.created`, `charge.dispute.closed`,
`charge.dispute.funds_withdrawn`, `charge.dispute.funds_reinstated`.

Instrukcja klienta mówi (`dokumentacja/INSTRUKCJA-INSTALACJI.md:102-104`):
```
3. Zdarzenia do nasłuchu — zaznacz OBA:
   - `checkout.session.completed` — zwykła płatność kartą,
   - `checkout.session.async_payment_succeeded` — płatności, które potwierdzają
     się z opóźnieniem (m.in. BLIK, przelewy).
```

Pomiar różnicowy, z próbą kontrolną (bo orzekam BRAK):
```
$ grep -oE "'(checkout|charge|refund)\.[a-z_.]+'" wtyczki/sklep-agentow/includes/zakup.php | sort -u | wc -l
12          # = 11 pełnych nazw zdarzeń + 1 prefiks rodziny 'charge.dispute.'
$ grep -oE "\`(checkout|charge|refund)\.[a-z_.*]+\`" dokumentacja/INSTRUKCJA-INSTALACJI.md | sort -u | wc -l
2
$ grep -cniE "refund|dispute" dokumentacja/INSTRUKCJA-INSTALACJI.md
0
$ grep -cniE "refund|dispute|charge\." wtyczki/sklep-agentow/readme.txt
0
# PRÓBA KONTROLNA — czy ten sam wzorzec w tym samym pliku w ogóle coś znajduje:
$ grep -c "checkout\." dokumentacja/INSTRUKCJA-INSTALACJI.md
3
```
Słowa `refund` i `dispute` nie padają w dokumentach klienta ANI RAZU, a ten sam sposób szukania
znajduje w tym samym pliku trzy trafienia na `checkout.` — więc to brak, nie ślepota przyrządu.

**MECHANIZM.** Trzy ogniwa, każde zmierzone:

① `zwrocono_grosze`, status `zwrocone` i status `sporne` zapisują **wyłącznie** dwie funkcje,
i obie mają dokładnie jednego wołającego — webhook:
```
$ grep -rn "zaksieguj_zwrot\|zaksieguj_spor" wtyczki/sklep-agentow --include=*.php
zakup.php:370:		if ( ! sklepag_zaksieguj_zwrot( $typ, $sesja ) ) {   ← w sklepag_webhook()
zakup.php:386:		if ( ! sklepag_zaksieguj_spor( $typ, $sesja ) ) {    ← w sklepag_webhook()
zakup.php:552:function sklepag_zaksieguj_zwrot( $typ, $obiekt ) {
zakup.php:714:function sklepag_zaksieguj_spor( $typ, $obiekt ) {
```

② **Siatka bezpieczeństwa NIE łapie tego przypadku.** `sklepag_dociagnij_zalegle()` odpytuje
Stripe tylko o dwie rzeczy: sesje w stanie `utworzona` (`zakup.php:1291-1296`) i zamówienia bez
maila (`zakup.php:1338-1344`). Nie ma w produkcie ani jednego wywołania pytającego Stripe o zwrot
— cała lista wyjść do API to `/v1/checkout/sessions` (utworzenie, odczyt po id, odczyt po
`payment_intent`), `stripe.php:112,116,129`. Instrukcja obiecuje siatkę w zdaniu, które samo ją
zawęża — i czytelnik tego zawężenia nie zauważy:
`INSTRUKCJA.md:114` *„sklep co 10 minut sam dopytuje Stripe **o opłacone sesje**"*.

③ **Pieniądze.** Pulpit liczy przychód i wykresy z odjęciem zwrotu
(`admin-dashboard.php:157`, `:581`, `:593`):
```
SELECT COALESCE(SUM(GREATEST(CAST(kwota_grosze AS SIGNED) - CAST(zwrocono_grosze AS SIGNED), 0)),0) AS przychod
```
Skoro `zwrocono_grosze` nigdy nie urośnie, odjęcie jest zawsze zerem: **kwota oddana kupującemu
i kwota zdjęta z konta przy obciążeniu zwrotnym zostają w przychodzie właściciela.**

**⭐ To jest dokładnie wzorzec, o który prosił kolega — „zrobione w połowie".** Obsługa zwrotów
i sporów w KODZIE została dopisana i domknięta (`SK-L3-001`, ZAMKNIETE). Do dokumentu, który jako
jedyny decyduje, czy ta obsługa w ogóle dostanie ładunek, **nikt nie wrócił**. Naprawiono
egzemplarz, klasa została opisana — i nic nie pilnowało drugiej połowy.

**Trzecia niespójność, w tej samej sprawie:** dwa dokumenty klienta podają dwie różne listy.
`wtyczki/sklep-agentow/readme.txt:99` mówi o **jednym** zdarzeniu
(*„dla zdarzenia `checkout.session.completed`"*), instrukcja o **dwóch**, kod potrzebuje
**dwunastu**. `DOKUMENTACJA-TECHNICZNA.md:133-140` wymienia komplet poprawnie — ale opisuje,
co kod obsługuje, nie każe niczego zaznaczyć, i nie jest dokumentem, do którego instrukcja
odsyła przy Kroku 3.

**SKUTEK — co traci człowiek.** Właściciel widzi w Pulpicie przychód, którego nie ma: sprzedaż
zwrócona kupującemu i sprzedaż zabrana przez bank liczą się dalej jako pieniądze. Zamówienie
zwrócone zostaje w panelu jako opłacone, więc przycisk „Wyślij ponownie" nadal je obsługuje
(odmowa dotyczy tylko statusów `zwrocone`/`sporne` — `INSTRUKCJA.md:162`), a eksport CSV
i statystyki kłamią. Kupujący nie traci nic; traci właściciel — i traci to po cichu, bo żaden
element sklepu nie ma jak zauważyć, że brakuje mu zdarzeń. Instrukcja obsługi w tym samym
pakiecie zapewnia go wprost, że jest odwrotnie:
`INSTRUKCJA.md:87` *„**zwrócone** … nic — sklep odnotował zwrot z powiadomienia Stripe i odjął
go od statystyk"*.

**RODZAJ SKUTKU:** `wyliczony`. Wartość i sytuacja podane: właściciel wykonuje Krok 3 instrukcji
(zaznacza wskazane dwa zdarzenia), wykonuje zwrot w Dashboardzie Stripe — zdarzenie `refund.created`
nie ma dokąd pójść, `sklepag_zaksieguj_zwrot()` nie zostaje wywołana, `zwrocono_grosze` zostaje 0,
`SUM(kwota_grosze − 0)` liczy pełną kwotę. Skutku nie zaobserwowałem na żywym Stripie — to wymaga
konta i przełączenia webhooka na stanowisku dzielonym z builderem, czego nie ruszałem.

**Waga — dlaczego KRYT, a nie DUZE.** Kryterium z briefingu wymienia „pieniądze" wprost.
Skutek dotyczy pieniędzy bezpośrednio i **nie ma ścieżki kompensacji** — w odróżnieniu od
płatności, gdzie brak webhooka nadrabia cron. ⚠️ Uczciwie: da się to czytać także jako DUZE
(„mylące zielone" — panel świeci przychodem, którego nie ma). Zostawiam KRYT, bo nieodwracalność
i brak siatki przeważają; jeśli koordynatorka woli DUZE, znalezisko nic na tym nie traci.

**Czego NIE twierdzę.** Nie twierdzę, że tak jest na stanowisku właściciela — nie widziałem jego
Dashboardu. Twierdzę, że **kto wykona naszą instrukcję dosłownie, ten to dostanie**, i że produkt
nie ma czym tego zauważyć. Stripe przy zakładaniu punktu końcowego nie zaznacza żadnego zdarzenia
domyślnie, więc lista z instrukcji jest listą, która realnie powstaje.

**Propozycja naprawy (dla buildera — NIE wykonałem):** ① instrukcja i `readme.txt` wymieniają
komplet zdarzeń, generowany z jednego źródła, nie przepisany ręcznie; ② produkt przestaje polegać
na dokumencie: przy zapisie klucza sekretnego odpytać `/v1/webhook_endpoints` i pokazać
w Ustawieniach, których zdarzeń brakuje — wtedy ochrona zaczyna widzieć to, czego dziś zobaczyć
nie może; ③ strażnik: lista zdarzeń w `zakup.php` = lista w instrukcji, próba kontrolna na
usuniętej nazwie. **Ta wada jest naprawialna tylko w OBU miejscach naraz** — sam poprawiony tekst
zostawia klasę otwartą, bo następna nazwa zdarzenia rozjedzie się tak samo.

---

### ⚠️ `A7-F2` — **MALE** · statyka mierzy kod pod PHP 8.1, produkt chodzi na PHP 8.4 (zmierzona różnica DZIŚ: zero)

**ZNALEZISKO.** `phpstan.neon.dist:8` deklaruje `phpVersion: 80100`. Produkt jest opisany
i uruchamiany na PHP 8.4 (`php -v` na stanowisku: `PHP 8.4.24`), a cała racja bytu własnego
klienta IMAP Segregatora to usunięcie `ext/imap` **w PHP 8.4**. Analiza statyczna nie zna więc
wycofań z 8.2–8.4 na kodzie, który na 8.4 realnie działa.

**DOWÓD — pomiar różnicowy, nie domysł:**
```
$ vendor/bin/phpstan analyse --no-progress -c phpstan.neon.dist            → [OK] No errors
$ vendor/bin/phpstan analyse --no-progress -c <ta sama konfiguracja, phpVersion: 80400>  → [OK] No errors
# PRÓBA KONTROLNA (czy przyrząd w ogóle patrzy na te pliki — „0 błędów" i „nic nie
# przeanalizowałem" wyglądają identycznie, klasa 12):
$ vendor/bin/phpstan analyse -c <ta sama konfiguracja, level: 9> | wc -l   → 1048
```
**Różnica między 8.1 a 8.4 wynosi dziś ZERO trafień**, a przyrząd na tych samych ścieżkach umie
znaleźć 1048 rzeczy — więc zero jest prawdziwym zerem, nie ślepotą.

**SKUTEK.** Dziś: żaden. Jutro: pierwsze wycofanie z 8.2–8.4, które ktoś wprowadzi do kodu,
przejdzie przez bramkę na zielono, a wyjdzie jako `Deprecated` w logu na serwerze klienta.
Wpis jest MALE i ma być MALE — to niespójność deklaracji z rzeczywistością, nie wada zachowania.
**RODZAJ SKUTKU:** `zaobserwowany` co do samej niespójności (odczyt pliku + `php -v`);
brak skutku funkcjonalnego jest **zmierzony**, nie założony.

---

### ⚠️ `A7-F3` — **MALE** · trzy funkcje istnieją i nie są wołane; jedna z nich trzyma DRUGĄ kopię reguły stop-lossu

**ZNALEZISKO.** Z 493 funkcji produktu trzy nie mają w `wtyczki/` ani jednego wystąpienia poza
własną definicją:

| funkcja | plik:linia | co to jest |
|---|---|---|
| `chatl_ai_uzycie_dzis()` | `chatbot-live/includes/ai.php:127` | licznik wywołań AI |
| `chatl_ai_tokeny_dzis()` | `chatbot-live/includes/ai.php:206` | suma tokenów płatnych |
| `segm_skrzynka_srodowisko_pada()` | `segregator-maila/includes/skrzynka.php:263` | „czy diagnostyka wykryła coś, co uniemożliwia połączenie" |

**DOWÓD:** przyrząd liczy definicje `^function` i wystąpienia nazwy w całym `wtyczki/`;
wynik `funkcji zdefiniowanych: 493; SIEROT: 3`. Próba kontrolna: ten sam przyrząd nie zgłasza
sierotą żadnej z 490 pozostałych, w tym funkcji wołanych wyłącznie przez `add_action`.

**⛔ Czego to NIE znaczy — sprawdziłem, zanim zgłosiłem.** Stop-loss kosztowy **działa**:
`chatl_ai_dostepny()` (`ai.php:288-303`) czyta `chatl_ai_zuzycie_dnia()` bezpośrednio i sprawdza
oba progi — dobowy limit wywołań i dobowy limit tokenów. Sieroty nie odbierają żadnej ochrony.

**MECHANIZM (dlaczego to mimo wszystko wpis, a nie milczenie).** `chatl_ai_tokeny_dzis()`
(`ai.php:206-209`) liczy tokeny płatne jako `wejscia + wyjscia + cache_zapis`. Dokładnie tę samą
regułę `chatl_ai_dostepny()` **liczy jeszcze raz, wprost w kodzie** (`ai.php:301`). To dwa
zapisy jednej reguły — klasa 27 ściągi („dwa rejestry tej samej rzeczy"). Dziś oba mówią to samo;
koszt pojawia się dopiero wtedy, gdy ktoś zmieni jeden. **SKUTEK dziś: żaden.**
**RODZAJ SKUTKU:** `przypuszczalny` — nie umiem podać sytuacji, w której to dziś szkodzi,
i tak to nazywam, zamiast dorabiać skutek do trafnej obserwacji.

---

## 2. TERENY CZYSTE — pełnoprawny wynik, zdejmują białą plamę z mapy

⭐ Każdy z nich był realnym kandydatem i każdy padł na pomiarze. Zapisuję je z przyrządem,
żeby następny audytor nie płacił drugi raz za to samo.

### T1 · nonce i uprawnienie PARĄ na 63 wejściach — ✅ czysto
Zestawienie wszystkich `wp_ajax_*`, `wp_ajax_nopriv_*` i `admin_post_*` (71 rejestracji, 63 różne
funkcje) + odczyt ciała każdej funkcji, z podążaniem za **jednym poziomem delegacji**.
Bez pary zostaje siedem wejść i **wszystkie siedem jest publicznych z założenia**: formularz
opinii i pobranie (autoryzacja = token z maila), zakup (`check_admin_referer` jest, uprawnienia
z definicji brak), oraz cztery punkty widgetu czatu.

⛔ **Pierwsza wersja tego przyrządu skłamała i mówię o tym wprost.** Bez podążania za delegacją
oskarżyła trzy eksporty CSV ChatBota (`csv.php`) o brak nonce'a i uprawnienia — bo obie kontrole
siedzą w `chatl_csv_brama()` wołanej w pierwszej linii każdego z nich (`csv.php:60`).
Odruch „sprawdź własne narzędzie, zanim oskarżysz cudze" oszczędził trzy fałszywe alarmy.

### T2 · SQL — ✅ czysto
Przyrząd wyciąga **zbalansowany argument** każdego z 240 wywołań `$wpdb->query|get_var|get_row|get_col|get_results`
(nie regeksem po linii — klasa 11) i pyta, czy zawiera `prepare()` albo choć jedną zmienną.
Wynik: `225 czystych, 15 podejrzanych` — z czego **14 to nazwa tabeli z `$wpdb->prefix`**,
a piętnaste (`class-sklepag-lista-zdarzen.php:92`, interpolowane `{$gdzie}`) składa WHERE
z dwóch sztywnych stałych po allowliście `in_array( $z, array( 'admin', 'system' ), true )`
(`:71`). Zero wejścia użytkownika w SQL bez `prepare()`.
`LIKE`: wszystkie 9 wystąpień z wartością mają `$wpdb->esc_like()` — klasa nr 2 kolegi
(kasowanie cudzych wpisów) nie ma tu egzemplarza.

### T3 · autoryzacja bez domyślnego otwarcia — ✅ czysto
Trzy punkty REST mają jawny `permission_callback`. Dwa publiczne (`sklepag/v1/webhook`,
`sklepag/v1/status`) niosą autoryzację w środku. **Sprawdziłem to, co briefing nazywa
najgroźniejszym wariantem — funkcję autoryzującą przy braku danych:**
`sklepag_stripe_weryfikuj_podpis()` (`stripe.php:145-147`) przy **pustym sekrecie** albo pustym
nagłówku zwraca `false`, nie `true`. Brak danych = odmowa. Publiczny `/status` oddaje wyłącznie
słowo statusu, pod klucz `cs_…` (identyfikator sesji Stripe), za limiterem 30/min.

### T5 · strażnicy zamkniętych znalezisk — ✅ czysto (to był NAJMOCNIEJSZY kandydat)
Meta-uwaga kolegi („czy Claude tego nie ominął albo nie zostawił na później") przeniesiona
na produkt brzmi: *czy strażniki, którymi zamknęliśmy 89 znalezisk, w ogóle jeszcze istnieją
i czy trzymają się dzisiejszego kodu.* Zmierzone:
- **76 odsyłaczy do plików** w trzech rejestrach (`REJESTR-ZNALEZISK.md` 28 · `sklep/REJESTR-ZNALEZISK-SKLEP.md` 40 · `REJESTR-ZNALEZISK-INFRA.md` 8) → **wszystkie 76 istnieją na dysku**, zero martwych odsyłaczy (a martwy odsyłacz UDAJE, że strażnik stoi);
- **57 testów PHP** w `testy/` → `php -l` przechodzi na wszystkich, **0 padnięć składni**;
- żaden z 57 nie woła funkcji produktu, która przestała istnieć (porównanie z 493 definicjami).

⛔ **Trzy trafienia w tym ostatnim pomiarze były fałszywe i sprawdziłem je, zanim zgłosiłem:**
`chatl_pulapka`, `chatl_sesje`, `chatl_zdarzenia` to nazwy **tabel i opcji** w treści SQL
(`INSERT INTO {$wpdb->prefix}chatl_sesje (sesja, …`), które mój wzorzec wziął za wywołanie
funkcji przez nawias po nazwie. Zero realnych.
⚠️ Czego ten teren NIE rozstrzyga: **czy te strażniki przechodzą**. Wymagają żywego stosu
dzielonego z builderem — nie uruchamiałem. Wiem, że istnieją i wiążą się z kodem; nie orzekam,
że są zielone.

### T6 · limity czasu wywołań zewnętrznych — ✅ czysto (klasa własna egzaminatora)
Klasa z `catsnboard`: 25 × retry ≈ 100 s przy limicie hostingu 30 s. Tutaj:
Stripe `timeout 15` bez pętli (`stripe.php:38`) · ChatBot AI `CHATL_AI_TIMEOUT = 9` + **jeden**
retry = 18 s (`ai.php:31`; wywołanie `:722`, ponowienie `:727`) · Segregator AI `SEGM_AI_TIMEOUT = 9` + jeden retry
(`ai.php:32`, `:436-439`) · IMAP: gniazdo 15 s, przebieg 20 s (`skrzynka.php:30,35`).
**Sprawdziłem też, czy ogranicznik jest WOŁANY, a nie tylko istnieje** (pytanie 4 briefingu):
pętla partii `array_chunk( $maile, SEGM_AI_PARTIA )` (`ai.php:577`) mogłaby wykonać wiele
wywołań — ale `SEGM_AI_MAX_PROBKA` jest egzekwowane **przed** nią, w `silnik.php:134`, i drugi
raz w panelu (`admin.php:463`, opcja wyszarzona z wyjaśnieniem). Suma zawsze poniżej 30 s.

### T7 · strefy czasu w retencji — ✅ czysto (klasa `KR6` NIE rozlała się)
`KR6` mówiła: retencja ChatBota kasowała rozmowy przed obiecanym terminem w strefie ujemnej.
Pytanie klasowe: czy sklep ma tego bliźniaka. Nie ma — obie strony porównania są w tym samym
układzie: kolumny pisane `current_time( 'mysql' )` (czas witryny), granica
`gmdate( …, strtotime( current_time( 'mysql' ) ) − 90 dni )` (`zakup.php:1370`), czyli też czas
witryny. ChatBot liczy dziś granicę przez `wp_date()`, Segregator dokłada `gmt_offset` —
trzy różne zapisy, wszystkie spójne wewnętrznie.

### T8 · przychód a waluta ze Stripe'a — ✅ czysto
Kandydat: Adaptive Pricing przewalutowuje kupującemu cenę, a my zapisujemy swoją.
Nieprawda — `sklepag_domknij_zakup()` bierze `amount_total` i `currency` **z sesji Stripe**
(`zakup.php:839-840`), a do ceny z bazy schodzi dopiero, gdy sesja ich nie ma. Instrukcja
poświęca temu dwa ostrzeżenia z miejscem w Dashboardzie (`INSTRUKCJA-INSTALACJI.md:154-170`).

### T10 · escapowanie wyjścia — ✅ czysto (domknięte PO korekcie własnego przyrządu)
⛔ **Ten teren najpierw oznaczyłem jako `NIE SPRAWDZIŁEM` i to była prawda o pierwszej wersji
przyrządu**, nie o kodzie: skrypt zdejmował samą NAZWĘ funkcji escapującej, zostawiał jej argument
i meldował **371 trafień na 371 linii** — czyli mianownik równy licznikowi, klasyczne „narzędzie
o zakresie szerszym niż jego rozdzielczość". Poprawiony przyrząd usuwa **zbalansowane** wywołania
funkcji bezpiecznych wraz z argumentami, odcina WARUNEK ternariusza (tam zmienna nie trafia
na wyjście) i czyta instrukcję `echo` **w całości do średnika**, a nie linię po linii.

**Pomiar:** 420 instrukcji `echo`/`print` ze zmienną → **31 kandydatów** → **31 obejrzanych,
0 realnych**. Wszystkie 31 mają escape w gałęzi ternariusza albo w zagnieżdżonym wywołaniu
(np. `admin-zamowienie.php:347` = `esc_html( $zam->mail_tresc )` w gałęzi, `:327` =
`sklepag_badge_statusu()`, która escapuje wszystkie trzy pola u siebie), albo są pobieranym
plikiem, nie HTML-em, z jawnym `phpcs:ignore` i powodem (`filtry.php:579,585` — XML/tekst dla
Gmaila i Outlooka; `konfiguracja.php:105` — JSON konfiguracji).

**KALIBRACJA przyrządu — bo „31 fałszywych alarmów" i „skrypt ślepy" wyglądają identycznie.**
Na podłożonej atrapie z dwiema wadami (goła zmienna w treści `<td>`, goła zmienna w atrybucie
`href`) i dwiema liniami czystymi (escape + ternariusz z literałami) przyrząd zgłosił
**2 z 2 mutantów i 0 fałszywych**. Dopiero po tym pomiarze mówię „czysto".

### T4b · uninstall i symetria zapis↔sprzątanie — ✅ czysto w zakresie, który sprawdziłem
Trzy pliki `uninstall.php` kasują tabele i opcje; usuwanie opcji idzie przez
`esc_like()` + `prepare()` (klasa nr 1 i nr 2 kolegi — klucz API i cudze transienty).
⚠️ Zakres: **czytałem, nie uruchamiałem** — realna deinstalacja wymaga usunięcia wtyczki
ze wspólnego stosu.

---

## 3. ⭐ CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Sekcja obowiązkowa: lista znanych klas kotwiczy, więc muszę nazwać, o co moje pytania NIE pytały.

1. **Zachowanie w przeglądarce.** Cała moja siatka jest statyczna i sięga po `php -l`, `phpstan`
   i zestawienia. Nie kliknąłem ani razu. Trzy z czterech znalezisk `A4b` poprzedniej sesji
   siedziały w STANIE ekranu, nie w adresie — mój przebieg takiej wady nie mógł zobaczyć
   **z konstrukcji**, nie z niedopatrzenia.
2. ~~**Escapowanie wyjścia** — `NIE SPRAWDZIŁEM`.~~ ✅ **DOMKNIĘTE po napisaniu tej sekcji**,
   patrz teren T10 wyżej. Zostawiam skreślenie, a nie kasuję zdania: pierwsza wersja mojego
   przyrządu meldowała 371 trafień na 371 linii i uczciwym werdyktem było wtedy „nie sprawdziłem",
   nie „czysto". Poprawiony przyrząd + kalibracja na podłożonej wadzie dały wynik czysty.
3. **Wnętrze klienta IMAP** (`class-segm-imap.php`, 1123 linie) — parsowanie odpowiedzi serwera
   z sieci to najbardziej surowe wejście w całym produkcie i nie tknąłem go ani jednym pytaniem.
4. **Treść tekstów sprzedażowych** wobec prawa i wobec produktu — mierzyłem LICZBY i NAZWY
   ZDARZEŃ, nie sens zdań.
5. **Wydajność przy danych** — zero pomiarów na objętości; nie pytałem o zapytanie w pętli.
6. **Drugi zakup, wygaśnięcie, przejście na klucze produkcyjne** — z pytania 5 briefingu zdążyłem
   przejść wyłącznie ZWROT i SPÓR (i tam siedzi `A7-F1`). Pozostałe trzy zmiany stanu są nietknięte.

## 4. ODRZUCONE — tropy, które sam obaliłem

| trop | dlaczego odrzucony |
|---|---|
| zwrot nie odcina linku pobrania | **znane i świadome**: `RAPORT-ODDANIA.md:95` i `INSTRUKCJA.md:90-91` mówią to wprost; decyzja właściciela, nie wada |
| stary plik produktu zostaje po podmianie ZIP-a | zamierzone — kupujący ma snapshot (`produkt_plik_kupiony`), podmiana nie może mu zabrać paczki |
| brak obsługi VAT / faktur w kodzie | reguły spoza zamówienia się nie wymyśla; faktury wystawia właściciel ręcznie, mail mówi to wprost (`mail-html.php:167-169`) |
| `is_page( 0 )` prawdą wszędzie (klasa 106) | **już naprawione**: `sklep-agentow.php:141` ma `$id > 0 && is_page( $id )` |
| fail-open limitera przy awarii bazy (`zakup.php:63`) | świadome i uzasadnione w kodzie: limiter chroni przed spamem, autoryzacja jest gdzie indziej (nonce/podpis/token) — ta sama decyzja co `chatl_rate_limit()` w rejestrze |
| różnica stop-lossów: ChatBot ma limit tokenów, Segregator tylko limit wywołań | Segregator ma twardy sufit próbki (`SEGM_AI_MAX_PROBKA` = 30), więc koszt przebiegu jest ograniczony z góry inną drogą; nie zgłaszam jako braku |

## 5. CO Z TYM ZROBIĆ — dla koordynatorki

- `A7-F1` jest znaleziskiem **produktu i dokumentu naraz**: wymaga wiersza w rejestrze
  (`audyt/REJESTR-ZNALEZISK.md` albo `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` — nie rozstrzygam,
  to nie moja decyzja) oraz **świadomego podniesienia licznika OTWARTYCH**. Nie ruszałem liczników
  sam: rejestr mówi, że wzrost musi być świadomy, a to jest ruch koordynatorki.
- `A7-F1` **nie ma dziś strażnika i to jest połowa jego wagi.** Strażnik proponowany w opisie
  (lista zdarzeń w kodzie = lista w instrukcji) jest tani i zamyka klasę, nie egzemplarz.
- `A7-F2` i `A7-F3` to MALE bez skutku dzisiaj — do paczki porządkowej, nie do osobnego PR-a.
- Duplikatów nie zgłaszam: przed pisaniem przeszedłem **89 pozycji** trzech rejestrów
  (28 + 33 + 28) i `RAPORT-ODDANIA.md`; dwa moje tropy okazały się tam opisane i wylądowały
  w sekcji ODRZUCONE, nie w znaleziskach.
