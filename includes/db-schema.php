<?php
/**
 * Database schema management for the Data Driven Optimizer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DDO_SCHEMA_VERSION', '1.4.0' );

/**
 * Create or update all plugin tables.
 */
function ddo_install_database_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_fb_data = $wpdb->prefix . 'ddo_fb_data';
    $table_ga_data = $wpdb->prefix . 'ddo_ga_data';
    $table_concepts = $wpdb->prefix . 'ddo_concepts';
    $table_feedback = $wpdb->prefix . 'ddo_feedback';
    $table_pageviews_data = $wpdb->prefix . 'ddo_pageviews_data';

    $sql_fb_data = "CREATE TABLE {$table_fb_data} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id VARCHAR(100) NOT NULL,
        ad_id VARCHAR(100) NOT NULL,
        metric_date DATE NOT NULL,
        spend DECIMAL(12,2) NOT NULL DEFAULT 0,
        clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        impressions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_metric_date (metric_date),
        KEY idx_campaign_id (campaign_id),
        KEY idx_ad_id (ad_id),
        KEY idx_status (status),
        KEY idx_campaign_date (campaign_id, metric_date),
        KEY idx_ad_date (ad_id, metric_date)
    ) {$charset_collate};";

    $sql_ga_data = "CREATE TABLE {$table_ga_data} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id VARCHAR(100) NOT NULL,
        ad_id VARCHAR(100) NOT NULL DEFAULT '',
        metric_date DATE NOT NULL,
        sessions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        users BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        conversions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_metric_date (metric_date),
        KEY idx_campaign_id (campaign_id),
        KEY idx_ad_id (ad_id),
        KEY idx_status (status),
        KEY idx_campaign_date (campaign_id, metric_date),
        KEY idx_ad_date (ad_id, metric_date)
    ) {$charset_collate};";

    $sql_concepts = "CREATE TABLE {$table_concepts} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id VARCHAR(100) NOT NULL,
        ad_id VARCHAR(100) NOT NULL,
        concept_name VARCHAR(191) NOT NULL,
        concept_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_concept_date (concept_date),
        KEY idx_campaign_id (campaign_id),
        KEY idx_ad_id (ad_id),
        KEY idx_status (status),
        KEY idx_campaign_date (campaign_id, concept_date),
        KEY idx_ad_date (ad_id, concept_date)
    ) {$charset_collate};";

    $sql_feedback = "CREATE TABLE {$table_feedback} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        concept_id BIGINT(20) UNSIGNED NULL,
        campaign_id VARCHAR(100) NOT NULL,
        ad_id VARCHAR(100) NOT NULL,
        feedback_date DATE NOT NULL,
        feedback_text TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        event_name VARCHAR(100) NOT NULL DEFAULT '',
        score TINYINT(3) UNSIGNED NULL,
        is_scored TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
        client_hash VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_concept_id (concept_id),
        KEY idx_feedback_date (feedback_date),
        KEY idx_campaign_id (campaign_id),
        KEY idx_ad_id (ad_id),
        KEY idx_status (status),
        KEY idx_event_name (event_name),
        KEY idx_score (score),
        KEY idx_is_scored (is_scored),
        KEY idx_campaign_date (campaign_id, feedback_date),
        KEY idx_ad_date (ad_id, feedback_date)
    ) {$charset_collate};";

    $sql_pageviews_data = "CREATE TABLE {$table_pageviews_data} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        metric_date DATE NOT NULL,
        page_path VARCHAR(191) NOT NULL,
        pageviews BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        source VARCHAR(50) NOT NULL DEFAULT 'ga4',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_metric_date (metric_date),
        KEY idx_page_path (page_path),
        KEY idx_source (source),
        KEY idx_metric_date_path (metric_date, page_path),
        KEY idx_metric_date_source (metric_date, source),
        KEY idx_page_path_date (page_path, metric_date),
        KEY idx_metric_date_path_source (metric_date, page_path, source)
    ) {$charset_collate};";

    dbDelta( $sql_fb_data );
    dbDelta( $sql_ga_data );
    dbDelta( $sql_concepts );
    dbDelta( $sql_feedback );
    dbDelta( $sql_pageviews_data );

    ddo_migrate_feedback_scoring_model( $table_feedback );

    update_option( 'ddo_schema_version', DDO_SCHEMA_VERSION );
}

