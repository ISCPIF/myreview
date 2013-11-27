<?php

/**
 * @category Zmax
 * @package    Zmax_Controller
 * @subpackage Action
 * @copyright
 * @license
 * @version
 */


/**
 * Overload of the Zend_Controller_Action class, mainly to initialize
 * the Zmax context
 *
 * @package Zmax_Controller
 * @subpackage Action
 * @author Philippe Rigaux
 */

class Zmax_Controller_Action extends Zend_Controller_Action
{
  /**
   * essentially, put the context as a property of the controller
   *
   */

  function init()
  {
    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();

    // Get the Zmax context
    $this->zmax_context = $registry->get("zmax_context");
    $user = $this->zmax_context->user;
    $config = $this->zmax_context->config;

    // Override the view attribute of the Controller.
    if ($config->view->zmax_view_system == "phplib") {
      // Disable the automatic view rendering. I do not know
      // why the 'setParam' of the front controller does not work...

      $this->view = $this->zmax_context->view;

      // Now, look at the module name. If it is not default, then
      // load the templates from application/<module>/views/templates
      $module_name = $this->getRequest()->getModuleName();
      if ($module_name != "default") {
        $this->view->setRootPath ($this->zmax_context->root_path . 'application'
        . DIRECTORY_SEPARATOR . $module_name
        . DIRECTORY_SEPARATOR . 'views'
        . DIRECTORY_SEPARATOR . 'templates');
      }

      $this->view->base_url = $config->app->base_url;
       $this->view->uri = base64_encode($_SERVER['REQUEST_URI']);
       
      // Take the templates in the specific subdirectory named after the controller
      $this->view->addBasePath($this->getRequest()->getControllerName());

     	$this->_helper->viewRenderer->setNoRender(true);
    }
    else{
      $this->view->zmax_context = $this->zmax_context;
      $this->view->texts = $this->zmax_context->texts;
      $this->view->base_url = $config->app->base_url;
        
      $Zmax_lang = new Zmax_Model_Lang();
      $this->view->lang_list = $Zmax_lang->getList();
    }

    /*
     * Create a pre-defined list of view elements, potentially useful
     * in every application
     */
    $this->view->breadcrumb = $this->createBreadcrumb();
    $this->view->module_name = $this->getRequest()->getModuleName();
    $this->view->controller_name = $this->getRequest()->getControllerName();
    $this->view->action_name = $this->getRequest()->getActionName();

    // Create the language list (for changing the lang.)
    $this->view->lang_choices = $sep =  "";
    if ($config->app->use_database == 1) {
      $zmax_lang = new Zmax_Model_Lang();
      $lang_list = $zmax_lang->getList();
      foreach($lang_list as $row){
        /*$this->view->lang_choices .=    "<a href='".
        $this->view->base_url."/".
        $this->view->module_name."/".
        $this->view->controller_name."/".
        $this->view->action_name.
        								"?zmax_display_lang=".
        $row->lang."' ";*/
        
        $this->view->lang_choices .=    " $sep <a href='" . $this->view->base_url."/index?zmax_display_lang=".
        $row->lang."' ";
        $sep="|";
        if($row->lang == $this->zmax_context->locale->getLanguage()){
          $this->view->lang_choices .= "style='font-weight:bold;color:#000;' ";
        }
        $this->view->lang_choices .=    ">".$row->name."</a>";
      }
    }

    /** Create a "logout" link if someone is connected, else a login link
     * Important: this assumes that there exists an "auth" controller.
     * @todo maybe use a config. parameter for this authentication controller
     */
    if ($this->zmax_context->is_authenticated and $this->zmax_context->user != null) {
      $user_name = $this->zmax_context->user->user_fname . " "
      . $this->zmax_context->user->user_lname;
      $this->view->logout = "<a href='".$this->view->base_url."/auth/logout'>".
      $this->zmax_context->texts->zmax->logout."(".$user_name.")</a> ‚Ä∫";
      $this->view->login = "";
    }
    else {
      $this->view->login = "<a href='".$this->view->base_url."/auth'>".
      $this->zmax_context->texts->zmax->login."</a>";
      $this->view->logout = "";
    }

  }

  /**
   * This method creates a "breadcrumb" anchor which can be used
   * to backtrack during the web navigation
   *
   * @return string the HTML link
   */

