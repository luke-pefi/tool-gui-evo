<?php

/**
 * 
 * Dispatcher - handle REST API requests (all endpoints)
 * 
 * You can find docs/user guide for the Router over here:
 * 
 *   http://route.thephpleague.com/
 *   
 * For PSR7 style request and response, there are some good examples here:
 * 
 *   https://mwop.net/blog/2015-01-26-psr-7-by-example.html
 *   
 * basically request and response are mostly "message" interface.
 * 
 */

namespace rest;

use util\LoggingTrait;
use util\StatusTrait;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use League\Route\Http\Exception\NotFoundException;
use League\Route\Http\Exception\MethodNotAllowedException;
use League\Container\Container;
use League\Route\RouteCollection;

use Zend\Diactoros\Stream;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\Response\JsonResponse;

use networking\WiFi;
use api\PefiAPI;
use api\DropboxConnector;
use api\ECU;
use vin\VIN;

class Dispatcher {
  
  /* bring in logging and status behaviors */

  use LoggingTrait;
  use StatusTrait;
  
  /**
   *
   * @var array $factoryDefault the default confiugration
   *
   */

  protected $factoryDefault     = [];

  /**
   *
   * standard constructor, you can optionally provider options and a Mono logger to use for logging.
   *
   *  @param array          $config options (optional)
   *  @param Monolog\Logger $logger you can optionally provide a logger (optional)
   *
   */

  public function __construct($config=null, $logger=null) {

    $r1 = microtime(true);
  
    /* connect to the system log */

    $this->setLogger($logger);

    $this->unReady();

    /* configure */

    if($config !== null) {

      if(is_array($config)) {
        $this->factoryDefault = array_replace($this->factoryDefault, $config);
      } else {
        $this->setError(get_class()."() - expecting an array for configuration.");
        return ;
      }
    }
    
    /* 
     * if we eventually have commonly used features like RAM Disk, caching etc.  We can 
     * also create those controllers here (once), instead of trying to create them in 
     * each and every class that needs them.
     * 
     */
    

    /* if we get this far, everything is ok */

    $this->makeReady();

    $r2 = microtime(true);

    $this->debug(get_class()."() time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");
  }

  /**
   *
   * yaffleError() - send (via EventSource stream) an INFO back to the client, and exit.  This is the EventSource
   * version of die().
   *
   * @param string $msg the actual info message
   * @param integer $id  the event sequence id (expected to be unique and time ordered)
   *
   * @note no return, this will exist the current script.
   *
   */

  private function yaffleInfo($msg, $id) {

    $data = json_encode((object)['status' => 'INFO', 'message' => $msg]);
    $this->info($msg);

    echo "id: $id\n";
    echo "event: info\n";
    echo "data: $data\n\n";

    ob_flush();
    flush();

  }

  /**
   *
   * yaffleError() - send (via EventSource stream) an error back to the client, and exit.  This is the EventSource
   * version of die().
   *
   * @param string $msg the actual error message
   * @param integer $id  the event sequence id (expected to be unique and time ordered)
   *
   * @note no return, this will exist the current script.
   *
   */

  private function yaffleError($msg, $id) {

    $this->error($msg);

    $data = json_encode((object)['status' => 'ERROR', 'message' => $msg]);

    echo "id: $id\n";
    echo "event: error\n";
    echo "data: $data\n\n";

    ob_flush();
    flush();
    sleep(1);
    $this->debug("[flash] exiting.");
    exit(0);

  }

  /**
   * 
   * clientIP() internal helper to fetch the (apparent) IP address of this request
   * For details see:
   * 
   *   http://stackoverflow.com/questions/15699101/get-the-client-ip-address-using-php
   *   
   * @return string our best guess as the remote IP.
   * 
   */
  
  private function clientIP() {

    $ipaddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP']))
      $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
      $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
      $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
      $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
      $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
      $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
      $ipaddress = 'UNKNOWN';
    
    return $ipaddress;
  }
    
  /**
   * 
   * run() - process the incoming web request, routing it to the appropriate
   * broker method.
   *  
   * @return boolean exactly false on error.
   * 
   */
  
