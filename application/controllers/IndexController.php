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


require_once("Slot.php");
require_once("ShowSlot.php");
require_once("ConfSession.php");

require_once("RequiredFile.php");
require_once("Mail.php");

/**
 * The index controller
 *
 * This controller is in charge of the following task (...)
 * @package    Index
 */

class IndexController extends Myreview_Controller_Action
{
  /**
   * The default action. It just displays the home page
   *
   */

  function indexAction()
  {
    $this->view->setFile ("content", "index.xml");
    echo $this->view->render("layout");
  }

  /**
   * Shows guidelines for authors
   */

  function guidelinesAction()
  {
    $loginSuccessful = $this->getRequest()->getParam("login_successful");
    if (!empty($loginSuccessful)) {
      $this->view->initial_message = $this->zmax_context->texts->welcome_connected_user;
    }
    else {
      $this->view->initial_message = "";
    }

    $this->view->setFile ("content", "guidelines.xml");
    echo $this->view->render("layout");
  }

  /**
   * Create an account
   * @author philipperigaux
   *
   */

  function createaccountAction()
  {
    $this->view->setFile ("content", "createaccount.xml");
    // Create a new user and propose the form
    $user  = new User();
    $userRow = $user->createRow();
    $userRow->addRole (User::AUTHOR_ROLE);
    $this->view->form_user =    $userRow->form($this->view);

    $this->view->form_mode = "insert";

    echo $this->view->render("layout");
  }


  /**
   * Add  an account
   */

  function addaccountAction()
  {

    $form_mode = $this->getRequest()->getParam("form_mode");
    $changePassword = $this->getRequest()->getParam("change_password");
    $register = $this->getRequest()->getParam("register");

    if (empty($form_mode)) {
      throw new  Zmax_Exception ("Invalid action request (User::addaccount)");
    }

    $user  = new User();

    // @todo Probably some filtering would be appropriate
    $data = array ("first_name" => $_POST['first_name'],
         "last_name" => $_POST['last_name'],
         "affiliation" => $_POST['affiliation'],
         "address" => $_POST['address'],
         "city" => $_POST['city'],
        "state" => $_POST['state'],
          "zip_code" => $_POST['zip_code'],
          "phone" => $_POST['phone'],
         "email" => $_POST['email'],
        "country_code" => $_POST['country_code'],
        "requirements" => $_POST['requirements'],
        "cv" => $_POST['cv'],
    "creation_date" => new Zend_Db_Expr('CURDATE()')
    );

    if ($changePassword) {
      $data["password"]  = $_POST['password'];
    }
     
    if  ($form_mode == "insert") {
      $userRow = $user->createRow();
      $currentPassword = "";
      $userRow->setFromArray($data);
      $messages = $userRow->checkInsert($this->zmax_context->texts);
    }
    else {
      $userRow = $this->user;
      $currentPassword = $userRow->password;
      $userRow->setFromArray($data);
      $messages = $userRow->checkUpdate($this->zmax_context->texts, $changePassword);
    }

    if (count($messages) > 0) {
      $this->view->setFile ("content", "error_account.xml");
      $this->view->setBlock ("content", "MESSAGE",  "MESSAGES");

      foreach ($messages as $message) {
        $this->view->message = $message;
        $this->view->SUCCESS = "";
        $this->view->append("MESSAGES", "MESSAGE");
      }
      //    print_r($messages);
      $this->view->form_mode = $form_mode;
      $this->view->form_user = $userRow->form($this->view, "form_user.xml",
      $changePassword, $register);
    }
    else {
      // OK, insert or update the new user
      if    ($form_mode == "insert") {
        if (!$register) {
          $this->view->content = $this->zmax_context->texts->author->confirm_account;
        }
        else {
          $this->view->content = $this->zmax_context->texts->author->confirm_registration;
        }
      }
      else {
        $userRow->putInView($this->view);
        $this->view->setFile("content", "confirm_update_account.xml");
        $this->view->setBlock("content", "INFO_REVIEWER");
        $this->view->setBlock("content", "INFO_ATTENDEE");
        if (!$userRow->isReviewer() ) {
          $this->view->INFO_REVIEWER = "";
        }
        if (!$userRow->isParticipant() ) {
          $this->view->INFO_PARTICIPANT = "";
        }

      }

      // Encrypt the password and save
      if ($changePassword) {
        $userRow->password = md5($userRow->password);
      }
      else {
        // Keep the current password
        $userRow->password = $currentPassword;
      }

      // Change the default role if this is a registration
      if ($register) {
        $userRow->setRole(User::PARTICIPANT_ROLE);
      }
      $userRow->save();
    }
    echo $this->view->render("layout");
  }

