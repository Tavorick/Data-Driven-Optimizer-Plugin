<?php
/**
 * Plugin Name: Data Driven Optimizer
 * Plugin URI:  https://example.com/data-driven-optimizer
 * Description: Basisplugin met admin-dashboard, instellingen en API-handlers.
 * Version:     1.2.2
 * Author:      DDO Team
 * License:     GPL-2.0-or-later
 * Text Domain: data-driven-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DDO_PLUGIN_VERSION', '1.2.2' );
define( 'DDO_PLUGIN_FILE', __FILE__ );
define( 'DDO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DDO_PLUGIN_DIR . 'includes/settings.php';
require_once DDO_PLUGIN_DIR . 'includes/admin-dashboard.php';
require_once DDO_PLUGIN_DIR . 'includes/api-handlers.php';
require_once DDO_PLUGIN_DIR . 'includes/ml-feedback.php';
require_once DDO_PLUGIN_DIR . 'includes/code-introspect.php';
require_once DDO_PLUGIN_DIR . 'includes/logger.php';
require_once DDO_PLUGIN_DIR . 'includes/cron.php';
require_once DDO_PLUGIN_DIR . 'includes/db-schema.php';

/**
 * Basisinitialisatie bij activatie.
 */
function ddo_activate_plugin() {
    ddo_install_database_schema();

    ddo_register_cron_events();

    if ( false === get_option( 'ddo_enabled' ) ) {
        add_option( 'ddo_enabled', true );
    }

    if ( false === get_option( 'ddo_api_key_primary' ) ) {
        add_option( 'ddo_api_key_primary', '' );
    }

    if ( false === get_option( 'ddo_api_key_secondary' ) ) {
        add_option( 'ddo_api_key_secondary', '' );
    }

    if ( false === get_option( 'ddo_feedback_retention_days' ) ) {
        add_option( 'ddo_feedback_retention_days', 180 );
    }

    ddo_maybe_migrate_api_keys_to_encrypted();

    $legacy_options = get_option( 'ddo_options', array() );

    if ( is_array( $legacy_options ) && array_key_exists( 'enabled', $legacy_options ) ) {
        update_option( 'ddo_enabled', ! empty( $legacy_options['enabled'] ) );
    }
}
register_activation_hook( DDO_PLUGIN_FILE, 'ddo_activate_plugin' );

/**
 * Opruimactie bij deactivatie.
 */
function ddo_deactivate_plugin() {
    ddo_clear_cron_events();
    flush_rewrite_rules();
}
register_deactivation_hook( DDO_PLUGIN_FILE, 'ddo_deactivate_plugin' );

/**
 * Start de plugin-onderdelen.
 */
function ddo_init_plugin() {
    ddo_maybe_upgrade_database_schema();
    ddo_maybe_migrate_api_keys_to_encrypted();
    ddo_register_settings();
    ddo_register_admin_menu();
    ddo_register_api_routes();
    ddo_register_cron_callbacks();
}
add_action( 'plugins_loaded', 'ddo_init_plugin' );
