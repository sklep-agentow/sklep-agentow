# SPRZĄTANIE — raport (2026-08-29)

## PEWNE DO USUNIĘCIA (z powodem i rozmiarem)
- Brak. Nie znaleziono *.tmp, *.bak, *_backup*, *~, .DS_Store, *.log poza audyt/, *.db, *.sqlite.

## DO DECYZJI CZŁOWIEKA (nie wiem czy potrzebne)
- `wymiana/k7-mutanty/sklep-agentow-0.9.13-MUTANTY.zip` (~3,9 MB) — jedyny plik w katalogu
  `wymiana/`, CAŁY katalog nieśledzony przez git (`git status` → `?? wymiana/`). Mutant testowy
  wersji 0.9.13, obecna wersja to 0.9.22. Może być dowodem audytu k7 albo odpadem po skończonym
  teście — nie sprawdzałem treści audytu k7, żeby to rozstrzygnąć.
- `boty/paczki/` — 23 pliki zip, 45 MB, i WSZYSTKIE są commitowane do gita
  (`git ls-files boty/paczki` → 23/23). To stare wersje: chatbot-live 1.5.7→1.5.13,
  segregator-maila 1.3.0→1.3.6, sklep-agentow 0.9.13→0.9.22. Bo są w historii gita, `.git/`
  waży 140 MB (na 284 MB całego katalogu). Jeśli do wydania potrzebna tylko najnowsza paczka
  per bot — reszta z working tree to ~40 MB do odzyskania; wyczyszczenie z HISTORII gita to
  osobna, nieodwracalna operacja (rewrite historii) — wymaga zgody Dzidka.

## ZOSTAWIĆ (wygląda na śmieć, ale jest potrzebne)
- `node_modules/` (14 MB) — w `.gitignore`, standardowa zależność dev, nie kasować ręcznie.
- `audyt/sklep/PROBY-KONTROLNE.log` — jedyny log wyjęty z reguły `.gitignore` (`!audyt/sklep/...`)
  wprost z komentarza w pliku: "Dowody audytu MUSZĄ być w repo — log poza origin nie istnieje".
- `.git/` (140 MB) — to historia repo, nie plik roboczy; rozdęta przez zipy w `boty/paczki/`
  (patrz wyżej), ale samego katalogu `.git` nie rusza się bez planu na rewrite historii.

ŁĄCZNIE: 2 pozycje do decyzji, ~49 MB (3,9 MB + 45 MB).
