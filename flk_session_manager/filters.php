<?php 
require FLK_PLUGIN_PATH. 'classes/FLKSessionEntry.php';


//hide admin locations page if the user role is location_edtior/location_contributor
add_action( 'admin_init', 'events_new_removal' );
function events_new_removal() {
    if( !current_user_can('national_editor') && !current_user_can('location_edtior') && !current_user_can('administrator') ){
        add_action( 'admin_head', 'events_new_removal_scripts' );
    }
}

function events_new_removal_scripts(){
    wp_enqueue_script( 'flk_session_manager_events', plugins_url( 'flk_session_manager/includes/js/flk_admin_session_manager.js', null, '1.1' ) );
}



/**
 * add export action for recurring events
 * It exports all valid session registration into spreadsheet
 * The action is available only if the session registration is enabled
 *
 * @param array $actions
 * @param WP_Post $post
 * @return string
 */
function admin_event_list_row_actions( $actions, $post ){

    // security check
    if( !has_access_to_plugin() ){
        return $actions;
    }

    // remove duplicate and quickedit post action for users with only session_namanger role
    if( !current_user_can('national_editor') && !current_user_can('location_edtior') && !current_user_can('administrator') ){
        if( isset( $actions['duplicate'] ) ) unset( $actions['duplicate'] );
        if( isset( $actions['inline hide-if-no-js'] ) ) unset( $actions['inline hide-if-no-js'] );
    }

    if ( $post->post_type == "event-recurring" || $post->post_type == "event" ) {
        $EM_Event = em_get_event($post);
        $flk_event = new FLK_Event( $EM_Event );

        if( $flk_event->getEnabledSessionRegistration() || $flk_event->getSessionRegistrationCount() > 0){
            $gform_id = $flk_event->isIntroductorySession() ? $flk_event->getFlkLocation()->getIntroductorySessionRegistrationFormId() : $flk_event->getFlkLocation()->getSessionRegistrationFormId();
            // preview registration entries
            $url = admin_url( 'admin.php?page=gf_entries' );
            $registrations_link = add_query_arg( array( 'id' => $gform_id, 'event_id' => $flk_event->event_id ), $url );
            $actions['entries'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $registrations_link ), esc_html( __( 'Registrations', FLK_PLUGIN_TEXTDOMAIN ) ) );
            
            // link to export session registrations
            $url = admin_url( 'edit.php?post_type='.$post->post_type );
            $export_link = add_query_arg( array( 'action' => 'export', 'eid' => $flk_event->event_id ), $url );
            $actions['export'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $export_link ), esc_html( __( 'Export', FLK_PLUGIN_TEXTDOMAIN ) ) );
            
            // link to import session registrations
//            $url = admin_url( 'edit.php?post_type=' .$post->post_type );
//            $export_link = add_query_arg( array( 'page' => 'import-session-attendance', 'eid' => $flk_event->event_id ), $url );
//            $actions['import'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $export_link ), esc_html( __( 'Import', FLK_PLUGIN_TEXTDOMAIN ) ) );

            $files = get_post_meta( $flk_event->post_id, 'imported_session_attendance', true );
            
            if( is_array($files) && count( $files ) > 0){
                $upload_dir = wp_upload_dir();

                $dirname = $upload_dir['baseurl'].'/flk_sessions/' . $flk_event->getFlkLocation()->post_id . '/' . $flk_event->post_id.'/';

                $dialog_content = '<div>'. __( 'Imported spreadsheets by date', FLK_PLUGIN_TEXTDOMAIN ) ;
                foreach( $files as $date => $file_name ){
                    $dialog_content .= sprintf( '<p><a href="%s">%s</a><p>', $dirname.$file_name, $date );
                }
                $dialog_content .= '</div>';
                
                $actions['attendance'] = sprintf( '<a class="session_attendance_dialog" href="#">%s</a>', esc_html( __( 'Download attendace sheets', FLK_PLUGIN_TEXTDOMAIN ) ) );
                $actions['attendance'] .= $dialog_content;
            }
            
        }
    }
    return $actions;
}
add_filter( 'post_row_actions', 'admin_event_list_row_actions', 99, 2 );

