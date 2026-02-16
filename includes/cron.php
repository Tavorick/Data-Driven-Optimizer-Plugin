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
}

/**
 * Ruim alle DDO scheduler events op bij deactivatie.
 */
function ddo_clear_cron_events() {
    wp_clear_scheduled_hook( 'ddo_hourly_fetch' );
    wp_clear_scheduled_hook( 'ddo_weekly_retrain' );
    wp_clear_scheduled_hook( 'ddo_daily_introspect' );
}

/**
 * Koppel cron-hooks aan hun handlers in losse modules.
 */
function ddo_register_cron_callbacks() {
    add_action( 'ddo_hourly_fetch', 'ddo_run_hourly_fetch_job' );
    add_action( 'ddo_weekly_retrain', 'ddo_run_weekly_retrain_job' );
    add_action( 'ddo_daily_introspect', 'ddo_run_daily_introspect_job' );
}


/**
 * Voer een geplande job uit met centrale logging en foutafhandeling.
 *
 * @param string   $job_name  Naam van de job.
 * @param callable $callback  Uit te voeren callback.
 */
function ddo_execute_scheduled_job( $job_name, $callback ) {
    ddo_log_scheduler_event( $job_name, 'job-start' );

    try {
        call_user_func( $callback );
        ddo_log_scheduler_event( $job_name, 'job-end' );
    } catch ( Exception $exception ) {
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
