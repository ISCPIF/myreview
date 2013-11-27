<?php

/**
 * @category   Zmax
 * @package    Zmax_Ldap
 * @subpackage Adapter
 * @copyright  
 * @license
 * @version
 */

/**
 * The Zmax LDap Adapter
 * 
  * 
 * @package    Zmax_Ldap
  * @author Olivier Boissin
  *
  */

class Zmax_Ldap_Adapter {
	protected $_link = null ;
	protected $_basedn = null ;
	protected $_filter = null ;
	protected $_attributes = null ;
	
  /**
   * Initialize a LDAP Adapter object
   *
   * @param	Zend_Config	$config
   * @return	Zmax_Ldap_Adapter
   */	
	
	public function __construct($config) {
		$this->_link = ldap_connect($config->server, $config->port) ;
		if(!$this->_link) {
			throw new Zmax_Exception("Cannot connect to LDAP server '" . $config->server 
			              . ":". $config->port . "'.") ;
		}
		
		ldap_set_option($this->_link, LDAP_OPT_PROTOCOL_VERSION, 3) ;
		
		// Attempt to connect to the LDAP server using the LDAP server,
		// or the anonymous account if the username is undefined
		$res = ($config->username != null && strlen(trim($config->username)) > 0 ?
			ldap_bind($this->_link, $config->username, $config->password) :
			ldap_bind($this->_link)	// anonymous
		);
		if (!$res) {
			throw new Zmax_Exception("Cannot bind to LDAP server '" . $config->server . ":". $config->port . "'.") ;
		}
		
		// Get the ther parameters from Zend_Config objects
		$this->_basedn = $config->basedn ;
		$this->_filter = $config->filter ;
		$this->_attributes = $config->attributes ;
		
		return true ;
	}
	
	/**
	 * Destructor: close the Ldap connection
	 *
	 */
	public function __destruct() {
		try { ldap_close($this->_link); } catch (Zmax_Exception $e) {	} 
		$this->_link = null ;
		
		return true ;
	}
	
  /**
   * Wrapper for PHP ldap functions. Prepend automatically the LDAP resource handler.
   * 
   * @param  string name of the LDAP PHP function that must be called
   * @param array arguments of the function
   */	
	public function __call($function, $args) {
		// appends automatically the resource link as first arg. 
	  // Should work for most of ldap_functions().
		return call_user_func_array($function , array_merge(array($this->_link), $args));		
	}
	
	/**
	 * Accessor for the list of attributes mapping
	 *
	 * @return array Ldap attributes
	 */
	public function getAttributes() {
		return $this->_attributes ;
	}
	
	/**
	 * Accessor for the LDap base attributes
	 *
	 * @return array Ldap base attributes
	 */
	public function getBaseDn() {
		return $this->_basedn ;
	}
	
	/**
	 * Accessor for the Ldap filter 
	 *
	 * @return array 
	 */
	public function getFilter() {
		return $this->_filter ;
	}	
}