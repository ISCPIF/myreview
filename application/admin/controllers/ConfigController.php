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

require_once("Mail.php");
require_once("User.php");

/**
 * The configuration controller of the MyReview admin.
 */

class Admin_ConfigController extends Myreview_Controller_Action_Auth
{

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

  /*
   * Shows the menu of all possible actions
   */
  function indexAction()
  {
    $this->view->setFile ("content", "index.xml");
    echo $this->view->render("layout");
  }

  function configAction()
  {
    // When the form is submitted
    $form_mess = "";
    if (isSet ($_POST['confName'])) {
      $form_mess = $this->updateConfig ($_POST, $this->db_v1, $this->view, $this->session);
      $this->config_v1 = GetConfig($this->db_v1);

      // Check whether the logo file has been transmitted
      $adapter = new Zend_File_Transfer_Adapter_Http();
      $adapter->setDestination('images/');
      $adapter->addValidator('IsImage', false);
      if ($adapter->receive()) {
        $name = $adapter->getFileName('logo_file');
        //récupérer le nom du fichier sans avoir tout le chemin
        $name = basename($name);
        $this->db_v1->execRequete ("UPDATE Config SET logo_file='$name'");
      }

      $config = new Config();
      $this->config = $config->fetchAll()->current();
      $this->config->putInView($this->view);
      $registry = Zend_registry::getInstance();
      $registry->set("Config", $this->config);
    }
     
    $this->view->config_message = $form_mess;

    $this->instantiateConfigVars ($this->config_v1, $this->view);
     
    $this->view->setFile ("content", "config.xml");
    $this->view->setFile("form_config", "form_config.xml");
    $form_mess = "";
     
    $this->view->messages =  $form_mess;
     
    // N.B: the config values ar eput in the views by the Myreview controller
    echo $this->view->render("layout");
  }

  private function UpdateConfig ($config, $db, &$tpl, &$session)
  {
    $form_mess =  "";

    if ($_POST['nbReviewersPerItem']<=0)
    $form_mess="Invalid  nb. reviewers per paper" . $form_mess;
     
    if (!CheckEMail($_POST['confMail']))
    $form_mess="<b>Conference mail is not valid</b><br>".$form_mess;
    if (!CheckEMail($_POST['chairMail']))
    $form_mess="<b>Chair mail is not valid</b><br>".$form_mess;

    $submission_deadline =
    $_POST['submissionDeadline']['_day'] . "/"  .
    $_POST['submissionDeadline']['_month'] . "/" .
    $_POST['submissionDeadline']['_year'] ;
    $review_deadline =
    $_POST['reviewDeadline']['_day'] . "/"  .
    $_POST['reviewDeadline']['_month'] . "/" .
    $_POST['reviewDeadline']['_year'] ;
    $cr_deadline =
    $_POST['cameraReadyDeadline']['_day'] . "/"  .
    $_POST['cameraReadyDeadline']['_month'] . "/" .
    $_POST['cameraReadyDeadline']['_year'] ;

    if (!isCorrectOrder($submission_deadline,
    $review_deadline))
    $form_mess="<b>Review deadline must follow the submission deadline</b>".$form_mess;
    if (!isCorrectOrder($review_deadline, $cr_deadline))
    $form_mess="<b>camera ready deadline must be after review deadline</b>".$form_mess;

    if ($form_mess != "") {
      return $form_mess;
    }

    // Update config table
    $confName = $db->prepareString($config['confName']);
    $confAcronym = $db->prepareString($config['confAcronym']);
    $confMail = $config['confMail'];
    $confLocation =  $db->prepareString($config['conf_location']);
    $chairNames =  $db->prepareString($config['chair_names']);
    $currency = $config['currency'];
    $date_format = $config['date_format'];
    $paypal_account = $config['paypal_account'];
    $submissionURL = $config['submissionURL'];
    $confURL = $config['confURL'];
    $chairMail = $config['chairMail'];

    $passwordGenerator = $db->prepareString($config['passwordGenerator']);

    $blind_review = $config['blind_review'];
    $two_phases_submission = $config['two_phases_submission'];
    $multi_topics = $config['multi_topics'];

    $isSubmissionOpen = $config['isSubmissionOpen'];
    $isReviewingOpen = $config['isReviewingOpen'];
    $isSelectionOpen = $config['isSelectionOpen'];
    $isProceedingsOpen = $config['isProceedingsOpen'];

    $discussion_mode = $config['discussion_mode'];
    $assignment_mode = $config['assignment_mode'];
    $nbReviewersPerItem = $config['nbReviewersPerItem'];
    $max_abstract_size = $config['max_abstract_size'];

    $mailOnAbstract = $config['mailOnAbstract'];
    $mailOnUpload = $config['mailOnUpload'];
    $mailOnReview = $config['mailOnReview'];

    $submissionDeadline = DisplaytoDB($submission_deadline);
    $reviewDeadline = DisplaytoDB($review_deadline);
    $cameraReadyDeadline = DisplaytoDB($cr_deadline);

    $style_name = $config['list_style'];

    $query = "UPDATE Config SET currency='$currency', confName='$confName', "
    . "paypal_account='$paypal_account', "
    . "confAcronym='$confAcronym', confMail='$confMail', "
    . " confURL='$confURL', submissionURL='$submissionURL',"
    . " conf_location='$confLocation', chair_names='$chairNames', "
    . "passwordGenerator='$passwordGenerator', blind_review='$blind_review', "
    .  " two_phases_submission='$two_phases_submission', multi_topics='$multi_topics', "
    . "isReviewingOpen='$isReviewingOpen', "
    . "isProceedingsOpen='$isProceedingsOpen', "
    . "isSelectionOpen='$isSelectionOpen', "
    . "isSubmissionOpen= '$isSubmissionOpen', "
    . "nbReviewersPerItem='$nbReviewersPerItem', "
    . "max_abstract_size='$max_abstract_size', "
    . "discussion_mode='$discussion_mode', "
    . "assignment_mode='$assignment_mode', "
    . "chairMail='$chairMail',mailOnAbstract='$mailOnAbstract', "
    . "mailOnUpload='$mailOnUpload', mailOnReview='$mailOnReview', "
    . "submissionDeadline='$submissionDeadline', "
    . " reviewDeadline='$reviewDeadline', "
    . " cameraReadyDeadline='$cameraReadyDeadline', "
    . "date_format='$date_format', "
    . " style_name='$style_name' " ;

    $db->execRequete ($query);
  }

