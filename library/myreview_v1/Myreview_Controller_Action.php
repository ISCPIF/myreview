<?php
/************************************************************************
 The MyReview system for web-based conference management

 Copyright (C) 2003-2009 Philippe Rigaux
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation;

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *****************************************************************************/

require_once("Session.php");


/**
 * The controller class of MyReview: identical to Zmax_Controller_Action,
 * but adds a few components in the init() actions. This helps to re-introduce
 * function from the V1 into the V2.
 *
 */

class  Myreview_Controller_Action extends Zmax_Controller_Action
{
  function init()
  {
    // Same as the parent
    parent::init();

    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();

    // Instantiate a Config object with all config values. Put it in the registry
    // (can be accessed by models) and in the controller.
    $config = new Config();
    $this->config = $config->fetchAll()->current();
    $this->config->putInView($this->view);
    $registry->set("Config", $this->config);

    /// The following is for backward compatibility with V1. To be removed eventually.

    // Initialize the $db_v1 object, using the Version DB interface.
    $db_config =  $this->zmax_context->config->db->params ;
    $this->db_v1 = new BD ($db_config->username, $db_config->password,
    $db_config->dbname, $db_config->host);

    // Load the translations for the myreview namespace
    $this->zmax_context->texts->addTranslation ($this->zmax_context->db,
    $this->zmax_context->locale, array ("namespaces" =>
        array('author', 'reviewer', 'admin', 'mail')));

    // Load the configuration of MyReview. Put the values in the view
    $this->config_v1 = GetConfig($this->db_v1);
     
    // Get the codes of the application (V1).
    $this->codes = new Codes("Codes.xml");

    // Keep the lang in the controller
    $this->lang =  $this->zmax_context->locale->getLanguage();

    $this->view->conf_name  = $this->config_v1['confName'];
    $this->view->page_title = "";

    // Check whether the user is connected
    $this->texts = $this->zmax_context->texts;

    $this->session = $this->user = null;

    if (!$this->checkSession()) {
      $this->view->user_status = $this->texts->user_not_connected;

      // Propose the links to create and account or log in
      $this->view->account_mngt = $this->texts->def->create_account;
      $this->view->account_mngt_link = "createaccount";;
      $this->view->login_logout = $this->texts->def->log_in;
      $this->view->login_logout_link =  "login";
    }
    else {
      $logoutLink = $this->view->base_url . "/index/logout"; 

      $this->view->user_status = $this->zmax_context->texts->you_are_currently_connected .
      " " . $this->user->first_name . " <b>" . $this->user->last_name . "</b>.";

      // Propose the links to edit account and account or log out
      $this->view->account_mngt = $this->texts->author->edit_account_header;
      $this->view->account_mngt_link = "editaccount";;
      $this->view->login_logout = $this->texts->def->log_out;
      $this->view->login_logout_link =  "logout";
    }

    // Put the user and the session in the registry
    $registry->set("session", $this->session);
    $registry->set("user", $this->user);
  }

  /*
   * Return the URL of the current action
   */
  function myUrl()
  {
    $request = $this->getRequest();
    return $this->view->base_url . "/" . $request->getModuleName()
    . "/" . $request->getControllerName() . "/" . $request->getActionName();
  }

  /**
   * Check that a session is open and valid
   * @author philipperigaux
   *
   */

  function checkSession()
  {
    $sessionTble = new Session();
    $this->session = $sessionTble->find(session_id())->current();

    $this->getRequest()->setParam("requestedUrl", $this->myUrl());
     
    if (!is_object($this->session) or empty($this->session)) {
      return false;
    }

    if (!$this->session->isValid()) {
      return false;
    }

    // Take the user and put it in the controller and in the registry
    $user = new User();
    $this->user = $user->find($this->session->id_user)->current();
    return true;
  }

}