  private function createBreadcrumb()
  {
    // The breadcrumb is made from the path steps to the current action
    $show_module_name= $module_name = $this->getRequest()->getModuleName();
    $show_controller_name= $controller_name = $this->getRequest()->getControllerName();
    $show_action_name = $action_name = $this->getRequest()->getActionName();

    // Check that the zmax context exist. If 'yes' take the translations.
    if (is_object($this->zmax_context->texts->zmax)) {
      $show_module_name = $this->zmax_context->texts->zmax->$module_name;
      $show_controller_name = $this->zmax_context->texts->zmax->$controller_name;
      $show_action_name = $this->zmax_context->texts->zmax->$action_name;
    }
     
    if ($module_name != "default")
    $breadcrumb = '<a href="'.$this->zmax_context->config->app->base_url.'/'.$module_name.'">'
    .$show_module_name.'</a>' . '&nbsp;>&nbsp;';
    else
    $breadcrumb = "";

    if($controller_name != 'index'){
      $breadcrumb .= '<a href="'.$this->zmax_context->config->app->base_url.
            '/'.$module_name.'/'.$controller_name.'">'.
      $show_controller_name.'</a>'  . '&nbsp;>&nbsp;';
    }

    if($action_name != 'index'){
      $breadcrumb .= '<a href="'.$this->zmax_context->config->app->base_url .
             '/'.$module_name.'/'.$controller_name.'/'.$action_name.'">'.
      $show_action_name.'</a>';
    }

    return $breadcrumb;
  }

  /**
   * The defaut action which is used when an error as been met
   * or an exception has been thrown
   *
   */

  public function stalledAction ()
  {
    // Check whether this is an exception or a PHP error
    $eh = $this->_getParam('error_handler');

    if (is_object($eh)) {
      $errmess =  $script = $line  = "";
      $context = "Error";

      // Check the error handler type
      switch ($eh->type) {

        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                   // Just redirect to the home page
          $this->_redirect ($this->view->base_url);
          
          //$context = "Zend MVC error";
        //$errmess = "Unknown controller/action -- Query: " . $_SERVER['QUERY_STRING'];
            break;

        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER:
          $exception = $eh->exception;
          if (is_subclass_of ($exception, "Zmax_Exception")
          or get_class($exception) == "Zmax_Exception") {
            $script = $exception->getZmaxFile();
            $line = $exception->getZmaxLine();
          }
          else {
            $script = $exception->getFile();
            $line = $exception->getLine();
          }
          $errmess = $exception->getMessage();
          $context = get_class($exception);
          break;

        default: // This should never happen
          $exception = $eh->exception;
          $errmess = $exception->getMessage();
          $script = $exception->getFile();
          $line = $exception->getLine();
          break;
      }
    }
    else { // The stalled action has been directly called: forbidden!
      $this->view->content = "You cannot call directly this page<br/>";
      if ($this->zmax_context->config->view->zmax_view_system == "phplib") {
        echo $this->view->render("layout");
      }
      return;
    }

    // Compose the message
    $message = "<b>$context</b> in <i>$script</i> at line $line: "
    . "<br>$errmess</b><br/>";

    // Get the configuration object
    $config = $this->zmax_context->config;
     
    if ($config->app->display_errors) {
      // Display the error message, along with the script and the line no
      $this->view->content = $message;
    }
    else {
      // Show a polite message
      if ($this->zmax_context->texts->zmax->stalled_message != "stalled_message")
      $this->view->content = $this->zmax_context->texts->zmax->stalled_message;
      else
      $this->view->content = "This application is momentarily unavailable. "
      . "Please come back later.";

      // Maybe use a message from the dictionary ?
      // $this->zmax_context->texts->zmax->stalled_message;
      // Send a mail to the administrator(s). Note: we do not use
      // Zend_Mail because we would not take the risk of throwing an exception
      // here ...
      mail  ($config->app->admin_mail,  $config->app->name . " error notice ",
      $message);
    }
    // Print the page with the message
    if ($this->zmax_context->config->view->zmax_view_system == "phplib") {
      echo $this->view->render("layout");
    }
  }

  
  // Method that exports a file towards the browser
  function exportFile($fileName, $mimeType, $content)
  {
      $type = "application/octet-stream";
      header("Content-disposition: attachment; filename=$fileName");
      header("Content-Type: application/force-download");
      header("Content-Transfer-Encoding: $mimeType\n");
      header("Content-Length: ".strlen($content));
      header("Pragma: no-cache");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
      header("Expires: 0");
      echo $content;
  }
  
}

