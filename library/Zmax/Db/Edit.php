<?php

/**
 * @category Zmax
 * @package    Zmax_Db
 * @subpackage Edit
 * @copyright
 * @license
 * @version
 */

/**
 * Create generic forms to insert, update or delete rows in a table.
 *
 * This class helps Zmax programmers create generic forms to update or create database
 * rows.
 *
 * @package Zmax_Db
 * @subpackage Edit
 * @author Philippe Rigaux
 * @todo This class needs to be fully documented. Also:
 *     - add support for the 'slave' tables
 *     - make sure that each method throws exceptions properly
 *     - add methods that explicits the settings now obtained from the configuration object
 *
 */

class Zmax_Db_Edit
{
  // Constants used by the class. Accnot be declared private in PHP 5, although they should ...
  const DB_INSERT     = '1';
  const DB_UPDATE   = '2';
  const DB_DELETE     = '3';
  const DB_EDIT     = '4';
  const SELECT_FIELD = "SELECT";
  const RADIO_FIELD = "RADIO";
  const HIDDEN_FIELD = "HIDDEN";
  const CHECKBOX_FIELD = "CHECKBOX";
  const BOOLEAN_FIELD = "BOOLEAN";
  const MAX_FIELD_LENGTH = 60;
  const  NB_ROWS_CHECKBOX = 7;
  // Value that identifies the "all" choice in the table form
  const ALL_INDEX = "all_index";
  // Name of the HTTP parameter which sets the next action (use
  // in the workflow
  const ZMAX_EDIT_ACTION = "zmax_edit_action" ;

  // Private part of the class
  private $db, $db_my_lang, $table_object, $table_name,
  $table_primary, $table_references;

  private  $url, $info, $title,
  $headers, $form_fields, $form_fields_type, $hidden_fields,
  $add_form_fields, $isChild, $dataChild, $tableChild,
  $order_by_clause, $where_clause, $auto_increment_key,
  $table_label, $lang, $hide_from_table, $creation_field,
  $revision_field, $insert_values, $update_values, $inserted_id,
  $use_dictionary, $lang_field, $locale, $form_table_query;

  private $modLink, $archLink, $delLink;

  // Properties that handle the form table
  private $show_table_form, $table_form_values;

  //mode_show_form = 1 : par d�faut, afficher la formulaire de saisie
  //mode_show_form = 0 : cacher la formulaire de saisie
  private $mode_show_form;
  private $show_btn_modify;
  private $show_btn_archive;
  private $show_btn_delete;
  private $doSaveUserProduct;

  private $slave_table, $schema_slave, $size_slave_table, $foreign_keys;
  private $texts;
  private $messages, $error_met;

  /**
   * Constructor
   *
   * The constructor instantiates a Zmax_Db_table object which must be
   * part of the model of the application. It also retrieves from
   * the Zmax context utilitary objects: texts, locale and db adapter.
   *
   * @param the name of the table whose content is to be edited
   * @throws Zmax_Db_Exception
   * @return void
   */

  function __construct ($table_name, $texts=null)
  {
    // First, instantiate a DB table object
    eval ("\$this->table_object  = new $table_name(); ");
    // get the info on the table object
    $this->info = $this->table_object->info();
    $this->table_primary = $this->table_object->getPrimary();
    $this->table_references = $this->info['referenceMap'];
    $this->table_name = $table_name;

    $registry = Zend_registry::getInstance();
    $zmax_context = $registry->get("zmax_context");

    if ($texts == null) {
      // Get the translations from the registry
      $this->texts = $zmax_context->texts;
    }
    else
    $this->texts = $texts;

    // Get the locale
    $this->locale = $zmax_context->locale;
    $this->lang = $this->locale->getLanguage();
    $this->lang_field = "";

    // Check whether we can rely on auto increment
    if ($this->table_object->isAutoIncremented())
    $this->setAutoIncrementedKey($this->table_primary[1]);

    // Initialize private variables
    $this->db = $this->table_object->getAdapter();
    $this->db_my_lang = $this->db; // Default: use the same connection
    $this->use_dictionary = false;
    $this->title = $this->table_label = $this->table_name = $this->info['name'] ;
    $this->form_table_query = "";

    $this->mode_show_form = true;
    $this->show_btn_modify = true;
    $this->show_btn_archive = false;
    $this->show_btn_delete = true;
    $this->show_table_form = false;
    $this->form_table_values = array();
    $this->isChild = false;

    // The id should always be auto-incremented ?!
    $this->auto_increment_key["id"]=true;
    $this->order_by_clause = "";
    $this->where_clause = " 1= 1 ";
    $this->hidden_fields = $this->add_form_fields = $this->hidden_from_table = array();
    $this->slave_table = $this->creation_field = $this->revision_field = "";
    $this->insert_values = $this->update_values = array();

    // The default choice is to print the name as its own label
    $body = "";
    foreach ($this->info['cols'] as $name) {

      /* $type = $this->info['metadata'][$name]['DATA_TYPE'];
       $length = $this->info['metadata'][$name]['LENGTH'];
       echo "Col : $name ($type $length)"    . "<br/>";
       foreach ($this->info['metadata'][$name] as  $meta)
       */

      $this->headers[$name] =  $this->texts->db->get($name) ;
    }

    // For each reference: create a select field
    foreach ($this->table_references as $ref) {
      if (isSet($ref['refLabel']))
      $this->setReferenceField($ref['refLabel'], $ref['refTableClass'],
      Zmax_Db_Edit::SELECT_FIELD);
    }
    // print_r ($this->table_references);
  }

  /**
   * keyValue
   *
   * @param an instance of Zmax_Db_Table, represented as an array
   * @throws Zmax_Db_Exception
   * @return value of the tuple's key
   */
  private function keyValue($tuple)
  {
    // IMPORTANT: only works so far for a single key attribute.
    foreach ($this->table_primary as $name) {
      return $tuple[$name];
    }

    // No key attribute ?!
    throw new Zmax_Db_Exception ("ERROR: unable to find the key value");
  }

