<?php
/**
 * Plugin Name: Data Driven Optimizer
 * Plugin URI:  https://example.com/data-driven-optimizer
 * Description: Basisplugin met admin-dashboard, instellingen en API-handlers.
 * Version:     1.0.0
 * Author:      DDO Team
 * License:     GPL-2.0-or-later
 * Text Domain: data-driven-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DDO_PLUGIN_VERSION', '1.0.0' );
define( 'DDO_PLUGIN_FILE', __FILE__ );
define( 'DDO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DDO_PLUGIN_DIR . 'includes/settings.php';
require_once DDO_PLUGIN_DIR . 'includes/admin-dashboard.php';
require_once DDO_PLUGIN_DIR . 'includes/api-handlers.php';
require_once DDO_PLUGIN_DIR . 'includes/db-schema.php';

/**
 * Basisinitialisatie bij activatie.
 */
function ddo_activate_plugin() {
    ddo_install_database_schema();

    if ( false === get_option( 'ddo_options' ) ) {
        add_option(
            'ddo_options',
            array(
                'enabled' => true,
            )
        );
    }
}
register_activation_hook( DDO_PLUGIN_FILE, 'ddo_activate_plugin' );

/**
 * Opruimactie bij deactivatie.
 */
function ddo_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( DDO_PLUGIN_FILE, 'ddo_deactivate_plugin' );

/**
 * Start de plugin-onderdelen.
 */
function ddo_init_plugin() {
    ddo_maybe_upgrade_database_schema();
    ddo_register_settings();
    ddo_register_admin_menu();
    ddo_register_api_routes();
}
add_action( 'plugins_loaded', 'ddo_init_plugin' );
