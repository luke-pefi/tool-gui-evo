<?php

/**
 *
 * VIN - Vehicle Identification Number parsing, and validation helper.  For basic structure of modern VIN 
 * numbers (typically 1980 or newer), the general format is detailed here:
 * 
 *   https://en.wikipedia.org/wiki/Vehicle_identification_number
 *   http://vin.dataonesoftware.com/vin_basics_blog/bid/95259/VIN-Decoding-101-Everything-you-wanted-to-know-about-VINs
 *   
 * format has changed over the years and each manufacturer has sometimes had their own rules,
 * but that format should be followed by most modern gear.  For stull older than 1980, you need to get 
 * into knowing how each manufacturer did their serial numbers.
 * 
 * For identifying the manufacturer, the first 3 characters are gerally the "WMI" or World Manufacturers
 * Identifier:
 * 
 *   http://www.sae.org/standardsdev/groundvehicle/vin.htm
 *   
 * The complete list is about 33K, and can be purchased from them for $500, not exactly cheap eh?  
 * 
 * We're really only interested in the commonly used ones and of course the ones for snowmobiles, 
 * and powersleds...or anything else we start doing ECU flashing of.
 *
 * If you need sample VIN #s you can grab old junker VIN #s for various brands here:
 * 
 *   http://www.autobidmaster.com/carfinder-online-auto-auctions/salvage-snowmobiles/
 * 
 * Apparently people wreck a lot of snowmobiles :)
 * 
 * You can check our results against other decoders:
 * 
 *   http://en.vindecoder.pl/2BPSERFAXFV000334
 *   https://www.vindecoderz.com/
 *   http://www.vindecoder.pl/
 * 
 */

namespace vin;

use util\LoggingTrait;
use util\StatusTrait;


class VIN {
  
  /* bring in logging and status behaviors */
  
  use LoggingTrait;
  use StatusTrait;
  
  /**
   *
   * @var string $vin the actual vin number
   *
   */
  
  protected $vin      = false;
  
  /**
   *
   * @var array $validChars quick lookup of valid VIN # characters
   *
   */
  
  private $validChars = [
    'A' => true,
    'B' => true, 
    'C' => true, 
    'D' => true, 
    'E' => true,
    'F' => true, 
    'G' => true, 
    'H' => true, 
    'J' => true, 
    'K' => true, 
    'L' => true, 
    'M' => true, 
    'N' => true, 
    'P' => true, 
    'R' => true,
    'S' => true,
    'T' => true, 
    'U' => true, 
    'V' => true, 
    'W' => true, 
    'X' => true, 
    'Y' => true, 
    'Z' => true, 
    '1' => true, 
    '2' => true, 
    '3' => true, 
    '4' => true, 
    '5' => true, 
    '6' => true, 
    '7' => true,
    '8' => true, 
    '9' => true, 
    '0' => true 
  ];
  
  private $yearChars = [
    'A' => 0, 
    'B' => 1, 
    'C' => 2, 
    'D' => 3, 
    'E' => 4, 
    'F' => 5, 
    'G' => 6, 
    'H' => 7, 
    'J' => 8, 
    'K' => 9,  
    'L' => 10, 
    'M' => 11, 
    'N' => 12, 
    'P' => 13, 
    'R' => 14, 
    'S' => 15, 
    'T' => 16, 
    'V' => 17, 
    'W' => 18, 
    'X' => 19, 
    'Y' => 20, 
    '1' => 21, 
    '2' => 22, 
    '3' => 23, 
    '4' => 24, 
    '5' => 25, 
    '6' => 26, 
    '7' => 27, 
    '8' => 28, 
    '9' => 29, 
    '0' => 30
  ];
  