  /**
   * keyAccess
   *
   * returns a key access to a tuple, aither in URL,  SQL or ARRAY format
   * @param an instance of Zmax_Db_Table, represented as an array
   * @throws Zmax_Db_Exception
   * @return value of the tuple's key
   */

  private function keyAccess($tuple, $format="url")
  {
    $separateur = $chaine = "";
    $key_array = array();
    // Scan the key attributes
    foreach ($this->table_primary as $name) {
      if ($format=="url")	 {
        $chaine .= "&amp;$name=" .  urlEncode($tuple[$name]);
        $separateur = "&amp;";
      }
      else  if ($format=="SQL") {
        $chaine .= "$separateur$name='" .
        addSlashes($tuple[$name]) . "'";
        $separateur = " AND ";
      }
      else { // Create an array
        $key_array[$name] = $tuple[$name];
      }
    }
    if ($format=="url" or $format=="SQL")
    return $chaine;
    else
    return $key_array;
  }

  // Function that returns the key of a slave table in SQL mode
  private function slaveAccess($tuple)
  {
    $separateur = $chaine = "";
    // Parcours des attributs
    foreach ($this->schema_slave as $nom => $options)
    {
      if (isSet($this->foreign_keys[$nom])) {
        $chaine .= "$separateur$nom='" .
        addSlashes($tuple[$this->foreign_keys[$nom]]) . "'";
        $separateur = " AND ";
      }
    }
    return $chaine;
  }

  // Function that checks structural constraints on the table
  private function controle(&$ligne, $action = "")
  {
    /*echo "Ligne = ";
     print_r ($ligne); echo "<p>";
     */

    // On commence par traiter toutes les cha�nes des attributs
    $cle_primaire = array();

    foreach ($this->info['cols'] as $name)  {
      $meta = $this->info['metadata'][$name];
      // Au passage on stocke les att. de la cl� primaire
      if($meta["PRIMARY"] and isSet($ligne[$name]) and $ligne[$name]!="")	{
        $cle_primaire[] = $name."='".$ligne[$name]."'";
      }

      // Contr�le sur la contrainte NOT NULL
      if (!isSet($this->auto_increment_key[$name])
      and $meta['NULLABLE'] == false
      and  empty($ligne[$name]) and !isSet($this->update_values[$name])) {
        $this->add_message($this->texts->zmax->err_not_null . " " . $this->texts->get($name));
      }
    }

    // Check unique value for the primary key
    if(count($cle_primaire) > 0 and $action=="insert"  and !$this->error_met)  {
      $condition = implode(" AND ", $cle_primaire);
      $sql = "SELECT * FROM $this->table_name WHERE $condition ;";
      $res = $this->db->query ($sql);

      if ($res->fetch())  {
        $this->add_message($this->texts->zmax->err_primary_key);
      }
    }

    // Maybe check also the type or length of data...
    return $this->in_error(); // false si un pb est rencontr�
  }

  /**
   * method to create an HTML form based on the table schema
   *
   * The method creates an HTML table of input fields, and takes account
   * of several options, including the possibility to use checkbox fields
   * for one-to-many relationships, radio flieds for many-to-one relationships,
   * and boolean fields.
   *
   * @param string  action that must be triggered by the form update or insert
   * @param array   the default values of the form fields
   * @return string a string containing the HTML form
   * @throws none
   */
  public function form ($action, $data)
  {
    // Create the form object
    $form = new Zmax_HTML_Form ("post", $this->url, false);
    $form->setTitle ($this->title);

    // Put the hidden fields
    foreach ($this->hidden_fields as $nom => $value) {
      $form->champCache ($nom, $value);
    }
    $form->champCache (self::ZMAX_EDIT_ACTION, $action);

    // Put also as hidden fields the values of the table form fields
    $form->champCache("show_table_form", $this->show_table_form);
    if ($this->show_table_form) {
      if (is_array($this->table_form_values)) {
        foreach ($this->table_form_values as $name => $value) {
          $form->champCache ($name, $value);
        }
      }
    }

    // Put the primary key in a hidden field, to handle
    // any possible change of the key
    foreach ($this->info['primary'] as $name) {
      if (isSet($data[$name])) {
        $form->champCache ("opk[$name]", $data[$name]);
      }
    }

    // Start a table, with 2 cols [Label | Field ]
    $form->debutTable (VERTICAL, array(), 1, $this->title);

    // For each attribute, create a form field
    foreach ($this->info['cols'] as $name) {
      // Get the metadata on the field
      $meta = $this->info['metadata'][$name];
      // Do not show hidden fields
      if (in_array($name, array_keys($this->hidden_fields))) continue;

      // Check that the default value exists
      if (!isSet($data[$name])) $data[$name] = "";
      $this->addFormField ($form, $action, $name, $data[$name], $meta);
    }
     
    // Check the many-to-many associations
    foreach ($this->add_form_fields as $name => $bridge) {
      // Check whether there is a default value
      if (!isSet($data[$name])) $data[$name] = "";
      $html_field_name = "$name" . "[]";
      $this->addFormField ($form, $action, $name, $data[$name],
      array("DATA_TYPE" => "", "LENGTH" => "", "PRIMARY" => false),
      $html_field_name);
    }

    // OK. Now add lines for the slave table, if any
    if (!empty($this->slave_table)) {
      $form->debutTable (HORIZONTAL, array(),
      $this->size_slave_table);

      // Create a field for each attribute
      foreach ($this->schema_slave as $nom => $options)	{
        // Check that the default value exists
        if (!isSet($ligne[$this->slave_table][$nom]))
        $valeur = "";
        else
        $valeur = $ligne[$this->slave_table][$nom];

        // Manage foreign keys
        if (isSet($this->foreign_keys[$nom])) {
          $options['cle_etrangere'] = 1;
          if ($action == MAJ_BD)
          $valeur = $ligne[$this->foreign_keys[$nom]];
        }

        $html_field_name = "$this->slave_table[$nom][]";
        $this->addFormField ($form, $action, $nom,
        $valeur, $options, $html_field_name);
      }
      $form->finTable ();
    }
    $form->finTable();

    if ($action == self::DB_UPDATE)
    $form->champValider ($this->texts->form->modify, "submit");
    else
    $form->champValider ($this->texts->form->insert, "submit");

    return $form->formulaireHTML();
  }

