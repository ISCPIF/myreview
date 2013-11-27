<?php
class Zend_View_Helper_BaseUrl
{
  function baseUrl()
  {
    $fc = Zmax_Controller_Front::getInstance();
    return $fc->getBaseUrl();
  }
}
