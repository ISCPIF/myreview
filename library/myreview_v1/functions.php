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

/************************************************************

Papers management

*************************************************************/


// Get the config
function GetConfig ($bd, $mode="array")
{
  // There should be only one line in Config
  $query = "SELECT * FROM Config";
  $result = $bd->execRequete ($query);
  if ($mode == "array")
  $config = $bd->ligneSuivante ($result);
  else
  $config = $bd->objetSuivant ($result);
  return $config;
}


function CheckEMail($email){
  // Check the fields of an email
  return preg_match("^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$^", $email);
}


// Get a paper with its id
function GetPaper ($id, $bd, $mode="array")
{
  $query = "SELECT * FROM Paper WHERE id = '$id'" ;
  $result = $bd->execRequete ($query);
  if ($mode == "array")
  $paper = $bd->ligneSuivante ($result);
  else
  $paper = $bd->objetSuivant ($result);
  return $paper;
}

// Get the list of authors for a paper
function GetAuthors ($id, $bd, $blind="N", $mode="array", $others="")
{
  $query = "SELECT * FROM Author WHERE id_paper = '$id' ORDER BY position" ;
  $result = $bd->execRequete ($query);
  if ($mode == "array")
  {
    $authors = array();
  }
  else
  {
    // Blind review? Never show the authors
    if ($blind == "Y") return "(blind review)";
    $comma = ""; $authors = "";
  }
  $i = 0;
  while ($author = $bd->objetSuivant($result))
  {
    if ($mode == "array")
    {
      $authors[$i]["first_name"] = $author->first_name;
      $authors[$i]["last_name"] = $author->last_name;
      $authors[$i]["affiliation"] = $author->affiliation;
    }
    else // Create a string
    {
      $authors .= "$comma $author->first_name $author->last_name" ;
      // . "($author->affiliation)";
      $comma = ", ";
    }
    $i++;
  }
  if ($mode == "string" and !empty($others)) $authors .= "$comma $others";
  return $authors;
}

// Get the list of topics for a paper
function GetPaperTopics ($id_paper, $bd, $mode="array")
{
  $query = "SELECT r.id, r.label FROM PaperTopic p, ResearchTopic r "
  . " WHERE id_paper = '$id_paper' AND r.id=p.id_topic";
  $result = $bd->execRequete ($query);
  if ($mode == "array")
  $topics = array();
  else {
    $comma = ""; $topics = "";
  }
  $i = 0;
  while ($topic = $bd->objetSuivant($result))
  {
    if ($mode == "array")
    $topics[$topic->id] = $topic->label;
    else // Create a string
    {
      $topics .= "$comma $topic->label" ;
      $comma = ", ";
    }
    $i++;
  }
  return $topics;
}

// Get the list of status
function GetListStatus ($db)
{
  $listS = array();
  $query = "SELECT * FROM PaperStatus ";
  $result = $db->execRequete ($query);
  while ($status =  $db->objetSuivant ($result))
  {
    $listS[$status->id] = array("label" => $status->label,
				  "mailTemplate" => $status->mailTemplate);
  }
  return $listS;
}

// Get the list of criterias
function GetListCriterias ($db)
{
  $listC = array();
  $query = "SELECT * FROM Criteria ORDER BY id";
  $result = $db->execRequete ($query);
  while ($cr =  $db->objetSuivant ($result))  {
    $listC[$cr->id] = array("label" => $cr->label,
			    "explanations" => $cr->explanations,
			    "weight" => $cr->weight);
  }
  return $listC;
}

// Get a review mark
function GetReviewMark ($idPaper, $email, $idCriteria, $bd, $mode="array")
{
  $query = "SELECT * FROM ReviewMark WHERE idPaper = '$idPaper' "
  . "AND email='$email' AND idCriteria='$idCriteria'" ;
  $result = $bd->execRequete ($query);
  if ($mode == "array")
  $reviewMark = $bd->ligneSuivante ($result);
  else
  $reviewMark = $bd->objetSuivant ($result);
  return $reviewMark;
}

/************************************************************

PC members management

*************************************************************/

// Get a member given his email
function GetMember ($email, $db, $mode="array")
{
  $safe_email = $db->prepareString($email);
  $query = "SELECT * FROM User WHERE email = '$safe_email'" ;
  $result = $db->execRequete ($query);
  if ($mode == "array")
  return $db->ligneSuivante ($result);
  else
  return $db->objetSuivant ($result);
}


/************************************************************

Review management

*************************************************************/

