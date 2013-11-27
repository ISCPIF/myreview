<?php

/**
 * Model of the Message table
 *
 */

class Message extends Zmax_Db_Table_Abstract
{

  protected $_name = 'Message';
  protected $_primary = 'id';
  protected $_sequence = true;

  // Define the references
  protected $_referenceMap = array (
    "Paper" => array (
        "columns" => 'id_paper', // The foreign key name
        "refTableClass" => "Paper", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
  ),
    "User" => array (
        "columns" => 'id_user', // The foreign key name
        "refTableClass" => "User", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
  ),
  "Message" => array (
        "columns" => 'id_parent', // The foreign key name
        "refTableClass" => "Message", // The foreign table name
        "refColumns" => "id" // The primary key referred to 
  )
  );

  /**
   *  Recursive function to display the current message and its descendants
   */

  static function display ($idPaper, $idParent, $view)
  {
    $messageTbl = new Message();
    $tree = "";

    $messages = $messageTbl->fetchAll ("id_paper='$idPaper' and id_parent = '$idParent'", "date");
    foreach ($messages as $message) {
      $message->putInView($view);
      $user = $message->findParentUser();
      $user->putInView($view);

      $currentVar = "message-{$message->id}";
      $view->assign ($currentVar, "message");

      // Appel récursif
      $children = self::display($idPaper, $message->id, $view);
      if (empty($children)) {
        $tree .= $view->getVar($currentVar);
      }
      else {
        $tree .= $view->getVar($currentVar) . "\n<ul>$children</ul>";
      }
    }
    return $tree;
  }

}
?>