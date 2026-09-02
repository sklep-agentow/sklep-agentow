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
	/* ⛔ TA TABLICA MUSI ZNAC KAZDY STATUS, KTORY POTRAFI ODDAC `sklepag/v1/status`.
	   Endpoint oddaje kolumne `zamowienia.status` WPROST, a kod pisze do niej takze
	   `zwrocone` i `sporne` (obsluga zwrotu i obciazenia zwrotnego w zakup.php).
	   Tych dwoch kluczy tu nie bylo, wiec kupujacy PO ZWROCIE PIENIEDZY czytal
	   „Przetwarzamy Twoja platnosc…" — i czytal to dalej, bo warunek konca odpytywania
	   tez ich nie znal. */
	var opisy = {
		oczekuje: 'Przetwarzamy Twoją płatność… (strona odświeża status sama)',
		oplacone: 'Płatność potwierdzona — przygotowujemy maila z linkiem do pobrania…',
		mail_w_toku: 'Płatność potwierdzona — wysyłamy maila z linkiem do pobrania…',
		mail_wyslany: 'Gotowe! Mail z potwierdzeniem i linkiem do pobrania został wysłany. Sprawdź skrzynkę (także spam).',
		mail_blad: 'Płatność potwierdzona, ale mail nie wyszedł. Właściciel sklepu widzi to w panelu i wyśle go ręcznie.',
		/* Od 0.9.37 brama pobran (`pobieranie.php`) ODMAWIA przy statusach `zwrocone` i `sporne`
		   (410), wiec oba zdania mowia o martwym linku wprost — kupujacy nie ma klikac w link z maila
		   i dopiero tam czytac, ze przestal dzialac. Do 0.9.36 brama statusu nie sprawdzala i zdania
		   celowo o linku milczaly. */
		zwrocone: 'Płatność za to zamówienie została zwrócona — nie jest już opłacone, a link do pobrania z maila przestał działać. Jeśli to pomyłka, odpisz na maila z potwierdzeniem zakupu.',
		sporne: 'Ta płatność jest reklamowana w banku i czeka na rozstrzygnięcie — do tego czasu link do pobrania nie działa. Jeśli to pomyłka, odpisz na maila z potwierdzeniem zakupu.',
		brak: 'Nie znaleziono zamówienia dla tego adresu.',
		/* ⛔ Wczesniej NIEZNANY status dostawal zdanie `oczekuje` — czyli kazdy status
		   dopisany w przyszlosci wracalby z ta sama wada. Zdanie zapasowe ma NIE TWIERDZIC
		   niczego o pieniadzach. */
		nieznany: 'Status tego zamówienia jest nietypowy. Sprawdź maila z potwierdzeniem zakupu albo odpisz na niego — właściciel sklepu widzi to zamówienie w panelu.'
	};
	/* Statusy KONCOWE: dalsze odpytywanie niczego nie zmieni, a strona odswiezajaca sie
	   przez dwie minuty po zwrocie wyglada, jakby cos jeszcze trwalo. */
	var koncowe = ['mail_wyslany', 'mail_blad', 'zwrocone', 'sporne'];

	function sprawdz() {
		proby += 1;
		fetch((window.sklepagRest || '/wp-json/sklepag/v1/') + 'status?sesja=' + encodeURIComponent(sesja))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				var s = d && d.status ? d.status : 'oczekuje';
				pole.textContent = opisy[s] || opisy.nieznany;
				if (koncowe.indexOf(s) !== -1 || proby >= 30) { return; }
				setTimeout(sprawdz, 4000);
			})
			.catch(function () {
				if (proby < 30) { setTimeout(sprawdz, 6000); }
			});
	}
	sprawdz();
})();
