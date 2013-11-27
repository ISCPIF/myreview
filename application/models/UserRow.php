<?php

require_once("UserTopic.php");
require_once("RegQuestion.php");
require_once("RegChoice.php");
require_once("RegAnswer.php");

/**
 * This class represents a user object
 */

class UserRow extends Zmax_Db_Table_Row_Abstract
{

  /**
   * List f preferred topics (used for reviewers)
   */
  private $_topics;


  /**
   * The list of registration questions/answers
   */

  private $_answers;

  /**
   * This function is executed when a User object is instantiated. We
   * look for the associated object if this is an existing paper.
   */
  function init() {
    $this->_topics = array();
    $this->_answers = array();

    // Load the topics
    if (!empty($this->id)) {
      $UserTopicTbl = new UserTopic();
      $topics =  $this->findUserTopic();
      foreach ($topics as $topic) {
        $this->_topics[] = $topic;
      }
    }

    // Fetch the answers
    if (!empty($this->id)) {
      $answers = $this->findRegAnswer();
      foreach ($answers as $answer) {
        $this->_answers[$answer->id_question] = $answer;
      }
    }
  }

  /**
   * Set the content of a user from an array
   * @input An array with all the values, including dependent rows (comes from the form)
   */

  function setFromArray($input)
  {
    // OK, call the parent function, god for level-1 values
    parent::setFromArray($input);

    if (isSet($_POST["topics"]) and is_array($_POST["topics"])) {
      $this->setTopicsFromArray($_POST["topics"]);
    }

    if (isSet($_POST["roles"])  and is_array($_POST["roles"])) {
      $this->setRolesFromArray($_POST["roles"]);
    }

    // Get the registration answers
    if (isSet($_POST['reg_answers'])  and is_array($_POST["reg_answers"])) {
      $this->setAnswersFromArray($_POST["reg_answers"]);
    }
  }

  function setTopicsFromArray($topics)
  {
    // Now we must take embedded objects: topics
    $UserTopicTable = new UserTopic();
    $this->_topics = array();
    foreach ($topics as $i => $val) {
      $topic = $UserTopicTable->createRow();
      $topic->id_user = $this->id;
      $topic->id_topic = $val;
      $this->_topics[] = $topic;
    }
  }

  function setRolesFromArray($roles)
  {
    $this->roles = "";
    foreach ($roles  as $role) {
      $this->addRole($role);
    }
  }

  function setAnswersFromArray($answers)
  {
    // Get the answers to additional questions
    $this->_answers = array();
    $regAnswer = new RegAnswer();
    foreach ($answers as $idQuestion => $idAnswer) {
      $this->_answers[$idQuestion] = $regAnswer->createRow();
      // Initialize the answer object. Note: the user id is not know yet
      $this->_answers[$idQuestion]->setFromArray(array("id_question" => $idQuestion,
                                          "id_answer" => $idAnswer));
    }
  }


  /**
   * Create a form to access a User
   *
   */

