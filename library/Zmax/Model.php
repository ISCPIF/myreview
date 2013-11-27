<?php

/**
 * @category   Zmax
 * @package    Zmax_Model
 * @copyright  
 * @license
 * @version
 */

/**
 * This class represent the Zmax core model 
 * 
 * The class provides a few static method that implement
 * the editing of the code Zmax tables.
  * 
  * @package Zmax_Model
  * @author Philippe Rigaux
  * @todo 
  *
  */

class Zmax_Model 
{
   /**
   * Generic editing form for lang
   * @param  request             The request object
   */

  public static function editLangs($request)
  {
    // Use the generic editor
    $crud = new Zmax_Db_Edit ("Zmax_Model_Lang");
    return $crud->edit($request);
  }
  
  /**
   * Generic editing form for namespaces
   * @param  request             The request object
   */

  public static function editNamespaces($request)
  {
    // Instantiate the generic editor
    $crud = new Zmax_Db_Edit ("Zmax_Model_Namespace");
    return $crud->edit($request);
  }

  /**
   * Generic editing form for texts
   * @param  request             The request object
   */

  public static function editTranslations($request)
  {
    $crud = new Zmax_Db_Edit ("Zmax_Model_Text");

    // The lang field is a reference to the Lang table. Use a radio HTML input
    $crud->setReferenceField("name", "Zmax_Model_Lang", Zmax_Db_Edit::RADIO_FIELD);
    // Same thing for the  namespace field 
    $crud->setReferenceField("namespace", "Zmax_Model_Namespace", 
                      Zmax_Db_Edit::RADIO_FIELD);
    // Show the table form
    $crud->showTableForm();

    return $crud->edit($request);
  }

    /**
   * Generic editing form for users
   * @param  request             The request object
   */

  public static function editUsers($request)
  {
      // Use the Edit functionality to generate a CRUD workflow
    $crud = new Zmax_Db_Edit ("Zmax_Model_User"); 

     // The comp_name field comes from the Company link
    $crud->setFormField("user_admin", Zmax_Db_Edit::BOOLEAN_FIELD);
    
    // Shows how to create a boolean field
    $crud->setFormField("user_super_admin", Zmax_Db_Edit::BOOLEAN_FIELD);

    return $crud->edit($request);
  }
  
 
}