  /**
   *
   * Manage the list of program committee members
   *
   */
  function usersAction()
  {
    $texts =  &$this->zmax_context->texts;

    // Create the infos for the filter list
    if (isSet($_POST['filter_roles'])) {
      $filterRoles = array_flip($_POST['filter_roles']);
     }
    else {
      // Show only reviewers
      $filterRoles = array_flip(array("R"));
    }
    if (isSet($_POST['mail_filter'])) {
      $this->view->mail_filter = $_POST['mail_filter'];
      $mailCriteria = " email LIKE '%{$this->view->mail_filter}%' ";
    }
    else {
      $this->view->mail_filter = "";
      $mailCriteria = " 1 " ;
    }
   if (isSet($_POST['name_filter'])) {
      $this->view->name_filter = $_POST['name_filter'];
      $nameCriteria = " last_name LIKE '%{$this->view->name_filter}%' ";
    }
    else {
      $this->view->name_filter = "";
     $nameCriteria = " 1 " ;
    }
    
    $this->view->filter_roles_list = Zmax_View_Phplib::checkboxField ("checkbox", "filter_roles[]",
    Config::$Roles, $filterRoles, array());
     $filterRolesList =""; $connector = "";
     foreach (array_flip($filterRoles) as $role) {
      $filterRolesList .= " $connector roles LIKE '%$role%' ";
      $connector = " OR ";
     }
    
    $user = new User();

    $request = $this->getRequest();
    $email = $request->getParam('email');

    // Check whther an export is required
    if (isSet($_REQUEST['export_action']))  {
      $exportRequired = true;
      $exportType = $_REQUEST['export_action'];
    }
    else {
      $exportRequired = false;
    }

    // load the template
    if (!$exportRequired) {
      $this->view->setFile("content", "users.xml");
       $this->view->setBlock("content", "post_message", " ");
     }
    else {
      if ($exportType == Config::EXPORT_EXCEL) {
        $this->view->setFile("content", "members_xls.xml");
        $mimeType = "text/xls";
        $exportName = "members.xls";
      }
      else {
        // Default: HTML
        $this->view->setFile("content", "members_html.xml");
        $mimeType = "text/plain";
        $exportName = "members.html";
      }
    }
     
 
    // After submission, insert
    if (isSet($_REQUEST['id_user']))  {
      $idUser = $request->getParam("id_user");

      if (!isSet($_POST['form_mode'])) {
        // The user exists. It must be modified or removed
        $instr = $request->getParam("instr");
        $userRow = $user->find($idUser)->current();

        if ($instr == "modify") {
          // Just show the form with default values
          $this->view->pcmember_message =  "Modify user infos";
          $this->view->form_action = $texts->form->update;
          $this->view->form_mode = "update";
          $userRow->putInView($this->view);
        }
        else if ($instr == "remove") {
          $this->view->pcmember_message = "User $email has been removed";
          $this->view->form_action = $texts->form->insert;
          $this->view->form_mode = "insert";
          $userRow->delete ();
          // Create a new user for insertion
          $userRow = $user->createRow();
          $userRow->roles = User::REVIEWER_ROLE;
          $userRow->putInView($this->view);
        }
      }
      else {
        // Data comes from the form
        $form_mode = $request->getParam("form_mode");
         
        if  ($form_mode == "insert") {
          $userRow = $user->createRow();
        }
        else {
          $userRow = $user->find($idUser)->current();
        }
        $this->view->form_action = $texts->form->update;
         
        $userRow->email = $_POST['email'];
        $userRow->first_name = $_POST['first_name'];
        $userRow->last_name = $_POST['last_name'];
        if (isSet($_POST['topics'])) {
          $userRow->setTopicsFromArray($_POST['topics']);
        }
        if (isSet($_POST['roles'])) {
          $userRow->setRolesFromArray($_POST['roles']);
        }
        $messages = $userRow->checkValues($this->zmax_context->texts,
        array("affiliation", "address", "city", "zip_code"));

        // Any error ?
        if (count($messages) > 0) {
          $this->view->setFile ("error", "error.xml");
          $this->view->setBlock ("error", "ERROR",  "ERRORS");

          foreach ($messages as $message) {
            $this->view->message = $message;
            $this->view->append("ERRORS", "ERROR");
          }
          $this->view->assign("pcmember_message", "ERRORS");
          $this->view->form_mode = $form_mode;
        }
        else {
          /* Everything is OK. Save and display the form with the user */
          $userRow->save();
          $this->view->assign("pcmember_message", "post_message");
        }
        // Always put the current data in the view
        $userRow->putInView($this->view);
      }
    }
    else {
      /* Display the form with an empty user*/
      $this->view->pcmember_message = "";
      $this->view->form_action = $texts->form->insert;
      $this->view->form_mode  = "insert";
      $userRow = $user->createRow();
      $userRow->roles = User::REVIEWER_ROLE;
      $userRow->putInView($this->view);
    }

    // We are ready to instantiate the form
    $this->view->form_reviewer = $userRow->form($this->view, "form_reviewer.xml");
    $this->view->someUser = Mail::SOME_USER;

   /* Select all the members and list them.
     First extract the 'block' describing a line from the template */

    $this->view->setBlock("content", "MEMBER", "MEMBERS");
    $pcmembers = $user->fetchAll("$mailCriteria AND $nameCriteria AND ($filterRolesList)", 'last_name');
    $i= 0;
    foreach ($pcmembers as $member) {
      $member->putInView($this->view);
      // Choose the CSS class
      $this->view->css_class = Config::CssCLass($i++);
      $this->view->append("MEMBERS", "MEMBER");
    }

    if ($exportRequired) {
      $this->view->assign ("export", "content");
      $this->exportFile($exportName, $mimeType, $this->view->export);
      return;
    }

    
    // Show the view
    echo $this->view->render("layout");
  }

