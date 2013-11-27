<?php

/**
 * @category   Zmax
 * @package    Zmax_Ldap
 * @subpackage Gateway
 * @copyright
 * @license
 * @version
 */

 /**
  * The Zmax LDap Gateway
  *
  * This is an abstract class that must be extended for each specific
  * entity to retrieve from an Ldap repository. Each extended class
  * inherits the methods from the abstract class: they allow to query
  * the LDAP server.
  *
  * Sample usage :
  * 	$ldap = new Zmax_Ldap_Adapter($config->ldap) ;
  * Zmax_Ldap_Gateway_Abstract::setDefaultAdapter($ldap);
  *
  *$ldapUser = new Zmax_Model_LdapUser();
  *$res = $ldapUser->findByLastnameOrFirstname("boissin");
  *$ldapUser->sortByLastnameThenFirstname($res);
  *$data = $ldapUser->getAllEntries($res);
  *
  * echo json_encode($data) ;
  *
  * Zmax_Model_LdapUser :
  * 	class Zmax_Model_LdapUser extends Zmax_Ldap_Gateway_Abstract {
  *protected $_type = "user";	// maps "ldap.basedn.user" and "ldap.attributes.user.*" in ldap.ini
  *}
  *
  *
  * @package    Zmax_Ldap
  * @subpackage Gateway
  * @author Olivier Boissin
  *
  */

abstract class Zmax_Ldap_Gateway_Abstract {
  /**
   * Zmax_Ldap_Adapter_Abstract default adapter
   *
   * @var Zmax_Ldap_Adapter_Abstract
   */
  protected static $_defaultLdap = null ;

  /**
   * Zmax_Ldap_Adapter_Abstract current adapter
   *
   * @var Zmax_Ldap_Adapter_Abstract
   */
  protected $_ldap = null ;

  /**
   * type as defined in the ldap.ini configuration file
   *
   * @var string
   */
  protected $_type = "" ;	// In PHP5 properties cannot be declared as abstract ! oO

  protected $_attributes = null ;
  protected $_basedn = null;
  protected $_filter = null;

  /**
   * Set default LDAP Adapter for all new instances.
   *
   * @param	Zmax_Ldap_Adapter	$ldap
   * @return	void
   */
  public static function setDefaultAdapter(Zmax_Ldap_Adapter $ldap = null) {
    self::$_defaultLdap = $ldap ;
  }

  /**
   * Initialize the instance. The ldap
   * parameter is optional: if null, the default adapter should
   * have been defined earlier.
   *
   * @param	Zmax_Ldap_Adapter	$ldap 	(optionnal)
   * @return	void
   */
  public function __construct(Zmax_Ldap_Adapter $ldap = null) {
    if ($ldap != null) {
      $this->_ldap = $ldap ;
    }
    elseif (self::$_defaultLdap != null) {
      $this->_ldap = self::$_defaultLdap ;
    }
    else {
      // Attempt to construct the Gateway without an Ldap adapter
      throw new Zmax_Exception("Attempt to inst. an LDAP gateway without an adapter.") ;
    }

    // store locally LDAP defined available attributes, basedn & filter
    $type = $this->_type ;
    $this->_attributes = $this->_ldap->getAttributes()->$type ;
    $this->_basedn = $this->_ldap->getBaseDn()->$type ;

    try {
      $this->_filter = $this->_ldap->getFilter()->$type ;
    }
    catch(Zmax_Exception $e) {	// an exception is thrown if no filter is defined
      $this->_filter = null ;
    }
  }

  /**
   * Acces to the LDAP server and retrieve the entries after
   * a call to a method findByXXX. The result data is cleaned
   * and returned.
   *
   * @param mixed Result of an LDAP query
   * @return array The data goty from LDAP
   */

  public function getAllEntries($res) {
    $data = $this->_ldap->ldap_get_entries($res) ;
    return $this->_cleanMessyResults($data);
  }

  /**
   * Transform the result of an LDAP query into a convenient
   * PHP array which cleanly presents the results.
   * @param array the LDAP data
   * @return array the cleaned LDAP data, a 2D array indexed on id + attributes name
   */

  protected function _cleanMessyResults($rawdata) {
    // clean messy array
    array_shift($rawdata); 	// remove the useless and annoying 'count' entry

    for($i=0 ; $i<count($rawdata) ; $i++) {
      $rawdata[$i] = $this->_cleanMessyEntry($rawdata[$i]);
    }

    // populate a new array with available attributes only, and
    // swap external to internal attributes
    $data = array();
    $i = 0 ;

    foreach ($rawdata as $entry) {
      // for each attribute of the entry
      foreach($entry as $attribute => $value) {
        // for each available attribute
        foreach($this->_attributes->toArray() as $internal => $external) {
          if ($attribute == $external) {
            $data[$i][$internal] = $value ;
          }
        }
      }
      $i++;
    }
    return $data ;
  }

