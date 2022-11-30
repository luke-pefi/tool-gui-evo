<?php

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use util\RaspberryInfo;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

$info = RaspberryInfo::info();

echo "X: info: ".print_r($info,true)."\n";

?>

