<?php
/**
 * Admin dashboard voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registreer admin menu en assets-hook.
 */
function ddo_register_admin_menu() {
    add_action( 'admin_menu', 'ddo_add_admin_menu_page' );
    add_action( 'admin_enqueue_scripts', 'ddo_enqueue_admin_assets' );
}

/**
 * Voeg de pluginmenu-pagina toe.
 */
function ddo_add_admin_menu_page() {
    add_menu_page(
        __( 'Data Driven Optimizer', 'data-driven-optimizer' ),
        __( 'DD Optimizer', 'data-driven-optimizer' ),
        'manage_options',
        'ddo-dashboard',
        'ddo_render_admin_page',
        'dashicons-chart-area',
        58
    );
}

/**
 * Laad admin CSS/JS op de pluginpagina.
 *
 * @param string $hook_suffix Huidige admin page hook.
 */
function ddo_enqueue_admin_assets( $hook_suffix ) {
    if ( 'toplevel_page_ddo-dashboard' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_style(
        'ddo-admin-style',
        DDO_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        DDO_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'ddo-admin-script',
        DDO_PLUGIN_URL . 'assets/js/admin.js',
        array(),
        DDO_PLUGIN_VERSION,
        true
    );
}

/**
 * Render de admin dashboardpagina.
 */
function ddo_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = get_option( 'ddo_options', array() );
    $enabled = ! empty( $options['enabled'] );
    ?>
    <div class="wrap ddo-admin-wrap">
        <h1><?php esc_html_e( 'Data Driven Optimizer', 'data-driven-optimizer' ); ?></h1>
        <p><?php esc_html_e( 'De plugin is succesvol geladen.', 'data-driven-optimizer' ); ?></p>
        <p>
            <strong><?php esc_html_e( 'Status:', 'data-driven-optimizer' ); ?></strong>
            <?php echo $enabled ? esc_html__( 'Ingeschakeld', 'data-driven-optimizer' ) : esc_html__( 'Uitgeschakeld', 'data-driven-optimizer' ); ?>
        </p>
    </div>
    <?php
}
