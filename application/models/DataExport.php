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

require_once("User.php");

/**
 * This class is used to export data in various formats, including
 * the proceedings in Latex or text format
 */

class DataExport
{
  // Enumerate the type of mail (batch yes/no, reviewer/author)
  const TEXT=1, LATEX=2, XML=3;

  private $_db, $_config, $_view, $_texts;

  private $_filePath;

  function __construct($scriptPath)
  {
    // Keep the context objects
    $this->_db =  Zend_Db_Table::getDefaultAdapter();

    // get the config from the registry
    $registry = Zend_registry::getInstance();
    $this->_config = $registry->get("Config");

    // Get the Zmax context for translations
    $zmax_context = $registry->get("zmax_context");
    $this->_texts = $zmax_context->texts;

    // Create a template engine to instantiate template mails
    $this->_view = new Zmax_View_Phplib();
    $this->_view->setPath ($scriptPath);

    // Put configuration information in the view (always useful)
    $this->_config->putInView($this->_view);

    // Files are always created in the upload area
    $this->_filePath = $zmax_context->config->app->upload_path;
  }

  /**
   * get the view object
   */
  function getView() {return $this->_view;}

  /**
   * Write a file in a directory
   *
   * @param $dir
   * @param $fname
   * @param $content
   */
  function writeFile ($dir, $fname, $content)
  {
    $handle=fopen("../" . $this->_filePath . "/$dir$fname","w");
    if ($handle) {
      fwrite($handle,$content);
      fclose($handle);
      return ;
    }
    else {
      throw new Zmax_Exception ("Unable to write " . $fname . " in " . $dir);
    }
  }


  /**
   * Download a file
   *
   * @param $dir
   * @param $fname
   * @param $content
   */
  function downloadFile ($dir, $fname)
  {
    $type = "application/octet-stream";
    $texFile = "../" . $this->_filePath . "/$dir$fname";
    header("Content-disposition: attachment; filename=$fname");
    header("Content-Type: application/force-download");
    header("Content-Transfer-Encoding: $type\n");
    header("Content-Length: ".filesize($texFile));
    header("Pragma: no-cache");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
    header("Expires: 0");
    readfile_chunked($texFile);
  }

  // Function used to get rid of bad Latex characters
  function replaceBadChars($contents)
  {
    $contents =  str_replace("/&/", "\&", $contents);
    $contents =  str_replace("/_/", "\_", $contents);
     $contents =  str_replace("#", "\#", $contents);
    $contents =  str_replace("^", "\\^", $contents);

    return $contents;
  }

  function fixoutput($str){
    $newstr = "";
    $len = strlen($str);
    $b = 0;
    while ($b < $len) {
      if($str[$b] == "«") {
        $newstr .= "\"";
        $newstr .= "";
        $b++;
      }
      if($str[$b] == "»") {
        $newstr .= "\"";
        $newstr .= "";
        $b++;
      }
      else  {
        $newstr .= $str[$b];
      }
      $b++;
    }//rof
    return $newstr;
  }

  // Get the list of PC members, using a template
  function exportProgramCommittee($templateFile)
  {
    // Load the template
    $this->_view->setFile ("member", $templateFile);

    $userTbl = new User();
    $this->_view->list_pc = "";
    $members = $userTbl->fetchAll("roles LIKE '%R%'");
    foreach ($members as $member) {
      $member->putInView($this->_view);
      $this->_view->append("list_pc", "member");
    }
    $contents = $this->_view->list_pc;
    $this->replaceBadChars($contents);
    return $contents;
  }

