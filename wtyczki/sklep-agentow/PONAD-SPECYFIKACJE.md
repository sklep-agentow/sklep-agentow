# PONAD SPECYFIKACJĘ — Sklep Agentów (jawna lista)

Zamówienie mówiło: sklep z dwoma agentami na WordPressie, po zakupie mail z potwierdzeniem
i instrukcją, opisy sprzedażowe produktów, panel z widokiem „kiedy, za ile, w jakim czasie kupili”,
baza kupujących i płatności, zapis produktów do bazy z dodawaniem, usuwaniem i edycją, dziennik
logowań na konto administratora, całość w trybie testowym. Poniżej **wszystko, co sklep robi ponad
literę tego zamówienia — z powodem**. Nic z tej listy nie wymaga od Ciebie dodatkowej konfiguracji;
to jest w paczce, bo bez tego sklep nie nadawałby się do sprzedaży, a nie dlatego, że tak było wygodnie.

## Płatność i domknięcie zakupu

1. **Stripe Checkout** — dane karty podaje się na stronie Stripe, nie na Twoim serwerze. Powód:
   przyjmowanie numerów kart we własnym formularzu wymagałoby certyfikacji, której sklep WordPress
   nie ma; to standard sklepów tej wielkości.
2. **Domknięcie zakupu dwiema niezależnymi drogami** — powiadomienie od Stripe (webhook z podpisem)
   oraz zadanie co 10 minut, które dopytuje Stripe o opłacone sesje; do tego przycisk „Dociągnij
   zaległe teraz” na Pulpicie. Powód: webhook bywa zablokowany przez hosting albo zły sekret —
   bez drugiej drogi zapłacony zakup wisiałby bez maila.
3. **Każde przejście stanu zamówienia jest jednorazowe** (blokada w bazie) — kupujący dostaje dokładnie
   jeden mail, nawet gdy Stripe wyśle powiadomienie dwa razy.
4. **Zwroty i spory (chargeback) z powiadomień Stripe** trafiają do zamówienia jako statusy „zwrócone”
   (pełny zwrot) i „obciążenie zwrotne”, z kwotą, i są odejmowane od statystyk sprzedaży; zwrot częściowy
   nie zmienia statusu, tylko kwotę. Spór wygrany albo wycofany przywraca poprzedni status; przegrany
   zostaje jako obciążenie zwrotne. Powód: bez tego Pulpit liczyłby jako sprzedaż pieniądze, których nie ma.

## Wydawanie pliku

5. **Brama pobrań** — plik wydaje wyłącznie sklep, po sprawdzeniu linku z maila: token, termin
   72 godzin, limit 10 pobrań na każdy wydany link, licznik zwiększany atomowo. Pliki produktów leżą w katalogu chronionym
   o losowej nazwie, nigdy pod publicznym adresem. Powód: plik pod jawnym adresem to produkt za darmo.
6. **Ponowna wysyłka ze świeżym linkiem** — z panelu albo automatycznie (do trzech prób po nieudanej
   wysyłce), z zapisem w osi czasu zamówienia; każdy świeży link ma licznik pobrań od zera; nieudana
   wysyłka z panelu przywraca poprzedni link do następnej próby automatycznej; dla zamówień zwróconych
   i spornych przycisk jest niedostępny. Powód: „nie dostałem maila” to najczęstszy kontakt po zakupie.
7. **Sprzedaż tylko z potwierdzonym plikiem** — sklep sprawdza ZIP po zapisie (obecność, rozmiar,
   suma kontrolna) i nie pozwala włączyć produktu bez pliku. Powód: sprzedanie czegoś, czego nie ma
   czym wydać, kończy się zwrotem i utratą zaufania.
8. **Kopia danych produktu w zamówieniu** (nazwa, cena, plik z chwili zakupu). Powód: zmiana ceny
   albo pliku nie może przepisywać historii sprzedaży ani odbierać kupionej wersji. Skutek uboczny,
   świadomie zostawiony: po podmianie pliku produktu stary plik zostaje w katalogu chronionym (nie
   wycieka — katalog jest zamknięty), bo sklep nie odróżnia jeszcze pliku trzymanego przez stare
   zamówienia od wolnego; sprzątanie to osobna zmiana na życzenie.

## Mail po zakupie

