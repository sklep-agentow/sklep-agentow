/* A4b — PRZELOT FRONTU OKIEM. Audyt białej plamy A4b (mapa terenu infra).
 *
 * Po co: nikt nigdy nie przeszedł ścieżki zakupu okiem. A4 zmierzyło, JAK ładują
 * się zasoby — nie CO robią. Ten skrypt robi to drugie.
 *
 * Zasady, których pilnuje (z briefingu audytora 01-09):
 *  - zrzut OKNEM po WOLNYM przewinięciu, nie `fullPage` — inaczej animacje wjazdu
 *    (IntersectionObserver) nie odpalają i ocenia się stan, którego nikt nie widzi;
 *  - dwie szerokości: desktop 1440x900 i telefon 390x844 (isMobile+hasTouch);
 *  - zbiera BŁĘDY konsoli, wyjątki JS, żądania 4xx/5xx i przepełnienie w poziomie.
 *
 * Uruchomienie (zawsze pod flockiem, stos jest wspólny z builderem):
 *   flock /root/praca/zlecenia/wymiana/stos.lock \
 *     node audyt/plamy/A4b-przelot-frontu.js
 *
 * Wynik: audyt/plamy/A4b-zrzuty/*.png + A4b-pomiar.json (kod wyjścia 0 = przelot
 * się odbył; werdykt CZYSTO/ZNALEZIONE stawia człowiek z raportu, nie ten skrypt).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');

const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const KAT = path.join(__dirname, 'A4b-zrzuty');

const STRONY = [
  { klucz: 'sklep', url: BAZA + '/sklep/' },
  { klucz: 'produkt-chatbot', url: BAZA + '/sklep/produkt/chatbot-live/' },
  { klucz: 'produkt-segregator', url: BAZA + '/sklep/produkt/segregator-maila/' },
  { klucz: 'dziekujemy-bez-sesji', url: BAZA + '/dziekujemy-za-zakup/' },
];

const WIDOKI = [
  { klucz: 'desktop', viewport: { width: 1440, height: 900 }, isMobile: false, hasTouch: false, deviceScaleFactor: 1 },
  { klucz: 'telefon', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
];

/* Wolne przewijanie: krok = 70 % wysokosci okna, pauza 700 ms, zeby zdazyly
   odpalic animacje wjazdu podpiete pod IntersectionObserver. Zrzut po KAZDYM
   kroku — to jest ten stan, ktory widzi czlowiek. */
async function przewinIZrzuc(p, prefiks) {
  const zrzuty = [];
  const wysokoscOkna = p.viewportSize().height;
  const krok = Math.round(wysokoscOkna * 0.7);
  let y = 0;
  let numer = 0;
  for (;;) {
    const nazwa = `${prefiks}-${String(numer).padStart(2, '0')}.png`;
    await p.screenshot({ path: path.join(KAT, nazwa) });
    zrzuty.push(nazwa);
    const dane = await p.evaluate(([y, krok]) => {
      window.scrollTo({ top: y + krok, behavior: 'smooth' });
      return { wysokoscDok: document.documentElement.scrollHeight, y: window.scrollY };
    }, [y, krok]);
    await p.waitForTimeout(700);
    const noweY = await p.evaluate(() => window.scrollY);
    if (noweY <= y + 2 || numer > 24) { break; }
    y = noweY;
    numer += 1;
  }
  return zrzuty;
}

/* Przepelnienie w poziomie: mierzone na ELEMENCIE, nie tylko na dokumencie —
   dokument potrafi miec overflow-x:hidden i schowac wade, ktora i tak psuje uklad. */
async function pomierzUklad(p) {
  return p.evaluate(() => {
    const szerokoscOkna = window.innerWidth;
    const wystajace = [];
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) { continue; }
      if (r.right > szerokoscOkna + 1 || r.left < -1) {
        const st = getComputedStyle(el);
        if (st.position === 'fixed' && r.width <= 1) { continue; }
        wystajace.push({
          tag: el.tagName.toLowerCase(),
          klasa: (el.className && el.className.toString().slice(0, 80)) || '',
          lewa: Math.round(r.left),
          prawa: Math.round(r.right),
          tekst: (el.textContent || '').trim().slice(0, 60),
        });
      }
    }
    /* Tylko NAJPLYTSZE wystajace elementy — dziecko wystaje razem z rodzicem,
       wiec lista bez tego filtra jest lawina duplikatow. */
    return {
      szerokoscOkna,
      szerokoscDokumentu: document.documentElement.scrollWidth,
      przewijaSieWPoziomie: document.documentElement.scrollWidth > szerokoscOkna + 1,
      wystajace: wystajace.slice(0, 12),
      liczbaWystajacych: wystajace.length,
      tytul: document.title,
    };
  });
}

(async () => {
  fs.mkdirSync(KAT, { recursive: true });
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const wynik = { baza: BAZA, kiedy: new Date().toISOString(), strony: [] };

  for (const widok of WIDOKI) {
    const ctx = await b.newContext({
      viewport: widok.viewport,
      isMobile: widok.isMobile,
      hasTouch: widok.hasTouch,
      deviceScaleFactor: widok.deviceScaleFactor,
      locale: 'pl-PL',
    });
    for (const strona of STRONY) {
      const p = await ctx.newPage();
      const bledyKonsoli = [];
      const wyjatki = [];
      const zleZadania = [];
      p.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warning') { bledyKonsoli.push(m.type() + ': ' + m.text().slice(0, 200)); } });
      p.on('pageerror', (e) => wyjatki.push(String(e).slice(0, 200)));
      p.on('requestfailed', (r) => zleZadania.push('FAILED ' + r.url().slice(0, 120) + ' ' + (r.failure() ? r.failure().errorText : '')));
      p.on('response', (r) => { if (r.status() >= 400) { zleZadania.push(r.status() + ' ' + r.url().slice(0, 120)); } });

      const odp = await p.goto(strona.url, { waitUntil: 'load', timeout: 45000 });
      await p.waitForTimeout(1200);
      const prefiks = `${strona.klucz}-${widok.klucz}`;
      const uklad = await pomierzUklad(p);
      const zrzuty = await przewinIZrzuc(p, prefiks);

      wynik.strony.push({
        strona: strona.klucz,
        widok: widok.klucz,
        url: strona.url,
        status: odp ? odp.status() : null,
        uklad,
        bledyKonsoli,
        wyjatki,
        zleZadania,
        zrzuty,
      });
      console.log(`${prefiks}  HTTP=${odp ? odp.status() : '?'}  zrzutów=${zrzuty.length}  ` +
        `poziom=${uklad.przewijaSieWPoziomie ? 'PRZEWIJA' : 'ok'}  wystające=${uklad.liczbaWystajacych}  ` +
        `konsola=${bledyKonsoli.length}  wyjątki=${wyjatki.length}  złeŻądania=${zleZadania.length}`);
      await p.close();
    }
    await ctx.close();
  }

  await b.close();
  fs.writeFileSync(path.join(__dirname, 'A4b-pomiar.json'), JSON.stringify(wynik, null, 2));
  console.log('ZAPISANE: audyt/plamy/A4b-pomiar.json');
})().catch((e) => { console.error('PRZELOT PADŁ:', e); process.exit(1); });
