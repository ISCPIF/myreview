<?php
/**
 * @category   Zmax
 * @package    Zmax_Model
 * @subpackage User
 * @copyright
 * @license
 * @version
 */

 /**
  * Model of the generic Zmax user
  * 
  * @package    Zmax_Model
  * @subpackage User
  * @author Philippe Rigaux
  *
  */

class Zmax_Model_User extends Zmax_Db_Table_Abstract
{
  protected $_name = 'zmax_user';
  protected $_primary = 'user_id';
  protected $_sequence = false;

  public function initFromArray($tab){
  	return $this->createRow($tab);
  }

  /**
   * searches a set of users with a substring of the name
   *
   * @param string name
   * @return a set of user objects
   */
  public function findByName ($name)
  {
    // Create a statement
    $select = $this->select()->where('user_lname like ?',  "%$name%");
     
    // Now execute the query, and show the list with the template
    return  $this->fetchAll ($select);
  }
}

?>