  /**
   *  Create and return the list of abstracts
   *
   *  The template must be organized with the following block hierarchy
   *  day
   *    session
   *      paper
   *        index
   *        abstract
   * @return unknown_type
   */
  function exportAbstracts($templateName, $exportType=self::LATEX)
  {
    $this->_view->set_file("proceedings", $templateName);
    $this->_view->setBlock ("proceedings", "day", "days");
    $this->_view->setBlock ("day", "session", "sessions");
    $this->_view->setBlock ("session", "paper", "papers");
    try {
      $this->_view->setBlock ("paper","index","indexes");
    }
    catch (Exception $e) {
    }
    $this->_view->setBlock ("paper","abstract","abstracts");

    // Get the list of countries
    $countries  = $this->_db->fetchPairs ("SELECT code, name FROM Country");


    $iPaper = 0;
    $q_days = "SELECT DISTINCT UNIX_TIMESTAMP(slot_date) AS slot_date FROM  Slot s "
    . " ORDER BY slot_date";
    $rDays = $this->_db->query ($q_days);
    while ($day =  $rDays->fetch (Zend_Db::FETCH_OBJ)) {

        $this->_view->slot_date = date ("l, F j", $day->slot_date);
        $this->_view->sessions = "";
        
      $q_sessions = "SELECT c.id, name, chair, comment as sess_comment, room, "
      . " end as slot_end, begin as slot_begin, UNIX_TIMESTAMP(slot_date) AS timestamp "
      . " FROM ConfSession c, Slot s "
      . " WHERE s.id=c.id_slot AND UNIX_TIMESTAMP(slot_date) = '{$day->slot_date}'"
      . " ORDER BY slot_date, begin, end, c.id";

      $rSess = $this->_db->query ($q_sessions);
      while ($session =  $rSess->fetch (Zend_Db::FETCH_OBJ)) {
        $this->_view->conf_session_name = $session->name;
        $this->_view->conf_slot_name = substr($session->slot_begin, 0, 5)
        . "-" . substr($session->slot_end, 0, 5);
        $this->_view->conf_session_comment = $session->sess_comment;
        $this->_view->conf_session_chair = $session->chair;
        $this->_view->conf_session_room = $session->room;
        $this->_view->papers = "";

        $q_papers = "SELECT * FROM Paper "
        . "WHERE id_conf_session='$session->id' ORDER BY position_in_session";
        $rp = $this->_db->query ($q_papers);
        while ($paper =  $rp->fetch (Zend_Db::FETCH_OBJ)) {
          $iPaper++;
          $this->_view->indexes = "";
          $this->_view->abstracts = "";
          $this->_view->paper_title = $paper->title;
          $this->_view->paper_position = $iPaper;
          $this->_view->paper_id = $paper->id;
          $this->_view->paper_authors =  PaperRow::getPaperAuthors($this->_db, $paper);
          $this->_view->authors_affiliations = "";

          // Instanciate the entities in PAPER_DETAIL.
          $queryAuthors = "SELECT u.last_name, u.first_name, u.affiliation, u.country_code from User u, Author a "
          . " WHERE a.id_paper='$paper->id' AND u.id=a.id_user ";
          $rAuthors = $this->_db->query  ($queryAuthors);
          $comma = "";
          while ($author =  $rAuthors->fetch (Zend_Db::FETCH_OBJ)) {
            $countryName = $countries[$author->country_code];
            $this->_view->author =  $author->last_name . ", " . $author->first_name;
            $this->_view->authors_affiliations .= $comma . $author->affiliation . " ($countryName)";
            $comma = ", ";
            try {
              $this->_view->append("indexes", "index");
            }
            catch (Exception $e) {
            }
          }

          $queryAbstracts = "SELECT * from AbstractSection u, Abstract a "
          . " WHERE a.id_paper='$paper->id' AND u.id=a.id_section ORDER BY position ";
          $rAbstracts = $this->_db->query  ($queryAbstracts);
          while ($abstract =  $rAbstracts->fetch (Zend_Db::FETCH_OBJ)) {
            $this->_view->section_name =  $this->_texts->author->get($abstract->section_name);
            $this->_view->abstract_content =  $abstract->content;
            $this->_view->append("abstracts", "abstract");
          }

          $this->_view->append("papers", "paper");
        }
        $this->_view->append("sessions", "session");
      }
        $this->_view->append("days", "day");
    }

    $this->_view->assign("result", "proceedings");

    $contents = $this->_view->result;

    // Get rid of Mac non-printable characters
    if ($exportType == self::LATEX) {
      $contents = $this->replaceBadChars($contents);
    }
    return $contents;
  }

