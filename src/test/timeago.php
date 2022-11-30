<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use util\TimeAgo;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Formatting time...\n";

echo "X: ".TimeAgo::ago(time())."\n";

echo "Done.\n";

?>