// Get a review with its key
function GetReview ($idPaper, $id_user, $bd)
{
  // First get the review
  $query = "SELECT * FROM Review "
  . " WHERE idPaper = '$idPaper' and id_user='$id_user' ";

  $result = $bd->execRequete ($query);
  $review = $bd->ligneSuivante ($result);

  if (!$review) return $review;

  // Else  get the criterias
  $listC = GetListCriterias ($bd);
  foreach ($listC as $id => $crVals)
  {
    $qCr = "SELECT * FROM ReviewMark WHERE idPaper='$idPaper' "
    . "AND id_user='$id_user' AND idCriteria='$id'";
    $rCr = $bd->execRequete($qCr);
    $cr = $bd->objetSuivant($rCr);
    if (is_object($cr))
    $review[$id] = $cr->mark;
    else
    $review[$id] = "";
  }
  return $review;
}

// Get a rating with its key
function GetRating ($idPaper, $email, $bd)
{
  $query = "SELECT * FROM Rating "
  . " WHERE idPaper = '$idPaper' and email='$email' ";

  $result = $bd->execRequete ($query);
  return $bd->objetSuivant ($result);
}

// Count the reviewers for a paper
function CountReviewers ($idPaper, $bd)
{
  $query = "SELECT COUNT(*) AS nbRev FROM Review "
  . " WHERE idPaper = '$idPaper'" ;
  $result = $bd->execRequete ($query);
  $rev =  $bd->objetSuivant ($result);
  if ($rev)
  return $rev->nbRev;
  else
  return 0;
}

// Get an array of the reviewers for a paper
function GetReviewers ($idPaper, $bd)
{
  $tabReviewers = array();
  $query = "SELECT p.* FROM Review r, PCMember p "
  . " WHERE r.idPaper = '$idPaper' AND p.email=r.email ";
  $result = $bd->execRequete ($query);
  while ($rev =  $bd->objetSuivant ($result))
  $tabReviewers[] = $rev;

  return $tabReviewers;
}

// Count the papers for a reviewer
function CountPapers ($email, $bd)
{
  $query = "SELECT COUNT(*) AS nbPapers FROM Review "
  . " WHERE email = '$email' " ;
  $result = $bd->execRequete ($query);
  $rev =  $bd->objetSuivant ($result);
  if ($rev)
  return $rev->nbPapers;
  else
  return 0;
}

// Recherche de l'intitul� d'une codif
function LibelleCodif ($nomCodif, $code, $db)
{
  $query = "SELECT * FROM $nomCodif WHERE id = '$code'" ;
  $result = $db->execRequete ($query);
  $codif = $db->ligneSuivante ($result);
  return $codif['label'];
}

/***************** INSTANCIATION OF TEMPLATES VARIABLES ************/
function InstanciatePaperVars ($paper, &$tpl, $db, $id_session="", $html=true)
{
  $config = GetConfig($db);

 
  // Instanciate template variables related to a paper
  $tpl->set_var("PAPER_ID", $paper->id);
  $tpl->set_var("PAPER_TITLE", String2HTML($paper->title, $html));

  $blind_review = false;

  /* if (empty($id_session)) $id_session = session_id();
  $session = GetSession ($id_session, $db);
  if (is_object($session)) {
    // If blind review is 'Y', hide the authors names,
    // except for chairs
    if ($config["blind_review"] == "Y" and
    !strstr($session->roles, "C"))
    $blind_review = true;
  }
  */

//  $tpl->set_var("PAPER_AUTHORS",  String2HTML(GetAuthors($paper->id, $db, $blind_review,
//				       "string", $paper->authors), $html));

  $tpl->set_var("PAPER_ABSTRACT", String2HTML($paper->abstract,$html));
  $tpl->set_var("PAPER_EMAIL_CONTACT", $paper->emailContact);
  //  $tpl->set_var("PAPER_WEIGHT", $paper->assignmentWeight);
  $tpl->set_var("PAPER_TOPIC", String2HTML(LibelleCodif("ResearchTopic",
  $paper->topic, $db), $html));
  $tpl->set_var ("PAPER_OTHER_TOPICS",
  GetPaperTopics ($paper->id, $db, "string"));
  $tpl->set_var("PAPER_NB_REVIEWERS", CountReviewers ($paper->id, $db));
  $tpl->set_var("PAPER_FILE_SIZE", $paper->fileSize);
  $tpl->set_var("PAPER_ID_CONF_SESSION", $paper->id_conf_session);
  $tpl->set_var("PAPER_POSITION_IN_SESSION", $paper->position_in_session);
}