  /**
   *  Create and return the list of authors
   */
  function exportAuthors($templateName, $exportType=self::LATEX)
  {
    $this->_view->set_file("template", $templateName);
    $this->_view->setBlock ("template", "author", "authors");
    try {
      $this->_view->setBlock ("author", "paper", "papers");
    }
    catch (Exception $e) {}

    // Get the list of countries
    $countries  = $this->_db->fetchPairs ("SELECT code, name FROM Country");

    // First, compute the position of papers
    $iPaper = 0;
    $paperInfo = array();

    $q_sessions = "SELECT c.id, name, chair, comment as sess_comment, room, "
    . " end as slot_end, begin as slot_begin, UNIX_TIMESTAMP(slot_date) AS timestamp "
    . " FROM ConfSession c, Slot s "
    . " WHERE s.id=c.id_slot "
    . " ORDER BY slot_date, begin, end, c.id";

    $rSess = $this->_db->query ($q_sessions);
    while ($session =  $rSess->fetch (Zend_Db::FETCH_OBJ)) {
      $q_papers = "SELECT * FROM Paper "
      . "WHERE id_conf_session='$session->id' ORDER BY position_in_session";
      $rp = $this->_db->query ($q_papers);
      while ($paper =  $rp->fetch (Zend_Db::FETCH_OBJ)) {
        $iPaper++;
        $paperInfo[$paper->id] = array ("position" => $iPaper,
             "title" => $paper->title);
      }
    }
     
    // OK, now loop on authors
    $qAuthors="SELECT u.* FROM User u, Author a, PaperStatus s, Paper p ".
       	  "WHERE u.id=a.id_user AND a.id_paper= p.id "
       	  .    " AND p.status=s.id and cameraReadyRequired='Y' "
       	  .  "ORDER BY u.last_name, u.first_name";
       	  $rAuthors = $this->_db->query ($qAuthors);
       	  while ($author =  $rAuthors->fetch (Zend_Db::FETCH_OBJ)) {
       	    $this->_view->author_name = $author->first_name . " " . $author->last_name;
       	    $this->_view->author_affiliation = $author->affiliation;
       	    $this->_view->papers = "";
       	    $qPapers = "SELECT * FROM Paper p, Author a WHERE a.id_user='$author->id' "
       	    .  " AND a.id_paper = p.id ORDER BY position";
       	    $rPapers = $this->_db->query ($qPapers);
       	    $positions = array();
       	    while ($paper =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
       	      if (isSet($paperInfo[$paper->id])) {
       	        $this->_view->paper_title = $paper->title;
       	        $this->_view->paper_position = $paperInfo[$paper->id]["position"];
       	        $positions[] = $paperInfo[$paper->id]["position"];
       	        try {
       	          $this->_view->append ("papers", "paper");
       	        }
       	        catch (Exception $e) {}
       	      }
       	    }
       	    // Sort the position
       	    sort ($positions);
       	    $paper_positions = $comma = "";
       	    for ($i=0; $i < count($positions); $i++) {
       	      $paper_positions .= $comma . $positions[$i];
       	      $comma = ", ";
       	    }
       	    $this->_view->paper_positions = $paper_positions;
       	    $this->_view->append("authors", "author");
       	  }
       	  $this->_view->assign("result", "template");

       	  $contents = $this->_view->result;

       	  // Get rid of Mac non-printable characters
       	  if ($exportType == self::LATEX) {
       	    $contents = $this->replaceBadChars($contents);
       	  }
       	  return $contents;
  }


