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

require_once ("User.php");
require_once('Paper.php');
require_once('RequiredFile.php');
require_once("Upload.php");
require_once("Mail.php");
require_once ("Message.php");

/**
 * The author controller
 *
 */

class AuthorController extends Myreview_Controller_Action_Auth
{

  /**
   * Init function: local the local menu template
   */
  function init()
  {
    parent::init();

    $this->view->setFile("local_menu", "local_menu.xml");
  }


  /**
   * The default action. It just displays the home page
   */

  function indexAction()
  {
    $this->_forward("index", "index", "default");
  }


  /**
   * Author console: shows tha account and the list of submitted papers
   */

  function consoleAction()
  {
    $this->view->setFile ("content", "index.xml");
    $this->view->setBlock ("content", "PAPER", "PAPERS");
    $this->view->setBlock ("PAPER", "UPLOAD", "UPLOADS");
    $this->view->setBlock ("PAPER", "UPLOAD_BUTTON");
    $this->view->setBlock ("UPLOAD", "DOWNLOAD", "DOWNLOAD_LINK");

    $requiredFileTbl = new RequiredFile();

    // Put user info in the view
    $this->user->putInView($this->view);

    // Loop on the papers of the current user
    $authors = $this->user->findAuthor();
    $i = 0;
    foreach ($authors as $author) {
      $this->view->css_class = Config::CssCLass($i++);
      // Get the paper
      $paper =    $author->findParentPaper();
      $author->putInView($this->view);
      $paper->putInView($this->view);
      $this->view->UPLOADS = "";

      // Loop on the required file, and propose an upload link if required
      $requiredFiles = $requiredFileTbl->fetchAll();
      $countRequired = 0;
      $submissionComplete = true;
      $message = "";
      foreach ($requiredFiles as $requiredFile) {
        // Check the file is required in the current phase
        if ($this->config->isPhaseOpen($requiredFile->id_phase)) {
          $requiredFile->putInView($this->view);
          $countRequired++;
          if ($requiredFile->mandatory == 'Y') {
            $this->view->file_mandatory = '*';
          }
          else {
            $this->view->file_mandatory = '';
          }

          if (!$paper->fileExists($requiredFile)) {
            $this->view->DOWNLOAD_LINK = " ";
            if ($requiredFile->mandatory == 'Y') {
              // The submission is not complete!
              $submissionComplete = false;
              $this->view->font_color = "red";
            }
            else {
              $this->view->font_color = "green";
            }
          }
          else {
            $this->view->assign("DOWNLOAD_LINK", "DOWNLOAD");
            $this->view->font_color = "green";
          }
          $this->view->append("UPLOADS", "UPLOAD");
        }
      }

      // Submission no complete? Show it
      if ($submissionComplete) {
        $this->view->icon_name = "ok-icon.png";
      }
      else {
        $this->view->icon_name = "warning-icon.png";
      }
      $this->view->message = $message;
       
      // Allright. Nothing to upload? Tell it.
      if ($countRequired == 0) {
        $this->view->UPLOADS = "No file to upload at this moment<br/>";
        $this->view->UPLOAD_BUTTON = " ";
      }

      // Two instantiations to resolve dynamic labels
      $this->view->assign ("PAPER2", "PAPER");
      $this->view->append("PAPERS", "PAPER2");
    }

    if ($i == 0) {
      // Don't show the submision block;
      $this->view->PAPERS = $this->texts->author->no_submission_so_far;
    }

    echo $this->view->render("layout");
  }

  /**
   * Show the informations related to the participation
   */

  function participationAction()
  {
    $this->view->setFile ("content", "participation.xml");
    // Put user info in the view
    $this->user->putInView($this->view);
    $this->view->assign("content", "content");
    echo $this->view->render("layout");
  }


  /**
   * A new submission
   * @author philipperigaux
   *
   */

  function submitAction()
  {
    if (!$this->config->submissionClosed()) {

      $this->view->setFile ("content", "submit.xml");

      // Instantiate a new paper
      $paper = new Paper();
      $paperRow = $paper->createRow();

      // Create the submision form
      $this->view->form_mode = "insert";
      $this->view->form_submit = $paperRow->form($this->user, $this->view);
    }
    else {
      // Submission is closed
      $this->view->content = $this->zmax_context->texts->author->abstract_submission_closed;
    }
    echo $this->view->render("layout");
  }

