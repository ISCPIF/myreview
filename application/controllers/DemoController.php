<?php

class DemoController extends Myreview_Controller_Action
{
  /**
   * The default action. It just displays the home page
   *
   */

  function indexAction()
  {
    $this->_redirect ("http://myreview.lri.fr");
  }

}