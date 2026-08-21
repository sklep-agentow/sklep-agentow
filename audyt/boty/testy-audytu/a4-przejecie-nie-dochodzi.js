const { chromium } = require('/usr/lib/node_modules/playwright');
const { execSync } = require('child_process');
const L=(...a)=>console.log(...a);
const tresc=()=>`[...document.querySelectorAll('.chatl-wiadomosc,.chatl-dymek')].map(x=>x.textContent).join(' | ')`;
(async()=>{
 const b=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu']});
 const p=await b.newPage(); let poll=0; const bledy=[];
 p.on('pageerror',e=>bledy.push(e.message));
 p.on('request',r=>{ if(r.url().includes('/wp-json/chatl/v1/rozmowa')) poll++; });
 await p.goto('http://localhost:8890/',{waitUntil:'domcontentloaded',timeout:60000});
 await p.waitForSelector('[aria-label="Otwórz czat"]',{timeout:60000});
 await p.click('[aria-label="Otwórz czat"]'); await p.waitForTimeout(2800);
 await p.fill('.chatl-pole-pytania','Dzien dobry, mam pytanie o cennik');
 await p.click('[aria-label="Wyślij pytanie"]'); await p.waitForTimeout(3500);
 const sesja=await p.evaluate(()=>sessionStorage.getItem('chatl_sesja'));
 L('  sesja goscia: '+sesja+' ; odpytan REST do tej pory: '+poll);

 L('=== OPERATOR PRZEJMUJE ROZMOWE (panel wlasciciela, po stronie serwera) ===');
 const out=execSync(`docker exec strona_wp_wordpress wp --allow-root eval '`+
   `require_once WP_PLUGIN_DIR."/chatbot-live/includes/operator.php"; `+
   `wp_set_current_user(1); `+
   `var_dump(chatl_przejmij("${sesja}",1)); `+
   `chatl_operator_wiadomosc("${sesja}","Tu Anna z obslugi — juz sprawdzam Twoj cennik."); `+
   `global $wpdb; echo "stan=",$wpdb->get_var($wpdb->prepare("SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja=%s","${sesja}")),"\\n";'`,{encoding:'utf8'});
 L('  '+out.trim().replace(/\n/g,' | '));

 L('=== GOSC SIEDZI Z OTWARTYM OKNEM I CZEKA 20 s (nic nie pisze) ===');
 const pollPrzed=poll;
 await p.waitForTimeout(20000);
 const widok=await p.evaluate(new Function('return '+tresc()));
 L('  odpytan REST przez te 20 s: '+(poll-pollPrzed));
 L('  czy gosc zobaczyl "Do rozmowy dolaczyl konsultant"? '+(/konsultant/i.test(widok)?'TAK':'⛔ NIE'));
 L('  czy gosc zobaczyl wiadomosc operatora ("Tu Anna")? '+(/Tu Anna/.test(widok)?'TAK':'⛔ NIE'));
 L('  ostatnie 160 znakow okna: ...'+widok.slice(-160));

 L('=== KONTROLA: gosc pisze DRUGIE pytanie — dopiero to uruchamia odpytywanie ===');
 const pollPrzed2=poll;
 await p.fill('.chatl-pole-pytania','Halo, jest tam kto?');
 await p.click('[aria-label="Wyślij pytanie"]'); await p.waitForTimeout(8000);
 const widok2=await p.evaluate(new Function('return '+tresc()));
 L('  odpytan REST po drugim pytaniu: '+(poll-pollPrzed2)+'   <- >0 = licznik DZIALA (proba kontrolna)');
 L('  teraz widzi konsultanta? '+(/konsultant/i.test(widok2)?'TAK':'NIE'));
 L('  teraz widzi wiadomosc operatora? '+(/Tu Anna/.test(widok2)?'TAK':'NIE'));
 L('  bledy JS: '+JSON.stringify(bledy));
 await b.close();
})().catch(e=>{console.log('PRZYRZAD PADL: '+e.message.slice(0,300));process.exit(2)});
