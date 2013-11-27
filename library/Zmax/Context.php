<?php

/**
 * @category   Zmax
 * @package    Zmax_Context
 * @copyright
 * @license
 * @version
 */


/**
 * This class represents the context of a Zmax application.
 * 
 * It consists of the following components:
 *  - the Zmax_Db connexion object
 * - The Zmax_Config configuration object
 * 
 * @package    Zmax_Context
 * @author Philippe Rigaux
 *  
 */

class Zmax_Context 
{
  private $_context = array ("db" => null,
                              "config" => null,
                              "texts" => null,
                              "view" => null,
                              "locale"=>null,
                              "user" => null,
                              "is_authenticated" => false,
                              "root_path" => DIRECTORY_SEPARATOR,
                             "document_root_path" =>  "www"
  );
  
    /**
     * Constructor.
     *
     * Supported params for $config are:-
     * - table       = class name or object of type Zend_Db_Table_Abstract
     * - data        = values of columns in this row.
     *
     * @param  array $context OPTIONAL Array of user-specified context options.
     * @return void
     * @throws 
     */
  
    public function __construct(array $context = array())
    {
      // Assign the components
      foreach ($context as $compName => $component)
        eval ("\$this->$compName = \$component;");        
    }

    /**
     * Retrieve a component of the context
     *
     * @param  string $compName The name of the component
     * @return object             The corresponding component object
     * @throws Zend_Exception if the $compName is not a component
     */
 
     public function __get($compName)
    {
        if (!array_key_exists($compName, $this->_context)) {
            throw new Zend_Exception("Specified component '$compName' is not in the Zmax context");
        }
        return $this->_context[$compName];
    }
    
     /**
     * Set  a component of the context
     *
     * @param  string $compName The name of the component
     * @param string $component The ncomponent
    * @throws Zend_Exception if the $compName is not an acepted component
     */
 
     public function __set($compName, $component)
    {
        if (!array_key_exists($compName, $this->_context)) {
            throw new Zend_Exception("Specified component ".
               $compName . " cannot be set in the Zmax context");
        }
         $this->_context[$compName] = $component;
    }    
}