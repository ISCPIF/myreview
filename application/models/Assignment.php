<?php

/**
 * Model of the Assignment table
 *
 */

class Assignment extends Zmax_Db_Table_Abstract
{
    
  protected $_name = 'Assignment';
  protected $_primary = array('idPaper', 'id_user');
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "Paper" => array (
        "columns" => 'idPaper', // The foreign key name
        "refTableClass" => "Paper", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
    "User" => array (
        "columns" => 'id_user', // The foreign key name
        "refTableClass" => "User", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),      
   );
}
?>