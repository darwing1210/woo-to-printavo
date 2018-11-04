<?php

/**
 * Custom hooks (actions and filters)
 * of the plugin
 */

class WooToPrintavoHooks {

	public static function loader() {
        require_once plugin_dir_path( __FILE__ ) . 'class-printavo-api.php';
    }

	/**
	 * Plugin options and settings
	 */
    public static function init() {
		self::loader();
		add_action( 'woocommerce_order_actions', array( __class__, 'woo_to_printavo_send_order_meta_box_action' ) );
		add_action( 'woocommerce_order_action_wc_custom_order_action', array( __class__, 'woo_to_printavo_process_send_order_meta_box_action' ) );
	}
	
	/**
	 * Add a custom action to order actions select box on edit order page
	 * Only added for paid orders that haven't fired this action yet
	 *
	 * @param array $actions order actions array to display
	 * @return array - updated actions
	 */
	public static function woo_to_printavo_send_order_meta_box_action( $actions ) {
		global $theorder;
		$actions['wc_custom_order_action'] = __( 'Send Order to Printavo', 'woo_to_printavo' );
		return $actions;
	}

	/**
	 * Add an order note when custom action is clicked
	 * Add a flag on the order to show it's been run
	 *
	 * @param \WC_Order $order
	 */
	public static function woo_to_printavo_process_send_order_meta_box_action( $order ) {
		
		// Init Printavo API
		$options = get_site_option( 'woo_to_printavo_options' );
		$email = $options['woo_to_printavo_field_client_email'];
		$password = $options['woo_to_printavo_field_client_password'];
		
		$api = new PrintavoAPI( $email, $password );
		$result = $api->post_create_order( $order );

		// add a order note
		// translators: Placeholders: %s is a user's display name
		$current_time = current_time( 'mysql' ); 
		$message = sprintf( __( 'Order sent to printavo on %s.', 'woo_to_printavo' ),  $current_time);
		$order->add_order_note( $message );
		
		// add the flag
		update_post_meta( $order->get_id(), '_wc_order_sent_to_printavo_date', $current_time );
	}
}
