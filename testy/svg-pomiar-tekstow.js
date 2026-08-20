/* Pomiar RAMEK wszystkich tekstów w SVG prawdziwą przeglądarką (getBBox).
 *
 * Po co osobno od kontroli prostokątów: SVG NIE zawija tekstu. Element <text>
 * z poprawną współrzędną x rozciąga się dokładnie tyle, ile zajmą glify —
 * i cicho wychodzi poza kadr. Sprawdzanie samego punktu startowego tego NIE
 * łapie (tak przeszła urwana stopka w pierwszej wersji diagramu).
 *
 * Sprawdza dwie rzeczy dla każdego <text> i <tspan>:
 *   1) czy ramka mieści się w kadrze — WSZYSTKIE CZTERY krawędzie,
 *   2) czy tekst mieści się w prostokącie, w którym się zaczyna — też z czterech stron.
 *
 * Wymaga playwright-core. Uruchamiać Z KATALOGU REPO:
 *   node testy/svg-pomiar-tekstow.js [plik.svg] [marg-poziom] [marg-pion] [marg-kadr]
 * Kod wyjścia 0 = wszystko się mieści, 1 = coś wystaje.
 */
const { chromium } = require('playwright-core');
const path = require('path');

const PLIK = process.argv[2] || 'wtyczki/chatbot-live/DIAGRAM-PRZEPLYWU.svg';
/*
 * Trzy różne progi, bo trzy różne rzeczy:
 *  - MARG_POZIOM: teksty w boksach są wcięte o 20 px, więc 12 px zapasu po
 *    bokach jest realnym wymogiem układu, nie kaprysem.
 *  - MARG_PION: pionowo wymagamy tylko, żeby tekst NIE DOTYKAŁ krawędzi boksu
 *    (ostatnia linia siedzi blisko dołu z założenia) — 4 px.
 *  - MARG_KADR: od krawędzi obrazu trzymamy 16 px, bo tam ucięcie jest
 *    nieodwracalne: w PDF i w przeglądarce po prostu nie ma tego piksela.
 */
const MARG_POZIOM = Number(process.argv[3] || 12);
const MARG_PION   = Number(process.argv[4] || 4);
const MARG_KADR   = Number(process.argv[5] || 16);

