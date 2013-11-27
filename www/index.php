<?php
// Always report all errors
error_reporting(E_ALL | ~E_STRICT);

// Directory setup and class loading
$root = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR ;

// Define the config directory (from the root path)
$configDir = "config" . DIRECTORY_SEPARATOR  . "default";

set_include_path('.' .
PATH_SEPARATOR . $root . 'library' . DIRECTORY_SEPARATOR .
PATH_SEPARATOR . $root . 'library' . DIRECTORY_SEPARATOR . 'ZF-1.10' . DIRECTORY_SEPARATOR .
PATH_SEPARATOR . ".." . DIRECTORY_SEPARATOR .  ".." . DIRECTORY_SEPARATOR  
             . "ZF-1.10" . DIRECTORY_SEPARATOR .
PATH_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR .
PATH_SEPARATOR . $root . 'application' . DIRECTORY_SEPARATOR .
                       'models' . DIRECTORY_SEPARATOR .
PATH_SEPARATOR . get_include_path()
);

// Always use the automatic loader
require_once 'Zend/Loader/Autoloader.php';
//Zend_Loader::registerAutoload();
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Zmax_');

// Load the MyReview V1 functions. Get rid of it when
// everything has been correctly rewritten
require_once("myreview_v1/functions.php");

// Load the controller that makes a few initialisations
// so that V1 functions remain compatible with V2 functions
require_once("myreview_v1/Constant.php");
require_once("myreview_v1/Myreview_Controller_Action.php");
require_once("myreview_v1/Myreview_Controller_Action_Auth.php");
require_once ("myreview_v1/BD.class.php");
require_once ("myreview_v1/Codes.class.php");
require_once ("myreview_v1/Tableau.class.php");
require_once ("myreview_v1/Formulaire.class.php");
require_once ("myreview_v1/IhmBD.class.php");

// Get the codes of the application (V1).
$CODES = new Codes("Codes.xml");

// This system might potentially reach the memory
// limit of PHP. Check that this does not happen
if (function_exists ("memory_get_usage")) {
  // The following instruction can raise the memory limit
  ini_set ("memory_limit", "100M");
  ini_set ("max_execution_time", "300"); // 5 mns
}

if (isSet($_GET["zmax_init_failed"])) {
  // The Boostrap initialization failed.
  Zmax_Bootstrap::failure($root);
}
else try {
  // Init and run the Zmax application
  Zmax_Bootstrap::init($root, $configDir);
}
catch (Zmax_Exception $e) {
  echo "Unable to initialize the MyReview environment.<br/>"
  . "<b>Message:</b> " . $e->getMessage()
  . " in " . $e->getFile() . " at line " . $e->getLine() . "<br/>";
}