  /**
   * Update the password of a user
   */

  function changepasswordAction()
  {
    $this->view->setFile("content", "updatepassword.xml");
    $this->view->setFile("form_password", "form_password.xml");

    $formPassword = $this->getRequest()->getParam("form_password");

    if (empty($formPassword)) {
      throw new  Zmax_Exception ("Invalid action request (Author::updatepassword)");
    }

    // OK, there should be a user
    $userRow = $this->user;
    $userRow->password = $this->getRequest()->getParam("password");
    $message = $userRow->checkPassword($this->zmax_context->texts);

    if (!empty($message)) {
      $this->view->message = $message;
    }
    else {
      // OK, insert or update the new user
      $this->view->message = $this->zmax_context->texts->author->confirm_password_update;
      $this->view->form_password = "";
      $userRow->password = md5($userRow->password);

      $userRow->save();
    }
    echo $this->view->render("layout");
  }

  /**
   * Action that explains how a password can be recovered
   */

  function lostpasswordAction()
  {
    $this->view->setFile ("content", "lostpassword.xml");
    echo $this->view->render("layout");
  }


  /**
   * Edit an account
   */

  function editaccountAction()
  {
    // Check that the user is connected !
    if ($this->checkSession()) {
      // If the user comes from the login form: welcome message
      $loginSuccessful = $this->getRequest()->getParam("login_successful");
      if (!empty($loginSuccessful)) {
        $this->view->initial_message = $this->zmax_context->texts->welcome_connected_user;
      }
      else {
        $this->view->initial_message = "";
      }

      $this->view->setFile ("content", "editaccount.xml");
      $this->view->setFile ("form_password", "form_password.xml");

      $this->view->form_mode = "update";
      $this->view->form_user = $this->user->form($this->view, "form_user.xml", false);

      echo $this->view->render("layout");
    }
    else {
      $this->_forward("login", "index");
    }
  }

  /**
   * Register to the conference
   * @author philipperigaux
   *
   */

  function registerAction()
  {
    $this->view->setFile ("content", "register.xml");
    // Create a new user and propose the form
    $user  = new User();
    $userRow = $user->createRow();
    $this->view->form_user =    $userRow->form($this->view, "form_user.xml", true, true);

    $this->view->form_mode = "insert";

    echo $this->view->render("layout");
  }

  /**
   * Show the program of the conference
   */

