<?php

/**
 * Manages translations to/from an XML file
 * @author philipperigaux
 *
 */

class Zmax_Translation
{
  // namespace of translations. Default is "zmax"
  private $_namespace, $_nsUpper, $_nsLength;

  // The SAX parser
  private $parser;

  // The array that stores the translations
  private $_translations;

  //  SAX variables
  private $_targetLang,
  $_currentPCdata, $_currentNamespace, $_currentText, $_currentLang, $_countTexts;

  // Constructor
  function __construct ($namespace="zmax")
  {
    $this->_namespace = $namespace;
    $this->_nsLength = strlen($namespace);

    // Keep also the Uppercase version (SAX gives everything in uppercase)
    $this->_nsUpper = strToUpper($namespace);
  }

  /**
   * Export in an XML file.
   */
  function export ($db, $view, $lang, $template, $sendToClient=false, $namespace='all')
  {
    //Header ("Content-type: text/xml");

    // Parse the template. Very important: it must be structured as
    // a "namespace" block, and a "text" nested block. Look at the Zmax example

    $view->setFile ("content", $template);
    $view->setBlock ("content", "namespace", "namespaces");
    $view->setBlock ("namespace", "text", "texts");
    $view->trSpace = $this->_namespace;

    // Loop on namespaces
    if ($namespace == 'all') {
      $namespaces = $db->query("SELECT * FROM zmax_namespace");
      $fileName = "translation";
    }
    else {
      $namespaces = $db->query("SELECT * FROM zmax_namespace WHERE namespace='$namespace'");
      $fileName =  $namespace;
    }

    while ($nspace =  $namespaces->fetch (Zend_Db::FETCH_OBJ)) {
      $view->texts = "";
      $view->nspace = $nspace->namespace;

      // Loop on texts
      $texts = $db->query("SELECT * FROM zmax_text WHERE namespace='{$nspace->namespace}' and lang='en'");
      while ($text =  $texts->fetch (Zend_Db::FETCH_OBJ)) {
        // Get the current translation in the chosen language
        $trRes = $db->query("SELECT * FROM zmax_text WHERE namespace='{$nspace->namespace}' "
        .  " AND text_code='{$text->text_code}' AND lang='$lang'");
        $translation =  $trRes->fetch (Zend_Db::FETCH_OBJ);
        if (is_object($translation)) {
          $view->the_translation = $translation->the_text;
        }
        else {
          $view->the_translation = "";
        }

        $view->tcode = $text->text_code;
        $view->the_text = $text->the_text;
        $view->append("texts", "text");
      }
      $view->append("namespaces", "namespace");
    }
    $view->assign ("result", "content");

    if ($sendToClient) {
      $type = "application/octet-stream";
      header("Content-disposition: attachment; filename=$fileName-$lang.xml");
      header("Content-Type: application/force-download");
      header("Content-Transfer-Encoding: $type\n");
      header("Content-Length: ".strlen($view->result));
      header("Pragma: no-cache");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
      header("Expires: 0");
    }
    return $view->result;
  }
  /**
   * Import the translation contained in an XML file
   * @param $file_name
   */

  function import ($fileName)
  {
    // Instanciate the SAX parser
    $this->parser = xml_parser_create();

    $this->_countTexts = 0;

    // Triggers = methods
    xml_set_object($this->parser, $this);

    // Put all tags and attributes in uppercase
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, true);
    xml_parser_set_option($this->parser,XML_OPTION_TARGET_ENCODING, "UTF-8");
     
    // Assign triggers
    xml_set_element_handler ($this->parser, "startElement", "endElement");
    xml_set_character_data_handler ($this->parser, "pcdataHandling");

    // Initialize the array
    $this->_translations = array();

