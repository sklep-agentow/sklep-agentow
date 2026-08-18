# Testy E2E na żywym stacku warsztatowym

Te testy NIE biegają w CI (tam nie ma WP + Stripe) — odpalasz je na maszynie
ze stackiem: WordPress na `http://localhost:8890`, mailpit na `:8025`,
`stripe listen` forwardujący webhooki (sekrety w `/root/.claude/sekrety-sklep.env`,
POZA repo).

## przelot2.js — pełny przelot zakupu kartą testową

Ścieżka: sklep → POST kup (nonce + zgoda) → hostowany Checkout Stripe
(karta 4242… w iframe'ach Elements) → powrót → status przez REST aż do
`mail_wyslany`. Kod wyjścia 0 = przelot domknięty, 2 = mail nie wyszedł, 1 = błąd.

Wymaga (jednorazowo, poza repo — `node_modules/` nie wjeżdża do gita):

```
cd /root/praca/zlecenia/sklep-agentow && npm install --no-save playwright-core
```

Uruchomienie:

```
cd /root/praca/zlecenia/sklep-agentow && node testy/e2e/przelot2.js
```

Uwaga: skrypt używa przeglądarki z `/root/.cache/ms-playwright/` (ścieżka
w środku pliku) — na innej maszynie popraw `executablePath`.
