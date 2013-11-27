<?php

/**
 * Model of the Abstract table. Note: Asbtract is a reserved keyword ...
 *
 */

class AbstractClass extends Zmax_Db_Table_Abstract
{
  protected $_name = 'Abstract';
  protected $_primary = array('id_paper', 'id_section');
  protected $_sequence = true;

   // Define the references
  protected $_referenceMap = array (
    "Paper" => array (
        "columns" => 'id_paper', // The foreign key name
        "refTableClass" => "Paper", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
   "AbstractSection" => array (
        "columns" => 'id_section', // The foreign key name
        "refTableClass" => "AbstractSection", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
     );
 
}