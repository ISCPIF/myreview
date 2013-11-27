<?php

/**
 * Model of the RequiredFile table
 *
 */

class RequiredFile extends Zmax_Db_Table_Abstract
{    
  protected $_name = 'RequiredFile';
  protected $_primary = 'id';
  protected $_sequence = true;
  
   // Define the references
  protected $_referenceMap = array (
    "FileType" => array (
        "columns" => 'file_extension', // The foreign key name
        "refTableClass" => "FileType", // The foreign table name
        "refColumns" => "extension" // The primary key referred to 
     ),
    "Phase" => array (
        "columns" => 'id_phase', // The foreign key name
        "refTableClass" => "Phase", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     )
     );
     
}
?>