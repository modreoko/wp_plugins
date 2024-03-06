<?php

/**
 * returns user id for of the national editor for given country. 
 * If there is not such national editor, return current user id
 * @param string $country_code - 2 characters ISO country code
 * @return number
 */
function getNationalAminForCountry( $country_code = null ){
    
    if( null === $country_code ){
        return get_current_user_id();
    }

    $national_country_id = getCountryPostIdByCode( $country_code );

    // prepare argunets for wp_user_query
    $args = array(
        'role' => 'national_editor',
        'meta_key' => 'country_access', 
        'meta_value' => '"'.$national_country_id.'"',
        'meta_compare' => 'LIKE',
        'orderby' => 'ID',
        'order' => 'ASC'
    );
    $user_query = new WP_User_Query( $args );

    // there is at least one national_editor in given country, we take the first one
    if ( !empty( $user_query->get_results() ) ) {
        $users = $user_query->get_results();
        return $users[0]->ID;
    }

    return get_current_user_id();
}


/**
 * changes email content type to html used by ep_mail()
 * @return string
 */
function flk_api_set_mail_content_type(){
    return "text/html";
}

/**
 * validated API request to be triggered only from certain IPs. 
 * 
 * @return WP_Error|boolean
 */
function valdateIp(){
    if( strpos( $_SERVER['REDIRECT_URL'], 'flk' ) === false ){
        return true;
    }

    // security check for IPs
    $ipInfo = new ipInfo ( '', 'json' );
    $ip = $ipInfo->getIPAddress();
    
    $allowed_ips = explode( ',', get_option('theme_options_flk_api_ips'));
    
    if( !in_array ($ip, $allowed_ips ) ){
        return new WP_Error( 'forbidden_access', 'Access denied', array( 'status' => 403 ) );
    }
    
    // allowed only JSON
    if( !empty( $_SERVER['CONTENT_TYPE'] ) && $_SERVER['CONTENT_TYPE'] != 'application/json' ){
        return new WP_Error( 'forbidden_access', 'Only JSON data allowed', array( 'status' => 403 ) );
    }
    
    return true;
}

/**
 * validates integer
 * @param string $value
 * @param WP_REST_Request $request Current request object.
 * @param string          $param   The name of the parameter
 * @return WP_Error|boolean
 */
function flk_api_validate_integer( $value, $request, $param ){
    $attributes = $request->get_attributes();
    
    if ( isset( $attributes['args'][ $param ] ) ) {
        $argument = $attributes['args'][ $param ];
        // Check to make sure our argument is numeric.
        if ( 'integer' !== $argument['type'] || !is_numeric( $value ) ) {
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not of type %2$s', FLK_API_TEXTDOMAIN ), $param, 'integer' ), array( 'status' => 400 ) );
        }
        
        // validate min value
        if( isset($argument['minimum']) && $value < $argument['minimum'] ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too low. Min value is %2$s', FLK_API_TEXTDOMAIN ), $param, $argument['minimum'] ), array( 'status' => 400 ) );
        }
        
        // validate max value
        if( isset($argument['maximum']) && $value > $argument['maximum'] ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too high. Max value is %2$s', FLK_API_TEXTDOMAIN ), $param, $argument['maximum'] ), array( 'status' => 400 ) );
        }
        
    } else {
        // This code won't execute because we have specified this argument as required.
        // If we reused this validation callback and did not have required args then this would fire.
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
    }
    
    return true;
}


/**
 * Our sanitization callback for parameter type of integer.
 *
 * @param mixed           $value   Value of the my-arg parameter.
 * @param WP_REST_Request $request Current request object.
 * @param string          $param   The name of the parameter
 * @return mixed|WP_Error The sanitize value, or a WP_Error if the data could not be sanitized.
 */
