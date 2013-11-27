<?php

require_once("Criteria.php");
require_once("ReviewAnswer.php");
require_once("ReviewQuestion.php");

/**
 * This class represents a review object
 */

class ReviewRow extends Zmax_Db_Table_Row_Abstract
{
  /**
   * Variables for computing the weighted average
   */

  private $totalWeight, $weightedMarks;

  /**
   * The list of review questions/answers
   */

  private $_answers;

  /**
   * The list of evaluation marks
   */
  private $_marks;

  /**
   * This function is executed when a Review object is instantiated. We
   * look for the associated object if this is an existing review.
   */
  function init() {
    $this->_answers = array();
    $this->_marks = array();
    $this->totalWeight = $this->weightedMarks = 0;

    if (!empty($this->id_user) and !empty($this->idPaper)) {
      // Fetch the answers
      $answers = $this->findReviewAnswer();
      foreach ($answers as $answer) {
        $this->_answers[$answer->id_question] = $answer;
      }
      // No answers? initialize them from the questions
      if (count($this->_answers) == 0) {
        $this->initAnswers();
      }

      // Fetch the marks
      $marks = $this->findReviewMark();
      foreach ($marks as $mark) {
        $this->_marks[$mark->idCriteria] = $mark;
      }
      // No mark? initialize them from the criteria
      if (count($this->_marks) == 0) {
        $this->initMarks();
      }

    }
  }

  /**
   * Iniializes the list of review marks from the list of criterias
   *
   */
  function initMarks()
  {
    $this->_marks = array();
    $criteriaTbl = new Criteria();
    $reviewMarkTbl = new ReviewMark();
    $criterias = $criteriaTbl->fetchAll();
    foreach ($criterias as $criteria) {
      $this->_marks[$criteria->id] = $reviewMarkTbl->createRow();
      $this->_marks[$criteria->id]->idPaper = $this->idPaper;
      $this->_marks[$criteria->id]->id_user = $this->id_user;
      $this->_marks[$criteria->id]->idCriteria = $criteria->id;
      $this->_marks[$criteria->id]->mark = 3; // Default value. Why not?
    }
  }

  /**
   * Iniializes the list of review answers from the list of review questions
   *
   */
  function initAnswers()
  {
    $this->_answers = array();
    $reviewQuestionTbl = new ReviewQuestion();
    $reviewAnswerTbl = new ReviewAnswer();
    $questions = $reviewQuestionTbl->fetchAll();
    foreach ($questions as $question) {
      $this->_answers[$question->id] = $reviewAnswerTbl->createRow();
      $this->_answers[$question->id]->id_paper = $this->idPaper;
      $this->_answers[$question->id]->id_user = $this->id_user;
      $this->_answers[$question->id]->id_question = $question->id;

      /** Get a default value for the answer: the first in the RQChoice table */
      $db = Zend_Db_Table::getDefaultAdapter();
      $res = $db->query("SELECT MIN(id_choice) AS min_id FROM RQChoice "
      .  " WHERE id_question=$question->id");
      $choice =  $res->fetch (Zend_Db::FETCH_OBJ);
      if ($choice) {
        $this->_answers[$question->id]->id_answer = $choice->min_id ;
      }
    }
  }

  /**
   *
   *  Update a review from an HTTP submission
   */
  function updateFromArray ($array)
  {
    // Load from the array
    $this->setFromArray($array);

    // Set the dates
    $now = date("U");
    if (empty($this->submission_date)) {
      // Set the submission_date
      $this->submission_date=$now;
    }
    else {
      // Set the last revision date
      $this->last_revision_date=$now;
    }

    // Now compute the overall rate as a weighted average
    if ($this->totalWeight == 0) {
      throw new Zmax_Exception ("THE SUM OF WEIGHTS IS NULL. Unable to compute the overall mark");
    }
    else {
      $this->overall = $this->weightedMarks / $this->totalWeight;
    }

    // Save the modifications
    $this->save();
  }

  // Insert or update a review mark
  function SQLReviewMark ($idPaper, $email, $idCriteria, $mark, $db)
  {
    // Get the review mark,  if exists
    $revMark = GetReviewMark ($idPaper, $email, $idCriteria, $db, "object");

    if (!is_object($revMark))
    {
      // Insert
      $query = "INSERT INTO ReviewMark (idPaper, email, idCriteria, mark)"
      . "VALUES ('$idPaper', '$email', '$idCriteria', '$mark') ";
    }
    else
    {
      // Update
      $query = "UPDATE ReviewMark SET mark='$mark' WHERE "
      . " idPaper='$idPaper' AND email='$email' AND idCriteria='$idCriteria'";
    }
    $db->execRequete ($query);
  }

  /*
   * Create a string representation of the review from a template.
   *
   * The template name is given as a third arg. (default: 'review'). It must contain two sublocks:
   *   - review_marks, the placeholder for mark, instantiated as a list of review_mark templates
   *   - review_answers, the placeholder for review answer, as a list of review_answer templates
   *
   *   The function returns a string with the instantiated template
   */
  public function showReview ($view, $html=false, $revName="review")
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    // Initialize the review content
    $view->review_marks = "";
    $view->review_answers = "";

    // Put the data in the view
    $this->putInView($view, $html);

    // Default selection of the reviewer expertise
    $selectedName = "selected" . $this->reviewerExpertise;
    $view->setVar($selectedName, "selected");

