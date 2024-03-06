<?php

class FLKGFAPI{
 
    protected static $form_field_mappings = array();
    
    protected static $form = null; // stores GF form when initialized

    /**
     * stores GF Form to local value
     * @param array $form
     */
    public static function setForm( &$form ){
        self::$form = $form; 
    }

    /**
     * initialize GF Form by ID and store it to local $form
     * @param integer $form_id
     */
    public static function setFormById( $form_id ){
        self::$form = GFAPI::get_form( $form_id );
    }
    
    /**
     * initialize gravity form by form ID
     * @param integer $form_id
     * @return array|NULL
     */
    public static function getFormById( $form_id ){
        if( self::$form === null || self::$form['id'] != $form_id ){
            self::setFormById( $form_id );
        }
        
        return self::$form;
    }

    public static function hideField($input_name){
        foreach( self::$form['fields'] as $i => $field ){
            if( $field->type == 'name' ){
                foreach( $field->inputs as $input ){
                    if( $input['name'] == $input_name ){
                        unset( self::$form['fields'][$i]);
                        return true; 
                    }
                }
                // common field
            } elseif($field->inputName == $input_name ) {
                unset( self::$form['fields'][$i]);
                return true;
            }
        }
        
    }
    
    /**
     * returns Gravity Forms field id by input name
     * @param string $input_name
     * @return NULL|string
     */
    public static function findFormFieldIdByInputName( $input_name ){
        if( self::$form === null || self::$form instanceof WP_Error ){
            return null;
        }
        
        // find the input name in local storage
        if( isset( self::$form_field_mappings[$input_name] ) ){
            return self::$form_field_mappings[$input_name];
        }
        
        // Loop over all fields and return if match found.
        // Store the field id in the local mappings
        foreach( self::$form['fields'] as $field ){
            // name is complex field, we need to look at its cmponents
            if( $field->type == 'name' ){
                foreach( $field->inputs as $input ){
                    if( $input['name'] == $input_name ){
                        self::$form_field_mappings[$input_name] = $input['id'];
                        return $input['id'];
                    }
                }
                // common field
            } elseif($field->inputName == $input_name ) {
                self::$form_field_mappings[$input_name] = $field->id;
                return $field->id;
            }
        }
        
        return null;
    }
}