  function topicsAction()
  {
    $ihm = new IhmBD ("ResearchTopic", $this->db_v1, $this->myUrl() . "?1=1");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }


  function requiredfileAction()
  {
    require_once("Phase.php");
    require_once("FileType.php");
    require_once("RequiredFile.php");
    $crud = new Zmax_Db_Edit ("RequiredFile");
    $crud->setFormField("mandatory", Zmax_Db_Edit::BOOLEAN_FIELD);
    $crud->setReferenceField("description", "FileType");
    $crud->setReferenceField("description", "Phase");
    $this->view->content  =  $crud->edit ($this->getRequest());
    echo $this->view->render("layout");
  }

  function abstractAction()
  {
    $crud = new Zmax_Db_Edit ("AbstractSection");
    $crud->setFormField("mandatory", Zmax_Db_Edit::BOOLEAN_FIELD);
    $this->view->content  =  $crud->edit ($this->getRequest());
    echo $this->view->render("layout");
  }

  function criteriaAction()
  {
    $ihm = new IhmBD ("Criteria", $this->db_v1, $this->myUrl() . "?1=1");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }

  function paperquestionsAction()
  {
    $ihm = new IhmBD ("PaperQuestion", $this->db_v1, $this->myUrl() . "?1=1");
    $ihm->setSlaveTable ("PQChoice", array("id_question" => "id"), 10);
    $ihm->setAutoIncrementedKey ("id_choice");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }

