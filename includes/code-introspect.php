<?php
/**
 * Code introspection handlers voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handler voor dagelijkse introspectie.
 */
function ddo_run_daily_introspect_job() {
    ddo_execute_scheduled_job(
        'ddo_daily_introspect',
        function () {
            $result = ddo_process_code_introspection();

            ddo_update_scheduler_job_metadata(
                'ddo_daily_introspect',
                array(
                    'last_result' => $result,
                )
            );
        }
    );
}

/**
 * Configureer een custom code introspect service.
 *
 * @param callable|null $service Service-callback.
 */
function ddo_set_code_introspection_service( $service ) {
    $GLOBALS['ddo_code_introspection_service'] = $service;
}

/**
 * Haal de actieve code introspect service op.
 *
 * @return callable
 */
function ddo_get_code_introspection_service() {
    $service = isset( $GLOBALS['ddo_code_introspection_service'] ) ? $GLOBALS['ddo_code_introspection_service'] : 'ddo_default_code_introspection_service';

    if ( ! is_callable( $service ) ) {
        return 'ddo_default_code_introspection_service';
    }

    return $service;
}

/**
 * Default code analyse service voor introspectie.
 *
 * @return array
 */
function ddo_default_code_introspection_service() {
    return array(
        'files_scanned' => 0,
        'issues_found'  => 0,
    );
}

/**
 * Verwerk introspectielogica voor code-analyse.
 *
 * @return array
 */
function ddo_process_code_introspection() {
    $started_at = microtime( true );
    $service    = ddo_get_code_introspection_service();
    $payload    = call_user_func( $service );
    $payload    = is_array( $payload ) ? $payload : array();

    $records_processed = isset( $payload['files_scanned'] ) ? (int) $payload['files_scanned'] : 0;
    $error_code        = isset( $payload['error_code'] ) ? (string) $payload['error_code'] : '';

    $result = array(
        'job'               => 'ddo_daily_introspect',
        'service'           => is_string( $service ) ? $service : 'custom-code-introspection-service',
        'records_processed' => max( 0, $records_processed ),
        'duration_ms'       => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
        'error_code'        => $error_code,
    );

    if ( '' !== $error_code ) {
        ddo_log_scheduler_event( 'ddo_daily_introspect', 'introspection-failed', 'error', $result );

        throw new RuntimeException( 'Code introspection failed.', 0 );
    }

    ddo_log_scheduler_event( 'ddo_daily_introspect', 'introspection-complete', 'info', $result );

    return $result;
}
