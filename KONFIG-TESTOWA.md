# KONFIGURACJA TESTOWA (egzaminacyjna) — dane JAWNE

- Konto Stripe: założone 17.08.2026 (mail konta klienckiego), TRYB TESTOWY, bez aktywacji.
- Klucz publiczny (publishable, jawny z natury — trafi do config.js witryny):
  `pk_test_51U5FhK7IEaC3aBGblOEl0u4vWlxzVXr3agYlUxmD2BM4MlU6JRqDsJjkriLKYcCYNrkulIXRYI2gXgYpsm1Z0qeg00pAOPGUw1`
- Klucz sekretny (`sk_test_...`): NIGDY tutaj — leży w `/root/.claude/sekrety-sklep.env`
  (chmod 600), stamtąd trafia wyłącznie do magazynu sekretów Workera.
- Sekret webhooka (`whsec_...`): powstanie przy podpinaniu webhooka w kości — ta sama zasada.