  private $countryCodes = [
    "AA-AH" => "South Africa",
    "AJ-AN" => "Ivory Coast",
    "AP-A0" => "not assigned",
    "BA-BE" => "Angola",
    "BF-BK" => "Kenya",
    "BL-BR" => "Tanzania",
    "BS-B0" => "not assigned",
    "CA-CE" => "Benin",
    "CF-CK" => "Malagasy",
    "CL-CR" => "Tunisia",
    "CS-C0" => "not assigned",
    "DA-DE" => "Egypt",
    "DF-DK" => "Morocco",
    "DL-DR" => "Zambia",
    "DS-D0" => "not assigned",
    "EA-EE" => "Ethiopia",
    "EF-EK" => "Mozambique",
    "EL-E0" => "not assigned",
    "FA-FE" => "Ghana",
    "FF-FK" => "Nigeria",
    "FF-FK" => "Madagascar",
    "FL-F0" => "not assigned",
    "GA-G0" => "not assigned",
    "HA-H0" => "not assigned",
    "JA-J0" => "Japan",
    "KA-KE" => "Sri Lanka",
    "KF-KK" => "Israel",
    "KL-KR" => "Korea (South)",
    "KS-K0" => "not assigned",
    "LA-L0" => "China",
    "MA-ME" => "India",
    "MF-MK" => "Indonesia",
    "ML-MR" => "Thailand",
    "MS-M0" => "not assigned",
    "NF-NK" => "Pakistan",
    "NL-NR" => "Turkey",
    "NS-N0" => "not assigned",
    "PA-PE" => "Philipines",
    "PF-PK" => "Singapore",
    "PL-PR" => "Malaysia",
    "PS-P0" => "not assigned",
    "RA-RE" => "United Arab Emirates",
    "RF-RK" => "Taiwan",
    "RL-RR" => "Vietnam",
    "RS-R0" => "not assigned",
    "SA-SM" => "Great Britain",
    "SN-ST" => "Germany",
    "SU-SZ" => "Poland",
    "S1-S0" => "not assigned",
    "TA-TH" => "Switzerland",
    "TJ-TP" => "Czechoslovakia",
    "TR-TV" => "Hungary",
    "TW-T1" => "Portugal",
    "T2-T0" => "not assigned",
    "UA-UG" => "not assigned",
    "UH-UM" => "Denmark",
    "UN-UT" => "Ireland",
    "UU-UZ" => "Romania",
    "U1-U4" => "not assigned",
    "U5-U7" => "Slovakia",
    "U8-U0" => "not assigned",
    "VA-VE" => "Austria",
    "VF-VR" => "France",
    "VS-VW" => "Spain",
    "VX-V2" => "Yugoslavia",
    "V3-V5" => "Croatia",
    "V6-V0" => "Estonia",
    "WA-W0" => "Germany",
    "XA-XE" => "Bulgaria",
    "XF-XK" => "Greece",
    "XL-XR" => "Netherlands",
    "XS-XW" => "U.S.S.R.",
    "XX-X2" => "Luxembourg",
    "X3-X0" => "Russia",
    "YA-YE" => "Belgium",
    "YF-YK" => "Finland",
    "YL-YR" => "Malta",
    "YS-YW" => "Sweden",
    "YX-Y2" => "Norway",
    "Y3-Y5" => "Belarus",
    "Y6-Y0" => "Ukraine",
    "ZA-ZR" => "Italy",
    "ZS-ZW" => "not assigned",
    "ZX-Z2" => "Slovenia",
    "Z3-Z5" => "Lithuania",
    "Z6-Z0" => "not assigned",
    "1A-10" => "United States",
    "2A-20" => "Canada",
    "3A-3W" => "Mexico",
    "3X-37" => "Costa Rica",
    "38-30" => "not assigned",
    "4A-40" => "United States",
    "5A-50" => "United States",
    "6A-6W" => "Australia",
    "6X-60" => "not assigned",
    "7A-7E" => "New Zealand",
    "7F-70" => "not assigned",
    "8A-8E" => "Argentina",
    "8F-8K" => "Chile",
    "8L-8R" => "Ecuador",
    "8S-8W" => "Peru",
    "8X-82" => "Venezuela",
    "83-80" => "not assigned",
    "9A-9E" => "Brazil",
    "9F-9K" => "Colombia",
    "9L-9R" => "Paraguay",
    "9S-9W" => "Uruguay",
    "9X-92" => "Trinidad & Tobago",
    "93-99" => "Brazil",
    "90-90" => "not assigned"
  ];

