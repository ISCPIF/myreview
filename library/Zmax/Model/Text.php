<?php
/**
 * @category   Zmax
 * @package    Zmax_Model
 * @subpackage Text
 * @copyright
 * @license
 * @version
 */

/**
 * Model of the generic Zmax Text
 * @package    Zmax_Model
 * @subpackage Text
 *
 */

class Zmax_Model_Text extends Zmax_Db_Table_Abstract
{
  protected $_name = 'zmax_text';
  protected $_primary = array('lang', 'namespace', 'text_code');
  protected $_sequence = false;

  // Define the reference to the namespace and to the lang
  protected $_referenceMap = array (
    "Zmax_Model_Lang" => array (
        "columns" => 'lang', // The foreign key name
        "refTableClass" => "Zmax_Model_Lang", // The foreign table name
        "refColumns" => "lang" // The primary key referred to 
  ),
   "Zmax_Model_Namespace" => array (
        "columns" => 'namespace', // The foreign key name
        "refTableClass" => "Zmax_Model_Namespace", // The foreign table name
        "refColumns" => "namespace" // The primary key referred to 
  )
  );

}

?>