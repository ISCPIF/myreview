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

require_once('ConfigRow.php');

/**
 * Model of the config table
 *
 */

class Config extends Zmax_Db_Table_Abstract
{

  protected $_rowClass = 'ConfigRow';

  protected $_name = 'Config';
  protected $_primary = 'confAcronym';
  protected $_sequence = false;

  // Constants for the assignment  mode code
  const  TOPIC_BASED = 1,  PREFERENCES_BASED = 2;

  // Constants for the discussion mode code
  const NO_DISCUSSION = 1, LOCAL_DISCUSSION = 2,  GLOBAL_DISCUSSION = 3;

  // Minimal difference to consider that a paper is in conflict
  const CONFLICT_GAP = 3;

  // How many papers must be rated in one pass?
  const SIZE_RATING = 20;

  // Constants for data export
  const EXPORT_EXCEL=1, EXPORT_XML=2, EXPORT_HTML=3;
  
  const UNKNOWN_RATING = -1;

  // level of expertise
  static public $Expertise = array ("1"=> "Low",
		      "2" => "Medium", 
		      "3" => "High"); 

  // Scale for rating papers
  static public $Scale = array ("7"=> "Strong Accept",
		"6" => "Accept", 
		"5" => "Weak Accept",  
		"4" => "Neutral ", 
		"3" => "Weak Reject",  
		"2" => "Reject", 
		"1" => "Strong Reject"); 

  // User roles
  static public $Roles = array ("A" => "Author", "C" => "Chair", "R" => "Reviewer");

  // Phases of the conference
  const SUBMISSION_PHASE = 1, REVIEWING_PHASE = 2, SELECTION_PHASE = 3,
  PROCEEDINGS_PHASE = 4;

  // Count the papers
  static function countAllPapers ()
  {
    $db = Zend_Db_Table::getDefaultAdapter();

    $result = $db->query ("SELECT COUNT(*) AS nbPapers FROM Paper ");
    $nb = $result->fetch (Zend_Db::FETCH_OBJ)  ;
    if ($nb) {
      return $nb->nbPapers;
    }
    else {
      return 0;
    }
  }

  // Count the reviewers
  static function countAllReviewers ()
  {
    $db = Zend_Db_Table::getDefaultAdapter();
    $result = $db->query ("SELECT COUNT(*) AS nbReviewers FROM User WHERE roles LIKE '%R%'");
    $nb = $result->fetch (Zend_Db::FETCH_OBJ)  ;
    if ($nb) {
      return $nb->nbReviewers;
    }
    else {
      return 0;
    }
  }

  /**
   * Determine the CSS class for alternating colors in tables
   */
  static function CssCLass ($i)
  {
    if ($i++ %2 == 0) {
      return "even";
    }
    else {
      return "odd";
    }
  }

  /**
   * Convert the damn smart quotes from Microsoft
   */
  function removeMSQuotes($string)
  {
    $search = array(chr(145), chr(146), chr(147), chr(148), chr(151));
    $replace = array("'",  "'",   '"',   '"',  '-');
    return str_replace($search, $replace, $string);
  }

}
?>