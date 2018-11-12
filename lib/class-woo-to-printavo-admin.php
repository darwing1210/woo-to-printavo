<?php

/**
 * The admin-specific functionality of the plugin.
 * mainly render functions
 */

class WooToPrintavoAdmin {

    public static function loader() {
        require_once plugin_dir_path( __FILE__ ) . 'class-printavo-api.php';
    }

    /**
     * Plugin options and settings
     */
    public static function init() {
        self::loader();
        add_action( 'network_admin_menu', array( __CLASS__, 'woo_to_printavo_menu' ) );
        add_action( 'network_admin_menu', array( __CLASS__, 'woo_to_printavo_settings_subpage' ) );
        add_action( 'admin_init', array( __CLASS__, 'admin_hooks' ) );
        // Special and neccesary action to process the saving of the options
        add_action( 'network_admin_edit_woo_to_printavo_update_network_options', array( __CLASS__, 'woo_to_printavo_update_network_options' ) );
    }
    
    /**
     * Admin Top Level Menu
     */
    public static function woo_to_printavo_menu() {
        add_menu_page(
            __( 'Printavo to WooCommerce Import Settings' , 'woo_to_printavo' ),
            __( 'Printavo' , 'woo_to_printavo' ),
            'manage_network_options',
            'woo_to_printavo'
        );
    }

    /**
     * Settings Subpage
     */
    public static function woo_to_printavo_settings_subpage() {
        add_submenu_page(
            'woo_to_printavo',
            __( 'Printavo to WooCommerce Import Settings' , 'woo_to_printavo' ),
            __( 'Importer Settings' , 'woo_to_printavo' ),
            'manage_network_options',
            'woo_to_printavo',
            array( __CLASS__, 'woo_to_printavo_options_page_html' )
        );
    }

