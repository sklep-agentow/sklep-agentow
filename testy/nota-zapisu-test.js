/* L7 — nota o zapisie rozmowy. Straznik, kod wyjscia 1 przy porazce.
 *
 * Co bylo nie tak: informacja o przetwarzaniu danych szla WYLACZNIE do
 * formularza kontaktu, a samo pisanie na czacie juz ladowalo w historii rozmow.
 * Gosc dowiadywal sie o zapisie dopiero, gdy zostawial maila — a wtedy czesc
 * rozmowy byla zapisana od kilku minut. Sprzedajemy wielu firmom i kazda staje
 * sie administratorem danych, wiec informacja ma byc PRZED pierwszym slowem.
 *
 * Wymaga playwright-core i stacku. Uzycie: node testy/nota-zapisu-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const KONTENER = 'strona_wp_wordpress';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}
function wpEval(kod) {
  /* null = WYWROTKA wywolania, '' = poprawna pusta odpowiedz — rozroznienie
   * chroni migawke: '' z wywrotki udawaloby prawidlowy stan i restore
   * zapisalby pustke w miejsce cudzej wartosci (WAZNA 8 recenzji #168:
   * ten plik byl zrodlem dryfu adres_polityki='' na wspolnym stosie,
   * ktory wywracal widget-formularz.js biegnacy PO nim). */
  try {
    return execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp', 'eval', kod], { encoding: 'utf8' }).trim();
  } catch (e) { return null; }
}
function ustawPolityke(adres) {
  const zapis = wpEval('$u = chatl_ustawienia(); $u["adres_polityki"] = "' + adres + '"; update_option("chatl_ustawienia", $u); echo "OK";');
  if (zapis !== 'OK') { throw new Error('ustawPolityke(): zapis adres_polityki padl (' + zapis + ')'); }
}
/* Restore IDEMPOTENTNE i wolane takze z .catch — wywrotka w polowie testu nie
 * moze zostawic stosu z pustka ani z adresem testowym (wzorzec z widget-formularz.js). */
let notaMigawka = null;
function przywrocPolityke() {
  if (notaMigawka === null) { return; }
  const w = wpEval('$u = chatl_ustawienia(); $u["adres_polityki"] = ' + JSON.stringify(notaMigawka) + '; update_option("chatl_ustawienia", $u); echo "OK";');
  if (w !== 'OK') {
    /* Pad restore NIE zeruje migawki (kolejne wywolanie, np. z .catch, sprobuje
     * raz jeszcze) i melduje glosno — jak bliźniak w widget-formularz.js. */
    console.error('przywrocPolityke(): restore adres_polityki PADL — stos mogl zostac ze stanem testowym');
    bledy++; /* pad restore = czerwony test (DROBNA 16 recenzji #168) */
    return;
  }
  notaMigawka = null;
}

(async () => {
  const staraPolityka = wpEval('echo chatl_ustawienia()["adres_polityki"];');
  if (staraPolityka === null) {
    throw new Error('nie odczytam migawki adres_polityki (docker/wp-cli padl) — nie ruszam stosu');
  }
  notaMigawka = staraPolityka;
  const retencja = wpEval('echo CHATL_RETENCJA_DNI;');
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)

  console.log('[1] nota jest widoczna PRZED napisaniem czegokolwiek');
  ustawPolityke('');
  await p.goto(BAZA + '/', { waitUntil: 'load' });
  await p.click('.chatl-babelek');
  await p.waitForTimeout(200);
  const nota = await p.$('.chatl-nota');
  sprawdz('nota istnieje zaraz po otwarciu czatu', nota !== null);
  const tekst = nota ? (await p.$eval('.chatl-nota', (e) => e.textContent.replace(/\s+/g, ' ').trim())) : '';
  console.log('       "' + tekst + '"');
  sprawdz('nota mowi, ze rozmowa JEST ZAPISYWANA', /zapisywan/i.test(tekst));
  sprawdz('nota podaje okres retencji z KODU (' + retencja + ' dni)',
    new RegExp('\\b' + retencja + ' dni').test(tekst));
  sprawdz('nota mowi, PO CO zapisujemy', /wrócić z odpowiedzią/i.test(tekst));

  console.log('\n[2] nota stoi PRZED powitaniem bota, nie po nim');
  const kolejnosc = await p.$eval('.chatl-wiadomosci', (e) => {
    const dzieci = [...e.children];
    return dzieci.map((d) => d.className).join(' | ');
  });
  console.log('       ' + kolejnosc);
  sprawdz('nota jest pierwszym elementem listy wiadomosci', /^chatl-nota/.test(kolejnosc));

  console.log('\n[3] bez adresu polityki nie ma pustego odnosnika');
  sprawdz('brak odnosnika, gdy wlasciciel nie podal adresu', (await p.$('.chatl-nota a')) === null);

  console.log('\n[4] z adresem polityki nota prowadzi do niej');
  ustawPolityke('https://przyklad.pl/polityka-prywatnosci');
  await p.goto(BAZA + '/', { waitUntil: 'load' });
  await p.click('.chatl-babelek');
  await p.waitForTimeout(200);
  const link = await p.$('.chatl-nota a');
  sprawdz('jest odnosnik do polityki', link !== null);
  if (link) {
    const href = await p.$eval('.chatl-nota a', (e) => e.getAttribute('href'));
    const rel = await p.$eval('.chatl-nota a', (e) => e.getAttribute('rel'));
    sprawdz('odnosnik prowadzi pod podany adres', href === 'https://przyklad.pl/polityka-prywatnosci', href);
    sprawdz('odnosnik otwierany w nowej karcie ma rel=noopener', 'noopener' === rel, String(rel));
  }

  console.log('\n[5] nota nie jest wypowiedzia bota (to informacja wlasciciela strony)');
  const dymki = await p.$$eval('.chatl-wiadomosci .chatl-dymek', (e) => e.map((x) => x.textContent));
  sprawdz('tresc noty nie wystepuje w zadnym dymku rozmowy',
    dymki.every((d) => !/zapisywan/i.test(d)), dymki.join(' || ').slice(0, 160));

  console.log('\n[6] widget nadal nie uzywa innerHTML (nota sklada sie z wezlow)');
  /* Szukamy UZYCIA wlasciwosci (kropka przed nazwa), nie samego slowa — inaczej
     liczy sie tez komentarz "bez innerHTML" i test padal na wlasnym opisie.
     Ten sam wzorzec ma kontrola dokumentacja-zgodnosc.sh (nie bramka CI), wiec obie licza to samo. */
  let uzycia = '0';
  try {
    uzycia = execFileSync('docker', ['exec', KONTENER, 'grep', '-c', '\\.innerHTML',
      '/var/www/html/wp-content/plugins/chatbot-live/assets/widget.js'], { encoding: 'utf8' }).trim();
  } catch (e) { uzycia = '0'; } // grep -c bez trafien konczy sie kodem 1
  sprawdz('zero UZYC .innerHTML w widgecie', uzycia === '0', 'trafien: ' + uzycia);

  przywrocPolityke();
  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { przywrocPolityke(); console.error(e); process.exit(1); });
