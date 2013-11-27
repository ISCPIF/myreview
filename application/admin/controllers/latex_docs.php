<?php
/**********************************************
  The MyReview system for web-based conference management

   Copyright (C) 2003-2006 Philippe Rigaux
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

// Main function that produces the latex documents
function latex_docs ($db)
{
  $messages = "";

  // Instanciate a template object
  $tpl = new Template (".");

  $config = GetConfig($db);
  $tex_dir = $config['uploadDir'] . "/";
  InstanciateConfigVars ($config, $tpl);

  // OK, now load the template files
  $tpl->set_file(array("proceedings" => TPLDIR . "texProceedings.tpl",
		       "latex" => TPLDIR . "latex.tpl",
		       "booklet" => TPLDIR . "texBooklet.tpl"
		       )
		 );

  // Extract the templates
  $tpl->set_block ("latex", "PCMember", "PCMembers");
  $tpl->set_var("PCMembers", "");

  $messages .= "Now creating the PC committee list...";
  // Output a file with the program committee
  $pclist = parsePC($tpl, "PCMember", $db);
  $messages .= write_tex ($tex_dir, "pc.tex", $pclist);

  // Abstracts 
  $messages .= "Now creating the list of abstracts...";
  $abstracts = parseAbstracts($tpl, "texAbstracts.tpl", $db);
  $messages .= write_tex ($tex_dir, "abstracts.tex", $abstracts);

  // Program of the conference
  $messages .= "Now creating the program of the conference...";
  $program = parseProgram($tpl, "texProgram.tpl", $db);
  $messages .= write_tex ($tex_dir, "program.tex", $program);

  // Booklet of abstracts	
  $messages .= "Now creating the booklet of abstracts...";
  $tpl->parse("BODY", "booklet");
  $contents = $tpl->get_var("BODY");
  $messages .= write_tex ($tex_dir, "booklet.tex", $contents);

  // Papers for the proceedings
  $messages .= "Now creating the list of papers for the proceedings...";
  $papers = parsePapers($tpl,"texProcPapers.tpl", $db, $messages);
  $messages .= write_tex ($tex_dir, "papers.tex", $papers);

  // Output the proceedings
  $messages .= "Now creating the proceedings...";
  $tpl->parse("BODY", "proceedings");
  $contents = $tpl->get_var("BODY");
  $messages .= write_tex ($tex_dir, "proceedings.tex", $contents);

   // CD ROM
  $messages .= "Now creating the CD ROM...";
  $tpl->set_file(array("CDROM" => TPLDIR . "texCDROM.tpl")); 
  $tpl->parse("BODY", "CDROM"); 
  $contents = $tpl->get_var("BODY");
  $messages .= write_tex ($tex_dir, "CDROM.tex", $contents);

  return $messages;
}

function write_tex ($tex_dir, $fname, $content)
{
  $handle=fopen("$tex_dir/$fname","w");
  if ($handle) {
    fwrite($handle,$content);
    fclose($handle);
    return "Done!<br>";
  }
  else 
    return "<br><font color=red>ERROR: Unable to write in file "
      . " $tex_dir/$fname</font><br>";
}

// Function used to get rid of bad Latex characters
function replaceBadChars($contents)
{
  $contents =  ereg_replace("&", "\&", $contents);
  $contents =  ereg_replace("_", "\_", $contents);
  $contents =  ereg_replace("", '"', $contents);
  $contents =  ereg_replace("", '"', $contents);
  $contents =  ereg_replace("", "'", $contents);
  $contents =  ereg_replace("", "'", $contents);
  $contents =  ereg_replace("", "--", $contents);
  $contents =  ereg_replace("", "...", $contents);
  $contents =  ereg_replace("", "--", $contents);
  $contents =  str_replace("#", "\\#", $contents);
  return $contents; 
}

// Get the list of PC members, using a template
function parsePC($tpl, $template, $db)
{
  $tpl->set_var("LIST_PC", "");
  // Gets all the members of the PC in the database
  $q_pcmembers = "SELECT lastName, firstName, affiliation FROM PCMember "
    . " WHERE roles LIKE '%R%'";

  $membs = $db->execRequete ($q_pcmembers);
  // For all of them we handle the template as needed
  while ($member = $db->objetSuivant ($membs))
    {
      $tpl->set_var("LAST_NAME", $member->lastName);
      $tpl->set_var("FIRST_NAME",$member->firstName);
      $tpl->set_var("AFFILIATION", $member->affiliation);
      $tpl->parse("LIST_PC", "PCMember", true);
    }
  $contents = $tpl->get_var("LIST_PC");
  replaceBadChars($contents);
  return $contents;
}

// Create and return the list of abstracts
function parseAbstracts($tpl, $templateFileName, $db)
{
  $tpl->set_file(array("abstract" => TPLDIR . $templateFileName));
  $tpl->set_block ("abstract", "PAPER_DETAIL", "PAPERS");
  $tpl->set_block ("PAPER_DETAIL","INDEX","INDEXES");
  $tpl->set_var("PAPERS", "");
  $tpl->set_var("INDEXES", "");

  // Gets all the sessions in the database
  $q_sessions = "SELECT c.id, name, chair, comment as sess_comment, "
    . " end as slot_end, begin as slot_begin, slot_date"
    . " FROM ConfSession c, Slot s "
    . "WHERE s.id=c.id_slot ORDER BY slot_date, begin, end";
  $sess = $db->execRequete ($q_sessions);
  // For all the sessions we handle the template as needed
  while ($session = $db->objetSuivant ($sess))
    {
      $tpl->set_var("CONF_SESSION_NAME", $session->name);
      $tpl->set_var("CONF_SLOT_NAME", 
		    $session->slot_begin . "-" . $session->slot_end);
      $tpl->set_var("SLOT_DATE",$session->slot_date);

      $tpl->set_var("CONF_SESSION_COMMENT", $session->sess_comment);

      $q_papers = "SELECT * FROM Paper "
	. " WHERE id_conf_session=$session->id ORDER BY position_in_session";
      $rp = $db->execRequete ($q_papers);
        
      while ($paper = $db->objetSuivant ($rp))
	{
	  // Instanciate the entities in PAPER_DETAIL. 
	  $currentId = $paper->id;
	  $queryAuthors = "SELECT last_name, first_name from Author "
	    . " WHERE id_paper=$currentId;";
	  $authors = $db->execRequete($queryAuthors);
	  while($author = $db->objetSuivant($authors))
            {
	      $tpl->set_var("AUTEUR",
			    $author->last_name . ", " . $author->first_name);
	      $tpl->parse("INDEXES", "INDEX", true);
            }
	  InstanciatePaperVars ($paper, $tpl, $db, "", false);
	  $tpl->parse("PAPERS", "PAPER_DETAIL", true);
	} 
    }
  $tpl->parse("BODY", "abstract");

  $contents = $tpl->get_var("BODY");
  // Get rid of Mac non-printable characters
  $contents = replaceBadChars($contents);

  return $contents;
}

// The program of the conference
function parseProgram($tpl, $templateFileName, $db)
{
  $tpl->set_file(array("program" => TPLDIR . $templateFileName));
  $tpl->set_block ("program", "SESSION_DETAIL", "SESSIONS");
  $tpl->set_block ("SESSION_DETAIL", "PAPER_DETAIL", "PAPERS");
  $tpl->set_block ("SESSION_DETAIL", "CHAIR", "SHOW_CHAIR");
  $tpl->set_var("SESSIONS", "");
  
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
      $tpl->set_var("PAPERS", "");
      $tpl->set_var("CONF_SESSION_NAME", $session->name);
      $tpl->set_var("CONF_SESSION_ROOM", $session->room);
      $tpl->set_var("CONF_SLOT_NAME",$session->slot_begin . "-" 
		    . $session->slot_end);

      if (strcmp($session->slot_date,$lastDate))
	{
	  $lastDate =  $session->slot_date;
	  $tpl->set_var("SLOT_DATE", date (OUTPUT_DATE_FORMAT, 
					   strtotime ($session->slot_date)));
	}
      else
	  $tpl->set_var("SLOT_DATE","");

      $tpl->set_var("CONF_SESSION_CHAIR", $session->chair);
      if (empty ($session->chair))
	$tpl->set_var("SHOW_CHAIR", "");
      else
	$tpl->parse("SHOW_CHAIR", "CHAIR");

      $tpl->set_var("CONF_SESSION_COMMENT", $session->sess_comment);

      $q_papers = "SELECT * FROM Paper "
	. " WHERE id_conf_session=$session->id ORDER BY position_in_session";
      $rp = $db->execRequete ($q_papers);
     
      while ($paper = $db->objetSuivant ($rp))
	{
	  // Instanciate the entities in PAPER_DETAIL. 
	  InstanciatePaperVars ($paper, $tpl, $db, "", false);
	  $tpl->parse("PAPERS", "PAPER_DETAIL", true);
	} 
      $tpl->parse("SESSIONS", "SESSION_DETAIL", true);
    }

  $tpl->parse("THE_PROGRAM", "program");
  $contents = $tpl->get_var("THE_PROGRAM");
  // this should be a function (get rid of Mac non-printable characters)
  replaceBadChars($contents);
  return $contents;
}

// Create the Latex commands for the list of papers in the proceedings
function parsePapers ($tpl, $templateFileName, $db, &$messages)
{
  $logs = "";

  $config = GetConfig($db);

  $tpl->set_file (array("papers"=> TPLDIR . $templateFileName));
  $tpl->set_block ("papers", "ARTICLE", "ARTICLES");
  $tpl->set_block ("ARTICLE","INDEX", "INDEXES");
  $tpl->set_var ("INDEXES", "");
  $tpl->set_var ("ARTICLES", "");

  // Take the paper following their order in the slots/sessions
  $query = "SELECT p.* FROM Paper p, ConfSession c, Slot s "
    . "WHERE p.id_conf_session = c.id AND c.id_slot=s.id "
    . " ORDER BY s.slot_date, s.begin, c.id, p.position_in_session ";
  $res = $db->execRequete ($query);
  while ($paper = $db->objetSuivant($res))
    {
      InstanciatePaperVars ($paper, $tpl, $db, "", false);

      // We have to check that the file actually exists, 
      // so as to avoid problems with \includepdf
      $cr_name = CRNamePaper($config['uploadDir'], $paper->status,
		    $paper->id, $paper->format);

      if (file_exists($cr_name))
	{
	  $tpl->set_var("INDEXES", "");
	  $queryIndex = "SELECT last_name, first_name FROM Author "
	    . " WHERE id_paper='$paper->id'";
	  // For each paper, we need to get the list of all the authors
	  $authors = $db->execRequete ($queryIndex);
	  while ($author = $db->objetSuivant($authors))
	    {
	      $tpl->set_var("AUTEUR", $author->last_name.", " 
			    .$author->first_name);		
	      $tpl->parse("INDEXES","INDEX", true);
	    }
	  $tpl->parse("ARTICLES", "ARTICLE", true);
	}
      else {
	$messages .= 
	  "<br><font color=red>ERROR: Unable to find the file $cr_name" 
	  . " of the paper <i>$paper->title</i></font><br>";
      }

    }
  $tpl->parse("BODY", "papers");

  $contents = $tpl->get_var("BODY");
  replaceBadChars($contents);
  return $contents;
}
?>
