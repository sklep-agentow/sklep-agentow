# RAPORT A6 — DOKUMENTACJA vs KOD, kierunek ODWROTNY

**Pytanie:** *„Co produkt ROBI, o czym dokumentacja MILCZY?"* — idę od kodu do dokumentacji,
nigdy odwrotnie.
**commit_head:** `3fc884d9d7c3d9b47b8229b4c3be5ddcd1eea5e4` (`3fc884d`, `origin/main`)
**Ustawienie drzewa:** `git fetch origin && git checkout -B audyt/a6-odwrotny origin/main` → czysto.

> ⚠️ **`origin/main` przesunął się w trakcie audytu** — z `3fc884d` na `67726f8` (scalony PR #135).
> Sprawdziłem, czy to unieważnia pomiary: `git diff --stat 3fc884d..origin/main -- wtyczki/`
> jest **pusty** — scalenie wniosło wyłącznie raport A3 i trzy pliki testów, kodu produktu
> nie ruszyło. Wszystkie znaleziska stoją. Gałąź przestawiona na aktualny main; `commit_head`
> pomiaru zostaje `3fc884d`, bo tam mierzyłem.

---

## PODSUMOWANIE

Zbudowałem grepem inwentarz tego, co oba boty naprawdę potrafią — 15 podstron panelu,
46 akcji `admin_post_`, 4 publiczne AJAX-y, 1 punkt REST, 3 zadania cykliczne, 5 własnych
filtrów, 8 opcji, 3 stałe `wp-config.php`, 0 shortcode'ów, 0 komend WP-CLI — i dla każdej
pozycji sprawdziłem, czy dokumentacja o niej mówi. **Dwa znaleziska, oba ze śladem wykonania.**
Poważniejsze jest pierwsze i dotyczy produktu: deklaracja prywatności ChatBota wylicza
**trzy** rzeczy, które w trybie AI opuszczają WordPressa, a kod wysyła **czwartą** —
do sześciu wcześniejszych wymian tej samej rozmowy, czyli wszystko, co gość napisał w tym
oknie zanim zadał bieżące pytanie. Przechwyciłem prawdziwy ładunek wychodzący: siedzą w nim
imię, nazwisko i numer telefonu z poprzedniej wiadomości. Właściciel pisze własną politykę
prywatności z tej sekcji, więc niepełne wyliczenie to sprawa RODO, nie literówka. Drugie
dotyczy materiału audytowego: `02-fakty-srodowiska.md` w liście „TEGO NIE ZGŁASZAJ" twierdzi,
że Segregator nie ma podpięcia skrzynki IMAP, a ChatBot nie ma czatu z operatorem — obie
funkcje są w produkcie od 20–21.08 i są jawnie zadeklarowane w `PONAD-SPECYFIKACJE-BOTY.md`;
sweep granic #113 poprawił pięć plików dokumentacji produktu i **materiału nie objął**.
Reszta wyszła czysto: CHANGELOG-i pokrywają wszystkie funkcje dołożone przed zamrożeniem,
komendy z obu instrukcji działają dosłownie po wpisaniu, deklaracja prywatności Segregatora
opisuje IMAP wprost, a wszystkie obietnice ze stron produktów mają pokrycie w kodzie.
**Trzy własne tropy odrzuciłem** po sprawdzeniu — opisuję je, bo pokazują, gdzie ta metoda
daje fałszywe alarmy.

---

## CO TEN PRODUKT MA ROBIĆ — jednym akapitem, z materiału

Dwie wtyczki WordPressa sprzedawane jako osobne produkty we własnym sklepie. **ChatBot LIVE**
stawia na stronie firmowej pływające okno czatu, które odpowiada na powtarzalne pytania
z bazy pytań-odpowiedzi prowadzonej przez właściciela; gdy nie zna odpowiedzi, nie zmyśla,
tylko zbiera kontakt i powiadamia właściciela mailem — i to jest sens członu „LIVE": most do
żywego człowieka. **Segregator maila** rozdziela pocztę do kategorii według reguł ułożonych
przez właściciela (pierwsza pasująca wygrywa, bez pola „priorytet") i pokazuje raport, ile
pracy wykonał. Oba boty są **hybrydą**: silnik regułowy działa zawsze i za darmo, a po
wpisaniu własnego klucza API klasyfikację lub odpowiedź przejmuje model — z zakazem zgadywania,
dziennym stop-lossem i automatycznym zjazdem do reguł przy awarii. Względem litery zamówienia
produkt urósł w dwóch dużych miejscach, oba jawnie zadeklarowane: ChatBot dostał **przejęcie
rozmowy przez człowieka**, a Segregator **przebieg na prawdziwej skrzynce IMAP** (domyślnie
na sucho, realne przenoszenie za osobnym potwierdzeniem). Obie wtyczki mają trzymać się
standardów WordPressa (nonce+capability, `prepare()`, czysty uninstall), nie mieć zależności
zewnętrznych i nieść **jawną deklarację prywatności** rozdzielającą tryb regułowy od AI.

---

## 🐞 ZNALEZISKO A6-1 — deklaracja prywatności ChatBota nie wymienia wszystkiego, co wychodzi do API

| | |
|---|---|
| **Wtyczka** | `chatbot-live` |
| **Dokument** | `wtyczki/chatbot-live/INSTRUKCJA.md` §9 „Deklaracja prywatności (ważne — RODO)" oraz `readme.txt` FAQ „Czy dane rozmów wychodzą poza mój serwer?" |
| **Kod** | `includes/front.php:456` → `includes/ai.php:403` (`'messages' => array_merge( $historia, … )`), `includes/ai.php:47` (`const CHATL_AI_PAMIEC_WYMIAN = 6`) |
| **Test** | `testy/a6-deklaracja-vs-ladunek-ai.php` |
| **Kalibracja** | ✅ pada na dokumencie z repo (EXIT=1), przechodzi po dopisaniu jednego zdania (EXIT=0); kontrola mechanizmu i przypadek bez wady trzymają w obu przebiegach |
| **Waga** | poważne — RODO, właściciel pisze z tej sekcji własną politykę prywatności |

### Na czym polega

Deklaracja wylicza **dokładnie trzy** składniki, które w trybie AI opuszczają WordPressa:

> **Tryb AI:** treść pytania gościa wraz z Twoim opisem firmy i bazą Q&A jest wysyłana
> do API dostawcy modelu (Anthropic) w celu wygenerowania odpowiedzi.

`readme.txt` powtarza to samo wyliczenie. Kod wysyła **czwarty** składnik: `front.php:456`
przekazuje `chatl_ai_historia( $sesja, $id_pytania )` do `chatl_ai_odpowiedz()`, a ta robi
`array_merge( $historia, [bieżące pytanie] )` na polu `messages`. To do **sześciu ostatnich
wymian** tej rozmowy — czyli treść, którą gość napisał wcześniej w tym samym oknie.

To nie jest teoria o tym, „co mogłoby tam być": gość, który najpierw przedstawia się z imienia
i podaje telefon, a potem pyta o godziny otwarcia, wysyła do dostawcy **oba** zdania.

⚖️ **Uczciwie o zakresie:** dokumentacja techniczna **mówi** o tym wprost
(`DOKUMENTACJA-TECHNICZNA.md:215` — *„ile ostatnich wymian bieżącej rozmowy trafia do
wywołania modelu"*), a instrukcja opisuje pamięć rozmowy jako **funkcję** (`INSTRUKCJA.md:157`,
`readme.txt:161`). Milczy konkretnie **sekcja, z której właściciel przepisuje treść do własnej
polityki prywatności** — i to jest miejsce, w którym wyliczenie musi być kompletne.

### Dowód wykonania

Przechwycone prawdziwe żądanie wychodzące (filtr `pre_http_request` — nic nie poleciało
do API, klucz i limit nietknięte):

```
  === CO NAPRAWDE POSZLOBY DO api.anthropic.com ===
  pol najwyzszego poziomu: model, max_tokens, system, messages
  messages[] — liczba wpisow: 3
    [0] user      : Nazywam sie Jan Kowalski, tel 555-111-222, chce reklamowac zamowienie
    [1] assistant : Przykro mi, prosze zostawic maila.
    [2] user      : A jakie sa godziny otwarcia?

  czy WCZESNIEJSZA wiadomosc goscia ("Jan Kowalski") jest w ladunku: TAK — opuszcza WordPressa
  czy telefon goscia (555-111-222) jest w ladunku: TAK
```

Strażnik `testy/a6-deklaracja-vs-ladunek-ai.php`, na dokumencie z repo:

```
  OK   0. kontrola: w ladunku SA skladniki, ktore deklaracja wymienia  [pytanie goscia + blok systemowy]
  BLAD 1. deklaracja NIE wymienia wczesniejszych wiadomosci goscia, a one wychodza do API
         [w ladunku jest: "Jan Kowalski, tel 555-111-222"]
  OK   2. deklaracja nie wymienia niczego ponad to, co kod wysyla
PRZYPADKOW NIEZALICZONYCH: 1        EXIT=1
```

Kalibracja odwrotna — do kopii instrukcji **w scratchpadzie** (repo nietknięte) dopisane jedno
zdanie o wcześniejszych wymianach:

```
  OK   0. kontrola: w ladunku SA skladniki, ktore deklaracja wymienia
  OK   1. deklaracja wymienia wczesniejsze wiadomosci rozmowy
  OK   2. deklaracja nie wymienia niczego ponad to, co kod wysyla
WSZYSTKIE KROKI PRZESZLY            EXIT=0
```

**Kontrola tożsamości dokumentu** (żeby nie zmierzyć nie tego pliku):
`md5sum` instrukcji w kontenerze = `md5sum` w repo = `f3011d82d48367d4e17da4ad7ef5b324`.

Test ma **kontrolę mechanizmu** (przypadek 0: w ładunku muszą być składniki, które deklaracja
wymienia — bez tego padnięcie w 1 nic nie znaczy) i **przypadek bez wady** (przypadek 2:
deklaracja nie może wymieniać czegoś, czego kod nie wysyła — inaczej „naprawa" przez dopisanie
wszystkiego świeciłaby na zielono).

---

## 🐞 ZNALEZISKO A6-2 — materiał audytowy twierdzi, że dwie największe funkcje nie istnieją

| | |
|---|---|
| **Dokument** | `audyt/boty/material/02-fakty-srodowiska.md`, sekcja „⛔ TEGO NIE ZGŁASZAJ", pozycje 1 i 2 |
| **Kontrprzykład w repo** | `boty/PONAD-SPECYFIKACJE-BOTY.md` (Segregator #7 ⭐ IMAP; sekcja „Czego CELOWO nie ma" — obie pozycje oznaczone jako **nieaktualne** od 20/21.08) |
| **Dowód** | `git show --stat e5de485` |
| **Waga** | średnia dla produktu, **wysoka dla procesu** — każdy następny audyt startuje z fałszywym modelem produktu |

### Na czym polega

Materiał, który audytor ma czytać **przed** rozpoczęciem, mówi:

> 1. **Brak podpięcia prawdziwej skrzynki (IMAP/OAuth) w Segregatorze** — świadoma decyzja
>    zamawiającego (D19). Bot działa na próbkach CSV. To jest w zamówieniu.
> 2. **Brak czatu z żywym operatorem / pamięci między wizytami w ChatBocie** — jw.

Oba zdania są nieprawdziwe wobec kodu na `3fc884d`. Segregator ma własny klient IMAP4rev1
(`includes/class-segm-imap.php`), podstronę „Skrzynka (IMAP)", realne przenoszenie wiadomości
na serwerze poczty, cofanie przebiegu i zadanie cykliczne. ChatBot ma podstronę „Rozmowy na
żywo", przyciski **Przejmij** / **Zakończ**, wysyłkę wiadomości operatora i punkt REST do
odpytywania. Obie funkcje są **jawnie zadeklarowane** w `PONAD-SPECYFIKACJE-BOTY.md`, gdzie
stare granice są wprost oznaczone jako *„⛔ nieaktualne od 2026-08-20 / 2026-08-21"*.

Przyczyna jest widoczna w historii: sweep granic #113 był dokładnie o tym, że *„zdania «bez
czatu operatora» przestały być prawdą"* — i objął pięć plików, **żaden z nich nie był materiałem
audytowym**:

```
$ git show --stat e5de485
e5de485 2026-08-21 docs: sweep granic — zdania "bez czatu operatora" przestaly byc prawda (L9, punkt C) (#113)
 boty/PONAD-SPECYFIKACJE-BOTY.md                 |  6 +++++-
 boty/SZKICE-BOTOW.md                            | 22 +++++++++++++++++-----
 wtyczki/chatbot-live/DOKUMENTACJA-TECHNICZNA.md |  5 ++++-
 wtyczki/chatbot-live/INSTRUKCJA.md              | 23 ++++++++++++++++++-----
 wtyczki/chatbot-live/readme.txt                 |  6 +++++-
```

Ten sam plik podaje też nieaktualne wersje jako **zmierzone**:

```
$ grep "Wtyczki aktywne" audyt/boty/material/02-fakty-srodowiska.md
| Wtyczki aktywne | `chatbot-live 1.2.1`, `segregator-maila 1.2.1`, ... | `wp plugin list` |
$ grep "^ \* Version:" wtyczki/*/[cs]*.php
 * Version: 1.3.1      (segregator-maila)
 * Version: 1.5.8      (chatbot-live)
```

### Dlaczego to zgłaszam mimo zakazu zgłaszania rzeczy z tej listy

Lista mówi „nie zgłaszaj **braku** tych funkcji". Ja zgłaszam coś odwrotnego: że **funkcje są**,
a materiał twierdzi, że ich nie ma. `MATERIAL-DLA-AUDYTORA.md` wprost o to prosi: *„jeśli
materiał ma dziurę — to jest znalezisko, zgłoś je"*. Praktyczny koszt jest mierzalny: ta lista
buduje u audytora model produktu, w którym dwie największe funkcje nie istnieją, więc nie
trafiają one na jego listę celów. Mnie samego omal nie wyprowadziła w pole.

---

## ✅ CO WYSZŁO CZYSTO — z komendami

### Inwentarz zbudowany z kodu (nie z pamięci)

```bash
grep -rn "add_menu_page\|add_submenu_page" wtyczki/*/  --include=*.php
grep -rn "wp_ajax_\|register_rest_route\|add_shortcode\|WP_CLI::add_command" wtyczki/*/ --include=*.php
grep -rho "admin_post_[a-z0-9_]*" wtyczki/*/ --include=*.php | sort -u
grep -rhn "apply_filters(\|do_action(" wtyczki/*/ --include=*.php | grep -oE "(apply_filters|do_action)\(\s*'[a-z0-9_]+'" | sort -u
grep -rhoE "(update_option|add_option|set_transient)\(\s*'[a-zA-Z0-9_]+'" wtyczki/*/ --include=*.php | sort -u
grep -rn "wp_schedule_event\|cron_schedules" wtyczki/*/ --include=*.php
```

Wynik: **15 podstron**, **46 akcji `admin_post_`**, **4 publiczne AJAX-y**, **1 REST**
(`chatl/v1/rozmowa`), **3 zadania cykliczne**, **5 filtrów** (`chatl_ai_model`, `chatl_ai_url`,
`chatl_ip_goscia`, `segm_ai_model`, `segm_ai_url`), **8 opcji**, **3 stałe `wp-config.php`**,
**0 shortcode'ów**, **0 komend WP-CLI**.

### Tabela pokrycia — pozycje, które sprawdzałem najostrzej

| Pozycja z kodu | INSTRUKCJA | readme | DOK-TECH | strona produktu | Werdykt |
|---|---|---|---|---|---|
| Rozmowy na żywo / przejęcie rozmowy | ✅ | ✅ | ✅ | ✅ (l. 44–48) | ✅ |
| Skrzynka IMAP + tryb na sucho | ✅ | ✅ | ✅ | ✅ | ✅ |
| Import/eksport CSV bazy Q&A | ⛔ **milczy** | ✅ | ✅ | ✅ | 🔻 **#133 w locie — naprawa PEŁNA**, patrz niżej |
| Podstrona „Start" | ⛔ **milczy** | ✅ | ✅ | — | 🔻 **#133 w locie — naprawa PEŁNA** |
| Stronicowanie list panelu | ⛔ **milczy** | — | ✅ | — | 🔻 **#133 w locie — naprawa PEŁNA** |
| „Przeprowadzka" (eksport/import JSON reguł) | ✅ (l. 68) | ✅ (l. 134) | ✅ | — | ✅ |
| Eksport filtrów Gmail/Outlook/CSV | ✅ | ✅ | ✅ | ✅ | ✅ |
| Filtry `*_ai_model`, `*_ai_url` | ✅ | — | ✅ | — | ✅ |
| Filtr `chatl_ip_goscia` | — | — | ✅ | — | ✅ (filtr techniczny, miejsce właściwe) |
| REST `chatl/v1/rozmowa` | — | — | ✅ | — | ✅ |
| Stałe `CHATL_KLUCZ_API`, `SEGM_KLUCZ_API`, `SEGM_IMAP_HASLO` | ✅ | ✅ | ✅ | — | ✅ |
| Deklaracja prywatności Segregatora (IMAP, retencja, zgoda) | ✅ pełna | — | ✅ | — | ✅ |

### Naprawa w locie #133 — sprawdzona, czy PEŁNA

Zasady każą nie zgłaszać zmian w locie jako nowych wad, ale ocenić, czy naprawa nie jest
dziurawa. Pobrałem gałąź i porównałem:

```bash
git fetch origin 'refs/pull/133/head:pr133'
git show 7f061d0:wtyczki/chatbot-live/INSTRUKCJA.md | grep -E "^#{1,3} "
```

Po naprawie instrukcja ma nowe sekcje: **„2a. Baza pytań z pliku — import i eksport CSV"**,
„Test bota bez wychodzenia na stronę", „Gość widzi, że bot pisze", **„Długie listy mają strony"**,
oraz akapit **„Podstrona «Start»"**. Liczniki po naprawie: `import`=5, `eksport`=4, `csv`=4,
stronicowanie=1, Start=1. **Naprawa pokrywa wszystkie cztery braki i dokłada eksport CSV,
którego opis PR-a nawet nie obiecywał.** Nie jest dziurawa — nie zgłaszam.

### Drugi ruch — historia zmian vs CHANGELOG

```bash
git log --since="2026-08-14" --oneline origin/main -- wtyczki/chatbot-live wtyczki/segregator-maila | wc -l   # 64
```

Funkcje dołożone tuż przed zamrożeniem (import CSV #100/1.5.7, stronicowanie #108/1.5.6,
pułapka #106/1.5.4, RODO #97/1.5.2, przejęcie rozmowy #95/1.5.0, IMAP #70) — **każda ma wpis
w CHANGELOG-u** odpowiedniej wtyczki. Liczniki trafień: import 4, stronicowanie 1, pułapka 4,
RODO 4, przejęcie 5, pamięć rozmowy 2, IMAP/skrzynka 11, Gmail/Outlook 3. ✅ czysto.

### Komendy z instrukcji WPISANE, nie przeczytane

Obie instrukcje każą wpisać do `wp-config.php` trzy stałe. Wpisałem je i sprawdziłem, czy kod
je czyta i czy mają pierwszeństwo nad bazą:

```
  CHATL_KLUCZ_API  -> chatl_klucz_ze_stalej()=TAK | chatl_klucz_api()=sk-ant-TEST-CHATL-Z-INSTRUKCJI
  SEGM_KLUCZ_API   -> segm_klucz_ze_stalej()=TAK  | segm_klucz_api()=sk-ant-TEST-SEGM-Z-INSTRUKCJI
  SEGM_IMAP_HASLO  -> segm_skrzynka_haslo_ze_stalej()=TAK | segm_skrzynka_haslo()=haslo-aplikacji-z-instrukcji
```
✅ czysto — wszystkie trzy działają dosłownie.

### Obietnice ze stron produktów → pokrycie w kodzie

| Obietnica | Pokrycie |
|---|---|
| „domyślnie chodzi NA SUCHO, nie rusza ani jednej wiadomości" | `skrzynka.php:59` `'tryb' => 'na_sucho'` + `EXAMINE` (4×) w kliencie IMAP |
| „ostatni przebieg da się cofnąć jednym kliknięciem" | `segm_post_skrzynka_cofnij` + `segm_skrzynka_cofnij()` |
| „całość może chodzić sama według harmonogramu" | `segm_skrzynka_cron` (5 wystąpień) |
| „eksport dokłada wykluczenia, żeby wynik zgadzał się z raportem" | `doesNotHaveTheWord` (2×) w `filtry.php` |
| „czego przełożyć się nie da, dostajesz wypisane" | `segm_filtry_ostrzezenia()` (5×) |
| „po pięciu minutach Twojej ciszy rozmowa sama wraca do bota" | `const CHATL_PRZEJECIE_CISZA = 300` |
✅ czysto.

---

## ⚠️ TRZY WŁASNE TROPY, KTÓRE ODRZUCIŁEM

Opisuję je, bo pokazują, gdzie ta metoda produkuje fałszywe alarmy — i bo audytor, który
zgłasza wszystko, co mu mignęło, jest bezużyteczny.

1. **„Segregator: eksport/import konfiguracji nieudokumentowany"** — mój pierwszy przebieg
   dał `⛔ MILCZY WSZĘDZIE`. Przyczyna była w mojej regexie, nie w produkcie: funkcja nazywa
   się w dokumentach **„Przeprowadzka: zabierz reguły ze sobą"**, a ja szukałem słowa
   „konfiguracja". Jest opisana w `INSTRUKCJA.md:68` i `readme.txt:134`. **Odrzucone.**
2. **„Strona produktu nie mówi o przejęciu rozmowy"** — pierwsza, wąska regexa dała zero
   trafień. Szersza pokazała `TRESC-STRON-PRODUKTOW.md:44-48`: *„«LIVE» znaczy dwie rzeczy…
   możesz w każdej chwili PRZEJĄĆ rozmowę"*. **Odrzucone.**
3. **„Kod robi więcej niż zamówienie — IMAP i operator bez deklaracji"** — zamówienie
   wymienia oba jako jawne granice, więc trop wyglądał na klasę #6 ściągi egzaminatora.
   `PONAD-SPECYFIKACJE-BOTY.md` deklaruje **oba**, z powodem, bezpiecznikiem i węższymi
   granicami. **Odrzucone** — i to właśnie ten plik dał mi znalezisko A6-2, tylko od drugiej
   strony.

---

## STAN MOJEJ SEKCJI MAPY POLOWANIA (A6 — 5 celów)

| Cel | Wynik |
|---|---|
| Każda LICZBA z instrukcji ma pokrycie w kodzie (G4) | ⏭️ **poza moim zleceniem** — zamknięte PR #131 (`LICZBY-DOKUMENTACJA-VS-KOD.md`); soczewka A6-odwrotna świadomie tego nie powtarza |
| Każda obietnica ze stron produktów ma pokrycie w kodzie | ✅ **czysto** — tabela wyżej, 6 obietnic zweryfikowanych w kodzie |
| Komendy z instrukcji WPISANE w terminal | ✅ **czysto** — trzy stałe wpisane i odczytane |
| Deklaracja prywatności rozdziela tryb regułowy i AI — i jest prawdziwa | 🐞 **ZNALEZISKO A6-1** (ChatBot); Segregator ✅ czysto |
| Wersja w nagłówku = w stałej = w paczce = w CHANGELOG | ⏭️ **poza moim zleceniem** — pilnuje `narzedzia-qa/paczka-zgodnosc.sh` |

**Trzy cele domknięte moją robotą, dwa świadomie oddane właścicielom.** Żaden nie został pusty.

---

## CZEGO NIE ZDĄŻYŁEM / NIE SPRAWDZIŁEM

1. **Pola formularzy i kolumny tabel** — inwentarz miał je obejmować; zrobiłem podstrony,
   akcje, filtry, cron, opcje, REST i stałe, ale **nie przeszedłem pole po polu** przez
   formularze panelu ani kolumna po kolumnie przez 13 tabel. Tam może siedzieć funkcja
   bez opisu, której nie widzę z poziomu akcji.
2. **Strona produktu w sklepie jako RENDER** — czytałem `boty/TRESC-STRON-PRODUKTOW.md`
   (źródło copy). Nie sprawdziłem, czy to, co sklep **naprawdę wyświetla** kupującemu
   (`wtyczki/sklep-agentow/includes/front-produkt.php` + treść w bazie), zgadza się z tym
   plikiem. Rozjazd źródło↔render byłby dokładnie moją klasą.
3. **Segregator: `readme.txt` i sekcja prywatności** — sprawdziłem `INSTRUKCJA.md` §9 (pełna).
   Nie porównałem jej zdanie po zdaniu z `readme.txt` Segregatora, tak jak zrobiłem dla ChatBota.
4. **`03-zalozenia.md`, `04-goldeny-botow.md`, `05-reguly-branzy.md`, `07-mapa-dokumentacji.md`,
   `MATERIAL-AUDYTU-*`** — przeczytałem zamówienie, fakty, mapę i `PONAD-SPECYFIKACJE`;
   pozostałych plików materiału **nie otworzyłem** (budżet). Dziewięć „goldenów" mogło dać
   dodatkowe kryteria.

---

## ⛔ CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Moje pytanie brzmiało „co produkt robi, o czym dokumentacja milczy". Kategorie, na które
trafiłem, a które się w nim nie mieszczą:

1. **Dokumentacja, która milczy o tym, czego produkt NIE robi.** Mój kierunek łapie funkcje
   bez opisu. Nie łapie **granic bez opisu** — a `PONAD-SPECYFIKACJE` wymienia je jako istotne:
   brak OAuth2 ⇒ Microsoft 365 i Outlook.com nieobsługiwane, na skrzynce klasyfikują reguły
   a nie AI, bez czytania załączników. Kupujący z Outlookiem dowie się o tym **po** zakupie,
   jeśli te zdania nie stoją na stronie produktu. Nie sprawdziłem, czy stoją.
2. **Dokumentacja jako produkt, który sam się psuje przy scaleniu.** Cztery PR-y są w locie
   i wszystkie ruszają dokumenty. Nikt nie sprawdza, czy po scaleniu czterech naraz sekcje
   nie zdublują się ani nie zaprzeczą sobie nawzajem. To nie jest wada żadnego z nich osobno
   — dlatego nie ma właściciela.
3. **Kto jeszcze czyta te dokumenty.** Sprawdzałem instrukcję, readme, dokumentację techniczną
   i copy strony. Nie sprawdziłem **komunikatów w samym panelu** — a to je właściciel czyta
   najczęściej i to one, a nie plik `.md`, są dla niego dokumentacją.
4. **Materiał audytowy nie ma właściciela ani bramki.** Znalezisko A6-2 istnieje, bo sweep
   dokumentacji objął pliki produktu i pominął `audyt/boty/material/`. Żadna bramka nie pilnuje,
   czy materiał audytowy nadąża za kodem — a to on ustawia model produktu w głowie każdego
   następnego audytora. **To jest luka procesowa, nie produktowa, i nie miała gdzie trafić
   na tej mapie.**
5. **Zgodność dokumentacji z kodem w czasie.** Sprawdziłem stan na jednym commicie. Nie ma
   niczego, co pilnuje, żeby nowa funkcja nie weszła bez wpisu w instrukcji — import CSV wszedł
   właśnie tak i został złapany dopiero przez audyt, dwie rundy później.

Miałem czym uzupełnić tę sekcję i uzupełniłem: pytanie „co robi, o czym milczy" jest ostre
w jedną stronę i ślepe na granice, na komunikaty w panelu i na dokumentację procesu.
