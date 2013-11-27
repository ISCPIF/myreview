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


// Load the libraries

function ComputeAssignment (&$tpl, $db, $maxReviewers)
{
  // Decompose the blocks of the template
  $tpl->set_block("content", "MEMBER_DETAIL", "MEMBERS");
  $tpl->set_block("content", "PAPER_DETAIL", "PAPERS");
  $tpl->set_block("content", "GROUP_DETAIL", "GROUP");
  $tpl->set_block("PAPER_DETAIL", "ASSIGNMENT_DETAIL", "ASSIGNMENTS");

  // This function might take a while to execute...
  ini_set ("max_execution_time", "900"); // 15 mns, should be enough...

  $tpl->set_var("MEMBERS", "");
  $tpl->set_var("PAPERS", "");
  $tpl->set_var("RESULTS", "");
  $tpl->set_var("ASSIGNMENTS", "");

  // Get global parameters
  $nbReviewers = Config::countAllReviewers ();
  $nbPapers = Config::countAllPapers ();
  $refPerPap = $maxReviewers;

  // How many papers for each referee ?
  $papRef= $nbPapers * $refPerPap;
  $papForRef = floor($papRef / $nbReviewers) + 1;

  // Templates assignment
  $tpl->set_var("NUMBER_PAPERS", $nbPapers);
  $tpl->set_var("NUMBER_REVIEWERS", $nbReviewers);
  $tpl->set_var("REVIEWERS_PAPERS", $refPerPap);
  $tpl->set_var("MAX_PAPERS_REVIEWER", $papForRef);

  if (isSet($_REQUEST['idMin'])) {
    // A group must be computed
    $idMin = $_REQUEST['idMin'];
    $idMax = $_REQUEST['idMax'];
    $nbGroups = 1;
  }
  else if ($nbPapers > MAX_PAPERS_IN_ROUND) {
    $tpl->set_file("TxtAssignmentGroups", TPLDIR . "TxtAssignmentGroups.tpl");
    $tpl->set_block("TxtAssignmentGroups", "GROUP_DETAIL", "GROUPS");
    $tpl->set_var("GROUPS", "");
    $tpl->set_var("MAX_PAPERS_IN_ROUND", MAX_PAPERS_IN_ROUND);

    // How many groups do we create?
    $nbGroups = ($nbPapers / MAX_PAPERS_IN_ROUND) + 1;
    $nb_papers_per_group = $nbPapers / $nbGroups;
    // Compute the groups
    $rPapers = $db->execRequete("SELECT * FROM Paper ORDER BY id");
    $iPaper = 0; $idMin=$idMax=-1; $iGroup = 1;
    $groups= array();
    while ($paper = $db->objetSuivant($rPapers))
    {
      if ($idMin == -1) $idMin = $paper->id;
      if ($paper->id > $idMax) $idMax = $paper->id;
      $iPaper++;
      if ($iPaper > $nb_papers_per_group)
      {
        // Create a group
        $groups[$iGroup++] = array("idMin" => $idMin,
					 "idMax" => $idMax);
        $idMin =  $idMax = -1;
        $iPaper = 0;
      }
    }
    // Create the last group
    $groups[$iGroup] = array("idMin" => $idMin,
			       "idMax" => $idMax);

    foreach ($groups as $key => $val)
    {
      $idMin = $val['idMin']; $idMax=$val['idMax'];
      $tpl->set_var("GROUP_ID", "$key");
      $tpl->set_var("ID_MIN", $idMin);
      $tpl->set_var("ID_MAX", $idMax);
      $tpl->parse("GROUPS", "GROUP_DETAIL", true);
    }
    $tpl->parse ("content", "assigngroup");
    return;
  }
  else
  {
    $nbGroups = 1;
    $nb_papers_per_group = $nbPapers;
  }

  /* Not useful if one priviledges the number of reviewers for each paper
   $papRef = $maxReviewers * $nbPapers;
   $papForRef = floor((float) $papRef / (float) $
   if ($papRef % $nbReviewers != 0) floor($papForRef++);
   */

  // Create two arrays, one for members,  one for papers
  $members = $papers = array();

  $qMembers = "SELECT * FROM User WHERE roles LIKE '%R%' ORDER BY last_name";
  $rMembers = $db->execRequete($qMembers);

  $iMember = 1;
  while ($member = $db->objetSuivant($rMembers)) {
    $members[$iMember++] = $member->id;
  }
  $nbReviewers = $iMember - 1;


  if (isSet($idMin)) {
    $qPapers = "SELECT * FROM Paper WHERE id BETWEEN $idMin AND $idMax ORDER BY id";
  }
  else {
    $qPapers = "SELECT * FROM Paper ORDER BY id";
  }
  $rPapers = $db->execRequete ($qPapers);
  $iPaper = 1;
  while ($paper = $db->objetSuivant($rPapers)) $papers[$iPaper++] = $paper->id;
  $nbPapers = $iPaper - 1;

  // OK, let's do the assignment
  if ($nbPapers > $nbReviewers) {
    $n = $nbPapers;
  }
  else {
    $n = $nbReviewers;
  }

  /*  echo "Nbpapers = $nbPapers Nb rev.=$nbReviewers N=$n<br>";
   return;
   */

  // Create the matrix
  $weight = array();

  reset($papers);
  while (list($iPaper, $idPaper) = each ($papers)) {
    reset($members);
    while (list($iRef, $id_user) = each ($members)) {
      $weight[$iRef][$iPaper] = GetRatingValue($idPaper, $id_user, $db);
    }

    // Complete to obtain a square matrix
    if ($nbReviewers < $n) {
      for ($i=$nbReviewers+1; $i <= $n; $i++)   $weight[$i][$iPaper] = 0;
    }
  }

  // Complete to obtain a square matrix if nbPapers < nbReviewers
  if ($nbPapers < $n)  {
    for ($i=$nbPapers+1; $i <= $n; $i++) {
      reset($members);
      while (list($iRef, $email) = each ($members))
      $weight[$iRef][$i] = 0;
    }
  }
  // End of init ****

  // Initialize the arrays
  $assignedPapers = $chosenPapers = $Rmate = $Lmate = $choice = array();

  for ($i = 1; $i <= $nbPapers; $i++) $chosenPapers[$i] = 0;

  for ($i = 1; $i <= $nbReviewers; $i++) {
    $assignedPapers[$i] = 0;
    for ($j = 1; $j <= $nbPapers; $j++)
    $choice[$i][$j] = 0;
  }

  $wsum = 1;		// dummy for first iteration
  for ($i = 1; $wsum > 0; $i++) {
    for ($j = 1; $j <= $n; $j++)
    $Rmate[$j] = $Lmate[$j] = 0;

    // Compute the matching for the current ballot
    matching($n, $Lmate, $Rmate, $weight);

    // Make the assignment - Remove choices already made
    takeout($nbReviewers, $nbPapers, $Lmate,
    $weight, $choice, $chosenPapers, $assignedPapers,
    $wsum, $maxReviewers, $papForRef);
    // if ($i >= $maxReviewers) break;
  }

  // Insert results in the DB
  $db->execRequete ("DELETE FROM Assignment");
  reset($papers);
  while (list($iPaper, $idPaper) = each ($papers)) {
    $weightPaper = 0;
    reset($members);
    $tabMails = array();
    while (list($iRef, $id_user) = each ($members)) {
      $weight = $choice[$iRef][$iPaper];
      if ($weight != 0) {
        $tabMails[] = $id_user;
        $query = "INSERT INTO Assignment(idPaper, id_user, weight)"
        . "VALUES ('$idPaper', '$id_user', '$weight')";
        $db->execRequete ($query);
      }
    }
    // Insert the assignment in the Review table
    // SQLReview ($idPaper, $tabMails, $db);
  }

  // Report the result
  ReportAssignment ($tpl, $members, $papers, $choice, $db);

  if (isSet($idMin)) {
    $tpl->set_var("ID_MIN", $idMin);
    $tpl->set_var("ID_MAX", $idMax);
    $tpl->parse("GROUP", "GROUP_DETAIL");
  }
  else
  $tpl->set_var("GROUP", "");
}

