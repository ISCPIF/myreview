<?php

require_once ("User.php");
require_once ("Author.php");
require_once("Abstract.php");
require_once("PaperQuestion.php");
require_once("PaperAnswer.php");
require_once("AbstractSection.php");

/**
 * This class represents a user object
 */

class PaperRow extends Zmax_Db_Table_Row_Abstract
{

  // The default behavior is to load abstract when the object is
  // instantiated. This should not be done when may papers are
  // selected, because it is VERY slow with Zend.
  public static $loadAbstracts = true;

  // Same thing for answers
  public static $loadAnswers = true;

  const STORE_MESSAGE_ID = 900;

  /**
   * List of messages, when errors are met
   * @var unknown_type
   */
  private $_messages = array();

  /**
   * Number of the contact author
   */
  private $_contactAuthor;

  /**
   * The list of authors (array of objects instance of User)
   *
   */
  private $_authors;

  /**
   * The list of paper questions/answers
   */

  private $_answers;

  /**
   * The list of abstract sections: an array indexed by the section name
   */
  private $_abstract;

  /**
   * An indicator that can be explicitly set to hide the authors
   */
  private $_hideAuthors;

  /**
   * Path to the directory of uploaded files
   */
  private $_filePath;

  /**
   * This function is executed when a Paper object is instantiated. We
   * look for the associated object if this is an existing paper.
   */
  function init() {
    $this->_authors = array();
    $this->_answers = array();
    $this->_abstract = array();

    $this->_hideAuthors = false;

    if (!empty($this->id)) {
      $authorTbl = new Author();
      $authors =  $authorTbl->fetchAll("id_paper='{$this->id}'", 'position ASC');
      $i=0;
      foreach ($authors as $author) {
        $user =  $author->findParentUser();
        if (!is_object($user)) {
          echo "Unkown user in $this->id<br/>";
        }
        else {
          $this->_authors[] = $user;
          if ($this->emailContact == $user->email) {
            $this->_contactAuthor = $i;
          }
        }
        $i++;
      }

      // Fetch the answers
      if (self::$loadAnswers) {
        $answers = $this->findPaperAnswer();
        foreach ($answers as $answer) {
          $this->_answers[$answer->id_question] = $answer;
        }
      }

      // Fetch the abstract
      if (self::$loadAbstracts) {
        $this->_abstract = $this->initAbstracts();
      }
    }

    // get the default number of authors
    $registry = Zmax_Bootstrap::getRegistry();
    $zmax_context = $registry->get("zmax_context");
    $config = $zmax_context->config;
    $defaultNbAuthorsInform = $config->app->nb_authors_in_form;

    // Set the file path
    $this->_filePath = $config->app->upload_path;

    // NB: the nb of authors shown is at least $defaultNbAuthorsInform, or the current number of authors
    // if the latter is greater.
    $this->nb_authors_in_form = max($defaultNbAuthorsInform, $this->nbAuthors());
  }

  /**
   * Get the abstract content from the database
   */
  function initAbstracts()
  {
    // Fetch the abstract
    $abstracts = $this->findAbstractClass();
    $abstractList = array();
    foreach ($abstracts as $abstract) {
      $abstractList[$abstract->id_section] = $abstract;
    }
    return $abstractList;
  }

  /**
   * Set the content of a paper from an array
   * @input An array with all the values, including dependent rows
   * @fullArray false if the array only contains Paper data (no authors, abstracts, etc.)
   *
   */

  function setFromArray(&$input, $fullArray=true)
  {
    // OK, call the parent function, god for level-1 values
    $this->setFilterData(true);
    parent::setFromArray($input);

    // Now set the authors and other dependent infos.
    if ($fullArray) {
      $this->setDependentFromArray($_POST);
    }
  }

  /**
   * Update the content of a paper from an array
   * @input An array with all the values, including dependent rows
   * @fullArray false if the array only contains Paper data (no authors, abstracts, etc.)
   */

