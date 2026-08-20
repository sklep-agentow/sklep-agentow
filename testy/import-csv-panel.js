/* Sprawdza w PRAWDZIWEJ przeglądarce cały obieg importu CSV: wgranie pliku,
   ekran podglądu, poprawienie podpowiedzianych słów kluczowych i zapis.
   Sedno, którego nie da się sprawdzić z PHP: że podgląd naprawdę POKAZUJE się
   właścicielowi i że NIC nie ląduje w bazie, dopóki nie kliknie „Zapisz".
   Wymaga playwright-core i poświadczeń admina w env (WP_ADMIN_USER/PASS,
   nigdy w kodzie). Użycie: node testy/import-csv-panel.js [adres] */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const fs = require('fs');
const { execFileSync } = require('child_process');
const os = require('os');
const path = require('path');
const BAZA = process.argv[2] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; if (szczegol) { console.log('       ' + szczegol); } }
}

/* Plik celowo taki, jaki naprawdę przychodzi od klienta: średnik zamiast
   przecinka (polski Excel), polskie znaki, BRAK kolumny ze słowami kluczowymi
   w dwóch wierszach i jeden duplikat wobec tego, co wgrywamy wyżej. */
const CSV = [
  'pytanie;slowa_kluczowe;odpowiedz',
  'TEST-PANEL-Jakie są godziny otwarcia?;;Otwarte pon-pt 9-17.',
  'TEST-PANEL-Czy wystawiacie fakturę VAT?;;Tak, wystawiamy fakturę VAT.',
  'TEST-PANEL-Gdzie zaparkować?;parking, postoj;Parking jest od podwórza.',
  'TEST-PANEL-Jakie są godziny otwarcia?;;Powtórka tego samego pytania.',
].join('\n') + '\n';

/* Test MUSI dac sie uruchomic drugi raz z tym samym wynikiem: gdyby wiersze
   z poprzedniego przebiegu zostaly w bazie, caly plik wszedlby jako duplikaty
   i licznik "3 do zapisania" pokazalby 0. Kasujemy je przed i po. */
function sprzataj() {
  // przez `wp eval`, a nie `wp db query`: to drugie potrzebuje klienta mysql
  // w kontenerze, ktorego tam nie ma (konczy sie kodem 127).
  execFileSync('docker', ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', 'eval',
    'global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_qa WHERE pytanie LIKE \'TEST-PANEL-%\'" );'],
    { stdio: 'ignore' });
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  sprzataj();

  const plik = path.join(os.tmpdir(), 'chatl-import-panel.csv');
  fs.writeFileSync(plik, CSV, 'utf8');

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  // Jawny kontekst, a nie browser.newPage(): druga karta w kroku 2 musi dzielic
  // ciastko logowania, a z kontekstu domyslnego nie da sie otworzyc kolejnej karty.
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1100 } });
  const p = await ctx.newPage();
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');

  const ileWpisow = async () => {
    const t = await p.$eval('.wrap', (e) => e.textContent);
    const m = t.match(/Wpisy \((\d+)\)/);
    return m ? parseInt(m[1], 10) : -1;
  };

  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa', { waitUntil: 'load' });
  const przed = await ileWpisow();
  sprawdz('strona bazy Q&A wczytuje sie i podaje liczbe wpisow', przed >= 0, 'przed: ' + przed);
  sprawdz('jest sekcja importu i eksportu',
    (await p.$$eval('h2', (h) => h.map((e) => e.textContent))).some((t) => /Import i eksport/.test(t)));
  sprawdz('jest przycisk pobrania calej bazy Q&A',
    (await p.$$eval('a', (a) => a.map((e) => e.textContent))).some((t) => /Pobierz CSV \(cała baza/.test(t)));

  console.log('\n[krok 1: wgranie pliku]');
  await p.setInputFiles('input[type=file][name=plik]', plik);
  await Promise.all([p.waitForLoadState('load'), p.click('input[type=submit][value="Wczytaj i pokaż podgląd"]')]);

  const trescPodgladu = await p.$eval('.wrap', (e) => e.textContent);
  sprawdz('po wgraniu widac EKRAN PODGLADU', /Podgląd importu/.test(trescPodgladu));
  sprawdz('i wprost napisane, ze nic jeszcze nie zapisano', /nic jeszcze nie zostało zapisane/.test(trescPodgladu));
  sprawdz('plik ze SREDNIKIEM zostal rozpoznany (widac pytania, nie jedna kolumne)',
    /TEST-PANEL-Jakie są godziny otwarcia/.test(trescPodgladu));
  sprawdz('polskie znaki nie sa krzakami', /fakturę VAT/.test(trescPodgladu));
  sprawdz('licznik: 3 do zapisania', /Do zapisania:\s*3/.test(trescPodgladu.replace(/\s+/g, ' ')),
    trescPodgladu.replace(/\s+/g, ' ').match(/Do zapisania:.{0,60}/));
  sprawdz('licznik: 1 duplikat pomijany', /pomijane duplikaty:\s*1/.test(trescPodgladu.replace(/\s+/g, ' ')));
  sprawdz('wiersze bez slow kluczowych oznaczone jako PODPOWIEDZIANE',
    /podpowiedziane/.test(trescPodgladu));

  const polaSlow = await p.$$('input[name^="slowa["]');
  sprawdz('slowa kluczowe sa w polach do EDYCJI, nie samym tekstem', polaSlow.length === 3,
    'pol edycji: ' + polaSlow.length);
  const podpowiedz = await polaSlow[0].inputValue();
  sprawdz('podpowiedz dla pierwszego wiersza nie jest pusta', podpowiedz.length > 0, 'wartosc: ' + podpowiedz);

  console.log('\n[krok 2: SEDNO — podglad NIE zapisal nic]');
  const stanPoPodgladzie = await (async () => {
    // Karta w TYM SAMYM kontekscie — inaczej sesja bez ciastka logowania.
    const p2 = await ctx.newPage();
    await p2.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa', { waitUntil: 'load' });
    const t = await p2.$eval('.wrap', (e) => e.textContent);
    await p2.close();
    const m = t.match(/Wpisy \((\d+)\)/);
    return m ? parseInt(m[1], 10) : -1;
  })();
  sprawdz('po podgladzie liczba wpisow w bazie sie NIE zmienila',
    stanPoPodgladzie === przed, 'przed: ' + przed + ', po podgladzie: ' + stanPoPodgladzie);

  console.log('\n[krok 3: poprawka slow i zapis]');
  await polaSlow[0].fill('godzin, otwarc, czynne');
  await Promise.all([p.waitForLoadState('load'), p.click('input[type=submit][id=submit]')]);

  const poZapisie = await p.$eval('.wrap', (e) => e.textContent);
  sprawdz('komunikat podaje, ILE wpisow weszlo', /Zaimportowano 3 wpisów/.test(poZapisie),
    (poZapisie.match(/Zaimportowano.{0,40}/) || [''])[0]);
  const po = await ileWpisow();
  sprawdz('w bazie przybyly dokladnie 3 wpisy', po === przed + 3, 'przed: ' + przed + ', po: ' + po);
  sprawdz('do bazy poszly slowa POPRAWIONE recznie, nie podpowiedziane',
    /godzin, otwarc, czynne/.test(poZapisie));
  sprawdz('slowa wpisane w pliku przez wlasciciela tez sa', /parking, postoj/.test(poZapisie));
  sprawdz('duplikat NIE zostal dopisany drugi raz',
    (poZapisie.match(/TEST-PANEL-Jakie są godziny otwarcia/g) || []).length === 1);

  await b.close();
  fs.unlinkSync(plik);
  sprzataj();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})();