    public static function admin_hooks() {

        /*
         * Register a new setting for "woo_to_printavo" page 
         * https://developer.wordpress.org/reference/functions/register_setting/
         */
        register_setting( 'woo_to_printavo', 'woo_to_printavo_options' );

        /* 
         * Register a new section in the "woo_to_printavo" page 
         * https://codex.wordpress.org/Function_Reference/add_settings_section
         */
        add_settings_section(
            'woo_to_printavo_api_section', 
            __( 'REST API settings', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_api_section_cb' ),
            'woo_to_printavo'
        );

        /* 
         * Register a new section in the "woo_to_printavo" page 
         * https://codex.wordpress.org/Function_Reference/add_settings_section
         */
        add_settings_section(
            'woo_to_printavo_export_section', 
            __( 'Export settings', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_export_section_cb' ),
            'woo_to_printavo'
        );

        /* 
         * Register fields in the "woo_to_printavo_section", inside the "woo_to_printavo" page
         * https://codex.wordpress.org/Function_Reference/add_settings_field
         */

        // Client Email
        add_settings_field(
            'woo_to_printavo_field_client_email',
            __( 'Client email', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_field_input' ), // The function to render
            'woo_to_printavo',
            'woo_to_printavo_api_section',
            array(
                'label_for' => 'woo_to_printavo_field_client_email', 
                'class'     => 'woo_to_printavo_row',
                'required'  => true,
                'type'      => 'email'
            )
        );

        // Client Password
        add_settings_field(
            'woo_to_printavo_field_client_password',
            __( 'Password', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_field_input' ),
            'woo_to_printavo',
            'woo_to_printavo_api_section',
            array(
                'label_for' => 'woo_to_printavo_field_client_password',
                'class'     => 'woo_to_printavo_row',
                'required'  => true,
                'type'      => 'password'
            )
        );

        /************ Export Section Fields ***************/

        // Default Status
        add_settings_field(
            'woo_to_printavo_field_order_default_status',
            __( 'Default Order Status', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_field_status_select' ),
            'woo_to_printavo',
            'woo_to_printavo_export_section',
            array(
                'label_for' => 'woo_to_printavo_field_order_default_status',
                'class'     => 'woo_to_printavo_row',
                'required'  => true
            )
        );

        add_settings_field(
            'woo_to_printavo_field_auto_send',
            __( 'Auto export Orders', 'woo_to_printavo' ),
            array( __CLASS__, 'woo_to_printavo_field_input' ),
            'woo_to_printavo',
            'woo_to_printavo_export_section',
            array(
                'label_for' => 'woo_to_printavo_field_auto_send',
                'class'     => 'woo_to_printavo_row',
                'type'      => 'checkbox',
                'required'  => false
            )
        );
    }

    /**
     * Callback to render section
     * Text that goes between header and fields
     */
    public static function woo_to_printavo_api_section_cb( $args ) {
        printf(
            '<p id="%s">%s</p>', 
            esc_attr( $args['id'] ), 
            __( 'Please add the following data to stablish a connection to Printavo API.', 'woo_to_printavo' )
        );
    }

    /**
     * Callback to render section
     * Text that goes between header and fields
     */
    public static function woo_to_printavo_export_section_cb( $args ) {
        printf(
            '<p id="%s">%s</p>', 
            esc_attr( $args['id'] ), 
            __( 'This seccion add the default export to Printavo settings', 'woo_to_printavo' )
        );
    }

    /**
     * Instance Text fields Callback
     */
    public static function woo_to_printavo_field_input( $args ) {
        $options = get_site_option( 'woo_to_printavo_options' );
        $stored_value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        ?>

        <input id="<?php echo esc_attr( $args['label_for'] ); ?>"
               class="regular-text"
               type="<?php echo $args['type'] ? esc_attr( $args['type'] ) : 'text' ?>"
               name="woo_to_printavo_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
               <?php echo $args['type'] == 'checkbox' && $stored_value == 'on' ? esc_attr( 'checked' ) : '' ?>
               <?php echo $args['required'] ? esc_attr( 'required' ) : '' ?>
            <?php echo esc_attr( "value={$stored_value}" )?>
        >
        <?php
    }


    /**
     * This function displays a select
     */
    public static function woo_to_printavo_field_status_select( $args ) {
        $options = get_site_option( 'woo_to_printavo_options' );
        $statuses = get_site_option( 'woo_to_printavo_statuses' );

        $stored_value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';

        if ( $options && $statuses ) {
        ?>
        <select 
               id="<?php echo esc_attr( $args['label_for'] ); ?>"
               class="regular-text"
               name="woo_to_printavo_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
               <?php echo $args['required'] ? esc_attr( 'required' ) : '' ?>
            <?php echo esc_attr( "value={$stored_value}" )?>
        >
        <option value="">Select an option</option>
        <?php foreach( $statuses as $status ) { ?>
            <option 
                value="<?php echo $status['id'] ?>" 
                <?php echo ( $stored_value == $status['id'] ? "selected" : '') ?> >
                    <?php echo $status['name'] ?>
            </option>
        <?php } ?>
        </select>
        <?php
        }
    }

    /**
     * Admin Top Level Menu
     * render functions
     */
    public static function woo_to_printavo_options_page_html() {
        // check user capabilities
        if ( ! current_user_can( 'manage_network_options' ) ) {
            return;
        }

        if ( isset( $_GET['updated'] ) ) {
            $options = get_site_option( 'woo_to_printavo_options' );
            
            $email = $options['woo_to_printavo_field_client_email'];
            $password = $options['woo_to_printavo_field_client_password'];

            $api = new PrintavoAPI( $email, $password );
            $session_token = $api->get_session_token();

            $statuses = $api->get_printavo_order_statuses();

            $api->logout();
            
            if ( ! is_wp_error( $session_token ) ) {
                // add settings saved message with the class of "updated"
                add_settings_error(
                    'woo_to_printavo_messages',
                    'woo_to_printavo_message',
                    __( 'Settings Saved, Success connection to Printavo', 'woo_to_printavo' ),
                    'updated'
                );

                if ( $statuses && ! is_wp_error( $statuses ) ) {
                    update_site_option( 'woo_to_printavo_statuses', $statuses );
                } else {
                    delete_site_option( 'woo_to_printavo_statuses' );
                    delete_transient( 'printavo_categories' );
                }

            } else {
                $message = $session_token->get_error_message();
                add_settings_error(
                    'woo_to_printavo_messages',
                    'woo_to_printavo_message',
                    __( "Error: we were unable to connect to Printavo, {$message}", 'woo_to_printavo'),
                    'error'
                );
                delete_site_option( 'woo_to_printavo_statuses' );
                delete_transient( 'printavo_categories' );
            }
        }

        // show error/update messages
        settings_errors( 'woo_to_printavo_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="edit.php?action=woo_to_printavo_update_network_options" method="post">
                <?php
                // output security fields for the registered setting "woo_to_printavo"
                settings_fields( 'woo_to_printavo' );

                do_settings_sections( 'woo_to_printavo' );
                submit_button( 'Save settings' );
                ?>
            </form>
        </div>
        <?php
    }

    public static function woo_to_printavo_update_network_options() {
        // we must add the '-options' postfix when we check the referer.
        check_admin_referer('woo_to_printavo-options');

        $options = 'woo_to_printavo_options';
        if ( isset( $_POST[$options] ) ) {
            update_site_option( $options, $_POST[$options] );
            delete_transient( 'printavo_categories' );
        } else {
            delete_site_option( $options );
            delete_site_option( 'woo_to_printavo_statuses' );
            delete_transient( 'printavo_categories' );
        }

        // Redirect back to our options page.
        wp_redirect(
            add_query_arg(
                array(
                    'page'      => 'woo_to_printavo',
                    'updated'   => 'true'
            ),
            network_admin_url( 'admin.php' ))
        );
        exit;
    }

}