  function updateFromArray(&$input, $fullArray=true)
  {
    // The paper comes from the DB. One replaces the DB values with the form input
    $this->title = htmlSpecialChars($input['title'], ENT_NOQUOTES);
    $this->topic = $input['topic'];

    // Now set the authors and other dependent infos.
    if ($fullArray) {
      $this->setDependentFromArray($_POST);
    }
  }

  /**
   * Create a form to access a paper
   *
   */
  function form(&$user, &$view)
  {
    $view->setFile ("form_submit", "form_submit.xml");
    $view->setBlock("form_submit", "ABSTRACT", "ABSTRACTS");
    $view->setBlock("form_submit", "FILE_UPLOAD");
    $view->setBlock("form_submit", "AUTHOR", "AUTHORS");
    $view->setBlock("form_submit", "QUESTION", "QUESTIONS");

    $registry = Zend_registry::getInstance();
    $config = $registry->get("Config");

    // If in two phases submission: hide the upload
    if ($config->two_phases_submission == "Y") {
      $view->FILE_UPLOAD = "";
    }

    // Put the connected user in the form
    $view->me_last_name = $user->last_name;
    $view->me_first_name = $user->first_name;
    $view->me_affiliation = $user->affiliation;
    $view->me_email = $user->email;
    $view->me_country = $user->country_code;

    $db = Zend_Db_Table::getDefaultAdapter();
    $user = new User();

    // Get the lists of choices
    $countryList = $db->fetchPairs ("SELECT * FROM Country");
    // Sort the countries in alphabetical order
    asort ($countryList, SORT_STRING);

    $topicList = $db->fetchPairs ("SELECT * FROM ResearchTopic");

    // Produce the list of abstracts sections ordered by their position
    $abstract = new AbstractSection();
    $abstract->select()->order('position ASC');
    $abstractRows = $abstract->fetchAll();
    $view->list_abstract_ids = $separator = "";
     
    foreach ($abstractRows as $abstractRow) {
      $abstractRow->putInView($view, false);
      // Set the default value
      if (isSet($this->_abstract[$abstractRow->id])) {
        $view->abstract_content = $this->_abstract[$abstractRow->id]->content;
      }
      else {
        $view->abstract_content = "";
      }
      if ($abstractRow->mandatory == 'Y') {
        $view->abstract_mandatory = "*";
      }
      else {
        $view->abstract_mandatory = "";
      }
      $view->list_abstract_ids .= $separator . $abstractRow->id;
      $separator = "; ";
      $view->append("ABSTRACTS", "ABSTRACT");
    }

    $view->topic_list =  Zmax_View_Phplib::selectField ("topic", $topicList, $this->topic);

    // Produce the list of fields for authors.
    for ($i=0; $i < $this->nb_authors_in_form; $i++) {
      if ($i % 2 == 0) $view->css_class='odd';
      else  $view->css_class='even';
      $view->ith = $i ;
      $view->iplus = $i + 1;
      $view->checked = "";
       
      // Take the existing author for default values
      if ($i < $this->nbAuthors()) {
        $author = $this->getAuthor($i);
        if ($this->_contactAuthor == $i) {
          $view->checked = "checked='1'";
        }
      }
      else {
        // Create an empty author
        $author = $user->createRow();
      }
      $author->putInView($view);
       
      // Propose the list of countries
      $view->country_list =  Zmax_View_Phplib::selectField ("country_code[$i]", $countryList,
      $author->country_code, 1, "author_country_$i");

      $view->append("AUTHORS", "AUTHOR");
    }

    // Produce the list of questions
    $paperQuestion = new PaperQuestion();
    $questions = $paperQuestion->fetchAll();
    foreach ($questions as $question) {
      $view->id_question = $question->id;
      $view->question = $question->question_code;
      // Get the list of choices, ordered by the position
      $view->CHOICES = "";

      $choices = $question->findPQChoice($paperQuestion->select()->order('position ASC'));
      $choicesList = array();
      $defaultChoice = "";
      foreach ($choices as $choice) {
        $choicesList[$choice->id_choice] = $choice->choice;
        if (empty($defaultChoice)) $defaultChoice = $choice->id_choice;

        // Check whether this is the default choice
        if (isSet($this->_answers[$question->id])) {
          if ($this->_answers[$question->id]->id_answer == $choice->id_choice) {
            $defaultChoice = $choice->id_choice;
          }
        }
      }
      $view->CHOICES = Zmax_View_Phplib::checkboxField ("radio", "answers[$question->id]",
      $choicesList, $defaultChoice, array("length" => 5));
      $view->append("QUESTIONS", "QUESTION");
    }

    // Put the values in the view
    $this->putInView($view, false);

    // Note: make a double assignment, to translate text code which are
    // dynamically generated
    $view->assign("form_result1", "form_submit");
    $view->assign("form_result2", "form_result1");
    return $view->form_result2;
  }


