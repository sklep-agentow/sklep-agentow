# Rozpoznanie przed pozycją A: przejęcie rozmowy przez człowieka

Data: **2026-08-20**. Autor: builder 1. Moduł: `wtyczki/chatbot-live`.

Ten dokument powstał **przed** napisaniem choćby linijki kodu funkcji i jest
warunkiem wejścia do zadania. Dwa pytania: **jak robi to topka branży** (bo nie ma
powodu wymyślać na nowo rzeczy, którą rynek zdążył przetestować na milionach rozmów)
i **czym odpytywać w WordPressie** (bo zły wybór zabije serwer kupującego, a nie nasz).

Wszystkie liczby o obciążeniu są **zmierzone na naszym stosie**, nie oszacowane —
metoda i surowe wyniki są niżej, żeby dało się je podważyć.

---

## 1. Jak to działa u topki branży

### 1.1. Chatwoot — stan rozmowy jest jawny i to on decyduje, kto odpowiada

Chatwoot rozwiązuje przejęcie **statusem rozmowy**, nie osobnym przełącznikiem.
Cytaty ze słownika dokumentacji:

| Status | Definicja (dosłownie) |
|---|---|
| `pending` | „A conversation status that indicates the conversation is **not yet ready for an agent to pick up** — typically used when an **AI assistant or bot is still handling the customer**." |
| `open` | „A conversation status implying that a conversation is **ready to be picked up by an agent**." |
| `resolved` | „A conversation status applied when a conversation is ready to conclude." |
| `snoozed` | „…when you need to wait a certain period or need a reply from the customer to continue." |

