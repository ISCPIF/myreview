<?php

/**
 * @category Zmax
 * @package    Zmax_Controller
 * @subpackage Action_Protected
 * @copyright
 * @license
 * @version
 */

/**
 * Inclusion of CAS functions
 */
// require_once('CAS/CAS.php');

/**
 * Ensure that all actions in the controller are protected by the authentication mechanism
 *
 * The class is equipped with an initilization mechanism which ensures
 * that none of its actions can be accessed by a non-authenticated user.
 *
 * @author Philippe Rigaux
 * @package    Zmax_Controller
 * @subpackage Action_Protected
 * @todo This class needs to be fully documented. Also:
 *
 */

class Zmax_Controller_Action_Protected extends Zmax_Controller_Action
{

  protected $auth_method; // The authentication mechanism: cas or digest

  protected $identity; // The id of the authenticated user

  protected $zmax_context; // The Zmax context
  protected $auth; // The Authentication object
  protected $sgi;   // SG identity
  protected $auth_messages  = ""; // Authentication messages
  protected $adapter;   // The Authentication adapter

  /**
   * The preDispatch method is called prior to any action.
   * This is the place to check whether an id has been validated
   *
   * @return void
   */

  function preDispatch ()
  {
    // How to produce a line in the passwords file
    // $user_code = "aidop:aidop:zmax";
    //  echo "Authenticate. MD5('$user_code') = " . md5($user_code) . "<br/>";

    // Get the authentication method
    $this->auth_method = $this->zmax_context->config->app->auth->method;
    // Instantiate an adapter
    switch ($this->auth_method) {
      case "cas":
        $cas_url = $this->zmax_context->config->app->auth->cas_url;
        $this->adapter = new Zmax_Auth_Adapter_CAS($cas_url, $this->getRequest());
        break;
         
      default: // Use the Digest adapter
        $filename = ".." . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "passwords";
        $realm = "zmax";
        $username = $this->getRequest()->getParam("zmax_login");
        $password = $this->getRequest()->getParam("zmax_password");
        $this->adapter = new Zend_Auth_Adapter_Digest($filename, $realm, $username, $password);
    }

    // Get a reference to the Singleton instance of Zmax_Auth
    $this->auth = Zend_Auth::getInstance();

    // Check whether an identity exists
    $is_authenticated = false;
    if ($this->auth->hasIdentity()) {
      $is_authenticated = true;
    }
    else if ($this->authenticate()) {
      $is_authenticated = true;
    }

    if (!$is_authenticated) {
      // Authentication failed. Ask the user id by forwarding to the login action.
      if ($this->getRequest()->getActionName() != "login"
      and  $this->getRequest()->getActionName() != "logout" )
      {
        $params = array_merge ( array ("req_module" => $this->getRequest()->getModuleName(),
        	  "req_controller" => $this->getRequest()->getControllerName(),
     		  "req_action" => $this->getRequest()->getActionName()),
        $this->getRequest()->getParams());
        $this->_forward("login", $this->getRequest()->getControllerName(),
        $this->getRequest()->getModuleName(), $params);
      }
    }
  }

  /**
   * Authenticate a user
   *
   *
   */

  private function authenticate()
  {
    // Check the authentication method
    switch ($this->auth_method) {
      case "cas":
        // Attempt authentication, saving the result
        $result = $this->auth->authenticate($this->adapter);
        if ($result->isValid()){
          Zmax_Bootstrap::setupUser();  // Gets the user
          //Zmax_Bootstrap::setupAuthentication();
        }
        return $result->isValid();
        break;

      default:   // Default authentication: digest
        if ($this->getRequest()->getParam("submit_login")) {
          // Attempt authentication, saving the result
          $result = $this->auth->authenticate($this->adapter);
          if (!$result->isValid()) {
            // Authentication failed; print the reasons why
            $this->auth_messages .= "<b>Error during authentication :</b> ";
            foreach ($result->getMessages() as $message) {
              $this->auth_messages .= "$message<br/>\n";
            }
            return false;
          } else {
            Zmax_Bootstrap::setupUser();  // Gets the user
            return true;
          }
        }
    }
    return false;
  }


  /**
   * Ask the login of the user authentication
   *
   *
   */

  function loginAction()
  {
    session_destroy();

    // Check that the call to this action contains both the initial
    // controller and action names
    $req_action = $this->getRequest()->getParam("req_action");
    $req_controller = $this->getRequest()->getParam("req_controller");
    $req_module = $this->getRequest()->getParam("req_module");

    // Default: send back the authenticated user to the home page
    if (empty($req_module)) $req_module = "default";
    if (empty($req_controller)) {$req_controller = $req_action = "index";}
    else if (empty($req_action)) $req_action = "index";

    // Check the authentication method
    switch ($this->auth_method) {
      case "cas":
        /* echo "Login URL = " . $this->adapter->getLoginUrl($req_action, $req_controller,
         $req_module,  $this->getRequest()->getParams()) . "<br/>"; */
        $this->_redirect($this->adapter->getLoginUrl($req_action, $req_controller,
        $req_module,  $this->getRequest()->getParams()));
        break;

      default:   // Default authentication: digest
        $this->view->content =  $this->auth_messages .
        self::loginForm($req_controller, $req_action, $this->getRequest());
        $this->view->content =  $this->view->content;
    }
    echo $this->view->render("layout");
  }

  /**
   * Logout action.
   *
   *
   * @author Philippe Rigaux
   * @todo This class needs to be fully documented. Also:
   *
   */

  function logoutAction ()
  {
    // Get a reference to the Singleton instance of Zend_Auth
    $auth = Zend_Auth::getInstance();
    $auth->clearIdentity();

    switch  ($this->auth_method) {
      case "cas":
        // Call the CAS logout service
        session_destroy();

        $this->_redirect($this->adapter->getLogoutUrl());
        break;

      default:
        // After logout, forward to the home page
        $this->_forward("index", "index");
    }
  }


  /**
   * Static method that returns a login form
   *
   */

  public static function loginForm($req_controller, $req_action, $request)
  {
    // Get the current action URL (use the config to obtain the base URL)
    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();
    $zmax_context = $registry->get("zmax_context");
    $module_name = $request->getModuleName();
    if (!empty($module_name))
    $module =  $request->getModuleName() . "/";
    else
    $module = "";
     
    $url = $zmax_context->config->app->base_url . "/" . $module
    . $request->getControllerName() . "/" . $request->getActionName() . "?1=1";

    // The form always refers to the current controller
    // and to the current action
    $current_action = $request->getActionName();
    $form = new Zmax_HTML_Form ($url);
    $form->champCache ("zmax_req_controller", $req_controller);
    $form->champCache ("zmax_req_action", $req_action);
    $form->debutTable();
    $form->champTexte ($zmax_context->texts->zmax->login, "zmax_login", "", 20);
    $form->champMotDePasse ($zmax_context->texts->zmax->password, "zmax_password",
                  "", 20);
    $form->champValider ($zmax_context->texts->zmax->submit, "submit_login");
    $form->finTable();
    return $form->fin();
  }
}

