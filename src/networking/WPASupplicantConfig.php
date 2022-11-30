<?php

/**
 *
 * WPASupplicantConfig - this controller manages the configuration file:
 * 
 *   /etc/wpa_supplicant/wpa_supplicant.conf
 *   
 * That file is used by the wpa_supplicant daemon to manage wireless connections and do things like join WiFi
 * networks.  
 * 
 * Our interest in it, is that we have to add entries to it after a user makes a valid connection to a WiFi
 * network, so we remember the valid connections accross boot time and the RPI can automatically join 
 * a WiFi network they previously logged into.
 * 
 * We also need to be able to Forget an entry (remove it from the file), and we have to be able to fetch
 * the password for a given network, in case they want to just manually join one, without having to 
 * re-input the password.
 *
 */

namespace networking;

use util\LoggingTrait;
use util\StatusTrait;

class WPASupplicantConfig {
  
  
  /* bring in logging and status behaviors */
  
  use LoggingTrait;
  use StatusTrait;
  
  protected $factoryDefault     = [
    'config'   => '/etc/wpa_supplicant/wpa_supplicant.conf',
    'prefix'   => "ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\nupdate_config=1\n\n"
  ];
  
  /**
   *
   * @var array $networks the currently known networks blocks from the supplicants config file
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
    
    if(!isset($this->factoryDefault['config']) || empty($this->factoryDefault['config'])) {
      
      $this->factoryDefault['config'] = '/etc/wpa_supplicant/wpa_supplicant.conf';
    }
    
    /*
     * initial parse so we know what the networks are...
     *
     */
    
    if($this->parse() === false) {
      
      $this->setError(get_class()."() - can not parse config file: ".$this->getError());
      return ;
    }
    
    /* if we get this far, everything is ok */
    
    $this->makeReady();
    
    $r2 = microtime(true);
    