  /**
   * Add a field to the form
   */

  private function addFormField (&$form, $action, $name, $value, $meta, $html_field_name="")
  {
    $data_type = $meta['DATA_TYPE'];
    $data_length = $meta['LENGTH'];
    if (empty($data_length) or $data_length == 0) {
      $data_length = 10;
    }

    // echo "Type = $data_type Length = $data_length<br/>";

    // Default: the form field name is the column name
    if (empty($html_field_name)) {
      $html_field_name = $name;
    }

    // Beware of specials chars in values
    if (!is_array($value)) $value = htmlSpecialChars($value);

    // When this is he primary key or a foreign: use a hidden field
    if ($meta['PRIMARY'] and isSet($this->auto_increment_key[$name])
    and $action == self::DB_UPDATE) {
      $form->champCache ($html_field_name, $value);
    }
    else if (isSet($meta['FOREIGN'])) {
      $form->champCache ($html_field_name, $value);
    }
    else {
      if (!isSet($this->auto_increment_key[$name])) {
        // Create the field, based on its type
        if ($data_type == "blob" or  $data_type == "text")
        $form->champfenetre ($this->headers[$name],
        $html_field_name, $value, 8, 80);
        else {
          if ($data_type == "time" and $action==self::DB_UPDATE) {
            // Show only hour and minutes
            $time = explode (":", $value);
            $value = $time[0] . ":" . $time[1];
          }
           
          // In general, create a simple text input field
          if (!isSet($this->form_fields_type[$name])) {
            $lg_visible = min (self::MAX_FIELD_LENGTH, $data_length);
            $form->champTexte ($this->headers[$name],
            $html_field_name, $value, $lg_visible,
            $meta['LENGTH']);
          }
          else if ($this->form_fields_type[$name] == self::SELECT_FIELD) {
            $form->champListe ($this->headers[$name],
            $html_field_name, $value, 1,
            $this->form_fields[$name]);
          }
          else if ($this->form_fields_type[$name] == self::RADIO_FIELD) {
            if (empty($value)) $value = key($this->form_fields[$name]);
             
            $form->champRadio ($this->headers[$name],  $html_field_name, $value,
            $this->form_fields[$name]);
          }
          else if ($this->form_fields_type[$name] == self::CHECKBOX_FIELD) {
            $form->champCheckBox ($this->headers[$name], $html_field_name, $value,
            $this->form_fields[$name], self::NB_ROWS_CHECKBOX);
          }
          else if ($this->form_fields_type[$name] == self::BOOLEAN_FIELD) {
            if (empty($value)) $value = 'N';
            $form->champRadio ($this->headers[$name], $html_field_name, $value,
            $this->form_fields[$name]);
          }
        }
      }
    }
  }

  /**
   * Insert a new row in the table
   */

  private  function insertion ($input)
  {
    // print_r($input);
    //print_r($this->insert_values);
    // Initisalisations
    $noms = $valeurs = $virgule = "";
    $messages = array();
    $this->controle ($input, "insert");
    // Contr�le avant toute mise � jour
    if ($this->in_error()) {
      return false;
    }

    // Create the data array
    $data = array();
    unset($id_master);

    foreach ($this->info['cols'] as $name) {
      $meta = $this->info['metadata'][$name];
      if (!isSet($this->auto_increment_key[$name])
      and !isSet($this->update_values[$name])) {
        if ($name == $this->revision_field or $name == $this->creation_field)
        $data[$name] = "NOW()"; // Hum, should only work with MySQL...
        else if (isSet($this->insert_values[$name]))
        $data[$name] = $this->insert_values[$nom];
        else if (isSet($input[$name])) // Is there anything to check here?
        $data[$name] = $input[$name];
      }
      if ($meta["PRIMARY"] and isSet($input[$name]))
      $id_master = $input[$name];
    }
     
    // Instantiate a new row
    $row = $this->table_object->createRow();
    // Feed the new row with the input values
    $row->setFromArray($data);

    // Now, save and we are done!
    $row->save();

    if (!isSet($id_master))
    $id_master = $this->db->lastInsertID();

    $this->inserted_id = $this->db->lastInsertID();;

    // Insertion dans les tables many-many si necessaire
    foreach ($this->add_form_fields as $name => $bridge) {
      if (isSet($input[$name]))
      $this->insertInBridge ($row, $input[$name], $bridge);
    }

    // Insertion dans la table esclave
    $this->insertInSlave ($id_master, $input);

    return true;
  }

  /**
   * Delete rows from a slave table (one-to-many relationship)
   *
   */
  private function deleteFromSlave ($id_master, $ligne)
  {
    // Take account of the slave table if any
    if (!empty($this->slave_table)) {
      $slave_values = $ligne[$this->slave_table];
      // On d�truit les lignes existantes
      $clause = $this->slaveAccess ($ligne);
      $query = "DELETE FROM $this->slave_table WHERE $clause";
      $res = $this->bd->execRequete($query);
    }
  }

