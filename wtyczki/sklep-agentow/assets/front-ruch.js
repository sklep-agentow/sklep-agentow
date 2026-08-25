/**
 * Pojawianie się sekcji sklepu przy przewijaniu — spójnie z motywem gospodarza
 * (feedback Dzidka 20.08: „podstrona sklepu nie ma animacji jak reszta strony").
 *
 * ⛔ ZASADA BEZPIECZEŃSTWA: CSS ukrywa sekcje TYLKO gdy jest klasa `sklepag-js`
 * na <html> (dodawana inline w <head> przed renderem) ORAZ klasa `sklepag-ruch`
 * na wyspie (dodawana tutaj). Bez JavaScriptu, przy błędzie skryptu albo przy
 * `prefers-reduced-motion` — treść jest po prostu widoczna. Nic nie znika.
 *
 * Parametry ruchu zmierzone z motywu Automatic AI (volt.js + chunks CSS, 20.08):
 * przesunięcie 26px, opacity 0.3s ease-out, transform 0.5s cubic-bezier.
 * Własny mechanizm zamiast doczepiania się do klas motywu — wtyczka musi działać
 * na KAŻDYM motywie, a cudza klasa `reveal` bez cudzego obserwatora = znikająca treść.
 */
( function () {
	'use strict';

	var wyspy = document.querySelectorAll( '.sklepag-wyspa' );
	if ( ! wyspy.length ) {
		return;
	}

	// Szanujemy ustawienie systemowe „ogranicz ruch" — wtedy zero animacji.
	var bezRuchu = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( bezRuchu || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var obserwator = new IntersectionObserver( function ( wpisy ) {
		for ( var i = 0; i < wpisy.length; i++ ) {
			if ( wpisy[ i ].isIntersecting ) {
				wpisy[ i ].target.classList.add( 'sklepag-widoczne' );
				obserwator.unobserve( wpisy[ i ].target );
			}
		}
	}, {
		// Sekcja pokazuje się, gdy wejdzie kawałkiem w ekran (nie dopiero po całości).
		rootMargin: '0px 0px -8% 0px',
		threshold: 0.05,
	} );

	Array.prototype.forEach.call( wyspy, function ( wyspa ) {
		wyspa.classList.add( 'sklepag-ruch' );
		Array.prototype.forEach.call( wyspa.children, function ( sekcja ) {
			obserwator.observe( sekcja );
		} );
	} );

	// Paralaksa panelu w hero listy (runda stron 25.08): okno jedzie o ułamek wolniej niż
	// strona — tylko zmienna CSS, transform składa się w arkuszu. Pasywny nasłuch + rAF,
	// zero pracy poza pierwszym ekranem. Stoi tu, za tą samą bramką „ogranicz ruch".
	var okno = document.querySelector( '.sklepag-panel-okno' );
	if ( okno ) {
		var czeka = false;
		var przesun = function () {
			czeka = false;
			var y = window.scrollY || window.pageYOffset || 0;
			okno.style.setProperty( '--sklepag-par', Math.min( y * 0.12, 72 ) + 'px' );
		};
		window.addEventListener( 'scroll', function () {
			if ( ! czeka ) {
				czeka = true;
				window.requestAnimationFrame( przesun );
			}
		}, { passive: true } );
	}
}() );

/* ===== Koszyk A (25.08): lightbox galerii + sticky CTA ===== */
(function () {
	'use strict';
	// Lightbox: klik w kafel galerii otwiera pełny obraz; klik/Esc zamyka.
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.sklepag-lightbox');
		if (link) {
			e.preventDefault();
			var tlo = document.createElement('div');
			tlo.className = 'sklepag-lightbox-tlo';
			var img = document.createElement('img');
			img.src = link.getAttribute('href');
			img.alt = (link.querySelector('img') || {}).alt || '';
			tlo.appendChild(img);
			tlo.addEventListener('click', function () { tlo.remove(); });
			document.addEventListener('keydown', function esc(ev) {
				if (ev.key === 'Escape') { tlo.remove(); document.removeEventListener('keydown', esc); }
			});
			document.body.appendChild(tlo);
		}
	});
	// Sticky CTA: pokazuje się po minięciu hero, znika przy widocznym formularzu zakupu.
	var pasek = document.querySelector('.sklepag-sticky');
	var hero = document.querySelector('.sklepag-hero');
	var formularz = document.getElementById('sklepag-kup-formularz');
	if (pasek && hero && 'IntersectionObserver' in window) {
		var heroWidoczne = true, formularzWidoczny = false;
		var przelacz = function () {
			if (!heroWidoczne && !formularzWidoczny) { pasek.removeAttribute('hidden'); }
			else { pasek.setAttribute('hidden', ''); }
		};
		new IntersectionObserver(function (w) { heroWidoczne = w[0].isIntersecting; przelacz(); }).observe(hero);
		if (formularz) {
			new IntersectionObserver(function (w) { formularzWidoczny = w[0].isIntersecting; przelacz(); }).observe(formularz);
		}
	}
})();

