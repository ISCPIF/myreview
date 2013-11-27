<?php

/**
 * Model of the PQChoice table (possible answers for a Paper question) 
 *
 */

class PQChoice extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'PQChoice';
  protected $_primary = 'id_choice';
  protected $_sequence = true;

   // Define the references
  protected $_referenceMap = array (
    "PaperQuestion" => array (
        "columns" => 'id_question', // The foreign key name
        "refTableClass" => "PaperQuestion", // The foreign table name
        "refColumns" => array("id") // The primary key referred to 
     )
   );
}
?>