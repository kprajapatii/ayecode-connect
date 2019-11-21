<?php

/**
 * Plugin Name: WP Service Client
 * Plugin URI: https://ayecode.io/
 * Description: Example plugin to demonstrate how to use WP Service Client
 * Version: 1.0.0
 * Author: AyeCode
 * Author URI: https://ayecode.io/
 * Requires at least: 4.7
 * Tested up to: 5.3
 *
 * Text Domain: wp-service-client
 * Domain Path: /languages/
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'plugins_loaded', 'wp_service_client' );

/**
 * Sets up the client
 */
function wp_service_client() {

    //Include the client connection class
    require_once plugin_dir_path( __FILE__ ) . 'wp-service-client.php';

    //Prepare client args
    $prefix = 'prefix';
    $args   = array(
        'remote_url'            => 'https://example.com', //URL to the WP site containing the WP_Service_Provider class
        'connection_url'        => 'https://example.com/connect', //This should be a custom page the authinticates a user the calls the WP_Service_Provider::connect_site() method
        'api_url'               => 'https://example.com/wp-json/', //Might be different for you
        'api_namespace'         => 'wp_service_provider/v1',
        'local_api_namespace'   => 'wp_service_client/v1', //Should be unique for each client implementation
        'prefix'                => $prefix, //A unique prefix for things (accepts alphanumerics and underscores). Each client on a given site should have it's own unique prefix
        'textdomain'            => 'textdomain',
    );

    //The following hook runs if the client is connected to the remote
    add_action( "{$prefix}_connected_to_remote", 'wp_service_client_connected_to_remote' );

    //The following hook runs if the client is not connected to the remote
    add_action( "{$prefix}_not_connected_to_remote", 'wp_service_client_not_connected_to_remote' );

    $client = new WP_Service_Client( $args );

    //Call the init method to register routes. This should be called exactly once per client (Preferably before the init hook).
    $client->init();

    //Later on, you can always instantiate the client class without calling the init()
    //method in order to use it's other methods

}

/**
 * Runs if the client is connected to the remote site
 * 
 * For example, might be usefull for license checks etc
 */
function wp_service_client_connected_to_remote(){
    //DO STUFF HERE
}

/**
 * Runs if the client is not connected to the remote site
 * 
 * For example, might be usefull to display a connection button
 */
function wp_service_client_not_connected_to_remote(){
    
    //Ask the user to connect
    add_action( 'admin_notices', function() {

        $args   = array(
            'remote_url'            => 'https://example.com',
            'connection_url'        => 'https://example.com/connect',
            'api_url'               => 'https://example.com/wp-json/',
            'api_namespace'         => 'wp_service_provider/v1',
            'local_api_namespace'   => 'wp_service_client/v1',
            'prefix'                => 'prefix',
            'textdomain'            => 'textdomain',
        );

        $client      = new WP_Service_Client( $args );
        $connect_url = esc_url( $client->build_connect_url() );

        ?>
            <div class="notice notice-success is-dismissible">
                <p><a href="<?php echo $connect_url;?>" class="button button-primary"><?php _e( 'Connect', 'wp-service-client' ); ?></a></p>
            </div>
        <?php


    });
}