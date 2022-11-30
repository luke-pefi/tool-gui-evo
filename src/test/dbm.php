<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use networking\DBM;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Getting dBm info...\n";

$result = DBM::info('-82dBm');

echo "X: info: ".print_r($result,true)."\n";

echo "Done.\n";

?>