  function form(&$view, $formTemplate="form_user.xml",
  $changePassword=true, $register=false)
  {
    $view->setFile ("form_user", $formTemplate);
    $view->setBlock ("form_user", "TOPICS");
    $view->setBlock ("form_user", "PASSWORD");
    $view->setBlock ("form_user", "ATTENDEE");
    $view->setBlock ("form_user", "NO_ATTENDEE");
   $view->setBlock ("form_user", "REGISTRATION_QUESTION");
    
    if ($register) {
      $this->addRole(User::PARTICIPANT_ROLE);
    }

    $db = Zend_Db_Table::getDefaultAdapter();

    // Put the values in the view (don't use HTML formating)
    $this->putInView($view, false);

    if (!$changePassword)  {
      // Do not show the password
      $view->PASSWORD = "";
    }
    $view->change_password = $changePassword;
    $view->register = $register;

    $countryList = $db->fetchPairs ("SELECT * FROM Country");
    // Sort the countries in alphabetical order
    asort ($countryList, SORT_STRING);

    $view->country_list =  Zmax_View_Phplib::selectField ("country_code", $countryList,
    $this->country_code);

    // Do not allow empty roles
    if (empty($this->roles)) {
      $this->addRole(User::REVIEWER_ROLE);
    }
     
    $existingRoles = array_flip(explode (",", $this->roles ));
    $view->roles_list = Zmax_View_Phplib::checkboxField ("checkbox", "roles[]",
    Config::$Roles, $existingRoles, $this->country_code);

    if ($this->isReviewer()) {
      // We show a form that allows the user to choose his/her topics
      $topicList = $db->fetchPairs ("SELECT * FROM ResearchTopic");
      $existingTopics = array();
      foreach ($this->_topics as $topic) {
        $existingTopics[$topic->id_topic] = 1;
      }
      $view->topic_list =  Zmax_View_Phplib::checkboxField ("checkbox", "topics[]",
      $topicList, $existingTopics, array("length" => 5));
      $view->assign("TOPICS", "TOPICS");
    }
    else {
      $view->TOPICS = "";
    }

    // If this is a registered user: show the attendee information, else hide them
    if ($this->isParticipant()) {
      $view->NO_ATTENDEE = "";
      $regQuestion = new RegQuestion();

      // Registration? Produce the list of questions
      $view->setBlock ("REGISTRATION_QUESTION", "QUESTION", "QUESTIONS");
      $questions = $regQuestion->fetchAll();
      foreach ($questions as $question) {
        $view->id_question = $question->id;
        $view->question = $question->question_code;
        // Get the list of choices, ordered by the position
        $view->CHOICES = "";

        $choices = $question->findRegChoice($regQuestion->select()->order('position ASC'));
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
        $view->CHOICES = Zmax_View_Phplib::checkboxField ("radio", "reg_answers[$question->id]",
        $choicesList, $defaultChoice, array("length" => 5));
        $view->append("QUESTIONS", "QUESTION");
      }

    }
    else {
      $view->ATTENDEE = "";
      $view->REGISTRATION_QUESTION = "";
    }

    // Instantiate the template
    $view->assign("form_result1", "form_user");
    $view->assign("form_result2", "form_result1");

    return $view->form_result2;
  }

  /**
   * Check the input defining a user.
   */
  function checkInsert (&$texts)
  {
    // Initialize the array of error messages with standard tests
    $messages = $this->checkValues($texts);

    // Check that this email does not already exist
    if (is_object($this->getTable()->findByEmail($this->email))) {
      $messages[]  = $texts->author->existing_email_error;
    }

    // Contrôle sur le mot de passe
    if ($this->password==""  or $_POST['confirm_password']==""
    or $_POST['confirm_password'] != $this->password or strlen($this->password) < 6 ) {
      $messages[]  .=  $texts->author->password_error;
    }
     
    return $messages;
  }


  /**
   * Check the input modifying a user.
   */
  function checkUpdate (&$texts, $changePassword=true)
  {
    // Initialize the array of error messages with standard tests
    $messages = $this->checkValues($texts);

    // Check that this email is not already used by another user
    /* if (is_object($this->getTable()->findByEmail($this->email))) {
     $messages[]  = $texts->author->existing_email_error;
     }*/

    // Contrôle sur le mot de passe
    if ($changePassword) {
      if ($this->password==""  or $_POST['confirm_password']==""
      or $_POST['confirm_password'] != $this->password or strlen($this->password) < 6 ) {
        $messages[]  .=  $texts->author->password_error;
      }
    }
     
    return $messages;
  }

