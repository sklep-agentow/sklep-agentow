/* Statystyki i raport luk — PELNA PETLA w prawdziwej przegladarce.
 *
 * To, czego nie sprawdzi zaden test jednostkowy: czy pytanie zadane przez
 * GOSCIA na stronie (przez widget, przez prawdziwy endpoint AJAX) doleci do
 * panelu jako luka, i czy przycisk skrotu naprawde otwiera dodawanie pary
 * z JUZ WPISANYM pytaniem.
 *
 * Kroki: gosc pyta o cos spoza bazy -> admin wchodzi na Statystyki i luki ->
 * pytanie jest na liscie -> klika "Dopisz odpowiedz" -> formularz Q&A ma
 * pytanie i podpowiedziane slowa kluczowe.
 *
 * Wymaga playwright-core i poswiadczen admina w env (WP_ADMIN_USER/PASS,
 * nigdy w kodzie). Uzycie: node testy/panel-statystyki.js [adres]
 * Kod wyjscia 0 = wszystkie kroki przeszly, 1 = ktorys nie.
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const BAZA = process.argv[2] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

/*
 * Pytanie musi byc UNIKALNE w kazdym przebiegu, inaczej drugi przebieg
 * patrzylby na luke z pierwszego i przechodzilby nawet ze zepsutym zapisem.
 *
 * Unikalne musza byc RDZENIE (pierwsze CHATL_LUKI_RDZEN znakow slow), a nie sam napis:
 * grupowanie sklada pytania o wspolnych rdzeniach w jeden temat, wiec
 * "zraszacze kontrolneAAA" i "zraszacze kontrolneBBB" wpadlyby do jednego
 * tematu (rdzen "kontr" ten sam) i skrot prowadzilby do pytania z POPRZEDNIEGO
 * przebiegu. Dlatego oba znaczniki maja rozne pierwsze piec znakow, a poza
 * nimi pytanie nie zawiera slow znaczacych, ktore moglyby je z czyms skleic.
 */
/*
 * ZMIERZONE, gdy znaczniki mialy staly przedrostek ('zqk' + losowe): rdzen ma
 * CZTERY znaki, wiec z losowego ogona wchodzil do niego tylko JEDEN znak —
 * dwa przebiegi zderzaly sie na rdzeniu z prawdopodobienstwem ~1/36, a wtedy
 * podobienstwo siegalo progu 50% i pytania z dwoch przebiegow ladowaly w JEDNYM
 * temacie. Skrot prowadzil wtedy do reprezentanta z poprzedniego przebiegu
 * i test padal co kilka uruchomien. Dlatego: caly znacznik jest losowy (rdzen
 * tez), a reszta pytania to wylacznie slowa puste i krotsze niz cztery znaki,
 * wiec dwa przebiegi nie maja ze soba ZADNEGO wspolnego rdzenia.
 */
