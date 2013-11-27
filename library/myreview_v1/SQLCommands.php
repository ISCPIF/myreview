<?php
/**********************************************
 The MyReview system for web-based conference management

 Copyright (C) 2003-2006 Philippe Rigaux
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
 ************************************************/

// Insert reviewers
function SQLReview ($idPaper, $tabIds, $db)
{
  foreach ($tabIds as $id_user)
  {
    // Never delete! Just insert
       if (!GetReview($idPaper, $id_user, $db)) {
        $query = "INSERT INTO Review (idPaper, id_user) VALUES ('$idPaper', '$id_user')";
        $db->execRequete ($query);
      }
      else	      // The review exists: do nothing
      ;
    }
}

function SQLMessage ($message, $db)
{
  // Insertion
  if (!isSet($message['idParent']))
  $idParent = 0;
  else
  $idParent = $message['idParent'];

  $idPaper = $message['idPaper'];
  $mess = $db->prepareString($message['message']);
  $emailReviewer = $db->prepareString($message['emailReviewer']);

  $query = "INSERT INTO Message (idParent, idPaper, message, date, "
  . "emailReviewer) "
  . " VALUES ('$idParent', '$idPaper', '$mess', NOW(), '$emailReviewer')";

  $db->execRequete ($query);
}


// Assign reviewers to paper
function InsertReviewers ($idPaper, $tabMails, $db)
{
  // Is there something to check ?

  // Access to the database
  SQLReview ($idPaper, $tabMails, $db);

  return ""; 
} 

// Update reviews
function UpdateReview ($review, $db)
{
  // Check the review?

  // Access to the database
  SQLUpdateReview ($idPaper, $tabMails, $db);
} 
?>