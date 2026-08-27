// Re-check 5 punktow przelotu na kodzie 0.9.19 (A5, A6, A12, C2). B6 osobno (wymaga logowania).
const { chromium } = require('/usr/lib/node_modules/playwright');
const D = '/root/praca/k7-dowody-28-08';
const PROD = 'http://localhost:8890/sklep/produkt/chatbot-live/';
const out = [];
const log = (s) => { console.log(s); out.push(s); };

(async () => {
  const b = await chromium.launch();

  // ---------- A5: nagranie w galerii — widoczny przycisk? ----------
  let p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(PROD, { waitUntil: 'networkidle' });
  const a5 = await p.evaluate(() => {
    const res = { przyciski: [], plakaty: [] };
    document.querySelectorAll('button, a, [role="button"]').forEach(el => {
      const t = (el.textContent || '').trim();
      if (/odtw[oó]rz|nagran/i.test(t)) {
        const cs = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        res.przyciski.push({ tekst: t.slice(0, 40), display: cs.display, visibility: cs.visibility,
          opacity: cs.opacity, w: Math.round(r.width), h: Math.round(r.height), tag: el.tagName });
      }
    });
    document.querySelectorAll('img, video').forEach(el => {
      const src = el.getAttribute('poster') || el.getAttribute('src') || '';
      if (/nagran|video|poster|plakat/i.test(src) || el.tagName === 'VIDEO') {
        res.plakaty.push({ tag: el.tagName, opacity: getComputedStyle(el).opacity, src: src.slice(-45) });
      }
    });
    return res;
  });
  log('=== A5 · przycisk odtwarzania nagrania (0.9.19) ===');
  log(JSON.stringify(a5, null, 1));
  await p.screenshot({ path: `${D}/A5-0919-galeria.png` });

  // ---------- C2: Tab dochodzi do przycisku galerii? ----------
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.keyboard.press('Tab');
  const trasa = [];
  for (let i = 0; i < 45; i++) {
    const f = await p.evaluate(() => {
      const a = document.activeElement; if (!a) return null;
      const r = a.getBoundingClientRect();
      return { tag: a.tagName, txt: (a.textContent || a.value || '').trim().slice(0, 38),
               cls: (a.className || '').toString().slice(0, 40), w: Math.round(r.width) };
    });
    if (f) trasa.push(f);
    await p.keyboard.press('Tab');
  }
  const trafiony = trasa.find(f => /odtw[oó]rz|nagran|galeri/i.test(f.txt + ' ' + f.cls));
  log('=== C2 · czy Tab dosiega przycisku nagrania (0.9.19) ===');
  log(trafiony ? 'TAK -> ' + JSON.stringify(trafiony) : 'NIE — w 45 krokach Tab nie trafil w przycisk nagrania');
  log('trasa Tab (pierwsze 20): ' + JSON.stringify(trasa.slice(0, 20).map(f => f.tag + ':' + (f.txt || f.cls).slice(0, 22))));

  // ---------- A6: blokada bez zgody — jezyk komunikatu ----------
  const zgoda = await p.evaluate(() => {
    const cb = [...document.querySelectorAll('input[type=checkbox]')]
      .find(c => /zgod|regulamin|akcept/i.test((c.closest('label')?.textContent || '') + c.name + c.id));
    if (!cb) return { brak: true };
    return { name: cb.name, id: cb.id, required: cb.required, checked: cb.checked,
             etykieta: (cb.closest('label')?.textContent || '').trim().slice(0, 90) };
  });
  log('=== A6 · pole zgody (0.9.19) ===');
  log(JSON.stringify(zgoda));
  // odznacz i sprobuj kupic — blokujemy wyjscie na stripe.com na wszelki wypadek
  await p.route('**://*.stripe.com/**', r => r.abort());
  await p.evaluate(() => {
    const cb = [...document.querySelectorAll('input[type=checkbox]')]
      .find(c => /zgod|regulamin|akcept/i.test((c.closest('label')?.textContent || '') + c.name + c.id));
    if (cb) cb.checked = false;
  });
  const przedURL = p.url();
  const btn = await p.$('button:has-text("Kup teraz"), input[value*="Kup teraz"]');
  if (btn) { await btn.click({ force: true }).catch(() => {}); }
  await p.waitForTimeout(1200);
  const a6 = await p.evaluate(() => {
    const cb = [...document.querySelectorAll('input[type=checkbox]')]
      .find(c => /zgod|regulamin|akcept/i.test((c.closest('label')?.textContent || '') + c.name + c.id));
    const wlasny = [...document.querySelectorAll('[class*="blad"],[class*="error"],[role=alert],[class*="komunikat"]')]
      .map(e => (e.textContent || '').trim()).filter(t => t.length > 2 && t.length < 200);
    return { natywny_komunikat: cb ? cb.validationMessage : '(brak pola)',
             wlasne_komunikaty_w_DOM: wlasny, url: location.href };
  });
  log('URL przed klikiem: ' + przedURL);
  log(JSON.stringify(a6, null, 1));
  await p.screenshot({ path: `${D}/A6-0919-bez-zgody.png` });
  await p.close();

  // ---------- A12: pigulka na telefonie ----------
  p = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  await p.goto(PROD, { waitUntil: 'networkidle' });
  const wys = await p.evaluate(() => document.body.scrollHeight);
  let kolizje = [];
  for (let y = 0; y < wys - 844; y += 200) {
    await p.evaluate(sy => window.scrollTo(0, sy), y);
    await p.waitForTimeout(350);
    const k = await p.evaluate(() => {
      const pig = document.querySelector('.sklepag-sticky, [class*="sticky"]');
      if (!pig) return null;
      const cs = getComputedStyle(pig);
      if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return null;
      const pr = pig.getBoundingClientRect();
      if (pr.height === 0) return null;
      const zderzenia = [];
      document.querySelectorAll('p, li, h1, h2, h3, span').forEach(el => {
        if (pig.contains(el) || el.contains(pig)) return;
        const t = (el.textContent || '').trim(); if (t.length < 12) return;
        if (el.children.length > 0) return;
        const r = el.getBoundingClientRect();
        if (r.height === 0 || r.bottom < 0 || r.top > innerHeight) return;
        const nakl = Math.min(r.bottom, pr.bottom) - Math.max(r.top, pr.top);
        const nakl_x = Math.min(r.right, pr.right) - Math.max(r.left, pr.left);
        if (nakl > 2 && nakl_x > 2) zderzenia.push({ txt: t.slice(0, 40), naklada_px: Math.round(nakl) });
      });
      return { pigulka_top: Math.round(pr.top), zderzenia };
    });
    if (k && k.zderzenia.length) kolizje.push({ scrollY: y, ...k });
  }
  log('=== A12 · pigulka zakupu vs tresc, 390x844 (0.9.19) ===');
  log(kolizje.length ? JSON.stringify(kolizje, null, 1) : 'BRAK kolizji na calej dlugosci strony (krok 200 px)');
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));
  await p.waitForTimeout(400);
  await p.screenshot({ path: `${D}/A12-0919-telefon-polowa.png` });
  await p.close();
  await b.close();

  require('fs').writeFileSync(`${D}/RECHECK-0919-wynik.txt`, out.join('\n'));
})();
