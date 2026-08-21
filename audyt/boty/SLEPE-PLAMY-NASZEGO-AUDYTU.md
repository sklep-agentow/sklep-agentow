> ⚠️ **PO CO TEN PLIK LEŻY W MATERIALE AUDYTOWYM:** sekcja o klasach powtarzalnych
> poszła do briefingu recenzenta i będzie łapana przy pull requeście. Tutaj liczy się
> sekcja **ŚLEPYCH PLAM** — to nie są błędy w kodzie, tylko wady NASZEGO SPRAWDZANIA:
> rzeczy, które przeszły przez nasz audyt i zauważył je dopiero klient albo kolejna runda.
> Audytor botów ma je przeczytać PRZED rozpoczęciem i uwzględnić w mapie polowania.
> Trzy wnioski wprost do rundy botów:
> 1. **Klikanie przez człowieka jest OBOWIĄZKOWE** — w poprzednim zleceniu 17 znalezisk
>    złapał wyłącznie człowiek klikający, zero kontroli automatycznych.
> 2. **Czytaj SUROWE zamówienie klienta**, nie naszą przepisaną wersję — błąd z tego
>    powodu przeżył kilka rund audytu.
> 3. **Mapa polowania ma dziury strukturalne** — cała kategoria potrafi nigdy nie trafić
>    na listę. Przed startem sprawdź, czego na mapie NIE MA, a nie tylko odhaczaj to, co jest.

# Destylat: klasy błędów z naszych raportów audytowych

Data: 2026-08-21. Cel: dopisać do briefingu recenzenta w `sklep-agentow` (obok `SCIAGA-EGZAMINATORA.md`,
której 7 klas NIE jest tu powtórzone — patrz tam).

## Źródła przeczytane (i uwaga o ich naturze)
- `/root/praca/zlecenia/mp-s1-klon/audyt/RAPORT-AUDYTU.md` (420 linii) + `UWAGI-DO-RAPORTU.md` (100 linii, cały)
- `/root/praca/zlecenia/mp-b3/audyt/RAPORT-AUDYTU.md` — ⚠️ **to WCZEŚNIEJSZY SNAPSHOT tego samego,
  ewoluującego dokumentu co mp-s1-klon** (diff pokazał tylko dopiski, mp-b3 nie ma nic unikalnego)
- `/root/praca/zlecenia/mp-service-suite/audyt/RAPORT-AUDYTU.md` — ⚠️ **bajt-identyczny z mp-s1-klon**
  (diff = 0 różnic). Te trzy ścieżki to w praktyce JEDNO źródło, nie trzy niezależne.