function flk_api_sanitize_integer( $value, $request, $param ){
    $attributes = $request->get_attributes();
    
    if ( isset( $attributes['args'][ $param ] ) ) {
        $argument = $attributes['args'][ $param ];
        // Check to make sure our argument is a string.
        if ( 'integer' === $argument['type'] ) {
            return absint( $value );
        }
    } else {
        // This code won't execute because we have specified this argument as required.
        // If we reused this validation callback and did not have required args then this would fire.
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
    }
    
    // If we got this far then something went wrong don't use user input.
    return new WP_Error( 'rest_api_sad', esc_html__( 'Something went terribly wrong.', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
}


/**
 * validates string against schema args
 * @param string $value
 * @param WP_REST_Request $request Current request object.
 * @param string          $param   The name of the parameter
 * @return WP_Error|boolean
 */
function flk_api_validate_string( $value, $request, $param ){
    $attributes = $request->get_attributes();
    
    if ( isset( $attributes['args'][ $param ] ) ) {
        $argument = $attributes['args'][ $param ];
        // Check to make sure our argument is a string.
        if ( 'string' !== $argument['type'] || !is_string( $value ) ) {
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not of type %2$s', FLK_API_TEXTDOMAIN ), $param, 'string' ), array( 'status' => 400 ) );
        }
        
        // validate string length
        $value_length = strlen( trim( $value ) );
        
        // validate required field
        if( isset( $argument['required'] ) && true === $argument['required'] && $value_length == 0 ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is required field.', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
        }
        
        // validate min. length
        if( isset( $argument['minLength'] ) && $value_length < $argument['minLength'] ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too short. Min string length is %2$s characters', FLK_API_TEXTDOMAIN ), $param, $argument['minLength'] ), array( 'status' => 400 ) );
        }
        
        // validate max length
        if( isset( $argument['maxLength'] ) && $value_length > $argument['maxLength'] ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too long. Max string length is %2$s characters', FLK_API_TEXTDOMAIN ), $param, $argument['minLength'] ), array( 'status' => 400 ) );
        }
        
        // validate pattern
        if( !empty( $argument['pattern'] ) && !preg_match( $argument['pattern'], $value) ){
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s does not match the pattern %2$s', FLK_API_TEXTDOMAIN ), $param, $argument['pattern'] ), array( 'status' => 400 ) );
        }
        
    } else {
        // This code won't execute because we have specified this argument as required.
        // If we reused this validation callback and did not have required args then this would fire.
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
    }
    
    return true;
}

/**
 * Our sanitization callback for location parameter type of string.
 *
 * @param mixed           $value   Value of the my-arg parameter.
 * @param WP_REST_Request $request Current request object.
 * @param string          $param   The name of the parameter
 * @return mixed|WP_Error The sanitize value, or a WP_Error if the data could not be sanitized.
 */
function flk_api_sanitize_string( $value, $request, $param ){
    $attributes = $request->get_attributes();
    
    if ( isset( $attributes['args'][ $param ] ) ) {
        $argument = $attributes['args'][ $param ];
        // Check to make sure our argument is a string.
        if ( 'string' === $argument['type'] ) {
            return sanitize_text_field( $value );
        }
    } else {
        // This code won't execute because we have specified this argument as required.
        // If we reused this validation callback and did not have required args then this would fire.
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
    }
    
    // If we got this far then something went wrong don't use user input.
    return new WP_Error( 'rest_api_sad', esc_html__( 'Something went terribly wrong.', FLK_API_TEXTDOMAIN ), array( 'status' => 500 ) );
}
/**
 * validates date using generic string validation plus custom validation for date
 * @param string $value
 * @param WP_REST_Request $request Current request object.
 * @param string          $param   The name of the parameter
 * @return WP_Error|boolean
 */
function flk_api_validate_date( $value, $request, $param ){
    // we run generic string validation for required fields
    $result = flk_api_validate_string( $value, $request, $param );
    if( $result instanceof WP_Error ){
        return $result;
    }

    $time = strtotime( $value );
    
    // invalid date
    if( $time === false ){
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not valid date', FLK_API_TEXTDOMAIN ), $param ), array( 'status' => 400 ) );
    }
    
    // data is too far in the past
    $min_year = date('Y') - 1;
    if( date( 'Y', $time ) < $min_year ){
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too far in the past. The min year is %2$s', FLK_API_TEXTDOMAIN ), $param, $min_year ), array( 'status' => 400 ) );
    }
    
    // data is too far in the future
    $max_year = date('Y') + 10;
    if( date( 'Y', $time ) > $max_year ){
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is too far in the future. The max year is %2$s', FLK_API_TEXTDOMAIN ), $param, $max_year ), array( 'status' => 400 ) );
    }
    
    return true;
}
