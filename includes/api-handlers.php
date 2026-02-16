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
            'permission_callback' => 'ddo_api_manage_options_permission',
        )
    );
}

/**
 * Geef simpele status terug.
 *
 * @return WP_REST_Response
 */
function ddo_api_get_status() {
    return rest_ensure_response(
        array(
            'plugin'  => 'data-driven-optimizer',
            'version' => DDO_PLUGIN_VERSION,
            'enabled' => (bool) get_option( 'ddo_enabled', true ),
        )
    );
}

/**
 * Controleer REST API permissies voor admin-status.
 *
 * @return bool
 */
function ddo_api_manage_options_permission() {
    return current_user_can( 'manage_options' );
}

/**
 * Handler voor periodieke data-fetches via scheduler.
 */
function ddo_run_hourly_fetch_job() {
    ddo_execute_scheduled_job(
        'ddo_hourly_fetch',
        function () {
            ddo_process_api_data_fetch();
        }
    );
}

/**
 * Placeholder voor fetch-logica naar externe APIs.
 */
function ddo_process_api_data_fetch() {
    do_action( 'ddo_api_data_fetch' );
}
