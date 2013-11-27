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

require_once("Review.php");
require_once("User.php");
require_once("Paper.php");
require_once("Criteria.php");
require_once("Message.php");
require_once('RequiredFile.php');
require_once("Mail.php");
require_once("Config.php");
/**
 * The reviewer controller
 *
 * This controller is in charge of the following task (...)
 * @package    Index
 */

class ReviewerController extends Myreview_Controller_Action_Auth
{

  /**
   *  check the role of the connected user
   */
  function preDispatch()
  {
    // The parent preDispatch check that a user is connected
    parent::preDispatch();

    // Now, check the role
    if (!$this->user->isReviewer()) {
      // Forward to the "access denied" action
      $this->_forward ("accessdenied", "index", "default");
    }

  }

  /**
   * The default action. It just displays the home page
   */

  function indexAction()
  {
    if (!$this->config->isPhaseOpen(Config::REVIEWING_PHASE)) {
      $this->view->content = $this->texts->reviewer->review_phase_is_closed;
      echo $this->view->render("layout");
      return;
    }

    $nbPapersInGroup = 15;

    // Get the current group
    $currentGroup = $this->getRequest()->getParam("group", "0");
    if ($currentGroup == "0") {
      if (isSet($_SESSION['myreview_current_group'])) {
        $currentGroup  = $_SESSION['myreview_current_group'];
      }
      else $currentGroup = 1;
    }

    // Put in the session
    $_SESSION['myreview_current_group'] = $currentGroup;

    $minPaper = ($currentGroup  -1) * $nbPapersInGroup + 1 ;
    $maxPaper = $minPaper + $nbPapersInGroup;

    $loginSuccessful = $this->getRequest()->getParam("login_successful");
    if (!empty($loginSuccessful)) {
      $this->view->initial_message = $this->zmax_context->texts->welcome_connected_reviewer;
    }
    else {
      $this->view->initial_message = "";
    }

    $this->view->setFile ("content", "index.xml");
    $texts = $this->zmax_context->texts;

    $requiredFileTbl = new RequiredFile();

    /* Select all the papers and list them.
     First extract the 'bloc' describing a line from the template */

    $this->view->setBlock("content", "GROUP", "GROUPS");
    $this->view->setBlock("content", "PAPER", "PAPERS");
    $this->view->setBlock("content", "FORUM_LINK");
    $this->view->setBlock("PAPER", "ALL_REVIEWS", "REVIEWS");
    $this->view->setBlock("PAPER", "MY_REVIEW", "REVIEW");
    $this->view->setBlock("PAPER", "DOWNLOAD", "DOWNLOADS");
    $this->view->setBlock("DOWNLOAD", "DOWNLOAD_LINK", "THE_LINK");
    $this->view->setBlock("PAPER", "review", "show_review");
    $this->view->setBlock("review", "review_mark", "review_marks");
    $this->view->setBlock("review", "review_answer", "review_answers");
    $this->view->setBlock("PAPER", "SECTION", "SECTIONS");
    $this->view->setBlock("PAPER", "ANSWER", "ANSWERS");

    if ($this->config->discussion_mode != Config::GLOBAL_DISCUSSION) {
      $this->view->set_var("FORUM_LINK", "");
    }

    // First, create the groups
    $iPaper = 0; $iGroup = 0; $begin = 1;
    $qPapers = "SELECT idPaper FROM Review WHERE id_user = '{$this->user->id}'";
    $rPapers = $this->zmax_context->db->fetchAll($qPapers);
    $nbPapers = count ($rPapers);
    // echo "Nb = $nbPapers<br/>";
    // while ($p =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
    foreach ($rPapers as $paper) {
      $iPaper++;
      if ($iPaper % $nbPapersInGroup == 0 or $iPaper == $nbPapers) {
        $iGroup ++;
        $end = $iPaper;
        $this->view->group_desc=  "($begin-$end)";
        $this->view->id_group = $iGroup;
        if ($iGroup == $currentGroup) {
          $this->view->current_group = " bgcolor='lightgrey' ";
        }
        else $this->view->current_group = "";
        $this->view->append ("GROUPS", "GROUP");
        $begin  = $end+1;
      }
    }

    // Loop on the papers assigned to the reviewer
    $reviews = $this->user->findReview();
    $nbPapers = $i = 0;
    foreach ($reviews as $review) {
      $nbPapers++;
      // Take account of groups
      if ($nbPapers < $minPaper or $nbPapers >= $maxPaper) continue;
       
      $this->view->set_var("css_class", Config::CssCLass($i));

      $paper = $review->findParentPaper();

      // Instantiate entities in the view
      $paper->putInView($this->view);

      // Loop on the files associated to the paper, and propose a download link
      $requiredFiles = $requiredFileTbl->fetchAll();
      $countRequired = 0;
      $this->view->DOWNLOADS = "";
      foreach ($requiredFiles as $requiredFile) {
        $requiredFile->putInView($this->view);
        $countRequired++;

        if (!$paper->fileExists($requiredFile)) {
          $this->view->THE_LINK = $texts->reviewer->not_yet_uploaded ;
        }
        else {
          $this->view->assign("THE_LINK", "DOWNLOAD_LINK");
        }
        $this->view->assign("DOWNLOAD_BIS", "DOWNLOAD");
        $this->view->append("DOWNLOADS", "DOWNLOAD_BIS");
      }
      if ($countRequired == 0) {
        $this->view->DOWNLOADS = $texts->reviewer->no_file_to_download ;
      }

      if (!$review->overall) {
        $this->view->submit_review = "<font color='red'>" .
        $texts->reviewer->submit_review . "</font>";
      }
      else {
        $this->view->submit_review =
         "<font color='green'>" .  $texts->reviewer->update_review. "</font>";
      }

      // If the local discussion mode is enabled AND the reviewer has posted her
      // review: give the ability to look at other reviews
      if ($this->config->discussion_mode != Config::NO_DISCUSSION and $review->overall) {
        $this->view->review_header = $texts->reviewer->all_reviews;
        $this->view->set_var("REVIEW","");
        $this->view->assign("REVIEWS","ALL_REVIEWS");
      }
      else {
        // Show only my review, no forum, no other reviews
        $this->view->show_review = $review->showReview($this->view);
        $this->view->assign("REVIEW","MY_REVIEW");
        $this->view->review_header = $texts->reviewer->your_review;
        $this->view->REVIEWS = "";
      }
      $this->view->append("PAPERS", "PAPER");
    }

    if ($nbPapers == 0) {
      $this->view->set_var("PAPERS", "No papers");
    }

    echo $this->view->render("layout");
  }


