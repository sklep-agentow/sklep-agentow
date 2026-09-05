/**
 * Okno rozmowy w panelu operatora: dociąganie nowych wiadomości.
 *
 * Ten sam problem co u gościa (WordPress nie ma gniazd), ale INNY model dostępu:
 * tu autoryzuje uprawnienie `manage_options` i nonce, a nie sekret sesji.
 * Dlatego osobny punkt AJAX, a nie gałąź „jeśli admin" w publicznym REST.
 *
 * Rytm: 5 sekund, bo to okno jest otwarte tylko wtedy, gdy operator faktycznie
 * rozmawia — czyli w chwili, gdy sekundy mają znaczenie. Zamknięcie karty
 * zatrzymuje odpytywanie, schowana karta zwalnia je do 30 s.
 */
(function () {
	'use strict';

	var korzen = document.getElementById('chatl-rozmowa');
	if (!korzen || !window.chatlPanel) { return; }

	var sesja = korzen.getAttribute('data-sesja');
	var ostatnieId = 0;
	var timer = null;

	/* Startowe id: najwyzsze juz wyrenderowane przez PHP, zeby nie dublowac. */
	korzen.querySelectorAll('[data-id]').forEach(function (w) {
		ostatnieId = Math.max(ostatnieId, parseInt(w.getAttribute('data-id'), 10) || 0);
	});

	var ETYKIETY = { gosc: 'Gość', bot: 'Bot', operator: 'Ty', system: 'System' };

	function dopisz(w) {
		var wiersz = document.createElement('div');
		wiersz.className = 'chatl-panel-wiadomosc chatl-panel-' + w.rola;
		wiersz.setAttribute('data-id', w.id);

		var kto = document.createElement('span');
		kto.className = 'chatl-panel-kto';
		kto.textContent = ETYKIETY[w.rola] || w.rola;

		var tresc = document.createElement('span');
		tresc.className = 'chatl-panel-tresc';
		/* textContent, nie innerHTML: tresc pochodzi od GOSCIA, czyli z wejscia
		   niezaufanego. W panelu administratora XSS jest grozniejszy niz na froncie. */
		tresc.textContent = w.tresc;

		var czas = document.createElement('span');
		czas.className = 'chatl-panel-czas';
		czas.textContent = w.czas;

		wiersz.appendChild(kto);
		wiersz.appendChild(tresc);
		wiersz.appendChild(czas);
		korzen.appendChild(wiersz);
		korzen.scrollTop = korzen.scrollHeight;
	}

	function odpytaj() {
		var body = new FormData();
		body.append('action', 'chatl_panel_rozmowa');
		body.append('_ajax_nonce', window.chatlPanel.nonce);
		body.append('sesja', sesja);
		body.append('od_id', ostatnieId);

		fetch(window.chatlPanel.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (o) { return o.json(); })
			.then(function (o) {
				/* Nonce panelu zyje 12-24 h i NIE odnawia sie sam: po tym czasie WP odpowiada `-1`
				   (403) i panel gluchl po cichu — operator widzial stara rozmowe i nic nowego.
				   Odswiezenie strony daje swiezy nonce; komunikat mowi, dlaczego. */
				if (-1 === o || (o && false === o.success)) {
					var uwaga = document.createElement('div');
					uwaga.className = 'notice notice-warning';
					uwaga.textContent = 'Sesja panelu wygasła — odświeżam stronę, żeby dalej odbierać wiadomości.';
					korzen.parentNode.insertBefore(uwaga, korzen);
					window.setTimeout(function () { window.location.reload(); }, 2000);
					return;
				}
				if (o && o.success && o.data) {
					(o.data.nowe || []).forEach(function (w) {
						ostatnieId = Math.max(ostatnieId, w.id);
						dopisz(w);
					});
				}
				zaplanuj();
			})
			.catch(function () { zaplanuj(30); });
	}

	function zaplanuj(sekundy) {
		if (timer) { window.clearTimeout(timer); }
		var s = sekundy || (document.visibilityState === 'hidden' ? 30 : 5);
		timer = window.setTimeout(odpytaj, s * 1000);
	}

	document.addEventListener('visibilitychange', function () {
		if ('visible' === document.visibilityState) { odpytaj(); }
	});

	zaplanuj();
}());
