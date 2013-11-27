<?php

/**
 * This class represents a session object
 */

class SessionRow extends Zmax_Db_Table_Row_Abstract
{

 /**
  * Check that a session is valid
   */

  function isValid ()
  {
    // Check that the session duration is not over

    $now = date ("U");
    if ($this->tempsLimite > $now) {
      // Remove the session
      session_destroy();

      $this->delete();
      
      return false;
    }
    else {
      // Allright
      return true;
    }
  }
  

  // End of the class
}

