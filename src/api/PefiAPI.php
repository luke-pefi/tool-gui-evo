<?php

/**
 *
 * PefiAPI - this is our adaptor for talking to the main website (dialing home!)
 *
 */

namespace api;

use Monolog\Logger;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;

use util\LoggingTrait;
use util\StatusTrait;

use util\RaspberryInfo;

class PefiAPI {

  /* bring in logging and status behaviors */

  use LoggingTrait;
  use StatusTrait;

  /**
   *
   * @var string $token the login access token (required for all REST calls)
   *
   */

  private $token      = false;

  /** @var Client $guzzle the http adaptor we use */

  private $guzzle     = null;

  /** @var object $profile the user's profile */

  private $profile    = null;

  /**
   * 
   * @var array $factoryDefault the default settings
   *
   */

  protected $factoryDefault = [

    /* guzzle options */

    'guzzle' => [
      'base_uri'        => 'https://flash.precisionefi.com',
      'allow_redirects' => false,
      'connect_timeout' => 10.0,
      'read_timeout'    => 10.0,
      'timeout'         => 10.0,
      'debug'           => false
    ],

    /* by default we don't auto-register the RPI on login */

    'autoregister'  => false,

    /* REQUIRED: our remote application id */

    'client_id'     => 'rpiclient',
    'client_secret' => 'aigh5phooX0Oawoh',
   
    /* REQUIRED: you have to provide login credentials for the main site */
 
    'username'      => false,
    'password'      => false
  ];

  /**
   *
   * standard constructor
   *
   * @param Monolog $logger the logger to use (optional)
   *
   */

