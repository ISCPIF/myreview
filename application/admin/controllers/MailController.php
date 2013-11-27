<?php
/**********************************************
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
 ************************************************/

require_once("Mail.php");
require_once("Paper.php");
require_once("PaperStatus.php");

/**
 * This controller is in charge of sending all the mails
 */

class Admin_MailController extends Myreview_Controller_Action_Auth
{
  function init ()
  {
    // Call the parent
    parent::init();

    // Check the role
    if (is_object($this->session)) {
      if (!strstr($this->session->roles, "C"))  {
        $this->_forward ("index", "config", "admin");
      }
    }
  }

  /**
   * This controller contains admin function: check the role of the connected user
   */
  function preDispatch()
  {
    // The parent preDispatch check that a user is connected
    parent::preDispatch();

    // Now, check the role
    if (!$this->user->isAdmin()) {
      // Forward to the "access denied" action
      $this->_forward ("accessdenied", "index", "default");
    }
  }

  function indexAction()
  {
    // Not used: forward to the configuration menu
    $this->_forward ("index", "config", "admin");
  }

  // Sends a mail
  function sendAction() {
    $texts = &$this->zmax_context->texts;

    // The form has been submitted: send the mail
    $to = $_POST['to'];
    $subject = $_POST['subject'];
    $mailType = $this->getRequest()->getParam("mailType");
    $mailFormat = $this->getRequest()->getParam("mailFormat");
    $template =   $this->getRequest()->getParam('template');
    $copyToChair = $this->getRequest()->getParam('copyToChair');

    if (empty($subject) or empty($template)) {
      $this->view->content = $texts->subject_and_body_required;
      echo $this->view->render("layout");
      return;
    }

    $mail = new Mail ($mailType, $subject, $this->view->getScriptPaths(), $template);
    if ($copyToChair == "Y") {
      $mail->setCopyToChair(true);
    }
    $mail->setFormat($mailFormat);

    // Confirm
    switch ($mailType) {
      case Mail::ALL_REVIEWERS:
        $this->view->content = $this->zmax_context->texts->admin->message_sent_to_reviewers;
        $mail->send();
        break;

      case Mail::SOME_USER:
        $mail->setTo($to);
        $this->view->content = $this->zmax_context->texts->admin->message_sent_to_some_user
        . " " . $mail->getTo();
        $mail->send();
        break;

      case Mail::ALL_AUTHORS:
        $this->view->content = $this->zmax_context->texts->admin->message_sent_to_authors;
        $mail->send();
        break;

      case Mail::ALL_PARTICIPANTS:
        $this->view->content = $this->zmax_context->texts->admin->message_sent_to_participants;
        $mail->send();
        break;

      case Mail::ALL_AUTHORS_ACCEPTED:
        $this->view->content = $this->zmax_context->texts->admin->message_sent_to_authors_accepted;
        $mail->send();
        break;
         
      default:
        $this->view->content = "Wrong mail type ?!<br/>";
    }

    echo $this->view->render("layout");
    return;
  }

  /**
   * Send invitation to reviewers
   */
  function invitationAction()
  {
    $this->view->setFile("content", "showmessage.xml");
    $this->view->setBlock("content", "WARNING_TEMPLATE");
     
    $subject = $this->texts->mail->subj_reviewer_invitation;

    if (isSet($_REQUEST['to']))  {
      $mail = new Mail (Mail::SOME_USER, $subject, $this->view->getScriptPaths());
      $mail->setTo($_REQUEST['to']);
    }
    else {
      $mail = new Mail (Mail::ALL_REVIEWERS, $subject, $this->view->getScriptPaths());
    }

    $mail->setFormat(Mail::FORMAT_HTML);
    $mail->loadTemplate ($this->lang, "reviewer_invitation");

    // Put in the view
    $mail->putInView ($this->view);

    echo $this->view->render("layout");
  }

  /**
   * Ask their paper bidings to reviewers
   */
  function askprefsAction()
  {
    $this->view->setFile("content", "showmessage.xml");
    $this->view->setBlock("content", "WARNING_TEMPLATE");
     
    $subject = $this->texts->mail->subj_prefs;
    $mail = new Mail (Mail::ALL_REVIEWERS, $subject, $this->view->getScriptPaths());
    $mail->setFormat(Mail::FORMAT_HTML);
    $mail->loadTemplate ($this->lang, "preferences");

    // Put in the view
    $mail->putInView ($this->view);
    echo $this->view->render("layout");
  }


