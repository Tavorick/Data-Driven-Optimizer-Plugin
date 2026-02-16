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
            'permission_callback' => 'ddo_api_submit_feedback_permission',
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
    $raw_identifier = trim( (string) $value );
    $identifier = ddo_api_sanitize_feedback_identifier( $value );

    if ( '' === $raw_identifier || '' === $identifier ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_missing', sprintf( __( 'Het veld "%s" is verplicht.', 'data-driven-optimizer' ), $param ), array( 'status' => 400, 'param' => $param ) );
    }

    if ( $raw_identifier !== $identifier ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_format_invalid', sprintf( __( 'Het veld "%s" bevat ongeldige tekens.', 'data-driven-optimizer' ), $param ), array( 'status' => 422, 'param' => $param ) );
    }

    if ( strlen( $identifier ) > $max_length ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_length_invalid', sprintf( __( 'Het veld "%s" mag maximaal %d tekens bevatten.', 'data-driven-optimizer' ), $param, $max_length ), array( 'status' => 422, 'param' => $param ) );
    }

    if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/', $identifier ) ) {
        return new WP_Error( 'ddo_feedback_' . $param . '_format_invalid', sprintf( __( 'Het veld "%s" bevat ongeldige tekens.', 'data-driven-optimizer' ), $param ), array( 'status' => 422, 'param' => $param ) );
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
 * Controleer REST API permissies voor publieke feedback ingest.
 *
 * @param WP_REST_Request $request REST request object.
 * @return true|WP_Error
 */
function ddo_api_submit_feedback_permission( WP_REST_Request $request ) {
    $params = $request->get_params();

    $hardening_result = ddo_api_validate_feedback_payload_minimum( $params );
    if ( is_wp_error( $hardening_result ) ) {
        return $hardening_result;
    }

    $nonce = isset( $params['nonce'] ) ? ddo_api_sanitize_feedback_nonce( $params['nonce'] ) : '';
    if ( '' === $nonce || 1 !== preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $nonce ) ) {
        return new WP_Error( 'ddo_feedback_nonce_invalid', __( 'Ongeldige feedback nonce.', 'data-driven-optimizer' ), array( 'status' => 403 ) );
    }

    $timestamp = isset( $params['timestamp'] ) ? ddo_api_sanitize_feedback_timestamp( $params['timestamp'] ) : 0;
    if ( $timestamp <= 0 || abs( time() - $timestamp ) > 5 * MINUTE_IN_SECONDS ) {
        return new WP_Error( 'ddo_feedback_timestamp_invalid', __( 'Feedback request is verlopen.', 'data-driven-optimizer' ), array( 'status' => 403 ) );
    }

    $signature = isset( $params['signature'] ) ? ddo_api_sanitize_feedback_signature( $params['signature'] ) : '';
    if ( '' === $signature || 1 !== preg_match( '/^[a-f0-9]{64}$/', strtolower( $signature ) ) ) {
        return new WP_Error( 'ddo_feedback_signature_invalid', __( 'Ongeldige feedback signature.', 'data-driven-optimizer' ), array( 'status' => 403 ) );
    }

    $signed_payload = array(
        'event'       => isset( $params['event'] ) ? ddo_api_sanitize_feedback_event( $params['event'] ) : '',
        'score'       => isset( $params['score'] ) ? (string) ddo_api_sanitize_feedback_score( $params['score'] ) : '',
        'client_id'   => isset( $params['client_id'] ) ? ddo_api_sanitize_feedback_identifier( $params['client_id'] ) : '',
        'campaign_id' => isset( $params['campaign_id'] ) ? ddo_api_sanitize_feedback_identifier( $params['campaign_id'] ) : '',
        'ad_id'       => isset( $params['ad_id'] ) ? ddo_api_sanitize_feedback_identifier( $params['ad_id'] ) : '',
    );

    $expected_signature = hash_hmac(
        'sha256',
        $nonce . '|' . $timestamp . '|' . wp_json_encode( $signed_payload ),
        ddo_api_get_feedback_signature_secret()
    );

    if ( ! hash_equals( $expected_signature, strtolower( $signature ) ) ) {
        return new WP_Error( 'ddo_feedback_signature_mismatch', __( 'Feedback signature komt niet overeen.', 'data-driven-optimizer' ), array( 'status' => 403 ) );
    }

    $rate_limit_result = ddo_api_check_feedback_rate_limit( $nonce, $signed_payload );
    if ( is_wp_error( $rate_limit_result ) ) {
        return $rate_limit_result;
    }

    return true;
}

