<?php 

/*
 * DBM - this class provides conversion from dbm signal strength values like -67dBm to more human readable values
 * like percent of ideal signal strength or number of bars.  Very good explanation here:
 * 
 *   https://www.adriangranados.com/blog/dbm-to-percent-conversion
 *   https://support.metageek.com/hc/en-us/articles/201955754-Understanding-WiFi-Signal-Strength
 *   
 * Signal strength is a measure of how much attenuation is there, how weak the signal is.  So you want it to be 
 * as close to 0 as possible (i.e. not weak).  However the farther you get from the signal source, the more it 
 * drops off, so its a logorithmic scale, and negative (because its gets weaker).
 * 
 * Basically for "bars" you typically see for a WiFi connection:
 * 
 * -30 dBm 	Max achievable signal strength. The client can only be a few feet from the AP to achieve this. Not typical 
 *          or desirable in the real world. 	
 *          
 * -67 dBm 	Minimum signal strength for applications that require very reliable, timely packet delivery. 	VoIP/VoWiFi, 
 *          streaming video
 *          
 * -70 dBm 	Minimum signal strength for reliable packet delivery.
 * 
 * -80 dBm 	Minimum signal strength for basic connectivity. Packet delivery may be unreliable. 
 * 
 * -90 dBm 	Approaching or drowning in the noise floor. Any functionality is highly unlikely.
 * 
 */

namespace networking;

use util\Color;

class DBM {
  
  /**
   *
   * @var integer $strength - the dBm signal strength
   *
   */
  
  static private $percentMap = [
    '93'  => 1,
    '92'  => 3, 
    '91'  => 6,
    '90'  => 8,
    '89'  => 10,
    '88'  => 13,
    '87'  => 15,
    '86'  => 17,
    '85'  => 20,
    '84'  => 22,
    '83'  => 24,
    '82'  => 26,
    '81'  => 28,
    '80'  => 30,
    '79'  => 32,
    '78'  => 34,
    '77'  => 36,
    '76'  => 38,
    '75'  => 40,
    '74'  => 42,
    '73'  => 44,
    '72'  => 46,
    '71'  => 48,
    '70'  => 50,
    '69'  => 51,
    '68'  => 53,
    '67'  => 55,
    '66'  => 56,
    '65'  => 58,
    '64'  => 60,
    '63'  => 61,
    '62'  => 63,
    '61'  => 64,
    '60'  => 66,
    '59'  => 67,
    '58'  => 69,
    '57'  => 70,
    '56'  => 71,
    '55'  => 73,
    '54'  => 74,
    '53'  => 75,
    '52'  => 76,
    '51'  => 78,
    '50'  => 79,
    '49'  => 80,
    '48'  => 81,
    '47'  => 82,
    '46'  => 83,
    '45'  => 84,
    '44'  => 85,
    '43'  => 86,
    '42'  => 87,
    '41'  => 88,
    '40'  => 89,
    '39'  => 90,
    '38'  => 90,
    '37'  => 91,
    '36'  => 92,
    '35'  => 93,
    '34'  => 93,
    '33'  => 94,
    '32'  => 95,
    '31'  => 95,
    '30'  => 96,
    '29'  => 96,
    '28'  => 97,
    '27'  => 97,
    '26'  => 98,
    '25'  => 98, 
    '24'  => 98,
    '23'  => 99,
    '22'  => 99,
    '21'  => 99,
    '20'  => 100
  ];
  
  /**
   *
   * @var integer $bars - the strength break points for bars
   *
   */
  
  static private $bars     = [
    '30' => 5,
    '67' => 4,
    '70' => 3,
    '80' => 2,
    '97' => 1
  ];
  
  /**
   * 
   * toNumber() - helper to convert stuff like -67dBm to 67 so we can use our tables to map to percent or number of 
   * bars.
   * 
   * @param mixed $dbm the string or integer that is the dBm signal strenth.
   * 
   * @return integer - the positive integer value
   * 
   */
  
  private static function toNumber($dbm) {
    
    if(is_numeric($dbm)) {
      return ceil(abs($dbm));
    }
    
    $dbm = preg_replace('/[^0-9.]/', '', $dbm);
    
    return ceil(abs($dbm));
  }
  
  /**
   * 
   * percentOf() - convert to percentage of maximum signal strength
   * 
   * @param mixed $dbm the string or integer that is the dBm signal strenth.
   * 
   * @return integer the percent (1..100), 100 is maximum signal strength.
   * 
   */
  
  public static function percentOf($dbm) {
    
    $dbm = self::toNumber($dbm);
    
    if($dbm < 20) {
      return 100;
    }
    
    if($dbm > 93) {
      return 1;
    }
    
    return self::$percentMap[$dbm];
  }
  
  /**
   *
   * barsOf() - convert to number of strength bars
   *
   * @param mixed $dbm the string or integer that is the dBm signal strenth.
   *
   * @return integer the bars (1..5), 5 is maximum signal strength.
   *
   */
  
  public static function barsOf($dbm) {
    
    $dbm   = self::toNumber($dbm);
    $level = 5;
    
    foreach(self::$bars as $dbLevel => $n) {
      
      if($dbm >= $dbLevel) {
        $level = $n;
      }
    }
    
    return $level;
  }
  
  /**
   *
   * colorOf() - convert to a color (based on percentage).  Red is 0% signal, and green is 100% signal
   * strength.
   *
   * @param mixed $dbm the string or integer that is the dBm signal strenth.
   *
   * @return string HTML/CSS color value.
   *
   */
  
  public static function colorOf($dbm) {
    
    /* get the percent, map that to a color, red is bad, green is good */
    
    $percent = 100 - self::percentOf($dbm);
    $color   = Color::ryg($percent);
    
    return $color;
  }
  
  /**
   *
   * info() - convert to the various formats we might want for a dBm signal value. Basically all of them :)
   *
   * @param mixed $dbm the string or integer that is the dBm signal strenth.
   *
   * @return object that has the various interpretations of a dBm value.
   *
   */
  
  public static function info($dbm) {
    
    $info = (object)[
      'dBm'     => $dbm,
      'percent' => self::percentOf($dbm),
      'bars'    => self::barsOf($dbm),
      'color'   => self::colorOf($dbm)
    ];
    
    return $info;
  }

}

?>