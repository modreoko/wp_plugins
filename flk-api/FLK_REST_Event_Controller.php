<?php 
/*

name: FLK_REST_Event_Controller.php

Author: Ivan Kubica

Author URI: http://taoisttaichi.oeg

Text Domain: FLK_API_TEXTDOMAIN

Description: MP 2 FLK API to create/edit/delete an event. You can test the API 
by using the https://dev.taoisttaichi.org/wp-json/flk/v1/events
*/

class FLK_REST_Event_Controller extends WP_REST_Controller {

    private $event_mapping = array(
        'event_status' => 'event_status',
        'event_name' => 'event_name',
        'event_start_time' => 'event_start_time',
        'event_end_time' => 'event_end_time',
        'event_all_day' => 'event_all_day',
        'event_start_date' => 'event_start_date',
        'event_end_date' => 'event_end_date',
        'post_content' => 'post_content',
        'event_private' => 'event_private',
        'recurrence' => 'recurrence',
        'recurrence_interval' => 'recurrence_interval',
        'recurrence_freq' => 'recurrence_freq',
        'recurrence_byday' => 'recurrence_byday',
        'recurrence_byweekno' => 'recurrence_byweekno',
        'recurrence_days' => 'recurrence_days',
        'event_start' => 'event_start',
        'event_end' => 'event_end',
        'event_timezone' => 'event_timezone',
        'event_categories' => 'event_categories',
        'external_link' => 'external_link',
        'location_id' => 'location_id'
    );

    private $session_fields = array(
        'recurrence_interval' => 'recurrence_interval',
        'recurrence_freq' => 'recurrence_freq',
        'recurrence_byday' => 'recurrence_byday',
        'recurrence_byweekno' => 'recurrence_byweekno',
        'recurrence_days' => 'recurrence_days',
        'event_start' => 'event_start',
        'event_end' => 'event_end',
        'event_timezone' => 'event_timezone',
    );

    private $common_fields = array(
        'event_status' => 'event_status',
        'event_name' => 'event_name',
        'event_start_time' => 'event_start_time',
        'event_end_time' => 'event_end_time',
        'event_all_day' => 'event_all_day',
        'event_start_date' => 'event_start_date',
        'event_end_date' => 'event_end_date',
        'post_content' => 'post_content',
        'event_private' => 'event_private',
        'recurrence' => 'recurrence',
        'external_link' => 'external_link',
        'location_id' => 'location_id'
    );

    private $route = null;

    private $edit_url = null;

    public $location_api = null; 

    private $location_address_required = false;

