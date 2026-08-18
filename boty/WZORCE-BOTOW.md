# WZORCE BOTÓW — zwiad mocnych projektów (warunek Dzidka, 18.08)

Zwiad: subagent, 12 wyszukiwań, wyłącznie nazwy ZWERYFIKOWANE w wynikach (zasada:
model nie zmyśla narzędzi). Cel: budować jak najlepsi, nie z głowy.

## A. ChatBot — u kogo podpatrzone
- **Chatwoot** (25 000+ ★): konfigurowalny widget (kolor/tekst/język), AI-agent
  automatyzuje odpowiedzi, auto-przypisanie zgłoszenia do człowieka.
- **Tidio** (80 000+ instalacji WP): AI „Lyro" z JAWNYM PROGIEM PEWNOŚCI — >70%
  odpowiada, poniżej PRZEKAZUJE do człowieka; pre-chat/offline form zbiera kontakt.
- **Crisp**: stały przycisk „Request Human" + webhook/powiadomienie przy żądaniu.
- **Botpress** (14,8k ★): baza wiedzy (dokumenty/URL) jako źródło odpowiedzi LLM —
  nie wolny prompt.
- **Typebot** (open source): przepływ rozmowy z polami (email/telefon/przyciski).
- Standard katalogu WP (AI Engine 100k+ instalacji): AI trenowane na treści strony
  + eskalacja do live chat.

**STANDARD dziedziny (co ma każdy dobry widget):** widget w rogu z brandingiem ·
zbieranie kontaktu (pre-chat lub przy braku odpowiedzi) · JAWNY próg pewności AI —
poniżej przekazanie, nie zgadywanie · WIDOCZNY stały przycisk „napisz do człowieka"
(nie tylko po porażce bota) · baza wiedzy/FAQ jako źródło AI · powiadomienie
właściciela o zgłoszeniu · historia rozmów w panelu.

## B. Segregator — u kogo podpatrzone
- **Gmail Filters** (standard rynkowy): warunek→akcja (nadawca/temat/słowa →
  etykieta/priorytet); kolejność reguł = pierwsza pasująca wygrywa. ✅ nasz szkic zgodny.
- **Exchange mail flow rules**: wzorzec TRYBU TESTOWEGO — reguła loguje „bym
  zadziałała", zanim ruszy naprawdę. (U nas cały produkt = tryb testowy na próbkach.)
- **SaneBox**: JAWNA deklaracja prywatności („czytamy tylko metadane") jako
  wyróżnik zaufania — wzorzec komunikacji, nie techniki.
- **n8n + LLM (gotowe szablony)**: AI łapie NIUANS ponad słowa kluczowe („kiedy
  będziesz mógł" = priorytet) — dokładnie nasz tryb „porównaj reguły vs AI".
- **Konwencja raportów klasyfikacji (ML)**: tabela trafień per kategoria +
  accuracy; przy próbce z etykietami referencyjnymi — gdzie się myli.

**STANDARD dziedziny:** warunek→akcja · kolejność pierwsza-wygrywa · test na
próbce PRZED żywą skrzynką · AI uzupełnia reguły, nie zastępuje · raport z
LICZBAMI (trafienia per kategoria, % pokrycia, accuracy przy etykietach) ·
jawna deklaracja, co robimy z treścią maila.

## CZEGO UNIKAĆ (anty-wzorce z obserwacji)
1. Chatbot bez widocznej ścieżki do człowieka — wszyscy liderzy ją mają.
2. Reguły odpalane od razu na żywej skrzynce bez trybu testowego.
3. Klasyfikacja „zrobione" bez metryki (bez % trafień i miejsc pomyłek).
4. Niejasność, co dzieje się z treścią maila/rozmowy (RODO) — deklarować wprost.

## CO Z TEGO WCHODZI DO NASZYCH SZKICÓW (delta 18.08)
1. ChatBot: STAŁY przycisk „zostaw wiadomość" (nie tylko po nieudanej odpowiedzi).
2. ChatBot: zapis HISTORII rozmów + widok w adminie (retencja 90 dni jak sklep).
3. ChatBot tryb AI: próg pewności w instrukcji systemowej (niepewny → zbierz
   kontakt, nie zgaduj) — zgodnie z Tidio/standardem.
4. Segregator: przy próbce CSV z kolumną `kategoria_oczekiwana` raport pokazuje
   ACCURACY (% trafień) i listę pomyłek — egzaminator widzi liczbę, nie „działa".
5. Oba: JAWNA deklaracja prywatności w instrukcji i na stronie produktu — tryb
   regułowy: treść nie opuszcza WP; tryb AI: treść pytania/maila idzie do API
   dostawcy (RODO — właściciel musi to wiedzieć i zapisać w polityce).

## NIE POTWIERDZONE (białe plamy zwiadu)
Zrzuty ekranu liderów (tylko opisy) · wtyczka WP segregująca SUROWE maile
(najbliższe: helpdeski od ticketów) · parametry progu pewności Chatwoot.
