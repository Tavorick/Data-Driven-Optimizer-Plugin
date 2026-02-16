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
            ddo_process_code_introspection();
        }
    );
}

/**
 * Placeholder voor introspectielogica.
 */
function ddo_process_code_introspection() {
    do_action( 'ddo_code_introspection' );
}
