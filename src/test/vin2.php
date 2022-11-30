<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use vin\VIN;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Creating a VIN...\n";


$vin = new VIN('4UF17SNW5HT808095', $log);

if(!$vin->isReady()) {
  echo "Can't valid VIN: ".$vin->getError()."\n";
  exit(1);  
}

$result = $vin->basicInfo();

echo "X: basics: ".print_r($result,true)."\n";

echo "Done.\n";

?>
