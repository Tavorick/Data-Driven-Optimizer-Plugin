<?php
/**
 * ML feedback handlers voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handler voor wekelijkse model retraining.
 */
function ddo_run_weekly_retrain_job() {
    ddo_execute_scheduled_job(
        'ddo_weekly_retrain',
        function () {
            ddo_process_ml_feedback_retrain();
        }
    );
}

/**
 * Placeholder voor retrain-logica.
 */
function ddo_process_ml_feedback_retrain() {
    do_action( 'ddo_ml_feedback_retrain' );
}