  /**
   * Clean an LDAP entry
   * @param array an LDAP entry
   * @result array the cleaned LDAP entry
   */
  protected function _cleanMessyEntry($entry) {
    $results = array();
    $mapping = $this->_attributes->toArray() ;

    // normalize the keys
    foreach($entry as $attribute => $value) {
      if (intval($attribute) === $attribute) {	// if 'data' entry
        array_shift($entry[$value]); 	// remove the useless and annoying 'count' entry

        // normalize the value
        if (count($entry[$value]) > 1)
        $results[$value] = $entry[$value] ;
        else
        $results[$value] = implode('', $entry[$value]) ; // no array if single value
      }
    }
  		return $results;
  }

  /**
   * Magic call method. Used to intercept the 'findByXXX' methods
   * and the 'sortByXXX' methods
   * @param string the name of the method (should be findByXXX or sortByXXX)
   * @pram array the parameter list
   */

  public function __call($method, $args) {
    // local handles
    $attributes = $this->_attributes ;
    $basedn = $this->_basedn ;
    $filter = $this->_filter ;

    // findBySomething1AndSomething1AndSomething3($whatever1)
    // findBySomething1OrSomething2($whatever1[, $whatever2, ...])
    if (preg_match('/^findBy([A-Z]{1}[a-z0-9]+?)(And|Or)((?:[A-Z]{1}[a-z0-9]+?)+?)$/',
    $method, $parts)) {
      // extracting & formatting attributes
      $arr_attributes = array_merge(array($parts[1]), explode($parts[2], $parts[3])) ;
      $arr_attributes = array_map("strtolower", $arr_attributes) ;
       
      foreach($arr_attributes as $attribute)  {
        if (!$attributes->$attribute)
        throw new Zmax_Exception("The attribute '" . $attribute . "' is not defined in the 'ldap.ini' file.");
      }
       
      // building LDAP search query
      $query = "(" . ($parts[2] == "Or" ? "|" : "&") ;
      $i=0 ;
      foreach($arr_attributes as $attribute)  {
        // TODO : escape the quotes from the entries
        $query .= "(" . $attributes->$attribute . "=" . $args[$i < count($args) ? $i : count($args) - 1] . ")";
        $i++;
      }
      $query .= ")" ;
       
      // add extra filter if defined (optionnal)
      // TODO : refactor this code
      if ($filter != null && strlen($filter) > 0) {
        $query = "(&" . $filter . $query . ")" ;
      }
       
      return $this->_ldap->ldap_search($basedn, $query);
    }
    // findBySomething($whatever)
    elseif (preg_match('/^findBy([A-Z]{1}[a-z0-9]+?)$/', $method, $parts)) {
      $attribute = strtolower($parts[1]);
       
      if (!$attributes->$attribute) throw new Zmax_Exception("The attribute '" . $attribute . "' is not valid.");
       
      // TODO : escape the quotes from the entries
      $query = sprintf("(%s=%s)", $attributes->$attribute, $args[0]) ;
       
      // add extra filter if defined (optionnal)
      // TODO : refactor this code
      if ($filter != null && strlen($filter) > 0) {
        $query = "(&" . $filter . $query . ")" ;
      }
       
      return $this->_ldap->ldap_search($basedn, $query);
    }
    // sortBySomething1ThenSomething2($search)
    elseif (preg_match('/^sortBy([A-Z]{1}[a-z0-9]+?)Then((?:[A-Z]{1}[a-z0-9]+?)+?)$/', $method, $parts)) {
      // extracting & formatting attributes
      $arr_attributes = array_merge(array($parts[1]), explode("Then", $parts[2])) ;
      $arr_attributes = array_map("strtolower", $arr_attributes) ;
       
      foreach($arr_attributes as $attribute)  {
        if (!$attributes->$attribute) throw new Zmax_Exception("The attribute '" . $attribute . "' is not defined in the 'ldap.ini' file.");
      }
       
      foreach($arr_attributes as $attribute)  {
        $this->_ldap->ldap_sort($args[0], $attributes->$attribute);
      }
    }
    else {
      throw new Zmax_Exception("The method '" . $method . "' is not valid.");
    }
  }
}