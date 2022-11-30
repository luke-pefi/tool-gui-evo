<?php 

/**
 * 
 * Ethernet helper class for getting status of the wired network.  We don't provide tools for making the
 * interface go up or down, we only expect to be able to sense if its up or down right now.  They can either
 * plugin the cable...or not.
 * 
 */

namespace networking;

class Ethernet {

  /* our Raspberry has only one NIC, and its eth0. */
  
  private static $interface = 'eth0';
  
  /**
   * 
   * isConnected() - test to see if we currently have a wired connection to the internet. This does not test 
   * latency or reachability, only that we are connected.
   * 
   * @return boolean exactly true if we are connected to the LAN/WAN and have an IP address.
   * 
   */
  
  public static function isConnected() {
    
    /* 
     * since we don't provide operations to setup or dismantle the wired network, we just check if they 
     * have a valid IP address, if they do, we are connected, if they don't...we aren't.
     * 
     */
    
    if(self::ipAddress() === false) {
      return false;
    }
    
    return true;
  }
  
  /**
   * 
   * ipAddress() - fetch (if possible) our IP address.  If we have one, it implies we are connected (wired),
   * to the LAN/WAN.
   * 
   * @return mixed exactly false on error, otherwise an IP address (the one for the wired connection).
   * 
   */
  
  public static function ipAddress() {
    
    $eth    = self::$interface;
    $output = `/sbin/ifconfig $eth | /bin/grep 'inet addr:' | /usr/bin/cut -d: -f2 | /usr/bin/awk '{print $1}'`;
    $output = trim($output);
    
    if(empty($output)) { 
      return false;
    }
    
    return $output;
  }
  
  /**
   * 
   * connection() - get the info object on the current wired connection to the LAN/WAN.  If not connected, the 
   * info object will not have an IP address, and its connected flag will be false.
   * 
   * @return mixed exactly false on error, otherwise the Ethernet info object.  
   * 
   */
  
  public static function connection() {
    
    /* default info object is the one for not being connected */
    
    $info = (object)[
      'ip'        => '',
      'connected' => false,
      'port'      => '',
      'duplex'    => '',
      'speed'     => ''
    ];
    
    $info->ip = self::ipAddress();
    
    if($info->ip) {
    
      /* since we are conneted, try to fill in other details */
    
      if(!is_executable('/sbin/ethtool')) {
        
        /* no probing today for you human. */
        
        return false;
      }
      
      $eth     = self::$interface;
      $output  = `/sbin/ethtool $eth`;
      $matches = [];
      
      if(preg_match('/\s+Speed:\s+(\S+)/', $output, $matches)) {
        $info->speed = $matches[1];
      }
      
      if(preg_match('/\s+Duplex:\s+(\S+)/', $output, $matches)) {
        $info->duplex = $matches[1];
      }
      
      if(preg_match('/\s+Port:\s+(\S+)/', $output, $matches)) {
        $info->port = $matches[1];
      }
            
    }
    
    /* pass it back */
    
    return $info;
  }
}

?>