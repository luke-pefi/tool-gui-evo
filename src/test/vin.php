<?php 

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use vin\VIN;

echo "Making log...\n";

$log = new Logger('name');
$log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));

echo "Creating a VIN...\n";

$samples = [
    
  /* invalid */
    
  '4UFD99NWX9T117314',

  /* tutorial */
    
  '1M8GDM9AXKP042788',
    
  /* arctic cat */
    
  '4UF17SNW5HT808095',
  '4UF17SNW7HT113021',
  '4UF17SNW4HT114367',
  '4UF17SNW0HT113944',
  '4UF17SNW7HT114492'.
    
  /* ski-doo */
    
  '2BPSCP7A47V000100',
  '2BPSCU8A98V000298',
  '2BPSUGFD8FV000270',
  '2BPSERFAXFV000334',
  '2BPSDNAA5AV000124',
    
  /* Yamaha */
    
  'JYE8FT0097A006641',
  'JYE8GN0087A004128',
  'JYE8FE0014A000940',
  'JYE8JA00XAA001071',
    
  /* polaris */
    
  'SN1EG8PSXGC172923',
  'SN1CG8GS2CC484915',
  'SN1EG8PS0GC164619',
  'SN1CW6GS4FC520678',
  'SN1BF6KSXAC862146'
    
    
];

$vin = new VIN('JYE8JA00XAA001071', $log);

if(!$vin->isReady()) {
  echo "Can't valid VIN: ".$vin->getError()."\n";
  exit(1);  
}

$result = $vin->basicInfo();

echo "X: basics: ".print_r($result,true)."\n";

echo "Done.\n";

?>
