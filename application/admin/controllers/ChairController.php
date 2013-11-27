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

/**
 * The configuration controller of the MyReview admin.
 */

require_once ("myreview_v1/AdminLists.php");
require_once ("myreview_v1/SQLCommands.php");
require_once ("myreview_v1/Assignment.php");

require_once("Paper.php");
require_once("Mail.php");
require_once("Rating.php");
require_once ("PaperStatus.php");
require_once ("User.php");
require_once ("Assignment.php");
require_once ("RequiredFile.php");
require_once ("Criteria.php");
require_once ("ReviewMark.php");
require_once("DataExport.php");

class Admin_ChairController extends Myreview_Controller_Action_Auth
{
  // How many authors shown simultaneously?
  const SIZE_AUTHORS_GROUP = 20;

  function init ()
  {
    // Call the parent
    parent::init();
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
    $this->view->setFile("content","index.xml");
    $this->view->setBlock("content", "PAPER_CLASSIFICATION", "PAPERS_CLASSIFICATION");
    // Should never be called
    $this->view->initial_message = "";
    $this->view->all_authors_accepted = Mail::ALL_AUTHORS_ACCEPTED;
    $this->view->all_reviewers   = Mail::ALL_REVIEWERS;

    $paperStatusTbl = new PaperStatus();

    $statusList = $paperStatusTbl->fetchAll("final_status='Y'");
   $this->view->mailType = Mail::ALL_AUTHORS_ACCEPTED;
    foreach ($statusList as $status) {
      $status->putInView($this->view);
      $this->view->append("PAPERS_CLASSIFICATION", "PAPER_CLASSIFICATION");
    }

    echo $this->view->render("layout");
  }

  /**
   * Take the identity of another user.
   */
  function impersonateAction()
  {
    $userTbl = new User();
    $this->view->setFile("content","impersonate.xml");
    // Create the infos for the filter list
    if (isSet($_POST['filter_roles'])) {
      $filterRoles = array_flip($_POST['filter_roles']);
    }
    else {
      // Show only reviewers
      $filterRoles = array_flip(array("R"));
    }

    // Take account of the roles
    $this->view->filter_roles_list = Zmax_View_Phplib::checkboxField ("checkbox", "filter_roles[]",
    Config::$Roles, $filterRoles, array());
    $filterRolesList =""; $connector = "";
    foreach (array_flip($filterRoles) as $role) {
      $filterRolesList .= " $connector roles LIKE '%$role%' ";
      $connector = " OR ";
    }

    // Get the list of users
    $users = $userTbl->fetchAll("($filterRolesList)", 'last_name');
    $listUsers = array();
    foreach ($users as $user) {
      $listUsers[$user->id] = "$user->first_name $user->last_name";
    }
    $this->view->list_users = Zmax_View_Phplib::selectField ("user_id", $listUsers, "");
     
    // Default initial message
    $this->view->initial_message = "You are currently connected as {$this->user->first_name} {$this->user->last_name}.";
     
    // Check whether impersonating is required
    if (isSet($_REQUEST['form_impersonate']) and !isSet($_REQUEST['refresh'])) {
      // Change the current user
      $newUserId = $_REQUEST['user_id'];
      $this->user = $userTbl->fetchAll("id = '$newUserId'")->current();

      // Modify the session
      $this->session->id_user = $newUserId;
      $this->session->roles = $this->user->roles;
      $this->session->save();
       
      $this->view->initial_message = "You are now connected as {$this->user->first_name} {$this->user->last_name}.";
    }
    echo $this->view->render("layout");
  }

