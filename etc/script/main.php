<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// the default date format is "Y-m-d\TH:i:sP"
$dateFormat = "Y-m-j H:i:s.u";
// the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
$output = "[%datetime%] %channel% - %level_name%: %message% %context%\n";
// finally, create a formatter
$formatter = new LineFormatter($output, $dateFormat);

// Create a handler
file_put_contents(__DIR__ . '/refactor_test.log', "");
$stream = new StreamHandler(__DIR__ . '/refactor_test.log', Logger::DEBUG);
$stream->setFormatter($formatter);

// create a log channel
$logM = new Logger('Master Process');
$logS = new Logger('SQL');
$logD = new logger('Deck Generation');
$logM->pushHandler($stream);
$logS->pushHandler($stream);
$logD->pushHandler($stream);

// $logM->pushProcessor(function ($record) {
//     $record['extra']['dummy'] = 'Hello world!';

//     return $record;
// });

$logM->info("Logger initialized");

/**
 * Importing the needed translation and dictionary data saved in json
 */

// Import the general json db
$url = dirname(__DIR__) . '/data/faction_and_deckSpec.json';
$generalDB = json_decode(file_get_contents($url), true);

// Import the frame db
$url = dirname(__DIR__) . '/data/deck_spec_distribution.json';
$frameDB = json_decode(file_get_contents($url), true);

/**
 * A switcher that execute corresponding functions when called by other process
 */
if (isset($_POST['action']) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'deckGen':
            $logM->info("deck generation command recieved", $_POST);
            $deckConf_set = $_POST["deckConf_set"];
            $deck = new DeckFactory();
            $DCode = $deck->getDecks($deckConf_set);
            $logM->info("deck code generation complete", array($DCode));
            echo $DCode;
            break;
    }
}

class DeckFactory
{
    function getDecks($deckConf_set)
    {
        /**
         * main function that fetch all the unit card and unit-transport
         * relationship data that matched with the given deck configuration
         * from the target mysql database
         * 
         * return: true when the fetching completed
         *         false when the connection failed
         */

        global $logM, $logS;
        $deckSetOutput = "";

        $logM->info("Start deck set generation", $deckConf_set);
        $cardLibrary = new CardLibrary();

        // initiate the database connection
        $cardLibrary->startConn();

        foreach ($deckConf_set as $deckConf) {
            $cardLibrary->fetchData($deckConf);
            $unitCardLib = $cardLibrary->getUnitLib();
            $trspCardLib = $cardLibrary->getTrspLib();

            $deck = new Deck($deckConf, $unitCardLib, $trspCardLib);
            $deckCode = $deck->getDeckCode();
            $deckConf_output = "{$deckConf['faction']} {$deckConf['spec']} {$deckConf['era']}<br>";
            $deckSetOutput .= $deckConf_output . $deckCode . "<br>";
        }

        // close the connection
        $cardLibrary->closeConn();
        $logS->info("Deck set generation complete");


        return $deckSetOutput;
    }
}

class CardLibrary
{
    /**
     * The card library that contains all the card that matches the given
     * deck configuration restriction, and all the transport relationship
     * for all the infantry card in this library
     * 
     * pre-requirement of this function is that the data stored in the
     * database must follow the required format:
     * - unit-card database
     * - unit-transport relationship database
     */

    // The container for the cards and the transport relationship
    private $unitCardLib;
    private $unitTrspLib;
    private $conn;

