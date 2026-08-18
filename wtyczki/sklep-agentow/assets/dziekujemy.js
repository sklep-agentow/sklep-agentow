/* Strona „Dziękujemy" — odpytywanie statusu zamówienia po id sesji (bez danych osobowych). */
(function () {
	var kontener = document.querySelector('.sklepag-dziekujemy');
	if (!kontener) { return; }

	var sesja = new URLSearchParams(window.location.search).get('sesja');
	var pole = kontener.querySelector('.sklepag-status') || kontener;
	if (!sesja) {
		pole.textContent = 'Brak identyfikatora zamówienia w adresie.';
		return;
	}

	var proby = 0;
	var opisy = {
		oczekuje: 'Przetwarzamy Twoją płatność… (strona odświeża status sama)',
		oplacone: 'Płatność potwierdzona — przygotowujemy maila z linkiem do pobrania…',
		mail_w_toku: 'Płatność potwierdzona — wysyłamy maila z linkiem do pobrania…',
		mail_wyslany: 'Gotowe! Mail z potwierdzeniem i linkiem do pobrania został wysłany. Sprawdź skrzynkę (także spam).',
		mail_blad: 'Płatność potwierdzona, ale mail nie wyszedł. Właściciel sklepu widzi to w panelu i wyśle go ręcznie.',
		brak: 'Nie znaleziono zamówienia dla tego adresu.'
	};

	function sprawdz() {
		proby += 1;
		fetch((window.sklepagRest || '/wp-json/sklepag/v1/') + 'status?sesja=' + encodeURIComponent(sesja))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				var s = d && d.status ? d.status : 'oczekuje';
				pole.textContent = opisy[s] || opisy.oczekuje;
				if (s === 'mail_wyslany' || s === 'mail_blad' || proby >= 30) { return; }
				setTimeout(sprawdz, 4000);
			})
			.catch(function () {
				if (proby < 30) { setTimeout(sprawdz, 6000); }
			});
	}
	sprawdz();
})();