(async () => {
  const b = await chromium.launch({
    executablePath: '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome',
  });
  const p = await b.newPage();
  await p.goto('file://' + path.resolve(PLIK), { waitUntil: 'load' });

  const wynik = await p.evaluate(({ MARG_POZIOM, MARG_PION, MARG_KADR }) => {
    const svg = document.querySelector('svg');
    const vb = svg.viewBox.baseVal;
    const W = vb.width, H = vb.height;

    /*
     * Kadr musi byc JEDNOZNACZNY. Gdy atrybuty width/height nie zgadzaja sie
     * z viewBox, "wysokosc obrazu" zalezy od tego, ktory z nich czyta dany
     * program — i ten sam plik jednemu narzedziu wychodzi na 910, a drugiemu
     * na 880. Wtedy kontrola moze swiecic zielono, a klient widziec ucieta
     * linie. Rozjazd = twardy blad, zanim ktokolwiek zacznie szukac roznicy
     * w tekstach.
     */
    const atrW = parseFloat(svg.getAttribute('width'));
    const atrH = parseFloat(svg.getAttribute('height'));
    const kadrNiespojny = (Number.isFinite(atrW) && atrW !== W) || (Number.isFinite(atrH) && atrH !== H);

    const prostokaty = [...svg.querySelectorAll('rect')]
      .map((r) => ({
        x: r.x.baseVal.value, y: r.y.baseVal.value,
        w: r.width.baseVal.value, h: r.height.baseVal.value,
      }))
      .filter((r) => !(r.w >= W && r.h >= H)); // pomijamy tło

    const zle = [];
    const wszystkie = [];
    for (const el of svg.querySelectorAll('text, tspan')) {
      const t = (el.textContent || '').trim();
      if (!t) { continue; }
      const bb = el.getBBox();
      const prawa = bb.x + bb.width, dol = bb.y + bb.height;
      const wpis = { tekst: t.slice(0, 46), pelny: t, x: Math.round(bb.x), prawa: Math.round(prawa), dol: Math.round(dol), szer: Math.round(bb.width) };
      const st = getComputedStyle(el);
      wpis.font = parseFloat(st.fontSize);
      wpis.mono = /mono/i.test(st.fontFamily) ? 1 : 0;
      wpis.bold = (parseInt(st.fontWeight, 10) || 400) >= 600 ? 1 : 0;
      wszystkie.push(wpis);

      // CZTERY KRAWEDZIE kadru, nie tylko prawa. Tekst potrafi wyjsc w dol
      // (ostatnia linia pod kadrem = scieta u dolu), w gore i w lewo tak samo
      // latwo jak w prawo — sprawdzanie samej szerokosci to kontrola z luka,
      // a kontrola z luka jest grozniejsza niz brak kontroli.
      const pozaKadrem = [];
      if (bb.x < MARG_KADR) { pozaKadrem.push('lewa (x=' + Math.round(bb.x) + ')'); }
      if (bb.y < MARG_KADR) { pozaKadrem.push('gorna (y=' + Math.round(bb.y) + ')'); }
      if (prawa > W - MARG_KADR) { pozaKadrem.push('prawa (' + Math.round(prawa) + ' > ' + (W - MARG_KADR) + ')'); }
      if (dol > H - MARG_KADR) { pozaKadrem.push('dolna (' + Math.round(dol) + ' > ' + (H - MARG_KADR) + ')'); }
      if (pozaKadrem.length) {
        zle.push({ ...wpis, powod: 'poza kadrem ' + W + 'x' + H + ' — krawedzie: ' + pozaKadrem.join(', ') });
        continue;
      }

      // W którym prostokącie zaczyna się ten tekst? Jesli w jakims — musi sie
      // w nim miescic ze WSZYSTKICH czterech stron.
      const boks = prostokaty.find((r) => bb.x >= r.x && bb.x <= r.x + r.w && bb.y >= r.y && bb.y <= r.y + r.h);
      if (boks) {
        const pozaBoksem = [];
        if (bb.x < boks.x + MARG_POZIOM) { pozaBoksem.push('lewa'); }
        if (bb.y < boks.y + MARG_PION) { pozaBoksem.push('gorna'); }
        if (prawa > boks.x + boks.w - MARG_POZIOM) { pozaBoksem.push('prawa'); }
        if (dol > boks.y + boks.h - MARG_PION) { pozaBoksem.push('dolna'); }
        if (pozaBoksem.length) {
          zle.push({ ...wpis, powod: 'wychodzi z ramki [' + Math.round(boks.x) + ',' + Math.round(boks.y) + ' ' + Math.round(boks.w) + 'x' + Math.round(boks.h) + '] — krawedzie: ' + pozaBoksem.join(', ') });
        }
      }
    }
    return { W, H, atrW, atrH, kadrNiespojny, ile: wszystkie.length, zle, wszystkie, najszersze: [...wszystkie].sort((a, c) => c.prawa - a.prawa).slice(0, 5), najnizsze: [...wszystkie].sort((a, c) => c.dol - a.dol).slice(0, 5) };
  }, { MARG_POZIOM, MARG_PION, MARG_KADR });

  await b.close();

  if (process.env.DUMP) {
    for (const w of wynik.wszystkie) { console.log(w.font + '\t' + w.mono + '\t' + w.bold + '\t' + w.szer + '\t' + w.pelny); }
    process.exit(0);
  }
  console.log('plik: ' + PLIK);
  console.log('viewBox: ' + wynik.W + ' x ' + wynik.H + '   atrybuty width/height: ' + wynik.atrW + ' x ' + wynik.atrH);
  console.log('zmierzonych tekstow (text + tspan): ' + wynik.ile);
  if (wynik.ile === 0) {
    console.log('\nNIEPOWODZENIE: zmierzono ZERO elementow — kontrola nie znalazla tekstow (zly plik albo zly selektor)');
    process.exit(1);
  }
  if (wynik.kadrNiespojny) {
    console.log('\nNIEPOWODZENIE: kadr niespojny — viewBox mowi ' + wynik.W + 'x' + wynik.H
      + ', a atrybuty width/height mowia ' + wynik.atrW + 'x' + wynik.atrH
      + '. Ten sam plik da rozne wyniki w roznych narzedziach.');
    process.exit(1);
  }
  console.log('\nnajdalej siegajace w PRAWO:');
  for (const w of wynik.najszersze) {
    console.log('  prawa=' + String(w.prawa).padStart(5) + '  szer=' + String(w.szer).padStart(4) + '  "' + w.tekst + '"');
  }
  console.log('\nnajnizej siegajace (dolna krawedz):');
  for (const w of wynik.najnizsze) {
    console.log('  dol=' + String(w.dol).padStart(5) + '  zapas do kadru=' + String(wynik.H - w.dol).padStart(4) + '  "' + w.tekst + '"');
  }
  if (wynik.zle.length) {
    console.log('\nPRZEPELNIENIA (' + wynik.zle.length + '):');
    for (const z of wynik.zle) {
      console.log('  x=' + z.x + ' prawa=' + z.prawa + ' dol=' + z.dol + '  [' + z.powod + ']');
      console.log('     "' + z.tekst + '"');
    }
    console.log('\nNIEPOWODZENIE: ' + wynik.zle.length + ' tekstow nie miesci sie');
    process.exit(1);
  }
  console.log('\nWSZYSTKIE TEKSTY SIE MIESZCZA');
  process.exit(0);
})().catch((e) => { console.error(e); process.exit(1); });