    public function startConn()
    {
        global $logS;


        $logS->info("setting up connection detail");
        // Create connection, parameters are: server name, user name, password and database name
        $this->conn = new mysqli(
            "sql261.main-hosting.eu",
            "u927028504_user",
            "1145141919810",
            "u927028504_db"
        );

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
            $logS->warning("connection to remote SQL failed");
            return false;
        }
        $logS->info("connection to remote SQL is successful");
    }

    public function closeConn()
    {
        global $logS;

        $logS->info("closing the connection");
        $this->conn->close();
        $logS->info("connection closed");

        return true;
    }

    public function fetchData($deckConf)
    {
        /**
         * main function that fetch all the unit card and unit-transport
         * relationship data that matched with the given deck configuration
         * from the target mysql database
         * 
         * return: true when the fetching completed
         *         false when the connection failed
         */

        global $logM, $logS;
        $this->unitCardLib = array();
        $this->unitTrspLib = array();

        $logM->info("Start fetching data", $deckConf);
        // retrive unit card data
        $logS->info("retriving the card data");
        $query = $this->makeQuery_unitCard($deckConf);
        $this->unitCardLib = $this->sql_fetch($this->conn, $query);

        // retrive transport rel data
        $logS->info("retriving the transport relation data");
        $query = $this->makeQuery_trspRel($query);
        $this->unitTrspLib = $this->sql_fetch($this->conn, $query);

        // close the connection
        $logS->info("data fetching");

        return true;
    }

    private function sql_fetch($conn, $query)
    {
        /**
         * from given query and mysql connection, fetch the data and
         * convert the data into php array format
         * 
         * return: a numeric array that contains all the data entries from the result
         */

        global $logS;

        $logS->info("SQL fetch called", array($query));

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
            $logS->warning("Given deck configuration yield 0 results");
        }

        $logS->info("SQL fetch finished");
        return $db;
    }

    private function makeTrsp($trspID, $unitDb)
    {
        /**
         * An auxillary function that attaches identity information
         * to the transport's data
         */

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

    private function makeQuery_unitCard($deckConf)
    {
        /**
         * Assemble the SQL query code corresponding to the deck configuration 
         * to retrieve all the matched cards' data from the database
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
        // if the faction is NATO or PACT alliance
        if (in_array($faction, $generalDB['ALLIES'])) {
            switch ($faction) {
                case "NATO":
                    $sql .= "LEAGUE = 'BLU' and Prototype = 0 and ";
                    break;
                case "PACT":
                    $sql .= "LEAGUE = 'RED' and Prototype = 0 and ";
                    break;
            }
        }
        // if the faction is a League (composition of multiple nation)
        elseif (in_array($faction, $generalDB['LEAGUES'])) {
            $sql .= '(';
            foreach ($generalDB['TranslateTable'][$faction] as $nation) {
                $sql .= "Nation = '{$nation}' or ";
            }
            $sql = substr($sql, 0, -4);
            $sql .= ') and ';
        }
        // if the faction is a Nation
        elseif (in_array($faction, $generalDB['NATIONS'])) {
            $addition = $generalDB['TranslateTable'][$faction];
            $sql .= "Nation = '{$addition}' and ";
        } else {
            // echo "error, unexpected faction";
        }

        // ============ SPEC CONFIGURATION ============
        // if the spec is not general deck
        if ($spec != "" and in_array($spec, $generalDB['DeckSpec'])) {
            $sql .= "Spec_{$generalDB['TranslateTable'][$spec]} is true and ";
        }

        // ============ YEAR CONFIGURATION ============
        // if the era setting is not all era
        if ($year != "") {
            $sql .= "year <= {$year} and ";
        }

        // ============ TRIMMING ============
        // trim off the 'and ' end of the assembled sql code and add the valid end ';'
        $sql = substr($sql, 0, -5);
        $sql .= ';';

        return $sql;
    }

    private function makeQuery_trspRel($baseQ)
    {
        /**
         * fill the query that retrieve all the transport relationships 
         * base on the given deck conf
         * 
         * The generation of the query will be base on the unit card
         * fetching query line
         */

        // only get the post WHERE clause
        $baseQ_cut = substr($baseQ, 30, -1);
        $sql = "SELECT b.* 
            FROM UNIT_CARD as a INNER JOIN UNIT_TRANSPORT_REL as b
            ON b.LEAGUE = a.LEAGUE and b.CARD_ID = a.CARD_ID
            WHERE a.{$baseQ_cut} and
                b.TRANSPORT_ID in (SELECT CARD_ID FROM UNIT_CARD
                WHERE {$baseQ_cut})";


        return $sql;
    }

    public function getUnitLib()
    {
        /**
         * get formatted unit card library
         * 
         * return: formatted unit card table that is categorized into tab reference
         * 
         * Format will be like:
         * array:
         *   --tab1:
         *     --unit1:data
         *     --unit2:data
         *     ....
         *   ....
         */

        global $generalDB;
        $formatted = array();

        // initialize the tabs inside the formatted database
        foreach ($generalDB['TABS'] as $tab) {
            $formatted[$tab] = array();
        }

        // filling the tab with the units
        foreach ($this->unitCardLib as $unitCard) {
            // the formatted databae will not have any transport vehicle card in it
            if ($unitCard["transport"] == 1) {
                continue;
            }

            // if it is not the transport, fill this unit into the tab that they belong to
            foreach ($generalDB['DB_TABS'] as $db_tab) {
                // search through all the tab_ column in the raw
                // to find the tab that this card belongs to
                if ($unitCard[$db_tab] == 1) {
                    array_push($formatted[$generalDB['REL_TABS'][$db_tab]], $unitCard);
                    break;
                }
            }
        }

        return $formatted;
    }

    public function getTrspLib()
    {
        /**
         * Format the raw transport relation data into the usable format 
         * 
         * return: a formatted unit-transport relationship database
         * 
         * Format will be like:
         * array:
         *   --passenger1:
         *     --transport_candidate1:data
         *     --transport_candidate2:data
         *     ....
         *   ....
         */
        $formatted = array();
        $currentID = '';
        foreach ($this->unitTrspLib as $trspRel) {
            // if the ID is encountered first time
            if ($currentID != $trspRel["CARD_ID"]) {
                // get the passenger unit's ID, make a new entry
                $currentID = $trspRel["CARD_ID"];
                // make a new numeric Array to contain the transport data
                $formatted[$currentID] = array();
            }

            // fill the transport candidate's data into the array
            $trsp = $this->makeTrsp($trspRel["TRANSPORT_ID"], $this->unitCardLib);
            array_push($formatted[$currentID], $trsp);
        }

        return $formatted;
    }
}