  // The program of the conference
  function parseProgram()
  {
    $this->_view->set_file(array("program" => TPLDIR . $templateFileName));
    $this->_view->set_block ("program", "SESSION_DETAIL", "SESSIONS");
    $this->_view->set_block ("SESSION_DETAIL", "PAPER_DETAIL", "PAPERS");
    $this->_view->set_block ("SESSION_DETAIL", "CHAIR", "SHOW_CHAIR");
    $this->_view->set_var("SESSIONS", "");

    // Take the paper following the order of their presentation
    $q_sessions = "SELECT c.id, name, room, chair, comment as sess_comment, "
    . " end as slot_end, begin as slot_begin, slot_date"
    . " FROM ConfSession c, Slot s "
    . "WHERE s.id=c.id_slot ORDER BY slot_date, begin, end";
    $sess = $db->execRequete ($q_sessions);
    // For all the sessions we handle the template as needed
    $lastDate="";
    while ($session = $db->objetSuivant ($sess))
    {
      $this->_view->set_var("PAPERS", "");
      $this->_view->set_var("CONF_SESSION_NAME", $session->name);
      $this->_view->set_var("CONF_SESSION_ROOM", $session->room);
      $this->_view->set_var("CONF_SLOT_NAME",$session->slot_begin . "-"
      . $session->slot_end);

      if (strcmp($session->slot_date,$lastDate))
      {
        $lastDate =  $session->slot_date;
        $this->_view->set_var("SLOT_DATE", date (OUTPUT_DATE_FORMAT,
        strtotime ($session->slot_date)));
      }
      else
      $this->_view->set_var("SLOT_DATE","");

      $this->_view->set_var("CONF_SESSION_CHAIR", $session->chair);
      if (empty ($session->chair))
      $this->_view->set_var("SHOW_CHAIR", "");
      else
      $this->_view->parse("SHOW_CHAIR", "CHAIR");

      $this->_view->set_var("CONF_SESSION_COMMENT", $session->sess_comment);

      $q_papers = "SELECT * FROM Paper "
      . " WHERE id_conf_session=$session->id ORDER BY position_in_session";
      $rp = $db->execRequete ($q_papers);
       
      while ($paper = $db->objetSuivant ($rp))
      {
        // Instanciate the entities in PAPER_DETAIL.
        InstanciatePaperVars ($paper, $this->_view, $db, "", false);
        $this->_view->parse("PAPERS", "PAPER_DETAIL", true);
      }
      $this->_view->parse("SESSIONS", "SESSION_DETAIL", true);
    }

    $this->_view->parse("THE_PROGRAM", "program");
    $contents = $this->_view->get_var("THE_PROGRAM");
    // this should be a function (get rid of Mac non-printable characters)
    replaceBadChars($contents);
    return $contents;
  }

  // Create the Latex commands for the list of papers in the proceedings
  function parsePapers ()
  {
    $logs = "";

    $config = GetConfig($db);

    $this->_view->set_file (array("papers"=> TPLDIR . $templateFileName));
    $this->_view->set_block ("papers", "ARTICLE", "ARTICLES");
    $this->_view->set_block ("ARTICLE","INDEX", "INDEXES");
    $this->_view->set_var ("INDEXES", "");
    $this->_view->set_var ("ARTICLES", "");

    // Take the paper following their order in the slots/sessions
    $query = "SELECT p.* FROM Paper p, ConfSession c, Slot s "
    . "WHERE p.id_conf_session = c.id AND c.id_slot=s.id "
    . " ORDER BY s.slot_date, s.begin, c.id, p.position_in_session ";
    $res = $db->execRequete ($query);
    while ($paper = $db->objetSuivant($res))
    {
      InstanciatePaperVars ($paper, $this->_view, $db, "", false);

      // We have to check that the file actually exists,
      // so as to avoid problems with \includepdf
      $cr_name = CRNamePaper($config['uploadDir'], $paper->status,
      $paper->id, $paper->format);

      if (file_exists($cr_name))
      {
        $this->_view->set_var("INDEXES", "");
        $queryIndex = "SELECT last_name, first_name FROM Author "
        . " WHERE id_paper='$paper->id'";
        // For each paper, we need to get the list of all the authors
        $authors = $db->execRequete ($queryIndex);
        while ($author = $db->objetSuivant($authors))
        {
          $this->_view->set_var("AUTEUR", $author->last_name.", "
          .$author->first_name);
          $this->_view->parse("INDEXES","INDEX", true);
        }
        $this->_view->parse("ARTICLES", "ARTICLE", true);
      }
      else {
        $messages .=
	  "<br><font color=red>ERROR: Unable to find the file $cr_name" 
        . " of the paper <i>$paper->title</i></font><br>";
      }

    }
    $this->_view->parse("BODY", "papers");

    $contents = $this->_view->get_var("BODY");
    replaceBadChars($contents);
    return $contents;
  }
}