  /**
   * Insert rows in a slave table (one-to-many relationship)
   */
  private function insertInSlave ($id_master, $ligne)
  {
    // Take account of the slave table if any
    if (!empty($this->slave_table)) {

      $slave_values = $ligne[$this->slave_table];

      // On fait une boucle sur le nombre de lignes possibles
      for ($i=0; $i < $this->size_slave_table; $i++) {
        $virgule=""; $noms=""; $valeurs=""; $insertion = true;
        // Parcours des attributs pour cr�er la requ�te
        foreach ($this->schema_slave as $nom => $options) {
          if (!isSet($this->auto_increment_key[$nom])) {
            // Liste des noms d'attributs + liste des valeurs
            $noms .= $virgule . $nom;
             
            // Attention � g�rer les cl�s �trang�res
            if (isSet($this->foreign_keys[$nom]))
            $valeur = $id_master;
            else
            $valeur = $slave_values[$nom][$i];
             
            // Never insert if the value of a primary key is missing
            if ($valeur == "" and $options["cle_primaire"])
            $insertion = false;
            $valeurs .= "$virgule '$valeur'";
            // A partir de la seconde fois, on s�pare par des virgules
            $virgule= ",";
          }
        }
        if ($insertion) {
          $requete = "INSERT INTO $this->slave_table($noms) VALUES ($valeurs)";
          //echo "<br>slave = ".$requete."<br><br>";
          $this->bd->execRequete ($requete);
        }
      }
    }
  }

  /**
   *Insert in a table that implements a many-to-many association
   *
   */
  private function insertInBridge ($obj, $data, $link_desc)
  {
    $local_name = get_class($this->table_object);
    $bridge_name = $link_desc['bridge_table'];
    $remote_name = $link_desc['remote_table'];
    $row = $obj->toArray();

    // Remove everything, ...
    $this->deleteFromBridge ($obj, $bridge_name);

    // Instantiate the bridge class
    eval ("\$bridge_table = new $bridge_name();");

    $refLocal = $bridge_table->getReference($local_name);
    $refRemote = $bridge_table->getReference($remote_name);
    $id_local = $this->keyValue($row);

    // Get the two foreign keys from the bridge table: the local
    // FK refers to the current table, the remote FK to the remote table
    // NOTE: so far, only work with id's
    $localFK = current($refLocal['refColumns']);
    $remoteFK = current($refRemote['refColumns']);
    // echo "Local FK = $localFK Remote FK = $remoteFK<br/>";
     
    // ... then insert
    foreach ($data as $remote_id) {
      $new_row = $bridge_table->createRow(array($localFK => $id_local,
      $remoteFK => $remote_id));
      $new_row->save();
    }
  }

  /**
   * Remove from bridge table
   */
  private function deleteFromBridge ($obj, $table_name)
  {
    // Loop on the rows associated to the current object, remove them
    $dependents = $obj->findDependentRowset ($table_name);
    foreach ($dependents as $dep) $dep->delete();
  }

  /**
   * Updates a row
   */
  private function update ($row)
  {
    // Check before any action
    $this->controle ($row);
    if ($this->in_error()) return false;

    // If the dictionary is used: first check whether the current
    // lang is distinct from the lang of the current row. If yes:
    // put in the dictionary, instead of the base table
    if ($this->use_dictionary) {
      $ligne_courante = $this->getRow($row);
      if (!isSet($ligne_courante[$this->lang_field])) {
        echo "Update error: invalid lang field ($this->lang_field)<br>";
        print_r ($ligne_courante);
        exit();
      }

      if ($this->lang != $ligne_courante[$this->lang_field]) {
        $insert_dic = true;
        // Get the name of the primary key, for the dictionary
        foreach ($this->info['cols'] as $nom)
        if(isSet($this->info['metadata'][$nom]['PRIMARY']))
        $cle_primaire = $nom;
      }
      else $insert_dic = false;
    }
    else
    $insert_dic = false;

    // Find the row object, using the old primary key
    // echo "Ici " . current($row["opk"]) . "<br/>";
    // $obj = $this->table_object->find(current($row["opk"]))->current();
    $obj = $this->getRow($row, "object");
    $data = array();

    // Now scan the attributes and create the query
    foreach ($this->info['cols'] as $nom) {
      $meta = $this->info['metadata'][$nom];
      if ($insert_dic) {
        // Insert in the dictionary
        if ($nom != $this->creation_field
        and !isSet($this->insert_values[$nom])
        and !isSet($this->update_values[$nom])
        and ($meta['DATA_TYPE'] == "string" or $meta['DATA_TYPE'] == "blob")
        ) {
          UpdateTranslation ($this->table_name, $row[$cle_primaire], $nom,
          $this->lang, $row[$nom], $this->db);
        }
      }
      else {
        // Create an array with the new values

        // A field is inserted only if it is not a creation field,
        // and if it is not defined as an insertion field
        if ($nom != $this->creation_field
        and !(isSet($this->insert_values[$nom])
        and !isSet($this->update_values[$nom]))) {
          if ($nom == $this->revision_field)
          $data[$nom] = "NOW()";
          else if (isSet($this->update_values[$nom]))
          $data[$nom] = $this->update_values[$nom];
          else
          $data[$nom] = $row[$nom] ;
        }
      }
      if ($meta['PRIMARY']) $id_master = $row[$nom];
    }

    // If the insertion is in the dictionary: it's finished
    if ($insert_dic) return;

    // Else process the update
    $obj->setFromArray($data);
    $obj->save();
     
    // Insertion dans les tables many-many si necessaire
    foreach ($this->add_form_fields as $name => $link_desc) {
      if (isSet($row[$name]))
      $this->insertInBridge ($obj, $row[$name], $link_desc);
    }

    // Take account of the slave table
    $this->deleteFromSlave ($id_master, $row);
    $this->insertInSlave ($id_master, $row);
    return true;
  }
  // End of update method

  /**
   * Checks whether a dependent row exists
   */
  private function verifChild($tableLink, $tableChild, $keyMaster, $keyChild, $valueMaster){
    $req = "SELECT * FROM ".$tableLink." WHERE ".$keyMaster." = '".$valueMaster."'";
    $res = $this->bd->execRequete($req);

    if($this->bd->nbrResultats($res)>0){
      $this->isChild = true;
      $this->tableChild = $tableChild;

      while($obj = $this->bd->objetSuivant($res)){
        $req2 = "SELECT * FROM ".$tableChild." WHERE ".$keyChild." = '".$obj->$keyChild."'";
        $res2 = $this->bd->execRequete($req2);
        $tabData = $this->bd->get_data($res2);
        $this->dataChild[] = $tabData[0];
      }
    }
  }

