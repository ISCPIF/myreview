<?php
/**
 * @category Zmax
 * @package    Zmax_Html
 * @subpackage Table
 * @copyright
 * @license
 * @version
 */


 /**
  * HTML Table generator.
  * 
  * This class is used in the Form function.
  * @package    Zmax_Html
 * @subpackage Table
  *  @todo probably deprecated once Zmax_Form has be reengineered
  *  * DO NOT USE THIS CLASS IN A ZMAX APPLICATION. It will
  * probably be replaced by a more sophisticated and Zend-compliant
  * implementation later on.
  * 
 *
  */

class Zmax_HTML_Table
{
  // ----   Partie priv�e : les constantes et les variables
  var $nb_dimensions;
  // Tableau des valeurs � afficher
  var $tableau_valeurs;
  // Tableaux des ent�tes
  var $entetes, $options_lig, $options_col;
  // Options de pr�sentation pour la table. A compl�ter.
  var $options_tables, $couleur_paire, $couleur_impaire, 
    $csg, $affiche_entete, $repetition_ligne=array(),
    $option_dim=array(), $legende; 
  // Constante pour remplir les cellules vides

  // Constructeur
  function __construct ($nb_dimensions=2, $tab_attrs=array())
  {
    // Initialisation des variables priv�es
    $this->tableau_valeurs = array();
    $this->options_tables=$this->couleur_paire=
      $this->couleur_impaire=$this->legende="";

    // Initialisation de la dimension. Quelques tests s'imposent...
    $this->nb_dimensions=$nb_dimensions;

    // Initialisation des tableaux d'ent�tes pour chaque dimension
    for ($dim=1; $dim <= $this->nb_dimensions; $dim++)
      {
	$this->entetes[$dim] = array();
	$this->affiche_entete[$dim] = TRUE;
      }
    // Attributs de la balise <TABLE>
    $this->ajoutAttributsTable($tab_attrs);
  }
			
  // M�thode ajoutant des attributs HTML pris dans un tableau		       
   function ajoutAttributsTable($tab_attrs=array())
  {
    foreach ($tab_attrs as $nom_attr => $val_attr)
      $this->options_tables .= " $nom_attr='$val_attr' ";
  }

  // M�thodes d�finissant les couleurs paire et impaire
   function setLegende($text)  {
    $this->legende = $text;
  }
   function setCouleurPaire($couleur)  {
    $this->couleur_paire = $couleur;
  }
   function setCouleurImpaire($couleur)  {
    $this->couleur_impaire = $couleur;
  }
  
  // M�thode permettant d'afficher ou non un ent�te
   function setAfficheEntete($dim, $bool)  {
    $this->affiche_entete[$dim] = $bool;
  }

  // M�thode permettant de r�p�ter n fois l'affichage d'une ligne
   function setRepetitionLigne($dim, $cle, $nb_repetitions)  {
    $this->repetition_ligne[$dim][$cle] = $nb_repetitions;
  }

  // M�thode indiquant des options pour une ligne ou une colonne
   function setOption ($dim, $cle, $options=array())  {
    foreach ($options as $option => $valeur)
      $this->options[$dim][$cle][$option] = $valeur;
  }

  // M�thode permettant d'afficher le coin sup�rieur gauche
   function setCoinSuperieurGauche($texte)  {
    $this->csg = $texte;
  }

  // M�thode d�finissant des attributs pour les ent�tes, lignes,
  // colonnes: � faire!!
   function ajoutAttributsEntete($options) {}
   function ajoutAttributsLigne($cle_ligne, $options) {}
   function ajoutAttributsColonne($cle_colonne, $options) {}

  // TABLEAU A DEUX DIMENSIONS: ajout d'une valeur dans une cellule
   function ajoutValeur($cle_ligne, $cle_colonne, $valeur)
  {
    // Maintenance des ent�tes
    if (!array_key_exists($cle_ligne, $this->entetes[1])) 
      $this->entetes[1][$cle_ligne] = $cle_ligne;
    if (!array_key_exists($cle_colonne, $this->entetes[2])) 
      $this->entetes[2][$cle_colonne] = $cle_colonne;

    // Stockage de la valeur
    $this->tableau_valeurs[$cle_ligne][$cle_colonne] = $valeur;
  }