  /*
   * Shows the list of submitted papers
   *
   */
  function submittedAction()
  {
    $paperTbl = new Paper();
    $reviewTbl = new Review();
    $requiredFileTbl = new RequiredFile();
    $db = $this->zmax_context->db;

    $this->view_initial_message = "";
    $this->view->mailType = Mail::SOME_USER;

    $this->view->setFile("content", "submitted.xml");

    // Check whether the current selection must be changed
    if (isSet($_POST['spStatus'])) {
      $this->config->changeCurrentSelection();
    }

    // If required, hide the selection form
    if (isSet($_REQUEST['hide_selection_form'])) {
      $this->config->show_selection_form = 'N';
      $this->config->save();
    }
    else if (isSet($_REQUEST['show_selection_form'])) {
      $this->config->show_selection_form = 'Y';
      $this->config->save();
    }

    /* Select all the papers and list them.
     First extract the 'block' describing a line from the template */

    $this->view->setBlock("content", "PAPER_DETAIL", "PAPERS");
    $this->view->setBlock("content", "SELECTION_FORM");
    $this->view->setBlock("content", "SHOW_SELECTION_FORM");
    $this->view->setBlock("PAPER_DETAIL", "REVIEWER", "REVIEWERS");
    $this->view->setBlock("PAPER_DETAIL", "DOWNLOAD", "DOWNLOADS");
    $this->view->setBlock("DOWNLOAD", "DOWNLOAD_LINK", "THE_LINK");

    $config = $this->config_v1;

    // Show the form for filtering papers, if required
    if ($this->config->show_selection_form == 'Y') {
      $this->view->set_var("FORM_SELECT_PAPERS",
      $this->FormSelectPapers ($this->myUrl(), $this->view, $this->db_v1));
      $this->view->set_var ("SHOW_SELECTION_FORM", "");
    }
    else {
      $this->view->set_var ("SELECTION_FORM", "");
    }

    $nbPapers = 0;

    // Check whether the paper must be removed
    if (isSet($_REQUEST['instr'])) {
      if ($_REQUEST['instr'] == "remove" and isSet($_REQUEST['idPaper'])) {
        $paper = $paperTbl->find($_REQUEST['idPaper'])->current();
        $paper->delete();
      }
      if ($_REQUEST['instr'] == "removeReviewer") {
        $review = $reviewTbl->find($_REQUEST['id_paper'], $_REQUEST['id_user'])->current();
        if ($review) {
          $review->delete();
        }
      }
    }

    // Do not load abstracts
    PaperRow::$loadAbstracts = false;
    PaperRow::$loadAnswers = false;

    $papers = $paperTbl->fetchAll ("inCurrentSelection='Y'",  "id");
    $i = 0;
    foreach ($papers as $paper) {
      $this->view->set_var("REVIEWERS","");
      $nbPapers++;
      $paper->putInView($this->view);
      // Choose the CSS class
      $this->view->css_class = Config::CssCLass($i++);

      $this->view->set_var("SESSION_ID", session_id());
      $this->view->set_var("SUBMISSION_URL", $this->config->submissionURL);
       
      /* Show the list of reviewers */
      $reviews = $paper->findReview();
      foreach ($reviews as $review) {
        $user = $review->findParentUser();
        $review->putInView($this->view);
        $user->putInView($this->view);
        $this->view->append ("REVIEWERS", "REVIEWER");
      }

      // Loop on the files associated to the paper, and propose a download link
      $requiredFiles = $requiredFileTbl->fetchAll();
      $countRequired = 0;
      $this->view->DOWNLOADS = "";
      foreach ($requiredFiles as $requiredFile) {
        // Check the file is required in the current phase
        // if ($this->config->isPhaseOpen($requiredFile->id_phase)) {
        $requiredFile->putInView($this->view);
        $countRequired++;

        if (!$paper->fileExists($requiredFile)) {
          $this->view->THE_LINK = $this->zmax_context->texts->reviewer->not_yet_uploaded ;
        }
        else {
          $this->view->assign("THE_LINK", "DOWNLOAD_LINK");
        }
        $this->view->assign("DOWNLOAD_BIS", "DOWNLOAD");
        $this->view->append("DOWNLOADS", "DOWNLOAD_BIS");
        // }
      }
      if ($countRequired == 0) {
        $this->view->DOWNLOADS = $this->zmax_context->texts->reviewer->no_file_to_download ;
      }

      /* Instanciate the entities in PAPER_DETAIL. Put in PAPERS   */
      $this->view->append("PAPERS", "PAPER_DETAIL");

    }

    echo $this->view->render("layout");
  }

  function authorsAction()
  {
    // Load the template
    $this->view->set_file ("content",  "authors.xml");

    // Put the session in the view
    $this->session->putInView($this->view);

    /* Select all the papers and list them.
     First extract the 'block' describing a line from the template */

    $this->view->set_block("content", "AUTHOR", "AUTHORS");
    $this->view->set_block("AUTHOR", "PAPER", "PAPERS");
    $this->view->set_block("content", "GROUPS_LINKS", "LINKS");
    $this->view->set_var("LINKS", "");

    $config = $this->config_v1;
    $nbAuthors = 0;

    // Initialize the current interval
    if (!isSet($_REQUEST['iMin'])) {
      $iMinCur = 1; $iMaxCur = self::SIZE_AUTHORS_GROUP;
    }
    else {
      $iMinCur = $_REQUEST['iMin'];  $iMaxCur = $_REQUEST['iMax'];
    }

    // Get all the authors, ordered by last name
    $userTbl = new User();
    $users = $userTbl->fetchAll(" roles LIKE '%A%'", "last_name");

    $i = 0;
    foreach ($users as $user) {
      $i++;
      if ($i >= $iMinCur and $i <= $iMaxCur) {
        // Choose the CSS class
        if ($i %2 == 0) {
          $this->view->set_var("css_class", "even");
        }
        else {
          $this->view->set_var("css_class", "odd");
        }

        $user->putInView($this->view);
         
        /* Get the authorship/ papers for this author */
        $this->view->set_var("PAPERS", "");
        $authors = $user->findAuthor();
        foreach ($authors as $author) {
          $paper = $author->findParentPaper();
          $author->putInView($this->view);
          $paper->putInView ($this->view);

          $this->view->append("PAPERS", "PAPER");
        }
        /* Instanciate the entities in AUTHOR_DETAIL. Put in AUTHORS   */
        $this->view->append("AUTHORS", "AUTHOR");
      }
    }

    // Create the groups
    $nbAuthors = $i;
    $nb_groups = $nbAuthors /  self::SIZE_AUTHORS_GROUP + 1;
    for ($i=1; $i <= $nb_groups; $i++) {
      $iMin = (($i-1) *  self::SIZE_AUTHORS_GROUP) + 1;
      if ($iMin >= $iMinCur and $iMin <= $iMaxCur) {
        $link = "<font color=red>$i</font>";
      }
      else {
        $link =$i;
      }
      $this->view->set_var("LINK", $link);
       
      $this->view->set_var("IMIN_VALUE", $iMin);
      $this->view->set_var("IMAX_VALUE", $iMin + self::SIZE_AUTHORS_GROUP -1);
      $this->view->append("LINKS", "GROUPS_LINKS");
    }

    echo  $this->view->render("layout");
  }


