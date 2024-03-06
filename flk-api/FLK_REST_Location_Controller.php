<?php 
/*

name: FLK_REST_Location_Controller.php

Author: Ivan Kubica

Author URI: http://taoisttaichi.oeg

Text Domain: FLK_API_TEXTDOMAIN

Description: MP 2 FLK API to create/edit/delete a location. You can test the API 
by using the https://dev.taoisttaichi.org/wp-json/flk/v1/location
*/

class FLK_REST_Location_Controller extends WP_REST_Controller {

    private $location_mapping = array(
        'location_name' => 'location_name',
        'location_address' => 'location_address',
        'location_town' => 'location_town',
        'location_state' => 'location_state',
        'location_postcode' => 'location_postcode',
        'location_region' => 'location_region',
        'location_country' => 'location_country',
        'email' => 'email',
        'phone' => 'phone',
        'facility_name' => 'facility_name',
    );

    private $location_search_fields = array(
        'location_address' => 'address',
        'location_town' => 'town',
        'location_state' => 'state',
        'location_postcode' => 'postcode',
        'location_region' => 'region',
        'location_country' => 'country',
    );

    private $location_important_fields = array( 'location_address', 'location_town', 'location_country' );
    
    public function __construct(){
        if ( ! has_filter( 'rest_authentication_errors', 'valdateIp' ) ){
            add_filter( 'rest_authentication_errors', 'valdateIp' );
        }
    }
    
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        $version = '1';
        $namespace = 'flk/v' . $version;
        $base = 'locations';

