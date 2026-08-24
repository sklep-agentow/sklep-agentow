/* Test formularza kontaktu ChatBota w PRAWDZIWEJ przeglądarce (chromium
   z ms-playwright). Sprawdza to, czego curl nie sprawdzi: czy gość, który
   zmienił zdanie, ma jak wyjść z formularza — i czy formularz nie wraca
   otwarty po ponownym otwarciu czatu (P1.1), oraz czy przy polu e-mail jest
   informacja o przetwarzaniu danych i WYMAGANY checkbox zgody (P0.3).
   Wymaga playwright-core (npm install --no-save playwright-core).
   Użycie: node testy/widget-formularz.js [adres]   (domyślnie localhost:8890)
   Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie. */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const KONTENER = 'strona_wp_wordpress';

function wpEval(kod) {
  try { return execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp', 'eval', kod], { encoding: 'utf8' }).trim(); } catch (e) { return ''; }
}

/* Warunek wstepny STAWIANY PRZEZ TEST (uwaga 7 recenzji #168 — symetria
   z pulapka-test.php): link do polityki renderuje sie tylko przy ustawionym
   adres_polityki, a wspolny stos dryfuje (bywa pusty i test czerwienial bez
   winy kodu). Migawka wraca na koncu, takze przy wywrotce. */
const POLITYKA_TEST = BAZA + '/polityka-prywatnosci-test/';
let politykaPrzed = null;
function ustawPolityke() {
  politykaPrzed = wpEval('$u = (array) get_option("chatl_ustawienia"); echo isset($u["adres_polityki"]) ? $u["adres_polityki"] : "";');
  wpEval('$u = (array) get_option("chatl_ustawienia"); $u["adres_polityki"] = ' + JSON.stringify(POLITYKA_TEST) + '; update_option("chatl_ustawienia", $u); wp_cache_flush();');
}
function przywrocPolityke() {
  if (politykaPrzed === null) { return; }
  wpEval('$u = (array) get_option("chatl_ustawienia"); $u["adres_polityki"] = ' + JSON.stringify(politykaPrzed) + '; update_option("chatl_ustawienia", $u); wp_cache_flush();');
  politykaPrzed = null;
}

let bledy = 0;
function sprawdz(opis, warunek) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; }
}

(async () => {
  ustawPolityke();
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage();
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)
  await p.goto(BAZA + '/', { waitUntil: 'load' });

  const otwarty = (sel) => p.$eval(sel, (e) => e.classList.contains('chatl-otwarty'));
  const jest = async (sel) => (await p.$(sel)) !== null;
  /* Brak elementu = krok nie przeszedl, a nie wywrocony test — porownujemy
     przebieg PRZED i PO naprawie, wiec musi dobiec do konca w obu. */
  const pole = async (sel, fn, gdyBrak) => ((await p.$(sel)) ? p.$eval(sel, fn) : gdyBrak);
  const klik = async (sel) => { if (await p.$(sel)) { await p.click(sel); } };
  /* Stan okna doprowadzamy jawnie, a nie przelacznikiem — wersja PRZED
     naprawa reaguje inaczej na Escape i slepy toggle rozjechalby scenariusz. */
  const upewnijOknoOtwarte = async () => {
    if (!(await otwarty('.chatl-okno'))) { await p.click('.chatl-babelek'); }
  };

  await p.click('.chatl-babelek');
  sprawdz('okno czatu otwarte po kliknieciu babelka', await otwarty('.chatl-okno'));

  console.log('\n[P0.3] informacja o przetwarzaniu danych i zgoda');
  await p.click('.chatl-przycisk-kontakt');
  sprawdz('formularz kontaktu otwarty', await otwarty('.chatl-formularz'));
  sprawdz('jest informacja o przetwarzaniu danych', await jest('.chatl-info-prywatnosc'));
  const info = await pole('.chatl-info-prywatnosc', (e) => e.textContent, '');
  console.log('       tresc: ' + info.trim());
  sprawdz('informacja mowi o retencji 90 dni', /90 dni/.test(info));
  sprawdz('jest link do polityki prywatnosci', await jest('.chatl-info-prywatnosc a'));
  sprawdz('link prowadzi pod USTAWIONY adres (nie zaszyty w kodzie)',
    (await pole('.chatl-info-prywatnosc a', (e) => e.href, '')).indexOf('polityka-prywatnosci-test') !== -1);
  sprawdz('jest checkbox zgody', await jest('.chatl-zgoda input[type=checkbox]'));
  sprawdz('checkbox zgody jest WYMAGANY', await pole('.chatl-zgoda input', (e) => e.required, false));

  console.log('\n[P1.1] wyjscie z formularza');
  sprawdz('jest przycisk zamkniecia formularza', await jest('.chatl-form-zamknij'));
  await klik('.chatl-form-zamknij');
  sprawdz('przycisk x zamyka formularz', !(await otwarty('.chatl-formularz')));

  await klik('.chatl-przycisk-kontakt');
  await p.fill('.chatl-formularz input[type=email]', 'gosc@przyklad.pl');
  if (await p.$('.chatl-zgoda input')) { await p.check('.chatl-zgoda input'); }
  await p.keyboard.press('Escape');
  sprawdz('Escape zamyka formularz', !(await otwarty('.chatl-formularz')));
  sprawdz('okno czatu po Escape nadal otwarte', await otwarty('.chatl-okno'));
  sprawdz('pole e-mail wyczyszczone przy zamknieciu',
    '' === (await pole('.chatl-formularz input[type=email]', (e) => e.value, 'BRAK')));
  sprawdz('zgoda odznaczona przy zamknieciu',
    false === (await pole('.chatl-zgoda input', (e) => e.checked, 'BRAK')));

  await p.keyboard.press('Escape');
  sprawdz('drugi Escape zamyka okno czatu', !(await otwarty('.chatl-okno')));

  console.log('\n[P1.1] formularz NIE wraca otwarty po ponownym otwarciu czatu');
  await upewnijOknoOtwarte();
  await klik('.chatl-przycisk-kontakt');
  sprawdz('formularz otwarty', await otwarty('.chatl-formularz'));
  await p.click('.chatl-zamknij');
  sprawdz('naglowkowy x zamyka okno czatu', !(await otwarty('.chatl-okno')));
  await p.click('.chatl-babelek');
  sprawdz('po ponownym otwarciu czatu formularz jest ZAMKNIETY', !(await otwarty('.chatl-formularz')));

  await b.close();
  przywrocPolityke();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { przywrocPolityke(); console.error(e); process.exit(1); });
