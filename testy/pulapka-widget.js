/* Sprawdza w PRAWDZIWEJ przeglądarce, że pułapka na boty NIE łapie człowieka.
   To jest jedyne ryzyko tej pozycji, którego nie da się zmierzyć z PHP: fałszywy
   alarm u prawdziwego gościa oznacza kontakt, który wygląda na wysłany, a trafia
   za filtr „podejrzane". Test przechodzi ścieżkę gościa tak, jak robi to człowiek:
   klika bąbelek, pisze, wysyła — i sprawdza w bazie, że zgłoszenie jest CZYSTE.
   Użycie: node testy/pulapka-widget.js [adres] */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const MAIL = 'pulapka-widget@example.test';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; if (szczegol) { console.log('       ' + szczegol); } }
}

function wp(kod) {
  return execFileSync('docker', ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', 'eval', kod],
    { encoding: 'utf8' }).trim();
}
function sprzataj() {
  wp(`global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email = '${MAIL}'" );`);
}

(async () => {
  sprzataj();
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const ctx = await b.newContext({ viewport: { width: 1200, height: 900 } });
  const p = await ctx.newPage();
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)
  await p.goto(BAZA + '/', { waitUntil: 'load' });

  const babelek = await p.$('.chatl-babelek, button[aria-label="Otwórz czat"]');
  sprawdz('bąbelek czatu jest na stronie', babelek !== null);
  if (!babelek) { await b.close(); process.exit(1); }

  await babelek.click();
  await p.waitForSelector('.chatl-okno.chatl-otwarty', { timeout: 5000 });

  /* Pole-pułapka MUSI istnieć w drzewie (bot ma je znaleźć) i jednocześnie być
     nieosiągalne dla człowieka — to są dwa różne warunki i oba trzeba zmierzyć. */
  await p.click('.chatl-kontakt, button:has-text("Zostaw wiadomość")').catch(() => {});
  await p.waitForSelector('.chatl-formularz.chatl-otwarty, .chatl-formularz', { timeout: 5000 });

  const pole = await p.$('input[name="chatl_pole_dodatkowe"]');
  sprawdz('pole-pułapka JEST w drzewie strony (bot je znajdzie)', pole !== null);
  if (pole) {
    /* NIE uzywamy isVisible() Playwrighta: dla niego element z opacity:0 lezacy
       poza kadrem jest "widoczny", bo ma niezerowa ramke. Mierzymy wiec to, co
       naprawde decyduje o tym, czy czlowiek go zobaczy i dosiegnie. */
    const box = await pole.boundingBox();
    sprawdz('leży poza kadrem (człowiek go nie zobaczy)',
      box === null || box.x < 0, box ? `x=${box.x}` : 'brak ramki');
    const styl = await pole.evaluate((e) => {
      const s = getComputedStyle(e);
      return { opacity: s.opacity, display: s.display, tab: e.tabIndex, aria: e.getAttribute('aria-hidden') };
    });
    sprawdz('jest przezroczyste', styl.opacity === '0', 'opacity=' + styl.opacity);
    sprawdz('NIE jest chowane przez display:none (proste boty je pomijaja)',
      styl.display !== 'none', 'display=' + styl.display);
    sprawdz('nie da się do niego dojść klawiszem Tab', styl.tab === -1, 'tabindex=' + styl.tab);
    sprawdz('czytnik ekranu je pomija', styl.aria === 'true', 'aria-hidden=' + styl.aria);
  }

  /* Ścieżka człowieka: pisanie zajmuje czas, więc próg czasu nie ma prawa uderzyć. */
  await p.fill('.chatl-formularz input[type=email]', MAIL);
  await p.fill('.chatl-formularz textarea', 'Dzien dobry, prosze o wycene uslugi.');
  const zgoda = await p.$('.chatl-formularz input[type=checkbox]');
  if (zgoda) { await zgoda.check(); }
  await p.click('.chatl-formularz button[type=submit], .chatl-formularz button:has-text("Wyślij")');
  await p.waitForTimeout(2500);

  const wynik = wp(`global $wpdb; $w = $wpdb->get_row( "SELECT podejrzane FROM {$wpdb->prefix}chatl_zgloszenia WHERE email = '${MAIL}'" ); echo $w ? (int) $w->podejrzane : -1;`);
  sprawdz('zgłoszenie prawdziwego gościa DOSZŁO do bazy', wynik !== '-1', 'wynik: ' + wynik);
  sprawdz('i jest CZYSTE — pułapka nie złapała człowieka', wynik === '0', 'podejrzane=' + wynik);

  await b.close();
  sprzataj();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})();