/**
 * Admin: Recurring Events
 * @desc: filters Admin Recurring Events list columns
 * We simply create our own columns list
 *
 * @param array $columns
 */
function flk_session_columns_add( $columns ){
    $columns['session_registrations'] = __('Registrations', FLK_PLUGIN_TEXTDOMAIN );
    return $columns;
}
add_filter( 'manage_edit-event-recurring_columns' , 'flk_session_columns_add', 25, 1);
add_filter( 'manage_edit-event_columns' , 'flk_session_columns_add', 25, 1);

/**
 * Admin: Events/Recurring Events
 * @desc: fill Session registration column
 *
 * @param string $column
 */
function flk_sessions_list_columns_output($column){
    global $post, $EM_Event;
    
    if( $post->post_type == 'event' || $post->post_type == 'event-recurring' ){
        $post = $EM_Event = em_get_event($post);
        $flk_event = new FLK_Event( $EM_Event );

        if( $column == 'session_registrations' ){
            $label = '<span title="Session registrations">%d</span> / <span title="Registration limit">%d</span>';
            if( current_user_can( 'session_manager' ) || current_user_can( 'administrator' ) ){
                $label .= '<div><input type="checkbox" class="form-check-input session-registration-switch ml-2" name="session_registration_switch_%s" value="%s" %s>Enabled</div>';
            }

            $count = $flk_event->getSessionRegistrationCount();
            echo sprintf( $label, $count, $flk_event->getSessionLimit(), $flk_event->post_id, $flk_event->post_id, $flk_event->getEnabledSessionRegistration() ? ' checked' : null );
        }
    }
}
add_filter( 'manage_posts_custom_column' , 'flk_sessions_list_columns_output', 25, 1 );

/**
 * modifies entry detail view screen 
 * @param string $value
 * @param GF_Field $field
 * @param array $entry
 * @param array $form
 * @return string
 */
