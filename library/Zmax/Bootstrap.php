<?php

/**
 * Zmax Framework
 *
 * @category   Zmax
 * @package    Zmax_Bootstrap
 * @subpackage
 * @copyright
 * @license
 * @version
 */

/**
 * Collection of static method to initialize a Zmax context
 *
 * @package Zmax_Bootstrap
 * @subpackage
 * @author Olivier Boissin & Philippe Rigaux
 *
 */

class Zmax_Bootstrap {
 	protected static $registry = null;
 	protected static $frontController = null;
 	protected static $root = null ;

 	public static function init($root, $configDir="config") {
 	  self::$root = $root ;
 	  /**
 	   * Motifidation RIGAUX le 08 09 2009: plus de Zned_Loader en 1.9
 	   * Zend_Loader::registerAutoload();
 	   */
 	   
 	  /*
 	   * Try to initialize the Zmax components
 	   * which are REALLY essential to launch the router.
 	   * The other components are initialized in the init()
 	   * method of Zmax_Controller_Action: this allows to handle
 	   * exceptions cleanly instead of printing out an ugly message.
 	   */
 	  try {
 	    self::setupRegistry();
 	    self::setupZmaxContext();
 	    self::setupConfig($configDir);
 	    self::setupEnvironment();
 	    self::setupDatabase();
 	    self::setupUser();
 	    self::setupLocale();

 	    self::setupFrontController();

 	    // Order here is important: the view must exist
 	    // when the translation is loaded.
 	    self::setupView();
 	    self::setupTranslation();

 	    self::getFrontController()->dispatch();
 	  } catch (Exception $e)
 	  {
 	    /*
 	     * An exception has been caught during Zmax initialization. What
 	     * can we do? Not much: any redirection will go through the same
 	     * process, and will raise the same error. The only solution would
 	     * be to bypass the init() method of Bootstrap.
 	     */
 	    if (is_subclass_of ($e, "Zmax_Exception")
 	    or get_class($e) == "Zmax_Exception") {
 	      $script = $e->getZmaxFile();
 	      $line = $e->getZmaxLine();
 	    }
 	    else {
 	      $script = $e->getFile();
 	      $line = $e->getLine();
 	    }

 	    $message = "<b>Caught exception in Zmax initialization.</b> ". $e->getMessage()
 	    . " <br/><b>in</b> " . $script . " <b>at line</b> " . $line . "<br/>";
 	    exit ($message);
 	  }
 	}

 	public static function getRoot() {
 	  return self::$root ;
 	}

 	public static function getRegistry() {
 	  return self::$registry ;
 	}

 	public static function getFrontController() {
 	  return self::$frontController ;
 	}

 	public static function setupRegistry() {
 	  self::$registry = new Zend_Registry(array(), ArrayObject::ARRAY_AS_PROPS);
 	  Zend_Registry::setInstance(self::$registry);
 	}

 	public static function setupZmaxContext() {
 	  $zmax_context = new Zmax_Context();
 	  $zmax_context->root_path = self::$root;

 	   
 	  if (!isset(self::$registry)) throw new Zend_Exception('setupRegistry() must be called before ' . __FUNCTION__ . '()') ;
 	  self::$registry->set("zmax_context", $zmax_context);
 	}

 	public static function setupConfig($configDir='config') {
 	  $config_dir = self::getRoot() . DIRECTORY_SEPARATOR .
 	  $configDir . DIRECTORY_SEPARATOR ;
 	   
 	  $config = new Zend_Config_Ini($config_dir . 'environment.ini', null, array('allowModifications' => true));

 	  if (!isset($config))
 	  throw new Zend_Exception('Cannot open mandatory \'' . $config_dir . 'environment.ini\' config file') ;
 	   
 	  $environment = $config->environment ;
 	   
 	  if (!isset($environment) || strlen($environment) == 0)
 	  throw new Zend_Exception('Cannot find valid \'environment\' parameter in mandatory \'' . $config_dir . 'environment.ini\' config file') ;

 	  if ($handle = opendir($config_dir)) {
 	    while (false !== ($file = readdir($handle))) {
 	      if (isset($file) && strlen($file) > 0 && $file != 'environment.ini' && substr($file, -4) == '.ini') {	// lowercase only
 	        $config = $config->merge(new Zend_Config_Ini($config_dir . $file, $environment, array('allowModifications' => true))) ;
 	      }
    		}
 	  }
 	   
 	  // set some computed values
 	  $config->app->root = self::getRoot() ;


 	  // add the config object to zmax_context
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  if (!isset($zmax_context)) throw new Zend_Exception('setupZmaxContext() must be called before ' . __FUNCTION__ . '()') ;
 	  $zmax_context->config = $config;

 	  if (isSet($config->app->document_root)) {
 	    $zmax_context->document_root_path = self::$root . $zmax_context->config->app->document_root;
 	  }
 	  else
 	  $zmax_context->document_root_path = self::$root . "www";

 	  //echo "Document root : " .  $zmax_context->document_root_path ;

 	}

