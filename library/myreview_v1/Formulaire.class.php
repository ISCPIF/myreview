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
 

// Classe g�rant les formulaires

// On a besoin d'instancier des objets Tableau
require_once ("Tableau.class.php");
  
// D�but de la classe
class Formulaire
{
  
const  INSERTION = "insert"; 
const  MAJ =  "maj"; 

  // ----   Partie priv�e : les propri�t�s et les constantes

  // Propri�t�s de la balise <form>
  var $methode, $action, $nom, $transfertFichier=FALSE;

  // Propri�t�s de pr�sentation
  var  $orientation="", $centre=TRUE, $classeCSS, $tableau, $title,
    $nbl_horizontales;

  // Propri�t�s stockant les composants du formulaire
  var $composants=array(), $nbComposants=0;

  // Constructeur de la classe
  function Formulaire ($methode="POST", 
		       $action="",
		       $centre=true,
		       $classe="Form", $nom="Form")
  {
    // Initialisation des propri�t�s de l'objet avec les param�tres
    $this->methode = $methode;
    $this->action = $action;
    $this->classeCSS = $classe;
    $this->nom = $nom;
    $this->centre = $centre;
    $this->title = "";
  }

  // ----   Partie priv�e : les m�thodes 


  // M�thode pour cr�er un champ input g�n�ral
   function champinput ($type, $nom, $val, $taille, $tailleMax)
  {
    // Attention aux probl�mes d'affichage
    $val = htmlSpecialChars($val);

    // Cr�ation et renvoi de la cha�ne de caract�res
    return "<input type='$type' name=\"$nom\" "
          . "value=\"$val\" SIZE='$taille' MAXLENGTH='$tailleMax'>\n";
  }

  // Champ de type texte
   function champTEXTAREA ($nom, $val, $lig, $col)
  {
    return "<TEXTAREA name=\"$nom\" ROWS='$lig' "
      . "COLS='$col'>$val</TEXTAREA>\n";
  }

  // Champ pour s�lectionner dans une liste
   function  champSELECT ($nom, $liste, $defaut, $taille=1)
  {
    $s = "<SELECT name=\"$nom\" SIZE='$taille'>\n";
    while (list ($val, $libelle) = each ($liste))
      {
	// Attention aux probl�mes d'affichage
	$val = htmlSpecialChars($val);
	$defaut = htmlSpecialChars($defaut);

        if ($val != $defaut)
	  $s .=  "<OPTION value=\"$val\">$libelle</OPTION>\n";
        else
	  $s .= "<OPTION value=\"$val\" SELECTED>$libelle</OPTION>\n";
      }
    return $s . "</SELECT>\n";
  }

  // Champ CHECKBOX ou RADIO
   function  champBUTTONS ($pType, $pNom, $pListe, $pDefaut, $params)
  {
    if ($pType == "CHECKBOX") $length = $params["LENGTH"];
    else $length = -1;

    // Toujours afficher dans une table
    $libelles=$champs="";
    $nbChoix = 0;
    $result = "<table BORDER=0 CELLSPACING=5 CELLPADDING=2>\n"; 
    while (list ($val, $libelle) = each ($pListe))
      {
	$libelles .= "<td><B>$libelle</B></td>";
	$checked = " ";
	if (!is_array($pDefaut))
	  {
	    if ($val == $pDefaut) $checked = "CHECKED";
	$champs .= "<td><input type='$pType' "
	  . "name=\"$pNom\" value=\"$val\" "
	  . " $checked> </td>\n";//adyilie: moved and changed from below
	  }
	else
	  {
	    if (is_int(strpos($pNom, "[]"))) {
		    $lNom=$pNom;//adyilie: inserted, radio buttons are arrays with indices, checkboxes are not
		} else $lNom=$pNom."[$nbChoix]";
	    if (isSet($pDefaut[$val])) $checked = "CHECKED";
	$champs .= "<td><input type='$pType' "
	  . "name=\"$lNom\" value=\"$val\" "
	  . " $checked> </td>\n";//adyilie: moved and changed from below
	  }

	$nbChoix++;

	// Eventuellement on place plusieurs lignes dans la table
	if ($pType == "CHECKBOX" and $length == $nbChoix)
	  {
	    $result .= "<tr>" . $libelles . "</tr><tr>"
	      . $champs . "</tr>\n";
	    $libelles = $champs = "";
	    $nbChoix = 0;
	  }
      }

    if (!empty($champs))
      return  $result . "<tr>" . $libelles .  "</tr>\n<tr>" . $champs 
	. "</tr></table>";
    else return $result . "</table>";
  }

   function champPlain ($pLibelle, $pValeur)
   {
     $this->champLibelle ($pLibelle, "", $pValeur, "PLAIN");
   }

