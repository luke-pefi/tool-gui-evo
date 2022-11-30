<?php

/**
 * 
 * TimeAgo - helper to do Twitter/Facebook style time formatting.
 * 
 * ref: https://stackoverflow.com/questions/6679010/converting-a-unix-time-stamp-to-twitter-facebook-style
 * 
 */

namespace util;

class TimeAgo {
  
  /**
   * 
   * ago() - format the given Unix style time as a Twitter/Facebook style time
   * 
   * @param mixed   $date        - a unix timestamp or any reasonably formatted date/time.
   * @param integer $granularity - the resolution to use
   * 
   * @return string - the formatted time
   * 
   */


  public static function ago($date, $granularity=2) {
    
    if(!is_numeric($date)) {
      $date     = strtotime($date);
    }
    
    $retval     = '';
    
    $difference = time() - $date;
    $periods    = [
      'decade'  => 315360000,
      'year'    => 31536000,
      'month'   => 2628000,
      'week'    => 604800, 
      'day'     => 86400,
      'hour'    => 3600,
      'minute'  => 60,
      'second'  => 1
    ];

    foreach ($periods as $key => $value) {
      
      if ($difference >= $value) {
        
        $time        = floor($difference/$value);
        $difference %= $value;
        $retval     .= ($retval ? ' ' : '').$time.' ';
        $retval     .= (($time > 1) ? $key.'s' : $key);
        
        $granularity--;
      }
      
      if ($granularity == '0') { 
        
        break; 
      }
    }
    
    if(empty($retval)) {
      $retval        = 'a moment';
    }
    
    return $retval; 
  }
  
}

?>