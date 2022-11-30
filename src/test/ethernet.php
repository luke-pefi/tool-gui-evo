<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use networking\Ethernet;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Getting connection...\n";

$result = Ethernet::connection();

echo "X: info: ".print_r($result,true)."\n";

echo "Done.\n";

?>