function update_session_registration_entry_screen( $value, $field, $entry, $form ) {
    if( !is_flk_session_registration_form( $form )  ){
        return $value;
    }

    FLKGFAPI::setForm( $form );
    FLKGFAPI::hideField('location_post_id');

    if( $field->inputName == 'registration_status' ){
        return $value == 1 ? 'active' : 'disabled';
    }
    
    if( $field->inputName == 'location_post_id' ){
        $EM_Location = new FLK_Location( $value, 'post_id' );
        return '<a href="/wp-admin/post.php?post='.$value.'&action=edit">' . $EM_Location->location_name.  '</a>';
    }

    if( $field->inputName == 'session_dates' ){
        $dates = (array)unserialize( $value );

        $dates = array_map( 'nice_dates', $dates );
        return implode( ', ' , $dates );
    }

    if( $field->inputName == 'secure_key' ){
        $location_id_field_index = FLKGFAPI::findFormFieldIdByInputName('location_post_id');
        $EM_Location = new FLK_Location( $entry[$location_id_field_index], 'post_id' );
        return '<a href="' . esc_url( $entry['source_url'] ) .'?key='.$value.'" target="_blank">'.esc_html( 'Edit entry on the location page' , FLK_PLUGIN_TEXTDOMAIN) .'</a>';
    }
    
    if( $field->inputName == 'location_events' ){
        FLKGFAPI::setForm( $form );
        $location_id_field_index = FLKGFAPI::findFormFieldIdByInputName('location_post_id');
        
        $EM_Location = new FLK_Location( $entry[ $location_id_field_index], 'post_id' );
        $EM_Location->session_event_recurring = 0; 

        $field->choices = array();
        
        foreach( $EM_Location->getSessions() as $FLK_Event ){
            $FLK_Event->setEventRegistrationEventsFieldId( $field->id  );
            $FLK_Event->setFlkLocation( $EM_Location );
            
            // session registration is not allowed
            if( !$FLK_Event->getEnabledSessionRegistration() ){
                continue;
            }
            
            // select event from existing entry
            $selected = $entry == null ? false : $entry[$field->id] == $FLK_Event->event_id;
            
            // skip the option when there are any free places and it was not selected when editing existing entry
            if( !$FLK_Event->hasFreePlaces() && !$selected ){
                continue;
            }
            
            $session_label = $FLK_Event->post_title . ', starts on: '  . $FLK_Event->getStartDateDateFormated();
            
            $session = array(
                'text' => $session_label,
                'value' => $FLK_Event->event_id,
                'isSelected' => $selected,
            );
            
            $field->choices[] = $session;
        }
    }
    
    if( $field->inputName == 'location_sessions' ){
        FLKGFAPI::setForm( $form );
        $location_id_field_index = FLKGFAPI::findFormFieldIdByInputName('location_post_id'); 

        $EM_Location = new FLK_Location( $entry[ $location_id_field_index], 'post_id' );
        $EM_Location->session_event_recurring = 1; 
        
        $field->choices = array();

        foreach( $EM_Location->getSessions() as $FLK_Event ){
            $FLK_Event->setSessionRegistrationSessionFieldId( $field->id  );
            $FLK_Event->setFlkLocation( $EM_Location );

            // session registration is not allowed
            if( !$FLK_Event->getEnabledSessionRegistration() ){
                continue;
            }

            // select event from existing entry
            $selected = $entry == null ? false : $entry[$field->id] == $FLK_Event->event_id;

            // skip the option when there are any free places and it was not selected when editing existing entry
            if( !$FLK_Event->hasFreePlaces() && !$selected ){
                continue;
            }

            $registrarion_period = $EM_Location->getSessionregistrationPeriod();
            $registration_start = $entry != null ? strtotime( rgar( $entry, 'date_created' ) ) : time();
            $registration_end = strtotime( '+'.$registrarion_period.' weeks', $registration_start );

            $label = str_replace( '%date', date( __( 'M j, Y', FLK_PLUGIN_TEXTDOMAIN),  $registration_end), __('registration expires on %date, available places: %places', FLK_PLUGIN_TEXTDOMAIN));
            $label = str_replace( '%places', $FLK_Event->getAvailableSessionPlaces(), $label );

            $session_label = $FLK_Event->getRecurringDateAndTimeLabel() . ', ' . $label;

            $session = array(
                'text' => $session_label,
                'value' => $FLK_Event->event_id,
                'isSelected' => $selected,
            );

            $field->choices[] = $session;
        }
    }
    return $value;
}
add_filter( 'gform_entry_field_value', 'update_session_registration_entry_screen', 10, 4 );

/**
 * 
 * @param string $content
 * @param stdClass $field
 * @param string $value
 * @param integer $lead_id
 * @param integer $form_id
 * @return NULL|string
 */