class Deck
{
    private $deck;
    private $deckConf;
    private $cardLib;
    private $trspLib;
    private $cardRecord = array();

    public function __construct($deckConf, $cardLib, $trspLib)
    {
        // save the deck conf as local variable first
        $this->deckConf = $deckConf;

        // initialize the formatted unit and transport relation database
        $this->cardLib = $cardLib;
        $this->trspLib = $trspLib;

        // generate the deck
        $this->deck = $this->deckAssembler();
    }

    public function getDeckCode()
    {
        $encoder = new DeckEncoder($this->deckConf);
        return $encoder->encode($this->deck);
    }

    private function deckAssembler()
    {
        // import the json and frame database and logger
        global $generalDB, $frameDB, $logD, $logM;

        $logM->info("Deck Assembling start");
        // setting up the empty deck
        $deck = array(
            "class1" => array(),
            "class2" => array(),
            "class3" => array()
        );

        // setting up the deck frame and deck point limit base on the deck configuration
        if ($this->deckConf['spec'] != "") {
            $frame = $frameDB[$generalDB["TranslateTable"][$this->deckConf['spec']]];
        } else {
            $frame = $frameDB["GENERAL"];
        }

        // get the point limit of this deck configuration
        $point = $this->getDeckPoint();

        // import the list of tab in the game
        $tabs = $generalDB['TABS'];
        // setting up the pointer of the deck's tab
        $tabPointer = array();
        foreach ($tabs as $tab) {
            $tabPointer[$tab] = 0;
        }
        unset($tab);

        // setting up the deck filling end condition
        $end = false;

        // no cv in the deck at start
        $cved = false;

        // ================== DECK GENERATION ==================
        // - Always start from the top tab (LOGI) to place a cv unit into the deck
        // - Afterwards, goes down from tab to tab, if it reached the end (in this case it is the air), starts from the top (LOGI) again.
        while ($end == false) {
            $logD->info("Start tabs checking iteration");
            $isPopped = false;
            foreach ($tabs as $key => $tab) {
                // ================== PRE CHECKING ==================
                // If the pop condition is true, pop the tab from the list
                //
                // - However at the same iteration, only one tab can be popped
                // this is because that the index only can be refreshed in
                // next iteration.
                if ($this->isTabPopping($tabPointer, $frame, $tab, $point)) {
                    $logD->info("{$tab} tab popping condition met");
                    if ($isPopped == false) {
                        $logD->info("no tab has been popped from current iteration, start popping");
                        unset($tabs[$key]);
                        $tabs = array_values($tabs);
                        $isPopped = true;
                    } else {
                        $logD->info("a tab has been popped during current iteration, stop popping");
                    }
                    $logD->info("skipping to next tab iteration");
                    break;
                }

                // randomly decide if the current tab is going to be filled
                // if it failed, skip to next tab
                if (rand(0, 1) == 0) {
                    $logD->info("{$tab} is skipped");
                    continue;
                } else {
                    $logD->info("drawing card for {$tab} tab");
                }

                // ================== CARD DRAWING ==================
                $cardDrawn = array();
                // Ensure that first drawn must contain a command unit
                if ($key == "LOG" and $cved == false) {
                    $logD->debug("no cv in the deck, draw a cv card into the LOG tab");
                    $condition = array(
                        "CMD" => "1"
                    );
                    $cardDrawn = $this->drawCard($tab, $condition);
                    $cved = true;
                } else {
                    // randomly choose a card from database
                    $cardDrawn = $this->drawCard($tab);
                }

                // if during the check, found that no card can be draw from this tab
                // remove the tab from iteration and skip to next tab
                if ($cardDrawn == "") {
                    $logD->warning("The card drawn is empty, cancel the filling sequence");
                    continue;
                }

                // if there is content in the returned card set, record them into the 
                // corresponding selected card list base on their class
                if ($cardDrawn["class"] == 2) {
                    // if it is the inf in a transport, then it is a class2 unit
                    array_push($deck["class2"], $cardDrawn);
                } else if ($cardDrawn["class"] == 1) {
                    // if it is just an independent unit, then it is a class1 unit
                    array_push($deck["class1"], $cardDrawn);
                } else {
                    // echo "ERROR: UNEXPECT CARD SET CLASS";
                }

                // moving the deck spec frame pointer and reduce the point correspondingly
                $point -= $frame[$tab][$tabPointer[$tab]];
                $tabPointer[$tab] += 1;
            }


            // if there is no tab left in the tab list or no point left, deck generation is finished
            if ($point == 0 or count($tabs) == 0) {
                $logM->info("deck generation complete");
                $end = true;
            }
            unset($tab);
        }
        $logM->debug("printing the final unitRecord", $this->cardRecord);
        $logM->debug("printing the final card library", $this->cardLib);
        $logM->debug("printing the final card library", $this->trspLib);


        return $deck;
    }

