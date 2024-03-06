<?php
/*
Plugin Name: FLK API

Description: One way sync for events and locations from MemberPlanet

Version: 0.6

Author: Ivan Kubica

Author URI: https://www.taoisttaichi.oeg, https://www.taoist.oeg

Text Domain: FLK_API_TEXTDOMAIN
*/

define( 'FLK_API_TEXTDOMAIN', 'flk_api' );

// this plugin works only when called for the FLK API
if( !empty($_SERVER['REDIRECT_URL']) && strpos( $_SERVER['REDIRECT_URL'], 'flk' ) !== false ){

    /**
     * we need to add some more arguments to the registered post types: location, event, event-recurring
     */
    function flk_api_post_types($args, $post_type){
        // extend location post type
        if ($post_type == 'location' ){
            $args['rest_controller_class'] = 'FLK_REST_Location_Controller';
            $args['show_in_rest'] = true;
        }

        if( $post_type == 'event' ){
            $args['rest_controller_class'] = 'FLK_REST_Event_Controller';
            $args['show_in_rest'] = true;
        }

        return $args;
    }
    add_filter( 'register_post_type_args', 'flk_api_post_types', 10, 2 );

    require 'functions.php';
    
    /**
     * initialize REST API
     */
    function init_flk_api(){
        require 'FLK_REST_Location_Controller.php';
        require 'FLK_REST_Event_Controller.php';
        
        $location_api = new FLK_REST_Location_Controller();
        $location_api->register_routes();

        $event_api = new FLK_REST_Event_Controller();
        $event_api->location_api = $location_api;
        $event_api->register_routes();
        
    }

    add_action( 'rest_api_init', 'init_flk_api' );
}
