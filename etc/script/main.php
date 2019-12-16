<?php
// Import the general json db
$url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
$generalDB = json_decode(file_get_contents($url), true);

// Import the frame db
$url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
$frameDB = json_decode(file_get_contents($url), true);

/**
 * A switcher that execute corresponding functions when called by other process
 */
if (isset($_POST['action']) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'deckGen':
            $deckConf = $_POST["deckConf"];
            $rawDBs = getData($deckConf);
    }
}


function makeQuery_unitCard($deckConf)
{
    /**
     * Assemble the SQL query code corresponding to the deck configuration 
     * to retrieve the matched card data from the database
     */

    // Import the translation table from the json file
    global $generalDB;

    // Extract the deck configuration info
    $faction = $deckConf['faction'];
    $spec = $deckConf['spec'];
    $year = $deckConf['era'];

    // Add the start of all unit card fetch query sql code
    $sql = "SELECT * FROM UNIT_CARD WHERE ";

    // ============ FACTION CONFIGURATION ============
    // if it is NATO or PACT deck
    if (in_array($faction, $generalDB['ALLIES'])) {
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
    elseif (in_array($faction, $generalDB['LEAGUES'])) {
        $sql .= '(';
        foreach ($generalDB['TranslateTable'][$faction] as $nation) {
            $sql .= "Nation = '{$nation}' or ";
        }
        $sql = substr($sql, 0, -4);
        $sql .= ') and ';
    }
    // if it is Nation
    elseif (in_array($faction, $generalDB['NATIONS'])) {
        $addition = $generalDB['TranslateTable'][$faction];
        $sql .= "Nation = '{$addition}' and ";
    } else {
        echo "error, unexpected faction";
    }

    // ============ SPEC CONFIGURATION ============
    // if the spec is not general deck
    if ($spec != "" and in_array($spec, $generalDB['DeckSpec'])) {
        $sql .= "Spec_{$generalDB['TranslateTable'][$spec]} is true and ";
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


function makeQuery_trspRel($baseQ)
{
    /**
     * fill the query that retrieve all the transport relationship base on the given deck conf
     */
    $baseQ_cut = substr($baseQ, 30, -1);
    $sql = "SELECT b.* 
            FROM UNIT_CARD as a INNER JOIN UNIT_TRANSPORT_REL as b
            ON b.LEAGUE = a.LEAGUE and b.CARD_ID = a.CARD_ID
            WHERE {$baseQ_cut} and
                b.TRANSPORT_ID in (SELECT CARD_ID FROM UNIT_CARD
                WHERE {$baseQ_cut})";


    return $sql;
}


function formatUnitDb($UnitDb)
{
    /**
     * Format the raw transport relation data into the usable format 
     */

    global $generalDB;
    $formatted = array();

    foreach ($generalDB['TABS'] as $tab) {
        $formatted[$tab] = array();
    }

    foreach ($UnitDb as $unitCard) {
        // extremely lazy and unoptimized way to categorize every card into corresponding tab
        if ($unitCard["transport"] == 1) {
            continue;
        }

        foreach ($generalDB['DB_TABS'] as $db_tab) {
            if ($unitCard[$db_tab] == 1) {
                array_push($formatted[$generalDB['REL_TABS'][$db_tab]], $unitCard);
                break;
            }
        }
    }

    return $formatted;
}


function formatTrspDb($trspDb, $unitDb)
{
    /**
     * Format the raw transport relation data into the usable format 
     */
    $formatted = array();
    $currentID = '';
    foreach ($trspDb as $trspRel) {
        // if the ID is encountered first time
        if ($currentID != $trspRel["CARD_ID"]) {
            // get the carrieed Unit's ID
            $currentID = $trspRel["CARD_ID"];
            // make a new numeric Array to contain the transport data
            $formatted[$currentID] = array();
        }

        $trsp = makeTrsp($trspRel["TRANSPORT_ID"], $unitDb);
        array_push($formatted[$currentID], $trsp);
    }

    return $formatted;
}


function makeTrsp($trspID, $unitDb)
{
    // get the card_limit of this transport
    foreach ($unitDb as $unitCard) {
        if ($unitCard["CARD_ID"] == $trspID) {
            $card_limit = $unitCard["Card_limit"];
            break;
        }
    }

    $trsp = array(
        "CARD_ID" => $trspID,
        "Card_limit" => $card_limit
    );

    return $trsp;
}


function getDeckPoint($deckConf)
{
    /**
     * Calculate the deck's point limit base on the deck configuration
     */
    // Import the translation table from the json file
    global $generalDB;

    $point = 0;
    $faction = $deckConf["faction"];
    $year = $deckConf["era"];
    if (in_array($faction, $generalDB["ALLIES"])) {
        $point = 45;
    } elseif (in_array($faction, $generalDB["LEAGUES"])) {
        $point = 55;
    } elseif (in_array($faction, $generalDB["NATIONS"])) {
        $point = 60;
    } else {
        echo "nation does not match any record";
    }

    if ($year == "1985") {
        $point += 5;
    } elseif ($year == "1980") {
        $point += 10;
    } elseif ($year == "") { } else {
        echo "era does not match any record";
    }

    return $point;
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
     * 
     * return: raw unit card database and transport relationship database
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
    $query = makeQuery_unitCard($deckConf);
    $unitCardDb = sql_fetch($conn, $query);

    // retrive transport rel data
    $query = makeQuery_trspRel($query);
    $trspRelDb = sql_fetch($conn, $query);

    $conn->close();

    print_r(formatTrspDb($trspRelDb, $unitCardDb));
    // print_r(formatUnitDb($unitCardDb));

    return array(
        "UnitCard" => $unitCardDb,
        "TrspRel" => $trspRelDb
    );
}


function drawCard($cardDb, $trspDb, $tab, $cardRecord)
{
    /**
     * randomly drawing card from the card library
     * auto delete the entry if the target card is depleted upon drawn
     * 
     * return: an associative array that contains the type of the card drawn
     *         (class3/2/1) and corresponding number of the card
     */

    $cardSet = array(
        "class" => 0
    );

    while (true) {
        // drawing the top level card
        $size = count($cardDb[$tab]);
        // if there is no card under this tab, return an empty string
        if ($size == 0) {
            return "";
        }

        $ind = rand(0, $size - 1);
        $card = $cardDb[$tab][$ind];
        $vet = drawVet($card);

        // if it is inf type, then it maybe class2 or class3
        // because currently ignoring the nav tab, it can only be class2
        if ($card["inf"] == 1) {
            // check if there is transport candidate
            $trsp = drawTrsp($trspDb, $card, $cardRecord);
            if ($trsp == "") {
                // if there is no transport candidate left, remove this card from the library, re-draw a new card
                unset($cardDb[$tab][$ind]);
                $cardDb[$tab] = array_values($cardDb[$tab]);
                continue;
            } else {
                // if it is class 2
                $cardSet["class"] = 2;
                $cardSet["vet"] = $vet;
                $cardSet["card"] = $card;
                $cardSet["trsp"] = $trsp;
                break;
            }
        } else {
            // if it is class 1
            $cardSet["class"] = 1;
            $cardSet["vet"] = $vet;
            $cardSet["card"] = $card;
            break;
        }
    }

    addRecord($card, $cardRecord);

    if (isCardDepleted($card, $cardRecord) == true) {
        unset($cardDb[$tab][$ind]);
        $cardDb[$tab] = array_values($cardDb[$tab]);
    }

    return $card;
}


function drawTrsp($trspDb, $card, $cardRecord)
{
    /**
     * randomly draw transport from the transport relationship database
     * auto delete the entry if the target card is depleted upon drawn
     * 
     * return: an integer string representing the ID of the transport
     */
    $size = count($trspDb[$card["CARD_ID"]]);
    // if the trsp rel for this card is empty, return empty string
    if ($size == 0) {
        return "";
    }
    $ind = rand(0, $size - 1);
    $trsp = $trspDb[$card["CARD_ID"]][$ind];
    addRecord($card, $cardRecord);

    // remove the transport from all the related carried card entry if it is depleted
    if (isCardDepleted($card, $cardRecord) == true) {
        foreach ($trspDb as $trspRel) {
            foreach ($trspRel as $trspCan) {
                if ($trspCan["CARD_ID"] == $trsp["CARD_ID"]) {
                    unset($trspCan);
                    break;
                }
            }
        }
    }

    return $trsp;
}


function drawVet($card)
{
    global $generalDB;

    $vetCans = array();

    foreach ($generalDB["AVAIL"] as $vetTier) {
        if ($card[$vetTier] != 0) {
            array_push($vetCans, $generalDB["REL_AVAIL"]["$vetTier"]);
        }
    }

    $size = count($vetCans);
    $ind = rand(0, $size - 1);
    $vet = $vetCans[$ind];

    return $vet;
}


function isCardDepleted($card, $cardRecord)
{
    foreach ($cardRecord as $cardRec) {
        if ($cardRec["CARD_ID"] == $card["CARD_ID"]) {
            if ($cardRec["USED"] == $card["Card_limit"]) {
                return true;
            } else {
                return false;
            }
        }
    }

    return false;
}


function addRecord($card, $cardRecord)
{
    // Check if it is in the record
    foreach ($cardRecord as $record) {
        if ($record["CARD_ID"] == $card["CARD_ID"]) {
            $record["USED"] += 1;
            return;
        }
    }
    // if it is not in the record, create a new entry
    $newEntry = array(
        "CARD_ID" => $card["CARD_ID"],
        "USED" => 1
    );
    array_push($cardRecord, $newEntry);
    return;
}


function deckAssembler($deckConf)
{
    // import the json and raw SQL database
    global $generalDB, $frameDB, $rawDBs;

    // initialize the formatted unit and transport relation database
    $cardDb = formatUnitDb($rawDBs["UnitCard"]);
    $trspDb = formatTrspDb($rawDBs["TrspRel"], $rawDBs["UnitCard"]);

    // import the deck configuration
    $spec = $deckConf['spec'];
    $year = $deckConf['era'];
    $faction = $deckConf['faction'];

    // setting up the quantity of different type of units (by transport):
    $class1List = array();
    $class2List = array();
    $class3List = array();

    // setting up the deck frame and deck point limit base on the deck configuration
    $frame = $frameDB[$generalDB["TranslateTable"][$spec]];
    $point = getDeckPoint($deckConf);

    // setting up the empty deck
    $deck = array();

    // setting up the record for drawn card
    $cardRecord = array();

    // import the list of tabin the game
    $tabs = $generalDB['TABS'];
    // setting up the pointer of the deck's tab
    $tabPointer = array();
    foreach ($tabs as $tab) {
        $tabPointer[$tab] = 0;
    }

    // setting up the deck filling end condition
    $end = false;

    // ================== DECK GENERATION ==================
    // - Always start from the top tab (LOGI) to place a cv unit into the deck
    // - Afterwards, goes down from tab to tab, if it reached the end (in this case it is the air), starts from the top (LOGI) again.
    while ($end == false) {
        foreach ($tabs as $tab) {
            // ================== PRE CHECKING ==================
            // if no unit left in the library OR filling of the tab is not possible with point limit
            // pop this tab from the iteration list, skip to next tab
            if ($frame[$tabPointer[$tab]] >= $point or count($cardDb[$tab]) == 0) {
                array_pop($tabs, $tab);
                continue;
            }
            // randomly decide if the current tab is going to be filled
            // if it failed, skip to next tab
            if (rand(0, 1) == 0) {
                continue;
            }

            // ================== CARD DRAWING ==================
            // randomly choose a card from database
            $cardDrawn = array();
            $cardDrawn = drawCard($cardDb, $trspDb, $tab, $cardRecord);

            // if during the check, found that no card can be draw from this tab
            // remove the tab from iteration and skip to next tab
            if ($cardDrawn == "") {
                array_pop($tabs, $tab);
                continue;
            }

            // if there is content in the returned card set, record them into the 
            // corresponding selected card list base on their class
            if ($cardDrawn["class"] == 2) {
                // if it is the inf in a transport, then it is a class2 unit
                array_push($class2List, $cardDrawn);
            } else if ($cardDrawn["class"] == 1) {
                // if it is just an independent unit, then it is a class1 unit
                array_push($class1List, $cardDrawn);
            } else {
                echo "ERROR: UNEXPECT CARD SET CLASS";
            }

            // moving the deck spec frame pointer and reduce the point correspondingly
            $point -= $frame[$tab][$tabPointer];
            $tabPointer[$tab] += 1;

            // if there is no tab left in the tab list or no point left, deck generation is finished
            if (count($tabs) == 0 or $point == 0) {
                $end = true;
            }
        }

        if ($point == 0) {
            $end = true;
        }
    }

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
// print_r(getDeckFrame($conf));
// print_r(deckPointCalc($conf));
getData($conf);
// print_r(getData($conf));
// print_r($conf);

// $url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
// $generalDB = json_decode(file_get_contents($url), true);

// formatUnitDb($conf);