    private function isTabPopping($tabPointer, $frame, $tab, $point)
    {
        /**
         * detect if the current tab needed to be popped from the tab list
         * or not
         */
        global $logD;

        // if the tab's slot is depleted
        if ($tabPointer[$tab] >= count($frame[$tab])) {
            $logD->info("==POP MET== {$tab} tab's slot is depleted");
            return true;
        }
        // if the filling of the tab would exceed the point limit
        elseif ($frame[$tab][$tabPointer[$tab]] > $point) {
            $logD->info("==POP MET== {$tab} tab's current slot filling would exceed point limit");
            return true;
        }
        // if the current tab has no unit
        elseif (count($this->cardLib[$tab]) == 0) {
            $logD->info("==POP MET== {$tab} tab has no unit left");
            return true;
        } else {
            return false;
        }
    }

    private function getDeckPoint()
    {
        /**
         * Calculate the deck's point limit base on the deck configuration
         */
        // Import the translation table from the json file
        global $generalDB, $logD;

        $point = 0;
        $faction = $this->deckConf["faction"];
        $year = $this->deckConf["era"];
        if (in_array($faction, $generalDB["ALLIES"])) {
            $point = 45;
        } elseif (in_array($faction, $generalDB["LEAGUES"])) {
            $point = 55;
        } elseif (in_array($faction, $generalDB["NATIONS"])) {
            $point = 60;
        } else {
            $logD->warning("faction does not match any record");
        }

        if ($year == "1985") {
            $point += 5;
        } elseif ($year == "1980") {
            $point += 10;
        } elseif ($year == "") {
        } else {
            $logD->warning("era does not match any record");
        }

        return $point;
    }

    private function simpfyCard($card)
    {
        $simp = array(
            "CARD_ID" => $card["CARD_ID"],
            "Name" => $card["Name"]
        );
        return $simp;
    }

