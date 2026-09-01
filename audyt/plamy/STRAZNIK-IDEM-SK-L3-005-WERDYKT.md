# WERDYKT: `testy/idem-nie-oddaje-oplaconej-sesji-test.php` pada — **kłamie TEST, nie produkt**

**Kto:** audytor, 2026-09-01. **Gałąź:** `audyt/czesciowo-01-09` (po scaleniu `origin/main`).
**Pytanie koordynatorki:** regresja produktu czy kłamiący strażnik? **Odpowiedź: kłamiący
strażnik.** Ochrona przed podwójnym kliknięciem **działa**, naprawa `SK-L3-005` **żyje**.
⛔ Kodu produktu ani pliku testu **nie tknąłem** — poprawiona kopia testu leży poza repo,
w katalogu roboczym sesji. Naprawę bierze builder.

## PLIK I LINIA — jedno zdanie

**`testy/idem-nie-oddaje-oplaconej-sesji-test.php:50`** buduje klucz okna z **dwóch** członów,
a **`wtyczki/sklep-agentow/includes/zakup.php:177`** czyta klucz z **trzech**:

```
test:50    $okno = 'sklepag_idem_' . md5( sklepag_ip() . '|' . (int) $produkt->id );
kod:177    $okno = 'sklepag_idem_' . md5( sklepag_ip() . '|' . $produkt_id . '|' . ( $osadzona ? 'o' : 'h' ) );
```

Trzeci człon (`o` = płatność osadzona, `h` = hostowana) wszedł w **0.9.25**, PR **#222**
(`2992247 feat(sklep): płatność osadzona na stronie produktu…`). Strażnik powstał w **0.9.18**,
PR #199/#200, i **od tamtej pory nie był tknięty** (`git log` na pliku testu: ostatni commit
`df53b1f`; `git log -S osadzona` na pliku testu: **zero trafień** — nigdy nie poznał trybu).
Czyli: **strażnik zapisuje transient pod kluczem, którego handler nigdy nie czyta**, i robi to
od siedmiu wydań.

## TRZY PUŁAPKI Z NAGŁÓWKA — sprawdzone ZANIM postawiłem werdykt

| pułapka | sprawdzenie | wynik |
|---|---|---|
| **Stos nie odpowiada → `EXIT=2`, nic nie zmierzone** | `bash narzedzia-qa/stos.sh stan` **przed** czymkolwiek | **`EXIT=0`** — WP `HTTP 200`, rdzeń 7.1, kontener wstał, łapacz 92 wiadomości. Pomiar odbył się na żywym stosie |
| **Limiter zakupu: 10 prób na adres i minutę, w CLI adres stały** | oba przebiegi zakończyły się **kodem 0 albo 1**, nie 2; komunikat „Za dużo prób zakupu" nie padł ani razu; przebiegi rozłożone w czasie | **nie dotknięty** — werdykt nie stoi na `EXIT=2` |
| **B i C to ten sam wiersz w bazie, rozstrzyga wiek okna (`SKLEPAG_IDEM_OKNO_S = 5 s`)** | w obu przebiegach C ustawia okno na `time() - 30` (5 + 25), a B na `time()`; C zachowuje się identycznie w obu przebiegach, zmienia się **wyłącznie** B | **nie mylę B z C** — gdyby test był „wolny o sekundę", padłoby B **i** C razem albo B zachowałoby się losowo między przebiegami; tu B jest deterministyczne: FAIL przy kluczu z dwóch członów, PASS przy trzech |

## DOWÓD — dwa przebiegi, różnica JEDNA LINIA W TEŚCIE, kod produktu ten sam

Oba na tym samym stosie, tym samym kodzie wtyczki (`sklep-agentow` **0.9.32** w kontenerze;
`zakup.php:177` w kontenerze **identyczny** z `main`), tą samą komendą
(`docker exec -u www-data … wp eval-file`).