  /**
   * Remove a line from the DB
   */
  private function delete ($input)
  {
    $obj = $this->getRow($input, "object");

    // Remove the content of the save table (like a CASCADE)
    if (!empty($this->slave_table)) {
      $clause = $this->slaveAccess ($input);
      $query = "DELETE FROM $this->slave_table WHERE $clause";
      $res = $this->db->query($query);
    }

    // Remove the content of the many-many  table (like a CASCADE)
    foreach ($this->add_form_fields as $nom => $link_desc) {
      $this->deleteFromBridge ($obj, $link_desc['bridge_table']);
    }

    // Delete the object
    $obj->delete();

    // Check whether there are children. If yes, remove them
    /* PR: check and modify
     foreach ($this->schemaTable as $nom => $options) {
     if($options['cle_primaire']==1){$cle_primaire = $nom;}
     if(substr($nom,-7)=="_parent"){$cle_etrangere = $nom;}
     }
     if(isset($cle_etrangere) && $cle_etrangere!=''){
     $requete2 = "DELETE FROM ".$this->nomTable." WHERE ".$cle_etrangere." = ".$ligne[$cle_primaire];
     $this->bd->execRequete($requete2);
     }
     */

    // If the dictionary is used: remove the translations
    if ($this->use_dictionary) {
      $req_dic  = "DELETE FROM dictionary WHERE table_name = '{$this->table_name} " .
				"AND row_id = '$id_master'";
      $this->db->query ($req_dic);
    }

    // Extension from JF: do not remove automatically a child: shows a message first
    // PR: to be checked
    /*
     *
     if($this->isChild){
     $msg = $this->texts->get('zmax_alert_child', $this->lang);
     $msg .= "<br />";
     $msg .= strtoupper($this->tableChild);
     $msg .= "<br />";
     $msg .= "<ul style='margin-left:25px;'>";
     foreach($this->dataChild as $key => $tab){
     $msg .= "<li>";
     foreach($tab as $keyData => $valueData){
     if(substr($keyData,-5)=="_name"){$msg .= $valueData;}
     if(substr($keyData,-3)=="_id"){$msg .= $valueData." : ";}
     }
     $msg .= "</li>";
     }
     $msg .= "</ul>";
     $this->add_message($msg);
     }
     else{

     $this->db->query ($requete);
     */
  }

  /**
   * set the boolean that indicates that the table form must be shown
   *
   * @return void
   */
  public function showTableForm ()
  {    $this->show_table_form = true; }

