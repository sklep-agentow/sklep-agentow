/**
 * Pojawianie się sekcji sklepu przy przewijaniu — spójnie z motywem gospodarza
 * (feedback z przeglądu 20.08: „podstrona sklepu nie ma animacji jak reszta strony").
 *
 * ⛔ ZASADA BEZPIECZEŃSTWA: CSS ukrywa sekcje TYLKO gdy na <html> jest klasa `sklepag-js`
 * (inline w <head>, przed renderem — warunkowo: IntersectionObserver + brak „ogranicz
 * ruch"). Ten skrypt tylko ODSŁANIA i zgłasza gotowość klasą `sklepag-ruch-ok`; gdy jej
 * nie zgłosi w 2,5 s (nie dojechał, padł), inline dodaje `sklepag-odslon` i CSS pokazuje
 * wszystko. Bez JavaScriptu klasa nie powstaje i nic nie jest ukryte. Nic nie znika.
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
		Array.prototype.forEach.call( wyspa.children, function ( sekcja ) {
			obserwator.observe( sekcja );
		} );
	} );
	// Gotowość: bezpiecznik z <head> (sklepag-odslon po 2,5 s) odpala tylko BEZ tej klasy.
	document.documentElement.classList.add( 'sklepag-ruch-ok' );

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
