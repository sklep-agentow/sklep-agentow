// B6 re-check na 0.9.19: czy odrzut walidacji TRZYMA wpisana tresc?
const { chromium } = require('/usr/lib/node_modules/playwright');
const fs=require('fs'); const D='/root/praca/k7-dowody-28-08';
const PASS=process.env.WP_ADMIN_PASS; const USER=process.env.WP_ADMIN_USER;
if(!PASS||!USER){console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env (patrz /root/.claude/sklep-admin-roboczy.env).');process.exit(1);}
const out=[]; const log=s=>{console.log(s);out.push(s);};
const NAZWA='SONDA B6 nazwa '+process.argv[2];
const OPIS='SONDA B6 dlugi opis, ktory ma NIE zginac przy odrzucie walidacji. '.repeat(4);
(async()=>{
 const b=await chromium.launch(); const p=await b.newPage({viewport:{width:1440,height:1000}});
 await p.goto('http://localhost:8890/wp-login.php',{waitUntil:'networkidle'});
 await p.fill('#user_login',USER); await p.fill('#user_pass',PASS);
 await p.click('#wp-submit'); await p.waitForLoadState('networkidle');
 log('zalogowana? '+ (p.url().includes('wp-admin')?'TAK':'NIE ('+p.url()+')'));
 await p.goto('http://localhost:8890/wp-admin/admin.php?page=sklepag-produkty&widok=formularz',{waitUntil:'networkidle'});
 // znajdz formularz dodawania produktu
 const pola = await p.evaluate(()=>[...document.querySelectorAll('form input,form textarea,form select')]
   .map(e=>({tag:e.tagName,type:e.type,name:e.name,id:e.id})).filter(e=>e.name));
 log('=== pola formularza produktu ==='); log(JSON.stringify(pola.slice(0,25)));
 // wypelnij: nazwa + opisy + cena, BEZ pliku, zaznacz "widoczny"
 const wpisane={};
 for(const f of pola){
   if(/nazwa|tytul/i.test(f.name)&&f.type==='text'){ await p.fill(`[name="${f.name}"]`,NAZWA); wpisane[f.name]=NAZWA; }
   else if(/opis/i.test(f.name)){ await p.fill(`[name="${f.name}"]`,OPIS); wpisane[f.name]=OPIS; }
   else if(/cena/i.test(f.name)){ await p.fill(`[name="${f.name}"]`,'123.45'); wpisane[f.name]='123.45'; }
 }
 const chk = pola.find(f=>f.type==='checkbox'&&/aktyw|widocz|kupowal/i.test(f.name));
 if(chk){ await p.check(`[name="${chk.name}"]`).catch(()=>{}); log('zaznaczono: '+chk.name); }
 log('WYSLANE pola: '+JSON.stringify(Object.keys(wpisane)));
 await p.screenshot({path:`${D}/B6-0919-przed-zapisem.png`,fullPage:true});
 await p.click('form [type=submit], form button[type=submit]');
 await p.waitForLoadState('networkidle'); await p.waitForTimeout(600);
 const po = await p.evaluate(()=>({
   url:location.href,
   komunikaty:[...document.querySelectorAll('.notice,.error,[class*="blad"],[role=alert]')].map(e=>(e.textContent||'').trim().slice(0,140)).filter(Boolean),
   wartosci:Object.fromEntries([...document.querySelectorAll('form input,form textarea')].filter(e=>e.name).map(e=>[e.name,(e.value||'').slice(0,60)]))
 }));
 log('=== PO ODRZUCIE (0.9.19) ==='); log('komunikaty: '+JSON.stringify(po.komunikaty));
 let zachowane=0, zgubione=[];
 for(const [k,v] of Object.entries(wpisane)){
   const teraz=po.wartosci[k]||'';
   if(teraz && v.startsWith(teraz.slice(0,20))) zachowane++; else zgubione.push(k+' -> "'+teraz+'"');
 }
 log(`pola wyslane: ${Object.keys(wpisane).length} · ZACHOWANE po odrzucie: ${zachowane} · zgubione: ${zgubione.length}`);
 if(zgubione.length) log('ZGUBIONE: '+JSON.stringify(zgubione));
 log(zachowane===Object.keys(wpisane).length ? 'WERDYKT B6: tresc TRZYMA sie po odrzucie — naprawione w 0.9.19'
     : 'WERDYKT B6: tresc NADAL ginie');
 await p.screenshot({path:`${D}/B6-0919-po-odrzucie.png`,fullPage:true});
 await b.close();
 fs.appendFileSync(`${D}/RECHECK-0919-wynik.txt`,'\n\n'+out.join('\n'));
})();
