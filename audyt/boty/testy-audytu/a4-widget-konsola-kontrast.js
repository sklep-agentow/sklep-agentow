const { chromium } = require('/usr/lib/node_modules/playwright');
const L = (...a) => console.log(...a);
function lum(c){const f=v=>{v/=255;return v<=0.04045?v/12.92:Math.pow((v+0.055)/1.055,2.4)};return 0.2126*f(c[0])+0.7152*f(c[1])+0.0722*f(c[2])}
function kontrast(a,b){const A=lum(a),B=lum(b);return ((Math.max(A,B)+0.05)/(Math.min(A,B)+0.05)).toFixed(2)}
function rgb(s){const m=s.match(/\d+(\.\d+)?/g)||[0,0,0];return [ +m[0], +m[1], +m[2], m[3]!==undefined?+m[3]:1 ]}
function naTle(f,t){const a=f[3];return [0,1,2].map(i=>Math.round(f[i]*a+t[i]*(1-a)))}

(async () => {
  const b = await chromium.launch({ headless:true, args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--js-flags=--max-old-space-size=256'] });
  const p = await b.newPage();
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)
  const konsola=[], bledy=[], zadania=[];
  p.on('console', m => { if(m.type()==='error'||m.type()==='warning') konsola.push(m.type()+': '+m.text()); });
  p.on('pageerror', e => bledy.push('pageerror: '+e.message));
  p.on('requestfailed', r => zadania.push('requestfailed: '+r.url().slice(0,90)+' :: '+(r.failure()||{}).errorText));
  await p.goto('http://localhost:8890/', { waitUntil:'networkidle', timeout:45000 });

  L('=== C4: konsola po zaladowaniu strony ===');
  L('  bledy JS: '+bledy.length+'  konsola(err/warn): '+konsola.length+'  nieudane zadania: '+zadania.length);
  [...bledy,...konsola,...zadania].slice(0,8).forEach(x=>L('   '+x));

  L('=== C1: czy babelek czatu istnieje i otwiera okno ===');
  const bab = await p.$('.chatl-babelek, [aria-label="Otwórz czat"]');
  L('  babelek znaleziony: '+!!bab);
  await bab.click();
  await p.waitForTimeout(900);
  const otwarte = await p.$eval('.chatl-okno', e => e.className + ' | display=' + getComputedStyle(e).display).catch(e=>'BRAK .chatl-okno: '+e.message);
  L('  okno po kliknieciu: '+otwarte);
  L('  fokus po otwarciu: '+await p.evaluate(()=>document.activeElement.className+' / '+(document.activeElement.getAttribute('aria-label')||'-')));

  L('=== C5: etykiety pol i kolejnosc TAB ===');
  L('  pola bez widocznej etykiety <label>: '+await p.evaluate(()=>{
     const pola=[...document.querySelectorAll('.chatl-okno input,.chatl-okno textarea')];
     return pola.filter(x=>x.type!=='checkbox'&&x.getAttribute('aria-hidden')!=='true').map(x=>(x.tagName)+'['+(x.type||'textarea')+'] label='+(!!x.labels&&x.labels.length>0)+' aria-label="'+(x.getAttribute('aria-label')||'')+'" placeholder="'+(x.placeholder||'')+'"').join(' ; ');
  }));
  const kol=[];
  for(let i=0;i<8;i++){ await p.keyboard.press('Tab'); kol.push(await p.evaluate(()=>{const a=document.activeElement;return (a.tagName||'')+(a.className?'.'+String(a.className).split(' ')[0]:'')})); }
  L('  kolejnosc TAB (8 skokow): '+kol.join(' -> '));

  L('=== C5: kontrast (WCAG 1.4.3, prog AA 4.5:1 dla tekstu <18pt) ===');
  const kolory = await p.evaluate(()=>{
    const q=s=>document.querySelector(s);
    const pole=q('.chatl-pole-pytania')||q('.chatl-okno textarea')||q('.chatl-okno input');
    const okno=q('.chatl-okno');
    const cs=pole?getComputedStyle(pole):null;
    const ph=pole?getComputedStyle(pole,'::placeholder'):null;
    const czas=q('.chatl-czas')||q('.chatl-info-prywatnosc');
    return {
      poleTekst: cs?cs.color:null, poleTlo: cs?cs.backgroundColor:null,
      placeholder: ph?ph.color:null,
      pomocniczyEl: czas?czas.className:null, pomocniczyKolor: czas?getComputedStyle(czas).color:null,
      pomocniczyTlo: czas?getComputedStyle(czas.parentElement).backgroundColor:null,
      oknoTlo: okno?getComputedStyle(okno).backgroundColor:null
    };
  });
  L('  '+JSON.stringify(kolory));
  const tlo = rgb(kolory.poleTlo && kolory.poleTlo!=='rgba(0, 0, 0, 0)' ? kolory.poleTlo : kolory.oknoTlo);
  if(kolory.placeholder){ const f=rgb(kolory.placeholder); L('  KONTRAST placeholder vs tlo pola: '+kontrast(naTle(f,tlo),tlo)+' :1'); }
  if(kolory.poleTekst){ const f=rgb(kolory.poleTekst); L('  KONTRAST tekst wpisywany vs tlo pola: '+kontrast(naTle(f,tlo),tlo)+' :1'); }
  if(kolory.pomocniczyKolor){ const f=rgb(kolory.pomocniczyKolor); const t=rgb(kolory.pomocniczyTlo&&kolory.pomocniczyTlo!=='rgba(0, 0, 0, 0)'?kolory.pomocniczyTlo:kolory.oknoTlo);
    L('  KONTRAST '+kolory.pomocniczyEl+' vs tlo: '+kontrast(naTle(f,t),t)+' :1'); }

  L('=== C3: podwojne klikniecie „Wyslij" (prog pulapki 2 s odczekany) ===');
  await p.waitForTimeout(2600);
  let strzaly=0;
  p.on('request', r => { if(r.url().includes('admin-ajax.php') && (r.postData()||'').includes('chatl_pytanie')) strzaly++; });
  await p.fill('.chatl-pole-pytania','Jak dziala zwrot towaru?');
  const btn = await p.$('.chatl-wyslij, [aria-label="Wyślij pytanie"]');
  await btn.click({force:true}); await btn.click({force:true}).catch(()=>{}); await btn.click({force:true}).catch(()=>{});
  await p.waitForTimeout(2500);
  L('  zadan chatl_pytanie po TRZECH kliknieciach: '+strzaly);
  L('  ostatnia wiadomosc w oknie: '+(await p.evaluate(()=>{const w=[...document.querySelectorAll('.chatl-wiadomosc,.chatl-dymek')];return w.length?w[w.length-1].textContent.slice(0,120):'(brak)'})));

  L('=== C2: siec pada w polowie — zadanie do bota ODCIETE ===');
  await p.route('**/admin-ajax.php', route => { const d=route.request().postData()||''; if(d.includes('chatl_pytanie')) return route.abort('connectionfailed'); return route.continue(); });
  await p.fill('.chatl-pole-pytania','Drugie pytanie przy zerwanej sieci');
  await btn.click({force:true});
  await p.waitForTimeout(4000);
  L('  co widzi gosc: '+(await p.evaluate(()=>{const w=[...document.querySelectorAll('.chatl-wiadomosc,.chatl-dymek')];return w.length?w[w.length-1].textContent.slice(0,180):'(brak)'})));
  L('  wskaznik „bot pisze" nadal widoczny? '+await p.evaluate(()=>{const d=document.querySelector('[aria-label="Bot pisze odpowiedź"]');return d?('TAK, display='+getComputedStyle(d).display):'nie ma go w DOM'}));
  L('  pole pytania odblokowane? '+await p.evaluate(()=>{const i=document.querySelector('.chatl-pole-pytania');return i? (i.disabled?'NIE — zablokowane':'tak'):'brak pola'}));
  await p.unroute('**/admin-ajax.php');

  L('=== C1: czy okno da sie ZAMKNAC (przycisk i Escape) ===');
  await p.keyboard.press('Escape'); await p.waitForTimeout(500);
  L('  po Escape klasa okna: '+await p.evaluate(()=>{const o=document.querySelector('.chatl-okno');return o?o.className:'brak'}));
  const zam = await p.$('[aria-label="Zamknij czat"]');
  if(zam){ await zam.click(); await p.waitForTimeout(500); }
  L('  po kliknieciu „Zamknij czat": '+await p.evaluate(()=>{const o=document.querySelector('.chatl-okno');return o?o.className:'brak'}));

  L('=== C4: konsola — PODSUMOWANIE po calej sesji klikania ===');
  L('  bledy JS: '+bledy.length+'  err/warn konsoli: '+konsola.length);
  [...bledy,...konsola].slice(0,10).forEach(x=>L('   '+x));
  await b.close();
})().catch(e=>{ console.log('PRZYRZAD PADL: '+e.message); process.exit(2); });