**1) Plik z repo, bez zmian:**
```
A. okno z sesja OPLACONA  -> …NOWA_SESJA…   WYNIK=PASS
B. okno z sesja CZEKAJACA -> …NOWA_SESJA…   WYNIK=FAIL - ochrona przed podwojnym klikiem przestala dzialac
C. okno STARE (30 s)      -> …NOWA_SESJA…   WYNIK=PASS
RAZEM: WYNIK=FAIL
EXIT=1
```

**2) Ta sama kopia, zmieniona WYŁĄCZNIE w linii 50** (dopisane `. '|h'`, zero zmian w produkcie):
```
A. okno z sesja OPLACONA  -> …NOWA_SESJA…    WYNIK=PASS
B. okno z sesja CZEKAJACA -> …STARA_SESJA…   WYNIK=PASS
C. okno STARE (30 s)      -> …NOWA_SESJA…    WYNIK=PASS
RAZEM: WYNIK=PASS
EXIT=0
```

**3) Mechanizm — pomiar samego klucza** (sonda nie woła handlera, więc nie zużywa limitera).
Zapis transientu **dokładnie tak, jak robi to strażnik**, potem odczyt trzema kluczami:
```
produkt #1 · adres w CLI: 127.0.0.1
klucz STRAZNIKA  (ip|id)   = sklepag_idem_2183d4d2e1ab434d340e29b8dbb9dcfc -> TABLICA (jest)
klucz HANDLERA h (ip|id|h) = sklepag_idem_b2c8700194042527a4b65d6dfdee1851 -> FALSE (handler NIC nie widzi)
klucz HANDLERA o (ip|id|o) = sklepag_idem_384fd690c4b79a6a1f1e15ccad1a4aa1 -> FALSE (handler NIC nie widzi)
```
⭐ **Potwierdzenie z niezależnego źródła:** klucz `sklepag_idem_b2c8700194042527a4b65d6dfdee1851`
widnieje w **żywej bazie stosu** wśród transientów sklepu (spis zrobiony przy terenie `A5`) —
czyli prawdziwe zakupy naprawdę zapisują okno pod kluczem z członem `|h`, a strażnik pod innym.

## ⛔ RZECZ GROŹNIEJSZA NIŻ SAMO PADANIE: ten strażnik **od 0.9.25 nie pilnuje niczego**

Skoro handler nie widzi okna założonego przez test, to **kierunki A i C przechodzą pusto**:
handler nie znajduje żadnego okna, więc tworzy nowy checkout — a nowy checkout to **dokładnie
to, czego A i C oczekują**. Ich `PASS` nie mówi „naprawa `SK-L3-005` działa"; mówi „nie było
czego sprawdzić". Gdyby ktoś cofnął naprawę z 0.9.18, ten strażnik **nadal** dałby A=PASS
i C=PASS. Jedynym kierunkiem, który w ogóle zauważył rozjazd, jest B — i to jego `FAIL` jest
tu kanarkiem, nie objawem regresji.

Naprawa klucza jest więc **warunkiem koniecznym, żeby A i C w ogóle zaczęły mierzyć**.
Że po jej wprowadzeniu naprawa `SK-L3-005` faktycznie żyje — pokazuje przebieg (2): przy
poprawnym kluczu A dostaje widoczne okno z sesją **opłaconą** i handler mimo to zakłada nowy
checkout, czyli **kontrola stanu zapamiętanej sesji działa**.

## Dlaczego nikt tego nie złapał przez siedem wydań

