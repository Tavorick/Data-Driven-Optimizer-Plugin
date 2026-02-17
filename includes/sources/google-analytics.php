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
    $access_token     = ddo_get_ga4_access_token( $service_secret, $legacy_api_token );

    if ( is_wp_error( $access_token ) ) {
        ddo_log_scheduler_event(
            $job_name,
            'ga4-access-token-unavailable',
            'error',
            array(
                'error_code' => ddo_get_wp_error_code_safe( $access_token ),
                'message'    => ddo_get_wp_error_message_safe( $access_token ),
            )
        );

        return $access_token;
    }

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
 * Valideer GA4 runtime configuratie voordat requests worden uitgevoerd.
 *
 * @return true|WP_Error
 */
function ddo_validate_ga4_runtime_config() {
    $property_id      = sanitize_text_field( (string) get_option( 'ddo_ga4_property_id', '' ) );
    $service_secret   = ddo_get_secret_option( 'ddo_ga4_service_account_json' );
    $legacy_api_token = ddo_get_api_key( 'ddo_api_key_primary' );

    $property_id      = is_string( $property_id ) ? trim( $property_id ) : '';
    $service_secret   = is_string( $service_secret ) ? trim( $service_secret ) : '';
    $legacy_api_token = is_string( $legacy_api_token ) ? trim( $legacy_api_token ) : '';

    $context = array(
        'property_id_present' => '' !== $property_id,
        'secret_present'      => '' !== $service_secret,
        'mode'                => 'missing',
    );

    if ( '' !== $service_secret && '{' === substr( ltrim( $service_secret ), 0, 1 ) ) {
        $context['mode'] = 'service_account_json';

        $credentials = json_decode( $service_secret, true );

        if ( ! is_array( $credentials ) ) {
            if ( '' !== $legacy_api_token ) {
                $context['mode'] = 'fallback_token';
            } else {
                return new WP_Error( 'ddo_ga4_missing_config', __( 'GA4-configuratie is incompleet. Vul property ID + service account JSON in.', 'data-driven-optimizer' ), $context );
            }
        } elseif ( empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
            return new WP_Error( 'ddo_ga4_missing_config', __( 'GA4-configuratie is incompleet. Vul property ID + service account JSON in.', 'data-driven-optimizer' ), $context );
        }
    } elseif ( '' !== $service_secret || '' !== $legacy_api_token ) {
        $context['mode'] = 'fallback_token';
    }

    if ( 1 !== preg_match( '/^\d{4,20}$/', $property_id ) ) {
        return new WP_Error( 'ddo_ga4_missing_config', __( 'GA4-configuratie is incompleet. Vul property ID + service account JSON in.', 'data-driven-optimizer' ), $context );
    }

    if ( 'missing' === $context['mode'] ) {
        return new WP_Error( 'ddo_ga4_missing_config', __( 'GA4-configuratie is incompleet. Vul property ID + service account JSON in.', 'data-driven-optimizer' ), $context );
    }

    return true;
}

/**
 * Resolve GA4 access token from a service-account secret or fallback token.
 *
 * @param string $service_secret Service-account JSON of direct bearer token.
 * @param string $fallback_token Legacy fallback token.
 * @return string|WP_Error
 */
function ddo_get_ga4_access_token( $service_secret, $fallback_token = '' ) {
    $service_secret = is_string( $service_secret ) ? trim( $service_secret ) : '';
    $fallback_token = is_string( $fallback_token ) ? trim( $fallback_token ) : '';

    if ( '' === $service_secret ) {
        return $fallback_token;
    }

    if ( '{' !== substr( $service_secret, 0, 1 ) ) {
        return $service_secret;
    }

    $credentials = json_decode( $service_secret, true );

    if ( ! is_array( $credentials ) ) {
        return '' !== $fallback_token
            ? $fallback_token
            : new WP_Error( 'ddo_ga4_service_account_json_invalid', __( 'GA4 service-account JSON is ongeldig.', 'data-driven-optimizer' ) );
    }

    if ( empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
        return '' !== $fallback_token
            ? $fallback_token
            : new WP_Error( 'ddo_ga4_service_account_json_missing_fields', __( 'GA4 service-account JSON mist verplichte velden.', 'data-driven-optimizer' ) );
    }

    return ddo_request_ga4_service_account_access_token( $credentials );
}

/**
 * Request an OAuth2 access token from Google using service-account credentials.
 *
 * @param array $credentials Service-account credentials.
 * @return string|WP_Error
 */
function ddo_request_ga4_service_account_access_token( $credentials ) {
    if ( ! function_exists( 'openssl_sign' ) ) {
        return new WP_Error( 'ddo_ga4_openssl_missing', __( 'OpenSSL ondersteuning ontbreekt voor GA4 authenticatie.', 'data-driven-optimizer' ) );
    }

    $client_email = sanitize_text_field( (string) $credentials['client_email'] );
    $private_key  = str_replace( "\\n", "\n", (string) $credentials['private_key'] );
    $token_uri    = isset( $credentials['token_uri'] ) ? esc_url_raw( (string) $credentials['token_uri'] ) : 'https://oauth2.googleapis.com/token';

    if ( '' === $client_email || '' === $private_key || '' === $token_uri ) {
        return new WP_Error( 'ddo_ga4_service_account_invalid', __( 'GA4 service-account credentials zijn onvolledig.', 'data-driven-optimizer' ) );
    }

    $issued_at = time();
    $payload   = array(
        'iss'   => $client_email,
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud'   => $token_uri,
        'iat'   => $issued_at,
        'exp'   => $issued_at + 3600,
    );

    $jwt_header   = ddo_base64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
    $jwt_payload  = ddo_base64url_encode( wp_json_encode( $payload ) );
    $jwt_unsigned = $jwt_header . '.' . $jwt_payload;

    $signature = '';
    $signed    = openssl_sign( $jwt_unsigned, $signature, $private_key, OPENSSL_ALGO_SHA256 );

    if ( ! $signed ) {
        return new WP_Error( 'ddo_ga4_jwt_sign_failed', __( 'Kon GA4 service-account JWT niet ondertekenen.', 'data-driven-optimizer' ) );
    }

    $assertion = $jwt_unsigned . '.' . ddo_base64url_encode( $signature );

    $token_response = wp_remote_post(
        $token_uri,
        array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ),
        )
    );

    if ( is_wp_error( $token_response ) ) {
        return new WP_Error( 'ddo_ga4_token_request_failed', __( 'Kon geen GA4 access token ophalen.', 'data-driven-optimizer' ) );
    }

    $status_code = (int) wp_remote_retrieve_response_code( $token_response );
    $token_body  = json_decode( (string) wp_remote_retrieve_body( $token_response ), true );

    if ( $status_code >= 400 || ! is_array( $token_body ) || empty( $token_body['access_token'] ) ) {
        return new WP_Error( 'ddo_ga4_token_invalid_response', __( 'GA4 token endpoint gaf een ongeldige response.', 'data-driven-optimizer' ) );
    }

    return sanitize_text_field( (string) $token_body['access_token'] );
}

/**
 * Base64-url encode helper for JWT signing.
 *
 * @param string $value Input value.
 * @return string
 */
function ddo_base64url_encode( $value ) {
    return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
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
