# SZKICE BOTÓW — do recenzji Dzidka PRZED budową (bramka z planu v3 §9)

Status: SZKIC 18.08, **zaktualizowany po decyzji Dzidka (18.08): HYBRYDA** — każdy
bot ma DWA silniki: regułowy (działa bez klucza, zawsze) + PRAWDZIWE AI przez API
(klucz właściciela). Warunek Dzidka: **przed budową ZWIAD WZORCÓW mocnych projektów**
(wyniki → `boty/WZORCE-BOTOW.md` + brief buildera). Prawdziwa skrzynka (IMAP) dalej
po egzaminie. Z klepniętych szkiców powstaje treść sprzedażowa stron produktów (K5).

---

## BOT 1 — ChatBot LIVE (wtyczka `chatbot-live`)

**Co kupuje klient (jedno zdanie):** bot na stronę firmową, który 24/7 odpowiada na
powtarzalne pytania klientów, a gdy nie zna odpowiedzi — zbiera kontakt, żeby
właściciel odezwał się na żywo.

**FRONT (co widzi gość strony):**
- pływający bąbelek czatu w rogu (włącz/wyłącz w ustawieniach; nie rusza motywu),
- okno czatu: powitanie (konfigurowalne) → gość pisze pytanie → bot odpowiada
  z bazy pytań-odpowiedzi (v1: PROSTE dopasowanie podłańcuchów po polu „słowa
  kluczowe z wariantami" — odmiany łapiesz wpisując warianty: „cena, ceny, cennik";
  automatyczny stemming polskich odmian = droga rozbudowy PO egzaminie — recenzja
  PR #27: bez cichego poszerzania wyceny §14),
- brak dopasowania → uczciwe „nie znam odpowiedzi — zostaw maila i pytanie,
  odezwiemy się" → zapis zgłoszenia + (opcja) powiadomienie mailem do właściciela.
  **To jest człon „LIVE": most do żywego człowieka, nie udawanie wszechwiedzy.**
- STAŁY przycisk „zostaw wiadomość" widoczny od początku rozmowy (wzorzec
  Crisp/standard dziedziny: ścieżka do człowieka zawsze, nie tylko po porażce bota).

