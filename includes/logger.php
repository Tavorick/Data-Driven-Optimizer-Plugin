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
    $normalized_level = in_array( $level, array( 'info', 'error' ), true ) ? $level : 'info';

    $log_line = sprintf(
        '[ddo_scheduler][%1$s][%2$s] %3$s',
        $normalized_level,
        $job_name,
        $message
    );

    if ( ! empty( $context ) ) {
        $encoded_context = wp_json_encode( $context );

        if ( false !== $encoded_context ) {
            $log_line .= ' | context=' . $encoded_context;
        }
    }

    error_log( $log_line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}