  // Champ de formulaire
   function champForm ($type, $nom, $val, $params, $liste=array())
  {
    // Action selon le type
    switch ($type)
      {
        case "PLAIN":
	  $champ = $val;
	break;

      case "TEXT": case "PASSWORD": case "SUBMIT": case "RESET": 
      case "FILE": case "HIDDEN":
	// Extraction des param�tres de la liste
	if (isSet($params['SIZE']))
	  $taille = $params["SIZE"];
	else  $taille = 0;
	if (isSet($params['MAXLENGTH']) and $params['MAXLENGTH']!=0)
	  $tailleMax = $params['MAXLENGTH'];
	else $tailleMax = $taille;

	// Appel de la m�thode champinput
	$champ = $this->champinput ($type, $nom, $val, $taille, $tailleMax);
	// Si c'est un transfert de fichier: s'en souvenir
	if ($type == "FILE") $this->transfertFichier=TRUE;
	break;

      case "TEXTAREA": 
	$lig = $params["ROWS"]; $col = $params["COLS"];
	// Appel de la m�thode champTEXTAREA de l'objet courant
	$champ = $this->champTEXTAREA ($nom, $val, $lig, $col);
	break;
    
      case "SELECT":
	$taille = $params["SIZE"];
	// Appel de la m�thode champSELECT de l'objet courant
	$champ = $this->champSelect ($nom, $liste, $val, $taille);
	break;

      case "CHECKBOX": 
	$champ = $this->champBUTTONS ($type, $nom, $liste, $val, $params);
	break;

      case "RADIO":
	// Appel de la m�thode champBUTTONS de l'objet courant
	$champ = $this->champBUTTONS ($type, $nom, $liste, $val, array());
	break;

      default: echo "<B>ERREUR: $type est un type inconnu</B>\n";
	break;
      }
    return $champ;
  }

  // Cr�ation d'un champ avec son libell�
   function champLibelle ($libelle, $nom, $val,  $type,
			 $params=array(),  $liste=array())
  {
    // On met le libell� en gras
    $libelle = "<B>$libelle</B>";

    if ($this->orientation != HORIZONTAL) {
      // Cr�ation de la balise HTML
      $champHTML = $this->champForm ($type, $nom, $val, $params, $liste);    
      // Stockage du libell� et de la balise dans le contenu
      $this->composants[$this->nbComposants] = array("type" => "CHAMP",
						     "libelle" => $libelle,
						     "champ" => $champHTML);
      // Renvoi de l'identifiant de la ligne, et incr�mentation
      return $this->nbComposants++;
    }
    else {
      // On place dans le tableau horizontal une colonne avec les champs
      $id_comp = ++$this->nbComposants;
      for ($i=0; $i < $this->nbl_horizontales;  $i++) {
	if (is_array($val) and isSet($val[$i])) 
	  $val_def = $val[$i];
	else
	  $val_def = "";
	$champ = $this->champForm ($type, $nom, $val_def, 
				       $params, $liste);    
	$this->tableau->ajoutEntete(2, $id_comp, $libelle);
	$this->tableau->ajoutValeur("ligne$i", $id_comp, $champ);
      }
    }
  }

  /* **************** methodES PUBLIQUES ********************/

  function setTitle ($title)
  {
    $this->title=$title;
  }

  // M�thode permettant de r�cup�rer un champ par son identifiant
   function getChamp($idComposant)
  {
    // On r�cup�re le composant, on extrait le champ. Manque les tests...
    $composant = $this->composants[$idComposant];
    return $composant['champ'];
  }

  // Cr�ation d'un champ et de son libell�: 
  // appel de la m�thode g�n�rale, avec juste les param�tres n�cessaires
   function champTexte ($libelle, $nom, $val, $taille, $tailleMax=0)
  { 
    return $this->champLibelle ($libelle, $nom, $val, 
			 "TEXT", array ("SIZE"=>$taille,
					"MAXLENGTH"=>$tailleMax));
  }

   function champMotDePasse ($pLibelle, $pNom, $pVal, $pTaille, 
				   $pTailleMax=0)
  { 
    return $this->champLibelle ($pLibelle, $pNom, $pVal, "PASSWORD", 
			 array ("SIZE"=>$pTaille, "MAXLENGTH"=>$pTailleMax));
  }

   function champRadio ($libelle, $nom, $val, $liste)
  {
    return $this->champLibelle ($libelle, $nom, $val, "RADIO", 
				array (), $liste);
  }

   function champCheckBox ($pLibelle, $pNom, $pVal, $pListe, $length=-1)
  {
    return $this->champLibelle ($pLibelle, $pNom, $pVal, "CHECKBOX", 
			 array ("LENGTH"=>$length), $pListe);
  }

   function champListe ($pLibelle, $pNom, $pVal, $pTaille, $pListe)
  {
    return $this->champLibelle ($pLibelle, $pNom, $pVal, "SELECT",
			 array("SIZE"=>$pTaille), $pListe);       
  }

   function champFenetre ($libelle, $nom, $val, $lig, $col)
  {
    return $this->champLibelle ($libelle, $nom, $val, "TEXTAREA",
			 array ("ROWS"=>$lig,"COLS"=>$col));       
  }

