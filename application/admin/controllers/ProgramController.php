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
require_once("DataExport.php");

require_once("RequiredFile.php");
require_once("Mail.php");

/**
 * The program management  controller
 */

class Admin_ProgramController extends Myreview_Controller_Action_Auth
{

  // How many authors shown simultaneously?
  const SIZE_ATTENDEES_GROUP = 50;

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
    $this->view->initial_message = "";
    $this->view->all_participants   = Mail::ALL_PARTICIPANTS;
    echo $this->view->render("layout");
  }


  function slotsAction()
  {
    $crud = new Zmax_Db_Edit ("Slot");
    $this->view->content  =  $crud->edit ($this->getRequest());
    echo $this->view->render("layout");
  }


  function sessionsAction()
  {
    $crud = new Zmax_Db_Edit ("ConfSession");
    $crud->setReferenceField("slot", "ShowSlot");

    $this->view->content  =  $crud->edit ($this->getRequest());
    echo $this->view->render("layout");
  }

  function assignAction()
  {
     
    $this->view->setFile("content","assign.xml");
    $this->view->initial_message = "";

    // Sort either by topic or by status
    if (isSet($_REQUEST['sort_topic']))  {
      $sortOption = "topic";
    }
    else {
      $sortOption = "status";
    }

    // Check whether papers are assigned to sessions
    if (isSet($_REQUEST['form_assign_session']))  {
      foreach ($_REQUEST['conf_session'] as $id_paper => $id_session) {
        if (!empty($id_session)) {
          if (isSet($_REQUEST['position_in_session'][$id_paper])) {
            $pos_in_session = trim($_REQUEST['position_in_session'][$id_paper]);
          }
          else {
            $pos_in_session = "";
          }
          if (!empty($pos_in_session)){
            $this->zmax_context->db->query ("UPDATE Paper SET id_conf_session='$id_session', "
            . "position_in_session='$pos_in_session' "
            . "WHERE id='$id_paper'");
          }
          else {
            $this->db->query ("UPDATE Paper SET id_conf_session='$id_session' "
            . "WHERE id='$id_paper'");
          }
        }
      }
    }
     
    /*  First extract the 'blocks' describing a line from the template */
    $this->view->set_block("content", "PAPER_DETAIL", "PAPERS");

    $conf_sessions  = $this->zmax_context->db->fetchPairs ("SELECT id, name FROM ConfSession");
    $conf_sessions[0] = $this->texts->admin->not_yet_assigned;
    ksort ($conf_sessions);

    // Get the list of required files in the proceedings phase
    $listFiles = array();
    $requiredFileTbl = new RequiredFile();
    $requiredFiles = $requiredFileTbl->fetchAll("id_phase = " . Config::PROCEEDINGS_PHASE);
    foreach ($requiredFiles as $requiredFile) {
      $listFiles[$requiredFile->file_code] = $requiredFile->file_extension;
    }

    // Directory of the CR files
    $fileDir =  ".." . DIRECTORY_SEPARATOR .
    $this->zmax_context->config->app->upload_path . DIRECTORY_SEPARATOR .
        "proceedings" . DIRECTORY_SEPARATOR ;

    // OK. Now execute the query, fetch the papers, display
    $query = "SELECT p.id, p.title, p.CR as cr, p.emailContact, t.label AS topic, IFNULL(id_conf_session,0) id_conf_session, "
    . " IFNULL(position_in_session,999) position_in_session, s.label "
    . " FROM Paper as p,  PaperStatus s, ResearchTopic t WHERE p.status=s.id "
    . " AND cameraReadyRequired ='Y' AND t.id=p.topic "
    . "ORDER BY id_conf_session DESC, position_in_session ASC, $sortOption";
     
    $rPapers = $this->zmax_context->db->query ($query);
    $i= 0;
    while ($paper =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
      $this->view->css_class = Config::CssCLass($i++);

      $this->view->session_list =
      Zmax_View_Phplib::selectField ("conf_session[$paper->id]", $conf_sessions,
      $paper->id_conf_session);

      $this->view->paper_id = $paper->id;
      $this->view->paper_title = $paper->title;
      $this->view->paper_status = $paper->label;
      $this->view->paper_topic = $paper->topic;
      $this->view->paper_position_in_session = $paper->position_in_session;
      $this->view->paper_authors =  PaperRow::getPaperAuthors($this->zmax_context->db,
      $paper);
      $this->view->paper_email_contact = $paper->emailContact;
      $this->view->someUser = Mail::SOME_USER;
       
      if (!$this->config->isPhaseOpen (Config::PROCEEDINGS_PHASE)) {
        $this->view->cr_paper = $this->texts->admin->camera_ready_not_open;
      }
      else  {
        // Take the name of the camera ready file
        foreach ($listFiles as $code => $ext) {
          $filePath =  $fileDir . $code . "_" . $paper->id . "." . $ext;
          if (file_exists($filePath)) {
            $this->view->download_link = $this->texts->camera_ready_uploaded;
          }
          else {
            $this->view->download_link = $this->texts->camera_ready_not_uploaded;
          }
        }
      }
      $this->view->append("PAPERS", "PAPER_DETAIL");
    }

    echo $this->view->render("layout");
     
  }

  // Show an HTML list of the abstracts
  function showabstractsAction ()
  {
    $this->view->setFile ("content", "showabstracts.xml");

    // Instantiate the data export object
    $dataExport = new dataExport($this->view->getScriptPaths());

    $this->view->list_abstracts = $dataExport->exportAbstracts("abstract.xml");

    echo $this->view->render("layout");
  }

  // Show an HTML list of the authors
  function showauthorsAction ()
  {
    $this->view->setFile ("content", "showauthors.xml");

    // Instantiate the data export object
    $dataExport = new dataExport($this->view->getScriptPaths());

    $this->view->list_authors = $dataExport->exportAuthors("author.xml");

    echo $this->view->render("layout");
  }

  // Main function that produces the latex documents
  function latexAction ()
  {
    $this->view->setFile ("content", "latex.xml");
    $this->view->setBlock ("content", "message", "messages");

    // Instantiate the data export object
    $dataExport = new dataExport($this->view->getScriptPaths());

    // The directory of the proceedings files
    $tex_dir = "proceedings/";

    // Load the latex templates
    $this->view->setFile(array("proceedings" =>  "Proceedings.tex",
    "booklet" =>  "Booklet.tex"
    )
    );

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

    // Program of the conference
    $messages .= "Now creating the program of the conference...";
    $program = parseProgram($this->view, "texProgram.tpl", $db);
    $messages .= write_tex ($tex_dir, "program.tex", $program);

    // Papers for the proceedings
    $messages .= "Now creating the list of papers for the proceedings...";
    $papers = parsePapers($this->view,"texProcPapers.tpl", $db, $messages);
    $messages .= write_tex ($tex_dir, "papers.tex", $papers);

    // Output the proceedings
    $messages .= "Now creating the proceedings...";
    $this->view->parse("BODY", "proceedings");
    $contents = $this->view->get_var("BODY");
    $messages .= write_tex ($tex_dir, "proceedings.tex", $contents);

    return $messages;
  }

  function regquestionsAction()
  {
    $ihm = new IhmBD ("RegQuestion", $this->db_v1, $this->myUrl() . "?1=1");
    $ihm->setSlaveTable ("RegChoice", array("id_question" => "id"), 10);
    $ihm->setAutoIncrementedKey ("id_choice");
    $this->view->content  =  $ihm->genererIHM($_REQUEST);   // Show the view
    echo $this->view->render("layout");
  }

  function attendeesAction()
  {
    $userTbl = new User();

    // Put the session in the view
    $this->session->putInView($this->view);

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
      $this->view->set_file ("content",  "attendees.xml");
    }
    else {
      if ($exportType == Config::EXPORT_EXCEL) {
        $this->view->setFile("content", "attendees_xls.xml");
        $mimeType = "text/xls";
        $exportName = "attendees.xls";
      }
      else {
        // Default: HTML
        $this->view->setFile("content", "attendees_html.xml");
        $mimeType = "text/plain";
        $exportName = "attendees.html";
      }
    }

    /* Select all the papers and list them.
     First extract the 'block' describing a line from the template */

    $this->view->set_block("content", "ATTENDEE", "ATTENDEES");
    $this->view->set_block("content", "GROUPS_LINKS", "LINKS");

    if (isSet($_REQUEST['remove'])) {
      $idUser = $this->getRequest()->getParam('id_user');
      // instantiate a PaperRow object. Ok, not elegant, but easier
      $user = $userTbl->find($idUser)->current();
      $user->delete();
    }

    if (isSet($_REQUEST['confirm_payment'])) {
      $idUser = $this->getRequest()->getParam('id_user');
      // instantiate a PaperRow object. Ok, not elegant, but easier
      $user = $userTbl->find($idUser)->current();
      $user->payment_received= 'Y';
      $user->save();
    }

    $nbAttendees = 0;

    // Initialize the current interval
    if (!isSet($_REQUEST['iMin'])) {
      $iMinCur = 1; $iMaxCur = self::SIZE_ATTENDEES_GROUP;
    }
    else {
      $iMinCur = $_REQUEST['iMin'];  $iMaxCur = $_REQUEST['iMax'];
    }
    // Export? No group.
    if ($exportRequired) {
      $iMinCur = 0; $iMaxCur = 999999999;
    }
     
    // Get all the attendees, ordered by last name
    $userTbl = new User();
    $users = $userTbl->fetchAll(" roles LIKE '%P%'", "last_name");
    $this->view->mailType = Mail::SOME_USER;
    $i = 0;
    foreach ($users as $user) {
      $i++;
      if ($i >= $iMinCur and $i <= $iMaxCur) {
        // Choose the CSS class
        $this->view->css_class = Config::CssCLass($i);
        $user->putInView($this->view);

        $this->view->choices = "";
        // Get the answer to open questions
        $rAnswers = $this->zmax_context->db->query ("SELECT * FROM RegAnswer a, RegQuestion q, "
        . " RegChoice c WHERE a.id_question = q.id and a.id_user='$user->id' "
        . " AND a.id_answer=c.id_choice AND c.id_question=q.id");
        while ($answer =  $rAnswers->fetch (Zend_Db::FETCH_OBJ)) {
          $this->view->choices .= "$answer->question_code: $answer->choice; ";
        }
        $this->view->append("ATTENDEES", "ATTENDEE");
      }
    }

    // Export if required
    if ($exportRequired) {
      $this->view->assign ("export", "content");
      $this->exportFile($exportName, $mimeType, $this->view->export);
      return;
    }

    // Create the groups
    $nbAttendees = $i;
    $nb_groups = $nbAttendees /  self::SIZE_ATTENDEES_GROUP + 1;
    for ($i=1; $i <= $nb_groups; $i++) {
      $iMin = (($i-1) *  self::SIZE_ATTENDEES_GROUP) + 1;
      if ($iMin >= $iMinCur and $iMin <= $iMaxCur) {
        $link = "<font color=red>$i</font>";
      }
      else {
        $link =$i;
      }
      $this->view->set_var("LINK", $link);
       
      $this->view->set_var("IMIN_VALUE", $iMin);
      $this->view->set_var("IMAX_VALUE", $iMin + self::SIZE_ATTENDEES_GROUP -1);
      $this->view->append("LINKS", "GROUPS_LINKS");
    }

    $this->view->nb_attendees = $nbAttendees;

    echo  $this->view->render("layout");
  }


}
