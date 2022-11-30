<?php

/**
 *
 * ECU - this is our wrapper for the ECU daemon running on port 5999.  The ECU daemon provides us with high level
 * programming access to the connected ECU (over CAN Bus).
 *
 */

namespace api;

use Monolog\Logger;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

use util\LoggingTrait;
use util\StatusTrait;

use vin\VIN;

use util\RaspberryInfo;

class ECU {

  /* bring in logging and status behaviors */

  use LoggingTrait;
  use StatusTrait;

  protected $factoryDefault = [
    'port'    => 'localhost:5999',
    'timeout' => 20.0,
    'debug'   => false
  ];

  /**
   *
   * standard constructor
   *
   * @param object $config this must be the details for the socket connection.  See $factoryDefault for an example.
   *
   * If you don't provide it, this class will attempt to use some hard coded default settings which may or may not
   * work. You should almost certainly provide your connection configuration.
   *
   * If you provide only parts of the configuration, then only they will be used to override the otherwise default
   * configuration.  So if you just want to provide a different read timeout, you only have to do:
   *
   *   ['read_timeout' => 12345]
   *
   * and the other attributes will be factory default.
   *
   * @param Monolog\Logger $logger you can optionally provide a logger
   *
   */

  public function __construct($config=null, $logger=null) {

    /* connect to the system log */

    $this->setLogger($logger);

    $this->unReady();

    /* configure for the database */

    if($config !== null) {

      if(is_array($config)) {
        $this->factoryDefault = array_replace($this->factoryDefault, $config);
      } else {
        $this->setError(get_class()."() - expecting an array for configuration.");
        return ;
      }
    }

    /*
     * all exchanges with the ECU daemon are local to that service call, we don't susstain any kind of open
     * connection accross service calls.
     *
     */


    if(!$this->ping()) {

      $this->setError(get_class()."() - daemon is not responsive.");
      return ;
    }

    /* if we get this far, we are good to go! */

    $this->makeReady();

  }

  /**
   *
   * dtc() - quick snapshot of trouble codes
   *
   * @return bool exactly false if the ECU daemon is not reachable.  Otherwise a PHP object with the results.
   *
   */

  public function dtc() {

    $this->debug(get_class()."::dtc...");

    $cmd = (object)[
      'command' => 'dtc'
    ];

    $obj = $this->request($cmd);

    if($obj === false) {

      $this->setError(get_class()."::dtc no response from daemon, offline?");
      return false;
    }

    if(isset($obj->status) && ($obj->status != "OK")) {

      $this->setError(get_class()."::dtc could not fetch: {$obj->error}");
      return false;
    }

    if(isset($obj->status) && !isset($obj->status->response->codes)) {

      $this->setError(get_class()."::dtc no response codes, ECU offline?");
      return false;
    }

    /* pass it back */

    return $obj;
  }

  /**
   *
   * dtcclr() - clear the DTCs
   *
   * @return bool exactly false if the ECU daemon is not reachable.  Otherwise a PHP object with the results.
   *
   */

  public function dtcclr() {

    $this->debug(get_class()."::dtc...");

    $cmd = (object)[
      'command' => 'dtcclr'
    ];

    $obj = $this->request($cmd);

    if($obj === false) {
      return false;
    }

    if(isset($obj->status) && ($obj->status != "OK")) {

      $this->setError(get_class()."::dtcclr could not fetch result: {$obj->error}");
      return false;
    }

    /* pass it back */

    return $obj;
  }

  /**
   *
   * sample() - quick snapshot of ECU data channels
   *
   * @return bool exactly false if the ECU daemon is not reachable.  Otherwise a PHP object with the results.
   *
   */

  public function sample() {

    $this->debug(get_class()."::sample...");

    $cmd = (object)[
      'command' => 'sample'
    ];

    $obj = $this->request($cmd);

    if($obj === false) {
      return false;
    }

    if(isset($obj->status) && ($obj->status != "OK")) {

      $this->setError(get_class()."::sample could not fetch: {$obj->error}");
      return false;
    }

    /* pass it back */

    return $obj;
  }

  /**
   *
   * info() - quick fetch of basic info from the ECU (assuming it's running)
   *
   * @return bool exactly false if the ECU daemon is not reachable.  Otherwise a PHP object with the results.
   *
   */