   function champValider ($pLibelle, $pNom)
  {
    return $this->champLibelle (" ", $pNom, $pLibelle, "SUBMIT");
  }

   function champAnnuler ($pLibelle, $pNom)
  {
    return $this->champLibelle (" ", $pNom, $pLibelle, "RESET");
  }

   function champFichier ($pLibelle, $pNom, $pTaille)
  {
    return $this->champLibelle ($pLibelle, $pNom, "", "FILE",
			 array ("SIZE"=>$pTaille));
  }

   function champCache ($nom, $valeur)
  {
    return $this->champLibelle ("", $nom, $valeur, "HIDDEN");
  }

  // Ajout d'un texte quelconque 
   function ajoutTexte ($texte)
  {
    // On ajoute un �l�ment dans le tableau $composants
    $this->composants[$this->nbComposants] = array("type"=>"TEXTE",
					    "texte" => $texte);
    // Renvoi de l'identifiant de la ligne, et incr�mentation
    return $this->nbComposants++;
  }

  // D�but d'une table, mode horizontal ou vertical
   function debutTable ($orientation=VERTICAL, 
			$attributs=array(),$nbLignes=1, $title="")
  {
    // On instancie un objet pour cr�er ce tableau HTML
    $tableau = new Tableau (2, $attributs);
    $this->orientation = $orientation;
    $this->nbl_horizontales = $nbLignes;

    if (!empty($title))
      $tableau->setLegende ($title);

    // Jamais d'affichage de l'ent�te des lignes
    $tableau->setAfficheEntete (1, FALSE);

    // Action selon l'orientation
    if ($orientation == VERTICAL) {
      // Pas d'affichage de l'ent�te des colonnes
      $tableau->setAfficheEntete (2, FALSE);
      
      // On cr�e un composant dans lequel on place le tableau
      $this->composants[$this->nbComposants] =  
	array("type"=>"DEBUTtable",
	      "orientation"=> $orientation,
	      "tableau"=> $tableau);
      
      // Renvoi de l'identifiant de la ligne, et incr�mentation
      return $this->nbComposants++;
    }
    else
      {
	$this->tableau = $tableau;
      }
  }

  // Fin d'une table
  function finTable ()
  {
    if ($this->orientation == HORIZONTAL) {
      $this->orientation = "";
      $this->champPLAIN ("", $this->tableau->tableauHTML());
      $this->tableau = "";
    }
    else
      {
      // Insertion d'une ligne marquant la fin de la table
      $this->composants[$this->nbComposants++] = array("type"=>"FINtable");
      $this->orientation = "";
      }
  }

  // Fin du formulaire, avec affichage �ventuel.
  // NB: on peut faire une version qui effectue directement les 'echo',
  // ce qui �vite de transmettre une grosse cha�ne de caract�res en retour

   function formulaireHTML ()
  {
    // On met un attribut ENCtype si on transf�re un fichier
    if ($this->transfertFichier) $encType = "enctype='multipart/form-data'";
    else                         $encType="";

    $formulaire = "";
    // Maintenant, on parcourt les composants et on cr�e le HTML
    foreach ($this->composants as $idComposant => $description)
      {
	// Agissons selon le type de la ligne
	switch ($description["type"])
	  {
	  case "CHAMP":
	  // C'est un champ de formulaire
	    $libelle = $description['libelle'];
	    $champ = $description['champ'];
	    if ($this->orientation == VERTICAL)
	      { 
		$this->tableau->ajoutValeur($idComposant, "libelle", $libelle);
		$this->tableau->ajoutValeur($idComposant, "champ", $champ);
	      }
	    else if ($this->orientation == HORIZONTAL)
	      {
		;
		$this->tableau->ajoutEntete(2, $idComposant, $libelle);
		$this->tableau->ajoutValeur("ligne", $idComposant, $champ);
	      }
	    else
	      $formulaire .= $libelle . $champ;
	    break;

	  case "TEXTE":
	  // C'est un texte simple � ins�rer
	    $formulaire .= $description['texte'];
	    break;
	    
	  case "DEBUTtable":
	    // C'est le d�but d'un tableau HTML
	    $this->orientation = $description['orientation'];
	    $this->tableau = $description['tableau'];
	    break;
	    
	  case "FINtable":
	    // C'est la fin d'un tableau HTML
	    $formulaire .= $this->tableau->tableauHTML();
	    $this->orientation="";
	    break;

	  default: // Ne devrait jamais arriver...
	    echo "<P>ERREUR CLASSE formULAIRE!!<P>";
	  }
      }

    // Encadrement du formulaire par les balises
    $formulaire = "\n<form  method='$this->methode' " . $encType
              . "action='$this->action' name='$this->nom'>" 
              . $formulaire . "</form>";

    // Il faut �ventuellement le centrer
    if ($this->centre) $formulaire = "<CENTER>$formulaire</CENTER>\n";;

    // On retourne la cha�ne de caract�res contenant le formulaire
    return $formulaire;
  }

  function fin($bool)
  {
    return $this->formulaireHTML();
  }
  // Fin de la classe
}
?>