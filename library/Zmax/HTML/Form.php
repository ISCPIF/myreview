<?php

/**
 * @category Zmax
 * @package    Zmax_Html
 * @subpackage Form
 * @copyright
 * @license
 * @version
 */

  
/**
 * Some constants 
 */ 
define ("HORIZONTAL", "H"); 
define ("VERTICAL", "V"); 

/**
  * Form generator.
  * 
  * This class is used in the Edit function.
  * DO NOT USE THIS CLASS IN A ZMAX APPLICATION. It will
  * probably be replaced by a more sophisticated and Zend-compliant
  * implementation later on.
  * 
  * @package    Zmax_Html
 * @subpackage Form
  *  @todo replace by the Zend standard class package
  *
 *
  */

class Zmax_HTML_Form
{
  // ----   Private part

  // Properties of the form tag
  var $methode, $action, $nom, $transfertFichier=FALSE;

  // Propri�t�s de pr�sentation
  var  $orientation="", $centre=TRUE, $classeCSS, $tableau, $title,
    $nbl_horizontales;

  // Propri�t�s stockant les composants du formulaire
  var $composants=array(), $nbComposants=0;

  // Constructeur de la classe
  function __construct ($methode="post", 
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

  // ----   Private part: methods


  // M�thode pour cr�er un champ INPUT g�n�ral
   function champINPUT ($type, $nom, $val, $taille, $tailleMax)
  {
    // Attention aux probl�mes d'affichage
    $val = htmlSpecialChars($val);

    // Cr�ation et renvoi de la cha�ne de caract�res
    $type = strtolower($type);
    
    $str_out = "<input type='$type' name=\"$nom\" id=\"$nom\" value=\"$val\" size='$taille' maxlength='$tailleMax'";
    if($type == 'button' || $type == 'submit'){$str_out .= " class=\"button\" ";}
    $str_out .= " />\n";
    return $str_out;
  }

  // Champ de type texte
   function champTEXTAREA ($nom, $val, $lig, $col)
  {
    return "<textarea name=\"$nom\" rows='$lig' "
      . "cols='$col'>$val</textarea>\n";
  }

  // Champ pour s�lectionner dans une liste
   function  champSELECT ($nom, $liste, $defaut, $taille=1)
  {
    $s = "<select name=\"$nom\" size='$taille'>\n";
    while (list ($val, $libelle) = each ($liste))
      {
	// Attention aux probl�mes d'affichage
	$val = htmlSpecialChars($val);
	$defaut = htmlSpecialChars($defaut);

        if ($val != $defaut)
	  $s .=  "<option value=\"$val\">$libelle</option>\n";
        else
	  $s .= "<option value=\"$val\" selected>$libelle</option>\n";
      }
    return $s . "</select>\n";
  }

  // Champ CHECKBOX ou RADIO
   function  champBUTTONS ($pType, $pNom, $pListe, $pDefaut, $params)
  {
	$html_type = strToLower($pType);
    if ($pType == "CHECKBOX") $length = $params["LENGTH"];
    else $length = -1;

    // Toujours afficher dans une table
    $libelles=$champs="";
    $nbChoix = 0;
    $result = "<table border='0' cellspacing='5' cellpadding='2'>\n"; 
    while (list ($val, $libelle) = each ($pListe))
      {
	$libelles .= "<td><b>$libelle</b></td>";
	$checked = " ";
	if (!is_array($pDefaut))
	  {
	    if ($val == $pDefaut) $checked = "checked=1";
	$champs .= "<td><input type='$html_type' "
	  . "name=\"$pNom\" value=\"$val\" "
	  . " $checked /> </TD>\n";//adyilie: moved and changed from below
	  }
	else
	  {
	    if (is_int(strpos($pNom, "[]"))) {
		    $lNom=$pNom;//adyilie: inserted, radio buttons are arrays with indices, checkboxes are not
		} else $lNom=$pNom."[$nbChoix]";
	    if (isSet($pDefaut[$val])) $checked = "checked=1";
	    $champs .= "<td><input type='$html_type' "
	    . "name=\"$lNom\" value=\"$val\" "
	    . " $checked /> </td>\n";//adyilie: moved and changed from below
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
      case "FILE": case "hidden":
	// Extraction des param�tres de la liste
	if (isSet($params['SIZE']))
	  $taille = $params["SIZE"];
	else  $taille = 0;
	if (isSet($params['MAXLENGTH']) and $params['MAXLENGTH']!=0)
	  $tailleMax = $params['MAXLENGTH'];
	else $tailleMax = $taille;

	// Appel de la m�thode champINPUT
	$champ = $this->champINPUT ($type, $nom, $val, $taille, $tailleMax);
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

      default: echo "<b>erreur: $type est un type inconnu</b>\n";
	break;
      }
    return $champ;
  }

  // Cr�ation d'un champ avec son libell�
   function champLibelle ($libelle, $nom, $val,  $type,
			 $params=array(),  $liste=array())
  {
    // On met le libell� en gras
    $libelle = "<b>$libelle</b>";

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
	if (isSet($val[$i])) 
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

  /* **************** METHODES PUBLIQUES ********************/

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
    return $this->champLibelle ("&nbsp;", $pNom, $pLibelle, "SUBMIT");
  }

   function champAnnuler ($pLibelle, $pNom)
  {
    return $this->champLibelle ("&nbsp;", $pNom, $pLibelle, "RESET");
  }

   function champFichier ($pLibelle, $pNom, $pTaille)
  {
    return $this->champLibelle ($pLibelle, $pNom, "", "FILE",
			 array ("SIZE"=>$pTaille));
  }

   function champCache ($nom, $valeur)
  {
    return $this->champLibelle ("&nbsp;", $nom, $valeur, "hidden");
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
    $tableau = new Zmax_HTML_Table (2, $attributs);
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
	array("type"=>"DEBUTTABLE",
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
      $this->composants[$this->nbComposants++] = array("type"=>"FINTABLE");
      $this->orientation = "";
      }
  }

  // Fin du formulaire, avec affichage �ventuel.
  // NB: on peut faire une version qui effectue directement les 'echo',
  // ce qui �vite de transmettre une grosse cha�ne de caract�res en retour

   function formulaireHTML ()
  {
    // On met un attribut ENCTYPE si on transf�re un fichier
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
	    
	  case "DEBUTTABLE":
	    // C'est le d�but d'un tableau HTML
	    $this->orientation = $description['orientation'];
	    $this->tableau = $description['tableau'];
	    break;
	    
	  case "FINTABLE":
	    // C'est la fin d'un tableau HTML
	    $formulaire .= $this->tableau->tableauHTML();
	    $this->orientation="";
	    break;

	  default: // Ne devrait jamais arriver...
	    echo "<p>ERREUR CLASSE FORMULAIRE!!</p>";
	  }
      }

    // Encadrement du formulaire par les balises
    $formulaire = "\n<form  method='$this->methode' " . $encType
              . "action='$this->action' name='$this->nom'>\n" 
              . $formulaire . "</form>\n";

    // Il faut �ventuellement le centrer
    if ($this->centre) $formulaire = "<center>$formulaire</center>\n";;

    // On retourne la cha�ne de caract�res contenant le formulaire
    return $formulaire;
  }

  function fin($bool=true)
  {
    return $this->formulaireHTML();
  }
  // End of class
}
