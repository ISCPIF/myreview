<?php
/**
 * @category Zmax
 * @package    Zmax_View
 * @subpackage Phplib
 * @copyright
 * @license
 * @version
 */


/**
 * Zend-compatible implementation of the Phplib templates
 *
 *  This class is part of the PHP framework for Sint Gobain. It implements the Zend_View_Interface
 * and provides the view functionnalities of the MVC architecture
 *
 * @package Zmax_View
 * @subpackage Phplib
 * @author Philippe Rigaux
 * @todo This class needs to be fully documented.
 *
 */

class Zmax_View_Phplib implements Zend_View_Interface
{
  private $classname = "Template";
  /* if set, echo assignments */
  private $debug     = false;

  /* relative filenames are relative to this pathname */
  private $root   = "";

  // Get the files from the following path
  private $path = "";

  /* $varkeys[key] = "key"; $varvals[key] = "value"; */
  private $varkeys = array();
  private $varvals = array();
  private $file = array();

  /* "remove"  => remove undefined variables
   * "comment" => replace undefined variables with comments
   * "keep"    => keep undefined variables
   */
  private $unknowns = "remove";

  /* "yes" => halt, "report" => report error, continue, "no" => ignore error quietly */
  private $halt_on_error  = "yes";

  /* last error message is retained here */
  private $last_error     = "";

  /**
   * Constructor
   *
   * @param string $tmplPath
   * @param array $extraParams
   */
  public function __construct($tmplPath = null, $extraParams = array())
  {
    if (null !== $tmplPath) {
      $this->setScriptPath($tmplPath);
    }

    foreach ($extraParams as $key => $value) {
      $this->set_var($key, $value);
    }
  }

  /**
   * Return the template engine object
   *
   */
  public function getEngine()
  {
    return "null";
  }

  /**
   * Set the path to the templates
   *
   * @param string $path The directory to set as the path.
   * @return void
   */
  public function setScriptPath($path)
  {
    $this->setPath ($this->root . "/" . $path);
  }

  /**
   * Set the path to the view scripts
   *
   * @author philipperigaux
   *
   */
  public function setPath($path)
  {
    $this->path = $path;
    if (!is_readable($this->path)) {
      throw new Exception("Invalid path '$this->path' provided");
    }
  }
   
  /**
   * Retrieve the current template directory as an array
   *
   * @return array(string)
   */
  public function getScriptPaths()
  {
    return $this->path;
  }

  /**
   * Retrieve the current template directory
   *
   * @return string
   */
  public function getRootPath()
  {
    return $this->root;
  }

  /**
   * Set the current template directory
   *
   * @param string
   */
  public function setRootPath($root)
  {
    $this->root = $root;
    $this->path = $root;
  }

  /**
   * Alias for setScriptPath
   *
   * @param string $path
   * @param string $prefix Unused
   * @return void
   */
  public function setBasePath($path, $prefix = 'GF_View')
  {
    return $this->setScriptPath($path);
  }

  /**
   * Alias for setScriptPath
   *
   * @param string $path
   * @param string $prefix Unused
   * @return void
   */
  public function addBasePath($path, $prefix = 'GF_View')
  {
    return $this->setScriptPath($path);
  }

  /* public: set_file(array $filelist)
   * @filelist: array of handle, filename pairs.
   *
   * public: set_file(string $handle, string $filename)
   * @handle: handle for a filename,
   * @filename: name of template file
   */
  public function set_file($handle, $filename = "") {
    if (!is_array($handle)) {
      if ($filename == "") {
        throw new Exception ("set_file: For handle $handle filename is empty.");
        return false;
      }
      $this->file[$handle] = $this->filename($filename);
      // Load the file now: this allows to load the file content
      // as en entity. Downside: maybe be not efficient at all
      // is setFile is called for files that are no used at the end ..
      $this->loadFile($handle);
      // Parse it to include pre-defined entities, such the base URL
      $this->parse ($handle, $handle);

    } else {
      reset($handle);
      while(list($h, $f) = each($handle)) {
        $this->file[$h] = $this->filename($f);
      }
    }
  }
  public function setFile ($handle, $filename = "")
  { $this->set_file($handle, $filename); }

  /* public: set_block(string $parent, string $handle, string $name = "")
   * extract the template $handle from $parent,
   * place variable {$name} instead.
   */
  public function set_block($parent, $handle, $name = "") {
    if (!$this->loadfile($parent)) {
      throw new Exception ("subst: unable to load $parent.");
      return false;
    }
    if ($name == "")
    $name = $handle;
    else
    $this->set_var($name, "");

    $str = $this->get_var($parent);
    $reg = "/<!--\s+BEGIN $handle\s+-->(.*)\n\s*<!--\s+END $handle\s+-->/sm";
    preg_match_all($reg, $str, $m);
    $str = preg_replace($reg, "{" . "$name}", $str);
    if(!isset($m[1][0]))
    {
      throw new Zmax_Exception ("Block $handle does not exist");
    }
    $this->set_var($handle, $m[1][0]);
    // echo "Replace $parent with <pre>" . htmlentities($str) . "</pre>";
    $this->set_var($parent, $str);
  }

