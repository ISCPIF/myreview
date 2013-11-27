<?php

require_once("Review.php");
require_once("User.php");
require_once("Rating.php");
require_once("Paper.php");

// Set the maximal size of the manual assignment table
define ("MAX_ITEMS_IN_ASSIGNMENT", 20);


// List the papers in the forum
function AdmForum ($email, &$tpl, $db, &$TEXTS, $i_min, $i_max)
{
  $config = GetConfig($db);
  $tpl->set_var("SUBMISSION_URL", $config['submissionURL']);
  $tpl->set_var("SESSION_ID", session_id());
  $class= 'even';

  /* Select all the papers which are NOT in conflict with the
   reviewer and list them.
   First extract the 'bloc' describing a line from the template */

  $query = "SELECT p.* FROM Paper p, Rating ra "
  . " WHERE ra.email='$email' AND ra.idPaper=p.id AND ra.rate > 0 ";

  $result = $db->execRequete ($query);
  $nbPapers = 0;
  $i = 0;
  while ($paper = $db->objetSuivant($result))
  {
    $nbPapers++;
    $i++;
    if ($i >= $i_min and $i <= $i_max)
    {
      if ($class == 'even') $class = 'odd'; else $class='even';
      $tpl->set_var("CSS_CLASS", $class);
      // Instanciate vars of the paper
      InstanciatePaperVars ($paper, $tpl, $db);
      // Show all other reviews, do not propose to see only my review
      $tpl->set_var("REVIEW","");
      // Show the messages
      $tpl->set_var("MESSAGES",
      DisplayMessages($paper->id, 0, $db, TRUE, "Forum.php"));
      $tpl->parse("FORUM", "Forum");

      $tpl->parse("PAPERS", "PAPER_DETAIL", true);
    }
  }
   
  // Create the groups
  $nb_groups = $nbPapers / SIZE_FORUM + 1;
  for ($i=1; $i <= $nb_groups; $i++)
  {
    $iMin = (($i-1) *  SIZE_FORUM) + 1;
    if ($iMin >= $i_min and $iMin <= $i_max)
    $link = "<font color=red>$i</font>";
    else
    $link =$i;
    $tpl->set_var("LINK", $link);
     
    $tpl->set_var("IMIN_VALUE", $iMin);
    $tpl->set_var("IMAX_VALUE", $iMin + SIZE_FORUM -1);
    $tpl->parse("LINKS", "GROUPS_LINKS", true);
  }

  if ($nbPapers == 0)
  $tpl->set_var("PAPERS", "No papers");
   
  $tpl->parse("BODY", "TxtPapersInForum");
}

