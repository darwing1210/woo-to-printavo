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

    private function get_printavo_user_id() {
        return $this->user_id;
    }

    private function get_printavo_client_email() {
        return $this->client_email;
    }

    public function handle_request_error( $key, $api_response, $error_code ) {
        $error = new WP_Error( $key, 'Unable to process request', array( 'status' => $error_code ) );
        if ( isset( $api_response['details'] ) ) {
            // $api_response['details'] can be an array or a string
            $error->errors = array( $key => is_array( $api_response['details'] ) ? $api_response['details'] : array( $api_response['details'] ) );
        } else if ( isset( $api_response['error_message'] ) ) {
            $error->errors = array( $key => array( $api_response['error_message'] ) );
        } else if ( isset( $api_response['error'] ) ) {
            $error->errors = array( $key => array( $api_response['error'] ) );
        }
        return $error;
    }

    public function parse_request( $method, $endpoint, $require_token = false, $queries = array(), $body = array() ) {
        
        if ( $require_token ) {
            $session_token = $this->get_session_token();
            if ( is_wp_error( $session_token ) ) {
                return $session_token; // Exit in case we couldn fetch token
            }
            $queries = array_merge( $queries, array(
                'email' => $this->client_email,
                'token' => $session_token
            ));
        }

        $endpoint_url = add_query_arg( $queries, $endpoint );

        // Request args
        $args = array( 'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' ) ); 
        if ( ! empty( $body ) ) {
            $args = array_merge( $args, array( 'body' => json_encode( $body) ) );
        }
        return $method( $endpoint_url, $args );
    }

	public function get_session_token() {
        
        if ( $this->session_token ) {
            return $this->session_token;
        }

        $endpoint_url = "{$this->api_url}{$this->api_version}/sessions";

        $body = array(
			'email' 	=> $this->client_email,
			'password'	=> $this->client_password
        );

        $response = $this->parse_request( 'wp_remote_post', $endpoint_url, false, array(), $body );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 201 ) {
            // Token Created
            $this->session_token = $api_response['token'];
            $this->user_id = $api_response['id'];
            return $this->session_token;
        } else {
            return $this->handle_request_error( 'session_token', $api_response, $response_code );
        }
    }

    public function get_printavo_customer_id( $email ) {
        
        $endpoint_url = "{$this->api_url}{$this->api_version}/customers/search";   
        $queries = array(
            'query'     => $email,
            'per_page'  => '1'
        );

        $response = $this->parse_request( 'wp_remote_get', $endpoint_url, true, $queries );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 ) {
            if ( isset( $api_response['data'] ) && !empty( $api_response['data'] ) ) {
                return $api_response['data'][0]['id'];
            }
            return null;
        } else {
            return $this->handle_request_error( 'customer_id', $api_response, $response_code );
        }

    }

    public function post_create_order( $order ) {

        $endpoint_url = "{$this->api_url}{$this->api_version}/orders";
        
        $completed_date = $order->get_date_created();
        
        $order_nickname = get_bloginfo('name'); // @TODO add coupon code
        
        $line_items = self::parse_order_items( $order );
        // Printavo uses porcentage for tax
        $sales_tax = ( $order->get_cart_tax() / $order->get_subtotal() ) * 100;
        
        // Attemp to Get Customer ID
        $customer_id = $this->get_printavo_customer_id( $order->get_billing_email() );
        if ( is_wp_error( $customer_id ) ) {
            return $customer_id;
        }

        $body = array(
            'user_id'                       => $this->get_printavo_user_id(), // Required
            'customer_id'                   => $customer_id, // Required
            'orderstatus_id'                => 80818, // Required @TODO set default status in settings
            'custom_created_at'             => (string) $completed_date,
            'formatted_due_date'            => $completed_date->date_i18n('m/d/Y'), // Required, format 11/11/2014
            'formatted_customer_due_date'   => $completed_date->date_i18n('m/d/Y'),
            'order_nickname'                => $order_nickname,
            'notes'                         => $order->get_customer_note(),
            'sales_tax'                     => (string) $sales_tax,
            'discount'                      => (string) $order->get_total_discount(),
            'production_notes'              => "WooCommerce Order id: {$order->get_id()}, Edit: {$order->get_edit_order_url()}",
            'lineitems_attributes'          => $line_items
        );

        $response = $this->parse_request( 'wp_remote_post', $endpoint_url, true, array(), $body );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 201 ) {
            // Order Succesefully created
            return $api_response;
        } else {
            return $this->handle_request_error( 'create_order', $api_response, $response_code );
        }
    }

    public static function parse_order_items( $order ) {
        $items = array();
        foreach( $order->get_items() as $item_id => $item ) {
            $unit_cost = $item->get_subtotal();
            $quantity = $item->get_quantity();
            $product_name = $item->get_name();

            $product = $item->get_product();
            
            // Product may not exist, we initialize parameters
            $sku = '';
            if ( $product ) {
                $sku = $product->get_sku();
            }

            $items[] = array(
                'style_number'          => $sku,
                'style_description'     => $product_name,
                'category_id'           => 45390, // Embroidery, fixed by now @TODO map product categories with printavo categories
                'unit_cost'             => $unit_cost,
                'size_other'            => $quantity, // Quantity
                'taxable'               => True,
            );

            // @TODO Get Product variation description
            // Only for product variation
            // if ( $product->is_type('variation') ) {
            //     // Get the variation attributes
            //     $variation_attributes = $product->get_variation_attributes();
            //     // Loop through each selected attributes
            //     foreach( $variation_attributes as $attribute_taxonomy => $term_slug ) {
            //         $taxonomy = str_replace('attribute_', '', $attribute_taxonomy );
            //         // The name of the attribute
            //         $attribute_name = get_taxonomy( $taxonomy )->labels->singular_name;
            //         // The term name (or value) for this attribute
            //         $attribute_value = get_term_by( 'slug', $term_slug, $taxonomy )->name;
            //     }
            // }
        }
        return $items;
    }
}