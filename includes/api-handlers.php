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

    register_rest_route(
        'ddo/v1',
        '/feedback',
        array(
            'methods'             => 'POST',
            'callback'            => 'ddo_api_submit_feedback',
            'permission_callback' => 'ddo_api_manage_options_permission',
        )
    );

    register_rest_route(
        'ddo/v1',
        '/feedback/summary',
        array(
            'methods'             => 'GET',
            'callback'            => 'ddo_api_get_feedback_summary',
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
 * Verwerk feedback submission via REST.
 *
 * @param WP_REST_Request $request REST request object.
 * @return WP_REST_Response
 */
function ddo_api_submit_feedback( WP_REST_Request $request ) {
    $feedback_id = ddo_store_feedback_payload( $request->get_json_params() );

    if ( is_wp_error( $feedback_id ) ) {
        return $feedback_id;
    }

    return rest_ensure_response(
        array(
            'success'     => true,
            'feedback_id' => (int) $feedback_id,
        )
    );
}

/**
 * Geef geaggregeerde feedbacksamenvatting terug.
 *
 * @return WP_REST_Response
 */
function ddo_api_get_feedback_summary() {
    return rest_ensure_response( ddo_get_feedback_summary() );
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