- `/root/praca/zlecenia/mp-service-suite/AUDYT-RAPORT.md` (739 linii, root katalogu) — STARSZY,
  bardziej surowy dziennik tego samego projektu, z fragmentami tabelarycznymi (np. „Rozliczenie
  dziewięciu pozycji"). Oba dokumenty razem = **protokół chronologicznych rund** ("Runda przy
  X.Y.Z"), NIE jedna tabela z jawną wagą przy każdej pozycji — wagi (duża/średnia/drobna) podane
  słownie w prozie i w niektórych tabelach, nie jako jednolita kolumna.
- `/root/praca/zlecenia/mp-service-suite/dokumentacja-techniczna/JAKOSC-I-AUDYTY.md` (141 linii, cały nagłówkowo)
- `/root/praca/zlecenia/strona-wp/AUDYT-RAPORT.md` (49 linii, przeczytany cały)
- `/root/praca/zlecenia/catsnboard/CHANGELOG.md` (grep) + `git log --format='%ae'` uruchomiony
  bezpośrednio na repo catsnboard (⚠️ katalogu z plikiem audytowym NIE znalazłam — `find -iname
  "*audyt*"` zero trafień; catsnboard jest już wyczerpująco opisany w `SCIAGA-EGZAMINATORA.md`
  z cytatami PR-owymi kolegi, więc to główne źródło dla tego projektu)
- `/root/praca/zlecenia/sklep-agentow/audyt/SCIAGA-EGZAMINATORA.md` — TYLKO jako filtr (żeby nie powtórzyć 7 klas)

---

## 1. KLASY POWTARZALNE (3)

### K1 — Naprawa trafia w JEDEN egzemplarz wzorca, nie we wszystkie jego powtórzenia
Ten sam błąd (nazwa/tekst/ustawienie) siedzi w kilku bliźniaczych miejscach (dwie wtyczki, doc+kod);
naprawiasz jedno, drugie wraca jako "nowe" znalezisko w kolejnej rundzie.
**Jak sprawdzić:** po naprawie ZAWSZE `grep -rn "<naprawiany ciąg>" --include=*.php --include=*.md .`
po całym repo, nie tylko w pliku gdzie go znaleziono.
**Dowód (4 wystąpienia, mp-service-suite):**
- `mp-service-suite/AUDYT-RAPORT.md:193-194` — kontrola nazw ról patrzyła WYŁĄCZNIE na `dla-klienta/*.md`,
  te same błędne cytaty ról zostały w kodzie PHP
- `mp-service-suite/AUDYT-RAPORT.md:182-186` — "Dowod, ze naprawa jezyka z 1.3.8 objela CZESC miejsc, nie wszystkie"
- `mp-s1-klon/audyt/RAPORT-AUDYTU.md:354` — opcja kasowana przy odinstalowaniu w jednej wtyczce, w bliźniaczej nie
- `mp-s1-klon/audyt/RAPORT-AUDYTU.md:413` — jawnie nazwana lekcja procesowa #1: "Naprawa wzorca, nie egzemplarza"

### K2 — Prywatny e-mail właściciela konta wycieka w historii commitów
Merge przez przycisk na GitHubie bierze adres autora z USTAWIEŃ KONTA, nie z lokalnego `git config`
(nawet gdy lokalnie jest ustawiony `users.noreply`) — commit trafia do historii publicznego repo z prywatnym mailem.
**Jak sprawdzić:** `git log --format='%ae' | sort | uniq -c | sort -rn` na repo PRZED oddaniem —
jeśli dominuje adres inny niż `*.noreply.github.com`, jest wyciek.
**Dowód (2 projekty):**
- `mp-service-suite/AUDYT-RAPORT.md:450-458` — 172 ze 177 commitów z prywatnym adresem
- catsnboard: zmierzone teraz bezpośrednio (`git log` w `/root/praca/zlecenia/catsnboard`) —
  **92 z 97 commitów** `szydlowskikonrad95@gmail.com`, tylko 5 `*.noreply.github.com`.
  ⚠️ Ten fakt NIE jest opisany w żadnym pliku audytowym catsnboard — nie znalazłam tam
  dedykowanego raportu (patrz uwaga w źródłach); to świeży pomiar, nie cytat z audytu.

### K3 — Paczka/ZIP zbudowany PRZED ostatnią poprawką dokumentacji
Repo (main) jest już poprawione, ale ZIP wydania powstał wcześniej — kto ściąga paczkę, dostaje
starą, czasem sprzeczną z kodem treść. To INNY mechanizm niż "Latest wskazuje stary tag" (klasa 4
w SCIAGA) — tu treść W ŚRODKU paczki jest zamrożona z chwili budowy, nawet gdy tag/Latest są poprawne.
**Jak sprawdzić:** zanim zbudujesz ZIP do wydania, sprawdź czy jest merge do dokumentów PO ostatnim
buildzie testowym paczki; buduj ZIP jako OSTATNI krok, nigdy przed mergem poprawki dokumentacji.
**Dowód (2 wystąpienia, ten sam projekt, różne wydania):**
- `mp-service-suite/AUDYT-RAPORT.md:426-434` — paczka 1.3.3 (zbudowana 02:41) nie miała poprawki
  dokumentacji scalonej 03:28 (PR #178); kto pobrał ZIP z "Latest", dostał `API-KONTRAKT.md` i
  `DATABASE.md` obiecujące funkcje, których nie ma
- `mp-service-suite/AUDYT-RAPORT.md:69` — przy 1.3.13 "Trzy wcześniejsze paczki unieważnione... bez ostatnich poprawek"

---

## 2. ⭐ ŚLEPE PLAMY (4)

### BS1 — Kontrola potwierdza zgodność z TEKSTEM specyfikacji, nie sprawdza czy da się dojść do końca w interfejsie
Ekran/funkcja "spełnia" wymóg dosłownie (funkcja istnieje w kodzie), ale człowiek klikający trafia
w ślepy zaułek (brak przycisku dalej) albo funkcja jest fizycznie nieosiągalna (brakujący hook rejestrujący).
Żadna z automatycznych/checklistowych kontroli tego nie łapie — łapie to wyłącznie CZŁOWIEK klikający.
**Dowód:**
- `mp-service-suite/AUDYT-RAPORT.md:155-167` — ekran "Wyjątki gwarancyjne" bez wybranego produktu
  był ślepym zaułkiem mimo spełnienia wymogu S-10; cytat wprost: *"Dlaczego nie zlapala tego zadna
  z szesciu kontroli"* i *"Trzeci raz w tym zleceniu przeglad rekami zamawiajacego dal znaleziska,
  ktorych nie dala zadna kontrola automatyczna (13 przy 1.3.8, trzy przy 1.3.10 z kalibracji, to przy 1.3.11)"*
  — czyli **17 znalezisk w tym jednym projekcie złapał WYŁĄCZNIE klikający człowiek**, zero kontroli automatycznych.
- `mp-s1-klon/audyt/UWAGI-DO-RAPORTU.md:14-29` — "odrzucenie wymagające powodu" uznane w
  `ZAKRES-SPRAWDZONY.md:48` za zrobione, a w rzeczywistości niewykonalne w UI: pole powodu nie
  renderuje się nigdy, bo zaczep `mp_rejection_reasons` **nie ma ani jednej rejestracji** w produkcie.

### BS2 — Audyt czyta WŁASNĄ (przepisaną) dokumentację zamiast surowego zamówienia klienta
Błąd "przeżywa" wiele rund audytu, bo każda runda porównuje kod z naszym opracowaniem, a to
opracowanie zostało po drodze "poprawione" tak, by zgadzało się z kodem — nie z oryginałem od klienta.
**Dowód:**
- `mp-s1-klon/audyt/RAPORT-AUDYTU.md:385-393` — numer sprawy miał 5 cyfr zamiast 4 z zamówienia;
  cytat: *"Złapał to dopiero audytor, któremu dano surowy dokument zamówienia zamiast naszego
  opracowania."* Nazwane wprost jako Lekcja procesowa #2 (linia 415).
- Potwierdzenie że to już wciągnięte do procesu: `mp-service-suite/dokumentacja-techniczna/JAKOSC-I-AUDYTY.md:58-60`
  ("Zgodność z zamówieniem — porównanie kodu z oryginalnym dokumentem zamówienia, nie z naszą własną dokumentacją").

### BS3 — Mapa zakresu audytu ma strukturalną dziurę: cała KATEGORIA nigdy nie trafia na listę
Nie chodzi o pominiętą pozycję, tylko o to, że dokument zakresu (`ZAKRES-SPRAWDZONY.md`) w ogóle
nie miał kategorii "widoczność MIĘDZY rolami personelu" — testowano tylko obcy/klient. Właśnie tam
siedziała luka (pracownik widział cudze sprawy), plus nieaktualny komentarz w kodzie 20 linii nad
poprawnym kodem, opisujący STARE (błędne) zachowanie.
**Dowód:** `mp-s1-klon/audyt/UWAGI-DO-RAPORTU.md:51-69` — cytat zakresu: *"Nie ma ani jednej pozycji
o widoczności między rolami personelu"*; nieaktualny komentarz w `CaseRepo.php:1003-1007` cytowany wprost.

### BS4 — Nazwa kryterium/rubryki obiecuje więcej niż faktycznie sprawdza automat
Rubryka mówi "Dokumenty klienta zgodne z kodem (liczby, statusy, **obietnice**)", ale bramka CI
(`liczby-zgodne-z-kodem.sh`) sprawdza WYŁĄCZNIE liczby. Efekt: 4 fałszywe zdania w dokumentach
klienta przeszły przy zielonej bramce, bo dotyczyły obietnic ("zrobi to programista"), nie liczb.
**Dowód:** `mp-s1-klon/audyt/UWAGI-DO-RAPORTU.md:72-93`, cytowane pliki: `INSTRUKCJA-KLIENTA.md:226`,
`mp-s1-klon/instrukcje/KOORDYNATOR.md:52`, `mp-s1-klon/instrukcje/ADMIN.md:75-77`,
`mp-s1-klon/README.md:20`.

---

## 3. ARCHIWUM (jednorazowe, bez rozwijania)
- strona-wp: brak canonical/OG/twitter meta na starcie, naprawione PR #6 — `strona-wp/AUDYT-RAPORT.md:22-24`
- strona-wp: README bez 3-kroków uruchomienia i noty o `.env`, naprawione PR #6 — `strona-wp/AUDYT-RAPORT.md:11-15`
- mp-service-suite: bramka pakująca miała listę 7 dokumentów, w repo było 8 — bramka SAMA to złapała
  (przykład działającej bramki, nie błąd który przeszedł) — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:361-371`
- mp-service-suite: pierwsza kalibracja audytora bezpieczeństwa 1/3, poniżej progu 2/3 — `mp-service-suite/AUDYT-RAPORT.md:242-246`
- mp-service-suite: wbudowana diagnostyka radziła krok, który NIE usuwał problemu — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:83-104`
- mp-service-suite: trzy zachowania rozjeżdżają się przy RÓWNOCZESNYCH żądaniach (limit dobowy, archiwizacja, przypomnienia) — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:83-104`
- mp-service-suite: klasa obsługująca sprawy zbyt duża, 2371-2437 linii / 45 metod — dług architektoniczny, świadomie zostawiony — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:118-147`
- mp-service-suite: moduł automatyzacji sięga wprost do klasy zgłoszeń z pominięciem hooka — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:118-147`
- mp-service-suite: przydział zgłoszeń wg kraju/języka niemożliwy (dane nigdy niezbierane) — świadoma decyzja zakresowa klienta, nie błąd — `mp-s1-klon/audyt/RAPORT-AUDYTU.md:118-147`

## Czego NIE ustaliłam
- Czy klasy K1-K3/BS1-BS4 występują też w `mp-b3` niezależnie od `mp-s1-klon` — NIE, bo to ten sam
  dokument (patrz sekcja źródeł); nie liczę tego jako dwóch osobnych wystąpień.
- Dedykowany plik audytowy dla catsnboard — NIE ZNALAZŁAM (`find -iname "*audyt*"` zero trafień);
  wiedza o catsnboard żyje w PR-ach kolegi, już wyczerpana w `SCIAGA-EGZAMINATORA.md`.
- Nie czytałam `mp-s1-klon/audyt/ZAKRES-SPRAWDZONY.md`, `RUBRYKA-GOTOWE.md`, `KONTROLE-AUTOMATYCZNE.md`,
  `pomiary-a11y-1312/` ani `mp-service-suite/testy/` w całości — poza diety (dwustopniowe czytanie,
  25 kroków); możliwe że kryją dodatkowe klasy, nieprzeszukane.
