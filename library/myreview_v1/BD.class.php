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
 
 
  // This class encapsulates the MySQL PHP API
  class BD
  {
    // ----   Private part: properties
 
    var $connexion, $erreurRencontree=0, $base;

    // Object constructor

    function BD ($login, $motDePasse, $base, $serveur)
    {
      // Connect to the server 
      $this->connexion = @mysql_pconnect ($serveur, $login, $motDePasse);
     mysql_query ("SET CHARACTER SET utf8", $this->connexion);
   
      if (!$this->connexion) 
       $this->message("Sorry, unable to connect to $serveur\n");

      // Connnect to the DB
      if (!@mysql_select_db ($base, $this->connexion)) 
      {
        $this->message ("Sorry, unable to access to the DB $base\n");
        $this->message ("<B>MySQL says: </B>" .
                             mysql_error($this->connexion));
        $this->erreurRencontree = 1;
      }

      $this->base = $base;
      // End of constructor
    }

    // ---- Private part: methods

    // Shows a message
    function message ($message)
    {
     // Just output an HTML message
     echo "<B>Error:</B> $message<BR>\n";
    }

    // ---- Public part

    // Execute a query
    function execRequete ($requete)
    {
      $resultat = mysql_query ($requete, $this->connexion);

      if (!$resultat)
      {  
       $this->message ("Problem when executing query: $requete");
       $this->message ("<B>MySQL says: </B>" .
                             mysql_error($this->connexion));
       $this->erreurRencontree = 1;
      }   
      return $resultat;
    }

    // Get the next object
    function objetSuivant ($resultat)
    {      return  mysql_fetch_object ($resultat);    } 

    // Get the next assoc. array
    function ligneSuivante ($resultat)
    {   return  mysql_fetch_assoc ($resultat);  }

    // Get the next array
    function tableauSuivant ($resultat)
    {   return  mysql_fetch_row ($resultat);  }

    // Check whether an error has been met
    function enErreur ()
    {  return  $this->erreurRencontree;   }

    // Get the id of the last inserted row
    function idDerniereLigne ()
    {  return  mysql_insert_id(); }

    // How many attributes in the result
    function nbrAttributs ($res)
      {  return  mysql_num_fields ($res); }

    // Get the name of an attribute
    function nomAttribut ($res, $position)
    {
      // Check position
      if ($position < 0 or $position >= $this->nbrAttributs($res))
      {
        $this->message ("No attribute at pos $position");
	return "Unknown";
      }
      else return  mysql_field_name ($res, $position);
    }
    
    // Get the schema of a table
    /*    function schemaTable ($tableName)
    {
      return mysql_list_fields ($this->$base, $tableName);
    }
    */

  // M�thode ajout�e: renvoie le sch�ma d'une table
   function schemaTable($nom_table)
  {
    // Recherche de la liste des attributs de la table
    $listeAttr = @mysql_list_fields($this->base, 
				    $nom_table, $this->connexion);
    
    if (!$listeAttr) echo ("Pb d'analyse de $nom_table"); 
    
    // Recherche des attributs et stockage dans le tableau
    for ($i = 0; $i < mysql_num_fields($listeAttr); $i++) {
	$nom =  mysql_field_name($listeAttr, $i);
	$schema[$nom]['longueur'] = mysql_field_len($listeAttr, $i);
	$schema[$nom]['type'] = mysql_field_type($listeAttr, $i);
	$schema[$nom]['cle_primaire'] = 
	  substr_count(mysql_field_flags($listeAttr, $i), "primary_key");
	$schema[$nom]['notNull'] = 
	  substr_count(mysql_field_flags($listeAttr, $i), "not_null");
      }
    return $schema; 
  }

    // Disconnect
    function quitter ()
    {      @mysql_close ($this->connexion);    }
 
    // Quotes escaping
    function prepareString($chaine)
    {return addSlashes ($chaine);  }
  
    // End of the class
 }
?>
