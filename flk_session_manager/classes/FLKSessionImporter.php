<?php
/**
 * @name: FLKSessionImporter.php
 * 
 * @author: Ivan Kubica
 * 
 * @date: 2021-03-25
 * 
 * @desc: FLK Sessions importer
 */

require_once FLK_PLUGIN_PATH. 'classes/SimpleXLSX.php';
require_once FLK_PLUGIN_PATH. 'classes/FLKGFAPI.php';

class FLKSessionImporter{

    protected static $session_id = null; // Event ID

    protected static $file_path = null; // abs path to imported file

    protected static $file_name = null; // imported file name
    
    protected static $errors = array();

    protected static $form_entries = null; // array for GF Entries

    protected static $log_errors = true; // logs all errors in WP debug file when true
    
    /**
     * returns FLK_Event ID or null
     * @return NULL|integer
     */
    public static function getSessionId(){
        if( self::$session_id === null ){
            $result = self::setSessionIdFromReqeust();

            if( !$result ){
                return false;
            }
        }

        return self::$session_id;
    }

    /**
     * set FLK_Event ID to local variable from POST or GET. Returns true on success
     * @return boolean
     */
    public static function setSessionIdFromReqeust(){
        if( isset( $_POST['eid'] ) && is_numeric( $_POST['eid'] ) ){
            self::$session_id = $_POST['eid'];
            return true; 
        } else if( isset( $_GET['eid'] ) && is_numeric( $_GET['eid'] ) ){
            self::$session_id = $_GET['eid'];
            return true;
        }

        self::addError( 'flk_session_id', 'Missing FLK Session identifier' );
        return false; 
    }

    /**
     * returns (absolute) file path to imported XLSX file
     * @return string
     */
    public static function getFilePath(){
        if( self::$file_path === null ){
            self::setFilePathFromRequest();
        }

        return self::$file_path;
    }
    
    /**
     * set absolute $file_path to local variable
     * @param string $file_path
     */
    public function setFilePath( $file_path ){
        self::$file_path = $file_path;
    }

    /**
     * set file path from request (POST) by given field name. Returns true on success
     * @param string $fied_name
     * @return boolean
     */
    public static function setFilePathFromRequest( $fied_name = 'session_attendance' ){
        if( !isset( $_FILES[$fied_name] ) ){
            self::addError( 'post_file', 'There is any uploaded file with such name: ' . $fied_name );
            return false;
        }

        $file = $_FILES[$fied_name];
        self::$file_path = $file['tmp_name'];

        return true;
    }

    /**
     * move uploaded file to final directory. returns tru on success
     * @param FLK_Event $FLK_event
     * @return boolean
     */
    public static function saveImportedFile( FLK_Event $FLK_event, $timestemp ){
        $upload_dir = wp_upload_dir();

        $import_dirname = $upload_dir['basedir'].'/flk_sessions/' . $FLK_event->getFlkLocation()->post_id . '/' . $FLK_event->post_id;
        if ( ! file_exists( $import_dirname ) ) {
            wp_mkdir_p( $import_dirname );
        }
        
        $file_name = date('Y-m-d-', $timestemp ) . wp_generate_uuid4() . '.xlsx';
        $new_file_path = $import_dirname . '/' . $file_name;

        if( move_uploaded_file( $_FILES["session_attendance"]["tmp_name"], $new_file_path ) ){
            self::$file_path = $new_file_path;
            self::$file_name = $file_name;
            return true;
        } else {
            return false;
        }
        
    }
    
