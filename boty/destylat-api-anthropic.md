# Destylat: Claude Messages API dla wtyczki WordPress (raw HTTP)

Data: 2026-08-20
Kontekst zlecenia: wtyczka woła API surowym `wp_remote_post`, nagłówki `x-api-key` + `anthropic-version: 2023-06-01`.

Źródła (w kolejności użycia):
1. Skill `claude-api` (wywołany narzędziem Skill), pliki bundlowane w:
   `/tmp/claude-0/bundled-skills/2.1.236/99be5d5f65270beb8308047461210cb0/claude-api/`
   - `shared/prompt-caching.md`
   - `curl/examples.md`
   - `shared/models.md`
2. `/root/praca/warsztat/docs/anthropic/claude-code-prompt-caching.md` (korporacja/uzupełnienie — dot. Claude Code CLI, ale opisuje ten sam mechanizm TTL API)

---

## 1. Prompt caching instrukcji systemowej

**JAK w surowym JSON** — pole systemu musi być TABLICĄ bloków, cache_control wchodzi jako klucz na bloku tekstu (nie top-level string):
```json
"system": [
  {"type": "text", "text": "<duży stały prompt systemowy>", "cache_control": {"type": "ephemeral"}}
]
```
Cytat (`curl/examples.md:172-174`):
> `"system": [ {"type": "text", "text": "<large shared prompt...>", "cache_control": {"type": "ephemeral"}} ]`

TTL 1-godzinny: dopisz pole `ttl` w tym samym obiekcie:
```json
"cache_control": {"type": "ephemeral", "ttl": "1h"}
```
Cytat (`shared/prompt-caching.md:121-122`):
> `"cache_control": {"type": "ephemeral"}              // 5-minute TTL (default)`
> `"cache_control": {"type": "ephemeral", "ttl": "1h"} // 1-hour TTL`