/**
 * Backwards-compatible alias voor legacy permissie callback naam.
 *
 * @param WP_REST_Request $request REST request object.
 * @return true|WP_Error
 */
function ddo_api_feedback_permission( WP_REST_Request $request ) {
    return ddo_api_submit_feedback_permission( $request );
}

/**
 * Geef gedeeld secret voor feedback signature checks.
 *
 * @return string
 */
function ddo_api_get_feedback_signature_secret() {
    $stored_secret = (string) get_option( 'ddo_feedback_webhook_secret', '' );

    if ( '' !== $stored_secret ) {
        return $stored_secret;
    }

    return wp_hash( wp_salt( 'auth' ) . '|ddo_feedback_signature' );
}

/**
 * Voer minimale payload checks uit voordat validatie start.
 *
 * @param array $params Request parameters.
 * @return true|WP_Error
 */
function ddo_api_validate_feedback_payload_minimum( $params ) {
    if ( ! is_array( $params ) ) {
        return new WP_Error( 'ddo_feedback_payload_invalid', __( 'Feedback payload is ongeldig.', 'data-driven-optimizer' ), array( 'status' => 400 ) );
    }

    if ( count( $params ) > 20 ) {
        return new WP_Error( 'ddo_feedback_payload_too_large', __( 'Feedback payload bevat te veel velden.', 'data-driven-optimizer' ), array( 'status' => 413 ) );
    }

    $required_fields = array( 'event', 'score', 'client_id', 'campaign_id', 'ad_id', 'nonce', 'signature', 'timestamp' );
    foreach ( $required_fields as $field ) {
        if ( ! isset( $params[ $field ] ) || '' === trim( (string) $params[ $field ] ) ) {
            return new WP_Error( 'ddo_feedback_payload_missing_field', sprintf( __( 'Vereist feedback veld ontbreekt: %s.', 'data-driven-optimizer' ), $field ), array( 'status' => 400 ) );
        }
    }

    $payload_size = strlen( wp_json_encode( $params ) );
    if ( $payload_size < 40 ) {
        return new WP_Error( 'ddo_feedback_payload_too_small', __( 'Feedback payload is te klein.', 'data-driven-optimizer' ), array( 'status' => 400 ) );
    }

    if ( $payload_size > 4096 ) {
        return new WP_Error( 'ddo_feedback_payload_too_large', __( 'Feedback payload is te groot.', 'data-driven-optimizer' ), array( 'status' => 413 ) );
    }

    return true;
}

/**
 * Sanitiseer nonce waarde voor feedback ingest.
 *
 * @param mixed $value Ruwe nonce.
 * @return string
 */
function ddo_api_sanitize_feedback_nonce( $value ) {
    return sanitize_text_field( (string) $value );
}

/**
 * Sanitiseer request timestamp voor feedback ingest.
 *
 * @param mixed $value Ruwe timestamp.
 * @return int
 */
function ddo_api_sanitize_feedback_timestamp( $value ) {
    return (int) $value;
}

/**
 * Sanitiseer request signature voor feedback ingest.
 *
 * @param mixed $value Ruwe signature.
 * @return string
 */
function ddo_api_sanitize_feedback_signature( $value ) {
    return strtolower( sanitize_text_field( (string) $value ) );
}

/**
 * Sanitiseer bron-IP voor feedback throttling.
 *
 * @return string
 */
function ddo_api_get_feedback_request_ip() {
    $raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( (string) $_SERVER['REMOTE_ADDR'] ) : '';

    if ( '' !== $raw_ip && false !== filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
        return $raw_ip;
    }

    return 'unknown';
}

/**
 * Controleer feedback ingest op throttling per IP + payload hash.
 *
 * @param string $nonce          Request nonce.
 * @param array  $signed_payload Gesigneerde payload.
 * @return true|WP_Error
 */