  function reviewquestionsAction()
  {
    $ihm = new IhmBD ("ReviewQuestion", $this->db_v1, $this->myUrl() . "?1=1");
    $ihm->setFormField ("public", BOOLEAN_FIELD, array());
    $ihm->setSlaveTable ("RQChoice", array("id_question" => "id"), 10);
    $ihm->setAutoIncrementedKey ("id_choice");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }
   
  function statuscodeAction()
  {
    $ihm = new IhmBD ("PaperStatus", $this->db_v1, $this->myUrl() . "?1=1");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }

  function closesubmissionAction()
  {
    // Close the submission
    $qUpdateConfig = "UPDATE Config SET isSubmissionOpen='N'";

    $this->db_v1->execRequete($qUpdateConfig);

    // Delete all reviews for papers not uploaded
    $qReviews = "SELECT idPaper,email FROM Paper p, Review r "
    . " WHERE p.id=r.idPaper AND isUploaded='N'";
    $rReviews = $this->db_v1->execRequete($qReviews);
    while ($review = $this->db_v1->objetSuivant($rReviews))
    DeleteReview ($review->idPaper, $review->email, $db);

    $this->view->setFile("content",    "submission_closed.tpl");   // Show the view
    echo $this->view->render("layout");
  }
   
  function zmaxlangsAction ()
  {
    // Use the editLangs method of Zmax_Translate
    $this->view->set_var("content",
    Zmax_Model::editLangs($this->getRequest()));
    echo $this->view->render("layout");
  }

  function zmaxnamespacesAction ()
  {
    // Use the editNamespaces method of Zmax_Translate
    $this->view->set_var("content",
    Zmax_Model::editNamespaces($this->getRequest()));
    echo $this->view->render("layout");
  }

  function zmaxtextsAction ()
  {
    // Use the editTexts method of Zmax_Translate
    $this->view->set_var("content",
    Zmax_Model::editTranslations($this->getRequest()));
    echo $this->view->render("layout");
  }

  function exporttextsAction ()
  {
    $this->view->setFile ("content", "export_texts.xml");
    $defaultLang = "fr";
     
    $langs  = $this->zmax_context->db->fetchPairs ("SELECT * FROM zmax_lang where lang !='en'");
    $this->view->list_langs = Zmax_View_Phplib::checkboxField ("radio", "lang",
    $langs, $defaultLang, array("length" => 5));

    $namespaces  = $this->zmax_context->db->fetchPairs ("SELECT namespace, namespace FROM zmax_namespace");
    $namespaces[' all'] = "All";
    ksort($namespaces);


    $this->view->list_namespaces = Zmax_View_Phplib::checkboxField ("radio", "namespace",
    $namespaces, ' all', array("length" => 10));

    echo $this->view->render("layout");
  }

  function doexportAction ()
  {
    //Header ("Content-type: text/xml");
    $translation = new Zmax_Translation ("myreview");

    $lang = $this->getRequest()->getParam("lang", "fr");
    $this->view->lang = $lang;

    $namespace = trim($this->getRequest()->getParam("namespace", "all"));
    $this->view->namespace = $namespace;

    // Export the file
    $translation->export($this->zmax_context->db, $this->view, $lang,
      "export.xml", true, $namespace);

    echo $this->view->result;
  }

