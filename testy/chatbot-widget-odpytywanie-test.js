/* Okno czatu w PRAWDZIWEJ przeglądarce — to widzi klient klienta (paczka C4).
 *
 * PO CO (audyt A4 + wcielenie kupującego):
 *  - A4-F1/F2: `odpytaj` i `zatrzymajOdpytywanie` żyją w domknięciu `zbudujOkno()`,
 *    a bąbelek wołał je spoza tego zasięgu → `ReferenceError: odpytaj is not defined`
 *    przy KAŻDYM otwarciu okna. Skutek dla gościa: operator przejmuje rozmowę,
 *    a gość nie dostaje ani komunikatu o konsultancie, ani jego wiadomości, dopóki
 *    sam czegoś nie napisze (zmierzone: 0 odpytań REST przez 20 s).
 *  - A4-F4: bąbelek nie mówił czytnikowi ekranu, czy czat jest otwarty
 *    (`aria-expanded`). Etykieta „Otwórz czat" ZOSTAJE — to po niej trafiają w
 *    bąbelek cztery testy i czytniki.
 *  - WK-1: formularz kontaktowy i jego krzyżyk — kupujący zgłosił, że formularz
 *    przykrywa rozmowę, a krzyżyk zamyka cały czat zamiast formularza.
 *
 * ⚠️ `audyt/boty/testy-audytu/a4-*.js` to SONDY (EXIT zawsze 0) — odtwarzają, nie
 * pilnują. Ten plik jest strażnikiem: zero `pageerror`, gość widzi konsultanta bez
 * drugiego pytania, zamknięcie bąbelkiem i Escape bez błędu JS.
 *
 * Sprząta po sobie: własna sesja i rozmowa kasowane z bazy na końcu.
 *
 * Kalibracja (opis PR C4): na kodzie sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Użycie: flock /root/praca/zlecenia/wymiana/stos.lock node testy/chatbot-widget-odpytywanie-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');

const BAZA = process.argv[2] || 'http://localhost:8890';
let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; if (szczegol) { console.log('       ' + szczegol); } }
}
function wp(kod) {
  return execFileSync('docker', ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', '--path=/var/www/html', 'eval', kod],
    { encoding: 'utf8' }).trim();
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium(), args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await b.newContext({ viewport: { width: 1200, height: 900 } });
  const p = await ctx.newPage();
  const bledyJs = [];
  let odpytan = 0;
  p.on('pageerror', (e) => bledyJs.push(e.message));
  p.on('request', (r) => { if (r.url().includes('/wp-json/chatl/v1/rozmowa')) { odpytan++; } });

  await p.goto(BAZA + '/', { waitUntil: 'load' });
  const babelek = await p.$('button[aria-label="Otwórz czat"]');
  sprawdz('bąbelek czatu jest na stronie (etykieta „Otwórz czat" — selektor 4 testów)', babelek !== null);
  if (!babelek) { await b.close(); process.exit(1); }

  console.log('\n=== A. A4-F4: bąbelek mówi czytnikowi, czy czat jest otwarty ===');
  sprawdz('przed otwarciem aria-expanded="false"', 'false' === await babelek.getAttribute('aria-expanded'),
    'jest: ' + await babelek.getAttribute('aria-expanded'));

  console.log('\n=== B. A4-F1/F2: otwarcie okna NIE wywala skryptu i URUCHAMIA odpytywanie ===');
  await babelek.click();
  await p.waitForSelector('.chatl-okno.chatl-otwarty', { timeout: 5000 });
  sprawdz('po otwarciu aria-expanded="true"', 'true' === await babelek.getAttribute('aria-expanded'));
  sprawdz('etykieta bąbelka NIE zmieniła się (selektor testów zostaje)', 'Otwórz czat' === await babelek.getAttribute('aria-label'));
  await p.waitForTimeout(2500);
  sprawdz('zero błędów JS po otwarciu okna', 0 === bledyJs.length, bledyJs.join(' | '));
  sprawdz('samo otwarcie okna URUCHOMIŁO odpytywanie (bez pisania)', odpytan > 0, 'odpytań: ' + odpytan);

  const sesja = await p.evaluate(() => window.sessionStorage.getItem('chatl_sesja'));
  sprawdz('gość ma sesję wydaną przez serwer', !!sesja && sesja.length === 32, String(sesja));

  console.log('\n=== C. SEDNO: operator przejmuje, gość widzi to BEZ drugiego pytania ===');
  const stan = wp(`require_once WP_PLUGIN_DIR."/chatbot-live/includes/operator.php"; wp_set_current_user(1);`
    + ` $ok = chatl_przejmij("${sesja}", 1);`
    + ` chatl_operator_wiadomosc("${sesja}", "Tu Anna z obslugi — juz sprawdzam.");`
    + ` global $wpdb; echo ($ok?"true":"false")."|".$wpdb->get_var($wpdb->prepare("SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja=%s","${sesja}"));`);
  sprawdz('przejęcie po stronie serwera doszło do skutku', 'true|czlowiek' === stan, stan);

  const odpytanPrzed = odpytan;
  /* Czekamy na WARUNEK, nie na sztywny czas: gdy rozmowę prowadzi bot, serwer
     podaje interwał 15 s, więc pierwsze odpytanie po przejęciu przychodzi dopiero
     po nim (potem interwał schodzi do 5 s). Sztywne 12 s dawało wynik zależny od
     tego, w którym momencie okna trafiło przejęcie — czyli test migotałby. */
  await p.waitForFunction(
    () => [...document.querySelectorAll('.chatl-dymek')].some((x) => /konsultant/i.test(x.textContent)),
    { timeout: 30000 }
  ).catch(() => {});
  const widok = await p.evaluate(() => [...document.querySelectorAll('.chatl-dymek')].map((x) => x.textContent).join(' | '));
  sprawdz('gość dostał komunikat o konsultancie, NIC nie pisząc', /konsultant/i.test(widok), widok.slice(-200));
  sprawdz('i widzi wiadomość operatora', widok.includes('Tu Anna'), widok.slice(-200));
  sprawdz('odpytywanie chodzi samo (kolejne żądania REST)', odpytan > odpytanPrzed, `${odpytanPrzed} → ${odpytan}`);
  sprawdz('nadal zero błędów JS', 0 === bledyJs.length, bledyJs.join(' | '));

  console.log('\n=== D. WK-1: formularz kontaktu nie przykrywa rozmowy, a jego krzyżyk zamyka TYLKO formularz ===');
  /* Formularz kontaktu wchodzi TYLKO wtedy, gdy odpowiada bot i nie zna odpowiedzi.
     Rozmowę prowadzi teraz człowiek (sekcja C), więc najpierw oddajemy ją botowi —
     inaczej mierzylibyśmy brak formularza tam, gdzie go z definicji nie ma. */
  wp(`require_once WP_PLUGIN_DIR."/chatbot-live/includes/przejecie.php";`
    + ` chatl_oddaj_botowi("${sesja}", "Konsultant zakończył rozmowę — dalej pomaga bot.");`);
  await p.waitForTimeout(6000);
  await p.fill('.chatl-pole-pytania', 'pytanie o coś, czego bot nie zna: xyzzy plugh');
  await p.click('button[aria-label="Wyślij pytanie"]');
  await p.waitForSelector('.chatl-formularz.chatl-otwarty', { timeout: 15000 }).catch(() => {});
  const formOtwarty = await p.$('.chatl-formularz.chatl-otwarty');
  sprawdz('po nieznanym pytaniu pokazuje się formularz kontaktu', formOtwarty !== null);

  if (formOtwarty) {
    const kolizja = await p.evaluate(() => {
      const f = document.querySelector('.chatl-formularz');
      const l = document.querySelector('.chatl-wiadomosci');
      const pierwszy = document.querySelector('.chatl-wiadomosci .chatl-wiadomosc');
      if (!f || !l || !pierwszy) { return { blad: 'brak elementów' }; }
      const rf = f.getBoundingClientRect();
      const rl = l.getBoundingClientRect();
      const rp = pierwszy.getBoundingClientRect();
      return {
        nachodzi_na_liste: rf.top < rl.bottom - 1,
        pierwsza_wiadomosc_zakryta: rp.bottom > rf.top && rp.top < rf.bottom,
        lista_przewijalna: l.scrollHeight > l.clientHeight + 1,
      };
    });
    sprawdz('formularz stoi POD listą wiadomości, nie na niej', false === kolizja.nachodzi_na_liste, JSON.stringify(kolizja));
    sprawdz('powitanie/rozmowa nie jest przykryta formularzem', false === kolizja.pierwsza_wiadomosc_zakryta, JSON.stringify(kolizja));

    await p.fill('.chatl-formularz input[type="email"]', 'wk1@example.test');
    await p.click('button[aria-label="Zamknij formularz wiadomości"]');
    await p.waitForTimeout(500);
    const poZamknieciu = await p.evaluate(() => ({
      czatOtwarty: !!document.querySelector('.chatl-okno.chatl-otwarty'),
      formOtwarty: !!document.querySelector('.chatl-formularz.chatl-otwarty'),
    }));
    sprawdz('WK-1: krzyżyk formularza zamyka formularz…', false === poZamknieciu.formOtwarty, JSON.stringify(poZamknieciu));
    sprawdz('…i NIE zamyka całego czatu', true === poZamknieciu.czatOtwarty, JSON.stringify(poZamknieciu));
    sprawdz('zero błędów JS po zamknięciu formularza', 0 === bledyJs.length, bledyJs.join(' | '));

    /* Escape ma DWA kroki i to jest decyzja, nie przypadek: najpierw wychodzi
       z węższego okna (formularz), dopiero drugi raz zamyka czat. Zamrożona sonda
       a4-zamkniecie-odpytywanie.js pokazuje „okno zamkniete Escape? false" —
       to jest właśnie ten pierwszy krok, nie wada. Pilnujemy obu kroków. */
    await p.fill('.chatl-pole-pytania', 'znowu coś, czego bot nie zna: xyzzy plugh');
    await p.click('button[aria-label="Wyślij pytanie"]');
    await p.waitForSelector('.chatl-formularz.chatl-otwarty', { timeout: 15000 }).catch(() => {});
    await p.keyboard.press('Escape');
    await p.waitForTimeout(500);
    const poEscape1 = await p.evaluate(() => ({
      czatOtwarty: !!document.querySelector('.chatl-okno.chatl-otwarty'),
      formOtwarty: !!document.querySelector('.chatl-formularz.chatl-otwarty'),
    }));
    sprawdz('Escape przy otwartym formularzu zamyka NAJPIERW formularz', false === poEscape1.formOtwarty && true === poEscape1.czatOtwarty, JSON.stringify(poEscape1));
    await p.keyboard.press('Escape');
    await p.waitForTimeout(500);
    sprawdz('drugi Escape zamyka czat', null === await p.$('.chatl-okno.chatl-otwarty'));
    await babelek.click();
    await p.waitForSelector('.chatl-okno.chatl-otwarty', { timeout: 5000 });
  }

  console.log('\n=== E. Zamknięcie czatu: bąbelkiem i klawiszem Escape, bez błędu JS ===');
  await babelek.click();
  await p.waitForTimeout(500);
  sprawdz('bąbelek zamyka okno', null === await p.$('.chatl-okno.chatl-otwarty'));
  sprawdz('i ustawia aria-expanded="false"', 'false' === await babelek.getAttribute('aria-expanded'));
  const odpytanPoZamknieciu = odpytan;
  await p.waitForTimeout(8000);
  sprawdz('zamknięte okno NIE odpytuje serwera', odpytan === odpytanPoZamknieciu, `${odpytanPoZamknieciu} → ${odpytan}`);
  sprawdz('zero błędów JS po zamknięciu bąbelkiem', 0 === bledyJs.length, bledyJs.join(' | '));

  await babelek.click();
  await p.waitForSelector('.chatl-okno.chatl-otwarty', { timeout: 5000 });
  await p.keyboard.press('Escape');
  await p.waitForTimeout(500);
  sprawdz('Escape zamyka czat', null === await p.$('.chatl-okno.chatl-otwarty'));
  sprawdz('i też ustawia aria-expanded="false"', 'false' === await babelek.getAttribute('aria-expanded'));
  sprawdz('zero błędów JS w całym przebiegu', 0 === bledyJs.length, bledyJs.join(' | '));

  await b.close();
  if (sesja) {
    wp(`global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja = %s", "${sesja}" ) );`
      + ` $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", "${sesja}" ) );`);
  }
  console.log('\n' + (bledy > 0 ? 'BLEDOW: ' + bledy : 'WSZYSTKO OK'));
  process.exit(bledy > 0 ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
