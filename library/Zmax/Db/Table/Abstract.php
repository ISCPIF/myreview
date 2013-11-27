<?php
/**
 * @category Zmax
 * @package    Zmax_Db
 * @subpackage Table
 * @copyright
 * @license
 * @version
 */

/**
 *  This class extends the Zend_Db_Table abstract class.
 *
 * @package    Zmax_Db
 * @subpackage Table
 *
 */

abstract class Zmax_Db_Table_Abstract extends Zend_Db_Table_Abstract
{

  // Zmax relies on subclassing for both the Row and Rowset classes

  protected $_rowClass = 'Zmax_Db_Table_Row_Abstract';
  private $enforce_db_conversion = false;
   
  /**
   * init
   *   initializes some contextual information for DB access
   *
   * @throws
   * @return array
   */
  public function init()
  {
    // Get the configuration
    $registry = Zend_Registry::getInstance();
    $zmax_context = $registry->get ("zmax_context");
    $config = $zmax_context->config;

    // Look at the "enforce_ut8" parameter
    $this->enforce_db_conversion = $config->db->enforce_conversion;
  }

  /**
   *	Used to transform a value coming from the table
   * @param string the value to convert
   * @return string the converted value
   */
  public function transform($data)
  {
    if (!is_string($data) or !($this->enforce_db_conversion)) {
      return $data;
    }
    else {// Always convert in UTF 8. Room left for other conversions if needed
      return utf8_encode($data);
    }
  }

  /**
   *	Used to decode a value to be stored in the table
   * @param string the value to convert
   * @return string the converted value
   */
  public function decode($data)
  {
    if (!is_string($data) or !($this->enforce_db_conversion)) {
      return $data;
    }
    else {// Always convert from UTF 8. Room left for other conversions if needed
      return utf8_decode($data);
    }
  }

  /**
   * getPrimary
   *
   * @throws
   * @return array (key_attributes)
   */

  public function getPrimary ()
  {
    $info =  $this->info();
    return $info['primary'];
  }

  /**
   * getPrimaryField: returns a single key attribute
   *
   * @throws
   * @return key_attributes
   */

  public function getPrimaryField ()
  {
    $info =  $this->info();
    if (count($info['primary']) > 1) {
      throw new Exception ("Zmax_Table_Abstract: this is a multi-attributes key");
    }
    else
    // Returns only the current field
    return current($info['primary']);
  }

  /**
   * Inserts a new row. Transform the input data if needed,
   * then call the Zend insert method.
   *
   * @param  array  $data  Column-value pairs.
   * @return mixed         The primary key of the row inserted.
   */
  public function insert(array $data)
  {
    // Call the Zmax transform method
    foreach ($data as $key => $val)
    $data[$key] = $this->decode($val);
    // Just call the Zend class method
    return parent::insert($data);
  }

  /**
   * Updates existing rows. Transform the input data if needed,
   * then call the Zend update method.
   *
   * @param  array        $data  Column-value pairs.
   * @param  array|string $where An SQL WHERE clause, or an array of SQL WHERE clauses.
   * @return int          The number of rows updated.
   */
  public function update(array $data, $where)
  {
    // Call the Zmax transform method
    foreach ($data as $key => $val)
    $data[$key] = $this->decode($val);
    // Just call the Zend class method
    return parent::update($data, $where);
  }

  /**
   * checks if the primary key is auto incremented
   *
   * @return boolean
   */

  public function isAutoIncremented ()
  {
    if (is_bool($this->_sequence)) return $this->_sequence;

    return false ; // Sequence is the sequence name
  }

  // End of the class
}

