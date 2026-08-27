const { chromium } = require('/usr/lib/node_modules/playwright');
const D='/root/praca/k7-dowody-28-08'; const out=[]; const log=s=>{console.log(s);out.push(s);};
(async()=>{
 const b=await chromium.launch();
 const p=await b.newPage({viewport:{width:1440,height:900}});
 await p.goto('http://localhost:8890/sklep/produkt/chatbot-live/',{waitUntil:'networkidle'});
 const przed = await p.evaluate(()=>document.body.innerHTML.length);
 await (await p.$('a.sklepag-lightbox')).click();
 await p.waitForTimeout(900);
 // znajdz PANEL: element o duzym z-index / position:fixed pokrywajacy ekran, zawierajacy przycisk zamkniecia
 const panel = await p.evaluate(()=>{
   const kand=[...document.querySelectorAll('body *')].filter(e=>{
     const cs=getComputedStyle(e); const r=e.getBoundingClientRect();
     return (cs.position==='fixed') && cs.display!=='none' && r.width>innerWidth*0.6 && r.height>innerHeight*0.6;
   });
   if(!kand.length) return null;
   const el=kand[kand.length-1];
   el.setAttribute('data-panel','1');
   return {tag:el.tagName,id:el.id,cls:el.className.toString().slice(0,60),
           zIndex:getComputedStyle(el).zIndex,
           ogniskowalne:[...el.querySelectorAll('a[href],button,video[controls],input,select,textarea,[tabindex]:not([tabindex="-1"])')]
             .map(x=>x.tagName+':'+((x.textContent||'').trim().slice(0,18)||x.className.toString().slice(0,18)))};
 });
 log('=== C2b · PANEL pelnego ekranu (0.9.19) ==='); log(JSON.stringify(panel,null,1));
 if(!panel){ log('BLAD SONDY: nie znalazlam panelu — nie orzekam.'); }
 else{
   const slad=[];
   for(let i=0;i<15;i++){
     await p.keyboard.press('Tab');
     slad.push(await p.evaluate(()=>{const a=document.activeElement;const pn=document.querySelector('[data-panel]');
       return {co:(a.textContent||a.value||'').trim().slice(0,22)||a.tagName+'.'+a.className.toString().slice(0,18),
               w_panelu: pn?pn.contains(a):null};}));
   }
   const poza=slad.filter(f=>f.w_panelu===false);
   log(poza.length? 'FOKUS UCIEKL POD SPOD '+poza.length+'/15 -> '+JSON.stringify(poza.slice(0,4))
                  : 'fokus nie wyszedl poza panel w 15 krokach Tab');
   log('slad: '+JSON.stringify(slad.map(f=>f.co+'|'+f.w_panelu)));
 }
 await p.screenshot({path:`${D}/C2b-0919-panel.png`});
 await p.keyboard.press('Escape'); await p.waitForTimeout(500);
 log('po Esc panel obecny? '+ await p.evaluate(()=>{const e=document.querySelector('[data-panel]');return e?getComputedStyle(e).display:'usuniety z DOM';}));
 await b.close();
 require('fs').appendFileSync(`${D}/RECHECK-0919-wynik.txt`,'\n\n'+out.join('\n'));
})();
