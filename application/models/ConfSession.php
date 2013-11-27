<?php

require_once("Slot.php");

/**
 * Model of the ConfSession table
 *
 */

class ConfSession extends Zmax_Db_Table_Abstract
{
  protected $_name = 'ConfSession';
  protected $_primary = 'id';
  protected $_sequence = true;

  // Define the references
  protected $_referenceMap = array (
    "Slot" => array (
        "columns" => 'id_slot', // The foreign key name
        "refTableClass" => "Slot", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ) ,
  "ShowSlot" => array (
        "columns" => 'id_slot', // The foreign key name
        "refTableClass" => "ShowSlot", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ) 
     );
}
?>