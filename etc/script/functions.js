function getRNG(max) {
    return Math.floor(Math.random() * (max));
}

function randomDeckGen(json, is_side_restricted = 0){
    let deck = '';
    let res;
    if (is_side_restricted == 0){
        res = getRNG(2);
    }
    else {
        res = is_side_restricted;
    }
        
    console.log(res)

    if (res == 1) {
        console.log("blufor boi");
        deck += json.Blufor[getRNG(21)];
    }
    else {
        console.log("redfor boi");
        deck += json.Redfor[getRNG(13)];
    }
        
    console.log(deck);

    deck += ' ' + json.DeckSpec[getRNG(6)];

    return deck;
}

$(function(){
    let deck = "";
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

    $("#roll_dice_button").click(function(){
        num = getRNG(6) + 1
        document.getElementById("output").innerHTML = deck;
    });

    

});