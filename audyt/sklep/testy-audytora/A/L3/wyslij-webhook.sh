#!/bin/bash
# uzycie: wyslij-webhook.sh <sekret> <plik-body>
SEK="$1"; B="$(cat "$2")"; T=$(date +%s)
SIG=$(printf '%s.%s' "$T" "$B" | openssl dgst -sha256 -hmac "$SEK" -r | cut -d' ' -f1)
curl -s -w " HTTP=%{http_code}\n" -X POST -H 'Content-Type: application/json' \
  -H "Stripe-Signature: t=$T,v1=$SIG" --data-binary "$B" \
  http://localhost:8890/wp-json/sklepag/v1/webhook