  /**
   * Ask their paper bidings to reviewers
   */
  function askreviewsAction()
  {
    $this->view->setFile("content", "showmessage.xml");
    $this->view->setBlock("content", "WARNING_TEMPLATE");
     
    $subject = $this->texts->mail->subj_askreviews;

    $mail = new Mail (Mail::ALL_REVIEWERS, $subject, $this->view->getScriptPaths());
    $mail->setFormat(Mail::FORMAT_HTML);
    $mail->loadTemplate ($this->lang, "ask_reviews");

    // Put in the view
    $mail->putInView ($this->view);
    echo $this->view->render("layout");
  }

  /**
   * Send the status to authors, with anonymous reviews
   */
  function notifyAction()
  {
    $db =    $this->zmax_context->db;
    $paperTbl = new Paper();
    $paperStatusTbl = new PaperStatus();

    // Load the reviews template
    $this->view->setFile("review", "review4author.xml");
    $this->view->setBlock("review", "review_mark", "review_marks");
    $this->view->setBlock("review", "review_answer", "review_answers");

    // Set the subject
    $subject =  $this->texts->mail->subj_notification;

    if (isSet($_REQUEST['id_paper'])) {
      // Mail for one paper
      $idPaper = $_REQUEST['id_paper'];
      $this->view->setFile("content", "showmessage.xml");
      $this->view->setBlock("content", "WARNING_TEMPLATE", " ");

      $paper = $paperTbl->find($idPaper)->current();
      if (!empty($paper->status)) {
        $paper->putInView($this->view);
        $this->view->reviews = $paper->showReviews($this->view);
        $statusRow = $paperStatusTbl->find($paper->status)->current();
 
        $mail = new  Mail (Mail::SOME_USER, $subject, $this->view->getScriptPaths());
        $mail->setTo($paper->emailContact);
        $mail->setFormat(Mail::FORMAT_HTML);
        $mail->loadTemplate ($this->lang, $statusRow->mailTemplate);
         
        // We know the paper, so we can instantiate the mail entities
        $this->view->setVar ("mailTemplate", $mail->getTemplate());
       $this->view->assign ("mailTemplate", "mailTemplate");
        $mail->setTemplate ($this->view->mailTemplate);
        // Put in the view
        $mail->putInView ($this->view);
      }
      else {
        $this->content = "Cannot send notification without a status<br/>";
      }
       echo $this->view->render("layout");
      return;
    }
    else {
      // Batch mail. Check that all papers have a status,
      // and that there are no missing reviews
      $this->view->setFile("content", "notify.xml");
      $this->view->setBlock("content", "TEMPLATE", "TEMPLATES");

      $res = $db->query("SELECT count(*) AS count FROM Paper p, PaperStatus s "
          .  " WHERE p.status = s.id AND final_status != 'Y'");
      $p =  $res->fetch (Zend_Db::FETCH_OBJ);
      if ($p->count > 0) {
        $this->view->content = "Cannot send notification mails: some papers do not have a status";
        echo $this->view->render("layout");
        exit;
      }

      $qReview = "SELECT count(*) AS count FROM Review WHERE overall IS NULL";
      $res = $db->query($qReview);
      $p =  $res->fetch (Zend_Db::FETCH_OBJ);
      if ($p->count > 0) {
        $this->view->content = "Cannot send notification mails: missing reviews";
        echo $this->view->render("layout");
        exit;
      }
       
      // OK. Now give the list of the templates that will be used
      $i = 0;
      $paperStatusList = $paperStatusTbl->fetchAll("final_status = 'Y'");
      $mail = new  Mail (Mail::SOME_USER, "", $this->view->getScriptPaths());
      
      foreach ($paperStatusList as $paperStatus) {
        $this->view->css_class = Config::CssCLass($i++);
        $paperStatus->putInView($this->view);
        $mail->loadTemplate ($this->lang, $paperStatus->mailTemplate);
        $this->view->setVar("template_content-{$paperStatus->id}", $mail->getTemplate());
        $this->view->assign("template_content", "template_content-{$paperStatus->id}");
        $this->view->append ("TEMPLATES", "TEMPLATE");
      }

      // Send the notification mails.
      $messages = "";
      if (isSet($_REQUEST['confirmed']) or isSet($_REQUEST['export'])) {
        PaperRow::$loadAbstracts = false;

        $papers = $paperTbl->fetchAll();
        $mail = new  Mail (Mail::SOME_USER, $subject, $this->view->getScriptPaths());
        $mail->setFormat(Mail::FORMAT_HTML);
        $mail->setCopyToChair(true);
        
      foreach ($papers as $paper) {
          $statusRow = $paperStatusTbl->find($paper->status)->current();
           
          $mail->setTo($paper->emailContact);
          $mail->loadTemplate ($this->lang, $statusRow->mailTemplate);
          $paper->putInView($mail->getEngine());
          $mail->getEngine()->reviews = $paper->showReviews($this->view);
           
          if (isSet($_REQUEST['confirmed'])) {
            $mail->send();
          }
          else {
            $messages .=  $mail->getMessage()  . "\n\n";
          }
        }
      }
    }

    if (isSet($_REQUEST['export'])) {
      header("Content-disposition: attachment; filename=notificationMails.txt");
      header("Content-Type: application/force-download");
      header("Content-Transfer-Encoding: text\n");
      header("Content-Length: ".strlen($messages));
      header("Pragma: no-cache");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
      header("Expires: 0");
      echo $messages;
    }
    else echo $this->view->render("layout");
  }

