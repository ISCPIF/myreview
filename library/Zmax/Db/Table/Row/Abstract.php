<?php

/**
 * @category   Zmax
 * @package    Zmax_Db
 * @subpackage Row
 * @copyright
 * @license
 * @version
 */


/**
 * This class extends the Zend_Db_Table_Row abstract class.
 *
 * Essentially, the overloading serves to apply some
 * custom transformations to data retrieved from the database
 *
 * @package Zmax_Db
 * @subpackage Row
 */

class Zmax_Db_Table_Row_Abstract extends Zend_Db_Table_Row_Abstract
{

  /**
   * A Boolean that states whether input data must be filtered (default = NO)
   */
  private $_filterData;
    
  /**
   * Override the magic get method : this allows to perform
   * text conversion if required
   *
   * @return array
   */

  public function __get($columnName)
  {
    return $this->getTable()->transform($this->_data[$columnName]);
  }

  /**
   * Returns the column/value data as an array.
   *
   * @return the values of the row, as an associative array. Use the transfor funtion
   *   of the Table package is required.
   */
  public function toArray()
  {
    foreach ($this->_data as $key => $value) {
      $result[$key] = $this->getTable()->transform($value);
    }
    return $result;
  }
   
  /**
   * This function puts in the view all the object's values
   *
   * @param The view object
   */
  function putInView($view, $html=true) {
    // Get the table name
    $info = $this->getTable()->info();
    $result = array();

    // Put in the view entities of the form 'table_name->att_name' (avoid clash with namespaces)
    foreach ($this->_data as $key => $value) {
      $entityName = $info["name"] . "->" . $key;

      // If HTML mode: protect the value with HTML entities
      if ($html) {
        // New: no need to protect data, because it is now filtered as input
        $value = nl2br($value);
      }
      $view->setVar($entityName, $value);
    }
  }

  /**
   * Always perform filtering when the content of an object is fed from an input
   */

  function setFromArray($input)
  {
    // Clean up
    foreach ($input as $key => $value) {
      if (is_string($value) and $this->_filterData) {
         $input[$key] =  htmlSpecialChars ($value, ENT_NOQUOTES);
      }
    }
    
    // OK, now call the parent function
    parent::setFromArray($input);
  }

  /**
   * Unset/set the filtering parameter
   */
  function setFilterData ($filter)
  {
    $this->_filterData = $filter;
  }
  
  // End of the class
}