  /**
   * The paper action: shows the detail son a paper
   */

  function paperAction()
  {
    if (!$this->config->isPhaseOpen(Config::REVIEWING_PHASE)) {
      $this->view->content = $this->texts->reviewer->review_phase_is_closed;
      echo $this->view->render("layout");
      return;
    }

    $reviewTbl = new Review();
    $paperTbl = new Paper();
    $messageTbl = new Message ();

    $this->view->setFile ("content", "paper.xml");
    $this->view->setBlock ("content", "paper_status_link");
    $this->view->setBlock ("content", "message", "messages");
    $this->view->setBlock("content", "review", "show_review");
    $this->view->setBlock("review", "review_mark", "review_marks");
    $this->view->setBlock("review", "review_answer", "review_answers");
    $this->view->initial_message = "";

    // Am I an admin? If yes I want a link to the paper status page
    if (!$this->user->isAdmin()) {
      $this->view->paper_status_link ="";
    }

    $texts = $this->zmax_context->texts;

    if (isSet($_REQUEST['id_paper']) and $this->config->discussion_mode != Config::NO_DISCUSSION) {
      $idPaper = $this->getRequest()->getParam("id_paper");
      $review = $reviewTbl->find($idPaper, $this->user->id)->current();
      $paper = $paperTbl->find($idPaper)->current();
      $paper->putInView($this->view);

      // Check that the paper is REALLY assigned to the reviewer (or admin)
      if (is_object($review) or $this->user->isAdmin())  {

        // Show all other SUBMITTED reviews, do not propose to see only my review
        $this->view->show_review = "";
        $reviews = $paper->findReview();
        foreach ($reviews as $otherReview) {
          if (!empty($otherReview->overall)) {
            $this->view->show_review .= $otherReview->showReview($this->view);
          }
        }

        // Message submitted ? Store it in the DB
        if (isSet($_REQUEST['form_message'])) {
          $message = $messageTbl->createRow();
          $message->id_parent = $this->getRequest()->getParam("id_parent");
          $message->id_user = $this->user->id;
          $message->id_paper =$this->getRequest()->getParam("id_paper");
          $message->message = htmlSpecialChars($this->getRequest()->getParam("message"), ENT_NOQUOTES);
          $message->date  = date("Y-m-d H-i-s");
          $message->save();

          // And now, we must send the message to the reviewers and to the PC chair.
          $mail = new Mail (Mail::SOME_USER,
          $this->texts->mail->subj_new_message . " " . $paper->id,
          $this->view->getScriptPaths());
          $mail->loadTemplate ($this->lang, "new_message");
          $mail->setFormat(Mail::FORMAT_HTML);
          $mail->getEngine()->base_url = $this->view->base_url;
          $mail->getEngine()->author_first_name = $this->user->first_name;
          $mail->getEngine()->author_last_name = $this->user->last_name;
          $paper->putInView ($mail->getEngine());
          $message->putInView($mail->getEngine());

          // Send the message to the PC chair.
          $fakeName = "User" . "->first_name";
          $mail->getEngine()->setVar($fakeName, $this->config->chair_names);
          $mail->setTo ($this->config->chairMail);
          $mail->send();

          // Now, mail to each OTHER reviewer
          $reviews = $paper->findReview();
          $mailOthers = $comma = "";
          foreach ($reviews as $review) {
            if ($review->id_user != $this->user->id) {
              $reviewer  = $review->findParentUser();
              $reviewer->putInView($mail->getEngine());
              $mail->setTo ($reviewer->email);
              $mail->send();
            }
          }
        }

        // Show the tree of messages
        $this->view->messages = Message::display($paper->id, 0, $this->view);
      }
      else {
        $this->view->content = "You do not have access to this paper!<br/>";
      }
    }
    else {
      $this->view->content = "Invalid action<br/>";
    }

     
    echo $this->view->render("layout");
  }