Przepływ przejęcia (dokumentacja „How to use Agent bots"):

- rozmowy z botem powstają jako `pending` — „allowing it to triage the conversation
  before passing it on to a human agent";
- bot oddaje ją człowiekowi zmieniając status: „it can use the conversation update API
  to change the status to `open` which would make the conversation available to a human";
- **powrót do bota to ta sama droga w drugą stronę**: „Agents can return a handed-off
  conversation to the bot queue by **changing the status back to `pending`**".

**Co z tego bierzemy:** jedno pole stanu na rozmowie, a nie dwa niezależne przełączniki
(„bot wyłączony" + „operator przypisany"), które zawsze kiedyś się rozjadą. Stan jest
źródłem prawdy dla obu stron: bot pyta o niego przed odpowiedzią, panel pokazuje go
w liście rozmów.

### 1.2. Crisp — ostrzeżenie, czego NIE robić

Dokumentacja Crispa mówi wprost o zachowaniu, które w naszym zadaniu byłoby błędem:

> „messages sent by operator during a Wait block **will not interrupt the workflow**,
> meaning that it will execute the next block even is an agent intervenes"

Czyli u nich wejście operatora **nie ucisza** automatu — scenariusz jedzie dalej i gość
dostaje wiadomości od bota **równolegle** z człowiekiem. Dla nas to jest dokładnie ta
sytuacja, której zabrania wymaganie „po przejęciu bot milczy", i dlatego test ma to
sprawdzać **w obie strony** (milczy po przejęciu, odzywa się po zakończeniu), a nie
tylko raz przy szczęśliwej ścieżce.

### 1.3. Tidio — przejęcie jest konfigurowalne i zależy od tego, czy ktoś jest na dyżurze

Z dokumentacji Tidio: przekazanie rozmowy do człowieka włącza się w zakładce
**Conversation handoff**, osobno dla dwóch sytuacji — gdy bot **nie zna odpowiedzi**
i gdy gość **sam poprosi o człowieka** — a zachowanie ustawia się **osobno dla godzin
online i offline**. Po przekazaniu rozmowa trafia do folderu **Unassigned** i może ją
podnieść dowolny operator.

**Co z tego bierzemy:** rozróżnienie „gość prosi o człowieka" od „operator sam wchodzi"
oraz jawną obsługę sytuacji, gdy **nikogo nie ma**. Wersja 1 robi jedną rzecz porządnie:
operator wchodzi sam z panelu. Prośba gościa o człowieka i godziny dyżuru to osobna
pozycja — nazwana wprost jako granica, nie przemilczana.

### 1.4. Czy gość wie, że rozmawia z człowiekiem

Chatwoot: „Your display name and avatar from Profile Settings appear on **outgoing
replies**", a w widgecie przy nieprzeczytanej rozmowie pokazywana jest **nazwa
przypisanego agenta** zamiast etykiety ogólnej. Czyli tożsamość drugiej strony jest
w interfejsie gościa, a nie tylko w panelu.

**Nasza decyzja idzie dalej niż rynek i jest świadoma.** U nas gość dostaje
**jednoznaczny komunikat systemowy w oknie rozmowy** („Do rozmowy dołączył człowiek…"),
a nie samą zmianę awatara, którą łatwo przeoczyć — i drugi komunikat przy powrocie do
bota. Uczciwość wobec gościa jest u nas zasadą produktu, a nie opcją do odhaczenia:
gość ma wiedzieć, komu pisze, **zanim** napisze coś, czego nie powiedziałby maszynie.

---

## 2. Mechanika w WordPressie: Heartbeat API czy własny punkt REST

WordPress nie ma gniazd (WebSocket), więc obie drogi to **odpytywanie**. Różnią się
częstotliwością, kosztem i tym, ile z nich kontrolujemy.

### 2.1. Co mówi oficjalna dokumentacja

**Heartbeat API** (Plugin Handbook + referencja hooków):

- „the client-side heartbeat code sets up an interval (called the 'tick') to run
  **every 15-120 seconds**" — a filtr `heartbeat_settings` przyjmuje `interval`
  z komentarzem w przykładzie z dokumentacji: `//Anything between 15-120`;
- żądanie idzie przez **`admin-ajax.php`** (nie przez REST);
- dla niezalogowanych istnieje `heartbeat_nopriv_received`
  (`apply_filters( 'heartbeat_nopriv_received', $response, $data, $screen_id )`,
  od WP 3.6, wywoływany z `wp_ajax_nopriv_heartbeat()`), więc **da się** go użyć
  na froncie dla gościa;
- po stronie JS: zdarzenia `heartbeat-send` i `heartbeat-tick`.

**Własny punkt REST** (REST API Handbook):

- rejestracja przez `register_rest_route()` na haku `rest_api_init`;
- **`permission_callback` jest obowiązkowy**: „As of WordPress 5.5, if a
  `permission_callback` is not provided, the REST API will issue a `_doing_it_wrong`
  notice" — dla punktu publicznego dopuszczalne `__return_true`, ale wtedy **całą**
  kontrolę dostępu bierzemy na siebie;
- parametry deklaruje się w `args` z `required`, `validate_callback` i
  `sanitize_callback` — walidacja odrzuca, sanityzacja czyści.

### 2.2. Ile to kosztuje — pomiar, nie szacunek

Metoda: do stosu demo wgrana **atrapa pomiarowa** (mu-plugin) z najtańszym możliwym
punktem REST (`return array( 'ok' => true )`) i najtańszą możliwą odpowiedzią
heartbeat. 3 żądania rozgrzewkowe, potem **30 pomiarów** `curl` z wnętrza kontenera
(bez sieci zewnętrznej, bez proxy). Atrapa **usunięta ze stosu po pomiarze**;
nie wchodzi do wtyczki ani do paczki.

| Co mierzone | Średnia | Mediana | Min | Max |
|---|---|---|---|---|
| własny punkt REST (`GET /wp-json/…`) | **31 ms** | 25 ms | 19 ms | 81 ms |
| `admin-ajax.php` (heartbeat, nopriv) | **19 ms** | 18 ms | 13 ms | 29 ms |
| plik statyczny (tło pomiaru) | 1 ms | 1 ms | 0 ms | 2 ms |

**Wynik niewygodny dla mojej rekomendacji, więc podaję go pierwszy:** REST jest
u nas **droższy** od `admin-ajax.php` (~25 ms vs ~18 ms mediany), bo dokłada
inicjalizację serwera REST. Kto twierdzi, że „REST jest lżejszy", nie zmierzył tego.

### 2.3. Co to robi z serwerem przy 100 gościach naraz

Przyjmuję **medianę 25 ms** dla REST i **18 ms** dla heartbeat. Kolumna „zajęte
procesy PHP" to `zapytania/s × koszt`, czyli ile **równoległych** procesów PHP zjada
samo odpytywanie (1,00 = jeden proces zajęty bez przerwy, nic innego się nie policzy).

| Interwał | Zapytań/min | Zapytań/s | Zajęte procesy PHP | Co to znaczy na współdzielonym hostingu |
|---|---|---|---|---|
| 1 s | **6 000** | 100 | **2,50** | pakiet z 1–2 procesami PHP **przewrócony**: kolejka, timeouty, 503 |
| 3 s | 2 000 | 33,3 | 0,83 | ~cały jeden proces zjedzony przez sam czat |
| 5 s | 1 200 | 20 | 0,50 | połowa procesu — sklep obok zaczyna zwalniać |
| 10 s | 600 | 10 | 0,25 | do przyjęcia, ale to już 600 zapytań/min „o nic" |
| 15 s (minimum Heartbeat) | 400 | 6,7 | 0,12 | tanio — ale patrz niżej, co to robi z rozmową |
| 60 s | 100 | 1,7 | 0,03 | tanio i bezużytecznie dla czatu na żywo |

Typowy pakiet współdzielony daje **1–4 procesy PHP** na całą witrynę — to samo
z czego korzysta koszyk, wyszukiwarka i panel właściciela. Odpytywanie co sekundę
przy 100 gościach potrzebuje **2,5 procesu wyłącznie dla siebie**, czyli więcej,
niż klient w ogóle ma. To nie jest „wolniej" — to jest **strona nie działa**.

### 2.4. Decyzja: własny punkt REST, mimo że jest droższy w pomiarze

| Kryterium | Heartbeat API | Własny REST | Kto wygrywa |
|---|---|---|---|
| koszt jednego żądania | 18 ms | 25 ms | Heartbeat |
| najkrótszy interwał | **15 s** (twarde minimum dokumentacji) | dowolny, nasz | **REST** |
| kontrola interwału | globalna dla całej strony, dzielona z innymi wtyczkami | wyłącznie nasza | **REST** |
| własny ładunek innych wtyczek doklejany do odpowiedzi | tak (wspólny `$response`) | nie | **REST** |
| przewidywalność u hostingów | `admin-ajax.php` bywa objęte regułami zapory i limitami | `/wp-json/` też, ale osobno i jawnie | remis |

**Rozstrzyga interwał.** Przy Heartbeat wiadomość operatora czekałaby w kolejce
**do 15 sekund**, zanim gość ją zobaczy — a to jest cała obietnica słowa „LIVE"
w nazwie produktu. 7 ms różnicy na żądaniu nie kupi nam tych piętnastu sekund.

**Z Heartbeatu bierzemy natomiast pomysły**, bo są dobre i sprawdzone: zwalnianie,
gdy karta traci uwagę użytkownika, i różny rytm zależnie od tego, czy coś się dzieje.

### 2.5. Rytm odpytywania, który proponuję

| Sytuacja gościa | Interwał | Uzasadnienie |
|---|---|---|
| widget **zamknięty** | **0 zapytań** | nie ma rozmowy — nie ma po co pytać |
| widget otwarty, rozmawia **bot** | 15 s | jedyny powód pytania: operator może wejść sam |
| rozmowę przejął **człowiek**, karta widoczna | **5 s** | tu liczy się każda sekunda: to jest ta rozmowa na żywo |
| przejęta, ale karta **schowana** | 30 s | gość i tak nie patrzy (Page Visibility API, bez bibliotek) |
| przejęta, ale **cisza > 2 min** | 15 s | rozmowa przygasła, nie ma po co dowozić 12 zapytań/min |

Koszt przy realistycznym obciążeniu: 100 otwartych widgetów w trybie bota (15 s)
to 6,7 zapytania/s ≈ **0,17 procesu PHP**; do tego 5 rozmów faktycznie przejętych
(5 s) to 1 zapytanie/s ≈ **0,03 procesu**. Razem **poniżej 0,2 procesu PHP** —
mieści się w pakiecie, którego 1-sekundowe odpytywanie by nie udźwignęło.

Dodatkowo odpowiedź ma być **tania, nie tylko rzadka**: gość wysyła `od_id`
(identyfikator ostatniej znanej mu wiadomości), a punkt zwraca wyłącznie to, co
nowsze — przy braku nowości leci najkrótsza możliwa odpowiedź, bez treści rozmowy.

---

## 3. Znalezisko bezpieczeństwa, które zmienia zakres

Punkt odpytywania jest **publiczny** (gość nie jest zalogowany), więc **jedynym**
kluczem do cudzej rozmowy jest identyfikator sesji. Sprawdziłem, jak powstaje dzisiaj
(`assets/widget.js`):

```js
id = (window.crypto && window.crypto.randomUUID)
    ? window.crypto.randomUUID()
    : 'chatl-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
```

Ścieżka główna jest w porządku (UUID v4 z generatora kryptograficznego). **Ścieżka
zapasowa nie jest**: `Date.now()` jest publicznie znany co do sekundy, a `Math.random()`
**nie jest** generatorem kryptograficznym i nie wolno na nim opierać niczego, co pełni
rolę hasła. `crypto.randomUUID()` wymaga **bezpiecznego kontekstu** (HTTPS), więc na
witrynie serwowanej po HTTP fallback nie jest wyjątkiem — jest **regułą**.

Dopóki sesja służyła tylko do sklejania własnej rozmowy, koszt zgadnięcia był niski.
Od chwili, gdy publiczny punkt na jej podstawie **wydaje treść rozmowy z operatorem**,
zgadnięty identyfikator to **podsłuch cudzej rozmowy**.

**Wniosek do zakresu pozycji A:** identyfikator sesji przestaje być wymyślany
w przeglądarce — wydaje go **serwer** (`random_bytes()` / `wp_generate_password()`),
a walidacja przy każdym żądaniu zostaje. To wchodzi do tego PR, bo bez tego
wymaganie „identyfikator nie do zgadnięcia" jest niespełnione.

---

## 4. Jak wymagania twarde mapują się na projekt

| Wymaganie | Rozwiązanie |
|---|---|
| publiczny punkt nie pokaże cudzej rozmowy | zapytanie **zawsze** zawężone `WHERE sesja = %s`; brak `LIKE`; identyfikator wydany serwerowo |
| identyfikator sprawdzany przy każdym żądaniu | `chatl_waliduj_sesje()` w `permission_callback`/na wejściu, przed jakimkolwiek odczytem |
| operator tylko z `manage_options` | wszystkie punkty operatorskie za `current_user_can( 'manage_options' )` + nonce |
| gość widzi, że rozmawia z człowiekiem | komunikat systemowy w oknie rozmowy przy przejęciu **i** przy oddaniu botowi |
| powrót do bota po ciszy operatora | znacznik czasu ostatniej aktywności operatora + próg; przekroczenie = powrót z komunikatem |
| zero usług zewnętrznych, bibliotek i CDN | czysty `fetch` i `setTimeout`, zero zależności — tak jak reszta widgetu |

Stan rozmowy trzymamy **jednym polem** (za Chatwootem), a nie zestawem flag.

---

## 5. Czego NIE sprawdziłem

- **Nie zmierzyłem zachowania pod równoległym obciążeniem** — pomiary są sekwencyjne
  (jedno żądanie naraz). Liczby „zajęte procesy PHP" to iloczyn kosztu i częstotliwości,
  a nie wynik testu obciążeniowego; przy nasyceniu koszt jednego żądania **rośnie**,
  więc tabela jest optymistyczna, nie pesymistyczna.
- **Nie sprawdziłem hostingów kupujących** — liczba procesów PHP (1–4) to typowa oferta
  współdzielona, nie pomiar u konkretnego klienta.
- **Dokumentacji Tidio nie udało się pobrać bezpośrednio** (serwer odrzucił pobranie,
  HTTP 403) — korzystałem z opisów ich artykułów pomocy w wynikach wyszukiwania.
  Chatwoot i Crisp czytałem wprost z ich stron dokumentacji.
- **Nie weryfikowałem, czy Crisp zmienił zachowanie bloku `Wait`** po dacie publikacji
  tamtego artykułu.
- **Nie badałem WebSocketów przez usługę zewnętrzną** — odpada z wymagania „zero usług
  zewnętrznych", więc porównanie byłoby teatrem.

---

## 6. Źródła

- Heartbeat API — Plugin Handbook: <https://developer.wordpress.org/plugins/javascript/heartbeat-api/>
- `heartbeat_settings` (interwał 15–120 s): <https://developer.wordpress.org/reference/hooks/heartbeat_settings/>
- `heartbeat_nopriv_received` (front dla niezalogowanych): <https://developer.wordpress.org/reference/hooks/heartbeat_nopriv_received/>
- REST API Handbook, własne punkty i `permission_callback`: <https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/>
- Chatwoot, „How to use Agent bots" (pending/open, powrót do bota): <https://www.chatwoot.com/hc/user-guide/articles/1677497472-how-to-use-agent-bots>
- Chatwoot, słownik statusów rozmowy: <https://www.chatwoot.com/hc/user-guide/articles/1677141565-chatwoot-glossary>
- Chatwoot, personalizacja widgetu (nazwa i awatar agenta u gościa): <https://www.chatwoot.com/features/widget-customization>
- Crisp, „Understanding and Mastering the Crisp Workflows" (operator nie przerywa scenariusza): <https://help.crisp.chat/en/article/understanding-and-mastering-the-crisp-ai-chatbot-1boypme/>
- Tidio, „How do flows, Lyro, and live agents work together" oraz „Lyro — the conversational AI agent" (zakładka Conversation handoff, folder Unassigned): <https://help.tidio.com/hc/en-us/articles/14667264947356-How-do-flows-Lyro-and-live-agents-work-together>