  // Compute prefereces and conflicts
  function computeprefsAction()
  {
    // Load the template
    $this->view->setFile("content",  "computeprefs.xml");
    $this->view->setBlock("content",  "MEMBER", "MEMBERS");

    $paperTbl = new Paper();
    $userTbl = new User();
    $ratingTbl = new Rating();

    // Loop on PC members
    $members = $userTbl->fetchAll("roles LIKE '%R%'");
    $i=0;
    foreach ($members as $member) {
      $this->view->css_class = Config::CssCLass($i++);

      $member->putInView($this->view);
       
      // Loop on papers
      if (1 == 1) {
        $qPapers = "SELECT * FROM Paper";
      }
      else {
        // Loop on papers that match the reviewer's topics
        $qPapers =   "SELECT p.* FROM (Paper p LEFT JOIN PaperTopic t ON id=id_paper), "
        . " UserTopic s "
        . " WHERE (p.topic=s.idTopic OR t.id_topic=s.idTopic) AND s.id_user='$user->id' ";
      }

      $comments = "";
      $rPapers = $this->zmax_context->db->query($qPapers);
      while ($p =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {

        // instantiate a PaperRow object. Ok, not elegant, but easier
        $paper = $paperTbl->find($p->id)->current();

        $conflict = false;

        // Get the rate, if exists
        $rating = $ratingTbl->find ($paper->id, $member->id)->current();
        if (!is_object($rating)) {
          // OK, the preference is unset. Check whether there is a conflict

          $rating = $ratingTbl->createRow();
          $rating->idPaper = $paper->id;
          $rating->id_user = $member->id;

          $conflict = $paper->checkConflictWithuser ($member, $comments);

          if ($conflict) {
            // Conflict! The default preference is 0
            $rating->rate = 0;
          }
          else {
            // Check whether some topics match
            if ($member->matchTopic ($paper->topic)) {
              // Match! The default preference is 3
              $comments .= "Topic match with paper $paper->id ($paper->title)<br/> ";
              $rating->rate = 3;
            }
            else {
              $rating->rate = 2;
            }
          }

          // OK, now save the rating
          $rating->save();
        }
        else {
          $comments = $this->zmax_context->texts->admin->conflicts_prefs_already_set;
        }
      } // Loop on papers
      $this->view->comments = $comments;
      $this->view->append ("MEMBERS", "MEMBER");
    } // End of loop on users

    echo  $this->view->render("layout");
  }

  /**
   * Compute the automatic assignment of papers
   *
   */
  function computeassignmentAction()
  {
    $paperTbl = new Paper();
    $userTbl = new User();
    $ratingTbl = new Rating();
    $assignmentTbl = new Assignment();

    // Form has been posted? Store the new assignment
    if (isSet($_POST['commitAssignment'])) {
      if (isSet($_POST['idMin'])) {
        // Commit for a group
        $idMin = $_POST['idMin'];
        $idMax = $_POST['idMax'];
      }
      else {$idMin = $idMax = -1;}
      $this->commitAssignment($idMin, $idMax, $this->db_v1);
      $this->view->set_file ("content", "index.xml");
      $this->view->initial_message = "Assignment committed!";
      // SummaryPapersAssignment (&$this->view, $this->db_v1);
    }
    else {

      if ($this->config->assignment_mode == Config::TOPIC_BASED) {
        // Assignment based on topics
        $this->view->setFile ("content", "topicbased.xml");
        $this->view->setBlock ("content", "MEMBER", "MEMBERS");
        $this->view->setBlock ("MEMBER", "PAPER", "PAPERS");
        $this->zmax_context->db->query ("DELETE FROM Assignment");

        // Loop on PC members
        $members = $userTbl->fetchAll("roles LIKE '%R%'");
        $i=0;
        foreach ($members as $member) {
          $member->putInView($this->view);
          $this->view->PAPERS = "";
          $nbPapers = 0;

          // Loop on papers that match the reviewer's topics
          $qPapers =   "SELECT p.* FROM Paper p, UserTopic s "
          . " WHERE p.topic=s.id_topic  AND s.id_user='$member->id' ";

          $rPapers = $this->zmax_context->db->query($qPapers);
          while ($p =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
            // instantiate a PaperRow object. Ok, not elegant, but easier
            $paper = $paperTbl->find($p->id)->current();

            // Check whether there is a conflict
            $comments = "";
            $conflict = $paper->checkConflictWithuser ($member, $comments);
            if ($conflict) {
              $this->view->PAPERS .= "<li>$comments</li>";
            }
            else {
              $paper->putInView($this->view);
              $nbPapers++;
              // OK, now save the rating
              $assignment = $assignmentTbl->createRow();
              $assignment->idPaper = $paper->id;
              $assignment->id_user = $member->id;
              $assignment->weight = 5;
              $assignment->save();
              $this->view->append ("PAPERS", "PAPER");
            }

            if ($nbPapers >= 4) break;
          } // Loop on papers
          $this->view->nb_papers = $nbPapers;
          $this->view->append ("MEMBERS", "MEMBER");
        } // End of loop on users
      }
      else {
        // Assignment based on reviewers preferences
        $this->view->set_file ("content", "computeassignment.xml");
        $this->view->set_file ("assigngroup", "assigngroup.xml");
        // Compute and propose the assignment
        if (isSet($_GET['nbRev'])) {
          $maxReviewers = $_GET['nbRev'];
        }
        else {
          $maxReviewers = $this->config->nbReviewersPerItem;
        }

        ComputeAssignment ($this->view, $this->db_v1, $maxReviewers);
      }
    }
    echo  $this->view->render("layout");

  }
  /*
   *
   * @author philipperigaux
   *
   */
  function showassignmentAction()
  {
    $this->view->set_file ("content", "showassignment.xml");
    $paperTbl = new Paper();
    $reviewTbl = new Review();
     
    // Set the selected paper topic if necessary

    if (isSet($_POST['paperTopic'])) {
      $this->config->selectedPaperTopic=$_POST['paperTopic'];
      $this->config->save();
    }

    // Set the selected reviewer topic if necessary
    if (isSet($_POST['reviewerTopic'])) {
      $this->config->selectedReviewerTopic=$_POST['reviewerTopic'];
      $this->config->save();
    }

    // Update the assignment if necessary
    if (isSet($_POST['changeAssignment'])) {
      $assignments = $_POST['assignments'];
      if (is_array($assignments)) {
        foreach ($assignments as $idPaper => $arrayReviews) {
          $paper = $paperTbl->find($idPaper)->current();
          $tabIds = array();
          foreach ($arrayReviews as $id_user => $val) {
            if ($val == 1) {
              $tabIds[] = $id_user;
            }
            else {
              // echo "Delete this review<br/>";
              $review = $paper->getReview($id_user);
              if (is_object($review)) {
                $review->delete();
              }
            }
          }
          // Now, insert the new reviewers for this paper
          if (count($tabIds) > 0) {
            $paper->assignReviewers($tabIds);
          }
        }
      }
    }

    // Show the table with the current assignments
    SummaryPapersAssignment ($this->view, $this->db_v1, $this->zmax_context->texts);
    echo  $this->view->render("layout");
  }

  /**
   * Close the submission phase
   */
  function closesubmissionAction()
  {
    // Close the submission
    $this->config->isSubmissionOpen = 'N';
    $this->config->save();

    // Delete all reviews for papers not uploaded
    $paperTbl = new Paper();
    $papers = $paperTbl->fetchAll ("isUploaded = 'N' ");
    foreach ($papers as $paper) {
      $reviews = $paper->findReview();
      foreach ($reviews as $review) {
        $review->delete();
      }
    }

    // Now, move all the papers in status 'submission' to status 'evaluation'
    $subStatus = PaperStatus::IN_SUBMISSION;
    $evalStatus = PaperStatus::IN_EVALUATION;
     
    $paperTbl = new Paper();
    $papers = $paperTbl->fetchAll ("status = $subStatus");
    foreach ($papers as $paper) {
      $paper->status = $evalStatus;
      $paper->save();
    }

    $this->view->content = $this->zmax_context->texts->admin->submission_now_closed;
    echo  $this->view->render("layout");
  }

  /**
   * Show the list of papers wit their status
   */
  function paperstatusAction()
  {
    $paperTbl = new Paper();
    $paperStatusTbl = new PaperStatus();

    $this->view->set_file("content",  "paperstatus.xml");
    $this->view->set_block("content", "SELECTION_FORM");
    $this->view->set_block("content", "MESSAGE");
    $this->view->set_block("content", "SHOW_SELECTION_FORM");
    $this->view->set_file("ranked_papers_list",  "ranked_papers.xml");

    if (isSet($_REQUEST['export_latex'])) {
      $exportLatex = true;
      $this->view->message = "The LaTeX file <i>paperstatus.tex</i> has been generated in files/selection";
    }
    else {
      $exportLatex = false;
      $this->view->MESSAGE = "";
    }

    // Check whether the current selection must be changed
    if (isSet($_POST['spStatus'])) {
      $this->config->changeCurrentSelection();
    }

    if (isSet($_REQUEST['remove']))  {
      $reviewTbl = new Review();
      $review = $reviewTbl->find($_REQUEST['idPaper'], $_REQUEST['remove'])->current();
      if (is_object($review)) {
        $review->delete();
      }
    }
    else if (isSet($_REQUEST['idPaper'])) {
      // If the status is submitted: update in the DB
      $idPaper = $_REQUEST['idPaper'];
      $status = $_REQUEST['status'];
      foreach ($idPaper as $key => $val) {

        if (isSet($status[$val])) {
          // echo "ID = $val<br/>";
          $paper = $paperTbl->find($val)->current();
          if (is_object($paper)) {
            $paper->status = $status[$val];
            $paper->save();
          }
          else {
            throw new Zmax_Exception ("Unknown paper id sent to the change status function?");
          }
        }
      }
    }

    // If required, hide the selection form
    if (isSet($_REQUEST['hide_selection_form'])) {
      $this->config->show_selection_form='N';
      $this->config->save();
    }
    else if (isSet($_REQUEST['show_selection_form'])) {
      $this->config->show_selection_form='Y';
      $this->config->save();
    }

    // Show the form for filtering papers, if required
    if ($this->config->show_selection_form== 'Y') {
      $this->view->set_var("FORM_SELECT_PAPERS",
      $this->formSelectPapers ($this->view->base_url . "/admin/chair/paperstatus",
      $this->view, $this->db_v1));
      $this->view->set_var ("SHOW_SELECTION_FORM", "");
    }
    else {
      $this->view->set_var ("SELECTION_FORM", "");
    }

    // Create the list of lists to toggle all the selected papers
    $comma = $listLinks = "";
    $statusList = $paperStatusTbl->fetchAll("final_status='Y'");
    foreach ($statusList as $status) {
      $listLinks .= $comma . " <a href='#' onClick=\"TogglePaperStatus('$status->id')\">"
      . $status->label . "</a>";
      $comma = ",";
    }
    $this->view->set_var("TOGGLE_LIST", $listLinks);

    // Always list the papers
    $this->papersReviews ($this->view,  "ranked_papers_list");

    // In addition, produce the Latex file
    if ($exportLatex) {
      $anonymized = false;
      if (isSet($_REQUEST['anonymized'])) {
        $anonymized = true;
      }
      // Instantiate the data export object
      $dataExport = new DataExport($this->view->getScriptPaths());

      // Load the latex templates
      $dataExport->getView()->setFile("latex_papers_list",  "paperstatus.tex");

      $this->papersReviews ($dataExport->getView(), "latex_papers_list", false, $anonymized);
      // The directory where Latex files are produced
      $tex_dir = "selection/";
      $dataExport->getView()->assign ("result", "latex_papers_list");
      $result = $dataExport->replaceBadChars($dataExport->getView()->result);
      $dataExport->writeFile ($tex_dir, "paperstatus.tex", $result);
      $dataExport->downloadFile($tex_dir, "paperstatus.tex");
    }

    echo  $this->view->render("layout");
  }

  /**
   * Show the list of papers with a given status
   */
  function acceptedAction()
  {
    // There should be a 'status' parameter
    $paperTbl = new Paper();
    $paperStatusTbl = new PaperStatus();
    $status = $this->getRequest()->getParam("status");
    $paperStatus = $paperStatusTbl->find($status)->current();

    // Is the 'text' format required ?
    if (isSet($_REQUEST['format'])  and $_REQUEST['format'] == "text") {
      $textFormat = true;
      $this->view->setFile ("content", "accepted_simple.txt");
    }
    else {
      $textFormat = false;
      $this->view->setFile ("content", "accepted_simple.xml");
    }
    $this->view->setBlock ("content", "PAPER", "PAPERS");

    $paperStatus->putInView($this->view);
   
    $paperTbl = new Paper();
    $no = 1;
    $papers = $paperTbl->fetchAll("status='$status'");
    foreach ($papers as $paper) {
      $paper->putInView($this->view);
      $this->view->no = $no++;
      $this->view->append("PAPERS", "PAPER");
    }

    //Function from MyReview V1. Allows assignment of accepted papers to sessions.
    /* AdmListAcceptedPapers ($_REQUEST['status'], $this->view, $this->db_v1,
     1, $this->zmax_context->texts);*/

    if ($textFormat) {
          Header ("Content-type: text/plain");
          $this->view->assign("result", "content");
          echo utf8_decode($this->view->result);
    }
    else {    
    echo $this->view->render("layout");
    }
  }

  // From V1. Get rid of it.
  private function Ancre ($url, $libelle, $classe=-1, $onClickAction="")
  {
    $optionClasse = "";
    if ($classe != -1) $optionClasse = " CLASS='$classe'";
    return "<A HREF='$url' $optionClasse $onClickAction>$libelle</A>\n";
  }


  // Function to commit the automatic assignment computation
  function commitAssignment($idMin, $idMax, $db)
  {
    // Delete existing reviews and existing review marks
    if ($idMin == -1)
    {
      // Remove all current assignment
      $qCleanReview="DELETE FROM Review WHERE submission_date IS NULL";
    }
    else
    {
      // Remove all asignments of the current group
      $qCleanReview="DELETE FROM Review "
      . "WHERE idPaper BETWEEN $idMin AND $idMax AND submission_date IS NULL";
    }
    $db->execRequete($qCleanReview);

    // Loop on papers
    $qPapers = "SELECT * FROM Paper ORDER BY id";
    $rPapers = $db->execRequete($qPapers);
    while ($paper = $db->objetSuivant($rPapers)) {
      // Take all the reviewers assigned to the paper
      $qAssignment = "SELECT * FROM Assignment WHERE idPaper='$paper->id'";
      $rAssignment = $db->execRequete($qAssignment);
      $tabIds = array();
      while ($assignment = $db->objetSuivant($rAssignment)) {
        $tabIds[] = $assignment->id_user;
      }
      // Insert the assignment in the Review table
      SQLReview ($paper->id, $tabIds, $db);
    }
  }

  /**
   * Create a form that allows to select a subset of papers
   */
  private  function formSelectPapers ($action, $tpl, $db)
  {
    // Load the form template
    $this->view->set_file("FormSelectPapers", "form_select_papers.xml");
    $this->view->set_block("FormSelectPapers", "ALL_QUESTIONS", "QUESTIONS_BLOCK");
    $this->view->set_block("ALL_QUESTIONS", "PAPER_QUESTION", "PAPER_QUESTIONS");
    $this->view->set_block("ALL_QUESTIONS", "REVIEW_QUESTION", "REVIEW_QUESTIONS");
    $this->view->set_var ("QUESTIONS_BLOCK", "");
    $this->view->set_var ("PAPER_QUESTIONS", "");
    $this->view->set_var ("REVIEW_QUESTIONS", "");

    // Let the lists of choices
    $config = GetConfig($db);
    $statusList = GetListStatus ($db);
    $sl = array(SP_ANY_STATUS => "Any", SP_NOT_YET_ASSIGNED => "Not yet assigned");
    foreach ($statusList as $id => $sVals) {
      $sl[$id]  = $sVals['label'];
    }

    $conflicts = $this->codes->get("conflicts");
    $missing = $this->codes->get("missing_review");
    $uploaded = $this->codes->get("uploaded");
    $filters = $this->codes->get("filters");

    $this->view->set_var ("action", $action);

    // Get the list of reviewers
    $rReviewers = $db->execRequete ("SELECT id, CONCAT(last_name, ', ', first_name) as name FROM User "
    . " WHERE roles LIKE '%R%'");
    $listReviewers = array();
    while ($reviewer = $db->objetSuivant($rReviewers)) {
      $listReviewers[$reviewer->id] = $reviewer->name;
    }
    $listReviewers["All"] = "Any";
    ksort ($listReviewers);

    // Get the list of topics
    $listTopics = array ("0" => "Any");
    $qTopics = "SELECT * FROM ResearchTopic ORDER BY label";
    $rTopics = $db->execRequete ($qTopics);
    while ($topic= $db->objetSuivant($rTopics))
    $listTopics[$topic->id] = $topic->label;

    // Set the default values
    $this->view->set_var ("PAPERS_WITH_TITLE", $config['papersWithTitle']);
    $this->view->set_var ("PAPERS_WITH_AUTHOR", $config['papersWithAuthor']);
    $this->view->set_var
    ("SP_UPLOADED", SelectField ('spUploaded', $uploaded,
    $config['papersUploaded']));
    $this->view->set_var ("SP_STATUS",
    SelectField ('spStatus', $sl, $config['papersWithStatus']));
    $this->view->set_var ("SP_FILTER", SelectField ('spFilter', $filters,
    $config['papersWithFilter']));
    $this->view->set_var ("SP_RATE", $config['papersWithRate']);
    $this->view->set_var ("SP_REVIEWERS",   SelectField ('spReviewer', $listReviewers,
    $config['papersWithReviewer']));
    $this->view->set_var ("SP_TOPICS",  SelectField ('spTopic', $listTopics,
    $config['papersWithTopic']));
    $this->view->set_var ("SP_CONFLICTS", SelectField ('spConflict', $conflicts,
    $config['papersWithConflict']));
    $this->view->set_var ("SP_MISSING", SelectField ('spMissing', $missing,
    $config['papersWithMissingReview']));

    // Paper questions
    $nb_pquestions = $this->createQuestionsField ($this->view, $db,
    $config['papersQuestions'],
					 "PAPER");
    // Review questions
    $nb_rquestions = $this->createQuestionsField ($this->view, $db,
    $config['reviewsQuestions'],
					 "REVIEW");
    if ($nb_pquestions > 0 or $nb_rquestions > 0)
    $this->view->assign ("QUESTIONS_BLOCK", "ALL_QUESTIONS");

    // Create the form and return
    $this->view->assign ("FSP", "FormSelectPapers");
    return $this->view->get_var("FSP");
  }

  private function CreateQuestionsField ($tpl, $db, $db_encoding, $field_type)
  {
    if ($field_type == "PAPER") {
      $tb_question = "PaperQuestion";
      $tb_choice = "PQChoice";
      $select_field = "paperQuestions";
      $namespace = "author";
    }
    else    {
      $tb_question = "ReviewQuestion";
      $tb_choice = "RQChoice";
      $select_field = "reviewQuestions";
      $namespace = "reviewer";
    }

    // First decode the current default values, from Config
    $questions = array();
    if (!empty($db_encoding)) {
      $coded_questions = explode (";", $db_encoding);
      $questions = array();
      foreach ($coded_questions as $question) {
        $q = explode (",", $question);
        $questions[trim($q[0])] = trim($q[1]);
      }
    }

    $nb_questions= 0;
    $rq = $db->execRequete ("SELECT * FROM $tb_question");
    while ($question = $db->objetSuivant($rq))
    {
      // Get the list of choices
      $rc = $db->execRequete("SELECT * FROM $tb_choice "
      . " WHERE id_question='$question->id'");
      $list_choices = array ("0" => "Any");
      while ($choice = $db->objetSuivant($rc)) {
        $list_choices[$choice->id_choice] = $choice->choice;
      }

      if (isSet($questions[$question->id]))
      $def_val = $questions[$question->id];
      else
      $def_val = SP_ANY_CHOICE;

      $sel_choices = SelectField ("$select_field" . "[$question->id]",
      $list_choices, $def_val);
      $this->view->set_var ("QUESTION", "{" . "$namespace." . $question->question_code . "}");
      $this->view->set_var ("CHOICES", $sel_choices);
      // Instantiate twice, for translations
      $this->view->assign ("TMP", "{$field_type}_QUESTION");
      $this->view->append ("{$field_type}_QUESTIONS", "TMP");
      $nb_questions++;
    }
    return $nb_questions;
  }

  /**
   *  List of papers with their reviews
   */

  private function papersReviews ($view, $templateName, $html=true, $anonymized=false)
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    $paperTbl = new Paper();
    $paperStatusTbl = new PaperStatus();
    $criteriaTbl = new Criteria();
    $reviewTbl = new Review();
    $reviewMarkTbl = new ReviewMark();

    $registry = Zend_registry::getInstance();
    $config = $registry->get("Config");
    $config->putInView($view);

    // Set the mail types
    $view->someUser = Mail::SOME_USER;

    // Extract the block for each paper
    $view->setBlock($templateName, "PAPER_DETAIL", "PAPERS");
    $view->setBlock($templateName, "REVIEW_CRITERIA", "REVIEW_CRITERIAS");
    $view->setBlock("PAPER_DETAIL", "PAPER_INFO", "PAPER_DATA");
    $view->setBlock("PAPER_DETAIL", "REVIEW_MARK", "REVIEW_MARKS");
    $view->setBlock("PAPER_DETAIL", "REVIEWER", "REVIEWER_INFO");

    // Header of the  table, taken from table Criteria
    $criterias = $criteriaTbl->fetchAll();
    $listCriterias = array();
    foreach ($criterias as $criteria)  {
      $criteria->putInView($view);
      $listCriterias[] = $criteria;
      $view->append ("REVIEW_CRITERIAS", "REVIEW_CRITERIA");
    }

    // Sort the papers on the average 'overall' field
    $query = "SELECT p.*, round(AVG(overall),4) AS overall "
    . "FROM Paper p LEFT JOIN Review r ON p.id=r.idPaper "
    .  " WHERE inCurrentSelection='Y' GROUP BY p.id";
    $result = $db->query($query);

    $arrPaper = $rankPaper = array();
    while ($paper = $result->fetch (Zend_Db::FETCH_OBJ)) {
      $arrPaper[$paper->id] = $paper;
      $rankPaper[$paper->id] = $paper->overall;
    }

    // Get the status list
    $statusList = $db->fetchPairs ("SELECT * FROM PaperStatus WHERE final_status='Y'");

    // Sort in descending order
    arsort($rankPaper);
    reset ($rankPaper);

    // List the papers in order
    $iPaper = 0;
    foreach ($rankPaper as $idPaper => $overall) {
      $paper = $arrPaper[$idPaper];

      // Choose the CSS class
      $view->css_class=  Config::CssCLass($iPaper++);
      $view->paper_id = $paper->id;
      $view->paper_title = $paper->title;

      if (!$anonymized) {
        $view->paper_authors = PaperRow::getPaperAuthors($db, $paper);
      }
      else {
        $view->paper_authors = "[anonymized]";
      }

      $view->paper_email_contact = $paper->emailContact;
      $view->paper_rank  = $iPaper;
      $view->paper_overall  = $overall;
      $view->form_status =  Zmax_View_Phplib::checkboxField ("radio", "status[$paper->id]",
      $statusList, $paper->status, array("length" => 2));

      // Now, loop on reviews
      $qRev = "SELECT * FROM Review r, User u "
      . " WHERE idPaper='{$paper->id}' AND u.id=r.id_user";
      $resRev = $db->query($qRev);
      $countReviews = 0;
      $mail_reviewers = $comma = "";
      while ($review = $resRev->fetch (Zend_Db::FETCH_OBJ)) {
        $countReviews ++;
        $mail_reviewers .= $comma . $review->email;
        $comma = ", ";
      }
      $view->paper_nb_reviewers = Max(1, $countReviews);
      //echo "Mail reviewers = $mail_reviewers<br/>";
      $view->paper_email_reviewers = $mail_reviewers;
      $view->append("PAPER_DATA", "PAPER_INFO");

      $resRev = $db->query($qRev);
      $iReview = 0;
      while ($review = $resRev->fetch (Zend_Db::FETCH_OBJ)) {
        $iReview++;
        $view->reviewer_id = $review->id;
        if ($anonymized == false) {
          $view->reviewer_fname = $review->first_name;
          $view->reviewer_lname = $review->last_name;
          $view->external_reviewer_fname = $review->fname_ext_reviewer;
          $view->external_reviewer_lname = $review->lname_ext_reviewer;
          $view->review_comments = $review->comments;
        }
        else {
          $view->reviewer_fname = $iReview;
          $view->reviewer_lname = "";
          $view->external_reviewer_fname = "";
          $view->external_reviewer_lname = "";
          $view->review_comments = "";
        }
        $view->reviewer_email = $review->email;
        $view->review_overall = $review->overall;
        $view->review_summary = $review->summary;
        $view->review_details = $review->details;

        if ($review->reviewerExpertise >= 1 and $review->reviewerExpertise <=3)
        $view->reviewer_expertise = Config::$Expertise[$review->reviewerExpertise];

        // Avoid to introduce Latex commands  in Latex files ....
        if (!$html) {
          $view->review_summary = str_replace ("\\", "", $view->review_summary );
          $view->review_details = str_replace ("\\", "", $view->review_details);
          $view->review_comments = str_replace ("\\", "",  $view->review_comments);
        }

        $view->assign("REVIEWER_INFO", "REVIEWER");

        reset($listCriterias);
        $view->set_var("REVIEW_MARKS", "");
        foreach ($listCriterias as $criteria) {
          $reviewMark = $reviewMarkTbl->find($review->idPaper, $review->id_user, $criteria->id)->current();
          if (!is_object($reviewMark)) {
            $reviewMark = $reviewMarkTbl->createRow(); // for default values
          }
          $reviewMark->putInView($view);
          $view->criteria_label = $criteria->label;

          $view->append ("REVIEW_MARKS", "REVIEW_MARK");
        }

        $view->append("PAPERS", "PAPER_DETAIL");
        // The paper data is shown only once for all the reviews
        $view->set_var("PAPER_DATA", " ");
      }


      // Show the paper even without reviewer
      if ($countReviews == 0) {
        $review = $reviewTbl->createRow();
        $review->putInView($view);
        $view->set_var("REVIEW_MARKS", "");
        foreach ($listCriterias as $id => $label) {
          $reviewMark = $reviewMarkTbl->createRow();
          $reviewMark->putInView($view);
          $view->append ("REVIEW_MARKS", "REVIEW_MARK");
        }
        $view->set_var("REVIEWER_INFO", "");
        $view->append("PAPERS", "PAPER_DETAIL");
        $view->set_var("PAPER_DATA", "");
      }
       
      // Summary for the paper
      if ($html) {
        $statPaper = Paper::getStats($paper->id, $listCriterias);
        $markFieldName = "ReviewMark" . "->mark" ;
        $overallFieldName = "review_overall" ;
        $view->set_var("NB_REVIEWERS", 1);
        if ($html)
        $view->set_var("PAPER_DATA","<td>&nbsp;</td><th>Summary</th>");
        $view->set_var("REVIEWER_INFO"," ");
        $view->set_var($overallFieldName, $paper->overall);

        reset($listCriterias);
        $view->set_var("REVIEW_MARKS", "");
        foreach ($listCriterias as $c) {
          $view->setVar( $markFieldName, $statPaper[$c->id]);
          $view->append ("REVIEW_MARKS", "REVIEW_MARK");
        }
        $view->append("PAPERS", "PAPER_DETAIL");
        $view->set_var("PAPER_DATA", "");
      }
    }
  }

