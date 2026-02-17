<?php
/**
 * Google Analytics 4 Data API source connector.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Haal veilig een WP_Error code op.
 *
 * @param WP_Error $error WP_Error object.
 * @return string
 */
function ddo_get_wp_error_code_safe( $error ) {
    if ( is_object( $error ) && method_exists( $error, 'get_error_code' ) ) {
        return (string) $error->get_error_code();
    }

    return isset( $error->code ) ? (string) $error->code : '';
}

/**
 * Haal veilig een WP_Error bericht op.
 *
 * @param WP_Error $error WP_Error object.
 * @return string
 */
function ddo_get_wp_error_message_safe( $error ) {
    if ( is_object( $error ) && method_exists( $error, 'get_error_message' ) ) {
        return (string) $error->get_error_message();
    }

    return isset( $error->message ) ? (string) $error->message : '';
}

/**
 * Haal pageview-data op uit de GA4 Data API (RunReport).
 *
 * @param string $start_date Startdatum in Y-m-d.
 * @param string $end_date   Einddatum in Y-m-d.
 * @return array|WP_Error
 */
function ddo_fetch_google_pageviews( $start_date, $end_date ) {
    $job_name   = 'ddo_hourly_fetch';
    $start_date = is_string( $start_date ) ? trim( $start_date ) : '';
    $end_date   = is_string( $end_date ) ? trim( $end_date ) : '';

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
        $error = new WP_Error( 'ddo_ga4_invalid_date_range', __( 'Invalid GA4 date range.', 'data-driven-optimizer' ) );

        ddo_log_scheduler_event(
            $job_name,
            'ga4-invalid-date-range',
            'error',
            array(
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'error_code' => ddo_get_wp_error_code_safe( $error ),
            )
        );

        return $error;
    }

    $property_id      = sanitize_text_field( (string) get_option( 'ddo_ga4_property_id', '' ) );
    $service_secret   = ddo_get_secret_option( 'ddo_ga4_service_account_json' );
    $legacy_api_token = ddo_get_api_key( 'ddo_api_key_primary' );
    $access_token     = '' !== $service_secret ? $service_secret : $legacy_api_token;

    if ( '' === $property_id || '' === $access_token ) {
        $error = new WP_Error( 'ddo_ga4_missing_config', __( 'GA4-configuratie is incompleet. Vul Property ID en service account JSON/token in.', 'data-driven-optimizer' ) );

        ddo_log_scheduler_event(
            $job_name,
            'ga4-missing-config',
            'error',
            array(
                'property_id_present'      => '' !== $property_id,
                'service_account_present'  => '' !== $service_secret,
                'legacy_api_token_present' => '' !== $legacy_api_token,
                'error_code'               => ddo_get_wp_error_code_safe( $error ),
            )
        );

        return $error;
    }

    $endpoint = sprintf(
        'https://analyticsdata.googleapis.com/v1beta/properties/%1$s:runReport',
        rawurlencode( $property_id )
    );

    $request_base = array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
        'timeout' => 20,
    );

    $metric_names = array( 'screenPageViews', 'sessions' );

    foreach ( $metric_names as $metric_name ) {
        $request_args         = $request_base;
        $request_args['body'] = wp_json_encode(
            array(
                'dimensions' => array(
                    array( 'name' => 'date' ),
                    array( 'name' => 'pagePath' ),
                ),
                'metrics'    => array(
                    array( 'name' => $metric_name ),
                ),
                'dateRanges' => array(
                    array(
                        'startDate' => $start_date,
                        'endDate'   => $end_date,
                    ),
                ),
            )
        );

        $http_response = wp_remote_post( $endpoint, $request_args );

        if ( is_wp_error( $http_response ) ) {
            $error = new WP_Error( 'ddo_ga4_request_failed', __( 'GA4 request failed.', 'data-driven-optimizer' ) );

            ddo_log_scheduler_event(
                $job_name,
                'ga4-request-failed',
                'error',
                array(
                    'metric'     => $metric_name,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'message'    => ddo_get_wp_error_message_safe( $http_response ),
                    'error_code' => ddo_get_wp_error_code_safe( $error ),
                )
            );

            return $error;
        }

        $response_code = (int) wp_remote_retrieve_response_code( $http_response );
        $raw_body      = (string) wp_remote_retrieve_body( $http_response );
        $decoded_body  = json_decode( $raw_body, true );

        if ( 401 === $response_code || 403 === $response_code ) {
            $error = new WP_Error( 'ddo_ga4_auth_failed', __( 'GA4 authentication failed.', 'data-driven-optimizer' ) );

            ddo_log_scheduler_event(
                $job_name,
                'ga4-auth-failed',
                'error',
                array(
                    'metric'        => $metric_name,
                    'response_code' => $response_code,
                    'error_code'    => ddo_get_wp_error_code_safe( $error ),
                )
            );

            return $error;
        }

        if ( $response_code >= 400 ) {
            $error = new WP_Error( 'ddo_ga4_http_error', __( 'GA4 returned an unexpected HTTP error.', 'data-driven-optimizer' ) );

            ddo_log_scheduler_event(
                $job_name,
                'ga4-http-error',
                'error',
                array(
                    'metric'        => $metric_name,
                    'response_code' => $response_code,
                    'error_code'    => ddo_get_wp_error_code_safe( $error ),
                )
            );

            return $error;
        }

        if ( ! is_array( $decoded_body ) ) {
            $error = new WP_Error( 'ddo_ga4_invalid_response', __( 'GA4 response could not be parsed.', 'data-driven-optimizer' ) );

            ddo_log_scheduler_event(
                $job_name,
                'ga4-invalid-response',
                'error',
                array(
                    'metric'     => $metric_name,
                    'error_code' => ddo_get_wp_error_code_safe( $error ),
                )
            );

            return $error;
        }

        $rows = ddo_map_ga4_pageviews_response_to_rows( $decoded_body );

        if ( ! empty( $rows ) ) {
            return array(
                'rows'    => $rows,
                'fetched' => count( $rows ),
            );
        }
    }

    ddo_log_scheduler_event(
        $job_name,
        'ga4-empty-response',
        'info',
        array(
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'error_code' => '',
        )
    );

    return array(
        'rows'    => array(),
        'fetched' => 0,
    );
}

