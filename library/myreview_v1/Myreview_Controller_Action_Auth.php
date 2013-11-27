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
 * Same as Myreview_Controller_Action, but protected by a pasword.
 *
 */

class  Myreview_Controller_Action_Auth extends Myreview_Controller_Action
{
  function init()
  {
    // Same as the parent
    parent::init();

    // Obtain the current request
    $base_url = $this->view->base_url;
    $req = $this->getRequest();
    $url = "$base_url/" . $req->getModuleName() . "/" . $req->getControllerName()  .
         "/" . $req->getActionName();

    // Keep the Admin local menu
    $this->view->admin_local_menu =   '<p>
[Local menu: <a href="{base_url}/admin/config">{def.configuration_tasks}</a> |
				<a href="{base_url}/admin/chair">{def.manage_submissions}</a> |
			<a href="{base_url}/admin/program">{def.program_registrations}</a> 
]
</p>';
    $this->view->assign ("admin_local_menu", "admin_local_menu"); 
  }

  function preDispatch()
  {
    // For all actions of this controller: check that a session exists
    if (!$this->checkSession()) {
  //    $redirect =  $this->view->base_url . "/index/login";
    // Seems that there is no need to put the base_url in the redirect path
      $redirect =  "/index/login";
      //  echo "No session<br/>";
 //     $this->_forward("login", "index", "default");
      $this->_redirect($redirect);
    }
  }


}