  function importtextsAction ()
  {
    $this->view->setFile ("content", "import_texts.xml");
     
    echo $this->view->render("layout");
  }

  function doimportAction ()
  {
    $this->view->setFile ("content", "import.xml");
    $fileCode = "translation";
    $missingTranslation = false;
     
    // Check whether a file has been uploaded
    if (isSet($_FILES["$fileCode"]['tmp_name']) and file_exists($_FILES["$fileCode"]['tmp_name'])) {

      $importEnglish = $this->getRequest()->getParam("importEnglish", "0");

      $translation = new Zmax_Translation ("myreview");

      // Import the file
      $translation->import ($_FILES["$fileCode"]['tmp_name']);

      // Save in the DB
      $nbInserts = $translation->save($this->zmax_context->db, $missingTranslation, $importEnglish);
      $this->view->message = "Import OK. : $nbInserts. texts inserted.";
    }
    else {
      $this->view->message = "You must provide a translation file";
    }

    if ($missingTranslation) {
      $this->view->message .= "<br/><b>Warning</b>: some translations are missing.";
    }

    echo $this->view->render("layout");
  }


  function sqlqueriesAction()
  {
    $this->view_initial_message = "";
    $config = $this->zmax_context->config;
    $sql_error = FALSE;
    $this->view->setFile("content", "sql.xml");
    $this->view->set_block("content", "RESULT", "SQL_RESULT");

    if (isSet($_POST['sqlQuery'])) {
      // Always display the submitted query
      $query = $_POST['sqlQuery'];
      $this->view->set_var("SQL_QUERY", $query);
      // Execute the query, put the result in the template
      $this->execQuery ($query, $this->db_v1);
    }
    else {
      // Try to connect to the DB with restricted rights
      $sql_error = FALSE;
      $connexion = @mysql_pconnect ($config->db->params->host,
      $config->db->params->sql_user, $config->db->params->sql_password);
      mysql_query ("SET CHARACTER SET utf8");
      if (!$connexion) {
        $sql_error = TRUE;
      }
      else {
        // Connnect to the DB
        if (!@mysql_select_db ($config->db->params->dbname, $connexion))
        $sql_error = TRUE;
      }
      if ($sql_error) {
        $this->view->set_var("content", $this->texts->get("TXT_INVALID_SQL_USER"));
      }
      else {
        $this->view->set_var("SQL_RESULT", "");
        $this->view->set_var("SQL_QUERY",
				  "SELECT first_name, last_name, roles FROM User ORDER BY last_name"); 
      }
    }
    // Show the query and the result
    echo $this->view->render("layout");
  }


  /**
   * This function runs a query and put the result in the template
   *
   */
  private function execQuery($query,  $db)
  {
    // Remove any /
    $query = stripSlashes ($query);
    $result = $db->execRequete ($query);

    // ProblËme ? On affiche le message d'erreur
    if (!$result or $db->enErreur()) {
      $this->view->set_var("SQL_RESULT", mysql_error());
      return;
    }

    // Create the result
    $nbLines = 0;
    $lines = "";
    while ($line = $db->tableauSuivant ($result)) {
      // Before the first line, show the result header
      if ($nbLines == 0) {
        // Show the attribute names
        $nbAttr = $db->nbrAttributs ($result);
        $header = "";
        for ($i=0; $i < $nbAttr; $i++) {
          $header .= "<th>" . $db->nomAttribut ($result, $i) . "</th>";
        }
        $header = "<tr class='header'>$header</tr>\n";
      }

      // Print each line
      if ($nbLines % 2 == 0) {
        $class = " class='even'";
      }
      else {
        $class = " class='odd'";
      }

      $lines .= "<tr $class>";
      for ($i=0; $i < $nbAttr; $i++) {
        if ($line[$i] == "") $line[$i] = "NULL";
        $lines .= "<td>" . $line[$i] . "</td>";
      }
      $lines .= "</tr>\n";
      $nbLines++;
    }

    if ($nbLines == 0) {
      $this->view->set_var("SQL_RESULT", "Empty result");
    }
    else {
      $this->view->set_var("LINES", $header . $lines);
      $this->view->assign("SQL_RESULT", "RESULT");
    }
  }


