<?php
/**
 * REST API handlers voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registreer routes.
 */
function ddo_register_api_routes() {
    add_action( 'rest_api_init', 'ddo_register_rest_routes' );
}

/**
 * Definieer de REST route(s).
 */
function ddo_register_rest_routes() {
    register_rest_route(
        'ddo/v1',
        '/status',
        array(
            'methods'             => 'GET',
            'callback'            => 'ddo_api_get_status',
            'permission_callback' => '__return_true',
        )
    );
}

/**
 * Geef simpele status terug.
 *
 * @return WP_REST_Response
 */
function ddo_api_get_status() {
    $options = get_option( 'ddo_options', array() );

    return rest_ensure_response(
        array(
            'plugin'  => 'data-driven-optimizer',
            'version' => DDO_PLUGIN_VERSION,
            'enabled' => ! empty( $options['enabled'] ),
        )
    );
}
