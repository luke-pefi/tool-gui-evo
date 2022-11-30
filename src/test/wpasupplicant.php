<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use networking\WPASupplicantConfig;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Creating a config parser...\n";

$wpa = new WPASupplicantConfig(null, $log);

if(!$wpa->isReady()) {
  echo "Can't make config file manager: ".$wpa->getError()."\n";
  exit(1);  
}

/*
$networks = $wpa->getNetworks();

echo "X: networks: ".print_r($networks,true)."\n";

*/

/*
echo "Saving...\n";

if(!$wpa->save()) {
  echo "Can't save config: ".$wpa->getError()."\n";
  exit(1);  
}

*/

echo "Setting new password...\n";

$ssid = 'mgarvin';
$pass = '"yyy"';

if(!$wpa->remember($ssid, $pass)) {
  echo "Can't remember password: ".$wpa->getError()."\n";
  exit(1); 
}

if(!$wpa->authenticate($ssid, $pass)) {
  echo "Can't authenticate password ($ssid:$pass): ".$wpa->getError()."\n";
  exit(1); 
}

/*
echo "Forgetting password...\n";

if(!$wpa->forget($ssid)) {
  echo "Can't forget password: ".$wpa->getError()."\n";
  exit(1);
}
*/

echo "Done.\n";

?>