<?php

require_once(__DIR__ . '/attackList.php');
$attackList = new AttackList();

try
{
  $filename = "/Users/Admin/Library/Application Support/OpenEmu/Save States/GameBoy Advance/Pokemon - Leaf Green Version (U) (V1.1)/Auto Save State.oesavestate/State";
  if(isset($argv[1]))
    $filename = $argv[1];

  $byteArray = readBytes($filename);
  $teamList = getTeamList($byteArray);
  echo json_encode($teamList, JSON_PRETTY_PRINT) . "\n";

  //fillTeamHP($byteArray);
  //healTeamStatus($byteArray);
  //writeBytes($byteArray, $filename);
}
catch(Exception $e)
{
  die($e->getMessage());
}

/*
 * ====================== Parse binary data functions
 */
function getTeamList($byteArray)
{
  // Name start
  $startPos = 0x0001ee00;
  $nameArray = array_slice($byteArray, $startPos, 7);

  // First member data start
  $startPos = getTeamListAddress();
  $members = array();
  for($i = 0; $i < 6; $i++)
  {
    $member = array();
    $personality = array_slice($byteArray, $startPos, 4);
    $otId = array_slice($byteArray, $startPos+4, 4);
    $member['name'] = arrayToString(array_slice($byteArray, $startPos+8, 10));
    $member['data'] = readData(array_slice($byteArray, $startPos+32, 48), $personality, $otId);
    $member['status'] = readStatus(array_slice($byteArray, $startPos+80, 4));
    $member['level'] = arrayToInt(array_slice($byteArray, $startPos+84, 1));
    $member['current_hp'] = arrayToInt(array_slice($byteArray, $startPos+86, 2));
    $member['total_hp'] = arrayToInt(array_slice($byteArray, $startPos+88, 2));
    $member['attack'] = arrayToInt(array_slice($byteArray, $startPos+90, 2));
    $member['defense'] = arrayToInt(array_slice($byteArray, $startPos+92, 2));
    $member['speed'] = arrayToInt(array_slice($byteArray, $startPos+94, 2));
    $member['sp. attack'] = arrayToInt(array_slice($byteArray, $startPos+96, 2));
    $member['sp. defense'] = arrayToInt(array_slice($byteArray, $startPos+98, 2));
    $members[] = $member;
    $startPos += 100;
  }

  $teamList = array(
    "trainer_name" => arrayToString($nameArray),
    "members" => $members
  );
  return $teamList;
}

function getTeamListAddress()
{
  return 0x00045284;
}

function readStatus($statusBytes)
{
  return arrayToBitStr($statusBytes);
}

function readData($dataBytes, $personality, $otId)
{
  $key = xor32($personality, $otId);
  $data = xor32($key, $dataBytes);
  $order = arrayToInt($personality) % 24;
  //echo arrayToInt($personality) . "\n";
  //echo arrayToBitStr($personality) . "\n";
  return array(
    "growth" => readGrowth($data, $order),
    "attacks" => readAttacks($data, $order),
  );
}

function readGrowth($data, $order)
{
  if($order <= 5)
    $block = 1;
  else if(in_array($order, array(6, 7, 12, 13, 18, 19)))
    $block = 2;
  else if(in_array($order, array(8, 10, 14, 16, 20, 22)))
    $block = 3;
  else
    $block = 4;
  $growthBlock = array_slice($data, ($block-1)*12, 12);
  return array(
    "species" => arrayToInt(array_slice($growthBlock, 0, 2)),
    "experience" => arrayToInt(array_slice($growthBlock, 4, 4)),
  );
}

function readAttacks($data, $order)
{
  global $attackList;
  if($order >= 6 && $order <= 11)
    $block = 1;
  else if(in_array($order, array(0, 1, 14, 15, 20, 21)))
    $block = 2;
  else if(in_array($order, array(2, 4, 12, 17, 18, 23)))
    $block = 3;
  else
    $block = 4;
  $attackBlock = array_slice($data, ($block-1)*12, 12);
  return array(
    "Move 1" => $attackList->getMove(arrayToInt(array_slice($attackBlock, 0, 2))),
    "Move 2" => $attackList->getMove(arrayToInt(array_slice($attackBlock, 2, 2))),
    "Move 3" => $attackList->getMove(arrayToInt(array_slice($attackBlock, 4, 2))),
    "Move 4" => $attackList->getMove(arrayToInt(array_slice($attackBlock, 6, 2))),
    "PP 1" => arrayToInt(array_slice($attackBlock, 8, 1)),
    "PP 2" => arrayToInt(array_slice($attackBlock, 9, 1)),
    "PP 3" => arrayToInt(array_slice($attackBlock, 10, 1)),
    "PP 4" => arrayToInt(array_slice($attackBlock, 11, 1)),
  );
}

function xor32($key, $bytes)
{
  $value = array();
  foreach($bytes as $i => $b1) {
    $value[] = $b1 ^ $key[$i%4];
  }
  return $value;
}

/*
 * ====================== Manipulate binary data functions
 */
function fillTeamHP(&$byteArray)
{
  $startPos = getTeamListAddress();
  for($i = 0; $i < 6; $i++)
  {
    $totalHP = array_slice($byteArray, $startPos+88, 2);
    $byteArray[$startPos+86] = $totalHP[0];
    $byteArray[$startPos+87] = $totalHP[1];
    $startPos += 100;
  }
}

function healTeamStatus(&$byteArray)
{
  $startPos = getTeamListAddress();
  for($i = 0; $i < 6; $i++)
  {
    $byteArray[$startPos+80] = 0;
    $byteArray[$startPos+81] = 0;
    $byteArray[$startPos+82] = 0;
    $byteArray[$startPos+83] = 0;
    $startPos += 100;
  }
}

/*
 * ====================== I/O functions
 */
function readBytes($filename)
{
  $filesize = filesize($filename);
  $handle = fopen($filename, "rb");
  $binary = fread($handle, $filesize);
  fclose($handle);
  $unpacked = unpack(sprintf('C%d', $filesize), $binary);
  $unpacked = array_values($unpacked);
  return $unpacked;
}

function writeBytes($byteArray, $filename)
{
  $filesize = count($byteArray);
  $packed = pack(sprintf('C%d', $filesize), ...$byteArray);
  $handle = fopen($filename, "wb");
  fwrite($handle, $packed, $filesize);
  fclose($handle);
}

/*
 * ====================== Conversion functions
 */
function arrayToString($array)
{
  $str = "";
  foreach($array as $i) {
    $str .= intToChar($i);
  }
  return $str;
}

function arrayToInt($array)
{
  $int = 0;
  $place = 1;
  foreach($array as $d) {
    $int += ($d * $place);
    $place*=256;
  }
  return $int;
}

function intToChar($int)
{
  // A-Z
  if($int >= 0xbb && $int <= 0xd4)
  {
    return chr($int-0xbb+65);
  }
  // a-z
  if($int >= 0xd5 && $int <= 0xee)
  {
    return chr($int-0xd5+97);
  }
  return " ";
}

function arrayToBitStr($bytes)
{
  $bitStr = "";
  foreach($bytes as $byte)
    $bitStr .= leftPad(decbin($byte), "0", 8);
  return $bitStr;
}

function leftPad($str, $pad, $len)
{
  while(strlen($str) < $len)
    $str = $pad . $str;
  return $str;
}