  /**
   * Check the description of a User.
   * @param $texts Translation of error messages
   * @param $unchecked List of field that do not needs to be checked
   */
  function checkValues ($texts, $unchecked=array())
  {
    // Initialize the array of error messages
    $messages = array();

    // Check that important data have been set
    if ($this->email=="") {
      $messages[] = $texts->author->email_mandatory;
    }
    else if (!$this->checkEmail($this->email)) {
      $messages[]  = $texts->author->email_error;
    }

    if ($this->first_name =="") $messages[]  = $texts->author->first_name_error;
    if ($this->last_name =="") $messages[]  = $texts->author->last_name_error;

    if (!in_array("affiliation", $unchecked)) {
      if ($this->affiliation =="") $messages[]  = $texts->author->affiliation_error;
    }

    if (!in_array("address", $unchecked)) {
      if ($this->address =="") $messages[]  = $texts->author->address_error;
    }
    if (!in_array("city", $unchecked)) {
      if ($this->city =="") $messages[]  = $texts->author->city_error;
    }
    if (!in_array("zip_code", $unchecked)) {
      if ($this->zip_code =="") $messages[]  = $texts->author->zip_code_error;
    }
    /* if (!in_array("phone", $unchecked)) {
     if ($this->phone =="") $messages[]  = $texts->author->phone_error;
     }*/
    return $messages;
  }

  /**
   * Override the 'save' function to encrypt the password
   */

  function save()
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    // Always put the email in lowercase
    $this->email = strToLower($this->email);

    // We do no accept empty password. If the password is
    // empty, assign the default one
    if (empty($this->password)) {
      $registry = Zend_registry::getInstance();
      $config = $registry->get("Config");
      $this->password = md5($this->defaultPassword($config->passwordGenerator));
    }

    // Trim some values
    $this->address =  trim($this->address);

    // No role? This is an author
    if (empty($this->roles)) {
      $this->roles = User::AUTHOR_ROLE;
    }

    parent::save();

    // Do we have research topics? Save them.
    if ($this->isReviewer()) {
      // Clean the UserTopic table for this paper.
      $db->query("DELETE FROM UserTopic WHERE id_user='{$this->id}'");

      $UserTopicTbl = new UserTopic();

      foreach ($this->_topics as $topic) {
        $userTopicRow = $UserTopicTbl->createRow();
        $userTopicRow->setFromArray( array("id_user" => $this->id,
                    "id_topic" => $topic->id_topic)); 
       $userTopicRow->save();
      }
    }

    // Clean the RegAnswer table for this user.
    $db->query("DELETE FROM RegAnswer WHERE id_user='{$this->id}'");

