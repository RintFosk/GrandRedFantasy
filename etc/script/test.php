<?php


class obju
{
    public $b = 5;
    public $arra = array(
        "LOG" => array(1, 3, 5)
    );

    public function chnageA()
    {
        unset($this->arra["LOG"][1]);
        $this->arra["LOG"] = array_values($this->arra["LOG"]);
    }

    public function getB()
    {
        return $this->arra;
    }
}

$obj1 = new obju();
print_r($obj1->b);
print_r($obj1->chnageA());
print_r($obj1->getB());