**ADMIN (wp-admin):**
- CRUD bazy Q&A: pytanie wzorcowe, słowa kluczowe (z wariantami), odpowiedź,
- lista zgłoszeń z czatu (kto pytał, o co, mail — do obdzwonienia/odpisania),
- HISTORIA rozmów (zapis wymian bot↔gość, widok w adminie, retencja 90 dni jak
  w sklepie — wzorzec „historia w jednym miejscu" z Chatwoot/standardu),
- ustawienia: włącznik, teksty powitania/awaryjny, mail powiadomień,
- seed demo PL: ~10 par Q&A typowej firmy usługowej (cennik, godziny, kontakt,
  oferta, dojazd...) — bot DZIAŁA od pierwszej minuty po aktywacji.

**„Ile czasu oszczędza" (pokrycie w treści, nie zmyłka):** odpowiedzi na powtarzalne
pytania bez udziału człowieka + leady zbierane poza godzinami; instrukcja pokazuje,
jak wpisać własne Q&A w kilkanaście minut (czas MIERZONY przez nie-autora we
wrogiej sesji 23.08 — obietnica z pokryciem, nie z sufitu).

**TRYB AI (hybryda — Dzidek 18.08):** w ustawieniach pole „klucz API" (klucz
WŁAŚCICIELA — nigdy w paczce/kodzie/repo). Z kluczem odpowiedzi generuje prawdziwy
model z instrukcją systemową: „jesteś asystentem firmy X; oto informacje o firmie
[tekst z ustawień]; odpowiadasz TYLKO na ich podstawie, po polsku, krótko; nie
wiesz → poproś o maila". PRÓG PEWNOŚCI (wzorzec Tidio/standard dziedziny): model
ma ZAKAZ zgadywania — niepewny odmawia i zbiera kontakt, zamiast konfabulować
o firmie. Bez klucza — pełnoprawny tryb regułowy (nie atrapa).
**STOP-LOSS:** dzienny limit wywołań + licznik w adminie; błąd/timeout API →
automatyczny zjazd do trybu regułowego. Builder przed integracją czyta skill
`claude-api` (model/parametry z docs, nie z pamięci).
**GRANICE na egzamin (jawne):** bez pamięci rozmów między wizytami (sesja
przeglądarki — **to zostaje prawdą**; pamięć W TRAKCIE jednej rozmowy w trybie AI
powstała, patrz rozdział „Pamięć bieżącej rozmowy w trybie AI"),
⛔ **nieaktualne od 2026-08-21:** „bez czatu operatora na żywo w czasie rzeczywistym
(kontakt = zgłoszenie + mail — w OBU trybach)" — **przejęcie rozmowy przez człowieka
POWSTAŁO i jest w produkcie** (podstrona „Rozmowy na żywo", przycisk **Przejmij**,
rozdział „Przejęcie rozmowy przez człowieka" w dokumentacji technicznej ChatBota);
bramka `bezpieczenstwo-agenta` przed oddaniem
(bot czyta niezaufany tekst gości; nie ma narzędzi ani danych poza bazą Q&A —
ryzyko niskie, ale sprawdzone, nie założone).
**Uczciwie (to zdanie idzie TEŻ na stronę produktu w K5):**
⛔ **nieaktualne od 2026-08-21.** Brzmiało: „to bot regułowy z bazy Q&A + most do maila
właściciela — NIE żywa rozmowa z operatorem ani prawdziwe AI; »LIVE« znaczy: żywy
człowiek ODPISZE na zebrany kontakt". Dziś **oba** zaprzeczenia są nieprawdziwe:
tryb AI z prawdziwym modelem działa (z kluczem właściciela), a rozmowę można przejąć
z panelu. Prawdziwe dziś: **bez klucza API** bot jest regułowy i wtedy „LIVE" znaczy
tyle, że człowiek odpisze na zebrany kontakt; **z kluczem** odpowiada model;
**po przejęciu** pisze człowiek, a bot milczy.
⚠️ Zdanie na stronie produktu (`TRESC-STRON-PRODUKTOW.md`) pochodzi stąd i wymaga
osobnej poprawki po stronie koordynatorki — ten plik jej nie zastępuje.

---

## BOT 2 — Segregator maila (wtyczka `segregator-maila`)

**Co kupuje klient (jedno zdanie):** automat, który porządkuje skrzynkę firmową —
rozdziela maile do kategorii wg reguł i pokazuje raport, ile roboty zrobił za Ciebie.

**RDZEŃ (co robi w wersji testowej):**
- reguły segregacji (CRUD): nadawca zawiera / temat zawiera / słowa kluczowe
  w treści → kategoria; JEDEN mechanizm rozstrzygania: kolejność na liście
  (pierwsza pasująca wygrywa; osobnego pola „priorytet" NIE MA — recenzja PR #27:
  dwa mechanizmy naraz = niejednoznaczność),
- kategorie (CRUD): np. Oferty, Faktury, Reklamacje, Spam-podejrzane, Reszta,
- wbudowana PRÓBKA ~30 maili demo PL + **import własnej próbki z CSV**
  (kolumny: od, temat, treść) — klient może wrzucić eksport ze SWOJEJ skrzynki,
- przebieg: „przepuść próbkę" → tabela wyników (mail → kategoria → która reguła)
  + RAPORT: ile do której kategorii, ile niedopasowanych, % pokrycia; eksport CSV.
  Przy próbce z kolumną `kategoria_oczekiwana`: ACCURACY (% trafień) + lista
  pomyłek (standard raportów klasyfikacji — egzaminator widzi LICZBĘ, nie „działa").

**ADMIN:** reguły, kategorie, przebiegi (historia raportów), import próbki.

**„Ile czasu oszczędza":** raport z próbki pokazuje NA LICZBACH, jaki % skrzynki
automat rozdzieli sam; instrukcja: jak ułożyć reguły pod własną firmę w ~20 minut
(czas mierzony jak w ChatBocie — wroga sesja 23.08).

**TRYB AI (hybryda — Dzidek 18.08):** z kluczem właściciela klasyfikację robi
model („przypisz mail do JEDNEJ z kategorii [lista]; niepewny → Reszta") — tryb
przebiegu do wyboru: reguły / AI / **porównaj oba** (raport pokazuje różnice —
mocny argument sprzedażowy: widać, co AI łapie ponad reguły). Ten sam stop-loss
i zjazd do reguł co w ChatBocie.
**GRANICE — ZAKTUALIZOWANE 2026-08-20** (poprzednia wersja tego akapitu mówiła
„bez podpięcia prawdziwej skrzynki… testowy bot" i jest już NIEPRAWDZIWA: podpięcie
skrzynki powstało i działa — pobieranie, przenoszenie do folderów na serwerze
poczty, tryb na sucho jako domyślny, cofnięcie ostatniego przebiegu, harmonogram).
Prawdziwe granice, jakie zostały:
- **logowanie wyłącznie loginem i hasłem aplikacji** (`LOGIN` wg RFC 3501); brak
  `AUTHENTICATE`/OAuth2 ⇒ **Microsoft 365 i Outlook.com nie zadziałają** — musi to
  stać na stronie produktu PRZED zakupem, nie w komunikacie błędu po zakupie;
- **na skrzynce klasyfikują REGUŁY**, tryb AI działa na próbce (koszt wywołań przy
  całej poczcie byłby niekontrolowany);
- **bez czytania załączników i struktury MIME** — decyzja z nadawcy, tematu
  i początku treści.

---

## WSPÓLNE ZASADY OBU WTYCZEK (z planu v3)

- własne prefiksy tabel/opcji (`chatl_`, `segm_`), tabele przez dbDelta + wersja
  schematu; czysty uninstall (domyślnie dane zostają, opcja pełnego czyszczenia),
- teksty PL wprost (D21), zero zewnętrznych zależności/CDN, WP 7.0 / PHP 8.4,
- bezpieczeństwo standardami WP: nonce+capability na każdej mutacji, prepare(),
  sanitize wejść / escape wyjść (czat i import CSV = wejście NIEZAUFANE — XSS!),
- assety ładowane tylko tam, gdzie bot działa; bump wersji przy każdej zmianie JS/CSS,
- każda wtyczka: instrukcja użytkownika PL (instalacja → konfiguracja w kwadrans →
  FAQ → droga rozbudowy) — **zip wtyczki + instrukcja = paczka produktu w sklepie**,
- JAWNA deklaracja prywatności (wzorzec SaneBox; RODO): tryb regułowy — treść NIE
  opuszcza WordPressa; tryb AI — treść pytania/maila idzie do API dostawcy.
  Informacja w instrukcji, na stronie produktu i jako punkt do polityki
  prywatności właściciela.
- wroga sesja botów 23.08 (czysty WP: aktywacja bez błędów/notice'ów, seed działa,
  uninstall czysty) — kryterium GOTOWE #10; W TYM pomiar czasu konfiguracji przez
  nie-autora (pokrycie obietnic czasowych z instrukcji i stron produktów).

## CO SPRAWDZI RECENZJA DZIDKA (pytania do Ciebie)

1. ~~Czy taki zakres pasuje?~~ ✅ CZĘŚCIOWO ROZSTRZYGNIĘTE 18.08: Dzidek wybrał
   HYBRYDĘ (prawdziwe AI + tryb regułowy bez klucza) z warunkiem zwiadu wzorców.
2. ChatBot: czy „LIVE = zbieranie kontaktu dla właściciela" czyta się dobrze,
   czy wolisz inny akcent (np. mocniejsze parcie na zostawienie numeru telefonu)?
3. Segregator: czy import CSV wystarczy jako „testowość", czy dodać drugą próbkę
   demo z innej branży?
4. Nazwy produktów w sklepie zostają dosłownie z notatki: „ChatBot LIVE",
   „Segregator maila"?