 	public static function setupEnvironment() {
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  $config = $zmax_context->config ;
 	  if (!isset($config)) throw new Zend_Exception('setupConfig() must be called before ' . __FUNCTION__ . '()') ;
 	   
 	  date_default_timezone_set($config->app->default_timezone);
 	  ini_set('display_errors', $config->app->display_errors);
 	}

  /**
   * Get the locale information. The ule is to take the $_GET parameter
   * 'zmax_display_lang' if it exists; else one takes the cookie with
   * the same name, and finally one considers the browser info.
   *
   */

 	public static function setupLocale() {
 	  $zmax_context = self::getRegistry()->get("zmax_context");

 	  if (!isset($zmax_context))
 	  throw new Zmax_Exception('setupZmaxContext() must be called before ' . __FUNCTION__ . '()') ;
 	   
 	  // Get the locale. If possible use the lang of the user. Else get the lang from the browser.
 	  if (isset($_GET['zmax_display_lang']) && $_GET['zmax_display_lang'] != ""){
 	    $end_time = time() + 3600 * 24 * $zmax_context->config->app->cookie->lifetime;
 	    setCookie("zmax_display_lang", $_GET['zmax_display_lang'], $end_time,
 	    $zmax_context->config->app->base_url . DIRECTORY_SEPARATOR);
 	    $locale = new Zmax_Locale($_GET['zmax_display_lang']);
 	  }
 	  elseif(isset($_COOKIE['zmax_display_lang'])){
 	    $locale = new Zmax_Locale($_COOKIE['zmax_display_lang']);
 	  }
 	  elseif(isset($zmax_context->locale)){
 	    $locale = $zmax_context->locale;
 	  }
 	  else {
 	    // in version 1.6 the Zend_Locale does not exist
 	    // it appears in 1.7 version during november
 	    // $locale = new Zmax_Locale();
 	     
 	    // Until Version 1.7 is available
 	    if (isSet ($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
 	      $browser_lang = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
 	    }
 	    // Use English, unless the default_lang is set
 	    if (isSet($zmax_context->config->app->default_lang)) {
	      $locale = new Zmax_Locale($zmax_context->config->app->default_lang);
 	    }
 	    else {
 	      $locale = new Zmax_Locale("en-en");
 	    }
 	  }
 	  $zmax_context->locale = $locale ;
 	}

 	public static function setupDatabase() {
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  $config = $zmax_context->config ;

 	  if (!isset($config))
 	  throw new Zmax_Exception('setupConfig() must be called before ' . __FUNCTION__ . '()') ;

 	  if (isset($_GET['zmax_display_lang']) && $_GET['zmax_display_lang'] != ""){
 	    $locale = new Zmax_Locale($_GET['zmax_display_lang']);
 	  }
 	  elseif(isset($_COOKIE['zmax_display_lang'])){
 	    $locale = new Zmax_Locale($_COOKIE['zmax_display_lang']);
 	  }
 	  elseif(isset($zmax_context->locale)){
 	    $locale = $zmax_context->locale;
 	  }
 	  else {
 	    $locale = new Zmax_Locale();
 	  }

 	  // Only try to access the DB if the config. requires it
 	  if ($config->app->use_database) {
 	    $db = new Zmax_Db_Adapter($config->db);

 	    //$db->query("SET NAMES 'utf8'");
 	    Zend_Db_Table::setDefaultAdapter($db);
 	    $zmax_context->db = $db ;
 	  }
 	  else
 	  $zmax_context->db = null;
 	}

 	public static function setupFrontController(){
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  $config = $zmax_context->config ;
 	  $app_dir = self::getRoot() . 'application' . DIRECTORY_SEPARATOR ;

 	  self::$frontController = Zmax_Controller_Front::getInstance();

 	  // Keep the front controller from sending exceptions
 	  self::$frontController->throwExceptions(false);

 	  // This tells where the controllers code can be found
 	  self::$frontController->setControllerDirectory(
 	  array(
          'default' => $app_dir . 'controllers' . DIRECTORY_SEPARATOR,
          'admin' => $app_dir . 'admin' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR
 	  )
 	  );

 	  // Set the error handler
 	  // PR: does not work, because it shows a warning when used
 	  // with Zend View and Zend_Layout ....
 	  if ($config->view->zmax_view_system == "phplib")
 	  set_error_handler (array(self::$frontController, "errorHandler"));
 	   
 	  // Change the error handler plugin, to forward exceptions
 	  // to the 'stalled' action of the index controller

 	  $eh = new Zend_Controller_Plugin_ErrorHandler();
 	  $eh->setErrorHandlerController('index')
 	  ->setErrorHandlerAction('stalled');
 	  self::$frontController->registerPlugin($eh);

 	  // Remove all automatic escaping
 	  self::$frontController->normalizeHTTP();

 	  // Set the base url
 	  self::$frontController->setBaseUrl();
 	}

 	public static function setupView() {
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  $config = $zmax_context->config ;
 	  if (!isset($config))
 	  throw new Zend_Exception('setupConfig() must be called before '
 	  . __FUNCTION__ . '()') ;
 	   
 	  if ($config->view->zmax_view_system == "phplib") {
 	    // disable ViewRenderer helper (enabled by default)
 	    self::getFrontController()->setParam('noViewRendered', true);

 	    // initiate Phplib template engine
 	    $zmax_context->view = new Zmax_View_Phplib();


      // First, load the layout template
      if (isSet ($config->app->layout)) {
        // The layout should be found in /themes/<name>.xml
        $layoutPath = 	  $config->app->root . "themes" . DIRECTORY_SEPARATOR .
        $config->app->layout . ".xml";
        $zmax_context->view->setFile ("layout", $layoutPath);
      }

      // First, take the layout in the default module
      $default_path = $zmax_context->root_path . 'application'
      . DIRECTORY_SEPARATOR . 'views'
      . DIRECTORY_SEPARATOR . 'templates';
      $zmax_context->view->setRootPath ($default_path);

      // Caution: if the layout is undefined in the configuration, take it from the templates
      if (!isSet ($config->app->layout)) {
        $zmax_context->view->setFile ("layout", "layout.xml");
      }
 	  }
 	  else {
 	    // Use the standard Zend View & Zend_Layout
 	    Zend_Layout::startMvc(
 	    array(
				'layoutPath' => self::getRoot() . 'application' 
				. DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts',
 	    	       'mvcSuccessfulActionOnly' => true
				)
				);
 	  }
 	}

 	/**
   * check whether a user is connected, and if yes retrieves the user's row
   * and assigns it to the context
   *
   */

 	public static function setupUser() {
 	  $user = null;
 	  $zmax_context = self::getRegistry()->get("zmax_context");
 	  if (!isset($zmax_context))
 	  throw new Zend_Exception('setupZmaxContext() must be called before ' . __FUNCTION__ . '()') ;
 	  $config = $zmax_context->config ;
 	  if (!isset($config))
 	  throw new Zend_Exception('setupConfig() must be called before '
 	  . __FUNCTION__ . '()') ;

 	  // Get a reference to the Singleton instance of Zmax_Auth
 	  $auth = Zend_Auth::getInstance();

    // Check whether an identity exists
    if ($auth->hasIdentity()) {
      $zmax_context->is_authenticated = true;
      // Identity exists; get it
      $identity = $auth->getIdentity() ;
       
      if ($config->app->auth->method == "cas")
      $user_id = $identity['user_id'] ; // Returned by CAS
      else
      $user_id = $identity['username'] ; // Returned by DIGEST
       
      // Create an empty object
      $user_table = new Zmax_Model_User ();
       
      // Try to find the user with the source configured in the app ini file
      switch($zmax_context->config->app->user->source){
        case 'ldap':
          $ldap = new Zmax_Ldap_Adapter($zmax_context->config->sector->ldap) ;
          Zmax_Ldap_Gateway_Abstract::setDefaultAdapter($ldap);
           
          $ldap_user = new Zmax_Model_LdapUser;
          $res_ldap_user = $ldap_user->findById($user_id);
          $tab_user = $ldap_user->getAllEntries($res_ldap_user);
           
          if(isset($tab_user) && count($tab_user)>0){
            $tmp['user_lname'] = strtoupper($tab_user[0]['lastname']);
            $tmp['user_fname'] = $tab_user[0]['firstname'];
            $tmp['user_email'] = strtolower($tab_user[0]['email']);
            $user = $user_table->initFromArray($tmp);
          }
          break;
           
        default: // by default search in the database
          if ($zmax_context->config->app->use_database) {
            $user = $user_table->find($user_id)->current();
          }
          break;
      }


      if (is_object($user)) {
        $zmax_context->user = $user;
      }
      else {
        $zmax_context->user = null ; // echo "No user found";
      }
    }
    else {
      $zmax_context->is_authenticated = false;
    }
 	}

 	public static function setupTranslation() {
 	  $zmax_context = self::getRegistry()->get("zmax_context");

 	  // Load the translations. Note: the default namespace and the
 	  // Zmax namespace are always loaded
 	  // Only try to access the DB if the config. requires it
 	  $ns_to_load = array("zmax", "db", "form");
 	  $zmax_context->texts = new Zmax_Translate('Zmax_Translate_Adapter_Db',
 	  $zmax_context->db, $zmax_context->locale,
 	  array ("namespaces" => $ns_to_load));

 	  // Test the addTranslation method
 	  /*
 	   $ns_to_load = array("db"); // Define the list of namespaces to load
 	   $texts->addTranslation ($zmax_context->db, $zmax_context->locale,
 	   array ("namespaces" => $ns_to_load));
 	   */
 	}

}