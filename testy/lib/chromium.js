/* JEDNO miejsce, w ktorym testy ustalaja, gdzie jest chromium.
 *
 * Uwaga recenzenta (PR #66): sciezka byla wpisana WPROST w kazdym tescie —
 * '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome'. To zle
 * z trzech powodow: zaszywa cudzy katalog domowy (/root), zaszywa NUMER REWIZJI
 * przegladarki (1234 — po `npm update playwright-core` katalog nazywa sie
 * inaczej) i powiela ten sam napis w 7 plikach, wiec poprawka w jednym miejscu
 * i tak zostawialaby szesc zepsutych.
 *
 * Kolejnosc szukania — od najbardziej jawnego do najbardziej domyslnego:
 *   1. zmienna srodowiskowa (CHATL_CHROMIUM / CHROME_PATH / CHROMIUM_PATH) —
 *      jawna decyzja czlowieka ma pierwszenstwo nad kazdym wykryciem,
 *   2. rejestr playwright-core (chromium.executablePath()) — zwraca dokladnie
 *      te przegladarke, ktora pasuje do ZAINSTALOWANEJ wersji playwrighta,
 *   3. przegladanie katalogu pobran playwrighta (PLAYWRIGHT_BROWSERS_PATH albo
 *      ~/.cache/ms-playwright) — dziala, gdy playwright-core nie jest wpiety,
 *      a przegladarka lezy; numer rewizji bierzemy z NAJWYZSZEGO znalezionego,
 *   4. chromium/chrome zainstalowany systemowo (PATH i typowe sciezki dystrybucji).
 *
 * Uzycie w tescie:      const { wymagajChromium } = require('./lib/chromium');
 * Uzycie w skrypcie sh: node testy/lib/chromium.js   (drukuje sciezke, EXIT 1 = brak)
 */
const fs = require('fs');
const os = require('os');
const path = require('path');

const ZMIENNE = ['CHATL_CHROMIUM', 'CHROME_PATH', 'CHROMIUM_PATH', 'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'];

function wykonywalny(p) {
  if (!p) { return false; }
  try {
    fs.accessSync(p, fs.constants.X_OK);
    return fs.statSync(p).isFile();
  } catch (e) {
    return false;
  }
}

/* Katalog pobran playwrighta zalezy od systemu i od PLAYWRIGHT_BROWSERS_PATH —
   nie od tego, czy ktos pracuje na koncie root. */
function katalogPobran() {
  if (process.env.PLAYWRIGHT_BROWSERS_PATH && process.env.PLAYWRIGHT_BROWSERS_PATH !== '0') {
    return process.env.PLAYWRIGHT_BROWSERS_PATH;
  }
  if (process.platform === 'darwin') {
    return path.join(os.homedir(), 'Library', 'Caches', 'ms-playwright');
  }
  if (process.platform === 'win32') {
    return path.join(process.env.LOCALAPPDATA || os.homedir(), 'ms-playwright');
  }
  return path.join(os.homedir(), '.cache', 'ms-playwright');
}

/* Numer rewizji jest liczba, wiec sortujemy LICZBOWO — porownanie napisow
   uznaloby '999' za nowsze niz '1234'. */
function zRejestruPlaywrighta() {
  try {
    const { chromium } = require('playwright-core');
    const p = chromium.executablePath();
    if (wykonywalny(p)) { return p; }
  } catch (e) { /* playwright-core nie wpiety albo bez pobranej przegladarki */ }
  return null;
}

function zKataloguPobran() {
  const baza = katalogPobran();
  let wpisy;
  try {
    wpisy = fs.readdirSync(baza);
  } catch (e) {
    return null;
  }
  const kandydaci = wpisy
    .map((n) => ({ n, m: /^chromium(?:_headless_shell)?-(\d+)$/.exec(n) }))
    .filter((k) => k.m)
    // Pelna przegladarka przed headless shell (shell nie umie zrzutow ekranu),
    // a przy remisie nowsza rewizja.
    .sort((a, c) => (/_headless_shell-/.test(a.n) ? 1 : 0) - (/_headless_shell-/.test(c.n) ? 1 : 0)
      || Number(c.m[1]) - Number(a.m[1]));

  const wzgledne = [
    ['chrome-linux64', 'chrome'],
    ['chrome-linux', 'chrome'],
    ['chrome-mac', 'Chromium.app', 'Contents', 'MacOS', 'Chromium'],
    ['chrome-win', 'chrome.exe'],
    ['chrome-headless-shell-linux64', 'chrome-headless-shell'],
  ];
  for (const k of kandydaci) {
    for (const w of wzgledne) {
      const p = path.join(baza, k.n, ...w);
      if (wykonywalny(p)) { return p; }
    }
  }
  return null;
}

function zSystemu() {
  const nazwy = ['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable', 'chrome'];
  const katalogi = (process.env.PATH || '').split(path.delimiter)
    .concat(['/usr/bin', '/usr/local/bin', '/snap/bin', '/opt/google/chrome']);
  for (const n of nazwy) {
    for (const k of katalogi) {
      if (!k) { continue; }
      const p = path.join(k, n);
      if (wykonywalny(p)) { return p; }
    }
  }
  return null;
}

/**
 * Sciezka do chromium albo null, gdy zadnej nie znaleziono.
 * @returns {string|null}
 */
function sciezkaChromium() {
  for (const z of ZMIENNE) {
    const p = process.env[z];
    if (p) {
      // Zmienna USTAWIONA, ale wskazujaca nicosc, to blad czlowieka — ma byc
      // widoczny od razu, a nie po cichu obchodzony wykryciem czegos innego.
      if (!wykonywalny(p)) {
        throw new Error(z + '=' + p + ' — pod ta sciezka nie ma wykonywalnego pliku');
      }
      return p;
    }
  }
  return zRejestruPlaywrighta() || zKataloguPobran() || zSystemu();
}

/**
 * Jak sciezkaChromium(), ale zamiast null konczy proces czytelnym komunikatem.
 * @returns {string}
 */
function wymagajChromium() {
  const p = sciezkaChromium();
  if (!p) {
    console.error('Nie znalazlem chromium. Ustaw CHATL_CHROMIUM=/sciezka/do/chrome albo pobierz przegladarke:');
    console.error('  npm install --no-save playwright-core && npx playwright install chromium');
    process.exit(1);
  }
  return p;
}

module.exports = { sciezkaChromium, wymagajChromium };

if (require.main === module) {
  let p = null;
  try {
    p = sciezkaChromium();
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
  if (!p) { process.exit(1); }
  console.log(p);
}
