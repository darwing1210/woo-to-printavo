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

    protected $session_token = false;

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

	public function get_session_token() {
        
        if ( $this->session_token ) {
            return $this->session_token;
        }

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

    private function get_printavo_user_id() {
        return $this->user_id;
    }

    public function get_printavo_customer_id( $email ) {
        // @TODO dont repeat youself, wrap this in an function/object
        $session_token = $this->session_token;
        if ( ! $session_token ) {
            $session_token = $this->get_session_token();
        }
        
        $endpoint_url = add_query_arg( 
            array(
                'email'     => $this->client_email,
                'token'     => $session_token,
                'query'     => $email,
                'per_page'  => '1'
            ),
            "{$this->api_url}{$this->api_version}/customers/search"
        );

        $response = wp_remote_get( $endpoint_url );
        
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
            return WP_Error( 'customer_id', 'Error retriving Customer', array( 'status' => $response_code ) );
        }

    }

    public static function parse_order_items( $order ) {
        $items = array();
        foreach( $order->get_items() as $item_id => $item ) {
            $unit_cost = $item->get_subtotal();
            $quantity = $item->get_quantity();
            $product_name = $item->get_name();

            $product = $item->get_product();
            $sku = $product->get_sku();

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

    public function post_create_order( $order ) {

        $session_token = $this->session_token;
        if ( ! $session_token ) {
            $session_token = $this->get_session_token();
        }

        $completed_date = $order->get_date_created();
        $order_nickname = get_bloginfo('name'); // @TODO add coupon code

        $line_items = self::parse_order_items( $order );

        // Printavo uses porcentage for tax
        $sales_tax = ( $order->get_cart_tax() / $order->get_subtotal() ) * 100;

        // @TODO validate errors
        $customer_id = $this->get_printavo_customer_id( $order->get_billing_email() ); 

        $args = array(
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

        $endpoint_url = add_query_arg( 
            array(
                'email' => $this->client_email,
                'token' => $session_token,
            ),
            "{$this->api_url}{$this->api_version}/orders"
        );

        $response = wp_remote_post(
            $endpoint_url,
            array( 
                'body'      => json_encode( $args ),
                'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' )
            )
        );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 201 ) {
            // Order Succesefully created
            return $this->api_response;
        } else {
            return WP_Error( 'create_order', 'Error creating Order', array( 'status' => $response_code ) );
        }

    }
    
    public function is_connected() {
        return $this->connected;
    }

}