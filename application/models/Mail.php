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

require_once("User.php");

/**
 * Model of the Mail objects
 */

class Mail
{
  // Enumerate the type of mail (batch yes/no, reviewer/author)
  const ALL_REVIEWERS = 1, ALL_AUTHORS=2, SOME_USER=3, 
  ALL_AUTHORS_ACCEPTED=5, PC_CHAIR=6, ALL_PARTICIPANTS=7;

  // Enumerate the mail format
  const FORMAT_TEXT="text/plain", FORMAT_HTML="text/html";

  private $_db, $_config, $_view, $_texts;
  private $_mailType, $_mailFormat;

  private $_from, $_to, $_subject, $_template;

  /**
   * Tells whether we send a CC of th email to the chair.
   */
  private $_copyToChair;

  function __construct($mailType, $subject, $scriptPath, $template=null)
  {
    // Keep the context objects
    $this->_db =  Zend_Db_Table::getDefaultAdapter();

    // get the config from the registry
    $registry = Zend_registry::getInstance();
    $this->_config = $registry->get("Config");

    // Get the Zmax context for translations
    $zmax_context = $registry->get("zmax_context");
    $this->_texts = $zmax_context->texts;

    $this->_mailType = $mailType;
    $this->_from = $this->_config['chairMail'];
    $this->_subject  = $subject;
    $this->_copyToChair = false;

    // Special case of a mail sent to the chair.
    // NOTE: the chair is assumed to be in User. Is it really safe ?
    if ($mailType == self::PC_CHAIR) {
      $this->setTo($this->_config->chairMail);
      $this->_mailType = self::SOME_USER;
    }

    // Default format: text
    $this->_mailFormat = self::FORMAT_TEXT;

    // Create a template engine to instantiate template mails
    $this->_view = new Zmax_View_Phplib();
    $this->_view->setPath ($scriptPath);

    // Put configuration information in the view (always useful)
    $this->_config->putInView($this->_view);
     
    // Record the template
    if ($template != null)
    $this->setTemplate ($template);

    // Put config information in the view: always useful
    $this->_config->putInView ($this->_view);
  }

  /**
   * Set the mail template, put it in the view
   * @param $template A character string containing the template
   */
  function setTemplate ($template)
  {
    $this->_template  = $this->br2nl($template);
    // Put the mail template in the view
    $this->_view->setVar("template", $template);
  }


  /**
   * Load a template from the zma_text table. NB: all the mails
   * should be in the 'mail' namespace
   * @param $lang The lang code
   * @param $mailId The code of the mail
   */

  function loadTemplate ($lang, $mailId)
  {
    // We check that the mail does exists in zmax_text
    if (!$this->_texts->exist ($lang, "mail", $mailId)) {
      $message = "Unable to find mail '$mailId' in language '$lang'";
      throw new Zmax_Exception ($message);
    }

    $this->setTemplate($this->_texts->mail->get($mailId));
  }

  /**
   * Accessors
   * @author philipperigaux
   *
   */
  function setTo($to) {$this->_to = $to;}
  function setFormat($format) {$this->_mailFormat = $format;}
  function setCopyToChair($bool) { $this->_copyToChair = $bool;}

  function getTo() {return $this->_to;}
  function getFrom() {return $this->_from;}
  function getSubject() {return $this->_subject;}
  function getTemplate() {return $this->_template;}
  function getEngine() {return $this->_view;}

  /**
   * Get an instantiated message
   */

  function getMessage()
  {
    // Look at the mail type
    if ($this->_mailType == Mail::SOME_USER) {
      $user = new User();
      $userRow = $user->findByEmail($this->_to);
      if (!is_object($userRow)) {
        echo "Unknown user: " . $this->_to . "<br/>";
      }
      else {
        $userRow->putInView($this->_view);
      }
      $this->_view->assign("message", "template");
      return $this->_view->message;
    }
    else {
      throw new Exception ("Mail::get Message. Cannot create a multi-user message");
    }
  }

