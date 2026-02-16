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
            $result = ddo_process_ml_feedback_retrain();

            ddo_update_scheduler_job_metadata(
                'ddo_weekly_retrain',
                array(
                    'last_result' => $result,
                )
            );
        }
    );
}

/**
 * Configureer een custom model retrain service.
 *
 * @param callable|null $service Service-callback.
 */
function ddo_set_ml_feedback_retrain_service( $service ) {
    $GLOBALS['ddo_ml_feedback_retrain_service'] = $service;
}

/**
 * Haal de actieve retrain service op.
 *
 * @return callable
 */
function ddo_get_ml_feedback_retrain_service() {
    $service = isset( $GLOBALS['ddo_ml_feedback_retrain_service'] ) ? $GLOBALS['ddo_ml_feedback_retrain_service'] : 'ddo_default_ml_feedback_retrain_service';

    if ( ! is_callable( $service ) ) {
        return 'ddo_default_ml_feedback_retrain_service';
    }

    return $service;
}

/**
 * Default retrain service voor feedbackmodellen.
 *
 * @return array
 */
function ddo_default_ml_feedback_retrain_service() {
    return array(
        'processed_count' => 0,
        'errors_count'    => 0,
        'model_version'   => 'baseline',
    );
}

/**
 * Verwerk retrain-logica voor feedbackmodellen.
 *
 * @return array
 */
