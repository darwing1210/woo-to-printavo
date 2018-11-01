<?php
/**
 * Plugin Name: WooCommerce To Printavo
 * Description: Custom implementation to sync WooCommerce Orders with Printavo
 * Version: 0.0.1
 * Author: Darwing Medina
 * Author URI: https://github.com/darwing1210
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WooToPrintavo {

    public static function loader() {
        require_once plugin_dir_path( __FILE__ ) . 'lib/class-woo-to-printavo-admin.php';
        require_once plugin_dir_path( __FILE__ ) . 'lib/class-woo-to-printavo-hooks.php';
    }

    public static function init() {
        
        self::loader();

        // Plugin specific
        add_filter( 'plugin_action_links_woo-to-printavo', array( __CLASS__, 'wp_add_plugin_settings_link' ) );

        // Init admin options
        WooToPrintavoAdmin::init();
        // Init hooks
        WooToPrintavoHooks::init();

    }

    // Add settings link on plugin page
    public static function wp_add_plugin_settings_link( $links ) {
        $settings_link = '<a href="/network/admin.php?page=woo_to_printavo">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public static function getPluginDirUrl() {
        return plugin_dir_url( __FILE__ );
    }
}

WooToPrintavo::init();