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
        add_action( 'woocommerce_thankyou', array( __class__, 'woo_to_printavo_after_checkout' ), 10, 1 );

        // Category field
        add_action( 'product_cat_add_form_fields', array( __class__, 'woo_to_printavo_add_category_meta_field' ), 10, 1 );
        add_action( 'product_cat_edit_form_fields', array( __class__, 'woo_to_printavo_edit_category_meta_field' ), 10, 1 );
        add_action( 'edited_product_cat', array( __class__, 'woo_to_printavo_save_category_custom_meta' ), 10, 1 );
        add_action( 'create_product_cat', array( __class__, 'woo_to_printavo_save_category_custom_meta' ), 10, 1 );   
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
     * Sends an order to printavo
     *
     * @param \WC_Order $order
     */
    public static function send_order_to_printavo( $order ) {
        // Init Printavo API
        $options = get_site_option( 'woo_to_printavo_options' );
        $email = $options['woo_to_printavo_field_client_email'];
        $password = $options['woo_to_printavo_field_client_password'];
        
        $api = new PrintavoAPI( $email, $password );
        $result = $api->post_create_order( $order );
        $api->logout();

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

    /**
     * Add an order note when custom action is clicked
     * Add a flag on the order to show it's been run
     *
     * @param \WC_Order $order
     */
    public static function woo_to_printavo_process_send_order_meta_box_action( $order ) {
        self::send_order_to_printavo( $order );
    }

    /**
     * Hook to send order automatically to printavo after success checkout
     *
     * @param int $order_id
     */
    public static function woo_to_printavo_after_checkout( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $options = get_site_option( 'woo_to_printavo_options' );
        $stored_value = isset( $options[ 'woo_to_printavo_field_auto_send' ] ) ? $options[ 'woo_to_printavo_field_auto_send' ] : false;
        if ( $stored_value && $stored_value == 'on' ) {
            $order = wc_get_order( $order_id );
            self::send_order_to_printavo( $order );
        }
    }


    //Product Cat Create page
    public static function woo_to_printavo_add_category_meta_field() {
        ?>
        <div class="form-field">
            <label for="woo_to_printavo_category"><?php _e('Printavo Category', 'woo_to_printavo'); ?></label>
            <input type="text" name="woo_to_printavo_category" id="woo_to_printavo_category">
            <p class="description"><?php _e('Select Printavo Category', 'woo_to_printavo'); ?></p>
        </div>
        <?php
    }

    //Product Cat Edit page
    public static function woo_to_printavo_edit_category_meta_field( $term ) {

        //getting term ID
        $term_id = $term->term_id;

        // retrieve the existing value(s) for this meta field.
        $printavo_category_id = get_term_meta( $term_id, 'woo_to_printavo_category', true );
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="woo_to_printavo_category"><?php _e( 'Printavo Category', 'woo_to_printavo' ); ?></label></th>
            <td>
                <input type="text" name="woo_to_printavo_category" id="woo_to_printavo_category" value="<?php echo esc_attr( $printavo_category_id ) ? esc_attr( $printavo_category_id ) : ''; ?>">
                <p class="description"><?php _e('Select Printavo Category', 'woo_to_printavo'); ?></p>
            </td>
        </tr>
        <?php
    }
    
    // Save extra taxonomy fields callback function.
    public static function woo_to_printavo_save_category_custom_meta( $term_id ) {
        $printavo_category_id = filter_input( INPUT_POST, 'woo_to_printavo_category' );
        update_term_meta( $term_id, 'woo_to_printavo_category', $printavo_category_id );
    }

}