  // TABLEAU A N DIMENSIONS: ajout d'une valeur dans une cellule
  // Le premier param�tre est un tableau qui contient les cl�s.
  // Par exemple: array(1=>"Cle1", 2=>"Cle2", 3=>"Cle3")
   function ajoutValeurDimN($position, $valeur)
  {
    // On construit les coordonn�es au fur et � mesure
    $coord  = "";

    for ($dim=1; $dim <= $this->nb_dimensions; $dim++)
      {
	$cle = $position[$dim];
	// Par d�faut, les ent�tes valent la cl� (si elles n'existent pas)
	if (!array_key_exists($cle, $this->entetes[$dim])) 
	  $this->entetes[$dim][$cle] = $cle;
	
	$coord .= "['$cle']";
      }
    // On construit la commande et on l'ex�cute
    eval ("\$this->tableau_valeurs$coord='$valeur';");
  }

  // M�thode d�finissant un ent�te, avec texte
   function ajoutEntete($dimension, $cle, $texte)
  {
    // Stockage de la cha�ne servant d'ent�te
    $this->entetes[$dimension][$cle] = $texte;
  }

  // Production du tableau HTML: ne marche qu'en dimension 2!!
  // A FAIRE: g�n�raliser cette fonction pour obtenir les
  // tableaux A, B, C, D pr�sent�s dans le livre
   function tableauHTML()
  {
    $chaine = $ligne = "";
    /* Pour afficher les tableaux d'ent�tes. 
    print_r ($this->entetes[1]);
    print_r ($this->entetes[2]); */  

    // Est-ce qu'on affiche le coin sup�rieur gauche?
    if ($this->affiche_entete[1]) $ligne = "<th>$this->csg</th>";

    if (!empty($this->legende))
      {
	$nb_cols = count($this->entetes[2]);
	$chaine = "<tr class='header'>\n<th colspan=$nb_cols>$this->legende"
	  . "</th>\n</tr>\n";
      }

    // Cr�ation des ent�tes de colonnes (dimension 2)
    if ($this->affiche_entete[2])
      {
	foreach ($this->entetes[2] as $cle => $texte) 
	  $ligne .= "<th>$texte</th>\n";
	
	// Ligne des ent�tes.
	$chaine = "<tr class='header'>$ligne</tr>\n";
      }

    $i=0;
    // Boucles imbriqu�es sur les deux tableaux de cl�s
    foreach ($this->entetes[1] as $cle_lig => $enteteLig) // Lignes
      {
	if ($this->affiche_entete[1])
	  $ligne = "<th>$enteteLig</th>\n";
	else
	  $ligne = "";

	$i++;

	foreach ($this->entetes[2] as $cle_col => $enteteCol) // Colonnes
	  {
	    // On prend la valeur si elle existe, sinon le d�faut
	    if (isSet($this->tableau_valeurs[$cle_lig][$cle_col]))
	      $valeur = $this->tableau_valeurs[$cle_lig][$cle_col];
	    else
	      $valeur = "&nbsp;";

	    // On place la valeur dans une cellule
	    $ligne .= "<td>$valeur</td>\n";
	  }
	// Eventuellement on tient compte de la couleur
	if ($i % 2 == 0)
	  {
	    $options_lig = " class='even'";
	    if (!empty($this->couleur_paire))
	      $options_lig .= " bgcolor='$this->couleur_paire' ";
	  }
	else if ($i % 2 == 1)
	  {
	    $options_lig = " class='odd'";
	    if (!empty($this->couleur_impaire))
	      $options_lig = " bgcolor='$this->couleur_impaire' ";
	  }
	else $options_lig = "";

	// Doit-on appliquer une option?
	if (isSet($this->options[1][$cle_lig]))
	  foreach ($this->options[1][$cle_lig] as $option => $valeur)
	    $options_lig .= " $option='$valeur' ";
	$ligne = "<tr$options_lig>\n$ligne\n</tr>\n";

	// Prise en compte de la demande de r�p�tition d'une ligne
	if (isSet($this->repetition_ligne[1][$cle_lig]))
	  {
	    $rligne = "";
	    for ($i=0; $i < $this->repetition_ligne[1][$cle_lig]; $i++)
	      $rligne .= $ligne;
	    $ligne = $rligne;
	  }
	// On ajoute la ligne � la cha�ne
	$chaine .= $ligne; 
      }
    // Placement dans la balise TABLE,  et retour
    return  "<table $this->options_tables>\n$chaine</table>\n";
  }
}			
