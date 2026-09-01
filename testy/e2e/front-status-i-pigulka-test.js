/* Dwa znaleziska z frontu, mierzone w PRAWDZIWEJ przegladarce na zywym stosie.
 *
 * ZNALEZISKO 1 — strona „Dziekujemy" nie zna statusow `zwrocone` i `sporne`.
 *   `assets/dziekujemy.js` mapuje status na zdanie przez `opisy[s] || opisy.oczekuje`.
 *   Tablica `opisy` nie ma tych dwoch kluczy, a endpoint `sklepag/v1/status` oddaje
 *   kolumne `status` wprost — wiec kupujacy PO ZWROCIE PIENIEDZY czyta „Przetwarzamy
 *   Twoja platnosc…". Do tego strona odpytuje dalej (warunek stopu zna tylko
 *   `mail_wyslany` i `mail_blad`), wiec zdanie nie ustapi przez ~2 minuty.
 *
 * ZNALEZISKO 2 — pigulka „Kup teraz" odslania sie DOKLADNIE w chwili platnosci.
 *   `front-ruch.js` pokazuje pigulke, gdy `!heroWidoczne && !formularzWidoczny`,
 *   a obserwuje `#sklepag-kup-formularz`. `platnosc.js` przy przejsciu do platnosci
 *   robi `form.hidden = true` — obserwator melduje „formularz niewidoczny", wiec
 *   pigulka wjezdza kupujacemu na panel karty i zaprasza go do kupienia jeszcze raz.
 *
 * ⛔ Znalezisko 2 odtwarzamy TA SAMA mutacja DOM, ktora robi platnosc.js (`form.hidden
 *    = true`), a nie pelnym obiegiem Stripe: obieg wymaga kluczy i sieci, a mierzona
 *    przyczyna siedzi w obserwatorze, nie w Stripe. Test nazywa to wprost zamiast
 *    udawac, ze przeszedl platnosc.
 *
 * Uzycie: node testy/e2e/front-status-i-pigulka-test.js [adres] [slug]
 * Kody: 0 = brak wad · 1 = wada · 2 = blad przyrzadu
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const { execFileSync } = require('child_process');

const BAZA = process.argv[2] || 'http://localhost:8890';
const SLUG = process.argv[3] || 'chatbot-live';

const wpEval = (kod) => execFileSync(
  'docker',
  ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', '--path=/var/www/html', 'eval', kod],
  { encoding: 'utf8' }
).trim();

let bledy = 0;
const sprawdz = (opis, ok, szczegol) => {
  console.log((ok ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!ok) { bledy++; }
};

/* Zamowienie testowe o zadanym statusie. Prefiks `cs_test_frontprob_` — wlasny, kasowany na koncu. */
function zamowienie(znacznik, status) {
  const sid = 'cs_test_frontprob_' + znacznik;
  wpEval(`global $wpdb; $t=$wpdb->prefix."sklepag_zamowienia"; $n=current_time("mysql");
    $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE stripe_session_id=%s", "${sid}"));
    $wpdb->insert($t, array("stripe_session_id"=>"${sid}","produkt_id"=>0,"produkt_nazwa_kupiona"=>"Proba frontu",
      "email_kupujacego"=>"front-proba-${znacznik}@example.test","kwota_grosze"=>4900,"status"=>"${status}",
      "utworzono"=>$n,"zmieniono"=>$n));
    echo $wpdb->insert_id;`);
  return sid;
}
function sprzataj() {
  return wpEval(`global $wpdb; $t=$wpdb->prefix."sklepag_zamowienia";
    echo (int) $wpdb->query("DELETE FROM {$t} WHERE stripe_session_id LIKE 'cs_test_frontprob_%'");`);
}

