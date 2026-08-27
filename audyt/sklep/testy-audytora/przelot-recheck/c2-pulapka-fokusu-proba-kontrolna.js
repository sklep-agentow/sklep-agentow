// PROBA KONTROLNA sondy C2b: czy detektor UMIE powiedziec "fokus poza panelem"?
const { chromium } = require('/usr/lib/node_modules/playwright');
(async()=>{
 const b=await chromium.launch(); const p=await b.newPage({viewport:{width:1440,height:900}});
 await p.goto('http://localhost:8890/sklep/produkt/chatbot-live/',{waitUntil:'networkidle'});
 await (await p.$('a.sklepag-lightbox')).click(); await p.waitForTimeout(800);
 await p.evaluate(()=>{const e=document.querySelector('.sklepag-lightbox-tlo'); if(e) e.setAttribute('data-panel','1');});
 // psuje DOKLADNIE JEDEN warunek: przestawiam fokus na element spoza panelu
 const wynik = await p.evaluate(()=>{
   const cel=[...document.querySelectorAll('a[href]')].find(a=>!document.querySelector('[data-panel]').contains(a));
   cel.focus();
   const a=document.activeElement, pn=document.querySelector('[data-panel]');
   return {co:(a.textContent||'').trim().slice(0,25), w_panelu: pn?pn.contains(a):null};
 });
 console.log('PROBA KONTROLNA (fokus celowo poza panelem): '+JSON.stringify(wynik));
 console.log(wynik.w_panelu===false ? 'OK — detektor UMIE wykryc ucieczke, wiec wczesniejsze "nie wyszedl" jest pomiarem, nie slepota.'
                                    : 'UWAGA — detektor NIE wykrywa ucieczki; wczesniejszy wynik NIEWAZNY.');
 await b.close();
})();