9. **Potwierdzenie zgody na dostarczenie treści cyfrowej** — dosłowna treść i czas zgody zaznaczonej
   przed zapłatą, zapisane przy zamówieniu i powtórzone w mailu. Powód: przy treściach cyfrowych
   prawo konsumenckie wymaga takiego potwierdzenia, żeby wygasło prawo do odstąpienia od umowy
   po pobraniu.
10. **W mailu instrukcja instalacji w skrócie, informacja o gwarancji (gdy produkt ją ma) i jednorazowy
    link do opinii.** Powód: kupujący ma dostać wszystko, czego potrzebuje, w jednym miejscu.

## Opinie

11. **Opinie kupujących z jednorazowego linku** (ważnego 30 dni), jedna na zamówienie, publikowane
    dopiero po ręcznym przyjęciu w panelu; bez ocen anonimowych gości i bez zbiorczej średniej.
    Powód: opinie podnoszą sprzedaż, ale tylko wiarygodne — od osób, które naprawdę kupiły.

## Dane osobowe i porządek po sobie

12. **Anonimizacja kupującego na żądanie** z panelu (adres znika z zamówienia i dziennika zdarzeń;
    z opinii znika imię, a sama opinia schodzi ze strony produktu na stałe; link do pobrania
    unieważniony; kwoty i czasy zostają). Sklep potwierdza usunięcie
    dopiero po sprawdzeniu, że zapis się udał. Powód: obowiązek z RODO — a potwierdzenie bez
    sprawdzenia to niezrealizowane żądanie.
13. **Szkice Regulaminu i Polityki prywatności** tworzone przy instalacji. Powód: sklep bez tych
    stron nie może legalnie sprzedawać; szkic jest punktem startu, nie gotową treścią prawną.
14. **Wybór, co zostaje po odinstalowaniu** — domyślnie historia sprzedaży zostaje, pełne czyszczenie
    do włączenia w Ustawieniach; klucze Stripe znikają zawsze. Powód: historia zamówień to dokument
    księgowy, a klucze to sekret.

## Panel i bezpieczeństwo administratora

15. **Dziennik zdarzeń** (obok dziennika logowań): płatności, wysyłki, pobrania, zwroty, spory,
    anonimizacje, eksporty — z osi czasu każdego zamówienia. Powód: „co się stało z tym zamówieniem”
    musi mieć odpowiedź bez grzebania w bazie.
16. **Spowalnianie nieudanych logowań** (wydłużany czas odpowiedzi do minuty; od siódmej próby w ciągu
    minuty z jednego adresu — odmowa na minutę, bez blokowania konta na stałe)
    i **usuwanie wpisów obu dzienników — logowań i zdarzeń (osi czasu zamówień) — starszych niż 90 dni**. Powód: zgadywanie hasła ma być
    nieopłacalne, a dziennik nie może rosnąć bez końca.
17. **Pulpit z porównaniem do poprzedniego okresu, rozkładem zakupów w czasie, filtrami po statusie
    i datach, szukaniem po e-mailu i eksportem CSV** z aktualnych filtrów. Powód: tak wyglądają
    panele dobrych sklepów; „kiedy, za ile, w jakim czasie” bez porównania i eksportu nie odpowiada
    na pytania, które właściciel zadaje naprawdę.
18. **Ostrzeżenie w kokpicie**, gdy struktura bezpośrednich odnośników jest ustawiona na „Zwykłe”.
    Powód: bez tego strona sklepu pokazuje bloga, a Stripe nie dociera do webhooka — i nikt nie wie,
    dlaczego.

## Strony sklepu

19. **SEO stron sklepu** — tytuły i opisy, znaczniki Open Graph, dane strukturalne (produkt, okruszki,
    pytania i odpowiedzi), mapa witryny produktów. Powód: strona sprzedażowa, której nie widać
    w wyszukiwarce, nie sprzedaje.
20. **Strony produktów z prezentacją sprzedażową** (sekcje, galeria, pytania i odpowiedzi, opinie),
    tworzone przez sklep — nie trzeba ich budować w WordPressie. Powód: zamówienie prosiło o opisy
    „na zachętę”; standard sklepów cyfrowych to pełna strona produktu, nie akapit.

## Czego świadomie NIE ma

Kont kupujących, kuponów i przecen, płatności cyklicznych, integracji z motywem i innymi wtyczkami,
wysyłania danych gdziekolwiek poza Stripe. Prawdziwych płatności — całość pracuje w trybie testowym
Stripe, a przejście na produkcję opisuje `INSTRUKCJA-INSTALACJI.md` (rozdział „Test a produkcja”).
