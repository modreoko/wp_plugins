<?php 
/**
 * @name: export.php
 * 
 * @desc: exports
 */

// include for export to XSLX
require FLK_PLUGIN_PATH . 'classes/XLSXWriter.php';

function export_session_registrations(){
    $FLK_Event = new FLK_Event( $_GET['eid'] );
    if( $FLK_Event->is_recurring() ){
        $recurring_wevents_url = '/wp-admin/edit.php?post_type=event-recurring';
        // form id reference
        $form_id = $FLK_Event->isIntroductorySession() ? $FLK_Event->getFlkLocation()->getIntroductorySessionRegistrationFormId() : $FLK_Event->getFlkLocation()->getSessionRegistrationFormId();
    } else {
        $recurring_wevents_url = '/wp-admin/edit.php?post_type=event';
        // form id reference
        $form_id = $FLK_Event->getFlkLocation()->getEventRegistrationFormId();
    }

    // security checke
    if( !has_user_access_to_event( $FLK_Event ) ){
        header( 'Location: '.$recurring_wevents_url );
        exit;
    }

    // form id reference
    $form = GFAPI::get_form( $form_id );

    // security check - this should not happen. Only in case the form has been deleted
    if( $form === false ){
        echo $form_id. ' - no form'; 
        exit('2');
        header( 'Location: '.$recurring_wevents_url );
        exit;
    }
    
    $header = array(); // spreadsheet header
    // Spain has special request to add one extra field at the beginning
    if( $FLK_Event->getFlkLocation()->location_country == 'ES' ){
        $header[ __( '¿ASISTE?', THEME_TEXTDOMAIN ) ] = 'string';
    }

    $field_napping = array(); // se use field mappings to get
    $location_label = __( 'Location:', THEME_TEXTDOMAIN );
    $start_date_label = __( 'Start date', THEME_TEXTDOMAIN );

    $name_field_id = 0;
    
    // we need to set some variables based on the used fields
    foreach( $form['fields'] as $field ){
        // we sord by first name
        if( $field->type == 'name' ){
            foreach( $field->inputs as $input ){
                
                if( $input['name'] == 'first_name'){
                    $field_napping[$input['name']] = $input['id'];
                    $header[ __( 'First name', THEME_TEXTDOMAIN )] = 'string';
                } elseif( $input['name'] == 'last_name'){
                    $field_napping[$input['name']] = $input['id'];
                    $header[ __('Last name', THEME_TEXTDOMAIN ) ] = 'string';
                    $name_field_id = $input['id'];
                }
            }

          // proccess address fields
        } elseif( $field->type == 'address' ){
            $header[ __('Address', THEME_TEXTDOMAIN ) ] = 'string';
            foreach( $field->inputs as $input ){
                $field_napping[$input['name']] = $input['id'];
            }

          // narrow the entries by event id
        } elseif( $field->inputName == 'email' ){
            $field_napping[$field->inputName] = $field->id;
            $header[__('Email', THEME_TEXTDOMAIN )] = 'string';

            // narrow the entries by event id
        } elseif( $field->inputName == 'phone' ){
            $field_napping[$field->inputName] = $field->id;
            $header[ __('Phone number', THEME_TEXTDOMAIN )] = 'string';

            // narrow the entries by event id
        } elseif( $field->inputName == 'location_sessions' || $field->inputName == 'location_events' ){
            $event_criteria = array( 'key' => $field->id, 'value' => $FLK_Event->event_id );

        // membership ID field
        } elseif( $field->inputName == 'membership_id' ){
            $field_napping[$field->inputName] = $field->id;
            $header[ __('Membership ID', THEME_TEXTDOMAIN )] = 'string';

            // only active registrations
        } elseif( $field->inputName == 'registration_status' ){
            $status_criteria = array( 'key' => $field->id, 'value' => 1 );
        }
    }

    // add last column to mark if person has attended the session - only for recurring sessions
    if( $FLK_Event->is_recurring() ){
        $header[$FLK_Event->getNextSessionDate( 'Y-m-d' )] = 'string';
    }

    // get all session registrations
    $search_criteria = array(
        'status' => 'active',
        'field_filters' => array( $event_criteria, $status_criteria )
    );

    $sorting = array( 'key' => $name_field_id, 'direction' => 'ASC' );
    // get all entries
    $entries = GFAPI::get_entries( $form['id'], $search_criteria, $sorting, array( 'offset' => 0, 'page_size' => 100 ) );

    $sheet_name = 'Participants list';
    $user = wp_get_current_user();

    // different columns widths based on registration details if it contains address
    $widths = isset( $header['Address'] ) ?  array( 20, 20, 25, 20, 60 ): array( 20, 20, 25, 20 );

    
    if( $FLK_Event->getFlkLocation()->location_country == 'ES' ){
        array_unshift( $widths, 10 );
    }
    
    // initialize XLSX writer and set some file header
    $writer = new XLSXWriter();
    $writer->setAuthor( $user->get('first_name') );
    $writer->setCompany( 'Fung Loy Kok Institute of Taoism' );
    $writer->setTitle( 'FLK Session registrations export' );
    $writer->setSubject( 'Session registrations at ' . $FLK_Event->getFlkLocation()->location_name . ', ' . $FLK_Event->getFlkLocation()->location_town, ', ' . $FLK_Event->getFlkLocation()->location_country );
    $writer->writeSheetHeader( $sheet_name, $header, array('suppress_row' => true, 'widths' => $widths ) );

    // spreadsheet header
    if( $FLK_Event->is_recurring() ){
        $next_date_label = $FLK_Event->getRecurringDayString( 'l' );
        $next_date_label .= ', ' . $FLK_Event->getNextSessionDate( __( 'M j, Y', THEME_TEXTDOMAIN) );
        $next_date_label .= ', ' . $FLK_Event->getRecurringTimeLabel();
    } else {
        $next_date_label = $FLK_Event->getStartDateDateFormated();
    }


    // write some sheet header including location name and session occurence
    $writer->writeSheetRow( $sheet_name, array( $location_label, $FLK_Event->getFlkLocation()->location_name . ', ' . $FLK_Event->getFlkLocation()->location_town, ', ' . $FLK_Event->getFlkLocation()->location_country ) );
    $writer->writeSheetRow( $sheet_name, array( $start_date_label, $next_date_label ) );
    $writer->writeSheetRow( $sheet_name, array() );

    // add table header
    $writer->writeSheetRow( $sheet_name, array_keys( $header ) );

    // loop over all entries and populate spreadsheet
    foreach( $entries as $entry ){
        $row = array();

        if( $FLK_Event->getFlkLocation()->location_country == 'ES' ){
            $row[] = '';
        }
        
        if( isset( $header[ __('Membership ID', THEME_TEXTDOMAIN ) ] ) ){
            $row[] = $entry[$field_napping['membership_id']];
        }
        
        $row[] = $entry[$field_napping['first_name']];
        $row[] = $entry[$field_napping['last_name']];
        $row[] = $entry[$field_napping['email']];
        $row[] = $entry[$field_napping['phone']];


        // lets fill anything related to address
        if( isset( $header['Address'] ) ){
            $address_details = array(); 
            $address_fields  = array( 'street', 'city', 'post_code', 'state',  'country' ) ; 

            foreach( $address_fields as $address_field ){
                if( isset( $field_napping[$address_field] )){
                    $address_details[] = $entry[ $field_napping[$address_field] ];
                }
            }
            
            if( count( $address_details ) > 0 ){
                $row[] = implode( ', ', $address_details );
            }
        }


        $writer->writeSheetRow( $sheet_name, $row );
    }


    // create some tidy file name
    if( $FLK_Event->is_recurring() ){
        $file_name = str_replace( '-', '', $FLK_Event->getFlkLocation()->location_name . ': '. $FLK_Event->getNextSessionDate( __( 'M j, Y', FLK_PLUGIN_TEXTDOMAIN) ).' '.$FLK_Event->getRecurringTimeLabel() );
    } else {
        $file_name = $FLK_Event->getFlkLocation()->location_name . '-'.$FLK_Event->getSessionCategorySlug(). ': '. $FLK_Event->getStartDateDateFormated( __( 'M j, Y', FLK_PLUGIN_TEXTDOMAIN) );
    }

    $file_name = sanitize_file_name( $file_name );
    $file_path = $writer->tempFilename( $file_name );

    header('Content-Disposition: attachment; filename='.$file_name . '.xlsx' );
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
   // header('Content-Length: ' . filesize( $file_path ) );
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    $writer->writeToStdOut( $file_path );
//    ob_clean();
    exit;
}
