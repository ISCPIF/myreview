<?php

/**
 * @category   Zmax
 * @package    Zmax_Translate
 * @subpackage Db
 * @copyright
 * @license
 * @version
 */

/**
 * An implementation of a Translae Adapter that searches the translations in a database
 *
 *
 * @package Zmax_Translate
 * @subpackage Db
 * @author Philippe Rigaux
 * @todo
 *
 */

class Zmax_Translate_Adapter_Db extends Zend_Translate_Adapter
{
  private $db; // The Zmax_Db object to communicate with the DB
  private $phplib_view = null; //The phplib view object (if used)

  const DEFAULT_NAMESPACE  = 'def';

  /**
   * Generates the adapter
   *
   * @param  array               $db    The DB connection
   * @param  string|Zend_Locale  $locale   OPTIONAL Locale/Language to set, identical with locale identifier,
   *                                       see Zend_Locale for more information
   * @param  array               $options  OPTIONAL Options to set
   */
  public function __construct($db, $locale = null, array $options = array())
  {

    // If $db is null: nothing to do.
    if ($db == null) return;

    // Keep the db reference for possible use
    $this->db = $db;

    // Check whether the view system is PHPLIB. In yes we
    // add all the translations as entities in the view object

    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();
    $zmax_context = $registry->get("zmax_context");
    $config = $zmax_context->config;
    if ($config->view->zmax_view_system == "phplib") {
      $this->phplib_view = $zmax_context->view;
    }

    // Now call the parent constructor
    // Adding this option is necessary starting with Zend 1.6, to prevent
    // an ugly message
    $options['disableNotices'] = 'true';
    parent::__construct($db, $locale, $options);
  }

  /**
   * Load translation data
   *
   * @param  mixed              $data
   * @param  string|Zend_Locale $locale
   * @param  array              $options (optional)
   * @return void
   */
  protected function _loadTranslationData($db, $locale, array $options = array())
  {
    $texts = array();
    // Check the type of the locale argument
    if (is_object ($locale))
    $lang = $locale->getLanguage();
    else
    $lang = $locale;
     
    // If the 'namespace' entry of the options array is set, just
    // take this namespace, else take the default namespace
    if (isSet($options['namespace'])) {
      $namespace = $options['namespace'];
    }
    else
    $namespace = self::DEFAULT_NAMESPACE;
     
    // If this is the default namespace: create a self reference
    // in the translation array. This supports the syntax 'texts->def->code',
    // instead of 'texts->code
    if ($namespace == self::DEFAULT_NAMESPACE)
    $this->_translate[$locale][self::DEFAULT_NAMESPACE] = $this;

    // Add some automatically determined zmax entries
    if ($namespace == "zmax") {
      $locale_obj = new Zmax_Locale();
      // RIGAUX ancien appel $lang_name = $locale_obj->getLanguageTranslation($locale);
      $lang_name = $locale_obj->getTranslation($locale, 'language',$locale);
      $this->_translate[$locale]['lang'] = $lang_name;
      if (is_object($this->phplib_view)) {
        $this->phplib_view->set_var("zmax.lang", $lang_name);
      }
    }

    $zmax_text = new Zmax_Model_Text();
    $where = "namespace='$namespace' AND lang='$lang'";
    $list_texts = $zmax_text->fetchAll($where);

    foreach ($list_texts as $t) {
   	  // If the PHPLIB view system is used: define entities
      if (is_object($this->phplib_view)) {
        $entity = $t->namespace . "." . $t->text_code;
        $this->phplib_view->set_var("$entity", $t->the_text);
      }
      $texts[$t->text_code] = $t->the_text;
    }

    // Merge the options with the internal ones
    $local_options = array_merge($this->_options, $options);
     
    // Clear the translation array
    if (($local_options['clear'] == true) ||  !isset($this->_translate[$locale])) {
      $this->_translate[$locale] = array();
    }

    // Store the translations of the namespace in the array
    $this->_translate[$locale] = array_merge($this->_translate[$locale], $texts);

     
    // Now handle namespace arrays.
    if (isSet($options['namespaces'])) {
      foreach ($options['namespaces'] as $namespace) {
        // echo "Load the namespace $namespace<br/>";
        if (isSet($this->_translate[$locale][$namespace])
        and is_object ($this->_translate[$locale][$namespace])) {
          // The sub-translation object already exists.
          $this->_translate[$locale][$namespace]->addTranslation ($db);
        }
        else {
          // Instantiate the sub-translate object, which loads the namespace translations
          $options = array('namespace' => $namespace);
          $this->_translate[$locale][$namespace] =
          new Zmax_Translate($this->toString(), $db, $locale, $options);
        }
      }
    }
  }

  /**
   * Returns the adapter name
   *
   * @return string
   */
  public function toString()
  {
    return "Zmax_Translate_Adapter_Db";
  }

  /**
   * DEPRECATED FUNCTION -- MUST NO LONGER BE USED
   *
   * @return string
   */
  function get($text_id, $lang="")
  {
    // Just a synonym for the magic get
    return $this->translate($text_id);
  }

  /**
   * Magic get method
   * @param  string             $text_id  Id of the text to translate
   * @return  translation of the text
   */

  public function __get($text_id)
  {
    return $this->translate($text_id);
  }

  /**
   * Check that a (namespace, text) code exists in a given namespace
   * 
   */
   public function exist ($lang, $namespace, $text_id)
   {
    // First, check that the namespace exists
     if (!isSet ($this->_translate[$lang][$namespace])) {
       return false;
     }
     else {
       // Check that the text exists in the namespace
       return $this->_translate[$lang][$namespace]->textExist($lang, $text_id);
    }
   }
   
   /**
    * Check that a text code exists in the local translator
    */
   public function textExist ($lang, $text_id) 
   {
     if (isSet($this->_translate[$lang][$text_id])) {
        return true;
     }
     else {
        return false;
     }
   }    
}