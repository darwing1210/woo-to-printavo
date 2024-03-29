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

    public function parse_request( $method, $endpoint, $require_token = false, $queries = array(), $body = array(), $extra_args = array() ) {
        
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
        $args = array_merge( array( 'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' ) ), $extra_args );
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

    public function logout() {
        $endpoint_url = "{$this->api_url}{$this->api_version}/sessions";;
        $extra_args = array( 'method' => 'DELETE' );

        $response = $this->parse_request( 'wp_remote_request', $endpoint_url, true, array(), array(), $extra_args );

        return $response;
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

    public function post_create_printavo_customer( $order ) {
        $endpoint_url = "{$this->api_url}{$this->api_version}/customers";
        $blog_name = get_bloginfo('name' );
        $body = array(
            'first_name'    => $order->get_billing_first_name(),
            'last_name'     => $order->get_billing_last_name(),
            'company'       => $order->get_billing_company(),
            'customer_email'=> $order->get_billing_email(),
            'phone'         => $order->get_billing_phone(),
            'shipping_address_attributes' => array(
                'address1'  => $order->get_shipping_address_1(),
                'address2'  => $order->get_shipping_address_2(),
                'city'      => $order->get_shipping_city(),
                'country'   => $order->get_shipping_country(),
                'state'     => $order->get_shipping_state(),
                'zip'       => $order->get_shipping_postcode()
            ),
            'billing_address_attributes' => array(
                'address1'  => $order->get_billing_address_1(),
                'address2'  => $order->get_billing_address_2(),
                'city'      => $order->get_billing_city(),
                'country'   => $order->get_billing_country(),
                'state'     => $order->get_billing_state(),
                'zip'       => $order->get_billing_postcode()
            ),
            'extra_notes'  => "Site: {$blog_name}"
        );

        $response = $this->parse_request( 'wp_remote_post', $endpoint_url, true, array(), $body );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 201 ) {
            return $api_response;
        } else {
            return $this->handle_request_error( 'create_customer', $api_response, $response_code );
        }
    }

    public function get_or_create_printavo_customer( $order ) {
        // Try to get
        $customer_id =  $this->get_printavo_customer_id( $order->get_billing_email() );
        if ( $customer_id || is_wp_error( $customer_id ) ) {
            return $customer_id;
        }
        $created_customer = $this->post_create_printavo_customer( $order );
        if ( is_wp_error( $created_customer ) ) {
            return $created_customer;
        }
        return $created_customer['id'];
    }


    public function post_create_order( $order ) {

        $endpoint_url = "{$this->api_url}{$this->api_version}/orders";
        $options = get_site_option( 'woo_to_printavo_options' );
        
        $created_date = $order->get_date_created();
        $due_date = clone $created_date->modify('+7 day');
        
        // By Requirement Nickname is t he COUPON_CODES + Blog Name
        $order_nickname = get_bloginfo('name' ); // By Default just the Blog name
        $coupons = implode( ', ' , $order->get_used_coupons() );
        if ( !empty( $coupons ) ) {
            $order_nickname = implode( " - ", array( $coupons, $order_nickname ) );
        }
        
        $line_items = self::parse_order_items( $order );
        // Printavo uses porcentage for tax
        $sales_tax = ( $order->get_cart_tax() / ( $order->get_subtotal() - $order->get_discount_total() ) ) * 100;
        
        // Attemp to Get Customer ID
        $customer_id = $this->get_or_create_printavo_customer( $order );
        if ( is_wp_error( $customer_id ) ) {
            return $customer_id;
        }

        $body = array(
            'user_id'                       => $this->get_printavo_user_id(), // Required
            'customer_id'                   => $customer_id,
            'orderstatus_id'                => $options['woo_to_printavo_field_order_default_status'],
            'custom_created_at'             => (string) $created_date,
            'formatted_due_date'            => $created_date->date_i18n('m/d/Y'), // Required, format 11/11/2014
            'formatted_customer_due_date'   => $created_date->date_i18n('m/d/Y'),
            'order_nickname'                => $order_nickname,
            'notes'                         => $order->get_customer_note(),
            'sales_tax'                     => (string) $sales_tax,
            'discount'                      => (string) $order->get_discount_total(),
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

    public function get_printavo_order_statuses() {
        $endpoint_url = "{$this->api_url}{$this->api_version}/orderstatuses";

        $queries = array(
            'per_page'  => '100' // @TODO handle pagination
        );

        $response = $this->parse_request( 'wp_remote_get', $endpoint_url, true, $queries );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 ) {
            if ( isset( $api_response['data'] ) && !empty( $api_response['data'] ) ) {
                return $api_response['data'];
            }
            return null;
        } else {
            return $this->handle_request_error( 'order_statuses', $api_response, $response_code );
        }
    }

    public function get_printavo_product_categories() {
        $endpoint_url = "{$this->api_url}{$this->api_version}/categories";

        $queries = array(
            'per_page'  => '100' // @TODO handle pagination
        );

        $response = $this->parse_request( 'wp_remote_get', $endpoint_url, true, $queries );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 ) {
            if ( isset( $api_response['data'] ) && !empty( $api_response['data'] ) ) {
                return $api_response['data'];
            }
            return null;
        } else {
            return $this->handle_request_error( 'product_categories', $api_response, $response_code );
        }
    }

    public static function parse_order_items( $order ) {
        $valid_sizes = array( 'yxs', 'ys', 'ym', 'yl', 'yxl', 'xs', 's', 'm', 'l', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', 'other' );
        $size_key = 'other'; // Default key

        $groups = array();

        foreach( $order->get_items() as $item_id => $item ) {
            $quantity = $item->get_quantity();
            $unit_cost = $item->get_subtotal() / $quantity;
            $product_name = $item->get_name();

            $product = $item->get_product();
            
            // Product may not exist, we initialize parameters
            $sku = '';
            $color = '';
            $terms = array();
            $printavo_category = null;  // @TODO set default category on settings
            if ( $product ) {
                $sku = $product->get_sku();
                $color = $product->get_attribute( 'color' );
                $terms = wp_get_post_terms( $item->get_product_id(), 'product_cat', array( 'fields' => 'ids' ) );
                // This maps sizes
                $size = $product->get_attribute( 'size' );
                if ( $size && in_array( strtolower( $size ), $valid_sizes ) ) {
                    $size_key = strtolower( $size );
                }
            }

            // Setting a category
            foreach ( $terms as $term_id ) {
                $cat = get_term_meta( $term_id, 'woo_to_printavo_category', true );
                if ( $cat ) {
                    $printavo_category = $cat;
                    break;
                }
            }

            // Extract product name without meta data
            $name_parts = explode(' - ', $product_name);
            $clean_name = "{$name_parts[0]}, {$color}";
            $usize = strtoupper($size_key);

            if ( array_key_exists( $clean_name, $groups ) ) {
                $groups[$clean_name]["size_{$size_key}"] = $quantity;
                $groups[$clean_name]['style_description'] .= "{$usize} - ${quantity}" . PHP_EOL;
            } else {
                $description = "{$clean_name}" . PHP_EOL .
                               "{$usize} - ${quantity}" . PHP_EOL;
                $groups[$clean_name] = array(
                    'style_number'          => $sku,
                    'style_description'     => $description,
                    'category_id'           => $printavo_category,
                    'unit_cost'             => $unit_cost,
                    'color'                 => $color,
                    "size_{$size_key}"      => $quantity, // Quantity
                    'taxable'               => True,
                );
            }
        }

        $items = array();
        foreach( $groups as $item ) {
            $items[] = $item;
        }
        return $items;
    }
}