    /*$this->debug(get_class()."() time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");*/
  }
 
  /**
   * 
   * getNetworks() - fetch the currently known networks (if there are any)
   * 
   * @return array (might be empty)
   * 
   */
  
  public function getNetworks() {
    return $this->networks;
  }
  
  /**
   * 
   * remember() - save/update the password for the given wifi network ($ssid).  We don't reconfigure the wpa_supplicant 
   * daemon (it might kill active wifi connections).  Just save the file so it gets remembered on next boot.
   * 
   * @param string $ssid     - the name/id of the wifi network involved.
   * @param string $password - the new password for this SSID. can be empty (it might be a public network)
   * 
   * @return boolean exactly false on error
   * 
   */
  
  public function remember($ssid, $password="") {
    
    /* ready? */
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::remember object not ready.");
      return false;
    }
    
    if(empty($ssid)) {
      
      $this->setError(get_class()."::remember no SSID provided.");
      return false;
    }
    
    /* add a new entry, we only add ssid and psk, leave all the other settings as default, its not broken, don't fix it. */
    
    $this->debug(get_class()."::remember setting new password for '$ssid'...");
    
    /* 
     * NOTE: we always add a new entry, because the password might change from empty to something or vice versa 
     * (i.e. public/secured), and that means we may or may not be using a psk line, and the key_mgmnt line may be
     * also changing at the same time.
     * 
     */

    if(!empty($password) && ($password != '""')) {
        
      $this->networks[$ssid] = (object)[
        'ssid'  => $ssid,
        'psk'   => $password,
        'block' => "
\tssid=\"$ssid\"
\tpsk=$password
\tkey_mgmt=WPA-PSK
"
      ];
        
    } else {

      $this->networks[$ssid] = (object)[
        'ssid'  => $ssid,
        'psk'   => '',
        'block' => "
\tssid=\"$ssid\"
\tkey_mgmt=NONE
"
      ];
        
    }
    
    /* save */
    
    if(!$this->save()) {
      
      $this->setError(get_class()."::remember can not save configuration: ".$this->getError());
      return false;
    }
    
    $this->debug(get_class()."::remember done.");
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * password() - fetch the WiFi password for the given WiFi network (if we know it already)
   * 
   * @param string $ssid     - the name/id of the wifi network involved.
   * 
   * @return mixed exactly false on error, otherwise the password for that WiFi network
   * 
   */
  
  public function password($ssid) {
  
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::password object not ready.");
      return false;
    }
    
    if(empty($ssid)) {
      
      $this->setError(get_class()."::password no SSID provided.");
      return false;
    }
    
    if(!isset($this->networks[$ssid])) {
      
      $this->setError(get_class()."::password no such SSID ($ssid).");
      return false;
    }
    
    /* pass it back */
    
    return $this->networks[$ssid]->psk;
  }
  
  /**
   * 
   * authenticate() for the given WiFi network ($ssid), confirm that this password is the one we already know (if we 
   * know one for this $ssid).
   * 
   * @param string $ssid     - the name/id of the wifi network involved.
   * @param string $password - the expected passwod
   * 
   * @return boolean exactly false on error or password mismatch.
   * 
   */
  
  public function authenticate($ssid, $password) {
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::authenticate object not ready.");
      return false;
    }
    
    if(empty($ssid)) {
      
      $this->setError(get_class()."::authenticate no SSID provided.");
      return false;
    }
    
    $this->debug(get_class()."::authenticate for '$ssid'...");
    
    $expected = $this->password($ssid);
    
    if($expected === false) {
      
      /* we don't have one yet */
      
      $this->debug(get_class()."::authenticate on '$ssid': no remembered");  
      return false;
    }
    
    if($password != $expected) {
      
      /* password mismatch */
      
      $this->debug(get_class()."::authenticate on '$ssid': mismatch.");
      return false;
    }
    
    /* ok! */
    
    $this->debug(get_class()."::authenticate on '$ssid': PASS");
    
    return true;
  }
  
  /**
   * 
   * forget() - remove this network entry, and stop remembering its password.
   * 
   * @param string $ssid     - the name/id of the wifi network involved.
   * 
   * @return boolean exactly false on error
   * 
   */
  
  public function forget($ssid) {
    
    /* ready? */
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::forget object not ready.");
      return false;
    }
    
    if(empty($ssid)) {
      
      $this->setError(get_class()."::forget no SSID provided.");
      return false;
    }
    
    $this->debug(get_class()."::forget dropping password of '$ssid'...");
    
    $expected = $this->password($ssid);
    
    if($expected === false) {
      
      /* we don't have one yet */
      
      $this->debug(get_class()."::forget ($ssid) not remembered yet.");
      return true;
    }
    
    /* go swim with the fishes */
    
    unset($this->networks[$ssid]);
    
    /* save */
   
    if(!$this->save()) {
      
      $this->setError(get_class()."::remember can not save configuration: ".$this->getError());
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  /**
   *
   * parse() - parse out the network blocks of the config file so we know what we 
   * have for networks.  We can then select which ones to put back in, or which ones
   * to add. 
   *
   * @return mixed exactly false on error, otherwise a list of WiFi networks.
   *
   */
  
  public function parse() {
    
    $r1          = microtime(true);
    
    /*$this->debug(get_class()."::parse starts..."); */
    
    /* get current configuration */
    
    $cmd         = "/usr/bin/sudo /bin/cat ".$this->factoryDefault['config'];
    $text        = `$cmd`;
    
    if(empty($text)) {
    
      $this->setError(get_class()."::parse can not read config file: ".$this->factoryDefault['config']);
      return false;
    }
    
    /* pattern match for blocks */
    
    $matches     = [];
    preg_match_all('/network\s*=\s*{([^}]+)}/', $text, $matches);
    
    foreach($matches[1] as $mdx => $block) {
      
      /* we have a network block */
      
      $ssid      = false;
      $psk       = '';
      
      /* pull out the SSID so we can identify the block */
      
      $smaatches = [];
      
      if(!preg_match('/ssid=\"([^"]+)\"/', $block, $smatches)) {
        
        $this->warning(get_class()."::parse corrupt network block, can't find SSID in block: $block");
        continue;
      }
      
      $ssid = $smatches[1];
      
      /* there may not be a psk line, because it might be a public network */
      
      if(preg_match('/\s+psk=(\S+)/', $block, $smatches)) {
        
        $psk = $smatches[1];
      }
      
      $this->networks[$ssid] = (object)[
        'ssid'  => $ssid,
        'psk'   => $psk,
        'block' => $block
      ];
    }
    
    $r2          = microtime(true);
    
    /* $this->debug(get_class()."::parse time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms."); */

    /* pass them back */
    
    return $this->networks;
  }
  
  /**
   * 
   * save() write the wpa supplicant configurtion file back to disk with whatever our networks
   * are at this moment.
   * 
   * @return boolean exactly false on error.
   * 
   */
  
  public function save() {
    
    if(!$this->isReady()) {
      
      $this->setError(get_class()."::save object not ready.");
      return false;
    }
    
    $r1          = microtime(true);
    
    $this->debug(get_class()."::save updating config file: ".$this->factoryDefault['config']); 

    /* create the text to go in the file */
    
    $text = $this->factoryDefault['prefix'];
    
    foreach($this->networks as $ndx => $item) {
      
      $text .= "network={";
      $text .= $item->block;
      $text .= "}\n\n";
    }
    
    /* save to a temporary file */
    
    $src = tempnam('/tmp', 'wpa-conf');
    $dst = $this->factoryDefault['config'];
    
    file_put_contents($src, $text);
    
    if(filesize($src) < strlen($text)) {
      
      $this->setError(get_class()."::save could not write new (temporary) configuration file ($src)");
      return false;
    }
    
    /* overwrite the current one */
    
    $cmd    = "/usr/bin/sudo /bin/cp $src ".$this->factoryDefault['config'];
    $output = `$cmd`;
    $cmd    = "/usr/bin/sudo /usr/bin/cmp --silent $src ".$this->factoryDefault['config']." > /dev/null 2>&1";
    $status = true;
    
    exec($cmd, $output, $status);
   
    if($status) {
      
      $this->setError(get_class()."::save could not overwrite wpa supplicant configuration file (".$this->factoryDefault['config'].")");
      return false;
    }
    
    /* 
     * we don't force wpa supplicant to reconfigure, because that would kill any existing wifi connection,
     * this is more about just remembering the details for after the next reboot.
     * 
     */
    
    $r2          = microtime(true);
    
    $this->debug(get_class()."::save time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");
    
    /* all done */
    
    return true;
  }
}

?>