function flk_entry_field_content( $content, $field, $value, $lead_id, $form_id ) {
    $mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];
    
    if ( RG_CURRENT_VIEW == 'entry' ) {
        if( $mode == 'view' ){
            // do not display some fields in view screen
            $ignored_fields = array( 'location_sessions', 'location_name', 'location_events' );
            if( in_array($field->inputName, $ignored_fields) ){
                return null;
            }
        } else{
            // do not display some fields in edit screen
            $ignored_fields = array( 'secure_key', 'session_name', 'location_name', 'event_name' );
            if( in_array($field->inputName, $ignored_fields) ){
                return sprintf( '<input type="hidden" id="input_%s" name="input_%s" value="%s">', $field->id, $field->id, $value );
            }

            FLKSessionEntry::initEntry( $lead_id );
    
            // we store the registration status field for later use to display all session
            if( $field->inputName == 'registration_status' ){
                FLKSessionEntry::setSessionRegistrationStatusFieldId( $field->id );
            }

            // we store the location_post_id value for later use to get all session in this location
            if( $field->inputName == 'location_post_id' ) {
                FLKSessionEntry::setLocationPostId( $value );

                $field_row = '<tr valign="top"><td class="detail-view" id="field_%s_%s"><label class="detail-label">%s</label><div class="ginput_container ginput_container_select"><select class="large_admin gfield_select" name="input_%s" id="input_%s">%s</select></div></td></tr>';

                if( current_user_can( 'administrator' ) ){
                    $locations = EM_Locations::get();
                } else {
                    $ids = get_user_locations_ids();
                    $locations = EM_Locations::get( $ids );
                }

                $options = '';
                $option_template = '<option %s value="%s">%s</option>';
                foreach( $locations as $location ){
                    $selected = $location->post_id == $value ? 'selected' : null; 
                    $options .= sprintf( $option_template, $selected, $location->post_id, $location->location_name );
                }

                $content = sprintf( $field_row, $form_id, $field->id, $field->label, $field->id, $field->id, $options );
            }

            // modify the options for sessions
            if( $field->inputName == 'location_sessions' ) {
                $field_row = '<tr valign="top"><td class="detail-view" id="field_%s_%s"><label class="detail-label">%s</label><div class="ginput_container ginput_container_radio"><ul class="gfield_radio" id="input_%s">%s</ul></div></td></tr>';
                $option_template = '<li class="gchoice_%s_%s"><input name="input_%s" type="radio" value="%s" %s id="choice_%s_%s" tabindex="31" /><label for="choice_%s_%s" id="label_%s_%s">%s</label></li>';
                $options = '';

                $field->choices = array();
                $EM_Location = FLKSessionEntry::getFlkLocation();
                $EM_Location->session_event_recurring = 1;
                if( $EM_Location === null ){
                    error_log(  'flk_entry_field_content missing location');
                    return $content; 
                }
                $entry = FLKSessionEntry::getEntry();

                // loop over all session
                foreach( $EM_Location->getSessions() as $i =>  $FLK_Event ){
                    $FLK_Event->setSessionRegistrationSessionFieldId( $field->id  );
                    $FLK_Event->setFlkLocation( $EM_Location );

                    // session registration is not allowed
                    if( !$FLK_Event->getEnabledSessionRegistration() ){
                        continue;
                    }

                    // select event from existing entry
                    $selected = $value == $FLK_Event->event_id ? ' checked' : null ;

                    // skip the option when there are any free places and it was not selected when editing existing entry
                    if( !$FLK_Event->hasFreePlaces() && !$selected ){
                        continue;
                    }

                    // prepare registration period
                    $registrarion_period = $EM_Location->getSessionregistrationPeriod();
                    $registration_start = strtotime( rgar( $entry, 'date_created' ) );
                    $registration_end = strtotime( '+'.$registrarion_period.' weeks', $registration_start );

                    $label = str_replace( '%date', date( __( 'M j, Y', THEME_TEXTDOMAIN),  $registration_end), __('registration expires on %date, available places: %places', THEME_TEXTDOMAIN));
                    $label = str_replace( '%places', $FLK_Event->getAvailableSessionPlaces(), $label );

                    $session_label = $FLK_Event->getRecurringDateAndTimeLabel() . ', ' . $label;
                    $options .= sprintf( $option_template, $field->id, $i, $field->id, $FLK_Event->event_id, $selected, $field->id, $i, $field->id, $i, $field->id, $i, $session_label );
                }
                $content = sprintf( $field_row, $form_id, $field->id, $field->label, $field->id, $options );
            }

            // modify the options for sessions
            if( $field->inputName == 'location_events' ) {
                $field_row = '<tr valign="top"><td class="detail-view" id="field_%s_%s"><label class="detail-label">%s</label><div class="ginput_container ginput_container_radio"><ul class="gfield_radio" id="input_%s">%s</ul></div></td></tr>';
                $option_template = '<li class="gchoice_%s_%s"><input name="input_%s" type="radio" value="%s" %s id="choice_%s_%s" tabindex="31" /><label for="choice_%s_%s" id="label_%s_%s">%s</label></li>';
                $options = '';
                
                $field->choices = array();
                $EM_Location = FLKSessionEntry::getFlkLocation();
                $EM_Location->session_event_recurring = 0;
                
                if( $EM_Location === null ){
                    error_log(  'flk_entry_field_content missing location');
                    return $content;
                }
                $entry = FLKSessionEntry::getEntry();
                
                // loop over all session
                foreach( $EM_Location->getSessions() as $i =>  $FLK_Event ){
                    $FLK_Event->setEventRegistrationEventsFieldId( $field->id  );
                    $FLK_Event->setFlkLocation( $EM_Location );
                    
                    // session registration is not allowed
                    if( !$FLK_Event->getEnabledSessionRegistration() ){
                        continue;
                    }
                    
                    // select event from existing entry
                    $selected = $value == $FLK_Event->event_id ? ' checked' : null ;
                    
                    // skip the option when there are any free places and it was not selected when editing existing entry
                    if( !$FLK_Event->hasFreePlaces() && !$selected ){
                        continue;
                    }
                    
                    $session_label = $FLK_Event->post_title . ', starts on: '  . $FLK_Event->getStartDateDateFormated();
                    $options .= sprintf( $option_template, $field->id, $i, $field->id, $FLK_Event->event_id, $selected, $field->id, $i, $field->id, $i, $field->id, $i, $session_label );
                }
                $content = sprintf( $field_row, $form_id, $field->id, $field->label, $field->id, $options );
            }
            
            
            // unserialize imported dates
            if( $field->inputName == 'session_dates' ){
                $field_row = '<tr valign="top"><td class="detail-view" id="field_%s_%s"><label class="detail-label">%s</label><div class="ginput_container"><span>%s </span> %s</div></td></tr>';

                $hidden_field = sprintf( '<input type="hidden" id="input_%s" name="input_%s" value="%s">', $field->id, $field->id, $value );

                $dates = (array)unserialize( $value );
                if( count( $dates ) > 0 ){
                    $dates = array_map( 'nice_dates', $dates );
                } else {
                    $dates = '';
                }

                $content = sprintf( $field_row, $form_id, $field->id, $field->label, implode( ', ', $dates ), $hidden_field );
            }
        }
    }

    return $content;
}
add_filter( 'gform_field_content', 'flk_entry_field_content', 10, 5 );

