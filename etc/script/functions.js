const sideENUM = {
	BLUFOR: 0,
	REDFOR: 1,
	ALL_SIDE: 2
};

const eraENUM = {
	ERA_A: 0, // 1990
	ERA_B: 1, // 1985
	ERA_C: 2, // 1980
	ALL_ERA: 3
};

function getRNG(max) {
	/**
	 * Generate a random integer from 0 to max - 1
	 */
	return Math.floor(Math.random() * max);
}

function deckConfGen(json, side_restriction = sideENUM.ALL_SIDE, era_restriction = eraENUM.ALL_ERA) {
	/**
	 * Generate a random deck configuration combination, faction side restriction is false by default
	 * Contains League/Nation, Specialization and the deck era
	 */

	// initialize the three conf components
	let faction = "";
	let spec = "";
	let era = -1;

	// Need to define what side the deck will be
	let side;

	// if the ERA restriction is false, randomly decide the deck's era within full range
	if (era_restriction == eraENUM.ALL_ERA) {
		era = getRNG(3);
	} else {
		// if the side restriction is on, force the side to be the parameter defined
		era = era_restriction;
	}

	// if the side restriction is false, randomly decide the deck's faction within full range
	if (side_restriction == sideENUM.ALL_SIDE) {
		side = getRNG(2);
	} else {
		// if the side restriction is on, force the side to be the parameter defined
		side = side_restriction;
	}

	// randomly select the faction from selected range
	switch (side) {
		case sideENUM.BLUFOR:
			faction = json.Blufor[getRNG(21)];
			break;

		case sideENUM.REDFOR:
			faction = json.Redfor[getRNG(13)];
			break;
	}

	// add the random deck spec after the nation
	spec = json.DeckSpec[getRNG(6)];

	return [faction, spec, era];
}


function deckConfWrap(confArray) {
	output = confArray[0] + ' ' + confArray[1];

	switch (confArray[2]) {
		case eraENUM.ERA_A:
			output += ' 1990';
			break;
		case eraENUM.ERA_B:
			output += ' 1985';
			break;
		case eraENUM.ERA_C:
			output += ' 1980';
			break;
	}

	output += "<br>";

	return output;
}


$(function () {
	/**
	 * Jquery stuff
	 * 
	 * Mainly used for button interaction
	 */

	// Initializing variables
	let deck = '';
	let side_restriction = 0;

	// check the states of the side restriction toggle switch
	$('#tgSwitch_blufor').on('change', function () {
		if ($('#tgSwitch_redfor').is(':checked')) {
			$('#tgSwitch_redfor').prop('checked', false);
		}
		switch ($(this).is(':checked')) {
			case true:
				side_restriction = 0;
				break;

			case false:
				side_restriction = 2;
				break;
		}
	});

	$('#tgSwitch_redfor').on('change', function () {
		if ($('#tgSwitch_blufor').is(':checked')) {
			$('#tgSwitch_blufor').prop('checked', false);
		}
		switch ($(this).is(':checked')) {
			case true:
				side_restriction = 1;
				break;

			case false:
				side_restriction = 2;
				break;
		}
	});

	// different button yield different number of random deck
	$('#gen_button1').click(function () {
		$.getJSON('etc/data/faction_and_deckSpec.json', function (json) {
			deck = deckConfWrap(deckConfGen(json, side_restriction));
			document.getElementById('output').innerHTML = deck;
			deck = ""
		});
	});

	$('#gen_button2').click(function () {
		$.getJSON('etc/data/faction_and_deckSpec.json', function (json) {
			for (i = 0; i < 3; i++) {
				deck += deckConfWrap(deckConfGen(json, side_restriction));
			}
			document.getElementById('output').innerHTML = deck;
			deck = ""
		});
	});

	$('#gen_button3').click(function () {
		$.getJSON('etc/data/faction_and_deckSpec.json', function (json) {
			for (i = 0; i < 10; i++) {
				deck += deckConfWrap(deckConfGen(json, side_restriction));
			}
			document.getElementById('output').innerHTML = deck;
			deck = ""
		});
	});

	// Dice rolling button
	$('#roll_dice_button').click(function () {
		num = getRNG(6) + 1;
		document.getElementById('output').innerHTML = num;
	});
});