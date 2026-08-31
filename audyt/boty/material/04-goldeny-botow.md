# 04 — GOLDENY (złote zasady z NASZYCH blizn — każda kosztowała nas realnie)

> Te dziewięć zdań to nie teoria. Każde pochodzi z błędu, który przeszedł przez
> nasze kontrole i wrócił od klienta albo od egzaminatora. Czytaj je jako listę
> miejsc, w których TA konkretna ekipa już raz się przewróciła.

## G1 — Mechanizm awaryjny potrafi ukrywać, że funkcja nie działa NIGDY
Oba boty wysyłały do API pole, którego model nie obsługuje. Każde wywołanie wracało
błędem, bot cicho zjeżdżał do reguł i **udawał, że działa**. Klient wpisałby swój
klucz, płacił za zapytania i nigdy nie dostał odpowiedzi od modelu.
**Siedmiu krytyków tego nie znalazło. Znalazło jedno prawdziwe wywołanie.**
→ Pytaj: co ten kod robi, gdy krok pośredni ZAWIEDZIE? I czy da się to odróżnić od sukcesu?

## G2 — Zapis do bazy bez sprawdzenia wyniku = fałszywy sukces
`$wpdb->insert()` bez sprawdzenia zwracanej wartości, a użytkownik i tak widzi
„Dziękujemy". W ChatBocie było to w 1 miejscu, w Segregatorze w 6 (i znalazło się 7.).
→ Trafienie w JEDNYM miejscu = natychmiast grep po całym repo. To zawsze klasa.

## G3 — Uninstall zostawiał KLUCZ API klienta w bazie
Wczesny `return` przed usunięciem opcji. Egzaminator wytknął tę klasę w poprzednim
projekcie i sprawdzi ją znowu.
→ Mierz DIFF bazy przed instalacją i po odinstalowaniu, obiema ścieżkami (domyślną i pełną).

## G4 — Dokumentacja kłamiąca względem kodu to najczęstsza uwaga egzaminatora
W poprzednim projekcie ok. 30 pozycji. Wytknięte DWA razy w tym samym miejscu:
„w readme dalej jest to samo, nie poprawiłeś tego".
→ Każdą LICZBĘ i obietnicę z instrukcji sprawdź w kodzie. Komendy z instrukcji **wpisz w terminal**, nie przeczytaj.

## G5 — „Nie ma X" trzeba sprawdzić własnoręcznie
Grep zwracający zero wygląda identycznie, gdy czegoś nie ma i gdy źle szukałeś.
Polskie odmiany („właściciel" vs „właściciela") potrafią rozłożyć wyszukiwanie.
→ Zero trafień = **obowiązkowa próba kontrolna** (poszukaj czegoś, co NA PEWNO tam jest).

## G6 — Twoje narzędzie pomiarowe też bywa zepsute
W jednym audycie 7 z 12 znalezisk okazało się wadą testu, nie produktu. Zrzut
całostronicowy nie odpalał animacji i skaził materiał dwóm sędziom.
→ Zanim oskarżysz kod: sprawdź, czy Twój pomiar mierzy to, co myślisz.

## G7 — Licznik i limit muszą mierzyć wymiar, który robi szkodę
Wspólny kubeł dla pytań i zgłoszeń, a dzienny limit AI globalny — jeden gość
wypalał budżet wszystkich.
→ Pytaj: kto poniesie koszt, gdy jedna osoba będzie działać w złej wierze?

## G8 — Kod robiący WIĘCEJ niż zamówienie musi to jawnie deklarować
Egzaminator traktuje niezadeklarowane rozszerzenie jako wadę, nie bonus.
→ Wszystko ponad literę zamówienia idzie do `PONAD-SPECYFIKACJE-BOTY.md`.

## G9 — Wiedza nie zatrzymuje wydania. Zatrzymuje TEST
W poprzednim projekcie ten sam błąd dotarł do nas trzy razy — listą, pomiarem
i obserwacją na żywo — i wydanie i tak poszło, bo wszystko było doradcze.
→ Każde potwierdzone znalezisko kończy się **maszyną z kodem wyjścia 1**, nie wpisem „pamiętaj".
