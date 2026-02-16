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
 * Handler voor dagelijkse feedback cleanup.
 */
function ddo_run_daily_feedback_cleanup_job() {
    ddo_execute_scheduled_job(
        'ddo_daily_feedback_cleanup',
        function () {
            $deleted_rows = ddo_cleanup_feedback_data();

            ddo_log_scheduler_event(
                'ddo_daily_feedback_cleanup',
                'cleanup-complete',
                'info',
                array(
                    'deleted_rows'   => $deleted_rows,
                    'retention_days' => ddo_get_feedback_retention_days(),
                )
            );
        }
    );
}

/**
 * Haal retentieperiode op voor feedbackdata.
 *
 * @return int
 */
function ddo_get_feedback_retention_days() {
    $retention_days = (int) get_option( 'ddo_feedback_retention_days', 180 );

    if ( $retention_days < 7 ) {
        return 180;
    }

    return min( 3650, $retention_days );
}

/**
 * Verwijder feedbackrecords ouder dan de ingestelde retentie.
 *
 * @return int Aantal verwijderde records.
 */
function ddo_cleanup_feedback_data() {
    global $wpdb;

    $feedback_table = $wpdb->prefix . 'ddo_feedback';
    $retention_days = ddo_get_feedback_retention_days();
    $cutoff_date    = gmdate( 'Y-m-d', time() - ( $retention_days * DAY_IN_SECONDS ) );

    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$feedback_table} WHERE feedback_date < %s",
            $cutoff_date
        )
    );

    if ( false === $deleted ) {
        ddo_log_scheduler_event(
            'ddo_daily_feedback_cleanup',
            'cleanup-query-failed',
            'error',
            array(
                'cutoff_date'    => $cutoff_date,
                'retention_days' => $retention_days,
            )
        );

        return 0;
    }

    return (int) $deleted;
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
    $campaign_id  = isset( $payload['campaign_id'] ) ? sanitize_text_field( $payload['campaign_id'] ) : '';
    $ad_id        = isset( $payload['ad_id'] ) ? sanitize_text_field( $payload['ad_id'] ) : '';

    return array(
        'event'      => $event_name,
        'score'      => max( 0, min( 10, $score ) ),
        'clientHash' => '' !== $client_token ? wp_hash( $client_token ) : '',
        'campaignId' => '' !== $campaign_id ? $campaign_id : 'general',
        'adId'       => '' !== $ad_id ? $ad_id : 'general',
        'timestamp'  => time(),
    );
}

/**
 * Sla feedback payload op in ddo_feedback tabel.
 *
 * @param array $payload Ruwe payload.
 * @return int|WP_Error
 */
function ddo_store_feedback_payload( $payload ) {
    global $wpdb;

    $prepared = ddo_prepare_feedback_payload( $payload );

    if ( '' === $prepared['event'] ) {
        return new WP_Error( 'ddo_feedback_event_missing', __( 'Feedback event is verplicht.', 'data-driven-optimizer' ), array( 'status' => 400 ) );
    }

    if ( strlen( $prepared['event'] ) < 2 || strlen( $prepared['event'] ) > 50 ) {
        return new WP_Error( 'ddo_feedback_event_length_invalid', __( 'Feedback event moet tussen 2 en 50 tekens lang zijn.', 'data-driven-optimizer' ), array( 'status' => 422 ) );
    }

    if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]*$/', $prepared['event'] ) ) {
        return new WP_Error( 'ddo_feedback_event_format_invalid', __( 'Feedback event bevat ongeldige tekens.', 'data-driven-optimizer' ), array( 'status' => 422 ) );
    }

    if ( $prepared['score'] < 0 || $prepared['score'] > 10 ) {
        return new WP_Error( 'ddo_feedback_score_range_invalid', __( 'Feedback score moet tussen 0 en 10 liggen.', 'data-driven-optimizer' ), array( 'status' => 422 ) );
    }


    if ( strlen( $prepared['campaignId'] ) > 80 ) {
        return new WP_Error( 'ddo_feedback_campaign_id_length_invalid', __( 'Feedback campaign_id mag maximaal 80 tekens bevatten.', 'data-driven-optimizer' ), array( 'status' => 422 ) );
    }

    if ( strlen( $prepared['adId'] ) > 80 ) {
        return new WP_Error( 'ddo_feedback_ad_id_length_invalid', __( 'Feedback ad_id mag maximaal 80 tekens bevatten.', 'data-driven-optimizer' ), array( 'status' => 422 ) );
    }

    $feedback_table = $wpdb->prefix . 'ddo_feedback';

    $result = $wpdb->insert(
        $feedback_table,
        array(
            'concept_id'     => null,
            'campaign_id'    => $prepared['campaignId'],
            'ad_id'          => $prepared['adId'],
            'feedback_date'  => gmdate( 'Y-m-d', $prepared['timestamp'] ),
            'feedback_text'  => '',
            'status'         => 'open',
            'event_name'     => $prepared['event'],
            'score'          => $prepared['score'],
            'client_hash'    => $prepared['clientHash'],
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
    );

    if ( false === $result ) {
        return new WP_Error( 'ddo_feedback_insert_failed', __( 'Feedback kon niet worden opgeslagen.', 'data-driven-optimizer' ), array( 'status' => 500 ) );
    }

    return (int) $wpdb->insert_id;
}

/**
 * Geef geaggregeerde feedbackdata terug voor dashboard/API.
 *
 * @return array
 */
function ddo_get_feedback_summary() {
    global $wpdb;

    $feedback_table = $wpdb->prefix . 'ddo_feedback';

    $totals = $wpdb->get_row(
        "SELECT COUNT(*) AS total_items, ROUND(AVG(score), 2) AS average_score FROM {$feedback_table}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );

    $event_rows = $wpdb->get_results(
        "SELECT event_name, COUNT(*) AS total_items, ROUND(AVG(score), 2) AS average_score
        FROM {$feedback_table}
        WHERE event_name <> ''
        GROUP BY event_name
        ORDER BY total_items DESC
        LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );

    $recent_rows = $wpdb->get_results(
        "SELECT id, event_name, score, feedback_date, status, campaign_id, ad_id
        FROM {$feedback_table}
        ORDER BY id DESC
        LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );

    return array(
        'totals' => array(
            'count'        => isset( $totals['total_items'] ) ? (int) $totals['total_items'] : 0,
            'averageScore' => isset( $totals['average_score'] ) ? (float) $totals['average_score'] : 0,
        ),
        'events' => is_array( $event_rows ) ? $event_rows : array(),
        'recent' => is_array( $recent_rows ) ? $recent_rows : array(),
    );
}
