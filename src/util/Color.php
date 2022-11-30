<?php

/**
 * 
 * Color - helper class for working with HTML colors 
 * 
 */

namespace util;

class Color {
  
  /**
   *
   * ryg() - convert a percent 0%-100% to a color, green (0%), yellow (50%), or red (100%), or some mix depending on the percentage.
   *
   * @param foat $percent - percentage from 0.0 to 100.0
   *
   * @return string an rgb() CSS string that can be used in styling the percentage failed indicator.
   *
   */

  public static function ryg($percent) {

    $color   = "#ffffff";

    $power   = $percent / 100.0;

    if($power<0) {
      $power = 0.0;
    } else if($power>1.0) {
      $power = 1.0;
    }

    $blue = 0;

    if($power < 0.5) {
      $green = 1.0;
      $red   = 2 * $power;
    } else {
      $red   = 1.0;
      $green = 1.0 - 2 * ($power-0.5);
    }

    $green   = (int)($green * 255);
    $red     = (int)($red * 255);

    $color   = "rgb($red,$green,$blue)";

    return $color;  
  }
   
}

?>