  function programAction()
  {
    $confSessionTbl = new ConfSession();

    $this->view->setFile("content","program.xml");
    $this->view->set_block ("content", "DATE", "DATES");
    $this->view->set_block ("DATE", "SESSION_DETAIL", "SESSIONS");
    $this->view->set_block ("SESSION_DETAIL", "PAPER_DETAIL", "PAPERS");
    $this->view->set_block ("SESSION_DETAIL", "PAPER_DOWNLOAD", " ");
    $this->view->set_block ("SESSION_DETAIL", "CHAIR", "SHOW_CHAIR");
    $this->view->set_block ("SESSION_DETAIL", "ROOM", "SHOW_ROOM");

    // Check whether the links to CR files are required
    $listFiles = array();
    if (isSet($_REQUEST['with_links'])) {
      // Get the list of required files in the proceedings phase
      $requiredFileTbl = new RequiredFile();
      $requiredFiles = $requiredFileTbl->fetchAll("id_phase = " . Config::PROCEEDINGS_PHASE);
      foreach ($requiredFiles as $requiredFile) {
        $listFiles[$requiredFile->file_code] = $requiredFile->file_extension;
      }

      // Directory of the CR files: the "proceedings" subdirectory must
      // be copied under the current directory when the program is published.
      $fileDir =  $this->zmax_context->config->app->upload_path . DIRECTORY_SEPARATOR ;
    }
    else {
      $this->view->download_link = "";
    }

    // First, loop on the dates
    $q_dates = "SELECT DISTINCT slot_date, UNIX_TIMESTAMP(slot_date) AS timestamp FROM Slot s ORDER BY slot_date";
    $rDates = $this->zmax_context->db->query ($q_dates);
    while ($date =  $rDates->fetch (Zend_Db::FETCH_OBJ)) {
      $this->view->SESSIONS = "";
      $zDate = new Zend_Date ($date->timestamp, Zend_Date::TIMESTAMP);
      $this->view->date = $zDate->toString("EEEE d MMM yyyy", $this->zmax_context->locale);
       
      $q_sessions = "SELECT c.id, name, chair, comment as sess_comment, room, "
      . " end as slot_end, begin as slot_begin "
      . " FROM ConfSession c, Slot s "
      . " WHERE s.id=c.id_slot AND slot_date='$date->slot_date'"
      . " ORDER BY slot_date, begin, end, c.id";
      $rSess = $this->zmax_context->db->query ($q_sessions);
      while ($session =  $rSess->fetch (Zend_Db::FETCH_OBJ)) {
        $this->view->PAPERS = "";
        $this->view->conf_session_name = $session->name;
        $this->view->conf_slot_name = substr($session->slot_begin,0,5) . "-" .
        substr($session->slot_end, 0, 5);
        $this->view->conf_session_comment = $session->sess_comment;
        $this->view->conf_session_chair = $session->chair;
        $this->view->conf_session_room = $session->room;

        if (empty ($session->room)) {
          $this->view->SHOW_ROOM = "";
        }
        else {
          $this->view->assign("SHOW_ROOM", "ROOM");
        }
        if (empty ($session->chair)) {
          $this->view->SHOW_CHAIR = "";
        }
        else {
          $this->view->assign("SHOW_CHAIR", "CHAIR");
        }

        // Now, loop on accepter papers
        $q_papers = "SELECT * FROM Paper "
        . "WHERE id_conf_session='$session->id' ORDER BY position_in_session";
        $rp = $this->zmax_context->db->query ($q_papers);
        while ($paper =  $rp->fetch (Zend_Db::FETCH_OBJ)) {
          $this->view->paper_authors =  PaperRow::getPaperAuthors($this->zmax_context->db, $paper);
           
          $this->view->paper_title = $paper->title ;

          // Take the name of the camera ready file
          foreach ($listFiles as $code => $ext) {
            $filePath =  "." . DIRECTORY_SEPARATOR . "proceedings" . DIRECTORY_SEPARATOR . $code . "_" . $paper->id . "." . $ext;
            if (file_exists($filePath)) {
              $this->view->file_path = $filePath;
              // $this->texts->author->get($code) . "</a>";
              $this->view->append("PAPERS", "PAPER_DOWNLOAD");
            }
            else {
              // No file to download
              $this->view->append("PAPERS", "PAPER_DETAIL");
            }
          }
        }
        $this->view->append("SESSIONS", "SESSION_DETAIL");
      }
      $this->view->append("DATES", "DATE");
    }
    echo $this->view->render("layout");
  }