    // Get the reviewer
    $reviewer = $this->findParentUser( );
    $reviewer->putInView($view, $html);

    // Get the paper
    $paper = $this->findParentPaper( );
    $paper->putInView($view, $html);

    // Show the marks
    $j = 0;
    foreach ($this->_marks as $mark) {
      // Choose the CSS class
      $view->css_class = Config::CssCLass($j++);

      $mark->putInView($view);
      $criteria = $mark->findParentCriteria();
      $criteria->putInView($view, $html);

      // Create the list of marks (for forms)
      $view->list_marks = Zmax_View_Phplib::selectField ("marks[$criteria->id]", Config::$Scale, $mark->mark);
      $view->append($revName . "_marks", $revName . "_mark");
    }

     
    // Put the answers to questions
    foreach ($this->_answers as $answer) {
      // Choose the CSS class
      $view->css_class = Config::CssCLass($j++);

      // Instantiate the answer
      $answer->putInView($view, $html);
       
      // Get and instantiate the question
      $question = $answer->findParentReviewQuestion();
      $question->putInView($view);

      // get and instantiate the choice
      $choiceList = $db->fetchPairs ("SELECT * FROM RQChoice WHERE id_question=$answer->id_question");
      $choice = $answer->findParentRQChoice();
      if (is_object($choice)) {
        $choice->putInView($view);
      }

      $view->list_choices =  Zmax_View_Phplib::checkboxField ("radio",
        "answers[$answer->id_question]", $choiceList,
      $answer->id_answer, array());

      $view->append ($revName . "_answers", $revName . "_answer");
    }

    // Instantiate twice to resolve translations
    $view->assign("result1", $revName);
    $view->assign("result2", "result1");
    return $view->result2;
  }

  /**
   *  Write a review in the DB
   */

  function save()
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    // First save the paper
    parent::save();

    // Save marks. First clean the current content
    $db->query("DELETE FROM ReviewMark WHERE id_user='{$this->id_user}' and idPaper='{$this->idPaper}'");
    foreach ($this->_marks as $mark) {
      // echo "Save $mark->idPaper -- $mark->id_user for criteria $mark->idCriteria<br/>";
      $mark->save();
    }

    // Save answers. First clean the current content
    $db->query("DELETE FROM ReviewAnswer WHERE id_user='{$this->id_user}' and id_paper='{$this->idPaper}'");
    foreach ($this->_answers as $answer) {
      $answer->save();
    }
  }

  /**
   * Set the content of a review from an array
   * @input An array with all the values, including dependent rows
   */

  function setFromArray($input)
  {     
    // Be careful: filter input data
    $this->setFilterData(true);
    
    // OK, call the parent function.
    parent::setFromArray($input);
     
    // Get the review marks
    $this->_marks = array();
    $criteriaTbl = new Criteria();
    $this->totalWeight = 0;
    $this->weightedMarks = 0;
    $markTbl = new ReviewMArk();
    if (isSet($input['marks'])) {
      foreach ($input['marks'] as $idCriteria => $value) {
        $criteria = $criteriaTbl->find($idCriteria)->current();
        $this->_marks[$idCriteria] = $markTbl->createRow();

        $this->totalWeight += $criteria->weight;
        $this->weightedMarks +=  $criteria->weight * $value;

        // Initialize the mark object.
        $this->_marks[$idCriteria]->setFromArray(array("idPaper" => $this->idPaper,
                "id_user" => $this->id_user, "idCriteria" => $idCriteria, "mark" => $value));
      }
    }

    // Get the answers to additional questions
    $this->_answers = array();
    $reviewAnswer = new ReviewAnswer();
    if (isSet($input['answers'])) {
      foreach ($input['answers'] as $idQuestion => $idAnswer) {
        $this->_answers[$idQuestion] = $reviewAnswer->createRow();
        // Initialize the answer object.
        $this->_answers[$idQuestion]->setFromArray(array("id_paper" => $this->idPaper,
                "id_user" => $this->id_user, "id_question" => $idQuestion,
                                          "id_answer" => $idAnswer));
      }
    }
  }

  /**
   * Delete a review and all dependent objects
   */
  function delete()
  {
    // Check whether we are in safe mode
    $registry = Zend_registry::getInstance();
    $safeMode = $registry->get("zmax_context")->config->app->safe_mode;

    // General rule: never delete a review already submitted
    if ($safeMode  and !empty($this->overall)) {
      throw new Exception ("MyReview error: attempt to delete an existing review ($this->idPaper, $this->id_user)."
      . "Operation cancelled (unset the safe mode in application.ini to override this)");
      return;
    }

    // Delete all review answers
    $answers = $this->findReviewAnswer();
    foreach ($answers as $answer) {
      $answer->delete();
    }

    // Delete all review marks
    $marks = $this->findReviewMark();
    foreach ($marks as $mark) {
      $mark->delete();
    }

    // Finally delete the review itself
    parent::delete();
  }

  /**
   * Put the review information is the view
   */
  function putInView($view, $html=false)
  {
    parent::putInView($view, $html);
     
    // Add the reviewer expertise
    $name = "Config" . "->reviewerExpertise";
    if (isSet(Config::$Expertise[$this->reviewerExpertise])) {
      $view->setVar($name, Config::$Expertise[$this->reviewerExpertise]);
    }
    else {
      $view->setVar($name, "unknwon_expertise");
    }

  }


  // End of the class
}

