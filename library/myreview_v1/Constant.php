<?php 

// Some constants 
define ("HORIZONTAL", "H"); 
define ("VERTICAL", "V"); 
 
define ("INSERTION", "insert"); 
define ("MAJ", "maj"); 
 
// Format for date output: see the PHP documentation (Date() function) 
define ("OUTPUT_DATE_FORMAT", "F d, Y"); 
 
define ("MAX_AUTHORS", 8); 
 
// Codes for messages 
define ("PWD_REVIEWER", "1"); 
define ("REVIEWS_TO_REVIEWERS", "2"); 
define ("STATUS_TO_AUTHORS", "3"); 
define ("FREE_MAIL", "4"); 
define ("MAIL_SELECT_TOPICS", "5"); 
define ("MAIL_RATE_PAPERS", "6"); 
define ("MAIL_PARTICIPATE_FORUM", "7"); 

// Rating parameters 

// How many papers shown in one page of the forum? 
define ("SIZE_FORUM", 20); 
 

// Maximal capacity of PHP matching algorithm  
define ("MAX_PAPERS_IN_ROUND", 150); 
 
// How many authors shown simultaneously? 
define ("SIZE_AUTHORS_GROUP", 20); 
define ("MAX_RATING", 5); 
define ("RATE_DEFAULT_VALUE", 2); 

// How many registrations shown simultaneously? 
define ("SIZE_REGISTRATIONS_GROUP", 2); 
 
// Prefix of papers names 
define ("PAPER_PREFIX", "p"); 
 
// No reviewer 
define ("NOBODY", "Nobody"); 
 
// Status of papers 
define ("ACCEPT", 'A'); 
define ("REJECT", 'R'); 
 
// Selection of papers 
define ("NEUTRAL_CHOICE", "Any"); 
define ("SP_ANY_STATUS", "0"); 
define ("SP_ANY_CHOICE", "0"); 
define ("SP_NOT_YET_ASSIGNED", "999"); 
define ("SP_ABOVE_FILTER", "1"); 
define ("SP_BELOW_FILTER", "2"); 

 
// Paypal payment mode 
define ("PAYPAL", "1"); 
 
 
// Generic code for accepted papers (whatever the specific status) 
define ("CAMERA_READY_STATUS", 99999); 
 
?>