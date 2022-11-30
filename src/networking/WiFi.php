<?php 

/**
 * 
 * WiFi - this controller can be used to query the current WiFi connection, but also to join or leave local WiFi networks.
 * The intention is that if you don't have Ethernet plugged in, you should be able to easily join a WiFi network and
 * keep going.  Tutorial on using wpa_cli to connect to a network:
 * 
 *   http://sirlagz.net/2012/08/27/how-to-use-wpa_cli-to-connect-to-a-wireless-network/
 * 
 * 
 */

namespace networking;

use util\LoggingTrait;
use util\StatusTrait;

use networking\DBM;
use networking\WPASupplicantConfig;

function network_sort($a, $b) {

  $p1 = trim($a->dBm->percent, ' %');
  $p2 = trim($b->dBm->percent, ' %');

  return $p2 - $p1;
}

class WiFi {
  
  
  /* bring in logging and status behaviors */
  
  use LoggingTrait;
  use StatusTrait;

  /* feature masks */

  const SECURE_WEP  = 1;
  const SECURE_WPA  = 2;
  const SECURE_WPA2 = 4;

  /**
   *
   * @var array $factoryDefault the default confiugration
   *
   */
  
  protected $factoryDefault     = [
    'max_join_wait'   => 20,
    'max_forget_wait' => 5,
    'max_scan_wait'   => 5
  ];
  
  private $wpaConfig            = false;
  
  /**
   * 
   * @var array $networks the currently known networks 
   * 
   */
  
  protected $networks           = [];
  
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
    
    if(!isset($this->factoryDefault['interface'])) {
      
      $this->factoryDefault['interface'] = 'wlan0';
    }
    
    /* 
     * setup a WPASupplicantConfig parser, it will do any updating to the wpa supplicant daemon config
     * file that we need; i.e. persistent passwords for WiFi, and allowing the RPI to connect by default
     * to WiFi networks we've already successfully connected to.
     * 
     */
    
    $this->wpaConfig = new WPASupplicantConfig($config, $logger);
    
    if(!$this->wpaConfig->isReady()) {
      $this->setError(get_class()."() - can't make a password store: ".$this->wpaConfig->getError());
      return ;
    }
    
    /* 
     * do a scan so we know in advance what networks are possible and what we can 
     * potentially connect to.
     * 
     */
    
    if($this->scan() === false) {
      
      $this->setError(get_class()."() - can not scan WiFi network: ".$this->getError());
      return ;
    }
    
    /* 
     * clear out any persistent networks (that aren't currentliy connected).  We are going 
     * to manage the connection, there is only going to be one at a time.
     * 
     */
    
    if(!$this->clearPersistentNetworks()) {
      
      $this->setError(get_class()."() - can not clear persistent networks: ".$this->getError());
      return ;
    }
    
    /* if we get this far, everything is ok */
    
    $this->makeReady();
    
    $r2 = microtime(true);
    
