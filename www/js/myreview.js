
/**
 * This function is used to show/hide parts of a page
 * 
 * The input is the id of a div element. There must be a "display-divName" which
 * display the hide/show link.
 */

function toggle(divName) {
	var ele = document.getElementById(divName);
	var text = document.getElementById("display-" + divName);
	if(ele.style.display == "block") {
    		ele.style.display = "none";
		text.innerHTML = "show";
  	}
	else {
		ele.style.display = "block";
		text.innerHTML = "hide";
	}
}

/**
 * Adds the connected user to a list of authors
 */

function AddContactAuthor(id) {
	// Get the value of the connected user
	ca_last_name = document.getElementById("ca_last_name");
	ca_first_name = document.getElementById("ca_first_name");
	ca_affiliation = document.getElementById("ca_affiliation");
	ca_email = document.getElementById("ca_email");
	ca_country = document.getElementById("ca_country");
	// alert ("Value =" + ca_last_name.value);

	// Get the target elements
	author_first_name = document.getElementById("author_first_name_" + id);
	author_last_name = document.getElementById("author_last_name_" + id);
	author_email = document.getElementById("author_email_" + id);
	author_affiliation = document.getElementById("author_affiliation_" + id);
	author_contact = document.getElementById("author_contact_" + id);
	author_country = document.getElementById("author_country_" + id);

	if (author_first_name) {
		author_last_name.value = ca_last_name.value;
		author_first_name.value = ca_first_name.value;
		author_email.value = ca_email.value;
		author_affiliation.value = ca_affiliation.value;
		author_contact.checked = true;

		/* Scan all the option buttons of the country list */
		for ( var j = 0; j < author_country.options.length; j++) {
			el = ca_country.value;

			if (el == author_country.options[j].value) {
					author_country.options.selectedIndex = j;
			}
		}
	}
	return;
}

/**
 * Toggle the status of selected papers
 */

function TogglePaperStatus (status)
{
  form = document.forms.PaperList;

  /* Scan all the radio buttons of the form */
  for (var j=0; j < form.elements.length; j++)
  {
   el = form.elements[j];  
   if (el.type=='radio')
   {
    // alert ("Value = " + el.value + " Status = " + status);
    
    if (el.value == status) el.checked = true;
   }
 }
}

/**
 * Count the words in a form
 * 
 */
function cnt(inputId, showId){
	// alert ("Id =" + id);
	x = document.getElementById(showId);
	
	w = document.getElementById(inputId);
	
	if (w == null) {
		alert ("Cannot find" + inputId);
	    return;
    }
	var y=w.value;
	var r = 0;
	a=y.replace(/\s/g,' ');
	a=a.split(' ');
	for (z=0; z<a.length; z++) {if (a[z].length > 0) r++;}
	x.innerHTML = "(" + r + " words)";
	return r;
	} 

/**
 * Sum up the words in the abstract fields
 * 
 */

function trim (myString)
{
return myString.replace(/^\s+/g,'').replace(/\s+$/g,'');
} 

function sumWords (){
	x = document.getElementById('list_abstract_ids');
   
	var ids = x.value.split(';');
	var r = 0;
	for ( var i = 0; i < ids.length; i++) {
		textarea_id = 'abstract_' + trim(ids[i]);
		show_id = 'count_words_' + trim(ids[i]);
		// Count the number of words
		r += cnt (textarea_id, show_id);
	}

	w = document.getElementById('sum_words');
	w.innerHTML = "=>" + r + " words";
	} 

/**
 * Check that a form field does not contain more than xxx chars.
 * 
 */

function checkFormFieldSize(obj){
  var mlength=obj.getAttribute? parseInt(obj.getAttribute("maxlength")) : ""
  if (obj.getAttribute && obj.value.length>mlength)
    obj.value=obj.value.substring(0,mlength)
}