  /**
   * Send an email to the user along with the password
   *
   */
  function passwordrecallAction ()
  {
    // There should be an email param
    $email = $this->getRequest()->getParam('email');

    if (empty($email)) {
      $this->_forward ("lostpassword", "index");
    }
    else {
      // We must send the password to the user
      $user = new User();
      $userRow = $user->findByEmail ($email);
       
      if (is_object($userRow)) {
        // Get the default password, and assign it to the user
        $password = $userRow->defaultPassword($this->config_v1["passwordGenerator"]);

        $userRow->password = md5($password);
        $userRow->save();

        $userRow->putInView($this->view);

        // And, finally: send a message to the chair, and show a polite ack.
        $mail = new Mail (Mail::SOME_USER, $this->texts->mail->subj_password_recall,
        $this->view->getScriptPaths());
        $mail->setFormat(Mail::FORMAT_HTML);
        $mail->loadTemplate ($this->lang, "send_password");
        $mail->setTo($userRow->email);
        $mail->getEngine()->password = $password;
        $mail->send();
      }

      $this->view->setFile ("content", "passwordrecall.xml");

      echo $this->view->render("layout");
    }
  }

  /**
   * Check whether an account already exists
   */
  function checkaccountAction()
  {
    // Check whther there is  an email param
    $email = $this->getRequest()->getParam('email');

    if (empty($email)) {
      $this->view->setFile ("content", "checkaccount.xml");
    }
    else {
      // We send an ack. to the user
      $user = new User();
      $userRow = $user->findByEmail ($email);
       
      if (is_object($userRow)) {
        // Send a message to the user
        $mail = new Mail (Mail::SOME_USER, $this->texts->mail->subj_check_account,
        $this->view->getScriptPaths());
        $mail->loadTemplate ($this->lang, "ack_account");
        $mail->setFormat(Mail::FORMAT_HTML);
        $mail->setTo($userRow->email);
        $mail->send();
      }
      $this->view->setFile ("content", "ack_account.xml");
    }
    echo $this->view->render("layout");
  }

  /*
   * Sho the login form
   */

  function loginAction ()
  {
    $request = $this->getRequest();
    $email = strToLower($request->getParam("email"));
    $password = $request->getParam("password");

    // Protect the input data
    $email = stripSlashes ($email);
    $password = stripSlashes ($password);
     
    $template = "login.xml";
    $idSession = session_id();

    $texts = $this->zmax_context->texts;

    // Check whether we arrive here from a denied access to a requested URL
    $requestedUrl = $request->getParam("requestedUrl") ;
    if (empty($requestedUrl)) $requestedUrl = $this->myUrl();

    // Put some info in the view
    $this->view->requestedUrl = $requestedUrl;
    $this->view->email = $email;
    $this->view->login_message = "";

    // Get the current session (if any)
    $sessionTbl = new Session();
    $currentSession = $sessionTbl->find($idSession)->current();

    // Session found?
    if (is_object($currentSession)) {
      // is it valid ?
      if ($currentSession->isValid()) {
        // Reinitialize the validity period
        $currentSession->tempsLimite = date ("U") + 7200;
        $currentSession->save();
        // And confirm the connection
        $template = "already_logged_in.xml";
      }
      else {
        $template = "login.xml";
        $this->view->login_message =  $texts->def->session_no_longer_valid;
      }
    }
    // Cas 2.a: pas de session mais email et mot de passe
     
    if (!empty($email)) {

      if ($sessionTbl->create ($email, $password, $idSession)) {
        // Connection OK. Forward to the a page, depending on the role
        $this->getRequest()->setParam("login_successful", 1);
        $userTbl = new User();
        $user = $userTbl->findByEmail($email);
        if ($user->isAdmin()) {
          $this->_forward("index", "chair", "admin");
        }
        else if ($user->isReviewer()) {
          $this->_forward("index", "reviewer");
        }
        else {
          $this->_forward("guidelines", "index");
        }

        return;
      }
      else {
        // echo "Login failed<br/>";
        $template = "login.xml";
        $this->view->login_message =  $texts->def->login_failed;
      }
    }

    $this->view->setFile("content", $template);
    echo $this->view->render("layout");
  }