  /**
   * Create a generic table showing the lines of the table
   *
   *
   */
  private function table ($attributs=array())
  {
    // Create an HTML table object
    $tableau = new Zmax_HTML_Table (2, $attributs);
    $tableau->setAfficheEntete(1, false);
    $tableau->setLegende($this->title);

    // Create the headers
    foreach ($this->info['cols'] as $name) {
      if (!isSet($this->auto_increment_key[$name]) and
      !isSet($this->hide_from_table[$name]))
      $tableau->ajoutEntete(2, $name, $this->headers[$name]);
    }

    // A form to select sub-contents of the table
    if ($this->show_table_form) {
      $form = new Zmax_HTML_Form ("post", $this->url, false, "table_form", "table_form");

      // Create a first row with select fields
      foreach ($this->info['cols'] as $name) {
        if (!isSet($this->auto_increment_key[$name]) and
        !isSet($this->hide_from_table[$name])) {
          // Define the HTML form field name
          $form_field_name =  $this->getTblFormFieldName($name);
          // Get the default value if it exists
          $form_field_name = $this->getTblFormFieldName($name);
          if (isSet($this->table_form_values[$form_field_name])) {
            $default = $this->table_form_values[$form_field_name];
          }
          else $default = "";
           
          // Create an input field, based on the field type
          if (!isSet($this->form_fields_type[$name])) {
            // A text field, for full text search
            $form_field = $form->champTexte ("", $form_field_name, $default, 20, 20);
            // Add in the where clause
            $safe_def = $this->db->prepareString($default);
            $this->where_clause .= " AND $name LIKE '%$safe_def%'";
          }
          else if ($this->form_fields_type[$name] == self::SELECT_FIELD
          or $this->form_fields_type[$name] == self::RADIO_FIELD)
          {
            $the_list = array_merge (array(self::ALL_INDEX => $this->texts->zmax->all),
            $this->form_fields[$name]);
            $form_field = $form->champListe ("", $form_field_name, $default, 1, $the_list);
            // Add in the where clause
            if ($default != self::ALL_INDEX)
            $this->where_clause .= " AND $name = '$default'";
            // echo "$name is a select field<br/>";
          }
          else if ($this->form_fields_type[$name] == self::CHECKBOX_FIELD) {
            $form_field = $form->champCheckBox ("", $form_field_name, "",
            $this->form_fields[$name], self::NB_ROWS_CHECKBOX);
          }
          else if ($this->form_fields_type[$name] == self::BOOLEAN_FIELD) {
            $form_field = $form->champRadio ("", $form_field_name, "",
            $this->form_fields[$name]);
          }
          // Put the field in the table
          $tableau->ajoutValeur(0, $name, $form->getChamp($form_field));
        }
      }
      $form_field = $form->champValider ($this->texts->form->submit, "submit");
      $tableau->ajoutValeur(0, "Action", $form->getChamp($form_field));
      unset ($form);
    } // End of the creation of the table form

    // Show the values of the many-to-many associations
    foreach ($this->add_form_fields as $remote_field_name => $link) {
      if (!isSet($this->hide_from_table[$remote_field_name])) {
        $tableau->ajoutEntete(2, $remote_field_name, $this->headers[$remote_field_name]);
      }
    }

    // Scan the table
    // echo "Where clause = " . $this->where_clause . "<br/>";
    if (!empty($this->order_by_clause))
    $this->table_object->select()->order($this->order_by_clause);
    if (empty($this->where_clause)) {
      $objs = $this->table_object->fetchAll();
    }
    else {
      $objs = $this->table_object->fetchAll($this->where_clause);
    }

    $i=0;
    foreach ($objs as $obj) {
      $row = $obj->toArray(); // Easier to manipulate as an array
      $i++;
      // Create the cells
      foreach ($this->info['cols'] as $name) {
        $meta = $this->info['metadata'][$name];
        // What is this for?
        // if(substr($name,-8)=="_archive"){$val_archive = $row[$name];}

        if (!isSet($this->auto_increment_key[$name]) and
        !isSet($this->hide_from_table[$name])) {
          if ($meta['DATA_TYPE'] == "time") {
            // Show only hour and minutes
            $time = explode (":", $row[$name]);
            $row[$name] = $time[0] . ":" . $time[1];
          }

          if (isSet ($this->form_fields[$name][$row[$name]])) {
            // The value is referred to by the foreign key $ligne[$nom]
            $label = $this->form_fields[$name][$row[$name]];
          }
          else
          // Attention: traitement des balises HTML avant affichage
          $label = htmlSpecialChars($row[$name]);
          $tableau->ajoutValeur($i, $name, $label);
        }
      }

      // Show the values of the many-to-many associations
      foreach ($this->add_form_fields as $remote_field_name => $remote_descr) {
        // Get teh name of the remote table and the remote field
        $bridge_table = $remote_descr['bridge_table'];
        $remote_table = $remote_descr['remote_table'];

        if (!isSet($this->hide_from_table[$remote_field_name])) {
          // Follow the many-to-many link
          $remotes = $obj->findManyToManyRowset ($remote_table, $bridge_table);
          $comma = $r_val = "";
          foreach  ($remotes as $remote) {
            $rem_arr = $remote->toArray();
            $r_val .= $comma . $rem_arr[$remote_field_name];
            $comma = ", ";
          }
          $tableau->ajoutValeur($i, $remote_field_name, $r_val);
        }
      }
      // Add links in the table
      $modLink = $archLink = $delLink = "";

      if ($this->show_btn_modify)	{
        // Show the modification URL
        $urlMod = $this->keyAccess($row) . "&amp;" . self::ZMAX_EDIT_ACTION . "=" . self::DB_EDIT;
        // Attention: traitement des balises HTML avant affichage
        $urlMod = $urlMod . $this->form_table_query ;
        $modLink = "<a href='{$this->url}$urlMod'>{$this->texts->form->modify}</a>";
      }

      // Show the deletion URL
      if($this->show_btn_delete) {
        // url de destruction
        $urlDel = $this->keyAccess($row) . "&amp;" . self::ZMAX_EDIT_ACTION . "=" . self::DB_DELETE;
        // Attention: traitement des balises HTML avant affichage
        $urlDel = $urlDel;
        $msg_suppression = $this->texts->are_you_sure;
        $url = $this->url . "/" . $urlDel . $this->form_table_query;
        $jscript= "onClick=\"if (confirm('" . $msg_suppression . "')) "
        . "{window.location = '" . $url . "';}  else alert ('" . $this->texts->form->canceled . "');\" ";
        $delLink = "<a $jscript href='#'>{$this->texts->form->delete}</a>";
      }

      // modification de la classe pour mettre toutes les actions dans la meme colonne by JF (webnet) 17/08/2007
      $outLink = $modLink;
      if ($modLink!="") {$outLink.="<br />\n";}
      $outLink .= $archLink;
      if($archLink!=""){$outLink.="<br />\n";}
      $outLink .= $delLink;
      $tableau->ajoutValeur($i, "Action", $outLink);
    }

    // If a form is associated to the table: create
    // the form, then put the table in it, then return
    if ($this->show_table_form) {
      $form = new Zmax_HTML_Form ("post", $this->url, false, "table_form", "table_form");
      $form->champCache ("show_table_form", 1);
      $form->ajoutTexte($tableau->tableauHTML());
      return $form->formulaireHTML();
    }
    else // Return the string that contains the HTML table
    return $tableau->tableauHTML();
  }

  /**
   * header setting
   *
   * define the header of a table column
   *
   * @param string  name of the attribute
   * @param strin   value of the  header
   *
   */

  public function setHeader($col_name, $text)
  { $this->headers[$col_name] = $text; }

  /**
   * title setting
   *
   * define the title of the form and table
   *
   * @param string  title
   *
   */
  public function setTitle($title)  { $this->title = $title;  }

  /**
   * set a field to be a reference to another table
   *
   * Define a form field as a reference to another table.
   *  Important: the referred table MUST be in the ORM description
   *
   * @param string  the name of the attribute referred to in the other table
   * @param string  name of the class that encapsulates the referred table
   * @param field type  either Zmax_Db_Edit::SELECT_FIELD or Zmax_Db_Edit::CHECKBOX_FIELD
   * @throws Zmax_Db_Exception
   */

  public function setReferenceField($ref_att_name, $table_ref, $field_type=Zmax_Db_Edit::SELECT_FIELD,  $options="")
  {
    // First check that the ref table is in the ORM
    if (!isSet($this->table_references[$table_ref])) {
      // The table is not part of the model.
      throw new Zmax_Db_Exception ("$table_ref is not referred to by {$this->table_name}");
    }
    $ref = $this->table_references[$table_ref];
    $fk_name = $ref['columns']; // Name of the foreign key
    // print_r ($ref);

    // Determine the list that must be shown
    eval ("\$table = new $table_ref(); ");
    $id_name = $table->getPrimaryField();

    if(isset($options["order"])) {
      $table->select()->order ($options["order"]);
    }
    else {
      $table->select()->order ($ref_att_name);
    }

    // Get the rows
    $res = array();
    $objs = $table->fetchAll();
    foreach ($objs as $obj) {
      $row = $obj->toArray();
      $res[$row[$id_name]] = $row[$ref_att_name];
    }
    $this->form_fields[$fk_name] = $res;
    $this->form_fields_type[$fk_name] = $field_type;
  }

