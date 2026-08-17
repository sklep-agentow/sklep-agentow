# ZWIAD: Stripe Checkout + webhooki (destylat z docs.stripe.com, 2026-08-17)

Zebrane subagentem wyłącznie z OFICJALNEJ dokumentacji (12 wywołań, każdy punkt z URL).
Wiedza NIEZALEŻNA od platformy strony (statyczny HTML / WordPress) — płatności obsługuje
zaplecze, nie strona.

## 1. Tworzenie Checkout Session — goły REST przez fetch (bez SDK)

Oficjalna droga potwierdzona w docs; SDK stripe-node w środowisku Workers NIE ma strony
w docs.stripe.com (tylko nieoficjalne szablony — odrzucone).

```
POST https://api.stripe.com/v1/checkout/sessions
Authorization: Bearer sk_test_...
Content-Type: application/x-www-form-urlencoded

mode=payment
line_items[0][price]={PRICE_ID}   # albo price_data (ceny "na żywca")
line_items[0][quantity]=1
success_url=https://.../dziekujemy?session_id={CHECKOUT_SESSION_ID}
cancel_url=https://.../sklep
customer_email=... (opcjonalnie; bez tego Stripe sam poprosi o mail)
```
- `{CHECKOUT_SESSION_ID}` w success_url — potwierdzony placeholder.
- Źródła: docs.stripe.com/api/checkout/sessions/create · docs.stripe.com/checkout/quickstart
- ⚠️ Dokładne podpola `price_data` (currency/unit_amount/product_data.name) — nazwa parametru
  potwierdzona, pełny schemat DO DOCZYTANIA przy implementacji.

## 2. Webhook — weryfikacja podpisu RĘCZNIE (Web Crypto, bez SDK)

`constructEventAsync` NIE istnieje w docs.stripe.com (tylko w issues stripe-node) — 
udokumentowany jest algorytm ręczny (docs.stripe.com/webhooks, „Verify manually"):

1. Nagłówek `Stripe-Signature`: `t=<timestamp>,v1=<podpis>,...` — rozbić po `,` i `=`.
2. `signed_payload = timestamp + "." + SUROWE_body` (żadnych modyfikacji/parsowania przed).
3. HMAC-SHA256(sekret_webhooka, signed_payload) → porównać STAŁOCZASOWO z v1.
4. Tolerancja czasu: domyślnie 5 min; tolerance=0 ZABRONIONE (wyłącza ochronę przed replay).

Web Crypto: `crypto.subtle.importKey('raw', enc(secret), {name:'HMAC',hash:'SHA-256'},
false, ['sign'])` → `crypto.subtle.sign(...)` → hex → porównanie stałoczasowe.

- Event zakupu: **`checkout.session.completed`** (potwierdzony w oficjalnym przykładzie CLI).
- `checkout.session.expired` — NIEPOTWIERDZONY w pobranej treści (sprawdzić przy implementacji).
- W payload `data.object` = obiekt sesji: `id` (cs_test_...), `customer_email`, `amount_total`,
  `currency`, `payment_status`, `status`.

## 3. Tryb testowy (docs.stripe.com/testing)

- Sukces: `4242 4242 4242 4242` (dowolny CVC, przyszła data)
- Odmowa: `4000 0000 0000 0002` (generic_decline) · wariant z quickstart: 4000 0000 0000 9995
- 3DS wymagane: `4000 0025 0000 3155`
- **BLIK testowo: NIEPOTWIERDZONE** (sugestia z wyszukiwarki, bez weryfikacji w docs) —
  rejestr założeń Z1, sprawdzić przy kości.
- ⛔ Sekret webhooka CLI ≠ sekret endpointu z Dashboardu — oba `whsec_...`, ale RÓŻNE
  wartości; nie mieszać (docs.stripe.com/webhooks/signature, cytat wprost).

## 4. Idempotencja po stronie Stripe (docs.stripe.com/api/idempotent_requests)

- Nagłówek `Idempotency-Key` (max 255 znaków, zalecany UUID v4, bez danych osobowych).
- Stripe zapisuje wynik PIERWSZEGO requestu; powtórka z tym samym kluczem = ten sam wynik.
- Retencja klucza: min. 24 h (potem klucz może być usunięty i request wykona się od nowa).
- Ten sam klucz + INNE parametry = błąd (ochrona przed pomyłką). Dotyczy tylko POST.

## 5. Retry webhooków (docs.stripe.com/webhooks, „Event delivery behaviors")

- Live: ponowienia do 3 DNI (exponential backoff). **Test/sandbox: 3 próby w kilka godzin.**
- Sukces = wyłącznie 2xx. 3xx (przekierowania!), 4xx, 5xx, timeout, TLS = porażka.
- Ręczny resend: Dashboard do 15 dni, CLI (`stripe events resend`) do 30 dni.
- Wniosek dla nas: handler webhooka ZWRACA BŁĄD dopóki cała robota niedomknięta —
  ponowienia DOKAŃCZAJĄ (wzorzec resume z planu §6); w trybie testowym mamy tylko 3 próby,
  więc cron „dociągnij zaległe" jest konieczny, nie opcjonalny.

## CZEGO ZWIAD NIE POTWIERDZIŁ (do doczytania przy implementacji)
1. stripe-node w Workers (brak oficjalnej strony) → REST przez fetch.
2. `constructEventAsync` (nie w docs; plan §4D go wymienia — do korekty planu: implementacja
   ręczna wg „Verify manually" spełnia ten sam cel).
3. `checkout.session.expired` jako nazwa eventu.
4. BLIK testowo (magic values).
5. Pełny schemat `price_data`.
