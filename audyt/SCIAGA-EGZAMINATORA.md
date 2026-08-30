# ŚCIĄGA EGZAMINATORA — czego kolega szukał w 2 poprzednich projektach

Źródło: sweep repo `catsnboard` (4+ rundy recenzji kolegi, PR #57/#70/#82/#84/#87-92,
cytaty verbatim z CHANGELOG/PR) + `mp-service-suite` (AUDYT-RAPORT; „16 uwag" z
`test-kolegi-serwis-wp` to nasz red-team PRZED budową — symulacja jego stylu, nie
jego słowa). Zebrane 18.08 subagentem, 45 wywołań. **Kolega będzie szukał TYCH
SAMYCH klas — każda ma tu swoją bramkę u nas albo dopisaną akcję.**

## Co wytknął DOSŁOWNIE (najcenniejsze cytaty/fakty)
- catsnboard: paczka klienta zawierała **KOPERTĘ RECENZENCKĄ** z wewnętrzną notatką
  QA „nie dla klienta" (KRYTYCZNE, PR #57).
- catsnboard: *„w readme dalej jest «czego nie kasuj» xd, nie poprawiłeś tego"* —
  README niespójne z kodem, wytknięte DRUGI raz (PR #87).
- catsnboard: `uninstall.php` nie kasował 6 opcji **w tym KLUCZA API klienta** (PR #84).
- catsnboard: `LIKE '_transient_pnb_%'` bez escape — `_` to wildcard SQL, ryzyko
  kasowania CUDZYCH transientów (PR #84); *„cleanup transientów zawęzić do
  pnb_pl_pary i pnb_plc_* — wtedy będzie elegancko spójne"* (PR #89).
- catsnboard: badge wersji w README nieaktualny — **zapomniany 3×** (PR #70);
  dokumentacja podawała „10→20→40", kod robił „20→40→80→120 min".
- MP: **„kolega widzi 1.1.0"** — tag istniał, ale strona Releases pokazywała stare
  „Latest" (wydanie niepublikowane = dla niego nie istnieje).
- catsnboard: timeout PHP przy 25×retry (~100 s > limit hostingu 30 s);
  licznik dziennego limitu zjadany PRZED sprawdzeniem błędu sieci;
  `insertAdjacentHTML` bez escapowania.

## 7 KLAS BŁĘDÓW → nasze pokrycie → LUKI (zamknięte 18.08)

| # | Klasa (ile razy wracała) | Co ją łapie U NAS | Luka → akcja |
|---|--------------------------|-------------------|--------------|
| 1 | Uninstall niekompletny, zostają opcje/KLUCZ API (×3) | kryterium GOTOWE #9: diff bazy/plików przed-po PUSTY (mierzony); symetria zapis↔sprzątanie w tym samym PR | ✅ dopisane do briefingu: pełne czyszczenie MUSI usuwać klucz API właściciela |
| 2 | `LIKE` z `_`/`%` bez escape → dotyka cudzych danych | — (nie było reguły!) | ✅ NOWA reguła w briefingu+kości: każdy LIKE = `$wpdb->esc_like()` dla wartości, `\_` w stałych; wroga sesja: test „czy sprzątanie nie łapie cudzego wpisu o podobnej nazwie" |
| 3 | Dokumentacja/komentarz KŁAMIE względem kodu (×4) | sweep „co przestało być prawdą" po każdej naprawie (K7b); zimna weryfikacja PR pyta o zgodność opisu | ✅ K7 dostaje jawną soczewkę „dok vs kod": każda LICZBA i obietnica z README/instrukcji sprawdzona w kodzie |
| 4 | Wersje/badge/„Latest" nieaktualne (×4) | bramka wersji sprawdza wpis w CHANGELOG (lekcja MP); K8: JEDNO wydanie | ✅ runbook oddania: po tagu OTWORZYĆ stronę Releases i POTWIERDZIĆ, że „Latest" = oddawana wersja (fakt „kolega widzi 1.1.0") |
| 5 | Ślady wewnętrzne w paczce klienta (koperta QA, adresy dev) | K7: sweep śmieci .bak/.orig/lorem | ✅ sweep rozszerzony JAWNIE o: notatki QA/recenzenckie, adresy dev, TODO/FIXME, nazwiska wewnętrzne |
| 6 | Kod robi WIĘCEJ niż spec bez deklaracji | MAPA-SPEC: statusy INTERPRETACJA/ROZSZERZENIE jawnie | ✅ K8 dostaje plik `PONAD-SPECYFIKACJE.md` w paczce (wzorzec z MP): lista wszystkiego ponad literę notatki (historia rozmów bota, accuracy, log akcji...) |
| 7 | Licznik/stan często zapisywany w `wp_options` (MP #3) | tabele własne na dane sklepu | ✅ briefing: liczniki wywołań AI (stop-loss) do TABELI bota, nie do opcji |

## Dodatkowe akcenty pod tego egzaminatora
- Publikacja wydania = częścią oddania (nie sam tag) — sprawdzić OCZAMI stronę Releases.
- Retry/limity: liczyć DOPIERO po sukcesie operacji (licznik zjadany przy awarii = jego uwaga).
- Timeouty zewnętrznych wywołań < limit PHP hostingu (jego uwaga o 100 s > 30 s) —
  nasz `wp_remote_post` do Stripe/AI: timeout 15 s, jeden retry, nigdy pętla ślepa.
- README/instrukcja czytana przez niego ZDANIE PO ZDANIU dwa razy — niespójność
  wytknięta ponownie boli podwójnie („nie poprawiłeś tego xd").

---

## KLASY DOPISANE 2026-08-27 — z 10 rund recenzji PR #198 (sklep)

Źródło: nie cudze projekty, tylko TEN PR. Dziesięć rund recenzji CI, około trzydziestu uwag,
z czego kod produktu był czysty od rundy 6 — cała reszta dotyczyła NASZEGO SPRAWDZANIA.
Każda klasa niżej ma dowód z tego dnia, bo klasa bez dowodu jest tylko notatką.

| # | Klasa (ile razy wróciła 27.08) | Dowód z tego dnia | Co ją łapie U NAS |
|---|---|---|---|
| 8 | **BLIŹNIAK naprawy** — załatana jedna ścieżka, siostrzana została (×3) | spór naprawiony w rundzie 2, zwrot dopiero w 4, „Wyślij ponownie” w 5 — za każdym razem znajdował to recenzent, po jednym egzemplarzu na rundę | bramka `status-bez-warunku` pyta o CAŁĄ klasę zapisu statusu naraz (11 zapisów; próba kontrolna 11/11) |
| 9 | **DOWÓD SKLEJANY RĘCZNIE, nie wzięty z przebiegu** | opis PR cytował sha z bieżącego przebiegu i liczbę sprzed dwóch rund — plik NIGDY nie miał takiego stanu; werdykt recenzenta: „cytat nie pochodzi z żadnego przebiegu” | `narzedzia-qa/dowody-do-opisu.sh` — uruchamia kontrole i składa blok z ICH wyjść; niczego nie ocenia i nie ukrywa (złapał własny martwy odsyłacz, wypisując `EXIT=2`) |
| 10 | **OPIS PR O RUNDĘ DO TYŁU** — recenzent czyta stan z chwili STARTU przebiegu (×3) | rundy 4, 5 i 10 postawiły tę samą uwagę; między pushem a podmianą opisu mijały dwie minuty i to wystarczyło | ⛔ **LUKA — jedyna klasa z dzisiaj bez bramki.** Kolejność commit → bramki → opis → push jest zapisana i **wracała mimo zapisu**. Do zamknięcia hookiem na `git push` |
| 11 | **BRAMKA O ZAKRESIE WĘŻSZYM NIŻ NAZWA** (×5 na jednym narzędziu) | ta sama bramka po kolei: widziała tylko jedną nazwę zmiennej · pomijała plik bez przypisania tabeli · szukała liniowo · regex parował „co drugi cudzysłów” i **zabrał pokrycie dwóm zapisom w ścieżce pieniędzy** (uwaga KRYTYCZNA) · nie znała `wpdb->update()` z tablicą danych w zmiennej | próba kontrolna ma osobny **test POKRYCIA na liczbie** (8 z 8 kształtów), nie tylko werdykt; bramka stoi na `token_get_all()`, nie na regeksach |
| 12 | **ZERO JAKO SUKCES** — narzędzie nic nie znalazło i świeci zielono | bramka meldowała „0 zapisów — każdy chroniony”, EXIT=0, a grep na tym samym pliku dawał 9 trafień | bramka **PADA** przy zerze znalezisk; próba kontrolna ma mutanta „zero w zakresie to ślepota, nie czystość” |

### Meta-lekcja dnia, ważniejsza od pojedynczych klas

**Cztery razy winne było NARZĘDZIE POMIAROWE, nie mierzony kod.** Fałszywe zero bramki (dwa
razy), test kłamiący o naprawie, która działała (atrapa z wywołaniami stojącymi za blisko
siebie), oraz sonda recenzenta, która mierzyła zapytanie zamiast ścieżki — pomijając białą
listę stojącą osiemnaście linii wyżej. Odruch „sprawdź własne narzędzie, zanim oskarżysz
cudze” zadziałał za każdym razem i za każdym razem oszczędził fałszywą naprawę.

**Notatka nie jest bramką.** Klasa 10 wróciła TRZY razy, mimo że po pierwszym nawrocie
zapisałam ją w pamięci razem z gotową komendą. Wróciła, bo nie miała wyzwalacza w narzędziu.
Te klasy, które bramkę dostały (8, 9, 11, 12), nie wróciły ani razu po jej postawieniu.

**Dla przyszłego audytu:** klasy 8 i 11 są najkosztowniejsze — obie objawiają się jako zielone
światło, więc nie da się ich zauważyć przez patrzenie na wynik. Widać je wyłącznie przez
pytanie „czego to narzędzie NIE widzi", zadane osobno od pytania „co znalazło".


---

## KLASY DOPISANE 2026-08-27 (wieczor) — z 4 rund recenzji PR #199 (naprawa SK-L3-005)

Zrodlo: naprawa JEDNEJ wady w sciezce pieniedzy. CZTERY rundy, uwaga WAZNA w kazdej z pierwszych
trzech. **Runda 1 dotyczyla KODU PRODUKTU** (naprawa opierala sie na stanie, ktory przestawia
webhook — klasa 13 nizej). Dopiero od rundy 2 kod byl czysty, a uwagi dotyczyly juz wylacznie
DOWODU i OPISU — i to rozroznienie jest cala lekcja tego rozdzialu.

| # | Klasa (skad wziecie) | Dowod z tego dnia | Co ja lapie U NAS |
|---|---|---|---|
| 13 | **NAPRAWA ZWEZA OKNO WADY, zamiast zamknac klase** — decyzja oparta na stanie, ktory przestawia ZEWNETRZNE zdarzenie | naprawa czytala `sesje_checkout.stan`, ale `oplacona` zapisuje wylacznie webhook albo reconciliation (ta druga pomija sesje mlodsze niz 120 s). Przez cale okno zaplacona sesja lezy jako `utworzona` — NIEODROZNIALNA od czekajacej. Recenzent odtworzyl wade sonda mimo naprawy | pytanie do KAZDEJ naprawy zaleznej od webhooka/cronu/callbacku: **co widzi kod, ZANIM to zdarzenie dotrze?** Jesli stan jest wtedy nieodroznialny — naprawa nie zamyka klasy; potrzebny warunek NIEZALEZNY od tego zdarzenia (u nas: wiek okna) |
| 14 | **DOWOD Z ZYWEGO STOSU SLEPY NA OPOZNIENIE, ktorego ten stos nie ma** | 2 przeloty E2E przez zywy Stripe, oba EXIT=0, rozne sesje — a wada byla nadal odtwarzalna. Przeloty szly przez `stripe listen` na TEJ SAMEJ maszynie, wiec webhook wracal natychmiast | gdy dwa przypadki testowe maja w bazie IDENTYCZNY wiersz, test NIE odroznia naprawy od jej braku w tym kierunku. Trzeba przypadku odtwarzajacego STAN POSREDNI wprost w bazie (u nas: przypadek C straznika) |
| 15 | **GALAZ RATUNKOWA W MARTWYM KODZIE** — deklarowana obsluga bledu nigdy sie nie wykonuje | straznik mial lapac limiter (429) i konczyc kodem 2; limiter konczy przez `wp_die()`, a to pod `wp eval-file` NIE rzuca wyjatku — uchwyt WP-CLI drukuje `Error:` i konczy proces. `catch` nigdy nie dostal tresci, `exit( 2 )` byl martwy. Commit deklarowal, ze dziala | ⛔ **kazda galaz ratunkowa musi miec wlasne URUCHOMIENIE w opisie.** Nie wystarczy ja napisac — trzeba pokazac kod wyjscia z sytuacji, ktora ma ja wywolac (u nas: 5 przebiegow pod rzad) |
| 16 | **BRAMKA SPRAWDZA ISTNIENIE WPISU, NIE JEGO PRAWDZIWOSC** | `dokumentacja-zgodnosc-sklep.sh` EXIT=0 i `0 rozjazdow`, podczas gdy CHANGELOG i readme mowily o produkcie nieprawde. Bramka sprawdza, czy wpis `[0.9.18]` ISTNIEJE — nie czy jest zgodny ze zmiana | to granica narzedzia, nie jego wada. ⛔ Przy KAZDEJ zmianie zachowania widocznego dla kupujacego teksty klienta czyta CZLOWIEK, nie bramka. Bramka pilnuje obecnosci, sens zostaje po naszej stronie |

### Meta-lekcja tej naprawy

**Kod produktu byl czysty od RUNDY 2, nie od poczatku — i to rozroznienie jest cala lekcja.**
Runda 1 dala uwage WAZNA wprost do `zakup.php` (naprawa opierala sie na stanie, ktory przestawia
webhook) i skonczyla sie zmiana PRODUKTU: znacznikiem czasu w oknie. Dopiero od rundy 2 uwagi
przestaly dotyczyc kodu. Kolejne dotyczyly juz wylacznie DOWODU i OPISU: dowodu (klasa 14), martwej galezi w tescie (15)
i tekstow dla klienta (16). ⛔ Klasa 13 nalezy do RUNDY 1 i jest jedyna, ktora dotyczyla kodu
produktu — nie licz jej wsrod uwag od rundy 2.
To odwrotnosc odruchu, ktory kaze sprawdzac przede wszystkim kod.

⛔ **Klasa 3 (dokumentacja klamie) ma nowy, grozniejszy wariant: dokumentacja klamie WSKUTEK
NASZEJ WLASNEJ ZMIANY.** Zdanie bylo prawdziwe wczoraj i przestalo byc dzisiaj, bo zmienilismy
zachowanie — a nikt nie wraca do tekstu, ktorego nie dotykal. Dwa razy w jednym PR: raz
w DOKUMENTACJA-TECHNICZNA.md (tabela opisana jako informacyjna zaczela rozstrzygac o sciezce
zakupu), raz w CHANGELOG.md i readme.txt (twierdzily, ze ochrona dziala bez zmian, gdy okno
skurczylo sie z 60 s do 5 s). ⭐ **Odruch: po naprawie zapytaj nie tylko czego to dotyka w KODZIE,
ale ktore ZDANIE o tym kodzie przestalo byc prawdziwe.**

---

## KLASY DOPISANE 2026-08-28 — z 4 rund recenzji PR #201 (przelot człowieka po sklepie)

Źródło: naprawa DWÓCH wad znalezionych przez człowieka klikającego produkt, nie przez test.
Cztery rundy. Kod produktu był czysty od rundy 3 — **wszystkie trzy klasy niżej to wady
MOJEGO WŁASNEGO SPRAWDZANIA**, złapane na sobie w trakcie rund, i to jest cała lekcja tego
rozdziału: im lepszy kod, tym bardziej wąskim gardłem staje się przyrząd, którym go mierzę.

| # | Klasa (skąd wzięcie) | Dowód z tych rund | Co ją łapie U NAS |
|---|---|---|---|
| 17 | **TEST KOŃCZĄCY SIĘ NA FUNKCJI, KTÓRA WOŁA `exit` — fałszywa zieleń nie do odróżnienia od zdanego testu** | Pierwsza wersja testu bliźniaka wołała handler opinii jako ostatnią instrukcję. Handler kończy `exit`, więc **cztery ostatnie asercje nigdy się nie wykonały**, a kod wyjścia i tak był ZEROWY — test meldował sukces, nie wykonawszy połowy sprawdzeń. Pod `wp eval-file` nie da się tego przechwycić: `exit` ubija proces, a funkcja zamknięcia biegnie po tym, jak WP-CLI opróżni bufory | Ścieżkę kończącą się `exit` mierzy się **z zewnątrz**: kroki w PHP tylko DRUKUJĄ, a werdykt i kod wyjścia liczy skrypt powłoki, który czyta TREŚĆ (wzorzec: `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh` + trzy kroki `opinia-odrzut-krok*.php`). ⛔ Pytanie kontrolne do każdego testu: **czy ostatnia instrukcja może zakończyć proces?** Jeśli tak — asercje po niej są ozdobą |
| 18 | **POSZERZENIE OKNA POMIARU NIE WYSTARCZA — asercja bywa martwa, bo WARUNEK WADY nie zachodzi** | Recenzent wytknął, że pauza mierzona 300 ms po Enterze nie dowodzi kryterium 2.2.2 (ruch >5 s). Wydłużyłam pomiar do 6,5 s — i asercja **nadal przechodziła nawet z CAŁKOWICIE usuniętą ochroną pauzy**, bo kafel wypadał z ekranu po 16 Tabach, a strażnik postępu pomija nagrania poza kadrem. Mierzyłam ciszę i nazywała się zgodnością | Każda asercja mierząca skutek uboczny musi **sprawdzić własny warunek wstępny i PAŚĆ jako błąd przyrządu**, gdy go nie osiągnęła (`kafel-wideo-klawiatura.js`: jedna asercja wymaga kafla W kadrze, sąsiednia wymaga, żeby WYPADŁ). ⛔ Test kalibruj **podkładając wadę**, nie tylko starą wersję pliku: jeśli asercja nie pada na kodzie bez ochrony, jest martwa — niezależnie od tego, jak długo czeka |
| 19 | **NARZĘDZIE, KTÓRE CICHO OBCINA WEJŚCIE — odbiorca nie ma jak zobaczyć własnej dziury** | Hook startowy wstrzykiwał żywy mózg maszyny poleceniem `head -60`, a plik urósł do 136 linii: **76 linii znikało bez jednego znaku ostrzeżenia**, w tym ścieżka repozytorium zlecenia i wskaźnik na ten rejestr. Skutek jest gorszy niż brak narzędzia: sesja startuje przekonana, że ma komplet | ⛔ **LUKA W TYM REPOZYTORIUM — klasa bez bramki i bez artefaktu tutaj**, bo naprawiony egzemplarz lezy POZA nim (hook startowy warsztatu). Wpis stoi, bo regula dotyczy kazdego naszego narzedzia, takze tych z `narzedzia-qa/` — do zamkniecia: przeglad `head` i `cut` w skryptach skladajacych opis PR-a oraz raporty. Każde `head -N`, `\| head`, `cut`, limit trafień i „pierwsze N wyników" w rzeczy, która KARMI kontekst albo decyzję, musi **powiedzieć, że uciął**: ile było, ile pokazano, gdzie reszta. ⛔ Drugie dno: limit ustawiony słusznie przestaje chronić, gdy pilnowany plik rośnie — pilnuje wtedy nie pliku, tylko cudzej wiedzy o nim |

### Meta-lekcja tych rund

**Kod produktu przestał być wąskim gardłem — stał się nim przyrząd.** Klasy 17 i 18 mają ten
sam kształt co klasy 11 i 12 z PR #198 (bramka o zakresie węższym niż nazwa, zero jako sukces),
tylko na wyższym piętrze: tam narzędzie nie widziało części terenu, tu **nie wykonało się w ogóle**
albo mierzyło stan, w którym wada nie mogła się objawić. Obie przechodzą na zielono i obie
wyglądają dokładnie jak dowód.

⭐ **Trzy pytania, które wychodzą z tych rund i kosztują sekundy, a nie rundę recenzji:**
1. Czy ostatnia instrukcja mojego testu może **zakończyć proces**?
2. Czy asercja **PADA**, gdy podłożę wadę — nie „czy przechodzi, gdy jest naprawa"?
3. Czy warunek, w którym wada się objawia, **na pewno zachodzi** w chwili pomiaru?

---

## KLASY DOPISANE 2026-08-28 (wieczór) — z rozplątywania PR #204 po squashu PR #203

Źródło: PR #204 stanął `CONFLICTING` natychmiast po scaleniu #203, mimo że nie zawierał ani
jednej sprzecznej zmiany. Trzy klasy niżej **nie dotyczą kodu produktu w ogóle** — dotyczą
historii repozytorium i tego, że zapisana wcześniej RECEPTA NAPRAWCZA sama była miną.

| # | Klasa (skąd wzięcie) | Dowód z tej sesji | Co ją łapie U NAS |
|---|---|---|---|
| 20 | **SQUASH-MERGE BAZY ROZJEŻDŻA GAŁĄŹ ZBUDOWANĄ NA JEJ SZCZYCIE** | #203 poszedł na `main` przez squash (jeden nowy commit `5073220`). Gałąź #204 była odbita z gałęzi #203, więc git widzi te same zmiany dwa razy pod innymi hashami: `gh pr view 204` → `mergeable: CONFLICTING`, `mergeStateStatus: DIRTY`, konflikt w 4 plikach. ⛔ Zero sprzecznych zmian w treści — konflikt jest wyłącznie skutkiem sposobu scalenia bazy | Zanim odbijesz gałąź NA gałęzi, ustal, czy baza pójdzie squashem. Jeśli tak — nie planuj scalania historii, tylko **przeniesienia commitów** (`cherry-pick`) po scaleniu bazy. ⛔ Sygnał wczesny: `git merge-base --is-ancestor <tip bazy> <twoja gałąź>` zwraca NIE — znaczy, że baza pojechała dalej bez ciebie |
| 21 | **NAPRAWIAJĄC JEDNĄ RZECZ, GASZĘ POMIAR OBOK** | Poprawka do uwagi recenzenta dopisała do `PRZELOT-CZLOWIEKA-SKLEP.md` dwa wiersze zaczynające się od „- ”. Licznik czyta takie wiersze jako pozycje listy i odmówił przeliczenia: `mapa-licznik.sh … --przelot --tylko-licz` → „2 wiersz(y) „- ” z NIEZNANYM znacznikiem”. Kontrola 5 w `k7-koniec.sh` zeszła na czerwono **przez moją poprawkę do czego innego** | Bramka JEST i zadziałała (`k7-koniec` warunek 5) — do rejestru idzie sama klasa, nie budowa nowej bramki. ⛔ Odruch: plik, który jest CZYTANY PRZEZ SKRYPT, ma składnię, nie tylko treść. Przed dopiskiem sprawdź, czy coś go parsuje, i puść ten licznik po dopisku |
| 22 | **RECEPTA NAPRAWCZA, KTÓRA CICHO COFA CUDZĄ ROBOTĘ — „odtwórz gałąź z `git diff origin/main...gałąź`” po squashu** | Zapisana w notatkach droga wyjścia z klasy 20 kazała zrobić łatkę przez `git diff origin/main...cele-k7-l1-11-ci-01-02`. Zmierzone: ta łatka ma **28 plików i 1304 wstawienia**, podczas gdy prawdziwa robota gałęzi to **8 plików i 281 wstawień**. Nadmiar to **5 commitów z rund 4 i 5 recenzji #203**, których gałąź #204 nigdy nie widziała — łatka nałożona na `main` COFNĘŁABY je. Trójkropek liczy od `merge-base(main, gałąź)` = `b7fe60e`, czyli sprzed squasha, a nie od punktu rozejścia gałęzi = `d5f8b6f`. Dowód przez wykluczenie: `git diff origin/domkniecie-przelotu-0.9.20 origin/main` → PUSTO (drzewo tipa bazy identyczne z `main`), a `git log cele-k7-l1-11-ci-01-02..origin/domkniecie-przelotu-0.9.20` → 5 commitów, których gałąź nie ma | ⛔ Przenosząc gałąź po squashu, licz od **punktu rozejścia z gałęzią bazową**, nie od `main`: `git merge-base <gałąź bazowa> <twoja gałąź>`, potem `git cherry-pick <rozejście>..<twoja gałąź>`. **Kontrola obowiązkowa po przeniesieniu**, obie muszą przejść: (1) `git diff --stat origin/main..<nowa gałąź>` pokazuje WYŁĄCZNIE pliki twojej roboty — każdy plik z nadmiaru to cudza zmiana, którą właśnie cofasz; (2) `grep` na charakterystyczne zdanie wniesione przez bazę — MUSI się znaleźć w drzewie nowej gałęzi |

### Meta-lekcja tych klas

**Zapisana recepta starzeje się razem z repozytorium.** Klasa 22 jest groźniejsza od klasy 20,
bo klasa 20 sama się zgłasza — git świeci `CONFLICTING` i nie da się jej przeoczyć. Recepta na
nią świeci na zielono: łatka nakłada się bez błędu, bramki przechodzą, a cudza robota znika po
cichu i wygląda to na porządek. ⛔ **Odruch: zanim zastosujesz zapisaną wcześniej komendę
naprawczą, ZOBACZ, co dokładnie ona zmienia** — `--stat` przed `apply`, nigdy po.

⭐ Trzy pytania z tej sesji:
1. Czy baza mojej gałęzi została scalona **squashem**? Jeśli tak, jej historia już nie istnieje.
2. Czy liczę różnicę od punktu, w którym **naprawdę się rozeszliśmy**, czy od `main`?
3. Czy w łatce są pliki, **których nie tykałem**? Każdy taki to cofnięcie cudzej zmiany.

---

## KLASY DOPISANE 2026-08-28 (noc) — RUNDA 4: MP SERVICE SUITE 1.3.13, projekt NIE ZDANY

⛔ **Skąd to się tu bierze i dlaczego tak późno.** Te klasy leżały od 2026-08-09 w warsztacie
(`docs/BRAMKI-ANTY-POWTORKA-bledy-kolegi.md`, sekcja „RUNDA 4"), ale **nigdy nie trafiły do TEGO
rejestru** — zmierzone 28.08: `grep` na `Attachments.php`, `CaseRepo`, `magic-link`, `FormConfig`
w tym pliku dawał **0 trafień**, przy próbie kontrolnej `catsnboard` → 7 trafień. Rejestr znał
rundy 1–3 i nie znał rundy 4, czyli **tej jedynej, po której projekt przepadł**. Ocena recenzenta:
**9,5/10 i NIEZALICZONE**, jego słowami *„zabrakło bardzo mało"*, a same błędy nazwał „małymi".

⭐ **Wszystkie sześć przeszło przez ~10 naszych audytów.** Nie zostały przeoczone przez pośpiech —
przeszły przez proces, który uważaliśmy za wystarczający.

| # | Klasa (skąd wzięcie) | Dowód z tamtego projektu | Co ją łapie U NAS |
|---|---|---|---|
| 23 | **KASOWANIE POZORNE — flaga „usunięto" stawiana bezwarunkowo, plik z danymi klienta zostaje** | `Attachments.php:519`. `wp_delete_file()` **nie zwraca wyniku**, więc kod nie miał jak wiedzieć, czy usunięcie się udało — a mimo to zapisywał stan „usunięty". Recenzent: „załącznik oznaczony jako usunięty potwierdza usunięcia pliku ale plik nadal zawiera dane klienta" | Test: skasuj przy katalogu **bez prawa zapisu** → stan MUSI zostać „nieusunięty". Po każdym `wp_delete_file()` weryfikuj `is_file()` PO operacji. ⛔ Dotyczy danych osobowych: pozorne kasowanie to nie bug, to niezrealizowane żądanie usunięcia |
| 24 | **LIMIT MIERZY INNY WYMIAR NIŻ TEN, KTÓRY ROBI SZKODĘ** | `Attachments.php:334` — limit BAJTÓW był, limit PIKSELI nie. PNG 2 MB o wymiarach 20000×20000 przechodzi limit rozmiaru i kładzie serwer przy dekodowaniu (obraz-bomba) | Przy KAŻDYM limicie nazwij, co robi szkodę, i sprawdź, czy limit mierzy TO. Tu: `getimagesize()` + próg w pikselach **przed** dekodowaniem. To samo pytanie stoi w `PULL_REQUEST_TEMPLATE.md` („limity mierzą to, co robi szkodę") |
| 25 | **TOKEN JEDNORAZOWY BEZ BLOKADY — dwie sesje z jednego linku** | `Front/Login.php:204` — odczyt tokenu i skasowanie go jako dwie osobne operacje. Recenzent: „jeden token moze w bardzo krotkim oknie utworzyc wiecej niz jedna sesje". ⛔ **POWTÓRKA błędu nr 3 z listy rund 1–3 w `docs/BRAMKI-ANTY-POWTORKA-bledy-kolegi.md`** (race condition w blokadzie importera) — ⚠️ to NIE jest klasa 3 tego rejestru (dokumentacja kłamie względem kodu); tamta numeracja jest osobna. Błąd był oznaczony ✅ | Zużycie tokenu = **JEDNA operacja z warunkiem**, nie „sprawdź, potem zapisz". Wzorce w tym repo: `pobieranie.php` (`UPDATE … WHERE id=%d AND pobrania_uzyte < pobrania_limit`) i `opinie.php` (CAS `… WHERE zamowienie_id=%d AND status='zalazek'`). Test **równoległy**: 2 żądania z tym samym tokenem → przechodzi dokładnie 1 |
| 26 | **TRANSAKCJA BEZ SPRAWDZENIA WYNIKU I BEZ ROLLBACK** | `CaseRepo.php:2042` — sprawa traciła klienta ORAZ zapasowy e-mail, czyli kontakt przepadał bezpowrotnie. ⛔ W tym samym pliku **10 innych transakcji miało ROLLBACK** — wzorzec leżał obok, nikt go nie sprawdził | Każdy zapis stanu poprzedzony **POMIAREM skutku**, nie samym wywołaniem funkcji. `COMMIT` warunkowy, żaden zapasowy ślad nie znika przed potwierdzeniem nowego zapisu. Test z **wymuszoną porażką zapisu** → dane zapasowe MUSZĄ przeżyć |
| 27 | **DWA REJESTRY TEJ SAMEJ RZECZY — filtr niespójny z walidacją** | `FormConfig.php:56` (twarda stała w intake) kontra `Categories.php` (dynamiczna lista z bazy). Recenzent: „powinien uzywac jednego przefiltrowanego rejestru a tak nie jest" | Jedno źródło prawdy: filtr i walidacja czytają **ten sam** rejestr. Test: dodaj pozycję w rejestrze → zgłoszenie z nią MUSI przejść walidację bez dotykania drugiego pliku |
| 28 | **KOSZT ROZWIĄZANIA PRZY ISTNIEJĄCEJ DARMOWEJ ALTERNATYWIE** (runda 1, `catsnboard` — uwaga NIGDY dotąd niezapisana) | Recenzent o silniku tłumaczeń na AI: *„nie jest to złe, że za claude tlumaczy, ale jest to koszt — gdzie istnieja tlumaczenia jakby darmowe"*. Nie zakwestionował poprawności, tylko **wybór płatnej drogi tam, gdzie istnieje darmowa** | Przy każdym module wołającym płatne API: nazwij w dokumentacji, **dlaczego nie darmowa alternatywa** (jakość / zakres / brak alternatywy), i podaj koszt jednostkowy. ⛔ Brak tego zdania czyta się jako niewiedzę o alternatywie, a nie jako decyzję |

### Dwie PRZYCZYNY-MATKI stojące pod tymi klasami

| # | Przyczyna-matka | Które klasy | Lek systemowy |
|---|---|---|---|
| **H** | **Nie sprawdzamy, czy operacja się udała — a mimo to zapisujemy jej skutek.** Kasujemy plik → stawiamy „usunięto". Zapisujemy klienta → robimy `COMMIT`. Zawsze bezwarunkowo | 23, 26 | Każdy zapis stanu poprzedzony POMIAREM skutku, nie wywołaniem funkcji |
| **I** | **Kontrola mierzy inny wymiar niż ten, który robi szkodę.** Limit bajtów przy zagrożeniu w pikselach. Zielona i ślepa | 24 | Przy każdym limicie: nazwij, co robi szkodę, i sprawdź, czy limit mierzy TO |

### ⛔ Meta-lekcja, którą tamten plik napisał sam o sobie

1. **Wszystkie bramki z rund 1–3 miały status „🔴 zbudować" albo „🟡 checklista". Ani jednej ✅.**
   Zaprojektowaliśmy maszyny i nie zbudowaliśmy żadnej. Plik miał na to trzy tygodnie.
2. **✅ na tamtej liście znaczyło „wiemy o tym", nie „nie może wrócić".** Błąd #3 (race condition)
   był oznaczony ✅, bo zapisaliśmy pamięć. Wrócił jako klasa 25 wyżej.
   ⭐ **Wiedza nie blokuje wydania — blokuje TEST.**
3. Pierwsze zdanie tamtego pliku brzmi: *„każdy błąd dostaje MASZYNĘ, nie notatkę «pamiętaj».
   Notatka zawiodła — dlatego się powtarzały."* **Ten plik stał się notatką.**

⭐ **Dlatego ten rozdział ma sens tylko wtedy, gdy każda klasa 23–28 dostanie u nas test z `exit 1`.**
Sam wpis w rejestrze jest dokładnie tym, co już raz nie zadziałało.

**STAN SPRAWDZENIA W TYM REPO (28.08, z odczytu kodu):**
- ✅ **25** zamknięta: `pobieranie.php` licznik atomowy w jednym `UPDATE` z warunkiem; `opinie.php`
  CAS na statusie + zerowanie tokenu w tej samej operacji.
- ✅ **26** zamknięta w tym samym miejscu: `if ( ! $zapisane ) { return 'wyscig'; }` — wynik operacji
  jest sprawdzany, nie zakładany.
- ⬜ **23, 24, 27, 28** — NIESPRAWDZONE w tym repo. Do przelotu osobno, klasa 23 pierwsza
  (dotyka danych osobowych kupujących).

---

## POCHODZENIE KLAS 29–44 (dopisane 2026-08-30)

Klasy 29–32 leżały od 28.08 w `/root/.claude/KLASY-29-32-DO-REJESTRU.md` (limit tygodniowy
95 %, świadomie odłożone), klasy 33–44 powstały 29.08 w dwóch plikach poza repo. ⛔ Rejestr nie
był karmiony przez dwa dni — dokładnie wbrew własnej meta-lekcji z 28.08. Dopisane jednym PR-em
przy starcie sesji 30.08, po sprawdzeniu `grep -cE '^\| (29|30|31|32) \|'` → 0.

## KLASY DOPISANE 2026-08-28 (późny wieczór) — z DZIESIĘCIU rund recenzji PR #207

Źródło: naprawa wady RODO `SK-L2-003`. **Cztery z dziesięciu rund znalazły wadę, którą wprowadziła
POPRZEDNIA runda tej samej naprawy.** Recenzent nie pomylił się ani razu — każda uwaga została
zweryfikowana pomiarem; jedna hipoteza postawiona przeze mnie samą („dwa rejestry statusów”)
**nie potwierdziła się** i została odrzucona, więc weryfikacja działa w obie strony.

| # | Klasa | Dowód z tych rund | Co ją łapie U NAS |
|---|---|---|---|
| 29 | **NAPRAWA PRZESUWA WADĘ O KROK ZAMIAST JĄ ZAMKNĄĆ — i wygląda wtedy jak naprawa** | Łańcuch z jednej sprawy: sprawdzanie wyniku → za mało (powtórka bezskuteczna, bo pierwszy zapis kasował klucz do znalezienia śladu) → odwrócenie kolejności → za mało (kod przechodził wszystkie zapisy przed sprawdzeniem) → twarde wyjście → za mało (pomiar w 1 miejscu z 3) → pomiar w trzech → za mało (czyszczenie po `zamowienie_id`, a adres bywa w wierszach bez niego) → po treści → **GORZEJ** (`REPLACE` bez zakotwiczenia przepisywał wpisy INNEGO kupującego) → kotwica → za mało (kotwica na 9 znakach, RFC dopuszcza więcej) | ⛔ Cztery pytania z `jak-audytowac/references/audyt-wlasnej-naprawy.md` **PRZED** wysłaniem naprawy recenzentowi; pierwsze brzmi: *czy zamknąłeś klasę, czy tylko zwęziłeś okno?* Zadziałało natychmiast, gdy je w końcu otworzyłam: wskazało `mail_tresc` jako podejrzany → sprawdzone na żywych danych: **5 zamówień z treścią maila, 0 z adresem w treści**. ⬜ DO ZROBIENIA: bramka wymuszająca te pytania — plik istniał i nie zadziałał, bo nic go nie podsuwało |
| 30 | **NAPRAWA FAŁSZYWEGO ALARMU PRZEZ POLUZOWANIE PROGU = ZAMIANA GO W ŚLEPOTĘ** | Asercja żądała „dokładnie jednego” wpisu i mogła się wywrócić przez pozostałość po wcześniejszym przebiegu. Poluzowałam próg do „co najmniej jeden” — wtedy przechodziłaby **także wtedy, gdy bieżący wpis został przepisany**, a licznik złapał cudzy. Recenzent: *„przeszła z fałszywego alarmu w ŚLEPOTĘ”* | ⛔ Fałszywy alarm naprawia się **warunkami wstępnymi**, nie progiem: test kasuje pozostałości PRZED pomiarem, asercja zostaje ostra. Dowód, że nie oślepła: kod sprzed naprawy → `WPIS_OBCEGO_RFC_CALY=0`, asercja PADA; po naprawie → `=1`, przechodzi. **Pytanie kontrolne: czy po mojej zmianie asercja NADAL pada na podłożonej wadzie?** |
| 31 | **SPRZĄTANIE KLUCZUJĄCE PO TREŚCI, KTÓRĄ SAMA TESTOWANA OPERACJA PODMIENIA** | Test sprzątał wiersze po adresie w treści (`opis LIKE %marker%`). UDANA anonimizacja **podmienia ten adres na znacznik**, więc wiersz przestawał zawierać marker i sprzątanie przestawało go widzieć. **Każdy UDANY przebieg zostawiał wiersz na wspólnym stosie**, a nieudany sprzątał się poprawnie — objaw wyglądał odwrotnie niż przyczyna | Sprzątanie kluczuj po polu, którego testowana operacja **NIE dotyka** (u nas `typ` zdarzenia, nie `opis`). ⛔ Pytanie: *czy klucz, po którym sprzątam, przeżyje operację, którą testuję?* Zmierzone po naprawie: `WIERSZE_TESTOWE=0` |
| 32 | **LOCALE ROZJEŻDŻA SUMY KONTROLNE MIMO IDENTYCZNEJ TREŚCI** | Asercja „mierzę ten sam kod” liczyła `sha256` katalogu w kontenerze i na hoście. Sumy się różniły, choć pliki były co do bajtu takie same: `sort` w obu miejscach ma inne locale i układał je w innej kolejności. Kuszące było „poprawiać aż zadziała” — zostałby przyrząd kłamiący w drugą stronę | Dowód rozstrzygnął jedną komendą: różnica **per plik** pokazała TEN SAM plik z TĄ SAMĄ sumą, tylko w innej pozycji. ⛔ Każde sortowanie w porównaniu maszyna–maszyna: `LC_ALL=C`. Ogólniej: **fałszywy alarm przyrządu rozkłada się na czynniki, nigdy nie obchodzi** |

### Meta-lekcja tych rund

**Recenzent znajdował wady w NAPRAWIE, nie w produkcie.** Kod wtyczki był bez uwag od rundy 4 —
kolejne sześć rund dotyczyło przyrządu, zapisów i tekstów. Ta sama diagnoza co przy `#198` i `#199`,
tylko ostrzejsza: **im lepszy kod, tym bardziej wąskim gardłem staje się to, czym go mierzę,
i to, co o nim piszę.**

⛔ Osobno do zapamiętania: **bramka `slady` złapała mnie TRZY RAZY jednego dnia** na tej samej
klasie — numery recenzji i odniesienia do naszego audytu w komentarzach kodu klienta — mimo że
mam ją zapisaną w pamięci. Wpis w pamięci nie zatrzymał ręki. Zatrzymała bramka.

---

## KLASY DOPISANE 2026-08-29 — z rund 1–3 PR #209 i rund 4–5 PR #208 (teksty jadące do klienta)

Źródło: dwa PR-y, których **jedynym produktem jest jakość tekstu czytanego przez kupującego** —
komentarze w kodzie, CHANGELOG-i, dokumentacja techniczna w paczce. Kod produktu nie zmienił się
ani o instrukcję. Mimo to poszły trzy pierwsze rundy `#209` i co najmniej pięć rund `#208`, w tym dwie uwagi KRYTYCZNE. ⛔ To jest
lekcja sama w sobie: **PR bez linijki logiki potrafi być trudniejszy od PR-a z logiką**, bo nie
broni go żadna bramka — `php-lint`, `phpcs`, `phpstan` i `paczka-zgodnosc.sh` przepuszczają
zepsute zdanie tak samo chętnie jak poprawne.

| # | Klasa | Dowód z tych rund | Co ją łapie U NAS |
|---|---|---|---|
| 33 | **PRÓBA KONTROLNA WZORCA DOWODZI, ŻE PRZYRZĄD MIERZY — NIE ŻE MIERZY CAŁĄ KLASĘ** | Zgłosiłam „47 trafień → 0" i uzasadniłam próbą kontrolną: ten sam wzorzec na innym katalogu nadal coś znajdował, więc „szuka poprawnie". To dowodzi, że wzorzec DZIAŁA, nie że OBEJMUJE KLASĘ. `#209` r1: recenzent szerszym wzorcem znalazł **22** pozostałe odniesienia (`SK-L3-005`, `LS-F1`, `A3-2`, `Do audytu`). Po mojej naprawie r2 znalazł **42 kolejne** innej rodziny. Po naprawie r2 mój własny szeroki zwiad znalazł **trzecią** rodzinę, której nie łapał ani mój, ani jego wzorzec (`feedback z przeglądu`, `recon`, `runda stron`, `ZMIERZONE DD.MM`) — **68 kandydatów, 65 podmian**. Trzy rundy, trzy wzorce, za każdym razem „zero" było przedwczesne | ⛔ Nie pytaj „czy mój wzorzec nic nie znajduje" — pytaj **„co w ogóle wygląda tu jak odsyłacz"**. Wypisz KANDYDATÓW i przejrzyj ich listę: `grep -rhoE '<szeroki kształt>' <katalog> \| sort \| uniq -c`. Dopiero brak kandydatów jest zerem. ⛔ **Wzorzec recenzenta też bywa za wąski** — jego 42 to nie była cała klasa |
| 34 | **KONTROLA WYBRANA TAK, ŻE Z ZAŁOŻENIA NIE WIDZI TEJ KLASY BŁĘDU** | Po masowej podmianie w 22 plikach wybrałam kontrolę „liczba linii w każdym pliku bez zmian". Wyglądała solidnie i złapała realny problem (sklejanie wierszy). Ale moje uszkodzenia były **wewnątrz linii**: `operator.php:309` urwane słowo („dalej odpowiada **bo**." zamiast „bot"), `DOKUMENTACJA-TECHNICZNA.md` osierocony wiersz z samą kropką, `CHANGELOG.md:138` zniknięta kropka kończąca zdanie. Wszystkie trzy przeszły przez `php-lint`, `phpcs`, `phpstan` i `paczka-zgodnosc.sh`, i **wszystkie trzy były już w zamrożonych paczkach** | ⛔ Pytanie PRZED wyborem kontroli: **czy ta kontrola potrafiłaby zobaczyć błąd, którego się boję?** Przy podmianach w tekście: przeczytać KAŻDĄ zmienioną linię w całości (było ich 31, potem 82 — dało się), plus wzorce na SKUTKI: `^[[:space:]]*[.,;][[:space:]]*$`, podwójna spacja między literami, `\*\*$` w wierszu listy |
| 35 | **KALIBRACJA NAPRAWY WYGODNIEJSZYM SCENARIUSZEM ZAMIAST ORYGINALNYM** | `#208`, rundy 4→5. Recenzent udowodnił wadę scenariuszem A („opustoszałe readme"). Naprawiłam i skalibrowałam scenariuszem B — psując **wzorzec pętli** (mój), nie **plik** (oryginalny). Wszystko zielone, wada została. R5: *„dwa różne źródła to nadal jedno źródło — pomiar z rundy 4 dalej przechodzi na EXIT=0"* | Kalibruj **tym samym scenariuszem, którym wykazano wadę**, i dopiero potem dokładaj własne. W opisie PR podaj scenariusz kalibracji dosłownie, żeby recenzent widział, czy to ten sam |
| 36 | **`\s*` W REGEXIE MASOWEJ PODMIANY SKLEJA WIERSZE** | `\s` łapie znak nowej linii. Wzorzec `\s*\(…\)` usuwający nawias na początku linii zjada koniec linii poprzedniej. Podgląd przed zapisem: **1536** zmienionych linii zamiast spodziewanych ~55, w 22 plikach. Po zamianie `\s` na `[ \t]` — 57 linii | W podmianach tekstowych `[ \t]*`, nigdy `\s*`. ⭐ Zawsze **podgląd przed zapisem** i patrz na LICZBĘ zmienionych linii, nie na przykłady — liczba wyła, przykłady wyglądały dobrze |
| 37 | **PRZECZYTANIE TYLKO PIERWSZEJ UWAGI RECENZJI** | `#209` r1 miała uwagę KRYTYCZNĄ i trzy dalsze. Rzuciłam się na krytyczną i wypchnęłam, nie przeczytawszy reszty. W r2 wróciły wszystkie trzy, a jedna **awansowała na KRYTYCZNĄ**, bo doszło do niej trzecie uszkodzenie. Trzy z pięciu uwag rundy 2 to niezaadresowane uwagi rundy 1 | Po każdej recenzji: **wypisz WSZYSTKIE uwagi jako tabelę i odhacz każdą osobno w opisie PR** — także te świadomie zostawiane jako dług, z powodem. Recenzent sprawdza właśnie to |
| 38 | **NARZĘDZIE PODMIAN, KTÓRE CICHO NIE TRAFIA — część podmian przechodzi, część znika bez śladu** | Skrypt podmian w Perlu bez `use utf8;`: literały z polskimi znakami nie pasowały do zdekodowanego tekstu, więc **55 z 65 podmian nie trafiło**, a 10 (te bez polskich liter) przeszło i pliki zostały ZAPISANE. Gdyby skrypt tylko podmieniał i milczał, zobaczyłabym zmienione pliki, uznała robotę za zrobioną i oddała 55 nietkniętych odsyłaczy jako „wyczyszczone" | ⛔ Każda masowa podmiana musi mieć **bramkę na trafienie**: dokładnie jedno dopasowanie na podmianę, inaczej skrypt PADA z listą nietrafionych (`PODMIANY OK: 65 · BLEDY: 0`, `exit 1` przy jakimkolwiek błędzie). ⛔ Osobno: przy polskich tekstach w Perlu `use utf8;` + `:encoding(UTF-8)` na wejściu i wyjściu — brak `use utf8` nie jest błędem składni, tylko cichym niedopasowaniem |
| 39 | **TEST PADA PRZEZ BRAKUJĄCY WARUNEK ŚRODOWISKA, A WYGLĄDA NA REGRESJĘ GAŁĘZI** | `segregator-skrzynka-proba-kontrolna.sh` kończył `EXIT=1` i trafił do logu dowodowego jako jedna z „7 z 30 padających prób". Recenzent słusznie zauważył, że commit deklarujący „te same co przed" ma diff pokazujący `EXIT=0 → EXIT=1` — czyli twierdzenie nie miało pokrycia w dowodzie. Przyczyna: **zatrzymany kontener `segm_imap`**, nie kod. Dowód przez wykluczenie: dwa przebiegi pod rząd przy wyłączonym kontenerze → `EXIT=1` z komunikatem *„czysty kod NIE przechodzi — proba nic nie znaczy"*; po samym `docker start segm_imap`, bez zmiany w kodzie → `EXIT=0` i *„STRAZNIK SKRZYNKI JEST SKALIBROWANY"* | ⛔ Zanim wpiszesz padającą próbę do dowodu, ustal **czy jej warunki wstępne w ogóle zachodziły**. Skrypt sam to mówił (*„proba nic nie znaczy"*) — komunikat trzeba przeczytać, nie tylko kod wyjścia. ⭐ Powtarzalny odruch: uruchom DWA razy pod rząd, potem zmień JEDEN warunek środowiska i uruchom trzeci raz. To rozdziela wadę kodu od dryfu wspólnego stosu |

### Meta-lekcja tych rund

**Ten rozdział nie zawiera ani jednej klasy dotyczącej kodu produktu.** Wszystkie siedem dotyczy
albo tekstu dla klienta, albo przyrządu, którym ten tekst mierzyłam. To ta sama linia, która
zaczęła się przy `#198` (klasy 11–12) i `#201` (17–19): **im czystszy kod, tym bardziej wąskim
gardłem staje się sprawdzanie.**

⛔ **Klasy 33 i 34 to jedna para i tak trzeba je czytać.** Klasa 33 mówi, że wzorzec szukający
może nie obejmować całej klasy. Klasa 34 mówi, że kontrola sprawdzająca może nie widzieć rodzaju
uszkodzenia. Obie kończą się zielonym światłem i obie wyglądają dokładnie jak dowód. Pytanie,
które rozbraja obie, jest jedno i to samo: **„czego to narzędzie NIE widzi?"** — zadane osobno
od pytania „co znalazło?".

⭐ **Pytania, które wychodzą z tego rozdziału i kosztują sekundy zamiast rundy:**
1. Czy wypisałam KANDYDATÓW, czy tylko sprawdziłam, że mój wzorzec nic nie znajduje?
2. Czy moja kontrola potrafi zobaczyć **ten rodzaj** uszkodzenia, którego się boję?
3. Czy skrypt podmian **PADA**, gdy podmiana nie trafi — czy po cichu jej nie robi?
4. Czy padająca próba miała spełnione **warunki wstępne**, zanim nazwę ją regresją?
5. Czy odhaczyłam **każdą** uwagę recenzji z osobna — łącznie z tymi, które zostawiam jako dług?

---

## KLASY DOPISANE 2026-08-29 (noc) — z rund 6–11 PR #209 i z kontroli po squashu (30.08)

| # | Klasa | Dowód | Czym obstawione |
|---|---|---|---|
| 40 | **ODHACZASZ W OPISIE PR RZECZ, KTÓREJ NIE ZROBIŁAŚ** | `#209` r8: opis PR i lista kontrolna twierdziły, że dług „wielkość litery w skanerze” jest zapisany w `CO-WISI.md`. Recenzent sprawdził: `grep -c 'wielkość litery' audyt/CO-WISI.md` → **0** (stan z r8, 29.08; dziś ta sama komenda daje **1**, bo wpis o skanerze w `CO-WISI.md` sam zawiera tę frazę — dług został dopisany PO tej uwadze). Własna klasa 3 (dokumentacja kłamie) zastosowana do własnego opisu | ⛔ Każdy odhaczony punkt listy kontrolnej musi mieć **komendę potwierdzającą** przed wysłaniem opisu. Odhaczenie z pamięci = deklaracja, nie dowód |
| 41 | **OBIETNICA KOMPLETNOŚCI, KTÓREJ NIE UMIESZ UTRZYMAĆ** | `#209` r6→r10: „PEŁNA lista” oznaczeń trybu testowego rosła **trzy → sześć → siedem** miejsc, za każdym razem niepełna. Osobno r9: zmieniłam punkt 6 na „nie traktuj listy jako kompletnej”, ale **nagłówek 27 wierszy wyżej** nadal obiecywał „nic poza tą listą nie jest potrzebne” | ⭐ Gdy nie umiesz zagwarantować kompletności — **nie obiecuj jej**, daj jedno działanie („napisz do nas”). Zdania bez obietnicy nie da się obalić kolejnym znalezionym miejscem. ⛔ Zmieniasz obietnicę? Zmień JĄ W CAŁYM dokumencie, nie w jednym akapicie |
| 42 | **SZUKASZ FORM ZAMIAST KSZTAŁTU — i dlatego każda runda przynosi nową rodzinę** | `#209` r6-r11: `Z10` → `Z6` → `luka L1` → `P0.4`, sześć rund łatania po jednej rodzinie. Wyliczenie po KSZTAŁCIE (`grep -rhoE '\b[A-Z]{1,3}[0-9]+(\.[0-9]+)?[a-z]?\b' … \| sort \| uniq -c`) dało **całą listę naraz**: 8 naszych oznaczeń plus rozpoznawalne fałszywe (UTF7, CP1250, SHA256, współrzędne SVG, `Z0` z regexów `a-zA-Z0-9`, ⛔ `Ctrl+F5` — skrót klawiszowy DLA KLIENTA, o mało nie wycięty) | ⛔ **Zero mojego wzorca ≠ zero listy kandydatów.** Przy rodzinach oznaczeń pytaj „co tu W OGÓLE wygląda jak oznaczenie”, nie „czy mój wzorzec coś znajduje”. Po naprawie **powtórz TO SAMO wyliczenie** — dopiero brak kandydatów jest zerem |
| 43 | **POTOK MASKUJE NIE TYLKO PORAŻKĘ, ALE I CZĘŚCIOWY SUKCES** | 29.08, scalanie `#209`: `gh pr merge … \| tail` wypisało `MERGE EXIT=0`, a komenda odmówiła (niezacommitowany plik). Prawda była JESZCZE INNA: **merge się udał**, padło tylko lokalne przełączenie gałęzi — pokazał to komunikat „was already merged” przy drugiej próbie. Potok zamaskował stan pośredni, groźniejszy od czystej porażki | ⛔ `$?` **nigdy** po potoku. Do operacji nieodwracalnych (merge, tag, release) dodaj **potwierdzenie NIEZALEŻNE od komendy**: `gh pr view N --json state,mergedAt` |
| 44 | **PO SQUASHU „COMMITY SPOZA MAIN” TO NIE DOWÓD UTRATY TREŚCI — i panika w drugą stronę też kosztuje** | 30.08, kontrola po pytaniu Dzidka „wszystko zapisane na pewno?”. `git log origin/main..origin/fix/…` pokazał **PIĘĆ commitów spoza main** i wyglądało to na utratę wszystkich napraw rund 8–11 (mail „płatność nie została pobrana”, `Z6`, `luka L1`, `P2.3`). NIEPRAWDA: PR scalono **squashem**, więc treść weszła JEDNYM commitem, a gałąź trzyma stare hashe. Rozstrzygnął dopiero pomiar TREŚCI: `git show main:…sitemap-produkty.php \| grep -c Z6` → 0, to samo dla `luka L1` i `P2.3`. Realna strata: **jeden plik, 37 linii** dokumentu wewnętrznego (commit powstał PO merge) | ⛔ Dowodem utraty jest **`git diff main <gałąź> --stat`** — porównanie DRZEW, nie historii. `git log main..gałąź` po squashu kłamie ZAWSZE. ⭐ Osobno: commit wypchnięty na gałąź **już scaloną** nie trafia nigdzie — po merge sprawdź `git diff main HEAD --stat`, zanim uznasz robotę za zapisaną |

---

## KLASY DOPISANE 2026-08-30 — z rund 4–6 PR #209 (skaner śladów a `dokumentacja/`)

⛔ Te trzy klasy `DO-ODDANIA.md` wymieniał od 29.08 jako „do przeniesienia”, a PR `#211` w pierwszej
wersji ich nie przeniósł — dowód recenzji r1: `grep -nE '263|czytanie JEST wyliczeniem' audyt/SCIAGA-EGZAMINATORA.md`
→ 0. Źródło treści: `audyt/CO-WISI.md`, sekcja o skanerze śladów (r4–r6). To jest własna klasa 41
(obietnica kompletności) na własnym PR-ze o rejestrze.

| # | Klasa | Dowód z tych rund | Co ją łapie U NAS |
|---|---|---|---|
| 45 | **ZASIĘG WĘŻSZY NIŻ WZORZEC — skaner zna wzorzec, ale pętla nigdy nie patrzy tam, gdzie ślad leży** | `#209` r4: napisałam „skaner nie zna żadnej z tych rodzin”. Nieprawda — `narzedzia-qa/slady-wewnetrzne.sh:78` zna `\bK[0-9]\b` od wersji 1.3.0, a mimo to przepuścił `K8` w `dokumentacja/INSTRUKCJA-INSTALACJI.md`, bo pętla `for katalog in wtyczki/*/` z własnym komentarzem stawiała znak równości między „pliki wtyczek” a „to, co trafia do klienta”. Dopisywanie wzorców niczego by nie naprawiło | ⛔ Przy każdym skanerze pytaj o TEREN pętli osobno od wzorców: „po czym iteruje i czy to jest wszystko, co dostaje klient?”. Próba kontrolna z podłożonym śladem **w każdym katalogu, który jedzie do klienta**, nie tylko w `wtyczki/` |
| 46 | **KALIBRACJA ODRZUCIŁA WŁASNĄ NAPRAWĘ — rozszerzenie zasięgu pada na CZYSTEJ treści** | `#209` r5: rozszerzenie pętli o `dokumentacja/` skalibrowane przed scaleniem: `SLADY_ZALEGLOSCI=/dev/null bash narzedzia-qa/slady-wewnetrzne.sh dokumentacja dokumentacja` → `EXIT=1` na dwóch **fałszywych** trafieniach (`localhost` w instrukcji Stripe CLI to treść, której klient potrzebuje). Wpięcie postawiłoby CI na czerwono od pierwszego przebiegu; dopisanie wyjątków do `slady-zaleglosci.txt` byłoby obejściem bramki („ta lista ma wyłącznie KURCZYĆ się”) | ⭐ Naprawa bramki ma DWIE kontrole: podłożona wada → czerwone **i** czysta treść → zielone. Odrzucenie własnej naprawy przez kalibrację to wynik, nie porażka — zapisz go i zostaw zasięg węższy z JAWNYM powodem. Recepta: wzorce **per teren** (dla `dokumentacja/` bez „adresu maszyny dev”), osobnym PR-em |
| 47 | **GDY TEREN DA SIĘ PRZECZYTAĆ W CAŁOŚCI, CZYTANIE JEST WYLICZENIEM — wzorzec jest wtedy zgadywaniem** | `#209` r5: dwa ślady w `dokumentacja/INSTRUKCJA-INSTALACJI.md` (263 wiersze), których **żaden** wzorzec by nie złapał: „zrzut ekranu dojdzie przy finalizacji instrukcji” (notatka o naszej pracy zwykłym słowem) i odsyłacz do `PLAN-PROJEKTU.md:433` podany **parafrazą** („w dokumentacji projektu”) — wzorzec `PLAN-PROJEKTU` był w skanerze i bezradny. Wzorzec przepuścił ślad trzy rundy z rzędu | ⛔ Dla katalogu, który da się przeczytać w minuty, miarą czystości jest **przeczytanie w całości**; wzorce zostają siatką na REGRESJE, nie dowodem. Para do klas 33 i 34: tam „czego wzorzec nie znajduje”, tu „kiedy wzorzec w ogóle nie jest właściwym narzędziem” |

---

## KLASY DOPISANE 2026-08-30 — z rund recenzji PR o instrukcji sklepu i PR o README, oraz z „awarii” CI

| # | Klasa | Dowód | Czym obstawione |
|---|---|---|---|
| 48 | **PR W KONFLIKCIE Z `main` = ZERO PRZEBIEGÓW CI, BEZ KOMUNIKATU — wygląda jak awaria platformy, a strażnik czeka wiecznie** | 30.08, PR o instrukcji sklepu: push 08:05 UTC, przez 35 minut zero przebiegów; sprawdzone po kolei uprawnienia Actions, status GitHuba, runner, pusty commit, close/reopen — nic. Rozstrzygnęło `gh pr view N --json mergeable,mergeStateStatus` → `CONFLICTING / DIRTY` (dwa PR-y dopisały sekcję na KOŃCU `CO-WISI.md`). Po `git merge origin/main` przebiegi ruszyły w 30 s | ⛔ „CI nie startuje” → PIERWSZA komenda: `gh pr view N --json mergeable`. Scaliłaś inny PR po otwarciu swojego → wciągnij `main` ZANIM czekasz na recenzję. Strażnik w tle: sufit czasu + warunek „przebieg w ogóle powstał” |
| 49 | **INSTRUKCJA PISANA „Z KODU” Z JEDNEGO MIEJSCA — zdanie o zachowaniu wzięte z komentarza, nie z warunku, który je wykonuje** | PR o instrukcji sklepu, r1: „dla zwróconych mail też wychodzi” — komentarz w `admin-zamowienie.php` opisywał jedną gałąź, a 30 linii wyżej `in_array()` odrzucał te statusy kodem 409. Do tego: „nikogo nie blokuje” (kod: 429 od 6. próby), „adres nietknięty” (prawda na jednej z dwóch ścieżek), „zwrócone przy częściowym” (kod: tylko pełny). Cztery zdania, cztery różne miejsca w kodzie, każde przeczytane w połowie | ⛔ Zdanie o zachowaniu w tekście dla klienta = przeczytać CAŁĄ ścieżkę od wejścia do skutku (warunki wstępne, wp_die, obie gałęzie porażki), nie komentarz obok. Bramka liczb (`dokumentacja-zgodnosc-sklep.sh` §F) tego nie widzi — mierzy liczby; treść zdań to czytanie w całości, z recenzją jako drugą parą oczu |
| 50 | **NOWA ASERCJA W BRAMCE BEZ PLIKU W PRÓBIE KONTROLNEJ — próba pada 0/N na CZYSTEJ kopii i cały przelot prób jest czerwony** | PR o instrukcji sklepu, r1 KRYTYCZNA: sekcja F czytała `INSTRUKCJA.md`, `PONAD`, `zrzuty/`, `admin-produkty.php`, `buduj-paczki.sh`, a `zbuduj_czysty()` w próbie kopiował ręczny podzbiór plików → 0/12 przypadków, w tym kontrola odwrotna | ⛔ Nowa asercja w bramce = nowy plik w `zbuduj_czysty()` + osobny przypadek psujący DOKŁADNIE ją (30.08: 12 → 21 przypadków, EXIT=0). Reguła w nagłówku próby (1.1.0) |
| 51 | **ZRZUT DO PACZKI KLIENTA NIESIE ŚRODOWISKO ROBOCZE — a skaner śladów nie czyta obrazów** | r1 WAŻNA: pasek admina z kontem `audytor-admin`, oś czasu z `admin_roboczy`, `ip 172.21.0.1`, front z paskiem WP zalogowanego; `paczka-zgodnosc.sh` meldował „66 plików bez śladów” — 5 z nich to PNG | ⛔ Zrzuty maszynowo, ale z UKRYCIEM tożsamości (pasek admina bez konta/witryny), front w niezalogowanym kontekście, kadr bez osi czasu — `testy/zrzuty-panelu.js`. Kontrola obrazów: OCZY (dług: OCR, `CO-WISI.md`) |
| 52 | **STRONA TYTUŁOWA ODSYŁA DO PLIKÓW Z INNEGO, NIESCALONEGO PR-a — „istnieją w repo” było prawdą na mojej gałęzi, nie na tej** | PR o README, r1 KRYTYCZNA: README i PRZECZYTAJ-MNIE odsyłały do 4 plików dostarczanych przez PR o instrukcji; opis PR-a twierdził, że zrzuty istnieją | ⛔ `testy/odsylacze-strony-tytulowej.sh` (30.08): każdy odsyłacz README/PRZECZYTAJ ma cel w drzewie; na gałęzi bez tamtego PR-a pada 5 z 36. Kolejność scalania zależnych PR-ów zapisana w opisie, nie w głowie |
| 53 | **INSTRUKCJA OBIECUJE ZACHOWANIE, KTÓREGO KOD NIE MA — „nowy limit pobrań” istniał tylko w zdaniu; naprawa klasy = OBIE ścieżki wydające świeży link, nie ta, o której mówiła uwaga** | PR o instrukcji sklepu, r4 KRYTYCZNA: `UPDATE` resendu bez `pobrania_uzyte`; kupujący z wyczerpanym limitem (ten, którego instrukcja po przycisk odsyła) dostawał świeży link i 410. r5 WAŻNA: moja naprawa objęła przycisk, a cron (`sklepag_domknij_zakup`) rotował token tą samą drogą bez zerowania — dowód z BRAKU (`grep pobrania_uzyte` → jedno zerowanie) | strażnik `testy/resend-odnawia-limit-pobran-test.php` — 3 części: resend, kompensacja, cron; FAIL na starym kodzie, PASS na nowym. Zasada: naprawiasz zapis w jednej ścieżce → `grep` po kolumnie, wypisz WSZYSTKIE zapisy tej kolumny, każdy dostaje część testu |
| 54 | **TREŚĆ TWORZONA PRZY AKTYWACJI („szkic Polityki”) NAPRAWIONA W KODZIE NIE DOCIERA DO DZIAŁAJĄCYCH INSTALACJI — a paczka o tym milczała; KOMPENSACJA MA TERMIN WAŻNOŚCI — inny proces (cron po 2 min) nadpisuje przywrócony stan** | PR o instrukcji sklepu, r4 WAŻNA: `sklepag_utworz_strony()` pomija stronę, która już istnieje; sklep z 0.9.22 zostaje ze szkicem bez IP. r6 WAŻNA: „nieudana wysyłka przywraca poprzedni link” prawdziwe przez ~120 s, potem cron rotuje token i przy porażce nic nie cofa | CHANGELOG + readme + instrukcja mówią, co dopisać ręcznie; zdanie o przywracaniu ma granicę czasową; dług: kompensacja w cronie (`CO-WISI.md`). Zasada: przy „X przywraca Y” zapytaj, KTO JESZCZE pisze Y i KIEDY |
| 55 | **DOKUMENT KOŃCOWY „OD NOWA” ŁAGODNIEJSZY OD WŁASNEGO WCZEŚNIEJSZEGO WERDYKTU — i dowód wskazujący plik, w którym tej treści NIE MA** | PR `#216` r1 KRYTYCZNA: rubryka K10 „tryb AI ręcznie z kluczem (`K2-WROGA-SESJA.md`)” — `grep -ic klucz` w tym pliku → 0 (próba kontrolna `OBRONIONE` → 8); nasz `L1-11-WERDYKTY-KRYTERIOW.md:93` mówił „brak dowodu”. K12 PASS przy niespełnionym drugim członie. `#215` r1: „nie wady produktu” vs rejestr „dług bezpieczeństwa”. `#217` r1: §6 „12 × PASS” po tym, jak `#216` dało 6/5/1 (bliźniak w innym pliku) | Rubryka przepisuje L1-11 (nagłówek mówi to wprost); brak bramki — do zrobienia: kontrola, że liczby werdyktów w `rubryka-GOTOWE.md`, `AUDYT-RAPORT.md`, `RAPORT-ODDANIA.md` i `DO-ODDANIA.md` są równe (dług w CO-WISI „trzy liczby w trzech plikach”) |
| 56 | **ZERO Z MARTWEGO PRZYRZĄDU — „0 notice’ów” bez dowodu, że logger w ogóle pisał; tester oznaczył niepewność, koordynatorka musi ją rozstrzygnąć PRZED cytowaniem** | K9 (30.08): `wp-config.php` bez `WP_DEBUG_LOG` (obraz 6.9.4 nie wpisał `WORDPRESS_CONFIG_EXTRA`), `debug.log` 0 B; `lib-stos-probny.sh` `log_bledow()` = `cat … 2>/dev/null \|\| true`, konsumenci `dowod-*.sh` piszą „(WP_DEBUG włączony)” bez pomiaru (recenzja `#218` W2). Po naprawie: podłożony `trigger_error` → `grep -c` = 1, potem 0 na 8 adresach | Brak bramki — do zrobienia (CO-WISI, K9 pkt 3): `log_bledow()` pada bez pliku/`WP_DEBUG_LOG`; `postaw_stos` kończy próbą kontrolną z podłożonym notice’em |
| 57 | **ŁAŃCUCH „EDYTUJ → TESTUJ → COMMITUJ” BEZ BRAMKI NA WYNIK — skrypt edycji padł w połowie (albo test był czerwony), a commit i push i tak poszły; dwa razy jednego wieczoru** | #222 (30.08): `153515c` (skrypt python przerwany na pierwszej krotce → licznik bez inicjalizacji), `f39b3c5` (test 2 BLAD, commit poszedł); recenzent r3/r4 czytał połówki napraw | brak bramki — do zrobienia: `git commit` w łańcuchu TYLKO za `if [ "$EXIT" = 0 ]`; wzór w tej sesji od `6b7674c` („NIE COMMITUJE — test czerwony”) |
| 58 | **NUMER RECENZJI/PR W KOMENTARZU KODU KLIENTA I ŚCIEŻKA `testy/` W CHANGELOG-U PACZKI — trzeci raz** (pamięć warsztatu o tym była, rejestr nie) | #222 (30.08): `paczka-zgodnosc.sh` → 3 rozjazdy (`platnosc.js`, `sklep-agentow.php` „recenzja #222 r1”, CHANGELOG `testy/…`) — dopiero PO commicie | `narzedzia-qa/slady-wewnetrzne.sh` + sekcja D `paczka-zgodnosc.sh` (EXIT=1) — łapie, ale PO fakcie; do zrobienia: pre-commit hook na `wtyczki/**` z tym skanerem |
| 59 | **DWA KLUCZE Z DWÓCH KONT — `pk_` od właściciela, `sk_` z bezpańskiego sandboxa; objaw „No such checkout.session” wygląda jak błąd kodu** | 30.08: sesja z `acct_1U5FhK7…` (sandbox CLI z 17.08, bez e-maila), `pk_test_51U5Fhg7…` z konta `acct_1U5Fhg7…`; rozstrzygnięte porównaniem prefiksów i `GET /v1/account` | brak bramki — do zrobienia: przy zapisie pk w Ustawieniach porównać prefiks konta z sk (oba niosą `51<acct>`) i ostrzec; w karcie stanowiska: `curl /v1/account` PRZED testem |
| 60 | **DOWÓD MASZYNOWY MA GRANICĘ TAM, GDZIE STRIPE BRONI SIĘ PRZED ROBOTEM — hCaptcha przy `confirm()`; „Przetwarzam…” bez końca to nie wada sklepu, a bez rozpoznania wygląda jak wada** | 30.08: `przelot-osadzony.js` — ramki `hcaptcha-invisible`, `POST api.hcaptcha.com/getcaptcha`, sesja `open/unpaid` po 30 s | `przelot-osadzony.js` rozpoznaje ramkę captchy i kończy kodem 3 z komunikatem; dowód końcowy = człowiek w przeglądarce (CO-WISI §hCaptcha) |

## KLASY DOPISANE 2026-08-30 (wieczór) — z rund recenzji PR o canonicalu (0.9.26) i PR o SECURITY/CONTRIBUTING

| # | Klasa | Dowód | Bramka |
|---|---|---|---|
| 61 | **WARUNEK Z ID Z OPCJI, KTÓRY PRZY PUSTEJ OPCJI JEST PRAWDĄ WSZĘDZIE — `is_page( 0 )` (jak `in_array( 0, … )` bez trzeciego argumentu) zwraca `true` na KAŻDEJ stronie; wtyczka drukowała opis, OG i canonical sklepu w cudze `<head>`; siedziało w 3 miejscach od K5, moja naprawa dołożyła czwarte; a w rundzie 2 wyszły jeszcze TRZY w `sklep-agentow.php` (assety CSS/JS na cudzych stronach), bo mój grep „po naprawie” był zawężony do `includes/` i do kształtu `is_page( (int) get_option` — w pliku głównym ID siedziało w zmiennej** | recenzja canonicala r1 (30.08): `pre_option_sklepag_strona_sklep` → 0, `/uslugi/` dostało `<link rel="canonical" href="…/uslugi/">` i `og:url` sklepu; stan osiągalny z `aktywacja.php` (opcja nie powstaje, gdy `wp_insert_post` zwróci 0) | `sklepag_jest_strona_z_opcji()` (`$id > 0 && is_page( $id )`) w 7 miejscach (4 w `front-seo.php`, 3 w `sklep-agentow.php` — assety); strażnik `testy/seo-canonical-test.php` krok F (opcja pusta, cudza strona: 0 znaczników, 0 meta) + mutant 3 w próbie kontrolnej; po naprawie `grep -rE 'is_(page|single|singular)\( *\(int\) *get_option' wtyczki/` → 0 (boty też czyste, zmierzone 30.08); do zrobienia: ten grep jako bramka w CI |
| 62 | **DOKUMENT BEZPIECZEŃSTWA WYMIENIA ŚRODEK OCHRONY, KTÓREGO NIE MA, ALBO ZAWYŻA OCHRONĘ — „`.gitignore` z `.env*`” (nie było), „token jednorazowy” (jednorazowy jest token opinii; pobrania: 10 przez 72 h), „stan dla wydania 0.9.26” (nie istniało); każde zdanie brzmiało prawdziwie, bo takie zwykle są** | recenzja SECURITY r1 (30.08): 5 uwag WAŻNYCH, r2: 3 kolejne („paczka-zgodnosc w CI” — chodzi przy pakowaniu; „próba kontrolna z podrobionym podpisem webhooka” — nie istnieje; lista RODO węższa niż własny szablon Polityki z `aktywacja.php:188`) — 8 zdań, wszystkie „tekst vs kod”; napisałam z pamięci o projekcie, nie z `grep`; dwa twierdzenia sprawdziłam PRZED zapisem (podpis webhooka, snapshot pliku) i te były dobre — reszty nie | zasada: **każde zdanie SECURITY z miejscem w kodzie** (plik:linia albo komenda) w opisie PR, jak przy zasadzie dowodu; do zrobienia: bramka `dokumentacja-zgodnosc` dla SECURITY.md (wersje z nagłówków wtyczek, wzorce `.gitignore`, limity z `SKLEPAG_*`) |
| 63 | **PRZEPISANA LISTA BRAMEK W DRUGIM PLIKU — mimo zakazu w CLAUDE.md, mimo że TEN SAM plik 16 linii niżej mówił „lista tylko w bramki.sh”; plus wynik `bramki.sh` bez `--tryb pelny` w opisie PR (auto-tryb pominął lint/PHPCS/PHPStan dla samych docs)** | recenzja SECURITY r1 uwagi 4 i 8: „7 z 10 (tryb: tekstowy)” u recenzenta vs „10 z 10” w moim opisie (u mnie drzewo miało pliki PHP w `git status` z drugiej gałęzi → auto-tryb pełny) | CONTRIBUTING każe `--tryb pelny`; do zrobienia: skaner w CI na wzorzec `actionlint, manifest, slady` poza `bramki.sh` (jak `slady` łapie ślady) |

## KLASY DOPISANE 2026-08-30/31 (noc) — z rund 1–2 PR #228 (długi 0.9.27)

| # | Klasa | Dowód | Bramka |
|---|---|---|---|
| 64 | **NAPRAWA KLASY WPROWADZA TĘ SAMĄ KLASĘ W TYM SAMYM PR** — nowa funkcja `sklepag_status_404_wczesnie` użyła `is_page( (int) get_option(...) )` godzinę po domknięciu klasy 61 pomocnikiem; dowód „grep → 0” z #225 był prawdziwy w chwili pomiaru i martwy dla nowego kodu | #228 r1 u1 / r2 u4 (30.08): recenzent tym samym grepem z rejestru | grep klasy 61 (`is_(page|single|singular)\( *\(int\) *get_option`) uruchamiany na KOŃCU każdego PR dotykającego wtyczek, nie raz przy domknięciu; docelowo w `bramki.sh` |
| 65 | **KOMPENSACJA-BLIŹNIAK PRZYWRACA PODZBIÓR** — komentarz mówił „ta sama zasada co Wyślij ponownie”, a bliźniak przywraca PIĘĆ rzeczy (token, termin, licznik, mail_tresc, skrót opinii), moja wersja trzy; strażnik czytał tylko te trzy kolumny, więc świecił zielono | #228 r2 u1: sonda recenzenta — `mail_tresc przywrocona=NIE`, `token opinii przywrocony=NIE` (panel pokazuje link, którego nikt nie dostał; link „oceń bota” umiera) | przy kopiowaniu zasady z bliźniaka: DIFF listy działań bliźniaka vs własnej (wypisz OBIE listy w opisie PR); strażnik asertuje KAŻDĄ pozycję z listy bliźniaka, nie własny podzbiór |
| 66 | **RECENZJA RUNDY N PRZECZYTANA PO PUSHU RUNDY N+1** — pchnęłam commit 4, gdy runda 1 już wisiała nieprzeczytana; pięć uwag wróciło jako „nieusunięte”, runda 2 w połowie o tym | #228 r2 u4–u8 + u10 (30.08, 22:0x) | przed KAŻDYM pushem na PR z biegnącą/zakończoną recenzją: `gh pr view N --json comments` i porównaj znacznik czasu ostatniego komentarza z własnym ostatnim odczytem; strażnik checków ma budzić także na NOWY KOMENTARZ, nie tylko na status |