/**
 * modify meta boxes on entry screen
 * @param array $meta_boxes
 * @param array $entry
 * @param array $form
 * @return array
 */
function modify_entry_details_meta_boxes( $meta_boxes, $entry, $form ) {
    
    $meta_boxes['submitdiv'] = array(
            'title'         => esc_html__( 'Entry', FLK_PLUGIN_TEXTDOMAIN ),
            'callback'      => array( 'FLKSessionEntry', 'display_meta_box_details' ),
            'context'       => 'side',
        );

    return $meta_boxes;
}
add_filter( 'gform_entry_detail_meta_boxes', 'modify_entry_details_meta_boxes', 10, 3 );


/**
 * The gform_get_entries_args_entry_list filter is executed immediately before entries
 * are fetched for display in the Entry List view. It provides the ability to filter all
 * arguments that are passed to the GFAPI::get_entries() method thereby allowing you
 * to filter which entries are displayed in the Entry List.
 */
function flk_session_registrations( $args ) {
    
    $form = GFAPI::get_form( $args['form_id']);
    $session_reg_foprm = is_flk_session_registration_form( $form );

    // curent form is not session registration
    if( !$session_reg_foprm ){
        if( current_user_can( 'administrator' ) ){
            return $args;
        } else {
            $form_ids = getAllowedFormIds();
            
            if( count( $form_ids ) == 0 ){
                echo '<script type="text/javascript">location.replace("/wp-admin/edit.php?post_type=event-recurring");</script>';
            }

            if( (!isset($_GET['id']) && !isset($_GET['form_id']) ) || !in_array( $_GET['id'], $form_ids ) ){
                echo '<script type="text/javascript">location.replace("/wp-admin/admin.php?page=gf_entries&id='.$form_ids[0].'");</script>';
            }

            if( count( $form_ids ) > 0 ){
                $form = GFAPI::get_form( $form_ids[0] );
                $args['form_id'] = $form_ids[0];
            }
        }
    // user is not an admin
    } elseif( !current_user_can( 'administrator' ) ){
        $form_ids = getAllowedFormIds();
        
        if( count( $form_ids ) == 0 ){
            echo '<script type="text/javascript">location.replace("/wp-admin/edit.php?post_type=event-recurring");</script>';
        }

        // the form id is does not belong to the form available in the caountries where the user has access
        if( (!isset($_GET['id']) && !isset($_GET['form_id']) ) || !in_array( $_GET['id'], $form_ids ) ){
            echo '<script type="text/javascript">location.replace("/wp-admin/admin.php?page=gf_entries&id='.$form_ids[0].'");</script>';
        }
    }

    $session_field = null;
    $location_id_field = null; 
    $fields = GFAPI::get_fields_by_type( $form, array('radio','hidden') );
    foreach( $fields as $field) {
        if( $field->inputName == 'location_sessions' || $field->inputName == 'location_events'){
            $session_field = $field;
        }
        
        if( $field->inputName == 'location_post_id' ){
            $location_id_field = $field;
        }
    }
    
    // filter entries for particular session
    if( isset( $_GET['event_id'])){
        if( !isset( $args['search_criteria']['field_filters'] ) ){
            $args['search_criteria']['field_filters'] = array();
        }

        $args['search_criteria']['field_filters'][] = array('key' => $session_field->id, 'value' => $_GET['event_id'] );

    // filter entries for locations assigned for national editor (whole country) or particular locations
    } elseif( current_user_can( 'national_editor' ) || current_user_can( 'location_edtior' ) || current_user_can( 'session_manager' ) ){
        $location_post_ids = getUserAllowedLocationPostIds();
        
        $args['search_criteria']['field_filters'][] = array(
            'key' => $location_id_field->id, 
            'operator' => 'in',
            'value' => $location_post_ids );
    }

    return $args;
}
add_filter( 'gform_get_entries_args_entry_list', 'flk_session_registrations', 10, 2 );

