<?php

/**
 * This class represents a review object
 */

class ReviewMarkRow extends Zmax_Db_Table_Row_Abstract
{
  
  /**
   * Put the review mark information is the view
   */
  function putInView($view, $html=false)
  {
    parent::putInView($view, $html);
     
    // Show the label of the scale instead of the number
    // Add the reviewer expertise
    $name = "ReviewMark" . "->mark";
    if (isSet(Config::$Scale[$this->mark])) {
      $view->setVar($name, Config::$Scale[$this->mark]);
    }
    else {
      $view->setVar($name, "?");
    }

  }


  // End of the class
}