        register_rest_route( $namespace, '/' . $base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_litem' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
        ), true );

        register_rest_route( $namespace, '/' . $base . '/(?P<id>[\d]+)', array(
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
     * Get one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_item( $request ){
        $flk_location = new FLK_Location( $request['location_id'] );

        $data = $this->prepare_item_for_response( $flk_location, $request );
        
        if( $data['status'] ){
            return new WP_REST_Response( $data, 200 );
        }

        return new WP_Error( 'cant-update', $data['error'], array( 'status' => 500 ) );
    }

    /**
     * Prepare the item for the REST response
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    public function prepare_item_for_response( $flk_location, $request ) {
        $response = array( 'status' => true ); 

        if( $flk_location instanceof  FLK_Location ){
            $response['location_id'] = $flk_location->location_id;
            $response['location_url'] = $flk_location->get_any_language_location_permalink();

            // we return full location details for GET
            if( $request->get_method() == WP_REST_Server::READABLE ){
                $meta_fields = array(
                    'email' => 'email',
                    'phone' => 'phone',
                    'facility_name' => 'facility_name' );

                foreach( $this->location_mapping as $request_field => $location_field ){
                    $response[$request_field] = isset($meta_fields[ $location_field ] ) ? get_post_meta( $flk_location->post_id, $location_field, true ): $flk_location->$location_field;
                }
            }

        } else {
            $response['status'] = false;
            $response['error'] = __( 'Location does not exists', FLK_API_TEXTDOMAIN );
        }

        return $response;
    }

    /**
     * Create one location from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function create_litem( $request ) {
        $flk_location = $this->prepare_item_for_database( $request );

        // an error during the update
        if( $flk_location instanceof WP_Error ){
            return $flk_location;
        }

        $data = $flk_location->save_api_location();

        // location successfully saved
        if( $data ){

            // send email notification to the owner of the location
            $user = new WP_User( $flk_location->location_owner );
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $location_admin_url = get_edit_post_link( $flk_location->post_id );
            $login_url = get_site_url( null, 'back_end' );
            $message = "Hi {$user->first_name},<br/>
                <p>
                    There is a new Location <a href='{$location_admin_url}'>{$flk_location->location_name}</a> created by MemberPlanet sync service.<br>
                    Log into the Admin and complete the Location settings. <br><br>
    
                    Note: the link to Location edit form works only when you logged into Admin<br/>
                    <a href='$login_url'>Log In to Admin</a>
                </p>";

            // we need to change the email content type to html
            add_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type', 10, 0 );
            wp_mail( $user->user_email, 'New Location created', $message, $headers );
            remove_filter( 'wp_mail_content_type', 'flk_api_set_mail_content_type' );

            $response = $this->prepare_item_for_response( $flk_location, $request );
            return new WP_REST_Response( $response, 200 );
        }
        
        new WP_Error( 'cant-create', __( 'Location was not created', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
    }
    
    /**
     * Update one location from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function update_item( $request ) {
        $flk_location = $this->prepare_item_for_database( $request );

        $data = $flk_location->save_api_location();

        if( $data ){
            $data = $this->prepare_item_for_response( $flk_location, $request );
            return new WP_REST_Response( $data, 200 );
        }

        new WP_Error( 'cant-create', __( 'Location was not updated', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
    }

    /**
     * Delete one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function delete_item( $request ) {
        $flk_location = new FLK_Location( $request['location_id'] );

        // hardcode delete from database
        if( !$flk_location instanceof FLK_Location ){
            return new WP_Error( 'cant-delete', __( 'Location does not exists', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
        }

        $result = $flk_location->delete( true );
        
        if( $result ){
            $response = array( 'status' => true, 'status_message' => __( 'Location was deleted', FLK_API_TEXTDOMAIN ) ); 
            return new WP_REST_Response( $response, 200 );
        }
        
        return new WP_Error( 'cant-delete', __( 'Location could not be delete', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
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
     * modify sql query and remove condition for location_status. 
     * We meed to get all locations not only active
     * 
     * @return string
     */
    public function get_location_sql_query( $sql, $args ){
        $sql = str_replace('`(location_status`=1) AND', '', $sql );
        return $sql;
    }

    /**
     * Prepare the item for create or update operation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_Error|FLK_Location $flk_location
     */
    protected function prepare_item_for_database( $request ) {
        // editing existing location
        if( !empty( $request['location_id'] ) ){
            $flk_location = new FLK_Location( $request['location_id'] );
            error_log( 'FLK API Location update ID: ' . $request['location_id'] );

            // location does not exists
            if( !$flk_location instanceof FLK_Location ){
                return new WP_Error( 'location_missing', __( 'Location with provided location_id does not exists', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
            }

        } else { // new location
            // we try to find the location by its address
            $args = array('orderby' => array('location_id'), 'order' => 'DESC' );
            foreach( $this->location_search_fields as $request_field => $location_field ){
                if( !empty( $request[$request_field] ) ){
                    $args[$location_field] = $request[$request_field];
                }
            }

            // we modify the sql before execution by removing location_status contition
            add_filter('em_locations_get_sql', array($this, 'get_location_sql_query'), 10, 2);
            $locations = EM_Locations::get( $args );
            error_log( 'FLK API Location search results: ' . var_export( $locations, true ) );
            remove_filter('em_locations_get_sql', array($this, 'get_location_sql_query'));

            if( count( $locations ) == null ){ // there is not such location on given address
                error_log( 'FLK API NEW Location' );
                $flk_location = new FLK_Location( null );
            } else { // there are some locations matching the search criteria, take first one
                error_log( 'FLK API Location - found existing location based on the location data' );
                $flk_location = new FLK_Location( $locations[0] );
            }
        }

        // map all available fields from request to the location
        foreach( $this->location_mapping as $request_field => $location_field ){

            if( !empty( $request[$request_field] ) ){
                // the field is important. value has changed and we have to put the location back to draft to finish it
                if( in_array( $request_field, $this->location_important_fields ) 
                    && $flk_location->$location_field != $request[$request_field] ){
                        $flk_location->switch_to_draft = true;
                }

                $flk_location->$location_field = empty( $request[$request_field] ) ? null : $request[$request_field];
            }
        }

        // new location
        if( $flk_location->location_id == null ){
            $flk_location->initDefaultValues();
        }

        return $flk_location;
    }

    /**
     * validates location name
     * @param string $value
     * @param WP_REST_Request $request Current request object.
     * @param string          $param   The name of the parameter 
     * @return WP_Error|boolean
     */    
    public function validate_location_name( $value, $request, $param ){
        // we run generic string validation for required fields
        $result = flk_api_validate_string( $value, $request, $param );

        if( $result instanceof WP_Error ){
            return $result;
        }

        // TODO: do we need som extra validation?
        
        // If we got this far then the data is valid.
        return true;
    }

    /**
     * returns validation schema 
     * @return string[][]
     */
    public function get_location_edit_args(){
        // taoist.org has only one allowed country: CA
        $allowed_countries = (bool)get_option('theme_options_is_the_main_taoist_website') ? array('CA') : array('AU','AW','BE','CH','CR','DE','DK','ES','FR','GB','HU','IE','IT','MX','MY','NL','NO','NZ','PL','PT','SK','SE','UA','US');

        $args = array(
            'location_name' => array(
                'description' => __( 'The location name', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 3,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => array( $this, 'validate_location_name'),
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'location_address' => array(
                'description' => __( 'The location address', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 3,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'location_town' => array(
                'description' => __( 'The location town/city', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'minLength'   => 3,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => true, ),
            'location_state' => array(
                'description' => __( 'The location state', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'location_postcode' => array(
                'description' => __( 'The location postcode', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'maxLength'   => 10,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'location_region' => array(
                'description' => __( 'The location region', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'maxLength'   => 50,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            'location_country' => array(
                'description' => __( 'The location country', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'enum'        => $allowed_countries,
                'minLength'   => 2,
                'maxLength'   => 3,
                'context'     => array( 'edit', 'create' ),
                'required'    => true, ),
            'email' => array(
                'description' => __( 'The location email address', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'format'      => 'email',
                'minLength'   => 5,
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'phone' => array(
                'description' => __( 'The location phone number address.', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'pattern'     => '^\+?[0-9\ \.\-]{5,30}',
                'minLength'   => 5,
                'maxLength'   => 50,
                'context'     => array( 'edit', 'create' ),
                'required'    => false, ),
            'facility_name' => array(
                'description' => __( 'The facility name.', FLK_API_TEXTDOMAIN ),
                'type'        => 'string',
                'maxLength'   => 255,
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => 'flk_api_validate_string',
                'sanitize_callback' => 'flk_api_sanitize_string',
                'required'    => false, ),
            );

        return $args;
    }

    /**
     * returns schema args for location_id
     * @return string[]
     */
    public function get_location_id_args(){
        $args = array(
            'location_id' => array(
                'description' => __( 'The location ID.', FLK_API_TEXTDOMAIN ),
                'type'        => 'integer',
                'context'     => array( 'edit', 'create' ),
                'validate_callback' => array( $this, 'validate_location_id' ),
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
    public function validate_location_id( $value, $request, $param ){
        $result = flk_api_validate_integer( $value, $request, $param );

        if( $result instanceof WP_Embed ){
            return $result;
        }

        global $wpdb;
        $results = $wpdb->get_row($wpdb->prepare("SELECT post_id FROM ".EM_LOCATIONS_TABLE." WHERE location_id=%d", $value ), ARRAY_A);
        if( empty($results['post_id']) ){
            return new WP_Error( 'location_id', sprintf( esc_html__( 'Location with location_id %1$s does not exist.', FLK_API_TEXTDOMAIN ), $value ), array( 'status' => 400 ) );
        }

        return true;
    }
    
    /**
     * returns args for location schema args
     * {@inheritDoc}
     * @see WP_REST_Controller::get_endpoint_args_for_item_schema()
     */
    public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ){
        $args = parent::get_endpoint_args_for_item_schema( $method );

        if ( WP_REST_Server::CREATABLE === $method ) {
            $args = array_merge( $args, $this->get_location_edit_args() );
        } elseIF(  'PUT' === $method ) {
            $args = array_merge( $args, $this->get_location_edit_args(), $this->get_location_id_args() );
        } elseIF(  WP_REST_Server::DELETABLE === $method || WP_REST_Server::READABLE === $method ) {
            $args = array_merge( $args, $this->get_location_id_args() );
        }

        return $args;
    }


}