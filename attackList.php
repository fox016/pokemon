<?php

class AttackList
{
  private $attackList = array();

  public function __construct($inFile="./moves.csv")
  {
    $fh = fopen($inFile, "r");
    $row = fgetcsv($fh);
    while($row !== false)
    {
      $attack = array(
        "number" => $row[0],
        "name" => $row[1],
        "type" => $row[2],
        "category" => $row[3],
        "pp" => $row[4],
        "power" => $row[5],
        "accuracy" => $row[6],
        "generation" => $row[7],
      );
      $this->attackList[] = $attack;
      $row = fgetcsv($fh);
    }
    fclose($fh);
  }

  public function getMove($i)
  {
    if(isset($this->attackList[$i-1]))
      return $this->attackList[$i-1];
    return NULL;
  }
}
