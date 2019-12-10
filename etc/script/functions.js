function getRNG(max) {
    /**
     * Generate a random integer from 0 to (max-1)
     */
    return Math.floor(Math.random() * (max));
}

function randomDeckGen(json, is_side_restricted = 0){
    /**
     * Generate a random deck combination, faction side restriction is false by default
     */

    let deck = '';
    // Need to define what side the deck will be 
    let side;

    // if the side restriction is false, randomly decide the deck's faction within full range
    if (is_side_restricted == 0){
        side = getRNG(2);
    }
    // if the side restriction is on, force the side to be the parameter defined, 1 = blue, 2 = red
    else {
        side = is_side_restricted - 1;
    }
        
    console.log(side)

    // randomly select the nation from selected range
    if (side == 1) {
        console.log("blufor boi");
        deck += json.Blufor[getRNG(21)];
    }
    else {
        console.log("redfor boi");
        deck += json.Redfor[getRNG(13)];
    }
        
    console.log(deck);

    // add the random deck spec after the nation
    deck += ' ' + json.DeckSpec[getRNG(6)];

    return deck;
}

$(function(){
    /**
     * Jquery stuff
     * 
     * Mainly used for button interaction
     */
    
    // Initialize deck
    let deck = "";

    // different button yield different number of random deck
    $("#gen_button1").click(function(){
        $.getJSON("etc/data/majsoul_result.json", function(json){
            deck = randomDeckGen(json);
            document.getElementById("output").innerHTML = deck;
        });
    });
    

    $("#gen_button2").click(function(){
        $.getJSON("etc/data/majsoul_result.json", function(json){
            deck = randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json);
            document.getElementById("output").innerHTML = deck;
        });
    });

    
    $("#gen_button3").click(function(){
        $.getJSON("etc/data/majsoul_result.json", function(json){
            deck = randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json) + 
            "<br>" + randomDeckGen(json);
            document.getElementById("output").innerHTML = deck;
        });
    });


    // Dice rolling button
    $("#roll_dice_button").click(function(){
        num = getRNG(6) + 1;
        document.getElementById("output").innerHTML = num;
    });

    

});