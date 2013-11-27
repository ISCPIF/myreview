<?php

require_once('UserRow.php');

/**
 * Model of the User table
 *
 */

class User extends Zmax_Db_Table_Abstract
{

  protected $_rowClass = 'UserRow';

  protected $_name = 'User';
  protected $_primary = 'id';
  protected $_sequence = true;

  // Enumerate the possible roles
  const AUTHOR_ROLE='A', REVIEWER_ROLE='R', ADMIN_ROLE='C', PARTICIPANT_ROLE='P';
  
   // Define the references
  protected $_referenceMap = array (
    "Country" => array (
        "columns" => 'country_code', // The foreign key name
        "refTableClass" => "Country", // The foreign table name
        "refColumns" => "code" // The primary key referred to 
     ),      
   );
   
    /**
   * Find a user by its email
   */
  function findByEmail ($email)
  {
    // Check that this email does not already exist
    $select = $this->select()->where('email = ?', $email);
    $user = $this->fetchRow ($select);
    return $user;
  }
  
}
?>