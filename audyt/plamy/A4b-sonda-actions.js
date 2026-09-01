/* A4b — SONDA: co naprawdę zwraca `checkout.loadActions()` w naszym panelu płatności.
 *
 * Powód: przelot ścieżki zakupu (A4b-sciezka-zakupu.js) złapał na OBU szerokościach
 * wyjątek `TypeError: actions.confirm is not a function`, rzucony z
 * `wtyczki/sklep-agentow/assets/platnosc.js:150`. Zanim oskarżę produkt, muszę
 * pokazać, czym jest obiekt, na którym wołamy `confirm()` — i czy metoda o tej
 * nazwie istnieje gdzie indziej (np. na samym `checkout`).
 *
 * Metoda: podmieniamy `window.Stripe` PRZED skryptami strony (addInitScript),
 * przechwytujemy obiekt z `initCheckoutElementsSdk()` i wypisujemy klucze —
 * jego własne, prototypu oraz wynik `loadActions()`. Niczego nie zmieniamy
 * w produkcie; sonda żyje wyłącznie w kontekście tej jednej karty przeglądarki.
 *
 * Uruchomienie:
 *   flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-sonda-actions.js
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });

  await p.addInitScript(() => {
    window.__A4B = { przechwycone: false, blad: null };
    let prawdziwy;
    Object.defineProperty(window, 'Stripe', {
      configurable: true,
      get() { return prawdziwy ? opakuj(prawdziwy) : undefined; },
      set(v) { prawdziwy = v; },
    });
    function opakuj(fn) {
      const zastepca = function (...a) {
        const stripe = fn.apply(this, a);
        const oryg = stripe.initCheckoutElementsSdk;
        if (typeof oryg === 'function') {
          stripe.initCheckoutElementsSdk = function (...b) {
            const checkout = oryg.apply(this, b);
            window.__A4B.przechwycone = true;
            window.__A4B.checkout = checkout;
            return checkout;
          };
        } else {
          window.__A4B.blad = 'brak initCheckoutElementsSdk na obiekcie stripe';
        }
        return stripe;
      };
      Object.assign(zastepca, fn);
      return zastepca;
    }
  });

  await p.goto(process.env.A4B_URL || (BAZA + '/sklep/produkt/chatbot-live/'), { waitUntil: 'load', timeout: 45000 });
  await p.waitForTimeout(1500);
  await p.check('#sklepag-kup-formularz input[name="zgoda"]');
  await p.click('#sklepag-kup-formularz .sklepag-kup');
  await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 25000 });
  await p.waitForTimeout(8000);

  const wynik = await p.evaluate(async () => {
    const s = window.__A4B;
    if (!s || !s.przechwycone) { return { przechwycone: false, blad: s ? s.blad : 'brak sondy' }; }
    const c = s.checkout;
    const klucze = (o) => {
      const zbior = new Set();
      let x = o;
      for (let i = 0; i < 4 && x; i += 1) {
        Object.getOwnPropertyNames(x).forEach((k) => zbior.add(k));
        x = Object.getPrototypeOf(x);
      }
      return [...zbior].sort();
    };
    const out = {
      przechwycone: true,
      typCheckout: typeof c,
      kluczeCheckout: klucze(c),
      confirmNaCheckout: typeof (c && c.confirm),
      loadActionsIstnieje: typeof (c && c.loadActions),
    };
    if (typeof c.loadActions === 'function') {
      try {
        const a = await c.loadActions();
        out.typActions = typeof a;
        out.kluczeActions = klucze(a);
        out.confirmNaActions = typeof (a && a.confirm);
        out.typPola = a && a.type;
        out.kluczeAActions = a && a.actions ? klucze(a.actions) : null;
        out.confirmNaAActions = typeof (a && a.actions && a.actions.confirm);
      } catch (e) { out.bladLoadActions = String(e).slice(0, 200); }
    }
    return out;
  });

  console.log(JSON.stringify(wynik, null, 2));
  await b.close();
})().catch((e) => { console.error('SONDA PADŁA:', e); process.exit(1); });