    private function drawCard($tab, $condition = array())
    {
        /**
         * randomly drawing card from the card library
         * auto delete the entry if the target card is depleted upon drawn
         * 
         * return: an associative array that contains the type of the card drawn
         *         (class3/2/1) and corresponding number of the card
         */
        global $logD;
        $logD->info("start card drawing");

        $cardSet = array(
            "class" => 0
        );

        while (true) {
            // drawing the top level card
            $size = count($this->cardLib[$tab]);
            // if there is no card under this tab, return an empty string
            if ($size == 0) {
                $logD->info("no card under this {$tab} tab");
                return "";
            }

            // draw card
            $ind = rand(0, $size - 1);
            $card = $this->cardLib[$tab][$ind];
            $vet = $this->drawVet($card);

            // if there is card restriction
            if (!empty($condition)) {
                $logD->debug("condition check triggered", $condition);
                $matched = true;
                foreach ($condition as $condition_key => $condition_value) {
                    if ($card[$condition_key] != $condition_value) {
                        $matched = false;
                    }
                }

                if ($matched == false) {
                    $logD->debug("condition check failed");
                    continue;
                } else {
                    $logD->debug("condition check passed");
                }
            }

            // if it is inf type, then it maybe class2 or class3
            // because currently ignoring the nav tab, it can only be class2
            if ($card["inf"] == 1) {
                // check if there is transport candidate
                $logD->info("the drawn card is inf type, a trsp card is need to be drawn");
                $trsp = $this->drawTrsp($card);
                if ($trsp == "") {
                    $logD->warning("there is no transport candidate left!, remove the card from library and redraw a new card", $this->simpfyCard($card));
                    // if there is no transport candidate left, remove this card from the library, re-draw a new card
                    unset($this->cardLib[$tab][$ind]);
                    $this->cardLib[$tab] = array_values($this->cardLib[$tab]);
                    continue;
                } else {
                    // if it is class 2
                    $cardSet["class"] = 2;
                    $cardSet["vet"] = $vet;
                    $cardSet["card"] = $this->simpfyCard($card);
                    $cardSet["trsp"] = $trsp;
                    $logD->info("class 2 card drawn", $cardSet);
                    break;
                }
            } else {
                // if it is class 1
                $cardSet["class"] = 1;
                $cardSet["vet"] = $vet;
                $cardSet["card"] = $this->simpfyCard($card);
                $logD->info("class 1 card drawn", $cardSet);
                break;
            }
        }

        $this->addRecord($card);

        if ($this->isCardDepleted($card) == true) {
            unset($this->cardLib[$tab][$ind]);
            $this->cardLib[$tab] = array_values($this->cardLib[$tab]);
        }

        $logD->info("card drawing complete");
        return $cardSet;
    }

    private function drawTrsp($card)
    {
        /**
         * randomly draw transport from the transport relationship database
         * auto delete the entry if the target card is depleted upon drawn
         * 
         * return: an integer string representing the ID of the transport
         */
        global $logD;
        $logD->info("start drawing transport");

        // if the given card already out of the trsp library
        if (!array_key_exists($card["CARD_ID"], $this->trspLib)) {
            return "";
        }

        $size = count($this->trspLib[$card["CARD_ID"]]);
        // if the trsp rel for this card is empty, return empty string
        if ($size == 0) {
            return "";
        }
        $ind = rand(0, $size - 1);
        $trsp = $this->trspLib[$card["CARD_ID"]][$ind];


        $logD->info("adding the trsp into the record");
        $this->addRecord($trsp);
        $logD->debug("current record", $this->cardRecord);

        // remove the transport from all the related carried card entry if it is depleted
        if ($this->isCardDepleted($trsp) == true) {
            $logD->debug("current transport is depleted, deleting all the related trsp relation", $trsp);
            foreach ($this->trspLib as &$trspRel) {
                foreach ($trspRel as $key => $trspCan) {
                    if ($trspCan["CARD_ID"] == $trsp["CARD_ID"]) {
                        $logD->info("deleting related entry");
                        $logD->debug("before", $trspRel);
                        unset($trspRel[$key]);
                        $trspRel = array_values($trspRel);
                        $logD->debug("after", $trspRel);
                        break;
                    }
                }
                unset($trspCan);
            }
        }
        $logD->info("transport drawing completed");

        return $trsp;
    }

    private function drawVet($card)
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

    private function isCardDepleted($card)
    {
        global $logD;

        foreach ($this->cardRecord as $cardRec) {
            if ($cardRec["CARD_ID"] == $card["CARD_ID"]) {
                if ("{$cardRec["USED"]}" == $card["Card_limit"]) {
                    $logD->info("This card is depleted", array($card["CARD_ID"]));
                    return true;
                } else {
                    return false;
                }
            }
        }

        return false;
    }

