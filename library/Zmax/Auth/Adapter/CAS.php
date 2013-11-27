<?php
/**
 *
 * Zmax Framework
 *
 * @category   Zmax
 * @package    Zmax_Auth
 * @subpackage CAS
 * @copyright
 * @license
 * @version
 */

 /**
  * We need the CAS functions to run this class
  */
require_once('CAS/CAS.php');

/**
 * Zmax extension of the Zend_Auth_Adapter_Digest class
 * @package    Zmax_Auth
 * @subpackage CAS
 * @author Philippe Rigaux
 */

class Zmax_Auth_Adapter_CAS implements Zend_Auth_Adapter_Interface
{
  /**
   * The CAS server that provides the auth. service
   *
   * @var string
   */
  protected $cas_url;

  /**
   * The base URL of the appl.
   *
   * @var string
   */
  protected $base_url;

  protected $request;

  /**
   * The module and controller
   *
   * @var string
   */
  protected $module, $controller;

  /**
   * Sets the CAS parameters for authentication
   *
   * @return void
   */
  public function __construct($cas_url, $request)
  {
    $this->request = $request;

    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();

    // Get the Zmax context
    $zmax_context = $registry->get("zmax_context");

    // Store the URL of the CAS server and the base URL
    $this->cas_url = $cas_url;
    $this->base_url = $zmax_context->config->app->base_url;

    // Compute the URL of the current action
    $this->controller = $request->getControllerName();
    $this->module = $request->getModuleName();
    if (empty($this->module)) $this->module = "default";

  }

  /**
   * Performs an authentication attempt
   *
   * @throws Zend_Auth_Adapter_Exception If authentication cannot be performed
   * @return Zend_Auth_Result
   */
  public function authenticate()
  {
    $result = array('code'  => Zend_Auth_Result::FAILURE,
        'identity' => array(
        		'user_id' => 0,
    ),
            'messages' => array()
    );

    // CAS authentication
    $ticket = $this->request->getParam("ticket");
    if (!empty($ticket)) {
      if($id = $this->auth_cas_get_name()) {
        $result['identity']['user_id'] = $id;
        $result['code'] = Zend_Auth_Result::SUCCESS;
      }
      else { // Invalid ticket: send back to CAS
        $result['code'] = Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID;
        $result['messages'][] = 'Invalid ticket';
      }
    }
    return new Zend_Auth_Result($result['code'], $result['identity'], $result['messages']);
  }

  /**
   * get the service
   *
   * @return string
   */
  public function getService($action, $controller, $module, $params=array())
  {
    // Remove from the params the Zmax-specific parameters
    $my_params = array_diff_key ($params,
    array("action" => 1, "controller" => 1, "module" => 1,
                                    "req_action" => 1, 
									"req_controller" => 1,
                                    "req_module" => 1));
    // Create the URL with params (if any)
    $url_params =  "";
    foreach ($my_params as $p_name => $p_value)  $url_params .= "/$p_name/$p_value";
    // Create the service URL
    $my_service = "http://" . $_SERVER["HTTP_HOST"] . $this->base_url .
      	"/$module/$controller/$action$url_params";

    return urlencode($my_service);
  }

  /**
   * get the login URL
   *
   * @param the action of the current controller/module protected by authentication
   * @return string
   */
  public function getLoginUrl($action, $controller, $module, $params=array())
  {
    return $this->cas_url . "/login?service=" .
    $this->getService($action, $controller, $module, $params);
  }

  /**
   * get the logout URL
   *
   * @param the action of the current controller/module protected by authentication
   * @return string
   */
  public function getLogoutUrl($action="index", $controller="index", $module="default")
  {
    return $this->cas_url . "/logout?service=" . $this->getService($action, $controller, $module);
  }

  /**
   * Call to the CAS layer.
   * @todo Try to understand how it REALLY works, and clean it
   */
  function auth_cas_init() {
     
    static $s_initialized=false;

    if (! $s_initialized ) {
      // phpCAS::setDebug();
      ## These should be set in config_inc.php
      ## $g_login_method = CAS_AUTH;

      $t_server_version = '2.0';
      $t_server_cas_server = 'sgsilxssop.saint-gobain.com';
      $t_server_port = 443;
      $t_server_uri = '/cas';
      $t_start_session = (boolean)FALSE;

      phpCAS::client($t_server_version, $t_server_cas_server, $t_server_port, $t_server_uri, $t_start_session);
      phpCAS::setEncodingUrl(true);
      $s_initialized = true;
    }

  }


  /**
   * Fetches the user's CAS name, authenticating if needed
  *	Can translate name through LDAP
   */
  
  function auth_cas_get_name()
  {
    # Get CAS username from phpCAS
    $this->auth_cas_init();
    // echo "forceAuthentication<br/>";
    phpCAS::forceAuthentication();
    //  echo "getUser<br/>";
    $t_cas_id =  phpCAS::getUser();

    # If needed, translate the CAS username through LDAP
    $sgi = strtoupper($t_cas_id);
    return $sgi;
  }

  // End of class
}