// Summary of paper assignment
function SummaryPapersAssignment (&$tpl, $db, &$TEXTS)
{
  $registry = Zend_registry::getInstance();
  $config = $registry->get("Config");
  $config->putInView($tpl);

  $db = Zend_Db_Table::getDefaultAdapter();
  $paperTbl = new Paper();
  $reviewTbl = new Review();
  $ratingTbl = new Rating();
  $userTbl = new User();

  /* Check whether there is a prefered topic for papers */
  if ($config->selectedPaperTopic) {
    $prefPaperTopic =$config->selectedPaperTopic ;
  }
  else {
    $prefPaperTopic= "%";
  }

  /* Check whether there is a prefered topic for reviewers */
  if ($config->selectedReviewerTopic) {
    $prefReviewerTopic = $config->selectedReviewerTopic ;
  }
  else {
    $prefReviewerTopic= "%";
  }

  // Decompose the blocks of the template
  $tpl->set_block("content", "MEMBER_DETAIL", "MEMBERS");
  $tpl->set_block("content", "PAPER_DETAIL", "PAPERS");
  $tpl->set_block("content", "NAVIGATION_TABLE", "NAVIGATION");
  $tpl->set_block("PAPER_DETAIL", "ASSIGNMENT_DETAIL", "ASSIGNMENTS");

  // Get the list of topics
  $topicList = $db->fetchPairs ("SELECT id, label FROM ResearchTopic");
  $topicList["0"] = "Any";
  ksort($topicList);

  // Show the selection list
  $tpl->paper_topics =  Zmax_View_Phplib::selectField ("paperTopic", $topicList, $prefPaperTopic);
  $tpl->reviewer_topics =  Zmax_View_Phplib::selectField ("reviewerTopic", $topicList, $prefReviewerTopic);

  /* Store the list of reviewers in an array (+easier, +efficient).  */
  $members = array();
  $nb_members = 0;
  $users = $userTbl->fetchAll("roles LIKE '%R%'", "last_name");
  foreach ($users as $user) {
    if ($prefReviewerTopic == '%' or $user->matchTopic($prefReviewerTopic)) {
      $members[++$nb_members] = $user;
    }
  }

  // Same thing for papers
  $papers = array();
  $nb_papers = 0;
  $rPapers = $db->query("SELECT * FROM Paper ORDER BY id");
  while ($paper =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
    if ($prefPaperTopic == '%' or $paper->topic == $prefPaperTopic) {
      $papers[++$nb_papers] = $paper;
    }
  }

  // Manage the navigation table
  if ($nb_papers > MAX_ITEMS_IN_ASSIGNMENT or $nb_members > MAX_ITEMS_IN_ASSIGNMENT) {
    // Show the navigation table
    $tpl->set_var("nb_paper", $nb_papers);
    $tpl->set_var("nb_reviewers", $nb_members);
    $tpl->set_var("max_items_in_assignment", MAX_ITEMS_IN_ASSIGNMENT);

    if (isSet($_REQUEST['i_paper_min'])) {
      // The request comes from the navigation table
      $i_paper_min = $_REQUEST['i_paper_min'];
      $i_paper_max = min($_REQUEST['i_paper_max'], $nb_papers);
      $i_member_min = $_REQUEST['i_member_min'];
      $i_member_max = min($_REQUEST['i_member_max'], $nb_members);
    }
    else {
      $i_paper_min = 1; $i_paper_max = min($nb_papers, MAX_ITEMS_IN_ASSIGNMENT);
      $i_member_min = 1; $i_member_max = min($nb_members,  MAX_ITEMS_IN_ASSIGNMENT);
    }

    // Show the navigation table
    $tpl->set_var("NAV_TABLE", "");
    $lines = "";
    $script= $tpl->base_url . "/admin/chair/showassignment?1=1";
    for ($i = 1; $i <= $nb_papers;  $i+=MAX_ITEMS_IN_ASSIGNMENT) {
      $line="";
      for ($j=1; $j <= $nb_members; $j+=MAX_ITEMS_IN_ASSIGNMENT) {
        $link = $script . "&i_paper_min=$i"
        . "&i_paper_max=" . ($i + MAX_ITEMS_IN_ASSIGNMENT -1)
        . "&i_member_min=$j"
        . "&i_member_max=".($j + MAX_ITEMS_IN_ASSIGNMENT - 1);

        if ($i==$i_paper_min and $j==$i_member_min) {
          $line .= "<td bgcolor=lightblue><a href='$link'>"
          . "<font color=white>$i/$j</font></a></td>";
        }
        else {
          $line .= "<td><a href='$link'>$i/$j</a></td>";
        }
      }
      $lines .=  "<tr>$line</tr>\n";
    }
    $tpl->set_var("NAV_TABLE", $lines);
    $tpl->append("NAVIGATION", "NAVIGATION_TABLE");
  }
  else {
    // Hide the navigation table
    $i_paper_min = $i_member_min = 1;
    $i_paper_max = $nb_papers; $i_member_max = $nb_members;
    $tpl->set_var("NAVIGATION", "");
  }

  // Put the current values in the template
  $tpl->set_var("I_PAPER_MIN", $i_paper_min);
  $tpl->set_var("I_PAPER_MAX", $i_paper_max);
  $tpl->set_var("I_MEMBER_MIN", $i_member_min);
  $tpl->set_var("I_MEMBER_MAX", $i_member_max);

  //  echo "I paper min=$i_paper_min I paper max = $i_paper_max<br>";

  // OK, now create the table. First the columns' headers
  for ($j=$i_member_min; $j <= $i_member_max; $j++) {
    $members[$j]->putInView($tpl);
    $tpl->member_nb_papers = $members[$j]->countPapers();
    $tpl->append("MEMBERS", "MEMBER_DETAIL");
  }

  // then each line
  $tpl->PAPERS = "";
  for ($i = $i_paper_min; $i <= $i_paper_max; $i++) {
    // Choose the CSS class
    if ($i%2 == 0) {
      $tpl->set_var("css_class", "even");
    }
    else {
      $tpl->set_var("css_class", "odd");
    }

    $paper = $papers[$i];
   $entity = "Paper" . "->id" ;
    $tpl->setVar ($entity, $papers[$i]->id); 

     
    $tpl->SESSION_ID = session_id();

    // Get the ratings of each PC member
     $nbReviewers = 0;
    for ($j=$i_member_min; $j <= $i_member_max; $j++) {
      $member = $members[$j];
      $member->putInView($tpl);

      $rating = $ratingTbl->find($paper->id, $member->id)->current();
      if ($rating) {
        $val = $rating->rate;
      }
      else {
        $val = 2;
      }
       
      $tpl->bg_color = "white";
      $tpl->set_var("paper_rating", $val);
      $tpl->set_var("CHECKED_YES", "");
      $tpl->set_var("CHECKED_NO", "checked='1'");

      // Check if the paper is assigned
      $review = $reviewTbl->find($paper->id, $member->id)->current();
      if ($review) {
        $nbReviewers = $nbReviewers + 1;
        $tpl->bg_color =  "yellow";
        $tpl->set_var("CHECKED_YES", "checked='1'");
        $tpl->set_var("CHECKED_NO", "");
      }

      // Add to the assignment line
      $tpl->append("ASSIGNMENTS", "ASSIGNMENT_DETAIL");
    }
    // Add to the list of papers
    $tpl->setVar("paper_nb_reviewers", $nbReviewers);
    $tpl->append("PAPERS", "PAPER_DETAIL");
    $tpl->set_var("ASSIGNMENTS", "");
  }
}