/**
 * Map GA4 response naar een uniforme interne row-structuur.
 *
 * @param array $response Raw response body van GA4.
 * @return array
 */
function ddo_map_ga4_pageviews_response_to_rows( $response ) {
    $rows = array();

    if ( ! is_array( $response ) || empty( $response['rows'] ) || ! is_array( $response['rows'] ) ) {
        return $rows;
    }

    foreach ( $response['rows'] as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $dimension_values = isset( $row['dimensionValues'] ) && is_array( $row['dimensionValues'] ) ? $row['dimensionValues'] : array();
        $metric_values    = isset( $row['metricValues'] ) && is_array( $row['metricValues'] ) ? $row['metricValues'] : array();

        $raw_date = isset( $dimension_values[0]['value'] ) ? (string) $dimension_values[0]['value'] : '';
        $page_path = isset( $dimension_values[1]['value'] ) ? (string) $dimension_values[1]['value'] : '';
        $pageviews = isset( $metric_values[0]['value'] ) ? (int) $metric_values[0]['value'] : 0;

        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw_date, $matches ) ) {
            $metric_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        } else {
            $metric_date = $raw_date;
        }

        if ( '' === $metric_date || '' === $page_path ) {
            continue;
        }

        $rows[] = array(
            'metric_date' => $metric_date,
            'page_path'   => $page_path,
            'pageviews'   => max( 0, $pageviews ),
            'source'      => 'ga4',
        );
    }

    return $rows;
}