  /**
   * Edit an existing submission
   */

  function editAction()
  {
    if (!$this->config->submissionClosed()) {

      $this->view->setFile ("content", "edit.xml");

      // The paper id should be a param
      $idPaper = $this->getRequest()->getParam('id_paper');
      $paper = new Paper();
      $paperRow = $paper->find($idPaper)->current();

      if (!is_object($paperRow)) {
        // Raise an exception
        throw  new Zmax_Exception ("Author::edit. Invalid paper id: $idPaper");
      }
       
      // Now, check that the current user is an author of the paper
      if ($paperRow->getAuthorByEmail($this->user->email) === null) {
        $this->view->content = "Author::upload. Invalid access";
        echo $this->view->render("layout");
        return;
      }

      // Create the submision form
      $this->view->form_mode = "update";
      $this->view->form_submit = $paperRow->form($this->user, $this->view);
    }
    else {
      // Submission is closed
      $this->view->content = $this->zmax_context->texts->author->abstract_submission_closed;
    }

    echo $this->view->render("layout");
  }

  /**
   * Show an existing submission
   */

  function showpaperAction()
  {
    $this->view->setFile ("content", "show_paper.xml");
    // Get the abstract section block
    $this->view->setBlock("content", "SECTION", "SECTIONS");
    $this->view->setBlock("content", "ANSWER", "ANSWERS");

    // The paper id should be a param
    $idPaper = $this->getRequest()->getParam('id_paper');
    $paper = new Paper();
    $paperRow = $paper->find($idPaper)->current();

    if (!is_object($paperRow)) {
      // Raise an exception
      throw  new Zmax_Exception ("Author::edit. Invalid paper id: $idPaper");
    }
     
    // Now, check that the current user is an author of the paper
    if ($paperRow->getAuthorByEmail($this->user->email) === null) {
      $this->view->content = "Author::upload. Invalid access";
      echo $this->view->render("layout");
      return;
    }

    $paperRow->putInView($this->view);

    echo $this->view->render("layout");
  }

  /**
   * Process a new submission
   */