  private $companies = [
    '4UF' => 'Arctic Cat',
    '2BP' => 'Ski-Doo',
    'JYE' => 'Yamaha',
    'SN1' => 'Polaris'
      
  ];
  
  /**
   *
   * @var string $validRange pattern matching ranage for valid VIN # characters
   *
   */
  
  private $validRange = 'ABCDEFGHJKLMNPRSTUVWXYZ1234567890';
  
  /**
   *
   * standard constructor, you can optionally provider options and a Mono logger to use for logging.
   *
   *  @param array          $config options (optional)
   *  @param Monolog\Logger $logger you can optionally provide a logger (optional)
   *
   */
  
  public function __construct($vin=false, $logger=null) {
    
    $r1 = microtime(true);
    
    /* connect to the system log */
    
    $this->setLogger($logger);
    
    $this->unReady();
    
    /* configure */
    
    if(!$this->check($vin)) {
      
      /* its an in valid VIN number */
      
      return ;
    }
    
    /* if we get this far, everything is ok */
    
    $this->makeReady();
    
    $r2 = microtime(true);
    
    $this->debug(get_class()."() time: ".sprintf("%4.4f", ($r2-$r1)*1000)."ms.");
  }
  
  public function basicInfo($vin=false) {
    
    $vin   = $this->defaultVin($vin);
    
    $info = [
      'vin'          => $vin,
      'serial'       => $this->serialNumber($vin),
      'year'         => $this->modelYear($vin),
      'manufacturer' => $this->manufacturerCode($vin),
      'wmi'          => $this->wmiCode($vin),
      'plant'        => $this->plantCode($vin),
      'vds'          => $this->vdsCode($vin),
      'country'      => $this->country($vin)
    ];
    
    /* all done */
    
    return $info;
  }

  public function modelYear($vin=false) {
    
    $vin   = $this->defaultVin($vin);
    
    $code  = $vin[9];
    
    $val   = $this->yearChars[$code];
    
    /* 
     * the base we are relative changed in 2010, and the 7th character needs to be non-numeric 
     * for the new style VIN #s.
     * 
     */
    
    if(is_numeric($vin[6])) {
      
      if($vin[6] != 0) {
        
        $year = 1980 + ($val % 30);
        
      } else {
        
        $year = 2010 + ($val % 30);
      }
      
    } else {
      
      $year = 2010 + ($val % 30);
    }
    
    return $year;
  }
  
  public function serialNumber($vin=false) {
    
    $vin = $this->defaultVin($vin);
    
    return $this->isLess500($vin) ? substr($vin, 14, 3) : substr($vin, 11, 6);
  }
  
  public function vdsCode($vin=false) {
    
    $vin = $this->defaultVin($vin);
    
    return substr($vin, 3, 5);
  }
  
  public function plantCode($vin=false) {
    
    $vin = $this->defaultVin($vin);
    
    return substr($vin, 10, 1);
  }
  
  public function country($vin) {
    
    $vin = $this->defaultVin($vin);
    
    /* convert to country code */
    
    $code = $this->countryCode($vin);  
    
    if(!$code) {
      
      $this->setError(get_class()."::country() can not find country code of vin ($vin).");
      return false;
    }
    
    $row  = $code[0];
    $col  = strpos($this->validRange, $code[1]);
    
    /* 
     * now we have to find which range of country codes this belongs to, because each country
     * has a range of possible codes. Becuase they are organized in a table with no country 
     * spanning more than a single row
     * 
     *   see: https://en.wikibooks.org/wiki/Vehicle_Identification_Numbers_(VIN_codes)/World_Manufacturer_Identifier_(WMI)
     * 
     * We can use the second character to sear with in the range of the second character of 
     * the start/end of the range markers.  The start end are row/col in that order, so basically
     * we look up the row by the first letter, and then check that column is in the right range.
     * 
     */
    
    $keys = preg_grep('/^'.$row.'/', array_keys($this->countryCodes));
    
    /* walk through the possible rows and look for one that has this column */
    
    $found = false;
    
    foreach($keys as $kdx => $key) {
     
      /* what is the column range in this row? */
      
      $start = strpos($this->validRange, $key[1]);
      $end   = strpos($this->validRange, $key[4]);
 
      if(($col >= $start) && ($col <= $end)) {
        
        /* ah, true love */
 
        $found = $this->countryCodes[$key];
        break;
      }
    }
    
    if(!$found) {
      
      $this->setError(get_class()."::country() can not find country of vin ($vin).");
      return false;
    }
    
    /* pass it back */
    
    return $found;
  }
  
