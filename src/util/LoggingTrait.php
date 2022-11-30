<?php

/**
 * 
 * LoggingTrait - E-Z logging methods that can be incorporated into all objects (like status).
 * Normally this is basically a way of incorporating Monolog\Logger into all our objects.
 * 
 */
 
namespace util;

use Monolog\Logger;

trait LoggingTrait {
  
  /**
   * 
   * @var Monolog\Logger $traitLog - the current logger (if there is one)
   * 
   */
  
  protected $traitLog = null;
  
  /**
   * 
   * setLogger() - set the current logger.
   * 
   * @param Monolog\Logger $logger the logger you want to use with this object.
   * 
   * @return boolean - exactly true.
   * 
   */
  
  public function setLogger($logger=null) {
    
    $this->traitLog = $logger;
    
    return true;
  }
  
  /**
   * 
   * hasLogger() - test if we have a logger for this object.
   * 
   * @return boolean - exactly true if there is a logger.
   * 
   */
  
  public function hasLogger() {
    
    if($this->traitLog) {
      return true;
    }
    
    return false;
  }
  
  /**
   *
   * isLoggingTrait() - test if this object includes logging traits.
   *
   * @return boolean - exactly true.
   *
   */
  
  public function isLoggingTrait() {
    
    return true;
  }
  
  /**
   *
   * getLogger() - fetch the current logger for this object if there is one.
   *
   * @return mixed - exactly null if no logger, otherwise (typically) a Monlog\Logger
   *
   */
  
  public function getLogger() {
    
    return $this->traitLog;
  }
  
  
  /**
   *
   * emergency() - "marines, we are leaving!"
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function emergency($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->emergency($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * alert() - you really shouldn't ignore this
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function alert($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->alert($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * critical() - something is really wrong, but we aren't panicing yet.
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function critical($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->critical($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * error() - general and non-fatal errors.
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function error($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->error($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * warnings() - low level errors
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function warning($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->warning($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * info() - normal but significant events
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function notice($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->notice($msg, $context);
    }
    
    return true;
  }
  
  /**
   *
   * info() - interesting events
   *
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   *
   * @return boolean - exactly true
   *
   */
  
  public function info($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->info($msg, $context);
    }
    
    return true;
  }
  
  /**
   * 
   * debug() - detailed messages for developers
   * 
   * @param string $msg     - the text to log
   * @param array  $context - variables to log at the same time
   * 
   * @return boolean - exactly true
   * 
   */
  
  public function debug($msg, array $context=[]) {
    
    if($this->traitLog) {
      $this->traitLog->debug($msg, $context);
    }
    
    return true;
  }
  
}

?>