  public function info() {

    $this->debug(get_class() . "::info...");

    $cmd = (object)[
      'command' => 'info',
      'noretry' => 'true'
    ];

    $obj = $this->request($cmd);

    if ($obj === false) {
      return false;
    }

    if (isset($obj->status) && ($obj->status != "OK")) {

      $this->setError(get_class() . "::info could not fetch info: {$obj->error}");
      return false;
    }

    if (!isset($obj->response->list)) {

      $this->setError(get_class() . "::info could not fetch info, no device list.  ECU/Dash offline?");
      return false;
    }

    /* $this->debug(get_class() . "::info list: ".print_r($obj->response->list,true)); */

    /*
     * normally we're get back a list of connected devices, usually there should be a maximum  of
     * two; the ECU and the Dashboard.  The ECU (if present) is the one with the VIN # and/or has
     * the 'dash' field filled in.
     *
     */

    $ecuInfo  = false;
    $dashInfo = false;

    foreach ($obj->response->list as $item) {

      if (!empty($item->dash)) {

        $dashInfo = $item;

      } else {

        $ecuInfo  = $item;
      }
    }

    /* now we have to merge into a single response that the GUI / user can use */

    if ($ecuInfo) {

      $response = $ecuInfo;

      unset($response->blob);

      $response->ecu = "Unknown";

      $models = preg_split("/,/", $response->ecu_model);

      if(in_array('cat_8000_2017', $models)) {

        $response->ecu = "Cat 8000";

      } else if(in_array('brp_900_t_2019', $models)) {

        $response->ecu = "BRP 900 ACE Turbo";

      } else if(in_array('ac_t_2017', $models)) {

        $response->ecu = "Sidewinder / Thundercat";

      } else if(in_array('wc_t_2017', $models)) {

        $response->ecu = "WildCat";
      }

      if(isset($response->allInfos)) {

        $response->allinfos = $response->allInfos;
        unset($response->allInfos);
      }

      if(isset($response->programDate)) {

        $response->programdate = $response->programDate;
        unset($response->programDate);
      }

      if(isset($response->shopCode)) {

        $response->shopcode = $response->shopCode;
        unset($response->shopCode);
      }

      if(!isset($response->dashblob)) {

        $response->dashblob = '';
      }

    } else {

      $response = (object)[
        'allinfos'       => '',
        'checksum'       => '',
        'checksum2'      => '',
        'ecu'            => '',
        'dash'           => '',
        'programdate'    => '',
        'shopcode'       => '',
        'version'        => '',
        'vin'            => '',
        'ecu_model'      => '',
        'dash_model'     => '',
        'dashblob'       => '',
        'dash_checksum'  => '',
        'dash_checksum2' => ''
      ];
    }

    if($dashInfo) {

      if(!empty($dashInfo->dash)) {

        $response->dash = $dashInfo->dash;
      }

      if(!empty($dashInfo->blob)) {

        $response->dashblob = $dashInfo->blob;
      }

      if(!empty($dashInfo->dash_checksum)) {

        $response->dash_checksum = $dashInfo->dash_checksum;
      }

      if(!empty($dashInfo->dash_checksum2)) {

        $response->dash_checksum2 = $dashInfo->dash_checksum2;
      }

      if(!empty($dashInfo->dash_model)) {

        $response->dash_model = $dashInfo->dash_model;
      }
    }

    $obj->response = $response;

    /* do any post-processing on the response */

    if(isset($obj->response->version)) {

      $matches = [];
      if (preg_match('/^(.*[vV]\d+.\d+.\d+)/', $obj->response->version, $matches)) {
        $obj->response->version = $matches[1];
      }
    }

    if(isset($obj->response->dashblob) && !empty($obj->response->dashblob)) {

      /* look for installed PEFI dash image */

      if (preg_match('/\|\d+PF\s*v([0-9\.\-]+)\|/', $obj->response->dashblob, $matches)) {
        $obj->response->dash = "PEFI v{$matches[1]}";
      }

      /* look for anything that identifies Yamaha/Artic Cat */

      $manufacturer = "";

      if (preg_match('/0720-041/', $obj->response->dashblob) ||
        preg_match('/01615-01/', $obj->response->dashblob)) {

        $manufacturer = "Arctic Cat";

      } else if (preg_match('/0720-040/', $obj->response->dashblob) ||
        preg_match('/01669-01/', $obj->response->dashblob)) {

        $manufacturer = "Yamaha";

      } else if (preg_match('/ATV\s+ISO\s+CAN/', $obj->response->dashblob)) {

        $manufacturer = "Arctic Cat";
      }

      if($manufacturer=='') {

        /*
         * we can't figure out what the manufacturer is from the Dash, but if there is a Dash then there must also
         * be an ECU...and an ECU has a VIN #.  So, try to get the manufacturer from teh VIN #...
         *
         */

        $vin = new VIN($obj->response->vin, $this->getLogger());

        if($vin->isReady()) {

          $result               = $vin->basicInfo();

          $this->debug(get_class()."::info VIN INFO: ".print_r($result,true)."\n");

          $manufacturer = $result['manufacturer'];
        }

      }

      if(!preg_match('/^\s*'.$manufacturer.'/', $obj->response->dash)) {

        if(!empty($obj->response->dash)) {
          $obj->response->dash .= ' - ';
        }

        $obj->response->dash .= $manufacturer;
      }

    }

    /* make sure the dash id blob and the version do not have any non-ascii chars */

    $obj->response->dashblob = preg_replace('/[[:^print:]]/', '', $obj->response->dashblob);
    $obj->response->version  = preg_replace('/[[:^print:]]/', '', $obj->response->version);

    if (empty($obj->response->ecu) && empty($obj->response->dash) ) {

      $this->setError(get_class() . "::info no devices found.  ECU/Dash offline?");
      return false;
    }

    /* pass it back */

    return $obj->response;
  }