  // Main function that produces the latex documents
  function latexAction ()
  {
    // Extract the templates
    // $this->view->setBlock ("latex", "PCMember", "PCMembers");

    $this->view->info ="Now creating the PC committee list...";
    $this->view->append("messages", "message");

    // Output a file with the program committee
    $pclist = $dataExport->exportProgramCommittee("member.tex");
    $dataExport->writeFile ($tex_dir, "pc.tex", $pclist);
     
    // Abstracts
    $this->view->info ="Now creating the list of abstracts...";
    $this->view->append("messages", "message");

    $abstracts = $dataExport->exportAbstracts("abstract.tex");
    $dataExport->writeFile ($tex_dir, "abstracts.tex", $abstracts);

    // Booklet of abstracts
    $this->view->info ="Now creating the booklet of abstracts...";
    $this->view->append("messages", "message");

    $this->view->assign("booklet_tex", "booklet");
    $booklet = $this->view->get_var("booklet_tex");
    $dataExport->writeFile ($tex_dir, "booklet.tex", $booklet);

    // Now, the same in plain text

    unset ($dataExport);
    $dataExport = new dataExport($this->view->getScriptPaths());

    $abstracts = $dataExport->exportAbstracts("abstract.txt", DataExport::TEXT);
    $dataExport->writeFile ($tex_dir, "abstracts.txt", $abstracts);
     
    echo $this->view->render("layout");

    return ;
  }
}