  /** Check the infos about a paper before inserting
   */

  function checkRequest ($connectedUser, $file, $fileRequired, &$texts)
  {
    $configTble = new Config();
    $config = $configTble->fetchAll()->current();

    $this->_messages = array();

    // Some tests...
    if (empty ($this->title))  {
      $this->_messages[] = $texts->author->missing_title;
    }

    // Check that the topic is not null or blanck
    if (empty($this->topic))  {
      $this->_messages[] =  $texts->author->missing_topic;
    }

    // Check the abstract. Loop on the abstract structure, and check that
    // each abstract section is filled if it is mandatory.
    $abstractStruct = new AbstractSection();
    $abstractStruct->select()->order('position ASC');
    $abstractStructRows = $abstractStruct->fetchAll();
    $countWords = 0;
    foreach ($abstractStructRows as $abstractStructRow) {
      if (!isSet($this->_abstract[$abstractStructRow->id])) {
        // This section should exist
        $this->_messages[] =  $texts->author->missing_abstract_section;
      }
      else {
        $this->_abstract[$abstractStructRow->id]->content = trim($this->_abstract[$abstractStructRow->id]->content);
        if ($abstractStructRow->mandatory=="Y" and empty($this->_abstract[$abstractStructRow->id]->content)) {
          $this->_messages[] =  $texts->author->abstract_section_empty . ": " .
               "{author." . $abstractStructRow->section_name. "}";
        }
        // Count the number of words
        $countWords += str_word_count($this->_abstract[$abstractStructRow->id]->content);
      }
    }
     
    // echo "Nb words = $countWords<br/>";
    if ($countWords > $config->max_abstract_size) {
      $this->_messages[] =  $texts->author->abstract_too_long . " ($countWords > $config->max_abstract_size)";
    }

    // Check the authors
    $found = false;
    $nbAuthors = $this->nbAuthors();
    if ($nbAuthors == 0) {
      $this->_messages[] = $texts->author->missing_authors;
    }

    $connUserPresent = false;
    $mailAuthors = array();
    for ($i=0; $i < $nbAuthors; $i++) {
      $author = $this->getAuthor($i);
      // Do not check the city and zip code for simple authors
      $messages = $author->checkValues($texts, array("address", "city", "zip_code"));
      foreach ($messages as $message) {
        $iplus = $i+1;
        $this->_messages[] = "(" . $texts->author->author .  " $iplus) - " . $message;
      }

      $mailAuthors[$author->email] = 1;

      // Check whether this is the connected user
      if ($author->email == $connectedUser->email)  $connUserPresent = true;
    }

    // Check that the same author is not reported twice: compare the number of email
    // to the number of authors (who can do better? Find a nice PHP function)
    if (count($mailAuthors) != count($this->_authors)) {
      $this->_messages[] =  $texts->author->duplicate_authors;
    }

    // Test: the connected user must be part of the author list
    if (!$connUserPresent) {
      $this->_messages[] = $texts->author->user_mandatory;
    }

    // Test: at least one contact author
    if ($this->_contactAuthor < 0 ) {
      $this->_messages[] =  $texts->author->missing_contact_author;
    }

    // Test: the file is provided (if required)
    if ($fileRequired) {

        if (!is_uploaded_file ($file['tmp_name'])) {
        $this->_messages[] = $this->uploadError ($file, $texts);
      }
      else {
        // Check the PDF format (always in lowercase)
        $ext = substr($file['name'], strrpos($file['name'], '.') + 1);
        if (strToLower($ext) != "pdf")
        $this->_messages[]=  $texts->author->invalid_format
        . " (extension:$ext, format:" . $paper['format'] . ")";
      }
    }

    // There should be no message
    if (count($this->_messages) > 0) {
      return false;
    }
    else {
      return true;
    }
  }

