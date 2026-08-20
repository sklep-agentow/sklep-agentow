/* P2.1 — sprawdza eksport CSV zgłoszeń i historii rozmów: dostęp tylko dla
   zalogowanego admina z noncem, poprawne nagłówki pliku, BOM UTF-8 (Excel PL)
   i neutralizacja formuł arkuszy (komórka od =,+,-,@ dostaje apostrof).
   Wymaga playwright-core + WP_ADMIN_USER/WP_ADMIN_PASS w env.
   Użycie: node testy/eksport-csv.js [adres] */
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
  const p = await b.newPage();

  console.log('[dostep bez zalogowania]');
  const obcy = await p.request.get(BAZA + '/wp-admin/admin-post.php?action=chatl_eksport_zgloszen');
  sprawdz('niezalogowany NIE dostaje pliku (HTTP ' + obcy.status() + ')', obcy.status() !== 200);

  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');

  console.log('\n[zalogowany admin, ale BEZ nonce]');
  const bezNonce = await p.request.get(BAZA + '/wp-admin/admin-post.php?action=chatl_eksport_zgloszen');
  /* Sprawdzamy SKUTEK, nie tresc komunikatu (zalezy od jezyka WordPressa):
     ma nie byc pliku CSV. */
  sprawdz('bez nonce brak HTTP 200 (jest ' + bezNonce.status() + ')', bezNonce.status() !== 200);
  sprawdz('bez nonce serwer NIE odsyla pliku CSV',
    !/text\/csv/.test(bezNonce.headers()['content-type'] || '')
    && !(bezNonce.headers()['content-disposition'] || '').includes('attachment'));

  console.log('\n[przyciski w panelu]');
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-zgloszenia', { waitUntil: 'load' });
  const linkZgl = await p.getAttribute('a[href*="chatl_eksport_zgloszen"]', 'href');
  sprawdz('na liscie zgloszen jest przycisk Pobierz CSV', !!linkZgl);
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-historia', { waitUntil: 'load' });
  const linkRoz = await p.getAttribute('a[href*="chatl_eksport_rozmow"]', 'href');
  sprawdz('na historii rozmow jest przycisk Pobierz CSV', !!linkRoz);

  console.log('\n[plik zgloszen]');
  const plik = await p.request.get(linkZgl);
  sprawdz('HTTP 200', plik.status() === 200);
  sprawdz('naglowek Content-Type text/csv; charset=utf-8',
    /text\/csv; charset=utf-8/.test(plik.headers()['content-type'] || ''));
  sprawdz('naglowek Content-Disposition: attachment z nazwa pliku',
    /attachment; filename=chatbot-zgloszenia-\d{4}-\d{2}-\d{2}\.csv/.test(plik.headers()['content-disposition'] || ''));
  const buf = await plik.body();
  sprawdz('plik zaczyna sie od BOM UTF-8 (Excel PL czyta polskie znaki)',
    buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF);
  const csv = buf.toString('utf8').replace(/^﻿/, '');
  console.log('       naglowek: ' + csv.split('\n')[0].trim());
  const wiersz = csv.split('\n').find((l) => /formula@przyklad\.pl/.test(l)) || '';
  console.log('       wiersz z formula: ' + wiersz.trim());
  sprawdz('komorka z formula ma apostrof neutralizujacy', /"'=HYPERLINK/.test(wiersz));
  sprawdz('w pliku NIE ma niezneutralizowanej formuly', !/(^|,)"?=HYPERLINK/.test(wiersz));

  console.log('\n[plik historii rozmow]');
  const plik2 = await p.request.get(linkRoz);
  const csv2 = (await plik2.body()).toString('utf8').replace(/^﻿/, '');
  console.log('       naglowek: ' + csv2.split('\n')[0].trim());
  sprawdz('HTTP 200', plik2.status() === 200);
  sprawdz('kolumny sesja/data/kto/silnik/tresc', /^sesja,data,kto,silnik,tresc/.test(csv2));
  sprawdz('sa wiersze rozmow', csv2.trim().split('\n').length > 1);
  console.log('       przykladowy wiersz: ' + (csv2.split('\n')[1] || '').trim().slice(0, 140));

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