/************************************************************

Matching algorithm

*************************************************************/

function matching ($n, &$Lmate, &$Rmate, &$weight)
{
  $pred = $cost = array();

  for ($r = 1; $r <= $n; $r++) $pred[$r] = 0;

  for ($k = 1; $k <= $n; $k++)
  {
    /*  initialization - alternating paths of length 1 */

    for ($r = 1; $r <= $n; $r++)
    {
      $cost[$r] = 0;   /* Dummy value, will be overwritten
      as G is complete */
      for ($s = 1; $s <= $n; $s++)
      {
        if (($Lmate[$s] == 0) && ($weight[$s][$r] > $cost[$r]))
        {
          $cost[$r] = $weight[$s][$r];
          $pred[$r] = $s;	/*  denotes predecessor of w_r
          on path so far */
        }
      }
    }

    /*  allow alternating paths of length 3, 5, ..., 2k + 1 */

    $improving = TRUE;

    for ($i = 2; ($i <= $k) && $improving; $i++)
    {
      $improving = FALSE;

      for ($r = 1; $r <= $n; $r++)
      for ($s = 1; $s <= $n; $s++)
      if (($r != $s) && ($Rmate[$s]) > 0)
      {
        $extra = $weight[$Rmate[$s]][$r] -
        $weight[$Rmate[$s]][$s];

        if ($cost[$s] + $extra > $cost[$r])
        {
          $cost[$r] = $cost[$s] + $extra;
          $pred[$r] = $Rmate[$s];
          $improving = TRUE;
        }
      }
    }

    /*  choose exposed w with maximum cost */

    $C = 0;
    for ($r = 1; $r <= $n; $r++)
    if ($Rmate[$r] == 0 && $cost[$r] > $C)
    {
      $C = $cost[$r];
      $w = $r;
    }
    /* augmenting path ending at w is optimal */

    if ($C > 0)
    {
      augment($w, $pred, $Lmate, $Rmate);
    }
    else
    {
      $improving = FALSE;
    }
  }
}