  public function __construct($config=null, $logger=null)
  {
    $r1    = microtime(true);

    $this->debug(get_class()."() setting up...");

    /* connect to the system log */

    $this->setLogger($logger);

    $this->unReady();

    /* override any options as needed */

    if($config !== null) {

      if(is_array($config)) {
        $this->factoryDefault = array_replace($this->factoryDefault, $config);
      } else {
        $this->setError(get_class()."() - expecting an array for configuration.");
        return ;
      }
    }

    /* 
     * minimum parameters: you have to provide a userid and password to login
     * at the main site.
     *
     */

    if(empty($this->factoryDefault['username'])) {

      $this->setError(get_class()."() - no user name provided."); 
      return ;
    } 

    if(empty($this->factoryDefault['password'])) {

      $this->setError(get_class()."() - no password provided.");
      return ;
    }

    /*
     * try to make a GuzzleHttp client; add middleware to handle automatic retry
     * on all requests.
     *
     */

    $this->debug(get_class()."() initializing GuzzleHTTP...");

    $handlerStack = HandlerStack::create(new CurlHandler() );
    $handlerStack->push(Middleware::retry($this->retryDecider()));

    $this->factoryDefault['guzzle']['handler'] = $handlerStack;

    $this->guzzle = new Client($this->factoryDefault['guzzle']);

    /* try to login, registering our RPI at the same time if required. */

    $this->debug(get_class()."() attempting login...");
    
    if(!$this->login($this->factoryDefault['autoregister'])) {

      $this->setError(get_class()."() - can not login to main website: ".$this->getError());
      return ;
    }

    /* if we get this far, we are good to go! */

    $this->debug(get_class()."() API is ready.");

    $this->makeReady();

    /* all done */

    $r2    = microtime(true);
    $this->debug(get_class()."() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

  }

  public function captureWPAStatus() {

    try {

      $log = $this->getLogger();

      $log->debug("[PefiAPI][WPA] capturing status...");

      $output = `/usr/bin/sudo /sbin/wpa_cli scan_results`;
      $lines = explode(PHP_EOL, $output);

      foreach ($lines as $idx => $line) {
        $log->debug(". $line");
      }

      $output = `/usr/bin/sudo /sbin/wpa_cli status verbose`;
      $lines = explode(PHP_EOL, $output);

      foreach ($lines as $idx => $line) {
        $log->debug(". $line");
      }

      $log->debug("[PefiAPI][WPA] logged.");

    } catch (Exception $e) {

      /* can't log? */

    }

    return true;
  }

  public function retryDecider() {

    $log      = $this->getLogger();
    $instance = $this;

    return function (

      $retries,
      Request          $request,
      Response         $response  = null,
      RequestException $exception = null

    ) use ($log, $instance) {

      $uri    = $request->getUri()->__toString();
      $method = $request->getMethod();

      $log->debug("[PefiAPI][Retry] just tried ($retries): [$method] $uri");

      if($response) {

        $code = $response->getStatusCode();
        $log->debug("[PefiAPI][Retry] . status code was: $code");
      }

      /* have we run out of retry attempts? */

      if ($retries >= 5) {

        $instance->setError("[PefiAPI][Retry] no more retries!");
        $this->captureWPAStatus();
        return false;
      }

      /* we can retry on connection errors */

      if($exception instanceof ConnectException) {

        $msg = "Connection problem. ";
        if($exception->hasResponse()) {
          $msg .= Psr7\str($exception->getResponse());
        }

        $instance->setError("[PefiAPI][Retry] $msg");

        return true;
      }

      /* we can retry on request errors */

      if($exception instanceof RequestException) {

        $msg = "Request problem. ";
        if($exception->hasResponse()) {
          $msg .= Psr7\str($exception->getResponse());
        }

        $instance->setError("[PefiAPI][Retry] $msg");

        return true;
      }

      /* we can retry on internal server errors */

      if($exception instanceof ServerException) {

        $msg = "Server problem. ";
        if($exception->hasResponse()) {
          $msg .= Psr7\str($exception->getResponse());
        }

        $instance->setError("[PefiAPI][Retry] $msg");

        return true;
      }

      /* we have to abort on transfer problems */

      if($exception instanceof TransferException) {

        $msg = "Transfer problem (will abort). ";
        if($exception->hasResponse()) {
          $msg .= Psr7\str($exception->getResponse());
        }

        $instance->setError("[PefiAPI][Retry] $msg");
        $this->captureWPAStatus();
        return false;
      }

      /* if we got a response we don't need to retry! */

      $log->debug("[PefiAPI][Retry] response was received!");

      if($response->getStatusCode() >= 400) {

        /* we got a response but its likely an API error. */

        $data  = json_decode($response->getBody());

        if(isset($data->message)) {

          $this->setError($data->message);
        }

      }


      return false;
    };

  }

  /**
   *
   * request() make a request back to the main website, we basically use Guzzle to make the request
   * for us, but we have to do extra stuff here to handle cases where the internet connection isn't
   * so reliable; we also have to worry about cURL not always working stuff like this:
   *
   * @link https://github.com/curl/curl/issues/619
   *
   * @param $method  - the method to use GET/POST etc.
   * @param $uri     - the REST endpoint
   * @param $options - any options for the request (like Authentication etc.)
   *
   * @return mixed exactly false on error, otherwise the response from the main website.
   *
   */

  public function request($method, $uri, $options) {

    $this->debug(get_class()."::request() attempting: [$method] $uri ...".print_r($options,true)." ...");

    /*
     * attempt the request, if we need to do retries, it is handled by the middleware we
     * installed in the constructor.
     *
     */

    $response = false;

    try {

      $response = $this->guzzle->request($method, $uri, $options);

    } catch (\Exception $e) {

      $this->setError(get_class()."::request() could not complete request (Exception) ($uri): ".$this->getError());
      return false;
    }

    if($response === false) {

      $this->setError(get_class()."::request() could not complete request (no response) ($uri): ".$this->getError());
      return false;
    }

    /* all of our main server responses (the valid ones) are in JSON */

    $data  = json_decode($response->getBody());

    if($response->getStatusCode() >= 400) {

      /* even if its a download, any status above 400 is an error an dwe should have error details. */

      if(isset($data->status) && ($data->status == "ERROR")) {

        $this->setError(get_class() . "::request(): {$data->message}");

        return $data;
      }
    }

    if(!is_object($data) && !is_array($data)) {

      /* if its not a download of a binary image, it must be a JSON response */

      if(!preg_match('/\/download/', $uri)) {

        $this->setError(get_class() . "::request() can't decode data: '{$response->getBody()}'");
        return false;
      }
    }

    /* pass back the object from the main server. */

    $this->debug(get_class()."::request() passing back: ".print_r($data,true).".");

    return $data;
  }

  /**
   *
   * login() - login into the main website and establish a session we can use
   * to send requests in.
   *
   * @param boolean $register - (optional) make true to also register this 
   * device upon successful login.
   *
   * @return boolean exactly false on error.
   *
   */
 
  public function login($register=false) {

    $r1    = microtime(true);

    $this->debug(get_class()."::login() starts ...");

    /* 
     * to login, we dial home for an access token, the access token can then 
     * be added to the header of any requests we send.
     *
     */

    $this->debug(get_class()."::login() dialing ...");

    $data = $this->request('POST', "/oauth/token", [

      'form_params' => [


        'grant_type'    => 'password',
        'client_id'     => $this->factoryDefault['client_id'],
        'client_secret' => $this->factoryDefault['client_secret'],
        'username'      => $this->factoryDefault['username'],
        'password'      => $this->factoryDefault['password']
      ]

    ]);

    if(!is_object($data)) {

      $this->setError(get_class()."::login() can't get data: ".$this->getError());
      return false;
    }

    /* we should have gotten an access token! */

    $this->token = $data->access_token;

    if(empty($this->token)) {

      $this->setError(get_class()."::login() no access token?");
      return false;
    }

    $this->debug(get_class()."::login() I'm in!");    

    if($register) {

      $this->debug(get_class()."::login() registering device...");

      if(!$this->register()) {

        $this->setError(get_class()."::login() can't register: ".$this->getError()); 

        return false;
      } 

    }

    $this->debug(get_class()."::login() complete.");

    /* fetch the profile */

    $this->debug(get_class()."::login() dialing for profile ...");

    $data   = $this->request('GET', "/rest/myaccount", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ]

    ]);