function ddo_process_ml_feedback_retrain() {
    $started_at = microtime( true );
    $service    = ddo_get_ml_feedback_retrain_service();
    $payload    = call_user_func( $service );
    $payload    = is_array( $payload ) ? $payload : array();

    $processed_count = isset( $payload['processed_count'] )
        ? (int) $payload['processed_count']
        : ( isset( $payload['trained_samples'] ) ? (int) $payload['trained_samples'] : 0 );
    $error_code      = isset( $payload['error_code'] ) ? (string) $payload['error_code'] : '';
    $errors_count    = isset( $payload['errors_count'] )
        ? (int) $payload['errors_count']
        : ( '' !== $error_code ? 1 : 0 );

    $result = array(
        'job'             => 'ddo_weekly_retrain',
        'service'         => is_string( $service ) ? $service : 'custom-ml-feedback-retrain-service',
        'processed_count' => max( 0, $processed_count ),
        'errors_count'    => max( 0, $errors_count ),
        'duration_ms'     => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
        'error_code'      => $error_code,
    );

    if ( $result['errors_count'] > 0 ) {
        throw new RuntimeException( 'ML feedback retrain failed.', 0 );
    }

    return $result;
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
    $score        = isset( $payload['score'] ) ? (int) $payload['score'] : null;
    $client_token = isset( $payload['client_id'] ) ? sanitize_text_field( $payload['client_id'] ) : '';
    $campaign_id  = isset( $payload['campaign_id'] ) ? sanitize_text_field( $payload['campaign_id'] ) : '';
    $ad_id        = isset( $payload['ad_id'] ) ? sanitize_text_field( $payload['ad_id'] ) : '';

    return array(
        'event'      => $event_name,
        'score'      => null !== $score ? max( 0, min( 10, $score ) ) : null,
        'isScored'   => null !== $score,
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

    if ( $prepared['isScored'] && ( $prepared['score'] < 0 || $prepared['score'] > 10 ) ) {
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
            'is_scored'      => $prepared['isScored'] ? 1 : 0,
            'client_hash'    => $prepared['clientHash'],
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
    );

    if ( false === $result ) {
        return new WP_Error( 'ddo_feedback_insert_failed', __( 'Feedback kon niet worden opgeslagen.', 'data-driven-optimizer' ), array( 'status' => 500 ) );
    }

    return (int) $wpdb->insert_id;
}

/**
 * Geef geaggregeerde feedbackdata terug voor dashboard/API.
 *
 * @param array $filters Optionele filters voor periode en sortering.
 * @return array
 */
function ddo_get_feedback_summary( $filters = array() ) {
    global $wpdb;

    $feedback_table = $wpdb->prefix . 'ddo_feedback';
    $normalized     = ddo_normalize_feedback_filters( $filters );

    if ( ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'get_results' ) ) {
        $rows = isset( $wpdb->feedback_rows ) && is_array( $wpdb->feedback_rows ) ? $wpdb->feedback_rows : array();

        return ddo_build_feedback_summary_from_rows( $rows, $normalized );
    }

    $where_clause = '';
    $where_args   = array();

    if ( $normalized['days'] > 0 ) {
        $where_clause = ' WHERE feedback_date >= %s';
        $where_args[] = gmdate( 'Y-m-d', time() - ( $normalized['days'] * DAY_IN_SECONDS ) );
    }

    $totals_query = "SELECT
        COUNT(*) AS total_items,
        ROUND(AVG(CASE WHEN is_scored = 1 THEN score ELSE NULL END), 2) AS average_score,
        MAX(CASE WHEN is_scored = 1 THEN score ELSE NULL END) AS highest_score,
        MIN(CASE WHEN is_scored = 1 THEN score ELSE NULL END) AS lowest_score,
        SUM(CASE WHEN is_scored = 0 OR score IS NULL THEN 1 ELSE 0 END) AS unscored_items
        FROM {$feedback_table}{$where_clause}";

    if ( ! empty( $where_args ) ) {
        $totals_query = $wpdb->prepare( $totals_query, $where_args );
    }

    $totals = $wpdb->get_row( $totals_query, ARRAY_A );

    $event_order = 'total_items DESC';
    if ( 'score_desc' === $normalized['sort'] ) {
        $event_order = 'average_score DESC, total_items DESC';
    }

    $events_query = "SELECT event_name, COUNT(*) AS total_items, ROUND(AVG(CASE WHEN is_scored = 1 THEN score ELSE NULL END), 2) AS average_score
        FROM {$feedback_table}
        WHERE event_name <> ''";

    if ( ! empty( $where_args ) ) {
        $events_query .= ' AND feedback_date >= %s';
    }

    $events_query .= "
        GROUP BY event_name
        ORDER BY {$event_order}
        LIMIT 5";

    if ( ! empty( $where_args ) ) {
        $events_query = $wpdb->prepare( $events_query, $where_args );
    }

    $event_rows = $wpdb->get_results( $events_query, ARRAY_A );

    $recent_query = "SELECT id, event_name, score, feedback_date, status, campaign_id, ad_id
        FROM {$feedback_table}{$where_clause}
        ORDER BY id DESC
        LIMIT 10";

    if ( ! empty( $where_args ) ) {
        $recent_query = $wpdb->prepare( $recent_query, $where_args );
    }

    $recent_rows = $wpdb->get_results( $recent_query, ARRAY_A );

    return array(
        'totals' => array(
            'count'        => isset( $totals['total_items'] ) ? (int) $totals['total_items'] : 0,
            'averageScore' => isset( $totals['average_score'] ) ? (float) $totals['average_score'] : 0,
            'highestScore' => isset( $totals['highest_score'] ) ? (float) $totals['highest_score'] : 0,
            'lowestScore'  => isset( $totals['lowest_score'] ) ? (float) $totals['lowest_score'] : 0,
            'unscored'     => isset( $totals['unscored_items'] ) ? (int) $totals['unscored_items'] : 0,
        ),
        'events' => is_array( $event_rows ) ? $event_rows : array(),
        'recent' => is_array( $recent_rows ) ? $recent_rows : array(),
        'filters' => $normalized,
    );
}

/**
 * Normaliseer filters voor feedbackrapportage.
 *
 * @param array $filters Inkomende filterwaarden.
 * @return array
 */
function ddo_normalize_feedback_filters( $filters ) {
    $filters = is_array( $filters ) ? $filters : array();
    $days    = isset( $filters['days'] ) ? (int) $filters['days'] : 30;
    $sort    = isset( $filters['sort'] ) ? sanitize_key( $filters['sort'] ) : 'count_desc';

    $allowed_days = array( 0, 7, 30 );
    if ( ! in_array( $days, $allowed_days, true ) ) {
        $days = 30;
    }

    $allowed_sort = array( 'count_desc', 'score_desc' );
    if ( ! in_array( $sort, $allowed_sort, true ) ) {
        $sort = 'count_desc';
    }

    return array(
        'days' => $days,
        'sort' => $sort,
    );
}

/**
 * Bouw dashboardsamenvatting op uit een dataset.
 *
 * @param array $rows     Dataset met feedbackregels.
 * @param array $filters  Genormaliseerde filters.
 * @return array
 */
