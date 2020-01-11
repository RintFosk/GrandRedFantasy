const sideENUM = {
	BLUFOR: 0,
	REDFOR: 1,
	ALL_SIDE: 2
};

const eraENUM = {
	ERA_A: 0, // all era
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

function deckConfGen(jsonDb, side_restriction = sideENUM.ALL_SIDE, era_restriction = eraENUM.ALL_ERA) {
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
		era = jsonDb.Era[getRNG(3)];
	} else {
		// if the side restriction is on, force the side to be the parameter defined
		era = jsonDb.Era[era_restriction];
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
			faction = jsonDb.Blufor[getRNG(21)];
			break;

		case sideENUM.REDFOR:
			faction = jsonDb.Redfor[getRNG(13)];
			break;
	}

	// add the random deck spec after the nation
	while (true) {
		spec = jsonDb.DeckSpec[getRNG(7)];
		if (spec == "Marine" && jsonDb.NO_MARINE.includes(faction)) {
			continue;
		} else if (spec == "Airborne" && jsonDb.NO_AIRBORNE.includes(faction)) {
			continue;
		} else if (spec == "Motorised" && jsonDb.NO_MOTOR.includes(faction)) {
			continue;
		} else {
			break;
		}
	}


	return [faction, spec, era];
}


function getRandomDecks(jsonDb, side_restriction = sideENUM.ALL_SIDE, era_restriction = eraENUM.ALL_ERA, amount = 1) {
	/**
	 * get certain number of the random decks
	 */
	let deck_output = "";
	let deckConf_set = [];
	for (i = 0; i < amount; i++) {
		deck = deckConfGen(jsonDb, side_restriction, era_restriction);
		deck_obj = {
			faction: deck[0],
			spec: deck[1],
			era: deck[2]
		};
		deck_output += deckConfWrap(deck);
		deckConf_set.push(deck_obj);
	}

	document.getElementById('output').innerHTML = deck_output;

	return deckConf_set;
}


function onUniqBtnPressed(mainButton, otherButtons) {
	/**
	 * Action which change all the other buttons to unselected state when the main button is pressed
	 */
	if (mainButton.is(':checked')) {
		otherButtons.forEach(function (element) {
			element.prop('checked', false);
		});
	}
}


function deckConfWrap(confArray) {
	/**
	 * A wrapper/prettifier for the generated random deck conf 
	 */
	let output = confArray[0] + ' ' + confArray[1] + ' ' + confArray[2] + "<br>";
	return output;
}


function requestRandomDeck(deckConf_set) {
	// deck generating message
	let loadingMsg = 'Generating deck...';
	let deckCode = "";
	let deckInfoHTML = "";

	console.log(deckConf_set);
	document.getElementById('output').innerHTML = loadingMsg;

	// objectify the form's input
	let package = {
		'deckConf_set': deckConf_set,
		'action': 'deckGen'
	};
	console.log(package);

	$.ajax({
		url: '/etc/script/main.php',
		data: package,
		type: 'post',
		success: function (response) {
			document.getElementById('output').innerHTML = response;
		}
	});
}


$(function () {
	/**
	 * Jquery stuff
	 * 
	 * Mainly used for button interaction
	 */

	// Initializing variables
	let side_restriction = 2;
	let era_restriction = 3;

	// Initializing the json objects
	$.getJSON('etc/data/faction_and_deckSpec.json', function (json) {
		console.log(json);
		faction_and_deckSpec_json = json;
	});

	// check the states of the side restriction toggle switch
	$('#tgSwitch_blufor').on('change', function () {
		onUniqBtnPressed($('#tgSwitch_blufor'), [$('#tgSwitch_redfor')]);
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
		onUniqBtnPressed($('#tgSwitch_redfor'), [$('#tgSwitch_blufor')]);
		switch ($(this).is(':checked')) {
			case true:
				side_restriction = 1;
				break;

			case false:
				side_restriction = 2;
				break;
		}
	});


	// event listener on click of the era restriction toogle box
	$('#tgSwitch_EraA').on('change', function () {
		onUniqBtnPressed($('#tgSwitch_EraA'), [$('#tgSwitch_EraB'), $('#tgSwitch_EraC')]);
		switch ($(this).is(':checked')) {
			case true:
				era_restriction = 0;
				break;

			case false:
				era_restriction = 3;
				break;
		}
	});

	// event listener on click of the era restriction toogle box
	$('#tgSwitch_EraB').on('change', function () {
		onUniqBtnPressed($('#tgSwitch_EraB'), [$('#tgSwitch_EraA'), $('#tgSwitch_EraC')]);
		switch ($(this).is(':checked')) {
			case true:
				era_restriction = 1;
				break;

			case false:
				era_restriction = 3;
				break;
		}
	});

	// event listener on click of the era restriction toogle box
	$('#tgSwitch_EraC').on('change', function () {
		onUniqBtnPressed($('#tgSwitch_EraC'), [$('#tgSwitch_EraA'), $('#tgSwitch_EraB')]);
		switch ($(this).is(':checked')) {
			case true:
				era_restriction = 2;
				break;

			case false:
				era_restriction = 3;
				break;
		}
	});

	// different button yield different number of random deck
	$('#gen_button1').click(function () {
		let deckConf_set = getRandomDecks(faction_and_deckSpec_json, side_restriction, era_restriction, 1);
		requestRandomDeck(deckConf_set);
	});

	$('#gen_button2').click(function () {
		let deckConf_set = getRandomDecks(faction_and_deckSpec_json, side_restriction, era_restriction, 3);
		requestRandomDeck(deckConf_set);
	});

	$('#gen_button3').click(function () {
		let deckConf_set = getRandomDecks(faction_and_deckSpec_json, side_restriction, era_restriction, 10);
		requestRandomDeck(deckConf_set);
	});


	// Dice rolling button
	$('#roll_dice_button').click(function () {
		let num = getRNG(6) + 1;
		document.getElementById('output').innerHTML = num;
	});
});