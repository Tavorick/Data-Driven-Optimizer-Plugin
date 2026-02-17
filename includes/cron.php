<?php
/**
 * WP-Cron scheduling voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Voeg custom interval toe voor zware calls (max 1x per 15 min).
 *
 * @param array $schedules Beschikbare WP-Cron schedules.
 * @return array
 */
function ddo_add_cron_schedules( $schedules ) {
    $schedules['ddo_every_15_minutes'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => __( 'Elke 15 minuten (DDO)', 'data-driven-optimizer' ),
    );

    return $schedules;
}
add_filter( 'cron_schedules', 'ddo_add_cron_schedules' );

/**
 * Haal scheduler metadata op uit de opties.
 *
 * @return array
 */
function ddo_get_scheduler_job_metadata() {
    $metadata = get_option( 'ddo_scheduler_job_metadata', array() );

    return is_array( $metadata ) ? $metadata : array();
}

/**
 * Werk scheduler metadata bij voor een specifieke job.
 *
 * @param string $job_name Jobnaam.
 * @param array  $updates  Te updaten velden.
 */
function ddo_update_scheduler_job_metadata( $job_name, $updates ) {
    $metadata = ddo_get_scheduler_job_metadata();
    $current  = isset( $metadata[ $job_name ] ) && is_array( $metadata[ $job_name ] ) ? $metadata[ $job_name ] : array();

    $metadata[ $job_name ] = array_merge( $current, $updates );

    update_option( 'ddo_scheduler_job_metadata', $metadata, false );
}

/**
 * Beschikbare scheduler jobs voor observability in admin.
 *
 * @return array
 */

/**
 * Voeg een run-resultaat toe aan rolling window metadata (laatste 10 runs).
 *
 * @param string $job_name Jobnaam.
 * @param bool   $success  Of de run succesvol was.
 */
function ddo_record_scheduler_run_outcome( $job_name, $success ) {
    $metadata = ddo_get_scheduler_job_metadata();
    $current  = isset( $metadata[ $job_name ] ) && is_array( $metadata[ $job_name ] ) ? $metadata[ $job_name ] : array();
    $history  = isset( $current['run_history'] ) && is_array( $current['run_history'] ) ? $current['run_history'] : array();

    $history[] = array(
        'timestamp' => time(),
        'success'   => (bool) $success,
    );

    if ( count( $history ) > 10 ) {
        $history = array_slice( $history, -10 );
    }

    ddo_update_scheduler_job_metadata(
        $job_name,
        array(
            'run_history' => array_values( $history ),
        )
    );
}

/**
 * Bereken health KPI's op basis van rolling window (laatste 10 runs).
 *
 * @param array $job_meta Job metadata.
 * @return array
 */
function ddo_get_scheduler_job_health_kpis( $job_meta ) {
    $job_meta     = is_array( $job_meta ) ? $job_meta : array();
    $history      = isset( $job_meta['run_history'] ) && is_array( $job_meta['run_history'] ) ? array_slice( $job_meta['run_history'], -10 ) : array();
    $total_runs   = count( $history );
    $success_runs = 0;

    foreach ( $history as $run ) {
        if ( ! empty( $run['success'] ) ) {
            $success_runs++;
        }
    }

    $success_rate = $total_runs > 0 ? ( $success_runs / $total_runs ) * 100 : 0;
    $last_success = isset( $job_meta['last_success'] ) ? (int) $job_meta['last_success'] : 0;

    if ( 0 === $total_runs ) {
        $status = 'down';
    } elseif ( $success_rate >= 80 ) {
        $status = 'healthy';
    } elseif ( $success_rate >= 40 ) {
        $status = 'degraded';
    } else {
        $status = 'down';
    }

    return array(
        'total_runs'    => $total_runs,
        'success_runs'  => $success_runs,
        'success_rate'  => $success_rate,
        'last_success'  => $last_success,
        'status'        => $status,
    );
}

function ddo_get_scheduler_observability_jobs() {
    return array(
        'ddo_hourly_fetch'    => array(
            'label'             => __( 'Hourly fetch', 'data-driven-optimizer' ),
            'expected_interval' => 15 * MINUTE_IN_SECONDS,
        ),
        'ddo_weekly_retrain'  => array(
            'label'             => __( 'Weekly retrain', 'data-driven-optimizer' ),
            'expected_interval' => WEEK_IN_SECONDS,
        ),
        'ddo_daily_introspect' => array(
            'label'             => __( 'Daily introspect', 'data-driven-optimizer' ),
            'expected_interval' => DAY_IN_SECONDS,
        ),
    );
}

/**
 * Registreer alle scheduler events bij activatie.
 */