// Date input
function DateField ($field_name, $field_seed, $default, &$codes)
{
  // Decode the default date
  $day = substr ($default, 8, 2);
  $month = substr ($default, 5, 2);
  $year = substr ($default, 0, 4);

  // Create the lists
  $cur_year = Date("Y");
  for ($d = 1; $d <= 31; $d++) $list_days[$d] = $d;
  for ($y = $cur_year - 1; $y <= $cur_year + 5; $y++)
  $list_years[$y] = $y;

  $list_months = $codes->get("months");

  // Create the select fields
  $day_field = SelectField ($field_name . "[" . $field_seed . "_day]",
  $list_days, $day);
  $month_field = SelectField ($field_name . "[" . $field_seed . "_month]",
  $list_months, $month);
  $year_field = SelectField ($field_name . "[" . $field_seed . "_year]",
  $list_years, $year);

  return $day_field . $month_field . $year_field;
}

function DBtoDisplay($date, $date_format){
  $tab=explode('-',$date);
  return date ($date_format, mktime (0, 0, 0, $tab[1], $tab[2], $tab[0]));
}

function DisplaytoDB($date){
  $tab=explode('/',$date);
  return $tab[2]."-".$tab[1]."-".$tab[0];
}

function is_num($str){
  return preg_match("/^\d+$/",$str);
}

function isCorrectOrder($date1,$date2){
  return (strtotime(DisplaytoDB($date1))<=strtotime(DisplaytoDB($date2)));
}

function isCorrectDate($date){
  /* controle de la longueur de la chaine jj/mm/aaaa = 10 */
  if(strlen($date)==10){
    if(substr($date,2,1)=="/" && substr($date,5,1)=="/"){
      /* les caract�res 1 et 6 sont des " / "  */
      if (is_num(substr($date,0,2)) && is_num(substr($date,3,2))
      && is_num(substr($date,6,4))) {
        $jour=intval(substr($date,0,2)); /* PHP num�rote les chaines depuis 0 */
        $mois=intval(substr($date,3,2));
        $annee=intval(substr($date,6,4));
        if($mois>=1 && $mois<=12){  /* verifie que le mois verifie 1<mois<12 */
          if($jour<=longueurMois($mois,$annee)){ /* controle le jour par */
            return true;                        /* rapport a la longueur du mois */
          }
          else {
            return false;
          }
        }
        else {
          return false;
        }
      }
      else {
        return false;
      }
    }
    else {
      return false;
    }
  }
  else {
    return false;
  }
}


function longueurMois($mois,$annee){
  if ($mois==4 || $mois==6 || $mois==9 || $mois==11) return 30;
  else if (($mois==2) && estBissextile($annee)) return 29;
  else if ($mois==2) return 28;
  else return 31;
}

function estBissextile($ans){
  if ((($ans % 4 == 0) && $ans % 100 != 0) || $ans % 400 == 0)
  return true;/*c'est une ann�e bissextile */
  else
  return false;/*ce n'en est pas une */
}


// The following function prepares a string for HTML output.
// All special characters are replaced by their entity ref.,
// and the newlines are converted to <br>
function String2HTML ($str, $html=true)
{
  if ($html)
  return nl2br(htmlSpecialChars($str));
  else
  return $str;
}


// Select list for HTML Forms
function  SelectField ($nom, $liste, $defaut, $taille=1)
{
  $s = "<SELECT NAME=\"$nom\" SIZE='$taille'>\n";
  while (list ($val, $libelle) = each ($liste))
  {
    // Attention aux probl�mes d'affichage
    $val = htmlSpecialChars($val);
    $defaut = htmlSpecialChars($defaut);

    if ($val != $defaut)
    $s .=  "<OPTION VALUE=\"$val\">$libelle</OPTION>\n";
    else
    $s .= "<OPTION VALUE=\"$val\" SELECTED>$libelle</OPTION>\n";
  }
  return $s . "</SELECT>\n";
}

// Get a code list
function GetCodeList ($tableName, $db, $id="id", $name="name",
$output=array())
{
  $res = $output;
  $result = $db->execRequete ("SELECT $id, $name FROM $tableName");
  while ($cursor = $db->ligneSuivante ($result))
  {
    $res[$cursor[$id]] = $cursor[$name];
  }
  return $res;
}

