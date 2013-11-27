<?php

/**
 * @category   Zmax
 * @package    Zmax_Translate
 * @copyright  
 * @license
 * @version
 */

/**
 * This is an extension of the Zend_Translate class. 
 * 
 * The main difference with Zmax_translate
 * is that an entry in the _translate table may itself be a Zmax_Tranlate
 * instance. Such entries correspond to the namespace concept added in Zmax.
 * This allows syntax 'like $translate->zmax->commit', which retrieves the
 * translation of the 'commit' text in the 'zmax' namespace.
 *
  * 
  * @package Zmax_Translate
  * @author Philippe Rigaux
  * @todo 
  *
  */

class Zmax_Translate extends Zend_Translate
{
    /**
     * Adapter names constants
     */
    const AN_ARRAY   = 'Array';
    const AN_CSV     = 'Csv';
    const AN_GETTEXT = 'Gettext';
    const AN_INI     = 'Ini';
    const AN_QT      = 'Qt';
    const AN_TBX     = 'Tbx';
    const AN_TMX     = 'Tmx';
    const AN_XLIFF   = 'Xliff';
    const AN_XMLTM   = 'XmlTm';

    const LOCALE_DIRECTORY = 'directory';
    const LOCALE_FILENAME  = 'filename';

    /**
     * Adapter
     *
     * @var Zend_Translate_Adapter
     */
    private $_adapter;
    protected static $_cache = null;

    /**
     * Generates the standard translation object
     *
     * @param  string              $adapter  Adapter to use
     * @param  array               $data     Translation source data for the adapter
     *                                       Depends on the Adapter
     * @param  string|Zend_Locale  $locale   OPTIONAL locale to use
     * @param  array               $options  OPTIONAL options for the adapter
     * @throws Zend_Translate_Exception
     */
    public function __construct($adapter, $data, $locale = null, array $options = array())
    {
        $this->setAdapter($adapter, $data, $locale, $options);
    }


  /**
   * Magic get method
   * @param  string             $text_id  Id of the text to translate
   * @return  translation of the text               $options  OPTIONAL Options to use
   */

  public function __get($text_id)
  {
     return $this->translate ($text_id);
  }
  
    /**
     * Sets a new adapter
     *
     * @param  string              $adapter  Adapter to use
     * @param  string|array        $data     Translation data
     * @param  string|Zend_Locale  $locale   OPTIONAL locale to use
     * @param  array               $options  OPTIONAL Options to use
     * @throws Zend_Translate_Exception
     */
    public function setAdapter($adapter, $data, $locale = null, array $options = array())
    {
     /** Change by  RIGAUX. our adapter is ot is this directory. Zend bug ? ... 
         if (Zend_Loader::isReadable('Zend/Translate/Adapter/' . ucfirst($adapter). '.php')) {
            $adapter = 'Zend_Translate_Adapter_' . ucfirst($adapter);
        }
    */
        if (!class_exists($adapter)) {
            Zend_Loader::loadClass($adapter);
        }

        if (self::$_cache !== null) {
            call_user_func(array($adapter, 'setCache'), self::$_cache);
        }
        $this->_adapter = new $adapter($data, $locale, $options);
        if (!$this->_adapter instanceof Zend_Translate_Adapter) {
            require_once 'Zend/Translate/Exception.php';
            throw new Zend_Translate_Exception("Adapter " . $adapter . " does not extend Zend_Translate_Adapter");
        }
    }

    /**
     * Returns the adapters name and it's options
     *
     * @return Zend_Translate_Adapter
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Returns the set cache
     *
     * @return Zend_Cache_Core The set cache
     */
    public static function getCache()
    {
        return self::$_cache;
    }

    /**
     * Sets a cache for all instances of Zend_Translate
     *
     * @param  Zend_Cache_Core $cache Cache to store to
     * @return void
     */
    public static function setCache(Zend_Cache_Core $cache)
    {
        self::$_cache = $cache;
    }

    /**
     * Returns true when a cache is set
     *
     * @return boolean
     */
    public static function hasCache()
    {
        if (self::$_cache !== null) {
            return true;
        }

        return false;
    }

    /**
     * Removes any set cache
     *
     * @return void
     */
    public static function removeCache()
    {
        self::$_cache = null;
    }

    /**
     * Clears all set cache data
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$_cache->clean();
    }

    /**
     * Calls all methods from the adapter
     */
    public function __call($method, array $options)
    {
          if (method_exists($this->_adapter, $method)) {
            return call_user_func_array(array($this->_adapter, $method), $options);
        }
        require_once 'Zend/Translate/Exception.php';
       throw new Zend_Translate_Exception("Unknown method '" . $method . "' called!");
    }
}
