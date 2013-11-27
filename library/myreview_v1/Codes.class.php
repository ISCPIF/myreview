<?php
/**********************************************
   The MyReview system for web-based conference management
 
   Copyright (C) 2003-2006 Philippe Rigaux
   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation;
 
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
 
   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
************************************************/
 
// Extracts codes from an XML file

class Codes
{
  // The SAX parser
  var $parser;
  // The array that stores the codes
  var $codes;
  // Global SAX variables
  var $current_element, $current_pcdata;

  // Constructor
  function Codes ($file_name="Codes.xml")
  {
    // Instanciate the SAX parser
    $this->parser = xml_parser_create();

    // Triggers = methods
    xml_set_object($this->parser, $this);

    // Put all tags and attributes in uppercase
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, true);

    // Assign triggers
    xml_set_element_handler ($this->parser, "startElement", "endElement");
    xml_set_character_data_handler ($this->parser, "pcdataHandling");

    // Initialize the array
    $this->codes = array();
    
    // Parse
    $this->parse($file_name);
  } 

  // Triggers
   function startElement ($parser, $name, $attrs)
  {
    if ($name == "VALUE")  {
      $this->codes[$this->current_element][$attrs['VALUE']] = $attrs['NAME']; 
    }
    else if ($name == "CODE")
      {
	$this->current_element = $attrs['NAME'];
      }
  }

   function endElement ($parser, $name)
  {
    // Nothing to do!
  }

   function pcdataHandling ($parser, $pcdata)
  {
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
    while ($data = fread($f, 4096)) 
      {
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
    echo "<font color=red>SAX ERROR: $message</font><p>";
  }

  /*********** Public part  *********************/

   function get($code_name)
  {
   if (isSet($this->codes[$code_name]))
      return $this->codes[$code_name];
    else
      return array("Unknown code $code_name" => "Unknown code $code_name");
  }

  function getValue($code_name, $val_name)
  {
    if (isSet($this->codes[$code_name][$val_name]))
      return $this->codes[$code_name][$val_name];
    else
      return "Code($code_name, $val_name) unknown";
  }
}