function ddo_register_cron_events() {
    if ( ! wp_next_scheduled( 'ddo_hourly_fetch' ) ) {
        wp_schedule_event( time(), 'ddo_every_15_minutes', 'ddo_hourly_fetch' );
    }

    if ( ! wp_next_scheduled( 'ddo_weekly_retrain' ) ) {
        wp_schedule_event( time(), 'weekly', 'ddo_weekly_retrain' );
    }

    if ( ! wp_next_scheduled( 'ddo_daily_introspect' ) ) {
        wp_schedule_event( time(), 'daily', 'ddo_daily_introspect' );
    }

    if ( ! wp_next_scheduled( 'ddo_daily_feedback_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'ddo_daily_feedback_cleanup' );
    }
}

/**
 * Ruim alle DDO scheduler events op bij deactivatie.
 */
function ddo_clear_cron_events() {
    wp_clear_scheduled_hook( 'ddo_hourly_fetch' );
    wp_clear_scheduled_hook( 'ddo_weekly_retrain' );
    wp_clear_scheduled_hook( 'ddo_daily_introspect' );
    wp_clear_scheduled_hook( 'ddo_daily_feedback_cleanup' );
}

/**
 * Koppel cron-hooks aan hun handlers in losse modules.
 */
function ddo_register_cron_callbacks() {
    add_action( 'ddo_hourly_fetch', 'ddo_run_hourly_fetch_job' );
    add_action( 'ddo_weekly_retrain', 'ddo_run_weekly_retrain_job' );
    add_action( 'ddo_daily_introspect', 'ddo_run_daily_introspect_job' );
    add_action( 'ddo_daily_feedback_cleanup', 'ddo_run_daily_feedback_cleanup_job' );
}

/**
 * Normaliseer foutcodes uit exceptions/errors naar consistente stringcodes.
 *
 * @param Throwable $throwable Opgevangen foutobject.
 * @return string
 */
function ddo_normalize_scheduler_error_code( $throwable ) {
    if ( is_object( $throwable ) && method_exists( $throwable, 'get_ddo_error_code' ) ) {
        $ddo_error_code = (string) $throwable->get_ddo_error_code();

        if ( '' !== $ddo_error_code ) {
            return $ddo_error_code;
        }
    }

    if ( is_object( $throwable ) && method_exists( $throwable, 'getCode' ) ) {
        $code = (string) $throwable->getCode();

        if ( '' !== $code && '0' !== $code ) {
            return $code;
        }
    }

    return 'ddo_scheduler_job_failed';
}


/**
 * Voer een geplande job uit met centrale logging en foutafhandeling.
 *
 * @param string   $job_name  Naam van de job.
 * @param callable $callback  Uit te voeren callback.
 */
function ddo_execute_scheduled_job( $job_name, $callback ) {
    $started_at = microtime( true );

    ddo_update_scheduler_job_metadata(
        $job_name,
        array(
            'last_start' => time(),
        )
    );

    ddo_log_scheduler_event( $job_name, 'job-start' );

    try {
        $result   = call_user_func( $callback );
        $result   = is_array( $result ) ? $result : array();
        $duration = max( 0, microtime( true ) - $started_at );

        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_success'       => time(),
                'last_run_duration'  => round( $duration, 3 ),
                'last_error_message' => '',
                'last_error_code'    => '',
                'last_result'        => $result,
            )
        );

        ddo_record_scheduler_run_outcome( $job_name, true );

        ddo_log_scheduler_event(
            $job_name,
            'job-end',
            'info',
            array(
                'duration'     => round( $duration, 3 ),
                'result_count' => ddo_extract_scheduler_result_count( $result ),
                'error_code'   => isset( $result['error_code'] ) ? (string) $result['error_code'] : '',
            )
        );
    } catch ( Exception $exception ) {
        $duration = max( 0, microtime( true ) - $started_at );
        $error_code = ddo_normalize_scheduler_error_code( $exception );

        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_error_message' => $exception->getMessage(),
                'last_error_code'    => $error_code,
                'last_error_at'      => time(),
                'last_run_duration'  => round( $duration, 3 ),
            )
        );

        ddo_record_scheduler_run_outcome( $job_name, false );

        ddo_log_scheduler_event(
            $job_name,
            'job-error',
            'error',
            array(
                'duration'   => round( $duration, 3 ),
                'message'    => $exception->getMessage(),
                'error_code' => $error_code,
            )
        );
    } catch ( Error $error ) {
        $duration = max( 0, microtime( true ) - $started_at );
        $error_code = ddo_normalize_scheduler_error_code( $error );

        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_error_message' => $error->getMessage(),
                'last_error_code'    => $error_code,
                'last_error_at'      => time(),
                'last_run_duration'  => round( $duration, 3 ),
            )
        );

        ddo_record_scheduler_run_outcome( $job_name, false );

        ddo_log_scheduler_event(
            $job_name,
            'job-error',
            'error',
            array(
                'duration'   => round( $duration, 3 ),
                'message'    => $error->getMessage(),
                'error_code' => $error_code,
            )
        );
    }
}

/**
 * Extraheer het genormaliseerde resultaatvolume uit schedulerresultaten.
 *
 * @param array $result Resultaatpayload.
 * @return int
 */
function ddo_extract_scheduler_result_count( $result ) {
    if ( isset( $result['result_count'] ) ) {
        return max( 0, (int) $result['result_count'] );
    }

    if ( isset( $result['processed_count'] ) ) {
        return max( 0, (int) $result['processed_count'] );
    }

    if ( isset( $result['deleted_rows'] ) ) {
        return max( 0, (int) $result['deleted_rows'] );
    }

    return 0;
}