    public function __construct(){
        $this->version = '1';
        $this->namespace = 'flk/v' . $this->version;
        $this->base = 'events';
        $this->route = '/'. $this->namespace . '/' . $this->base;

        if ( !has_filter( 'rest_authentication_errors', 'valdateIp' ) ){
            add_filter( 'rest_authentication_errors', 'valdateIp' );
        }

        // we need to find out, if the location id is provided. 
        // When it is missing there has to be full address provided
        $raw_data = WP_REST_Server::get_raw_data();
        if( empty( $raw_data ) ){
            return true;
        }

        $route = untrailingslashit( $GLOBALS['wp']->query_vars['rest_route'] );

        $raw_data = json_decode( $raw_data );
        if( $route == $this->route && !empty( $raw_data ) ){
            $this->location_address_required = !is_numeric( $raw_data->location_id ); 
        }
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
        ), true );

        register_rest_route( $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'force' => array(
                        'default' => false,
                    ),
                ),
            ),
        ), true );
    }

    /**
     * Create one event from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function create_item( $request ) {
        $flk_event = $this->prepare_item_for_database( $request );

        // an error during the update
        if( $flk_event instanceof WP_Error ){
            return $flk_event;
        }

        // send email notification to the owner of the location
        $user = new WP_User( $flk_event->location_owner );
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $event_admin_url = get_edit_post_link( $flk_event->post_id );
        $login_url = get_site_url( null, 'back_end' );
        $message = "Hi {$user->first_name},<br/>
                <p>
                    There is a new event <a href='{$event_admin_url}'>{$flk_event->event_name}</a> created by MemberPlanet sync service.<br>
                    Log into the Admin and check the event settings. <br><br>
                    
                    Note: the link to event edit form works only when you logged into Admin<br/>
                    <a href='$login_url'>Log In to Admin</a>
                </p>";

        // we need to change the email content type to html
        add_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type', 10, 0 );
        wp_mail( $user->user_email, 'New event created', $message, $headers );
        remove_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type' );

        $response = $this->prepare_item_for_response( $flk_event, $request );
        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Update one event from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function update_item( $request ) {
        $flk_event = $this->prepare_item_for_database( $request );

        if( $flk_event instanceof FLK_Event ){
            $data = $this->prepare_item_for_response( $flk_event, $request );
            return new WP_REST_Response( $data, 200 );
        }

        new WP_Error( 'cant-update', __( 'Event was not updated', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
    }

    /**
     * Delete one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function delete_item( $request ) {
        $flk_event = new FLK_event( $request['event_id'] );

        // hardcode delete from database
        if( $flk_event instanceof FLK_event ){
            if(  $flk_event->delete( true ) ){
                return new WP_REST_Response( array('status' => true, 'status_message' => __( 'Event was deleted', FLK_API_TEXTDOMAIN ) ), 200 );
            }
        }

        // we force delete from database
        $flk_event->delete( true );
        
        return new WP_Error( 'cant-delete', __( 'Event cnould not be delete', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
    }

    /**
     * Get one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_item( $request ){
        $flk_location = new FLK_Event( $request['event_id'] );
        
        $data = $this->prepare_item_for_response( $flk_location, $request );

        if( $data['status'] ){
            return new WP_REST_Response( $data, 200 );
        }

        return new WP_Error( 'cant-update', $data['error'], array( 'status' => 500 ) );
    }

    /**
     * Prepare the item for create or update operation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_Error|FLK_Event
     */
    protected function prepare_item_for_database( $request ) {
        // a event has not supported occurence. We return error and send notification to the admins
        if( !empty( $request['has_unsupported_recurrence'] ) ){
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            $event_data = array();
            foreach( $this->event_mapping as $request_param => $event_param ){
                $event_data[] = $event_param.': '. $request[$request_param];
            }

            $user = wp_get_current_user();
            $message = "Hi Admin,<br/>
                <p>There has been received event with unsupported recurrence via API. This event was not imported.</p>
                <p>Event details received via API: </p>
                <p>".implode( '<br>', $event_data )."</p>
                <p>You can edit this event in Memberpanet</p>";

            // we need to change the email content type to html
            add_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type', 10, 0 );
            wp_mail( $user->user_email, 'New Location created', $message, $headers );
            remove_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type' );

            return new WP_Error( 'unsupported_recurrence', __( 'Event recurrence is not supported', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
        }

        // editing existing event
        if( !empty( $request['event_id'] ) ){
            $flk_event = new FLK_Event( $request['event_id'] );

            // event does not exists
            if( !$flk_event instanceof FLK_Event ){
                return new WP_Error( 'event_missing', __( 'Event with provided event_id does not exists', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
            }
        } else { // new event
            $flk_event = new FLK_Event( null );
        }

        // map all available fields from request to the location
        foreach( $this->event_mapping as $request_param => $event_param ){
            if( !empty( $request[$request_param] ) ){
                $flk_event->$event_param = empty( $request[$request_param] ) ? null : $request[$request_param];
            }
        }

        // we set some default values for new event
        if( $flk_event->event_id == null ){
            $flk_event->initDefaultValues();
        }

        // new temp location for this event
        if( $this->location_address_required ){
            $flk_location = $this->getLocationApi()->prepare_item_for_database( $request );

            // an error during the update
            if( $flk_location instanceof WP_Error ){
                return $flk_location;
            }

            // we mark this location to be hidden on find-a-location page
            $flk_location->setVirtualLocation( true );

            $data = $flk_location->save();

            // location was created
            if( $data ){
                $flk_event->location_id = $flk_location->location_id;
            } else {
                return new WP_Error( 'location_failed', __( 'Location could not be saved for this event.', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
            }
        }

        $result = $flk_event->save_api_event();
        // event saved
        if( $result ){
            return $flk_event; 
        }
        
        return new WP_Error( 'event_failed', __( 'Event could not be saved.', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
    }

    /**
     * Prepare the item for the REST response
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    public function prepare_item_for_response( $flk_event, $request ) {
        $response = array( 'status' => true );

        if( $flk_event instanceof  FLK_Event ){
            $response['event_id'] = $flk_event->event_id;
            $response['event_url'] = site_url( $flk_event->getAnyLanguageParmalink() );
            
            // we return full location details for GET
            if( $request->get_method() == WP_REST_Server::READABLE ){
                // commen fields for one-off events and sessions
                foreach( $this->common_fields as $response_field => $location_field ){
                    $response[$response_field] = $flk_event->$location_field;
                }

                // add fields for sessions
                if( $flk_event->recurrence ){
                    // commen fields for one-off events and sessions
                    foreach( $this->session_fields as $response_field => $location_field ){
                        $response[$response_field] = $flk_event->$location_field;
                    }
                }

                // add event categories
                $args = array(
                    'taxonomy' => 'class-categories',
                    'orderby' => 'slug',
                    'hide_empty' => false,
                    'fields' => 'slugs',
                    'object_ids' => $flk_event->post_id,
                );

                // get all event categories as array of slugs
                $all_class_categories_slugs = get_terms( $args );
                $response['event_categories'] = $all_class_categories_slugs; 
            }

        } else {
            $response['status'] = false;
            $response['error'] = __( 'Location does not exists', FLK_API_TEXTDOMAIN );
        }
        
        return $response;
    }
    
    /**
     * returns REST Location API Controller
     * @return FLK_REST_Location_Controller
     */
    public function getLocationApi(){
        if( $this->location_api === null ){
            $this->location_api = new FLK_REST_Location_Controller();
        }
        
        return $this->location_api;
    }

    /**
     * Check if a given request has access to create items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function permissions_check( $request ) {
        return current_user_can( 'national_editor' ) || current_user_can( 'administrator' );
    }
    
    /**
     * returns schema args for event_id
     * @return string[]
     */
    private function get_event_id_args(){
        $args = array(
            'event_id' => array(
                'description' => __( 'The Event ID.', FLK_API_TEXTDOMAIN ),
                'type'        => 'integer',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => array( $this, 'validate_event_id' ),
                'sanitize_callback' => 'flk_api_sanitize_integer',
                'required'    => true, )
        );
        
        return $args;
    }

    /**
     * validates event_id
     * @param string $value
     * @param WP_REST_Request $request Current request object.
     * @param string          $param   The name of the parameter
     * @return WP_Error|boolean
     */
    public function validate_event_id( $value, $request, $param ){
        $result = flk_api_validate_integer( $value, $request, $param );

        if( $result instanceof WP_Embed ){
            return $result; 
        }

        global $wpdb;
        $results = $wpdb->get_row( $wpdb->prepare("SELECT post_id FROM ".EM_EVENTS_TABLE." WHERE event_id=%d", $value ), ARRAY_A);
        if( empty($results['post_id']) ){
            return new WP_Error( 'event_id', sprintf( esc_html__( 'Event with event_id %1$s does not exist.', FLK_API_TEXTDOMAIN ), $value ), array( 'status' => 400 ) );
        }

        return true;
    }
    
    /**
     * returns schema args for events
     * @return array
     */
    private function get_event_edit_args(){

        // get all event categories slug used in the event_categories validation
        $args = array(
            'taxonomy' => 'class-categories',
            'orderby' => 'slug',
            'hide_empty' => false,
            'fields' => 'slugs'
        );
        $all_class_categories_slugs = get_terms( $args );        
        
        $args = array(
            'event_status' => array(
                'description' => __( 'The event status', FLK_API_TEXTDOMAIN ),
                'type'        => 'integer',
                'minimum'     => 0,
                'maximum'     => 2,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_integer',
                'sanitize_callback' => 'flk_api_sanitize_integer',
                'required'    => true, ),
            'event_name' => array(
                'description' => __( 'The event name', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 1,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'event_start_time' => array(
                'description' => __( 'The start time', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 8,
                'maxLength'   => 8,
                'pattern' => '/^\d{2}:\d{2}:\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'event_end_time' => array(
                'description' => __( 'The start time', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 8,
                'maxLength'   => 8,
                'pattern' => '/^\d{2}:\d{2}:\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'event_all_day' => array(
                'description' => __( 'The event all day duration settings', FLK_API_TEXTDOMAIN ),
                'type'        => 'boolean',
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'event_start_date' => array(
                'description' => __( 'The event start date', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 10,
                'maxLength'   => 10,
                'pattern' => '/^\d{4}\-\d{2}\-\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_date',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'event_end_date' => array(
                'description' => __( 'The event start date', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 10,
                'maxLength'   => 10,
                'pattern' => '/^\d{4}\-\d{2}\-\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_date',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'post_content' => array(
                'description' => __( 'The event description', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'maxLength'   => 600,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'event_private' => array(
                'description' => __( 'The event private switch', FLK_API_TEXTDOMAIN ),
                'type'        => 'boolean',
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'recurrence' => array(
                'description' => __( 'The event recurrence setting', FLK_API_TEXTDOMAIN ),
                'type'        => 'boolean',
                'context'     => array( 'edit', 'create' ),
                'required'    => true, ),
            'recurrence_interval' => array(
                'description' => __( 'The recurring event interval.', FLK_API_TEXTDOMAIN ),
                'type'        => 'integer',
                'minimum'     => 0,
                'maximum'     => 12,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_integer',
                'sanitize_callback' => 'flk_api_sanitize_integer',
                'required'    => false, ),
            'recurrence_freq' => array(
                'description' => __( 'The recurring event frequency.', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 5,
                'maxLength'   => 7,
                'enum'        => array( 'daily', 'weekly', 'monthly', 'yearly' ),
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'recurrence_byweekno' => array(
                'description' => __( 'The monthly recurring event period week in month.', FLK_API_TEXTDOMAIN ),
                'type'        => 'integer',
                'minimum'     => -1,
                'maximum'     => 6,
                'context'     => array( 'edit', 'create' ),
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'recurrence_byday' => array(
                'description' => __( 'The monthly/weekly recurring event period day(s) in week.', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 0,
                'maxLength'   => 13,
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'event_start' => array(
                'description' => __( 'The recurring event start date', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 10,
                'maxLength'   => 10,
                'pattern' => '/^\d{4}\-\d{2}\-\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_date',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'event_end' => array(
                'description' => __( 'The recurring event start date', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 10,
                'maxLength'   => 10,
                'pattern' => '/^\d{4}\-\d{2}\-\d{2}$/',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_date',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'event_timezone'  => array(
                'description' => __( 'The Event timezone', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 0,
                'maxLength'   => 30,
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'event_categories' => array(
                'description' => __( 'The Event categories', FLK_API_TEXTDOMAIN ),
                'type'        => 'array',
                'uniqueItems' => true,
                'items'       => array(
                    'type' => 'string',
                    'enum' => $all_class_categories_slugs,
                ),
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'external_link'  => array(
                'description' => __( 'Full URL to event page on MP', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'format'      => 'uri',
                'minLength'   => 0,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'has_unsupported_recurrence'  => array(
                'description' => __( 'Event recurrence is not supported', FLK_API_TEXTDOMAIN ),
                'type'        => 'boolean',
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            );

        // the location_id is empty, we need to add schema for the location address
        if( $this->location_address_required ){
            $location_args = $this->getLocationApi()->get_location_edit_args();
        } else {
            $location_args = $this->getLocationApi()->get_location_id_args();
        }

        return array_merge( $args, $location_args );
    }

    /**
     * returns args for event schema args
     * @param string $method
     * @see WP_REST_Controller::get_endpoint_args_for_location_schema()
     * @return array
     */
    public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ){
        $args = parent::get_endpoint_args_for_item_schema( $method );

        if ( WP_REST_Server::CREATABLE === $method ) {
            $args = array_merge( $args, $this->get_event_edit_args() );
        } elseIF(  'PUT' === $method ) {
            $args = array_merge( $args, $this->get_event_edit_args(), $this->get_event_id_args() );
        } elseIF(  WP_REST_Server::DELETABLE === $method || WP_REST_Server::READABLE === $method ) {
            $args = array_merge( $args, $this->get_event_id_args() );
        }

        return $args;
    }
}