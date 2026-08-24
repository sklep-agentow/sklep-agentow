/* Konfigurowalny wyglad widgetu — od pola w panelu do PIKSELA na stronie.
 *
 * Trzy rzeczy, ktorych nie sprawdzi test jednostkowy:
 *  1) czy liczba kontrastu policzona w PRZEGLADARCE (panel-wyglad.js) zgadza sie
 *     z ta policzona przez PHP po zapisie — bo wzor WCAG istnieje w dwoch
 *     miejscach i moze sie po cichu rozjechac (klasa bledu z recenzji PR #65),
 *  2) czy wybrany kolor naprawde dociera na strone jako tlo babelka,
 *  3) czy widget faktycznie przenosi sie do lewego rogu.
 *
 * Zapisuje tez zrzuty widgetu w dwoch zestawach kolorow (dowod do PR).
 *
 * Wymaga playwright-core i WP_ADMIN_USER/WP_ADMIN_PASS w env.
 * Uzycie: node testy/wyglad-panel.js [katalog-zrzutow] [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { zaloguj } = require('./lib/logowanie');
const fs = require('fs');
const GDZIE = process.argv[2] || 'zrzuty-wygladu';
const BAZA = process.argv[3] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

/* "rgb(18, 48, 107)" -> "#12306b"; przegladarka zawsze zwraca postac rgb(). */
function naHex(rgb) {
  const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(String(rgb));
  if (!m) { return String(rgb); }
  return '#' + [1, 2, 3].map((i) => Number(m[i]).toString(16).padStart(2, '0')).join('');
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  fs.mkdirSync(GDZIE, { recursive: true });

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)
  await zaloguj(p, BAZA, user, pass);

  const USTAWIENIA = BAZA + '/wp-admin/admin.php?page=chatl-ustawienia';

  /* Ustawia kolory w formularzu; zwraca liczby kontrastu policzone W PANELU. */
  async function ustaw(glowny, naglowek, pozycja) {
    await p.goto(USTAWIENIA, { waitUntil: 'load' });
    await p.fill('#chatl-kolor-glowny', glowny);
    await p.fill('#chatl-kolor-naglowka', naglowek);
    await p.check('#chatl-tekst-auto');
    await p.check('input[name=pozycja][value="' + pozycja + '"]');
    await p.dispatchEvent('#chatl-kolor-glowny', 'input');
    await p.waitForTimeout(150);
    return p.$$eval('#chatl-kontrast [data-chatl-para]', (w) => w.map((e) => ({
      para: e.getAttribute('data-chatl-para'),
      wartosc: e.querySelector('.chatl-kontrast-wartosc').textContent.trim(),
      ocena: e.querySelector('.chatl-kontrast-ocena').textContent.trim(),
    })));
  }
  async function zapisz() {
    await p.click('#submit');
    await p.waitForLoadState('load');
    return p.$$eval('#chatl-kontrast [data-chatl-para]', (w) => w.map((e) => ({
      para: e.getAttribute('data-chatl-para'),
      wartosc: e.querySelector('.chatl-kontrast-wartosc').textContent.trim(),
      ocena: e.querySelector('.chatl-kontrast-ocena').textContent.trim(),
    })));
  }
  /* Otwiera widget na stronie i zwraca zmierzone kolory oraz pozycje. */
  async function zmierzWidget(plikZrzutu) {
    await p.goto(BAZA + '/', { waitUntil: 'load' });
    await p.click('.chatl-babelek');
    await p.waitForTimeout(300);
    const dane = await p.evaluate(() => {
      const st = (sel, wl) => getComputedStyle(document.querySelector(sel))[wl];
      const korzen = document.querySelector('.chatl-korzen').getBoundingClientRect();
      return {
        babelekTlo: st('.chatl-babelek', 'backgroundColor'),
        babelekTekst: st('.chatl-babelek', 'color'),
        naglowekTlo: st('.chatl-naglowek', 'backgroundColor'),
        naglowekTekst: st('.chatl-naglowek', 'color'),
        lewa: Math.round(korzen.left),
        prawa: Math.round(window.innerWidth - korzen.right),
        /* Ile stylow wtyczka doklada do KODU STRONY (0 = domyslny wyglad). */
        doklejka: [...document.querySelectorAll('style')]
          .map((e) => e.textContent).filter((t) => t.indexOf('--chatl-') !== -1).join('').length,
      };
    });
    await p.screenshot({ path: GDZIE + '/' + plikZrzutu, clip: { x: 0, y: 200, width: 700, height: 640 } });
    return dane;
  }

  console.log('[1] zestaw WLASNY: granat + ciemny granat, widget po LEWEJ');
  const przed = await ustaw('#12306b', '#0b1b3a', 'lewo');
  console.log('       panel przed zapisem: ' + JSON.stringify(przed));
  const po = await zapisz();
  console.log('       serwer po zapisie:   ' + JSON.stringify(po));
  sprawdz('kontrast policzony w PANELU = kontrast policzony przez PHP',
    JSON.stringify(przed) === JSON.stringify(po),
    'jeden wzor WCAG w dwoch jezykach — rozjazd bylby tu widoczny');

  const granat = await zmierzWidget('widget-granat.png');
  console.log('       zmierzone na stronie: ' + JSON.stringify(granat));
  sprawdz('tlo babelka = wybrany kolor glowny', naHex(granat.babelekTlo) === '#12306b', naHex(granat.babelekTlo));
  sprawdz('tlo naglowka = wybrany kolor naglowka', naHex(granat.naglowekTlo) === '#0b1b3a', naHex(granat.naglowekTlo));
  sprawdz('tekst na ciemnym tle dobrany na JASNY', naHex(granat.babelekTekst) === '#f5f5f5', naHex(granat.babelekTekst));
  sprawdz('widget stoi przy LEWEJ krawedzi', granat.lewa < granat.prawa, 'lewa=' + granat.lewa + ' prawa=' + granat.prawa);
  sprawdz('zmieniony wyglad doklada do strony male style', granat.doklejka > 0 && granat.doklejka < 300,
    'bajtow doklejki: ' + granat.doklejka);

  console.log('\n[2] powrot do zestawu DOMYSLNEGO: limonka + tusz, widget po PRAWEJ');
  await ustaw('#bfff38', '#08090b', 'prawo');
  await zapisz();
  const domyslny = await zmierzWidget('widget-domyslny.png');
  console.log('       zmierzone na stronie: ' + JSON.stringify(domyslny));
  sprawdz('tlo babelka wrocilo do limonki', naHex(domyslny.babelekTlo) === '#bfff38', naHex(domyslny.babelekTlo));
  sprawdz('tlo naglowka wrocilo do tuszu', naHex(domyslny.naglowekTlo) === '#08090b', naHex(domyslny.naglowekTlo));
  sprawdz('tekst na limonce dobrany na CIEMNY', naHex(domyslny.babelekTekst) === '#08090b', naHex(domyslny.babelekTekst));
  sprawdz('tekst na naglowku dobrany na JASNY', naHex(domyslny.naglowekTekst) === '#f5f5f5', naHex(domyslny.naglowekTekst));
  sprawdz('widget wrocil do PRAWEJ krawedzi', domyslny.prawa < domyslny.lewa, 'lewa=' + domyslny.lewa + ' prawa=' + domyslny.prawa);
  sprawdz('DOMYSLNY wyglad nie doklada do strony ANI JEDNEGO bajtu', domyslny.doklejka === 0,
    'bajtow doklejki: ' + domyslny.doklejka);

  console.log('\n[3] ostrzezenie o kontrascie przy zlym zestawie');
  await p.goto(USTAWIENIA, { waitUntil: 'load' });
  await p.uncheck('#chatl-tekst-auto');
  await p.fill('#chatl-kolor-tekstu', '#ffffff');
  await p.dispatchEvent('#chatl-kolor-tekstu', 'input');
  await p.waitForTimeout(150);
  const zle = await p.$eval('#chatl-kontrast [data-chatl-para="glowny"]', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       ' + zle);
  sprawdz('biel na limonce oznaczona jako PONIZEJ progu WCAG', /PONI/.test(zle));
  await p.check('#chatl-tekst-auto');
  await p.dispatchEvent('#chatl-tekst-auto', 'change');
  await p.waitForTimeout(150);
  const dobre = await p.$eval('#chatl-kontrast [data-chatl-para="glowny"]', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       ' + dobre);
  sprawdz('po wlaczeniu automatu zestaw spelnia WCAG AA', /spełnia WCAG AA/.test(dobre));
  await p.click('#submit');
  await p.waitForLoadState('load');

  await b.close();
  console.log('\nzrzuty: ' + GDZIE + '/widget-granat.png, ' + GDZIE + '/widget-domyslny.png');
  console.log(bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY');
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
