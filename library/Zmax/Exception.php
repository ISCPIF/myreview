<?php

/**
 * Zmax Framework
 *
 *
 * @category   Zmax
 * @package    Zmax_Exception
 * @copyright
 * @license
 * @version
 */


/**
 * Zmax exceptions
 *
 * All exceptions classes derive from this class

 * @package    Zmax_Exception
 */
class Zmax_Exception extends Zend_Exception {

  protected $line, $script, $zmax_error=false;

  /**
   * Assign the line where the exeption occurred
   *
   * @param integer the line number
   */
  public function setLine($_line) {
    $this->line = $_line;
  }
  /**
   * Assign the script where the exeption occurred
   *
   * @param string the script name
   */
  public function setScript($_script) {
    $this->script = $_script;
  }

  /**
   * Tells that an exception is actually a PHP error. Zmax
   * aims at handling PHP as specific Zmax exceptions. See the
   * erroHandlet method of Zmax_Front, which redirects PHP errors
   * as Zmax_Exception, setting this flag to true.
   *
   * @param boolean true if it is a PHP error, false otherwise
   */
  public function setZmaxError($_bool) {
    $this->zmax_error = $_bool;
  }

  /**
   * Get the line where the exeption occurred
   *
   * @retun integer the line number
   */
  public function getZmaxLine() {
    if ($this->zmax_error)
    return $this->line;  // Return the line where the error occurred
    else
    return parent::getLine(); // Return the line where the exception was raised
  }

  /**
   * Get the script where the exeption occurred
   *
   * @retun string the script name
   */
  public function getZmaxFile() {
    if ($this->zmax_error)
    return $this->script;
    else return parent::getFile();
  }
}