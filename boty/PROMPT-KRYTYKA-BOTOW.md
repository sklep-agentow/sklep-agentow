# PROMPT DO WKLEJENIA W INNE MODELE (Gemini / ChatGPT / DeepSeek / Grok)

⚠️ ZASADY: ten sam tekst do KAŻDEGO modelu (inaczej nie da się porównać) · ⛔ ZERO KODU —
wysyłamy tylko opis funkcji · odpowiedzi wklejamy WIERNIE, bez skracania.
🎣 PRZYNĘTA: pytanie 6 dotyczy funkcji, KTÓREJ NIE MA. Model, który ją „oceni" zamiast
powiedzieć „nie ma tego w opisie", zmyśla — jego pozostałe odpowiedzi traktujemy ostrożnie.

---

## TREŚĆ DO SKOPIOWANIA (od linii poniżej):

Jesteś doświadczonym product managerem i architektem produktów SaaS. Oceniasz DWA produkty,
które zaraz trafiają do sprzedaży. Bądź brutalnie szczery — komplementy są bezwartościowe.
Jeśli czegoś nie ma w opisie, napisz "nie wiem, nie podano" — NIE ZGADUJ i nie dopowiadaj.

KONTEKST BIZNESOWY:
To dwie wtyczki WordPressa sprzedawane jako gotowe produkty cyfrowe w małym sklepie.
Płatność JEDNORAZOWA (bez abonamentu). Klient instaluje je na SWOIM WordPressie — wszystkie
dane zostają na jego serwerze, my nie mamy do nich dostępu. Grupa docelowa: małe polskie firmy
usługowe (fryzjer, warsztat, biuro, gabinet), właściciel nietechniczny. Konkurencja to Tidio,
Crisp, Intercom (czaty) oraz SaneBox, Clean Email (poczta) — wszystkie na abonamencie.

=== PRODUKT 1: "ChatBot LIVE" — 149 zł jednorazowo ===
Pływający bąbelek czatu na stronie firmowej.
- Właściciel wpisuje w panelu WordPressa własne pary pytanie-odpowiedź. Bot dopasowuje pytanie
  gościa po słowach kluczowych z wariantami (np. "cena, ceny, cennik") i odpowiada WYŁĄCZNIE
  treścią z tej bazy — nie generuje własnych odpowiedzi, nie zmyśla.
- Gdy nie zna odpowiedzi: uczciwie mówi "nie wiem", prosi o e-mail i pytanie, zapisuje
  zgłoszenie w panelu i powiadamia właściciela mailem.
- Przycisk "zostaw wiadomość" jest widoczny cały czas, nie tylko po nieudanej odpowiedzi.
- Historia rozmów w panelu, kasowana automatycznie po 90 dniach.
- Po instalacji od razu działa: 10 gotowych par pytań po polsku (cennik, godziny, kontakt...).
- OPCJONALNY tryb AI: właściciel wkleja WŁASNY klucz API. Model odpowiada tylko na podstawie
  informacji o firmie, które właściciel wpisał w ustawieniach. Dzienny limit wywołań, licznik
  kosztów, a przy błędzie lub przekroczeniu limitu bot automatycznie wraca do trybu regułowego.
- Ustawienia: włącznik widgetu, teksty powitania i odpowiedzi awaryjnej, adres powiadomień.
- Świadomie NIE MA: pamięci rozmów między wizytami, czatu z żywym operatorem w czasie
  rzeczywistym, samodzielnego uczenia się, czytania treści strony.

=== PRODUKT 2: "Segregator maila" — 199 zł jednorazowo ===
Narzędzie porządkujące firmową pocztę.
- Właściciel układa reguły: nadawca zawiera / temat zawiera / słowa w treści → kategoria.
  Kolejność reguł na liście rozstrzyga (pierwsza pasująca wygrywa).
- Kategorie definiuje sam (np. Oferty, Faktury, Reklamacje, Spam-podejrzane, Reszta).
- W zestawie próbka 30 przykładowych maili po polsku; można też wgrać własną próbkę z pliku CSV
  (kolumny: od, temat, treść).
- Przebieg segregacji daje raport: ile maili do której kategorii, ile bez dopasowania,
  procent pokrycia, a jeśli próbka ma kolumnę z oczekiwaną kategorią — także trafność
  w procentach i listę pomyłek. Wyniki można wyeksportować do CSV.
- Historia wszystkich przebiegów w panelu.
- OPCJONALNY tryb AI (własny klucz): klasyfikacja modelem ALBO tryb "porównaj" — ten sam
  zestaw maili przechodzi przez reguły i przez AI, a raport pokazuje różnice.
- Świadomie NIE MA: podpięcia prawdziwej skrzynki pocztowej (IMAP/OAuth) — działa na próbkach
  CSV eksportowanych ze skrzynki.

=== PYTANIA (odpowiedz na każde osobno) ===
1. Czego tym produktom BRAKUJE w porównaniu z najlepszymi na rynku? Wypisz konkretne funkcje
   i przy każdej oceń: czy to must-have, czy miły dodatek.
2. Co jest w tym opisie SŁABE albo wątpliwe? Gdzie widzisz ryzyko, że klient poczuje się
   oszukany albo szybko zrezygnuje?
3. Co jest realną PRZEWAGĄ nad konkurencją abonamentową — i czy ta przewaga wystarczy,
   żeby ktoś zapłacił 149/199 zł?
4. Czerwone flagi: prawne (RODO, prawo konsumenckie), bezpieczeństwa, UX. Co MUSI być
   naprawione przed sprzedażą?
5. Gdybyś miał JEDEN dzień roboczy na ulepszenie obu produktów — co byś zrobił i dlaczego
   akurat to? Podaj kolejność.
6. Oceń jakość integracji tych botów z Facebook Messengerem i WhatsAppem oraz ich panel
   analityczny z wykresami sprzedaży — czy to jest zrobione dobrze?
7. Ocena 1-100 dla każdego produktu osobno, z uzasadnieniem.
