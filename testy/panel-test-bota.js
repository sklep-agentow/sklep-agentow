/* P1.3 — sprawdza w PRAWDZIWEJ przeglądarce, że panel bazy Q&A ma działający
   test bota: pokazuje odpowiedź ORAZ która para zadziałała (id + pytanie
   wzorcowe + trafione warianty), a przy pytaniu spoza bazy — tekst awaryjny.
   Wymaga playwright-core i poświadczeń admina w env (WP_ADMIN_USER/PASS,
   nigdy w kodzie). Użycie: node testy/panel-test-bota.js [adres] */
const { chromium } = require('playwright-core');
const BAZA = process.argv[2] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; }
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  const b = await chromium.launch({
    executablePath: '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome',
  });
  const p = await b.newPage({ viewport: { width: 1280, height: 1000 } });
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');

  const karta = '.wrap .card';
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa', { waitUntil: 'load' });
  sprawdz('na stronie bazy Q&A jest sekcja "Test bota"',
    (await p.$$eval('h2', (h) => h.map((e) => e.textContent))).some((t) => /Test bota/.test(t)));
  sprawdz('jest pole na pytanie', (await p.$('input[name=test]')) !== null);

  console.log('\n[pytanie Z bazy]');
  await p.fill('input[name=test]', 'ile kosztuje wasza usluga');
  await p.click(karta + ' input[type=submit]');
  await p.waitForLoadState('load');
  const wynik = await p.$eval(karta, (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       ' + wynik.slice(wynik.indexOf('Odpowiedz') >= 0 ? 0 : 0, 600));
  sprawdz('pokazuje odpowiedz bota', /Odpowiedź, którą dostałby gość/.test(wynik));
  sprawdz('pokazuje KTORA para zadzialala (numer + pytanie wzorcowe)', /Zadziałała para #\d+/.test(wynik));
  sprawdz('pokazuje trafione warianty', /Dopasowane warianty/.test(wynik));
  sprawdz('jest link do edycji tej pary', (await p.$(karta + ' a[href*="edytuj="]')) !== null);

  console.log('\n[pytanie SPOZA bazy]');
  await p.fill('input[name=test]', 'czy montujecie panele fotowoltaiczne na dachu stodoly');
  await p.click(karta + ' input[type=submit]');
  await p.waitForLoadState('load');
  const wynik2 = await p.$eval(karta, (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       ' + wynik2.slice(0, 400));
  sprawdz('mowi wprost, ze zadna para nie pasuje', /Żadna para nie pasuje/.test(wynik2));
  sprawdz('pokazuje tekst awaryjny', /Nie znam odpowiedzi na to pytanie/.test(wynik2));

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
