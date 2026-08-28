// A12 poprawnie: czy JAKIS tekst jest ZAWSZE pod pigulka (nie do przeczytania na zadnym scrollu)?
// C2 czesc druga: czy w otwartym pelnym ekranie Tab NIE ucieka pod spod (pulapka fokusu)?
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../../../testy/lib/chromium');
const D = __dirname + '/wyniki';
const PROD = 'http://localhost:8890/sklep/produkt/chatbot-live/';
const out = []; const log = s => { console.log(s); out.push(s); };

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });

  // ---------- A12 ----------
  let p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto(PROD, { waitUntil: 'networkidle' });
  await p.evaluate(() => { document.querySelectorAll('p,li,h1,h2,h3').forEach((e,i)=>e.setAttribute('data-sonda',i)); });
  const wys = await p.evaluate(() => document.body.scrollHeight);
  const czysty = new Set(); const wszystkie = new Set();
  for (let y = 0; y <= wys; y += 100) {
    await p.evaluate(sy => window.scrollTo(0, sy), y);
    await p.waitForTimeout(120);
    const r = await p.evaluate(() => {
      const pig = document.querySelector('.sklepag-sticky, [class*="sticky"]');
      let pr = null;
      if (pig) { const cs = getComputedStyle(pig);
        if (cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0') {
          const q = pig.getBoundingClientRect(); if (q.height > 0) pr = q; } }
      const widoczne = [], czyste = [];
      document.querySelectorAll('[data-sonda]').forEach(el => {
        const t = (el.textContent||'').trim(); if (t.length < 12 || el.children.length) return;
        const q = el.getBoundingClientRect(); if (q.height === 0) return;
        const id = el.getAttribute('data-sonda');
        // element w calosci w oknie?
        if (q.top < 0 || q.bottom > innerHeight) { widoczne.push(id); return; }
        widoczne.push(id);
        if (!pr) { czyste.push(id); return; }
        const nakl = Math.min(q.bottom, pr.bottom) - Math.max(q.top, pr.top);
        const naklX = Math.min(q.right, pr.right) - Math.max(q.left, pr.left);
        if (!(nakl > 2 && naklX > 2)) czyste.push(id);
      });
      return { widoczne, czyste };
    });
    r.widoczne.forEach(i => wszystkie.add(i));
    r.czyste.forEach(i => czysty.add(i));
  }
  const zawsze_zaslonione = [...wszystkie].filter(i => !czysty.has(i));
  log('=== A12 · czy jakis tekst jest ZAWSZE pod pigulka (390x844, 0.9.19) ===');
  log(`elementow tekstowych zbadanych: ${wszystkie.size} · dalo sie przeczytac bez zaslony: ${czysty.size}`);
  if (zawsze_zaslonione.length) {
    const tresc = await p.evaluate(ids => ids.map(i => (document.querySelector(`[data-sonda="${i}"]`)?.textContent||'').trim().slice(0,70)), zawsze_zaslonione);
    log('ZAWSZE ZASLONIETE (' + zawsze_zaslonione.length + '): ' + JSON.stringify(tresc, null, 1));
  } else log('WYNIK: zero tekstu trwale zaslonietego — kazdy da sie wyscrollowac spod pigulki');
  await p.close();

  // ---------- C2 czesc 2: pulapka fokusu w pelnym ekranie ----------
  p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(PROD, { waitUntil: 'networkidle' });
  const kafel = await p.$('.sklepag-kafel-odtworz, a[class*="lightbox"]');
  await kafel.click();
  await p.waitForTimeout(900);
  const otwarty = await p.evaluate(() => {
    const lb = document.querySelector('[class*="lightbox"][class*="otwart"], .sklepag-lightbox-panel, dialog[open], [class*="lightbox"]:not(a)');
    return lb ? { klasa: lb.className.toString().slice(0,70), display: getComputedStyle(lb).display } : null;
  });
  log('=== C2b · pelny ekran otwarty? ==='); log(JSON.stringify(otwarty));
  const uciekl = [];
  for (let i = 0; i < 20; i++) {
    await p.keyboard.press('Tab');
    const f = await p.evaluate(() => {
      const a = document.activeElement; if (!a) return null;
      const lb = [...document.querySelectorAll('[class*="lightbox"]')].find(e => e.tagName !== 'A' && getComputedStyle(e).display !== 'none');
      return { tag: a.tagName, txt: (a.textContent||a.value||'').trim().slice(0,32),
               cls: (a.className||'').toString().slice(0,38),
               w_srodku: lb ? lb.contains(a) : null };
    });
    uciekl.push(f);
  }
  const poza = uciekl.filter(f => f && f.w_srodku === false);
  log('=== C2b · 20 x Tab w otwartym pelnym ekranie ===');
  log(poza.length ? 'FOKUS UCIEKL POD SPOD ' + poza.length + '/20 razy -> ' + JSON.stringify(poza.slice(0,5))
                  : 'fokus zostal w pelnym ekranie przez wszystkie 20 krokow (pulapka dziala)');
  log('slad: ' + JSON.stringify(uciekl.map(f => (f? (f.txt||f.cls).slice(0,20)+'|'+f.w_srodku : 'null'))));
  await p.screenshot({ path: `${D}/C2-0919-lightbox-tab.png` });
  // Esc zamyka?
  await p.keyboard.press('Escape'); await p.waitForTimeout(500);
  const poEsc = await p.evaluate(() => {
    const lb = [...document.querySelectorAll('[class*="lightbox"]')].find(e => e.tagName !== 'A');
    return lb ? getComputedStyle(lb).display : '(brak elementu)';
  });
  log('po Esc, display pelnego ekranu: ' + poEsc);
  await p.close(); await b.close();
  require('fs').appendFileSync(`${D}/RECHECK-0919-wynik.txt`, '\n\n' + out.join('\n'));
})();
