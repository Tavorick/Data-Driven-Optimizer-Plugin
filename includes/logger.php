<?php
/**
 * Centrale logging utilities voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Log een scheduler event met consistente DDO-prefix.
 *
 * @param string $job_name Naam van de geplande job.
 * @param string $message  Logbericht.
 * @param string $level    Logniveau (info|error).
 * @param array  $context  Extra context voor debuggen.
 */
function ddo_log_scheduler_event( $job_name, $message, $level = 'info', $context = array() ) {
    $normalized_level   = in_array( $level, array( 'info', 'error' ), true ) ? $level : 'info';
    $safe_message       = ddo_redact_sensitive_log_message( (string) $message );
    $normalized_context = ddo_normalize_scheduler_log_context( $job_name, $context );

    $log_line = sprintf(
        '[ddo_scheduler][%1$s][%2$s] %3$s',
        $normalized_level,
        $job_name,
        $safe_message
    );

    if ( ! empty( $normalized_context ) ) {
        $encoded_context = wp_json_encode( $normalized_context );

        if ( false !== $encoded_context ) {
            $log_line .= ' | context=' . $encoded_context;
        }
    }

    ddo_store_scheduler_event(
        array(
            'timestamp' => time(),
            'level'     => $normalized_level,
            'job_name'  => $job_name,
            'message'   => $safe_message,
            'context'   => $normalized_context,
        )
    );

    error_log( $log_line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}


/**
 * Controleer of contextkey gevoelig materiaal kan bevatten.
 *
 * @param string $key Contextsleutel.
 * @return bool
 */
function ddo_is_sensitive_log_key( $key ) {
    $key = strtolower( (string) $key );

    if ( '' === $key ) {
        return false;
    }

    if ( in_array( $key, array( 'token_uri', 'oauth_token_uri' ), true ) ) {
        return false;
    }

    foreach ( array( 'api_key', 'apikey', 'secret', 'client_secret', 'private_key', 'token', 'refresh_token', 'bearer', 'signature', 'hash', 'password', 'authorization' ) as $needle ) {
        if ( false !== strpos( $key, $needle ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Redigeer gevoelige logwaarden recursief.
 *
 * @param mixed       $value Te redigeren waarde.
 * @param string|null $key   Optionele contextsleutel.
 * @return mixed
 */
function ddo_redact_sensitive_log_data( $value, $key = '' ) {
    if ( is_array( $value ) ) {
        $redacted = array();

        foreach ( $value as $child_key => $child_value ) {
            $redacted[ $child_key ] = ddo_redact_sensitive_log_data( $child_value, (string) $child_key );
        }

        return $redacted;
    }

    if ( ddo_is_sensitive_log_key( $key ) ) {
        return '[redacted]';
    }

    if ( is_string( $value ) ) {
        return ddo_redact_sensitive_log_message( $value );
    }

    return $value;
}

/**
 * Redigeer gevoelige patronen uit logberichten.
 *
 * @param string $message Logbericht.
 * @return string
 */
function ddo_redact_sensitive_log_message( $message ) {
    $message = (string) $message;

    $patterns = array(
        '/(api[_-]?key\s*[=:]\s*)([^\s,;]+)/i',
        '/(token\s*[=:]\s*)([^\s,;]+)/i',
        '/(refresh[_-]?token\s*[=:]\s*)([^\s,;]+)/i',
        '/(client[_-]?secret\s*[=:]\s*)([^\s,;]+)/i',
        '/(private[_-]?key\s*[=:]\s*)([^\s,;]+)/i',
        '/(secret\s*[=:]\s*)([^\s,;]+)/i',
        '/(signature\s*[=:]\s*)([^\s,;]+)/i',
        '/(authorization\s*[=:]\s*)([^\s,;]+)/i',
        '/(bearer\s+)([A-Za-z0-9._\-]+)/i',
        '/\b[a-f0-9]{64}\b/i',
    );

    $replacements = array(
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '$1[redacted]',
        '[redacted-hash]',
    );

    return preg_replace( $patterns, $replacements, $message );
}

/**
 * Normaliseer scheduler context met consistente observability velden.
 *
 * @param string $job_name Jobnaam.
 * @param array  $context  Inkomende context.
 * @return array
 */
function ddo_normalize_scheduler_log_context( $job_name, $context ) {
    $context      = ddo_redact_sensitive_log_data( is_array( $context ) ? $context : array() );
    $duration_raw = isset( $context['duration'] ) ? $context['duration'] : ( isset( $context['duration_ms'] ) ? ( (float) $context['duration_ms'] / 1000 ) : 0 );

    if ( isset( $context['result_count'] ) ) {
        $result_count = (int) $context['result_count'];
    } elseif ( isset( $context['processed_count'] ) ) {
        $result_count = (int) $context['processed_count'];
    } elseif ( isset( $context['deleted_rows'] ) ) {
        $result_count = (int) $context['deleted_rows'];
    } else {
        $result_count = 0;
    }

    $error_code = isset( $context['error_code'] )
        ? (string) $context['error_code']
        : ( isset( $context['code'] ) ? (string) $context['code'] : '' );

    return array_merge(
        $context,
        array(
            'job'          => (string) $job_name,
            'duration'     => max( 0, (float) $duration_raw ),
            'result_count' => max( 0, $result_count ),
            'error_code'   => $error_code,
        )
    );
}

/**
 * Bewaar recente scheduler events in een rolling optie voor admin observability.
 *
 * @param array $event Event payload.
 */
function ddo_store_scheduler_event( $event ) {
    $events   = get_option( 'ddo_scheduler_events', array() );
    $events   = is_array( $events ) ? $events : array();
    $events[] = $event;

    if ( count( $events ) > 200 ) {
        $events = array_slice( $events, -200 );
    }

    update_option( 'ddo_scheduler_events', $events, false );
}

/**
 * Haal recente scheduler events op, nieuwste eerst.
 *
 * @param int $limit Maximaal aantal events.
 * @return array
 */
function ddo_get_recent_scheduler_events( $limit = 20 ) {
    $events = get_option( 'ddo_scheduler_events', array() );
    $events = is_array( $events ) ? $events : array();
    $events = array_reverse( $events );

    return array_slice( $events, 0, max( 1, (int) $limit ) );
}
