<?php

/**
 * @category Zmax
 * @package    Zmax_Db
 * @subpackage Adapter
 * @copyright
 * @license
 * @version
 */

/**
 * This class extends the Zend_Db adapter abstract class.
 *
 * Since we cannot easily subclass the Zend_Db_Adapter,
 *  the class is actually a wrapping of an object instatiating
 *  a non-abstract Zend_Db_Adapter object. The magic __call method
 * ensures that all the calls to methods which are undefined at
 * this level are forwarded to the zendèdb object.
 *
 * @package Zmax_Db
 * @subpackage Adapter
 * @author Philippe Rigaux
 *
 */


class Zmax_Db_Adapter extends Zend_Db_Adapter_Abstract
{
  // A Zend DB object that serves to provide an implementation
  // for some abstract functions
  private $zend_db, $pdo_db;

  // Object constructor
  function __construct ($config)
  {
    // Add some mandatory options for the framework
    $options = array(
    // Zend_Db::CASE_FOLDING => Zend_Db::CASE_LOWER,
    Zend_Db::AUTO_QUOTE_IDENTIFIERS => false
    );

    // Create the connect. array
    $arr_config = $config->toArray();
    $arr_config['params']['options']  =  $options;

    // Create the Zend Db Adapter object
    $this->zend_db = Zend_Db::factory($config->adapter, $arr_config['params']);
    // Get the PDO reference (and check that the connection is succesful)
    $this->pdo_db = $this->zend_db->getConnection();
    // Get the driver name
    $this->driver = $this->pdo_db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Specific actions for MySQL
    if ($this->driver == 'mysql') {
      $this->pdo_db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
      // Always set the connection with utf8 encoding
      $this->query("SET CHARACTER SET utf8");
    }

    // End of constructor
  }

  /**
   * @return string
   */

  public function getQuoteIdentifierSymbol()
  {
    return $this->zend_db->getQuoteIdentifierSymbol();
  }

  // Now, set the implementation of the abstraction functions. Just
  // call the method on the zend_db object.

  public function listTables()
  {return $this->zend_db->listTables(); }

  /**

  */
  public function describeTable($tableName, $schemaName = null)
  {return $this->zend_db->describeTable($tableName, $schemaName); }

  /**
   * Creates a connection to the database.
   *
   * @return void
   */
  protected function _connect()
  { // Nothing to do, since we do not connect the current obj, but use zend_db
  }

  /**
   * Test if a connection is active
   *
   * @return boolean
   */
  public function isConnected() {return true;}
  public function getServerVersion() {return "xxx";}

  /**
   * Force the connection to close.
   *
   * @return void
   */
  public function closeConnection() {$this->zend_db->closeConnection();}

  /**
   * Prepare a statement and return a PDOStatement-like object.
   *
   * @param string|Zend_Db_Select $sql SQL query
   * @return Zend_Db_Statment|PDOStatement
   */
  public function prepare($sql) {return $this->zend_db->prepare($sql);}

  /**     */
  public function lastInsertId($tableName = null, $primaryKey = null)
  {return $this->zend_db->lastInsertID($tableName, $primaryKey);}

  /**
   * Begin a transaction.
   */
  protected function _beginTransaction()
  {return $this->zend_db->_beginTransaction();}

  /**
   * Commit a transaction.
   */
  protected function _commit()
  {return $this->zend_db->_commit();}


  /**
   * Roll-back a transaction.
   */
  protected function _rollBack()
  {return $this->zend_db->_rollBack();}

  /**
   * Set the fetch mode.
   *
   * @param integer $mode
   * @return void
   * @throws Zend_Db_Adapter_Exception
   */
  public function setFetchMode($mode) {
    $this->zend_db->setFetchMode($mode);
  }
   

  /**
   * Adds an adapter-specific LIMIT clause to the SELECT statement.
   *
   * @param mixed $sql
   * @param integer $count
   * @param integer $offset
   * @return string
   */
  public function limit($sql, $count, $offset = 0)
  {return $this->zend_db->limit($sql, $count, $offset);}
   

  /**
   * Check if the adapter supports real SQL parameters.
   *
   * @param string $type 'positional' or 'named'
   * @return bool
   */
  public function supportsParameters($type)
  {return $this->zend_db->supportsParameters($type) ;}

  // We override the lastInsertID of the present class to prevent
  // the use of AUTO INCREMENTED fields. It is always required
  // to call the getNextID() function before inserting a row
   
  // New function for the Framework: generates an ID from a sequence.
  public function getNextId($sequence_name)
  {
    // Specific action for MySQL
    if ($this->driver == 'mysql') {
      // Insert one row to trigger the auto-increment
      $this->query("INSERT INTO $sequence_name VALUES()");
      return $this->zend_db->lastInsertId();
    }
    else { // Should work with PostgreSQL, check with ORACLE ...
      // Call the sequence
      return $this->fetchOne("SELECT NextVal('$sequence_name') AS id");
    }
  }

  // New function: prepapes a value for direct inclusion in a query
  public function prepareString ($name)
  {
    // For MySQL
    if ($this->driver == 'mysql') {
      // Insert one row to trigger the auto-increment
      return addSlashes($name);
    }
    else {
      // TO DO
      return $name;
    }
  }

  /**
   * Forward all methods which are not implemented at THIS level
   * to the wrapped object
   */
  public function __call($method, array $options)
  {
    /* Check if the method exists locally */
    if (method_exists($this, $method)) {
      return call_user_func_array(array($this, $method), $options);
    }
    else if (method_exists($this->zend_db, $method)) {
      return call_user_func_array(array($this->zend_db, $method), $options);
    }
    throw new Zend_Db_Exception("Unknown method '" . $method . "' called!");
  }
  // End of the class
}

