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
 * Voer een geplande job uit met centrale logging en foutafhandeling.
 *
 * @param string   $job_name  Naam van de job.
 * @param callable $callback  Uit te voeren callback.
 */
function ddo_execute_scheduled_job( $job_name, $callback ) {
    ddo_update_scheduler_job_metadata(
        $job_name,
        array(
            'last_start' => time(),
        )
    );

    ddo_log_scheduler_event( $job_name, 'job-start' );

    try {
        call_user_func( $callback );

        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_success'       => time(),
                'last_error_message' => '',
            )
        );

        ddo_log_scheduler_event( $job_name, 'job-end' );
    } catch ( Exception $exception ) {
        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_error_message' => $exception->getMessage(),
                'last_error_at'      => time(),
            )
        );

        ddo_log_scheduler_event(
            $job_name,
            'job-error',
            'error',
            array(
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
            )
        );
    } catch ( Error $error ) {
        ddo_update_scheduler_job_metadata(
            $job_name,
            array(
                'last_error_message' => $error->getMessage(),
                'last_error_at'      => time(),
            )
        );

        ddo_log_scheduler_event(
            $job_name,
            'job-error',
            'error',
            array(
                'message' => $error->getMessage(),
                'code'    => $error->getCode(),
            )
        );
    }
}
