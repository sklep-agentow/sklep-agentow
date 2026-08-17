# ZWIAD: Cloudflare Worker + D1 + R2 przez wranglera (destylat z developers.cloudflare.com, 2026-08-17)

Zebrane subagentem wyłącznie z OFICJALNEJ dokumentacji (12 wywołań). Dotyczy WARIANTU
zaplecza na Cloudflare — jeśli piwot WP go zmieni, plik zostaje jako zwiad wariantu.

## 1. Konfiguracja: wrangler.jsonc (ZALECANY dla nowych projektów)

„Some newer Wrangler features will only be available to projects using a JSON config file."

```jsonc
{
  "$schema": "./node_modules/wrangler/config-schema.json",
  "name": "sklep-zaplecze",
  "main": "src/index.js",
  "compatibility_date": "2026-08-17",
  "account_id": "<ACCOUNT_ID>",            // u nas: z `wrangler whoami` (17.08 działa)
  "d1_databases": [{ "binding": "DB", "database_name": "...", "database_id": "<uuid>" }],
  "r2_buckets": [{ "binding": "BUCKET", "bucket_name": "..." }],
  "triggers": { "crons": ["*/10 * * * *"] }
}
```
- Auth CLI: env `CLOUDFLARE_API_TOKEN` (mamy w sekrety-sklep.env, zweryfikowany).
- Środowiska (`env.<nazwa>`): bindingi NIE dziedziczą się — definiować per env.
- Źródło: developers.cloudflare.com/workers/wrangler/configuration/

## 2. D1

- `wrangler d1 create <nazwa>` → output zawiera gotowy blok JSON z `database_id`.
- Zapytania w Workerze (env.DB): `prepare().bind().run()/first()/raw()`.
- **`env.DB.batch([...])` = TRANSAKCJA** („If a statement in the sequence fails...
  it aborts or rolls back the entire sequence") — nasz mechanizm atomowości.
- `d1 execute <db> --local/--remote` — bez flagi lokalna Miniflare, `--remote` = edge.
- Źródła: developers.cloudflare.com/d1/get-started/ · /d1/worker-api/d1-database/
- ⚠️ NIEPOTWIERDZONE: składnia `d1 migrations create/apply` · przykład `ON CONFLICT DO
  NOTHING` (D1=SQLite, składnia SQLite prawdopodobna, ale bez oficjalnego przykładu —
  sprawdzić testem na kości).

## 3. R2

- Upload z terminala: `wrangler r2 object put <bucket>/<klucz> --file=<plik>`.
- Worker (env.BUCKET): `get(key)` → `object.body` STRUMIENIOWANE w Response;
  `object.writeHttpMetadata(headers)` + własny `Content-Disposition: attachment`.
- **Presigned URLs: oficjalnie TAK, przez S3 API** — wymaga OSOBNYCH kluczy S3
  (Access Key ID + Secret), innych niż CLOUDFLARE_API_TOKEN. Podpis AWS SigV4
  liczony client-side. Źródło: developers.cloudflare.com/r2/api/s3/presigned-urls/
- ⚠️ NIEPOTWIERDZONE: `r2 bucket create` (standardowy wzorzec, bez cytatu) · flagi
  content-type/disposition przy `object put`.

## 4. Sekrety Workera

- Udokumentowane non-interactive: `wrangler secret bulk secrets.json` (bez deployu)
  albo `wrangler deploy --secrets-file secrets.json`. Pipe stdin — niepotwierdzony.
- Dostęp w kodzie: `env.NAZWA`. Lokalne dev: pliki `.dev.vars` / `.dev.vars.<env>`.
- Źródło: developers.cloudflare.com/workers/configuration/secrets/

## 5. Panel (statyczne pliki) + API w JEDNYM Workerze

```jsonc
"assets": { "directory": "./panel", "binding": "ASSETS", "run_worker_first": ["/api/*"] }
```
- Domyślnie URL pasujący do pliku → asset bez kodu; `run_worker_first` wymusza fetch
  handler dla wzorców (API). Ręczna delegacja: `env.ASSETS.fetch(request)`.
- Źródło: developers.cloudflare.com/workers/static-assets/

## CZEGO ZWIAD NIE POTWIERDZIŁ (doprecyzować przy budowie: `--help` na żywo)
d1 migrations create/apply (+--remote) · r2 bucket create · wrangler dev local-vs-remote
i --test-scheduled (cron) · vitest-pool-workers setup · secret put --env · flagi object put ·
ON CONFLICT przykład · --json przy d1 create.
