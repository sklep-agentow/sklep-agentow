# RAPORT — SOCZEWKA Ł (ŁAMACZ ZAŁOŻEŃ) · ChatBot LIVE

- `commit_head` = **3fc884d** (`3fc884d9d7c3d9b47b8229b4c3be5ddcd1eea5e4`), gałąź audytu `audyt/lamacz-chatbot` z `origin/main`.
- Kod wtyczki w kontenerze = kod z repozytorium co do bajtu:
  `md5 includes/front.php = 6655327aaff65f773c20e2def9164f3c` po obu stronach.
- Stos: WordPress **7.0.4**, PHP **8.4.21**, `strona_wp_wordpress`, `free -m` przed startem: 1560 MB dostępne
  (⛔ przeglądarki NIE uruchamiałem — patrz „czego nie zdążyłem").
- Wersja wtyczki zmierzona: `CHATL_WERSJA = 1.5.8`.

## Poprawka do materiału środowiskowego (`02-fakty-srodowiska.md`)
Materiał każe kierować żądania na tunel `https://linda-refrigerator-potato-objective.trycloudflare.com`,
bo „testy po localhost nie są miarodajne". **Tunel dziś nie żyje**, a localhost odpowiada:

```
$ curl -sk -o /dev/null -w 'tunel: %{http_code}\n' --max-time 10 https://linda-refrigerator-potato-objective.trycloudflare.com/
tunel: 000
$ curl -s -o /dev/null -w 'localhost: %{http_code}\n' http://localhost:8890/
localhost: 200
$ curl -s -w '\nkod=%{http_code}\n' -X POST http://localhost:8890/wp-admin/admin-ajax.php -d 'action=chatl_sesja'
{"success":true,"data":{"sesja":"h5CiHmxps31DZZItrZSO4KEd73xWDmpY"}}
kod=200
```

`admin-ajax.php` nie podlega przekierowaniu kanonicznemu, więc pomiary niżej (wszystkie idą przez
`admin-ajax.php`) są miarodajne na localhost. **Punkt „adres witryny" w materiale jest nieaktualny.**

---

## Co ten produkt ma robić — jednym akapitem, moimi słowami

ChatBot LIVE to wtyczka WordPressa za 149 zł, którą właściciel małej firmy wgrywa na swoją stronę,
żeby bąbelek czatu w rogu odpowiadał gościom na powtarzalne pytania przez całą dobę. Bot ma dwa
silniki: regułowy (baza pytań i odpowiedzi z ~10 parami demo wgranymi przy aktywacji, dopasowanie
po podłańcuchach słów kluczowych — działa bez żadnego klucza) oraz AI (właściciel wkleja własny
klucz, model odpowiada tylko na podstawie informacji o firmie, a niepewny ma ZAKAZ zgadywania
i zamiast konfabulacji prosi o kontakt). Gdy bot nie zna odpowiedzi, zbiera maila i pytanie —
i to jest człon „LIVE": nie żywa rozmowa, tylko most do człowieka, który odpisze. Ponad literę
zamówienia doszło przejęcie rozmowy przez operatora w panelu i pamięć bieżącej rozmowy.
Właściciel widzi w wp-admin bazę Q&A, historię rozmów (retencja 90 dni), zgłoszenia, statystyki
i luki, a koszt trybu AI trzymają dwa stop-lossy (dobowy limit wywołań i tokenów).
Obietnica ze strony produktu, która spina to wszystko: **„Start od pierwszej minuty — 10 gotowych
par pytań po polsku w zestawie, działa zaraz po instalacji"** i **„ODPOWIADA 24/7"**.

---

## 🐞 ZNALEZISKO Ł-1 (najcięższe) — PIERWSZA wiadomość każdego gościa jest po cichu wyrzucana

**Założenie autora:** „prawdziwy gość nie zdąży zadać pytania w mniej niż 2 sekundy od wydania
mu sesji — kto zdąży, jest skryptem" (`CHATL_PULAPKA_SEKUND = 2`, `front.php:47`).

**Kiedy u klienta jest fałszywe:** ZAWSZE, przy pierwszej wiadomości. Widget nie prosi o sesję
przy otwarciu okna — prosi o nią **leniwie, w tym samym łańcuchu co wysyłane pytanie**:
`zadanie()` woła `dajSesje()` i dopiero w `.then()` wysyła `chatl_pytanie`
(`assets/widget.js:53-59`, `:84-95`). Między wydaniem sesji a pytaniem jest **jeden obieg HTTP**,
nie „czas namysłu gościa". Ile gość pisał — nie ma znaczenia, bo sesja powstaje w chwili wysłania.

**Wywołanie stanu — cztery przebiegi, zmienna jest JEDNA (odstęp sesja → pytanie):**

Stanowisko: `admin-ajax.php`, kolejność żądań 1:1 z widgetem (`chatl_sesja` → `chatl_pytanie`),
puste pole pułapki `chatl_pole_dodatkowe`, ciastko sekretu sesji trzymane w tym samym słoiku.

```
### C: TRYB REGULOWY (bez klucza), pytanie MA dopasowanie w bazie Q&A
-- 1. pytanie:
{"success":true,"data":{"odpowiedz":"Nie znam odpowiedzi na to pytanie. Zostaw proszę maila
 i pytanie — odezwiemy się najszybciej, jak się da.","silnik":"reguly","trafiono":false}}
odstep sesja->pytanie: .087989758 s
-- 2. pytanie (ta sama sesja, chwile pozniej):
{"success":true,"data":{"odpowiedz":"Nie znam odpowiedzi na to pytanie. ...","silnik":"reguly","trafiono":false}}
SESJA=SjC5efFor1CrnOzS3zKyvvBOS3cDhKdW
--- wiersze rozmowy dla sesji SjC5efFor1CrnOzS3zKyvvBOS3cDhKdW ---
ile_wierszy=0
```

```
### D (PRÓBA KONTROLNA — przypadek BEZ wady): to samo pytanie 3 s po wydaniu sesji
-- 1. pytanie:
{"success":true,"data":{"trafiono":true,"odpowiedz":"Pracujemy od poniedziałku do piątku
 w godzinach 8:00–16:00. W weekendy odpisujemy na wiadomości w miarę możliwości."}}
odstep sesja->pytanie: 3.188931401 s
--- wiersze rozmowy dla sesji gfa3NeAucYsJNDNrkCrSaPJsbJj2IVxq ---
ile_wierszy=4
  6022 gosc silnik=reguly trafiono='1' Jakie sa godziny otwarcia?
  6023 bot  silnik=reguly trafiono='1' Pracujemy od poniedziałku do piątku w go
  6024 gosc silnik=reguly trafiono='1' Jakie sa godziny otwarcia?
  6025 bot  silnik=reguly trafiono='1' Pracujemy od poniedziałku do piątku w go
```

To samo w trybie AI (klucz ustawiony, dostawca podstawiony atrapą `pre_http_request`, patrz
„stanowisko" niżej) — przebiegi A i B:

```
### A: pytanie NATYCHMIAST po wydaniu sesji
{"success":true,"data":{"odpowiedz":"Nie znam odpowiedzi na to pytanie. ...","silnik":"reguly","trafiono":false}}
### B: pytanie 3 s po wydaniu sesji
{"success":true,"data":{"trafiono":true,"odpowiedz":"ODPOWIEDZ-Z-ATRAPY-AI"}}
=== log atrapy AI === (jedno wywolanie, z przebiegu B)
16:54:22 WYWOLANIE tryb=ok url=https://api.anthropic.com/v1/messages
```

**Kalibracja stanowiska** (⚠️ materiał ostrzegał, że poprzednia próba dała fałszywy wynik):
licznik pułapki w bazie potwierdza, KTÓRY strażnik zadziałał — nie zgaduję z kodu:

```
$ wp option get chatl_pulapka --format=json
{"ile":1,"ostatnia":"2026-08-21 18:54:19","powod":"zbyt szybkie żądanie"}
```

Oraz dowód, że silnik regułowy zna odpowiedź (czyli C nie padło z braku dopasowania):

```
$ wp eval 'var_export( chatl_dopasuj("Jakie sa godziny otwarcia?") );'
array ( 'id' => 2, 'odpowiedz' => 'Pracujemy od poniedziałku do piątku w godzinach 8:00–16:00. ...
$ wp eval '... SELECT COUNT(*) FROM wp_chatl_qa ...'   → par_qa=14
```

**Ścieżka w kodzie:** `chatl_ajax_pytanie()` (`includes/front.php:359`) sprawdza pułapkę jako
PIERWSZĄ rzecz po nonce; `chatl_pulapka_zlapala()` (`:665`) liczy wiek wiersza sesji i przy
`wiek < 2` zwraca `'czas'`; handler odsyła `tekst_awaryjny` z `silnik: 'reguly'` **i kończy
żądanie przed jakimkolwiek zapisem** — dlatego w `chatl_rozmowy` nie powstaje ani jeden wiersz.

**Szkoda dla kupującego — nie „brzydki komunikat", tylko utrata pieniędzy i danych:**
1. Gość zadaje pytanie, na które bot ZNA odpowiedź, i słyszy „nie znam odpowiedzi". Pierwsza
   wiadomość to ta, która decyduje, czy gość zostanie — bot przegrywa ją zawsze.
2. Tryb AI, za który kupujący płaci osobno u dostawcy, przy pierwszym pytaniu **nie jest nawet
   wywoływany** (log atrapy: zero wywołań w przebiegu A).
3. Rozmowa **nie zostawia śladu w bazie** — właściciel nie zobaczy jej w historii rozmów, nie
   trafi do „luk", nie policzy jej statystyka. Nie ma jak zauważyć, że coś traci.
4. Licznik pułapki rośnie, więc panel mówi właścicielowi „mechanizm działa, łapię boty" —
   sygnał jest odwrotny do prawdy.
5. Wprost łamie obietnicę ze strony produktu: **„Start od pierwszej minuty — 10 gotowych par
   pytań po polsku w zestawie, działa zaraz po instalacji"** (`wtyczki/sklep-agentow/includes/front-produkt.php:113`)
   oraz literę zamówienia „bot DZIAŁA od pierwszej minuty po aktywacji" (`material/01-zamowienie.md`).
   Ten sam plik reklamuje zrzut z podpisem „gość pyta o godziny otwarcia, bot odpowiada wpisem
   z bazy" (`:193`) — czyli dokładnie scenariusz, który w przebiegu C padł.

**To jest klasa, nie egzemplarz — i nie jest to znana wada.** Materiał `MATERIAL-AUDYTU-CHATBOT.md`
§3 D3 nazywa to jako *dziurę w mapie polowania* („sprawdź to jako fałszywy alarm"), a pamięć
zespołu ma próg pułapki zapisany jako **wadę narzędzia QA**. Tu jest udowodnione jako **wada
produktu na ścieżce prawdziwego widgetu**: przyczyną nie jest szybki skrypt testowy, tylko
leniwe wydawanie sesji w `widget.js`. Ta sama pułapka stoi też w `chatl_ajax_zgloszenie()`
(`front.php:516`), ale tam próg czasu jest **świadomie wyłączony** (`$sprawdzaj_czas = false`) —
autor rozpoznał ten fałszywy alarm przy formularzu i naprawił go w JEDNYM z dwóch miejsc.

**Najtańsza znana mi naprawa (audyt nie naprawia — to tylko wskazówka):** albo poprosić o sesję
przy otwarciu okna czatu zamiast przy wysyłce, albo — jak przy zgłoszeniu — wyłączyć próg czasu.

---

## 🐞 ZNALEZISKO Ł-2 — ścieżka pułapki jest CAŁKOWICIE nielimitowana i każde żądanie pisze do `wp_options`

**Założenie autora:** „złapane żądanie nie może zużywać kubła, który blokuje PRAWDZIWYCH gości"
(komentarz w `chatl_ajax_pytanie()`, `front.php:362-367`) — pułapka jest sprawdzana **przed**
limitem. Konsekwencji nikt nie policzył: żądanie złapane nie zużywa kubła, więc **nie ma dla
niego ŻADNEGO sufitu**, a każde z nich woła `chatl_odnotuj_pulapke()` → `get_option()` +
`update_option()` na `wp_options`.

**Wywołanie stanu:** 200 żądań z jednego adresu, ważny nonce, wypełnione pole pułapki:

```
licznik pulapki PRZED: {"ile":3,"ostatnia":"2026-08-21 18:58:04","powod":"zbyt szybkie żądanie"}
--- salwa 200 zadan z WYPELNIONA pulapka (nonce wazny) ---
$ tr ' ' '\n' < wynik | grep -E '^(200|429|403|400)$' | sort | uniq -c
    201 200
czas: 7.064565758 s
licznik pulapki PO: {"ile":153,"ostatnia":"2026-08-21 18:59:04","powod":"ukryte pole"}
```

**Dwa fakty z jednego pomiaru:**
1. **0 z 200 żądań dostało 429.** Limit `CHATL_LIMIT_NA_MIN = 10` nie widzi tej ścieżki w ogóle.
   200 żądań w 7 sekund z jednego adresu = ~1700/min, a mapa pytała o 500/min. Nic nie pada,
   nic nie hamuje — pada za to na współdzielonym hostingu tabela `wp_options`, bo mechanizm,
   który ma CHRONIĆ przed botami, na każdego bota odpowiada zapisem do bazy.
2. **Licznik gubi zdarzenia: 200 żądań → 150 zliczonych** (3 → 153, 25% w plecy). `chatl_odnotuj_pulapke()`
   robi odczyt-modyfikacja-zapis bez atomowości, więc równoległe żądania nadpisują się nawzajem.
   Liczba, którą właściciel czyta w panelu jako „tyle botów złapałem", jest zaniżona — a to ta
   sama liczba, po której (wg komentarza w kodzie, `front.php:686-690`) ma poznać fałszywy alarm.

**Szkoda:** obciążenie bazy kupującego bez sufitu + liczba w panelu, która nie jest prawdziwa.
Klasa pokrewna do znanej G7 („licznik mierzy zły wymiar"), ale to INNY kubeł niż wspólny kubeł
pytań i zgłoszeń — tam limit istnieje i za mało rozróżnia, tu **limitu nie ma wcale**.

---

## Cele mapy polowania — soczewka Ł (5 celów, zero pustych)

| cel | wynik | czym rozstrzygnięty |
|---|---|---|
| Ł1 „baza nie odpowie w połowie przebiegu" | ⚠️ **anomalia / cel niedokończony** | Podstawiłem filtr `query` psujący `INSERT INTO wp_chatl_rozmowy`. **Padło moje stanowisko, nie produkt**: `Fatal error: Allowed memory size exhausted` w `class-wpdb.php` przy każdym żądaniu i przy wp-cli. Atrapę usunąłem, stos wrócił do zdrowia (`strona: 200`, `par_qa=14`, kontrolne pytanie odpowiada poprawnie). Wyniku o produkcie **nie mam** i nie zgłaszam go jako znaleziska (G6). |
| Ł2 „klucz API zły / wygasł / zerowy limit" | ✅ **czysto** | Dostawca odrzuca każde wywołanie (HTTP 429). Gość dostaje poprawną odpowiedź z reguł; właściciel dostaje znacznik `chatl_blad_ai = {"kiedy":"2026-08-21 19:01:07","kod":429,"tresc":"{\"type\":\"error\"}"}`; do dostawcy poszły 4 wywołania na 2 pytania = dokładnie jedno ponowienie, zgodnie z opisem. Licznik stop-loss **nie liczy** nieudanych wywołań, a liczy udane: `PRZED: 4/440` → po 4 wywołaniach 429 `4/440` → po jednym udanym pytaniu `6/660`. |
| Ł3 „500 żądań w minutę" | 🐞 **znalezisko Ł-2** | 200 żądań / 7 s z jednego adresu, wszystkie HTTP 200, zero 429 (wyżej). |
| Ł4 „WordPress w wersji minimalnej z nagłówka" | ✅ **czysto** | Nagłówek deklaruje `Requires at least: 6.0`; jedyne użyte API nowsze niż 6.0 to `wp_trigger_error()` (rdzeń w kontenerze: `@since 6.4.0`, `wp-includes/functions.php:6093`). **Oba** wywołania są osłonięte `function_exists( 'wp_trigger_error' )` (`chatbot-live.php:40`, `includes/ai.php:548`), więc na WP 6.0–6.3 nie ma błędu krytycznego — ginie tylko wpis do logu, a znacznik awarii dla właściciela (`chatl_blad_ai`) działa niezależnie. Próba kontrolna grepu (G5): ten sam grep po `wp_send_json_success` zwraca 8 trafień w `front.php`, więc wzorzec szukania działa. |
| Ł5 „właściciel wyłączy `DISABLE_WP_CRON`" | ⚠️ **cel niewykonany — zabrakło budżetu** | Nie wykonałem. Jedyny fakt statyczny, który zdążyłem zdjąć: harmonogram jest zakładany raz, przy aktywacji (`includes/instalator.php:136`, `wp_next_scheduled`), a w panelu **nie ma ani jednego miejsca**, które sprawdza, czy cron w ogóle chodzi (grep po `DISABLE_WP_CRON` w `includes/` = zero trafień). Sam brak retencji przy wyłączonym cronie jest już zgłoszony wcześniej — nie powtarzam go. |

---

## CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Kategorie, na które trafiłem, a które nie mieszczą się w pytaniu soczewki Ł:

1. **„Sesja wydawana leniwie" to nie tylko pułapka — to też koszt pierwszego pytania.**
   Pierwsza wiadomość gościa to trzy żądania HTTP zamiast jednego (`chatl_nonce` przy starcie,
   `chatl_sesja`, `chatl_pytanie`). Nikt nie mierzył, ile żądań generuje jedna otwarta karta —
   D2 z materiału ChatBota dalej stoi otwarte.
2. **Zaniżający się licznik w `wp_options` to prawdopodobnie klasa, nie egzemplarz.**
   `chatl_odnotuj_pulapke()` i `chatl_odnotuj_odrzucony_nonce()` mają identyczny wzorzec
   odczyt-modyfikacja-zapis bez atomowości; drugiego nie zmierzyłem. Warto sprawdzić wszystkie
   liczniki trzymane w opcjach — i przy okazji, czy nie jest to klasa #7 ściągi („licznik
   w `wp_options` zamiast własnej tabeli"), skoro limity mają już własną tabelę `chatl_rate_limit`.
3. **`chatl_odnotuj_odrzucony_nonce()` jest wołane PRZED limitem żądań** (`chatl_wymagaj_nonce()`
   jest pierwszą linią handlera). Nie zmierzyłem tego, ale kształt jest ten sam co w Ł-2:
   nielimitowany zapis do `wp_options` wywoływany żądaniem bez żadnego uwierzytelnienia.
4. **Strona z cache — sprawdzone tylko od strony sesji, nie od strony nonce'a.** Sesja NIE jest
   zapiekana w HTML (idzie osobnym AJAX-em `chatl_sesja`), więc cache jej nie psuje i to jest
   dobra decyzja. Ale nonce ląduje w `wp_localize_script`, czyli w cache'owanym HTML — ścieżki
   odnowienia nonce'a przy wygasłym tokenie nie wykonałem.
5. **Dwie karty naraz / wyciek historii między sesjami** (D5 z materiału) — nietknięte.
   Ciastko sekretu jest per sesja (`chatl_k_<sesja>`), co wygląda na dobrą decyzję, ale nie
   sprawdziłem, czy `chatl_ai_historia()` nie wciągnie cudzych wierszy.
6. **Przejęcie rozmowy przez operatora** — cała funkcja (jedyna trasa REST) nietknięta przeze mnie.
7. **Emoji / HTML / cudzysłowy / 1000 znaków w pytaniu** — kierunek z mojego promptu, którego
   nie zdążyłem wykonać; `CHATL_MAX_PYTANIE = 500` obcina po stronie serwera, ale nie zmierzyłem.
8. **Materiał audytowy sam ma nieaktualny fakt** (adres witryny = martwy tunel, sekcja na górze
   tego raportu). To nie wada produktu, ale wada materiału, która wysyła audytora w ślepy zaułek —
   i dokładnie ta klasa, przed którą ostrzega `02-fakty-srodowiska.md`.

---

## Stanowisko testowe — co podstawiłem i czy po mnie posprzątane

- `mu-plugins/atrapa-ai.php` — filtr `pre_http_request` podstawiający odpowiedź dostawcy AI
  (tryby: `ok`, `blad429`, `smiec`, `pustka`) i logujący każde przechwycone wywołanie.
  Powód: żeby dowód nie zależał od prawdziwego klucza ani od sieci. **Usunięty.**
- `mu-plugins/atrapa-bazy.php` — filtr `query` psujący INSERT (cel Ł1). **Usunięty**, bo położył stos.
- Opcje `chatl_klucz_api` i `atrapa_tryb` — **skasowane**; sprawdzone: `klucz=skasowany ai_dostepny=false`.
- Po sprzątaniu w `wp-content/mu-plugins/` został wyłącznie `mailpit-smtp.php` (stan zastany).
- **Zostawione w bazie poligonu** (świadomie, bo to dane, nie kod): wiersze rozmów z przebiegów
  A–D, podbity licznik `chatl_pulapka` i wpisy w `chatl_rate_limit`. Kodu produktu **nie zmieniałem**
  (`git status --porcelain` czysty poza tym raportem).

## Czego nie zdążyłem
Cel Ł1 (awaria bazy w połowie przebiegu) — instrument padł, wynik nieznany. Cel Ł5
(`DISABLE_WP_CRON`) — niewykonany. Nie uruchamiałem **przeglądarki** (warunek W1 materiału),
więc znalezisko Ł-1 jest dowiedzione na poziomie żądań HTTP odtwarzających kolejność widgetu,
nie klikaniem — kolejność wzięta z `assets/widget.js:53-95`, nie zgadnięta. Nie dotknąłem
przejęcia rozmowy, RODO, stronicowania, importu CSV ani znaków specjalnych w pytaniu.
