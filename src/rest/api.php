<?php

/**
 * 
 * api.php - this is the front end to our REST API on the Raspberry PI.  All REST endpoints, in fact all web traffic 
 * is handled here.  The PI doesn't have a web server, except for the REST API that is used by the GUI (i.e. the 
 * touch screen).
 * 
 * We funnel logging into the rest.log file we want to use, and we ensure the Dispatcher is called to actually handle
 * the REST call.
 * 
 */

$errorLog = dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'php.log';

if(!is_writable($errorLog)) {
  touch($errorLog);
  if(!is_writable($errorLog)) {
    die("Can not write to log file: $errorLog\n");
  }
}

ini_set("log_errors",   1);
ini_set("error_log",    $errorLog);

/* setup auto-loading */

require dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

/* open the PHP low level log file (for syntax errors etc.) */

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use rest\Dispatcher;

/* open our application level (REST API) log */

$apiLog   = dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'rest.log';
$log      = new Logger('name');

$log->pushHandler(new StreamHandler($apiLog, Logger::DEBUG));

/* make the dispatcher that routes incoming requests to the broker */

$dispatch = new Dispatcher(null, $log);

if(!$dispatch->isReady()) {

  error_log("[REST API][FATAL] Could not create a dispatcher: ".$dispatch->getError());

  $msg = "System Error, please contact Administrator.\n";
  exit(1);  
}

/* Do it! Route me now!! */

$dispatch->run();

/*
 * clean up our local PHP file while we're at it, we don't let it get bigger than 10MB
 *
 */

$limit    = 10*1024*1024;
$size     = filesize($errorLog);

if(!is_file($errorLog)) {
  error_log("[REST API][INFO]There is no $errorLog file.");
}

if($size > $limit) {
  error_log("[REST API][INFO] Truncating $errorLog");
  $h = fopen($errorLog, 'r+');
  ftruncate($h, 0);
  fclose($h);
}

/*
 * clean up our REST log file while we're at it, we don't let it get bigger than 10MB
 *
 */

$limit    = 10*1024*1024;
$size     = filesize($apiLog);

if(!is_file($apiLog)) {
  error_log("[REST API][INFO]There is no $apiLog file.");
}

if($size > $limit) {
  error_log("[REST API][INFO] Truncating $apiLog");
  $h = fopen($apiLog, 'r+');
  ftruncate($h, 0);
  fclose($h);
}

exit(0);