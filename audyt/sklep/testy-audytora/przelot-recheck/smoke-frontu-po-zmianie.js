const { chromium } = require('/usr/lib/node_modules/playwright');
(async()=>{
 let bledy=0; const ok=(o,w,s)=>{console.log((w?'  OK   ':'  BLAD ')+o+(s?' :: '+s:'')); if(!w)bledy++;};
 const b=await chromium.launch(); const p=await b.newPage({viewport:{width:1440,height:900}});
 const konsola=[]; p.on('console',m=>{if(m.type()==='error')konsola.push(m.text());});
 p.on('pageerror',e=>konsola.push('PAGEERROR: '+e.message));
 await p.goto('http://localhost:8890/sklep/produkt/chatbot-live/',{waitUntil:'networkidle'});
 await p.waitForTimeout(1200);
 ok('zero bledow JS w konsoli', konsola.length===0, JSON.stringify(konsola.slice(0,3)));
 ok('skrypt ruchu zglosil gotowosc (sklepag-ruch-ok)',
    await p.evaluate(()=>document.documentElement.classList.contains('sklepag-ruch-ok')));
 const ukryte = await p.evaluate(()=>[...document.querySelectorAll('.sklepag-sekcja,section')]
   .filter(e=>{const c=getComputedStyle(e);return c.opacity==='0'||c.visibility==='hidden';}).length);
 await p.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));
 await p.waitForTimeout(1500);
 const ukryte_po = await p.evaluate(()=>[...document.querySelectorAll('.sklepag-sekcja,section')]
   .filter(e=>{const c=getComputedStyle(e);const r=e.getBoundingClientRect();
     return (c.opacity==='0'||c.visibility==='hidden') && r.top<innerHeight && r.bottom>0;}).length);
 ok('po przewinieciu nic widocznego nie zostalo ukryte', ukryte_po===0, 'przed:'+ukryte+' po:'+ukryte_po);
 ok('pigulka zakupu sie pokazala',
    await p.evaluate(()=>{const e=document.querySelector('.sklepag-sticky');
      return !!e && !e.hasAttribute('hidden');}));
 await p.evaluate(()=>window.scrollTo(0,0)); await p.waitForTimeout(400);
 await (await p.$('a.sklepag-lightbox')).click(); await p.waitForTimeout(700);
 ok('pelny ekran nadal sie otwiera', await p.evaluate(()=>!!document.querySelector('.sklepag-lightbox-tlo')));
 await p.keyboard.press('Escape'); await p.waitForTimeout(400);
 ok('Esc nadal zamyka', await p.evaluate(()=>!document.querySelector('.sklepag-lightbox-tlo')));
 await b.close();
 console.log(bledy?('NIEPOWODZENIE: '+bledy):'SMOKE FRONTU OK'); process.exit(bledy?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