function ddo_build_feedback_summary_from_rows( $rows, $filters ) {
    $filtered_rows = ddo_filter_feedback_rows( $rows, $filters );
    $totals        = ddo_calculate_feedback_totals( $filtered_rows );
    $events        = ddo_aggregate_feedback_events( $filtered_rows, $filters['sort'] );
    $recent_rows   = $filtered_rows;

    usort(
        $recent_rows,
        function ( $left, $right ) {
            return (int) $right['id'] <=> (int) $left['id'];
        }
    );

    return array(
        'totals' => $totals,
        'events' => array_slice( $events, 0, 5 ),
        'recent' => array_slice( $recent_rows, 0, 10 ),
        'filters' => $filters,
    );
}

/**
 * Filter feedbackregels op basis van periode.
 *
 * @param array $rows    Feedbackregels.
 * @param array $filters Genormaliseerde filters.
 * @return array
 */
function ddo_filter_feedback_rows( $rows, $filters ) {
    if ( empty( $filters['days'] ) ) {
        return $rows;
    }

    $cutoff = gmdate( 'Y-m-d', time() - ( (int) $filters['days'] * DAY_IN_SECONDS ) );

    return array_values(
        array_filter(
            $rows,
            function ( $row ) use ( $cutoff ) {
                return isset( $row['feedback_date'] ) && $row['feedback_date'] >= $cutoff;
            }
        )
    );
}


/**
 * Bepaal of een feedbackregel een score bevat.
 *
 * @param array $row Feedbackregel.
 * @return bool
 */
function ddo_feedback_row_has_score( $row ) {
    if ( isset( $row['is_scored'] ) ) {
        return 1 === (int) $row['is_scored'];
    }

    return isset( $row['score'] ) && '' !== $row['score'] && null !== $row['score'];
}

/**
 * Bereken totaal-KPI's over feedbackregels.
 *
 * @param array $rows Feedbackregels.
 * @return array
 */
function ddo_calculate_feedback_totals( $rows ) {
    $scores       = array();
    $unscored     = 0;
    $total_count  = count( $rows );

    foreach ( $rows as $row ) {
        $has_score = ddo_feedback_row_has_score( $row );

        if ( ! $has_score ) {
            $unscored++;
            continue;
        }

        $scores[] = (float) $row['score'];
    }

    return array(
        'count'        => $total_count,
        'averageScore' => ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ), 2 ) : 0,
        'highestScore' => ! empty( $scores ) ? max( $scores ) : 0,
        'lowestScore'  => ! empty( $scores ) ? min( $scores ) : 0,
        'unscored'     => $unscored,
    );
}

/**
 * Aggregeer feedbackregels per event.
 *
 * @param array  $rows Feedbackregels.
 * @param string $sort Sorteervolgorde.
 * @return array
 */
function ddo_aggregate_feedback_events( $rows, $sort ) {
    $events = array();

    foreach ( $rows as $row ) {
        if ( empty( $row['event_name'] ) ) {
            continue;
        }

        $event_name = (string) $row['event_name'];
        if ( ! isset( $events[ $event_name ] ) ) {
            $events[ $event_name ] = array(
                'event_name'   => $event_name,
                'total_items'  => 0,
                'score_sum'    => 0,
                'score_count'  => 0,
                'average_score'=> 0,
            );
        }

        $events[ $event_name ]['total_items']++;

        if ( ddo_feedback_row_has_score( $row ) ) {
            $events[ $event_name ]['score_sum']   += (float) $row['score'];
            $events[ $event_name ]['score_count'] += 1;
        }
    }

    foreach ( $events as &$event_row ) {
        $event_row['average_score'] = $event_row['score_count'] > 0 ? round( $event_row['score_sum'] / $event_row['score_count'], 2 ) : 0;
        unset( $event_row['score_sum'], $event_row['score_count'] );
    }
    unset( $event_row );

    $events = array_values( $events );

    usort(
        $events,
        function ( $left, $right ) use ( $sort ) {
            if ( 'score_desc' === $sort ) {
                $score_compare = (float) $right['average_score'] <=> (float) $left['average_score'];
                if ( 0 !== $score_compare ) {
                    return $score_compare;
                }
            }

            return (int) $right['total_items'] <=> (int) $left['total_items'];
        }
    );

    return $events;
}
