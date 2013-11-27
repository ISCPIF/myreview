<?php

/**
 * Model of the Upload table. Stores the files uploaded with a submission
 *
 */

class Upload extends Zmax_Db_Table_Abstract
{
  protected $_name = 'Upload';
  protected $_primary = array('id_paper', 'id_file');
  protected $_sequence = true;

   // Define the references
  protected $_referenceMap = array (
    "Paper" => array (
        "columns" => 'id_paper', // The foreign key name
        "refTableClass" => "Paper", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
   "RequiredFile" => array (
        "columns" => 'id_file', // The foreign key name
        "refTableClass" => "RequiredFile", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
     );
 
}