  function instantiateConfigVars ($config, &$tpl)
  {
    global $CODES;

    $yesNo = array ('Y'  => 'Yes', 'N' => 'No');

    // Instanciate template variables
    $tpl->set_var("SUBMISSION_URL", $config['submissionURL']);
    $tpl->set_var("CONF_URL", $config['confURL']);
    $tpl->set_var("CONF_ACRONYM", $config['confAcronym']);
    $tpl->set_var("CONF_NAME", $config['confName']);
    $tpl->set_var("CONF_MAIL", $config['confMail']);
    $tpl->set_var("CONF_CURRENCY", $config['currency']);
    $tpl->set_var("CONF_DATE_FORMAT", $config['date_format']);
    $tpl->set_var("CONF_PAYPAL_ACCOUNT", $config['paypal_account']);
    $tpl->set_var("CONF_PASSWORD_GENERATOR", $config['passwordGenerator']);
    $tpl->set_var("CONF_CHAIR_MAIL", $config['chairMail']);
    $tpl->set_var("SHOW_SUBMISSION_DEADLINE",
    DBtoDisplay($config['submissionDeadline'],
    $config['date_format']));

    $tpl->set_var("SHOW_CR_DEADLINE",
    DBtoDisplay($config['cameraReadyDeadline'],
    $config['date_format']));

    $tpl->set_var("CONF_REVIEW_DEADLINE",
    DateField ("reviewDeadline",
			   "", 
    $config['reviewDeadline'],
    $CODES));
    $tpl->set_var("CONF_SUBMISSION_DEADLINE",
    DateField ("submissionDeadline",  "", $config['submissionDeadline'], $CODES));
    $tpl->set_var("CONF_CAMERA_READY_DEADLINE", DateField ("cameraReadyDeadline",
			   "", $config['cameraReadyDeadline'], $CODES));

    $tpl->set_var("CONF_NB_REV_PER_PAPER",$config['nbReviewersPerItem']);
    $tpl->set_var("MAX_ABSTRACT_SIZE",$config['max_abstract_size']);

    $tpl->set_var("LIST_BLIND_REVIEW", RadioFields ('blind_review', $yesNo, $config['blind_review']));
    $tpl->set_var("LIST_TWO_PHASES", RadioFields ('two_phases_submission', $yesNo,
    $config['two_phases_submission']));

    $tpl->set_var("LIST_MULTI_TOPICS", RadioFields ('multi_topics', $yesNo, $config['multi_topics']));

    $tpl->set_var("LIST_SELECTION_OPEN", RadioFields ('isSelectionOpen', $yesNo, $config['isSelectionOpen']));
    $tpl->set_var("LIST_SUBMISSION_OPEN",   RadioFields ('isSubmissionOpen', $yesNo, $config['isSubmissionOpen']));
    $tpl->set_var("LIST_REVIEWING_OPEN",  RadioFields ('isReviewingOpen',  $yesNo, $config['isReviewingOpen']));
    $tpl->set_var("LIST_PROCEEDINGS_OPEN", RadioFields ('isProceedingsOpen',  $yesNo, $config['isProceedingsOpen']));

    $tpl->set_var("LIST_DISCUSSION_MODE", RadioFields ('discussion_mode',  $CODES->get("discussion_mode"),
    $config['discussion_mode']));
    $tpl->set_var("LIST_ASSIGNMENT_MODE", RadioFields ('assignment_mode',  $CODES->get("assignment_mode"),
    $config['assignment_mode']));

    $tpl->set_var("SEND_ON_ABSTRACT", RadioFields ('mailOnAbstract', $yesNo, $config['mailOnAbstract']));
    $tpl->set_var("SEND_ON_UPLOAD",  RadioFields ('mailOnUpload', $yesNo, $config['mailOnUpload']));
    $tpl->set_var("SEND_ON_REVIEW", RadioFields ('mailOnReview', $yesNo, $config['mailOnReview']));
    $tpl->set_var("LIST_STYLE",RadioFields('list_style',
    $CODES->get("list_style"),$config['style_name']));

  }

}