    /**
     * @desc: Import Session attendance for registered members
     *  
     * @return boolean
     */
    public static function Import(){
        $session_id = self::getSessionId();

        // missing session id
        if( $session_id == null || $session_id == false ){
            return false; 
        }

        // validate XLSX file path
        if( self::getFilePath() == null ){
            self::addError( 'file_path', 'Imported file does not exists.' );
            return false;
        }

        // these are all columns that may appeer in the export
        $default_fields = array('First name', 'Last name', 'Address', 'Email', 'Phone number' );

        $FLK_event = new FLK_Event( $session_id );

        // incorrect session
        if( !$FLK_event instanceof  FLK_event){
            self::addError( 'event', 'There is not such FLK Session.' );
            return false;
        }

        // security check for current user access to this session
        if( !has_access_to_session( $FLK_event ) ){
            self::addError( 'event', 'You do not have access to this Session.' );
            return false;
        }

        $form_id = $FLK_event->getFlkLocation()->getSessionRegistrationFormId();

        // get all session registrations
        if( !self::initFormSessionEntries( $form_id, self::getSessionId() ) ){
            self::addError( 'form_entries', 'There are any entries for given form and Session.' );
            return false;
        }

        // find out if there is a session_dates field in the form. If not, we cannot store data
        $session_dates_field_id = FLKGFAPI::findFormFieldIdByInputName( 'session_dates' );
        if( $session_dates_field_id == null ){
            self::addError( 'session_dates', 'Missing field to save date for the Session' );
            return false;
        }

        if ( $xlsx = SimpleXLSX::parse( self::getFilePath() ) ) {
            $errors = array();

            foreach( $xlsx->rows() as $i => $row ) {

                // we skip first three lines since these are header only
                if( $i < 3 ){
                    continue;
                }

                // header
                if( $i == 3 ){
                    // get the session date
                    $date = array_pop( $row );
                    $date = strtotime( $date );

                    $header = array_intersect( $row, $default_fields );
                } else {
                    // when true, the member has attended a class
                    $status = array_pop( $row );
                    $status = strlen( $status ) > 0;

                    // match fields and values
                    $user_data = array_combine($header, $row);
                    //print_r( $user_data );

                    // find the stred for entry
                    $entry = self::findSessionEntryByIdentifiers( $user_data['First name'], $user_data['Last name'], $user_data['Email'] );

                    // entry not found
                    if( $entry == null ){
                        $errors[$i - 3] = "Inported participant [{$user_data['First name']} {$user_data['Last name']}, {$user_data['Email']}] was not recognized in the registered participants";
                        continue;
                    }

                    $saved_dates = unserialize( $entry[$session_dates_field_id] );
                    // update the attendace field
                    if( !array( $saved_dates ) ){
                        $saved_dates = array();
                    }

                    $date_saved = in_array( $date, $saved_dates );

                    // add the date to array for participant attended on the session
                    if( $status && !$date_saved ){
                        $saved_dates[] = $date;
                    // remove date from saved dates
                    } elseif( !$status && $date_saved ){
                        $key = array_search( $date, $date_saved);
                        unset( $date_saved[$key] );
                    }

                    $entry[$session_dates_field_id] = serialize( $saved_dates );

                    $result = GFAPI::update_entry( $entry );
                    // error on entry save
                    if( $result instanceof WP_Error ){
                        $errors[$i - 3] = "Entry data save failed: " . $errors->get_error_messages();
                    }
                }
            }

            // store errors during the import
            if( count( $errors ) > 0 ){
                self::addError( 'participants', $errors );
                return false;

            } else{

                // save imported file when moved to the destination directory
                if( self::saveImportedFile( $FLK_event, $date )){
                    $files = get_post_meta( $FLK_event->post_id, 'imported_session_attendance', true );
                    if( !$files ){
                        $files = array();
                    }

                    $files[ date( 'Y-m-d', $date )] = self::$file_name;
                    update_post_meta( $FLK_event->post_id, 'imported_session_attendance', $files );

                } else {
                    self::addError( 'file_path', 'File was not saved to the destination directory' );
                    return false;
                }
            }
            
        } else {
            self::addError( 'xlsx_parser', SimpleXLSX::parseError() );
            return false;
        }

        
        return true;
    }

    /**
     * initialize form entries. Load all active form entries and store them locally
     * 
     * @param integer $form_id
     * @param integer $session_id
     * @return boolean
     */
    public static function initFormSessionEntries( $form_id, $session_id ){
        $form = FLKGFAPI::getFormById( $form_id );

        // form validation
        if( $form == null ){
            self::addError( 'session_form', 'There is not such form with given ID: ' . $form_id );
            return false;
        }

        $session_field = null;

        $fields = GFAPI::get_fields_by_type( $form, 'radio' );
        foreach( $fields as $field) {
            if( $field->inputName == 'location_sessions' || $field->inputName == 'location_events' ){
                $session_field = $field;
                break;
            }
        }

        // missing location_session field in the form
        if( $session_field == null ){
            self::addError( 'session_field', 'Missing Gravity Form field indications location sessions. The field must have input name "location_sessions".' );
            return false;
        }

        $args = array( 'status' => 'active');
        
        $args['field_filters'] = array();
        $args['field_filters'][] = array( 'key' => $session_field->id, 'value' => $session_id );

        self::$form_entries = GFAPI::get_entries( $form_id, $args );
        return true; 
    }

    /**
     * add error with context
     * @param string $context
     * @param string $message
     */
    protected static function addError( $context, $message ){
        self::$errors[$context] = $message;

        if( self::$log_errors ){
            if( is_array( $message ) ){
                error_log( 'FLKSessionImporter error: ' . $context . ': ' . var_export( $message, true ) );
            } else {
                error_log( 'FLKSessionImporter error: ' . $context . ': ' . $message );
            }
        }
    }


    
    /**
     * get array with all errors
     * @return array
     */
    public static function getErrors(){
        return self::$errors;
    }

    /**
     * Identify form entry in collection by the first namd, last name and email address
     * Returns entry as array or null
     * 
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @return array|null
     */
    protected static function findSessionEntryByIdentifiers( $first_name, $last_name, $email ){
        $first_name_field_id = FLKGFAPI::findFormFieldIdByInputName( 'first_name' );
        $last_name_field_id = FLKGFAPI::findFormFieldIdByInputName( 'last_name' );
        $email_field_id = FLKGFAPI::findFormFieldIdByInputName( 'email' );

        foreach( self::$form_entries as $entry ){
            // entry found
            if( strtolower( $entry[$first_name_field_id] ) == strtolower( $first_name ) 
                && strtolower( $entry[$last_name_field_id] ) == strtolower( $last_name ) 
                && strtolower( $entry[$email_field_id] ) == strtolower( $email ) ){
                return $entry; 
            }
        }
        
        return null;
    }

}