  /**
   *
   * ping() - quick test to see if the ECU daemon is running and we can talk to it.
   *
   * @return bool exactly false if the ECU daemon is not reachable.
   *
   */

  public function ping() {

    $this->debug(get_class()."::ping...");

    $cmd = (object)[
      'command' => 'ping'
    ];

    $obj = $this->request($cmd);

    if($obj === false) {
      return false;
    }

    $this->debug(get_class()."::...pong");

    return true;
  }

  /**
   *
   * request() - given a JSON style string request, send it to the daemon, and give back the results, timing out if
   * the daemon is not responding...
   *
   * @param object $argObj - a PHP object with attributes that are the command arguments.  You must have at least
   * one attribute, which named 'command', with a value that is the name of the command to run.
   *
   * @return boolean exactly false on any kind of error.  Otherwise a PHP object which is the result.
   *
   */

  public function request($argObj) {

    /* try to setup for command... */

    if(!is_object($argObj)) {

      $this->setError(get_class()."::request expecting argument to be an object.");
      return false;
    }

    $r1         = microtime(true);
    $jsonString = json_encode($argObj);

    $this->debug(get_class()."::request starts $jsonString...");

    /* try to connect ... */

    $errno        = false;
    $errstr       = false;

    $socket = stream_socket_client(
      $this->factoryDefault['port'],
      $errno,
      $errstr,
      $this->factoryDefault['timeout']
    );

    if($socket === false) {

      $this->setError(get_class()."::request could not connect to socket ({$this->factoryDefault['socket']}) [$errno]: $errstr");
      return false;
    }

    /* seem to be connected, set the read timeout ... */

    stream_set_timeout($socket, $this->factoryDefault['timeout']);

    /* send the command ... */

    if(!fwrite($socket, "$jsonString\n")) {

      fclose($socket);
      $this->setError(get_class()."::request could not send request.");
      return false;
    }

    /* await the response... */

    $response = trim(fgets($socket));
    $result   = json_decode($response);

    fclose($socket);

    if(!is_object($result)) {

      $this->setError(get_class()."::request can not decode response.");
      return false;
    }

    /* all done */

    $r2 = microtime(true);

    $this->debug(get_class()."::request time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");

    return $result;
  }

  /**
   *
   * flash() - given a ROM file, flash it to the ECU
   *
   * @param $file     the ROM file to use for flashing the ECU
   * @param $model    the Dash model it is compatible with
   * @param $progress the closure to invoke with progress updates as we are flashing.
   *
   * @return mixed exactly false on error, otherwise the final status.
   *
   */