**Nagłówek beta: NIE jest potrzebny.** Przykład cURL (`curl/examples.md:165-176`) używa TYLKO `Content-Type`, `x-api-key`, `anthropic-version: 2023-06-01` — żadnego `anthropic-beta`, także dla wariantu 1h TTL (linia 179: „For 1-hour TTL: …" — bez wzmianki o headerze). Cache_control jest więc funkcją GA, kompatybilną z dokładnie tym zestawem nagłówków, którego już używa wtyczka.

**Minimalna długość promptu, żeby cache zadziałał — ZALEŻY OD MODELU** (poniżej progu marker jest po cichu ignorowany: brak błędu, po prostu `cache_creation_input_tokens: 0`). Tabela (`shared/prompt-caching.md:130-135`):
| Model | Minimum |
|---|---|
| Claude Opus 5, Fable 5, Mythos 5 | 512 tokenów |
| Opus 4.8, Sonnet 5, Sonnet 4.6, Sonnet 4.5, Opus 4.1, Opus 4, Sonnet 4 | 1024 tokenów |
| Opus 4.7, Mythos Preview, Haiku 3.5 | 2048 tokenów |
| Opus 4.6, Opus 4.5, **Haiku 4.5** | **4096 tokenów** |

⚠️ Ważne dla tej wtyczki: jeśli plugin użyje `claude-haiku-4-5`, próg to **4096 tokenów** — krótszy prompt systemowy nie scache'uje się w ogóle (cicho, bez błędu).

**Jak długo żyje cache (TTL):** domyślnie **5 minut** od ostatniego użycia (odświeża się przy każdym trafieniu), opcjonalnie **1 godzina** przez `"ttl": "1h"` w tym samym polu (droższy zapis: patrz ekonomika niżej). Cytat (`shared/prompt-caching.md:141`):
> „Cache writes cost 1.25× for 5-minute TTL, 2× for 1-hour TTL."

Korporacja z `docs/anthropic/claude-code-prompt-caching.md:157`:
> „The time to live (TTL) controls how long a gap the cache survives. The API offers two: a five-minute TTL, and a one-hour TTL…"

Dodatkowo: max **4** breakpointy `cache_control` na request; można je wstawiać na dowolnym bloku (system, definicje narzędzi, blok wiadomości) — `shared/prompt-caching.md:125-126`.

---

## 2. Pola `usage` dotyczące zużycia i cache

Z `shared/prompt-caching.md:149-153` (tabela „API reference — Verifying cache hits"):
| Pole | Znaczenie |
|---|---|
| `input_tokens` | tokeny przetworzone w pełnej cenie (NIEcache'owana reszta promptu) |
| `output_tokens` | (standardowe, poza tą tabelą, ale zawsze obecne w `usage`) |
| `cache_creation_input_tokens` | tokeny zapisane do cache w tym requeście (zapłacone ~1.25×) |
| `cache_read_input_tokens` | tokeny odczytane z cache w tym requeście (zapłacone ~0.1×) |

Cytat dosłowny:
> „`cache_creation_input_tokens` — Tokens written to cache this request (you paid the ~1.25× write premium)"
> „`cache_read_input_tokens` — Tokens served from cache this request (you paid ~0.1×)"
> „**`input_tokens` is the uncached remainder only.** Total prompt size = `input_tokens + cache_creation_input_tokens + cache_read_input_tokens`."

Weryfikacja trafień: `curl/examples.md:179` — „Verify hits via the response `usage.cache_creation_input_tokens` / `usage.cache_read_input_tokens` fields."

Zerowe `cache_read_input_tokens` przy identycznych requestach = cichy „silent invalidator" w prefiksie (`shared/prompt-caching.md:155`).

---

## 3. Identyfikator modelu: alias bez daty vs. datowany + wycofywanie

Tak, istnieje alias bez daty. Tabela „Current Models" (`shared/models.md:56-68`), wiersz Haiku:
> `| Claude Haiku 4.5  | claude-haiku-4-5  | claude-haiku-4-5-20251001   | 200K | 64K | Active |`

Czyli: `claude-haiku-4-5` (alias) i `claude-haiku-4-5-20251001` (pełny/datowany ID) to TEN SAM model — alias wskazuje na ten konkretny snapshot. Zalecenie źródła (`shared/models.md:3`): „Use aliases wherever available" — alias automatycznie przesuwa się na nowszy snapshot przy aktualizacji, datowany ID zawsze wskazuje ten sam zamrożony snapshot.

**Polityka wycofywania** (na podstawie realnych przykładów z tabel w `shared/models.md:83-99`, nie ma osobnego akapitu „polityki" — wzorzec wynika z danych):
- Model przechodzi status: **Active → Deprecated (z datą „Retires: …") → Retired (zniknął z listy dostępnych)**.
- Przykład deprecated z podaną datą (`models.md:86`): „Claude Opus 4.1 | `claude-opus-4-1` | `claude-opus-4-1-20250805` | Deprecated (retires 2026-08-05 — migrate to `claude-opus-5`)".
- Przykład już retired (`models.md:99-106`, tabela „Retired Models (no longer available)"): np. `claude-3-5-sonnet-20241022` — retired Oct 28, 2025; `claude-3-opus-20240229` — retired Jan 5, 2026; `claude-3-7-sonnet-20250219` i `claude-3-5-haiku-20241022` — retired Feb 19, 2026.
- Wniosek: **TAK, datowany identyfikator kiedyś przestaje działać** — po dacie „Retires" model znika z API (przechodzi do tabeli „Retired Models (no longer available)"). Deprecation jest zapowiadana z wyprzedzeniem (konkretna data), retirement następuje po tej dacie.
- Źródło NIE podaje ogólnej reguły „ile miesięcy wyprzedzenia" jako osobnego zdania polityki — to obserwacja z wzorca dat w tabelach, nie cytat z jednego zdania „polityki".

---

## 4. Czy `claude-haiku-4-5-20251001` jest nadal aktualny?

**TAK.** Wiersz w tabeli „Current Models (recommended)" (`shared/models.md:68`), kolumna Status:
> `| Claude Haiku 4.5 | claude-haiku-4-5 | claude-haiku-4-5-20251001 | 200K | 64K | Active |`

Status: **Active**. Nie figuruje ani w tabeli „Deprecated Models (retiring soon)" (`models.md:89-95`), ani w „Retired Models (no longer available)" (`models.md:97-99`). Potwierdzone też w quick-reference tabeli cenowej samego promptu skilla („Claude Haiku 4.5 | `claude-haiku-4-5` | 200K | $1.00 | $5.00").

---

## CZEGO NIE POTWIERDZIŁEM

- **Nie znalazłem w źródłach** ogólnego, jednozdaniowego opisu „polityki wycofywania" (np. „X miesięcy wyprzedzenia po wydaniu następcy") — powyższa odpowiedź w pkt 3 to wzorzec wywnioskowany z konkretnych dat w tabelach, nie cytat z jednego paragrafu polityki. Jeśli taki paragraf istnieje, jest poza plikami które przeszukałem (mogłaby to mieć strona `docs.claude.com/.../model-deprecations`, której nie sprawdzałem — WebFetch był poza dozwolonym źródłem tego zadania).
- **Niepotwierdzona (z pamięci, NIE ze źródła):** w starszych wersjach API 1-godzinny TTL cache wymagał nagłówka beta `extended-cache-ttl-2025-04-11` lub podobnego. W sprawdzonych źródłach (skill `claude-api`, stan cache 2026-06-24) TEN wymóg już nie występuje — `ttl: "1h"` działa bez nagłówka beta. Nie mam potwierdzenia, kiedy dokładnie to się zmieniło ani czy jest to uniwersalne dla wszystkich kont/platform (np. Bedrock może się różnić — `shared/prompt-caching.md:169` sugeruje wariancje na Bedrock, ale nie sprawdzałem szczegółów).
- **Niepotwierdzona (z pamięci):** realne API zwraca też zagnieżdżony obiekt `cache_creation: {ephemeral_5m_input_tokens, ephemeral_1h_input_tokens}` jako rozbicie `cache_creation_input_tokens` na TTL. Sprawdziłem to explicite grepem w `prompt-caching.md` i `curl/examples.md` — brak wzmianki. Nie potwierdzone w przeszukanych źródłach, może istnieć w oficjalnej dokumentacji API której nie sprawdzałem (poza zakresem zlecenia: tylko skill + docs/anthropic).
- Nie sprawdzałem, czy `docs/anthropic/` zawiera gdzieś indziej (np. `anthropic-github/`) osobną, oficjalną kopię referencji Messages API — przeszukałem tylko katalog główny (same pliki `claude-code-*.md`, które dotyczą CLI Claude Code, nie surowego HTTP API) ze względu na limit kroków (dieta spawnu).
