-- Migracja 0001: schemat v1 wg PLAN-PROJEKTU.md §5 (MODEL DANYCH v2).
-- Czasy: TEXT ISO-8601 UTC (sortowalne, czytelne w panelu).
-- Kwoty: INTEGER w groszach (zero ułamków).

CREATE TABLE IF NOT EXISTS produkty (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nazwa TEXT NOT NULL,
  opis_krotki TEXT NOT NULL DEFAULT '',
  opis_pelny TEXT NOT NULL DEFAULT '',
  cena_grosze INTEGER NOT NULL CHECK (cena_grosze > 0),
  waluta TEXT NOT NULL DEFAULT 'pln',
  plik_klucz_r2 TEXT,
  plik_rozmiar INTEGER,
  plik_sha256 TEXT,
  -- aktywacja tylko z potwierdzonym plikiem w R2 (pilnuje kod, D8)
  aktywny INTEGER NOT NULL DEFAULT 0 CHECK (aktywny IN (0, 1)),
  utworzono TEXT NOT NULL,
  zmieniono TEXT NOT NULL
);

-- Ślad po NASZEJ stronie zanim przyjdzie webhook (C1): podstawa
-- "dociągnij zaległe" i testu sesji porzuconych.
CREATE TABLE IF NOT EXISTS sesje_checkout (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stripe_session_id TEXT UNIQUE,
  produkt_id INTEGER NOT NULL REFERENCES produkty (id),
  stan TEXT NOT NULL DEFAULT 'utworzona'
    CHECK (stan IN ('utworzona', 'oplacona', 'wygasla')),
  utworzono TEXT NOT NULL
);

-- Snapshot produktu z chwili zakupu (B11): edycja/deaktywacja produktu
-- NIE zmienia historii zamówień.
CREATE TABLE IF NOT EXISTS zamowienia (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stripe_session_id TEXT NOT NULL UNIQUE,
  produkt_id INTEGER NOT NULL,
  produkt_nazwa_kupiona TEXT NOT NULL,
  produkt_cena_kupiona INTEGER NOT NULL,
  produkt_plik_klucz TEXT NOT NULL,
  email_kupujacego TEXT,
  kwota_grosze INTEGER NOT NULL,
  waluta TEXT NOT NULL,
  -- przepływ §6: oczekuje -> oplacone -> mail_w_toku -> mail_wyslany | mail_blad
  status TEXT NOT NULL DEFAULT 'oczekuje'
    CHECK (status IN ('oczekuje', 'oplacone', 'mail_w_toku', 'mail_wyslany', 'mail_blad')),
  zgoda_tresc TEXT,
  zgoda_czas TEXT,
  link_wygasa TEXT,
  pobrania_limit INTEGER NOT NULL DEFAULT 10,
  pobrania_uzyte INTEGER NOT NULL DEFAULT 0,
  mail_proby INTEGER NOT NULL DEFAULT 0,
  utworzono TEXT NOT NULL,
  zmieniono TEXT NOT NULL
);

-- Nieusuwalna oś czasu (kartka MP): append-only, obejmuje TAKŻE akcje admina.
CREATE TABLE IF NOT EXISTS zdarzenia (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  zamowienie_id INTEGER,
  typ TEXT NOT NULL,
  opis TEXT NOT NULL,
  czas TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS logowania_admina (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  czas TEXT NOT NULL,
  login_wpisany TEXT NOT NULL,
  wynik TEXT NOT NULL CHECK (wynik IN ('udane', 'nieudane')),
  ip TEXT,
  user_agent TEXT
);

CREATE TABLE IF NOT EXISTS konfig (
  klucz TEXT PRIMARY KEY,
  wartosc TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_zamowienia_utworzono ON zamowienia (utworzono);
CREATE INDEX IF NOT EXISTS idx_zamowienia_status ON zamowienia (status);
CREATE INDEX IF NOT EXISTS idx_zdarzenia_zamowienie ON zdarzenia (zamowienie_id);
CREATE INDEX IF NOT EXISTS idx_sesje_stan ON sesje_checkout (stan);