  /*
   * function conflictAction ()
  {
    // Send a mail to the reviewers of a paper, with reviews
    if (isSet($_REQUEST['idMessage']))
    if ($_REQUEST['idMessage'] == REVIEWS_TO_REVIEWERS)
    {
      $idPaper = $_REQUEST['idPaper'];
      $paper = GetPaper ($idPaper, $db);
      $subject = $config['confAcronym'] . " - "
      . $TEXTS->get("SUBJ_ACTION_REQUIRED") . "#$idPaper";

      $tpl->set_var("PAPER_ID", $paper['id']);
      $tpl->set_var ("PAPER_TITLE", $paper['title']);
      $tpl->set_var ("PAPER_AUTHORS",
      GetAuthors($paper['id'], $db, $config['blind_review'],
				  "string", $paper['authors']));
      $tpl->set_var("REVIEWS",
      DisplayReviews ($idPaper, "TxtShowReview", $tpl, $db));

      // Get the mail addresses
      $tabReviewers = GetReviewers($idPaper, $db);
      $comma = $mails = "";
      do {
        $rev=current($tabReviewers);
        $mails .= $comma . $rev->email;
        $comma = ", ";
      } while (next($tabReviewers));

      // Create the message
      $tpl->parse("Mail", "MailActionRequired");
      $message = $tpl->get_var("Mail");
      $tpl->set_var("BODY", FormSendMail ($mails,
      stripSlashes($subject),
      $message));
    }
  }
  */

  /**
   * Send a free mail to someone
   *
   */
  function freemailAction ()
  {
    // There must be a 'mailType' parameter
    $mailType = $this->getRequest()->getParam("mailType");
    if (empty($mailType)) {
      throw new Zmax_Exception ("There must a 'mailType' parameter for the 'freemail' action");
    }

    // There could be an 'id paper' param, in which case the
    // mail relates to this paper.
    $idPaper = $this->getRequest()->getParam("paper_id");
    if (!empty( $idPaper)) {
      $paperTbl = new Paper();
      $paper = $paperTbl->fetchAll("id=$idPaper")->current();
      $subject = "About submission #$idPaper -  '{$paper->title}'" ;
      $body = "About submission #$idPaper: '{$paper->title}'" .  "(" . $paper->getAuthors() . ")";
    }
    else {
      $subject = "";
      $body = "";
    }

    if ($mailType == Mail::SOME_USER) {
      // There must be a 'to' parameter
      $to = $this->getRequest()->getParam("to");

      if (empty($to)) {
        throw new Zmax_Exception ("There must a 'to' parameter for the 'freemail' action");
      }
    }
    else  if ($mailType == Mail::ALL_AUTHORS_ACCEPTED) {
      // There must be a 'to' parameter
      $to = $this->texts->mail->to_all_authors_accepted;
    }
    else  if ($mailType == Mail::ALL_REVIEWERS) {
      // There must be a 'to' parameter
      $to = $this->texts->mail->to_all_reviewers;
    }
    else  if ($mailType == Mail::ALL_PARTICIPANTS) {
      // There must be a 'to' parameter
      $to = $this->texts->mail->to_all_participants;
    }
    else {
      $to = "";
    }

    // We are all set. Show the form
    $this->view->setFile("content", "form_mail.xml");
    $this->view->mailType = $mailType;
    $this->view->subject = $subject;
    $this->view->body = $body;
    $this->view->to = $to;

    echo $this->view->render("layout");
  }

  /**
   * Instantiate a notification mail
   */
  private function notificationMail($subject, $paper)
  {
    $subjectMail = $subject . " " . $paper->id;
    $paperStatus = $paper->findParentPaperStatus();
     
    $paper->putInView($this->view);
    $this->view->assign("mail", "template");
  }
}

?>