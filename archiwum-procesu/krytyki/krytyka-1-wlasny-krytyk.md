# KRYTYKA 1 — własny zimny krytyk (subagent, 2026-08-17 ~01:10)

Zapis WIERNY (bez skrótów). Prompt: otwarty (PROMPT-KRYTYKA.md), bez checklisty.
Kontekst dany krytykowi: solo-warsztat + AI, zlecenie egzaminacyjne, tryb testowy, blizna „43 funkcje bez sprawdzania".

---

Plan jest pisany dyscyplinie (kość→przyrosty, jawny rejestr założeń) — ale to formalna forma, nie gwarancja treści. Poniżej realne dziury.

## KRYTYCZNE

**1. Sedno produktu (wpięcie w stronę hydrowaną Reactem) testowane dopiero w kroku 5/7.**
Cała wartość oferty to „wpinany i odpinany jak wtyczka" plugin na 4 różne strony. Mechanizm wpięcia (link w menu vs dołączany skrypt manipulujący DOM) jest oznaczony jako niepewny (Z3) i rozstrzygany dopiero w przedostatnim kroku planu pracy. Skrypt dopisujący element do DOM strony hydrowanej Reactem ryzykuje hydration mismatch albo nadpisanie przy re-renderze — to nie jest szczegół, to fundament architektury. Jeśli w kroku 5 okaże się, że żaden z „2 wariantów" nie działa niezawodnie, trzeba przeprojektować mechanizm integracji tuż przed audytem końcowym i oddaniem — bez bufora czasowego. **Powinno być spike'em przed krokiem 1, nie testem w kroku 5.**

**2. Zgoda na utratę prawa odstąpienia (checkbox z sek. 6) nie ma miejsca w przepływie zakupu.**
Sekcja 3 (przepływ) idzie prosto: klik „Kup" → `POST /api/zamow` → sesja Stripe → przekierowanie. Nigdzie nie ma kroku „pokaż i zbierz zgodę". Sekcja 2A (wykaz stron: lista/produkt/dziękujemy/panel) też jej nie mieści. To wymóg prawny, który sam dokument sobie narzuca w sek. 6, a architektura go nie realizuje — egzaminator techniczny kupujący produkt zauważy to od razu, bo nic nie musi zaznaczyć.