  /**
   * Customize a form field
   *
   * @param string  the name of the attribute
   * @param string  type of the field (always self::BOOLEAN_FIELD for now)
   * @throws Zmax_Db_Exception
   */

  public function setFormField($nomAttr, $type)
  {
    if ($type == self::BOOLEAN_FIELD)
    {
      $question = $this->locale->getQuestion($this->locale->getLanguage());
      $this->form_fields[$nomAttr] = array('Y' => $question['yes'], 'N' => $question['no']);
      $this->form_fields_type[$nomAttr] = self::BOOLEAN_FIELD;
      return;
    }

    throw new Zmax_Db_Exception ("Invalid type for form field: $type");
  }

  /**
   * Add a checkbox list for many-to-many relationships
   *
   *  @param sring  the remote field name, to be displayed as checkbox value
   * @param string bridge table name (the table that implements the many-many rel.)
   * @param string the name of the table with a many-to-many assoc. to the current one
   * @param array a list of options; the order option dictates the ordering of
   *     distant rows
   */

  public function addManyToManyField ($remote_field, $bridge_table_name, $remote_table_name,
  $options=array())
  {
    // By default, sort on the remote field
    if(isset($options["order"]))
    $order = $options["order"];
    else
    $order = $remote_field;

    // Instantiate the bridge table object (dynamic instance; maybe use a factory?)
    eval ("\$remote_table =new $remote_table_name();");

    // Get the list of remote objects
    $primary = $remote_table->getPrimaryField();
    $remote_objs = $remote_table->fetchAll();
    $form_checkbox = array();

    // Create an array that will be passed to the checkbox function of the form
    foreach ($remote_objs as $remote) {
      $rem_arr = $remote->toArray();
      $form_checkbox[$rem_arr[$primary]] = $rem_arr[$remote_field];
    }
    $this->add_form_fields[$remote_field] =
    array ("remote_table" => $remote_table_name,
   				"bridge_table" => $bridge_table_name,
                "remote_primary" => $primary);
    $this->form_fields[$remote_field] = $form_checkbox;
    $this->form_fields_type[$remote_field] = self::CHECKBOX_FIELD;
    $this->headers[$remote_field] = $this->texts->get ($remote_field, $this->lang);
  }

  /**
   * getRow: gets an instance of the table_object using its key
   * @param params the HTTP parameters
   * @param an array that contains the values of the row's key
   * @param the expected format of the output, 'array' being the default
   * @return either a row or an object, depending on the required format
   */

  private function getRow ($params, $format="tableau")
  {
    // On constitue la clause WHERE
    $where = $this->keyAccess ($params, "SQL");
    $select = $this->table_object->select();
    $select->where($where);

    // Find the row with its key
    $obj = $this->table_object->fetchRow($select);

    if ($format == "tableau") {
      $row = $obj->toArray();

      // Next, get the rows from the slave table
      if (!empty($this->slave_table)) {
        $clause = $this->slaveAccess ($ligne);
        $query = "SELECT * FROM $this->slave_table WHERE $clause";
        $res = $this->bd_my_lang->execRequete($query);
        while ($sl = $this->bd_my_lang->ligneSuivante($res)) {
          foreach ($sl as $nom_att => $val_att)
          $row[$this->slave_table][$nom_att][] = $val_att;
        }
      }

      // and, of course, take as well the rows of the bridge table
      foreach ($this->add_form_fields as $remote_field_name => $remote_desc) {
        $bridge_table = $remote_desc['bridge_table'];
        $remote_table = $remote_desc['remote_table'];
        $remote_primary = $remote_desc['remote_primary'];

        $remotes = $obj->findManyToManyRowset ($remote_table, $bridge_table);
        foreach ($remotes as $remote) {
          $r_arr = $remote->toArray();
          $row[$remote_field_name][$r_arr[$remote_primary]] = true;
        }
      }
      return $row;
    }
    else
    return $obj;
  }

  /**
   * Edit method: used to manage a simple workflow for editing
   * @param object the request object
   * @return none
   */

