<?php

require_once("Paper.php");

/**
 * This class represents a Config object
 */

class ConfigRow extends Zmax_Db_Table_Row_Abstract
{

  /**
   * Check that a session is valid
   */

  function putInView(&$view)
  {
    // Call the parent method: default behavior
    parent::putInView($view);

    // Now refine. Dates are shown using the required format.
    $reviewDeadline =  Zmax_View_Phplib::formatDate($this->reviewDeadline, $this->date_format);
    $name = "Config" . "->reviewDeadline";
    $view->setVar($name, $reviewDeadline);

    $submissionDeadline =  Zmax_View_Phplib::formatDate($this->submissionDeadline, $this->date_format);
    $name = "Config" . "->submissionDeadline";
    $view->setVar($name, $submissionDeadline);

    $name = "Config" . "->nb_papers";
    $view->setVar($name, Config::countAllPapers());

    $name = "Config" . "->nb_words";
    $view->setVar($name, 250);
  }


  /**
   * Function that checks whether the submission phase is not over
   */

  function submissionClosed()
  {
    if ($this->isSubmissionOpen == 'N') {
      return true;
    }
    return false;
  }

  /*
   * Check whether a phase is open
   *
   */
  function isPhaseOpen($idPhase)
  {
    if ($idPhase == Config::SUBMISSION_PHASE and $this->isSubmissionOpen=='Y')
    return true;
    if ($idPhase == Config::REVIEWING_PHASE and $this->isReviewingOpen=='Y')
    return true;
    if ($idPhase == Config::SELECTION_PHASE and $this->isSelectionOpen=='Y')
    return true;
    if ($idPhase == Config::PROCEEDINGS_PHASE and $this->isProceedingsOpen=='Y')
    return true;

    return false;
  }

  /**
   * This function is called when the form selection has been submitted.
   * We change the "current selection" (papers that appear in the lists)
   */
  function changeCurrentSelection ()
  {
    // If the current selection is updated, record it
    if (isSet($_POST['spStatus'])) {
      $this->papersWithStatus=$_POST['spStatus'];
      $this->papersWithFilter=$_POST['spFilter'];
      $this->papersWithRate=$_POST['spRate'];
      $this->papersWithReviewer = $_POST['spReviewer'];
      $this->papersWithTopic=$_POST['spTopic'];
      $this->papersWithConflict=$_POST['spConflict'];
      $this->papersWithMissingReview=$_POST['spMissing'];
      $this->papersWithTitle=$_POST['spTitle'];
      $this->papersWithAuthor=$_POST['spAuthor'];
      $this->papersUploaded=$_POST['spUploaded'];
      $this->papersQuestions=$this->encodeQuestions ("paperQuestions");
      $this->reviewsQuestions=$this->encodeQuestions ("reviewQuestions");
      $this->save();

      // Compute the current selection
      $this->getCurrentSelection ();
    }
  }

