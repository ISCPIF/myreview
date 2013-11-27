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
 * The administrator index controller
 *
 * This is a basic example whose purpose is simply
 * to show how to organize a module.
 * @package    Index
 */

class Admin_IndexController extends Myreview_Controller_Action_Auth
{
  function init ()
  {
    // Call the parent
    parent::init();

    // Load the texts of administration tasks
    $this->zmax_context->texts->addTranslation ($this->zmax_context->db,
    $this->zmax_context->locale, array ("namespaces" => array('admin')));
  }
  /**
   * This controller contains admin function: check the role of the connected user
   */
  function preDispatch()
  {
    // The parent preDispatch check that a user is connected
    parent::preDispatch();

    // Now, check the role
    if (!$this->user->isAdmin()) {
      // Forward to the "access denied" action
      $this->_forward ("accessdenied", "index", "default");
    }
  }
  
  function indexAction()
  {
    $this->_forward ("index", "config", "admin");
  }


}