/**
 * filters form switch on entries page
 * @param array $forms
 * @return array
 */
function limit_form_switcher_forms( $forms ) {
    if( current_user_can( 'administrator' ) ){
        return $forms; 
    }

    $allowed_form_ids = getAllowedFormIds();
    
    // fallback when there is missing form for the user's country
    if( count( $allowed_form_ids ) == 0 ){
        return array();
    }
    
    foreach( $forms as $i => $form ){
        if( !in_array( $form->id , $allowed_form_ids ) ){
            unset( $forms[$i] );
        }
    }

    return $forms; 
}
add_filter( 'gform_form_switcher_forms', 'limit_form_switcher_forms', 10, 1 );

/**
 * filter modifies links above the entries list
 * @param array $filter_links
 * @param array $form
 * @param boolean $include_counts
 * @return array
 */
function flk_session_registration_filter_links( $filter_links, $form, $include_counts ){

    // return links when the form is not used for session registrations
    if( !is_flk_session_registration_form( $form )  ){
        return $filter_links;
    }
    $session_field = null;
    $location_id_field = null;

    $fields = GFAPI::get_fields_by_type( $form, array( 'radio', 'hidden' ) );
    foreach( $fields as $field) {
        if( $field->inputName == 'location_sessions' || $field->inputName == 'location_events' ){
            $session_field = $field;
        }

        if( $field->inputName == 'location_post_id' ){
            $location_id_field = $field;
        }
    }

    unset($filter_links[2]);

    // filter links for particular event
    if( isset( $_GET['event_id']) ){
        $session_filter = array('key' => $session_field->id, 'value' => $_GET['event_id'] );

        $filter_links[0]['field_filters'][] = $session_filter;
        $filter_links[1]['field_filters'][] = $session_filter;
        $filter_links[3]['field_filters'][] = $session_filter;
    }

    // filter numbers for national/location editors
    if( current_user_can( 'national_editor' ) || current_user_can( 'location_editor' ) ){
        $location_post_ids = getUserAllowedLocationPostIds();

        $location_filter = array('key' => $location_id_field->id, 'operator' => 'in', 'value' => $location_post_ids );

        // total
        $search_criteria = array('field_filters' => array( $location_filter ));
        $filter_links[0]['count'] = GFAPI::count_entries( $form['id'], $search_criteria );
        $filter_links[0]['field_filters'][] = $location_filter;

        // unread
        $search_criteria1 = array(
            'status' => 'active',
            'is_read' => 0,
            'field_filters' => array( $location_filter ));
        $filter_links[1]['count'] = GFAPI::count_entries( $form['id'], $search_criteria1 );
        $filter_links[1]['field_filters'][] = $location_filter;

        // trash
        $search_criteria3 = array(
            'status' => 'trash',
            'field_filters' => array( $location_filter ));
        $filter_links[3]['count'] = GFAPI::count_entries( $form['id'], $search_criteria3 );
        $filter_links[3]['field_filters'][] = $location_filter;
    }

    return $filter_links;
}
add_filter( 'gform_filter_links_entry_list', 'flk_session_registration_filter_links', 10, 3 );

