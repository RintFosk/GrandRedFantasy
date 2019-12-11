const sideENUM = {
	ALL_SIDE : 0,
	BLUFOR   : 1,
	REDFOR   : 2
};

function getRNG(max) {
	/**
     * Generate a random integer from 1 to max
     */
	return Math.floor(Math.random() * max) + 1;
}

function randomDeckGen(json, side_restriction = sideENUM.ALL_SIDE) {
	/**
     * Generate a random deck combination, faction side restriction is false by default
     */

	let deck = '';
	// Need to define what side the deck will be
	let side;

	// if the side restriction is false, randomly decide the deck's faction within full range
	if (side_restriction == sideENUM.ALL_SIDE) {
		side = getRNG(2);
	}
	else {
		// if the side restriction is on, force the side to be the parameter defined, 1 = blue, 2 = red
		side = side_restriction;
	}

	console.log(side);

	// randomly select the nation from selected range
	switch (side) {
		case sideENUM.BLUFOR:
			console.log('blufor boi');
			deck += json.Blufor[getRNG(21) - 1];
			break;

		case sideENUM.REDFOR:
			console.log('redfor boi');
			deck += json.Redfor[getRNG(13) - 1];
			break;
	}

	console.log(deck);

	// add the random deck spec after the nation
	deck += ' ' + json.DeckSpec[getRNG(6) - 1];

	return deck;
}

$(function() {
	/**
     * Jquery stuff
     * 
     * Mainly used for button interaction
     */

	// Initializing variables
	let deck = '';
	let side_restriction = 0;

	// check the states of the side restriction toggle switch
	$('#tgSwitch_blufor').on('change', function() {
		switch ($(this).is(':checked')) {
			case true:
				side_restriction = 1;
				alert('blufor restriction is applied');
				break;

			case false:
				alert('false');
				break;
		}
	});

	// different button yield different number of random deck
	$('#gen_button1').click(function() {
		$.getJSON('etc/data/majsoul_result.json', function(json) {
			deck = randomDeckGen(json);
			document.getElementById('output').innerHTML = deck;
		});
	});

	$('#gen_button2').click(function() {
		$.getJSON('etc/data/majsoul_result.json', function(json) {
			deck = randomDeckGen(json) + '<br>' + randomDeckGen(json) + '<br>' + randomDeckGen(json);
			document.getElementById('output').innerHTML = deck;
		});
	});

	$('#gen_button3').click(function() {
		$.getJSON('etc/data/majsoul_result.json', function(json) {
			deck =
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json) +
				'<br>' +
				randomDeckGen(json);
			document.getElementById('output').innerHTML = deck;
		});
	});

	// Dice rolling button
	$('#roll_dice_button').click(function() {
		num = getRNG(6);
		document.getElementById('output').innerHTML = num;
	});
});
