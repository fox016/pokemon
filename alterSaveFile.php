<?php

require_once(__DIR__ . '/attackList.php');
$attackList = new AttackList();

try
{
  $filename = "/Users/Admin/Library/Application Support/OpenEmu/Save States/GameBoy Advance/Pokemon - Leaf Green Version (U) (V1.1)/Auto Save State.oesavestate/State";
  if(isset($argv[1]))
    $filename = $argv[1];

  $byteArray = readBytes($filename);
  //$teamList = getTeamList($byteArray);
  //echo json_encode($teamList, JSON_PRETTY_PRINT) . "\n";

  //setMemberAttack($byteArray, 1, 4, 94);
  //setMemberAttack($byteArray, 1, 3, 344);
  //setMemberAttack($byteArray, 3, 4, 15);

  fillTeamHP($byteArray);
  healTeamStatus($byteArray);
  fillTeamPP($byteArray);

  writeBytes($byteArray, $filename);
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
  $securityKey = array_slice($byteArray, $startPos+0x0AF8, 4);

  // First member data start
  $startPos = getTeamListAddress();
  $members = array();
  for($i = 0; $i < 6; $i++)
  {
    $member = array();
    $personality = array_slice($byteArray, $startPos, 4);
    $otId = array_slice($byteArray, $startPos+4, 4);
    $member['name'] = arrayToString(array_slice($byteArray, $startPos+8, 10));
    $member['data'] = readMemberData(array_slice($byteArray, $startPos+32, 48), $personality, $otId);
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

  $money = array_slice($byteArray, $startPos, 4);
  /*
  echo arrayToBitStr($money) . "\n";
  echo arrayToBitStr($securityKey) . "\n";
  echo arrayToBitStr(xor32($money, $securityKey)) . "\n\n";
  echo arrayToInt($money) . "\n";
  echo arrayToInt($securityKey) . "\n";
   */

  $teamList = array(
    "trainer_name" => arrayToString($nameArray),
    //"money" => arrayToInt(xor32($money, $securityKey)), // Supposed to be 64922
    "members" => $members
  );
  //echo $teamList['money'] . "\n";
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

function readMemberData($dataBytes, $personality, $otId)
{
  $key = xor32($personality, $otId);
  $data = xor32($key, $dataBytes);
  $order = arrayToInt($personality) % 24;
  return array(
    "growth" => readGrowth($data, $order),
    "attacks" => readAttacks($data, getAttackBlock($order)),
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

function getAttackBlock($order)
{
  if($order >= 6 && $order <= 11)
    return 1;
  else if(in_array($order, array(0, 1, 14, 15, 20, 21)))
    return 2;
  else if(in_array($order, array(2, 4, 12, 17, 18, 23)))
    return 3;
  else
    return 4;
}

function readAttacks($data, $block)
{
  global $attackList;
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

function fillTeamPP(&$byteArray)
{
  global $attackList;

  // Get position for first team member's data section
  $startPos = getTeamListAddress();

  // For each team member
  for($i = 0; $i < 6; $i++)
  {
    // Decrypt data block
    $personality = array_slice($byteArray, $startPos, 4);
    $otId = array_slice($byteArray, $startPos+4, 4);
    $key = xor32($personality, $otId);
    $data = xor32($key, array_slice($byteArray, $startPos+32, 48));

    // Find position for moves and get move max PP
    $attackBlock = getAttackBlock(arrayToInt($personality) % 24);
    $pos = (($attackBlock-1)*12);
    $maxPP = array();
    for($moveOrder = 0; $moveOrder < 4; $moveOrder++)
    {
      $moveNumber = arrayToInt(array_slice($data, $pos + $moveOrder*2, 2));
      if($moveNumber !== 0)
        $maxPP[] = $attackList->getMovePP($moveNumber);
    }

    // Find position for PP and modify
    $ppPos = $pos + 8;
    foreach($maxPP as $max) {
      $data[$ppPos] = $max;
      $ppPos++;
    }

    // Calculate new checksum
    $checksum = array_fill(0, 2, 0);
    for($pos = 0; $pos < 48; $pos += 2)
    {
      $word = array_slice($data, $pos, 2);
      $checksum = addByteArrays($checksum, $word);
    }
 
    // Encrypt and replace old data in byte array
    $encrypted = xor32($key, $data);
    $pos = $startPos+32;
    for($j = 0; $j < 48; $j++) {
      $byteArray[$pos+$j] = $encrypted[$j];
    }

    // Replace old checksum in byte array
    $byteArray[$startPos+28] = $checksum[0];
    $byteArray[$startPos+29] = $checksum[1];

    // Onto next one
    $startPos += 100;
  }
}

/*
 * @param memberOrder 1-6
 * @param moveOrder 1-4
 * @param moveNumber 1-354
 */
function setMemberAttack(&$byteArray, $memberOrder, $moveOrder, $moveNumber)
{
  global $attackList;

  // Get position for team member's data section
  $startPos = getTeamListAddress() + (100 * ($memberOrder-1));

  // Decrypt data block
  $personality = array_slice($byteArray, $startPos, 4);
  $otId = array_slice($byteArray, $startPos+4, 4);
  $key = xor32($personality, $otId);
  $data = xor32($key, array_slice($byteArray, $startPos+32, 48));

  // Find position for move and modify it
  $attackBlock = getAttackBlock(arrayToInt($personality) % 24);
  $pos = (($attackBlock-1)*12) + (($moveOrder-1)*2);
  if($moveNumber < 256) {
    $data[$pos] = $moveNumber;
    $data[$pos+1] = 0;
  }
  else {
    $data[$pos] = $moveNumber-256;
    $data[$pos+1] = 1;
  }

  // Find position for PP and modify it
  $pp = $attackList->getMovePP($moveNumber);
  $ppPos = $pos;
  $ppPos += ((5 - $moveOrder) * 2) + ($moveOrder - 1);
  $data[$ppPos] = $pp;

  // Calculate new checksum (sum of 4 blocks)
  // To validate the checksum given in the encapsulating Pokémon data structure, the entirety of the four unencrypted data substructures must be summed into a 16-bit value.
  // Also, the checksum loops. Adding the unencrypted values should give you a value greater then 0xFFFF (max size), so it just loops. To find the correct value, MOD by 65536 (decimal) or 0x10000.
  $checksum = array_fill(0, 2, 0);
  for($pos = 0; $pos < 48; $pos += 2)
  {
    $word = array_slice($data, $pos, 2);
    $checksum = addByteArrays($checksum, $word);
  }
  
  // Encrypt and replace old data in byte array
  $encrypted = xor32($key, $data);
  $pos = $startPos+32;
  for($i = 0; $i < 48; $i++) {
    $byteArray[$pos+$i] = $encrypted[$i];
  }

  // Replace old checksum in byte array
  $byteArray[$startPos+28] = $checksum[0];
  $byteArray[$startPos+29] = $checksum[1];
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
    $bitStr .= leftPad(decbin($byte), "0", 8) . " ";
  return $bitStr;
}

function leftPad($str, $pad, $len)
{
  while(strlen($str) < $len)
    $str = $pad . $str;
  return $str;
}

/*
 * ====================== Binary functions
 */

function xor32($key, $bytes)
{
  $value = array();
  foreach($bytes as $i => $b1) {
    if(!is_numeric($b1) || !is_numeric($key[$i%4]))
      die("not numeric: {$b1}, {$key[$i%4]}\n");
    $value[] = $b1 ^ $key[$i%4];
  }
  return $value;
}

function addByteArrays($b1, $b2)
{
  $size = count($b1);
  $sum = array();
  $carry = 0;
  for($i = 0; $i < $size; $i++)
  {
    if(!is_numeric($b1[$i]) || !is_numeric($b2[$i]) || !is_numeric($carry))
      die("not numeric: {$b1[$i]}, {$b2[$i]}, $carry\n");
    $digit = $b1[$i] + $b2[$i] + $carry;
    $carry = 0;
    while($digit > 255) {
      $digit = $digit-256;
      $carry++;
    }
    $sum[] = $digit;
  }
  return $sum;
}
