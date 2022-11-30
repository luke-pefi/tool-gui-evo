<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use networking\WiFi;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Making controller ...\n";

$wifi = new WiFi(['interface' => 'wlan0'], $log);

if(!$wifi->isReady()) {
  echo "Can't valid WiFi: ".$wifi->getError()."\n";
  exit(1);  
}

/* Mayblitz-guest  mgarvin */


echo "Joining...\n";

$con      = $wifi->join('NETGEAR25', 'vastbox585');

if($con === false) {
  echo "Could not join network: ".$wifi->getError()."\n";
  exit(1);  
}


/*
echo "Forgetting...\n";

if(!$wifi->forget('mgarvin')) {
  echo "Cloud not forget: ".$wifi->getError()."\n";
  exit(1);
}
*/

echo "Listing networks...\n";

$networks = $wifi->scan();

echo "X: networks: ".print_r($networks,true)."\n";


/*
echo "Fetching current connection...\n";

$info     = $wifi->connection();

echo "X: connection: ".print_r($info,true)."\n";
*/

/*
echo "Disconnecting...\n";

if(!$wifi->disconnect()) {
  echo "Cloud not leave WiFi: ".$wifi->getError()."\n";
  exit(1);
}
*/

echo "Done.\n";

?>