function augment ($ww, $pred, &$Lmate, &$Rmate)
{
  $w = $ww;
  $u = $pred[$w];

  while ($Lmate[$u] != 0)		/* while u is not start vertex */
  {
    $v = $Lmate[$u];
    $Lmate[$u] = $w;		/* {u,w} added to matching,
    {u,v} removed from matching */
    $Rmate[$v] = 0;		/* {u,v} removed on the other side */
    $Rmate[$w] = $u;		/* doubling it on the other side */
    $w = $v;
    $u = $pred[$w];
  }
  $Lmate[$u] = $w;
  $Rmate[$w] = $u;

}

function takeout($nbReviewers, $nbPapers, &$Lmate,
&$weight, &$choice, &$chosenPapers, &$assignedPapers,
&$wsum, $maxReviewers, $papForRef)
{
  $wsum = 0;

  // Assign the result of the current ballot in choice
  for ($i = 1; $i <= $nbReviewers; $i++)
  {
    $j = $Lmate[$i];
    if ($j > 0)
    {
      $choice[$i][$j] = $weight[$i][$j];
      $wsum += $weight[$i][$j];
      $chosenPapers[$j]++;
      $assignedPapers[$i]++;
      /* if the number of referees for paper j reached the required
       limit, then disable any other assignment for this paper  */

      if ($chosenPapers[$j] == $maxReviewers)
      for ($k = 1; $k <= $nbReviewers; $k++)
      $weight[$k][$j] = 0;

      /* If the number of papers assigned to a reviewer
       reaches the limit, then disable any other assignment
       to this reviewer                                       */
      if ($assignedPapers[$i] == $papForRef)
      for ($k = 1; $k <= $nbPapers; $k++)
      $weight[$i][$k] = 0;

      $weight[$i][$j] = 0;
    }
  }
}

// Display a table with results
function ReportAssignment (&$tpl, &$members, &$papers, &$choice, $db)
{
  // get the config from the registry
  $registry = Zend_registry::getInstance();
  $config = $registry->get("Config");
  $config->putInView($tpl);

  $tpl->set_var("MAXIMAL_WEIGHT", $config['nbReviewersPerItem']*4);
  $tpl->set_var("SESSION_ID", session_id());

  // This function might potentially reach the memory
  // limit of PHP. Check that this does not happen
  if (function_exists ("memory_get_usage")) {
    // The following instruction can raise the memory limit
    ini_set ("memory_limit", "100M");
    ini_set ("max_execution_time", "300"); // 5 mns
  }

  // Header of the array
  reset($members);
  $userTbl = new User();
  foreach ($members as $id) {
    $user = $userTbl->find($id)->current();
    $user->putInView($tpl);
    $tpl->append("MEMBERS", "MEMBER_DETAIL");
  }

  // Display the array
  reset($papers);
  while (list($iPaper, $idPaper) = each ($papers))
  {
    $weightPaper = 0;
    reset($members);
    while (list($iRef, $email) = each ($members))
    {
      $paper = GetPaper ($idPaper, $db, "object");
      InstanciatePaperVars ($paper, $tpl, $db);

      if ($choice[$iRef][$iPaper] != 0)
      {
        $tpl->set_var("BG_COLOR", "lightblue");
        $tpl->set_var("PAPER_RATING", $choice[$iRef][$iPaper]);
        $weightPaper += $choice[$iRef][$iPaper];
      }
      else
      {
        $tpl->set_var("BG_COLOR", "white");
        $tpl->set_var("PAPER_RATING",
        GetRatingValue($papers[$iPaper],
        $members[$iRef], $db));
      }

      $tpl->set_var("WEIGHT", "$weightPaper");
      $tpl->append("ASSIGNMENTS", "ASSIGNMENT_DETAIL");
    }
    // Add to the list of papers
    $tpl->append("PAPERS", "PAPER_DETAIL");
    $tpl->set_var("ASSIGNMENTS", "");
  }
}


// Get a rating value with its key
function GetRatingValue ($idPaper, $id_user, $bd)
{
  $query = "SELECT * FROM Rating  WHERE idPaper = '$idPaper' and id_user='$id_user' ";

  $result = $bd->execRequete ($query);
  $rating = $bd->objetSuivant ($result);
  if (is_object($rating))
  return $rating->rate;
  else
  return RATE_DEFAULT_VALUE;
}

?>