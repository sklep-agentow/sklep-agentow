# Historia zmian — narzędzia QA i bramki CI

Wszystkie istotne zmiany w `narzedzia-qa/` są zapisywane w tym pliku.

Format według [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie według [SemVer](https://semver.org/lang/pl/). Wersja dotyczy
**pojedynczego narzędzia** (stała `WERSJA` w jego nagłówku), nie całego
katalogu — te skrypty żyją własnym życiem i wchodzą osobnymi PR-ami.

Plik zaczyna się przy bramce recenzji (PR po #94). Wcześniejsze narzędzia
(`bramki.sh`, `sprawdz-manifest.sh`, `kalibrator-straznikow.sh`,
`licznik-odstepstw.sh`, `waga-znaleziska.sh`) powstały bez wpisów — historii
wstecz nie dopisuję, bo nie umiałbym jej odtworzyć uczciwie. Ich zmiany od tej
pory lądują tutaj.

## [Niewydane]
- **`stanowisko/sprzatnij-zamowienia-testowe.sh` — NOWY (I-22): lista / `--kasuj` zamówień testowych ze stanowiska (domena
  `test.local`/`example.com` ORAZ sesja Stripe `cs\_test\_`; pusty e-mail NIGDY — r3 po #296 r2 WAŻNA 1: na stanowisku `cs_test_`
  ma każde zamówienie, więc nie odróżnia testów od ręcznych zakupów; wiersze bez e-maila są wypisywane osobno, bez kasowania)
  po zrzucie do JSON, razem ze zdarzeniami, opiniami i sesjami; demo `@seed.local` wypisywane osobno, kasowane tylko z `--kasuj --z-seedem` — r4 W1).** Nasze testy zostawiały zamówienia po 149 zł, które liczyły się do przychodu na Pulpicie; 5.09 usunięto 20 (zrzut
  sprawdzony po #296 r1 WAŻNA 1: 2 z pustym e-mailem, wszystkie 20 z `cs_test_`). Pierwsza wersja dawała FAŁSZYWE ZERO
  (cudzysłowy SQL rozrywały string PHP) — od r2 kontrola dodatnia: pomiar musi oddać liczbę WSZYSTKICH zamówień, inaczej kod 2
  (WAŻNA 2); pusty e-mail sam nie jest znacznikiem testu, bo domknięcie sesji zapisuje go przy prawdziwym zakupie (WAŻNA 1);
  lista przed kasowaniem bez ucięcia (DROBNA 3); nota o sprzątaniu także w `przelot2.js` (WAŻNA 3).
- **`rejestr-liczniki-proba-kontrolna.sh` — NOWA (I-23): rejestr INFRA liczy sam siebie i coś go pilnuje** — nagłówek == pomiar
  komendą pliku, suma statusów == wiersze, zero przecięć; od r2 także DRUGI rejestr: każdy kod OTWARTY musi być w sekcji „ŚWIADOMY
  DŁUG NA WYDANIE” w `audyt/CO-WISI.md` (#296 r1 KRYT 1: sekcja mówiła „~20”, pomiar 30). Mutanty: nagłówek +1, wiersz o dwóch
  statusach, kod wycięty z CO-WISI, liczba „**N OTWARTYCH**” w sekcji zawyżona (r4 DROBNA 2) — złapane. Biegnie z **`bramka-znaleziska.sh`** na KAŻDYM PR (WAŻNA 4: przelot prób omija
  jawnie PR-y ruszające wyłącznie `audyt/`).
- **`rodo-anonimizacja-ip-proba-kontrolna.sh` — NOWA: dowód dla `P-5` (czyszczenie IP z dziennika przy anonimizacji kupującego)
  jest odtwarzalny (#291 WAŻNA 4, DROBNA 5).** Stos jednorazowy, kod z drzewa, mutant w kopii w kontenerze (EXIT=1), przypadek
  BEZ wady: wpis IP obcego zamówienia zostaje nietknięty w obu kierunkach. DROBNA 6 (`zakup.php:410` zapisuje IP bez
  `zamowienie_id`, czyszczenie po `zamowienie_id` go nie obejmie) — nazwana komentarzem w kodzie, bez zmiany zachowania:
  numer zamówienia w tym miejscu nie jest jeszcze znany. Lista „wymaga stosu WP” +1, sufity pominięć 9 / 12 (przeliczone).
- **`slady-wewnetrzne.sh` 1.4.1 — wzorzec „nasz obieg pracy” łapie też gołe „ten/tego PR” i „Runda N” (#291 WAŻNA 3, klasa 5 + 8).**
  Trzy komentarze w `includes/admin.php` cytowały nasz proces i jechały do klienta przy zielonej bramce `slady`, bo wzorzec
  wymagał „PR #N” albo słowa recenzent/koordynator/builder. Nowy przypadek w `paczka-zgodnosc-proba-kontrolna.sh`
  (`psuj_obieg_pracy_ten_pr`): pada na podłożonym zdaniu (EXIT=1), przechodzi na czystym (EXIT=0); cała próba 22/22, EXIT=0.
- **`pakuj-dla-klienta.sh` 1.2.1 — kontrola śladów w dokumentach paczki TYM SAMYM skanerem, co zipy (#291 r6 WAŻNA 2,
  klasa 11).** Asercja „brak śladów wewnętrznych w dokumentach paczki” miała własną listę 9 wzorców, obcą
  `slady-wewnetrzne.sh` 1.4.1 (zero wspólnych), a `PRZECZYTAJ-MNIE.txt` i `INSTRUKCJA-INSTALACJI.md` jadą do kupującego.
  Teraz skaner dostaje kopię obu dokumentów w osobnym katalogu (EXIT 1 = ślad, 2 = nie zmierzył — oba padają).
  Próba z podłożonym śladem („recenzent”) w PRZECZYTAJ-MNIE.txt → asercja PADA; bez → przechodzi.
- **`pakuj-dla-klienta.sh` 1.2.0 — nagłówek zna `zrzuty/`, i dołożona kontrola sum między dwiema
  kopiami zrzutów (#283 r1, WAŻNE 3/4).** Nagłówek skryptu wymieniał zawartość paczki bez katalogu
  `zrzuty/`, mimo że jedzie do klienta od recenzji #280 — dopisany. Ważniejsze: `wtyczki/sklep-agentow/zrzuty/`
  (kopia dla wpisu na WordPress.org) i `dokumentacja/zrzuty/` (źródło pakowania) trzymają te same pliki,
  a nic nie pilnowało, że są równe — rozjazd byłby cichy (klasa 8, bliźniak). Skrypt teraz porównuje
  sumy `sha256sum` obu kopii PRZED złożeniem paczki i kończy `EXIT=2`, gdy się różnią albo gdy bliźniak
  brakuje. Dowód: podłożony 1 bajt różnicy w `wtyczki/sklep-agentow/zrzuty/sklep-front.png` →
  „PAKUJ: rozjazd kopii zrzutów”, `EXIT=2`; po przywróceniu bajtu — pełna samokontrola PRZESZŁA
  (19/19), `EXIT=0`.
  Przy okazji (WAŻNA 1): kontrola „każdy obrazek z instrukcji istnieje w paczce” zmieniła nazwę na
  „…ma PLIK o tej nazwie w paczce (obecność, nie treść)” — sprawdzała i nadal sprawdza wyłącznie
  obecność pliku pod daną nazwą, nie zawartość obrazu (na treść zdjęcia patrzy tylko człowiek,
  klasa 11 — nazwa bramki węższa niż mogłaby sugerować pełne pokrycie).
- **`slady-wewnetrzne.sh` 1.4.0 — nowy wzorzec: kod naszego rejestru znalezisk w paczce klienta (D-4).**
  72 pliki w 12 z 29 archiwów `boty/paczki/` niosły oznaczenia audytu (np. `A6-F1`, `Q-5`, `CZ-1`,
  `K10`, „runda 2”, „klasa 8”) — komentarz cytował kod zamiast nieść powód. Dołożony wzorzec łapie
  format „litera(y)-myślnik-numer” (`A7b-F2`, `B-6`, `D-4`, `CZ-1`…), wymaga MYŚLNIKA — bez tego
  warunku łapał fragmenty regeksów (`[a-zA-Z0-9]` widziane jako „Z0-9”) i współrzędne SVG (`M100`).
  Świadomie NIE łapie gołych kodów bez myślnika (`K10`, `C3`) ani słów prozy („klasa N”, „runda N”,
  „znalezisko”) — oba warianty dały fałszywe trafienia na tym repo (SVG/regeksy, zwykłe zdania);
  to zostaje jako otwarty gap, nie cichy dopisek. Ta sama funkcja obsługuje bramkę `slady` (każdy PR)
  i `paczka-zgodnosc.sh` (zamrożenie) — jedno źródło wzorców.
  Dowód: `narzedzia-qa/paczka-zgodnosc-proba-kontrolna.sh` — nowy przypadek „kod znaleziska audytu
  (A7b-F2) w paczce” EXIT=1 z naprawą; ten sam przypadek na kodzie SPRZED naprawy EXIT=0 (BLAD,
  próba kontrolna skalibrowana); czysta paczka nadal EXIT=0. Realna paczka złożona
  `narzedzia-qa/pakuj-dla-klienta.sh` z bieżącego źródła, rozpakowana: 0 trafień wzorca, kontrola
  dodatnia (pliki `.php` z `<?php`) = 54/54.
- **`proby-kontrolne-przelot.sh` — sufit pominięć i PODŁOGA uruchomionych per odłamek; przelot biegnie też dla zmian w `testy/`
  i `boty/`; podłoga obowiązuje TYLKO dla zakresów, dla których została policzona (`.` i `narzedzia-qa`) — inny zakres
  (np. zagnieżdżony przelot dwuplikowy w `przelot-brak-zip-proba-kontrolna.sh`) dostaje jawny wiersz `PODLOGA: brak`
  (#290 WAŻNA 1).** Literały podłogi przeliczone (58 prób w repo → 48; 22 w `narzedzia-qa` → 15).
- **`przelot-bramy-proba-kontrolna.sh` — NOWA, trwała próba bram przelotu (#290 WAŻNA 2):** kopia przelotu z ATRAPAMI
  prób, bez uruchamiania prawdziwych; A (równo podłoga → 0), B (za mało → 1 z wierszem o podłodze), C (odłamek `bramki`
  z jedną atrapą kod 2 → 1, sufit 0), D (mutant `MIN_URUCHOMIONYCH_ZAKRES=0` → B przestaje padać → ŚLEPY). Podłoga
  czytana z przelotu, kotwica mutanta po wzorcu — literał zmienił się 14→15 tego samego dnia i sztywna liczba już raz zgniła.
- **`przelot-brak-zip-proba-kontrolna.sh` — czysty kod musi dać kod 0** (dotąd asertowany był tylko kod 2, więc kod 1
  z podłogi przelatywał bez śladu).
- **`bramki.sh` — bramka `actionlint` zapewnia pinowany shellcheck** (suma, cache) zamiast polegać na binarce z obrazu.
- **`gitleaks-proba-kontrolna.sh` 1.1.2 — binarka ZAWSZE przez `zapewnij_narzedzie` (suma sprawdzana także dla trafienia
  z cache), nie „jeśli plik w /tmp jest wykonywalny, bierz” (#287 DROBNA 1, znalezisko B-5: cache w /tmp zapisywalnym dla
  każdego).**
- **`przelot-odlamki-proba-kontrolna.sh` — drugi mutant: literówka w `FILTR_BEZ` odłamka `reszta`** (#287 DROBNA 2; dotąd
  mutowany był tylko filtr typu TYLKO).
- **`recenzent.yml` — allowlista recenzenta obejmuje przelot i jego próby** (`proby-kontrolne-przelot.sh`,
  `przelot-odlamki-`, `przelot-brak-zip-`, `przelot-bramy-`, `bramki-narzedzia-proba-kontrolna.sh`) — #290 WAŻNA 4,
  #287 DROBNA 3: recenzent nie mógł uruchomić niczego, co mierzy zmiany w przelocie.
- **`stanowisko/README.md` + `audyt/LINIA-BAZOWA-TESTOW.md` — sprawdzenie crona z POPRAWNYM kodem wyjścia** (#293 WAŻNA 1,
  klasa 8): `grep -c` przy zerze trafień kończy kodem 1, więc stan zdrowy dawał EXIT 1; teraz `[ "$(… | grep -c ',now$')" = 0 ]`.
- **`stanowisko/wp-cron-stanowisko.sh` 1.0.1 — padnięty `docker ps` nie udaje zgaszonego kontenera (#289 DROBNA 2),
  kody wyjścia opisane zgodnie z `docker exec` (DROBNA 1), kontrola crona nazwana uczciwie: liczy zaległe TERAZ,
  bez progu wieku (WAŻNA 5, klasa 11).** Status listy kontenerów bierze się teraz z `docker ps`, nie z `grep` za potokiem;
  demon, który nie odpowiada, kończy kodem 3 z komunikatem na stderr. Dowód: podstawiony `docker` padający kodem 1 → skrypt
  `EXIT=3`; prawdziwy Docker z żywym kontenerem → `EXIT=0`. ⛔ Kopia na hoście (`/usr/local/sbin/`) NIE aktualizuje się sama
  (#289 WAŻNA 7, dług w `audyt/CO-WISI.md`) — po scaleniu: `bash narzedzia-qa/stanowisko/instaluj-spust-crona.sh`.
- **`stanowisko/README.md` — rozmiar paczki 0.9.40 poprawiony na zmierzony (4 859 567 B, `ls -l`; poprzednia liczba
  9 098 363 B nie miała pokrycia — #289 WAŻNA 6, klasa 3).**
- **`stanowisko/wp-cron-stanowisko.sh` 1.0.0 + `stanowisko/instaluj-spust-crona.sh` 1.0.0 — WP-Cron stanowiska ma ZEWNETRZNY spust
  (znalezisko `I-25`).** Przyczyna nie jest brak ruchu: `siteurl` to `http://localhost:8890`, a w kontenerze na 8890 nikt
  nie slucha (apache na :80), wiec petla zwrotna `wp-cron.php` konczy sie odmowa polaczenia (kod 000, curl exit 7) i WP-Cron
  nie ma jak wystartowac SAM. Ustawione: `DISABLE_WP_CRON` w `wp-config.php` + wpis `/etc/cron.d/stanowisko-wp-cron` co minute.
  Dowod: zaleglych 8 z 15 przed, 0 po. Proba kontrolna (wada = wyjety wpis crona): zdarzenie zaplanowane 5 min wstecz wisi
  po 140 s (FAIL); po przywroceniu wpisu wykonuje sie w 54 s (PASS).
- **`stanowisko/README.md` — opis warunkow wstepnych stanowiska i ich prob kontrolnych** (cron, limity uploadu, trwala baza
  lapacza maili, wp-cli montowany z hosta). Zmiany w definicji stosu siedza w `/root/praca/zlecenia/strona-wp/docker-compose.yml`
  i `strona-wp/wp/php-stanowisko.ini` (limity 2M/8M -> 64M/64M, `I-26`; `MP_DATABASE=/data/mailpit.db` + wolumen `mailpit_data`, `I-27`).
- **`gitleaks-proba-kontrolna.sh` 1.1.1 — SAMOWYSTARCZALNA: sama zapewnia binarkę gitleaks (prawdziwą `zapewnij_narzedzie`
  z `bramki.sh`, tryb samych definicji), zamiast żądać „uruchom najpierw bramki.sh”.** Ukryta zależność od KOLEJNOŚCI,
  którą ujawnił podział przelotu na odłamki (#287, pierwszy przebieg): w pełnym przelocie binarkę pobierała przypadkiem
  wcześniejsza próba, w odłamku `reszta` nikt — próba oddawała kod 2, przelot padał na suficie niezmierzonych (0).
  Dowód: z pustym `TMPDIR` (jak świeży wykonawca) próba pobiera, weryfikuje sumę i przechodzi (EXIT=0); z pełnym cache
  bez zmian (EXIT=0).
- **`quality.yml` — blok `run:` kroku przelotu przechodzi shellcheck (SC2086).** ⚠️ STAN PO #290: bramka `actionlint`
  w `bramki.sh` SAMA zapewnia pinowaną binarkę shellchecka (`zapewnij_narzedzie`, suma SHA-256; shellcheck z obrazu
  o innej sumie jest ODRZUCANY i pobierany własny), więc zdania niżej o „shellchecku z obrazu GitHuba” i „ręcznym
  parytecie w `/tmp/bramki-narzedzia`” opisują stan sprzed #290 (recenzja #290 WAŻNA 3, klasa 3). Obraz GitHuba ma `shellcheck`, więc
  bramka `actionlint` sprawdza tam także skrypty w `run:`; lokalnie shellchecka nie było i actionlint milczał (#287 r1:
  `BRAMKA actionlint: EXIT=1`). Parytet lokalny: `shellcheck` w `/tmp/bramki-narzedzia` obok actionlinta — stara wersja
  pliku pada lokalnie tak samo jak w CI (EXIT=1), nowa przechodzi.
- **`proby-kontrolne-przelot.sh` — ODŁAMKI (`PRZELOT_ODLAMEK=bramki|poswiadczenia|reszta`) i tryb listy (`PRZELOT_LISTA=1`).**
  CI biegnie trzema odłamkami RÓWNOLEGLE (D41 c.d., 5.09): dwie próby zjadały ~80 % przelotu (590 s + 310 s z 15),
  reszta ~30 s. Odłamki nazwane W SKRYPCIE, nie w workflow — jedno źródło prawdy; nieznana nazwa albo odłamek
  bez trafień = ODMOWA (kod 2), nie zielone. Log niesie wiersz `ODLAMEK:`, żeby logu odłamka nie wziąć za pełny.
  Bez nazwy odłamka zachowanie bez zmian (`k7-koniec`, przelot ręczny).
- **`bramki-proba-kontrolna.sh` — uzupełnienie zapisu incydentu z #286 (recenzja r2, DROBNA 4, klasa 11).** Zależność
  od diffu gałęzi dawała nie tylko fałszywą CZERWIEŃ (0 zamiast 1), ale i fałszywą ZIELEŃ dwóch asercji „ma być 0”
  (docker+unzip, docker bez unzip) — one przechodziły z tego samego powodu, dla którego trzecia padała, czyli
  niczego nie mierzyły. `--tryb pelny` naprawia oba kierunki naraz; wpis wyżej mówił tylko o czerwieni.
- **`przelot-odlamki-proba-kontrolna.sh` — NOWA, dwukierunkowo.** Suma trzech odłamków = pełna lista (na kopii
  przelotu, bez uruchamiania prób); nieznany odłamek → kod 2; mutant z literówką w filtrze odłamka → suma ≠ pełna,
  złapane. Bez tej próby literówka w jednym filtrze kurczyłaby zestaw po cichu — klasa I-2 (mianownik zero).
- **`bramki-proba-kontrolna.sh` — pomiary pominięcia goldena wymuszają `--tryb pelny` (5.09, #286).** Zapowiedź
  „golden bedzie POMINIETA” pada tylko w trybie pełnym, a `bramki.sh` bez flagi wykrywa tryb z diffu gałęzi — PR
  zmieniający same pliki tekstowe wpadał w tryb tekstowy i trzy pomiary (docker+unzip / bez dockera / docker bez
  unzip) oraz nowy przypadek `BEZ_STOSU_WP=1` dawały 0 zamiast 0/1/0/1 → próba czerwona, choć kod bramki zdrowy.
  Klasa L38 („gałąź, w którą środowisko nie wchodzi”): na `ciuser` bez Dockera cały przypadek był pomijany, więc
  zależność od diffu nie miała jak wyjść przez tydzień; na wykonawcy GitHuba (Docker widoczny) ruszył pierwszy raz.
  Dowód: pomiar celowany na gałęzi tekstowej — auto 0, `--tryb pelny` 1; pełna próba po naprawie w opisie PR-a.
- **`bramki.sh` — druga jawna odpowiedź na pytanie „czy golden ma gdzie wystartować”: `BEZ_STOSU_WP=1` (D41, 5.09).**
  Wykonawca GitHuba (`ubuntu-latest`) Dockera MA, a kontenera bazy stosu warsztatu NIE — bez deklaracji golden
  widziałby Dockera i świecił czerwono jako „wada stanowiska” na każdym PR-ze. Deklaracja stoi wyłącznie w
  workflow (`env:`), lokalnie golden biegnie jak dotąd. Ta sama ścieżka pominięcia: log, podsumowanie, próg o jeden.
- **`bramki-proba-kontrolna.sh` — nowy przypadek dla `BEZ_STOSU_WP=1`, DWUKIERUNKOWO i z mutantem.** Z deklaracją
  1 pominięcie i powód nazywający zmienną; bez deklaracji na koncie z Dockerem 0; mutant z wyciętą gałęzią
  deklaracji ma być złapany (powód znika). ⛔ Stary przypadek „pominięcie tylko przy braku Dockera” odcina
  teraz deklarację (`env -u BEZ_STOSU_WP`) — na wykonawcy GitHuba stoi ona w środowisku całego joba i bez
  odcięcia pomiar „docker+unzip” liczył 1 zamiast 0. Złapane pierwszym przelotem prób pod `BEZ_STOSU_WP=1`
  (próba samodzielnie EXIT=0, pod deklaracją EXIT=1); pomiar celowany po naprawie: z odcięciem 0, bez 1.
- **`proby-kontrolne-przelot.sh` — próby z `WYMAGA_DOCKERA` pomijane także przy `BEZ_STOSU_WP=1`, z własnym powodem
  w logu.** Bez tego na wykonawcy GitHuba ruszałyby i padały na braku bazy, a przelot liczyłby to jako porażki
  produktu. Komentarz o „dwóch runnerach” przeniesiony w czas przeszły — od D41 biegnie jeden obraz GitHuba.
- **`k7-koniec.sh` 1.4.0 — warunek 2 (kalibracja) przestaje żądać stanu NIEOSIĄGALNEGO.** Reguła
  `PLAN-K7 §5` mówi „dwie próby i koniec, potem NIEKALIBROWANA”, a bramka wymagała `PASS`
  w KAŻDYM wierszu **oraz** `proby ≤ 2` — razem: soczewka raz oblana nie miała żadnej drogi do
  `PASS` poza wpisaniem nieprawdy. Nowa reguła jest **ostrzejsza, nie luźniejsza**: tabela
  rozliczenia dostaje kolumnę `powod_powtorki` (pole 8; `proby` zostaje polem 7, więc stare
  wiersze parsują się bez zmian), próby 3 i 4 przechodzą **tylko z wypełnionym powodem**,
  a powyżej 4 bramka pada bezwarunkowo — powód nie ratuje. Dotąd nikt nie musiał uzasadnić
  ANI JEDNEJ powtórki. Decyzja właściciela z 4.09 (`DECYZJE.md`, zmiana `D33`).
  ⛔ Zmiana ZACHOWANIA bramki, stąd `MINOR`, nie `PATCH`.
- **`k7-koniec-proba-kontrolna.sh` — dwie nowe próby, DWUKIERUNKOWO.** Bez kierunku pozytywnego
  nie dałoby się odróżnić zaostrzenia bramki od jej zepsucia:
  `proby=3` bez powodu → `EXIT=1` · `proby=3` z powodem → `EXIT=0` · `proby=5` mimo powodu → `EXIT=1`.
  Komplet przechodzi (`EXIT=0`).
- **`sprawdz-manifest.sh` — sufit `MAX_NIEZMIERZONYCH` dostaje własnego strażnika (kontrola 1b2).** Był JEDYNYM progiem w `bramki.sh` bez kontroli: `PROG_PELNY` pilnuje 1b, a sufitu nie pilnowało nic (grep po całym repozytorium: same wystąpienia w `bramki.sh` i jedno zdanie tutaj). Kontrola żąda **literału równego zeru** i odmawia, gdy sufit czyta ze środowiska, gdy jest podniesiony albo gdy zniknął z pliku. ⛔ Pierwsza wersja tej kontroli miała własną furtkę — przepuszczała podniesiony sufit, jeśli nazwa `MAX_NIEZMIERZONYCH` padała gdziekolwiek w tym CHANGELOG-u, a padała, bo dopisał ją commit wprowadzający sufit. Raz wpisane słowo otwierałoby drogę na zawsze; złapała to własna kalibracja (mutant „sufit=3" przeszedł na zielono). Dowód: 3/3 mutanty złapane (środowisko / podniesiony / usunięty), czysty kod dwa razy zielony.
- **`bramki.sh` — kod wyjścia 2 przestaje być cichym zielonym dla WSZYSTKICH TRZYNASTU bramek.** Runda 9 słusznie przestała traktować kod 2 jak porażkę (brak Dockera na koncie CI wyglądał identycznie jak regresja produktu), ale zatrzymała się w pół drogi: licznik `WYKONANE` rósł PRZED rozgałęzieniem na kod, więc bramka bez pomiaru zaliczała się do progu, `PORAZKI` zostawało zerem, a zestaw kończył się `WSZYSTKIE WYKONANE BRAMKI ZIELONE` i **EXIT=0**. Zasięg był szerszy niż golden: `sprawdz-manifest.sh:32,36,40` i `zdarzenia-webhooka.sh:39,44,61,77,145` używają kodu 2 na **brak pliku w repozytorium** — po rundzie 9 skasowanie manifestu bramek albo `wtyczki/sklep-agentow/includes/zakup.php` kończyło się zielono. Teraz kod 2 nie podbija `WYKONANE` (bramka została URUCHOMIONA, nie WYKONANA), a **sufit `MAX_NIEZMIERZONYCH` sprawdzany jest PRZED progiem** — i to jest istota, nie kolejność dla porządku. Pierwsza wersja tej naprawy stawiała sufit PO progu i tym samym czyniła go w trybie pełnym **martwym**: `PROG_PELNY` równa się tam liczbie bramek uruchomionych (13 przy 13; pominięcie goldena obniża próg o dokładnie jeden), więc każde `NIEZMIERZONE > 0` znaczyło `WYKONANE < PROG` i wygrywała gałąź progu — z komunikatem *„sprawdź, która **wypadła**"* o bramce, która nie wypadła, tylko ruszyła i odmówiła werdyktu. Zdanie `NIE WSZYSTKO ZMIERZONE` nie mogło paść w jedynym trybie, w którym `golden` w ogóle biegnie. Próg liczy teraz bramki, które **ruszyły** (`WYKONANE + |NIEZMIERZONE|`), więc mierzy to, co obiecuje: czy zestaw się skurczył. ⛔ Sufit jest **literałem `0`**, nie odczytem ze środowiska — pierwsza wersja czytała `${MAX_NIEZMIERZONYCH:-0}`, więc `MAX_NIEZMIERZONYCH=2 bash narzedzia-qa/bramki.sh` przywracało ciche zielone bez zmiany jednego bajtu w repozytorium. Precedens, na który powoływał się komentarz — `MAX_POMINIETYCH` w `proby-kontrolne-przelot.sh:69` — jest literałem właśnie dlatego. Nagłówek pliku wymienia trzeci stan, którego wcześniej nie znał. Dowód (kalibracja dwóch kształtów tego samego kodu, bramka kończąca się kodem 2): kształt po naprawie `PORAZKI:WYKONANE = 0:0`, kształt sprzed naprawy `0:1` — czyli stary byłby złapany; oba kształty dające to samo są w skrypcie kalibrującym osobnym błędem.
- **`bramki-proba-kontrolna.sh` — pomiar kodów wyjścia mierzy DWIE wielkości i CAŁY skrypt, nie sam licznik porażek.** Do rundy 10 pętla czytała wyłącznie `$PORAZKI` i dlatego przepuściła wadę opisaną wyżej: dla obu kształtów `PORAZKI` wynosi 0, więc asercja świeciła zielono niezależnie od tego, czy naprawa jest. Teraz format to `kod:PORAZKI:WYKONANE` (`0:0:1`, `1:1:1`, `2:0:0`), a osobny przypadek uruchamia **pełny `bramki.sh`** z jedną bramką podmienioną na komendę kończącą się kodem 2 i wymaga, żeby cały zestaw skończył się czerwono z frazą `NIE ZMIERZONO` — bo to kod wyjścia całego skryptu trafia do opisu PR-a, nie wartość zmiennej wewnątrz funkcji. ⛔ Ten przypadek biegnie w trybie **tekstowym**, w którym `golden` jest pomijany — czyli nie pokrywał jedynej bramki, dla której cała naprawa powstała (klasa 11: pokrycie obiecane szersze niż faktyczne). Doszedł więc drugi, mierzący **sam ogon skryptu** na scenariuszu trybu pełnego (13 bramek, golden z kodem 2) — dwukierunkowo: przy jednej niezmierzonej `NIE WSZYSTKO ZMIERZONE` i EXIT=1, przy komplecie `WSZYSTKIE WYKONANE BRAMKI ZIELONE` i EXIT=0. Kontrola wycięcia pilnuje, żeby pusty ogon nie udawał zielonego. Poprawiony też harness: podstawiał `BEZ_GOLDENA`, nazwę, której w `bramki.sh` nie ma ani razu, zamiast `DOCKER_WIDOCZNY`, którą `bramka()` naprawdę czyta — przypadek przechodził tylko dlatego, że atrapa nie nazywa się `golden` i warunek zwierał się przed odczytem drugiej zmiennej.
- **`lib-stos-probny.sh` 1.0.3 — znacznik własności stosu: zwija ten proces, który stos POSTAWIŁ.** Regresja z 1.0.2 (recenzja #272, runda 3, WAŻNA 1): przeniesienie zamka do `postaw_stos` sprawiło, że zamek jest brany PO założeniu pułapki `trap zwin_stos EXIT` w skryptach wołających. Bieg, któremu zamek ODMÓWIŁ dostępu, wychodził przez `padnij` (exit 2), odpalał własną pułapkę i kasował kontener `probny_wp` ORAZ dropował bazę `wp_probny` temu biegowi, KTÓRY ZAMEK TRZYMA. Zamek chronił ścieżkę udaną, a rozbrajał się na ścieżce przewidzianej. Teraz `postaw_stos` ustawia `STOS_PROBNY_POSTAWILEM=tak` (NIE eksportowane — zwija ten, kto stawiał, nie ten, kto odziedziczył zamek), a `zwin_stos` bez tego znacznika wraca bez ruszania czegokolwiek. Strażnik: `testy/zamek-stosu-probnego-proba-kontrolna.sh` punkty H i I — z podłożoną wadą (usunięta JEDNA linia straży) punkt H daje 2 wywołania zamiast 0 i test pada (EXIT=1); po naprawie 0 wywołań, a kontrola dodatnia I potwierdza, że WŁAŚCICIEL stos zwija (2 wywołania).
- **`lib-stos-probny.sh` 1.0.2 — stos próbny dostaje WŁASNY zamek, brany przez `postaw_stos`, czyli przez KAŻDEGO z szesnastu użytkowników `probny_wp`.** Runda 2 recenzji #272, uwagi 1 i 2. Do 2.09 zamek brało dwóch użytkowników i był to `wymiana/stos.lock` — zamek stosu WARSZTATOWEGO. Dwie wady naraz: (a) siedmiu pozostałych (`dowod-z-paczki`, `dowod-aktualizacji`, dwa `dowod-zachowania-*`, `k7-stos-rundy`, dwie próby kontrolne z `testy/`) nie brało zamka wcale, a `postaw_stos` zaczyna od `docker rm -f -v probny_wp`, więc jeden bieg kasował kontener spod drugiego; (b) `linia-bazowa.sh:110,116` uruchamia każdy `testy/*.sh` POD `flock wymiana/stos.lock`, a golden brał ten sam plik drugi raz (nowy opis otwartego pliku) — ZAKLESZCZENIE aż do `timeout 300`, EXIT=124 i fałszywe czerwone. Teraz: `ZAMEK_PROBNY=/root/praca/zlecenia/wymiana/stos-probny.lock`, znacznik `STOS_PROBNY_ZAMEK_WZIETY` eksportowany (potomek nie bierze zamka drugi raz). Strażnik: `testy/zamek-stosu-probnego-proba-kontrolna.sh` 1.1.0 (DZIEWIĘĆ punktów A–I, w tym próba kontrolna samej próby: z podłożonym starym kształtem EXIT=1, oraz asercja na LICZBIE użytkowników stosu próbnego). Pomiar leku dla `-v` z 1.0.1: kontrola dodatnia `docker rm -f` BEZ `-v` → **+1 wolumen**, pełny bieg goldena z naprawą → **+0 wolumenów**, `probny_wp` po biegu 0, golden EXIT=0 w 11 s.
- **`linia-bazowa.sh` — `golden-stos-probny.sh` (spust bramki) i `lib-golden-stos.sh` (biblioteka) wypadają z listy testów.** Pierwszy stawiał stos od zera pod `LIMIT_S=300`, drugi tylko sourc'ował bibliotekę i dopisywał wiersz „przechodzi”, który niczego nie mierzył. Precedens: `zrzuty-panelu.js` („narzędzie, nie test”). Strażnik: `testy/linia-bazowa-pomijane-proba-kontrolna.sh` (wzorce WYCINANE z pliku, nie przepisywane; kontrola dodatnia: 15 prawdziwych testów `.sh` dalej biegnie).
- **`bramki.sh` — pominięcie bramki `golden` ma znowu DOKŁADNIE JEDEN powód: konto nie widzi Dockera.** Runda 4 zrównała warunek pominięcia z `wymagaj_narzedzi` „dla zgodności" i tym samym poszerzyła ciche pominięcie z jednego powodu na cztery: brak `unzip`, nieodczytany `KONTENER_DB` i niechodzący kontener bazy do rundy 4 kończyły się `padnij` (exit 2), a po niej dawały `WSZYSTKIE WYKONANE BRAMKI ZIELONE` i EXIT=0 — przy trzech dokumentach mówiących, że pominięcie dotyczy wyłącznie braku Dockera. Warunek jest teraz CELOWO WĘŻSZY niż `wymagaj_narzedzi`: jedyne pytanie brzmi, czy golden ma gdzie wystartować, a każdy brak na koncie Z Dockerem świeci czerwono przez `wymagaj_narzedzi`. Dowód (próba kontrolna psująca jeden warunek na raz, ten sam plik `bramki.sh`): docker widoczny + `unzip` jest → 0 zapowiedzi pominięcia · konto bez Dockera (atrapa) → 1 pominięcie, próg 13→12 · docker widoczny + BRAK `unzip` → **nowy kod 0, stary kod 1** · sam `testy/golden-stos-probny.sh` bez `unzip` → **EXIT=2, „STOS PROBNY: brak unzip"**.
- **`linia-bazowa-pomijane-proba-kontrolna.sh` — druga asercja: SUFIT POMIJANYCH (34).** Próg `-ge` na samych uruchamianych nie łapie commitu, który dokłada wzorzec pomijania I nowy test naraz — liczba stoi w miejscu, a pokrycie spada. Teraz mierzone są obie strony podziału (49 plików `testy/*.sh` = 15 uruchamianych + 34 pomijane). Poprawiony też komentarz uzasadniający próg 15: mówił „stan po dołączeniu `golden-migawka-proba-kontrolna.sh` do pomijanych", co było nieprawdą — ten plik nie został nigdzie dołączony, podpada pod istniejący wzorzec `*-proba-kontrolna.sh`. Dowód: sufit 5 → EXIT=1 („POMIJANYCH 34, sufit 5"), próg 99 → EXIT=1, bez wady → EXIT=0.
- **`zamek-stosu-probnego-proba-kontrolna.sh` — asercja na liczbie użytkowników sprawdza teraz DOKUMENTY, nie własny literał.** Komentarz obiecywał porównanie „z liczbą w CHANGELOG-u i `lib-golden-stos.sh:27`", a kod porównywał policzoną liczbę z literałem obok. Dokumenty niosą tę liczbę słownie, więc próba sprawdza dodatkowo, czy oba mówią to samo słowo. Dowód: liczba 10 → EXIT=1 i trzy komunikaty rozjazdu · liczba 42 (brak formy słownej) → EXIT=1 · bez wady → EXIT=0.
- **`k7-stos-rundy.sh` — ręczne sprzątanie to DWIE komendy, nie jedna.** Nagłówek podawał sam `docker rm -f -v probny_wp` i nazywał to sprzątaniem, a `zwin_stos` (`lib-stos-probny.sh:159-160`) kasuje też bazę `wp_probny`; po samej pierwszej komendzie baza zostawała.
- **`bramki.sh` — kolejność warunków w PODSUMOWANIU taka sama jak w `bramka()`.** Jeden przebieg podawał DWA różne powody pominięcia goldena: „tryb tekstowy — zmiana nie dotyka PHP” przy biegu i „wymaga Dockera” w podsumowaniu. Gałąź dockerowa stała przed gałęzią trybu (uwaga 7 recenzji #272).
- **`lib-golden-stos.sh` 1.0.1 — `chown` sprawdzany (`|| return 1`).** Opis PR-a obiecywał „wynik każdej operacji jest sprawdzany”, a kod sprawdzał trzy z czterech (uwaga 8).
- **`golden-migawka-proba-kontrolna.sh` 2.0.1 — pułapka `trap` zakładana PRZED `mktemp`.** Wyjście przez `padnij` na ścieżce PRZEWIDZIANEJ (zajęty stos) zostawiało katalog `golden-mutant-*` na dysku, bo pułapka powstawała dopiero po nim (uwaga 9).
- **`proby-kontrolne-przelot.sh` — komentarz mówił „Te cztery”, tablica ma pięć pozycji** (uwaga 6).
- **`lib-stos-probny.sh` 1.0.1 — `docker rm -f -v`: zwijanie stosu próbnego kasuje ANONIMOWY wolumen `/var/www/html`, który obraz WordPressa zakłada przy każdym `docker run`.** Zmierzone 2.09: 83 wiszące wolumeny po 83 MB z trzech dni (39 z jednego dnia), 6,5 GB, dysk 100 % i stanięty runner CI. Do sprzątnięcia po sobie na maszynie: `docker volume prune -f` (tylko wolumeny bez kontenera).
- **`bramki.sh` — 13. bramka `golden` (`PROG_PELNY` 13; bez Dockera bramka POMINIĘTA jawnie i próg 12) + `proby-kontrolne-przelot.sh` (`golden-migawka-proba-kontrolna.sh` w `WYMAGA_DOCKERA`, sufit pominięć 5→6).** Znalezisko `I-24` (1.09): golden „kupujący dostaje to, co kupił” (PR #258, błąd wskazany przez recenzenta zewnętrznego) był jedynym strażnikiem naprawy migawki produktu i nic go nie wołało, a jedyny sposób uruchomienia wgrywał kod gałęzi na WSPÓLNY stos i go tam zostawiał. Spust `testy/golden-stos-probny.sh` biegnie na stosie próbnym od zera (~15 s), kalibracja `testy/golden-migawka-proba-kontrolna.sh` 2.0.0 mutuje kopię wtyczki w katalogu tymczasowym (4/4 mutanty złapane, czysty kod EXIT=0).
- **`security-wersje.sh` — pilnuje też DATY STANU, i to wobec WSZYSTKICH wtyczek.** Dokument
  bezpieczeństwa deklarował stan starszy niż wydanie, które opisuje: scalenia rozstrzygały konflikt
  w `SECURITY.md` regułą „wygrywa wyższy numer” i przeciągały **cały wiersz**, więc razem z numerem
  wjeżdżała cudza data. Zdarzyło się to dwa razy w jednej kolejce PR-ów (#275 — numer ChatBota
  cofnięty, czerwone CI; #274 — data cofnięta o dwa dni), a strażnik milczał, bo pilnował wyłącznie
  numerów (`grep 'Stan na|data|date'` → 0 trafień). Reguła: data stanu nie może być starsza niż
  data **najnowszego wydania spośród wszystkich** `wtyczki/*/CHANGELOG.md`.
  ⛔ Pierwsza wersja porównywała datę wyłącznie z CHANGELOG-iem sklepu, choć pętla numerów chodzi
  po trzech wtyczkach, a wypis melduje „sprawdzonych wtyczek 3” — ChatBot ma osobny rytm wydań,
  więc jego wydanie nowsze od sklepowego przeszłoby niezauważone (recenzja #274, runda 8, WAŻNA 2).
  Komunikat podaje teraz nazwę wtyczki, która wyznaczyła datę. Brak wiersza `Stan na` albo brak
  jakiegokolwiek datowanego wydania = kod 2 (nie zmierzone), nie „czysto”.
- **`security-wersje-proba-kontrolna.sh` — z 7 przypadków do 11.** Doszły: data starsza niż wydanie
  (kod 1), dokument bez daty stanu (kod 2, podłoga), data starsza niż wydanie **innej** wtyczki
  (kod 1) i kontrola dodatnia do tej pary (dokument świeższy od obu → kod 0).
  ⛔ Nowa kontrola odsłoniła brak w MATERIALE próby: atrapy dokumentów nie miały ani daty stanu,
  ani CHANGELOG-u, więc trzy istniejące przypadki zaczęły padać na kod 2 „nie mam z czym porównać”
  — wyglądało to jak regresja strażnika, a było atrapą NIE TEGO RODZAJU CO PRODUKCJA. Atrapy buduje
  teraz `zbuduj_dokument`.
- **`k7-koniec-proba-kontrolna.sh` — atrapa niesie datę stanu i CHANGELOG.** Nowa kontrola daty
  wywaliła próbę kontrolną **cudzej** bramki: jej piaskownica budowała `SECURITY.md` bez wiersza
  `Stan na`, więc warunek 9 dostawał FAIL i cała próba padała na warunku, którego nie testuje
  (`K7-KONIEC POMYLIL SIE 5 RAZY`, EXIT=1 → po naprawie EXIT=0). To ten sam brak, który ten plik
  opisał już raz we własnym komentarzu: „potrzebuje w atrapie tego samego RODZAJU plików co produkcja”.
  ⭐ Klasa, nie wpadka: **nowe wymaganie w narzędziu psuje każdą cudzą piaskownicę, która go nie zna** —
  przy zmianie bramki najpierw `grep -rl "<nazwa>" narzedzia-qa/ testy/`, kto jeszcze ją woła.
- **`linia-bazowa.sh` — strażnik A6-F1 nie wchodzi już na wspólny stos.** Pętla `testy/*.php` brała
  `schemat-wersja-po-sprawdzeniu-test.php`, który robi `DROP TABLE` na tabeli opinii i woła
  `sklepag_aktywacja()` (strony, katalog, zadanie cykliczne, przebudowa reguł adresów) — na kontenerze
  `strona_wp_wordpress` i bez przywracania bazy między testami, wbrew własnemu nagłówkowi pliku.
  Dopisany wzorzec pomijania z powodem. Spust jest RĘCZNY: ktoś musi uruchomić przelot
  bez zakresu przed wydaniem albo samą próbę kontrolną na stosie próbnym. `k7-koniec.sh`
  przelotu NIE uruchamia — czyta jego log i przy braku każe go uruchomić.
- **`zdarzenia-webhooka.sh` 1.0.0 (NOWE) + `bramki.sh` (12 bramek, `PROG_PELNY` 12,
  `PROG_TEKSTOWY` 7; wpis do manifestu w TYM SAMYM PR, bo bez niego strażnik manifestu
  słusznie świeci na czerwono — kontrola 1a) — lista zdarzeń Stripe w instrukcji == lista
  obsługiwana w kodzie.** Powód z życia i o pieniądzach (znalezisko A7-F1, 1.09): instrukcja
  instalacji kazała zaznaczyć w Dashboardzie **dwa** zdarzenia, a `zakup.php` obsługiwał
  **jedenaście** w czterech rodzinach (sprzedaż, sesja niedoszła, zwrot, obciążenie zwrotne).
  Klient wykonujący instrukcję dosłownie nie dostawał powiadomienia ani o zwrocie, ani
  o reklamacji płatności w banku — a Pulpit liczy przychód jako `kwota - zwrocono`, więc
  oddane pieniądze zostawały w statystyce sprzedaży na zawsze, bez jednego ostrzeżenia.
  Bramka porównuje zbiory w OBIE strony (dokument gubi nazwę / dokument każe zaznaczyć coś,
  czego kod nie obsługuje), zapis `rodzina.*` liczy jako komplet rodziny, a `CHANGELOG.md`,
  `archiwum-procesu/` i `audyt/` wyklucza świadomie — opisują przeszłość, nie dzisiejszy
  kontrakt. Dwie kontrole dodatnie w środku: rodzina obsługiwana prefiksem bez ani jednej
  nazwy w kodzie ORAZ zero dokumentów z listą kończą się kodem **2**, nie zielonym zerem.
  Próba kontrolna `zdarzenia-webhooka-proba-kontrolna.sh` — dziesięć przypadków, w tym ten,
  dla którego bramka powstała: nowe zdarzenie w kodzie przy niezmienionym dokumencie.
- **`bramki.sh`** (naprawy B-5 i B-10 z polowania na infrastrukturę, 31.08): pobierane
  `actionlint`/`gitleaks` weryfikowane sumą SHA256 na każdej z trzech dróg (PATH, cache,
  świeże pobranie) — wcześniej `curl` szedł prosto do `chmod +x`, a cache w `/tmp` był brany
  po samej nazwie pliku, więc kto mógł tam zapisać, ten wykonywał swój kod jako wykonawca CI.
  Bramka `gitleaks` dostała drugi przelot `skan_archiwow()` po zawartości śledzonych archiwów
  — skan historii widzi zip jako jeden blob i nie zaglądał do środka (zmierzone: klucz luzem
  wykrywany, ten sam klucz w zipie niewidoczny). Podłoga na mianownik zero: zero rozpakowanych
  plików przy niezerowej liczbie archiwów pada, zamiast meldować „czysto”.
  Dodana furtka `BRAMKI_TYLKO_FUNKCJE=1` wyłącznie dla próby kontrolnej (ścieżka CI jej nie widzi).
- **`bramki-narzedzia-proba-kontrolna.sh` 1.0.0 (NOWE)** — 8 przypadków do dwóch napraw wyżej,
  w tym podłożona binarka w cache i archiwum nie do rozpakowania. Woła PRAWDZIWE funkcje
  z `bramki.sh`, nie ich kopie.
- **`security-wersje.sh` 1.0.0 (NOWE)** — czy `SECURITY.md` mówi prawdę o wersjach wtyczek
  (znalezisko D-1: dokument mówił „wydane 0.9.25”, kod stał na 0.9.28, i nie pilnowało go nic).
  Czyta numery z prozy dokumentu; uruchomiony spoza korzenia odmawia kodem 2.
  **Od 3.09 sprawdza także DATĘ STANU** — patrz wpis w `[Niewydane]`.
- **`security-wersje-proba-kontrolna.sh` 1.0.0 (NOWE)** — przypadki: rozjazd, brak wpisu,
  brak dokumentu, brak nagłówków `Version`, uruchomienie spoza repo, prawdziwy plik repo.
  ⚠️ Liczby przypadków ten wiersz **celowo nie podaje** — była tu „7”, a plik ma dziś 11
  (recenzja #274, runda 8: wiersz kłamał liczbą, choć opis PR-a sam mówił o wzroście).
  Liczbę rozstrzyga wykonanie: `grep -c '^sprawdz ' narzedzia-qa/security-wersje-proba-kontrolna.sh`.
- **`k7-koniec.sh`** — dziewiąty warunek zamknięcia: zgodność `SECURITY.md` z wersjami wtyczek.
  Kod 2 („nie zmierzone”) liczy się jako FAIL, nie jako cisza. Poprawione oba napisy o liczbie
  warunków — dodanie warunku unieważniało własny opis skryptu.
- **`k7-koniec-proba-kontrolna.sh`** — atrapa uzupełniona o wtyczkę z nagłówkiem `Version`
  i `SECURITY.md` (bez nich nowy warunek wywracał 5 przypadków, których nie dotyczy) oraz
  2 nowe przypadki dowodzące, że warunek 9 jest naprawdę WPIĘTY, a nie tylko istnieje.
- **`pakuj-dla-klienta.sh` 1.1.0** (recenzja PR o README, D3–D5): samokontrola otwiera zipy wtyczek (`unzip -t`, nagłówek Version == wersja z nazwy, INSTRUKCJA.md w każdym, PONAD-SPECYFIKACJE.md w sklepie), kontrola śladów wymaga istnienia plików, oba katalogi tymczasowe sprzątane trapem także przy porażce.
- **`testy/odsylacze-strony-tytulowej.sh` 1.0.0 (NOWE)** — README.md i PRZECZYTAJ-MNIE.txt odsyłają tylko do plików, które istnieją (obrazki, odsyłacze, ścieżki repo, obietnice zawartości paczek). Recenzja PR o README, KRYTYCZNA+WAŻNA.
- **`pakuj-dla-klienta.sh` 1.0.0 (NOWE)** — zbiorcza paczka wydania jednym poleceniem: 3 zipy wtyczek (wersje z naglowkow, zipy musza istniec w boty/paczki/) + PRZECZYTAJ-MNIE.txt + INSTRUKCJA-INSTALACJI.md + diagramy PNG + SHA256SUMS.txt; SAMOKONTROLA na rozpakowanej paczce (komplet, sumy, kazdy plik w sumach, brak sladow wewnetrznych w dokumentach). Wynik do wydanie/ (poza repo).
- **`plugin-check-paczki.sh` 1.1.0** (recenzja PR o raporcie, W3): raport przywiązany do WERSJI paczki — `docker cp` i `wp plugin install` sprawdzane po kodzie wyjścia, wersja w kontenerze == wersja z nazwy zipa, inaczej raport tej paczki NIE jest zapisywany (EXIT=1); pierwszy wiersz raportu = slug, wersja, zip, WP/PHP, data.
- **`plugin-check-paczki.sh` 1.0.0 (NOWE) — RAPORT (nie bramka) z oficjalnego Plugin Check WordPress.org na PACZKACH klienta**, w stosie próbnym `probny_wp`; surowe wyniki do `audyt/plugin-check/<slug>.txt`, triage w `audyt/PLUGIN-CHECK-30-08.md`. Świadomie poza CI (wykonawca własny, 3,8 GB RAM).
- **`bramki.sh`** — gitleaks po `--log-opts=HEAD` zamiast wszystkich referencji: klon runnera/recenzenta ma `refs/pull/N/*` zamkniętych PR-ów, więc zatruty commit ze SKASOWANEJ gałęzi (#194) palił bramkę na zawsze (#195 W1; lekcja = `bramka-poswiadczenia` 1.1.1). **`gitleaks-proba-kontrolna.sh` 1.0.0 (NOWE)** — klucz na cudzej gałęzi przechodzi, w historii HEAD pada.
- **`k7-koniec.sh` 1.3.6** (#196 W2/W3): wyjątki bez komentarzy `#`, dopasowanie dokładne po kolumnach, MARTWE wyjątki sprawdzane po wszystkich wierszach (także do nieistniejącego raportu, także ostatni bez `\n`); 1a: dokładnie JEDEN wiersz `CELE:`. Próba 1.3.3: +3. **`gitleaks-proba-kontrolna.sh` 1.1.0** (#196 W1/D1/D2): komenda gitleaks czytana Z `bramki.sh` (cofnięcie `HEAD`→`--all` psuje próbę), ścieżka cache jak w bramce. **`mapa-licznik.sh` 1.3.0**: `--odcisk` wypisuje tylko hash.
- **`k7-koniec.sh` 1.3.5** — warunek 8: plik `audyt/sklep/raporty/WYJATKI-WARUNEK-8.txt` (koordynatorka; `raport|cel|powód`) dla werdyktów, których dowód jest komendą (AN-011, AN-021); wyjątki wypisywane, MARTWY wyjątek = FAIL (#195 W2: warunek był niespełnialny z założenia). Próba kontrolna 1.3.2: +3 przypadki (wyjątek → GO, martwy → NO-GO, cel przeredagowany → 1b).
- **`mapa-licznik.sh` 1.3.0** — zamrożenie pilnuje ODCISKU TREŚCI celów (`tresc=` = sha256 „ID · treść” bez statusu/odsyłacza), nie tylko liczby (#195 D3: podmiana celu przy tej samej liczbie przechodziła); `--odcisk`; stary wiersz bez `tresc=` = ostrzeżenie.
- **`k7-koniec.sh` 1.3.4** — wynik liczy KONTROLE, nie warunki: „NO-GO — N z M kontroli niespełnionych (8 warunków zamknięcia)” zamiast „N warunków” — także w linii „TRZY LICZBY DLA WŁAŚCICIELA” (#194 W3) (recenzja #191 D1: „8 warunków niespełnionych” przy 5 PASS czytało się jak „padło wszystko”); licznik PASS.
- **`bramka-poswiadczenia.sh` 1.1.2** — nonce w URL bez wymogu `?`/`&` przed `_wpnonce=` (zrzuty HTML niosą `&amp;`; recenzja #190 W1, fixture w próbie kontrolnej).
- **`bramka-poswiadczenia.sh` 1.1.1** — historia od `HEAD` zamiast `--all`: runner GitHuba widzi `refs/pull/N/head` ZAMKNIĘTYCH PR-ów (niezmienialne), więc #188/#189 z martwym sekretem testowym paliłyby bramkę na zawsze; zmierzone w CI #190 (6 trafień w `refs/pull/188|189`).
- **`bramka-poswiadczenia.sh` 1.1.0** (recenzja #189 W1/W2): nonce w URL (`[?&]_wpnonce=`) i JSON (`"nonce":`), `whsec_` od 6 znaków, faza 2 = skan HISTORII (`git grep` po `git rev-list --all`); próba kontrolna 1.1.0: 5 podłożonych przypadków, w tym cookie-jar w historii klonu usunięty `git rm` (musi paść).
- **`bramka-poswiadczenia.sh` 1.0.0 (NOWE) + `bramki.sh` (9 bramek, PROG_PELNY 9, PROG_TEKSTOWY 6; wpis do manifestu = OSOBNY PR wg zasady manifestu) — żywe poświadczenia w repo:** cookie-jar WP (`#HttpOnly_`, `wordpress_logged_in_<32hex>`), zrzuty z `_wpnonce`, sekrety `whsec_…`/`sk_test_…`; skan całego repo (śledzone + nieśledzone). Powód: recenzja #188 r2 KRYTYCZNA — gitleaks i `slady` (tylko `wtyczki/`) puściły ważną sesję admina. Próba kontrolna `bramka-poswiadczenia-proba-kontrolna.sh` (cookie-jar i sekret podłożone MUSZĄ paść).
- **`k7-koniec-proba-kontrolna.sh`** — przypadek „5 soczewek w 6 wierszach” (L5→L3 dubel): stary licznik wierszy przepuszczał, nowy (1.3.3) pada „soczewek=5” (recenzja #189 W3).
- **`k7-koniec.sh` 1.3.3** — banner wypisuje wersję ze stałej `WERSJA` w nagłówku (#188 r2 W4: banner mówił 1.3.2 przy kodzie 1.3.3).
- **`k7-stos-rundy.sh` 1.0.1 → 1.0.2** — bramka „wersja wtyczki w kontenerze == wersja z nagłówka pliku głównego w zipie” (nagłówek obiecywał „aktywna w podanej wersji”, skrypt tylko wypisywał — #187 r2 a); opis EXIT 2 rozszerzony o brak zipa/sekretu/narzędzia (#187 r2 b); wpis w PLAN §9 (#187 r2 c). Zmierzone 26.08 na zamrożonej paczce: `✅ wersja w kontenerze = wersja z zipa (0.9.13)`, EXIT 0.
- **`k7-koniec.sh` 1.3.2 → 1.3.3** — warunek 2 liczy RÓŻNE soczewki w tabeli rozliczenia (kolumna 2, `sort -u`), nie wiersze — L3 z dwoma mutantami liczył się podwójnie, etykieta „soczewek” była szersza niż pomiar (recenzja #188 D8, klasa 11).
- **`k7-stos-rundy.sh` 1.0.0 (NOWE) — czysty stos pod rundę K7:** `probny_wp` na `wordpress:6.7-php8.1-apache` (PLAN-K7 §9) z PODANYM zipem
  (runda A = paczka z mutantami, B = zamrożona), permalinki, `blog_public=0`, konta `audytor-admin`/`audytor-sub` z sekretów, dowody:
  sha256 zipa, wersja, wzorzec w kodzie kontenera, debug.log 0 notice, HTTP 200. Stos ZOSTAJE. Zmierzone 26.08: EXIT 0, WP 6.7.2 / PHP 8.1.32.
- **`mapa-licznik.sh` 1.2.0 → 1.2.1** — nagłówek opisuje oba zestawy statusów (mapa / `--przelot`) i wcięty punkt (#186 r2 D4).
- **`k7-stos-rundy.sh` 1.0.0 → 1.0.1** — konta porównywane z OCZEKIWANĄ rolą + hasło dowiedzione `wp user check-password` (wrapper `wp` scala stderr — „niepuste” ≠ „jest”; #187 WAŻNA); brak sekretu = EXIT 2; `wymagaj_narzedzi`; debug.log liczony PO renderze strony.
- **`k7-koniec.sh` 1.3.1 → 1.3.2** — komunikat warunku 5 przy nieaktualnym LICZNIKU podaje komendę naprawczą (`mapa-licznik.sh … --przelot`), jak 1a (#186 r2 D1).
- Dokumenty (#186 r2 D1–D4): przelot wie, kto liczy jego LICZNIK; PLAN §8b „13 linii PASS/FAIL (8 warunków)”; nagłówek `mapa-licznik.sh` opisuje oba zestawy statusów; CHANGELOG bez liczby trafień grepa.
- **`mapa-licznik.sh` 1.1.1 → 1.2.0 — `--przelot`:** zestaw statusów przelotu człowieka (⬜ ✅ 😖 🐞) i jego format LICZNIKA;
  jedyne narzędzie uprawnione do `CELE:` w `PRZELOT-CZLOWIEKA-SKLEP.md` (recenzja #186 W2: D2 wymagał LICZNIKA, którego nic nie utrzymywało).
- **`k7-koniec.sh` 1.3.0 → 1.3.1** — warunek 5 liczy przelot przez `mapa-licznik.sh --przelot --tylko-licz` (nieznany znacznik = FAIL);
  `git show` rozliczenia osobno od `sed` (bez `pipefail` kod potoku był kodem seda — martwy człon, #186 D3).
- **`k7-koniec-proba-kontrolna.sh` 1.3.0 → 1.3.1 — 31 przypadków**: `\|` w komórce rozliczenia + `proby=3` → NO-GO (bez osłony pola
  przesuwają się i bramka ŁAGODNIEJE — dlatego przypadek łączy `\|` z licznikiem, inaczej przechodziłby zawsze; #186 W1), przelot z ❓.
- **`k7-koniec.sh` 1.2.0 → 1.3.0 — recenzja #184 r2:** (W1) warunek 2 rozstrzyga gałąź mutantów z FALLBACKIEM `origin/` i rozróżnia
  „brak gałęzi” od „brak pliku” (świeży klon dawał fałszywy FAIL — klasa W8); (W2) `\|`→`\x01` także w tabeli rozliczenia (bliźniak
  W6, każde wejście `awk -F'|'` w skrypcie czyta dane, które przeszły przez `sed 's/\\|/\x01/g'` — ROZL, WIERSZE, AN_WIERSZE i wiersze `$w` z WIERSZE; bez liczby, bo grep liczy też komentarze); (W4) warunek 8 egzekwuje słownik `werdykt` i `rodzaj_skutku` WPROST ze
  `SCHEMAT-RAPORTU.json`; (D2) warunek 5 porównuje LICZNIK przelotu z policzonym, jak 1a dla mapy.
- **`k7-koniec-proba-kontrolna.sh` 1.2.0 → 1.3.0 — 29 przypadków** (+ gałąź tylko jako `origin/` → GO, brak gałęzi → NO-GO,
  LICZNIK przelotu nieaktualny, werdykt spoza słownika, `rodzaj_skutku` spoza słownika); atrapa z `SCHEMAT-RAPORTU.json` i LICZNIKIEM przelotu.
- **Prompty L1/L3/L5/STOPKA** — słownik werdyktów = enum schematu (`NIE DA SIĘ SPRAWDZIĆ`, `przypuszczalny`); goldeny opisane jako
  REGUŁY kontraktowe, nie scenariusze (W3). **`dokumentacja-zgodnosc-sklep-proba-kontrolna.sh`** — rozjazdy liczone tylko z przypadków
  `ocz=1`, bez magicznej `-1` (D1).
- **`k7-koniec.sh` 1.1.0 → 1.2.0 — trzy klasy z recenzji #184 r1:** (W4) warunek 5 ma PODŁOGĘ ≥ 27 punktów przelotu i liczy 😖
  jako niedomknięte (pusty/obcięty plik przechodził jako „komplet”); (W5) warunek 4 czyta CAŁĄ tabelę sekcji `## Anomalie`,
  wiersz bez ID `AN-NNN` = FAIL (dotąd wiersz z innym ID był niewidzialny); (W6) `\|` w komórkach rejestru zamieniane na `\x01`
  przed splitem `awk -F'|'` — potok w kolumnie dowodu nie przesuwa już statusu/prób (klasa bliźniacza W2).
- **`k7-koniec-proba-kontrolna.sh` 1.1.0 → 1.2.0 — 24 przypadki** (+ przelot obcięty, przelot z 😖, anomalia z ID bez `AN-`,
  wcięty punkt mapy, potok `\|` w dowodzie → nadal GO); atrapa rejestru z nagłówkami `## Znaleziska`/`## Anomalie` jak produkcja
  (bez nich nowy parser sekcji nie widział tabeli — wada atrapy złapana przez próbę).
- **`mapa-licznik.sh` 1.1.0 → 1.1.1** — (D1) wcięty punkt `  - ⬜` = nieznany znacznik (EXIT 1), nie cicha zguba; (D2) walidacja
  `--zamroz <sha>` PRZED zapisem LICZNIKA.
- **`testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh`** — jawne podsumowanie „PRZYPADKÓW: N/N OK · rozjazdów M” (W2:
  „12/12” w trzech miejscach było sumą rozjazdów, nie przypadków — poprawione na 10/10 przypadków, 12 rozjazdów).
- **`k7-koniec.sh` 1.0.0 → 1.1.0 — trzy naprawy po recenzji #182 r2:** (W2) warunek 2 „proby ≤ 2” był MARTWY — `$NF` czytał pustą
  kolumnę za końcowym `|`; teraz pole 7 po indeksie. (W8) 7b porównuje `k7-zamrozenie^{commit}` — tag jest adnotowany, goły
  `rev-parse` dawał sha obiektu tagu = fałszywy FAIL na prawdziwym stanie. (D7) bramka NIE PISZE do audytowanej mapy: woła
  `mapa-licznik.sh --tylko-licz`, nieznany znacznik celu = FAIL z listą linii, nieaktualny LICZNIK w pliku = FAIL.
- **`k7-koniec-proba-kontrolna.sh` 1.0.0 → 1.1.0 — każdy przypadek psuje DOKŁADNIE JEDEN warunek** (stary „FAIL+proby=3” naraz
  maskował martwy licznik prób); atrapa z tagiem ADNOTOWANYM jak produkcja; nowe przypadki: proby=3 przy PASS, znacznik ❓,
  LICZNIK nieaktualny, mapa bajt w bajt ta sama po przebiegu (D7). 20/20.
- **`mapa-licznik.sh` 1.0.0 → 1.1.0 — `--tylko-licz` (bez zapisu) i `--sprawdz-zamrozenie` tylko do odczytu;** wiersz „- ” z nieznanym
  znacznikiem = EXIT 1 z numerami linii w każdym trybie (cel nie może zniknąć z sumy bez śladu). Powód: recenzja #182 r2 D7.
- **`stos-proba-kontrolna.sh` — dwa odczyty tabel SKLEPU** (`tabele` widzi `wp_sklepag_zamowienia`, `wiersze wp_sklepag_produkty`
  odpowiada) — strażnik dla `stos.sh` 1.3.1. Powód: recenzja #182 r2 D5.
- **`testy/dokumentacja-zgodnosc-sklep.sh` — sekcja C w obie strony z licznikiem** (W3: etykiety z KODU, nie z zaszytej listy; nowa
  pozycja menu bez readme i pozycja readme bez kodu padają) · **NOWA `testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh`** (W4:
  10 przypadków = 12 rozjazdów, jawna liczba rozjazdów na przypadek, podsumowanie „N/N przypadków”) · wpięta do `.github/RECENZENT-BRIEFING.md` i allowlisty recenzenta (W1).
- **`slady-wewnetrzne.sh` 1.1.1 → 1.2.0 — numer zgłoszenia BEZ słowa „PR"** (po myślniku/nawiasie/„nr"/„zgłoszenie"/„uwaga": `— #170)`), bez łapania kolorów `#000`; zmierzone na 3 wtyczkach: 1 trafienie właściwe, 0 fałszywych. Próba kontrolna bramki paczki dostała przypadek „goły numer". Powód: recenzja #182 WAŻNA 1.
- **`stos.sh` 1.3.0 → 1.3.1 — okno widzi tabele sklepu** (`wp_sklepag_` w białej liście i w filtrze `tabele`); dotąd recenzent i audytor nie mogli zajrzeć do żadnej tabeli sklepu (recenzja #182 DROBNA 6, CO-WISI).
- **`k7-koniec.sh` 1.0.0 (NOWE) — koniec audytu K7 ogłasza skrypt, nie uczucie.** Osiem warunków zamknięcia
  z PLAN-K7 §8b (mapa 0 ⬜ + zamrożenie, kalibracja z gałęzi mutantów, rejestr bez otwartych KRYTYCZNYCH/DUŻYCH
  i bez DO WERYFIKACJI, `naprawa_proby ≤ 2`, strażnik z dowodem przy każdym ZAMKNIETE, anomalie 0 OTWARTA,
  przelot człowieka komplet, 26 prób kontrolnych EXIT 0, sha256 paczki i tag `k7-zamrozenie`, parser raportów JSON:
  SPEŁNIONE bez plik:linia i BRAK bez próby kontrolnej = odrzucone). Powód: plan v1 mówił „liczone skryptem",
  a skryptu nie było (krytyka r1). Próba kontrolna: `k7-koniec-proba-kontrolna.sh`.
- **`mapa-licznik.sh` 1.0.0 (NOWE) — jedyny, któremu wolno pisać wiersz LICZNIK mapy polowania;** `--zamroz <sha>`
  i `--sprawdz-zamrozenie` (mapa rosnąca po zamrożeniu = EXIT 1). Powód: mapa botów miała licznik martwy (0/36 po
  pełnym przebiegu) — odhacza koordynator, liczy skrypt.
- **`k7-stare-wersje.sh` 1.0.0 (NOWE) — paczki starych wersji sklepu z historii gita** (0.2.0 / 0.5.0 / 0.9.8) do testu
  aktualizacji w miejscu (`dowod-aktualizacji.sh`), do katalogu POZA repo. Powód: test migracji przez podmianę numeru
  schematu = fałszywy sukces (krytyka r1); artefakt odtwarzalny skryptem, nie plik luzem (lekcja banku mutantów).
- **`lib-stos-probny.sh` 1.0.0 → 1.0.1 — obraz stosu próbnego parametrem `STOS_PROBNY_OBRAZ`** (domyślnie bez zmian);
  K7 stawia czysty stos na `wordpress:6.7-php8.1-apache` = deklarowane minimum wtyczek, nie tylko nasz stos 7.1/8.4.
- **`stos.sh` 1.2.0 → 1.3.0 — `opcja` maskuje sekrety.** Opcje, których nazwa wskazuje sekret
  (klucz/haslo/token/secret/api/key/pass), wypisują tylko długość i 2-znakowy ogon (ogon przy
  ≥ 12 zn.). Powód: 23.08 recenzent zobaczył w logach akcji pełny klucz API dostawcy. Błąd
  stosu z podpowłoki jest przekazywany dalej (EXIT 2). Próba kontrolna sprawdza maskowanie
  i brak wzorca klucza w wyjściu. Granica świadoma: filtr po NAZWIE, nie po treści.

### Naprawione

- **`slady-wewnetrzne.sh` 1.1.0 → 1.1.1 — imię z zespołu łapane po RDZENIU (`[Dd]zid`),
  nie po jednej formie słowa.** Wzorzec `Dzidek` z 1.1.0 widział tylko mianownik i
  przepuścił „Dzidka"; poprawka na `Dzidk` (na gałęzi, bez podbicia wersji) złapała odmiany,
  ale przestała widzieć mianownik — dziura przesunięta, nie zamknięta. Dowód: `grep -E 'Dzidk'`
  na linii „feedback Dzidek" nie daje trafienia. Próba kontrolna nie miała ŻADNEGO przypadku
  z imieniem, więc regresja przeszła po cichu — dostała dwa (mianownik i odmiana); na wzorcu
  `Dzidk` przypadek z mianownikiem PRZECHODZI NIEZAUWAŻONY, na `[Dd]zid` oba złapane.
  Wpis 1.1.0 niżej mówi o `Dzidek` — tak było w 1.1.0 i tak zostaje w historii.

### Dodane

- **`adresy-w-dokumencie.sh` 1.0.0 — mechaniczna kontrola, czy dokument nie kłamie
  o WŁASNYCH odsyłaczach.** Powstało, bo 21.08 **trzeci raz** dokument opisujący jakąś
  klasę błędu sam ją popełnił: przegląd „co robi kod, czego nie ma w instrukcji" miał
  nieścisłe adresy `plik:linia`. Pisanie O KLASIE nie chroni przed KLASĄ — uwaga siedzi
  na treści, nie na formie. Chroni tylko kontrola **po** napisaniu.

  Dla każdego `sciezka/plik:linia[,linia]` skrypt sprawdza: czy plik istnieje, czy linia
  istnieje, i **czy treść się zgadza** — w cytowanej linii (albo w nazwie funkcji, wewnątrz
  której stoi) musi wystąpić nazwa albo liczba, którą obiecuje wiersz dokumentu. Adres
  do innego repozytorium jest pomijany jawnie, nie po cichu.

  ⭐ Tryb **SUROWY** włącza sam dokument znacznikiem `<!-- adresy: potwierdzalne -->`:
  wtedy adres, którego wiersz nie nazywa po imieniu, jest **błędem**. Bez tego rozdziału
  narzędzie krzyczałoby na każdy stary dokument w repo i zostałoby wyłączone w tydzień.

  Kalibracja: przestawienie `ai.php:47` → `:470` (linia istnieje, treść inna) i
  `powitanie.php:32` → `:99999` (poza plikiem) — **oba złapane, EXIT=1**.

  Pierwszy przebieg znalazł **7 z 7 adresów nie do użycia** w świeżo napisanym dokumencie
  (brak przedrostka `wtyczki/<wtyczka>/`) oraz — po dopisaniu sekcji do instrukcji ChatBota —
  **12 adresów w przeglądzie liczb, które przestały pasować**, bo mój własny dopisek
  przesunął numery linii w cytowanym pliku. Bez tej kontroli oddałbym oba dokumenty.

### Naprawione

- **KLASA, nie egzemplarz: `wp option get` w teście pustości — pięć miejsc, nie jedno.**
  Naprawa znacznika awarii AI zamknęła tylko jedno wystąpienie. `grep` po wszystkich
  skryptach QA pokazał następne cztery, w tym **strażnika luki L1** (`chatl_blad_nonce`,
  `dowod-zachowania-chatbot.sh:229`), który meldował „odrzucony nonce ZOSTAŁ ODNOTOWANY"
  **zawsze** — także gdyby wtyczka nie odnotowała niczego. Fałszywy pozytyw akurat tam,
  gdzie pilnujemy luki.

  Wspólny odczyt `opcja()` mieszka teraz w `lib-stos-probny.sh`, czyli w **jednym**
  miejscu dla wszystkich trzech dowodów. Pomiar różnicy:

  ```
  $ wp option get nie_ma_takiej_opcji            → Error: Could not get … (NIEPUSTE)
  $ wp eval '… get_option( "nie_ma_takiej_opcji" ) …'  → (PUSTE)
  ```

  Sprawdzone też, gdzie ten wzorzec **NIE** jest błędem: `dowod-z-paczki.sh:123` pyta
  o **kod wyjścia**, nie o tekst — i to jest poprawne, zostaje bez zmian.

- **`dowod-aktualizacji.sh` — dowód wymagał, żeby KAŻDA aktualizacja podnosiła schemat.**
  Wydanie dokumentacyjne (1.5.7 → 1.5.8, tak samo 1.3.0 → 1.3.1) schematu nie rusza,
  więc na zamrożonej parze paczek dowód meldował **dwie wady produktu, których nie ma**.
  Prawdziwym warunkiem nie jest „numer urósł", tylko **„baza zgadza się z kodem"**:
  awarią jest brak wpisu, rozjazd z kodem albo numer, który poszedł **w dół**.
  Po poprawce `chatbot-live 1.5.7 → 1.5.8` przechodzi wszystkie pięć dowodów (EXIT=0),
  a próba kontrolna dalej łapie wszystkie trzy podłożone awarie (EXIT=0) — mutantowi
  „nowa wersja nie ma kodu migracji" zmieniła się tylko fraza, którą dowód go nazywa.

### Naprawione

- **`dowod-zachowania-chatbot.sh` — dziura D3 była wadą PRZYRZĄDU, nie bota. Trzy błędy,
  wszystkie w narzędziu.** Po naprawie dowód na paczce **1.5.8** daje `EXIT=0`:
  odpowiedź idzie z modelu (`silnik = ai` zapisany przez bota), po odcięciu klucza
  bot zjeżdża do reguł, znacznik zapisuje się z kodem 401, panel pokazuje ostrzeżenie,
  a po powrocie API znacznik znika sam.

  1. **Pytał bota szybciej niż próg pułapki na boty.** Sesja i pytanie szły jedno po
     drugim, a wtyczka traktuje pytanie zadane wcześniej niż `CHATL_PULAPKA_SEKUND`
     od wydania sesji jako bota: odpowiada tekstem awaryjnym, **nie woła modelu i nie
     zapisuje ani jednego wiersza rozmowy**. Cały dowód D3 mierzył pułapkę zamiast
     trybu AI. Przerwa czytana jest teraz **z kodu**, nie wpisana liczbą.
  2. **Czytał brak opcji jako jej obecność.** `wp option get` nieistniejącej opcji
     wypisuje „Error: Could not get …", a wrapper `wp()` scala stderr do stdout — więc
     `[ -z "$(wp option get …)" ]` widział niepusty napis. Dwie pozycje meldowały wadę
     produktu tam, gdzie produkt zachował się poprawnie. Pytamy teraz PHP-em.
  3. **Szukał w panelu zdania, którego panel nie pisze** („Ostatnie wywołanie API nie
     powiodło się" zamiast „Tryb AI nie działa"). Kotwica zgniła po zmianie tekstu.

- **`dowod-zachowania-chatbot.sh` i `dowod-zachowania-segregator.sh` — domyślna paczka
  brana z NAGŁÓWKA WTYCZKI, nie wpisana numerem.** Wpisany numer starzeje się przy
  pierwszym podbiciu wersji i wtedy dowód cicho bada **poprzednią** paczkę, która dalej
  leży obok nowej. Złapane przy zamrożeniu: domyślna wartość wskazywała `1.5.7`, choć
  zamrożona jest `1.5.8`.

### Naprawione

- **`dowod-zachowania-segregator-proba-kontrolna.sh` — mutant M1 zakotwiczony WZORCEM,
  nie numerem linii.** Było `sed -i '145s/.*/…MUTANT/'`, czyli podmiana CAŁEJ linii 145
  bez względu na jej treść — takie podstawienie udaje się **zawsze**, więc kontrola
  „czy mutacja weszła" zawsze mówiła TAK. Po refaktorze, gdy prawdziwe przypisanie
  `$na_zywo` przesunie się gdzie indziej, podłożylibyśmy mutanta w **przypadkowym
  miejscu**, dowód padłby z innego powodu, a kalibracja zostałaby odhaczona jako zdana.
  Teraz kotwicą jest `$na_zywo = segm_przenoszenie_dozwolone();` — brak dopasowania
  = brak podstawienia = zgniła kotwica widoczna. (M2 i M3 były zakotwiczone wzorcem
  od początku.)

- **…a przy sprawdzaniu tej poprawki wyszło, że sama kontrola zgniłej kotwicy MILCZAŁA.**
  `zmutuj()` biegnie w **podpowłoce** (`$(zmutuj …)`), więc zwiększony w niej licznik
  błędów ginął razem z nią, a komunikat wypisany na stdout wpadał do zmiennej z nazwą
  zipa zamiast na ekran — do tego `&&` pomijało wtedy sprawdzenie. Efekt: zgniła kotwica
  kończyła się **ciszą i EXIT=0**, czyli próba kontrolna kłamała dokładnie tak, jak miał
  kłamać mutant, którego miała złapać. Diagnostyka idzie teraz na **stderr**, a liczenie
  błędów przeniesione do powłoki głównej (`mutuj_i_sprawdz`).

  Sprawdzone symulacją refaktoru (kotwica celowo zepsuta): **BLAD … (mutacja NIE weszla
  — kotwica zgnila)**, `EXIT=1`. Na poprawnych kotwicach cały przebieg dalej `EXIT=0`.

- **Próba kontrolna sprawdza teraz NAJPIERW SAMĄ SIEBIE.** Pierwszy przypadek podkłada
  kotwicę, której w pliku NIE MA, i wymaga, żeby `zmutuj` odmówił **kodem wyjścia**.
  Bez tego cała reszta kalibracji stoi na założeniu, że mutanty w ogóle wchodzą do
  plików — a to jest dokładnie to założenie, które się dziś nie sprawdziło. Przypadek
  jest tani (sam `sed` na rozpakowanej kopii, zero dockera), więc biegnie zawsze:
  `OK   kotwica, ktorej nie ma w pliku, jest odrzucana (EXIT=1)`.

### Dodane

- **`stos.sh` 1.1.0 → 1.2.0 — `wiersze-gdzie <tabela.kolumna=wartosc>`.** Wąskie okno
  recenzenta umiało policzyć tylko CAŁĄ tabelę, więc dowodu opartego na liczbie wierszy
  z `silnik = ai` **nie dało się powtórzyć cudzą ręką** — recenzent musiał wierzyć na
  słowo. Teraz: `bash narzedzia-qa/stos.sh wiersze-gdzie wp_chatl_rozmowy.silnik=ai`.
  Wszystkie trzy części wejścia przechodzą przez tę samą białą listę znaków co reszta
  skryptu (`[a-zA-Z0-9_-]`), tabela dodatkowo przez białą listę tabel; SQL składa skrypt,
  nie dzwoniący (`silnik=ai';DROP` → ODMOWA, sprawdzone).

  Przy okazji **jedna** funkcja rozmawia z bazą zamiast dwóch: wywołań dockera w skrypcie
  jest teraz **5, a nie 6** — strażnik z próby kontrolnej (pada, gdy ich przybywa, bo to
  wygląda jak obejście białej listy) zostaje nietknięty i przechodzi.

- **`dowod-zachowania-segregator.sh` 1.0.0 — dowód zachowania Segregatora NA SKRZYNCE,
  z paczki.** Odpowiednik `dowod-zachowania-chatbot.sh`, którego dla Segregatora nie było
  wcale, a punkt D listy domknięcia wymaga go dla OBU botów. Trzy dowody: przebieg
  **na sucho nie rusza ani jednej wiadomości**, przebieg **na żywo przenosi i daje się
  cofnąć**, **retencja kasuje wpis sprzed 91 dni i NIE rusza świeższego**.

  ⛔ Stan skrzynki mierzony **niezależnie, Pythonem (`imaplib`) z zewnątrz**, nie klasą
  `Segm_Imap` z wtyczki — test mierzący tym samym kodem, który sprawdza, potwierdza sam
  siebie. Skrzynka warsztatowa (`segm_imap`) nietknięta: skrypt stawia **własny**
  serwer poczty (`probny_imap`, greenmail) i kasuje go po sobie.

  Dwie pułapki złapane po drodze i zamknięte w kodzie: heredok `<<-` zjadał wcięcia
  skryptu Pythona (pomiar wracał pusty, a puste porównanie „przed == po" świeciło na
  **zielono** — dowód, który przechodzi, bo nic nie zmierzył), oraz `docker-proxy`
  przyjmujący połączenie, zanim greenmail podepnie się pod port (czekamy teraz na
  **powitanie protokołu**, nie na samo TCP). Doszła twarda bramka: jeśli pomiar nie
  zwróci liczby wiadomości w INBOX, skrypt **pada**, zamiast porównywać puste napisy.

- **`dowod-zachowania-segregator-proba-kontrolna.sh`** — trzy mutanty celujące
  w obietnice sprzedażowe (na sucho przestaje chronić pocztę, cofnięcie nic nie cofa,
  retencja kasuje wszystko) plus kontrola odwrotna na czystej paczce. Każdy mutant
  sprawdzany dwa razy: czy **wszedł do pliku** i czy dowód padł **z właściwego powodu**.

### Naprawione

- **`dowod-z-paczki.sh` — próba trybu regułowego dla Segregatora mierzyła własny błąd.**
  Podawała maila jako **tablicę** i reguły przez `ARRAY_A`, a silnik czyta `$mail->nadawca`
  i `$regula->pole` — tak wołają go wszystkie trzy miejsca w produkcji
  (`skrzynka-przebieg.php:213`, `admin-probka.php:130`, `silnik.php:175`), zawsze wierszami
  z bazy, czyli obiektami. Efekt: siedem Warningów, „ODPOWIEDZ:Array" i **dowód nie
  wychodził na poprawnej wtyczce**. Próba buduje teraz obiekty i sprawdza KONKRETNY
  wynik (`kategoria=Faktury regula=7`), a nie „cokolwiek niepustego".

### Zmienione

- **Bramka `slady` wpisana do `.github/manifest-bramek.txt` — z siódmej „ponad
  podłogą" staje się TWARDYM MINIMUM.** Do tej pory działała, ale nikogo nie
  zatrzymywała twardo: dało się ją wyjąć z `bramki.sh` i strażnik manifestu nawet
  by nie mrugnął, bo nie było jej na liście obowiązkowych. Mieliśmy już w tym
  projekcie przypadek, że ta sama informacja dotarła trzy razy i nic nie zatrzymała,
  bo wszystko było doradcze — ten wpis to zamyka. Skutek uboczny wpisu: `PROG_PELNY`
  nie może już zejść poniżej **siedmiu**, bo manifest wymienia siedem bramek.

  Trzy nowe przypadki w `bramki-proba-kontrolna.sh`, w tym jeden **end-to-end**:
  ślad podłożony do prawdziwego pliku wtyczki, uruchomione dokładnie to polecenie,
  które woła `quality.yml` — `BRAMKA slady: EXIT=1`, `NIEZALICZONE BRAMKI: 1`,
  cały przebieg **EXIT=1**. Plik wtyczki wraca z kopii zapasowej niezależnie od wyniku.

### Naprawione

- **`slady-wewnetrzne.sh` 1.0.0 → 1.1.0 — wzorzec „nasz obieg pracy" łapie NASZE
  SŁOWNICTWO, nie tylko nasze numery.** W Segregatorze został **bliźniak**
  wyczyszczonych śladów: „Zarzut z pierwszej **rundy krytyk**" mówi o naszym obiegu
  dokładnie to samo, co „recenzja PR #27", tylko innymi słowami — i wzorzec go nie
  widział. Poprawienie samego zdania byłoby naprawą egzemplarza, więc wzorzec dostał
  `\bkrytyk`, `egzaminator` i `Dzidek` (granica słowa przy `krytyk` jest konieczna:
  bez niej wzorzec łapie „diakrytyków"). Dwa nowe przypadki w próbie kontrolnej.
  Skutek na repozytorium: segregator 11 → 12 znalezionych (wszystkie zamknięte poza
  czterema w dokumentacji), sklep 31 → 42 — w tym **jeden ślad w tekście widzianym
  przez użytkownika**, nie w komentarzu: `sklep-agentow/includes/admin-zamowienie.php`
  wypisuje w panelu „(podgląd — egzaminator widzi mail bez skrzynki)".

### Dodane

- **`dowod-aktualizacji.sh` 1.0.0 — dowód AKTUALIZACJI W MIEJSCU.** U klienta
  aktualizacja zdarza się częściej niż czysta instalacja, a to zupełnie inna
  ścieżka kodu: **podmiana plików nie przechodzi przez hook aktywacji**. Wszystko,
  co wtyczka robi „przy instalacji" (tabele, kolumny, opcje startowe), musi przy
  aktualizacji zrobić kod porównujący wersję schematu przy `plugins_loaded`.
  Gdy go nie ma albo jest błędny, klient dostaje wtyczkę pytającą bazę o kolumnę,
  której u niego nie ma — a **czysta instalacja nigdy tego nie pokaże**, bo tam
  hook aktywacji zrobił wszystko. To dziura **D4** z materiału audytowego.

  Skrypt instaluje **starą** paczkę, zdejmuje odcisk schematu i zostawia własny
  wiersz-znacznik, podmienia pliki na **nową** paczkę BEZ deaktywacji, puka na
  stronę (dopiero żądanie odpala kod aktualizacji) i sprawdza pięć rzeczy:
  wersja wtyczki jest nowa · **wersja schematu poszła w górę** i zgadza się
  z nowym kodem · schemat **przybył** i nic z niego **nie znikło** ·
  **stare dane przetrwały** co do wiersza i treści · zero notice, warning,
  deprecated i fatal.

  Starą paczkę bierze z `boty/paczki/`, a gdy jej tam już nie ma (nowe wydanie
  kasuje poprzednią) — **wyciąga ją z historii gita**. To celowe: ścieżka
  aktualizacji dotyczy właśnie wersji, których w drzewie roboczym już nie ma.

  Znacznik jest **własny**, nie z seeda instalatora: przetrwanie danych, które
  instalator i tak wpisuje od nowa, niczego nie dowodzi.

- **`dowod-aktualizacji-proba-kontrolna.sh` — trzy mutanty i kontrola odwrotna.**
  Nowa wersja bez kodu migracji (schemat stoi) · migracja kasująca starą kolumnę
  (znika miejsce na dane klienta) · migracja czyszcząca tabelę (dane klienta giną).
  Każdy przypadek sprawdza kod wyjścia **oraz czy dowód nazwał przyczynę**.
  Mutant wchodzi pod nazwą prawdziwej paczki, więc próba **zawsze przywraca**
  oryginał z kopii — także gdy przebieg zostanie przerwany.

- **`lib-stos-probny.sh` 1.0.0 — wspólny warsztat obu dowodów.** Stawianie
  czystej instalacji z `WP_DEBUG`, odciski bazy i schematu, log błędów PHP,
  sprzątanie. Wyciągnięte z `dowod-z-paczki.sh`, żeby poprawka w jednym miejscu
  (jak naprawa kolejności odcisku) trafiała do obu dowodów — ta sama zasada,
  co „napraw klasę, nie egzemplarz". Doszedł **odcisk schematu** (tabela + kolumna
  + typ): sama lista tabel nie wystarcza, bo aktualizacja dokładająca KOLUMNĘ
  wygląda po niej identycznie jak aktualizacja, która nie zrobiła nic.
  Odcisk schematu pyta bazę z **ucieczką podkreślnika** (`ESCAPE '!'`): w SQL
  `LIKE` `_` jest metaznakiem „dowolny jeden znak", więc `wp_chatl%` łapałoby
  też `wpXchatl…`. To klasa #2 ze ściągi, a tu jest biblioteka WSPÓLNA —
  nawyk stąd rozniósłby się na każdy kolejny dowód, który z niej skorzysta.

- **`dowod-z-paczki.sh` 1.0.0 — powtarzalny dowód Z PACZKI, nie z repozytorium.**
  Kupujący nie dostaje repozytorium, dostaje ZIP — więc wszystko, co sprawdzamy na
  drzewie roboczym, sprawdza **inny artefakt niż sprzedawany**. Skrypt bierze paczkę,
  stawia **czystą instalację WordPressa z włączonym `WP_DEBUG`** i przechodzi pełne
  życie wtyczki, odpowiadając kodem wyjścia na cztery pytania:

  1. czy wgranie **z zipa** i aktywacja przechodzą bez notice, warning, deprecated
     i fatal (log `WP_DEBUG_LOG`, nie „wygląda czysto"),
  2. czy **silnik regułowy odpowiada, gdy klucza API nie ma wcale**,
  3. czy po deinstalacji z pełnym czyszczeniem **różnica odcisków bazy jest PUSTA** —
     i różnica jest **pokazywana**, nie streszczana,
  4. czy **sekrety właściciela znikają ZAWSZE**, także gdy właściciel nie zaznaczył
     „usuń wszystkie dane".

  Działa dla **obu botów** (tablica z sekretami, opcją czyszczenia i wywołaniem
  silnika wpisana w skrypcie, nie zgadywana), bierze paczkę jako argument i kończy
  się **kodem 1**, gdy cokolwiek z tego nie wyjdzie.

  ⛔ **Nie dotyka stosu warsztatowego.** Stawia własną instalację (kontener
  `probny_wp`, baza `wp_probny`) i tylko ją kasuje — wgrywanie i kasowanie wtyczek
  na wspólnym stosie zniszczyłoby cudzą robotę w toku. Odczyty idą przez
  `stos.sh --stos probny`; drugiego dostępu do stosu tu nie ma.

  Odcisk pomija wpisy, które WordPress dopisuje w tle niezależnie od wtyczki —
  **lista pominięć jest wypisywana przy każdym przebiegu**, żeby „pusta różnica"
  nie stała się pustym słowem.

- **`dowod-z-paczki-proba-kontrolna.sh` — cztery mutanty i kontrola odwrotna.**
  Psuje w kopii paczki po jednej rzeczy naraz, dokładnie tej, której dany dowód
  pilnuje: uninstall przestaje kasować klucz API, wtyczka rzuca notice przy starcie,
  pełne czyszczenie zostawia opcję w bazie, silnik regułowy milczy bez klucza.
  Każdy przypadek sprawdza **kod wyjścia ORAZ czy dowód nazwał przyczynę** — dowód
  padający na wszystko z tym samym komunikatem jest bezużyteczny przy szukaniu wady.
  Paczka w repo nie jest dotykana ani razu.
- **`dowod-zachowania-chatbot.sh` 1.0.0 — dowód ZACHOWANIA z paczki (punkt D).**
  `dowod-z-paczki.sh` odpowiada na pytania o cykl życia wtyczki; ten skrypt na trzy
  pytania o zachowanie: **D3** klucz API odcięty w trakcie (zjazd do reguł + znacznik
  w panelu), **D4** strona z cache i nonce starszy niż życie (**luka L1**), **D6**
  retencja naprawdę kasuje. Każdy dowód ma przypadek **BEZ wady**: świeży nonce ma
  działać za pierwszym razem, wpis sprzed jednego dnia ma przeżyć sprzątanie, a przy
  sprawnym API znacznika awarii ma nie być.
  ⛔ **Zero internetu w dowodzie**: „odcięcie klucza" udaje własny punkt końcowy
  w kontenerze przez filtr `chatl_ai_url`, który wtyczka sama daje integratorom.
  Dowód zależny od cudzego serwera nie jest dowodem, tylko prognozą pogody.
  ⚠️ Skrypt **wymaga `lib-stos-probny.sh` z PR #105** — do czasu jego scalenia nie
  da się go uruchomić z samego maina.

- **`slady-wewnetrzne.sh` 1.0.0 — nowe narzędzie i SIÓDMA bramka `slady`
  w `bramki.sh` (próg pełny 6 → 7, tekstowy 3 → 4).** Wyczyszczenie 20 śladów
  z ChatBota było naprawą EGZEMPLARZA: jutro wjeżdża nowy komentarz
  z odsyłaczem do `testy/…` albo numerem naszego zgłoszenia i wszystko wraca.
  Bramka zamrożenia sprawdza to raz, przy zamrażaniu; bramka `slady` sprawdza to
  przy **każdym PR-ze**, także tekstowym — komentarz w dokumentacji to ten sam problem.
  Jedno źródło wzorców dla obu miejsc, żeby dwie listy nie rozjechały się po dopisku.

- **`slady-zaleglosci.txt` — lista DŁUGU, nie lista wyjątków.** 42 ślady, które
  już leżą w repozytorium (segregator-maila 11, sklep-agentow 31, chatbot-live 0),
  są wypisywane przy każdym przebiegu, ale nie blokują — inaczej bramka wywracałaby
  cudze PR-y od pierwszego dnia. Wpis, który przestał do czegokolwiek pasować,
  jest **czerwony**: lista ma się kurczyć, a nie gnić. Przy zamrażaniu lista
  **nie obowiązuje** — `paczka-zgodnosc.sh` woła skaner z pustą listą, bo paczka
  oddawana klientowi ma mieć zero śladów.

### Naprawione

- **`bramki-proba-kontrolna.sh` — trzy zgniłe kotwice.** Dołożenie siódmej bramki
  unieważniło trzy przypadki na raz: fraza „wykonano 5 bramek", `PROG_PELNY=6`
  i pełne brzmienie tablicy `BRAMKI=(…)` w sed-zie. Dwa przypadki cicho przestały
  cokolwiek podkładać, jeden przechodził niezauważony. Kotwice są teraz bez
  zaszytych liczb i bez przepisywania całej tablicy. Do tego **sześć nowych
  przypadków** dla bramki `slady` (TODO, adres dev, ścieżka drzewa, numer PR-a,
  martwy wpis na liście, kontrola odwrotna na czystej wtyczce).

### Zmienione

- **`paczka-zgodnosc.sh` 1.0.0 → 1.1.0 — bramka zamrożenia dostała dwie kontrole,
  których zgodny numer i suma kontrolna NIE łapią.**

  **(C) Wydanie z opisem, nie z samym numerem.** Nagłówek `## [1.5.7]` bez ani
  jednej linii pod spodem przechodził przez porównanie wersji, bo numer był
  poprawny — brakowało tego, co ten numer znaczy. Sprawdzane w `CHANGELOG.md`
  **i** w sekcji `= wersja =` w `readme.txt`, bo to drugie widzi kupujący w sklepie.

  **(D) Paczka bez naszych śladów wewnętrznych.** Zip równy repozytorium co do
  bajtu jest wciąż zipem, w którym siedzi `TODO`, ścieżka `testy/…`, adres
  `localhost:8890` albo „uwaga recenzenta do PR #83". Kontrola zgodności z repo
  takiego śladu **nie widzi z definicji** — ślad jest w repo tak samo jak w paczce.
  Skanowana jest **rozpakowana paczka**, w czterech klasach wzorców: znacznik
  roboczy, adres maszyny dev, ścieżka naszego drzewa, nasz obieg pracy.

  Kalibracja: na stanie sprzed naprawy stara bramka 1.0.0 dawała **EXIT=0**,
  nowa na tym samym stanie **EXIT=1** i 20 wypisanych śladów.

- **`paczka-zgodnosc-proba-kontrolna.sh` — sześć nowych podłożonych przypadków**
  (pusty wpis w CHANGELOG-u, pusta sekcja w readme, TODO w paczce, adres dev,
  ścieżka `testy/…`, numer PR-a) plus kontrola „mutacja naprawdę weszła do pliku".
  Stan czysty badany jest teraz na `chatbot-live`: paczka segregatora 1.3.0 ma
  dziś własne ślady wewnętrzne, więc jako wzorzec czystości się nie nadaje.

- **`.github/workflows/recenzent.yml`** — `--allowedTools` dostał `test-php.sh`
  i jego próbę kontrolną, żeby recenzent mógł uruchomić testy PHP z `testy/`.
  Świadomie **nie** dostał `Bash(php testy/:*)` ani `Bash(docker exec:*)` —
  powód wpisany w komentarzu nad listą, żeby następny czytający nie „poprawił"
  tego z powrotem.
- **`.github/RECENZENT-BRIEFING.md`** — zdanie o tym, że testy `testy/*.php`
  uruchamia się przez `test-php.sh`, bo samo `php testy/X.php` zawsze pada.

- **`.github/workflows/recenzent.yml` — recenzent URUCHAMIA nasze testy, nie tylko
  je czyta.** Do `--allowedTools` doszły **23 pozycje**: wszystkie skrypty z `testy/`
  (poza jednym, niżej), cztery próby kontrolne z `narzedzia-qa/` oraz
  `bramki.sh` — komplet sześciu bramek jednym poleceniem, którego recenzent dotąd
  nie mógł wywołać, przez co pisał „nie uruchomiłem phpcs/phpstan".

  Wpisane **jawnie, po jednym**, zamiast wzorca z gwiazdką: lista ma dać się
  przeczytać w całości i ma boleć przy dopisywaniu, bo każda pozycja to zgoda na
  wykonanie kodu w checkoucie recenzenta.

  ⛔ **Celowo poza listą: `testy/seed-demo.sh`** — jako jedyny pisze do **wspólnego
  stosu warsztatowego** (zamówienia demo w bazie), a nie do własnego checkoutu.
  Reszta prób podmienia pliki wyłącznie u siebie i przywraca je po sobie,
  a checkout runnera jest jednorazowy i bez prawa zapisu do repo.

  ⛔ **Też poza listą: `dowod-z-paczki` i `dowod-aktualizacji` wraz z próbami** —
  stawiają kontenery i biegną kilkanaście minut, czyli dłużej niż cała recenzja.
  To kalibracja przed oddaniem, nie narzędzie recenzenta.

- **`.github/RECENZENT-BRIEFING.md`** — akapit „NASZE TESTY MASZ URUCHAMIAĆ, NIE
  CZYTAĆ". Zdanie „nie uruchomiłem próby kontrolnej" wolno napisać tylko wtedy,
  gdy próba **padła albo odmówiła** — wtedy z cytatem jej wyjścia. Sama allowlista
  nie wystarcza: przy #103 recenzent miał już narzędzie i nadal nie wiedział,
  że ma po nie sięgnąć.


### Dodane

- **`test-php.sh` 1.0.0 — okno recenzenta na URUCHOMIENIE testu PHP.** PR #109
  otworzył recenzentowi całe `testy/`, ale wyłącznie skrypty powłoki. Testy
  `testy/*.php` zostały poza zasięgiem, bo **nie są zwykłymi skryptami PHP**:
  każdy zaczyna się od `WP_PLUGIN_DIR` i `require` plików wtyczki, więc
  `php testy/import-csv-test.php` kończy się `Undefined constant "WP_PLUGIN_DIR"`
  i kodem 255. Uruchamia je wyłącznie wp-cli **wewnątrz kontenera**
  (`docker cp` + `wp eval-file`). Wzorzec `Bash(php testy/:*)` w `--allowedTools`
  otworzyłby więc prawo do polecenia, które i tak zawsze pada, a
  `Bash(docker exec:*)` oddałby całą maszynę.
  Skrypt jest **jedną nazwaną operacją**, dokładnie jak `stos.sh`: dzwoniący
  podaje wyłącznie NAZWĘ testu (wzorzec `[a-z0-9-]`, więc `../`, ścieżka
  bezwzględna, `;` i `$(…)` odpadają zanim cokolwiek dotknie dockera), katalog
  `testy/` jest wpisany w skrypcie, a wołane jest `wp eval-file` na pliku
  Z REPOZYTORIUM, nigdy `wp eval` (kod z wiersza poleceń). `test-php.sh lista`
  wypisuje, co wolno uruchomić. Plik testowy jest kasowany z kontenera także
  wtedy, gdy test padł.
  ⚠️ To **nie** jest okno czysto odczytowe, w odróżnieniu od `stos.sh` — testy
  piszą do bazy warsztatowej (własnym przedrostkiem, sprzątając po sobie).

- **`test-php-proba-kontrolna.sh` — 20 kontroli.** Dwanaście prób wyjścia poza
  `testy/` i przemycenia polecenia w nazwie — każda musi paść **na wzorcu nazwy**,
  nie „gdzieś dalej". To rozróżnienie jest tu sednem: przy poluzowanym wzorcu
  większość ścieżek i tak kończy się kodem 2 (`/etc/passwd.php` nie istnieje),
  więc próba sprawdzająca sam kod wyjścia **przechodziła na dziurawym skrypcie**.
  Dlatego przypadkiem rozstrzygającym jest ścieżka, która po doklejeniu `.php`
  trafia w **istniejący** plik poza `testy/` (`../wtyczki/chatbot-live/chatbot-live`),
  a próba wymaga w wyjściu konkretnego powodu odmowy. Do tego trzy kontrole
  czytające treść samego skryptu (zero `wp eval`, katalog jako stała, liczba
  wywołań dockera bez wzrostu), odmowa z listą przy nieznanym teście, dwie
  kontrole nadmiarowych argumentów i kontrola odwrotna: `import-csv-test.php`
  ma się naprawdę uruchomić i przejść.

- **`stos.sh` 1.0.0 — czysto odczytowe okno recenzenta na żywy stos.**
  Pomiar wyjściowy: w recenzjach z 20.08 padło **91** zdań „czego nie
  sprawdziłem", z czego **51** brzmiało „nie mam uruchomionego WordPressa",
  „nie zweryfikowałem paczki", „nie sprawdziłem renderowania" — a warsztatowy
  WordPress (`http://localhost:8890`), jego baza i łapacz maili stoją na tej
  samej maszynie co wykonawca CI. Dostępu nie było wyłącznie dlatego, że nikt
  go nie dał.

  **Recenzent nie dostaje dockera ani powłoki — dostaje nazwane operacje:**
  `stan`, `strona <sciezka>`, `naglowki <sciezka>`, `opcja <nazwa>`,
  `opcje <prefiks>`, `wtyczki`, `tabele`, `wiersze <tabela>`, `maile`,
  `ostatni-mail`, `wersja-wp`. Polecenie jest zawsze wpisane w skrypcie;
  dzwoniący podaje co najwyżej jedną wartość i tylko taką, która przejdzie
  twarde sprawdzenie wzorca. Nazwy tabel dodatkowo przez białą listę prefiksów.
  Nie ma operacji „uruchom", nie ma przekazywania flag, nie ma wyboru kontenera.

  **Zero zapisu.** Wołane są wyłącznie odczytowe podpolecenia `wp`; jedyny SQL
  składa skrypt i zawsze jest to `SELECT COUNT(*)`. Liczenie wierszy idzie do
  kontenera bazy, bo obraz WordPressa nie ma klienta mysql; poświadczenia
  czytane są przez wp-cli z `wp-config` i nigdy nie są wypisywane.

  Gdy stos nie odpowiada albo brakuje narzędzia — **kod wyjścia 2** i zdanie
  wprost, nigdy ciche zielone. Paczek ten skrypt nie dotyka: od nich jest
  `paczka-zgodnosc.sh`.

- **`stos-proba-kontrolna.sh` — 27 kontroli.** Trzynaście prób przemycenia
  własnego polecenia i wyjścia poza białą listę (średnik i podstawienie
  polecenia w ścieżce, `..`, flaga jako trzeci argument, wstrzyknięcie SQL
  w nazwie tabeli, tabela spoza listy) — każda musi skończyć się kodem 2.
  Trzy kontrole czytające **treść samego skryptu**: żadne wywołanie `wp` nie
  może być operacją zapisu, liczba wywołań dockera nie może urosnąć (obejście
  białej listy ma rzucać się w oczy w diffie), a tekst pomocy nie może zawierać
  literalnych sekwencji ucieczki. Jedna kontrola „brak narzędzia to kod 2". I dziesięć odczytów z **żywego stosu**, każdy sprawdzany po treści
  odpowiedzi, nie po samym kodzie wyjścia.
- **`paczka-zgodnosc.sh` 1.0.0 — bramka ZAMROŻENIA produktu.** Sprawdza dwie
  rzeczy, które przy zamrażaniu sprawdza się „na oko" i obie sprawdza się źle.

  **Wersja w pięciu miejscach**, nie w czterech: nagłówek wtyczki, stała
  w kodzie, `Stable tag` w `readme.txt`, najnowsze wydanie w `CHANGELOG.md`
  oraz sekcja `== Changelog ==` w `readme.txt` — o tym piątym łatwo zapomnieć,
  bo to osobny format WordPressa w tym samym pliku co `Stable tag`.
  Osobno pilnowane jest to, czego zgodność pięciu miejsc NIE wyklucza:
  **niepusta sekcja `[Niewydane]` w CHANGELOG-u** oznacza, że w kodzie siedzą
  zmiany ponad ostatnie wydanie, więc paczka z tym numerem nie jest tym, co ten
  numer opisuje.

  **Paczka porównana co do bajtu, w trzy strony:** każdy plik zipa musi mieć
  sumę SHA-256 równą plikowi z repozytorium (sama lista nazw nie mówi nic —
  plik o tej samej nazwie bywa wersją sprzed trzech PR-ów), żaden plik wtyczki
  nie może zostać pominięty przy pakowaniu, a zawartość zipa musi zgadzać się
  z allowlistą w `boty/buduj-paczki.sh`.

  Osobno sprawdzana jest surowa postać wersji z nagłówka: numer, który nie
  wygląda jak numer (np. `1.3.`), przeszedłby przez porównanie „wszystkie mówią
  to samo", bo wszystkie mówiłyby to samo źle (uwaga z recenzji PR #101).

  Brak wtyczki, brak paczki dla wersji z nagłówka albo brak `unzip`/`sha256sum`
  to **kod wyjścia 2**, nie domyślne zielone.

- **`paczka-zgodnosc-proba-kontrolna.sh` — 12 podłożonych rozjazdów.** Cztery
  warianty wersji, która została w tyle (każdy z osobna), kod ponad wersją,
  plik w zipie o tej samej nazwie i innej treści, plik zgubiony przy pakowaniu,
  pasażer na gapę (`.env`), allowlista rozjechana ze zbudowanym zipem, brak
  paczki, wersja, która nie jest numerem — plus kontrola odwrotna, że czysty stan przechodzi. Każdy przypadek
  sprawdza nie tylko kod wyjścia, ale też czy bramka **nazwała** przyczynę.
  Pracuje na kopii drzewa, więc prawdziwe pliki i prawdziwa paczka nie są
  dotykane ani razu.


- **`bramka-recenzji.sh` 1.0.0 — bramka na „niemy zgon" recenzenta.** Akcja
  recenzenta potrafi zakończyć się **zielono, nie napisawszy ani słowa**:
  przy odmowie wymiany tokenu krok trwa dwadzieścia sekund, artefakt z wyjściem
  CLI jest pusty, a check na liście PR-a wygląda identycznie jak po czystej
  recenzji. Zdarzyło się to dwa razy 20.08 i raz doprowadziło do scalenia PR-a
  z otwartą uwagą KRYTYCZNĄ.

  Kryterium jest **podwójne** i musi być spełnione w całości: recenzja trwała
  **dłużej niż 60 s** ORAZ recenzent zostawił **komentarz w trakcie tego
  przebiegu**. Sam czas nie wystarcza (przebieg może mielić i nic nie napisać),
  sama obecność komentarza też nie — przy `track_progress: true`
  komentarz-znacznik powstaje w pierwszych sekundach, więc widnieje także pod
  zgonem po dwudziestu sekundach. Liczone są wyłącznie komentarze `claude[bot]`
  powstałe **od startu tego przebiegu**; wpis z poprzedniej recenzji nie
  uchodzi za dzisiejszą pracę.

  **Wyjątek jest wąski i wymaga OBU warunków naraz:** PR rusza plik
  w `.github/workflows/` ORAZ CLI recenzenta w ogóle nie wystartowało (brak
  pliku z wyjściem). Sam fakt, że PR dotyka CI, nie wystarcza — inaczej
  prawdziwy niemy zgon przechodziłby bokiem dokładnie tam, gdzie i tak nikt nas
  nie sprawdzi. Wyjątek jest **wypisany w logu wraz z żądaniem, żeby PR-a
  przeczytał człowiek** — nigdy ciche przepuszczenie.

  Brak danych wejściowych (znacznika czasu, tokenu, `jq`, odpowiedzi API) to
  **kod wyjścia 2**, nie domyślne zielone.

- **`bramka-recenzji-proba-kontrolna.sh` — 14 podłożonych przypadków.**
  Sześć wariantów niemego zgonu (ma paść), trzy warianty legalnego wyjątku
  (ma przepuścić), pułapka „PR rusza CI, ale CLI ruszyło i zdechło" (ma paść —
  wyjątek nie może przykryć prawdziwego zgonu), dwie kontrole odwrotne
  (prawdziwa recenzja ma przechodzić) i dwie kontrole wejścia. Dane podstawiane
  plikami, więc próba działa bez pukania do GitHuba, tak samo na biurku i w CI.

### Zmienione

- **`dowod-z-paczki.sh` → 1.0.1** — korzysta ze wspólnej biblioteki zamiast
  własnej kopii stawiania stosu. Zachowanie bez zmian: przebieg na paczce
  ChatBota i pełna kalibracja (4 mutanty + kontrola odwrotna) po refaktorze
  dają ten sam wynik co przed nim.
  Domknięte dwie uwagi z recenzji: komentarz nagłówka mówił `WERSJA 1.0.0`,
  a stała w kodzie `1.0.1` (**klasa #4 — wersja rozjechana**, tym razem
  wewnątrz JEDNEGO pliku), oraz `LIKE '${PREFIKS}%'` liczył opcje bez ucieczki
  podkreślnika (**klasa #2 — `esc_like`**: `chatl_%` łapało też `chatlX…`).
  Ucieczka idzie przez jawne `ESCAPE '!'`, nie przez odwrotny ukośnik —
  ukośnik przeżyłby dzisiejszą drogę do bazy, ale nie musi przeżyć następnej.

- **`sprawdz-manifest.sh`** — czwarta kontrola: **rozjazd wersji wewnątrz
  jednego skryptu** `narzedzia-qa/*.sh` (komentarz nagłówka kontra stała
  `WERSJA`). Egzemplarz z recenzji #105 naprawiony ręcznie, ale klasa zostaje
  zamknięta tutaj, żeby następny refaktor nie przywiózł jej z powrotem.
  Skalibrowane: na kodzie sprzed naprawy strażnik pada z **EXIT=1** i nazywa
  plik, po naprawie **EXIT=0**.

- **`stos.sh` → 1.1.0** — wybór stosu z **zamkniętej listy dwóch pozycji**
  (`--stos warsztat|probny`). To nadal nie jest wybór kontenera przez dzwoniącego:
  kontenery i adresy są wpisane w skrypcie, a nazwa spoza listy to odmowa z kodem 2.
  Potrzebne, żeby dowód z paczki czytał swoją czystą instalację **tym samym oknem**,
  zamiast dorabiać drugi dostęp do WordPressa.
  Przy okazji naprawiony tekst pomocy: heredok `POMOC` nie interpretuje ucieczek,
  więc wpisane w nim `\n\n` wypisywało się jako cztery znaki zamiast dwóch pustych
  linii. `stos-proba-kontrolna.sh` dostał kontrolę na nawrót — **pada na kodzie
  sprzed naprawy**, przechodzi po niej.

- **`.github/workflows/recenzent.yml`** — `--allowedTools` dostał trzy skrypty:
  `stos.sh`, `paczka-zgodnosc.sh` i `instrukcja-liczby-segregator.sh`. Dwóch
  ostatnich brakowało od PR-ów #94 i #101, przez co recenzent dwa razy pisał,
  że nie mógł uruchomić bramki dostarczonej w recenzowanym PR-ze.
- **`.github/RECENZENT-BRIEFING.md`** — akapit „MASZ ŻYWY STOS": zanim
  recenzent napisze w „czego nie sprawdziłem" zdanie o braku WordPressa,
  paczki, renderowania albo zawartości bazy, ma o to **zapytać stos i podać
  odpowiedź**. Takie zdanie wolno napisać tylko wtedy, gdy `stos.sh` sam
  odpowiedział, że nie działa — wtedy z cytatem jego komunikatu.

- **`.github/workflows/recenzent.yml`** — doszedł krok `Znacznik startu
  recenzji` (pomiar czasu) oraz krok `Bramka: recenzja naprawde sie odbyla`
  z warunkiem `!cancelled()`. Przebieg ubity przez `concurrency` nie jest
  niemym zgonem, tylko naszą własną oszczędnością, więc czerwień za to byłaby
  kłamstwem.