  /**
   * The message action: shows a for to enter a message
   */

  function messageAction()
  {
    if (!$this->config->isPhaseOpen(Config::REVIEWING_PHASE)) {
      $this->view->content = $this->texts->reviewer->review_phase_is_closed;
      echo $this->view->render("layout");
      return;
    }


    $messageTbl = new Message();
    $idPaper = $this->getRequest()->getParam("id_paper");
    if (empty($idPaper)) {
      throw new Zmax_Exception ("Reviewer::message error -- no paper id");
    }

    // Get the paper
    $paperTbl = new Paper();
    $paper = $paperTbl->fetchAll("id = '$idPaper'")->current();
    $paper->putInView($this->view);

    $this->view->setFile ("content", "form_message.xml");
    $this->view->setBlock ("content", "parent_message");

    $texts = $this->zmax_context->texts;

    if (isSet($_REQUEST['id_parent'])) {
      $idParent = $this->getRequest()->getParam("id_parent");
      // Get the parent message
      $parentMessage = $messageTbl->fetchAll ("id = '$idParent'")->current();
      $parentMessage->putInView ($this->view);
      $author = $parentMessage->findParentUser();
      $this->view->message_author = $author->first_name . " " . $author->last_name;
    }
    else {
      // Level 0 message
      $idParent = 0;
      $this->view->parent_message = "";
    }

    $this->view->id_parent = $idParent;
    $this->view->id_paper = $idPaper;
    $this->view->id_user = $this->user->id;
     
    echo $this->view->render("layout");
  }


  /**
   * The review form action. It shows a form to enter a review.
   *
   * @return unknown_type
   */

  function reviewformAction()
  {
    if (!$this->config->isPhaseOpen(Config::REVIEWING_PHASE)) {
      $this->view->content = $this->texts->reviewer->review_phase_is_closed;
      echo $this->view->render("layout");
      return;
    }


    $reviewTbl = new Review();
    $this->view->setFile("content", "reviewform.xml");
    $this->view->setBlock("content", "SECTION", "SECTIONS");


    // Extract the blocks from the template
    $this->view->setFile("review", "form_review.xml");
    $this->view->setBlock ("review", "review_mark", "review_marks");
    $this->view->setBlock ("review", "review_answer", "review_answers");

    $this->view->selected1 =    $this->view->selected2 =     $this->view->selected3 = "";

    // Actions if the id of a paper is submitted
    if (isSet($_REQUEST['id_paper'])) {
      $idPaper = $this->getRequest()->getParam("id_paper");
      $review = $reviewTbl->find($idPaper, $this->user->id)->current();

      // Check that the paper is REALLY assigned to the reviewer
      if (is_object($review))  {
        $this->view->review_form = $review->showReview($this->view, false);
      }
      else {
        $this->view->content = "You do not have access to this paper!<br/>";
      }
    }
    else {
      $this->view->content = "Invalid action<br/>";
    }
     
    echo $this->view->render("layout");
  }


  /**
   * Action that processes a review after submission
   * @author philipperigaux
   *
   */

