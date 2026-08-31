# NA OSTATNI GUZIK — lista domknięcia obu botów (20.08)

Powód powstania: kryterium „GOTOWE" #10 z `PLAN-PROJEKTU.md:404` pisaliśmy pod
egzamin („rdzenie działają"). Po zwrocie właściciela z 20.08 („boty normalne DO
SPRZEDAŻY, porządne") ten próg jest za niski. Poniżej próg właściwy.
⛔ Każda pozycja BINARNA: albo jest dowód, albo pozycja jest otwarta. Zero „chyba".

## A. KOD — co jeszcze musi wejść
- [ ] Dziewięć otwartych PR-ów scalone (#80–#88 bez #79/#77, już na main).
- [ ] ChatBot: **przejęcie rozmowy przez człowieka** (pozycja A z `boty/KOLEJKA-CHATBOT.md`)
      — bez tego nazwa „LIVE" nie ma pokrycia i to jest jedyna DUŻA rzecz, jaka została.
- [ ] ChatBot: wpięcie w narzędzia RODO WordPressa (eksporter + kasownik),
      import bazy Q&A z pliku, stronicowanie list w panelu.
- [ ] Segregator: reszta jego kolejki + uwaga DROBNA o zapytaniach N razy zamiast raz.
- [ ] Decyzja właściciela: czy tryb AI wchodzi do przebiegu na skrzynce (L8), czy piszemy
      WPROST na stronie produktu, że na skrzynce pracują same reguły.

## B. ZAMROŻENIE — dopiero po A, i dopiero potem audyt
- [ ] Każda scalona zmiana ma podbitą wersję (dziś Segregator dostał funkcję przy
      wersji 1.3.0 — sprawdzić, czy bramka wersji tego nie przepuściła).
- [ ] Wersja zgadza się w czterech miejscach: nagłówek wtyczki = stała w kodzie =
      `Stable tag` w `readme.txt` = wpis w `CHANGELOG.md`.
- [ ] CHANGELOG ma WPIS dla wydawanej wersji (nie tylko zgodny numer — u nas raz
      wyszło wydanie bez wpisu).
- [ ] Paczki przebudowane; **zip == repo** (lista plików + suma kontrolna, nie „na oko").

## C. DOKUMENTY — sweep po starym brzmieniu
- [ ] Instrukcja PL każdej wtyczki kompletna: instalacja → konfiguracja → FAQ →
      droga rozbudowy; każda LICZBA z instrukcji ma pokrycie w kodzie.
- [ ] Strona produktu ma komplet: co robi, jak działa, ile czasu oszczędza, cena,
      dla kogo, FAQ.
- [ ] ⛔ **Sweep granic (L9):** szkice, `PONAD-SPECYFIKACJE-BOTY.md`, treść stron
      produktów i opisy w bazie sklepu mówią „bez czatu operatora, bez pamięci
      rozmów". Po scaleniu pamięci rozmowy i przejęcia — to są zdania NIEPRAWDZIWE.
- [ ] Deklaracja prywatności rozdziela tryb regułowy (nic nie wychodzi) od AI
      (treść idzie do dostawcy) — w instrukcji, na stronie produktu i jako punkt
      do polityki właściciela.

## D. DOWÓD NA ŻYWYM WORDPRESSIE (na PACZCE, nie w repo, przez NIE-autora)
- [ ] Świeża instalacja z `WP_DEBUG`: aktywacja obu wtyczek bez fatali i notice'ów.
- [ ] Tryb regułowy (bez klucza) — test OBOWIĄZKOWY, oba boty.
- [ ] Tryb AI: odpowiedź wyłącznie z informacji o firmie; **odcięcie klucza w trakcie
      = zjazd do reguł + widoczny znacznik błędu w panelu**.
- [ ] ChatBot na stronie z włączonym cache: czat działa po wygaśnięciu nonce'a (L1).
- [ ] Segregator: przebieg na skrzynce w trybie na sucho NIE rusza poczty, a na żywo
      przenosi i daje się cofnąć.
- [ ] Uninstall: diff bazy przed i po = pusty (obiema ścieżkami); klucz API znika ZAWSZE.
- [ ] Retencja faktycznie kasuje (podłóż wpis sprzed 91 dni, odpal, policz przed/po).
- [ ] Pomiar czasu konfiguracji przez nie-autora — liczba z instrukcji ma pokrycie.

## E. AUDYT (dopiero na zamrożonej wersji, z SHA commita w materiale)
- [ ] Kalibracja na mutantach — audytor, który nie znajdzie podłożonego błędu, jest ślepy.
- [ ] Dwa niezależne przebiegi na TEJ SAMEJ wersji, każdy bot osobno.
- [ ] 34 cele z `06-mapa-polowania.md` zamknięte (✅ / 🐞 / ⚠️ — zero pustych).
- [ ] Każdy znaleziony błąd ma STRAŻNIKA: test z exit 1, wpięty do CI.
- [ ] Bramka bezpieczeństwa: wstrzyknięcie polecenia w trybie AI, klucz nie wycieka
      do odpowiedzi/logów/HTML/eksportu, akcje panelu bez uprawnień = odmowa.

## F. WARUNEK STOPU
⛔ NIE „zero błędów" — ✅ „zero powtórek i zero rzeczy poważnych".
Koniec, gdy: zero pozycji otwartych wyżej · mutanty złapane · zero anomalii bez
wyjaśnienia · każdy błąd ma strażnika.

## G. PO BOTACH — recenzent jako NASZ GOTOWIEC (decyzja właściciela 20.08)
- [ ] Briefing recenzenta wyjęty z pliku CI do `.github/RECENZENT-BRIEFING.md`
      (poprawki recenzenta przestają wywracać gałęzie builderów).
- [ ] Recenzent ma zależności i pełną historię — URUCHAMIA bramki, nie czyta ich.
- [ ] Wolno mu sieć, ale **wyłącznie źródła oficjalne**: podręcznik wtyczek WP,
      wytyczne katalogu wtyczek, podręcznik PHP, normy RFC, dokumentacja Anthropic.
      Żadnych blogów i forów; gdy nie zweryfikował — ma napisać, że nie sprawdził.
- [ ] Szablon do warsztatu: recenzent + miejsce na briefing per zlecenie, żeby
      następny projekt startował z tym od pierwszego dnia. Jego siła to nasze
      blizny zapisane w pliku (7 klas błędów kolegi + zasada dowodu z żywego WP).
