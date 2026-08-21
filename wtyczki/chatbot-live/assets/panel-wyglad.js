/* Podglad wygladu widgetu w panelu ustawien: kolory zmieniaja sie od razu,
   a odczyt kontrastu mowi, czy zestaw spelnia WCAG — PRZED zapisem.

   Wzor kontrastu jest tu drugi raz (pierwszy w includes/wyglad.php), bo
   przegladarka nie ma jak zapytac PHP przy kazdym ruchu suwaka. Zeby te dwie
   kopie nie rozjechaly sie po cichu, pilnuje ich test w przegladarce:
   ustawia kolory, czyta liczbe policzona TUTAJ, zapisuje ustawienia
   i porownuje z liczba policzona przez PHP. Rozjazd = test pada. */
(function () {
	'use strict';

	function rgb(kolor) {
		var k = String(kolor).trim().toLowerCase();
		if (/^#[0-9a-f]{3}$/.test(k)) {
			k = '#' + k[1] + k[1] + k[2] + k[2] + k[3] + k[3];
		}
		if (!/^#[0-9a-f]{6}$/.test(k)) { return [0, 0, 0]; }
		return [parseInt(k.substr(1, 2), 16), parseInt(k.substr(3, 2), 16), parseInt(k.substr(5, 2), 16)];
	}

	/* Luminancja wzgledna wg WCAG 2.1 — ten sam wzor co chatl_luminancja(). */
	function luminancja(kolor) {
		var wagi = [0.2126, 0.7152, 0.0722];
		return rgb(kolor).reduce(function (suma, wartosc, i) {
			var c = wartosc / 255;
			c = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			return suma + wagi[i] * c;
		}, 0);
	}

	/* Zaokraglenie do dwoch miejsc TAK SAMO jak round() w PHP, zeby porownanie
	   panelu z serwerem nie potykalo sie o ostatnia cyfre. */
	function kontrast(a, b) {
		var la = luminancja(a), lb = luminancja(b);
		var wynik = (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
		return Math.round(wynik * 100) / 100;
	}

	function kolorTekstuNa(tlo) {
		return kontrast(tlo, chatlWyglad.ciemny) >= kontrast(tlo, chatlWyglad.jasny)
			? chatlWyglad.ciemny : chatlWyglad.jasny;
	}

	function odswiez() {
		var podglad = document.getElementById('chatl-podglad');
		if (!podglad) { return; }

		var glowny = document.getElementById('chatl-kolor-glowny').value;
		var naglowek = document.getElementById('chatl-kolor-naglowka').value;
		var auto = document.getElementById('chatl-tekst-auto').checked;
		var wlasny = document.getElementById('chatl-kolor-tekstu').value;

		var tekstGlowny = auto ? kolorTekstuNa(glowny) : wlasny;
		var tekstNaglowka = auto ? kolorTekstuNa(naglowek) : wlasny;

		/* Podmieniamy TE SAME zmienne CSS, ktorych uzywa widget na froncie —
		   podglad nie ma wlasnych regul, wiec nie moze pokazac czegos innego. */
		podglad.style.setProperty('--chatl-glowny', glowny);
		podglad.style.setProperty('--chatl-glowny-tekst', tekstGlowny);
		podglad.style.setProperty('--chatl-naglowek', naglowek);
		podglad.style.setProperty('--chatl-naglowek-tekst', tekstNaglowka);

		document.getElementById('chatl-kolor-glowny-kod').textContent = glowny;
		document.getElementById('chatl-kolor-naglowka-kod').textContent = naglowek;

		var pary = { glowny: [glowny, tekstGlowny], naglowek: [naglowek, tekstNaglowka] };
		var wiersze = document.querySelectorAll('#chatl-kontrast [data-chatl-para]');
		Array.prototype.forEach.call(wiersze, function (w) {
			var para = pary[w.getAttribute('data-chatl-para')];
			if (!para) { return; }
			var k = kontrast(para[0], para[1]);
			var ok = k >= chatlWyglad.prog;
			w.querySelector('.chatl-kontrast-wartosc').textContent = k.toFixed(2).replace('.', chatlWyglad.sep);
			var ocena = w.querySelector('.chatl-kontrast-ocena');
			ocena.textContent = ok ? chatlWyglad.zgodny : chatlWyglad.niezgod;
			ocena.style.color = ok ? '#00a32a' : '#d63638';
		});
	}

	function start() {
		var pola = ['chatl-kolor-glowny', 'chatl-kolor-naglowka', 'chatl-tekst-auto', 'chatl-kolor-tekstu'];
		var brak = pola.some(function (id) { return !document.getElementById(id); });
		if (brak) { return; }
		pola.forEach(function (id) {
			var e = document.getElementById(id);
			e.addEventListener('input', odswiez);
			e.addEventListener('change', odswiez);
		});
		odswiez();
	}

	if (document.readyState !== 'loading') { start(); } else {
		document.addEventListener('DOMContentLoaded', start);
	}
})();