  function processAction()
  {

    if (!$this->config->submissionClosed()) {

      $form_mode = $this->getRequest()->getParam("form_mode");
      if (empty($form_mode)) {
        throw new  Zmax_Exception ("Invalid action request (Author::process)");
      }

      if ($this->config->two_phases_submission == "N") {
        $upload = true;
      }
      else {
        $upload = false;
      }

      // Check whether the file has been uploaded
      if (isSet($_FILES['paper']['tmp_name']) and file_exists($_FILES['paper']['tmp_name'])) {
        $file =$_FILES['paper'];
      }
      else {
        $file=array("tmp_name" => "none");
      }

      // Try to insert or update the paper
      $paper = new Paper();
      $data = array ("title" => $_POST['title'],
                "topic" => $_POST['topic'],
                "nb_authors_in_form"  => $_POST['nb_authors_in_form']);
       
      if ($form_mode == "insert") {
        // Instantiate a new paper. Fill with the post params.
        $paperRow = $paper->createRow();
        $paperRow->setFilterData (true);
        $paperRow->setFromArray($data);
      }
      else {
        // The paper exists. First: load from the DB
        $id = $this->getRequest()->getParam("id");
        $paperRow = $paper->find($id)->current();
        // Second: update its content from the form post
        $paperRow->updateFromArray($data);
      }

      // Look whether the user asks for an additional author
      if (isSet($_REQUEST['addAuthor'])) {
        $this->view->setFile ("content", "submit.xml");
        $paperRow->nb_authors_in_form++;
        $this->view->form_mode = $form_mode;
        $this->view->form_submit = $paperRow->form($this->user, $this->view);
        echo $this->view->render("layout");
        return;
      }

      // First, check the content
      $ok = $paperRow->checkRequest ($this->user, $file, $upload, $this->zmax_context->texts);
       
      // Error?
      if (!$ok) {
        // Error reporting
        $this->view->setFile ("content", "submit_error.xml");

        $messages = $paperRow->getMessages();

        $this->view->setBlock("content", "ERROR", "ERRORS");
        foreach ($messages as $message) {
          $this->view->ERROR_MESSAGE = $message;
          // NB: parsing is done twice because of abstract codes indirection
          $this->view->assign("message", "ERROR");
          $this->view->append("ERRORS", "message");
        }

        $this->view->form_mode = $form_mode;
        $this->view->form_submit = $paperRow->form($this->user, $this->view);
      }
      else {
        // Save the paper
        $paperRow->saveAll();
        // Put in the view
        $paperRow->putInView($this->view);
         
        // Store the file if any
        if ($upload) {
          // get the required file in the submission phase (assume there is only one)
          $requiredFileTbl = new RequiredFile();
          $requiredFiles = $requiredFileTbl->fetchAll();
          foreach ($requiredFiles as $requiredFile) {
            if ($requiredFile->id_phase == Config::SUBMISSION_PHASE and $requiredFile->file_extension=="pdf") {
              $required = $requiredFile;
            }
          }
          if (!isSet($required)) {
            throw new Zmax_Exception ("Unable to determine the file required in submission phase. Stop");
          }
          $paperRow->storeFile($required, $file);
        }

        // Get the mail template
        if ($form_mode == "insert") {
          $mailContent = "ack_submit";
          $this->view->setFile ("content", "ack_submit.xml");
        }
        else {
          $mailContent = "ack_edit";
          $this->view->setFile ("content", "ack_edit.xml");
        }

        // Send a mail to ack the submission
        $mail = new Mail (Mail::SOME_USER, $this->texts->mail->subj_new_submission,
        $this->view->getScriptPaths());
        $mail->setTo($paperRow->emailContact);
        $mail->loadTemplate ($this->lang, $mailContent);
        $mail->setFormat(Mail::FORMAT_HTML);
        if ($this->config->mailOnAbstract == "Y") {
          $mail->setCopyToChair(true);
        }
        $mailView=$mail->getEngine();
        $paperRow->putInView($mailView);
        $this->config->putInView($mailView);
        $mail->send();
      }
    }
    else {
      // Submission is closed
      $this->view->content = $this->zmax_context->texts->author->abstract_submission_closed;
    }
    // Two instantiation for ref. solving
    $this->view->assign ("content", "content");
    echo $this->view->render("layout");
  }

  /**
   * Paper upload
   */

  function uploadAction()
  {
    // The paper id should be a param
    $idPaper = $this->getRequest()->getParam('id_paper');
    $paper = new Paper();
    $paperRow = $paper->find($idPaper)->current();

    if (!is_object($paperRow)) {
      // Raise an exception
      throw  new Zmax_Exception ("Author::upload. Invalid paper id: $idPaper");
    }

    // Now, check that the current user is an author of the paper
    if ($paperRow->getAuthorByEmail($this->user->email) === null) {
      $this->view->content = "Author::upload. Invalid access";
      echo $this->view->render("layout");
      return;
    }

    $requiredFileTbl = new RequiredFile();
    $uploadTbl = new Upload();
    // Proceedings phase? No need to recall the submission deadline
    if ($this->config->isPhaseOpen(Config::PROCEEDINGS_PHASE)) {
      $this->view->deadline = $this->config->getWorkflowDate (Config::PROCEEDINGS_PHASE);
    }
    else {
      $this->view->deadline = $this->config->getWorkflowDate (Config::SUBMISSION_PHASE);
    }

    $this->view->setFile ("content", "ack_upload.xml");
    $this->view->setBlock ("content", "FILE", "FILES");
    $paperRow->putInView($this->view);

    // Loop on the required file, and check whether one of them has been uploaded
    $requiredFiles = $requiredFileTbl->fetchAll();
    foreach ($requiredFiles as $requiredFile) {
      $requiredFile->putInView($this->view);
      $fileCode = $requiredFile->file_code;
      if (isSet($_FILES[$fileCode]['tmp_name']) and file_exists($_FILES[$fileCode]['tmp_name'])) {
        $file = $_FILES[$fileCode];
        // Paper submission phase
        if ($paperRow->storeFile  ($requiredFile, $file)) {
          $this->view->file_size = $file['size'];
          $this->view->assign ("TMP", "FILE");
          $this->view->append ("FILES", "TMP");
        }
        else {
          $this->view->content = "<br/>Unable to store the file '$requiredFile->file_code': "
          . $paperRow->getMessage(PaperRow::STORE_MESSAGE_ID);
          break;
        }
      }
    }

    /*{
     // Propose the form
     $this->view->setFile ("content", "upload.xml");
     $this->view->setFile ("form_upload", "form_upload.xml");
     $paperRow->putInView($this->view);
     }*/
    $this->view->assign ("content", "content");
    echo $this->view->render("layout");
  }

