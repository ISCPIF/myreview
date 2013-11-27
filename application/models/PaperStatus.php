<?php


/**
 * Model of the PaperStatus table
 *
 */

class PaperStatus extends Zmax_Db_Table_Abstract
{
  protected $_name = 'PaperStatus';
  protected $_primary = array('id');
  protected $_sequence = false;
  
  /**
   * Enumeration of the fixed values
   */
  const IN_SUBMISSION=1, IN_EVALUATION=2, IN_AUTHOR_FEEDBACK=3,
      IN_REVISION=4;
}
?>