/* ===== GALAKTYKA BOTÓW — pole cząstek w hero listy (runda działu stron 25.08) =====
   Własna implementacja w RODZINIE silnika gospodarza (volt.js, hero strony głównej):
   dysk cząstek obracany w 3D + promienie + pył, trzy kolory motywu, smugi ruchu,
   oddychająca poświata, przyciąganie do kursora, adaptacyjna liczba cząstek na słabszej
   maszynie, statyczna klatka przy „ogranicz ruch", pauza poza ekranem i na ukrytej karcie.
   Zero bibliotek. Bez canvasa/JS zostaje cicha poświata CSS (.sklepag-mglawica). */
( function () {
	'use strict';

	var canvas = document.querySelector( 'canvas.sklepag-galaktyka' );
	if ( ! canvas || ! canvas.getContext ) {
		return;
	}
	var ctx = canvas.getContext( '2d' );
	if ( ! ctx ) {
		return;
	}
	var scena = canvas.parentElement;
	var okno  = scena.querySelector( '.sklepag-panel-okno' );
	var mq    = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;
	var bezRuchu = function () { return !! ( mq && mq.matches ); };

	var KOLORY = [ '237, 238, 240', '191, 255, 56', '143, 146, 156' ];
	var DYSK = 0, PROMIEN = 1, PYL = 2;
	var BX = 0.42, BY = -0.87;
	var dpr   = Math.min( window.devicePixelRatio || 1, 1.75 );
	var rdzenie = navigator.hardwareConcurrency || 8;
	var szer = 0, wys = 0, cx = 0, cy = 0, promien = 0;
	var czastki = [], raf = 0, chodzi = false, wEkranie = true;
	var ostatni = 0, czas = 0, klatki = 0, wolne = 0, redukcje = 0, timerRozmiaru = 0;
	var mysz = { x: 0, y: 0, aktywna: false }, smx = 0, smy = 0, poswiataMyszy = 0;

	var szum = function ( x, y, t ) {
		return Math.sin( x * 1.3 + t ) * 0.5 + Math.sin( y * 0.9 - t * 0.7 ) * 0.5;
	};

	function nowyDysk( p, zewnetrzny ) {
		p.tryb = DYSK;
		p.r    = zewnetrzny ? 0.85 + Math.random() * 0.4 : 0.16 + Math.random() * 1.05;
		p.a    = Math.random() * Math.PI * 2;
		p.h    = ( Math.random() - 0.5 ) * ( 0.04 + 0.18 * p.r );
		p.sp   = 0.6 + Math.random() * 0.8;
		p.pr   = 0;
	}

	function zrobCzastki( ile ) {
		czastki = [];
		var pylu = Math.round( ile * 0.14 );
		for ( var i = 0; i < ile; i++ ) {
			var los = Math.random();
			var p = { tryb: DYSK, a: 0, r: 0, h: 0, sp: 1, sz: 0.7 + Math.random() * 1.4, c: los < 0.7 ? 0 : los < 0.88 ? 1 : 2, x: 0, y: 0, pr: 0 };
			if ( i < pylu ) {
				p.tryb = PYL;
				p.x = Math.random() * szer;
				p.y = Math.random() * wys;
				p.sp = 0.3 + Math.random() * 0.7;
				p.sz = 0.5 + Math.random();
			} else {
				nowyDysk( p, false );
			}
			czastki.push( p );
		}
	}

	function docelowo() {
		var ile = Math.round( szer * wys / 1500 );
		if ( szer < 768 ) {
			ile = Math.min( ile, 320 );
		}
		if ( rdzenie <= 4 ) {
			ile *= 0.65;
		}
		ile = Math.max( 260, Math.min( ile, 900 ) );
		if ( bezRuchu() ) {
			ile = Math.min( ile, 260 );
		}
		return Math.round( ile * Math.pow( 0.72, redukcje ) );
	}

	function rozmiar() {
		var rect = canvas.getBoundingClientRect();
		szer = rect.width;
		wys  = rect.height;
		canvas.width  = Math.round( szer * dpr );
		canvas.height = Math.round( wys * dpr );
		ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
		// Środek galaktyki = środek okna panelu (za szkłem), promień od jego większego boku.
		if ( okno ) {
			var o = okno.getBoundingClientRect();
			cx = o.left - rect.left + o.width * 0.5;
			cy = o.top - rect.top + o.height * 0.48;
			promien = Math.max( o.width, o.height ) * 0.62;
		} else {
			cx = szer * 0.66;
			cy = wys * 0.46;
			promien = Math.min( szer * 0.32, wys * 0.42 );
		}
		ctx.fillStyle = 'rgb(8, 9, 11)';
		ctx.fillRect( 0, 0, szer, wys );
	}

	function upewnijCzastki() {
		var cel = docelowo();
		var roznica = Math.abs( czastki.length - cel ) / Math.max( cel, 1 );
		if ( ! czastki.length || roznica > 0.15 ) {
			zrobCzastki( cel );
		}
	}

	function krok( dt ) {
		var scroll = Math.min( ( window.scrollY || 0 ) / Math.max( wys, 1 ), 1 );
		var tempo  = 1 + scroll * 0.9;
		czas += dt * tempo;
		var tx = mysz.aktywna ? ( mysz.x / szer ) * 2 - 1 : 0;
		var ty = mysz.aktywna ? ( mysz.y / wys ) * 2 - 1 : 0;
		smx += ( tx - smx ) * 0.04;
		smy += ( ty - smy ) * 0.04;
		poswiataMyszy += ( ( mysz.aktywna ? 1 : 0 ) - poswiataMyszy ) * 0.05;
		var yaw = czas * 0.035 + smx * 0.22, pitch = -0.52 + smy * 0.1;
		var cosY = Math.cos( yaw ), sinY = Math.sin( yaw ), cosP = Math.cos( pitch ), sinP = Math.sin( pitch );
		var R = promien * ( 1 + scroll * 0.12 );

		// Smugi: półprzezroczyste tło zamiast czyszczenia (jak u gospodarza).
		ctx.fillStyle = 'rgba(8, 9, 11, 0.3)';
		ctx.fillRect( 0, 0, szer, wys );
		var oddech = 0.68 + 0.22 * Math.sin( czas * 0.55 );
		ctx.globalCompositeOperation = 'lighter';
		var g = ctx.createRadialGradient( cx, cy, 0, cx, cy, R * 0.5 );
		g.addColorStop( 0, 'rgba(191, 255, 56, ' + ( 0.18 * oddech ) + ')' );
		g.addColorStop( 1, 'rgba(191, 255, 56, 0)' );
		ctx.fillStyle = g;
		ctx.fillRect( cx - R * 0.5, cy - R * 0.5, R, R );
		g = ctx.createRadialGradient( cx, cy, 0, cx, cy, R * 0.14 );
		g.addColorStop( 0, 'rgba(245, 245, 245, ' + ( 0.12 * oddech ) + ')' );
		g.addColorStop( 1, 'rgba(245, 245, 245, 0)' );
		ctx.fillStyle = g;
		ctx.fillRect( cx - R * 0.14, cy - R * 0.14, R * 0.28, R * 0.28 );
		if ( poswiataMyszy > 0.02 ) {
			g = ctx.createRadialGradient( mysz.x, mysz.y, 0, mysz.x, mysz.y, 150 );
			g.addColorStop( 0, 'rgba(191, 255, 56, ' + ( 0.09 * poswiataMyszy ) + ')' );
			g.addColorStop( 1, 'rgba(191, 255, 56, 0)' );
			ctx.fillStyle = g;
			ctx.fillRect( mysz.x - 150, mysz.y - 150, 300, 300 );
		}

		for ( var i = 0; i < czastki.length; i++ ) {
			var p = czastki[ i ], sx = 0, sy = 0, alfa = 0, roz = p.sz;
			if ( p.tryb === PYL ) {
				p.x += p.sp * dt * 7 * tempo;
				p.y += szum( p.x * 0.004, p.y * 0.004, czas * 0.25 ) * dt * 4;
				if ( p.x > szer + 4 ) { p.x = -4; }
				if ( p.y > wys + 4 ) { p.y = -4; }
				if ( p.y < -4 ) { p.y = wys + 4; }
				sx = p.x; sy = p.y;
				alfa = 0.1 + 0.12 * p.sp;
				roz = p.sz * 0.9;
			} else if ( p.tryb === DYSK ) {
				p.a += dt * p.sp * ( 0.14 + 0.36 / ( p.r + 0.26 ) ) * tempo;
				p.r -= dt * p.sp * 0.042 * ( 1 + 0.5 * szum( p.a, p.r * 3, czas * 0.5 ) ) * tempo;
				p.h += szum( p.r * 4, p.a, czas ) * dt * 0.04;
				if ( p.r < 0.14 ) {
					if ( Math.random() < 0.28 ) { p.tryb = PROMIEN; p.pr = 0; p.x = ( Math.random() - 0.5 ) * 12; }
					else { nowyDysk( p, true ); }
					continue;
				}
				var lx = p.r * Math.cos( p.a ), lz = p.r * Math.sin( p.a ), ly = p.h;
				var x1 = lx * cosY - lz * sinY, z1 = lx * sinY + lz * cosY;
				var y2 = ly * cosP - z1 * sinP, z2 = ly * sinP + z1 * cosP;
				var persp = 1 / ( 1 + z2 * 0.5 );
				sx = cx + x1 * R * persp;
				sy = cy + y2 * R * persp;
				var glebia = Math.max( 0, Math.min( 1, ( z2 + 1 ) / 2 ) );
				var wnetrze = 1 - Math.min( 1, p.r );
				alfa = ( 0.24 + 0.6 * wnetrze ) * ( 1.05 - glebia * 0.65 );
				roz = p.sz * persp * ( 0.72 + wnetrze * 0.55 );
			} else {
				p.pr += dt * ( 0.28 + p.sp * 0.16 ) * tempo;
				if ( p.pr >= 1 ) { nowyDysk( p, true ); continue; }
				var dyst = p.pr * R * 1.45, luk = Math.sin( p.pr * Math.PI ) * 8;
				sx = cx + BX * dyst + -BY * ( p.x + luk * 0.3 );
				sy = cy + BY * dyst + BX * ( p.x + luk * 0.3 );
				alfa = 0.5 * ( 1 - p.pr ) * ( 0.5 + 0.5 * Math.min( 1, p.pr * 6 ) );
				roz = p.sz * ( 1 - p.pr * 0.5 );
			}
			if ( poswiataMyszy > 0.02 && p.tryb !== PYL ) {
				var dx = mysz.x - sx, dy = mysz.y - sy, d2 = dx * dx + dy * dy;
				if ( d2 < 16900 ) {
					var dd = Math.sqrt( d2 ) || 1, k = ( 1 - dd / 130 ) * poswiataMyszy;
					sx += ( dx / dd ) * k * 10;
					sy += ( dy / dd ) * k * 10;
					alfa += k * 0.2;
				}
			}
			if ( alfa <= 0.012 ) { continue; }
			ctx.globalAlpha = Math.min( alfa, 0.85 );
			ctx.fillStyle = 'rgb(' + KOLORY[ p.c ] + ')';
			if ( roz < 1.4 ) {
				ctx.fillRect( sx, sy, roz, roz );
			} else {
				ctx.beginPath();
				ctx.arc( sx, sy, roz * 0.6, 0, Math.PI * 2 );
				ctx.fill();
			}
		}
		ctx.globalAlpha = 1;
		ctx.globalCompositeOperation = 'source-over';
	}

	function statycznie() {
		for ( var i = 0; i < 72; i++ ) { krok( 0.016 ); }
	}

	function klatka( teraz ) {
		if ( ! chodzi ) { return; }
		var dt = Math.min( ( teraz - ostatni ) / 1000, 0.033 );
		ostatni = teraz;
		krok( dt );
		klatki++;
		if ( dt > 0.024 ) { wolne++; }
		if ( klatki >= 110 ) {
			if ( wolne > 40 && redukcje < 2 && czastki.length > 300 ) {
				redukcje++;
				zrobCzastki( docelowo() );
			}
			klatki = 0; wolne = 0;
		}
		raf = window.requestAnimationFrame( klatka );
	}

	function start() {
		if ( chodzi || bezRuchu() || ! wEkranie || document.hidden ) { return; }
		chodzi = true;
		ostatni = performance.now();
		raf = window.requestAnimationFrame( klatka );
	}
	function stop() { chodzi = false; window.cancelAnimationFrame( raf ); }

	function odswiez() {
		rozmiar();
		upewnijCzastki();
		if ( bezRuchu() ) { stop(); statycznie(); } else { start(); }
	}

	scena.classList.add( 'sklepag-galaktyka-on' );
	odswiez();
	if ( ! bezRuchu() ) {
		// Zanim pierwsza klatka zdąży się rozkręcić, dysk już jest widoczny.
		for ( var w = 0; w < 40; w++ ) { krok( 0.016 ); }
	}

	scena.addEventListener( 'mousemove', function ( e ) {
		if ( bezRuchu() ) { return; }
		var rect = canvas.getBoundingClientRect();
		mysz.x = e.clientX - rect.left;
		mysz.y = e.clientY - rect.top;
		mysz.aktywna = true;
	} );
	scena.addEventListener( 'mouseleave', function () { mysz.aktywna = false; } );
	window.addEventListener( 'resize', function () {
		window.clearTimeout( timerRozmiaru );
		timerRozmiaru = window.setTimeout( odswiez, 150 );
	} );
	document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } else { start(); } } );
	if ( 'IntersectionObserver' in window ) {
		new IntersectionObserver( function ( w ) {
			wEkranie = w[ 0 ].isIntersecting;
			if ( wEkranie ) { start(); } else { stop(); }
		}, { threshold: 0.02 } ).observe( scena );
	}
	if ( mq && mq.addEventListener ) {
		mq.addEventListener( 'change', odswiez );
	}
}() );
