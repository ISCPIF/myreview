<?php

/**
 * Model of the RegAnswer table
 *
 */

class RegAnswer extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'RegAnswer';
  protected $_primary = array('id_user', 'id_question');
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "User" => array (
        "columns" => 'id_user', // The foreign key name
        "refTableClass" => "User", // The foreign table name
        "refColumns" => array("id") // The primary key referred to 
     ),
    "RegQuestion" => array (
        "columns" => 'id_question', // The foreign key name
        "refTableClass" => "RegQuestion", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
   "RegChoice" => array (
        "columns" => 'id_answer', // The foreign key name
        "refTableClass" => "RegChoice", // The foreign table name
        "refColumns" => "id_choice" // The primary key referred to 
     ),
   );
}
?>