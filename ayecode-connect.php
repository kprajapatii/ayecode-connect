<?php

/**
 * Plugin Name: AyeCode Connect
 * Plugin URI: https://ayecode.io/
 * Description: A service plugin letting users connect AyeCode Services to their site.
 * Version: 1.0.1
 * Author: AyeCode
 * Author URI: https://ayecode.io
 * Requires at least: 4.7
 * Tested up to: 5.3
 *
 * Text Domain: ayecode-connect
 * Domain Path: /languages/
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( !defined( 'AYECODE_CONNECT_VERSION' ) ) {
    define( 'AYECODE_CONNECT_VERSION', '1.0.1' );
}

add_action( 'plugins_loaded', 'ayecode_connect' );

/**
 * Sets up the client
 */
function ayecode_connect() {

    //Include the client connection class
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ayecode-connect.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ayecode-connect-settings.php';

    //Prepare client args
    $args   = ayecode_connect_args();

    $client = new AyeCode_Connect( $args );

    //Call the init method to register routes. This should be called exactly once per client (Preferably before the init hook).
    $client->init();

    // Load textdomain
    load_plugin_textdomain( 'ayecode-connect', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}

/**
 * The AyeCode Connect arguments.
 *
 * @return array
 */
function ayecode_connect_args(){
    $base_url = 'https://ayecode.io';
    return array(
        'remote_url'            => $base_url, //URL to the WP site containing the WP_Service_Provider class
        'connection_url'        => $base_url.'/connect', //This should be a custom page the authinticates a user the calls the WP_Service_Provider::connect_site() method
        'api_url'               => $base_url.'/wp-json/', //Might be different for you
        'api_namespace'         => 'ayecode/v1',
        'local_api_namespace'   => 'ayecode-connect/v1', //Should be unique for each client implementation
        'prefix'                => 'ayecode_connect', //A unique prefix for things (accepts alphanumerics and underscores). Each client on a given site should have it's own unique prefix
        'textdomain'            => 'ayecode-connect',
    );
}


/**
 * Remove wp cron on deactivation if set.
 */
register_deactivation_hook( __FILE__, 'ayecode_connect_deactivation' );
function ayecode_connect_deactivation() {
    $args = ayecode_connect_args();
    $prefix = $args['prefix'];
    wp_clear_scheduled_hook( $prefix.'_callback' );
}