  /**
   *  Write a paper in the DB with all its dependent objects
   *
   */

  function saveAll()
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    // Remove invalid characters
    $this->title = Config::removeMSQuotes(trim($this->title));
    $this->title =  preg_replace("/[\n\r]/","", $this->title);

    // First save the paper
    $this->save();

    // Save abstracts. Be very careful not to erase something
    $currentAbstracts = $this->initAbstracts();
    $asbtractSectionTbl = new AbstractSection();
    $abstractSections = $asbtractSectionTbl->fetchAll();
     
    foreach ($abstractSections as $abstractSection) {
      if (isSet($this->_abstract[$abstractSection->id])) {
        $abstract = $this->_abstract[$abstractSection->id];
        $abstract->content = Config::removeMSQuotes(trim($abstract->content));
        $abstract->content = htmlSpecialChars ($abstract->content, ENT_NOQUOTES);
        
        // Do not store optional and empty abstracts
        if (empty($abstract->content) and $abstractSection->mandatory=='N') continue;
        if (isSet ($currentAbstracts[$abstractSection->id])) {
          // Already in the DB: just update
          $currentAbstracts[$abstractSection->id]->content = $abstract->content;
          $currentAbstracts[$abstractSection->id]->save();
        }
        else {
          // This is a new row
          $abstract->id_paper = $this->id;
          $abstract->save();
        }
      }
    }
     
    // Clean the Author table for this paper.
    $db->query("DELETE FROM Author WHERE id_paper='{$this->id}'");

    // OK, now save the authors
    $user = new User();
    $authorTble = new Author();

    $i=0;
    //     echo "Contact author: " . $this->_contactAuthor . "<br/>";

    foreach ($this->_authors as $author) {
      // Check that the user does not already exist
      $existingAuthor = $user->findByEmail($author->email);
      if (is_object($existingAuthor)) {
        // Change the values with those obtained from the form
        $existingAuthor->last_name = $author->last_name;
        $existingAuthor->first_name = $author->first_name;
        $existingAuthor->affiliation = $author->affiliation;
        $existingAuthor->country_code = $author->country_code;

        // Mark the user as an author
        $existingAuthor->addRole(User::AUTHOR_ROLE);
        $existingAuthor->save();
        $idUser =  $existingAuthor->id;
      }
      else {
        // Ok, simply save the new author
        $author->addRole(User::AUTHOR_ROLE);
        $author->save();
        $idUser =  $author->id;
      }

      // In all cases, insert in the Author table (link between User and Paper)
      $authorRow = $authorTble->createRow();
      if ($this->_contactAuthor == $i) $contact='Y';
      else $contact='N';

      $authorRow->setFromArray( array("id_paper" => $this->id,
                    "id_user" => $idUser,
                    "position" => $i+1,
                      "contact" => $contact));
      $authorRow->save();
      $i++;
    }

    // Clean the PaperAnswer table for this paper.
    $db->query("DELETE FROM PaperAnswer WHERE id_paper='{$this->id}'");

