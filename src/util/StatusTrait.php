<?php

/**
 * 
 * StatusTrait - provides basic status and error state ability.
 * 
 */
 
namespace util;
 
trait StatusTrait {

  /**
   * 
   * @var boolean $traitReady - object status
   * 
   */
  
  private $traitReady   = false;
  
  /**
   *
   * @var string $traitMessage - current error message
   *
   */
  
  private $traitMessage = '';
  
  /**
   * 
   * setError() - set the new current error message
   * 
   * @param string $msg - the new current error message
   * @return boolean - exactly true
   * 
   */
  
  public function setError($msg='') {
    
    $this->traitMessage = $msg;
    
    if(method_exists($this, "isLoggingTrait")) {
      $this->error($msg);
    }
    
    return true;
  }
  
  /**
   * 
   * getError() - fetch the current error message 
   * 
   * @return string - the current error message
   * 
   */
  
  public function getError() {
    
    return $this->traitMessage;
  }
  
  /**
   * 
   * isReady() - test to see if this object is ready.
   * 
   * @return boolean - true if object is ready
   * 
   */
  
  public function isReady() {
    
    return $this->traitReady;
  }
  
  /**
   * 
   * makeReady() - force this object ot be ready.
   * 
   * return boolean - exactly true.
   * 
   */
  
  public function makeReady() {
    
    $this->traitReady = true;
    
    return true;
  }
  
  /**
   * 
   * unReady() - force this object to not be ready.
   * 
   * @return boolean - exactly true
   * 
   */
  
  public function unReady() {
    
    $this->traitReady = false;
    
    return true;
  }
}

?>