**3. Rubryka „GOTOWE" (sek. 9) nie testuje NICZEGO z checklisty bezpieczeństwa/prawa (sek. 6).**
Sekcja 6 wymienia: cena zawsze z bazy, RODO-checkbox, regulamin, Omnibus. Sekcja 9 — formalna rubryka egzaminu — nie ma ani jednego punktu weryfikującego te rzeczy (np. „próba nadpisania ceny w żądaniu POST zostaje zignorowana"). Egzaminator, który „będzie próbował zepsuć", pierwsze co spróbuje to manipulację ceną w requeście — plan sam to przewiduje jako zagrożenie, ale nie umieszcza w kryteriach akceptacji. Konsekwencja praktyczna: przy realizacji wg sek. 7 (przyrosty budowane pod kryteria z sek. 9) bezpieczeństwo łatwo zostanie pominięte, bo nic go formalnie nie wymusza.

## WAŻNE

**4. Sprzeczność: Z5 obiecuje test w „kości", krok 1 wyklucza z kości panel.**
Z5 (sek. 8): sposób sesji panelu (token w nagłówku) ma być „potwierdzony wygodowo w kości". Krok 1 planu pracy (sek. 7) explicite: „Bez panelu". Panel pojawia się dopiero w kroku 3. Więc Z5 fizycznie nie może zostać zweryfikowane tam, gdzie plan mówi że zostanie.

**5. Token sesji panelu „w nagłówku, nie cookie" (Z5) → najpewniej localStorage, zero wzmianki o XSS/CSP.**
Panel admina to najbardziej uprzywilejowany punkt systemu (CRUD produktów, upload do R2, dane klientów). Sekcja 6 wymienia hasła/hash/limit prób/dziennik, ale ani słowa o ochronie tokenu przed kradzieżą przez XSS. Jeśli token leży w localStorage, jeden wstrzyknięty skrypt w panelu = pełne przejęcie konta admina.

**6. Sprzeczność: „usuwanie danych kupującego z panelu" (RODO, sek. 6) vs model danych (sek. 4) vs dashboard finansowy (sek. 1/5).**
Model D1 nie ma żadnego pola/mechanizmu wspierającego usuwanie ani anonimizację. A skoro panel ma pokazywać „kiedy/za ile kupili" jako historię sprzedaży, twarde usunięcie rekordu zamówienia psuje tę historię. Plan nie rozstrzyga: pseudonimizacja email czy DELETE całego wiersza. To się rozjedzie w budowie, bo dwa wymogi z tego samego dokumentu ciągną w różne strony.

**7. Omnibus (przekreślone ceny) wymieniony w checklist prawnym, ale nigdzie indziej w planie nie ma promocji/przecen jako funkcji, a tabela `produkty` (sek. 4) nie ma historii cen.**
Albo ten punkt checklisty jest martwy (bo nie ma promocji), albo brakuje mu realizacji w modelu danych. Obecnie to punkt prawny bez adresata.

**8. Brak automatycznego retry przy statusie `mail_blad`.**
Klient zapłacił, mail nie wyszedł (błąd API dostawcy) — jedyna naprawa to ręczne kliknięcie admina w panelu, gdy go zauważy. Zero cron/retry. Realny scenariusz: klient zapłacił i czeka bez produktu do czasu, aż ktoś zajrzy do dashboardu.

**9. Konto Stripe dla 4 różnych właścicieli stron — nieopisane.**
Sek. 2B mówi wprost: „każdy właściciel strony = własne darmowe konto Cloudflare (izolacja danych i pieniędzy)". O Stripe — cisza. Jeśli konto Stripe jest jedno wspólne, to pieniądze z 4 różnych sklepów płyną na jedno konto — to nie jest już „izolacja pieniędzy", to jest wprost sprzeczne z deklarowaną zasadą izolacji.

**10. Status zamówienia (`oplacone`/`mail_wyslany`/`mail_blad`) bez opisanej maszyny stanów.**
Co się dzieje ze statusem po kliknięciu „wyślij ponownie" z poziomu `mail_blad`? Wraca do `mail_wyslany`? Zostaje? Może dojść do podwójnego niejasnego stanu przy audycie.

**11. Kryterium GOTOWE #1 (≤2 min do maila) zależy od SLA zewnętrznego dostawcy (Brevo) bez marginesu.**
Darmowy plan dostawcy maili bywa throttlowany. To robi z twardego kryterium akceptacji test, który może się posypać losowo przy powtórzeniu przez egzaminatora — bez winy kodu.

**12. Limity Cloudflare opisane tylko jako pojemność (GB, żądania/dzień) — pominięte osobne limity operacji.**
D1 i R2 na darmowym planie mają zwykle osobne pułapy na liczbę operacji odczytu/zapisu, nie tylko rozmiar bazy/magazynu. Dokument tego nie adresuje — do zweryfikowania, nie jest to pewnik z samego planu.

## DROBNE

**13.** Wspólny adres nadawcy Brevo dla 4 sklepów różnych właścicieli — mail wygląda niespójnie z marką sklepu, ryzyko trafienia do spamu.
**14.** „Strona błędu z instrukcją kontaktu" przy wygasłym linku — kanał kontaktu nigdzie niezdefiniowany.
**15.** Limit prób logowania na IP bez podanego czasu/mechanizmu odblokowania — ryzyko self-lockout admina.
**16.** Brak strony polityki prywatności/regulaminu w wykazie stron (sek. 2A), mimo że sek. 6 je wymaga jako obowiązkowe.
**17.** Brak specyfikacji limitu rozmiaru pliku produktu i sposobu uploadu do R2 (przez Worker czy presigned URL) — Workers mają pułap rozmiaru requestu.

## Czego NIE da się ocenić z samego dokumentu

- Ram czasowych całego projektu i per-krok — nie wiadomo, czy 7-etapowy plan mieści się w czasie egzaminu ani czy jest bufor na naprawy po „wrogiej sesji" (krok 2).
- Czy „wroga sesja" i audyt końcowy (kroki 2 i 6) są zautomatyzowane, czy to test eksploracyjny ręczny — wpływa na powtarzalność.
- Realnego wyglądu/UX stron produktowych — „wg wzorców rynku" bez makiety.
- Treści instrukcji wdrożenia dla właściciela strony-gospodarza — krok 7 ją obiecuje, ale plan nie pokazuje nawet szkicu, więc nie da się ocenić wiarygodności „≤30 min".
- CSP strony-gospodarza — CORS jest omówiony, ale restrykcyjny CSP na stronie docelowej może samodzielnie zablokować całe podpięcie fetch/skryptu do Workera; plan tego nie rusza.
- Dokładnej rubryki egzaminatora poza przytoczonym cytatem — nie wiadomo, czy punktacja premiuje coś, czego ten plan nie adresuje wcale.
