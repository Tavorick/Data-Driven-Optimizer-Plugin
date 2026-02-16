<?php
/**
 * Database schema management for the Data Driven Optimizer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DDO_SCHEMA_VERSION', '1.2.0' );

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

    dbDelta( $sql_fb_data );
    dbDelta( $sql_ga_data );
    dbDelta( $sql_concepts );
    dbDelta( $sql_feedback );

    ddo_migrate_feedback_score_semantics( $table_feedback );

    update_option( 'ddo_schema_version', DDO_SCHEMA_VERSION );
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
 * Migreer score-semantiek zodat ongescoorde feedback onderscheidbaar wordt van score 0.
 *
 * @param string $table_feedback Naam van de feedbacktabel.
 */
function ddo_migrate_feedback_score_semantics( $table_feedback ) {
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
}
