<?php

/**
 * Small Wrapper to connect to printavo, IDK i'm not a PHP developer (:S)
 */

class PrintavoAPI {

	/**
	 * Plugin options and settings
     */

	protected $api_version = 'v1';

	protected $api_url = 'https://www.printavo.com/api/';
    
    protected $client_email;

    protected $client_password;

    protected $session_token;

    protected $user_id;

    protected $connected = false;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 */
	public function __construct( $client_email, $client_password ) {
        $this->client_email = $client_email;
        $this->client_password = $client_password;
	}

	public function get_access_token() {
        $endpoint_url = "{$this->api_url}{$this->api_version}/sessions";
        
        $args = array(
			'email' 	=> $this->client_email,
			'password'	=> $this->client_password
        );

		$response = wp_remote_post(
			$endpoint_url,
			array( 
                'body' 		=> json_encode( $args ),
                'headers' 	=> array( 'Content-Type' => 'application/json; charset=utf-8' )
			)
        );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 201 ) { // Creates a new token
            $this->session_token = $api_response['token'];
            $this->user_id = $api_response['id'];
            $this->connected = true;
            return $this->session_token;
        } else {
            $error = new WP_Error( 'session_token', 'Unable to get session token', array( 'status' => $response_code ) );
            if ( isset( $api_response['details'] ) ) {
                $error->errors = array( 'session_token' => is_array( $api_response['details'] ) ? $api_response['details'] : array( $api_response['details'] ) );
            } else {
                $error->errors = array( 'session_token' => array( $api_response['error_message'] ) );
            }
            return $error;
        }
    }
    
    public function is_connected() {
        return $this->connected;
    }

}