  /**
   * Logout action: delete the current session
   *
   */
  public function logoutAction()
  {
    // Delete the current session
    $q = "DELETE FROM Session WHERE idSession='" . session_id() . "'";
    $this->zmax_context->db->query($q);

    // Forward to the "index" action of the current module
    $req = $this->getRequest();
    $this->_forward("index", "index", $req->getModuleName());
  }

  /*
   * Action launched when an access is denied
   */
  function accessdeniedAction()
  {
    $this->view->content = $this->zmax_context->texts->access_denied;
    echo $this->view->render("layout");
  }

  /*
   * A reviewer declines an invitation to participate
   */
  function declineAction()
  {
    // Check that this is the true reviewer
    $email = $this->getRequest()->getParam("email");
    $password = $this->getRequest()->getParam("password");
    $idSession = session_id();
     
    // Delete the curent session if any
    $this->deleteCurrentSession();

    // Now, try to open a session with the email and password
    $sessionTbl = new Session();
    if (!$sessionTbl->create ($email, $password, $idSession)) {
      // No way to open a session? Something wrong: redirect to the home page.
      $redirect = $this->view->base_url . "/";
      $this->_redirect($redirect);;
    }

    // Get the user and remove the 'reviewer' role
    $user = new User();
    $userRow = $user->findByEmail($email);
    $userRow->removeRole(User::REVIEWER_ROLE);
    $userRow->save();

    // Put the user and the config in the view
    $userRow->putInView($this->view);

    // And, finally: send a message to the chair, and show a polite ack.
    $mail = new Mail (Mail::PC_CHAIR, $this->texts->mail->subj_decline_invitation,
    $this->view->getScriptPaths());
    $mail->loadTemplate ($this->lang, "decline_invitation");
    $mail->getEngine()->invited_user =  $this->user->fullName();
    $mail->send();

    $this->view->setFile("content", "decline.xml");
    echo $this->view->render("layout");
  }

  /*
   * A reviewer accepts an invitation to participate
   */
  function acceptAction()
  {
    // Check that this is the true reviewer
    $email = $this->getRequest()->getParam("email");
    $password = $this->getRequest()->getParam("password");
    $idSession = session_id();
     
    // Delete the curent session if any
    $this->deleteCurrentSession();

    // Get the user, mark as "confirmed", and show some instructions
    $user = new User();
    $this->user= $user->findByEmail($email);

    // User not found? Probably an attempt to enter the system without auth.
    if (!is_object($this->user)) {
      $this->_redirect($this->view->base_url . "/");
    }

    // Set the default password
    $this->user->invitation_confirmed = 'Y';
    $password = $this->user->defaultPassword($this->config->passwordGenerator);
    $this->user->password = md5($password);
    $this->user->save();
    $this->user->putInView($this->view);

    // Send a message to the chair
    $mail = new Mail (Mail::PC_CHAIR, $this->texts->mail->subj_accept_invitation,
    $this->view->getScriptPaths());
    $mail->setFormat(Mail::FORMAT_HTML);
    $mail->loadTemplate ($this->lang, "accept_invitation");
    $mail->getEngine()->invited_user =  $this->user->fullName();
    $mail->send();

    // Send a message to the user with instructions
    $mail->loadTemplate ($this->lang, "reviewer_instructions");
    $this->config->putInView($mail->getEngine());
    $mail->getEngine()->password = $password;
    $mail->send();

    $this->view->setFile("content", "accept.xml");
    echo $this->view->render("layout");
  }

  /**
   * Utility function that deletes the current session
   */
  private function deleteCurrentSession()
  {
    $idSession = session_id();
    $sessionTbl = new Session();
    $currentSession = $sessionTbl->find($idSession)->current();

    // Session found? Delete it
    if (is_object($currentSession)) {
      $q = "DELETE FROM Session WHERE idSession='$idSession'";
      $this->zmax_context->db->query($q);
    }

  }
}