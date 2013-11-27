<?php

/**
 * Model of the RegChoice table (possible answers for a registration question) 
 *
 */

class RegChoice extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'RegChoice';
  protected $_primary = 'id_choice';
  protected $_sequence = true;

   // Define the references
  protected $_referenceMap = array (
    "RegQuestion" => array (
        "columns" => 'id_question', // The foreign key name
        "refTableClass" => "RegQuestion", // The foreign table name
        "refColumns" => array("id") // The primary key referred to 
     )
   );
}
?>