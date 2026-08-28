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
| 25 | **TOKEN JEDNORAZOWY BEZ BLOKADY — dwie sesje z jednego linku** | `Front/Login.php:204` — odczyt tokenu i skasowanie go jako dwie osobne operacje. Recenzent: „jeden token moze w bardzo krotkim oknie utworzyc wiecej niz jedna sesje". ⛔ **POWTÓRKA błędu #3 z rund 1–3** (race condition w blokadzie importera), który mieliśmy oznaczony ✅ | Zużycie tokenu = **JEDNA operacja z warunkiem**, nie „sprawdź, potem zapisz". Wzorce w tym repo: `pobieranie.php` (`UPDATE … WHERE id=%d AND pobrania_uzyte < pobrania_limit`) i `opinie.php` (CAS `… WHERE zamowienie_id=%d AND status='zalazek'`). Test **równoległy**: 2 żądania z tym samym tokenem → przechodzi dokładnie 1 |
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
