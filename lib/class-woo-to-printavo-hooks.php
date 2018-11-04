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

	public static function woo_to_printavo_error_notice() {
		?>
			<div class="error notice">
				<p><?php _e( 'There was an error with this request', 'woo_to_printavo' ); ?></p>
			</div>
		<?php
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

		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', array( __class__, 'woo_to_printavo_error_notice' ) );
			$message = sprintf(
				__( 'There was an error sending order to printavo, %s: %s', 'woo_to_printavo' ),
				$result->get_error_code(),
				$result->get_error_message()
			);
			$order->add_order_note( $message );
			return;
		}

		$printavo_id = $result['id'];
		$visual_id = $result['visual_id'];
		$url = $result['url'];

		// Add a order note
		$message = sprintf( __( 'Order sent to printavo, Visual Id: %s, URL: %s', 'woo_to_printavo' ),  $visual_id, $url);
		
		$order->add_order_note( $message );
		
		// Add the flag
		update_post_meta( $order->get_id(), '_wc_order_sent_to_printavo_date', $current_time );
		update_post_meta( $order->get_id(), '_wc_order_printavo_id', $printavo_id );
		update_post_meta( $order->get_id(), '_wc_order_printavo_visual_id', $visual_id );
		update_post_meta( $order->get_id(), '_wc_order_printavo_url', $url );
	}
}