    // Parse
    $this->parse($fileName);
  }

  /**
   * Save a set of translations in the DB
   * @return nb of texts inserted
   */

  function save($db, &$missingTranslation, $importEnglish=false)
  {
    $targetLang =  $this->targetLang();

    $nbInserts = 0;
    $missingTranslation = false;

    // Check that the lang exists
    $langRes = $db->query("SELECT * FROM zmax_lang WHERE lang='{$targetLang}' ");
    $zmaxLang  =  $langRes->fetch (Zend_Db::FETCH_OBJ);
    if (!is_object($zmaxLang)) {
      $this->error( "Language $targetLang is unknown. Please create the language in the configuration page.");
    }
    else {
      foreach ($this->_translations as $namespace => $texts) {
        foreach ($texts as $code => $translation) {
          foreach ($translation as $lang => $value) {
            if (!$importEnglish and $lang =='en') {
              continue; // English not imported
            }

            if (trim($value) != "") {
              // OK, we can insert/replace the translation
              $db->query("DELETE FROM zmax_text WHERE namespace='$namespace' "
              .  "AND text_code = '$code' AND lang='$lang' " );
              $data = array( 'namespace'      => $namespace,
              'lang' => $lang,
              'text_code'      => $code,
              'the_text' => trim($value)
              );

              $db->insert("zmax_text", $data );
              // echo "Insert<br/>";
              $nbInserts++;
            }
            else {
              $missingTranslation = true;
            }
          }
        }
      }
    }
    return     $nbInserts;
  }

  // Triggers
  function startElement ($parser, $name, $attrs)
  {
    if ($name == $this->_nsUpper . ":TRANSLATIONS")
    {
      $this->_targetLang = $attrs['LANG'];
      // $this->_translations[$this->_currentNamespace][$this->_currentText] = "";
    }
    else if ($name == $this->_nsUpper . ":NAMESPACE")  {
      $this->_currentNamespace = $attrs['ID'];
      $this->_translations[$this->_currentNamespace] = array();
    }
    else if ($name == $this->_nsUpper . ":TEXT")
    {
      $this->_currentText = $attrs['ID'];
      $this->_translations[$this->_currentNamespace][$this->_currentText] = array();
    }
    else if ($name == $this->_nsUpper . ":TRANSLATION")
    {
      $this->_currentLang = $attrs['LANG'];
      $this->_currentPCdata = "";
    }
    else if (substr($name, 0, $this->_nsLength) != $this->_nsUpper ) {
      // Literal element: copy as is
      $aTag = "";
      foreach ($attrs as $aName => $aValue) {
        $aName = strToLower($aName);
        $aValue = str_replace ("&", "&amp;", $aValue);
        $aTag .= " $aName='$aValue'";
      }
      $tag = strToLower($name);
      $this->_currentPCdata .= "<$tag$aTag>";
    }

  }

  function endElement ($parser, $name)
  {

    if ($name == $this->_nsUpper . ":TRANSLATION") {
      $this->_translations[$this->_currentNamespace][$this->_currentText][$this->_currentLang]
      = $this->_currentPCdata;
      $this->_countTexts++;
      $this->_currentPCdata = "";
    }
    else if (substr($name, 0, $this->_nsLength) != $this->_nsUpper ) {
      // Literal element: copy as is
      $tag = strToLower($name);
      $this->_currentPCdata .= "</$tag>";
    }

  }

  function pcdataHandling ($parser, $pcdata)
  {
    // Put back the comments
    $pcdata = str_replace ("@BeginComment", "<!--", $pcdata);
    $pcdata = str_replace ("@EndComment", "-->",  $pcdata);

    $this->_currentPCdata .=  str_replace ("&", "&amp;", $pcdata);
    // Nothing to do
  }


  // Parse method
  function parse($file)
  {
    // Open the XML file
    if ( !($f = fopen($file, "r")))
    {
      $this->error ("ERROR: Unable to open file: $file");
      return;
    }

    // Scan the document
    while ($data = fread($f, 100000))
    {
      // Take Care: we want to preserve the comments!
      $data = str_replace ("<!--", "@BeginComment", $data);
      $data = str_replace ("-->", "@EndComment", $data);
      if (!xml_parse($this->parser, $data, feof($f)))
      {
        $this->error (" line " . xml_get_current_line_number($this->parser)
        . " of $file:"
        . xml_error_string(xml_get_error_code($this->parser)));
        return;
      }
    }
    fclose ($f);
  }

  // Destructor PHP 5 only
  /*  function __destruct()
  {
  xml_parser_free($this->parser);
  }*/

  function error ($message)
  {
    echo "<font color=red>Translation error: $message</font><p>";
  }

  /*********** Public part  *********************/

  public function nbTexts()
  {
    return $this->_countTexts;
  }

  public function targetLang()
  {
    return $this->_targetLang;
  }

  function getText($namespace, $code)
  {
    if (isSet($this->_translations[$namespace][$code])) {
      return $this->_translations[$namespace][$code];
    }
    else {
      return "Translation($namespace, $code) unknown";
    }
  }


  public function getTranslations()
  {
    return $this->_translations;
  }
}