  function processreviewAction()
  {
    if (!$this->config->isPhaseOpen(Config::REVIEWING_PHASE)) {
      $this->view->content = $this->texts->reviewer->review_phase_is_closed;
      echo $this->view->render("layout");
      return;
    }

    $this->view->setFile("content", "processreview.xml");
    $this->view->setBlock("content", "review");
    // Extract the block with marks.
    $this->view->set_block('review', "review_mark", "review_marks");
    // Extracts the block with answers
    $this->view->set_block('review', "review_answer", "review_answers");


    // Actions if the id of a paper is submitted
    if (isSet($_REQUEST['idPaper'])) {
      $idPaper = $this->getRequest()->getParam("idPaper");
      $reviewTbl = new Review();
      $review = $reviewTbl->find($idPaper, $this->user->id)->current();

      // Check that the paper is REALLY assigned to the reviewer
      if (is_object($review))  {

        // Put the review in the database
        $review->updateFromArray ($_POST);

        // Create the review presentation
        $this->view->review = $review->showReview($this->view, true) ;
        // Resolve the entities replacement
        $this->view->assign("content", "content");
         
        // Send a mail to confirm review submission
        $mail = new Mail (Mail::SOME_USER, $this->texts->mail->subj_ack_review,
        $this->view->getScriptPaths());
        $mail->setFormat(Mail::FORMAT_HTML);
        $mail->setTo($this->user->email);

        $mail->loadTemplate ($this->lang, "ack_review");
        $mailViewEngine = $mail->getEngine();
        $mailViewEngine->setBlock("template", "template_mark", "template_marks");
        $mailViewEngine->setBlock("template", "template_answer", "template_answers");

        $instantiatedMail = $review->showReview($mailViewEngine, true, "template");
        $mail->setTemplate ($instantiatedMail);
        if ($this->config->mailOnReview == "Y") {
          $mail->setCopyToChair(true);
        }

        $mail->send();
      }
      else {
        $this->view->content = $this->texts->def->access_denied;;
      }
    }
    else {
      $this->view->content = "Invalid action<br/>";
    }
    echo $this->view->render("layout");
  }

  /**
   * Propose the list of papers to reviewers -- ask them to provide bids
   */
  function bidsAction()
  {
    $db = $this->zmax_context->db;
    $this->view->setFile("content", "bids.xml");
    $this->view->set_block("content", "PAPER", "PAPERS");
    $this->view->set_block("PAPER", "SECTION", "SECTIONS");
    $this->view->set_block("content", "GROUPS_LINKS", "LINKS");
    $this->view->set_var("size_rating", Config::SIZE_RATING);

    // Initialize the current interval
    if (!isSet($_REQUEST['iMin']))  {
      $iMinCur = 1; $iMaxCur = Config::SIZE_RATING;
    }
    else {
      $iMinCur = $_REQUEST['iMin'];  $iMaxCur = $_REQUEST['iMax'];
    }

    $this->view->set_var("IMIN_CUR", $iMinCur);
    $this->view->set_var("IMAX_CUR", $iMaxCur);

    // If rates have been submitted: insert/update in the DB
    if (isSet($_POST['rates'])) {
      foreach ($_POST['rates'] as $idPaper => $rate) {
        if ($rate != Config::UNKNOWN_RATING) {
          $this->user->addBid ($idPaper, $rate);
        }
        $this->view->message = $this->texts->reviewer->ack_rating;
      }
    }
    else {
      // Print the main message
      $this->view->message = $this->texts->reviewer->rating_message;
    }
    $this->view->assign ("bids_message",  "message");
     
    // Get the list of ratings
    $rateLabels = $db->fetchPairs ("SELECT * FROM RateLabel");
    $rateLabels = array_merge (array(Config::UNKNOWN_RATING => "?"), $rateLabels);

    $form = new Formulaire ( "POST", "RatePapers.php");

    /* Select the papers   */
    $paperTbl = new Paper();
    $papers = $paperTbl->fetchAll("1=1", "id");

    $i = 0;
    foreach ($papers as $paper) {
      // Choose the CSS class
      $this->view->css_class = Config::CssClass($i++);

      // Only show the current group
      if ($iMinCur <= $i and $i <= $iMaxCur) {
        // Instantiate paper variables
        $paper->putInView($this->view);

        // Get the current bid
        $bid = $paper->getBid($this->user->id);
         
        // Show the selection list
        $this->view->list_bids =  Zmax_View_Phplib::selectField ("rates[$paper->id]", $rateLabels,
        $bid);

        /* Instantiate the entities in PAPER_. Put the result in PAPERS   */
        $this->view->append("PAPERS", "PAPER");
      }
      if ($i > $iMaxCur) break;
    }

    // Create the groups
    $nbPapers = Config::countAllPapers();
    $nb_groups = $nbPapers / Config::SIZE_RATING + 1;
    for ($i=1; $i <= $nb_groups; $i++)  {
      $iMin = (($i-1) *  Config::SIZE_RATING) + 1;
      if ($iMin >= $iMinCur and $iMin <= $iMaxCur) {
        $link = "<font color=red>$i</font>";
      }
      else {
        $link =$i;
      }
      $this->view->LINK = $link;

      $this->view->IMIN_VALUE = $iMin;
      $this->view->IMAX_VALUE = $iMin + Config::SIZE_RATING -1;
      $this->view->append("LINKS", "GROUPS_LINKS");
    }

    echo $this->view->render("layout");
  }
}