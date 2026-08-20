# DOWODY DEKLARACJI — boty (20.08.2026, noc)

## 🔴 ZNALEZISKO KRYTYCZNE: tryb AI NIE DZIAŁA w żadnym z botów

**Kiedy:** 20.08.2026 ~02:05, pierwszy raz w historii projektu odpalono tryb AI na ŻYWYM kluczu.
**Dlaczego dopiero teraz:** nie mieliśmy klucza Anthropic — tryb AI był obietnicą bez ani jednego
wywołania na żywo. Dokładnie ta dziura, którą wskazały krytyki (4 modele + 3 naszych krytyków).

**Objaw:** bot odpowiada z bazy reguł także wtedy, gdy klucz jest wpisany i limit niewyczerpany.
Wygląda, jakby działał — bo zjazd do reguł maskuje awarię.

**Przyczyna (plik:linia):**
- `wtyczki/chatbot-live/includes/ai.php:133` — `'output_config' => array( 'effort' => 'low' )`
- `wtyczki/segregator-maila/includes/ai.php:118` — to samo (KLASA, nie egzemplarz)

Model `claude-haiku-4-5-20251001` nie obsługuje tego pola. API odrzuca całe żądanie.

**Surowy dowód — wywołanie funkcji produktu na stacku:**
```
HTTP: 400
BODY: {"type":"error","error":{"type":"invalid_request_error",
       "message":"This model does not support the effort parameter."},
       "request_id":"req_011CeD2J63eEddDzfg5MELid"}
```

**PRÓBA KONTROLNA (to samo zapytanie, jedyna różnica = sporne pole):**
```
$ curl ... -d '{"model":"claude-haiku-4-5-20251001","max_tokens":50,...}'          → HTTP:200
$ curl ... -d '{... ,"output_config":{"effort":"low"}, ...}'                        → HTTP:400
```
Klucz działa (osobny test: HTTP 200, odpowiedź modelu „Tak."), kontener ma dostęp do api.anthropic.com
(HTTP 401 na puste żądanie = połączenie jest, brak autoryzacji).

**Co to znaczy dla produktu:** klient wpisuje własny klucz, płaci za wywołania, a bot cicho
odpowiada z reguł. Obietnica „tryb AI" ze strony produktu i instrukcji jest MARTWA.
Klasa błędu: cicha awaria maskowana mechanizmem awaryjnym (nasza przyczyna-matka H).

**Status:** przekazane builderowi jako zadanie Z0 (przed wszystkimi innymi).