  public function setBlock ($parent, $handle, $name = "")
  { $this->set_block ($parent, $handle, $name); }

  /**
   * Overloading of the __set magic method: put the value in
   * the array, indexed by the key
   * Assign a variable to the template
   *
   * @param string $key The variable name.
   * @param mixed $val The variable value.
   * @return void
   */
  public function __set($key, $val)
  {
    $this->set_var($key, $val);
  }

  /**
   * Retrieve an assigned variable (overload the magic __get method)
   *
   * @param string $key The variable name.
   * @return mixed The variable value.
   */
  public function __get($key)
  {
    return $this->get_var($key);
  }

  /**
   * Allows testing with empty() and isset() to work
   *
   * @param string $key
   * @return boolean
   */
  public function __isset($key)
  {
    return (null !== $this->varvals[$key]);
  }

  /**
   * Allows unset() on object properties to work
   *
   * @param string $key
   * @return void
   */
  public function __unset($key)
  {
    $this->clearVars();
  }

  /**
   * Assign variables to the template
   *
   * Allows setting a specific key to the specified value, OR passing an array
   * of key => value pairs to set en masse.
   *
   * @see __set()
   * @param string|array $spec The assignment strategy to use (key or array of key
   * => value pairs)
   * @param mixed $value (Optional) If assigning a named variable, use this
   * as the value.
   * @return void
   */
  public function setVar($spec, $value = null)
  {  	  $this->set_var($spec, $value); }

  /* public: set_var(string $varname, string $value)
   * @varname: name of a variable that is to be defined
   * @value:   value of that variable
   */
  public function set_var ($varname, $value = "")
  {
    if (!is_array($varname)) {
      if (!empty($varname))
      $this->varkeys[$varname] = "/". $this->varname($varname) ."/";
      $this->varvals[$varname] = $value;
    } else {
      reset($varname);
      foreach ($varname as $k => $v) {
        if (!empty($k))
        $this->varkeys[$k] = "/".$this->varname($k)."/";
        $this->varvals[$k] = $v;
      }
    }
  }

  /* public: get_var(string $varname, string $value)
   * @varname: name of a variable that is to be retrieved
   * @return:   value of that variable
   */
  public function get_var ($varname)
  {
    return $this->varvals[$varname];
  }

  // Synonym of the previous one
  public function getVar ($varname) { return $this->get_var($varname); }

  /* public: clearVar(string $varname, string $value)
   * @varname: name of a variable that is to be undefined
   * @return:   value of that variable
   */
  public function clearVar ($varname)
  {
    $this->varvals[$varname] = null;
    $this->varkeys[$varname] = null;
  }
   
  /**
   * Clear all assigned variables
   *
   * Clears all variables assigned to Zend_View either via {@link assign()} or
   * property overloading ({@link __get()}/{@link __set()}).
   *
   * @return void
   */
  public function clearVars()
  {
    $this->varvals = array();
    $this->varkeys = array();
  }

  /* Instantiate a template, and put the result in an entity
   *
   * @target: handle of variable to generate
   * @handle: handle of template to substitute
   * @append: append to target handle
   */

  private function parse($target, $handle, $append = false) {
    $str = $this->subst($handle);
    // echo "parse: new str = <pre>" . htmlspecialchars($str) . "</pre>";
    if ($append) {
      // The new value is appended to the old one
      $this->set_var($target, $this->get_var($target) . $str);
    } else {
      // The new value replaces the old one
      $this->set_var($target, $str);
    }
    //return $str;
  }

  /** Instantiate a template and put it in an entity
   * whose content is replaced
   *
   */
  public function assign ($target, $handle=null) {
    if ($handle != null)
    $this->parse ($target, $handle);
    else
    $this->target = "";
  }
   
  /** Instantiate a template and put it in an entity
   * whose content is cumulated
   *
   */
  public function append ($target, $handle) {
    $this->parse ($target, $handle, true);
  }
   
   
  /**
   * Check that a entity exists
   *
   */

  function entityExists($entityName)
  {
    if (isSet($this->varkeys[$entityName])) {
      return true;
    }
    else {
      return false;
    }
  }

  /**
   * Processes a template and returns the output.
   *
   * @param string $name The template to process.
   * @return string The output.
   */
  public function render($name)
  {
    $this->parse("GF_view", $name);
    $str = $this->get_var("GF_view");
    $this->clearVar ("GF_view");
    return $str;
  }

  /************** Private part of the class  *****************/

