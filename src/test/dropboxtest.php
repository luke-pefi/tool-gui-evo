<?php

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use api\DropboxConnector;

echo "Making log...\n";

$log    = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Making API...\n";

$api    = new DropboxConnector(['user' => 'mgarvin'], $log);

if(!$api->isReady()) {

  echo "Could not make API: ".$api->getError()."\n";
  exit(1);
}

/*
if(!$api->rm('/support/uploads/mgarvin/gui2.php')) {
  echo "Could no remove file: ".$api->getError()."\n";
  exit(1);
}
*/


/*
$info = $api->ls("/support/uploads/mgarvin/");
echo "X: info: ".print_r($info,true)."\n";
*/

/*
$info = $api->copy('./gui.php', '/support/uploads/mgarvin/gui2.php');
echo "X: info: ".print_r($info,true)."\n";
*/

/*
$info = $api->is_file("/support/uploads/mgarvin");
echo "X: info: ".print_r($info,true)."\n";
*/

/*
$status = $api->mkdir("/support/uploads/mgarvin");
if(!$status) {
  echo "Could not make folder: ".$api->getError()."\n";
  exit(1);
}
*/


$files = $api->findSupportLogs();
echo "X: ".print_r($files, true)."\n";
exit(1);

//$info = $api->fileinfo("/mike");

$status = $api->sendLogs();

if(!$status) {
  echo "Could not send logs: ".$api->getError()."\n";
  exit(1);
}

echo "Done.\n";

?>

