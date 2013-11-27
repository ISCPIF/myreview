<?php

/************************************************************************
 The MyReview system for web-based conference management

 Copyright (C) 2003-2009 Philippe Rigaux
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
 *****************************************************************************/

require_once ("ReviewMarkRow.php");

/**
 * Model of the ReviewMark table
 *
 */

class ReviewMark extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'ReviewMark';
  protected $_primary = array('idPaper', 'id_user', 'idCriteria');
  protected $_sequence = false;
  protected $_rowClass = "ReviewMarkRow";

  // Define the references
  protected $_referenceMap = array (
    "Review" => array (
        "columns" => array('idPaper', 'id_user'), // The foreign key name
        "refTableClass" => "Review", // The foreign table name
        "refColumns" => array("idPaper", "id_user") // The primary key referred to 
     ),
    "Criteria" => array (
        "columns" => 'idCriteria', // The foreign key name
        "refTableClass" => "Criteria", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
      
   );
}
?>