  public function edit ($request)
  {
    // Extract the controller and action info.
    $module = $request->getModuleName();
    $control = $request->getControllerName();
    $action = $request->getActionName();

    // Set the current edit action URL (use the config to obtain the base URL)
    // Get the utilitary objects from the registry
    $registry = Zend_registry::getInstance();
    $zmax_context = $registry->get("zmax_context");
    if ($module == "default")
    $this->url = $zmax_context->config->app->base_url . "/" . $control . "/" . $action . "?1=1";
    else
    $this->url = $zmax_context->config->app->base_url
    . "/" . $module  . "/" . $control . "/" . $action . "?1=1";

    // echo "Control =  $control  Action = $action URL=$this->url<br/>";

    // Get the values of the  table form
    if ($request->getParam("show_table_form")) {
      // Get the values submitted in the table form
      foreach ($this->info['cols'] as $name) {
        $form_field_name = $this->getTblFormFieldName($name);
        if ($request->getParam($form_field_name)) {
          $this->table_form_values[$form_field_name] = $request->getParam($form_field_name);
          // Put these values in a GET query
          $this->form_table_query .= "&amp;$form_field_name="
          . urlEncode($request->getParam($form_field_name));
        }
      }
      // echo "Table form fields:";
      //  print_r ($this->table_form_values);
      $this->form_table_query = "&amp;show_table_form=1" . $this->form_table_query;
    }

    // We are only interested in POST parameters
    $paramsHTTP = $_REQUEST; // PR: do better

    // Check whether an action is required.
    if (isSet($paramsHTTP[self::ZMAX_EDIT_ACTION]))
    $action = $paramsHTTP[self::ZMAX_EDIT_ACTION];
    else
    $action = "";

    $affichage = "";
    switch ($action) {
      case self::DB_INSERT: // Insertion required
        if ($this->insertion($paramsHTTP)) {
          $affichage .= "<i>Insertion: done</i>";
          $affichage .= "<h2>Input form</h2>";
          $affichage .= $this->form(self::DB_INSERT, array());
        }
        else{
          $affichage .= $this->messageList();
          $affichage .= $this->form(self::DB_INSERT, $paramsHTTP);
        }
        break;

      case self::DB_UPDATE:
        // We must modify the row
        if ($this->update($paramsHTTP))
        $affichage .= "<i>Update: done.</i>";
        else
        $affichage .= $this->messageList();

        $ligne  = $this->getRow ($paramsHTTP);
        $affichage .= $this->form(self::DB_UPDATE, $ligne);
        break;

      case self::DB_DELETE:
        // Remove the line
        $this->delete($paramsHTTP);
        $affichage .= "<i>Deletion: done.</i>";
        $affichage .= $this->form(self::DB_INSERT, array());
        break;

      case self::DB_EDIT:
        // Search a row and edit it
        $ligne  = $this->getRow ($paramsHTTP);
        $affichage .= $this->form(self::DB_UPDATE, $ligne);
        break;
         
      default:
        $affichage .= $this->form(self::DB_INSERT, array());
        break;
    }
    $affichage .= "<br />\n"; // petite separation

    // Always show the HTML table with the content of the table
    $affichage .= "<h2><i>$this->table_label</i></h2>\n";
    $affichage .=  $this->table(array("BORDER" => 2));

    if($this->mode_show_form)
    {
      $add_url = $this->url . $this->form_table_query;
      $affichage .= "<a href='$add_url'>{$this->texts->form->add_line}</a>\n";
    }

    // Retour de la page HTML
    return $affichage;
  }

  // Add a hidden field in the form
  public function addHiddenField ($name, $value)
  {  $this->hidden_fields[$name] = $value;  }

  // Do not show a field in the table
  function hideFromTable ($name)
  {  $this->hide_from_table[$name] = true; }

  // Pour placer une valeur dans la table en cr�ation seulement
  private function set_insert_value ($field_name, $field_value)
  {
    $this->insert_values[$field_name] = $field_value;
    $this->hide_from_table ($field_name);
    $this->addHiddenField ($field_name, "");
  }

  // Pour placer une valeur dans la table en update seulement
  private function set_update_value ($field_name, $field_value)
  {
    $this->update_values[$field_name] = $field_value;
    $this->hide_from_table ($field_name);
    $this->addHiddenField ($field_name, "");
  }

  // Pour ajouter une clause de tri
  // The list must be SQL-compliant: field[, field], ...
  function setOrderBy ($fields_list)
  { $this->order_by_clause = $fields_list; }

  // Ajout d'une clause WHERE pour l'affichage du tableau
  function setWhereClause ($where_clause)
  { $this->where_clause = $where_clause;  }

  // D�finition des champs de cr�ation et derni�re modif (doit �tre de type DATETIME)
  function setRevisionField ($field_name)
  {
    $this->revision_field = $field_name;
    $this->hide_from_table ($field_name);
    $this->addHiddenField ($field_name, "");
  }

  function setCreationField ($field_name)
  {
    $this->creation_field = $field_name;
    $this->hide_from_table ($field_name);
    $this->addHiddenField ($field_name, "");
  }

  // Pour ajouter une table-esclave
  function setSlaveTable ($tbname, $foreign_keys, $nb_lines=10)
  {
    $this->slave_table = $tbname;
    $this->schema_slave = $this->bd->schemaTable($tbname);
    $this->size_slave_table = $nb_lines;
    $this->foreign_keys = $foreign_keys;

    // Par d�faut, les textes des attributs sont leurs noms
    foreach ($this->schema_slave as $nom => $options) {
      if (isSet($this->texts->texts[$nom][$this->lang])) {
        $this->headers[$nom] = $this->texts->get ($nom, $this->lang);
      }
      else
      $this->headers[$nom] = $nom;
    }
  }

  // Define a field as being auto incremented
  function setAutoIncrementedKey ($ai_name)
  {
    $this->auto_increment_key[$ai_name]=true;
  }

  // Pour indiquer la connexion de la base contenant des traductions (utilis� pour les lectures)
  function setReadOnlyDB($bd_my_lang) {	  $this->bd_my_lang = $bd_my_lang; }

  /**
   * Get teh name of a table form field
   * @param attribute name
   * @return table form field name
   */
  private function getTblFormFieldName($att_name)
  { return "table_form_" . $att_name;}

  // Pour indiquer si on utilise le mode dictionnaire. Si oui, on ins�re
  // dans le dictionnaire quand la langue de saisie ($lang) est diff�rente de la langue
  // d'un tuple dans la base (indiqu� par $lang_field)
  function setDictionaryMode($lang, $lang_field)
  {
    $this->lang = $lang;
    $this->lang_field = $lang_field;
    $this->use_dictionary = true;
  }

  // Indique si une erreur est rencontrée
  private function in_error()
  {    return $this->error_met;  }

  private  function get_messages()
  {    return $this->messages;  }

  private function add_message($message)
  {
    $this->messages[] = $message;
    $this->error_met = true;
  }

  // Creates a list of messages
  private function messageList()
  {
    $mess_list = "";
    if ($this->in_error()) {
      $mess_list = "<h3>Errors</h3>";  $mess =  "";
      foreach ($this->messages as $m)
      {
        $mess .= "<li>" . $this->texts->get($m, $this->lang) . "</li>";
      }
      $mess_list .= "<ol>$mess</ol><br/>";
    }
    return $mess_list;
  }

  // End of the class
}
?>