/* Tekst, ktory strona „Dziekujemy" pokazuje dla zamowienia o danym statusie. */
async function tekstStatusu(browser, sid, czekajMs) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  // Liczymy odpytania endpointu: status KONCOWY ma je zatrzymac. Strona odswiezajaca sie
  // przez dwie minuty po zwrocie wyglada, jakby cos jeszcze trwalo.
  let odpytan = 0;
  page.on('request', (r) => { if (r.url().indexOf('sklepag/v1/status') !== -1) { odpytan++; } });
  await page.goto(BAZA + '/dziekujemy-za-zakup/?sesja=' + encodeURIComponent(sid), { waitUntil: 'domcontentloaded' });
  // Pierwsze odpytanie idzie od razu; kolejne co 4 s. Czekamy dluzej niz jeden odstep,
  // zeby brak drugiego odpytania byl POMIAREM, a nie zbiegiem okolicznosci.
  await page.waitForTimeout(czekajMs || 2500);
  const t = await page.evaluate(() => {
    const k = document.querySelector('.sklepag-dziekujemy');
    if (!k) { return null; }
    const p = k.querySelector('.sklepag-status') || k;
    return (p.textContent || '').trim();
  });
  await ctx.close();
  return { tekst: t, odpytan: odpytan };
}

(async () => {
  const browser = await chromium.launch({ executablePath: wymagajChromium() });
  try {
    console.log('=== A. Strona „Dziekujemy" a statusy finansowe ===');

    // Kontrola dodatnia: przyrzad ma cokolwiek mierzyc — zwykly, domkniety zakup czyta sie dobrze.
    const sidOk = zamowienie('wyslany', 'mail_wyslany');
    const rOk = await tekstStatusu(browser, sidOk);
    const tOk = rOk.tekst;
    sprawdz('przyrzad dziala: status mail_wyslany daje zdanie o wyslanym mailu',
      !!tOk && /Mail z potwierdzeniem/i.test(tOk), tOk);

    const sidZwrot = zamowienie('zwrot', 'zwrocone');
    const rZwrot = await tekstStatusu(browser, sidZwrot, 7000);
    const tZwrot = rZwrot.tekst;
    sprawdz('po ZWROCIE pieniedzy strona NIE mowi „Przetwarzamy Twoja platnosc"',
      !!tZwrot && !/Przetwarzamy Twoj/i.test(tZwrot), tZwrot);
    sprawdz('i mowi wprost o zwrocie',
      !!tZwrot && /zwr[oó]c/i.test(tZwrot), tZwrot);

    const sidSpor = zamowienie('spor', 'sporne');
    const rSpor = await tekstStatusu(browser, sidSpor);
    const tSpor = rSpor.tekst;
    sprawdz('przy SPORZE (obciazenie zwrotne) tez nie „Przetwarzamy"',
      !!tSpor && !/Przetwarzamy Twoj/i.test(tSpor), tSpor);
    sprawdz('i mowi wprost o reklamacji platnosci w banku',
      !!tSpor && /(reklamac|bank|spor)/i.test(tSpor), tSpor);

    // Status koncowy ma ZATRZYMAC odpytywanie. Przy odstepie 4 s i czekaniu 7 s drugie
    // odpytanie byloby juz widoczne — jedno trafienie znaczy „przestala pytac”.
    sprawdz('po zwrocie strona PRZESTAJE odpytywac (status koncowy)',
      rZwrot.odpytan === 1, 'odpytan: ' + rZwrot.odpytan);

    // PRZYPADEK BEZ WADY: zakup w toku ma dalej mowic „Przetwarzamy” i dalej odpytywac —
    // inaczej „naprawa” odebralaby strone kazdemu, kto wlasnie zaplacil.
    const sidCzeka = zamowienie('czeka', 'oczekuje');
    const rCzeka = await tekstStatusu(browser, sidCzeka, 7000);
    sprawdz('BEZ WADY: zakup w toku nadal mowi „Przetwarzamy Twoja platnosc"',
      !!rCzeka.tekst && /Przetwarzamy Twoj/i.test(rCzeka.tekst), rCzeka.tekst);
    sprawdz('BEZ WADY: i nadal odpytuje (status nie jest koncowy)',
      rCzeka.odpytan >= 2, 'odpytan: ' + rCzeka.odpytan);

    console.log('\n=== B. Pigulka „Kup teraz" w chwili platnosci ===');
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    page.setDefaultTimeout(60000);
    await page.goto(BAZA + '/sklep/produkt/' + SLUG + '/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    // Warunek wstepny 1: formularz i panel platnosci siedza w TEJ SAMEJ sekcji zakupu.
    const uklad = await page.evaluate(() => {
      const sek = document.getElementById('sklepag-zakup');
      const form = document.getElementById('sklepag-kup-formularz');
      const panel = document.getElementById('sklepag-platnosc');
      return {
        sekcja: !!sek, form: !!form, panel: !!panel,
        formWSekcji: !!(sek && form && sek.contains(form)),
        panelWSekcji: !!(sek && panel && sek.contains(panel)),
      };
    });
    sprawdz('warunek wstepny: formularz i panel platnosci sa w sekcji #sklepag-zakup',
      uklad.formWSekcji && uklad.panelWSekcji, JSON.stringify(uklad));

    // Przewijamy do sekcji zakupu — tak stoi kupujacy w chwili klikniecia „Kup teraz”.
    await page.evaluate(() => document.getElementById('sklepag-zakup').scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(1200);
    const przed = await page.evaluate(() => {
      const s = document.querySelector('.sklepag-sticky');
      return { widoczna: !!s && s.classList.contains('sklepag-sticky--widoczna') };
    });
    // Warunek wstepny 2: przy widocznym formularzu pigulki NIE MA — inaczej nie mierzymy zmiany.
    sprawdz('warunek wstepny: przy widocznym formularzu pigulki nie ma', przed.widoczna === false, JSON.stringify(przed));

    // ⛔ DOKLADNIE ta mutacja, ktora robi platnosc.js przy przejsciu do platnosci (linia 124).
    const po = await page.evaluate(async () => {
      const form = document.getElementById('sklepag-kup-formularz');
      const panel = document.getElementById('sklepag-platnosc');
      form.hidden = true;
      panel.hidden = false;
      await new Promise((r) => setTimeout(r, 900));
      const s = document.querySelector('.sklepag-sticky');
      const rs = s ? s.getBoundingClientRect() : null;
      const rp = panel.getBoundingClientRect();
      const nachodzi = !!rs && rs.bottom > rp.top && rs.top < rp.bottom && rs.right > rp.left && rs.left < rp.right;
      return {
        widoczna: !!s && s.classList.contains('sklepag-sticky--widoczna'),
        nachodziNaPanelPlatnosci: nachodzi,
      };
    });
    sprawdz('po przejsciu do platnosci pigulka „Kup teraz" NIE odslania sie',
      po.widoczna === false, JSON.stringify(po));

    // PRZYPADEK BEZ WADY: pigulka ma NADAL robic swoje. Bez tego kroku „naprawa”, ktora
    // po prostu gasi pigulke na zawsze, przechodzilaby na zielono.
    const nizej = await page.evaluate(async () => {
      const panel = document.getElementById('sklepag-platnosc');
      const form = document.getElementById('sklepag-kup-formularz');
      panel.hidden = true; form.hidden = false;           // powrot ze splaty, jak przycisk „Wroc”
      window.scrollTo(0, document.body.scrollHeight);      // ponizej calej sekcji zakupu
      await new Promise((r) => setTimeout(r, 1200));
      const s = document.querySelector('.sklepag-sticky');
      const sek = document.getElementById('sklepag-zakup').getBoundingClientRect();
      return {
        widoczna: !!s && s.classList.contains('sklepag-sticky--widoczna'),
        sekcjaNadOknem: sek.bottom < 0,
      };
    });
    sprawdz('BEZ WADY: ponizej sekcji zakupu pigulka nadal sie pokazuje',
      nizej.widoczna === true && nizej.sekcjaNadOknem === true, JSON.stringify(nizej));

    await ctx.close();
  } finally {
    await browser.close();
    console.log('\n=== C. Sprzatanie ===');
    console.log('  usunietych zamowien probnych: ' + sprzataj());
  }
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error('PRZYRZAD: ' + (e && e.stack ? e.stack : e)); process.exit(2); });
