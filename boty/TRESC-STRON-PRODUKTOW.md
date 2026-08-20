# TREŚĆ STRON PRODUKTÓW (copy do K5 PR 2) — ze SZKICÓW botów (klepniętych 18.08)

Zasada: każda liczba ma pokrycie w treści paczki (kryterium GOTOWE #10). Liczby
niezmierzone = PLACEHOLDER-POMIAR (wstawiamy po wrogiej sesji 23.08, sweep przed K8).
Ceny NIGDY w treści statycznej — zawsze z bazy (spójność z CRUD).

---

## CHATBOT LIVE

**HERO** — mono: `[ PRODUKT · 01 ]` · H1: „ChatBot LIVE" ·
zdanie: „Odpowiada klientom 24/7. A gdy nie zna odpowiedzi — bierze kontakt
i oddaje rozmowę Tobie." · cena z bazy · CTA „Kup teraz (płatność testowa)" ·
pasek-signature: `● [ BOT · ONLINE ] ODPOWIADA 24/7 — PRZETESTUJ: BĄBELEK W ROGU`

**PROBLEM** — „Te same pytania. Codziennie." / „Cennik? Godziny? Dojazd? Każde
z nich to przerwana robota — albo klient, który pisał po godzinach i nie doczekał
się odpowiedzi."

**CO DOSTAJESZ** (kafle; obok mockup okna czatu z dymkami):
- Baza pytań i odpowiedzi — wpisujesz własne, z wariantami słów („cena, ceny, cennik").
- Zbieranie kontaktów: bot nie zna odpowiedzi → prosi o maila i zapisuje zgłoszenie;
  stały przycisk „zostaw wiadomość" od początku rozmowy.
- Historia rozmów w wp-admin (retencja 90 dni) + lista zgłoszeń do odpisania.
- 10 gotowych par pytań po polsku — bot działa od pierwszej minuty po instalacji.
- Tryb AI (opcjonalny): TWÓJ klucz API, odpowiedzi tylko z informacji o Twojej
  firmie, dzienny limit kosztów i automatyczny powrót do reguł przy błędzie.

**JAK DZIAŁA — 3 KROKI** (numeracja = realna sekwencja):
1. Zainstaluj wtyczkę i włącz bąbelek.
2. Wpisz swoje pytania i odpowiedzi (PLACEHOLDER-POMIAR: „w kilkanaście minut" —
   liczba po pomiarze 23.08).
3. Bot odpowiada, Ty dostajesz kontakty do klientów.

**KARTY-DOWODY** (liczba + podpis skąd):
- „24/7" — bot nie ma godzin pracy; odpowiada też wtedy, gdy Ty śpisz.
- „10 par Q&A w zestawie startowym" — policzalne w paczce, po polsku.
- „100% rozmów w Twoim WordPressie" — tryb regułowy: treść nie opuszcza serwera.

**DLA KOGO** — usługi lokalne (fryzjer, warsztat, gabinet) · sklepy i firmy
z powtarzalnymi pytaniami · każdy, kto traci klientów piszących po godzinach.

**„TESTOWY BOT — CO TO ZNACZY"** (sekcja uczciwości; idzie też do instrukcji):
Pełnoprawny silnik regułowy działa od razu — bez kluczy i opłat. „LIVE" znaczy:
żywy człowiek (Ty) odpisuje na zebrane kontakty — bot nie udaje wszechwiedzy.
Tryb AI włączasz WŁASNYM kluczem; przy limicie dziennym lub błędzie bot sam
wraca do reguł. Prywatność: reguły = zero wysyłki na zewnątrz; AI = pytanie
gościa idzie do API dostawcy (informacja także w polityce prywatności).

**FAQ**: Czy potrzebuję klucza AI? (Nie — bot w pełni działa bez niego.) ·
Co gdy bot nie zna odpowiedzi? (Prosi o maila, zapisuje zgłoszenie, może
powiadomić Cię mailem.) · Czy bot sam się uczy? (Nie — odpowiada wyłącznie
z Twojej bazy; to zaleta: zero konfabulacji o Twojej firmie.) · Jak wstawię
bota na stronę? (Instalujesz wtyczkę — bąbelek pojawia się sam; włącznik w
ustawieniach.) · Co z RODO? (Zgłoszenia gości zostają w Twojej bazie; w trybie
AI treść pytań idzie do dostawcy API — wzmianka do Twojej polityki prywatności.)

**FINALNY CTA** — „Zacznij odpowiadać w nocy. Bez nocek." + przycisk.

---

## SEGREGATOR MAILA

**HERO** — mono: `[ PRODUKT · 02 ]` · H1: „Segregator maila" ·
zdanie: „Rozdziela pocztę do kategorii według Twoich reguł — na próbce albo
na Twojej prawdziwej skrzynce — i pokazuje raport, ile roboty zrobił za Ciebie."
· cena z bazy · CTA · pasek-signature:
`● [ SEGREGATOR · GOTOWY ] PRÓBKA 30 MAILI + PODPIĘCIE SKRZYNKI`

**PROBLEM** — „Wszystko na jednej kupie." / „Oferty, faktury, reklamacje i spam
w jednym strumieniu. Zanim odpiszesz ważnym, przekopujesz się przez resztę."

**PRZED / PO** (wizualizacja-przewaga): lewa kolumna — skrzynka-chaos (szare
wiersze); prawa — te same maile w kategoriach z limonkowymi tagami (Oferty,
Faktury, Reklamacje, Reszta); strzałka między kolumnami.

**CO DOSTAJESZ** (kafle):
- Reguły segregacji: nadawca / temat / słowa w treści → kategoria; kolejność
  na liście rozstrzyga (pierwsza pasująca wygrywa — zero niejednoznaczności).
- Kategorie pod Twoją firmę (CRUD, dowolne nazwy).
- Wbudowana próbka 30 maili PL + import WŁASNEJ próbki z CSV (od, temat, treść).
- Raport z przebiegu: ile do której kategorii, ile bez dopasowania, % pokrycia;
  przy próbce z oczekiwaną kategorią — trafność (accuracy) i lista pomyłek.
- Tryb AI (opcjonalny, Twój klucz): klasyfikacja modelem + przebieg
  „porównaj reguły vs AI" z raportem różnic — na próbce.
- **Przebieg na Twojej prawdziwej skrzynce**: podpinasz ją hasłem aplikacji,
  a wtyczka rozkłada pocztę do folderów NA SERWERZE POCZTY. Domyślnie chodzi
  NA SUCHO — pokazuje, co by zrobiła, i **nie rusza ani jednej wiadomości**,
  dopóki sam nie przełączysz trybu i nie potwierdzisz. Ostatni przebieg da się
  **cofnąć jednym kliknięciem**, a całość może chodzić sama według harmonogramu.
- **Eksport reguł do filtrów Gmaila i Outlooka**: te same reguły możesz przenieść
  do swojej skrzynki i zostawić je tam na stałe, niezależnie od wtyczki.

**JAK DZIAŁA — 3 KROKI**: 1. Zainstaluj. 2. Ułóż reguły pod swoją skrzynkę
(PLACEHOLDER-POMIAR: „~20 minut" — po pomiarze 23.08). 3. Puść przebieg
i czytaj raport.

**KARTY-DOWODY** (liczba + podpis):
- „30 maili demo w zestawie" — przebieg testowy od pierwszej minuty.
- „% pokrycia liczony z TWOJEJ próbki" — raport pokazuje liczby, nie obietnice.
- „0 maili wysłanych na zewnątrz" — tryb regułowy działa w całości w WordPressie.

**DLA KOGO** — biura i sekretariaty · firmy usługowe z jedną wspólną skrzynką ·
każdy, kto zaczyna dzień od przekopywania poczty.

**„CO ROBI NA TWOJEJ SKRZYNCE — I CZEGO NIE ROBI"** (sekcja uczciwości; idzie
też do instrukcji):
Reguły układasz i sprawdzasz na próbce — wbudowanej albo swojej z pliku CSV —
więc widzisz wynik, ZANIM cokolwiek dotknie poczty. Potem podpinasz skrzynkę
i puszczasz ten sam zestaw reguł na prawdziwych mailach.
⛔ **Czego nie ma, powiedziane wprost przed zakupem:**
- **Logowanie tylko loginem i hasłem aplikacji.** Działa z Gmailem i większością
  skrzynek hostingowych. **Microsoft 365 i Outlook.com NIE zadziałają** —
  Microsoft wymaga tam logowania OAuth, którego ta wtyczka nie obsługuje.
  Sprawdź to, zanim kupisz.
- **Na skrzynce pracują REGUŁY, nie AI.** Tryb AI działa na próbce (i w trybie
  „porównaj reguły vs AI"), bo klasyfikowanie modelem całej poczty to koszt,
  którego nikt nie kontroluje z góry.
- **Bez czytania załączników** — decyzja zapada z nadawcy, tematu i początku treści.
Prywatność: reguły = nic nie opuszcza Twojego serwera, także przy przebiegu
na skrzynce; AI = treść idzie do API dostawcy (wzmianka do polityki prywatności).

**FAQ**: Czy muszę podpinać skrzynkę? (Nie musisz — możesz pracować wyłącznie
na próbkach CSV. Ale możesz: wtyczka obsługuje prawdziwą skrzynkę.) ·
Czy zadziała z Microsoft 365 albo Outlook.com? (Nie — tam wymagane jest
logowanie OAuth. Gmail i skrzynki hostingowe z hasłem aplikacji: tak.) ·
Czy coś mi się pomiesza w poczcie? (Domyślny tryb na sucho niczego nie rusza,
a ostatni przebieg da się cofnąć.) ·
Skąd wezmę CSV? (Eksport z klienta poczty; instrukcja pokazuje jak.) ·
Co znaczy accuracy? (Dodajesz do próbki kolumnę z oczekiwaną kategorią —
raport liczy % trafień i pokazuje pomyłki.) · Czy AI jest wymagane? (Nie —
pełny tryb regułowy bez klucza.) · Co z RODO? (Próbki zostają w Twojej bazie;
w trybie AI treść idzie do dostawcy API.)

**FINALNY CTA** — „Niech poczta sama się układa." + przycisk.
