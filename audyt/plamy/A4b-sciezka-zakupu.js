/* A4b — ŚCIEŻKA ZAKUPU OKIEM: produkt → zgoda → panel płatności → „Dziękujemy”.
 *
 * Uzupełnia `A4b-przelot-frontu.js` (tamten ogląda strony, ten KLIKA). Robi zrzuty
 * na desktopie i na telefonie w każdym kroku, plus dwa przypadki brzegowe, które
 * kupujący trafia naprawdę:
 *   - klik „Kup teraz” BEZ zaznaczonej zgody (co widzi człowiek),
 *   - strona „Dziękujemy” z PRAWDZIWYM id sesji (stan po odpytaniu REST).
 *
 * ⚠️ Granica dowodu maszynowego: Stripe wystawia niewidzialną hCaptchę
 * zautomatyzowanej przeglądarce (zmierzone 30.08, klasa 60 ściągi) — `confirm()`
 * nie przejdzie. Skrypt to ROZPOZNAJE i zapisuje jako `captcha`, zamiast wpisywać
 * „nie działa”. Dowód domknięcia płatności robi człowiek w zwykłej przeglądarce.
 *
 * Uruchomienie:
 *   flock /root/praca/zlecenia/wymiana/stos.lock \
 *     node audyt/plamy/A4b-sciezka-zakupu.js <id-sesji-istniejacego-zamowienia>
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');

const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const SESJA_ISTNIEJACA = process.argv[2] || '';
const KAT = path.join(__dirname, 'A4b-zrzuty');

const WIDOKI = [
  { klucz: 'desktop', viewport: { width: 1440, height: 900 }, isMobile: false, hasTouch: false, deviceScaleFactor: 1 },
  { klucz: 'telefon', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
];

async function wypelnijWRamkach(p, dopasowania) {
  const wynik = {};
  for (const [wzor, wartosc] of dopasowania) {
    let ok = false;
    for (const fr of p.frames()) {
      const pole = fr.locator(`input[name*="${wzor}" i], input[id*="${wzor}" i], input[placeholder*="${wzor}" i], input[autocomplete*="${wzor}" i]`).first();
      if (await pole.count().catch(() => 0)) {
        await pole.fill(wartosc, { timeout: 5000 }).then(() => { ok = true; }).catch(() => {});
        if (ok) { break; }
      }
    }
    wynik[wzor] = ok;
  }
  return wynik;
}

(async () => {
  fs.mkdirSync(KAT, { recursive: true });
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const raport = { baza: BAZA, kiedy: new Date().toISOString(), kroki: [] };

  for (const widok of WIDOKI) {
    const ctx = await b.newContext({
      viewport: widok.viewport, isMobile: widok.isMobile, hasTouch: widok.hasTouch,
      deviceScaleFactor: widok.deviceScaleFactor, locale: 'pl-PL',
    });
    const p = await ctx.newPage();
    const bledy = [];
    p.on('console', (m) => { if (m.type() === 'error') { bledy.push(m.text().slice(0, 160)); } });
    p.on('pageerror', (e) => bledy.push('WYJATEK ' + String(e).slice(0, 160)));

    const krok = { widok: widok.klucz, zdarzenia: [], bledy };
    await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'load', timeout: 45000 });
    await p.waitForTimeout(1500);

    /* KROK 1 — pudełko zakupu w kadrze. Przewijamy do formularza, nie zrzucamy calej strony. */
    await p.locator('#sklepag-kup-formularz').scrollIntoViewIfNeeded();
    await p.waitForTimeout(900);
    await p.screenshot({ path: path.join(KAT, `zakup-1-pudelko-${widok.klucz}.png`) });

    /* KROK 2 — klik BEZ zgody: co widzi czlowiek. */
    await p.click('#sklepag-kup-formularz .sklepag-kup').catch(() => {});
    await p.waitForTimeout(1200);
    krok.bezZgody = {
      panelWidoczny: await p.locator('#sklepag-platnosc:not([hidden])').count().then((n) => n > 0),
      komunikatPrzegladarki: await p.evaluate(() => {
        const c = document.getElementById('sklepag-zgoda-1');
        return c ? c.validationMessage : null;
      }),
      widocznyKomunikat: await p.evaluate(() => {
        const el = document.querySelector('[data-sklepag-blad], .sklepag-blad, [role=alert]:not([hidden])');
        return el ? (el.textContent || '').trim().slice(0, 160) : null;
      }),
    };
    await p.screenshot({ path: path.join(KAT, `zakup-2-bez-zgody-${widok.klucz}.png`) });

    /* KROK 3 — zgoda + „Kup teraz” → panel platnosci osadzonej. */
    await p.check('#sklepag-zgoda-1');
    await p.click('#sklepag-kup-formularz .sklepag-kup');
    let panel = false;
    try { await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 25000 }); panel = true; } catch (e) { /* zapisany nizej */ }
    await p.waitForTimeout(7000); // ramki Stripe
    krok.panelPlatnosci = panel;
    krok.bladPanelu = await p.evaluate(() => {
      const el = document.getElementById('sklepag-platnosc-blad');
      return el && !el.hidden ? (el.textContent || '').trim() : null;
    });
    await p.locator('#sklepag-platnosc').scrollIntoViewIfNeeded().catch(() => {});
    await p.waitForTimeout(700);
    await p.screenshot({ path: path.join(KAT, `zakup-3-panel-${widok.klucz}.png`) });

    /* KROK 4 — karta testowa + „Zapłać”. */
    if (panel) {
      for (const fr of p.frames()) {
        const t = fr.locator('[role=button]:has-text("Karta"), button:has-text("Karta"), [role=tab]:has-text("Karta"), [role=button]:has-text("Card"), button:has-text("Card"), [role=tab]:has-text("Card")').first();
        if (await t.count().catch(() => 0)) { await t.click({ timeout: 5000 }).catch(() => {}); break; }
      }
      await p.waitForTimeout(2500);
      krok.pola = await wypelnijWRamkach(p, [['email', 'audyt-a4b@test.local'], ['number', '4242424242424242'], ['expiry', '12 / 30'], ['cvc', '123']]);
      Object.assign(krok.pola, await wypelnijWRamkach(p, [['name', 'Audyt A4b'], ['postal', '00-001']]));
      await p.waitForTimeout(1500);
      krok.zaplacWlaczony = await p.evaluate(() => {
        const b = document.getElementById('sklepag-platnosc-zaplac');
        return b ? !b.disabled : null;
      });
      await p.screenshot({ path: path.join(KAT, `zakup-4-karta-${widok.klucz}.png`) });

      await p.click('#sklepag-platnosc-zaplac').catch(() => {});
      let powrot = false;
      try { await p.waitForURL((u) => u.toString().includes('dziekujemy'), { timeout: 60000 }); powrot = true; } catch (e) { /* zapisany nizej */ }
      krok.powrotNaDziekujemy = powrot;
      krok.captchaStripe = p.frames().some((f) => /hcaptcha|recaptcha/i.test(f.url()));
      krok.urlPoZaplac = p.url().split('?')[0];
      krok.bladPoZaplac = await p.evaluate(() => {
        const el = document.getElementById('sklepag-platnosc-blad');
        return el ? (el.textContent || '').trim() : null;
      }).catch(() => null);
      await p.screenshot({ path: path.join(KAT, `zakup-5-po-zaplac-${widok.klucz}.png`) });
    }

    /* KROK 5 — „Dziekujemy” z PRAWDZIWYM id sesji (odczyt, zero zapisu). */
    if (SESJA_ISTNIEJACA) {
      await p.goto(BAZA + '/dziekujemy-za-zakup/?sesja=' + encodeURIComponent(SESJA_ISTNIEJACA), { waitUntil: 'load', timeout: 30000 });
      await p.waitForTimeout(4000);
      krok.dziekujemyTekst = await p.evaluate(() => {
        const el = document.querySelector('.sklepag-dziekujemy');
        return el ? (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 400) : 'BRAK KONTENERA .sklepag-dziekujemy';
      });
      await p.screenshot({ path: path.join(KAT, `zakup-6-dziekujemy-${widok.klucz}.png`) });
    }

    raport.kroki.push(krok);
    console.log(JSON.stringify(krok, null, 1));
    await p.close();
    await ctx.close();
  }

  await b.close();
  fs.writeFileSync(path.join(__dirname, 'A4b-sciezka.json'), JSON.stringify(raport, null, 2));
  console.log('ZAPISANE: audyt/plamy/A4b-sciezka.json');
})().catch((e) => { console.error('ŚCIEŻKA PADŁA:', e); process.exit(1); });
