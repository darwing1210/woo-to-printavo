<?php

/**
 * The admin-specific functionality of the plugin.
 * mainly render functions
 */

 class WooToPrintavoAdmin {

	/**
	 * Plugin options and settings
	 */

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'admin_hooks' ) );
        add_action( 'network_admin_menu', array( __CLASS__, 'woo_to_printavo_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'woo_to_printavo_settings_subpage' ) );
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
         * Register fields in the "woo_to_printavo_section", inside the "woo_to_printavo" page
         * https://codex.wordpress.org/Function_Reference/add_settings_field
         */

        // Client Email
		add_settings_field(
			'woo_to_printavo_field_client_email',
			__( 'Client email', 'woo_to_printavo' ),
			array( __CLASS__, 'woo_to_printavo_field_text_cb' ),
			'woo_to_printavo',
			'woo_to_printavo_api_section',
			array(
                'label_for' => 'woo_to_printavo_field_client_email', 
                'class'     => 'woo_to_printavo_row'
            )
		);

        // Client Password
		add_settings_field(
			'woo_to_printavo_field_user_password',
			__( 'Password', 'woo_to_printavo' ),
			array( __CLASS__, 'woo_to_printavo_field_password_cb' ),
            'woo_to_printavo',
            'woo_to_printavo_api_section',
			array(
                'label_for' => 'woo_to_printavo_field_user_password',
                'class'     => 'woo_to_printavo_row'
            )
		);
    }
    
    /**
	 * Admin Top Level Menu
	 */
	public static function woo_to_printavo_menu() {
		add_menu_page( __( 'Printavo to WooCommerce Import Settings' , 'woo_to_printavo' ), __( 'Printavo' , 'woo_to_printavo' ), 'administrator', 'woo_to_printavo' );
	}

	/**
	 * Settings Subpage
	 */
	public static function woo_to_printavo_settings_subpage() {
		add_submenu_page(
			'woo_to_printavo',
			__( 'Printavo to WooCommerce Import Settings' , 'woo_to_printavo' ),
			__( 'Importer Settings' , 'woo_to_printavo' ),
			'administrator',
			'woo_to_printavo',
			array( __CLASS__, 'woo_to_printavo_options_page_html' )
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
	 * Instance Text fields Callback
	 */
	public static function woo_to_printavo_field_text_cb( $args ) {
		$options = get_option( 'woo_to_printavo_options' );
		?>

		<input type="text"
		       id="<?php echo esc_attr( $args['label_for'] ); ?>"
               required
		       class="regular-text"
		       name="woo_to_printavo_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
			<?php echo ( isset( $options[ $args['label_for'] ] ) ? 'value=' . $options[ $args['label_for'] ] : ( '' ) ) ?>
		>
		<?php
	}

	/**
	 * Instance Password fields Callback
	 */
	public static function woo_to_printavo_field_password_cb( $args ) {
		$options = get_option( 'woo_to_printavo_options' );
		?>

		<input type="password"
		       id="<?php echo esc_attr( $args['label_for'] ); ?>"
		       class="regular-text"
		       name="woo_to_printavo_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
               <?php echo ( isset( $options[ $args['label_for'] ] ) ? 'value=' . $options[ $args['label_for'] ] : ( '' ) ) ?>
		>
		<?php
	}

	/**
	 * Admin Top Level Menu
	 * render functions
	 */
	public static function woo_to_printavo_options_page_html() {
		// check user capabilities
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error(
                'woo_to_printavo_messages',
                'woo_to_printavo_message',
                __( 'Settings Saved, Success connection to Printavo', 'woo_to_printavo' ),
                'updated'
            );
		} else {
			add_settings_error(
			    'woo_to_printavo_messages',
                'woo_to_printavo_message',
                __( 'Error: we were unable to connect to Printavo', 'woo_to_printavo'),
                'error'
            );
        }

		// show error/update messages
		settings_errors( 'woo_to_printavo_messages' );
		?>
		<div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
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

}
