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
            'args'                => array(
                'event'       => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'ddo_api_sanitize_feedback_event',
                    'validate_callback' => 'ddo_api_validate_feedback_event',
                ),
                'score'       => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'ddo_api_sanitize_feedback_score',
                    'validate_callback' => 'ddo_api_validate_feedback_score',
                ),
                'client_id'   => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'ddo_api_sanitize_feedback_identifier',
                    'validate_callback' => 'ddo_api_validate_feedback_client_id',
                ),
                'campaign_id' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'ddo_api_sanitize_feedback_identifier',
                    'validate_callback' => 'ddo_api_validate_feedback_campaign_id',
                ),
                'ad_id'       => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'ddo_api_sanitize_feedback_identifier',
                    'validate_callback' => 'ddo_api_validate_feedback_ad_id',
                ),
            ),
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
    $feedback_id = ddo_store_feedback_payload( $request->get_params() );

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
 * Sanitiseer eventnaam voor feedback payload.
 *
 * @param mixed $value Ruwe eventnaam.
 * @return string
 */
function ddo_api_sanitize_feedback_event( $value ) {
    return sanitize_key( (string) $value );
}

/**
 * Valideer eventnaam voor feedback payload.
 *
 * @param mixed           $value   Eventwaarde.
 * @param WP_REST_Request $request Request object.
 * @param string          $param   Param naam.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_event( $value, WP_REST_Request $request, $param ) {
    $raw_event = trim( (string) $value );
    $event     = ddo_api_sanitize_feedback_event( $value );

    if ( '' === $raw_event || '' === $event ) {
        return new WP_Error( 'ddo_feedback_event_missing', __( 'Het veld "event" is verplicht.', 'data-driven-optimizer' ), array( 'status' => 400, 'param' => $param ) );
    }

    if ( $raw_event !== $event ) {
        return new WP_Error( 'ddo_feedback_event_format_invalid', __( 'Het veld "event" bevat ongeldige tekens.', 'data-driven-optimizer' ), array( 'status' => 422, 'param' => $param ) );
    }

    if ( strlen( $event ) < 2 || strlen( $event ) > 50 ) {
        return new WP_Error( 'ddo_feedback_event_length_invalid', __( 'Het veld "event" moet tussen 2 en 50 tekens lang zijn.', 'data-driven-optimizer' ), array( 'status' => 422, 'param' => $param ) );
    }

    if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]*$/', $event ) ) {
        return new WP_Error( 'ddo_feedback_event_format_invalid', __( 'Het veld "event" bevat ongeldige tekens.', 'data-driven-optimizer' ), array( 'status' => 422, 'param' => $param ) );
    }

    return true;
}

/**
 * Sanitiseer score voor feedback payload.
 *
 * @param mixed $value Ruwe score.
 * @return int
 */
function ddo_api_sanitize_feedback_score( $value ) {
    return (int) $value;
}

/**
 * Valideer score-range voor feedback payload.
 *
 * @param mixed           $value   Scorewaarde.
 * @param WP_REST_Request $request Request object.
 * @param string          $param   Param naam.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_score( $value, WP_REST_Request $request, $param ) {
    if ( ! is_numeric( $value ) ) {
        return new WP_Error( 'ddo_feedback_score_type_invalid', __( 'Het veld "score" moet numeriek zijn.', 'data-driven-optimizer' ), array( 'status' => 422, 'param' => $param ) );
    }

    $score = ddo_api_sanitize_feedback_score( $value );

    if ( $score < 0 || $score > 10 ) {
        return new WP_Error( 'ddo_feedback_score_range_invalid', __( 'Het veld "score" moet tussen 0 en 10 liggen.', 'data-driven-optimizer' ), array( 'status' => 422, 'param' => $param ) );
    }

    return true;
}

/**
 * Sanitiseer generieke identifier voor feedback payload.
 *
 * @param mixed $value Ruwe identifier.
 * @return string
 */
function ddo_api_sanitize_feedback_identifier( $value ) {
    return sanitize_text_field( (string) $value );
}

/**
 * Valideer identifier veld op lengte en vereiste inhoud.
 *
 * @param mixed  $value         Identifierwaarde.
 * @param string $param         Param naam.
 * @param int    $max_length    Max toegestane lengte.
 * @param bool   $allow_general Of de placeholder "general" is toegestaan.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_identifier_field( $value, $param, $max_length = 100, $allow_general = false ) {
    $identifier = ddo_api_sanitize_feedback_identifier( $value );

    if ( '' === $identifier ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_missing', sprintf( __( 'Het veld "%s" is verplicht.', 'data-driven-optimizer' ), $param ), array( 'status' => 400, 'param' => $param ) );
    }

    if ( strlen( $identifier ) > $max_length ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_length_invalid', sprintf( __( 'Het veld "%s" mag maximaal %d tekens bevatten.', 'data-driven-optimizer' ), $param, $max_length ), array( 'status' => 422, 'param' => $param ) );
    }

    if ( ! $allow_general && 'general' === strtolower( $identifier ) ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_value_invalid', sprintf( __( 'Het veld "%s" bevat een gereserveerde waarde.', 'data-driven-optimizer' ), $param ), array( 'status' => 422, 'param' => $param ) );
    }

    return true;
}

/**
 * Valideer client_id voor feedback payload.
 *
 * @param mixed           $value   client_id.
 * @param WP_REST_Request $request Request object.
 * @param string          $param   Param naam.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_client_id( $value, WP_REST_Request $request, $param ) {
    return ddo_api_validate_feedback_identifier_field( $value, $param, 100, false );
}

/**
 * Valideer campaign_id voor feedback payload.
 *
 * @param mixed           $value   campaign_id.
 * @param WP_REST_Request $request Request object.
 * @param string          $param   Param naam.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_campaign_id( $value, WP_REST_Request $request, $param ) {
    return ddo_api_validate_feedback_identifier_field( $value, $param, 80, true );
}

/**
 * Valideer ad_id voor feedback payload.
 *
 * @param mixed           $value   ad_id.
 * @param WP_REST_Request $request Request object.
 * @param string          $param   Param naam.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_ad_id( $value, WP_REST_Request $request, $param ) {
    return ddo_api_validate_feedback_identifier_field( $value, $param, 80, true );
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