function ddo_api_check_feedback_rate_limit( $nonce, $signed_payload ) {
    $ip_address = ddo_api_get_feedback_request_ip();
    $fingerprint_source = $ip_address . '|' . $nonce . '|' . wp_json_encode( $signed_payload );
    $fingerprint        = wp_hash( $fingerprint_source );
    $transient_key      = 'ddo_rl_' . substr( md5( $fingerprint ), 0, 24 );

    $window_seconds = 5 * MINUTE_IN_SECONDS;
    $max_attempts   = 30;
    $now            = time();
    $bucket         = get_transient( $transient_key );
    $bucket         = is_array( $bucket ) ? $bucket : array(
        'count'    => 0,
        'window'   => $window_seconds,
        'expires'  => $now + $window_seconds,
    );

    if ( ! isset( $bucket['expires'] ) || (int) $bucket['expires'] <= $now ) {
        $bucket['count']   = 0;
        $bucket['expires'] = $now + $window_seconds;
    }

    $bucket['count'] = isset( $bucket['count'] ) ? (int) $bucket['count'] + 1 : 1;

    if ( $bucket['count'] > $max_attempts ) {
        set_transient( $transient_key, $bucket, max( 1, (int) ( $bucket['expires'] - $now ) ) );

        return new WP_Error( 'ddo_feedback_rate_limited', __( 'Te veel feedback requests. Probeer later opnieuw.', 'data-driven-optimizer' ), array( 'status' => 429 ) );
    }

    set_transient( $transient_key, $bucket, max( 1, (int) ( $bucket['expires'] - $now ) ) );

    return true;
}

/**
 * Handler voor periodieke data-fetches via scheduler.
 */
function ddo_run_hourly_fetch_job() {
    ddo_execute_scheduled_job(
        'ddo_hourly_fetch',
        function () {
            return ddo_process_api_data_fetch();
        }
    );
}

/**
 * Configureer een custom API data fetch service.
 *
 * @param callable|null $service Service-callback.
 */
function ddo_set_api_data_fetch_service( $service ) {
    $GLOBALS['ddo_api_data_fetch_service'] = $service;
}

/**
 * Haal de actieve API data fetch service op.
 *
 * @return callable
 */
function ddo_get_api_data_fetch_service() {
    $service = isset( $GLOBALS['ddo_api_data_fetch_service'] ) ? $GLOBALS['ddo_api_data_fetch_service'] : 'ddo_default_api_data_fetch_service';

    if ( ! is_callable( $service ) ) {
        return 'ddo_default_api_data_fetch_service';
    }

    return $service;
}

/**
 * Default API client service voor geplande fetch jobs.
 *
 * @return array
 */
function ddo_default_api_data_fetch_service() {
    return array(
        'processed_count' => 0,
        'errors_count'    => 0,
        'source'          => 'default-api-client',
    );
}

/**
 * Voer fetch-logica uit naar externe APIs.
 *
 * @return array
 */
function ddo_process_api_data_fetch() {
    $started_at = microtime( true );
    $service    = ddo_get_api_data_fetch_service();
    $payload    = call_user_func( $service );
    $payload    = is_array( $payload ) ? $payload : array();

    $processed_count = isset( $payload['processed_count'] )
        ? (int) $payload['processed_count']
        : ( isset( $payload['records_fetched'] ) ? (int) $payload['records_fetched'] : 0 );
    $error_code      = isset( $payload['error_code'] ) ? (string) $payload['error_code'] : '';
    $errors_count    = isset( $payload['errors_count'] )
        ? (int) $payload['errors_count']
        : ( '' !== $error_code ? 1 : 0 );

    $result = array(
        'job'             => 'ddo_hourly_fetch',
        'service'         => is_string( $service ) ? $service : 'custom-api-data-fetch-service',
        'processed_count' => max( 0, $processed_count ),
        'errors_count'    => max( 0, $errors_count ),
        'duration_ms'     => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
        'error_code'      => $error_code,
    );

    if ( $result['errors_count'] > 0 ) {
        throw new RuntimeException( 'API data fetch failed.', 0 );
    }

    ddo_log_scheduler_event( 'ddo_hourly_fetch', 'fetch-complete', 'info', $result );

    return $result;
}
