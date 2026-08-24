/* Zrzuty ekranów do INSTRUKCJI obu botów — maszynowo, przez prawdziwą przeglądarkę.
   NARZĘDZIE (nie test): linia bazowa pomija je jawnie, jak zrzuty-panelu.js.

   Czemu skryptem, a nie ręcznie: zdjęcia starzeją się przy każdej zmianie UI,
   a ręczne kadry rozjeżdżają się między sobą. Wzorzec i sześć pułapek z MP:
   kadr DO ELEMENTU (#wpbody-content), nie pełna strona (stopki/wizytówki);
   przeglądarka po polsku (pola dat!); czekanie na DOWÓD ekranu (selektor),
   nie na "network idle"; front: okno czatu fotografowane jako element.

   Użycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... node testy/zrzuty-instrukcje.js
   Wyjście: wtyczki/chatbot-live/zrzuty/*.png + wtyczki/segregator-maila/zrzuty/*.png */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { zaloguj } = require('./lib/logowanie');
const fs = require('fs');
const BAZA = process.argv[2] || 'http://localhost:8890';

const ADMIN = [
  /* [wtyczka, nazwa pliku, slug strony, selektor-DOWÓD ekranu] */
  /* [wtyczka, plik, slug, DOWOD ekranu, KADR (opcjonalnie — domyslnie caly #wpbody-content)] */
  ['chatbot-live', 'ustawienia', 'chatl-ustawienia', '#wpbody-content form', '#wpbody-content form'],
  ['chatbot-live', 'baza-qa', 'chatl-qa', '#wpbody-content table, #wpbody-content form'],
  ['chatbot-live', 'zgloszenia', 'chatl-zgloszenia', '#wpbody-content table'],
  /* Statystyki: kadr GORNY (kafle + ruch dzienny) — dolna lista luk niesie
     tresci pytan z materialu testowego stosu, a stopka WP wizytowke silnika. */
  ['chatbot-live', 'statystyki', 'chatl-statystyki', '#wpbody-content', '#wpbody-content', 1020],
  ['segregator-maila', 'skrzynka-imap', 'segm-skrzynka', '#wpbody-content form', '#wpbody-content form'],
  ['segregator-maila', 'reguly', 'segm-reguly', '#wpbody-content table, #wpbody-content form'],
  ['segregator-maila', 'kategorie', 'segm-kategorie', '#wpbody-content'],
  ['segregator-maila', 'probka-demo', 'segm-probka', '#wpbody-content'],
  ['segregator-maila', 'przebiegi', 'segm-przebiegi', '#wpbody-content'],
  ['segregator-maila', 'ustawienia', 'segm-ustawienia', '#wpbody-content form', '#wpbody-content form'],
];

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  for (const w of ['chatbot-live', 'segregator-maila']) {
    fs.mkdirSync('wtyczki/' + w + '/zrzuty', { recursive: true });
  }
  const b = await chromium.launch({
    executablePath: wymagajChromium(),
    args: ['--lang=pl-PL,pl'],
    env: { ...process.env, LANG: 'pl_PL.UTF-8' },
  });
  const p = await b.newPage({ viewport: { width: 1280, height: 1000 } });
  p.setDefaultTimeout(60000);
  let zrobione = 0, bledy = 0;

  await zaloguj(p, BAZA, user, pass);

  for (const [wtyczka, nazwa, slug, dowod, kadr] of ADMIN) {
    const cel = 'wtyczki/' + wtyczka + '/zrzuty/' + nazwa + '.png';
    try {
      await p.goto(BAZA + '/wp-admin/admin.php?page=' + slug, { waitUntil: 'load' });
      await p.waitForSelector(dowod.split(',')[0].trim());
      /* Paski aktualizacji WP to srodowisko testowe, nie produkt — precz z kadru. */
      await p.addStyleTag({ content: '.update-nag{display:none!important} #wpfooter{display:none!important}' });
      const el = p.locator(kadr || '#wpbody-content').first();
      const maxH = ADMIN.find((r) => r[1] === nazwa && r[0] === wtyczka)[5];
      if (maxH) {
        const box = await el.boundingBox();
        await p.screenshot({ path: cel, clip: { x: box.x, y: box.y, width: box.width, height: Math.min(box.height, maxH) } });
      } else {
        await el.screenshot({ path: cel });
      }
      console.log('  OK   ' + cel);
      zrobione++;
    } catch (e) {
      console.log('  BLAD ' + cel + ' — ' + e.message.split('\n')[0]);
      bledy++;
    }
  }

  /* Front: widget ChatBota oczami gościa — osobna, NIEZALOGOWANA karta
     (admin widzi pasek WP, gość nie — zdjęcie ma pokazywać prawdę gościa). */
  const g = await b.newPage({ viewport: { width: 1280, height: 900 } });
  g.setDefaultTimeout(60000);
  try {
    await g.goto(BAZA + '/', { waitUntil: 'load' });
    await g.waitForSelector('.chatl-babelek');
    await g.locator('.chatl-babelek').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-babelek.png' });
    await g.click('.chatl-babelek');
    await g.waitForSelector('.chatl-okno.chatl-otwarty');
    await g.waitForTimeout(400); /* koniec animacji otwarcia */
    await g.locator('.chatl-okno').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-okno.png' });
    await g.click('.chatl-przycisk-kontakt');
    await g.waitForSelector('.chatl-formularz.chatl-otwarty');
    await g.waitForTimeout(300);
    await g.locator('.chatl-okno').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-formularz.png' });
    console.log('  OK   widget: babelek, okno, formularz');
    zrobione += 3;
  } catch (e) {
    console.log('  BLAD widget — ' + e.message.split('\n')[0]);
    bledy++;
  }

  await b.close();
  console.log('\nZROBIONE: ' + zrobione + ', BLEDOW: ' + bledy);
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
