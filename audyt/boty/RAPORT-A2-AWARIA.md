# RAPORT AUDYTU A2 — AWARIA: „skąd kod wie, że to się stało"

**Data:** 2026-08-21 · **Soczewka:** A2 (awaria) w brzmieniu wzmocnionym — nie „czy wynik
operacji jest sprawdzany", tylko **czy kod ZMIERZYŁ stan PO operacji, czy tylko założył**.
**commit_head:** `01cfb6bab3a479f5736dc9807fe16e9152dce78f`
**Audytowane drzewo:** `807d72d61c07108a11a934e4d2b74f691ea7c495` — sprawdzone, nie założone:
`git diff --stat 807d72d HEAD -- wtyczki/chatbot-live/ wtyczki/segregator-maila/` → **pusty**
(kod obu wtyczek na HEAD jest bajt w bajt tym z zamrożenia).

---

## PODSUMOWANIE DLA DECYDUJĄCEJ (jeden akapit)

**Trzy znaleziska dowiedzione wykonaniem (F1, F2, F3) i jedna klasa o sześciu dalszych
wystąpieniach odczytanych z kodu, ale nieuruchomionych (F4).** Wszystkie są jedną klasą i
wszystkie leżą **w ChatBocie**, choć Segregator ma na to gotowy, działający wzorzec —
`segm_zapisz_opcje()` — którego ChatBot nie ma w ogóle; to jest podręcznikowe K1 z naszego
destylatu („naprawa trafiła w jeden egzemplarz wzorca"). **Za poważne uważam F1 i F3, i to w
tej kolejności.** F1 — panel melduje „Ustawienia zapisane", gdy baza zapis odrzuciła — dotyka
**dziennego limitu wywołań AI**, czyli stop-lossu kosztowego: właściciel widzi zielone
potwierdzenie, obowiązuje stara liczba, a dowie się z rachunku od dostawcy. F3 — zapis klucza
API nie jest mierzony — jest gorszy niż wygląda, bo właściciel po wymianie klucza kliknie
„Przetestuj klucz" i **przetestuje stary klucz**, dostając zielone potwierdzenie działania
klucza, którego właśnie nie zapisał; wtyczka sama produkuje wtedy fałszywy dowód. F2 (przejęcie
rozmowy melduje sukces przy niewykonanym zapisie, a gość dostaje komunikat „Do rozmowy dołączył
konsultant") jest realne i dowiedzione, ale bije w mniejszą liczbę pieniędzy. F4 traktowałbym
jako pozycję do przeglądu, nie do paniki — z jednym wyjątkiem: **znacznik awarii trybu AI**
(`ai.php:439` w ChatBocie, `ai.php:205` w Segregatorze) jest jedynym sygnałem, że tryb AI po
cichu zjeżdża do reguł, a jego zapis nie jest sprawdzany w żadnej z wtyczek — czyli dokładnie
ta cisza, przed którą ten wpis broni, wraca przy awarii bazy. ⛔ **Kodu nie naprawiałem** —
produktem jest znalezisko plus dowód. Uwaga o ważności: **wszystkie trzy dowiedzione znaleziska
wymagają awarii po stronie bazy** (uszkodzona tabela, pełny dysk, odebrane uprawnienia, replika
tylko do odczytu); nie są to błędy widoczne przy normalnej pracy — ale bliźniacza wtyczka uznaje
ten sam przypadek za wart obsłużenia i obsługuje go poprawnie, więc to nie jest spór o to, czy
warto. Najsłabsze ogniwo raportu nazywam wprost w sekcji CZEGO NIE BYŁO NA MOJEJ LIŚCIE, punkt 5.

---

## STANOWISKO I PRZYRZĄD

| co | wartość | czym zmierzone |
|---|---|---|
| WordPress (rdzeń) | 7.0.4 | `wp core version` |
| PHP | 8.4.21 | `php -r 'echo PHP_VERSION;'` |
| Baza | MariaDB 11.8.8 | `mariadb --version` |
| Wtyczki w kontenerze | `chatbot-live 1.5.8`, `segregator-maila 1.3.1` | `wp plugin list` |

Kod wtyczek wgrany do kontenera **z audytowanego drzewa**, nie z tego, co tam leżało wcześniej
(zastane było `chatbot-live 1.5.7`).

**Przyrząd:** `wp eval-file` na pliku testowym (źródło w załączniku na końcu). Wymusza **realną**
awarię zapisu do bazy filtrem `query` rdzenia WordPressa — podmienia konkretne zapytanie na takie,
które MariaDB odrzuca, więc `$wpdb->update()` zwraca `false` dokładnie tak, jak przy odebranych
uprawnieniach do tabeli albo pełnym dysku. Baza melduje przy tym `Table
'wordpress.tabela_ktorej_nie_ma' doesn't exist`. **To nie jest atrapa: kod obu wtyczek wykonuje
się w całości**, testowane funkcje są wołane po nazwie, a przekierowanie `wp_safe_redirect()`
przechwytywane filtrem `wp_redirect`, żeby dało się odczytać, co dokładnie zobaczy właściciel.

### Kalibracja — bez niej wynik niżej nic nie znaczy

| kod | na audytowanym `807d72d` | po minimalnej naprawie (kopia robocza, **repo nietknięte**) |
|---|---|---|
| T1 (F1) | **PADA** | PRZESZŁO |
| T3 (F2) | **PADA** | PRZESZŁO |
| T5 (F3) | **PADA** | PRZESZŁO |
| T2, T4, T6 (próby kontrolne) | PRZESZŁO | PRZESZŁO |
| **suma** | **ZDANYCH 3/6, PADA 3, EXIT=1** | **ZDANYCH 6/6, PADA 0** |

Po kalibracji kod audytowany wrócił do kontenera, test znów pokazuje 3/6, a `git status
--porcelain` był pusty — łatka żyła wyłącznie w kopii roboczej w katalogu tymczasowym.

**Trzy pozycje kontrolne są tam celowo**, przeciw wnioskowi nr 2 z `MATERIAL-AUDYTU-CHATBOT.md`
(„zielone — sprawdź, czy cokolwiek zmierzył"): T2, T4 i T6 to **bliźniacze operacje wykonane pod
TĄ SAMĄ awarią** i one przechodzą. Czyli awaria jest prawdziwa, a asercja jest spełnialna — test
nie jest „zawsze czerwony" i nie pada na braku pliku.

---

## ZNALEZISKA

Wszystkie należą do jednej klasy: **kod nie zmierzył stanu po operacji, tylko założył, że się
udała**. Punkt odniesienia leży w drugiej wtyczce: `wtyczki/segregator-maila/segregator-maila.php:70`,
`segm_zapisz_opcje()`, którego docblock mówi wprost *„True, gdy po operacji w bazie leży to, co
miało tam trafić"* — i który potwierdza porażkę **odczytem opcji z powrotem**, bo samo `false`
z `update_option()` nie odróżnia „nic się nie zmieniło" od „zapis padł".

### 🐞 F1 — ChatBot melduje „Ustawienia zapisane", gdy baza zapis odrzuciła

**Plik i linia:** `wtyczki/chatbot-live/includes/admin.php:1068`
**Waga:** poważne (dotyka stop-lossu kosztowego AI)
**Status kalibracji:** ✅ test PADA na `807d72d`, PRZECHODZI po naprawie

```
     ChatBot   -> .../admin.php?page=chatl-ustawienia&chatl_ok=zapisano
     w bazie zostalo ai_limit_dzienny=11 (proszono o 999)
T1   PADA      ChatBot: po odrzuconym zapisie wlasciciel NIE moze dostac potwierdzenia "zapisano"
```

`update_option( 'chatl_ustawienia', $nowe );` — wynik wyrzucony, a przekierowanie z
`chatl_ok=zapisano` leci bezwarunkowo linijkę niżej. Właściciel podnosi **dzienny limit wywołań
AI**, zmienia adres powiadomień, tekst awaryjny albo link do polityki prywatności, dostaje
zielony komunikat — i obowiązują stare wartości. Skutek jest niewidzialny do chwili rachunku od
dostawcy albo skargi klienta.

**Bliźniak zamknięty poprawnie, w drugiej wtyczce:** `wtyczki/segregator-maila/includes/admin.php:1258`
to strukturalnie ten sam handler (`current_user_can` → `check_admin_referer` → zbudowanie `$nowe`
→ zapis → redirect), tylko sprawdza wynik i przekierowuje z komunikatem *„Nie udało się zapisać
ustawień — baza odrzuciła zapis. Obowiązują POPRZEDNIE ustawienia (m.in. dzienny limit wywołań
AI)"*. To jest dowód T2 z tego samego przebiegu:

```
Warning: segm_post_ustawienia_zapisz(): zapis do bazy nie powiódł się (ustawienia wtyczki
  (limit AI, usuwanie danych)): Table 'wordpress.tabela_ktorej_nie_ma' doesn't exist
     Segregator-> .../admin.php?page=segm-ustawienia&segm_blad=Nie%20uda%C5%82o%20si%C4%99%20zapisa%C4%87…
     w bazie zostalo ai_limit_dzienny=11 (proszono o 999)
T2   PRZESZLO  PROBA KONTROLNA: blizniaczy handler Segregatora na TEJ SAMEJ awarii
```

### 🐞 F2 — `chatl_przejmij()` zwraca `true` nigdy tego nie sprawdziwszy, a gość dostaje nieprawdę

**Plik i linia:** `wtyczki/chatbot-live/includes/przejecie.php:239-262` (zapis: `:245`)
**Waga:** średnia (myli gościa i operatora, nie kosztuje pieniędzy)
**Status kalibracji:** ✅ test PADA na `807d72d`, PRZECHODZI po naprawie

```
T4   PRZESZLO  PROBA KONTROLNA: przy dzialajacej bazie przejecie zwraca true I stan=czlowiek (czlowiek)
     chatl_przejmij() zwrocilo: true; stan w bazie: 'bot'
     komunikatow 'Do rozmowy dolaczyl konsultant' dopisanych goscowi: 1
T3   PADA      ChatBot: chatl_przejmij() ma zwrocic false, gdy przejecie NIE doszlo do skutku
```

Docblock nad funkcją brzmi `@return bool Czy przejęcie doszło do skutku.` — a `return true;` stoi
na końcu bezwarunkowo, wynik `$wpdb->update` nigdzie nie ląduje. Pod awarią zapisu rozmowa
**zostaje przy bocie**, operator dostaje `chatl_ok=przejeta` (`includes/operator.php:308-309`
także ignoruje zwrotkę), a do rozmowy **wchodzi gościowi komunikat** *„Do rozmowy dołączył
konsultant — od tej chwili odpowiada człowiek, nie bot."*. Gość czeka na człowieka, którego nie
ma; odpowiada mu dalej bot.

**Pozostałe zapisy w tym samym pliku, z tej samej klasy, ani jeden sprawdzony** — `przejecie.php`:

| linia | operacja | skutek porażki |
|---|---|---|
| `:65` | `chatl_nowa_sesja()`, INSERT sesji | funkcja zwraca identyfikator i ustawia ciastko także wtedy, gdy wiersza nie ma; rozmowa **nie pojawi się w panelu operatora**, bo `chatl_zywe_rozmowy()` czyta `FROM chatl_sesje` — gość pisze w próżnię |
| `:212` | `chatl_oddaj_botowi()` | rozmowa zostaje przy człowieku, gość dostaje „konsultant zakończył" |
| `:245` | `chatl_przejmij()` | **dowiedzione wyżej** |
| `:275` | `chatl_operator_wiadomosc()` | znacznik aktywności operatora nie odświeżony → rozmowa może wrócić do bota mimo pisania |
| `:364` | sprzątanie po ciszy operatora | rozmowy zostają zawieszone w stanie `czlowiek` |

⚠️ **Uruchomiłem tylko `:245`.** Pozostałych czterech nie mierzyłem — zgłaszam je jako to samo
wystąpienie klasy, nie jako osobno dowiedzione.

⚠️ **Uczciwie o granicy mojej łatki kalibracyjnej:** `false === $wynik` to **minimum, nie pełny
pomiar**. `$wpdb->update` zwraca `0` (nie `false`), gdy żaden wiersz nie pasował — np. gdy wiersza
sesji nie ma. Prawdziwe „skąd wiem, że się stało" przy tej operacji to odczyt kolumny `stan` po
zapisie, tak jak robi to `segm_skrzynka_zapisz_haslo()`.

### 🐞 F3 — zapis klucza API bez pomiaru; stary klucz zostaje, nikt się nie dowiaduje

**Plik i linia:** `wtyczki/chatbot-live/includes/ai.php:99-112`
**Waga:** poważne (wtyczka produkuje fałszywy dowód poprawności — patrz niżej)
**Status kalibracji:** ✅ test PADA na `807d72d`, PRZECHODZI po naprawie

```
     chatl_zapisz_klucz() zwrocilo: NULL; w bazie: 'STARY-KLUCZ'
T5   PADA      ChatBot: zapis klucza API musi dac wolajacemu odrozniac sukces od porazki
     segm_skrzynka_zapisz_haslo() zwrocilo: false; w bazie: 'STARE-HASLO'
T6   PRZESZLO  PROBA KONTROLNA: blizniaczy zapis sekretu w Segregatorze MIERZY stan po operacji
```

`chatl_zapisz_klucz()` zwraca `void`. Wołający (`includes/admin.php:1057-1060`) nie ma jak
odróżnić zapisu od jego braku, a redirect `chatl_ok=zapisano` leci i tak (to F1).

**Dlaczego uważam to za poważniejsze niż F2:** przy wymianie klucza na nowy właściciel kliknie
potem **„Przetestuj klucz"** — i przetestuje **stary** klucz, więc dostanie zielone potwierdzenie
działania klucza, którego właśnie nie zapisał. Wtyczka nie tylko przemilcza porażkę, ale
dostarcza dowodu, że jej nie było.

**T6 pokazuje, że to nie jest ograniczenie platformy:** `wtyczki/segregator-maila/includes/skrzynka.php:120-134`,
`segm_skrzynka_zapisz_haslo()`, robi dokładnie tę samą operację (zapis sekretu z `autoload=no`,
gałąź `add_option`/`update_option`) na tej samej awarii i **odczytuje opcję z powrotem**,
zwracając `false`.

**Bliźniak niezamknięty, w Segregatorze:** `wtyczki/segregator-maila/includes/ai.php:159-172` —
`segm_zapisz_klucz()` jest kopią `chatl_zapisz_klucz()` co do kształtu (ta sama gałąź, ten sam
`void`), stoi **osiem linii nad** poprawnym wzorcem z T6 w tej samej wtyczce. ⚠️ **Nie
uruchamiałem go** — zgłaszam z odczytu kodu, nie z pomiaru.

### ⚠️ F4 — pełny spis tej samej klasy, 6 dalszych miejsc, NIE URUCHOMIONE

Wypisuję komplet, żeby nie powtórzyło się „naprawiłem tylko jedno wystąpienie z pięciu".
**Żadnego z poniższych nie zmierzyłem** — to odczyt kodu.

| plik:linia | co zapisuje | dlaczego to boli |
|---|---|---|
| `wtyczki/chatbot-live/includes/ai.php:439` | znacznik awarii trybu AI | **najważniejszy z tej szóstki** — to jedyny sygnał właściciela, że tryb AI po cichu zjeżdża do reguł |
| `wtyczki/segregator-maila/includes/ai.php:205` | j.w., bliźniak | komentarz nad kodem mówi wprost: *„bez tego wpisu wtyczka miesiącami «działa w trybie AI»"* — porażka zapisu przywraca dokładnie tę ciszę |
| `wtyczki/chatbot-live/includes/rodo.php:99` | ślad wniosku RODO (rozmowy współdzielone) | zapisywany **przed** kasowaniem; porażka nie wstrzymuje kasowania, dowód współdzielenia przepada |
| `wtyczki/chatbot-live/includes/front.php:202` | znacznik/licznik na ścieżce gościa | sygnał dla właściciela ginie |
| `wtyczki/chatbot-live/includes/front.php:699` | licznik zadziałań pułapki na boty | licznik istnieje po to, żeby fałszywy alarm był WIDOCZNY — niezapisany, nie jest |
| `wtyczki/chatbot-live/includes/front.php:715` | znacznik „powiadomienia mailowe nie wychodzą" | ironia klasy: znacznik awarii poczty sam pada po cichu |

**Niższa waga, ta sama klasa:** `*_wersja_schematu` w czterech miejscach
(`wtyczki/chatbot-live/chatbot-live.php:78`, `wtyczki/chatbot-live/includes/instalator.php:133`,
`wtyczki/segregator-maila/segregator-maila.php:111`,
`wtyczki/segregator-maila/includes/instalator.php:120`) — porażka jest tu idempotentna (migracja
pobiegnie ponownie przy następnym żądaniu), ale kod tak samo nie wie, czy się udało.

---

## CO W TEJ SOCZEWCE SPRAWDZIŁEM I WYSZŁO CZYSTO

Nie są to znaleziska i uważam tę sekcję za ważniejszą od listy wyżej — pokazuje, że klasa z F1–F4
jest **wyjątkiem w tym kodzie**, nie regułą.

- **Ścieżka AI, ChatBot** (`includes/ai.php:620-696`): `is_wp_error` + kod HTTP, jeden retry
  wyłącznie dla 5xx/429/błędu połączenia (nie dla 4xx), a po HTTP 200 kod **nie zakłada sukcesu** —
  dekoduje ciało i traktuje nieczytelną odpowiedź jak awarię, z uzasadnieniem w komentarzu, że
  dla właściciela skutek jest identyczny jak przy złym kluczu. Licznik zużycia naliczany **po**
  kodzie 200 (dostawca policzył wywołanie niezależnie od tego, czy treść da się użyć). Znacznik
  awarii gaśnie sam po pierwszym udanym wywołaniu. Padnięty inkrement licznika **jest** sprawdzany
  (`ai.php:225`).
- **Formularz kontaktu** (`includes/front.php:579-620`): INSERT sprawdzony — gość dostaje HTTP 500
  i prawdę zamiast „Dziękujemy"; wynik `wp_mail()` sprawdzony i przekuty na znacznik dla
  właściciela, bo gościowi ta informacja jest niepotrzebna (jego wiadomość *jest* w bazie).
- **Kasownik RODO** (`includes/rodo.php:329-380`): raportuje **zmierzone liczby** skasowanych
  wierszy, a przy zerze pisze wprost, że nic nie usunięto — z komentarzem tłumaczącym, że
  wcześniejsza wersja potwierdzała nieprawdę w cudzym imieniu.
- **Przebieg skrzynki IMAP** (`wtyczki/segregator-maila/includes/skrzynka-przebieg.php:250-360`):
  każdy `przenies()` / `utworz_folder()` / `otworz()` sprawdzany po wyniku; awaria zapisu historii
  **po udanym** przeniesieniu na serwerze daje właścicielowi zdanie mówiące dokładnie, co się
  stało (*„Wiadomości ZOSTAŁY przeniesione na serwerze, ale zapis historii do bazy nie powiódł się
  — nie ma z czego cofnąć tego przebiegu"*). To jest wzorcowa obsługa awarii w połowie operacji.
- **Ustawienia i zgody Segregatora** — 7 wywołań `segm_zapisz_opcje()`, w tym zgoda na wysyłkę
  próbki do AI: przy odrzuconym zapisie tryby AI **zostają zablokowane** i tekst to mówi.

---

## CZEGO NIE SPRAWDZIŁEM / CZEGO NIE DA SIĘ ORZEC

- **Sześć punktów z F4 oraz cztery zapisy `*_wersja_schematu`** — odczytane, **nie uruchomione**.
  Budżet 25 wywołań narzędzi poszedł na skalibrowanie trzech dowiedzionych.
- **Cztery pozostałe zapisy w `przejecie.php`** (`:65`, `:212`, `:275`, `:364`) — j.w.
- **`segm_zapisz_klucz()`** (`wtyczki/segregator-maila/includes/ai.php:159-172`) — zgłoszony
  z odczytu kodu jako bliźniak F3, **nie zmierzony**.
- **Czy któraś z tych ścieżek pada BEZ awarii bazy** — nie sprawdziłem, czy w produkcji istnieje
  przebieg, w którym `$wpdb->update` zwraca `0` zamiast `false` (np. wiersz sesji skasowany między
  narysowaniem panelu a kliknięciem „Przejmij"). Podejrzewam, że tak przy F2 — **nie zmierzyłem**.
- **Tryb AI u prawdziwego dostawcy** — klucz na stanowisku jest nieważny (HTTP 401, §5
  `MATERIAL-AUDYTU-CHATBOT.md`). Wszystko, co piszę o ścieżce AI, pochodzi z odczytu kodu i
  z udawanego punktu końcowego. **NIE DA SIĘ ORZEC** bez ważnego klucza.
- **Nie klikałem w przeglądarce** (warunek W1). Ten raport pokrywa jedną soczewkę wykonywaną
  przez `wp eval-file`; W1 zostaje otwarty i **nie uważam go za spełniony**.

---

## CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Moje pytanie brzmiało „co, gdy padnie" i dlatego **nie zapytało** o poniższe. Wszystkie przy tych
modułach wyglądają na istotne; punkty 3–6 uważam za realnie bolesne dla kupującego.

1. **Awaria, która nie jest awarią bazy ani sieci: PHP kończy się w połowie.** Limit czasu
   hostingu albo zabity proces w środku pętli przenoszenia IMAP. `SEGM_SKRZ_LIMIT_CZASU` broni
   przed urwaniem, ale nikt nie sprawdził, co zostaje, gdy proces zniknie **po** `UID MOVE`
   i **przed** `$wpdb->insert` historii. Żadne sprawdzanie wyniku tego nie łapie — tu potrzebna
   jest transakcja albo dziennik zamiaru, a wtyczka nie ma ani jednego, ani drugiego.
2. **Współbieżność liczników.** `chatl_ai_zuzyj()` jest atomowy (`ON DUPLICATE KEY UPDATE`), ale
   sprawdzenie limitu i jego zużycie to dwa osobne żądania do bazy — nie pytałem, ile wywołań
   ponad limit przechodzi przy salwie równoległej. Autor materiału sam pisze, że żaden jego pomiar
   tego nie dotyczy.
3. **Blokada przebiegu skrzynki** (`segm_zwolnij_blokade()`) — pytałem o zapisy, nie o samą
   blokadę. Co ją zdejmuje, gdy przebieg zginie przed `rozlacz()`? Blokada, która zostaje po
   zabitym przebiegu, **cicho wyłącza cron na zawsze** i wygląda dokładnie tak samo jak „nic
   nowego nie przyszło".
4. **`DISABLE_WP_CRON`.** Cała retencja (90 dni, obiecana gościowi w tekście o prywatności i we
   wniosku RODO) wisi na WP-Cronie. Jeśli właściciel go wyłączy i nie ustawi systemowego,
   **obietnica retencji przestaje być prawdziwa**, a nic w panelu tego nie melduje. To jest
   pytanie „skąd kod wie", którego nie zadałem, bo dotyczy braku wywołania, nie jego wyniku.
5. **Czy właściciel te błędy w ogóle ZOBACZY.** `*_log_bledu_bazy()` idzie przez
   `wp_trigger_error()`, który **respektuje `WP_DEBUG`** — na typowym hostingu produkcyjnym
   (`WP_DEBUG = false`) log **nie powstaje**. Komentarz w kodzie mówi o tym uczciwie i wskazuje
   komunikat dla użytkownika jako właściwą obronę — ale to znaczy, że wszędzie tam, gdzie
   komunikatu dla użytkownika nie ma (F1, F2, F3 i cała szóstka z F4), błąd **ginie całkowicie**.
   Nie zmierzyłem tego. To najpoważniejsza kategoria, o którą moje pytanie nie zapytało, i gdyby
   ktoś miał sprawdzić po mnie jedną rzecz, to tę.
6. **`chatl_sesje` nie ma retencji i nie jest ruszana przez kasownik RODO** — tabela rośnie bez
   końca, a wiersz sesji przeżywa skasowanie rozmowy na wniosek RODO. To pytanie o dane, nie
   o awarię, więc leżało poza moją soczewką.

---

## ZAŁĄCZNIK — źródło testu (do odtworzenia wyniku)

Zapisz jako plik, wgraj do kontenera i uruchom:

```
docker cp test-awaria-zapisu.php strona_wp_wordpress:/tmp/t.php
docker exec strona_wp_wordpress wp --allow-root eval-file /tmp/t.php ; echo "EXIT=$?"
```

Oczekiwane na `807d72d`: `ZDANYCH 3/6, PADA 3`, `EXIT=1`.
⚠️ Test zapisuje i zeruje opcje `chatl_klucz_api` / `segm_imap_haslo` oraz zakłada wiersz
w `chatl_sesje` — **uruchamiać wyłącznie na stanowisku testowym**, nigdy na instalacji klienta.

```php
/**
 * PADAJACY TEST — soczewka "skad kod wie, ze sie udalo".
 * Wymusza REALNA awarie zapisu do bazy (filtr 'query' rdzenia WP psuje
 * konkretne zapytanie -> $wpdb->update zwraca false, dokladnie jak przy
 * odebranych uprawnieniach do tabeli / pelnym dysku) i pyta, co widzi
 * wlasciciel. Kod wtyczek wykonuje sie NAPRAWDE, nic nie jest atrapa.
 */
global $wpdb;
// Panele obu wtyczek ladowane sa pod is_admin(); WP-CLI nie jest adminem,
// wiec dociagamy DOKLADNIE te pliki, ktore laduje wtyczka w panelu.
foreach ( array( 'chatbot-live/includes/admin.php', 'chatbot-live/includes/operator.php', 'chatbot-live/includes/import.php', 'chatbot-live/includes/csv.php', 'chatbot-live/includes/statystyki.php', 'chatbot-live/includes/powitanie.php', 'segregator-maila/includes/csv.php', 'segregator-maila/includes/admin.php', 'segregator-maila/includes/admin-probka.php', 'segregator-maila/includes/admin-skrzynka.php', 'segregator-maila/includes/admin-powitanie.php', 'segregator-maila/includes/filtry.php', 'segregator-maila/includes/konfiguracja.php' ) as $plik ) {
	if ( file_exists( WP_PLUGIN_DIR . '/' . $plik ) ) { require_once WP_PLUGIN_DIR . '/' . $plik; }
}
$wpdb->suppress_errors( true );
wp_set_current_user( 1 );
$GLOBALS['PAD'] = 0;
$GLOBALS['ZDA'] = 0;

function zdaj( $id, $ok, $co ) {
	$GLOBALS['ZDA']++;
	if ( ! $ok ) { $GLOBALS['PAD']++; }
	printf( "%-4s %-9s %s\n", $id, $ok ? 'PRZESZLO' : 'PADA', $co );
}
/** Psuje zapis do wp_options dla wskazanej opcji (albo UPDATE wskazanej tabeli). */
function psuj( $igla ) {
	$f = function ( $q ) use ( $igla ) {
		if ( false !== stripos( $q, $igla ) && 0 === stripos( ltrim( $q ), 'UPDATE' ) ) {
			return 'UPDATE ' . $GLOBALS['wpdb']->options . ' SET option_value = 1 WHERE option_id = (SELECT 1 FROM tabela_ktorej_nie_ma)';
		}
		return $q;
	};
	add_filter( 'query', $f, 999 );
	return $f;
}
function odpsuj( $f ) { remove_filter( 'query', $f, 999 ); }

/** Uruchamia handler admin_post i zwraca adres przekierowania. */
function gdzie_przekieruje( $fn ) {
	$przechwyt = function ( $l ) { throw new Exception( 'R:' . $l ); };
	add_filter( 'wp_redirect', $przechwyt, 1 );
	try { $fn(); $wynik = '(brak przekierowania)'; }
	catch ( Exception $e ) { $wynik = substr( $e->getMessage(), 2 ); }
	remove_filter( 'wp_redirect', $przechwyt, 1 );
	return $wynik;
}

echo "===== T1/T2: zapis USTAWIEN, gdy baza odrzuca zapis =====\n";

update_option( 'chatl_ustawienia', array_merge( chatl_ustawienia(), array( 'ai_limit_dzienny' => 11 ) ) );
$_POST = array( 'wlaczony' => 1, 'ai_limit_dzienny' => 999 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
$f = psuj( 'chatl_ustawienia' );
$adres_ch = gdzie_przekieruje( 'chatl_post_ustawienia_zapisz' );
odpsuj( $f );
wp_cache_flush();
$limit_ch = (int) chatl_ustawienia()['ai_limit_dzienny'];
echo "     ChatBot   -> $adres_ch\n     w bazie zostalo ai_limit_dzienny=$limit_ch (proszono o 999)\n";
zdaj( 'T1', 999 === $limit_ch || false === strpos( $adres_ch, 'chatl_ok=zapisano' ),
	'ChatBot: po odrzuconym zapisie wlasciciel NIE moze dostac potwierdzenia "zapisano"' );

update_option( 'segm_ustawienia', array_merge( segm_ustawienia(), array( 'ai_limit_dzienny' => 11 ) ) );
$_POST = array( 'ai_limit_dzienny' => 999 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
$f = psuj( 'segm_ustawienia' );
$adres_se = gdzie_przekieruje( 'segm_post_ustawienia_zapisz' );
odpsuj( $f );
wp_cache_flush();
$limit_se = (int) segm_ustawienia()['ai_limit_dzienny'];
echo "     Segregator-> $adres_se\n     w bazie zostalo ai_limit_dzienny=$limit_se (proszono o 999)\n";
zdaj( 'T2', 999 === $limit_se || false === strpos( $adres_se, 'segm_ok=zapisano' ),
	'PROBA KONTROLNA: blizniaczy handler Segregatora na TEJ SAMEJ awarii' );

echo "\n===== T3/T4: PRZEJECIE ROZMOWY, gdy baza odrzuca zapis =====\n";
$sesja = chatl_nowa_sesja();
$wynik_ok = chatl_przejmij( $sesja, 1 );
$stan_ok  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $sesja ) );
zdaj( 'T4', true === $wynik_ok && 'czlowiek' === $stan_ok,
	'PROBA KONTROLNA: przy dzialajacej bazie przejecie zwraca true I stan=czlowiek (' . $stan_ok . ')' );

chatl_oddaj_botowi( $sesja, '' );
wp_cache_flush();
$przed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE tresc LIKE 'Do rozmowy dolaczyl%' OR tresc LIKE 'Do rozmowy dołączył%'" );
$f = psuj( 'chatl_sesje' );
$wynik_pad = chatl_przejmij( $sesja, 1 );
odpsuj( $f );
$stan_pad = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $sesja ) );
$po = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE tresc LIKE 'Do rozmowy dolaczyl%' OR tresc LIKE 'Do rozmowy dołączył%'" );
echo "     chatl_przejmij() zwrocilo: " . var_export( $wynik_pad, true ) . "; stan w bazie: '$stan_pad'\n";
echo "     komunikatow 'Do rozmowy dolaczyl konsultant' dopisanych goscowi: " . ( $po - $przed ) . "\n";
zdaj( 'T3', ( 'czlowiek' === $stan_pad ) || ( false === $wynik_pad ),
	'ChatBot: chatl_przejmij() ma zwrocic false, gdy przejecie NIE doszlo do skutku (docblock: "Czy przejecie doszlo do skutku")' );

echo "\n===== T5/T6: ZAPIS SEKRETU (klucz API / haslo IMAP), gdy baza odrzuca zapis =====\n";
update_option( 'chatl_klucz_api', 'STARY-KLUCZ' );
$f = psuj( 'chatl_klucz_api' );
$r5 = chatl_zapisz_klucz( 'NOWY-KLUCZ' );
odpsuj( $f );
wp_cache_flush();
echo "     chatl_zapisz_klucz() zwrocilo: " . var_export( $r5, true ) . "; w bazie: '" . get_option( 'chatl_klucz_api' ) . "'\n";
zdaj( 'T5', 'NOWY-KLUCZ' === get_option( 'chatl_klucz_api' ) || false === $r5,
	'ChatBot: zapis klucza API musi dac wolajacemu odrozniac sukces od porazki' );

update_option( 'segm_imap_haslo', 'STARE-HASLO' );
$f = psuj( 'segm_imap_haslo' );
$r6 = segm_skrzynka_zapisz_haslo( 'NOWE-HASLO' );
odpsuj( $f );
wp_cache_flush();
echo "     segm_skrzynka_zapisz_haslo() zwrocilo: " . var_export( $r6, true ) . "; w bazie: '" . get_option( 'segm_imap_haslo' ) . "'\n";
zdaj( 'T6', 'NOWE-HASLO' === get_option( 'segm_imap_haslo' ) || false === $r6,
	'PROBA KONTROLNA: blizniaczy zapis sekretu w Segregatorze MIERZY stan po operacji' );

update_option( 'chatl_klucz_api', '' );
update_option( 'segm_imap_haslo', '' );
echo "\nZDANYCH " . ( $GLOBALS['ZDA'] - $GLOBALS['PAD'] ) . "/" . $GLOBALS['ZDA'] . ", PADA " . $GLOBALS['PAD'] . "\n";
exit( $GLOBALS['PAD'] > 0 ? 1 : 0 );
```