  public function flash($file, $model, \Closure $progress) {

    /*
     * This is a special case, its not just one line out/one line back.  We send our request, and then we get
     * a bunch of status updates...and then we get one final message (either OK or FAIL on the whole thing).
     *
     * So we send our request and the we loop...if we get a final OK/FAIL, we stop, otherwise we pass the
     * "progress" to the progress callback.
     *
     */

    /* check the arguments ... */

    if(!file_exists($file)) {

      $this->setError(get_class()."::flash can not find ROM file ($file)");
      return false;
    }

    if(empty($model)) {

      $this->setError(get_class()."::flash no ECU model provided.");
      return false;
    }

    /* try to setup for command... */


    $argObj = (object)[
      'command' => 'flash',
      'file'    => $file,
      'model'   => $model
    ];


    /*
    $argObj = (object)[
      'command' => 'okprogress'
    ];
    */

    $r1         = microtime(true);
    $jsonString = json_encode($argObj);

    $this->debug(get_class()."::flash starts $jsonString...");

    /* try to connect ... */

    $errno        = false;
    $errstr       = false;

    $socket = stream_socket_client(
      $this->factoryDefault['port'],
      $errno,
      $errstr,
      $this->factoryDefault['timeout']
    );

    if($socket === false) {

      $this->setError(get_class()."::flash could not connect to socket ({$this->factoryDefault['socket']}) [$errno]: $errstr");
      return false;
    }

    /* seem to be connected, set the read timeout ... */

    stream_set_timeout($socket, $this->factoryDefault['timeout']);

    /* send the request ... */

    if(!fwrite($socket, "$jsonString\n")) {

      fclose($socket);
      $this->setError(get_class()."::flash could not send request.");
      return false;
    }

    /*
     * ok, loop watching for either a progress update, or the final status...
     *
     */

    $result     = [];

    while(true) {

      /* try read this one... */

      $response = trim(fgets($socket));

      if(empty($response)) {
        $this->setError(get_class()."::flash got an empty line.");
        continue;
      }

      $result   = json_decode($response);

      if(!is_object($result)) {

        fclose($socket);
        $this->setError(get_class()."::flash garbled response ($response).");
        return false;
      }

      if(!isset($result->request)) {

        /* its a progress update */

        $progress($result);
        continue;
      }

      /* final status */

      break;
    }

    fclose($socket);

    if(isset($result->status) && ($result->status != "OK")) {

      $this->setError(get_class()."::flash incomplete flash: {$result->error}");
      return false;
    }

    /*
     * NOTE: the response we get is of the form:
     *
     *   [response] => stdClass Object
     *        (
     *            [chunksize] => 258
     *            [elapsed] =>  0:56
     *            [eta] =>  0:00
     *            [file] => /home/mgarvin/can-daemon/test/cat8000.bin
     *            [postchecksum] => 0x664B9CD6
     *            [prechecksum] => 0x664B9CD6
     *            [speed] =>   4.23 KB/sec
     *            [total] => 228.00 KB Flashed
     *            [totalsofar] => 228.00
     *            [vin] => 4UF18SNW7JT101857
     *        )
     *
     */

    /* pass back the final status */

    $r2 = microtime(true);

    $this->debug(get_class()."::flash time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");

    return $result;
  }

  /**
   *
   * dashflash() - given a ROM file, flash it to the Dash
   *
   * @param $file     the ROM file to use for flashing the Dash
   * @param $model    the Dash model it is compatible with
   * @param $progress the closure to invoke with progress updates as we are flashing.
   *
   * @return mixed exactly false on error, otherwise the final status.
   *
   */

  public function dashflash($file, $model, \Closure $progress) {

    /*
     * This is a special case, its not just one line out/one line back.  We send our request, and then we get
     * a bunch of status updates...and then we get one final message (either OK or FAIL on the whole thing).
     *
     * So we send our request and the we loop...if we get a final OK/FAIL, we stop, otherwise we pass the
     * "progress" to the progress callback.
     *
     */

    /* check the arguments ... */

    if(!file_exists($file)) {

      $this->setError(get_class()."::dashflash can not find ROM file ($file)");
      return false;
    }

    if(empty($model)) {

      $this->setError(get_class()."::dashflash no ECU model provided.");
      return false;
    }

    /* try to setup for command... */


    $argObj = (object)[
      'command' => 'dashflash',
      'file'    => $file,
      'model'   => $model
    ];


    /*
    $argObj = (object)[
      'command' => 'okprogress'
    ];
    */

    $r1         = microtime(true);
    $jsonString = json_encode($argObj);

    $this->debug(get_class()."::dashflash starts $jsonString...");

    /* try to connect ... */

    $errno        = false;
    $errstr       = false;

    $socket = stream_socket_client(
      $this->factoryDefault['port'],
      $errno,
      $errstr,
      $this->factoryDefault['timeout']
    );

    if($socket === false) {

      $this->setError(get_class()."::dashflash could not connect to socket ({$this->factoryDefault['socket']}) [$errno]: $errstr");
      return false;
    }

    /* seem to be connected, set the read timeout ... */

    stream_set_timeout($socket, $this->factoryDefault['timeout']);

    /* send the request ... */

    if(!fwrite($socket, "$jsonString\n")) {

      fclose($socket);
      $this->setError(get_class()."::dashflash could not send request.");
      return false;
    }

    /*
     * ok, loop watching for either a progress update, or the final status...
     *
     */

    $result     = [];

    while(true) {

      /* try read this one... */

      $response = trim(fgets($socket));

      if(empty($response)) {
        $this->setError(get_class()."::dashflash got an empty line.");
        continue;
      }

      $result   = json_decode($response);

      if(!is_object($result)) {

        fclose($socket);
        $this->setError(get_class()."::dashflash garbled response ($response).");
        return false;
      }

      if(!isset($result->request)) {

        /* its a progress update */

        $progress($result);
        continue;
      }

      /* final status */

      break;
    }

    fclose($socket);

    if(isset($result->status) && ($result->status != "OK")) {

      $this->setError(get_class()."::dashflash incomplete flash: {$result->error}");
      return false;
    }

    /* pass back the final status */

    $r2 = microtime(true);

    $this->debug(get_class()."::dashflash time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");

    return $result;
  }

}

?>