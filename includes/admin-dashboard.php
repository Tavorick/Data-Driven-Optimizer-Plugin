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
    add_action( 'admin_post_ddo_submit_concept', 'ddo_handle_concept_submit' );
    add_action( 'wp_ajax_ddo_preview_concept', 'ddo_handle_preview_concept_ajax' );
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
    if ( 'toplevel_page_ddo-dashboard' !== $hook_suffix || ! current_user_can( 'manage_options' ) ) {
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
        array( 'jquery' ),
        DDO_PLUGIN_VERSION,
        true
    );

    wp_localize_script(
        'ddo-admin-script',
        'ddoAdmin',
        array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'previewNonce' => wp_create_nonce( 'ddo_preview_concept' ),
            'i18n'         => array(
                'previewFailed' => __( 'Preview ophalen mislukt.', 'data-driven-optimizer' ),
            ),
        )
    );
}

/**
 * Render de admin dashboardpagina.
 */
function ddo_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $enabled        = (bool) get_option( 'ddo_enabled', true );
    $concept_result = get_transient( 'ddo_concept_result_' . get_current_user_id() );

    if ( false !== $concept_result ) {
        delete_transient( 'ddo_concept_result_' . get_current_user_id() );
    }
    ?>
    <div class="wrap ddo-admin-wrap">
        <h1><?php esc_html_e( 'Data Driven Optimizer', 'data-driven-optimizer' ); ?></h1>
        <p><?php esc_html_e( 'Beheer hier API-keys en conceptvalidatie.', 'data-driven-optimizer' ); ?></p>
        <p>
            <strong><?php esc_html_e( 'Status:', 'data-driven-optimizer' ); ?></strong>
            <?php echo $enabled ? esc_html__( 'Ingeschakeld', 'data-driven-optimizer' ) : esc_html__( 'Uitgeschakeld', 'data-driven-optimizer' ); ?>
        </p>

        <?php settings_errors( 'ddo_messages' ); ?>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'ddo_settings_group' );
            do_settings_sections( 'ddo_settings_group' );
            submit_button( __( 'Instellingen opslaan', 'data-driven-optimizer' ) );
            ?>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Conceptinvoer (admin-only)', 'data-driven-optimizer' ); ?></h2>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="ddo-concept-form">
            <input type="hidden" name="action" value="ddo_submit_concept" />
            <?php wp_nonce_field( 'ddo_submit_concept', 'ddo_submit_concept_nonce' ); ?>
            <textarea name="ddo_concept_input" rows="5" class="large-text" required></textarea>
            <?php submit_button( __( 'Verwerk concept', 'data-driven-optimizer' ), 'secondary', 'submit', false ); ?>
            <button type="button" class="button" id="ddo-preview-concept"><?php esc_html_e( 'Preview via AJAX', 'data-driven-optimizer' ); ?></button>
        </form>

        <?php if ( is_array( $concept_result ) ) : ?>
            <h3><?php esc_html_e( 'Laatste resultaat', 'data-driven-optimizer' ); ?></h3>
            <p><strong><?php esc_html_e( 'Invoer:', 'data-driven-optimizer' ); ?></strong> <?php echo esc_html( $concept_result['input'] ); ?></p>
            <p><strong><?php esc_html_e( 'Samenvatting:', 'data-driven-optimizer' ); ?></strong> <?php echo esc_html( $concept_result['summary'] ); ?></p>
        <?php endif; ?>

        <div id="ddo-ajax-preview-response" aria-live="polite"></div>
    </div>
    <?php
}

/**
 * Verwerk conceptinvoer vanuit admin-post.
 */
function ddo_handle_concept_submit() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Onvoldoende rechten.', 'data-driven-optimizer' ) );
    }

    check_admin_referer( 'ddo_submit_concept', 'ddo_submit_concept_nonce' );

    $concept_input = isset( $_POST['ddo_concept_input'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddo_concept_input'] ) ) : '';

    $result = array(
        'input'   => $concept_input,
        'summary' => ddo_build_concept_summary( $concept_input ),
    );

    set_transient( 'ddo_concept_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

    wp_safe_redirect( admin_url( 'admin.php?page=ddo-dashboard' ) );
    exit;
}

/**
 * AJAX preview voor conceptinvoer.
 */
function ddo_handle_preview_concept_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Onvoldoende rechten.', 'data-driven-optimizer' ) ), 403 );
    }

    check_ajax_referer( 'ddo_preview_concept', 'nonce' );

    $concept_input = isset( $_POST['concept'] ) ? sanitize_textarea_field( wp_unslash( $_POST['concept'] ) ) : '';

    wp_send_json_success(
        array(
            'summary' => ddo_build_concept_summary( $concept_input ),
        )
    );
}

/**
 * Maak een veilige korte samenvatting van conceptinvoer.
 *
 * @param string $concept_input Concepttekst.
 * @return string
 */
function ddo_build_concept_summary( $concept_input ) {
    if ( '' === $concept_input ) {
        return __( 'Geen invoer ontvangen.', 'data-driven-optimizer' );
    }

    return sprintf(
        /* translators: %d: aantal tekens */
        __( 'Concept ontvangen (%d tekens).', 'data-driven-optimizer' ),
        mb_strlen( $concept_input )
    );
}
