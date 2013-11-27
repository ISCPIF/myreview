<?php
/**
 * @category   Zmax
 * @package    Zmax_Model
 * @subpackage Lang
 * @copyright
 * @license
 * @version
 */


/**
 * Model of the generic Zmax lang
 * @package    Zmax_Model
 * @subpackage Lang
 */

class Zmax_Model_Lang extends Zmax_Db_Table_Abstract
{
  protected $_name = 'zmax_lang';
  protected $_primary = 'lang';
  protected $_sequence = false;

  public function getList()
  {
    $select = $this->select();
    $select->order('name ASC');
    return  $this->fetchAll();
  }
}

?>