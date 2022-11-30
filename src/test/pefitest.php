<?php

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use api\PefiAPI;

echo "Making log...\n";

$log    = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Making API...\n";

$config = [
  'autoregister' => false,
  'username'     => 'mgarvin',
  'password'     => '1Nush00z'
];

$api    = new PefiAPI($config, $log);

if(!$api->isReady()) {

  echo "Could not make API: ".$api->getError()."\n";
  exit(1);
}

/*
echo "Fetching assigned licenses report...\n";

$report = $api->assignedSummary();

if(!is_array($report)) {

  echo "Could not fetch report: ".$api->getError()."\n";
  exit(1);
}

echo "Report: ".print_r($report,true)."\n";
*/

/*
echo "Updating flash status...\n";

$status = $api->flashUpdate(4, 'flash_flashing', "Flash process started on RPI");

if(!$status) {
  echo "Could not update flash status: ".$api->getError()."\n";
  exit(1);
}
*/


echo "fetching burnables......\n";

$items = $api->flashes('4UF18MPV1JT301973');

if(!$items) {
  echo "Could not update flash status: ".$api->getError()."\n";
  exit(1);
}

echo "Report: ".print_r($items,true)."\n";


/*
echo "downloading flash...\n";

$info = $api->flashDownload(8);

if(!$info) {
  echo "Could not download flash: ".$api->getError()."\n";
  exit(1);
}

echo "X: flash info: ".print_r($info,true)."\n";
*/

echo "OK.\n";

echo "Done.\n";

?>