    // And, finally, save the answers to questions
    $regAnswerTbl = new RegAnswer();
    foreach ($this->_answers as $idQuestion => $answer) {
        $regAnswerRow = $regAnswerTbl->createRow();
       $regAnswerRow->setFromArray( array("id_question" =>$idQuestion,
                    "id_answer" => $answer->id_answer, "id_user" => $this->id)); 
       $regAnswerRow->save();
    }
  }


  /**
   * Delete a user and all its dependent objects
   */
  function delete()
  {
    $reviews = $this->findReview();
    foreach ($reviews as $review) {
      $review->delete();
    }

    $topics = $this->findUserTopic();
    foreach ($topics as $topic) {
      $topic->delete();
    }

    $ratings = $this->findRating();
    foreach ($ratings as $rating) {
      $rating->delete();
    }

    $assignments = $this->findAssignment();
    foreach ($assignments as $assignment) {
      $assignment->delete();
    }

    $regAnswers = $this->findRegAnswer();
    foreach ($regAnswers as $regAnswer) {
      $regAnswer->delete();
    }

    $sessions = $this->findSession();
    foreach ($sessions as $session) {
      $session->delete();
    }

    // Finally delete the user itself
    parent::delete();
  }

  /**
   *  Regular expr. that checks an email
   */
  private function checkEmail($email){
    return preg_match("^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$^", $email);
  }

  /**
   * Check that a password is correct
   */
  function checkPassword (&$texts)
  {
    if ($this->password==""  or $_POST['confirm_password']==""
    or $_POST['confirm_password'] != $this->password or strlen($this->password) < 6 ) {
      return $texts->author->password_error;
    }
     
    return "";
  }


  /**
   * Put the values in the view
   *
   * Overriden method: put the name of the country instead of the code
   * @author philipperigaux
   *
   */
  function putInView(&$view, $html=true)
  {
    // Trim some values
    $this->address =  trim($this->address);

    parent::putInView($view, $html);

    // Get the config
    $registry = Zend_registry::getInstance();
    $config = $registry->get("Config");

    $name = "User" . "->country";
    $country = $this->findParentCountry();
    if (!is_object($country)){
      $country_name ="";
    }
    else {
      $country_name = $country->name;
    }
    $view->setVar($name, $country_name);

    // Put the default password in the view
    $name = "User" . "->default_password";
    $view->setVar($name, $this->defaultPassword($config->passwordGenerator));

    // Create a view field with the list of topics (for existing users)
    $strTopics =  $comma = "";
    if ($this->id != "") {
      $usertopics = $this->findUserTopic();
      foreach ($usertopics as $usertopic) {
        $topic = $usertopic->findParentResearchTopic();
        if (is_object($topic)) {
          $strTopics .= $comma . $topic->label;
          $comma = ", ";
        }
      }
    }

    $name = "User" . "->topics";
    $view->setVar($name, $strTopics);
  }

  /**
   *  Compute the default password of a user
   */
  function defaultPassword ($seed)
  {
    // MD5 encryption
    return substr(md5($seed . $this->email), 0, 6);
  }

  /**
   *  Compute the full name of a user
   */
  function fullName ()
  {
    return $this->first_name . " " . $this->last_name;
  }

  /**
   * Sets a first role for a user
   */
  function setRole($role)
  {
    $this->roles  = $role;
  }

  /**
   * Add a role to a user
   */
  function addRole($role)
  {
    // Role already set? Nothing to do
    if ($this->checkRole($role)) return;

    if (empty($this->roles)) {
      $this->roles  = $role;
    }
    else {
      $this->roles .= ",$role";
    }
  }
  /**
   * Remove a role
   */
  function removeRole($theRole)
  {
    if (empty($this->roles)) return;

    $arrRoles = explode (",", $this->roles);
    $this->roles = "";
    foreach ($arrRoles as $role) {
      if ($role != $theRole) $this->addRole($role);
    }
  }

  /*
   * Check whether the member is an administrator/author/reviewer
   */
  function isAdmin ()   {    return $this->checkRole(User::ADMIN_ROLE);  }
  function isAuthor ()   {    return $this->checkRole(User::AUTHOR_ROLE);  }
  function isReviewer ()   {    return $this->checkRole(User::REVIEWER_ROLE);  }
  function isParticipant ()   {    return $this->checkRole(User::PARTICIPANT_ROLE);  }

  /*
   * Check a role
   */
  private function checkRole ($role)
  {
    $result = strstr($this->roles, $role);

    return !empty($result);
  }
   
  /*
   * Check whether a topic matches one of the user's topics
   */
  function matchTopic ($topicCode)
  {
    foreach ($this->_topics as $topic) {
      if ($topic->id_topic == $topicCode) {
        return true;
      }
    }

    return false;
  }

  /**
   * Count the umber of papers assigned to a reviewer
   */

  function countPapers ()
  {
    $db = Zend_Db_Table::getDefaultAdapter();
    $result = $db->query ("SELECT COUNT(*) AS nbPapers FROM Review WHERE id_user={$this->id}");
    $nb = $result->fetch (Zend_Db::FETCH_OBJ)  ;
    if ($nb) {
      return $nb->nbPapers;
    }
    else {
      return 0;
    }
  }

  /**
   * Modify the bid of a reviewer of the paper
   */
  function addBid($idPaper, $rate)
  {
    $found = false;
    $bids = $this->findRating();
    foreach ($bids as $bid) {
      if ($bid->idPaper == $idPaper) {
        $bid->rate = $rate;
        $found = true;
        $bid->save();
      }
    }

    // Insert if not found
    if (!$found) {
      $ratingTbl = new Rating();
      $rating = $ratingTbl->createRow();
      $rating->idPaper = $idPaper;
      $rating->id_user = $this->id;
      $rating->rate = $rate;
      $rating->save();
    }
  }

  // End of the class
}

