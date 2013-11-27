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


/**
 * Model of the ReviewAnswer table
 *
 */

class ReviewAnswer extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'ReviewAnswer';
  protected $_primary = array('id_paper', 'id_user', 'id_question');
  protected $_sequence = false;

  // Define the references
  protected $_referenceMap = array (
    "Review" => array (
        "columns" => array('id_paper', 'id_user'), // The foreign key name
        "refTableClass" => "Review", // The foreign table name
        "refColumns" => array("idPaper", "id_user") // The primary key referred to 
     ),
    "ReviewQuestion" => array (
        "columns" => 'id_question', // The foreign key name
        "refTableClass" => "ReviewQuestion", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
     ),
   "RQChoice" => array (
        "columns" => 'id_answer', // The foreign key name
        "refTableClass" => "RQChoice", // The foreign table name
        "refColumns" => "id_choice" // The primary key referred to 
     ),
     
   );

}
?>