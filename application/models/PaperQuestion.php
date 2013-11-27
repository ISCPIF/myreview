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
 * Model of the PaperQuestion table
 *
 */

class PaperQuestion extends Zmax_Db_Table_Abstract
{
  
  protected $_name = 'PaperQuestion';
  protected $_primary = 'id';
  protected $_sequence = true;

}
?>