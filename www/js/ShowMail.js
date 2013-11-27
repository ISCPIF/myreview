function ShowMail(mail)
{
  options = "width=300,height=200";
  fenetre = window.open('','Mail',options);

  fenetre.document.open();
  manuel = "<BODY bgcolor='white'>" 
           + mail + " !</BODY>";
  fenetre.document.write(manuel);
  fenetre.document.close();
}
