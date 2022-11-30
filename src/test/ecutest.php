<?php

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use api\ECU;

echo "Making log...\n";

$log    = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Making API...\n";

$api    = new ECU(null, $log);

if(!$api->isReady()) {

  echo "Could not make API: ".$api->getError()."\n";
  exit(1);
}

echo "Pinging...\n";;

if(!$api->ping()) {

  echo "daemon not responding: ".$api->getError()."\n";
  exit(1);
}

/* basic operations ... */

$result = $api->info();

if($result === false) {

  echo "failed: ".$api->getError()."\n";
  exit(1);
}

echo "X: status info: ".print_r($result,true)."\n";

/*
echo "Fetching DTC codes...\n";
$result = $api->dtc();

if($result === false) {

  echo "failed: ".$api->getError()."\n";
  exit(1);
}

echo "X: DTC codes: ".print_r($result,true)."\n";
*/

/*
echo "Clearing DTC codes...\n";
$result = $api->dtcclr();

if($result === false) {

  echo "failed: ".$api->getError()."\n";
  exit(1);
}

echo "X: DTC codes: ".print_r($result,true)."\n";
*/

/*
$result = $api->sample();

if($result === false) {

  echo "failed: ".$api->getError()."\n";
  exit(1);
}

echo "X: results: ".print_r($result,true)."\n";
*/

/* do a flash... */

/*
$result = $api->flash(
  "/home/mgarvin/can-daemon/test/wildcat-stock.bin",
  "wc_t_2017",
  function($progress) {

  echo "X: update: ".print_r($progress, true)."\n";
});

if($result === false) {

  echo "Could not do flash: ".$api->getError()."\n";
  exit(1);
}

echo "X: result: ".print_r($result,true)."\n";
*/


echo "OK.\n";

echo "Done.\n";

?>
