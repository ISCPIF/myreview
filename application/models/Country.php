<?php

/**
 * Model of the country table
 *
 */

class Country extends Zmax_Db_Table_Abstract
{    
  protected $_name = 'Country';
  protected $_primary = 'code';
  protected $_sequence = false;
}
?>