    // And, finally, save the answer to questions
    foreach ($this->_answers as $answer) {
      $answer->id_paper = $this->id;
      $answer->save();
    }
  }

  /**
   * Delete a paper and all its dependent objects
   * @return unknown_type
   */
  function delete()
  {
    $assignments = $this->findAssignment();
    foreach ($assignments as $assignment) {
      $assignment->delete();
    }

    $reviews = $this->findReview();
    foreach ($reviews as $review) {
      $review->delete();
    }

    $authors = $this->findAuthor();
    foreach ($authors as $author) {
      $author->delete();
    }

    $ratings = $this->findRating();
    foreach ($ratings as $rating) {
      $rating->delete();
    }

    $uploads = $this->findUpload();
    foreach ($uploads as $upload) {
      $upload->delete();
    }

    $answers = $this->findPaperAnswer();
    foreach ($answers as $answer) {
      $answer->delete();
    }
    // Delete the file
    // @todo Delete the file
    /*
     if (file_exists($file_name)) {
     @unlink ($file_name);
     }
     */
    // Finally delete the paper itself
    parent::delete();

  }

  /**
   * Count the number of authors
   */
  function nbAuthors()
  {
    return count($this->_authors);
  }

  /**
   * Get an author by its position
   */
  function getAuthor($i)
  {
    return $this->_authors[$i];
  }

  /**
   * Check whether there is a conflict with a user/reviewer
   * @param $member The potential reviewer
   * @return Boolean
   */
  function checkConflictWithuser ($member, &$conflict)
  {
    $nbAuthors = $this->nbAuthors();
    for ($i=0; $i < $nbAuthors; $i++) {
      //  echo "Paper $paper->id. Compare authors with ";
      $author = $this->getAuthor($i);
      if ($author->id == $member->id) { //the member is one of the author...
        $conflict .= "Conflict found with paper $this->id ($this->title): member is an author<br/> ";
        return true;
      }
      else if ($author->affiliation == $member->affiliation) {
        $conflict .= "Conflict found with paper $this->id ($this->title): an author has the member's affiliation<br/> ";
        return true;
      }
    }
    // No conflict found
    return false;
  }

  /**
   * Check whether the author must be shown
   *
   * In blind review, authors must be hidden. Except when
   * the current user is an administrator, or itself an author
   */

  private function hideAuthors()
  {
    // Maybe the authors are explicitly hidden
    if ($this->_hideAuthors) return true;

    // We first look at the blind review mode
    $registry = Zend_registry::getInstance();
    $config = $registry->get("Config");
    $user = $registry->get("user");

    // Check whether the connected user is an admin
    $isAdmin = false;
    if (is_object($user)) {
      if ($user->isAdmin()) {
        $isAdmin = true;
      }
    }

    // Check whether the connected user is an author
    $isAuthor = false;
    if (is_object($user)) {
      if ($this->getAuthorByEmail ($user->email)) {
        $isAuthor = true;
      }
    }

    // If blind -> return the anonymous string, except if the user is admin
    if ($config->blind_review == "Y" and !$isAdmin and !$isAuthor) {
      return true;
    }
    else {
      return false;
    }
  }

  /**
   * Get the list of authors
   */
  function getAuthors($format="string")
  {
    // We first look at the blind review mode
    $registry = Zend_registry::getInstance();
    $zmax_context = $registry->get("zmax_context");

    // If blind -> return the anonymous string, except if the user is admin
    if ($this->hideAuthors() ) {
      return $zmax_context->texts->anonymous_author;
    }
    else {
      // Create a string with list of authors
      $strAuthors = $comma = "";
      foreach ($this->_authors as $author) {
        $strAuthors .= $comma . $author->first_name . " " . $author->last_name;
        $comma = ", ";
      }
      return $strAuthors;
    }
  }

  // Same function, but in static mode: avoids the ORM interface (slow)
  static  function getPaperAuthors($db, $paper, $blind="N", $format="string")
  {
    $strAuthors = $comma = "";

    $rAuthors=  $db->query ("SELECT u.* FROM `User` u, Author a WHERE a.id_paper='$paper->id' "
    . " AND u.id=a.id_user ORDER BY a.position");
    while ($author =  $rAuthors->fetch (Zend_Db::FETCH_OBJ)) {

      $strAuthors .= $comma . $author->first_name . " " . $author->last_name
         . " ($author->affiliation)";
      $comma = ", ";
    }
    return $strAuthors;
  }

  /**
   * Get an author by its email
   */
  function getAuthorByEmail($email)
  {
    foreach  ($this->_authors as $author) {
      if ($author->email == $email) {
        return $author;
      }
    }
    // Not found
    return null;
  }

  /**
   * Get the bid of a reviewer of the paper
   */
  function getBid($idUser)
  {
    $bids = $this->findRating();
    foreach ($bids as $bid) {
      if ($bid->id_user == $idUser) {
        return $bid->rate;
      }
    }
    // Default value
    return Config::UNKNOWN_RATING;
  }

  function getMessages() {
    return $this->_messages;
  }

  /**
   * Put paper information in the view
   * @author philipperigaux
   *
   */
  function putInView(&$view, $html=false)
  {
    // Call the parent which does most of the job
    parent::putInView($view, $html);

    // Show the abstracts
    $view->SECTIONS = "";
    foreach ($this->_abstract as $abstract) {
      $abstractStruct = $abstract->findParentAbstractSection();
      if (is_object($abstractStruct)) {
        // Maybe the abstract section has been removed.
        $abstractStruct->putInView($view);
        $abstract->putInView($view, $html);
        if ($view->entityExists("SECTION")) {
          $view->assign ("temp", "SECTION");
          $view->append("SECTIONS", "temp");
        }
      }
    }
     
    // OK, now get the topic
    $topic = $this->findParentResearchTopic();
    if (is_object($topic)) {
      $topic_name= $topic->label;
    }
    else $topic_name ="";
    $ename = "Paper" . "->" . "topic_name";
    $view->setVar($ename, $topic_name);

    // OK, now get the status
    $status = $this->findParentPaperStatus();
    if (is_object($status)) {
      $statusName= $status->label;
    }
    else $statusName ="";
    $ename = "Paper" . "->" . "status";
    $view->setVar($ename, $statusName);

    // Create a string with list of authors
    $ename = "Paper" . "->" . "authors";
    $view->setVar($ename, $this->getAuthors());

    // Idem with the list of reviewers
    $strReviewers = $comma = "";
    if (!empty($this->id)) {
      $reviews = $this->findReview();
      foreach ($reviews as $review) {
        // Get the user
        $user = $review->findParentUser();
        $strReviewers .= $comma . $user->first_name . " " . $user->last_name ;
        $comma = ",";
      }
    }
    $ename = "Paper" . "->" . "reviewers";
    $view->setVar($ename, $strReviewers);

    // Show the answers
    $view->ANSWERS = "";
    if ($view->entityExists("ANSWER")) {
      foreach ($this->_answers as $answer) {
        $answer->putInView($view);
        $question = $answer->findParentPaperQuestion();

        if (is_object($question)) {
          $question->putInView($view);
        }
        $choice = $answer->findParentPQChoice();
        $choice->putInView($view);
        $view->assign ("temp", "ANSWER");
        $view->append("ANSWERS", "temp");
      }
    }

  }

  /**
   * Show the reviews of a paper
   *
   */

  function showReviews($view, $html=false)
  {
    // Simply loop on the reviews, create a string repr. for each, conctenate
    $reviews = $this->findReview();

    $result = "";
    $i = 1;
    foreach ($reviews as $review) {
      $view->reviewer_no = $i++;
      $result .= $review->showReview($view, $html);
    }
    return $result;
  }
  /**
   * Get a message from its id
   *
   */
  function getMessage($idMessage)
  {
    return $this->_messages[$idMessage];
  }

  /**
   *  Get the list of email of the reviewers
   *
   */

  function getEmailReviewers ()
  {
    // Simply loop on the reviews, create a string repr. for each, conctenate
    $reviews = $this->findReview();

    $emails = $comma = "";
    foreach ($reviews as $review) {
      $user = $review->findParentUser();
      $emails .= $comma . $user->email;
      $comma=",";
    }
    return $emails;
  }

  // Compute the average mark for a paper
  function averageMark ()
  {
    $reviews = $this->findReview();

    foreach ($reviews as $review) {
      $nbReviews = $sum = 0;
      if (!empty($review->overall)) {
        $nbReviews++;
        $sum += $review->overall;
      }

      if ($nbReviews > 0) {
        return $sum / $nbReviews;
      }
      else return 0;
    }
  }

  /**
   * Check whether a reviewer must review the current paper
   */

  function hasReviewer($idUser) {
    $reviews = $this->findReview();
    foreach ($reviews as $review) {
      if ($review->id_user == $idUser) return true;
    }
    return false;
  }

  /**
   * Get the review  a reviewer
   */

  function getReview($idUser) {
    $reviews = $this->findReview();
    foreach ($reviews as $review) {
      if ($review->id_user == $idUser) return $review;
    }

    return null;
    //    throw new Zmax_Exception ("Unable to find user $idUser in getReview() for paper $this->id");
  }

  /**
   *  Store the file
   *  @param $requiredFile Object instance of RequiredFile
   *  @param $upload  The Uploaded file
   */
  function storeFile ($requiredFile, $uploadFile)
  {
    // Check the format (always in lowercase)
    $ext = substr($uploadFile['name'], strrpos($uploadFile['name'], '.') + 1);
    if (strToLower($ext) != $requiredFile->file_extension) {
      $this->_messages[self::STORE_MESSAGE_ID] =
         "Invalid file format: (extension:$ext, required:" .$requiredFile->file_extension . ")<br/>";
      return false;
    }

    // Encode the path and file name
    $paperName = $this->filePath  ($requiredFile->id_phase, $requiredFile->file_code,
    $requiredFile->file_extension);
     if (!copy($uploadFile['tmp_name'], $paperName)) {
      return false;
    }
    else {
      // Record the fact that a file has been uploaded
      $db = Zend_Db_Table::getDefaultAdapter();
      $uploadTbl = new Upload();
      $db->query ("DELETE FROM Upload WHERE id_paper='$this->id' "
      .     " AND id_file='$requiredFile->id' ");
      $uploadRow = $uploadTbl->createRow();
      $uploadRow->id_paper = $this->id;
      $uploadRow->id_file = $requiredFile->id;
      $uploadRow->file_size = $uploadFile['size'];
      $now =       Date("Y-m-d H:i:s");
      $uploadRow->upload_date = $now;
      $uploadRow->save();

      // Record the fact that the paper is uploaded.
      // @todo: not satisfying if several files are required. Do better
      $this->isUploaded = 'Y';
      $this->save();
      return true;
    }
  }

  /**
   * Check whether a fil exists
   */

  // Store the file
  function fileExists ($requiredFile)
  {
    // Encode the file name
    $paperName = $this->filePath  ($requiredFile->id_phase, $requiredFile->file_code,
    $requiredFile->file_extension);
    if (!file_exists($paperName)) {
      return false;
    }
    else {
      return true;
    }
  }

  /**
   * Download a paper
   */

  function download($requiredFile)
  {
    $type = "application/octet-stream";
    $file = $this->filePath  ($requiredFile->id_phase, $requiredFile->file_code,
    $requiredFile->file_extension);

    header("Content-disposition: attachment; filename=" . $requiredFile->file_code
    . $this->id . "." . $requiredFile->file_extension);
    header("Content-Type: application/force-download");
    header("Content-Transfer-Encoding: $type\n");
    header("Content-Length: ".filesize($file));
    header("Pragma: no-cache");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
    header("Expires: 0");
    readfile_chunked($file);
  }

  /**
   * This private function takes the authors and the answers
   * from a posted form, which replace the current paper description
   */

  private function setDependentFromArray(&$input)
  {
    // Now we must take embedded objects: abstracts
    $abstract = new AbstractClass();
    if (isSet($input["abstract"])) {
      foreach ($input["abstract"] as $id => $content) {
        $this->_abstract[$id] = $abstract->createRow();
        $this->_abstract[$id]->content = htmlSpecialChars($content, ENT_NOQUOTES);
        $this->_abstract[$id]->id_section = $id;
      }
    }

    // Next, authors
    $this->_authors = array();
    $this->_contactAuthor = -1;
    $user = new User();

    // Instantiate all the authors
    if (isSet($input["last_name"])) {
      $emails = $input["email"];
      $lastNames = $input["last_name"];
      $firstNames = $input["first_name"];
      $affiliations = $input["affiliation"];
      $country_code = $input["country_code"];
      if (isSet($input["contact_author"])) {
        $contactAuthor = $input["contact_author"];
      }
      else {
        $contactAuthor = -1;
      }

      foreach ($lastNames as $i => $lastName) {
        // Since the array comes from a form with possibly left
        // blank lines, we do not consider an empty name as a mistake
        if ($lastName != "") {
          $userRow = $user->createRow();
          $userRow->setFilterData(true);
          $userRow->setFromArray (array("last_name" => $lastName,
                             "first_name" => $firstNames[$i],
                 "affiliation" => $affiliations[$i],
           "country_code" => $country_code[$i],
            "email" => $emails[$i]));      
          $this->_authors[] = $userRow;

          // Check the contact author
          if ($contactAuthor == $i) {
            $this->_contactAuthor = $i;
            $this->emailContact = $emails[$i];
          }
        }
      } // End of loop on last_name
    } // End of test of the existence of 'last_name'

    // Get the answers to additional questions
    $this->_answers = array();
    $paperAnswer = new PaperAnswer();
    if (isSet($input['answers'])) {
      foreach ($input['answers'] as $idQuestion => $idAnswer) {
        $this->_answers[$idQuestion] = $paperAnswer->createRow();
        // Initialize the answer object. Note: the paper d is not know yet
        $this->_answers[$idQuestion]->setFromArray(array("id_question" => $idQuestion,
                                          "id_answer" => $idAnswer));
      }
    }
  }

  /*
   *  Compute the file name of the submitted file
   * @todo this method is deprecated. To be removed
   */
  function fileName ($id=0, $ext="pdf")
  {
    return  ".." . DIRECTORY_SEPARATOR . "files"
                 . "submission/paper" .$this->id . "." . $ext;
  }

  /*
   *  Compute the file path. Must replace the previous function
   */
  function filePath ($idPhase, $fileCode, $ext)
  {
    if ($idPhase == Config::SUBMISSION_PHASE) {
      $dir =  "submission";
    }
    if ($idPhase == Config::SELECTION_PHASE) {
      $dir =   "selection";
    }
    if ($idPhase == Config::REVIEWING_PHASE) {
      $dir =  $this->_filePath . "submission";
    }
    if ($idPhase == Config::PROCEEDINGS_PHASE) {
      $dir =  "proceedings";
    }

    // Name: path/file_code_id_.extension
    return   ".." . DIRECTORY_SEPARATOR . $this->_filePath . DIRECTORY_SEPARATOR .
        $dir . DIRECTORY_SEPARATOR . $fileCode . "_" . $this->id . "." . $ext;
  }

  /**
   * Count the number of reviewer assigned to this paper
   */

  function countReviewers ()
  {
    $db = Zend_Db_Table::getDefaultAdapter();
    $result = $db->query ("SELECT COUNT(*) AS nb FROM Review WHERE idPaper={$this->id}");
    $nb = $result->fetch (Zend_Db::FETCH_OBJ)  ;
    if ($nb) {
      return $nb->nb;
    }
    else {
      return 0;
    }
  }

  /**
   * Assign a list of reviewers to the paper
   */

  function assignReviewers ($tabIds)
  {
    $reviewTbl = new Review();
    // Loop on the reviewers ids
    foreach ($tabIds as $id_user) {
      // Check whether the review exists
      $review = $reviewTbl->find($this->id, $id_user)->current();
      // Never delete! Just insert
      if (!is_object($review)) {
        $review = $reviewTbl->createRow();
        $review->idPaper = $this->id;
        $review->id_user = $id_user;
        $review->save();
      }
    }
  }

  /**
   * Set the hide authors field to hide the authors of this paper
   */
  function setHideAuthors()
  {
    $this->_hideAuthors = true;
  }




  private function uploadError($file, &$texts)
  {
    // Get the error code (only available in PHP 4.2 and later)
    if (isSet($file['error']))
    {
      // Ok the code exists
      switch ($file['error'])
      {
        case UPLOAD_ERR_NO_FILE:
          return $texts->author->missing_file;
          break;
           
        case UPLOAD_ERR_INI_SIZE: 	case UPLOAD_ERR_FORM_SIZE:
          return $texts->author->file_too_large;
          break;
           
        case UPLOAD_ERR_PARTIAL:
          return $texts->author->partial_upload;
          break;

        default:
          return "Unknown upload error";
      }
    }
    else
    {
      // Probably the file was not sent at all
      return $texts->author->missing_file;
    }
  }

}