    private function addRecord($card)
    {
        // Check if it is in the record
        foreach ($this->cardRecord as $key => $record) {
            if ($record["CARD_ID"] == $card["CARD_ID"]) {
                $this->cardRecord[$key]["USED"] += 1;
                return;
            }
        }
        // if it is not in the record, create a new entry
        $newEntry = array(
            "CARD_ID" => $card["CARD_ID"],
            "USED" => 1
        );
        array_push($this->cardRecord, $newEntry);
        return;
    }
}

class DeckEncoder
{
    private $deckConf;

    public function __construct($deckConf)
    {
        $this->deckConf = $deckConf;
    }
    /**
     * An encoder that converts the generated deck into game-readable
     * format for the use inside Wargame:Red Dragon
     */

    function decimalToBi($value, $max)
    {
        /**
         * Convert the decimal value to certain length of binary, with padding if needed
         */
        $quantity = decbin($value);
        $result = "{$quantity}";

        if (strlen($result) < $max) {
            $padding = '';
            for ($i = 0; $i < ($max - strlen($result)); $i++) {
                $padding .= "0";
            }
            $result = $padding . $result;
        }

        return $result;
    }

    function hexfy($code)
    {
        global $generalDB;
        $encodeForm = array_flip($generalDB["DECODE_TABLE"]);
        $hexed = "@";

        $num_of_blocks = strlen($code) / 6;
        // print_r($num_of_blocks);
        for ($i = 0; $i < $num_of_blocks; $i++) {
            $part = substr($code, $i * 6, 6);
            $hexChar = $encodeForm[$part];
            $hexed .= $hexChar;
        }

        $suffixInd = 4 - $num_of_blocks % 4;
        switch ($suffixInd) {
            case 1:
                $hexed .= "A";
                break;
            case 2:
                $hexed .= "A=";
                break;
            case 3:
                $hexed .= "A==";
                break;
        }

        return $hexed;
    }

    function encode($deck)
    {
        global $generalDB;
        $code = '';

        // Add the faction code
        $code .= $generalDB["FACTION_CODE"][$this->deckConf["faction"]];

        // Add the spec code
        $code .= $generalDB["SPEC_CODE"][$this->deckConf["spec"]];

        // Add the era code
        $code .= $generalDB["ERA_CODE"][$this->deckConf["era"]];

        // add the code for class 3 card quantity
        $quantity = decbin(count($deck["class3"]));
        $code .= $this->decimalToBi($quantity, 4);

        // add the code for class 2 card quantity
        $quantity = count($deck["class2"]);
        $code .= $this->decimalToBi($quantity, 5);

        // adding code for class 3 card if there is any
        // currently not supported

        // adding code for class 2 card if there is any
        foreach ($deck["class2"] as $key => $class2_card) {
            $vet = (int) $class2_card["vet"];
            $unit = (int) $class2_card["card"]["CARD_ID"];
            $trsp = (int) $class2_card["trsp"]["CARD_ID"];

            $line = "";
            $line .= $this->decimalToBi($vet, 3);
            $line .= $this->decimalToBi($unit, 11);
            $line .= $this->decimalToBi($trsp, 11);
            $code .= $line;
        }

        // adding code for class 1 card if there is any
        foreach ($deck["class1"] as $key => $class1_card) {
            $vet = (int) $class1_card["vet"];
            $unit = (int) $class1_card["card"]["CARD_ID"];

            $line = "";
            $line .= $this->decimalToBi($vet, 3);
            $line .= $this->decimalToBi($unit, 11);
            $code .= $line;
        }

        // add padding so the length of the code is 6n
        if (strlen($code) % 6 > 0) {
            $padding = '';
            for ($i = 0; $i < (6 - (strlen($code) % 6)); $i++) {
                $padding .= "0";
            }
            $code .= $padding;
        }

        // encode the binary code into the hexan code base on the encode table


        // echo hexfy($code);
        return $this->hexfy($code);
    }
}

// $conf_set = array(
//     array(
//         "faction" => "Poland",
//         "spec" => "",
//         "era" => ""
//     ),
//     array(
//         "faction" => "PACT",
//         "spec" => "",
//         "era" => "1985"
//     ),
//     array(
//         "faction" => "NATO",
//         "spec" => "",
//         "era" => "1980"
//     )
// );

// $logM->info("deck generation test");
// $deck = new DeckFactory();
// $DCode = $deck->getDecks($conf_set);
// $logM->info("deck code generation complete", array($DCode));
// print_r($DCode);