  public function run() {

    /*$this->debug(get_class()."::run top");*/

    /* on every request we make sure we have a PHP session, but that it doesn't get too old */

    try {

      \session_start();

    } catch (\Exception $e) {

      $this->setError(get_class()."::run can not start session: ".$e->getMessage());
      return false;
    }

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > (3600 * 4))) {
      \session_unset();
      \session_destroy();
    }



    $_SESSION['LAST_ACTIVITY'] = time();

    /* normal processing of request... */

    $ip  = $this->clientIP();
    $r1  = microtime(true);
    $url = $_SERVER['REQUEST_URI'];
    
    if(!$this->isReady()) {

      $this->setError(get_class()."::run object not ready.");
      return false;
    }
    
    /*$this->debug(get_class()."::run starts (remote: $ip) $url ...");*/
    
    /* 
     * make a basic web applicatoin container, this is the engine that will make 
     * sure auto-wiring, events etc all happen as they should.  We basiclaly use
     * it to host our URL router to define our API.
     * 
     */
    
    $container = new Container;

    /* implementer for responses */
    
    $container->share('response', Response::class);
    
    /* implementor for requests */
    
    $container->share('request', function () {
      return ServerRequestFactory::fromGlobals(
        $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
      );
    });

    /* implementing event handler */
    
    $container->share('emitter', SapiEmitter::class);

    /* ok, define our URL routes... */
    
    $router      = new RouteCollection($container);


    /*
     * helper to initiate the upload of support info to drop box.
     *
     */

    $router->map('POST','/rest/support/sendlogs/{userid}',function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      /* check the parameters */

      if(!isset($args['userid']) || empty($args['userid'])) {

        $result = (object)[
          'status'  => 'ERROR',
          'message' => 'No Userid Supplied'
        ];

        $response->getBody()->write(json_encode($result));

        return $response;
      }

      /* connect to dropbox */

      $api    = new DropboxConnector(['user' => $args['userid']], $this->getLogger());

      if(!$api->isReady()) {

        $result = (object)[
          'status'  => 'ERROR',
          'message' => "Can't connect to Dropbox: ".$api->getError()
        ];

        $response->getBody()->write(json_encode($result));

        return $response;
      }

      /* upload! */

      $status = $api->sendLogs();

      if(!$status) {

        $result = (object)[
          'status'  => 'ERROR',
          'message' => "Problems uploading: ".$api->getError()
        ];

        $response->getBody()->write(json_encode($result));

        return $response;
      }

      /* if we get this far we're golden */

      $result = (object)[
        'status'  => 'OK',
        'message' => '<strong>Support info uploaded.</strong>'
      ];

      $response->getBody()->write(json_encode($result));

      return $response;
    });

    /*
     * simple fetch of the team viewer id
     *
     */

    $router->map('GET','/rest/support/partnerid',function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $partnerID = `/usr/bin/teamviewer -info | /bin/grep -P 'TeamViewer\s+ID:' | /usr/bin/awk '{print \$NF;}'`;
      $partnerID = trim($partnerID);

      $response->getBody()->write(json_encode((object)['partnerid' => $partnerID]));

      return $response;

    });

    /*
     * fire and forget method to launch a support terminal (password protected).
     *
     */

    $router->map('GET','/rest/support/terminal',function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      `/usr/bin/aterm -display :0 -bg black -fg yellow -tr -trsb -sh 10 -fn '-adobe-courier-medium-r-normal--25-180-100-100-m-150-iso10646-1' -e /bin/su -c /bin/bash - mgarvin`;

      $response->getBody()->write(json_encode((object)[]));

      return $response;

    });

    /*
     * fetch the current ECU info
     *
     */

    $router->map('GET', '/rest/ecu/dtc', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/ecu/dtc  ////');

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Could not initialize ECU: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * fetch!
       *
       */

      $info = $ecu->dtc();

      if($info === false) {

        $data = (object)[
          'status'   => 'ERROR',
          'message'  => 'Can not determine ECU dtc info: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $result = (object)[
        'status'  => 'OK',
        'codes' => $info->response->codes
      ];

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($result));

      return $response;

    });

    /*
     * clear the trouble codes
     *
     */

    $router->map('GET', '/rest/ecu/dtcclr', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/ecu/dtcclr  ////');

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Could not initialize ECU: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * clear!
       *
       */

      $info = $ecu->dtcclr();

      if($info === false) {

        $data = (object)[
          'status'   => 'ERROR',
          'message'  => 'Can not clear dtc info: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $result = (object)[
        'status'  => 'OK',
        'codes' => $info->response
      ];

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($result));

      return $response;

    });


    /*
     * get the CPU temperature
     *
     */

    $router->map('GET', '/rest/system/temperature', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/system/temperature  ////');

      $cmd = dirname(__FILE__)."/cputemp.sh 2>&1";
      $temp = `$cmd`;

      $result = (object)[
        'status' => 'OK',
        "temp"   => $temp
      ];

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($result));

      return $response;

    });

    /*
     * fetch the current ECU info
     *
     */

    $router->map('GET', '/rest/ecu/sample', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/ecu/sample  ////');

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Could not initialize ECU: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * fetch!
       *
       */

      $info = $ecu->sample();

      if($info === false) {

        $data = (object)[
          'status'   => 'ERROR',
          'message'  => 'Can not determine ECU snapshot: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $result = (object)[
        'status'  => 'OK',
        'samples' => $info->response->samples
      ];

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($result));

      return $response;

    });


    /*
     * fetch the current ECU info
     *
     */

    $router->map('GET', '/rest/ecu/info', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/ecu/info  ////');

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Could not initialize ECU: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * fetch!
       *
       */

      $info = $ecu->info();

      if($info === false) {

        $data = (object)[
          'status'   => 'ERROR',
          'message'  => 'Can not determine ECU info: '.$ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($info->checksum)) {

        $info->checksum = '';
      }
      if(!isset($info->checksum2)) {

        $info->checksum2 = '';
      }
      if(!isset($info->dash_checksum)) {

        $info->dash_checksum = '';
      }
      if(!isset($info->dash_checksum2)) {

        $info->dash_checksum2 = '';
      }

      if(!isset($info->ecu)) {

        $info->ecu = '';
      }

      if(!isset($info->ecu_model)) {

        $info->ecu_model = '';
      }

      if(!isset($info->dash_model)) {

        $info->dash_model = '';
      }

      $info->vin = trim($info->vin);
      if(empty($info->vin)) {
        $info->vin = "ZZZZZZZZZZZZZZZZZ";
      }

      $result = (object)[
        'status'         => 'OK',
        'allinfos'       => $info->allinfos,
        'programdate'    => $info->programdate,
        'shopcode'       => $info->shopcode,
        'version'        => $info->version,
        'dash'           => $info->dash,
        'dashblob'       => $info->dashblob,
        'vin'            => $info->vin,
        'checksum'       => $info->checksum,
        'checksum2'      => $info->checksum2,
        'dash_checksum'  => $info->dash_checksum,
        'dash_checksum2' => $info->dash_checksum2,
        'ecu'            => $info->ecu,
        'ecu_model'      => $info->ecu_model,
        'dash_model'     => $info->dash_model
      ];

      /* before passing back, try to parse the VIN and break into some detail... */

      $vin = new VIN($info->vin, $this->getLogger());

      if($vin->isReady()) {

        $result->vin = $vin->basicInfo();

      } else {

        $result->vin = [
          'vin'          => $info->vin,
          'serial'       => '',
          'year'         => '',
          'manufacturer' => '',
          'wmi'          => '',
          'plant'        => '',
          'vds'          => '',
          'country'      => ''
        ];
      }

      /* if we get this far we have details to pass back */

      $this->debug("passing back: ".print_r($result, true));

      $response->getBody()->write(json_encode($result));

      return $response;

    });

    /*
     * fetch the current WiFi status
     *
     */

    $router->map('GET', '/rest/wifi/status', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/wifi/status  ////');

      $wifi = new WiFi(null, $this->getLogger());

      if(!$wifi->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not initialize WiFi: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * by default we aren't connected until we know for sure that we are.  note that "not connected"
       * is wether or not the WiFi info object has an IP address.
       *
       */

      $con = $wifi->connection();

      if($con === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not determine WIFI status: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     * fetch the current WiFi networks available to us
     *
     */

    $router->map('GET', '/rest/wifi/networks', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/wifi/networks  ////');

      $wifi = new WiFi(null, $this->getLogger());

      if(!$wifi->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not initialize WiFi: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * do a scan...
       *
       */

      $networks = $wifi->scan();

      if($networks === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not determine WIFI networks: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($networks));

      return $response;

    });

    /*
     * disconnect from any currently connected network and return a connection status object (presumably with
     * connection == false)
     *
     */

    $router->map('POST', '/rest/wifi/disconnect', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/wifi/disconnect  ////');

      $wifi = new WiFi(null, $this->getLogger());

      if(!$wifi->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not initialize WiFi: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /*
       * do it.
       *
       */

      $networks = $wifi->disconnect();

      if($networks === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not disconnect properly: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $con = $wifi->connection();

      if($con === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not determine WIFI status: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     * forget from any currently connected network and return a connection status object (presumably with
     * connection == false)
     *
     */

    $router->map('POST', '/rest/wifi/forget', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/wifi/forget  ////');

      $wifi = new WiFi(null, $this->getLogger());

      if(!$wifi->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not initialize WiFi: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the parameters */

      $data = $request->getParsedBody();

      if(!isset($data['ssid']) || empty($data['ssid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing network SSID'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ssid     = $data['ssid'];

      /*
       * disconnect and forget the password
       *
       */

      $result = $wifi->forget($ssid);

      if($result === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not forget properly: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $con = $wifi->connection();

      if($con === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not determine WIFI status: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     * join a network and return a connection status object
     *
     */

    $router->map('POST', '/rest/wifi/join', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/wifi/join  ////');

      $wifi = new WiFi(null, $this->getLogger());

      if(!$wifi->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not initialize WiFi: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the parameters */

      $data = $request->getParsedBody();

      if(!isset($data['ssid']) || empty($data['ssid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing network SSID'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ssid     = $data['ssid'];
      $password = $data['password'];

      $this->debug("ssid: $ssid password: $password");

      /*
       * join...
       *
       */

      $con = $wifi->join($ssid, $password);

      if($con === false) {

        $data = [
          'status'   => 'ERROR',
          'message'  => 'Can not join: '.$wifi->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     * try to auto-login; reuse existing user account data from the current PHP session...
     *
     */

    $router->map('GET', '/rest/pefi/autologin', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/pefi/autologin  ////');

      /* clear out any existing saved user in the session */

      $this->debug("[autologin] looking for existing user session");

      /*
       * persistent auto-login (requested by customer): when we login to the main website we save the user handle and
       * password in the the home directory.  So, we try to recover it here, if there is no session user set.  This
       * allows us to go back an duse the user that last logged in, before the power was last cycled on teh RPI.
       *
       * Yes this is insecure, but was requested by the customer specifically.
       *
       */

      $prevUser = false;

      if(!isset($_SESSION['pefi_user'])) {

        if(is_readable('/home/mgarvin/.pefi-last-login')) {

          $this->debug("[autologin] trying to recover previous login from /home/mgarvin/.pefi-last-login");

          $data   = file_get_contents('/home/mgarvin/.pefi-last-login');
          $config = json_decode($data);

          if($config) {

            $prevUser = $config;
          }
        }
      }

      /*
       * if we doln't have a user session but we do have a previous user from saved credentials on disk...then we can
       * try to use that.
       *
       */

      if (!isset($_SESSION['pefi_user']) && !$prevUser) {

        $this->debug("[autologin] no current user session, and no saved credentials.");

        $data = [
          'status'  => 'ERROR',
          'message' => 'No existing user session.'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(isset($_SESSION['pefi_user'])) {

        $data     = $_SESSION['pefi_user'];

        $this->debug("[autologin] session: " . print_r($data, true) . "\n");

        $userid   = $data->userid;
        $password = $data->password;

      } else if($prevUser) {

        $this->debug("[autologin] re-using saved login credentials: " . print_r($prevUser, true) . "\n");

        $userid   = $prevUser->username;
        $password = $prevUser->password;

      } else {

        $this->debug("[autologin] failed to find a credentials method.");

        $data = [
          'status'  => 'ERROR',
          'message' => 'No credentials method.'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $this->debug("[autologin] userid: $userid password: $password");

      /* setup the API */

      $config = [
        'autoregister' => true,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => "Could not connect ($userid), bad password? | ".$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the user profile */

      $con = $api->getProfile();

      $_SESSION['pefi_user'] = $con;

      $this->debug("[autologin] logged in!");

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     * try to login to the main site, you must provide the userid/password of the account
     *
     */

    $router->map('POST', '/rest/pefi/login', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/pefi/login  ////');

      /* clear out any existing saved user in the session */

      unset($_SESSION['pefi_user']);

      /* try to login */

      $data = $request->getParsedBody();

      if(!isset($data['userid']) || empty($data['userid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing userid'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['password']) || empty($data['password'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing password'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $userid   = $data['userid'];
      $password = $data['password'];

      $this->debug("[login] userid: $userid password: $password");

      /* setup the API */

      $config = [
        'autoregister' => true,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not Connect, bad password? | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the user profile */

      $con = $api->getProfile();

      $_SESSION['pefi_user'] = $con;

      /*
       * persistent auto-login (requested by customer): save the credentials to the home directory,
       * so when we try to auto-login, if its not set in the session already, we can try to use the saved
       * credentials, which might still fail anyways, but most of the time it should avoid them having to re-enter
       * the password after restarting the RPI.
       *
       */

      file_put_contents('/home/mgarvin/.pefi-last-login', json_encode((object)$config));

      $this->debug("[login] logged in!");

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($con));

      return $response;

    });

    /*
     *
     * Helper for flashing the Dashboard, similar to the ECU, but its a CANBus device tha provides virtual gauges
     * and control functions (a user interface)
     *
     */

    $router->map('GET', '/rest/licenses/dash/{userid}/{password}/{fid}', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/licenses/dash/{userid}/{password}/{fid}  ////');

      /* setup for streaming events back to Yaffle... */

      $this->debug("[dash] pre-amble...");

      header("Content-Type: text/event-stream");
      header("Cache-Control: no-cache");
      header("Access-Control-Allow-Origin: *");

      $lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);
      if ($lastEventId == 0) {
        $lastEventId = floatval(isset($_GET["lastEventId"]) ? $_GET["lastEventId"] : 0);
      }
      echo ":" . str_repeat(" ", 2048) . "\n";
      echo "retry: 2000\n";

      /*
       * check parameters, we expect userid/password (user credentials) + the flash id or "fid".
       *
       */

      $this->debug("[dash] checking args...");

      $this->debug('////  /rest/licenses/dash/{userid}/{password}/{fid}  ////');

      if(!isset($args['userid']) || empty($args['userid'])) {

        $lastEventId++;
        $msg = "Missing userid";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      if(!isset($args['password']) || empty($args['password'])) {

        $lastEventId++;
        $msg = "Missing password";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      if(!isset($args['fid']) || empty($args['fid'])) {

        $lastEventId++;
        $msg = "Missing flash id";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $userid      = rawUrlDecode($args['userid']);
      $password    = rawUrlDecode($args['password']);
      $fid         = $args['fid'];

      $this->debug("[dash] userid: $userid password: $password fid: $fid");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $this->debug("[dash] creating main server API...");

      $lastEventId++;
      $this->yaffleInfo("Connecting to flash.precisionefi.com...", $lastEventId);

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $lastEventId++;
        $msg = 'Could not Connect, bad password? | '.$api->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $this->debug("[dash] creating ECU API...");

      $lastEventId++;
      $this->yaffleInfo("Connecting to ECU...", $lastEventId);

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $lastEventId++;
        $msg = 'Could not initialize ECU: '.$ecu->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      /*
       * grab the known ECU/Dash info, in case the server needs any info to gate the
       * download.
       *
       */

      $ecuInfo     = $ecu->info();

      if(!$ecuInfo || !is_object($ecuInfo)) {

        $lastEventId++;
        $msg = 'Could not get basic information from ECU: '.$ecu->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $ecuInfo->vin = trim($ecuInfo->vin);
      if(empty($ecuInfo->vin)) {
        $ecuInfo->vin = "ZZZZZZZZZZZZZZZZZ";
      }

      $vin            = trim($ecuInfo->vin);
      $shopcode       = $ecuInfo->shopcode;
      $programdate    = $ecuInfo->programdate;
      $allinfos       = $ecuInfo->allinfos;
      $dash           = $ecuInfo->dash;
      $dashblob       = $ecuInfo->dashblob;
      $version        = $ecuInfo->version;
      $checksum       = $ecuInfo->checksum;
      $checksum2      = $ecuInfo->checksum2;
      $dash_checksum  = $ecuInfo->dash_checksum;
      $dash_checksum2 = $ecuInfo->dash_checksum2;
      $ecu_model      = $ecuInfo->ecu_model;
      $dash_model     = $ecuInfo->ecu_model;

      /* ok, download the ROM image (form our main site) to flash... */

      $lastEventId++;
      $this->yaffleInfo("Downloading ROM image...", $lastEventId);

      $info = $api->flashDownload($fid, $vin, $version, $dashblob, $checksum, $checksum2, $dash_checksum, $dash_checksum2);

      if(!$info || !is_object($info)) {

        $lastEventId++;
        $msg = 'Could not download flash: '.$api->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $lastEventId++;
      $this->yaffleInfo("ROM image ready for flashing.", $lastEventId);

      $path        = $info->path;
      $model       = $info->model;
      $imgName     = $info->imgname;
      $licVin      = trim($info->vin);
      $imgChecksum = $info->checksum;

      $this->debug("[dash] ROM image $licVin : $model : $path : $imgName : ck=$imgChecksum : models=$model");

      /* we can't flash the dash if its not present */

      if(empty($dash)) {

        $lastEventId++;
        $msg = "No Dash is present.";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */

      }

      $this->debug("[dash] $vin : $shopcode : $programdate : $allinfos : ck=$checksum : models=$dash_model");

      if(($licVin != '') && ($licVin != '*')) {

        if ($vin != $licVin) {

          $lastEventId++;
          $msg = "VIN # of ROM image does not match VIN # of ECU ($vin != $licVin)";

          $this->yaffleError($msg, $lastEventId);

          /* process exit */
        }
      }

      /* the big show, lets do it... */

      $this->debug("[dash] flashing...");

      `DISPLAY=:0 /usr/bin/import -window root /tmp/before-dash-flash.png 2>&1`;

      $result = $ecu->dashflash($path, $model, function($progress) use ($lastEventId) {

        /* for each progress object, just send that back to the GUI as a progress event */

        $lastEventId++;

        $data = json_encode($progress);

        echo "id: $lastEventId\n";
        echo "event: progress\n";
        echo "data: $data\n\n";

        ob_flush();
        flush();

      });

      if($result === false) {

        /* send back the failure message */

        $lastEventId++;

        $this->error("[dash] FAILED: ".$ecu->getError());

        $data = json_encode((object)['status' => 'FAILED', 'message' => $ecu->getError()]);

        echo "id: $lastEventId\n";
        echo "event: failed\n";
        echo "data: $data\n\n";

        ob_flush();
        flush();

        /* process exit */

        $this->debug("[dash] exiting.");
        sleep(1);

        `DISPLAY=:0 /usr/bin/import -window root /tmp/after-dash-flash.png 2>&1`;

        exit(0);
      }

      /* send back the success message */

      $this->error("[dash] COMPLETED!");

      $lastEventId++;

      $data = json_encode((object)['status' => 'COMPLETE', 'message' => "Complete."]);

      echo "id: $lastEventId\n";
      echo "event: completed\n";
      echo "data: $data\n\n";

      ob_flush();
      flush();


      /* process exit */

      $this->debug("[dash] exiting.");
      sleep(1);

      `DISPLAY=:0 /usr/bin/import -window root /tmp/after-dash-flash.png 2>&1`;

      exit(0);
    });

    /*
     *
     * flash an already activated license, you must pass in the flash id of the flash that was generated for the
     * license you are trying to flash.
     *
     * Pre-Condition:  It is assumed that you've already generated the flash download (i.e. flash.tgz on the main
     * server) for the license involved as part of the "/rest/licenses/activate" request.  We do not generate the flash
     * here, we just download it to the ECU (after downloading from the  main site).
     *
     * This request is special; its not just a REST request where we expect to send a typical JSON result back to the
     * web browser.  This request has "progress" that must be streamed back to the browser, so the GUI can show a live
     * progress dialog.  We do this using Server Sent Events (SSE).  See:
     *
     *   https://www.html5rocks.com/en/tutorials/eventsource/basics/
     *
     * We assume the JavaScript "EventSource" consumer in the browser is the polyfill:
     *
     *   https://github.com/Yaffle/EventSource
     *
     * So this server side implementation is based on what Yaffle expects.
     *
     */

    $router->map('GET', '/rest/licenses/flash/{userid}/{password}/{fid}', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/licenses/flash/{userid}/{password}/{fid}  ////');

      /* setup for streaming events back to Yaffle... */

      $this->debug("[flash] pre-amble...");

      header("Content-Type: text/event-stream");
      header("Cache-Control: no-cache");
      header("Access-Control-Allow-Origin: *");

      $lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);
      if ($lastEventId == 0) {
        $lastEventId = floatval(isset($_GET["lastEventId"]) ? $_GET["lastEventId"] : 0);
      }
      echo ":" . str_repeat(" ", 2048) . "\n";
      echo "retry: 2000\n";

      /*
       * check parameters, we expect userid/password (user credentials) + the flash id or "fid".
       *
       */

      $this->debug("[flash] checking args...");

      $this->debug('////  /rest/licenses/flash/{userid}/{password}/{fid}  ////');


      if(!isset($args['userid']) || empty($args['userid'])) {

        $lastEventId++;
        $msg = "Missing userid";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      if(!isset($args['password']) || empty($args['password'])) {

        $lastEventId++;
        $msg = "Missing password";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      if(!isset($args['fid']) || empty($args['fid'])) {

        $lastEventId++;
        $msg = "Missing flash id";

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $userid      = rawUrlDecode($args['userid']);
      $password    = rawUrlDecode($args['password']);
      $fid         = $args['fid'];

      $this->debug("[flash] userid: $userid password: $password fid: $fid");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $this->debug("[flash] creating main server API...");

      $lastEventId++;
      $this->yaffleInfo("Connecting to flash.precisionefi.com...", $lastEventId);

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $lastEventId++;
        $msg = 'Could not Connect, bad password? | '.$api->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $this->debug("[flash] creating ECU API...");

      $lastEventId++;
      $this->yaffleInfo("Connecting to ECU...", $lastEventId);

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $lastEventId++;
        $msg = 'Could not initialize ECU: '.$ecu->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      /* grab the ECU *and* dash info in case the main server needs it as part of requesting the download */

      $ecuInfo     = $ecu->info();

      if(!$ecuInfo || !is_object($ecuInfo)) {

        $lastEventId++;
        $msg = 'Could not get basic information from ECU: '.$ecu->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $ecuInfo->vin = trim($ecuInfo->vin);
      if(empty($ecuInfo->vin)) {
        $ecuInfo->vin = "ZZZZZZZZZZZZZZZZZ";
      }

      $vin         = trim($ecuInfo->vin);
      $shopcode    = $ecuInfo->shopcode;
      $programdate = $ecuInfo->programdate;
      $allinfos    = $ecuInfo->allinfos;
      $dash        = $ecuInfo->dash;
      $dashblob    = $ecuInfo->dashblob;
      $version     = $ecuInfo->version;
      $checksum    = $ecuInfo->checksum;
      $ecu_model   = $ecuInfo->ecu_model;
      $dash_model  = $ecuInfo->ecu_model;

      /* ok, download the ROM image (form our main site) to flash... */

      $lastEventId++;
      $this->yaffleInfo("Downloading ROM image...", $lastEventId);

      $info = $api->flashDownload($fid, $vin, $version, $dashblob);

      if(!$info || !is_object($info)) {

        $lastEventId++;
        $msg = 'Could not download flash: '.$api->getError();

        $this->yaffleError($msg, $lastEventId);

        /* process exit */
      }

      $lastEventId++;
      $this->yaffleInfo("ROM image ready for flashing.", $lastEventId);

      $path        = $info->path;
      $ecuModel    = $info->model;
      $kind        = $info->kind;
      $imgName     = $info->imgname;
      $licVin      = trim($info->vin);
      $imgChecksum = $info->checksum;

      $this->debug("[flash] ROM image $licVin : $ecuModel : $path : $imgName : ck=$imgChecksum : models=$ecuModel");

      /* make sure the VIN of the flash and the VIN of the ECU actually match! */

      $this->debug("[flash] ECU $vin : $shopcode : $programdate : $allinfos");

      if(($licVin != '') && ($licVin != '*')) {

        if ($vin != $licVin) {

          $lastEventId++;
          $msg = "VIN # of ROM image does not match VIN # of ECU ($vin != $licVin)";

          $this->yaffleError($msg, $lastEventId);

          /* process exit */
        }
      }

      /* the big show, lets do it... */

      $this->debug("[flash] flashing...");

      $this->debug("[flash] $vin : $shopcode : $programdate : $allinfos : ck=$checksum : models=$ecu_model");

      if($kind == 'dash') {

        $this->debug("[flash] flashing do dash...");

        `DISPLAY=:0 /usr/bin/import -window root /tmp/before-dash-flash.png 2>&1`;

        $result = $ecu->dashflash($path, $ecuModel, function ($progress) use ($lastEventId) {

          /* for each progress object, just send that back to the GUI as a progress event */

          $lastEventId++;

          $data = json_encode($progress);

          echo "id: $lastEventId\n";
          echo "event: progress\n";
          echo "data: $data\n\n";

          ob_flush();
          flush();

        });

      } else {

        $this->debug("[flash] flashing do ecu...");

        `DISPLAY=:0 /usr/bin/import -window root /tmp/before-flash.png 2>&1`;

        $result = $ecu->flash($path, $ecuModel, function ($progress) use ($lastEventId) {

          /* for each progress object, just send that back to the GUI as a progress event */

          $lastEventId++;

          $data = json_encode($progress);

          echo "id: $lastEventId\n";
          echo "event: progress\n";
          echo "data: $data\n\n";

          ob_flush();
          flush();

        });
      }

      if($result === false) {

        /* send back the failure message */

        $lastEventId++;

        $this->error("[flash] FAILED: ".$ecu->getError());

        $data = json_encode((object)['status' => 'FAILED', 'message' => $ecu->getError()]);

        echo "id: $lastEventId\n";
        echo "event: failed\n";
        echo "data: $data\n\n";

        ob_flush();
        flush();

        /* process exit */

        $this->debug("[flash] exiting.");
        sleep(1);

        `DISPLAY=:0 /usr/bin/import -window root /tmp/after-flash.png 2>&1`;

        exit(0);
      }

      /* send back the success message */

      $this->error("[flash] COMPLETED!");

      $lastEventId++;

      $data = json_encode((object)['status' => 'COMPLETE', 'message' => "Complete."]);

      echo "id: $lastEventId\n";
      echo "event: completed\n";
      echo "data: $data\n\n";

      ob_flush();
      flush();

      /* process exit */

      $this->debug("[flash] exiting.");
      sleep(1);

      `DISPLAY=:0 /usr/bin/import -window root /tmp/after-flash.png 2>&1`;

      exit(0);
    });

    /*
     * activate a license (if available) for the given product and VIN
     *
     */

    $router->map('POST', '/rest/licenses/activate', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/licenses/activate  ////');

      $data = $request->getParsedBody();

      if(!isset($data['userid']) || empty($data['userid'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing userid'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['password']) || empty($data['password'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing password'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['pid']) || empty($data['pid'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing product id'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['vin']) || empty($data['vin'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing VIN #'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['programdate'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing programming date'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['shopcode'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing shop code'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['dashblob'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing dash blob'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['version'])) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Missing ECU version'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $userid      = $data['userid'];
      $password    = $data['password'];
      $productId   = $data['pid'];
      $vin         = $data['vin'];
      $programDate = $data['programdate'];
      $shopCode    = $data['shopcode'];
      $dashblob    = $data['dashblob'];
      $version     = $data['version'];

      /* but, if we have the critical ECU info from the ECU itself... use that. */

      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'can not connect to ECU: ' . $ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ecuInfo     = $ecu->info();

      if(!$ecuInfo || !is_object($ecuInfo)) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'can not connect to ECU'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ecuInfo->vin = trim($ecuInfo->vin);
      if(empty($ecuInfo->vin)) {
        $ecuInfo->vin = "ZZZZZZZZZZZZZZZZZ";
      }

      $vin            = trim($ecuInfo->vin);
      $shopCode       = $ecuInfo->shopcode;
      $programDate    = $ecuInfo->programdate;
      $allInfos       = $ecuInfo->allinfos;
      $dash           = $ecuInfo->dash;
      $dashblob       = $ecuInfo->dashblob;
      $version        = $ecuInfo->version;
      $checksum       = $ecuInfo->checksum;
      $checksum2      = $ecuInfo->checksum2;
      $dash_checksum  = $ecuInfo->dash_checksum;
      $dash_checksum2 = $ecuInfo->dash_checksum2;
      $ecu_model      = $ecuInfo->ecu_model;
      $dash_model     = $ecuInfo->ecu_model;

      if(empty($programDate) || ($programDate=='null')) {
        $programDate = '';
      }

      if(empty($allInfos) || ($allInfos=='null')) {
        $allInfos = '';
      }

      if(empty($shopCode) || ($shopCode=='null')) {
        $shopCode = '';
      }

      if(empty($dash) || ($dash=='null')) {
        $dash = '';
      }

      if(empty($dashblob) || ($dashblob=='null')) {
        $dashblob = '';
      }

      if(empty($version) || ($version=='null')) {
        $version = '';
      }

      if(empty($checksum) || ($checksum=='null')) {
        $checksum = '';
      }
      if(empty($checksum2) || ($checksum2=='null')) {
        $checksum2 = '';
      }
      if(empty($dash_checksum) || ($dash_checksum=='null')) {
        $dash_checksum = '';
      }
      if(empty($dash_checksum2) || ($dash_checksum2=='null')) {
        $dash_checksum2 = '';
      }

      $this->debug("userid: $userid password: $password");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'Could not Connect, bad password? | '.$api->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* activate a license */

      $data = $api->activateLicense($productId, $vin, $programDate, $shopCode, $dashblob, $version, $checksum, $checksum2, $dash_checksum, $dash_checksum2);

      if($data === false) {

        $msg      = 'Can not activate: '.$api->getError();

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => $msg
        ];

        $this->error($msg);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($data));

      return $response;

    });

    /*
     * update the status of a flash
     *
     */

    $router->map('POST', '/rest/flashes/update', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/flashes/update  ////');

      $data = $request->getParsedBody();

      if(!isset($data['userid']) || empty($data['userid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing userid'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['password']) || empty($data['password'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing password'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['fid']) || empty($data['fid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing flash id'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['verb']) || empty($data['verb'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing update verb'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['message']) || empty($data['message'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing update verb'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $userid   = $data['userid'];
      $password = $data['password'];
      $fid      = $data['fid'];
      $verb     = $data['verb'];
      $message  = $data['message'];
      $vin      = $date['vin'];

      $this->debug("userid: $userid password: $password flashid: $fid verb: $verb message: $message vin: $vin");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not Connect, bad password? | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the licenses */

      $status = $api->flashUpdate($fid, $verb, $message, $vin);

      if(!$status) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Problem updating flash status | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode((object)[
        'status'  => 'OK',
        'message' => 'OK'
      ]));

      return $response;

    });

    /*
     * fetch a summary of the flashes we can burn
     *
     */

    $router->map('POST', '/rest/flashes/summary', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/flashes/summary  ////');

      $data = $request->getParsedBody();

      if(!isset($data['userid']) || empty($data['userid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing userid'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['password']) || empty($data['password'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing password'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['vin']) || empty($data['vin'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing VIN'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $userid   = $data['userid'];
      $password = $data['password'];
      $vin      = $data['vin'];

      $this->debug("userid: $userid password: $password vin: $vin");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not Connect, bad password? | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the licenses */


      $ecu = new ECU(null, $this->getLogger());

      if(!$ecu->isReady()) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'can not connect to ECU: ' . $ecu->getError()
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ecuInfo     = $ecu->info();

      if(!$ecuInfo || !is_object($ecuInfo)) {

        $data     = (object)[
          'status'   => 'ERROR',
          'message'  => 'can not connect to ECU'
        ];

        $this->error($data->message);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $ecuInfo->vin = trim($ecuInfo->vin);
      if(empty($ecuInfo->vin)) {
        $ecuInfo->vin = "ZZZZZZZZZZZZZZZZZ";
      }

      $vin            = trim($ecuInfo->vin);
      $shopCode       = $ecuInfo->shopcode;
      $programDate    = $ecuInfo->programdate;
      $allInfos       = $ecuInfo->allinfos;
      $dash           = $ecuInfo->dash;
      $dashblob       = $ecuInfo->dashblob;
      $version        = $ecuInfo->version;
      $checksum       = $ecuInfo->checksum;
      $checksum2      = $ecuInfo->checksum2;
      $dash_checksum  = $ecuInfo->dash_checksum;
      $dash_checksum2 = $ecuInfo->dash_checksum2;
      $ecu_model      = $ecuInfo->ecu_model;
      $dash_model     = $ecuInfo->ecu_model;

      $data = $api->flashes($vin, $checksum, $checksum2, $dash_checksum, $dash_checksum2);

      if(!is_array($data)) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Problem fetching flash list | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($data));

      return $response;

    });

    /*
     * fetch a summary of the licenses we can convert to flashes.
     *
     */

    $router->map('POST', '/rest/licenses/summary', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/licenses/summary  ////');

      $data = $request->getParsedBody();

      if(!isset($data['userid']) || empty($data['userid'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing userid'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      if(!isset($data['password']) || empty($data['password'])) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Missing password'
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      $userid   = $data['userid'];
      $password = $data['password'];

      $this->debug("userid: $userid password: $password");

      /* setup the API */

      $config = [
        'autoregister' => false,
        'username'     => $userid,
        'password'     => $password
      ];

      $api    = new PefiAPI($config, $this->getLogger());

      if(!$api->isReady()) {

        $data     = [
          'status'   => 'ERROR',
          'message'  => 'Could not Connect, bad password? | '.$api->getError()
        ];

        $this->error($data['message']);

        $response->getBody()->write(json_encode($data));

        return $response;
      }

      /* fetch the licenses */

      $data = $api->assignedSummary();

      /* if we get this far we have details to pass back */

      $response->getBody()->write(json_encode($data));

      return $response;

    });

    /*
     * Simple ECHO  endpoint to test if API is live.
     *
     */
    
    $router->map('GET', '/rest/echo/{msg}', function(ServerRequestInterface $request, ResponseInterface $response, array $args) {

      $this->debug('////  /rest/echo/{msg}  ////');

      /* content type as JSON */
      
      $response   = $response->withHeader('Content-Type', 'application/json');
      
      $msg        = $args['msg'];
      $ipAddress  = $this->clientIP();

      $this->debug("msg: $msg ip: $ipAddress");

      /* send it back */
      
      $json       = json_encode((object)['message' => $msg]);
      
      $response->getBody()->write($json);
      
      return $response;
      
    });

    /* route it! */
      
    $actual = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
      
    $this->debug(get_class()."::run routing  ($actual)...");
      
    try {
        
      $response = $router->dispatch($container->get('request'), $container->get('response'));
        
    } catch(NotFoundException $e) {
        
      /* we need to send back a not foudn response */
        
      $data     = [
        'status' => 'FAIL',
        'value'  => 'no such endpoint'
      ];
        
      $response = new JsonResponse($data, 404, [
        'Content-Type' => [ 'application/json' ],
      ]);
        
      $this->setError(get_class()."::run Not Found: $actual");
      
    } catch(MethodNotAllowedException $e) {
        
      /* we need to send back a not found response */
        
      $data     = [
        'status' => 'FAIL',
        'value'  => 'not allowed (wrong method)'
      ];
        
      $response = new JsonResponse($data, 404, [
        'Content-Type' => [ 'application/json' ],
      ]);
      
      $this->setError(get_class()."::run Not Allowed: $actual");
    }
      
    /* send results back to user's browser */
      
    /*$this->debug(get_class()."::run sending response back to user...");*/
      
    $container->get('emitter')->emit($response);
      
    /* all done */
      
    $r2 = microtime(true);
      
    /*$this->debug(get_class()."() time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");*/
      
    return true;
  }
  
}

?>
