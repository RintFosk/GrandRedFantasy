<?php

/**
 * A switcher that execute corresponding functions when called by other process
 */
if (isset($_POST['action']) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {

        case 'getCardData':
            $deckConf = $_POST['deck_conf'];
            $deckFrame = loadFrame($deckConf);
            break;


        case 'getTransportRel':
    }
}


function loadFrame($deckConf)
{
    /**
     * Load the deck frame base on the deck configuration
     */
    $url = dirname(__DIR__) . '/data/deck_spec_distribution.json';
    $frameDB = json_decode(file_get_contents($url), true);
    $frame = $frameDB[$deckConf['spec']];

    return $frame;
}


function UnitCardQueryFilling($deckConf)
{
    /**
     * Assemble the SQL query code corresponding to the deck configuration 
     * to retrieve the matched card data from the database
     */

    // Import the translation table from the json file
    $url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
    $jsonDb = json_decode(file_get_contents($url), true);

    // Extract the deck configuration info
    $faction = $deckConf['faction'];
    $spec = $deckConf['spec'];
    $year = $deckConf['era'];

    // Add the start of all unit card fetch query sql code
    $sql = "SELECT * FROM UNIT_CARD WHERE ";

    // ============ FACTION CONFIGURATION ============
    // if it is NATO or PACT deck
    if (in_array($faction, $jsonDb['ALLIES'])) {
        switch ($faction) {
            case "NATO":
                $sql .= "LEAGUE = 'BLU' and ";
                break;
            case "PACT":
                $sql .= "LEAGUE = 'RED' and ";
                break;
        }
    }
    // if it is a League (composition of multiple nation)
    elseif (in_array($faction, $jsonDb['LEAGUES'])) {
        $sql .= '(';
        foreach ($jsonDb['TranslateTable'][$faction] as $nation) {
            $sql .= "Nation = '{$nation}' or ";
        }
        $sql = substr($sql, 0, -4);
        $sql .= ') and ';
    }
    // if it is Nation
    elseif (in_array($faction, $jsonDb['NATIONS'])) {
        $addition = $jsonDb['TranslateTable'][$faction];
        $sql .= "Nation = '{$addition}' and ";
    } else {
        echo "error, unexpected faction";
    }

    // ============ SPEC CONFIGURATION ============
    // if the spec is not general deck
    if ($spec != "" and in_array($spec, $jsonDb['DeckSpec'])) {
        $sql .= "Spec_{$jsonDb['TranslateTable'][$spec]} is true and ";
    }

    // ============ YEAR CONFIGURATION ============
    if ($year != "") {
        $sql .= "year <= {$year} and ";
    }

    // ============ TRIMMING ============
    // trim off the 'and ' end of the assembled sql code and add the valid end ';'
    $sql = substr($sql, 0, -5);
    $sql .= ';';

    return $sql;
}


function trspQueryFilling($baseQ)
{
    $baseQ_cut = substr($baseQ, 30, -1);
    $sql = "SELECT b.* 
            FROM UNIT_CARD as a INNER JOIN UNIT_TRANSPORT_REL as b
            ON b.LEAGUE = a.LEAGUE and b.CARD_ID = a.CARD_ID
            WHERE {$baseQ_cut} and
                b.TRANSPORT_ID in (SELECT CARD_ID FROM UNIT_CARD
                WHERE {$baseQ_cut})";


    return $sql;
}


function formatTrspDb($trspDb)
{
    /**
     * Format the raw transport relation data into the usable format 
     */
    $formatted = array();
    $currentID = '';
    foreach ($trspDb as $trspRel) {
        // if the ID is encountered first time
        if ($currentID != $trspRel["CARD_ID"]) {
            $currentID = $trspRel["CARD_ID"];
            $formatted[$currentID] = array($trspRel["TRANSPORT_ID"]);
        }
        // if the ID is encountered
        else {
            array_push($formatted[$currentID], $trspRel["TRANSPORT_ID"]);
        }
    }

    return $formatted;
}


function sql_fetch($conn, $query)
{
    /**
     * from given query and mysql connection, fetch the data and convert the data into php array format
     */

    // Create an array object to contain the data received from the mysql database
    $db = array();

    // Send the query through the connection
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        // if the feedback's size is larger than 0
        // output data of each row and store it into the database array
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $db[$i] = $row;
            $i++;
        }
    } else {
        echo "Given deck configuration yield 0 results";
    }

    return $db;
}


function getData($deckConf)
{
    /**
     * Get all the card data and its transport relationship data into the local with the deck configuration
     */
    // Setting up the connection authentication detail
    $servername = "sql261.main-hosting.eu";
    $username = "u927028504_user";
    $password = "1145141919810";
    $dbname = "u927028504_db";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // retrive unit card data
    $query = UnitCardQueryFilling($deckConf);
    $unitCardDb = sql_fetch($conn, $query);

    // retrive transport rel data
    $query = trspQueryFilling($query);
    $trspRelDb = sql_fetch($conn, $query);

    $conn->close();

    return array(
        "UnitCardDb" => $unitCardDb,
        "TrspRelDb" => formatTrspDb($trspRelDb)
    );
}


function deckAssembler($deckFrame, $cardDb, $trspDb)
{
    $deck = array();
    // - Always start from the top tab (LOGI) to place a cv unit into the deck

    // - Afterwards, goes down from tab to tab, if it reached the end (in this case it is the air), starts from the top (LOGI) again.

    // - Sub-process is:
    // - Check if filling of the current slot would exceed the deck point limit, if so, ditch this tab from the tab iteration list and jump to the next tab
    // - Check if there is unit deployable to the slot, if there is none, ditch this tab from the tab iteration list and jump to the next tab
    // - Do a randomly roll to decide whether this tab is going to be filled, if no, jump to next tab, if yes:
    // - Randomly select a unit from the requested result, will need some check such as is the card/transport depleted, if failed check, do a random draw again until a success fill is performed. 
    // - repeat the tab iteration until there is nothing left in the tab iter-list


    return deckEncoder($deck);
}


function deckEncoder($deck)
{
    $code = '';

    return $code;
}


$conf = array(
    "faction" => "UK",
    "spec" => "Motorised",
    "era" => ""
);

// echo "start";
// print_r(loadFrame($conf));
// getData($conf);
print_r(getData($conf));
// print_r($conf);

// $url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
// $jsonDb = json_decode(file_get_contents($url), true);