const LOS = () => Math.random().toString(36).slice(2, 10);
const ZNACZNIK = LOS();
const ZNACZNIK2 = LOS();
const PYTANIE = 'Czy to jest ' + ZNACZNIK + ' oraz ' + ZNACZNIK2 + '?';

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1440, height: 1100 } });

  console.log('[1] gosc zadaje pytanie SPOZA bazy (prawdziwy widget, prawdziwy AJAX)');
  console.log('       pytanie: ' + PYTANIE);
  await p.goto(BAZA + '/', { waitUntil: 'load' });
  await p.click('.chatl-babelek');
  await p.fill('.chatl-pole-pytania', PYTANIE);
  await p.click('.chatl-wyslij-pytanie');
  await p.waitForFunction(
    () => document.querySelectorAll('.chatl-wiadomosci .chatl-bot').length >= 2,
    null,
    { timeout: 15000 }
  );
  const rozmowa = await p.$eval('.chatl-wiadomosci', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('bot odpowiedzial tekstem awaryjnym (nie zna odpowiedzi)', /Nie znam odpowiedzi/.test(rozmowa),
    rozmowa.slice(-160));

  console.log('\n[1b] gosc zadaje pytanie, ktore bot ZNA (z bazy demo)');
  /* Bez tego kroku okres moglby miec same pytania bez odpowiedzi i kafelek
     skutecznosci pokazywalby 0% niezaleznie od tego, czy licznik trafien
     w ogole dziala. Po tym kroku w okresie SA oba rodzaje wymian. */
  await p.fill('.chatl-pole-pytania', 'ile kosztuje wasza usluga');
  await p.click('.chatl-wyslij-pytanie');
  await p.waitForFunction(
    () => document.querySelectorAll('.chatl-wiadomosci .chatl-bot').length >= 3,
    null,
    { timeout: 15000 }
  );
  const rozmowa2 = await p.$eval('.chatl-wiadomosci', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  /* Sprawdzamy NIE tresc odpowiedzi, tylko ze bot NIE zjechal do tekstu
     awaryjnego. Tresc zalezy od bazy Q&A wlasciciela i od tego, czy wlaczony
     jest tryb AI — a licznik trafien ma dzialac tak samo w obu trybach. */
  sprawdz('bot znal odpowiedz (nie zjechal do tekstu awaryjnego)',
    !/Nie znam odpowiedzi/.test(rozmowa2.slice(-200)),
    rozmowa2.slice(-200));

  console.log('\n[2] admin wchodzi na Statystyki i luki');
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-statystyki&zakres=7', { waitUntil: 'load' });

  const naglowki = await p.$$eval('h1, h2', (h) => h.map((e) => e.textContent.trim()));
  sprawdz('strona ma tytul "Statystyki i luki"', naglowki.includes('Statystyki i luki'), naglowki.join(' | '));
  sprawdz('jest sekcja ruchu dzien po dniu', naglowki.some((t) => /Ruch dzien po dniu|Ruch dzień po dniu/.test(t)));
  sprawdz('jest sekcja braków w bazie', naglowki.some((t) => /Czego botowi brakuje/.test(t)));

  const kafelki = await p.$$eval('.wrap div[style*="flex:1"]', (d) => d.map((e) => e.textContent.replace(/\s+/g, ' ').trim()));
  console.log('       kafelki: ' + kafelki.join('  ||  '));
  sprawdz('sa 4 kafelki z liczbami', kafelki.length === 4);
  sprawdz('kafelek pytan gosci pokazuje liczbe', /Pytania gości\s*\d/.test(kafelki.join(' ')));
  sprawdz('kafelek skutecznosci pokazuje procent', /Bot odpowiedział\s*[\d ]+\s*\([\d,.]+%\)/.test(kafelki.join(' ')));
  sprawdz('kafelek zebranych kontaktow pokazuje konwersje', /Zebrane kontakty[\s\S]*rozmów \([\d,.]+%\)/.test(kafelki.join(' ')));

  /* Najmocniejszy krok tej sekcji: w okresie jest CO NAJMNIEJ jedno pytanie
     trafione (1b) i co najmniej jedno nietrafione (1), wiec skutecznosc musi
     wyjsc STRICTE miedzy 0% a 100%. Licznik, ktory nie dziala, daje 0% albo
     100% — jedno i drugie ten krok zlapie. */
  const mSkut = /Bot odpowiedział\s*([\d ]+)\s*\(([\d,.]+)%\)/.exec(kafelki.join(' '));
  sprawdz('kafelek skutecznosci ma liczbe i procent (nie kreske)', mSkut !== null);
  if (mSkut) {
    const trafione = Number(mSkut[1].replace(/\s/g, ''));
    const procent = Number(mSkut[2].replace(',', '.'));
    sprawdz('trafionych odpowiedzi >= 1 (pytanie z bazy zostalo policzone)', trafione >= 1, 'trafione: ' + trafione);
    sprawdz('skutecznosc jest strictly miedzy 0% a 100% (sa oba rodzaje wymian)',
      procent > 0 && procent < 100, 'skutecznosc: ' + procent + '%');
  }

  console.log('\n[3] pytanie goscia jest na liscie luk');
  const tresc = await p.$eval('.wrap', (e) => e.textContent);
  sprawdz('nasze pytanie trafilo do raportu luk', tresc.indexOf(ZNACZNIK) !== -1);

  const skrot = await p.$('a.button-primary[href*="nowe_pytanie="]');
  sprawdz('przy luce jest przycisk skrotu do dodania pary', skrot !== null);

  console.log('\n[4] skrot otwiera dodawanie pary z WPISANYM pytaniem');
  /* Klikamy skrot DOKLADNIE przy naszym pytaniu, nie pierwszy z brzegu —
     inaczej test przechodzilby na cudzej luce z wczesniejszych przebiegow. */
  const link = await p.$eval(
    'a.button-primary[href*="nowe_pytanie="]',
    (a, zn) => {
      const wiersze = [...document.querySelectorAll('tr')];
      const w = wiersze.find((r) => r.textContent.indexOf(zn) !== -1);
      const cel = w ? w.querySelector('a.button-primary[href*="nowe_pytanie="]') : a;
      return cel ? cel.href : '';
    },
    ZNACZNIK
  );
  sprawdz('skrot przy NASZYM pytaniu istnieje', link !== '', link);
  await p.goto(link, { waitUntil: 'load' });

  const wpisane = await p.$eval('#chatl-pytanie', (e) => e.value);
  const slowa = await p.$eval('#chatl-slowa', (e) => e.value);
  const odpowiedz = await p.$eval('#chatl-odpowiedz', (e) => e.value);
  console.log('       pytanie w formularzu: "' + wpisane + '"');
  console.log('       podpowiedziane slowa: "' + slowa + '"');
  sprawdz('pole "Pytanie wzorcowe" jest juz wypelnione pytaniem goscia', wpisane.indexOf(ZNACZNIK) !== -1);
  /* Podpowiedz ma byc RDZENIAMI slow z pytania, i to rdzeniem DLUZSZYM niz
     ten uzywany do grupowania (CHATL_SUGESTIA_RDZEN = 5 kontra
     CHATL_LUKI_RDZEN = 4) — sprawdzamy to wprost na naszych znacznikach,
     zamiast zgadywac wzorcem "cokolwiek z literami". */
  sprawdz('podpowiedz zawiera rdzenie obu znacznikow z pytania',
    slowa.indexOf(ZNACZNIK.slice(0, 5)) !== -1 && slowa.indexOf(ZNACZNIK2.slice(0, 5)) !== -1,
    'szukane: ' + ZNACZNIK.slice(0, 5) + ' i ' + ZNACZNIK2.slice(0, 5));
  sprawdz('podpowiedz uzywa rdzenia 5-znakowego, nie 4-znakowego jak grupowanie',
    slowa.split(', ').filter(function (w) { return w.length === 5; }).length >= 2, slowa);
  sprawdz('pole odpowiedzi zostaje PUSTE (to wlasnie ma dopisac wlasciciel)', odpowiedz === '');
  sprawdz('formularz mowi, skad wzielo sie pytanie',
    (await p.$eval('.wrap', (e) => e.textContent)).indexOf('Statystyki i luki') !== -1);
  const idUkryte = await p.$eval('input[name=id]', (e) => e.value);
  sprawdz('to DODANIE nowej pary, nie edycja istniejacej (id=0)', idUkryte === '0');

  console.log('\n[5] polskie znaki i cudzyslowy przezywaja droge przez adres URL');
  /* Pytanie goscia idzie do formularza PRZEZ ADRES: rawurlencode w linku,
     sanitize_text_field po drugiej stronie. Tu latwo o krzaki (podwojne
     kodowanie) albo o dziure (brak escapowania w atrybucie value), wiec
     sprawdzamy oba naraz na pytaniu z ogonkami i cudzyslowem. */
  const TRUDNE = 'Czy wystawiacie fakturę na firmę zagraniczną — "z UE"?';
  const linkPl = BAZA + '/wp-admin/admin.php?page=chatl-qa'
    + '&nowe_pytanie=' + encodeURIComponent(TRUDNE)
    + '&nowe_slowa=' + encodeURIComponent('wysta, faktu, firme, zagra');
  await p.goto(linkPl, { waitUntil: 'load' });
  const wpisanePl = await p.$eval('#chatl-pytanie', (e) => e.value);
  console.log('       w formularzu: "' + wpisanePl + '"');
  sprawdz('ogonki nie zamienily sie w krzaki', wpisanePl.indexOf('fakturę na firmę zagraniczną') !== -1);
  sprawdz('mysllnik i cudzyslow przechodza bez podwojnego kodowania',
    wpisanePl.indexOf('—') !== -1 && wpisanePl.indexOf('%') === -1);
  const polaPl = await p.$eval('#chatl-slowa', (e) => e.value);
  sprawdz('slowa kluczowe tez przechodza', polaPl === 'wysta, faktu, firme, zagra', polaPl);

  console.log('\n[6] wstrzykniecie w parametrze skrotu nie wychodzi z atrybutu');
  const ZLE = '"><script>window.__zle=1</script>';
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa&nowe_pytanie=' + encodeURIComponent(ZLE), { waitUntil: 'load' });
  const wykonalSie = await p.evaluate(() => typeof window.__zle !== 'undefined');
  sprawdz('podlozony skrypt NIE wykonal sie', wykonalSie === false);
  const skryptyWFormie = await p.$$eval('#chatl-pytanie ~ *, .form-table script', (e) => e.length);
  sprawdz('w tabeli formularza nie ma wstrzyknietych elementow', skryptyWFormie === 0);

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
