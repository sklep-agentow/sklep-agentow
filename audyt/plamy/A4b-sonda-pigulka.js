/* A4b — SONDA UKŁADU: co zasłania pigułka „Kup teraz”, gdy otwarty jest panel płatności.
 *
 * Mechanizm z kodu (`assets/front-ruch.js:292-306`): pigułka chowa się, gdy WIDOCZNY jest
 * `#sklepag-kup-formularz`. Otwarcie płatności ustawia `form.hidden = true`
 * (`assets/platnosc.js:125`) — formularz przestaje być widoczny, więc obserwator
 * ODSŁANIA pigułkę dokładnie w chwili, w której kupujący wypełnia dane karty.
 * Ta sonda mierzy, czy i CO pigułka wtedy przykrywa — na telefonie i na desktopie.
 *
 * Uruchomienie:
 *   flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-sonda-pigulka.js
 */
const path = require('path');
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const KAT = path.join(__dirname, 'A4b-zrzuty');

const WIDOKI = [
  { klucz: 'desktop', viewport: { width: 1440, height: 900 }, isMobile: false, hasTouch: false },
  { klucz: 'telefon', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
];

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  for (const w of WIDOKI) {
    const ctx = await b.newContext({ viewport: w.viewport, isMobile: w.isMobile, hasTouch: w.hasTouch, locale: 'pl-PL' });
    const p = await ctx.newPage();
    await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'load', timeout: 45000 });
    await p.waitForTimeout(1500);

    const przedOtwarciem = await p.evaluate(() => {
      const el = document.querySelector('.sklepag-sticky');
      return el ? el.classList.contains('sklepag-sticky--widoczna') : null;
    });

    await p.check('#sklepag-zgoda-1');
    await p.click('#sklepag-kup-formularz .sklepag-kup');
    await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 25000 });
    await p.waitForTimeout(8000);

    /* Pigułka jest przyklejona do dołu okna, a treść płynie pod nią — więc pytanie
       „czy zasłania” ma sens dopiero PRZY PRZEWINIĘCIU w głąb panelu, tak jak robi
       to kupujący wypełniający pola. Mierzymy w dwóch miejscach: na górze panelu
       (wyżej) i po zjechaniu na jego koniec (tu). */
    await p.evaluate(() => {
      const pan = document.getElementById('sklepag-platnosc');
      window.scrollTo({ top: pan.getBoundingClientRect().bottom + window.scrollY - window.innerHeight, behavior: 'instant' });
    });
    await p.waitForTimeout(1200);

    const pomiar = await p.evaluate(() => {
      const pig = document.querySelector('.sklepag-sticky');
      if (!pig) { return { brakPigulki: true }; }
      const r = pig.getBoundingClientRect();
      const widoczna = pig.classList.contains('sklepag-sticky--widoczna') && r.width > 0 && r.height > 0;
      /* Co pigułka przykrywa: elementy panelu płatności, których prostokąt
         przecina prostokąt pigułki i które są w kadrze. */
      const zaslaniane = [];
      for (const el of document.querySelectorAll('#sklepag-platnosc *')) {
        const q = el.getBoundingClientRect();
        if (q.width === 0 || q.height === 0) { continue; }
        const przecina = q.left < r.right && q.right > r.left && q.top < r.bottom && q.bottom > r.top;
        if (przecina) {
          zaslaniane.push({ tag: el.tagName.toLowerCase(), id: el.id || '', klasa: (el.className || '').toString().slice(0, 50) });
        }
      }
      const kotwica = pig.querySelector('a');
      return {
        widoczna,
        prostokat: { g: Math.round(r.top), d: Math.round(r.bottom), l: Math.round(r.left), p: Math.round(r.right) },
        formularzSchowany: document.getElementById('sklepag-kup-formularz').hidden,
        celKotwicy: kotwica ? kotwica.getAttribute('href') : null,
        napisKotwicy: kotwica ? kotwica.textContent.trim() : null,
        celIstnieje: kotwica && kotwica.getAttribute('href') ? !!document.querySelector(kotwica.getAttribute('href')) : null,
        zaslanianeSztuk: zaslaniane.length,
        zaslaniane: zaslaniane.slice(0, 8),
      };
    });

    await p.screenshot({ path: path.join(KAT, `pigulka-w-platnosci-${w.klucz}.png`) });
    console.log(w.klucz + ' | pigułka przed otwarciem widoczna=' + przedOtwarciem);
    console.log(JSON.stringify(pomiar, null, 1));
    await p.close();
    await ctx.close();
  }
  await b.close();
})().catch((e) => { console.error('SONDA PADŁA:', e); process.exit(1); });
