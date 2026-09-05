# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)


## ⭐⛔ CO Z TEGO DŁUGU JEDZIE DO KLIENTA — lista do przejrzenia PRZED KAŻDYM WYDANIEM (3.09)

**Po co ta sekcja:** ten plik ma kilkadziesiąt pozycji i **nic nie pytało, które z nich wchodzą do paczki**.
To klasa `I-16` w najgorszym wydaniu — raz już tak wyszedł do klienta brak eksportera i erasera RODO.
⛔ To **nie jest** rejestr znalezisk: `audyt/REJESTR-ZNALEZISK.md` wymaga strażnika przy każdym wpisie
(`bramka-znaleziska.sh`: „znalezisko bez testu zatrzymuje bramkę"), a te pozycje są w większości świadomym
długiem, nie robotą do zrobienia w tym wydaniu. Tu stoją po to, żeby **nikt ich nie wydał, nie wiedząc**.

**Sprawdzone 3.09 przez `narzedzia-qa/pakuj-dla-klienta.sh` i allowlistę `boty/buduj-paczki.sh`** — przez listę
plików paczki, nie przez nazwę katalogu.

| co | gdzie w kodzie | dlaczego jedzie | status |
|---|---|---|---|
| pola `required` bez polskiego komunikatu | `admin-produkty.php:319,322` · `admin-ustawienia.php` | właściciel zobaczy przy pierwszym pustym zapisie produktu | otwarte, tanie |
| asymetria list znaków w eksporcie CSV | `admin-dashboard.php:269` · `segregator/csv.php:310` · `chatbot/csv.php:29` | sklep chroni 7 znaków, segregator 6 (bez apostrofu), chatbot 4 — ciche uszkodzenie danych, nie awaria | otwarte |
| status „zamknięty" w trzech składniach | `sklep-agentow.php:44` · `admin-zamowienie.php` (5 miejsc) | dziś śpi; obudzi się przy dodaniu nowego statusu — np. „Wyślij ponownie" po obciążeniu zwrotnym | otwarte |
| pięć zapisów w ponownej wysyłce bez sprawdzonego wyniku | `admin-zamowienie.php:104,130,179,194,196` | ta sama klasa, którą naprawialiśmy w anonimizacji | ⭐ **dług NAZWANY** — `admin-zamowienie.php:28` niesie nasze własne przyznanie się |
| luki w instrukcji instalacji | `dokumentacja/INSTRUKCJA-INSTALACJI.md` | **zero obrazów** (kontrola wzorca: instrukcje wtyczek mają 6/5/6), myląca numeracja menu, ewidencja kasy fiskalnej, retencja faktur, zachowanie przy złym NIP-ie | otwarte, tekst |
| pięć obrazów w paczce sklepu | `wtyczki/sklep-agentow/zrzuty/*.png` | pokazują menu z ChatBot LIVE i Segregatorem; klient bez tych wtyczek zobaczy ekran, którego u siebie nie ma | otwarte, drobne |

**Dopisane 3.09 po przelocie egzaminatora panelu (świeże oczy, nie moja rama):**

| co | gdzie w kodzie | dlaczego jedzie | status |
|---|---|---|---|
| **surowe kody zdarzeń w dzienniku, w tym jedyne angielskie słowo w polskim panelu** | `class-sklepag-lista-zdarzen.php`, `column_default()` — gałąź `case 'typ'` zwraca `esc_html( $item->$column_name )`, zero mapowania na etykietę | właściciel widzi `reconciliation_blad`, `reconciliation_wynik`, `webhook_unpaid`, `checkout_blad` — 28 kodów, bez legendy. Ten sam mechanizm ma na pulpicie ludzką etykietę (przycisk „Dociągnij zaległe teraz” w `admin-dashboard.php`), więc rozjazd jest w jednym ekranie | **świadomie ODŁOŻONE 3.09** |

⛔ **Dlaczego odłożone, a nie naprawione od ręki:** jeden z typów jest **sklejany dynamicznie**
(`opinie.php:205`: `'opinia_' . $nowy`), więc uczciwa mapa wymaga najpierw rozstrzygnięcia zbioru
wartości, a mapa niepełna bez pilnowanego fallbacku jest gorsza niż surowy kod. Naprawa warta
zrobienia **razem z testem, który pilnuje, że każdy typ użyty w kodzie ma etykietę** — inaczej
kompletność jest obietnicą, nie bramką. Widoczne dopiero po wejściu w podstronę Dziennik, więc
nie blokuje wydania.

⛔ **Czego ta lista NIE obejmuje** (uczciwie, żeby nie udawała kompletnej): pozycje czysto narzędziowe
zakwalifikowano po katalogu (`testy/`, `narzedzia-qa/`, `audyt/` są poza allowlistą), **bez osobnego uruchomienia**;
zawartość pięciu obrazów sprawdzono co do liczby plików, nie co do treści; numerów linii w wierszu „asymetria CSV"
nie odświeżano wobec dzisiejszego `HEAD`.

## 🟡 UWAGI WAŻNE Z RECENZJI `#288` r3 I `#289` — co poprawione PR-em porządkowym, co zostaje długiem (5.09)

Zasada z 5.09: WAŻNE nie idą rundami w PR-ze obszaru, tylko do JEDNEGO PR-a porządkowego po scaleniu.
Scalone z zerem KRYTYCZNYCH: `#288` (obszar B, r3: 1 × WAŻNA, 1 × DROBNA), `#289` (obszar D: 7 × WAŻNA, 3 × DROBNA).

**Poprawione w PR porządkowym `porzadek/po-288-289` (dowody w opisie PR):**
- `#288` W — niezaescapowane `|` w komórkach trzech wierszy (`DECYZJE.md` D29/D30, `E5-F2` w rejestrze INFRA).
- `#289` W1 — rejestr INFRA: `I-25`, `I-27` ZAMKNIETE, `I-26` W NAPRAWIE; obalona przyczyna `I-25` przepisana; liczniki nagłówka przeliczone komendą pliku (32 · 53 · 4 = 89).
- `#289` W4 — `LINIA-BAZOWA-TESTOW.md` mówi wprost, że `dowod-z-paczki.sh` NIE mierzy Kroku 1 (omija formularz „Wyślij na serwer”).
- `#289` W5 — sprawdzenie crona nazwane uczciwie: liczy zaległe TERAZ, nie „starsze niż 2 min” (README + komentarz skryptu).
- `#289` W6 — rozmiar paczki 0.9.40: **4 859 567 B** (`ls -l`), nie 9 098 363 B (README, linia bazowa, rejestr).
- `#289` D1/D2 — `wp-cron-stanowisko.sh`: padnięty `docker ps` = kod 3 z komunikatem, nie „kontener zgaszony”; kody wyjścia opisane zgodnie z `docker exec`.

**ZOSTAJE DŁUGIEM (każdy = jeden brief buildera, jeden PR):**
- `#289` W2 — próba kontrolna spustu crona istnieje tylko PROZĄ (`stanowisko/README.md`); potrzebny `narzedzia-qa/stanowisko-cron-proba-kontrolna.sh` w formie repo (dwukierunkowo: wada = wyjęty wpis crona → FAIL; bez wady → PASS). Do tego czasu `I-25` liczy się jako „bez strażnika”.
- `#289` W3 — BLIŹNIAK (klasa 8): `narzedzia-qa/lib-stos-probny.sh` stawia stos z `-p 8891:80` i `--url=http://localhost:8891` → ta sama martwa pętla zwrotna `wp-cron.php`; `grep -c DISABLE_WP_CRON lib-stos-probny.sh` → 0. Każdy test reconciliation na `probny_wp` mierzy stos bez działającego crona. Naprawa: `DISABLE_WP_CRON` + jawne `wp cron event run --due-now` w krokach testów albo spust w pętli stosu próbnego.
- `#289` W7 — `instaluj-spust-crona.sh` kopiuje do `/usr/local/sbin/` i nic nie porównuje kopii ze źródłem; kolejny commit do skryptu nie dotrze na hosta po cichu. Kandydat: sprawdzenie `cmp` w `stos.sh stan` (warunek wstępny stanowiska) — do czasu naprawy po KAŻDEJ zmianie skryptu ręcznie `bash narzedzia-qa/stanowisko/instaluj-spust-crona.sh`.
- `#289` D3 — „98 wiadomości przepadło” w README nie ma pomiaru utraty; zdanie do usunięcia albo pomiar.
- `#289` W1 (reszta) — `I-26` domyka dopiero przejście 7 kroków instrukcji PRZEZ PANEL na `probny_wp` (pozycja 4 listy startowej w mózgu).
- `#288` DROBNA — opis PR krótszy o jeden plik (`testy/nota-zapisu-test.js`, komentarz) — PR scalony, bez skutku; klasa „opis PR odświeżany PRZED pushem” zostaje w pamięci.

## 🟡 SPRZĄTANIE STRAŻNIKA LIMITU UPLOADU KASUJE WSZYSTKO, CO POWSTAŁO W TRAKCIE PRZEBIEGU (2.09, recenzja `#263` r3, DROBNA 4) — dług

`testy/limit-uploadu-mowi-prawde.sh`: `DELETE … WHERE typ = 'upload_produktu_blad' AND id > ID_PRZED` kasuje po sobie
wpisy dziennika młodsze niż stan sprzed przebiegu — czyli także CUDZY wpis tego typu założony na wspólnym stosie
w oknie przebiegu (kilkadziesiąt sekund). Komentarz mówi „WYŁĄCZNIE po sobie", warunek realizuje „wszystko z okna".
Wpisy zakłada produkt, nie strażnik, więc nie ma jak ich oznaczyć znacznikiem. **Kształt roboty:** zawęzić po treści
wpisu (nazwa produktu strażnika `STRAZNIK LIMITU …` jest w komunikacie? sprawdzić kolumny `sklepag_zdarzenia`) albo
uruchamiać strażnika WYŁĄCZNIE na stosie próbnym (`probny_wp`), gdzie cudzych wpisów nie ma — wtedy komentarz zmienić
na „stos próbny albo pełna świadomość okna". **Jedzie do klienta:** NIE — narzędzie testowe, poza paczką.


## ✅ ROZLICZONE 5.09 — ASYMETRIA NEUTRALIZACJI CSV: ChatBot ma JEDNĄ stałą `CHATL_CSV_ZNAKI_FORMULY` (7 znaków, jak sklep; import czyta tę samą), strażnik `testy/csv-neutralizacja-blizniaki-test.sh` mierzy 3 kopie z podłogą `MIN_KOPII=3` i ma spust: `narzedzia-qa/csv-neutralizacja-blizniaki-proba-kontrolna.sh` w przelocie CI (3 mutanty padają, czysta kopia przechodzi) — PR #298 (ChatBot 1.5.17). Segregator zostaje przy 6 znakach (bez apostrofu — nie ma importu, odwracalność niepotrzebna; zapisane w jego DOKUMENTACJA-TECHNICZNA.md).

## 🟡 (archiwum) ASYMETRIA NEUTRALIZACJI CSV MIĘDZY TRZEMA WTYCZKAMI (2.09, recenzja `#264` r1 i r3) — dług nazwany, nie naprawiony

Trzy wtyczki mają po kopii tej samej obrony (`*_csv_komorka()`, trzy kopie są uzasadnione — instalują
się osobno), ale **listy znaków różnią się** i żaden grep po nazwie funkcji tego nie pokaże:

| wtyczka | funkcja | lista znaków poprzedzanych apostrofem |
|---|---|---|
| sklep | `wtyczki/sklep-agentow/includes/admin-dashboard.php:269` `SKLEPAG_CSV_ZNAKI_FORMULY` | `= + - @ \t \r '` |
| Segregator | `wtyczki/segregator-maila/includes/csv.php:310` `segm_csv_komorka()` | `= + - @ \t \r` |
| ChatBot | `wtyczki/chatbot-live/includes/csv.php:29` `chatl_csv_komorka()` | `= + - @` |

Dwie różnice, obie zapisane, żadna nie jest naprawiana w `#264` (teren builderów 1 i 2, kod wtyczek
botów tam nietknięty):
1. **`\t` i `\r` brakują w ChatBocie.** Opisane w `audyt/boty/RAPORT-A1-BEZPIECZENSTWO.md:188` jako asymetria
   bez drogi dojścia (sanityzacja wejścia gościa zdejmuje wiodący tabulator — pomiar tamże). Zostaje długiem,
   nie znaleziskiem.
2. **Apostrof jest TYLKO w sklepie** (wniesiony rundą 2 `#264` dla pełnej odwracalności: wartość zaczynająca się
   od apostrofu dostaje drugi, więc zdjęcie jednego zawsze oddaje oryginał). W ChatBocie i Segregatorze wartość
   od apostrofu wychodzi nietknięta i jest nieodróżnialna od zneutralizowanej. Bezpieczeństwa nie dotyka,
   dotyka obietnicy odczytu maszynowego — a tej obietnicy boty w dokumentacji **nie składają** (sprawdzić przed
   wyrównaniem: jeśli składają, to znalezisko klasy 3, nie dług).

**Kształt roboty (osobny PR na terenie botów, jedna runda):** wyrównać obie listy do siedmiu znaków sklepu
i dopisać kierunek do strażników botów; dowód = `grep -rn "csv_komorka" --include=*.php wtyczki/` z trzema
identycznymi listami. **Jedzie do klienta z długiem:** TAK, świadomie — odwracalność eksportu botów nie jest
obiecana klientowi, a `\t`/`\r` nie mają drogi dojścia.


## 🟡 DWIE UWAGI WAŻNE Z RECENZJI `#259` (runda 2) ŚWIADOMIE NIENAPRAWIONE — dług, nie przeoczenie (1.09)

Decyzja koordynatorki: cztery uwagi rundy 1 domknięte (krytyczna jako KLASA, nie egzemplarz),
te dwie idą tutaj jako nazwany dług. ⛔ Obie dotyczą **tekstów o kodzie**, nie zachowania
produktu — ale obie są tą samą klasą 3. („dokumentacja/komentarz kłamie względem kodu"),
której te strażniki mają pilnować. To najgorsze możliwe miejsce na taki dług.

**1. Nieprawdziwe zdanie o kodzie przeżyło w CZWARTYM miejscu — w docbloku strażnika, który
ten PR tworzy.** `testy/chatbot-kasownik-slad-sesji-test.php:5-6` mówi: „`chatl_rodo_kasownik()`
sprawdzało wynik kasowania śladu współdzielonego i zgłoszeń, ale wynik kasowania wiersza
`chatl_sesje` szedł WYŁĄCZNIE do dziennika błędów". **Dwa błędy w jednym zdaniu:** ślad
współdzielony jest **zapisywany**, nie kasowany, a wynik kasowania **zgłoszeń** wcale nie był
sprawdzany — `git show origin/main:wtyczki/chatbot-live/includes/rodo.php` linie 400-402 to
`$ile_z = $wpdb->query( … DELETE FROM {$tz} … ); $ile_z = max( 0, (int) $ile_z );`, bez żadnego
`false ===`. Poprawną wersję tego zdania niesie już `wtyczki/chatbot-live/CHANGELOG.md`, wpis
`[1.5.14]`. Do tego opis zakresu w `:13-16` wymienia sekcje **A** i **B**, a plik ma **A-E** —
opis został z poprzedniej rundy.
**Kształt roboty:** przepisać zdanie z `CHANGELOG.md [1.5.14]` do docbloku i doprowadzić listę
sekcji do A-E. ⭐ Szersza reguła do rozważenia: **zdanie „co groziło" w strażniku ma być
CYTATEM z wpisu w CHANGELOG-u, nie jego parafrazą** — parafraza rozjeżdża się przy każdej
korekcie, a to już czwarte miejsce, w którym rozjechała się ta sama.

**2. Deklaracja zakresu bramki jest SZERSZA niż to, co bramka mierzy (klasa 11).**
`testy/faktura-obietnica-zgodna-z-kodem-test.php:19-23` ogłasza: „strona produktu · mail po
zakupie w OBU wersjach · SZABLON polityki prywatności zakładany przy aktywacji. **To wszystkie
miejsca**, w których sklep pisze kupującemu o fakturze", a wyjątek robi tylko dla ekranów panelu.
Jest **piąty** tekst i nie jest ekranem panelu: `wtyczki/sklep-agentow/includes/prywatnosc.php:807-816`
(`sklepag_polityka_prywatnosci()`) wstrzykuje przez `wp_add_privacy_policy_content()` drugi tekst
polityki, mówiący kupującemu o danych do faktury i o tym, że **zostają po wniosku o usunięcie**.
Sąsiadujący docblock sam nazywa te dwa teksty parą.
**Kształt roboty:** albo objąć pomiarem `sklepag_polityka_prywatnosci()`, albo zwęzić deklarację
tak, żeby nie mówiła „to wszystkie miejsca". ⛔ Zwężenie deklaracji jest tańsze, ale zostawia
piąty tekst bez strażnika — wtedy należy dopisać go do listy „znalezisk BEZ strażnika".

⛔ Oba wpisy są **nazwanym długiem, nie zamkniętą sprawą**. Wracają przy najbliższym dotknięciu
któregokolwiek z tych dwóch strażników.


## 🟡 TRZY UWAGI Z RECENZJI `#260` ŚWIADOMIE NIENAPRAWIONE — dług, nie przeoczenie (1.09)

PR `#260` (książka klas 94-96) nie dotyka kodu produktu, więc idzie **jedną rundą**: naprawione
zostały uwagi **1** (dwie fałszywe liczby w dwóch plikach — klasa 8, więc obie linie) i **2**
(pomiar skilla stał na komendzie, która nie mogła dać trafienia). Trzy pozostałe zapisuję tutaj.

**1. Numery linii w klasie 94 są z rewizji sprzed `#258`, a nie z `HEAD`** (recenzja `#260`, uwaga 3).
Wpis cytuje `zakup.php:211`, `:827`, `:845-852` — dziś pod tymi numerami stoi komentarz o naprawie,
bo migawka przesunęła plik. Mechanizm zgadza się co do linii w `80aac64^`. Sam wpis nie mówi,
której rewizji dotyczy, a opis PR-a twierdził, że „ze stanu na dziś".
**Kształt roboty:** dopisać przy cytatach `(stan sprzed #258, 80aac64^)`. Szersza reguła do rozważenia:
**każdy cytat `plik:linia` w rejestrze klas ma nieść rewizję**, bo numery przesuwają się przy każdym
scaleniu — to ta sama zasada, którą `CLAUDE.md` stosuje do sekcji dokumentacji („odsyłaj po tytule,
nie po numerze”).

**2. Klasa 95 ma ✅, choć obstawiony jest jeden test, nie klasa** (uwaga 4). Reguła we wpisie mówi
„każdy nowy test musi mieć warunki wstępne”, a mają je **7 plików ze 119** w `testy/`
(`grep -rln "warunek wstepny\|warunek wstępny" testy/`). Do tego werdykt goldena stoi na liczniku
BŁĘDÓW, a nie na **liczniku wykonanych asercji** — mimo że zdanie „sukces bez zliczonych asercji”
obiecuje właśnie ten drugi; przerwanie w połowie z zerem przeszłoby.
**Kształt roboty:** albo zejść z ✅ na ⬜, albo dołożyć licznik asercji do wzorca testu i bramkę,
która pilnuje, że każdy `testy/*-test.php` kończy się porównaniem `wykonane > 0`.

**3. „Siódmy przypadek rodziny” bez możliwości odtworzenia liczby** (uwaga 5). Nawias wymienia
cztery przypadki, a mówi „siódmy”; `grep -n "melduje nieprawd" audyt/SCIAGA-EGZAMINATORA.md` daje
jedno trafienie — tę samą linię. Rodziny nie da się policzyć z rejestru pod tą nazwą.
**Kształt roboty:** albo dopisać brakujące dwa do nawiasu, albo podać numery klas, albo nadać
rodzinie własny znacznik i liczyć ją komendą.

⛔ Wszystkie trzy dotyczą **rejestru, nie produktu**. Zapisane świadomie, żeby PR bez kodu nie
wchodził w drugą rundę — ale to jest dług tej samej klasy, którą ten rejestr tropi
(„dokumentacja kłamie względem kodu”), więc nie ma prawa leżeć tu długo.


## 🟡 Kontrola „każde wydanie z readme ma wpis w CHANGELOG” istnieje TYLKO u Segregatora (29.08, z recenzji #208 r5)

Wzorzec dodany w `#208` żyje w `testy/dokumentacja-zgodnosc-segregator.sh`. ChatBot i Sklep go
nie mają — czyli u nich skasowanie opisu wydania po jednej stronie nadal przejdzie na zielono.

To **klasa 8** (naprawa trafia w jeden egzemplarz wzorca, bliźniak zostaje) — ta sama, przez którą
kontrola `DOK=KOD` wracała w tym PR-ze trzy razy, zanim trafiła do wszystkich trzech wtyczek.
Zapisuję ją świadomie zamiast dokładać czwartą rundę zmian do PR-a o paczkach.

Kształt roboty: przenieść pętlę po wersjach z readme (z progiem czytanym z `CHANGELOG.md`,
czyli z INNEGO pliku niż pętla — inaczej wraca tautologia z r4/r5) do
`testy/dokumentacja-zgodnosc.sh` i `testy/dokumentacja-zgodnosc-sklep.sh`, plus po jednym
przypadku w ich próbach kontrolnych.

⛔ Do zrobienia przy najbliższym PR-ze dotykającym bramek dokumentacji — nie pilne dla klienta,
bo dotyczy naszego oprzyrządowania, nie produktu.

---

## ✅ ROZLICZONE (zmierzone 5.09) — STRAŻNIK PACZEK STRZELA SAM: `paczka` jest w `BRAMKI=()` w `narzedzia-qa/bramki.sh:47` (bieg 13 z 13 na każdym PR-ze w CI); `paczka-zgodnosc.sh` dodatkowo w briefingu recenzenta

## 🔴 (archiwum) STRAŻNIK PACZEK ISTNIEJE, ALE NIE STRZELA SAM — wymaga PR-a manifestowego (29.08, z recenzji #208 r1)

`narzedzia-qa/paczka-zgodnosc.sh` wykrywa rozjazd paczki z repo i **pada poprawnie** (kod 1 na
`f310eec` dla obu botów, kod 0 po naprawie). Ale nie ma go w `BRAMKI=()` w `narzedzia-qa/bramki.sh`
ani w `.github/workflows/quality.yml:84`, który uruchamia dokładnie jedną komendę: `bash
narzedzia-qa/bramki.sh`. Czyli wykrycie rozjazdu zależy od tego, czy komuś przyjdzie do głowy
sięgnąć po paczkę.

**To już trzecie wystąpienie tej klasy** (1.5.8/1.3.1 → 1.5.11/1.3.4 → teraz), udokumentowane
w `audyt/boty/material/06-mapa-polowania.md:286`.

Dlaczego nie zrobione od razu: `.github/manifest-bramek.txt` ma własną zasadę wpisaną w nagłówku —
*„każda zmiana = osobny PR, który Dzidek czyta w całości"* oraz *„agent NIE dotyka tego pliku
w PR-ach z kodem"*. A `sprawdz-manifest.sh` ma kontrolę odwrotną: bramka w `BRAMKI=()`, której nie
ma w manifeście, świeci na czerwono. Więc dopisanie jej w PR #208 złamałoby zasadę domu.

Kształt, który ma sens (za recenzją #208 r1) — **nie** samo dopisanie `paczka-zgodnosc` do listy,
bo to wywracałoby każdy PR ruszający kod wtyczki bez wydania. Warunek: *pliki wtyczki zmienione
po commicie budującym aktualny zip **ORAZ** wersja nie podbita* → PAD.

⛔ Do decyzji właściciela: osobny PR manifestowy teraz czy po oddaniu.

---

## ✅ ROZLICZONE 5.09 (#291) — KLASA 5: skaner 1.4.0/1.4.1 zna formy litera-myślnik-numer (`A2-F3`, `P2-Z1`, `DK-5`, `WK-13`) i „ten/tego PR”/„Runda N”; drzewo i nowe paczki 0 śladów (`paczka-zgodnosc.sh` EXIT=0 ×3); stare wydane archiwa zostają (klasa 10)

## 🟡 (archiwum) KLASA 5 (ślady wewnętrzne w paczce) NIE JEST DOMKNIĘTA — 47 trafień rodziny, której skaner nie widzi (29.08, z recenzji #208 r1)

`#208` usunął z paczek jeden ślad (`zasada D22`). Skaner `narzedzia-qa/slady-wewnetrzne.sh`
zna wzorce `\bD[0-9]{1,2}\b` i `\bK[0-9]\b` (linia 77) — i dlatego zobaczył `D22`. **Nie zna**
form `A2-F3`, `P2-Z1`, `DK-5`, `KR1/KR2`, `WK-13` ani zwrotu „wroga sesja".

Pomiar (29.08, komenda do powtórzenia):

```
grep -rnE "audyt (A[0-9]|DK-|P2-|KR[0-9])|\(audyt [A-Z0-9-]+\)|A[0-9]-F[0-9]|DK-[0-9]|P2-Z[0-9]|wrogiej sesji|wroga sesja" \
  wtyczki/chatbot-live/ wtyczki/segregator-maila/ wtyczki/sklep-agentow/ \
  --include=*.php --include=*.js --include=*.txt --include=*.md | wc -l
→ 47   (chatbot-live 34 · segregator-maila 12 · sklep-agentow 1)
```

Trzy z tych plików **jadą do klienta** (są na allowliście `boty/buduj-paczki.sh`):
`wtyczki/chatbot-live/CHANGELOG.md`, `wtyczki/chatbot-live/DOKUMENTACJA-TECHNICZNA.md`,
`wtyczki/segregator-maila/CHANGELOG.md`. Kupujący czyta w historii zmian „audyt A5, KR1/KR2"
i „A2-F3" — numery raportów, których nigdy nie zobaczy.

⚠️ To jest **tekst czytany przez klienta**, czyli kategoria, która wg reguły rund zawsze wraca
do naprawy. Nie zrobione w #208 świadomie: PR nie tyka kodu produktu, a czyszczenie 47 komentarzy
plus rozszerzenie skanera plus przypadki do próby kontrolnej to osobna robota — recenzent #208 r1
wprost dopuścił drogę „nazwany dług zamiast czyszczenia w tym PR-ze".

⛔ Do zrobienia przed oddaniem klientowi: rozszerzyć wzorzec skanera o tę rodzinę, dopisać
przypadki do `narzedzia-qa/paczka-zgodnosc-proba-kontrolna.sh`, wyczyścić trafienia w trzech
plikach jadących do klienta (komentarz ma nieść POWÓD, numer zostaje w PR i commicie).

### STAN PO `#209` (29.08) — CZTERY RODZINY, TRZY RUNDY, I DLACZEGO ZA KAŻDYM RAZEM „ZERO" BYŁO PRZEDWCZESNE

⛔ **Ten wpis był raz nieprawdziwy i to jest część lekcji.** Po rundzie 2 stało tu zdanie
„rodzina odsyłaczy słownych — **wyczyszczona**". Runda 3 wykazała, że w paczce zostaje wtedy
**20 wierszy**, w tym siedem będących odmienioną formą słowa poprawionego w tym samym pliku
i tym samym commicie. Zdanie zostało poprawione dopiero po tej uwadze.

**Co znalazła która runda — i czym mierzona:**

| runda | rodzina | ile | miara |
|---|---|---|---|
| r1 | identyfikatory (`SK-L3-005`, `LS-F1`, `A3-2`, `Do audytu`) | 22 | wzorzec recenzenta |
| r2 | odsyłacze słowne, mianownik (`przegląd na żywo DD.MM`, `sędzia`, `Znalezisko testu`) | 42 | wzorzec recenzenta |
| r2 (moje) | formy poza oboma wzorcami (`feedback`, `recon`, `runda stron`, `ZMIERZONE DD.MM`) | 65 podmian | wzorzec własny — **za wąski** |
| r3 | odmiany polskie (`sędzi`, `sędziego`) + nasze rundy robocze (`Koszyk A`, `Opinie v2`) + nazwy dokumentów (`ZWIAD-STRIPE`, `K3d`, `raport audytora`) | 20 | wzorzec recenzenta |
| r3 (moje) | odsyłacze do `PLAN-PROJEKTU.md` i naszych PR: `Plan §7`, `granica §9`, `§4A`, `§5b.1`, `§5b.3`, `(PR 2)` | 8 | **wyliczenie kandydatów** |

⛔ **Rodzina z ostatniego wiersza nie była na liście żadnego z nas.** Znalazło ją dopiero
wyliczenie po znaku `§` i po kształcie naszych oznaczeń — nie szukanie form, o których się
pamiętało. To jest cała różnica między „mój wzorzec nic nie znajduje" a „nie ma już kandydatów".

**Miara, która ma zostać** (trzy niezależne wyliczenia, wszystkie odtwarzalne):

```
# A — każda linia z datą, BEZ żadnego kontekstu przed datą
grep -rnE '[0-9]{2}\.[0-9]{2}' wtyczki --include=*.php --include=*.md --include=*.js \
     --include=*.txt --include=*.css

# B — rdzenie słowne, NIE całe słowa (polski odmienia: sędzia → sędziego → sędzi)
grep -rniE '(sędzi|zwiad|wzorc[eóa]w? §|Koszyk A|Opinie v2|rundzie stron|feedback|recon |znalezisk|UZUPEŁNIEN|przegląd|audyt [0-9]|raport audytora)' \
     wtyczki --include=*.php --include=*.md --include=*.js --include=*.txt --include=*.css \
  | grep -viE 'przeglądark|reconciliation'

# C — kształt NASZYCH oznaczeń (to wyliczenie znalazło rodzinę, której nikt nie szukał)
grep -rnE '(§[0-9]|\bK[0-9][a-z]?\b|\bA[0-9]-|\bT[0-9]\b|ZWIAD|\bD2[0-9]\b|\bPR [0-9])' \
     wtyczki --include=*.php --include=*.md --include=*.js --include=*.txt --include=*.css
```

Fałszywe trafienia tych wyliczeń są znane i mają zostać: daty w API Stripe (`28.10.2024`),
przykład formatu wpisu (`'25.08.2026'`), `56.01rem`, `50.000`, stała gamma `12.92`, słowa
„przeglądarka" i „reconciliation", `uzupełnienie` jako zwykłe słowo, odsyłacze do **publicznych**
standardów (RFC 3501 §6.4.8, XML 1.0 §2.5) oraz `§N` odsyłające **wewnątrz** dokumentacji
technicznej, którą klient dostaje w paczce.

⛔ **Co ZOSTAJE długiem — i UWAGA, pierwotny opis tego długu był NIETRAFNY.** Napisałam tu
„skaner nie zna żadnej z tych czterech rodzin". Recenzja r4 wykazała, że dla rodziny
`\bK[0-9]\b` to nieprawda: skaner **zna** ten wzorzec od wersji 1.3.0
(`narzedzia-qa/slady-wewnetrzne.sh:78`), a mimo to przepuścił `K8`
w `dokumentacja/INSTRUKCJA-INSTALACJI.md`. Prawdziwa wada była w **ZASIĘGU**, nie we
wzorcach: pętla `for katalog in wtyczki/*/` nigdy nie patrzyła poza katalogi wtyczek,
przy własnym komentarzu stawiającym znak równości między „pliki wtyczek" a „to, co
trafia do klienta". ⭐ To ma znaczenie praktyczne: dopisywanie wzorców niczego by nie naprawiło.

⛔ **Zasięgu NIE rozszerzyłam w tym PR-ze i to jest decyzja z pomiaru, nie zaniechanie.**
Próba rozszerzenia pętli o `dokumentacja/` została **skalibrowana przed scaleniem** i kalibracja
ją odrzuciła — skaner na CZYSTEJ dokumentacji kończy `EXIT=1`:

```
$ SLADY_ZALEGLOSCI=/dev/null bash narzedzia-qa/slady-wewnetrzne.sh dokumentacja dokumentacja
  SLAD  INSTRUKCJA-INSTALACJI.md:101 (adres maszyny dev): Serwery Stripe nie widzą localhosta — użyj oficjalnego Stripe CLI:
  SLAD  INSTRUKCJA-INSTALACJI.md:103 (adres maszyny dev): stripe listen --forward-to http://localhost:PORT/wp-json/sklepag/v1/webhook
SLADY 1.3.0 [dokumentacja]: nowe=2, dlug z listy=0, martwe wpisy=0   → EXIT=1
```

Oba trafienia są **fałszywe**: `localhost` w pliku wtyczki jest śladem, ale w instrukcji
instalacji jest **treścią, której klient potrzebuje** — bez niej nie skonfiguruje webhooka.
Wpięcie tego zasięgu postawiłoby CI na czerwono od pierwszego przebiegu. ⛔ Dopisanie tych
dwóch wierszy do `slady-zaleglosci.txt` **nie wchodzi w grę** — nagłówek tego pliku mówi wprost:
*„ta lista ma się wyłącznie KURCZYĆ; dopisanie nowego śladu, żeby przepchnąć swój PR, jest
obejściem bramki, nie jej użyciem"*.

**CO NAPRAWDĘ ZAMYKA tę wadę:** rozdzielić wzorce per TEREN — dla `wtyczki/` pełny zestaw,
dla `dokumentacja/` węższy (rodzina „numeracja naszych dokumentów”: `K[0-9]`, `D[0-9]{1,2}`,
`§[0-9]`, `S-0[0-9]`), **bez** wzorca „adres maszyny dev”. Dopiero wtedy pętla może objąć
`dokumentacja/` i kontrola odwrotna („czysty tekst przechodzi”) będzie zielona. To zmiana
narzędzia QA, nie produktu — osobny PR.

⛔ **ALE SAMA TA RECEPTA NIE WYSTARCZY — i to jest najważniejsze zdanie tego wpisu.**
Recenzja r5 wykazała dwa ślady w `dokumentacja/INSTRUKCJA-INSTALACJI.md`, których **żaden
wzorzec z tej recepty by nie złapał**:
- „zrzut ekranu dojdzie przy finalizacji instrukcji” — notatka o stanie NASZEJ pracy zapisana
  zwykłym **słowem**, bez jednego oznaczenia (bliźniak skreślonego w tym samym commicie zdania
  „do finalizacji w K8: zrzuty + diagram”);
- „Pełna lista różnic: rozdział «Co oddziela tryb testowy od produkcji» **w dokumentacji
  projektu**” — odsyłacz do `PLAN-PROJEKTU.md:433`, czyli pliku, którego klient nigdy nie
  dostaje, podany **parafrazą** zamiast nazwą. Wzorzec `PLAN-PROJEKTU` jest w skanerze
  (`slady-wewnetrzne.sh:77`) i był bezradny, bo tej nazwy w zdaniu nie ma.

⭐ **Wniosek, który przenosi się na cały projekt:** dla `dokumentacja/` miarą nie jest wzorzec,
tylko **przeczytanie pliku w całości**. To JEDEN plik na 263 wiersze — przeczytanie go kosztuje
minuty, a wzorzec przepuścił tam ślad trzy rundy z rzędu. Wzorce zostają jako **siatka
bezpieczeństwa dla regresji**, nie jako dowód czystości. ⛔ Zasada ogólna: gdy teren da się
przeczytać w całości, czytanie JEST wyliczeniem — wzorzec jest wtedy zgadywaniem, nie pomiarem.

Nadal długiem zostaje też to, że skaner nie zna rodzin: odsyłaczy słownych, naszych rund
roboczych i dat `DD.MM`. Teksty są czyste dziś, ale nic nie broni ich jutro — pierwszy nowy komentarz
z odsyłaczem wjedzie do paczki klienta bez jednego czerwonego światła. To już **trzeci** PR
z rzędu, w którym ten sam dług kosztuje rundę recenzji.
**CO ZAMYKA:** wpiąć te trzy wyliczenia do skanera (każda rodzina osobno, plus lista znanych
fałszywych trafień jako allowlista) i dołożyć po jednym przypadku na rodzinę do
`narzedzia-qa/paczka-zgodnosc-proba-kontrolna.sh`, **żeby próba mierzyła POKRYCIE, nie samo
działanie wzorca** — bo dokładnie ta różnica kosztowała trzy rundy.
⛔ Świadomie NIE robione przed oddaniem: to zmiana narzędzia QA, nie produktu.

---

## ✅ ROZLICZONE — baner „TRYB TESTOWY” ma warunek od 0.9.29 (wpis z 29.08, recenzja #209 r6)

⛔ **Ten wpis był otwarty o dwa dni za długo i zdążył zrodzić fałszywą decyzję D38** (wycofaną 3.09).
Zmierzone na `origin/main`, każde miejsce osobno: `sklepag_tryb_testowy()` czyta prefiks klucza,
`sklepag_baner_testowy()` zwraca **pusty napis** przy `sk_live_`, a pozostałe miejsca w `sklep-agentow.php`,
`front-produkt.php`, `zakup.php` (treść maila „TRYB TESTOWY”) i `mail-html.php` idą przez `sklepag_tryb_testowy()`. Wydanie: sekcja `## [0.9.29]`
w `CHANGELOG.md` (1.09). **Nauka:** rozliczaj wpis W TEJ SAMEJ rundzie, w której naprawiasz — wpis żyjący po
naprawie jest groźniejszy niż brak wpisu, bo czytelnik nie sprawdza kodu, tylko wierzy rejestrowi.

<details><summary>Treść wpisu z 29.08 (historia — opisuje stan SPRZED 0.9.29)</summary>

`sklepag_baner_testowy()` (`wtyczki/sklep-agentow/sklep-agentow.php:168`) zwraca **stały HTML,
bez jednego warunku**, a **wszystkie TRZY wywołania są bezwarunkowe**: `sklep-agentow.php:286` (lista),
`includes/front-produkt.php:791` (strona produktu) oraz `sklep-agentow.php:393` — ⛔ **strona
„Dziękujemy za zakup”, czyli ekran widziany PO ZAPŁACIE**. Trzecie miejsce przeoczyłam
w rundzie 6 i wykazała je dopiero recenzja r7: ktoś, kto właśnie zapłacił prawdziwe
pieniądze, przeczytałby tam, że płatności nie są pobierane. Treść banera:

> **TRYB TESTOWY** — płatności nie są pobierane. Przetestujesz cały zakup kartą testową, nic nie płacąc.

⛔ **Po przejściu na klucze `sk_live_…` ten napis zostaje.** Kupujący czyta, że nie płaci —
i płaci. To ścieżka pieniędzy, nie kosmetyka.

**Skąd się wzięło:** instrukcja instalacji obiecywała, że baner „znika sam po przełączeniu na
klucze produkcyjne" — nieprawda o kodzie (klasa 3, wykazana w recenzji `#209` r6). **Tekst
poprawiony na prawdziwy w tym PR-ze**: instrukcja mówi teraz wprost, że baner nie znika sam
i że usunięcie go jest po naszej stronie.

⛔ **KODU NIE RUSZAM PRZED ODDANIEM — decyzja z powodem, nie zaniechanie.** Komentarz przy
funkcji mówi: *„napis bez zmian — testy na nim polegają"*, więc zmiana warunku tuż przed
zamrożeniem grozi regresją w testach, które ten napis sprawdzają. Naprawa tekstu **zamyka klasę
błędu** (dokumentacja przestaje kłamać); wada produktu zostaje tu nazwana.

⛔ **UWAGA — TO NIE JEST JEDNA FUNKCJA. Recepta poniżej była raz za wąska** (recenzja r8/r9:
obejmowała wyłącznie `sklepag_baner_testowy()`, czyli mniej niż połowę wady). Oznaczeń trybu
testowego widzianych przez KUPUJĄCEGO jest **siedem** (liczba rosła w rundach 6-10:
trzy -> sześć -> siedem, za każdym razem bo wypisywałam listę zamiast ją policzyć; od 0.9.24
OSIEM — wersja HTML maila niesie to samo zdanie, `grep -rn "płatność nie została pobrana" wtyczki/sklep-agentow` liczy oba):

```
sklep-agentow.php:286             baner — lista produktów          ⟵ sklepag_baner_testowy()
includes/front-produkt.php:791    baner — strona produktu          ⟵ sklepag_baner_testowy()
sklep-agentow.php:393             baner — strona „Dziękujemy”      ⟵ sklepag_baner_testowy()
includes/front-produkt.php:593    ⛔ ETYKIETA PRZYCISKU: „Kup teraz (płatność testowa)”
includes/front-produkt.php:594    notka przy przycisku zakupu (karta 4242…)
sklep-agentow.php:365             FAQ „Co znaczy tryb testowy?”
includes/zakup.php:959            ⛔ TREŚĆ MAILA po zakupie („płatność nie została pobrana”)
includes/mail-html.php            ⛔ to samo zdanie w wersji HTML maila (0.9.24) — szukaj po frazie, nie po numerze linii
```

Panel administratora ma własne oznaczenia (`admin-dashboard.php:338`, `admin-zamowienie.php:475`,
`admin-ustawienia.php:107`) — te widzi WYŁĄCZNIE właściciel, więc zostają.

**CO ZAMYKA:** jedno źródło prawdy o trybie — funkcja typu `sklepag_tryb_testowy()` czytająca
prefiks klucza (`sk_test_` / `sk_live_`), i **wszystkie siedem miejsc pyta JEJ**, zamiast każde
osobno trzymać swój napis. Do tego przypadek testowy na OBA tryby dla KAŻDEGO z siedmiu miejsc
(przy kluczu testowym napis JEST, przy produkcyjnym GO NIE MA) — inaczej naprawa mogłaby zabrać
oznaczenia wszystkim i świecić na zielono, a kryterium 11 zamówienia wymaga banera WIDOCZNEGO
w trybie testowym. Bump wersji sklepu + przebudowa paczki.
⚠️ **Do decyzji właściciela: czy robimy to przed oddaniem, czy po.** Przy oddaniu na kluczach
TESTOWYCH baner jest poprawny i egzaminu nie psuje — problem pojawia się dopiero przy pierwszym
prawdziwym wdrożeniu.

---

</details>

## 🟡 ODDANE DO K8 — odbior WYDAJNOSCI (perf) na docelowym hostingu · AN-022
- `perf` z Lighthouse NIE zostal odebrany w K7 i to jest decyzja, nie przeoczenie: na tym stosie
  pomiar mierzy OBCIAZENIE NASZEJ MASZYNY, nie jakosc strony. Ta sama komorka (sklep/mobile)
  dawala 100 · 87 · mediane 100 przy load 1,47 · mediane 93 przy load 2,94. Serwer ma 2 vCPU,
  a tryb telefonu spowalnia procesor 4x; sam pomiar dokłada sie do obciazenia.
- ODEBRANE i NIE wymagaja powtorki: `a11y`, `bp`, `seo` — >=96 w kazdej komorce, stabilne.
- ⛔ DO ZROBIENIA PRZY WYDANIU (K8): zmierzyc `perf` na DOCELOWYM hostingu klienta komenda
  `bash testy/e2e/lighthouse-3-podstrony.sh <adres-klienta> <katalog> 95 3`. Dopiero ten wynik
  jest werdyktem dla celu L6-01 — liczba z naszego stosu nie przenosi sie na produkcje.
- Cel L6-01 stoi w mapie jako ⚠️ (anomalia AN-022, status WYJAŚNIONA), NIE jako zamkniety.
- ⛔ WRACA ⇒ kasujesz ten wpis i przestawiasz L6-01 na ✅ z wynikiem z hostingu klienta.


## 🟡 DŁUG ZASTANY — okno idempotencji nie obejmuje JEDNOCZESNEGO podwójnego kliknięcia (5.09: po stronie przeglądarki przycisk JEST blokowany po wysłaniu — `assets/platnosc.js:146`; zostaje wyścig dwóch żądań spoza przeglądarki, dług nie błąd klienta)
(z recenzji #199 r2, DROBNA 5 — NIE regresja z tego PR, mechanizm istnieje od 0.9.0)
- Odczyt okna (`zakup.php` `get_transient`) i jego zapis (`set_transient`) dzieli wyjście do
  sieci: `sklepag_stripe_utworz_sesje()`. Dwa żądania wysłane NAPRAWDĘ jednocześnie przechodzą
  przez odczyt, zanim którekolwiek dojdzie do zapisu — więc oba tworzą własną sesję Stripe.
- Po stronie przeglądarki nic tego nie blokuje: formularz zakupu (`front-produkt.php`) nie ma
  `onsubmit` ani `disabled`, a w `wtyczki/sklep-agentow/assets/*.js` nie ma ani jednego trafienia
  na `disabled|submit` (dowód z braku, podany przez recenzenta).
- Skutek: dwie sesje checkout dla jednego kliknięcia-dubletu. Opłacona zostanie jedna (druga
  wygasa), więc NIE jest to podwójne obciążenie — ale dziennik dostaje sesję-sierotę.
- ⭐ Najtańsze domknięcie: wyłączenie przycisku na czas wysyłki po stronie przeglądarki
  (jedna linia JS) ALBO atomowa rezerwacja okna przed wyjściem do Stripe.
- ⛔ WRACA ⇒ kasujesz ten wpis. Nie blokuje wydania: skutek jest kosmetyczny, nie finansowy.


## 📌 KONTRAKT NA PRZYSZŁOŚĆ (z recenzji #177 r3–r5, 25.08)
- **`sklepag_opinia_moderuj` przepuszcza opinię BEZ wiersza zamówienia** (INNER JOIN →
  null → wolno moderować). Dziś taki stan nie powstaje produkcyjnie (zero ścieżek DELETE
  na zamówieniach — grep recenzji). ⛔ GDY powstanie pierwsza ścieżka kasowania zamówień,
  warunek MUSI przejść na „brak danych = odmowa" (reguła CLAUDE.md) — inaczej kasowanie
  zamówienia otworzy z powrotem moderację opinii zapomnianego klienta.

## 🔧 PRZYRZĄDY (z recenzji #177, DROBNA, 25.08)
- **Wzorzec testowania handlerów admin-post ISTNIEJE w repo** (sprostowanie po recenzji
  #177 r8 — mój wcześniejszy wpis „nie ma harnessu" był FAŁSZYWY, obalony grepem
  recenzji): `segm_u_odpal` + `wp_die_handler` w testach segregatora, `dokad()`
  u chatbota; handler resendu sklepu pokryty tym wzorcem w `opinie-mechanizm-test.php`
  (sekcja C3: nonce + przechwycony redirect + `pre_wp_mail`). ⬜ POKRYĆ tym wzorcem
  pozostałe handlery sklepu (produkt_zapisz/przelacz, anonimizuj, dociagnij,
  eksport_csv, opinia_moderuj) przy następnej rundzie QA.
- **`stos.sh` nie widzi ŻADNEJ tabeli sklepu** — okno odczytu recenzenta nie ma operacji
  zaglądania do `wp_sklepag_*`, więc recenzent nie może sam obejrzeć np. wierszy opinii
  (dostał odmowę przy #177 i oparł się na przebiegu testu). Rozszerzyć okno o bezpieczną
  operację odczytu tabel sklepu (SELECT-only, bez danych osobowych w wyjściu — imię maskować).

- **`dowod-z-paczki-proba-kontrolna.sh` umiera na ZASZYTEJ NA SZTYWNO wersji paczki** —
  szuka `boty/paczki/chatbot-live-1.5.0.zip`, którego w repo nie ma — katalog zawiera
  wyłącznie wersje od 1.5.7 wzwyż (lista rośnie z każdym wydaniem, dlatego nie wymieniam jej tu
  po numerze: zdanie z listą wersji dezaktualizuje się przy najbliższej paczce).
  Kończy **EXIT=2**, czyli „błąd przyrządu”, i tak samo zachowuje się na czystym `origin/main`
  (zmierzone 28.08: log prób z `main`, commit `28533e0`, ta sama pozycja `EXIT=2 0s`).
  ⛔ Znaczenie: nie wiemy dziś, czy `dowod-z-paczki.sh` **potrafi paść** — a to on pilnuje
  paczki jadącej do klienta. Luka dotyczy WIARYGODNOŚCI narzędzia, nie samej paczki:
  zgodność paczki 0.9.20 jest zmierzona osobno (`paczka-zgodnosc.sh` → EXIT=0, 59/59 plików).
  **CO ZAMYKA:** wskazać w próbie paczkę, która w repo istnieje (albo wyliczać najnowszą
  zamiast wpisywać wersję), potem przelot prób od nowa. ⚠️ Naprawa unieważnia świeży log
  prób (warunek 6 `k7-koniec` przeterminowuje dowód po zmianie w `narzedzia-qa/`), więc
  robi się ją RAZEM z pozostałymi pięcioma padającymi próbami chatbota, nie osobno.

## 🔍 K7 — AUDYT SKLEPU W TOKU (od 25.08 wieczór) — plan `audyt/PLAN-K7-AUDYT-SKLEPU.md` v2, krytyka r1 obok
- Faza: PR-0 (paczka 0.9.13 + D33) → materiał `audyt/sklep/material/` → kalibracja → runda A. Stan bieżący
  i decyzje: `/root/.claude/CONTEXT-SERWER.md`. ⛔ Kod zamrożony tagiem `k7-zamrozenie` po scaleniu PR-0.
- Zaległości znalezione PRZY CZYTANIU (cele mapy, NIE naprawiać przed audytem): DOK-TECH §3 „7 tabel" vs §8 „6",
  „#170" w dokumencie klienta, tabela §2 bez `opinie.php`; `REJESTR-WERYFIKACJI.md` martwe wpisy Cloudflare/Vercel;
  zakup gościa wymaga nonce (`zakup.php:66`) → page-cache; brak eksportera/erasera RODO WP (D: dopisać w naprawach);
  zwroty Stripe bez obsługi (decyzja po L3); WP-Cron bez ruchu nie w instrukcji.
- 3 martwe pliki mediów w paczce (1,06 MB) — kasowanie ZGODA właściciela 25.08, wykonać w rundzie napraw (bump).
- ✅ **ROZLICZENIE TRZECH ZALEGŁOŚCI Z TEJ LISTY (3.09, pomiar na `origin/main`, kod otwarty ręcznie):**
  **DOK-TECH „7 tabel" vs „6"** — dziś dwa wystąpienia „7 tabel", zero „6 tabel" ⇒ rozjazdu NIE MA ·
  **brak eksportera/erasera RODO WP** — `includes/prywatnosc.php` rejestruje przez `add_filter` `wp_privacy_personal_data_exporters`
  i `…_erasers`, bliźniaki w `chatbot-live/includes/rodo.php`; oba pliki dołączane BEZWARUNKOWO
  (w plikach glownych obu wtyczek), nie za `is_admin()`, bo kreator woła też przez `admin-ajax` i cron ·
  **zwroty Stripe bez obsługi** — `includes/zakup.php` w obsłudze webhooka nasłuchuje `refund.created`, `refund.updated`
  ORAZ `charge.refunded` i rozstrzyga po kwocie już zapisanej w bazie.
  ⛔ Reszta wiersza wyżej (`#170` w dokumencie klienta, tabela §2 bez `opinie.php`, martwe wpisy Cloudflare/Vercel,
  nonce zakupu gościa, WP-Cron w instrukcji) **NIE była w tym pomiarze** — zostaje otwarta.

## ✅ ZAMKNIĘTE 25.08 (wieczór) — SKLEP: readme.txt + CHANGELOG.md + paczka 0.9.13 (PR-0 K7)
- `paczka-zgodnosc.sh sklep-agentow` EXIT 0 (6 miejsc wersji, zip == repo 59 plików, treść wydania, 0 śladów),
  próba kontrolna bramki EXIT 0, `bramki.sh --tryb pelny` 8/8. `buduj-paczki.sh` z wyborem wtyczki argumentem
  (zamek wydanych zipów botów). Dług z #176 zamknięty.

## 🔄 W TOKU 20.08 (noc) — RUNDA BOTÓW, DWA BUILDERY
⏰ **TERMIN: do 1.09** (właściciel 20.08 — zmiana z 30.08).
- **builder1** (tmux praca:builder1, drzewo główne, gałąź `boty/runda-finalna`): TYLKO chatbot
  + klasa AI w obu botach. Zrobione: Z1 (fałszywy sukces zapisu, oba boty), **Z0 (tryb AI —
  był MARTWY)**, Z2 (zgoda RODO w formularzu). Dalej: PLAN-NAPRAW-CHATBOT v2, P1 i P2.
- **builder2** (tmux praca:builder2, drzewo `sklep-agentow-boty`, gałąź `boty/segregator-naprawy`):
  TYLKO segregator, punkty 1-8 z BRIEF-BUILDER2. ⛔ nie dotyka `segregator-maila/includes/ai.php`.
- ⛔ **Scalenie gałęzi + bump 1.2.2 + przebudowa paczek = koordynator**, nie buildery.
- ⏸️ **WSTRZYMANE do decyzji**: eksport filtrów Gmail/Outlook (P1.3) — nasz silnik zatrzymuje się
  na pierwszej pasującej regule, Gmail stosuje wszystkie naraz → eksport 1:1 dałby inny wynik
  niż nasz raport. Rekomendacja: eksport z jawnym ostrzeżeniem + lista reguł nieprzekładalnych.
- 🔑 Klucze API (Anthropic, Gemini) w `/root/.claude/sekrety-*.env` — **DO SKASOWANIA po testach**.
- ⚠️ Tunel publiczny AKTYWNY, siteurl stacka przestawiony na adres tunelu (przepis przywrócenia
  w CONTEXT-SERWER.md).


## ⚠️ WISZĄCE OD 19.08 (sesja koordynatorka-3) — TUNEL PUBLICZNY na czas oglądania właściciela
- ~~Tunel cloudflared AKTYWNY~~ **NIEAKTUALNE (sprawdzone 30.08: proces `cloudflared` nie żyje, `siteurl`=`http://localhost:8890`)** — wpis zostawiony jako ślad procedury: `https://linda-refrigerator-potato-objective.trycloudflare.com`
  → :8890 (proces w tle sesji; log `/tmp/cf-sklep-8890.log`). **siteurl+home stacka
  PRZESTAWIONE na adres tunelu** — po seansie właściciela PRZYWRÓCIĆ:
  `docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update siteurl "http://localhost:8890" && docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update home "http://localhost:8890"`
  + ubić tunel (`pkill -f "cloudflared tunnel --url http://127.0.0.1:8890"`).
  ⛔ Do tego czasu testy automatyczne po localhost NIE są miarodajne (canonical
  redirect na tunel); E2E przez tunel: kopia `przelot2-tunel.js` ⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE — do odtworzenia z `testy/e2e/przelot2.js` przez podmianę adresu bazowego na tunel.
- **header.php motywu KOPII zmieniony poza repo** (link „Sklep" w obu nav —
  D28): backup w kontenerze `header.php.przed-sklepem`; dosłowne fragmenty
  w INSTRUKCJA-INSTALACJI (sekcja Ustawienia produkcyjne).

## ✅ ZAMKNIĘTE 19.08 (późny wieczór) — ⭐ K5b3 FINAŁ SZLIFU (PR #61 MERGED) = SZLIF WIZUALNY ZAMKNIĘTY
- Sędzia finalny **84/100** (przynęta „sekcja wideo" rozbrojona): finalny CTA
  listy („Wybierz bota…" + 2 przyciski) · hierarchia linków (DLA KOGO:
  „Więcej o tym bocie") · kadr raportu 1200×470 (czytelność +60%) ·
  BONUS: Sklep dopisany do nav STOPKI (footer.php kopii, backup; instrukcja).
- Zarzut „pusta stopka" 2× ODRZUCONY z twardym dowodem: viewport po WOLNYM
  scrollu = pełna stopka, wszystkie elementy op=1. ⭐ WADA NARZĘDZIA:
  fullPage-screenshot playwrighta NIE odpala reveal-animacji motywu — oceny
  wyglądu odtąd zrzutami VIEWPORTU po wolnym scrollu, nigdy samym fullPage.
- Recenzja #61 czysta (zero uwag krytycznych/ważnych). E2E: mail_wyslany
  (5. pełny przelot dnia). Sklep w nav: nagłówek desktop + mobile + stopka.

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5b2 ILUSTRACJE SVG KART (PR #60 MERGED, D28b)
- Karty listy: WŁASNE ilustracje SVG w brandzie (roboty-bliźniaki, 880×560,
  ~4 KB) — feedback właściciela „fajne obrazy jak profesjonaliści"; zrzuty żywych
  botów zostały na stronach produktów (podział: lista=marketing, produkt=dowód).
  Generyczny stock/AI-obrazy ODRZUCONE (D28b). Opinie/gwiazdki NIE (D6/Omnibus
  — powiedziane właścicielowi wprost).
- Recenzent złapał REALNEGO BUGA (modyfikator klasy CSS doklejany do złego
  członu przy dwuczłonowym $klasa) → naprawiony, dowód surowym renderem klas;
  D28b dopisane do rejestru (kod cytował decyzję bez wpisu — klasa #3).
  ⭐ NOWA ZASADA z uwagi krytycznej: opis PR-a MA surowe wyjście komend, nie
  deklaracje. E2E po każdej porcji: mail_wyslany (łącznie 4× dziś).

## ✅ ZAMKNIĘTE 19.08 (popołudnie) — ⭐ K5b WIZUALIA (PR #59 MERGED, sklep 0.9.0, D28)
- **Feedback właściciela obsłużony**: link „Sklep" w nav (desktop+mobile) · zrzuty
  ŻYWYCH botów na kartach i w sekcji „JAK TO WYGLĄDA" (rama „okna aplikacji") ·
  lista: pasek konkretów + OD ZAKUPU DO DZIAŁANIA + DLA KOGO (jedno źródło
  treści) + PYTANIA (liczby ze stałych = dok=kod). Wideo ODRZUCONE (D28).
- **Sędzia 79/100 (przynęta rozbrojona)**: 2 zgrzyty naprawione (okno 20rem,
  rama okna), zarzut „pustka przed stopką" ODRZUCONY Z DOWODEM (artefakt
  fullPage bez scrolla — reveal stopki gospodarza, opacity [0,0]→[1,1] po
  scrollu; ⚠️ zrzuty full-page odtąd PO przescrollowaniu!), postulat opinii
  ODRZUCONY (D6/Omnibus).
- **E2E ×2 przez tunel: mail_wyslany** · recenzent: 4 drobne uwagi zaadresowane
  (2 kodem, 2 pomiarem: wymiary JPG zmierzone 1:1, kadr segregatora obejrzany) ·
  stack==main diff czysty · paczki botów 1.2.1 NIETKNIĘTE.
- Zwiad wzorców: agenciai.pl = landing 1 szkolenia → osobna domena sprzedażowa
  claudedlafirm.pl (30k px, countdown, opinie) — model NIE nasz; podebrane:
  kroki uruchomienia, konkrety techniczne, FAQ listy.

## ✅ ZAMKNIĘTE 19.08 (noc) — ⭐ K6 INSTALACJA OD ZERA (przyciągnięte z 24.08), sklep 0.8.2
- **NIE-autor = niezależna sesja-tester** (Sonnet, tmux, deny na kod/warsztat —
  decyzja właściciela: „tester niezależny przez tmux jak buildery"). Pełna ścieżka
  ZALICZONA: instalacja→Stripe→produkty→zakup→boty→deinstalacja; czas 26:58
  brutto / 15-20 min netto (kryterium #1 ≤30 min ✓). Raporty:
  audyt/K6-INSTALACJA-OD-ZERA.md + K6-RAPORT-TESTERA.md (verbatim).
- **4 znaleziska naprawione w PR k6/naprawy-po-tescie:** 🔴 permalinki „Zwykłe"
  na świeżym WP (Krok 0 instrukcji + notice 0.8.2, zmierzony na żywo w obie
  strony) · 🟠 500 na checkout.session.completed = celowy RESUME (wyjaśnienie
  w instrukcji) · 🟡 „Zamówienia"→„Pulpit" · 🔴 boty zostają w bazie po
  deinstalacji wg instrukcji (10 tabel — pomiar zewnętrzny; sekcja
  Odinstalowanie uzupełniona o osobne czyszczenie botów).

## ✅ ZAMKNIĘTE 19.08 (dzień) — DROBNICA recenzji #40/#42 (PR #57)
- **Rate-limit sklepu → LAST_INSERT_ID** (#40): osobny SELECT licznik miał
  mikro-okno wyścigu → wzorzec 1:1 z chatl_rate_limit; komentarz ostrzegawczy
  o fail-open dołożony (uwaga recenzji #57).
- **Test dziennika/opóźnienia** (#42): `testy/dziennik-test.js` na żywym stacku —
  salwa/2^n/reset/retencja/konkrety S-09, próba kontrolna (próg 999 → pada).
- **Indeks okno_do chatbota** nazwany w PONAD-SPECYFIKACJE-BOTY (klasa #6).
- **Zipy produktów na stacku podmienione na 1.2.1** przez PRAWDZIWY CRUD panelu
  (sha bazy == sha zipów, zmierzone; snapshot zam#29 nietknięty — stare zamówienia
  trzymają swój plik, zgodnie z projektem).

## ✅ ZAMKNIĘTE 19.08 (dzień) — ⭐ PAKIET BOTÓW (D25, przyciągnięty z 23.08), boty 1.2.1
- **phpcs na WSZYSTKIE 3 wtyczki:** phpcbf (58 auto-napraw, diff obejrzany — zero zmian
  semantyki) + 3 adnotacje inline z uzasadnieniem + `customSanitizingFunctions`
  (chatl_waliduj_sesje) + sniff doradczy DirectDatabaseQuery wyłączony dla botów JEDNĄ
  deklaracją w ruleset (uzupełnienie D25 — boty w 100% na własnych tabelach; PreparedSQL
  aktywny wszędzie). Zakres bramki potwierdzony PRÓBĄ KONTROLNĄ (podłożony var_dump
  w bocie = czerwone). phpcs EXIT 0, phpstan EXIT 0.
- **Widget chatbota przemalowany na paletę strony** (top-uwaga sędziego K5): limonka
  #bfff38 / tusz #08090b / węgiel #101116, dymki 1:1 z mockupem strony produktu,
  hover-pierścień jak CTA sklepu, obwódka bąbelka pod WCAG 1.4.11 na jasnych stronach;
  zrzuty na stacku gospodarza ORAZ na czystym WP; kolory zmierzone z żywego DOM.
- **WROGA SESJA BOTÓW na czystym WP** (audyt/WROGA-SESJA-BOTY.md): aktywacja bez
  jednego notice (WP_DEBUG_LOG), seedy komplet, cykl deakt→akt bez strat, przebieg
  segregatora działa, pełne czyszczenie = zero tabel/opcji/cronów. 🔴→✅ ZNALEZISKO
  klasy #1 ściągi: klucz API zostawał w bazie przy DOMYŚLNYM uninstallu — naprawione
  (delete przed early-return, oba boty), zmierzone przed/po, INSTRUKCJE dok=kod,
  paczki 1.2.1 przebudowane + powtórka weryfikacji `diff -r` (zip == repo).

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5 WITRYNA KOMPLET, 3 PR-y (#51-#53), wersja 0.8.1
- **#51 fundament+lista** (wyspa z tokenami zmierzonymi z motywu; mono-etykiety,
  pille, baner-pigułka; H1 nasz — motyw nie renderuje własnego, zmierzone) ·
  **#52 strona produktu+SEO** (jedna strona WP + query var; rewrite ze sluga
  z opcji; sekcje §2B komplet: mockup czatu/PRZED-PO/kroki/dowody/uczciwość/FAQ;
  SEO: title/desc/canonical/OG/JSON-LD Product+Breadcrumb+FAQPage/okładki PNG/
  noindex-dziękujemy/sitemap provider [nazwa BEZ myślnika — regex rdzenia];
  hijack metadanych query varem znaleziony recenzją, zamknięty Z POMIAREM;
  PEŁNY przelot E2E po zmianie: mail_wyslany) · **#53 szlif po sędzi** (ikony
  SVG kart, jawne „nie zmyślamy recenzji", FAQ separatory, kropka-glow).
- **Sędzia wyglądu: 73/100 → 85/100** (dwa świeże przebiegi, przynęty rozbrojone
  w OBU; 2 zaniżenia rundy 2 = artefakty zestawu zrzutów, odrzucone z dowodem;
  PEŁNE werdykty obu rund: komentarze w PR #53 — rekomendacja recenzji #54).
- Cross-browser 6/6 (chromium+firefox, zero błędów konsoli) · zagadka sitemap
  ROZWIĄZANA: blog_public=0 wyłącza sitemapy w rdzeniu (nie usterka!) → wpis
  do instrukcji K8: na produkcji wyłączyć „Zniechęcaj wyszukiwarki".
- Na stacku doszedł REALNY produkt „Segregator maila" (zip 1.2.0) — sklep ma
  docelowe 2 produkty.

## ✅ ZAMKNIĘTE 19.08
- **Uwagi recenzji #49 → PR #50 MERGED (main=19dbcc4, 0.6.1):** dok=kod (.bin
  usunięte + sweep całe repo: seed-demo, adnotacja hist. w K2-WROGA-SESJA) ·
  wynik $wpdb->insert/update sprawdzany + sierota sprzątana (dowód na żywo:
  wymuszona awaria INSERT → błąd, nie fałszywy sukces; plików 4→4) · kryterium #6
  W TEŚCIE produkty-test.js (9/9, z próbą kontrolną „edycja zaszła") · ścieżka
  zrzutu przez env. Recenzent potwierdził wszystko 2× + własny grep sweepu.
- **K5 plan wyglądu:** zwiad motywu (paleta/typografia/detale zmierzone) + zwiad
  wzorców stron produktowych (subagent, źródła w archiwum-procesu/PLAN-WYGLADU-K5.md §5) →
  `archiwum-procesu/PLAN-WYGLADU-K5.md` CZEKA NA KLEPNIĘCIE właściciela przed budową.

## ✅ ZAMKNIĘTE 18.08 (dzień)
- Model AI botów haiku-4-5 (właściciel) · timeout AI #37 · **oba boty scalone z haiku**
  (#36 chatbot, #37 segregator) · recenzent zna WP+ściągę+Handbook (#38) · hardening
  uninstall wg Handbook (#39) · chatbot atomowy rate-limit (#40) · **paczki 1.2.0
  zweryfikowane (haiku + finalny kod w zipie)** (#35). ZERO otwartych PR.
- **K2 wroga sesja sklepu — 7 ataków obronionych** (audyt/K2-WROGA-SESJA.md).

## ✅ ZAMKNIĘTE 18.08 (wieczór) — ⭐ K3 KOMPLET, 7 PR-ów (#41-#47), wersja 0.5.1
- **#41 bramki CI PHP** (php-lint 8.4 · phpcs WPCS[sklep, boty→23.08 D25] · phpstan L5
  wszystkie wtyczki · gitleaks; manifest +4; E2E przelot2.js uratowany do repo).
- **#42 dziennik logowań** (S-09; opóźnienie wykładnicze ZMIERZONE 0.3→2.3→4.3→8.3 s,
  reset po sukcesie; limit 6/min per IP PRZED sleep — salwa 12: 6×200+6×429 w 0.1 s;
  retencja 90 dni; schemat v3) · **#43 ustawienia** (Settings API; sekrety autoload=no
  TAKŻE przy update-in-place; uninstall kasuje sekrety ZAWSZE — klasa #1 ściągi;
  nadawca wokół naszego wp_mail) · **#44 dashboard** (KPI+delta %, rozkład, tabela,
  CSV, self-heal Z BUDŻETEM CZASU 8/15 s; suma słupków = KPI co do grosza) ·
  **#45 zamówienie** (mail+resend ze świeżym tokenem [stary 403/nowy 200],
  anonimizacja RODO — schemat v4) · **#46 fix ukrytej strony** (bug złapany ZRZUTEM —
  wp eval go nie widział) · **#47 szlif po sędzi wyglądu**.
- **Sędzia wyglądu #1: 66/100** (przynęty rozbrojone — wiarygodny); TOP-braki
  zaadresowane w #47 (pasek statusów, kolumna Akcje, notice, hierarchia KPI,
  zrzuty-dowody); świadome odstępstwa w WZORCE-DASHBOARD-K3 §8. Sędzia #2 w toku.
- Zrzuty panelu (10 szt.) ⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE; odtwarza je `testy/zrzuty-panelu.js` — skrypt jest w repo, więc dowód jest ODTWARZALNY, choć pliki przepadły.

## Blokuje wydanie
- (nic pilnego) — Live-smoke AI na żywym api.anthropic.com w ODWODZIE (~5$ za zgodą
  właściciela), gdyby próba 1 chciała żywe AI; egzamin trybem regułowym 0 zł.

## Nie zaczęte (kolejność planu)
- **Nazwany dług (recenzja #49, drobna):** przy podmianie zipa na produkcie BEZ
  zamówień stary plik zostaje w katalogu chronionym (nie szkodzi, nie wycieka —
  katalog deny; sprzątanie wymagałoby odróżniania „trzymany snapshotem" od
  „wolny"). Świadomie NIE sprzątamy w v1 → wpis do `PONAD-SPECYFIKACJE.md` w K8.
- **Świadomie NIE ruszone (recenzja #40, #3):** default `$prog` w `chatl_rate_limit`
  — sklep ma default 10, bot nie; jedyne wywołanie w bocie podaje oba argumenty,
  recenzent oznaczył jako nieblokujące. Zostaje bez zmian celowo.
- **Instrukcja wdrożenia (K8): wzmianka fail2ban/WAF** na wp-login.php przy wielu IP
  (D26 — rezydualne ryzyko poza wtyczką).
- K7 25.08 (audyt + mutanty + kalibracja) · K7b 26.08 · K8 27.08 = próba 1. (K6 ✅ 19.08)

## Stan maszyny
- `stripe listen` forwarduje na :8890/webhook (sekret z `/root/.claude/sekrety-sklep.env`).
  Start po padzie: `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- Builder1: tmux praca:1, worktree sklep-agentow-boty, stoi (boty domknięte).

## Odroczenia z #172 (24.08, runda 1 — świadome, nieblokujące)
- **readme.txt Segregatora, wiersz „Wymagania"** — dopisać `stream_socket_client` + OpenSSL
  (dla prawdziwej skrzynki IMAP; FAQ sklepu już to mówi). Paczka 1.3.3 jest WYDANA i pod
  zamkiem — poprawka wchodzi z NASTĘPNYM wydaniem bota, nie osobno.
  ✅ **ZAMKNIĘTE (potwierdzone 3.09 na `origin/main`):** `wtyczki/segregator-maila/readme.txt:85-86` mówi
  o `stream_socket_client` i rozszerzeniu OpenSSL, z ostrzeżeniem o najtańszych hostingach i wskazaniem
  wbudowanej diagnostyki; `wtyczki/segregator-maila/CHANGELOG.md:32-33` potwierdza wejście poprawki (⛔ do rundy 2 stał tu odsyłacz do `CHANGELOG.md` SKLEPU zamiast Segregatora; wskazywał wtedy
  sekcję „[0.9.29]” z wpisem o banerze trybu testowego. ⛔ Numer podaję w czasie PRZESZŁYM, bo
  scalenie `d253789` przesunęło tamten plik i dziś pod tym numerem stoi zupełnie inny wpis). Wpis wisiał otwarty po naprawie.
- **Bramka na LICZBY strony sprzedażowej** (`front-produkt.php`: „6 zrzutów", „10 par",
  „30 maili demo") — dziś prawdziwe (zmierzone w r1 #172), ale bez strażnika zgniją po cichu
  jak każda liczba; wzór: `dokumentacja-zgodnosc*.sh`.
- **Regulamin sklepu (strona DRAFT #120)**: dopisać gwarancję satysfakcji 14 dni (D31)
  i tryb wygaśnięcia aktualizacji przy zwrocie — przy finalizacji regulaminu w K8.

- ⛔ **SZEŚĆ PRÓB KONTROLNYCH NARZĘDZI PADA — dług sprzed PR #203, nie z niego.**
  ⭐ **TRZY POMIARY, KTÓRE ROZSTRZYGAJĄ:** przelot `d28c971` → 6 padających · `15540a9` → **5**
  (`przejecie` przeszła) · przelot na gałęzi celów → znowu **6** (`przejecie` znowu padła).
  Między żadnym z nich nie ruszyłam pliku chatbota. Liczba padających jest więc RUCHOMA,
  a log niesie zawsze stan z ostatniego przebiegu — nie traktuj żadnej z tych liczb jako stałej. Poniższy akapit opisuje pierwszy przelot; różnica między nimi jest sednem
  znaleziska o chwiejności.
  Pierwszy przelot, z commitu `d28c971`: uruchomionych 30,
  `EXIT≠0` w sześciu — `dowod-z-paczki` (EXIT=2, brak `boty/paczki/chatbot-live-1.5.0.zip`),
  `blad-klucza`, `nonce`, `ip-za-proxy`, `przejecie`, `pulapka`. Wszystkie sześć dotyczą
  **chatbota** — pięć ma `CEL=…/plugins/chatbot-live`, szósta wymaga starej paczki chatbota.
  Żadna nie tyka sklepu ani segregatora. [zawężone po recenzji #203 r2: wcześniej pisało tu
  „chatbota i segregatora”, co było nieścisłe]
  ⭐ POMIAR, NIE PRZYPUSZCZENIE: te same dwa strażniki (`blad-klucza`, `nonce`) uruchomione
  w osobnym drzewie roboczym na `origin/main` padają IDENTYCZNIE (`EXIT=1`, komunikat
  „czysty kod NIE przechodzi — strażnik pada zawsze, więc nic nie znaczy”). PR #203 nie zmienia
  ani jednego pliku botów — `git diff --stat origin/main...HEAD -- wtyczki/chatbot-live/ wtyczki/segregator-maila/ boty/`
  jest pusty.
  ⛔ Dwa z nich (`blad-klucza`, `nonce`) to nie ślepota na część wad, tylko **strażnik, który
  pada ZAWSZE** — czyli nie odróżnia kodu z wadą od kodu bez wady i jako bramka nie znaczy nic.
  ⛔ Skutek (recenzja #210): **przez ten dług zabezpieczenie nonce i obsługa błędu klucza w chatbocie są dziś NIEPILNOWANE** — regresji w nich nie złapie żadna bramka. To dług bezpieczeństwa, nie tylko padająca próba.
  To pilne, ale to teren botów (chatbot ma świadomie otwartą klasę 8) — do rozstrzygnięcia
  osobno, nie w PR-ze o przelocie sklepu.
  ⭐ **JEDNA Z NICH JEST CHWIEJNA — zmierzone dwoma przelotami tej samej gałęzi.**
  Przelot z `d28c971`: 6 z EXIT≠0. Przelot z `15540a9`: **5** — `przejecie-proba-kontrolna.sh`
  przeszła, choć między tymi commitami nie ruszyłam ani jednego pliku chatbota (zmiany to
  komentarze, dokumentacja i test sklepu). Czyli jej wynik zależy od stanu wspólnego stosu,
  a nie od kodu, który ma pilnować.
  ⛔ Chwiejny strażnik jest GORSZY od stale czerwonego: stale czerwony zmusza do naprawy,
  chwiejny uczy, żeby uruchomić ponownie i pojechać dalej na zielonym z przypadku.
  Skutek dla domknięcia: `k7-koniec.sh` kontrola 6 zostaje czerwona niezależnie od tego PR-a.

- ⛔ **DWA POLA `required` W PANELU WŁAŚCICIELA NADAL BEZ POLSKIEGO KOMUNIKATU — świadomie nie objęte przez PR #203.**
  `wtyczki/sklep-agentow/includes/admin-produkty.php:168` (Nazwa) i `:171` (Cena) stoją na samym
  `required`, więc puste pole odpowie właścicielowi dymkiem przeglądarki — tym samym, który
  PR #203 usunął z frontu. Naprawa tam nie sięga **z definicji**: `assets/front-ruch.js` ładuje się
  wyłącznie na stronach sklepu (`sklep-agentow.php:118-149`, `is_page( $strona_sklep ) || is_page( $strona_dziekujemy )`),
  a ekrany `wp-admin` żadnego z tych skryptów nie dostają.
  **Dlaczego odłożone, a nie naprawione:** to tekst dla WŁAŚCICIELA (jedna osoba, która i tak zna
  swój sklep), nie dla kupującego. Nasza reguła każe zawsze wracać do tekstu czytanego przez
  KLIENTA — ten nim nie jest. Objęcie panelu wymaga osobnej kolejki assetów dla `wp-admin`,
  czyli zmiany szerszej niż PR o przelocie.
  **Ile naprawdę liczy ta klasa** (wskazane w recenzji #203 r3, przemiot poprawiony po r4):
  `admin-produkty.php:168` (`required`), `:171` (`required` + `type="number"` + `min` + `pattern` — `pattern` dopisany po recenzji #203 r5, wcześniej go gubiłam),
  `admin-ustawienia.php:249` i `:254` (`type="email"`), `:259` (`pattern`).
  Czyli **pięć miejsc, nie dwa** — samo `required` to dopiero część.
  ⛔ We wcześniejszej wersji tego wpisu liczyłam sześć, bo doliczyłam `admin-produkty.php:158`
  — a to KOMENTARZ o polu, nie pole. Sprawdzone `sed -n 158p`. Ta sama klasa, na którą
  łapię się w tym audycie: numer linii przepisany bez otwarcia linii.
  **Co trzeba zrobić, gdy wróci:** wpiąć te same stałe `SKLEPAG_BLAD_*` przez atrybut
  `data-sklepag-blad` i dołożyć tę samą procedurę do assetów panelu. ⛔ To zamknie WYŁĄCZNIE
  brak wartości: procedura ustawia tekst pod warunkiem `pole.validity.valueMissing`, więc
  `typeMismatch` (`type="email"`) i `patternMismatch` (`pattern`) nadal rysowałaby przeglądarka.
  Na froncie to bez znaczenia — cztery objęte pola mogą naruszyć wyłącznie `valueMissing`.
  Zgłoszone przez recenzenta w #203 r2 jako DROBNA 2, przyjęte jako dług z nazwą.

- ✅⭐ **KONTROLA 2 `k7-koniec.sh` ZAMKNIĘTA 4.09 — obie soczewki mają dowód czułości z uruchomienia.**
  ⛔ **Zapis decyzji z 28.08 zostaje niżej w całości — NIE kasuję go, zmieniam werdykt i dopisuję powód.**
  Co się zmieniło: obawa, która stała za tamtą decyzją („powtarzanie kalibracji do skutku = uczenie
  audytora pod mutanta”), została ZMIERZONA i się nie ziściła. Poprawka, na której zdał `L1`, weszła
  26.08 (`d314d7c`) z wniosków rundy A — przed rundą B i bez wzmianki o tym mutancie; przy `L5`
  naprawiono MECHANIZM raportu, a nie dołożono wzorca pod `HEAD`. Kontrola odwrotna zamknięta:
  na CZYSTYM kodzie obie soczewki milczą. Wyniki: `L1`/M1 **PASS** (próba 3), `L5`/M6 **PASS** (próba 4),
  0 fałszywych alarmów na `F1–F4`. Raporty: `audyt/sklep/raporty/weryfikacja/`.
  ⭐ Limit powtórek zmieniony wpisem **`D39`** (2 → 4, każda powyżej drugiej z obowiązkowym POWODEM;
  zaślepka = brak powodu; powyżej 4 bramka pada bezwarunkowo) — **decyzja właściciela 4.09**, po
  przedstawieniu pomiaru i obu dróg z ceną. Reguła jest OSTRZEJSZA niż `D33` w jednym wymiarze:
  dotąd nikt nie musiał uzasadnić ANI JEDNEJ powtórki.

- 📜 **ZAPIS HISTORYCZNY — stan i decyzja z 28.08, nieaktualne od 4.09 (patrz wyżej i `D39`):**
  Dwie soczewki, `L1` i `L5`, mają w `ROZLICZENIE-MUTANTOW-SKLEP.md` werdykt **FAIL — NIEKALIBROWANA**,
  każda po **dwóch** próbach. `L1` nie złapał M1 (nieudane logowanie na NIEISTNIEJĄCY login nie trafia
  do dziennika), `L5` nie złapał M6 (`HEAD` zużywa slot pobrania, wbrew zamówieniu i readme).
  ⛔ **Trzeciej próby NIE MA i nie będzie** [stan na 28.08 — zmienione 4.09 wpisem `D39`] — zasada zapisana w tej samej tabeli brzmi: „jedna poprawka
  promptu/materiału i jeden ponowny przebieg (`proby` ≤ 2); drugi FAIL = soczewka NIEKALIBROWANA”.
  Bramka osobno sprawdza `proby ≤ 2`, więc trzecie podejście i tak by ją zapaliło. Reguła dwóch prób
  istnieje po to, żeby nie kręcić promptem aż audytor trafi przypadkiem — wtedy kalibracja przestaje
  cokolwiek mierzyć.

  ⭐ **ROZJAZD NARZĘDZIA Z RUBRYKĄ KLIENTA — i to jest sedno tej decyzji.**
  Kryterium GOTOWE #12 (`PLAN-PROJEKTU.md` §12) wymaga kalibracji **≥ 2/3 mutantów**. Policzone
  z tabeli rozliczenia: mutantów prawdziwych 7 (M1–M7), złapanych 5 (M2, M3, M4, M5, M7) = **71 %**,
  przy 0 fałszywych alarmach na F1–F4. **Kryterium klienta jest SPEŁNIONE.**
  `k7-koniec.sh` warunek 2 jest OSTRZEJSZY: żąda, żeby KAŻDA soczewka miała PASS. Bramka i rubryka
  mierzą więc dwie różne rzeczy, a bramka jest surowsza od tego, co obiecaliśmy.

  **DECYZJA (właściciel, 28.08): oddajemy z jawnym zapisem.** Odrzucone świadomie wtedy
  (⛔ **pierwsze z tych dwóch cofnięte 4.09 wpisem `D39`, drugie NADAL obowiązuje** — warunku 2
  nie poluzowano do „≥ 2/3”, przeciwnie: doszedł wymóg powodu przy każdej powtórce):
  – trzecia próba dla `L1`/`L5` → łamie własną regułę, psuje sens kalibracji;
  – poluzowanie warunku 2 w `k7-koniec.sh` do „≥ 2/3” → obrona robiona na narzędziu w dniu oddania,
    czyli dokładnie wtedy, kiedy najłatwiej sobie coś przyklepać.

  **CO TO ZNACZY DLA KLIENTA, po ludzku:** audyt sprawdził sklep siedmioma niezależnymi soczewkami.
  Pięć z nich udowodniło na podłożonych błędach, że naprawdę widzą wady. Dwie tego nie udowodniły —
  więc **to, czego nie znalazły w obszarze dziennika logowań (`L1`) i zgodności dokumentacji
  z zachowaniem (`L5`), nie jest dowodem, że tam nic nie ma.** Ich „czysto” waży mniej niż pozostałych.
  ⛔ To zdanie ma trafić do raportu oddania. Nie wolno go zastąpić zdaniem „audyt przeszedł”.

  **CO ZAMYKA TĘ POZYCJĘ, gdy wróci:** przepisać prompty `L1` i `L5` wg wniosków z rundy A
  (`L1`: ścieżka WROGA, nie szczęśliwa — logowanie na nieistniejący login; `L5`: obietnica behawioralna
  = wykonanie, nie grep stałej), zbudować NOWY bank mutantów i przebiec kalibrację od zera —
  jako osobne zadanie z własnym licznikiem prób, nie jako trzecią próbę tej samej.

## 🟡 TRZECI NO-GO w `k7-koniec` (kontrola 3d) — ZAMIERZONY, nie regresja (28.08)
`SK-L6-003` ma status `ZAMKNIETE` i **nie ma dowodu kalibracji** — kolumna
`straznik_padl_na_starym` niesie `—` zamiast SHA. To jest poprawka, nie usterka: wcześniej stał tam
`b7fe60e`, a teza tego samego wiersza mówiła, że na `b7fe60e` przyrząd **nie padał** (po cichu
uruchamiał chromium i kończył zerem). Kalibracja jest tu **niewykonalna z natury** — znaleziskiem
był BRAK pomiaru, nie wada produktu, więc nie istnieje commit, na którym strażnik mógłby paść.
⛔ **Odrzucone świadomie:** dorobienie bramce wyjątku na wzór `WYJATKI-WARUNEK-8.txt`. Ten sam ruch
odrzuciliśmy w `#205` przy kalibracji jako obronę robioną na narzędziu w dniu oddania.
**CO ZAMYKA:** albo bramka dostanie jawny rejestr wyjątków (osobny PR, poza dniem oddania), albo
rejestr dostanie czwarty stan dla znalezisk typu „brak pomiaru”, których nie da się skalibrować.

## 🟡 DŁUG Z RECENZJI `#206` i `#207` — drobne, nie zmieniają tego, co dostaje klient (28.08)
- **`#206` DROBNA 6** — kalibracja walidacji nazwy silnika była zadeklarowana, nie pokazana.
  ✅ ZAMKNIĘTE w `#207`: mutant odtwarzający starą logikę (`msedge` → chromium) kończy `EXIT=2`,
  bez mutanta `EXIT=0`.
- **`#206` DROBNA 4** — strażnik z rejestru nie był uruchamiany przez nic. ✅ ZAMKNIĘTE: sędzia
  `testy/rodo-anonimizacja-pomiar-skutku-test.sh` leży w `testy/`, a `narzedzia-qa/linia-bazowa.sh`
  zbiera `testy/*.sh`. Kroki pomocnicze przeniesione do `testy/kroki-rodo/`, żeby linia bazowa
  nie uruchamiała ich osobno (bez sędziego nie sprzątają po sobie).
- ⬜ **`#207` WAŻNA 4, druga połowa** — asercja zgodności kodu ze stosem stoi w teście RODO,
  ale POZOSTAŁE testy na żywym stosie jej nie mają. Do przeglądu przy następnej rundzie QA:
  każdy test mierzący stos powinien najpierw dowieść, że mierzy TEN kod, który jest w repo.
- ✅ ZAMKNIĘTE 31.08 (0.9.28, #234 r5): **`#207` DROBNA 5** — `mail_tresc` w anonimizacji
  rozstrzygnięte w OBIE strony naraz: pole wypadło z SELECT-a na wejściu (`admin-zamowienie.php:264`)
  ORAZ zostało objęte anonimizacją (`mail_tresc=NULL` w UPDATE :374 + człon w pomiarze skutku).
  Teza „dziś nieszkodliwe" obalona w r5 #234: treść niosła pełną kopię danych do faktury
  (nazwa firmy, NIP, adres). Dowód: #234 r5 (naprawa w squashu `78e53cf` na main) + strażnik
  `testy/rodo-anonimizacja-pomiar-skutku-test.sh` (przypadek z fakturą w treści maila,
  kalibrowany na kodzie sprzed naprawy: FAIL dokładnie na nowej asercji).
- ⬜ **Strażnik renderu panelu (r6 #234) mierzy tylko przypadek Z wadą** — obie asercje wiszą
  na renderze zamówienia ZANONIMIZOWANEGO; wykreślenie gałęzi „niedomknięte" dla wszystkich
  zamówień zostawiłoby strażnika zielonym (zasada „test sprawdza też przypadek BEZ wady",
  r7 #234 DROBNA 2). **CO ZAMYKA:** drugi render w kroku — zamówienie NIEzanonimizowane
  z pustym `mail_tresc` oczekuje frazy o rdzeniu „niedomkni".
- ⬜ **Siódmy migoczący strażnik chatbota: `testy/statystyki-proba-kontrolna.sh`.** Kończy `EXIT=1`
  z komunikatem „TEST SLEPY NA 1 PODLOZONYCH BLEDOW” — nie łapie wady „wiersze bez znacznika wliczone
  do skuteczności” w raporcie skuteczności chatbota. ⛔ Zmierzone 28.08 dowodem przez wykluczenie:
  pada **także na czystym `origin/main`** (`EXIT=1`), a PR-y sklepu nie ruszały ani jednego pliku
  chatbota (`git diff --stat origin/main..HEAD -- wtyczki/chatbot-live/` → pusto). Migocze między
  przelotami (raz jest w szóstce, raz w siódemce), stąd rozbieżne liczby w logach — **nie jest to
  regresja żadnego PR-a sklepu**. **CO ZAMYKA:** naprawa razem z pozostałymi strażnikami chatbota
  — to jeden teren, jedna robota, nie sześć osobnych.
- ⬜ **BLIŹNIAK klasy „zapisujemy skutek, nie sprawdziwszy, czy operacja się udała" —
  ścieżka ponownej wysyłki maila** (`wtyczki/sklep-agentow/includes/admin-zamowienie.php`,
  `sklepag_obsluz_resend()`). Zmierzone 28.08 (recenzja `#207`, runda 3) jako sześć wywołań;
  po 0.9.28 (#234, wyliczenie skorygowane w r7) zostało **pięć** `$wpdb->query()` bez
  przechwyconego WYNIKU zapisu — linie 104, 130, 179, 194, 196: rotacja tokenu przed
  wysyłką (:104), status po sukcesie ze śladem `dor_*` (:130), status `mail_blad`
  (:179 — CAS własności siedzi w klauzuli WHERE, co NIE jest sprawdzeniem wyniku zapisu),
  przywrót treści maila (:194) i skrótu opinii (:196). Spłacona część: kompensacja
  mierzona przez `$przywrocono`.
  DRUGI CZŁON tego samego długu (kod przypisuje go wprost: `admin-zamowienie.php:33-36`;
  r1 #235): kompensacja licznika pobrań po nieudanej wysyłce przywraca `pobrania_uzyte`
  ze ZDJĘCIA `$zam` z wejścia (`:142`, `$zrodlo_uzyte`), nie ze stanu bazy — pobranie
  wykonane w oknie między odczytem a zapisem ginie z licznika.
  ⛔ To ta sama klasa, którą `#234` naprawił w anonimizacji — czyli klasa 8 briefingu (naprawa
  egzemplarza zamiast klasy). Nazwana w kodzie, przy funkcji.
  **CO ZAMYKA:** przenieść wzorzec z anonimizacji — pomiar STANU po zapisie zamiast kodu powrotu —
  wraz z testem podkładającym awarię każdego z pięciu zapisów osobno, ORAZ odczyt
  `pobrania_uzyte` ze stanu bazy w chwili kompensacji (nie ze zdjęcia wejścia) z własnym
  przypadkiem testowym „pobranie w oknie kompensacji". Osobna robota, bo to inna
  ścieżka: ponowna wysyłka i kompensacja po nieudanym mailu mają własne przypadki brzegowe.
- ✅ **ZAMKNIĘTE 1.09 (P-4).** ~~Adres z `/` w części lokalnej zostaje w dzienniku mimo
  potwierdzenia usunięcia.~~ `wp_json_encode` bez flag zapisuje ukośnik jako `\/`, a anonimizacja
  szukała SUROWEJ postaci adresu (`opis LIKE %adres%`), więc ślad zostawał, a wtyczka
  potwierdzała usunięcie. Nazwane świadomie w recenzji `#207` r7.
  **JAK ZAMKNIĘTE:** `sklepag_postacie_w_dzienniku()` oddaje wszystkie postacie danej (surową
  i tę z `wp_json_encode`, braną Z TEJ SAMEJ funkcji, która ją tworzy); `sklepag_wzorce_w_dzienniku()`
  i `sklepag_kandydaci_z_dziennika()` są JEDNYM źródłem dla czyszczenia **i** pomiaru — dotąd
  pomiar miał własne, równie ślepe zapytanie i z definicji nie mógł tego zobaczyć.
  Kalibracja: wada odtworzona na stosie prawdziwym zamówieniem (wiersz `eksport_csv` przeżywał
  anonimizację przy `ok=TRUE`), zasiew strażnika składa opis TĄ SAMĄ funkcją co produkcja
  (dotąd podstawiał adres surowy w ręcznie sklejony JSON i dlatego różnicy nie widział),
  próba kontrolna `testy/rodo-anonimizacja-postacie-proba-kontrolna.sh` — trzy mutanty.
  ⚠️ **Czego to NIE zamyka (zmierzone przy okazji):** w dzienniku zostają adresy IP
  (`pobranie`, `webhook_zly_podpis`) — anonimizacja ich nie szuka, ogranicza je wyłącznie
  retencja dziennika. Osobna pozycja, jeśli właściciel uzna ją za potrzebną.

---

## 🟡 SKANER ŚLADÓW MIJA WZORZEC PRZEZ WIELKOŚĆ LITERY (29.08, recenzja `#209` r6 i r8)

⛔ **Ten wpis powstał z opóźnieniem i to jest część lekcji.** W opisie PR-a rundy 7 napisałam,
że ten dług „zostaje nazwany w `CO-WISI.md`" — a nie było go tutaj. Recenzja r8 sprawdziła to
komendą i wykazała: `grep -c 'wielkość litery' audyt/CO-WISI.md` → **0**. Czyli odhaczyłam
w liście kontrolnej rzecz, której nie zrobiłam. Klasa 3 zastosowana do **własnego opisu**.

**Wada:** `narzedzia-qa/slady-wewnetrzne.sh` zna wzorzec „próba kontrolna", ale mija go, gdy
w tekście stoi z wielkiej litery (`Próba kontrolna`). Egzemplarz w kodzie klienta
(`sklep-agentow/includes/aktywacja.php:230`) usunięty w `#209`; **sam wzorzec nadal jest ślepy**.

**CO ZAMYKA:** dopisać `-i` albo jawne warianty wielkości liter do tej rodziny wzorców
i dołożyć przypadek do próby kontrolnej, który podkłada ślad **z wielkiej litery** — inaczej
próba przechodzi, bo mierzy wyłącznie wariant z małej. To zmiana narzędzia QA, nie produktu.

⛔ **Rodzina pokrewna, wykazana w r8 i szersza niż ta:** odsyłacze do naszych dokumentów
zapisane **słowem, nie oznaczeniem** — `plan v3 par. 4A`, `(Z10)`, `patrz Z0`, `Wymóg z briefu`.
Żaden z trzech wzorców z tego pliku ich nie widzi, bo wszystkie szukają `§`, `K[0-9]`, `D2[0-9]`.
Egzemplarze usunięte w `#209`; wzorce nadal ich nie znają. ⭐ To potwierdza zasadę zapisaną
wyżej: **wzorzec jest siatką na regresję, nie dowodem czystości** — dla plików, które da się
przeczytać, miarą jest przeczytanie.

## 🟡 PLUGIN CHECK NA PACZKACH (30.08) — jedno znalezisko w kodzie, reszta poza zakresem wtyczki dla klienta

Raport i triage: `audyt/PLUGIN-CHECK-30-08.md` (surowe wyniki `audyt/plugin-check/`, narzędzie
`narzedzia-qa/plugin-check-paczki.sh`). 416 pozycji, z czego **jedna dotyczy kodu**:
- ⬜ **`wtyczki/sklep-agentow/includes/admin-opinie.php:16` i `:120`** — `esc_html__( 'Brak uprawnień.' )`
  bez domeny w wtyczce, która nie tłumaczy niczego (brak `Text Domain`, 0 innych `__`). Naprawa:
  `esc_html( 'Brak uprawnień.' )`. Zero wpływu na kupującego i właściciela — string jest po polsku
  i wyświetla się poprawnie. **Wchodzi z pierwszą następną zmianą kodu sklepu** (podbicie wersji,
  paczka, runda) — nie samo przed 1.09.
- Pozostałe: reguły katalogu WP.org (readme po angielsku, `.md` w korzeniu, prefiksy w `uninstall.php`),
  fałszywe alarmy na nazwach tabel oraz nasze własne sanitizery/brama nonce, których Plugin Check
  nie zna, a `phpcs.xml.dist` zna (D25). Świadomie NIE wyciszane — Plugin Check to raport, nie bramka.
- Plugin Check w CI: **świadomie nie** — wykonawca własny na 3,8 GB RAM, akcja stawia drugi WP.

## 🟡 INSTRUKCJA INSTALACJI BEZ ZRZUTÓW (30.08, recenzja PR `#217`)

`dokumentacja/INSTRUKCJA-INSTALACJI.md` jest tekstowa (0 obrazów; K8 planu żądał zrzutów maszynowych). Kroki nazywają ekrany
po nazwie, instrukcja OBSŁUGI sklepu ma 5 zrzutów. **Dług:** przebieg `testy/zrzuty-instrukcje.js` na czystym stosie → 4–6
zrzutów (wgranie wtyczki, aktywacja, Ustawienia sklepu, webhook) — po oddaniu, przy 0.9.25 (0.9.24 objęło tylko mail — RAPORT-ODDANIA §6).

## 🟡 PO K9 (instalacja z Release na motywie kolegi, 30.08) — trzy długi na 0.9.25 (NIE weszły do 0.9.24 — RAPORT-ODDANIA §6), `audyt/K9-INSTALACJA-Z-RELEASE-0.9.23.md`

1. `dokumentacja/INSTRUKCJA-INSTALACJI.md`, menu mobilne: „po pozycji 08” — w pliku FAQ ma „07” (opóźnienie 0,35 s); tester trafił,
   numeracja myli. Tekst dla klienta, ale paczka zbiorcza już w Release → poprawka = nowe wydanie (0.9.25), nie w miejscu.
2. Komunikat aktywacji „Brak katalogu chronionego — deaktywuj i aktywuj” nie nazywa przyczyny (uprawnienia `wp-content/uploads`);
   „Najczęstsze problemy” wiąże `chown` tylko z uploadem mediów. Dopisać przyczynę w komunikacie i w instrukcji.
3. Narzędzie: `narzedzia-qa/lib-stos-probny.sh` deklaruje `WP_DEBUG_LOG` przez `WORDPRESS_CONFIG_EXTRA`, obraz `wordpress:6.9.4` tego
   NIE wpisuje → „0 notice’ów” na tym stosie było zerem z martwego loggera (tester to wyłapał). Naprawa: `wp config set` po `postaw_stos`
   + próba kontrolna z podłożonym `trigger_error` (K9 zrobiła to ręcznie: 1 trafienie, potem 0 na 8 adresach).
   ⛔ Mechanizm, który to ukrył (recenzja `#218`): `log_bledow()` w tej bibliotece robi `cat debug.log 2>/dev/null … || true` —
   brak pliku i brak loggera dają TEN SAM pusty wynik co „zero błędów”, a konsumenci (`dowod-z-paczki.sh:171-177,195,249`,
   `dowod-aktualizacji.sh:228`, `dowod-zachowania-chatbot.sh:190,247,281`, `dowod-zachowania-segregator.sh:195,238`) piszą
   „(WP_DEBUG włączony)”, czego nie mierzą. Naprawa klasy: `log_bledow()` ma PADAĆ (exit 2), gdy `debug.log` nie istnieje lub
   `WP_DEBUG_LOG` nie jest zdefiniowany, a `postaw_stos` ma kończyć się podłożonym notice’em i `grep -c` = 1.

## 🟡 LISTA STATUSÓW ZAMKNIĘTYCH W CZTERECH SKŁADNIACH (30.08, `/code-review ultra` na `#213` — jedyne znalezisko, waga „nit”; 2.09 recenzja #268 r2: czwarta kopia)

⚠️ **2.09 (#268, Q-4):** czwarta kopia `array( 'zwrocone', 'sporne' )` w **bramie pobrań** (`includes/pobieranie.php`, warunek odmowy 410). Tu rozjazd kosztuje pieniądze wprost: nowy status zamknięty dopisany do stałej SQL, a zapomniany w bramie = wydanie pliku po zwrocie, czyli `Q-4` z powrotem. Dług bez zmian (jedna tablica jako źródło + strażnik porównujący), waga po tej kopii: nie „nit”, tylko do zrobienia PRZED wydaniem 1.0.

`'zwrocone','sporne'` żyje jako fragment SQL `SKLEPAG_STATUSY_ZAMKNIETE` (`wtyczki/sklep-agentow/sklep-agentow.php:44`), tablica PHP
w bramce 409 (`wtyczki/sklep-agentow/includes/admin-zamowienie.php:65`) i konkatenacja `NOT IN` (`:130`, `:182`); `'mail_wyslany','mail_blad'` — bramka 409 (`:69`)
i widoczność przycisku (`:356`). ⛔ Numery sprawdzone wykonaniem 2.09 (`grep -n`), poprzednie (63/117/154/67/504) wskazywały nieistniejące miejsca. Scenariusz: nowy status zamknięty dopisany do stałej SQL, zapomniany w tablicy → „Wyślij
ponownie” przechodzi mimo chargebacku. **Dług:** jedna tablica PHP jako źródło, fragment SQL budowany z niej (`implode`),
strażnik porównujący oba. Po oddaniu, przy 0.9.25. Zero blokerów w tej recenzji.

## 🟡 TRZY LICZBY BRAMKI KOŃCOWEJ ŻYJĄ W CZTERECH PLIKACH, PRZEPISANE RĘCZNIE (30.08, recenzja PR rubryki, D8)

`AUDYT-RAPORT.md` (korzeń, czyta go hook `oddanie-guard` spoza repo), `rubryka-GOTOWE.md`, `audyt/RAPORT-ODDANIA.md` §2 i `audyt/DO-ODDANIA.md`
niosą te same `KRYTYCZNE 0 · DUŻE 0 · FAIL 2 z 13` — nic w repo nie sprawdza, że się zgadzają z wyjściem `k7-koniec.sh`.
**Dług:** jedna kontrola w `narzedzia-qa/k7-koniec.sh` (porównanie nagłówka `AUDYT-RAPORT.md` z policzonymi liczbami,
FAIL przy rozjeździe) + próba kontrolna. Po oddaniu.

## 🟡 BRAMKA ODSYŁACZY STRONY TYTUŁOWEJ: NIEWPIĘTA, BEZ PRÓBY KONTROLNEJ, CZYTA TYLKO README (30.08, recenzja PR oddania r2–r3, W3–W5, D7–D8)

`testy/odsylacze-strony-tytulowej.sh` (36 celów po `#213`) nikt nie woła — do `BRAMKI=` nie wchodzi z zasady
manifestu (osobny PR), więc uruchamiać ręcznie przed wydaniem. Nie ma próby kontrolnej (`*-proba-kontrolna.sh`,
przelot znajduje je po nazwie) — kalibracja „pada 5/36 bez #213” zniknęła ze scaleniem. Czyta treść tylko `README.md`;
`dla-klienta/PRZECZYTAJ-MNIE.txt` sprawdza wyłącznie istnieniem. **Dług:** próba kontrolna (podłożony martwy odsyłacz
→ EXIT=1), pętla po treści PRZECZYTAJ-MNIE, wpięcie przez manifest. Osobno: trzy PNG w `dokumentacja/` są generowane
(`render-diagramy.js`) i nic nie sprawdza zgodności ze źródłem SVG (klasa 10); liczba kontroli w komunikacie
`pakuj-dla-klienta.sh` wpisana ręcznie, nie policzona. Wszystko po oddaniu — narzędzia, nie produkt.

## ✅ ZAMKNIĘTE: kompensacja tokenu w cronie (30.08 r6 W1 → #228: kompensacja + CZĘŚĆ 4; 0.9.28: klasa domknięta śladem `dor_*`, CZĘŚĆ 6)

## 🟡 SEKCJA F MIERZY PIĘĆ Z DZIEWIĘCIU LICZB INSTRUKCJI + ZRZUTY Z OBCYM MENU (30.08, recenzja PR instrukcji r2, WAŻNA 3 + DROBNA 5)

`testy/dokumentacja-zgodnosc-sklep.sh` §F pilnuje 72 h / 10 pobrań / 30 dni / 20 MB / co 10 min; NIE mierzy
90 dni retencji, progu 7 prób logowania, 3 prób maila, minuty odmowy i „pięciu stron” menu. Nazwa bramki
zawężona do tego, co mierzy (klasa 11). Dwie z niemierzonych liczb były nieprawdziwe w tekście i przeszły
z EXIT=0 — naprawione w tekście ręcznie. **Dług:** cztery asercje (`90`, `6` w `sklepag_rate_limit`, `3`, `60`)
+ przypadki do próby kontrolnej — po oddaniu, wzorzec `stala`/`grep -oP` gotowy w §F.
Osobno: pięć PNG w paczce pokazuje menu z **ChatBot LIVE** i **Segregator maila** oraz plakietkę Plugins ① —
u klienta z samym sklepem tych pozycji nie będzie. Nie blokuje; przy następnym `testy/zrzuty-panelu.js`
robić zrzuty na stosie z JEDNĄ wtyczką.

## 🟡 ZRZUTY PNG W PACZCE SĄ POZA SKANEREM ŚLADÓW (30.08, recenzja PR o instrukcji sklepu, WAŻNA)

`narzedzia-qa/paczka-zgodnosc.sh` melduje „w 66 plikach paczki nie ma śladów wewnętrznych”, ale pięć
z nich to PNG — skaner tekstowy nie czyta obrazów, więc nazwa kontroli jest szersza niż jej zasięg
(klasa 5 i klasa 11 naraz). Zrzuty z 30.08 były robione z widocznym kontem `audytor-admin`, osią czasu
z `admin_roboczy` i adresem sieci dockera; naprawione w `testy/zrzuty-panelu.js` (pasek admina bez
tożsamości, front w niezalogowanym kontekście, kadr bez osi czasu) i sprawdzone OCZAMI.
**Dług:** jedyną kontrolą obrazów jest człowiek. Ewentualny automat: OCR (tesseract) na PNG z paczki
z tą samą listą wzorców co skaner — po oddaniu, jeśli zrzutów przybędzie.

## 🟡 MAIL PO ZAKUPIE — DŁUGI PO 0.9.24 (30.08, po audycie maila i przebudowie na HTML)

- **Metoda płatności w mailu** („Visa •••• 4242” — standard Stripe/Apple): sesja Checkout nie niesie
  marki i końcówki karty; trzeba dociągnąć `payment_intent` → `payment_method` (1 zapytanie do API
  na zamówienie, w webhooku). Wraca, gdy będzie decyzja o kolejnej wersji.
- **Logo obrazkiem w nagłówku maila**: dziś nazwa nadawcy tekstem (D34). Gdy właściciel ustawi
  `custom_logo` w motywie — czytać `get_custom_logo` z fallbackiem na tekst; motyw kolegi go nie ma.
- **Zrzut `zrzuty/ustawienia.png` w INSTRUKCJA.md** pokazuje Ustawienia BEZ sekcji „Dane sprzedawcy”
  (0.9.23). Odświeżyć maszynowo przy następnej rundzie zrzutów.
- **Stanowisko `:8892` dostało mu-plugin `mailpit-smtp.php`** (kopia z `:8890`) — bez niego
  `wp_mail` na stosie próbnym pada „Could not instantiate mail function” i E2E kończy się
  `mail_blad`, choć kod jest dobry. Do `lib-stos-probny.sh` dopisać ten krok (dług narzędzia).

## 🟡 DŁUGI Z RECENZJI #220 RUNDA 3 (30.08, po scaleniu 0.9.24 — 0 WAŻNYCH, 3 DROBNE, reguła rund: produkt czysty → dług)

- **CHANGELOG 0.9.24 mówi „25 sprawdzeń” strażnika maila, test ma 26** (asercja C2b doszła w rundzie 1, liczba
  w CHANGELOG-u została; klasa 3). Naprawa = jedna liczba w `wtyczki/sklep-agentow/CHANGELOG.md` — ale CHANGELOG
  jest w zipie, więc zmiana = nowy zip i nowa wersja. Wchodzi do następnej wersji (0.9.25), nie osobno.
- **Sanityzacja adresu firmy liczy `\r\n` inaczej niż `maxlength` w polu i nie zwija pustych wierszy**, choć oba
  miejsca odbierające wartość (`sklepag_stopka_sprzedawcy_tekst`, `mail-html.php`) normalizują `\r\n`.
  Skutek: adres o 300 znakach z przeglądarki może dostać błąd „301 znaków” po stronie serwera. Naprawa:
  normalizować `\r\n → \n` PRZED liczeniem w `sklepag_sanityzuj_adres_firmy()`.
- **Sprzątanie strażnika maila w shutdown obejmuje 3 z 4 opcji** — `sklepag_email_kontaktowy` jest przywracany
  z `$poprzedni` (`get_option(…, '')`), więc gdy opcji nie było, zostaje PUSTA zamiast skasowana (klasa 8).
  Naprawa: `get_option(…, false)` + `delete_option` jak przy trzech pozostałych.

## 🟡 0.9.25 — PŁATNOŚĆ OSADZONA I DANE DO FAKTURY: długi (30.08 wieczór)

- **Font w ramce Stripe = systemowy** (Geist tylko jeśli klient ma go w systemie). Appearance API bierze własny font
  wyłącznie z publicznego URL-a CSS (`fonts.cssSrc`); motyw kolegi trzyma Geist lokalnie jako woff2 bez pliku CSS
  z `@font-face` pod publicznym adresem. Naprawa: wystawić `assets/fonty.css` z `@font-face` i podać `cssSrc` (+ CSP `connect-src`).
- **E2E płatności osadzonej** (`testy/e2e/przelot-osadzony.js`: ramki Payment Element na NASZEJ stronie) — do napisania
  i uruchomienia po otrzymaniu klucza publikowalnego; do tego czasu dowód = próby serwera (JSON `client_secret`,
  `ui_mode=elements`, metadata) + E2E ścieżki zapasowej z danymi faktury.
- **Ewidencja pod zwolnienie z kasy fiskalnej** (zwiad prawny 30.08, rozporządzenie o zwolnieniach z kas, poz. 37):
  płatność online przez operatora liczy się jako „na rachunek”, o ile z ewidencji jednoznacznie wynika, jakiej
  czynności dotyczy — panel ma numer zamówienia, kwotę, sesję Stripe. Do „Test a produkcja” w instrukcji: zdanie
  dla właściciela, żeby to potwierdził ze swoją księgową (sklep nie jest kasą i nie zastępuje ewidencji).
- **Retencja danych do faktury**: obowiązek 5-letni dotyczy FAKTURY u sprzedawcy (jego księgowość), nie kopii w sklepie —
  anonimizacja w sklepie czyści `dane_faktury` razem z e-mailem. Zapisać w INSTRUKCJA §6 przy następnej korekcie
  (dziś: §9 FAQ).

## 🟡 K10 (30.08, tester niezależny, instalacja z Release 0.9.24 na czystym WP 6.9.4 + motyw kolegi) — co wyszło
Raport (oryginał): `/root/praca/k7-dowody-30-08/k10-tester-0.9.24/RAPORT-TESTERA.md` (kopia dla właściciela: `/root/do-panelu/K10-0.9.24-tester/`).
26 min zegara (≈15 min czystej pracy z instrukcją — w limicie 30), 22 ✅ · 11 🐞 · 6 😖 · 6 ⬜.
- **NAPRAWIONE w 0.9.25** (#222): „Dociągnij zaległe” milczało o pominiętych (młodszych niż 2 min) + starwacja przez
  porzucone koszyki; instrukcje o WP-Cron/pętli zwrotnej.
- **Stanowisko, nie produkt**: limit uploadu 2 MB i `uploads` należący do roota (obraz Dockera — instrukcja „Najczęstsze
  problemy” to opisuje i tester to potwierdził), brak sekretu webhooka w pliku (moja karta startowa obiecała zmienną,
  której nie było — `stripe listen --print-secret` do karty na K11), „Settings saved.” po angielsku (WP w `en_US`,
  bo `wp core install` bez `--locale=pl_PL` — do `lib-stos-probny.sh`), wspólny łapacz poczty dla wielu stanowisk.
- **Dług produktu/instrukcji**: Theme File Editor cofa zapis przy zepsutej pętli zwrotnej („Unable to communicate back
  with site”) — instrukcja nawigacji ma wspomnieć SFTP jako drogę główną; „po pozycji 08” nadal myli (dług K9 → 0.9.25?
  NIE weszło, zostaje 0.9.26); Adaptive Pricing Stripe (EUR zamiast PLN) — instrukcja ostrzega, rozważyć wyłączenie
  w Dashboardzie jako krok instalacji; zły NIP w Ustawieniach czyści pole zamiast zostawić do poprawki (UX).

## 🟡 E2E PŁATNOŚCI OSADZONEJ — automat zatrzymuje się na hCaptcha Stripe (30.08 wieczór)
`testy/e2e/przelot-osadzony.js` z prawdziwymi kluczami (konto `acct_1U5Fhg7…`, kontakt.dobrastrona@gmail.com) dochodzi do
końca ramek: panel bez błędu, e-mail + karta 4242 wypełnione w ramkach Stripe, „Zapłać” aktywne i kliknięte — po czym Stripe
wystawia niewidzialną hCaptcha (ramka `hcaptcha-invisible`, `POST api.hcaptcha.com/getcaptcha`) i `confirm()` czeka;
sesja zostaje `open/unpaid`. To zabezpieczenie Stripe dla zautomatyzowanej przeglądarki, nie wada sklepu (kod wyjścia 3).
**Dowód końcowy = człowiek w przeglądarce** (właściciel/kolega przez tunel albo na hostingu). Do rozważenia: Stripe Radar
→ reguły testowe, albo Playwright w trybie z nagłówkiem — nie sprawdzałam. Ścieżka zapasowa (hostowana) przetestowana
maszynowo do końca (zam #9901178 z danymi faktury).


## 🟡 DŁUGI Z RECENZJI #222 r4 (30.08): strażnik reconciliation (R4) woła prawdziwe `api.stripe.com` przy domykaniu opłaconej atrapy (nagłówek obiecuje „bez sieci”) i nie asertuje `bledy`; trzy komentarze w strażniku opisują poprzednie asercje. Do korekty przy 0.9.26 razem z długami K10.

## 🟡 DŁUG Z RECENZJI #226 r2 (30.08, SECURITY.md)
- Brak PRÓBY KONTROLNEJ strażnika dla podpisu webhooka (odrzut złego/brakującego podpisu dowodzą tylko testy audytu L3 na żywym stosie). Do zrobienia: `testy/webhook-podpis-test.php` + `-proba-kontrolna.sh` (mutant: `hash_equals` → `true`).
## 🟡 DŁUGI Z RECENZJI #225 r1 (30.08, canonical 0.9.26) — drobne, poza regułą rund
- **HEAD na stronie nieistniejącego produktu oddaje 200, GET oddaje 404** (`status_header( 404 )` w renderze
  skrótkodu, po `wp_head`; mechanizm różnicy GET/HEAD nieustalony — recenzent też nie ustalił). Zwykłe 404 WP
  oddaje 404 obiema metodami. Do zbadania przy 0.9.27: przenieść ustawienie statusu na `template_redirect`
  po sprawdzeniu produktu z query vara.
- **Wtyczki SEO gospodarza (Yoast/RankMath)** drukują własny canonical bez `rel_canonical` WP → z naszym byłyby
  dwa. Na stosie ich nie ma; jeśli klient je zainstaluje — wyjątek (nie drukować, gdy `defined('WPSEO_VERSION')`
  lub klasa RankMath). Nie mierzone.
- `has_action( 'wp_head', 'rel_canonical' )` traktuje priorytet `0` jak brak podpięcia — `false !== has_action(…)`
  byłoby ściślejsze. Stan nieodtworzony.
- Statyczna sonda wydajności strony produktu (30.08): 63 kB HTML + ~1,3 MB zasobów, z czego 1 MB to `stripe.js`
  ładowany w stopce TYLKO na stronie produktu z kluczem publikowalnym (lista i cudze strony: 0 — zmierzone); nasz `assets/front.css` 83 kB — kandydat do odchudzenia.
  `perf` Lighthouse nadal tylko na hostingu klienta (sekcja wyżej).

## 🟡 DŁUG NA 0.9.27 z recenzji #225 r4 (30.08, tekst klienta w readme.txt)
- `readme.txt` 0.9.26 mówi „bez tych stron nie robi nic poza własnym panelem” — nieprecyzyjne: skrót `[sklepag_sklep]`
  wstawiony ręcznie na dowolnej stronie nadal renderuje sklep (i ma). Poprawne zdanie: „nie dokleja niczego do innych
  stron; sklep pokaże się tylko tam, gdzie sam wstawisz skrót”. Nie weszło do 0.9.26: paczka była już na `origin/main`
  (zamek klasy 10 → podbicie wersji), a zdanie nie szkodzi. Wchodzi z 0.9.27 razem z HEAD-200 i próbą kontrolną podpisu.

## ✅ ROZLICZENIE DŁUGÓW w 0.9.27 (30.08 późny wieczór, decyzja właściciela „mamy czas do rana”)
ZROBIONE: kompensacja tokenu w cronie (decyzja właściciela 30.08 ~22:15 „wszystko co jest błędem naprawiamy”; CZĘŚĆ 4 strażnika resend — na 0.9.26 pada: token_przywrocony=NIE) · HEAD-200 na 404 (template_redirect + `testy/produkt-404-proba-kontrolna.sh`) · próba kontrolna podpisu webhooka
(`testy/webhook-podpis-test.php` + mutanty hash_equals/tolerancja) · wyjątek Yoast/RankMath (krok H strażnika SEO) ·
`false !== has_action` · zdanie o skrócie w readme · A3/licznik klas/§8a-b/odsyłacz a1b2495 z recenzji #227 r3.
ZAMKNIĘTE UZASADNIENIEM: honeypot opinii (formularz tylko z jednorazowego tokenu z maila — bot bez zakupu go nie widzi).
ZOSTAJE: front.css 83 kB (kandydat, nie wada) · `perf` na hostingu klienta · klik karty (człowiek) · NIP→faktury (decyzja).

## 🟡 DŁUG: allowlista recenzenta (workflow) nie zna prób `produkt-404`/`webhook-podpis` (#228 r1 u5/r2 u10)
`.github/workflows/recenzent.yml` wymienia próby po nazwie — dwie nowe wymagają PR-a ruszającego workflow (bez recenzji automatycznej, czyta koordynator). Zrobić jako pierwszy PR po 0.9.27.

## 🟡 DŁUG: `statystyki-proba-kontrolna.sh` (ChatBot) ślepa na mutanta 3 na tym stosie (30.08 ~23:15)
Przelot na `a2f9dc2`: „TEST SLEPY NA 1 PODLOZONYCH BLEDOW” — mutant „wiersze bez znacznika wliczone do skuteczności” przechodzi na zielono. ChatBot nietknięty PR-em #228 (diff pusty), więc to nie regresja kodu — kandydat: dane wspólnego stosu maskują wadę (klasa z pamięci: test na wspólnym stosie bez własnych warunków wstępnych). Diagnoza na czystym stanowisku przy K11/0.9.28. AKTUALIZACJA 31.08: przelot na `9b86106` — próba PRZESZŁA (mutant 3 widoczny); ślepota CHWIEJNA, zależna od aktualnych danych stosu, co wzmacnia diagnozę — dług zostaje. AKTUALIZACJA K11 (31.08): część „wada kodu strażnika” SKREŚLONA (u audytora PASS 4/4 mutanty) — patrz sekcja ✅ K11 na końcu pliku; zostaje izolacja przyczyny chwiejności na świeżym stosie.

## ✅ ZAMKNIĘTE 31.08 (0.9.28): zapis `mail_blad` pod dowodem własności tokenu (#228 r4 u5)
Naprawa w OBU ścieżkach (`zakup.php` cron + bliźniak `admin-zamowienie.php` resend): `AND token_pobrania=%s`
(świeży skrót albo przywrócony przy udanej kompensacji). Strażnik: CZĘŚĆ 5 `resend-odnawia-limit-pobran-test.php`
oczekuje teraz `mail_w_toku` po padzie procesu-przegranego; kalibracja na kodzie z main → FAIL na CAS-B.

## ✅ ZAMKNIĘTE 31.08 (0.9.28): kompensacja przywraca stan ostatniej DORĘCZONEJ wysyłki (#228 r5 u1)
Schemat v9: ślad `dor_*` (token/termin/treść/skrót opinii) zapisywany w tym samym CAS-ie co
`mail_wyslany`; kompensacja crona I resendu przywraca ze śladu (zapas dla zamówień sprzed v9: stan
sprzed rotacji). Strażnik: CZĘŚĆ 6 (cron: A padł w trakcie, B pada → wraca stan DORĘCZONY) + CZĘŚĆ 2b
(resend ze śladu); kalibracja na kodzie z main → FAIL dokładnie na DOR i RESEND-DOR. RODO: anonimizacja
czyści też `dor_*`, pomiar skutku poszerzony. readme/CHANGELOG 0.9.28 mówią jawnie: wyjątek z 0.9.27 zamknięty.

## 🟡 ŚWIADOMIE NIE W 0.9.28 — komunikaty POMINIĘTE trzech bramek dokumentacyjnych (klasa 68)
Trzy bliźniaki odwrócone z K11 R-2 (fałszywie-ZIELONE gałęzie POMINIETE w `dokumentacja-zgodnosc.sh`,
`-segregator.sh`, `-sklep.sh`) czekają dalej: PR 0.9.28 i tak niesie schemat+2 naprawy+wygląd, a to
narzędzia, nie produkt — osobny mały PR narzędziowy po wydaniu. Rejestr: ściąga klasa 68.

## ✅ K11 (31.08, ~01:15–02:10): kroki 1–8 ZGODNE · ROZJAZDY=2 (oba PROCES, żaden w produkcie)
Raport W REPO: `audyt/K11-RAPORT-0.9.27.md` (wciÄgniÄty jak K2/K6/K9/K10; oryginaÅ z maszyny audytora). Paczka=repo=release co do znaku
(digest), E2E zakupu domknięty (mail, link 200, sha pliku = baza), 404 GET/HEAD, axe 0, rubryka 6/5/1.
⭐ Dług „statystyki ślepa na mutanta 3" SKREŚLONY w części „wada kodu strażnika": u K11 test widzi
WSZYSTKIE 4 mutanty (EXIT=0 czysty). Zastrzeżenie audytora: stanowisko czyste, ale STOS wspólny —
przyczyna chwiejności NIEwyizolowana; pełna izolacja = przebieg na świeżo zaseedowanym stosie (0.9.28).

## 🟡 DŁUG (K11 R-1, klasa PROCESU): log przelotu z gałęzi + squash = dowód z commitu spoza historii
`PROBY-KONTROLNE.log` niósł `COMMIT: 44d32db` — po squash nie istnieje w historii main; kontrola 6
`k7-koniec` pada z powodu FORMALNEGO (inny niż opisany w rubryce #12), a weryfikator z zewnątrz dostaje
log o nieznanym rodowodzie. Domknięte dla 0.9.27: przelot powtórzony NA MAIN po scaleniu, log w repo
z commitu z historii. NA STAŁE (od 0.9.28): po każdym squash-merge przelot na main przed wydaniem —
krok DO DOPISANIA w procedurze wydania/`k7-koniec` (0.9.28) — dziś pilnuje tego kontrola 6 k7 (to ona wykryła); szablon dnia-zero w warsztacie już go niesie.
- ⬜ **Przelot 33 prób kontrolnych (`proby-kontrolne-przelot.sh`) na commicie main po 0.9.28** —
  log `audyt/sklep/PROBY-KONTROLNE.log` niesie `COMMIT: 6a7922c` (linia 0.9.27, sprzed #234/#235),
  więc kontrola 6 k7 pada z powodu przeterminowania dowodu (klasa 67; r1 #236 W1). Przelot bramek
  i strażników na main 0.9.28 (`78e53cf`) WYKONANY — brakuje pełnego przelotu prób z podmianą logu.
  **CO ZAMYKA:** `bash narzedzia-qa/proby-kontrolne-przelot.sh` na `0433fc6` (lub nowszym main),
  log do repo, oczekiwane pasmo EXIT≠0: 5–6 znanych (§7); nagłówek AUDYT-RAPORT aktualizuje powód
  kontroli 6 po podmianie.

## 🟡 DŁUG (K11 R-2 + bliźniaki, runda 1 #229): bramki dokumentacyjne kłamią przy POMINIĘTE — jedna fałszywie-CZERWONA, trzy fałszywie-ZIELONE
Bez chromium gałąź POMINIETE podbija licznik BLEDY i komunikat brzmi „NIEZGODNE: 1 pozycji rozjechanych
z kodem" — czytelnik szuka kłamstwa w instrukcji, a brakuje mu przeglądarki. Zamknięcie bramki słuszne,
kłamie komunikat. KLASA, nie egzemplarz: trzy bliźniaki ODWRÓCONE pomijają CICHO bez licznika — `dokumentacja-zgodnosc.sh` („Tested up to”), `-segregator.sh` („Tested up to”), `-sklep.sh` („stos nie odpowiada”) — podsumowanie mówi „0 rozjazdów” mimo NIEZMIERZONEJ kontroli (fałszywy zielony PRZEPUSZCZA, czerwony tylko straszy). Naprawa wszystkich czterech gałęzi: osobny licznik POMINIETE + komunikat „NIE ZMIERZONO (brak przyrządu)”. 0.9.28.

## 🔴 RYZYKO PROCESU (K11 O-4): nic nie wiąże pliku SPRZEDAWANEGO z wersją WYDANIA
E2E audytora pobrał z bramy plik demo K3 (25 354 B, pliki z 18–19.08), bo taki siedzi w bazie stosu —
mechanizm OK (sha zgodne z bazą), ale klasa „sprzedaliśmy stary ZIP" nie ma strażnika. Kandydat 0.9.28:
kontrola wydania porównująca `plik_sha256` produktów z sumami paczek wydania + ostrzeżenie w panelu.

## 📌 BACKLOG od K11 (§4 raportu, skróty): baner „TRYB TESTOWY" na produkcji (wpis 🔴 już wisi) ·
deinstalacja/symetria (rubryka 🐞 #9) · nonce+uprawnienia: próba `nonce` ChatBota pada zawsze = rodzina
niepilnowana · RODO w ruchu (retencja/anonimizacja/eksport WP) · dostarczalność poczty na hostingu
(SPF/DKIM; dowód stoi na łapaczu) · aktualizacja w miejscu 0.9.13→0.9.27 na żywej bazie · O-2 komunikat
bramek „10 z 10” przy braku vendor · O-3 nagłówek rubryki v0.9.23 (świadome, osobny PR wg DECYZJE) ·
DECYZJE.md: duplikat D28 i luka D29–30 (porządek numeracji, 0.9.28).

## ✅ DOMKNIĘTE 2.09 (dawniej 🔴 DŁUG, audytor 1.09 wieczór — `I-24`): GOLDEN pilnujący błędu kolegi (do 2.09 zapisany jako „właściciela”) MA spust
**Rozwiązanie pułapki:** golden biegnie na STOSIE PRÓBNYM od zera (`testy/lib-golden-stos.sh` na `narzedzia-qa/lib-stos-probny.sh`), nie na wspólnym — bramka `golden` w `bramki.sh` (tryb pełny; bez Dockera pominięta JAWNIE, próg 13→12), próba kontrolna 2.0.0 mutuje KOPIĘ wtyczki w katalogu tymczasowym. Dowody uruchomienia w PR. ⛔ Klasa do ściągi PO scaleniu #271 (kolizja numeracji): **„strażnik bez spustu”** — ten sam wzorzec co `I-3`, `I-4`, `I-8` (narzędzie istnieje, nic go nie woła); kontrola: każdy nowy test/bramka w TYM SAMYM PR ma wołającego (grep nazwy w `bramki.sh`/CI/przelocie ≥ 1).

<details><summary>Treść długu z 1.09 (historia)</summary>

`testy/golden-kupujacy-dostaje-to-co-kupil-test.php` z PR #258 jest **jedynym** strażnikiem naprawy „kupujący
dostaje dokładnie to, co kupił" — a więc tego błędu, którego właściciel nie zdradzał przez cały dzień polowania.
Sam test jest dobry: na `main` **EXIT=0** (14 asercji), jego próba kontrolna **EXIT=0** (4 mutanty, każdy wywala
właściwą asercję). **Brakuje wyłącznie spustu:** łapie go wzorcem `testy/*.php` tylko `narzedzia-qa/linia-bazowa.sh:125`,
a ta nie jest wołana z niczego — zmierzone z kontrolą dodatnią: `bramki.sh` → **21** wywołań,
`proby-kontrolne-przelot.sh` → **7**, `linia-bazowa.sh` → **0**. Skutek: cofnięcie migawki produktu przy przyszłym
refaktorze `sklepag_domknij_zakup()` **nie zapali się na czerwono** i kupujący znów zapłaci za jedno, a dostanie drugie.
⛔ **Pułapka do rozstrzygnięcia PRZED wpięciem, nie w trakcie:** golden do pełnego przebiegu wymaga wgrania kodu
z gałęzi **do kontenera**, czyli tej samej operacji, która w `testy/golden-migawka-proba-kontrolna.sh` okazała się
niebezpieczna dla WSPÓLNEGO stosu — podmienia wtyczkę i jej nie przywraca (zmierzone: przed `0.9.32`/`a9ce0f70…`,
po `0.9.31`/`704b0ab7…`, czyli cofnięta czyjaś robota w toku). Wpięcie bez rozwiązania tego cofa builderom kod pod
stopami przy każdym biegu bramek. Dowody: `audyt/plamy/GOLDEN-258-WERYFIKACJA.md`.

</details>

## 🟡 CZTERY DROBNE Z RECENZJI #276 r7 — ODŁOŻONE ŚWIADOMIE, NIE PRZEOCZONE (3.09, r9)

Recenzent słusznie zauważył, że w rundach 8 i 9 zadeklarowałam te cztery jako „zostają" —
i nie dostały wiersza **nigdzie**, czyli zniknęły z widoku. Zasada tego pliku brzmi
„oddajesz ⇒ wiersz", więc oto one. Wszystkie dotyczą `audyt/SCIAGA-EGZAMINATORA.md`,
żadna nie zmienia zachowania produktu ani żadnej bramki.

| # | co | dlaczego odłożone |
|---|---|---|
| D1 | liczba w wierszu klasy podana **bez przyrządu**, którym ją zmierzono | ten sam wzorzec naprawiłam w klasie 113 w rundzie 9; pozostałe wiersze to praca przekrojowa przez cały rejestr, nie jedna poprawka |
| D2 | **znacznik za szeroki** — obejmuje więcej tekstu, niż powinien wyróżniać | kosmetyka składu, zero wpływu na treść klasy |
| D3 | **zdublowany nawias** w klasie 105 | literówka |
| D4 | **zbłąkany dwukropek** | literówka |

⛔ **Dlaczego nie od ręki, skoro to drobiazgi:** ten pull request jest ósmy w kolejce jednego
wykonawcy CI, a każda runda to komplet bramek plus recenzja dla wszystkich czekających.
D1 wymaga przejścia całego rejestru klas, a nie jednego wiersza — i jako jedyna z czwórki ma
wartość merytoryczną. **Do zrobienia razem z najbliższą pracą, która i tak otwiera ten plik.**

## 🟡 UWAGI Z RECENZJI 2.09 PRZENIESIONE PO REGULE JEDNEJ RUNDY (dokumenty: #269, #271; kod: #270 r3 — same DROBNE)

Każda pozycja = tania poprawka tekstowa do wzięcia przy najbliższym PR-ze w tym pliku; nic z tego nie jest wadą produktu u klienta.

### #269 (raporty A7, scalone 2.09 --merge)
- WAŻNA 1: opis PR „realnych rozjazdów” — liczba do sprostowania (recenzent: liczba w opisie ≠ liczba w raporcie).
- WAŻNA 2: nagłówek `audyt/plamy/A7-RAPORT.md` (⛔ było `audyt/czesciowo/` — ten katalog istnieje, więc zła ścieżka wyglądała wiarygodnie; sprawdzone `find`) — jedno zdanie, że `A7-F1` domknięte przez #256.
- DROBNA 3: `A7b-RAPORT.md:176` dwa układy odniesienia w jednym zdaniu (drugi numer prowadzi na HEAD w inny wiersz).
- DROBNA 4: `A7b-RAPORT.md` dwie różne liczby zdarzeń dla tej samej rzeczy.
- DROBNA 5: cytaty samą nazwą pliku tam, gdzie nazwa istnieje w trzech wtyczkach.
- DROBNA 6: „kandydat na klasę” z opisu PR-a jest już klasą 12 ściągi.

### #271 (ściąga klasy 99–104)
- DROBNA 1: wpis klasy cytuje `REJESTR-ZNALEZISK-INFRA.md:171` (A6-F1) — po #267 linia 171 to `P-1`; kotwica zamiast numeru (wiersz `A6-F1`), źródło: #267 odnotował kolizję kodu A6-F1.
  ⛔ **SPROSTOWANIE 2.09, runda 4 #276 (DROBNA 5 recenzji — recenzent ma rację):** uzasadnienie parkowania, które tu stało, było FAŁSZYWE. Mówiło, że poprawka wywoła konflikt w pięciu PR-ach edytujących `audyt/REJESTR-ZNALEZISK-INFRA.md` — ale **obie** parkowane poprawki (kotwica `A6-F1` i odsyłacz 98→103) są zmianami w `audyt/SCIAGA-EGZAMINATORA.md`, czyli w pliku, który ten PR i tak otwiera. Argument o konflikcie dotyczył innego pliku niż naprawa. To ta sama rodzina co klasa 3: zdanie brzmiało wiarygodnie i nikt nie sprawdził, czy mówi o właściwym pliku.
  ✅ **Zrobione w rundzie 4 #276:** obie poprawki wchodzą w tym PR-ze, więc ta pozycja jest zamknięta, a nie przełożona po raz drugi.
- ~~DROBNA 2: klasa 103 unieważnia przepis z klasy 98 („siedem miejsc” bez `SECURITY.md`) — dopisać do 98 odsyłacz „patrz 103: osiem miejsc”.~~ ✅ **ZROBIONE w #276 (runda 4/5):** odsyłacz stoi w kolumnie „Klasa”, a od rundy 5 także w kolumnie „Bramka” tego samego wiersza — czyli tam, skąd czyta się CZYNNOŚĆ przy bumpie wersji. ⛔ Zostawione przekreśleniem, nie skasowane: reguła nagłówka („wraca ⇒ kasujesz”) dotyczy pozycji długu, a to jest ślad, że dług został spłacony właśnie tutaj.

### ~~Klasa do ściągi PO #271 (numer 105)~~ — ✅ ZAMKNIĘTE 2.09 (#276)
⛔ **Reguła z nagłówka tego pliku: „oddajesz ⇒ wiersz, wraca ⇒ kasujesz”.** Klasa 105 WRÓCIŁA — jest w `audyt/SCIAGA-EGZAMINATORA.md` jako pełny wiersz (dowód: `2.09, #272`). Trzymanie jej tutaj jako „do zrobienia” po scaleniu dałoby DWA źródła prawdy naraz na `main` — dokładnie ten stan, przed którym klasa 105 ostrzega. Treść niżej zostaje wyłącznie jako ślad, skąd klasa się wzięła; ⛔ **nie jest już zadaniem** (DROBNA 4 recenzji #276, runda 3).
- „STRAŻNIK BEZ SPUSTU”: narzędzie/test istnieje, nic go nie woła (I-24 golden; I-3 kalibrator; I-4 licznik-odstepstw; I-8 adresy-w-dokumencie/waga-znaleziska). Kontrola: nowy test/bramka w TYM SAMYM PR ma wołającego — `grep -rn <nazwa> narzedzia-qa/bramki.sh .github/workflows/ narzedzia-qa/proby-kontrolne-przelot.sh` ≥ 1.
- Kandydat: przelot `testy/` bez Dockera → ~20 prób EXIT=1, bo `WYMAGA_DOCKERA` zna 4 nazwy (`MAX_POMINIETYCH=5` to osobna wielkość — sufit pominięć, nie liczba nazw); CI nie biegnie po `testy/` (stan sprzed #272).

### #270 r3 (F4-F4) — 3 DROBNE, scalać po #268 (0 KRYT, 0 WAŻNA)
- DROBNA 1: krok render sam podaje `faktura=1` — asercja mierzy własne wejście; poprawka: brać parametr z prawdziwego REDIRECT z WY_A.
- DROBNA 2: opis w sekcji RODO PRZED kliknięciem obiecuje zachowanie faktury także przy zakupie prywatnym (pusta kolumna) — warunek `$ma_fakture` także w tekście przed operacją.
- DROBNA 3 (klasa 11): `testy/anonimizacja-zachowuje-fakture-proba-kontrolna.sh` (⚠️ plik istnieje TYLKO na gałęzi PR #270, nie na `main`) — komunikat obiecuje rozróżnienie, którego warunek nie robi; bliźniak w bazie robi poprawnie.


### #282 r1 (strażnik liczników) — 2 WAŻNE, 5 DROBNYCH · ⛔ ŚWIADOMIE ODŁOŻONE 5.09
⛔ **Decyzja i jej powód:** to była **siódma runda poprawek NARZĘDZIA testowego**, przy nietkniętym
kodzie produktu i czekającym przelocie oraz wydaniu. Strażnik **działa** — próba dwukierunkowa zdana
(`EXIT=0` czysto, `EXIT=1` z podłożonym martwym licznikiem, wskazuje właściwy plik). Dalsze
doskonalenie go ustępuje pierwszeństwa wydaniu. Recenzja: PR #282, komentarz `claude[bot]`.

- **WAŻNA 1 — werdykt kierunku negatywnego nie jest zakotwiczony w PODŁOŻONEJ wadzie**
  (`liczniki-zywe-proba-kontrolna.sh:142`). Warunek `KOD -eq 1 && grep -qF "$OFIARA"` przechodzi także
  wtedy, gdy strażnik wypisał `OFIARA` z INNEGO powodu niż podłożona atrapa — np. gdy ten sam plik ma
  już prawdziwy martwy licznik. Wtedy kierunek negatywny „przechodzi na pusto”.
  ⇒ Poprawka: asercja na KONKRETNY licznik z atrapy (`ODWROTNA`), nie na samą nazwę pliku.
- **WAŻNA 2 — kontrola wycięcia (`:135-139`) nie odróżnia „wycięto sekcję” od „wycięto sekcję i CAŁĄ
  resztę pliku”.** `sed` z zakresem od znacznika do znacznika przy braku znacznika końca tnie do końca
  pliku; `grep -qF 'KIERUNEK NEGATYWNY'` wykryje brak sekcji w obu przypadkach jednakowo.
  ⇒ Poprawka: porównać liczbę linii kopii przed/po wycięciu z oczekiwanym zakresem.
- 5 DROBNYCH: w komentarzu recenzenta na PR #282 (nie przepisuję — czytać u źródła).

⚠️ Recenzent NIE URUCHOMIŁ tego skryptu (`This command requires approval` — brak w `--allowedTools`,
`recenzent.yml:205`), więc obie uwagi stoją na cytatach kodu i `grep`, nie na przebiegu.


### #283 r1 (zrzuty i obrazki) — 3 WAŻNE, 3 DROBNE · ⛔ ŚWIADOMIE ODŁOŻONE 5.09
⛔ **Zamknięte w PR-ze:** KRYTYCZNA 1 (oś czasu z `admin_roboczy` i IP stosu poza kadrem),
DROBNA 3 (klucz publikowalny naszego konta → wzorcowy; przełącznik czyszczenia na domyślny),
WAŻNA 2 (cztery podpisy opisują to, co w kadrze jest). Poniższe **nie dotyczą tego, co jedzie
do klienta w obrazie** — to spójność narzędzi i dokumentacji. Ósma runda poprawek narzędzi
w jedną noc; przelot prób i wydanie mają pierwszeństwo.

- **WAŻNA 1 (klasa 11) — nowa bramka nazywa się szerzej, niż sprawdza.** Kontrola „każdy obrazek
  z instrukcji istnieje w paczce” nie złapałaby wady, którą ten PR opisuje (obraz z danymi
  wewnętrznymi): sprawdza obecność pliku, nie jego treść. ⇒ Albo zawęzić nazwę, albo dołożyć
  kontrolę na treść obrazu (trudne — patrz niżej).
- **WAŻNA 3 — spis treści paczki nie wymienia zrzutów.** `narzedzia-qa/pakuj-dla-klienta.sh:2`
  nadal wylicza zawartość bez `zrzuty/`, mimo że katalog jedzie do klienta. ⇒ Dopisać do nagłówka
  i podbić `WERSJA`.
- **WAŻNA 4 (ekspozycja na klasę 8) — dwie kopie tych samych zrzutów, nic nie pilnuje, że są
  równe.** `wtyczki/sklep-agentow/zrzuty/` i `dokumentacja/zrzuty/` trzymają te same pliki;
  rozjazd jest możliwy i cichy. ⇒ Bramka porównująca sumy, albo jedno źródło i kopiowanie przy
  pakowaniu.
- DROBNA 1 (komunikat błędu podpowiada komendę, która nie wytworzy brakującego pliku),
  DROBNA 2 (nagłówek skryptu pakującego nie nadąża), DROBNA 4 (obie nowe kontrole są poza CI —
  `BRAMKI=` ich nie zna, więc chodzą tylko przy ręcznym pakowaniu).

⭐ **Reguła, która z tej recenzji weszła do klasy 139:** żadna bramka nie złapie danych na
ZDJĘCIU, bo skanery czytają tekst, nie obrazy — `paczka-zgodnosc.sh` meldował „w 69 plikach
paczki nie ma śladów wewnętrznych” na paczce z nazwą konta i IP na obrazku. Jedyna kontrola,
która działa, to **człowiek otwierający plik**.

### D41 (CI na wykonawcach GitHuba, 5.09) — dług JAWNY z przejścia
- **`golden` w CI mógłby biec NAPRAWDĘ.** Obraz `ubuntu-latest` ma Dockera; brakuje tylko kontenera
  bazy (`strona_wp_db`), którego `lib-stos-probny.sh` oczekuje po nazwie. Dziś `BEZ_STOSU_WP=1`
  pomija goldena i siedem prób z `WYMAGA_DOCKERA` JAWNIE — dokładnie jak dotąd na koncie bez Dockera,
  więc pokrycie CI nie zmalało, ale też nie wzrosło, choć mogłoby. ⇒ Osobny PR: krok stawiający
  `mariadb` pod tą nazwą + pomiar czasu (obraz WP ~250 MB do pobrania przy każdym przebiegu; to
  minuty z puli 2000). Decyzja po pomiarze, nie przed.
- **Minuty GitHuba są teraz kosztem.** 2000 min/mies. ≈ 80 pchnięć; tydzień 29.08–5.09 miał ich
  ~200. ⇒ Budżet z twardym stopem w GitHubie (Budgets and alerts) — ustawia właściciel; oraz
  reguła tempa: scalać przy ZERZE uwag krytycznych, WAŻNE tu, jednym PR-em porządkowym.
- ~~**Po transferze repo do organizacji `sklep-agentow`:**~~ ✅ ZAMKNIĘTE 5.09 (PR porządkowy po #285; ⛔ `narzedzia-qa/bramka-recenzji-proba-kontrolna.sh:45` ZOSTAJE CELOWO — to LOGIN użytkownika w atrapie komentarza, nie adres repo, a login się nie zmienił; recenzja #286 DROBNA 2): odznaki i odnośniki w `README.md` (3 wiersze
  z `szydlowskikonrad95-art`), `narzedzia-qa/bramka-recenzji-proba-kontrolna.sh` (1),
  `boty/BRIEFING-BUILDERA-BOTY.md` (1) wskazują starego właściciela — GitHub przekierowuje, ale
  odznaka `badge.svg` pod starym adresem może przestać się odświeżać. ⇒ Commit porządkowy po transferze.

### ZGUBIONE Z LIST — Dzidek przypomniał 5.09 04:40 (było tylko w `do-panelu/PORZADKOWY-SZKIELET.md:134-166`)
- **`narzedzia-qa/przeglad-strony-repo.sh` NIE ISTNIEJE.** Szkielet obiecał bramkę z CZTEREMA mierzalnymi
  kontrolami strony repo — m.in. odznaka wersji w `README.md` kontra `/releases/latest` (zrzuty Dzidka 5.09:
  README `v0.9.38`, „latest” `v0.9.28`; grep tego nie widzi, bo to nie jest w kodzie). ⇒ Budowa PRZED wydaniem
  0.9.40, z próbą kontrolną (podłożona rozbieżność wersji → EXIT≠0).
- **Claude w Chrome na maszynie Dzidka — oględziny „oczami klienta”.** To, czego skrypt nie zmierzy: czy zrzuty
  w README pokazują aktualny produkt, czy strona sklepu wygląda jak coś, za co ktoś zapłaci. Osobna sesja, nie
  widzi naszego stanu — dostaje kartkę z listą ekranów. ⇒ Po wydaniu 0.9.40, przed oddaniem; wynik = lista uwag
  do `REJESTR-ZNALEZISK`, nie „ok”.