  /**
   * Send a mail (or several mails)
   * @author philipperigaux
   *
   */
  function send()
  {
    // Look at the mail type
    switch ($this->_mailType) {
      case Mail::SOME_USER:
        $user = new User();
        $userRow = $user->findByEmail($this->_to);
        if (!is_object($userRow)) {
          // echo "Unknown user: " . $this->_to . "<br/>";
        }
        else {
          $userRow->putInView($this->_view);
        }
        $this->_view->assign("message", "template");

        // Send the mail
        $this->sendMail ($this->_to, $this->_subject, $this->_view->message, $this->_from,
        $this->_from, $this->_from);
        break;

      case Mail::ALL_REVIEWERS: case Mail::ALL_AUTHORS: case Mail::ALL_PARTICIPANTS:

        // Determine the role from the mail type
        if ($this->_mailType == Mail::ALL_REVIEWERS) {
          $role = "%R%";
        }
        else if ($this->_mailType == Mail::ALL_AUTHORS) {
          $role = "%A%";
        }
        else if ($this->_mailType == Mail::ALL_PARTICIPANTS) {
          $role = "%P%";
        }

        $user = new User();
        $userRows = $user->fetchAll("roles LIKE '$role' ");
        foreach ($userRows as $userRow) {
          // Instanciate all variables present in reviewers messages
          $userRow->putInView($this->_view);
           
          // instantiate the message
          $this->_view->assign("message", "template");
          $this->sendMail ($userRow->email, $this->_subject, $this->_view->message,
          $this->_from, $this->_from);
        }
        break;

        case Mail::ALL_AUTHORS_ACCEPTED:
        // Loop on the contact authors of accepted papers,
        // instanciate and send the mail
        $qPapers="SELECT p.* FROM PaperStatus s, Paper p ".
       	  "WHERE p.status=s.id and cameraReadyRequired='Y' ";
        $rPapers = $this->_db->query ($qPapers);
        while ($paper =  $rPapers->fetch (Zend_Db::FETCH_OBJ)) {
          $subjectWithID = "Submission " . $paper->id . "-- " . $this->_subject ;
          $to = $paper->emailContact;
          $this->_view->assign("message", "template");
          // Send the free mail text
          $this->sendMail ($to,  $subjectWithID, $this->_view->message,
          $this->_from, $this->_from, $this->_config->chairMail);
        }
        break;
         
      default:
        echo "Send mail: INVALID SEND MODE. CANNOT CONTINUE <br/>";
        exit;
    }
  }

  // Encapsulate the mail function
  function sendMail ($to, $subject, $mail, $from="", $replyTo="", $cc="" /* Deprecated */)
  {

   // echo "Send a mail to $to from $from. Subject: $subject <br/>Text: <pre>$mail</pre>";
    // return;

    // Seems that there is an issue with the line ending character. Either
    // we use \r\n or \n. \n seems to work better ....

    // Construct the header. NB: always sent with UTF 8 encoding
    $header = "Content-type: " . $this->_mailFormat . "; charset=UTF-8\n";
    if (!empty($from)) $header .= "From: $from\n";
    
    // Check whether we must send a copy to the chair
    if ($this->_copyToChair) {
         $header .= "Cc: " . $this->_config->chairMail . "\n";
    }
     if (!empty($replyTo)) $header .= "Reply-to: $replyTo\n";

    // Use the standard mail function.
    // NB: seems that we need to replace \r\n with \n to avoid double CR
    $mail = str_replace (chr(10), '', $mail);

    // Sometimes the -f option does not work
    mail ($to,  $this->_config['confAcronym'] . " -- " . $subject, $mail, $header, "-f $from");
  }
   
  /**
   * Export a mail (can be used to check
   */
  function exportMail($mimeType, $content)
  {
    $type = "application/octet-stream";
    header("Content-disposition: attachment; filename=mail-myreview");
    header("Content-Type: application/force-download");
    header("Content-Transfer-Encoding: $mimeType\n");
    header("Content-Length: ".strlen($content));
    header("Pragma: no-cache");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
    header("Expires: 0");
    echo $content;
  }

  /**
   * Put the mail information in a view.
   *
   * @param $view
   *
   */
  function putInView ($view)
  {
    if ($this->_mailType == Mail::SOME_USER) {
      $view->to = $this->_to;
    }
    else  if ($this->_mailType == Mail::ALL_REVIEWERS) {
      $view->to = $this->_texts->mail->to_all_reviewers;
    }
    else  if ($this->_mailType == Mail::ALL_AUTHORS) {
      $view->to = $this->_texts->mail->to_all_authors;
    }
    else  if ($this->_mailType == Mail::ALL_PARTICIPANTS) {
      $view->to = $this->_texts->mail->to_all_participants;
    }

    $view->from = $this->_from;
    $view->mailType = $this->_mailType;
    $view->mailFormat = $this->_mailFormat;
    $view->subject = $this->getSubject();
    //   $view->template = htmlEntities($this->getTemplate());
    $view->template = htmlSpecialchars($this->getTemplate());
  }


  /**
   * Inverse de nl2br
   * @param $foo
   * @return unknown_type
   */
  function br2nl($foo) {
    return preg_replace("/\<br\s*\/?\>/i", "\n", $foo
    );
  }
}
