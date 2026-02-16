<?php
/**
 * ML feedback handlers voor Data Driven Optimizer.
 *
 * Dataminimalisatiebeleid:
 * - Sla alleen functioneel noodzakelijke feedbackvelden op (event, score, geaggregeerde metadata).
 * - Vermijd opslag van ruwe inhoud, persoonlijke gegevens en directe identifiers.
 * - Pseudonimiseer client-identifiers via hashing met WordPress salts.
 * - Bewaar feedbackdata maximaal zolang nodig is voor modelverbetering.
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

/**
 * Sanitiseer en anonimiseer feedback payload.
 *
 * @param array $payload Ruwe feedbackdata.
 * @return array
 */
function ddo_prepare_feedback_payload( $payload ) {
    $payload      = is_array( $payload ) ? $payload : array();
    $event_name   = isset( $payload['event'] ) ? sanitize_key( $payload['event'] ) : '';
    $score        = isset( $payload['score'] ) ? (int) $payload['score'] : 0;
    $client_token = isset( $payload['client_id'] ) ? sanitize_text_field( $payload['client_id'] ) : '';

    return array(
        'event'      => $event_name,
        'score'      => max( 0, min( 10, $score ) ),
        'clientHash' => '' !== $client_token ? wp_hash( $client_token ) : '',
        'timestamp'  => time(),
    );
}
