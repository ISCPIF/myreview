<?php

/**
 * @category Zmax
 * @package    Zmax_Controller
 * @subpackage Front
 * @copyright
 * @license
 * @version
 */


/**
  * Overload of the Zend_Controller_Front class, provide
  * a few utilitary functions used to initialize an application
  * 
  * @package Zmax_Controller
  * @subpackage Front
  * @author Philippe Rigaux
  * @todo Document the methods
  */

class Zmax_Controller_Front extends Zend_Controller_Front
{
  /* Overload of the static getInstance method */
  public static function getInstance()
  {
    if (null === self::$_instance) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  /**
   * Overload the setBaseURL method.
  */ 
  public function setBaseURL($base = null) {
    // First get the config object
    $registry = Zmax_Bootstrap::getRegistry();
    $zmax_context = $registry->get("zmax_context");
    $config = $zmax_context->config;
     
    // Remove the trailing '/'
    parent::setBaseUrl($this->cleanUrl($config->app->base_url));
  }

  /**
   * 
   *  Make the appl. independent from the magic_quote_gpc setting
   * 
   */
  public function normalizeHTTP()
  {
    if (get_magic_quotes_gpc())
    {
      $this->normalizeArray($_POST);
      $this->normalizeArray($_GET);
      $this->normalizeArray($_REQUEST);
    }
  }

  private function normalizeArray(&$arr)
  {
    // The following function strips slashes from
    // an HTTP input. Note: parameter is passed by reference
    // Scan the array
    foreach ($arr as $key => $value)
    {
      if (!is_array($value)) // Let's go
      $arr[$key] = stripSlashes($value);
      else  // Recursive call.
      $this->normalizeArray($arr[$key]);
    }
    reset($arr);
    return $arr;
  }

  private function cleanUrl($url){
    if(substr($url, -1) == "/"){
      $url = substr($url, 0, strlen($url)-1);
    }
    return $url;
  }

  /**
   * This is the function used to handle PHP errors
   * 
   * @param int error level (according to PHP pre-defined levels)
   * @param string error message
   * @param string the script name
   * @param in the line number
   */
  
  public function errorHandler ($level, $message, $script, $line, $context=array())
  {
    
 /*	echo "<br>".$message;
    echo "<br>".$script;
    echo "<br>".$line;
    echo "<br>";
    print_r($context);
    exit;
   */ 
  	// Check the error level
    switch ($level)
    {
      // PHP would never transmit these errors
      case E_ERROR:
      case E_PARSE:
      case E_CORE_ERROR:
      case E_CORE_WARNING:
      case E_COMPILE_ERROR:
      case E_COMPILE_WARNING:
        echo "Unexcepted error in Zmax_Exception::handleError";
        exit;

      case E_WARNING:
        $typeErreur = "Warning";
        break;

      case E_NOTICE:
        $typeErreur = "Notice";
        break;

      case E_STRICT:
        $typeErreur = "PHP 5 strict notice";
        break;

      case E_USER_ERROR:
        $typeErreur = "Appl. error";
        break;

      case E_USER_WARNING:
        $typeErreur = "Appl. warning";
        break;

      case E_USER_NOTICE:
        $typeErreur = "Appl. notice";
        break;

      default:
        $typeErreur = "Unknown error type";
    }

   
    // Here: throw an exception. The message will be handled by the 
    // exeption mechanism
    $message =  $message . " [$typeErreur] ";
    // echo $script . " " . $line;
    
    if ($level != E_STRICT) {
      $e= new Zmax_Exception ($message);
      $e->setLine($line);
      $e->setScript($script);
      $e->setZmaxError(true); // Tell that the exception is in fact an error
      // echo "Error in script $script at line $line: $message<br/>";
      throw $e;
    }
  }
  
}