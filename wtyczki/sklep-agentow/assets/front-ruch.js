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
		// Elementy z własnym wejściem WEWNĄTRZ długiej sekcji (fazy galerii) — obserwowane osobno,
		// żeby ich kaskada odpalała, gdy same wchodzą w ekran, a nie razem z górą sekcji.
		Array.prototype.forEach.call( wyspa.querySelectorAll( '.sklepag-obserwuj' ), function ( element ) {
			obserwator.observe( element );
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
	var bezRuchu = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Nagrania w kaflach faz: plakat (stop-klatka) gaśnie dopiero, gdy wideo NAPRAWDĘ gra —
	// obraz jest od pierwszej klatki, bez „suchego" startu. Przy „ogranicz ruch" nagranie
	// stoi na plakacie: zero ruchu, o który użytkownik nie prosił.
	Array.prototype.forEach.call(document.querySelectorAll('.sklepag-kafel--wideo video'), function (wideo) {
		var kafel = wideo.closest('.sklepag-kafel');
		if (bezRuchu) {
			wideo.removeAttribute('autoplay');
			wideo.pause();
			return;
		}
		var gra = function () {
			if (kafel) { kafel.classList.add('sklepag-kafel--gra'); kafel.classList.remove('sklepag-kafel--zablokowane'); }
		};
		wideo.addEventListener('playing', gra);
		if (!wideo.paused && wideo.readyState >= 3) { gra(); }
		// Przeglądarka z blokadą autoodtwarzania (np. Brave) odrzuca play() bez gestu — wtedy plakat
		// zostaje, a kafel dostaje przycisk „Odtwórz" (klik = gest użytkownika). Próba, gdy kafel
		// wjeżdża w kadr (Chromium i tak pauzuje autoplay poza kadrem).
		var sprobuj = function () {
			var obietnica = wideo.play();
			if (obietnica && obietnica.catch) {
				obietnica.catch(function () { if (kafel) { kafel.classList.add('sklepag-kafel--zablokowane'); } });
			}
		};
		var wKadrze = false;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (w) {
				wKadrze = w[0].isIntersecting;
				if (wKadrze && wideo.paused) { sprobuj(); }
			}, { threshold: 0.25 }).observe(wideo);
		} else {
			wKadrze = true;
			sprobuj();
		}
		// Strażnik: nagranie W KADRZE ma iść do przodu. Stoi (blokada bez odrzucenia play(), zacięcie
		// ładowania) → przycisk „Odtwórz", JEDNA ponowna próba i STOP timera — dalej rusza je tylko
		// gest użytkownika (bez clearInterval play() leciał co 2 s bez końca przy blokadzie
		// autoodtwarzania). Timer wraca, gdy nagranie naprawdę gra (`playing`). Poza kadrem
		// Chromium pauzuje autoplay samo — to nie blokada.
		var ostatniCzas = -1;
		var straznik = null;
		var zatrzymajStraznika = function () {
			if (straznik !== null) { window.clearInterval(straznik); straznik = null; }
		};
		var uruchomStraznika = function () {
			if (straznik !== null) { return; }
			ostatniCzas = -1;
			straznik = window.setInterval(function () {
				if (!wKadrze) { ostatniCzas = -1; return; }
				var stoi = wideo.paused || wideo.currentTime === ostatniCzas;
				ostatniCzas = wideo.currentTime;
				if (!stoi) {
					if (kafel) { kafel.classList.remove('sklepag-kafel--zablokowane'); }
					return;
				}
				zatrzymajStraznika();
				if (kafel) { kafel.classList.add('sklepag-kafel--zablokowane'); }
				sprobuj();
			}, 2000);
		};
		wideo.addEventListener('playing', uruchomStraznika);
		uruchomStraznika();
		if (kafel) {
			kafel.addEventListener('click', function (e) {
				if (kafel.classList.contains('sklepag-kafel--zablokowane') || wideo.paused) {
					e.preventDefault();
					sprobuj();
				}
			});
		}
	});

	// Lightbox: dialog z pełnym ekranem, podpisem z kafla i przyciskiem zamknięcia;
	// Esc / klik w tło albo obraz zamyka; strona pod spodem nie przewija się; fokus wraca do kafla.
	var otworzLightbox = function (link) {
		var maly = link.querySelector('img');
		var podpis = link.getAttribute('data-podpis') || '';
		var tlo = document.createElement('div');
		tlo.className = 'sklepag-lightbox-tlo';
		tlo.setAttribute('role', 'dialog');
		tlo.setAttribute('aria-modal', 'true');
		tlo.setAttribute('aria-label', podpis || 'Powiększony zrzut');
		var zamknij = document.createElement('button');
		zamknij.type = 'button';
		zamknij.className = 'sklepag-lightbox-zamknij';
		zamknij.setAttribute('aria-label', 'Zamknij powiększenie');
		zamknij.textContent = '×';
		var ramka = document.createElement('figure');
		ramka.className = 'sklepag-lightbox-ramka';
		var duzy = document.createElement('img');
		duzy.src = link.getAttribute('href');
		duzy.alt = (maly && maly.alt) || '';
		ramka.appendChild(duzy);
		if (podpis) {
			var opis = document.createElement('figcaption');
			opis.className = 'sklepag-mono-etykieta';
			opis.textContent = podpis;
			ramka.appendChild(opis);
		}
		tlo.appendChild(zamknij);
		tlo.appendChild(ramka);
		var naKlawisz = function (ev) { if (ev.key === 'Escape') { sprzatnij(); } };
		var sprzatnij = function () {
			tlo.remove();
			document.documentElement.classList.remove('sklepag-lightbox-otwarty');
			document.removeEventListener('keydown', naKlawisz);
			link.focus();
		};
		tlo.addEventListener('click', function (ev) {
			if (ev.target === tlo || ev.target === zamknij || ev.target === duzy) { sprzatnij(); }
		});
		document.addEventListener('keydown', naKlawisz);
		document.documentElement.classList.add('sklepag-lightbox-otwarty');
		document.body.appendChild(tlo);
		zamknij.focus();
	};
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.sklepag-lightbox');
		if (link) {
			e.preventDefault();
			otworzLightbox(link);
		}
	});
	// Sticky CTA: pokazuje się po minięciu hero, znika przy widocznym formularzu zakupu.
	var pasek = document.querySelector('.sklepag-sticky');
	var hero = document.querySelector('.sklepag-hero');
	var formularz = document.getElementById('sklepag-kup-formularz');
	if (pasek && hero && 'IntersectionObserver' in window) {
		// `hidden` schodzi raz (bez JS pigułki nie ma wcale); dalej steruje KLASA stanu, żeby CSS mógł
		// zrobić wjazd z dołu i zjazd z wygaszeniem zamiast przeskoku display (przegląd na żywo 25.08).
		pasek.removeAttribute('hidden');
		var heroWidoczne = true, formularzWidoczny = false;
		var przelacz = function () {
			pasek.classList.toggle('sklepag-sticky--widoczna', !heroWidoczne && !formularzWidoczny);
		};
		new IntersectionObserver(function (w) { heroWidoczne = w[0].isIntersecting; przelacz(); }).observe(hero);
		if (formularz) {
			new IntersectionObserver(function (w) { formularzWidoczny = w[0].isIntersecting; przelacz(); }).observe(formularz);
		}
	}
})();