  /* private: subst(string $handle)
   * @handle: handle of template where variables are to be substituted.
   */
  private function subst($handle) {
    // Load the file. If it is already done, the loadfile returns at once
    if (!$this->loadfile($handle)) {
      throw new Exception("subst: unable to load $handle.");
    }
   	// Make the substitution
    $str = $this->get_var($handle);
    $str = preg_replace($this->varkeys, $this->varvals, $str);
    return $str;
  }

  /* private: varname($varname)
   * @varname: name of a replacement variable to be protected.
   */
  private function varname($varname) {
    return preg_quote("{".$varname."}");
  }

  /***************************************************************************/
  /* private: filename($filename)
   * @filename: name to be completed.
   */
  private function filename($filename) {
    // Here:  "/"  generated an error message with Windows.
    if (substr($filename, 0, 1) != "/") {
      // The filename is relative: add the local path
      $filename = $this->path . DIRECTORY_SEPARATOR . $filename;
    }

    // Here:  "\"  generated an error message with Windows.
    if (substr($filename, 0, 1) == "\\") {
      $filename = substr($filename, 1, strlen($filename)-1);
    }

    // Here:  "\"  generated an error message with Windows.
    if (substr($filename, 0, 1) == "\\") {
      $filename = substr($filename, 1, strlen($filename)-1);
    }

    if (!file_exists($filename))
    throw new Exception ("filename: file $filename does not exist.");

    return $filename;
  }

  /* private: loadfile(string $handle)
   * @handle:  load file defined by handle, if it is not loaded yet.
   */
  private function loadfile($handle) {
    if (isset($this->varkeys[$handle]) and !empty($this->varvals[$handle]))
    return true;

    if (!isset($this->file[$handle])) {
      throw new Exception ("loadfile: $handle is not a valid handle.");
      return false;
    }
    $filename = $this->file[$handle];

    $str = implode("", @file($filename));
    if (empty($str)) {
      throw new Exception ("loadfile: While loading $handle, $filename does not exist or is empty.");
      return false;
    }

    $this->set_var($handle, $str);

    return true;
  }


  // Select list for HTML Forms
  static function selectField ($nom, $liste, $defaut, $taille=1, $id=null)
  {
    if ($id == null) {
      $s = "<select name=\"$nom\" size='$taille'>\n";
    }
    else {
      $s = "<select id=\"$id\" name=\"$nom\" size='$taille'>\n";
    }

    while (list ($val, $libelle) = each ($liste))
    {
      $val = htmlSpecialChars($val);
      $defaut = htmlSpecialChars($defaut);

      if ($val != $defaut) {
        $s .=  "<option value=\"$val\">$libelle</option>\n";
      }
      else {
        $s .= "<option value=\"$val\" selected='1'>$libelle</option>\n";
      }
    }
    return $s . "</select>\n";
  }

  /**
   * Function that produces a list of checkbox or radio fields, with default values
   */
  static function  checkboxField ($pType, $pNom, $list, $pDefaut, $params)
  {
    if (isSet($params["length"]))
    $length = $params["length"];
    else $length = -1;

    // Toujours afficher dans une table
    $libelles=$champs="";
    $nbChoix = 0;
    $result = "<table border='0' cellspacing='5' cellpadding='2'>\n";
    foreach ($list as $val => $libelle)   {
      $libelles .= "<td><b>$libelle</b></td>";
      $checked = " ";
      if (!is_array($pDefaut)) {
        if ($val == $pDefaut) $checked = " checked='1' ";
        $champs .= "<td><input type='$pType' "
        . "name=\"$pNom\" value=\"$val\" "
        . " $checked> </td>\n";//adyilie: moved and changed from below
      }
      else {
        if (is_int(strpos($pNom, "[]"))) {
          $lNom=$pNom;//adyilie: inserted, radio buttons are arrays with indices, checkboxes are not
        }
        else {
          $lNom=$pNom."[$nbChoix]";
        }
        if (isSet($pDefaut[$val])) $checked = "checked='1'";

        $champs .= "<td><input type='$pType' name=\"$lNom\" value=\"$val\" "
        . " $checked> </td>\n";
      }

      $nbChoix++;

      // Eventuellement on place plusieurs lignes dans la table
      if ($length == $nbChoix) {
        $result .= "<tr>" . $libelles . "</tr><tr>"
        . $champs . "</tr>\n";
        $libelles = $champs = "";
        $nbChoix = 0;
      }
    }

    if (!empty($champs)) {
      return  $result . "<tr>" . $libelles .  "</tr>\n<tr>" . $champs
      . "</tr></table>";
    }
    else  {
      return $result . "</table>";
    }
  }

  static function formatDate($date, $date_format){
    $tab=explode('-',$date);
    return date ($date_format, mktime (0, 0, 0, $tab[1], $tab[2], $tab[0]));
  }
}