Ten strażnik **nie jest w bramkach ani w CI**: `grep '^BRAMKI=' narzedzia-qa/bramki.sh` →
`actionlint manifest slady znaleziska status-bez-warunku paczka zdarzenia-webhooka php-lint
phpcs phpstan gitleaks poswiadczenia` — nie ma go tam, `grep -rn 'idem-nie-oddaje' narzedzia-qa/ .github/`
→ **zero trafień**. Wymaga żywego stosu, więc chodzi wyłącznie ręcznie. Ostatni zapisany
przebieg zielony to `rubryka-GOTOWE.md:23` — **„EXIT=0, 30.08"** — i on **nie jest sprzeczny**
z tym werdyktem: klucz rozjechał się commitem `2992247` z **30.08 o 17:46**, więc przebieg
z tamtego dnia mógł być wcześniejszy i wtedy naprawdę zielony. Zostaje osobna lekcja:
ten wpis jest **zbiorczy dla trzech strażników** i nie rozdziela ich kodów wyjścia, więc
z rubryki nie da się odczytać, który z nich przeszedł i kiedy.

## CO ODDAJĘ BUILDEROWI (nie robię tego sam)

1. **`testy/idem-nie-oddaje-oplaconej-sesji-test.php:50`** — klucz musi mieć trzeci człon.
   ⛔ Nie wystarczy dopisać `'|h'` na sztywno: strażnik woła `sklepag_obsluz_kup()` bez
   `$_POST['osadzona']`, więc handler idzie ścieżką hostowaną — ale gdy jutro zmieni się
   domyślny tryb, test znów rozjedzie się po cichu. Sensowniej **wyliczyć klucz tą samą
   funkcją co produkt** (dziś jej nie ma — klucz jest składany w miejscu użycia,
   `zakup.php:177`), czyli naprawić **klasę**: jedno źródło klucza dla kodu i dla testu.
2. ⛔ **KALIBRACJĘ TRZEBA POWTÓRZYĆ, NIE PRZEPISAĆ Z PR #199.** To nie jest formalność
   i nie wolno tu iść na skróty. Dowód kalibracji z PR #199 mówi: „strażnik pada na kodzie
   sprzed naprawy `SK-L3-005`" — i był prawdziwy w 0.9.18, gdy klucz się zgadzał. **Dziś jest
   nieprawdziwy**: przy kluczu, którego handler nie czyta, kierunek A przechodzi pusto,
   więc na kodzie sprzed 0.9.18 ten strażnik **skończyłby się zerem**. Dopóki po naprawie
   klucza nikt nie pokaże ŚWIEŻEGO przebiegu padającego na kodzie sprzed naprawy (kod wyjścia,
   nie zdanie), naprawiony strażnik jest **nieskalibrowany** — czyli dokładnie tym samym,
   czym jest dziś: testem, który przechodzi zawsze.
3. **Nagłówek testu kłamie o adresie**: mówi „w CLI `sklepag_ip()` zwraca stale `0.0.0.0`"
   (`testy/idem-nie-oddaje-oplaconej-sesji-test.php:29` i powtórzone w komentarzu przy
   limiterze) — **zmierzone: `127.0.0.1`** (sonda kluczy, `EXIT=0`). Wniosku to nie zmienia,
   bo adres i tak jest stały i licznik dzielą wszystkie przebiegi, ale to wartość wpisana
   w dokumentację niezgodna z pomiarem — poprawić przy okazji naprawy klucza.
4. **Rejestr**: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md`, wiersz `SK-L3-005` ma w kolumnie
   strażnika ten plik. Dopóki klucz jest rozjechany, wiersz „ZAMKNIETE + strażnik" jest
   **prawdziwy co do naprawy i nieprawdziwy co do strażnika**.

## Czego NIE zmierzyłem

- **Ścieżki osadzonej (`|o`)** — strażnik jej nie dotyka, ja też nie; nie wiem, czy okno
  w trybie osadzonym zachowuje się tak samo (kod jest wspólny, ale to nie jest pomiar).
- **Zachowania w przeglądarce** — cały dowód jest na poziomie handlera wołanego z CLI.
- **Czy poza tym jednym plikiem coś jeszcze buduje ten klucz** — sprawdzone i **nie**:
  `grep -rln 'sklepag_idem_' testy/ narzedzia-qa/` zwraca wyłącznie ten test.
