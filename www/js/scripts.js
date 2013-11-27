

function searchUser(search, url){
	var params = "/search/" + search + "*";
	var div    = "result_search_user";
	
	dojo.byId(div).innerHTML = '<img src="/images/ajax-middle.gif" alt="" title="" />';
	
	if(window.XMLHttpRequest){ // FIREFOX
		xhr_object = new XMLHttpRequest();
	}
    else if(window.ActiveXObject){ // IE
		xhr_object = new ActiveXObject("Microsoft.XMLHTTP");
	}
    else{
		return(false);
	}
	
	xhr_object.open("GET", url+params, true);
	xhr_object.send(null);
	xhr_object.onreadystatechange = function(){
		if (xhr_object.readyState == 4){
			dojo.byId(div).style.display = '';
			dojo.byId(div).innerHTML = xhr_object.responseText;
		}
	}
}

function affectUser(sgi, lastname, firstname, email){
	dojo.byId('user_id').value = sgi;
	dojo.byId('user_lname').value = lastname;
	dojo.byId('user_fname').value = firstname;
	dojo.byId('user_email').value = email;
}