  public function countryCode($vin=false) {
    
    $vin = $this->defaultVin($vin);
    
    return substr($vin, 0, 2);
  }
  
  public function wmiCode($vin=false) {
      
    $vin = $this->defaultVin($vin);
    
    return substr($vin, 0, 3);
  }
  
  public function manufacturerCode($vin=false) {
       
    $vin  = $this->defaultVin($vin);
   
    $code = $this->isLess500($vin) ? substr($vin, 11, 3) : substr($vin, 0, 3);

    if(isset($this->companies[$code])) {
      $code = $this->companies[$code];
    }

    return $code;
  }
  
  public function isLess500($vin=false) {
    
    $vin = $this->defaultVin($vin);
    
    /* is it the right length? */
    
    if(strlen($vin) != 17) {
      
      $this->setError(get_class()."::isLess500() VIN # must have exactly 17 characters.");
      return false;
    }
    
    if($vin[2] == '9') {
      return true;
    }
    
    return false;
  }
  
  public function check($vin=false) {
    
    if($vin === false) {
      
      $vin = $this->vin;
      
      if($vin === false) {
        
        $this->setError(get_class()."::check() no VIN # given.");
        return false;
      }
    }
    
    /* make sure we have a clean VIN # string */
    
    $vin = trim(strtoupper($vin));
    $vin = preg_replace('/[\x00-\x1F\x7F]/', '', $vin);
    $vin = iconv("UTF-8", "ASCII//IGNORE", $vin);
    $vin = trim($vin,'\'"');
    
    if(empty($vin)) {
      
      $this->setError(get_class()."::check() VIN # is empty.");
      return false;
    }
    
    /* remove any invalid characters */
    
    $tmp = preg_replace('/[^'.$this->validRange.']/', '', $vin);
    
    if($tmp != $vin) {
      
      $this->setError(get_class()."::check() VIN # contains invalid characters.");
      return false;
    }
    
    /* is it the right length? */
    
    if(strlen($vin) != 17) {
      
      $this->setError(get_class()."::check() VIN # must have exactly 17 characters.");
      return false;
    }
    
    /* calculate the checksum */
    
    $chksum = $this->checksum($vin);
    
    if($chksum != $vin[8]) {
      $this->setError(get_class()."::check() VIN # checksum invalid $chksum != {$vin[8]}.");
      return false;
    }
    
    /* if we don't yet have a VIN set, and that one was valid, then we use it. */
    
    if($this->vin === false) {
      $this->vin = $vin;
    }
    
    /* all checks passed */
    
    return true;
  }
  
  private function transliterate ($c) {
    
    if(!isset($this->validChars[$c])) {
      
      $this->setError(get_class()."::transliterate() invalid character ($c)");
      return false;
    }
    
    $pos = strpos('0123456789.ABCDEFGH..JKLMN.P.R..STUVWXYZ', $c);
    
    return $pos % 10;
  }
  
  private function checksum($vin) {
    
    /*
     * NOTE: can compare our results to an independant implementation:
     * 
     *   http://www.alton-moore.net/vin_calculation.html
     *   
     */
    
    if(empty($vin)) {
      
      $this->setError(get_class()."::checksum() no VIN # provided.");
      return false;
    }
    
    if(strlen($vin) != 17) {
      
      $this->setError(get_class()."::checksum() VIN # must have exactly 17 characters.");
      return false;
    }
    
    $map     = "0123456789X";
    $weights = "8765432X098765432";
    
    $sum     = 0;
    
    for($i=0; $i<17; ++$i) {
      $sum  += $this->transliterate($vin[$i]) * strpos($map, $weights[$i]);
    }
    
    return $map[$sum % 11];
  }
  
  private function defaultVin($vin=false) {
    return $vin ? $vin : $this->vin;
  }
}

?>
