<?php


/**
 * Model of the UserTopic table
 *
 */

class UserTopic extends Zmax_Db_Table_Abstract
{
  protected $_name = 'UserTopic';
  protected $_primary = array('id_user', 'id_topic');
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "User" => array (
        "columns" => 'id_user', // The foreign key name
        "refTableClass" => "User", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
    "ResearchTopic" => array (
        "columns" => 'id_topic', // The foreign key name
        "refTableClass" => "ResearchTopic", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),      
   );
}
?>