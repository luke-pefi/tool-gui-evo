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

/* do a flash... */

$result = $api->dashflash(
  "/home/mgarvin/public_html/src/test/multimap_dash_with_LC.bin",
  "ac_dash_2017",
  function($progress) {

  echo "X: update: ".print_r($progress, true)."\n";
});

if($result === false) {
  echo "Could not do flash: ".$api->getError()."\n";
  exit(1);
}

echo "X: result: ".print_r($result,true)."\n";

echo "Done.\n";

?>