    /*$this->debug(get_class()."() time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");*/
  }
  
  /**
   * 
   * scan() - do a scan of available WiFi networks and set these as the currently known networks you can connect to.
   * We also return these networks (as an array of network objects), so you can use them right away.
   * 
   * @return mixed exactly false on error, otherwise a list of WiFi networks.
   * 
   */
  
  public function scan() {
    
    $r1             = microtime(true);
    
    /* 
     * we want to force the wpa_spplicant daemon to do a rescan, but if its currently busy, we might get FAIL-BUSY
     * so we do a loop and we try a couple of times before giving up.
     * 
     */
    
    $eth            = $this->factoryDefault['interface'];
    $retry          = $this->factoryDefault['max_scan_wait'];
    
    while($retry > 0) {
   
      $output       = `/usr/bin/sudo /sbin/wpa_cli scan $eth`;
      $retry--;
      
      if(preg_match('/FAIL-BUSY/', $output)) {
        
        $this->debug(get_class()."::scan suplicant is busy, sleeping for 1 sec...");
        
        sleep(1);
        
        $this->debug(get_class()."::scan retrying ($retry) ...");
        continue;
      }
      
      break;
       
    }
    
    if(!preg_match('/\s+OK$/', $output)) {
      $this->setError(get_class()."::scan can not do 'wpa_cli scan', output was: $output");
      return false;
    }
    
    $output         = `/usr/bin/sudo /sbin/wpa_cli scan_results`;
    $lines          = explode(PHP_EOL, $output);
    
    $this->networks = [];
    
    array_shift($lines); /* Selected interface 'wlan0' */
    array_shift($lines); /* bssid / frequency / signal level / flags / ssid */
    
    foreach($lines as $lineNum => $line) {
     
      /* 
       * looking for:
       * 
       *   28:c6:8e:87:9d:d3       2412    -32     [WPA2-PSK-CCMP][WPS][ESS]       NETGEAR25
       *   
       */
      
      $matches      = [];
      
      if(!preg_match('/^([^ ]+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
        
        /* huh ? */
        
        continue;
      }
      
      $bssid        = $matches[1];
      $freq         = $matches[2];
      $dbm          = $matches[3];
      $features     = preg_split('/\]\[/', trim($matches[4], '[]'));
      $ssid         = $matches[5];
      
      if(empty($ssid)) {
        continue;
      }
      
      $secured      = false;
      
      /* 
       * double check the encryption features to see if this is a secured network (i.e. you must
       * provide a password.
       * 
       */
      
      foreach($features as $cipher) {
        
        if($cipher != 'ESS') {
          $secured  = true;
        }
      }
      
      /* add a network */
        
      $this->networks[$ssid] = (object)[
        'ssid'      => $ssid,
        'connected' => false,
        'password'  => '',
        'secured'   => $secured,
        'bssid'     => $bssid,
        'freq'      => $freq,
        'dBm'       => DBM::info($dbm),
        'features'  => $features
      ];
    }
    
    /* double check if we are already connected to one of them */
    
    /* $this->debug(get_class()."::scan checking if one is connected already..."); */
    
    $con = $this->connection();
    
    if($con === false) {
      
      $this->setError(get_class()."::scan can not get connection status: ".$this->getError());
      return false;
    }
    
    if($con->connected) {
     
      $this->debug(get_class()."::scan already connected to '{$con->ssid}'.");
      
      $this->networks[$con->ssid]->connected = true;
      
      /* also, the connected one always goes to the top of the list */
      
      $this->networks = ["{$con->ssid}" => $this->networks[$con->ssid]] + $this->networks;
      
    }
    
    /* finally, fill in the password for any that we know the password for (from previous use) */
    
    /* $this->debug(get_class()."::scan pre-filling passwords ... "); */
    
    $persistent = $this->wpaConfig->getNetworks();
    
    foreach($persistent as $pdx => $item) {
      
      $ssid     = $item->ssid;
      $password = $item->psk;
      
      if(isset($this->networks[$ssid])) {
        $this->networks[$ssid]->password = $password;
      }
      
    }

    /* final pass to sort them into a reasonably deterministic order, we sort by signal strength */

    uasort($this->networks, '\networking\network_sort');

    /* all done! */
    
    $r2 = microtime(true);
    
    /*$this->debug(get_class()."::scan time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");*/
    
    /* pass them back */
    
    return $this->networks;
  }
  
  /**
   * 
   * autoScan() - if we don't know any local networks yet, do a scan.
   * 
   * @return exactly true.
   * 
   */
  
  public function autoScan() {
    
    if(count($this->networks) == 0) {
      $this->scan;
    }
    
    return true;
  }
  
  /**
   *
   * connection() - fetch the current connection, if there is one.  If not connected, the
   * info object will not have an IP address, and its connected flag will be false.
   *
   * NOTE: same as rawConnection() except that we wait for stable connection status.
   * 
   * @return mixed exactly false on error, otherwise the Ethernet info object.
   *
   */
  
  public function connection() {
    
    $retry = 5;
    $con   = false;
    
    while($retry > 0) {
    
      $con = $this->rawConnection();
    
      if($con !== false) {
        break;
      }
    
      $this->debug(get_class()."::connection waiting for stable status, will sleep 1 sec.");
    
      sleep(1);
    
      $retry--;
    }
    
    if($con === false) {
      $this->setError(get_class()."::connection could not get stable connection status.");
      return false;
    }
    
    /* pass it back */
    
    return $con;
  }
  
  /**
   * 
   * rawConnection() - fetch the current connection, if there is one.  If not connected, the 
   * info object will not have an IP address, and its connected flag will be false.
   * 
   * @return mixed exactly false on error, otherwise the Ethernet info object. 
   *  
   */
  
  public function rawConnection() {
    
    /* default info object is the one for not being connected */
    
    $info            = (object)[
      'ip'           => '',
      'connected'    => false,
      'mac'          => '',
      'freq'         => '',
      'ssid'         => '',
      'id'           => '',
      'secured'      => true,
      'freq'         => 0,
      'dBm'          => false,
      'features'     => ''
    ];
    
    /* 
     * fetch the current status, if it includes attributes that we can map to an active connection, like 
     * having an IP address and wpa_state=COMPLETED, then we have a connection.
     * 
     */
    
    $eth             = $this->factoryDefault['interface'];
    $pattern         = ':a;N;$!ba;s/\n/;/g';
    $output          = `/usr/bin/sudo /sbin/wpa_cli status verbose | /bin/sed 's/=/;/' | /bin/sed '$pattern'`;
    $cols            = preg_split('/;/', $output);
    
    array_shift($cols); /* Selected interface 'wlan0' */
    
    $attribs         = [];
    
    for($i=0; $i<count($cols); ) {
      
      $k             = $cols[$i];
      $v             = $cols[$i+1];
      
      $attribs[$k]   = $v;
      
      $i            += 2;
    }
    
    if(!isset($attribs['ip_address']) || empty($attribs['ip_address'])) {
      
      /* we are not connected */
      
      /*$this->debug(get_class()."::rawConnection no active connection."); */
      
      return $info;
    }
    
    if(isset($attribs['wpa_state']) && ($attribs['wpa_state'] != 'COMPLETED')) {
      
      /* we are not connected */
      
      $this->debug(get_class()."::rawConnection {$attribs['ip_address']} is '".$attribs['wpa_state']."'.");
      
      return false;
    }
    
    /*$this->debug(get_class()."::rawConnection filling details for active connection."); */
    
    /* looks like we have a solid connection */
    
    $info->ip        = $attribs['ip_address'];
    $info->mac       = $attribs['address'];
    $info->connected = true;
    $info->id        = $attribs['id'];
    $info->ssid      = $attribs['ssid'];
    
    /* make sure we know the networks list */
    
    $this->autoScan();
    
    /* add in details for from the network scan to the current connection info so we have more complete info */
    
    $station         = $this->networks[$info->ssid];
    
    $info->dBm       = $station->dBm;
    $info->secured   = $station->secured;
    $info->freq      = $station->freq;
    $info->features  = $station->features;
    
    /*$this->debug(get_class()."::rawConnection returning active WiFi connection for {$info->ssid}."); */
    
    /* all done! */
    
    return $info;
  }

  /**
   *
   * securityLevel() - helper to fetch the security level mask for the given network
   *
   * @param string $ssid - the ssid of the network we want the security mask for.
   *
   * @return int
   *
   */

  private function securityLevel($ssid) {

    if(!isset($this->networks[$ssid])) {
      return 0;
    }

    /* walk through the features looking for what security levels are supported */

    $mask = 0;

    foreach($this->networks[$ssid]->features as $item) {

      $item = strtoupper(trim($item));

      if(preg_match('/^WPA2-PSK/', $item)) {
        $mask |= self::SECURE_WPA2;
      }

      if(preg_match('/^WPA-PSK/', $item)) {
        $mask |= self::SECURE_WPA;
      }

      if(preg_match('/^WEP/', $item)) {
        $mask |= self::SECURE_WPA;
      }
    }

    return $mask;

  }

  /**
   * 
   * join() - join the given WiFi network.  If we have a current connection already, then forget() that
   * one and join this one.
   * 
   * @param string $ssid     - the name of the WiFi network to join
   * @param string $password - (optional) the password to use if the network is secured.
   * 
   * @return mixed exactly false on error, otherwise the connection we just created.
   * 
   */
  
  public function join($ssid, $password='') {
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::join object is not ready.");
      return false;
    }
    
    $ssid       = trim($ssid);

    /* trim whitespace off password */

    $password   = trim($password, ' ');

    /*
     * only trim the quotes, if it is quoted on both sides.  Some passwords might just have a leading
     * or trailing quote in them.  Its still possible a user could intentionally have a password with
     * both a leading and trailing double quote, but that should be exceptionally rare.
     *
     */

    $password   = trim($password, ' ');

    if(preg_match('/^(["]).*\1$/m', $password)) {
      $password   = trim($password, '"');
    }

    if(!empty($password)) {
      $password = '"'.$password.'"';
    }
    
    $saved      = false;
    
    /* wifi comes along, and goes away, so before we do anything, do a fresh scan. */
    
    $this->debug(get_class()."::join auto-scanning...");
    
    $this->scan();
    
    /* make sure we aren't already connected to that one */
    
    $this->debug(get_class()."::join double checking which wifi we are connected to...");
    
    foreach($this->networks as $sdx => $network) {
      
      if($network->connected) {
        
        if($ssid == $sdx) {
          
          /* already connected to that one! */
          
          $this->debug(get_class()."::join already connected to '$ssid'.");
          return true;
        }
      }
    }
    
    /* 
     * look up this network and make sure we know how to connect, and if a password is required, then we 
     * require it.
     * 
     */
    
    $this->debug(get_class()."::join validating credentials...");
    
    if(!isset($this->networks[$ssid])) {
      $this->debug("networks: ".print_r($this->networks,true));
      $this->setError(get_class()."::join no such network '$ssid'");
      return false;
    }
    
    if($this->networks[$ssid]->secured && empty($password))  {
       
      $this->debug(get_class()."::join no password provided, checking if we know it...");
      
      $password = $this->networks[$ssid]->password;
      
      if(empty($password)) {
        
        $this->debug(get_class()."::join no password provided, trying to use previously provided one...");
      
        $password = $this->wpaConfig->password($ssid);
        $saved    = $password;
        
        if(!$password) {
        
          $this->debug(get_class()."::join can't find any previously used password for '$ssid'.");
        
          $this->setError(get_class()."::join network ('$ssid') requires password.");
          return false;
        }
      }
    }

    /* we give preference to WPA its the most common */

    $proto  = "";
    $mask   = $this->securityLevel($ssid);

    if($mask & self::SECURE_WPA) {

      $proto = "WPA";

    } else if($mask & self::SECURE_WPA2) {

      $proto = "WPA2";

    } else if($mask & self::SECURE_WEP) {

      $proto = "WEP";
    }

    /*
     * NOTE: we currently aren't supporting WEP, but we could.
     *
     */

    if($proto == "WEP") {
      $this->setError(get_class()."::join network ('$ssid') requires WEP (not implemented).");
      return false;
    }

    $this->debug(get_class()."::join security protocol: $proto");

    /*
     * we know which network it is, and we have a password, if the network is secured then the password
     * has a minimum length; 5 for WEP and 8 for WPA.
     *
     */

    if($this->networks[$ssid]->secured) {

      if($proto == "WEP") {

        if (strlen($password) < 5) {

          $this->setError(get_class() . "::join password must be at least 8 characters (WEP)");
          return false;
        }

      } else {

        if (strlen($password) < 8) {

          $this->setError(get_class() . "::join password must be at least 8 characters (WPA)");
          return false;
        }
      }
    }

    /* disconnect any current connection */
    
    $oldCon   = $this->disconnect();
    
    if($oldCon === false) {
      
      $this->setError(get_class()."::join could not forget existing connection: ".$this->getError());
      return false;
    }
    
    /* we appear to be good to go, try to connect... */
    
    /* add a network in our own WiFi controller */
    
    $this->debug(get_class()."::join adding network...");

    $cmdLog   = [];
    $cmd      = "/usr/bin/sudo /sbin/wpa_cli add_network";
    $output   = `$cmd`;
    $cmdLog[] = $cmd;
    $matches  = [];
    
    if(!preg_match('/\s+(\d+)$/', $output, $matches)) {
      
      $this->setError(get_class()."::join can not do $cmd, output was: $output");
      
      /* try to clean up the mess */
      
      $this->removeNetwork($id);
      
      return false;
    }
    
    $id       = $matches[1];
    
    /* set the credentials to join, if no password, then no key management (i.e. don't use ciphers) */
    
    $this->debug(get_class()."::join configuring network ($id) name ($ssid)...");
    
    $name     = '"'.$ssid.'"';
    $cmd      = "/usr/bin/sudo /sbin/wpa_cli set_network $id ssid '$name'";
    $cmdLog[] = $cmd;
    $output   = `$cmd`;
    
    if(!preg_match('/\s+OK$/', $output, $matches)) {
      
      $this->setError(get_class()."::join can not do $cmd, output was: $output");
      
      /* try to clean up the mess */
      
      $this->removeNetwork($id);
      
      return false;
    }
    
    $this->debug(get_class()."::join configuring network ($id) password...");
    
    if($this->networks[$ssid]->secured) {
      
      $this->debug(get_class()."::join . setting password ($password)...");

      $cmd      = "/usr/bin/sudo /sbin/wpa_cli set_network $id psk '$password'";
      $cmdLog[] = $cmd;
      $output   = `$cmd`;
          
      if(!preg_match('/\s+OK$/', $output, $matches)) {
        
        $this->setError(get_class()."::join can not do $cmd, output was: $output");
        
        /* try to clean up the mess */
        
        $this->removeNetwork($id);
        
        return false;
      }
      
    } else {

      $this->debug(get_class()."::join setting public password...");

      $cmd      = "/usr/bin/sudo /sbin/wpa_cli set_network $id key_mgmt NONE";
      $cmdLog[] = $cmd;
      $output   = `$cmd`;
      
      $this->debug(get_class()."::join . disabling key management...");
      
      if(!preg_match('/\s+OK$/', $output, $matches)) {
        
        $this->setError(get_class()."::join can not do $cmd, output was: $output");
        
        /* try to clean up the mess */
        
        $this->removeNetwork($id);
        
        return false;
      }
             
    }
    
    /* enable it for joining, the wpa supplicant daemon will notice this, and try to associate, we just have to wait... */
   
    $this->debug(get_class()."::join enabling network ($id)...");

    $cmd      = "/usr/bin/sudo /sbin/wpa_cli enable_network $id";
    $cmdLog[] = $cmd;
    $output   = `$cmd`;
    
    if(!preg_match('/\s+OK$/', $output, $matches)) {
      
      $this->setError(get_class()."::join can not do $cmd, output was: $output");
      
      /* try to clean up the mess */
      
      $this->removeNetwork($id);
      
      return false;
    }

    $this->debug(get_class()."::join commands sent: ");
    foreach($cmdLog as $cmd) {
      $this->debug(get_class()."::join . $cmd");
    }

    $this->debug(get_class()."::join confirming connection...");

    /* 
     * fetch the current connection, there better be one! The network may be slow in joining though, so 
     * do a little retry loop where we wait a few seconds if we have to for the join to happen.
     * 
     */
    
    $retry    = $this->factoryDefault['max_join_wait'];;
    $con      = false;
    
    while($retry > 0) {
      
      $this->debug(get_class()."::join has joined? ($retry)... ");
      
      $con    = $this->connection();
      
      if($con === false) {
        
        $this->setError(get_class()."::join can not get connection status: ".$this->getError());
        return false;
      }
      
      if($con->connected) {
        
        $this->debug(get_class()."::join connected! ($ssid)");
        
        break;
      }
      
      /* that would be no. */
      
      $this->debug(get_class()."::join not ready yet, sleeping for 1 sec ... ");
      
      sleep(1);
      
      $retry--;
    }
    
    if(!$con->connected) {
      
      $this->setError(get_class()."::join could not actually join network ($ssid). Bad password?");
      
      /* try to clean up the mess */
      
      $this->removeNetwork($id);
      
      return false;
    }
    
    /* 
     * we successfully connected! store the password, and enable this wifi for auto-boot when the 
     * raspberry pi is next powered on.
     * 
     */
    
    if($saved) {
      
      $this->debug(get_class()."::join used existing password, no need to re-save.");
      
    } else {
      
      $this->debug(get_class()."::join new password detected, saving...");
      
      if(!$this->wpaConfig->remember($ssid, $password)) {
      
        $this->setError(get_class()."::join could not save password ($ssid): ".$this->wpaConfig->getError());
        return false;
      }
    }
    
    /* all done */
    
    return $con;
  }
  
  /**
   * 
   * removeNetwork() - helper method to just remove a single network from the wpa supplicant internal list.
   * 
   * @param integer $id the wpa supplicate id for a network
   * 
   * @return boolean exactly false on error.
   * 
   */
  
  public function removeNetwork($id) {
   
    /* when you remove the network, the wpa_supplicant daemon will notice and eventually tear it down */
    
    $output = `/usr/bin/sudo /sbin/wpa_cli remove_network $id`;
    
    if(!preg_match('/\s+OK$/', $output)) {
      $this->setError(get_class()."::removeNetwork can not do 'wpa_cli remove_network $id', output was: $output");
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * disconnect() - drop the current WiFi network, if there is one.
   * 
   * @return mixed exactly false on error, otherwise the connection we just dropped.  If there 
   * is no current connection we return exactly true.
   * 
   */
  
  public function disconnect() {
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::disconnect object is not ready.");
      return false;
    }
    
    /* do we even have a current connection? */
    
    $con    = $this->connection();
      
    if($con === false) {
      
      $this->setError(get_class()."::disconnect can not get connection status: ".$this->getError());
      return false;
    }
    
    if(!$con->connected) {
      
      $this->debug(get_class()."::disconnect no current connection.");
      
      return true;
    }
    
    $this->debug(get_class()."::disconnect disconnecting from {$con->ssid}...");
    
    $id     = $con->id;
    
    $this->removeNetwork($id);
    
    /* now wait/cofirm that it really did go away */
    
    $this->debug(get_class()."::forget confirming disconnect...");
    
    $retry = $this->factoryDefault['max_forget_wait'];;
    $check = false;
    
    while($retry > 0) {
      
      $check = $this->connection();
      
      if(!$check->connected) {
        
        $this->debug(get_class()."::disconnect disconnected! ({$con->ssid}:{$con->id})");
        
        break;
      }
      
      $retry--;
    }
  
    if($check->connected) {
      
      $this->setError(get_class()."::disconnect could not actually disconnect ({$con->ssid}:{$con->id}).");
      return false;
    }
    
    /* all done */
    
    $this->debug(get_class()."::disconnect disconnected.");
    
    return $con;
  }
  
  /**
   * 
   * forget() - is a stronger version of disconnect(), we drop the connection if this one is connected, but 
   * we also delete any saved password, and completely remove it from the stored wpa supplicant configuration,
   * so it will not be a candidate for auto-start when the raspberry pi reboots.
   * 
   * @param string $ssid - the SSID of the WiFi network to forget about.
   * 
   * @return boolean exactly false on error
   * 
   */
  
  public function forget($ssid) {
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::forget object is not ready.");
      return false;
    }
    
    if(empty($ssid)) {
      
      $this->setError(get_class()."::forget no SSID provided.");
      return false;
    }
    
    /* do we have a current connection? */
    
    $con    = $this->connection();
    
    if($con === false) {
      
      $this->setError(get_class()."::forget can not get connection status: ".$this->getError());
      return false;
    }
    
    if($con->connected) {
      
      /* only disconnect if its the one we are forgetting */
      
      if($con->ssid == $ssid) {
        
        $this->debug(get_class()."::forget disconnecting from {$con->ssid}...");
        
        if($this->disconnect() === false) {
          
          $this->setError(get_class()."::forget could not disconnect from {$con->ssid}.");
          return false;
        } 
        
      }
 
    }
    
    /* actually erase the password, and stop this wifi from being auto-boot enabled */
    
    if(!$this->wpaConfig->forget($ssid)) {
      
      $this->setError(get_class()."::forget could not forget password ($ssid): ".$this->wpaConfig->getPassword());
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  public function clearPersistentNetworks() {
    
    /*$this->debug(get_class()."::clearPersistentNetworks cleaning...");*/
    
    /* get the current connection, if there is one */
    
    $con = $this->connection();
    
    if($con === false) {
      
      $this->setError(get_class()."::clearPersistentNetworks can not get connection status: ".$this->getError());
      return false;
    }
    
    $id  = false;
    $key = false;
    
    if($con->connected) {
      
      $id = $con->id;  
      
      /* $this->debug(get_class()."::clearPersistentNetworks has active network $id ({$con->ssid})"); */
    }
    
    /* get the persistent networks that the wpa supplicant has in memory */
    
    $ids = $this->persistentNetworks();
    
    if(!is_array($ids)) {
      
      $this->setError(get_class()."::clearPersistentNetworks can not get persistent network ids: ".$this->getError()); 
      return false;
    }
    
    /* if one of them is active, remove it from the list ... */
    
    if($id !== false) {
      
      $key = array_search($id, $ids);
    
      if($key !== false) { 
      
        /* $this->debug(get_class()."::clearPersistentNetworks not removing active network $id ({$con->ssid})"); */
        unset($ids[$key]);
      }
    }
    
    /* 
     * get rid of all them except the one we are currently connected to (if we are connected). We're 
     * going to manage the network connections, and there will only ever be one active network, 
     * so there is absolutely no confusion about which network we are trying to join, and we don't
     * accumlate a big list of sessions.
     * 
     */
    
    foreach($ids as $idx => $toRemove) {
      
      /* when you remove the network, the wpa_supplicant daemon will notice and eventually tear it down */
      
      /*$this->debug(get_class()."::clearPersistentNetworks removing network $toRemove ..."); */
      
      if(!$this->removeNetwork($toRemove)) {
        $this->setError(get_class()."::clearPersistentNetworks can not do 'wpa_cli remove_network $toRemove', output was: $output");
        return false;
      }
      
    }
    
    /*$this->debug(get_class()."::clearPersistentNetworks clear!"); */
    
    /* all done */
    
    return true;
  }
  
  public function persistentNetworks() {
    
    /* figure out what the persistent networks are */
    
    $output    = `/usr/bin/sudo /sbin/wpa_cli list_networks`;
    
    if(empty($output) || !preg_match('/network\s+id\s+\/\s+ssid\s+\/\s+bssid\s+\/\s+flags/', $output)) {
      
      $this->setError(get_class()."::persistentNetworks can not find network list, output was: $output"); 
      return false;
    }
    
    $lines     = explode(PHP_EOL, $output);
    $ids       = [];
    
    /* walk through the list, we want the network ids, so we can do things like remove them :) */
    
    foreach($lines as $lineNum => $line) {
      
      $matches = [];
      
      if(!preg_match('/^\s*(\d+)\s+/', $line, $matches)) {
        
        /* garbled? */
        
        continue;
      }
      
      $ids[] = $matches[1];
    }
    
    $this->debug(get_class()."::persistentNetworks passing back network ids: ".implode(',', $ids));
    
    /* pass them back */
    
    return $ids;
  }
  
}

?>