/**
 * entry list field filter
 * @param array $field_filters
 * @param array $form
 * @return array
 */
function flk_session_registration_update_filters( $field_filters, $form ){
    // return links when the form is not used for session registrations
    if( !is_flk_session_registration_form( $form )  ){
        return $field_filters;
    }

    $ignored_fields = array( 'Starred', 'IP Address', 'Source URL', 'Payment Status', 'Payment Date', 'Payment Amount', 'Transaction ID', 'User' ); 
    
    foreach ( $field_filters as $i => $filter ){
        if( in_array( $filter['text'], $ignored_fields) ){
            unset( $field_filters[$i]);
        }

        // update the filter key to include only avalable sessions as dropdown
        if( $filter['text'] == 'Sessions' ){
            $filter['operators'] = array('is');
            $filter['values'] = array(); 
            
            $location_ids = getUserAllowedLocationPostIds('location_id');
            
            foreach( $location_ids as $location_id ){
                $EM_Location = new FLK_Location( $location_id );
                
                if( !$EM_Location->hasSessionRegistration() ){
                    continue;
                }
                
                foreach( $EM_Location->getSessions() as $FLK_Event ){
                    $FLK_Event->setSessionRegistrationSessionFieldId( $filter['key'] );
                    $FLK_Event->setFlkLocation( $EM_Location );

                    // session registration is not allowed
                    if( !$FLK_Event->getEnabledSessionRegistration() ){
                        continue;
                    }

                    $session_label = $FLK_Event->getRecurringDateAndTimeLabel();

                    $filter['values'][] = array(
                        'text' => $EM_Location->post_title . ': '.$session_label,
                        'value' => $FLK_Event->event_id,
                        'isSelected' => false,
                    );
                }
            }
            
            $field_filters[$i] = $filter;
        }
    }

    return $field_filters;
}
add_filter( 'gform_field_filters', 'flk_session_registration_update_filters', 10, 2 );


/**
 * return form ids for all forms available for a user other than administrator
 * @return boolean
 */
function getAllowedFormIds(){
    $allowed_form_ids = array();

    if( !current_user_can( FLK_PLUGIN_CAPABILITY ) ){
        return $allowed_form_ids;
    }

    $user = wp_get_current_user();
    
    // find out if national editor has granted access to countrye in given the event location
    if ( current_user_can( 'national_editor' ) ) {
        $country_posts = get_user_meta( $user->ID , 'country_access', true );

        foreach( $country_posts as $country_post_id ){
            $allowed_form_ids[] = get_post_meta( $country_post_id, 'session_registration_form', true );
            $allowed_form_ids[] = get_post_meta( $country_post_id, 'introductory_session_registration_form', true );
        }

    // find out access for location editor
    } elseif ( current_user_can( 'location_edtior' ) || current_user_can( 'session_manager') ) {
        $location_post_ids = get_user_meta( $user->ID , 'location_access', true );
        
        foreach( $location_post_ids as $location_post_id ){
            $FLK_Location = new FLK_Location( $location_post_id, 'post_id' );
            $form_id = $FLK_Location->getSessionRegistrationFormId();

            if( $form_id != null && !in_array( $form_id, $allowed_form_ids ) ){
                $allowed_form_ids[] = $form_id;
            }

            $form_id = $FLK_Location->getIntroductorySessionRegistrationFormId();
            
            if( $form_id != null && !in_array( $form_id, $allowed_form_ids ) ){
                $allowed_form_ids[] = $form_id;
            }

        }
    }

    return $allowed_form_ids;
}