    if(!is_object($data)) {

      $this->setError(get_class()."::login() can't get data: ".$this->getError());
      return false;
    }

    if(isset($data->status) && ($data->status == "ERROR")) {

      $this->setError(get_class()."::login() problem: ".$data->message);
      return false;
    }

    $this->profile            = $data;
    $this->profile->connected = true;
    $this->profile->password  = $this->factoryDefault['password'];

    /* all done! */

    $r2    = microtime(true);
    $this->debug(get_class()."::login() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return true;
  }

  /**
   *
   * register() - self-register this RPI with the main website for teh currently 
   * logged in user.
   *
   * @return boolean exactly false on error.
   *
   */

  private function  register() {

    $r1         = microtime(true);

    $info       = RaspberryInfo::info();

    $this->debug(get_class()."::register will register: ".print_r($info,true));

    $info->kind = "rpi";

    $data       = json_encode($info);

    $this->debug(get_class()."::register() dialing ...");

    $data   = $this->request('POST', "/rest/myaccount/device/register", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ],

      /* the content */

      'body'    => $data

    ]);

    if(!is_object($data)) {

      $this->setError(get_class()."::register() can't get data: ".$this->getError());
      return false;
    }

    if(isset($data->status) && ($data->status == "ERROR")) {

      $this->setError(get_class()."::register() problem: ".$data->message);

      return false;
    } 

    /* if we get this far, were good. */

    $r2    = microtime(true);
    $this->debug(get_class()."::register ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return true;
  }

  /**
   *
   * getProfile() - fetch the simplified PHP object for our "connection" with the main site.
   *
   * @return object
   *
   */

  public function getProfile() {
    return $this->profile;
  }

  /**
   * 
   * assignedSummary() fetch a report of what implied licenses I have, that is
   * X of VALID licenses for product Y, XX of ACTIVATED licenses for product YY
   * etc.  This can easily be used to select what kind of license is to be 
   * activated with a VIN etc.
   *
   * @return mixed exactly false on error, otherwise a simplified PHP object 
   * with the report (an array of rows).
   *
   */

  public function assignedSummary() {

    $r1         = microtime(true);

    $this->debug(get_class()."::assignedSummary() starts...");

    $data   = $this->request('GET', "/rest/myaccount/licenses/assigned", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ]

    ]);

    if(!is_array($data) && !is_object($data)) {
 
      $this->setError(get_class()."::assignedSummary() garbled response: ".print_r($data,true)); 

      return false;
    }

    /* if we get this far, were good. */

    $r2    = microtime(true);
    $this->debug(get_class()."::register ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return $data;
  }

  /**
   *
   * flashDownload() - download the given flash and save it to a secure area.  Return an info object that has any
   * of the details we need to work with the flash.
   *
   * @param integer $flashId  the actual database id of the flash
   * @param string  $vin      the ECU VIN #
   * @param string  $version  the ECU flash image version
   * @param string  $dashblob if the Dash is present then its the dash identifier blob
   * @param string  $checksum if available, the program checksum from the ECU
   *
   * @return mixed exactly false on error, otherwise a simple PHP object with the info we need for working with the
   * flash image. (i.e. downloading to the ECU.
   *
   */

  public function flashDownload($flashId, $vin='', $version='', $dashblob='', $checksum='', $checksum2='', $dash_checksum='', $dash_checksum2='') {

    $r1         = microtime(true);
    $infoObj    = (object)[];

    $this->debug(get_class()."::flashDownload() $flashId");

    /* make sure we have as secure download location... */

    $workArea = "/var/ecu";

    if(!is_dir($workArea)) {

      $this->setError(get_class()."::flashDownload() no work area! ($workArea)");
      return false;
    }

    foreach(glob("{$workArea}/*") as $file) {
      @unlink($file);
    }

    /*
     * download the flash pack to the work area, when we request a download, we pass along the current VIN #,
     * ECU version and Dash identifier blob (if we have them), so that the main server can make a final
     * choice about this download being allowed or not.
     *
     */

    $info        = (object)[
      'fid'            => $flashId,
      'vin'            => $vin,
      'version'        => $version,
      'dashblob'       => $dashblob,
      'checksum'       => $checksum,
      'checksum2'      => $checksum2,
      'dash_checksum'  => $dash_checksum,
      'dash_checksum2' => $dash_checksum2
    ];

    $data       = json_encode($info);

    $this->debug(get_class()."::flashDownload() dialing ...");

    $datapack   = "$workArea/flash.tgz";
    $data       = $this->request('POST', "/rest/myaccount/flashes/download", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ],

      /* the content */

      'body'    => $data,
      'save_to' => $datapack

    ]);

    if($data === false) {

      $this->setError(get_class()."::flashDownload() ".$this->getError());

      return false;
    }

    if(isset($data->status) && ($data->status == "ERROR")) {

      $this->setError(get_class()."::flashDownload() download problem: ".$data->message);

      return false;
    }

    /* did we get the download? */

    if(!is_readable($datapack)) {

      $this->setError(get_class()."::flashDownload() no file received.");
      return false;
    }

    /* unpack */

    $cmd    = "/bin/tar -C $workArea -zxvf $datapack 2>&1";
    $output = `$cmd`;

    $flashFile = "$workArea/flash.bin";
    $flashMeta = "$workArea/flash.json";

    if(!is_readable($flashFile)) {

      $this->setError(get_class()."::flashDownload() did not unpack flash image.");
      return false;
    }

    if(!is_readable($flashMeta)) {

      $this->setError(get_class()."::flashDownload() did not unpack flash meta data.");
      return false;
    }

    /* make our meta-info object that we can pass back... */

    $infoObj   = json_decode(trim(file_get_contents($flashMeta)));

    if(!is_object($infoObj)) {

      $this->setError(get_class()."::flashDownload() garbled meta-data.");
      return false;
    }

    $infoObj->path = $flashFile;

    /* check the MD5 signature... */

    $md5 = md5_file($flashFile);

    if($md5 != $infoObj->md5) {

      $this->setError(get_class()."::flashDownload() bad download, signature mismatch $md5 != {$infoObj->md5}.");
      return false;

    } else {

      $this->debug(get_class()."::flashDownload() correct signature, download is good ($md5).");
    }

    if(!isset($infoObj->version)) {
      $infoObj->version = '';
    }
    if(!isset($infoObj->checksum)) {
      $infoObj->checksum = '';
    }
    if(!isset($infoObj->checksum2)) {
      $infoObj->checksum2 = '';
    }
    if(!isset($infoObj->dash_checksum)) {
      $infoObj->dash_checksum = '';
    }
    if(!isset($infoObj->dash_checksum2)) {
      $infoObj->dash_checksum2 = '';
    }

    if(is_object($infoObj->base_updated)) {
      $infoObj->base_updated = $infoObj->base_updated->date;
    }

    /* if we get this far, were good. */

    $this->debug(get_class()."::flashDownload()        imgname: {$infoObj->imgname}");
    $this->debug(get_class()."::flashDownload()         origin: {$infoObj->origin}");
    $this->debug(get_class()."::flashDownload()      base date: {$infoObj->base_updated}");
    $this->debug(get_class()."::flashDownload()        version: {$infoObj->version}");
    $this->debug(get_class()."::flashDownload()       checksum: {$infoObj->checksum}");
    $this->debug(get_class()."::flashDownload()      checksum2: {$infoObj->checksum2}");
    $this->debug(get_class()."::flashDownload()  dash_checksum: {$infoObj->dash_checksum}");
    $this->debug(get_class()."::flashDownload() dsah_checksum2: {$infoObj->dash_checksum2}");

    $r2    = microtime(true);
    $this->debug(get_class()."::flashDownload() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return $infoObj;
  }

  /**
   *
   * flashUpdate() - for this user, and one of their flashes, track an action that was taken on it.
   *
   * @param integer $flashId - the actual database id of the flash
   * @param string  $verb    - the verb (kind of action taken)
   * @param string  $message - detailes on the action
   *
   * @return boolean exactly false on error.
   *
   */

  public function flashUpdate($flashId, $verb, $message, $vin='') {

    $r1         = microtime(true);

    $this->debug(get_class()."::flashUpdate() $flashId, $verb, $message");

    $info       = (object)[
      'fid'     => $flashId,
      'verb'    => $verb,
      'message' => $message,
      'vin'     => $vin
    ];

    $data       = json_encode($info);

    $this->debug(get_class()."::flashUpdate() dialing ...");

    $data       = $this->request('POST', "/rest/myaccount/flashes/update", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ],

      /* the content */

      'body'    => $data

    ]);

    if(is_object($data)) {

      if (isset($data->status) && ($data->status == "ERROR")) {

        $this->setError(get_class() . "::flashUpdate() problem: " . $data->message);
        return false;
      }

    }

    if(!isset($data->status) && ($data->status != "OK")) {

      $this->error(et_class() . "::flashUpdate() could not track flash update.");
      $this->setError(get_class() . "::flashUpdate() could not track flash update.");

      return false;
    }

    /* if we get this far, were good. */

    $r2    = microtime(true);
    $this->debug(get_class()."::flashUpdate() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return true;
  }

  /**
   *
   * flashes() - for this user, and their vehicle we want the possible flashes we can download.
   *
   * @param string  $vin         - the VIN #
   * @param string  $checksum    - some ECU's give us a program checksum we can use for validity checks
   * @param string  $model       - some ECU's know their supported models
   *
   * @return mixed  exactly false on error, otherwise the new Flash object.
   *
   */

  public function flashes($vin, $checksum='', $checksum2='', $dash_checksum='', $dash_checksum2='', $model='') {

    $r1          = microtime(true);

    $this->debug(get_class()."::flashes() $vin");

    $info        = (object)[
      'vin'            => $vin,
      'model'          => $model,
      'checksum'       => $checksum,
      'checksum2'      => $checksum2,
      'dash_checksum'  => $dash_checksum,
      'dash_checksum2' => $dash_checksum2
    ];

    $data        = json_encode($info);

    $this->debug(get_class()."::flashes() dialing ...");

    $data        = $this->request('POST', "/rest/myaccount/flashes", [

      /* be sure to authenticate */

      'headers'  =>  [
        'Authorization' => "Bearer {$this->token}"
      ],

      /* the content */

      'body'     => $data

    ]);

    if(is_object($data)) {

      if (isset($data->status) && ($data->status == "ERROR")) {

        $this->setError(get_class() . "::flashes() problem: " . $data->message);

      }

      return false;
    }

    if(!is_array($data)) {

      $this->error(et_class() . "::flashes() garbled format: ".print_r($data,true));
      $this->setError(get_class() . "::flashes() expecting array of data.");

      return false;
    }

    /* if we get this far, were good. */

    $r2    = microtime(true);
    $this->debug(get_class()."::flashes() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return $data;
  }

  /**
   *
   * activateLicense() - for this user, we want to activate a license for a VIN, this will make the
   * main site prepare a flash for download.  To actually get the flash, call the downloadFlash()
   * method.
   *
   * @param integer $productId   - the product id (database id)
   * @param string  $vin         - the VIN #
   * @param string  $programDate - the last programming date of the ECU
   * @param string  $shopCode    - the last shop code of the ECU
   * @param string  $dashblob    - the identifying header of the Dash if there is one
   * @param string  $version     - the (known) ECU flash version
   * @param string  $checksum    - some ECU's have a program checksum we can use for validity checks
   *
   * @return mixed  exactly false on error, otherwise the new Flash object.
   *
   */

  public function activateLicense($productId, $vin, $programDate='', $shopCode='', $dashblob='', $version='', $checksum='', $checksum2='', $dash_checksum='', $dash_checksum2='') {

    $r1         = microtime(true);

    $this->debug(get_class()."::activateLicense() $productId | $vin | $programDate | $shopCode | $dashblob | $version | $checksum | $checksum2 | $dash_checksum | $dash_checksum2");

    $info       = (object)[
      'pid'            => $productId,
      'vin'            => $vin,
      'programdate'    => $programDate,
      'shopcode'       => $shopCode,
      'dashblob'       => $dashblob,
      'version'        => $version,
      'checksum'       => $checksum,
      'checksum2'      => $checksum2,
      'dash_checksum'  => $dash_checksum,
      'dash_checksum2' => $dash_checksum2,
    ];

    $data       = json_encode($info);

    $this->debug(get_class()."::activateLicense() dialing ...");

    $data       = $this->request('POST', "/rest/myaccount/license/activate", [

      /* be sure to authenticate */

      'headers' =>  [
        'Authorization' => "Bearer {$this->token}"
      ],

      /* the content */

      'body'    => $data

    ]);

    if(!is_object($data)) {

      $this->setError(get_class()."::activateLicense() can't get data: ".$this->getError());
      return false;
    }

    if(isset($data->status) && ($data->status == "ERROR")) {

      $this->setError(get_class()."::activateLicense() problem: ".$data->message);

      return false;
    }

    /* if we get this far, were good. */

    $r2    = microtime(true);
    $this->debug(get_class()."::activateLicense() ".sprintf("%4.4f", ($r2-$r1)*1000)." ms.");

    return $data;
  }

  public function downloadFlash($flashId) {
  }

}

?>
