/* Pops up a window and outputs an HTML document  */

function ShowWindow(url)
{
   options = "width=600,height=400,resizable=yes,scrollbars=yes";
   // Open the window and output the HTML content
   w = window.open(url,'PaperInfos',options);
}

function ConfirmAction(message, url)
{
  if (confirm(message))
  {
	window.location = url;	
  } 
  else alert ('Cancelled');
}