function nice_dates($date) {
    return $date != null ? date( 'Y-m-d', $date ) : 'N/A';
}

/**
 * modify entry list column data
 * @param string $value
 * @param integer $form_id
 * @param integer $field_id
 * @param array $entry
 * @param string $query_string
 * @return string
 */
function flk_session_entries_column_data( $value, $form_id, $field_id, $entry, $query_string ) {
    
    $form = FLKGFAPI::getFormById( $form_id );

    // we modify only session registrations
    if( !is_flk_session_registration_form( $form ) ){
        return $value; 
    }
    
    $field = GFAPI::get_field( $form_id, $field_id );

    if( $field == false ){
        return $value;
    }
    
    // session registration status
    if( $field->inputName == 'registration_status' ){
        return $value == 1 ? 'active' : 'disabled';
    }
    
    // display session dates in readable format
    if( $field->inputName == 'session_dates' ){
        $dates = (array)unserialize( $value );

        $dates = array_map( 'nice_dates', $dates );

        $dates_count = count( $dates );
        if( $dates_count == 0){
            return 'No dates available';
        } elseif( $dates_count == 1){
            return $dates[0];
        } else {
            $string = '<a href="#" class="link_to_preview">%s<span>%s</span></a>';
            $count_label = $dates_count . ' dates';
            return sprintf( $string, $count_label, implode( ', ' , $dates ) );
        }
    }

    // turn the secure_key into link to location page and trigger registration form
    if( $field->inputName == 'secure_key' ){
        return '<a href="' . esc_url( $entry['source_url'] ) .'?key='.$value.'" target="_blank">'.esc_html( 'Edit entry on the location page' , FLK_PLUGIN_TEXTDOMAIN) .'</a>';
    }

    if( $field->inputName == 'location_name' ){
        $field_id = FLKGFAPI::findFormFieldIdByInputName( 'location_post_id' );
        FLKSessionEntry::setLocationPostId( $entry[$field_id] );
        $FLK_Location = FLKSessionEntry::getFlkLocation();
        
        return '<a href="/wp-admin/post.php?post='.$value.'&action=edit">' . $FLK_Location->location_name.  '</a>';
    }
    
    return $value;
}
add_filter( 'gform_entries_column_filter', 'flk_session_entries_column_data', 10, 5 );

/**
 * returns true when the form is used for Session registrations
 * @param array $form - GF Form object
 * @return boolean
 */
function is_flk_session_registration_form( $form ){
    return strpos( $form['cssClass'], 'session_registration') !== false;
}

/**
 * returns array of post_id (location_id) for current user that is national_editor or location_editor
 * 
 * @param string $column
 * @return array
 */
function getUserAllowedLocationPostIds( $column = 'post_id'){
    global $wpdb; 

    $user = wp_get_current_user();
    $ids = array(); 
    
    // get all location Post Ids for national editor
    if( current_user_can( 'national_editor') ){
        $country_post_ids = get_user_meta( $user->ID, 'country_access', true );

        foreach( $country_post_ids as $country_post_id ){
            $country_code = get_post_meta( $country_post_id, 'country_code', true );
            $country_ids = $wpdb->get_col( "SELECT $column FROM {$wpdb->prefix}em_locations 
                    WHERE location_country = '" . $country_code . "'" );
            
            $ids = array_merge( $ids, $country_ids );
        }
    } elseif( current_user_can( 'location_edtior') || current_user_can( 'session_manager') ){
        $ids = get_user_meta( $user->ID, 'location_access', true );

        if( $column == 'location_id' ){
            $ids = $wpdb->get_col( "SELECT location_id FROM {$wpdb->prefix}em_locations
                    WHERE post_id IN (" . implode( ',', $ids ) . ")" );
        }
    }
    
    return $ids;
}
