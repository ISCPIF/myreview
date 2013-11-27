<?php


require_once('SessionRow.php');
require_once("myreview_v1/Formulaire.class.php");
require_once ("User.php");
require_once ("Config.php");

/**
 * Model of the Session table
 */

class Session extends Zmax_Db_Table_Abstract
{
  protected $_rowClass = 'SessionRow';

  protected $_name = 'Session';
  protected $_primary = 'idSession';
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "User" => array (
        "columns" => 'id_user', // The foreign key name
        "refTableClass" => "User", // The foreign table name
        "refColumns" => array("id") // The primary key referred to 
  )
  );
   
  /**
   * Attempt to create a user, given a user email and a password
   */

  function create ($email, $password, $idSession)
  {
    $user = new User();
    $userRow = $user->findByEmail($email);

    $config = new Config();
    $configRow = $config->fetchAll()->current();

    // Does this user exist?
    if ($userRow) {
      // Check the password
      if ($userRow->password == md5($password)) {
        // Delete a possibly existing session. Safer.
        $db = Zend_Db_Table::getDefaultAdapter();
        $db->query ("DELETE FROM Session WHERE idSession = '$idSession'");

        // Insert in Session, for 2 hours
        $sessionRow = $this->createRow();
        $sessionRow->idSession = $idSession;
        $sessionRow->id_user = $userRow->id;
        $this->tempsLimite = date ("U") + 7200;
        $sessionRow->roles = $userRow->roles;
        $sessionRow->save();

        return true;
      }
      return false;
    }
    else {
      return false;
    }
  }
}
?>