/**
 * Store GA4 pageview rows in batch.
 *
 * @param array $rows List met rows met keys metric_date, page_path, pageviews en optional source.
 * @return array
 */
function ddo_store_pageviews_rows( $rows ) {
    global $wpdb;

    $table = $wpdb->prefix . 'ddo_pageviews_data';

    if ( ! is_array( $rows ) || empty( $rows ) ) {
        return array(
            'inserted' => 0,
            'skipped'  => 0,
            'errors'   => 0,
        );
    }

    $inserted   = 0;
    $skipped    = 0;
    $errors     = 0;
    $batch_size = 200;
    $valid_rows = array();

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            ++$skipped;
            continue;
        }

        $metric_date = isset( $row['metric_date'] ) ? trim( (string) $row['metric_date'] ) : '';
        $page_path   = isset( $row['page_path'] ) ? trim( (string) $row['page_path'] ) : '';
        $pageviews   = isset( $row['pageviews'] ) ? (int) $row['pageviews'] : 0;
        $source      = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : 'ga4';

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $metric_date ) || '' === $page_path ) {
            ++$skipped;
            continue;
        }

        $valid_rows[] = array(
            'metric_date' => $metric_date,
            'page_path'   => substr( sanitize_text_field( $page_path ), 0, 191 ),
            'pageviews'   => max( 0, $pageviews ),
            'source'      => substr( '' === $source ? 'ga4' : $source, 0, 50 ),
        );
    }

    if ( empty( $valid_rows ) ) {
        return array(
            'inserted' => 0,
            'skipped'  => $skipped,
            'errors'   => 0,
        );
    }

    foreach ( array_chunk( $valid_rows, $batch_size ) as $chunk ) {
        $values_sql   = array();
        $prepare_args = array();

        foreach ( $chunk as $valid_row ) {
            $values_sql[] = '( %s, %s, %d, %s, NOW(), NOW() )';

            $prepare_args[] = $valid_row['metric_date'];
            $prepare_args[] = $valid_row['page_path'];
            $prepare_args[] = $valid_row['pageviews'];
            $prepare_args[] = $valid_row['source'];
        }

        $query_sql = "INSERT INTO {$table} (metric_date, page_path, pageviews, source, created_at, updated_at) VALUES " . implode( ', ', $values_sql );

        $prepared = $wpdb->prepare( $query_sql, $prepare_args );

        if ( false === $prepared ) {
            $errors += count( $chunk );
            continue;
        }

        $result = $wpdb->query( $prepared );

        if ( false === $result ) {
            $errors += count( $chunk );
            continue;
        }

        $inserted += (int) $result;
    }

    return array(
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => $errors,
    );
}

/**
 * Run schema migration when the version changes.
 */
function ddo_maybe_upgrade_database_schema() {
    $installed_version = get_option( 'ddo_schema_version', '' );

    if ( DDO_SCHEMA_VERSION !== $installed_version ) {
        ddo_install_database_schema();
    }
}

/**
 * Migreer scoring-model zodat is_scored leidend is voor scored/unscored semantiek.
 *
 * @param string $table_feedback Naam van de feedbacktabel.
 */
function ddo_migrate_feedback_scoring_model( $table_feedback ) {
    global $wpdb;

    if ( empty( $table_feedback ) ) {
        return;
    }

    // Legacy data gebruikte score=0 als default voor ontbrekende score.
    $wpdb->query(
        "UPDATE {$table_feedback}
        SET is_scored = 0, score = NULL
        WHERE score = 0 AND ( feedback_text = '' OR feedback_text IS NULL )"
    );

    // Oudere datasets zonder expliciete is_scored-waarde erven score-aanwezigheid.
    $wpdb->query(
        "UPDATE {$table_feedback}
        SET is_scored = 1
        WHERE score IS NOT NULL AND ( is_scored IS NULL )"
    );

    // Rows zonder score moeten altijd als unscored worden behandeld.
    $wpdb->query(
        "UPDATE {$table_feedback}
        SET is_scored = 0
        WHERE score IS NULL"
    );

    // is_scored=0 is leidend: verwijder eventuele score om ambiguiteit te voorkomen.
    $wpdb->query(
        "UPDATE {$table_feedback}
        SET score = NULL
        WHERE is_scored = 0"
    );
}