  /**
   * Download a paper
   */

  function downloadAction()
  {
    // The paper id should be a param
    $idPaper = $this->getRequest()->getParam('id_paper');
    $paper = new Paper();
    $paperRow = $paper->find($idPaper)->current();

    // The file id should be here as well
    $idFile = $this->getRequest()->getParam('id_file');
    $requiredFileTbl = new RequiredFile();
    $requiredFileRow = $requiredFileTbl->find($idFile)->current();

    if (!is_object($paperRow) or !is_object($requiredFileRow)) {
      // Raise an exception
      throw  new Zmax_Exception ("Author::download. Invalid call: ($idPaper, $idFile)");
    }

    $paperRow->download($requiredFileRow);
  }

  /**
   * Shows the info on a submission in a secondary window
   */

  function showinfoAction()
  {
    $paperTbl = new Paper();

    $this->view->setFile("content", "show_info.xml");
    $this->view->setBlock("content", "SECTION", "SECTIONS");
    $this->view->setBlock("content", "DISCUSSION", "SHOW_DISCUSSION");
    $this->view->setBlock("DISCUSSION", "message", "messages");
    $this->view->setFile("review", "show_tbl_review.xml");
    $this->view->setBlock("review", "review_mark", "review_marks");
    $this->view->setBlock("review", "review_answer", "review_answers");

    $idPaper = $this->getRequest()->getParam("id_paper");
    $paper = $paperTbl->find($idPaper)->current();

    if (!is_object($paper)) {
      throw new Zmax_Exception ("showinfo: I am waiting for a valid paper id !?");
    }

    // Default: print only the basic informations. Do not show the reviews
    // nor the forum
    $this->view->setBlock("content", "ANSWER", "ANSWERS");
    $this->view->setBlock("content", "REVIEWER", "REVIEWERS");
    $this->view->setBlock("content", "REVIEWS");
    $this->view->setVar("SHOW_DISCUSSION", "");

    // instantiate the template for the paper and its dependent objects
    $paper->putInView($this->view);

    // Check the context to determine what must be printed
    if ($this->user->isAdmin() or isSet ($_REQUEST['in_forum'])) {

      // It is an administrator. Print everything
      $this->view->setVar("SHOW_REVIEWS", $paper->showReviews($this->view, true));

      // Add the messages if the discussion is opened
      if ($this->config->discussion_mode != Config::NO_DISCUSSION) {
        $this->view->messages = Message::display($idPaper, 0, $this->view);
        $this->view->assign ("TMP_DISCUSSION", "DISCUSSION");
        $this->view->assign ("SHOW_DISCUSSION", "TMP_DISCUSSION");
      }
    }
    else if ($paper->hasReviewer($this->user->id)) {
      // It is a reviewer,
      $review = $paper->getReview($this->user->id);

      //check the 'allReviews' parameter
      if (isSet($_REQUEST['allReviews']))
      if ($_REQUEST['allReviews']) {
        $this->view->SHOW_REVIEWS = $review->showReview($this->view, true);
      }
      else {
        $this->view->SHOW_REVIEWS = $paper->showReviews($this->view, true);
      }

      // Add the messages if the discussion is open
      // if ($config['discussion_mode'] != NO_DISCUSSION)
      //$this->view->set_var("FORUM", DisplayMessages($idPaper, 0, $db, false));
    }

    // Take account of the 'noReview' and 'noForum' parameter
    if (isSet($_REQUEST['noReview'])) {
      $this->view->REVIEWS = " ";
    }

    //  if (isSet($_REQUEST['noForum'])) $view->set_var("FORUM", "");

    $this->view->assign("action_result", "content");
    $this->view->assign("action_result2", "action_result");
    echo $this->view->action_result2;
  }
}
