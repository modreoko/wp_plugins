<?php
/*

Plugin Name: FLK Session Manager

Description: Manage session registrations: export, import. Extends Events Manager and Gravity Forms plugins

Version: 1.0

Author: Ivan Kubica

Author URI: http://taoisttaichi.oeg

Text Domain: FLK_PLUGIN_TEXTDOMAIN
*/

/**
 * initialize Admin menu item in the "Events"
 * 
 * @var integer $session_id
 * @var boolean $import_result
 */

define( 'FLK_PLUGIN_PATH', ABSPATH . 'wp-content/plugins/flk_session_manager/');
define( 'FLK_PLUGIN_CAPABILITY', 'session_registrations' );
define( 'FLK_PLUGIN_TEXTDOMAIN', 'flk_session_manager' );

$session_id = null; 
$import_result = null; 

function flk_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=event',
        __( 'Import sesssion attendance', FLK_PLUGIN_TEXTDOMAIN ),
        __( 'Import sessionn attendance', FLK_PLUGIN_TEXTDOMAIN ),
        FLK_PLUGIN_CAPABILITY,
        'import-session-attendance',
        'flk_import_attendance_content'
    );
}
add_action( 'admin_menu', 'flk_admin_menu' );

/**
 * add plugin User Role and capability
 */
function flk_session_roles() {
    // Gets the simple_role role object.
    $role = get_role( 'session_manager' );
    
    if( $role === null ){
        add_role(
            'session_manager',
            'Session Manager',
            array(
                FLK_PLUGIN_CAPABILITY => true,
                'gravityforms_view_entries' => true, 
            )
        );
    }
}
add_action( 'init', 'flk_session_roles', 11 );

/**
 * hook to custom action when importing data
 */
function flk_do_import_session_attendance(){
    require_once FLK_PLUGIN_PATH. 'classes/FLKSessionImporter.php';

    global $import_result;
    $import_result = FLKSessionImporter::Import();
    
    set_transient( 'flk_session_import_result', $import_result ? 'success' : 'error', 90 );

    if( !$import_result ){
        set_transient( 'flk_session_import_errors', serialize( FLKSessionImporter::getErrors() ), 90 );
    }

    wp_redirect( '/wp-admin/edit.php?post_type=event&page=import-session-attendance' );

    exit();
}
add_action( 'admin_post_import_session_attendance', 'flk_do_import_session_attendance' );

/**
 * start up screen for session registration attendance
 */
function flk_import_attendance_content() {
    global $session_id;

    $session_id = ( isset( $_GET['eid'] ) && is_numeric( $_GET['eid'] ) ) ? $_GET['eid'] : null;

    require_once FLK_PLUGIN_PATH. 'templates/import_form.php';
}

function flk_sessions_load_scripts( $hook ) {
    $allowed_hooks = array('edit.php', 'forms_page_gf_entries', 'event_page_import-session-attendance' );
    
    // Load only on ?page=sample-page
    if( !in_array( $hook, $allowed_hooks) ) {
        return null;
    }

    // Load style & scripts.
    wp_enqueue_style( 'flk_session_manager', plugins_url( 'flk_session_manager/includes/css/styles.css', null, '1.2' ) );
    wp_enqueue_script( 'flk_session_manager', plugins_url( 'flk_session_manager/includes/js/flk_session_manager.js', null, '1.2' ) );
}
add_action( 'admin_enqueue_scripts', 'flk_sessions_load_scripts' );

/**
 * security check 
 * @return boolean
 */
function has_access_to_plugin(){
    // security check - Admins ans users with dedicated plugin credential can access
    return ( current_user_can( 'administrator' )  || current_user_can( FLK_PLUGIN_CAPABILITY ) );
}

/**
 * security check if user has access to the specific Event
 * @return boolean
 */
function has_access_to_session( FLK_Event $flk_event ){
    if( current_user_can( 'administrator' ) ){
        return true;
    }

    $user = wp_get_current_user();
    global $wpdb; 

    // national editors have access only to events in country locations
    if( current_user_can('national_editor') ) {

        // country post ids assigned to user
        $user_country_post_ids = get_user_meta( $user->ID, 'country_access', true);
        $location_post_ids = array();
        // a national editor can have access to more than one country. i.e. Germany & Switzerland
        foreach($user_country_post_ids as $user_country_post_id ){
            // country code is stored in post meta
            $country_code = get_post_meta($user_country_post_id, 'country_code', true);
            
            // get post_ids for all locations within the country
            $args = array('sql_only' => true, 'country' => $country_code);
            
            // get sql query for all locations post_ids
            $sql = EM_Locations::get( $args );
            
            // get locations post_ids
            $location_post_ids += $wpdb->get_col( $sql );
        }

       // returns true when the session location post id is in array of all locations
        return in_array( $flk_event->getFlkLocation()->post_id, $location_post_ids );
    }
    
    // locations editor
    if( current_user_can('location_edtior') || current_user_can( 'session_manager') ) {
        $location_post_ids = get_user_meta( $user->ID, 'location_access', true);
        return in_array( $flk_event->getFlkLocation()->post_id, $location_post_ids );
    }

    return false;
}

// include filters
if( ( isset($_REQUEST['post_type']) && ( $_REQUEST['post_type'] == 'event-recurring' || $_REQUEST['post_type'] == 'event' ) )
    || ( isset($_REQUEST['page']) && $_REQUEST['page'] == 'gf_entries') ){
        require_once FLK_PLUGIN_PATH. 'classes/FLKGFAPI.php';
        require_once FLK_PLUGIN_PATH. 'filters.php';
}


/**
 * returns array of FLK_Events
 * 
 * @return FLK_Event[]
 */
function getAvalableSessionOptions(){
    global $current_user, $wpdb;
    $user_roles = $current_user->roles;
    $user_role  = array_shift( $user_roles );

    $location_ids = array();
    
    // find out if national editor has granted access to countrye in given the event location
    if ( current_user_can( 'session_manager' ) ) {
        $country_posts = get_user_meta( $current_user->ID , 'country_access' );
        $country_posts = implode( ",", $country_posts );
        
        if( is_array( $country_posts ) ){
            $country_posts = implode( ",", $country_posts );
        }
        
        // get all country codes we need
        $country_codes = $wpdb->get_col( "SELECT meta.meta_value 
            FROM $wpdb->postmeta AS meta 
            INNER JOIN $wpdb->posts AS post ON meta.post_id = post.id 
            WHERE post.post_type = 'country'
              AND meta.post_id IN ({$country_posts})
              AND meta.meta_key = 'country_code' " );

        $location_ids = $wpdb->get_col( "SELECT location_id 
            FROM {$wpdb->prefix}em_locations 
            WHERE location_contry = IN ('" . implode( "','", $country_codes ) . "')
             AND location_status = 1");

    // find out access for location editor
    } elseif ( ( $user_role == 'location_edtior' || current_user_can( 'session_manager') ) ) {
        $location_ids = get_user_meta( $current_user->ID , 'location_access' );
    }

    // select all locations
    $args = array(
//        'recurring' => 1,
        'event_status' => 1,
        'orderby' => 'location_name',
    );

    if( !current_user_can( 'administrator' ) ){
        $args['location_id'] = $location_ids;
    }

    $events = EM_Events::get( $args );

    return $events; 
}

// export registrations
if( isset($_REQUEST['action']) && $_REQUEST['action'] == 'export' ){
    require_once FLK_PLUGIN_PATH. 'export.php';
    add_action( 'admin_init', 'export_session_registrations' );
}