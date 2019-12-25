<?php
class testobj
{
    private $trspLib = array(
        "114" => array(
            array(
                "CARD_ID" => "12"
            ),
            array(
                "CARD_ID" => "18"
            )
        ),
        "514" => array(
            array(
                "CARD_ID" => "12"
            )
        )
    );


    public function drawTrsp()
    {
        $size = count($this->trspLib["114"]);
        // if the trsp rel for this card is empty, return empty string
        if ($size == 0) {
            return "";
        }
        $ind = rand(0, $size - 1);
        $trsp = $this->trspLib["114"][$ind];

        // remove the transport from all the related carried card entry if it is depleted
        foreach ($this->trspLib as &$trspRel) {
            foreach ($trspRel as $key => $trspCan) {
                if ($trspCan["CARD_ID"] == $trsp["CARD_ID"]) {
                    unset($trspRel[$key]);
                    $trspRel = array_values($trspRel);
                    break;
                }
            }
            unset($trspCan);
        }
        print_r("{$trsp['CARD_ID']} is popped \n");
        print_r($this->trspLib);
        return $this->trspLib;
    }
}

$ubh = new testobj();
print_r($ubh->drawTrsp());