  // Function that marks all the papers in the current selection
  private function getCurrentSelection ()
  {
    $db = Zend_Db_Table::getDefaultAdapter();
    $paperTbl = new Paper();

    // Initialize the current selection
    $db->query ("UPDATE Paper SET inCurrentSelection='N'");

    // crWhere : applies to selection on paper
    // crJoin  : applies to outer join Paper Review

    $crJoin = $crWhere = " 1 = 1 ";
    if (isSet($_POST['paperQuestions'])) {
      $paperQuestions = $_POST['paperQuestions'];
    }
    else {
      $paperQuestions = array();
    }

    if (isSet($_POST['reviewQuestions'])) {
      $reviewQuestions = $_POST['reviewQuestions'];
    }
    else {
      $reviewQuestions = array();
    }

    // Take into account the filtering criterias.
    if ($this->papersWithStatus == SP_ANY_STATUS) {
      $crWhere .= "";
    }
    else if ($this->papersWithStatus == SP_NOT_YET_ASSIGNED) {
      $crWhere .= " AND EXISTS (SELECT 'c' FROM PaperStatus s WHERE p.status=s.id AND final_status !='Y') ";
    }
    else {
      $crWhere .= " AND status='" . $this->papersWithStatus . "'";
    }

    // Full text search on titles?
    if ($this->papersWithTitle != "Any") {
      $crWhere .= " AND title LIKE '%" . $this->papersWithTitle . "%' ";
    }

    // Show uploaded or not yet uploaded papers?
    if ($this->papersUploaded == "Y") $crWhere .= " AND isUploaded = 'Y' ";
    if ($this->papersUploaded == "N") $crWhere .= " AND isUploaded = 'N' ";

    // Show papers for a specific reviewer?
    if ($this->papersWithReviewer != "All")
    $crWhere .= " AND id_user='" . $this->papersWithReviewer . "' ";

    // Sort the papers on the average 'overall' field
    $query = "SELECT DISTINCT p.* FROM Paper p LEFT JOIN Review r ON p.id=r.idPaper"
    .  " WHERE $crWhere ";

    $res = $db->query ($query);
    while ($p =  $res->fetch (Zend_Db::FETCH_OBJ)) {
      $paper = $paperTbl->find($p->id)->current();

      $keep = $keep2 = $keep3 = $keep4 = $keep5 = false;
      $keep6 = $keep7 = true;

      // Should we consider conflicts?
      if ($this->papersWithConflict == "Y" and $this->paperInConflict($paper->id)) {
        $keep = true;
      }
      else if ($this->papersWithConflict == "N" and $this->paperInConflict($paper->id)) {
        $keep = true;
      }
      else if ($this->papersWithConflict == "A") $keep = true;

      // Should we consider missing review?
      if ($this->papersWithMissingReview == "Y"  and $this->missingReview($paper->id)) {
        $keep2 = true;
      }
      else if ($this->papersWithMissingReview == "N" and !$this->missingReview($paper->id)) {
        $keep2 = true;
      }
      else if ($this->papersWithMissingReview == "A") {
        $keep2 = true;
      }

      // Take care of the filter
      $overall = $paper->averageMark ();

      if ($this->papersWithFilter == SP_ABOVE_FILTER and $overall >= $this->papersWithRate) {
        $keep3 = true;
      }
      else if ($this->papersWithFilter == SP_BELOW_FILTER and $overall <= $this->papersWithRate) {
        $keep3 = true;
      }
      else if ($this->papersWithFilter == 0) {
        $keep3 = true;
      }

      // Take care of authors
      if ($this->papersWithAuthor != "Any"  and !empty($this->papersWithAuthor)) {
        $authors = $paper->getAuthors();
        if (strpos($authors,  $this->papersWithAuthor)) {
          $keep4 = true;
        }
      }
      else $keep4 = true;

      // Show papers for a specific topic?
      if ($this->papersWithTopic != 0) {
        $the_topic = $this->papersWithTopic;
        if ($paper->topic == $the_topic) {
          $keep5 = true; // OK with the main topic
        }
        else {
          /* FROM V1 -- NO LONGER USED
           $rt = $db->query ("SELECT * FROM PaperTopic WHERE id_paper='$paper->id' "
           . " AND id_topic='$the_topic'");
           if ($db->objetSuivant($rt))
           $keep5 = true; // found in other topics
           else
           $keep5 = false;
           */
          ;
        }
      }
      else $keep5 = true;

      // Now look at the paper question
      reset ($paperQuestions);
      foreach ($paperQuestions as $id_question => $id_choice) {
        if ($id_choice != SP_ANY_CHOICE) {
          // Check that the paper's answer matches the choice
          $rq = $db->query
          ("SELECT * FROM PaperAnswer WHERE id_paper='$paper->id' "
          . "AND id_question='$id_question' AND id_answer='$id_choice'");
          if (!is_object($rq->fetch (Zend_Db::FETCH_OBJ))) {
            $keep6 = false;
            break;
          } // No need to look further
        }
      }

      // Now look at the review question
      reset ($reviewQuestions);
      foreach ($reviewQuestions as $id_question => $id_choice) {
        if ($id_choice != SP_ANY_CHOICE) {
          // Check that some reviewer's answer matches the choice
          $rq = $db->query ("SELECT * FROM Review r, ReviewAnswer a "
          . " WHERE id_paper='$paper->id' AND r.id_user=a.id_user "
          . "AND id_question='$id_question' AND id_answer='$id_choice'");
          if (!is_object($rq->fetch (Zend_Db::FETCH_OBJ))) {
            $keep7 = false;
            break;
          } // No need to look further
        }
      }

      //echo "Keep= (1: $keep) (2: $keep2) (3: $keep3) (4: $keep4) (5:$keep5) (6: $keep6) (7: $keep7)<br/>";

      if ($keep and $keep2 and $keep3 and $keep4 and $keep5 and $keep6 and $keep7) {
        // echo "Upate yes<br/>";
        $qCS = "UPDATE Paper SET inCurrentSelection='Y' WHERE id='$paper->id'";
        $db->query($qCS);
      }
    }
  }


  /**
   *  Determine whether there is a conflict for a paper
   */
  private function paperInConflict ($idPaper)
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    $query = "SELECT p.* FROM Paper p, Review r1, Review r2"
    . " WHERE p.id='$idPaper' AND r1.idPaper='$idPaper' AND r2.idPaper='$idPaper' "
    . "AND r1.overall IS NOT NULL "
    . "AND r2.overall IS NOT NULL "
    . "AND r1.id_user != r2.id_user "
    . "AND ABS(r1.overall - r2.overall) >= " . Config::CONFLICT_GAP ;

    $result = $db->query ($query);
    $rev =  $result->fetch (Zend_Db::FETCH_OBJ);
    if (is_object($rev)) {
      return true;
    }
    else {
      return false;
    }
  }

  // Determine whether there is a missing review for a paper
  private function missingReview ($idPaper)
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    $query = "SELECT p.* FROM Paper p, Review r1"
    . " WHERE p.id=$idPaper AND r1.idPaper=$idPaper AND r1.overall IS NULL";

    $result = $db->query ($query);
    $rev =  $result->fetch (Zend_Db::FETCH_OBJ);
    if (is_object($rev)) {
      return true;
    }
    else {
      return false;
    }
  }

  /**
   *  Encode the array of questions choices for the current selection
   */
  private function encodeQuestions ($field_name)
  {
    $questions = ""; $sep="";

    if (isSet($_POST[$field_name])) {
      $arr_questions = $_POST[$field_name];
    }
    else {
      $arr_questions = array();
    }

    foreach ($arr_questions as $id_question => $id_choice)
    {
      $questions .= "$sep $id_question,$id_choice";
      $sep=";";
    }
    return $questions;
  }


  // Format a submission date
  function getWorkflowDate ($phase)
  {
    if ($phase == Config::REVIEWING_PHASE) {
      $date = $this->reviewDeadline;
    }
    else if ($phase == Config::PROCEEDINGS_PHASE) {
      $date = $this->cameraReadyDeadline;
    }
    else { // Default: submission deadline
      $date = $this->submissionDeadline;
    }

    $tab=explode('-',$date);
    return date ($this->date_format, mktime (0, 0, 0, $tab[1], $tab[2], $tab[0]));

  }

  // End of the class
}

