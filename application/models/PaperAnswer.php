<?php

/**
 * Model of the PaperAnswer table
 *
 */

class PaperAnswer extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'PaperAnswer';
  protected $_primary = array('id_paper', 'id_question');
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "Paper" => array (
        "columns" => 'id_paper', // The foreign key name
        "refTableClass" => "Paper", // The foreign table name
        "refColumns" => array("id") // The primary key referred to 
     ),
    "PaperQuestion" => array (
        "columns" => 'id_question', // The foreign key name
        "refTableClass" => "PaperQuestion", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
   "PQChoice" => array (
        "columns" => 'id_answer', // The foreign key name
        "refTableClass" => "PQChoice", // The foreign table name
        "refColumns" => "id_choice" // The primary key referred to 
     ),
   );
}
?>