// Radio list HTML Forms
function  RadioFields ($nom, $liste, $defaut)
{
  // Always dispay in a table
  $libelles=$champs="";
  $nbChoix = 0;
  $result = "<TABLE BORDER=0 CELLSPACING=5 CELLPADDING=2>\n";
  while (list ($val, $libelle) = each ($liste))
  {
    $libelles .= "<td><B>$libelle</B></td>";
    $checked = " ";
    if ($val == $defaut) $checked = "CHECKED";

    $champs .= "<td><INPUT TYPE='RADIO' "
    . "NAME=\"$nom\" VALUE=\"$val\" $checked> </td>\n";
  }

  if (!empty($champs))
  return  $result . "<tr>" . $libelles .  "</tr>\n"
  . "<tr>" . $champs . "</tr></table>";
  else return $result . "</table>";
}

// Checkbox list HTML Forms
function  CheckBoxFields ($nom, $liste, $listChecked)
{
  // Always dispay in a table
  $libelles=$champs="";
  $nbChoix = 0;
  $result = "<TABLE BORDER=0 CELLSPACING=5 CELLPADDING=2>\n";
  while (list ($val, $libelle) = each ($liste))
  {
    $libelles .= "<td><B>$libelle</B></td>";
    $checked = " ";
    if (array_key_exists ($val, $listChecked))
    $checked = "CHECKED";

    $champs .= "<td><INPUT TYPE='CHECKBOX' "
    . "NAME=\"$nom\" VALUE=\"$val\" $checked> </td>\n";
  }

  if (!empty($champs))
  return  $result . "<tr>" . $libelles .  "</tr>\n"
  . "<tr>" . $champs . "</tr></table>";
  else return $result . "</table>";
}

function ShowInvoice ($person, $db, &$tpl, $template)
{
  // Show the invoice
  $tpl->set_file ($template, TPLDIR . $template);
  $tpl->set_block ($template, "ROW_CHOICE", "ROWS_CHOICES");
  $tpl->set_block ($template, "PAYPAL_PAYMENT");
  $tpl->set_block ($template, "OTHER_PAYMENT");
  $tpl->set_block ("PAYPAL_PAYMENT", "PAYPAL_ITEM", "PAYPAL_ITEMS");
  $tpl->set_var ("ROWS_CHOICES", "");
  $tpl->set_var ("PAYPAL_ITEMS", "");

  // InstantiatePersonVars ($person, $tpl, $db);

  $config = GetConfig ($db);
  InstanciateConfigVars ($config, $tpl);
  $tpl->set_var ("PAYPAL_BUSINESS", $config["paypal_account"]);
  $tpl->set_var ("PAYPAL_CURRENCY", $config["currency"]);
  $tpl->set_var ("REGISTRATION_ID", $person->id);

  $total = 0.;

  $q_invoice = "SELECT c.id_question, question, choice, cost "
  . " FROM RegQuestion q, RegChoice c, PersonChoice p "
  . " WHERE q.id=p.id_question AND c.id_question=p.id_question "
  . " AND c.id_choice=p.id_choice AND p.id_person='$person->id'";
  $r_invoice = $db->execRequete ($q_invoice);
  while ($l_invoice = $db->objetSuivant($r_invoice)) {
    $tpl->set_var("REG_QUESTION", $l_invoice->question);
    $tpl->set_var("REG_CHOICE", $l_invoice->choice);
    $tpl->set_var("REG_COST", $l_invoice->cost);

    $tpl->set_var("ITEM_NAME", $l_invoice->question . " - "
    . $l_invoice->choice);
    $tpl->set_var("ITEM_ID", $l_invoice->id_question);
    $tpl->set_var("ITEM_AMOUNT", $l_invoice->cost);

    $total += $l_invoice->cost;
    $tpl->parse("ROWS_CHOICES", "ROW_CHOICE", true);
    $tpl->parse("PAYPAL_ITEMS", "PAYPAL_ITEM", true);
  }

  $tpl->set_var ("TOTAL_COST", $total);

  if ($person->payment_mode != PAYPAL)
  $tpl->set_var("PAYPAL_PAYMENT", "");
  else
  $tpl->set_var("OTHER_PAYMENT", "");

  $tpl->parse ("RESULT", $template);
  return $tpl->get_var("RESULT");
}


//adyilie-added to support better downloads
function readfile_chunked ($filename) {
  $chunksize = 1*(1024*1024); // how many bytes per chunk
  $buffer = '';
  $handle = fopen($filename, 'rb');
  if ($handle === false) {
    return false;
  }
  while (!feof($handle)) {
    $buffer = fread($handle, $chunksize);
    print $buffer;
  }
  return fclose($handle);
}