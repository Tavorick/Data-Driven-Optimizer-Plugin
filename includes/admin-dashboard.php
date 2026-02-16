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
    add_action( 'admin_post_ddo_run_scheduler_job', 'ddo_handle_run_scheduler_job' );
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

    $enabled          = (bool) get_option( 'ddo_enabled', true );
    $concept_result   = get_transient( 'ddo_concept_result_' . get_current_user_id() );
    $feedback_summary = ddo_get_feedback_summary();

    if ( false !== $concept_result ) {
        delete_transient( 'ddo_concept_result_' . get_current_user_id() );
    }
    ?>
    <div class="wrap ddo-admin-wrap">
        <h1><?php esc_html_e( 'Data Driven Optimizer', 'data-driven-optimizer' ); ?></h1>
        <p><?php esc_html_e( 'Beheer hier API-keys, conceptvalidatie en feedbackinzichten.', 'data-driven-optimizer' ); ?></p>
        <p>
            <strong><?php esc_html_e( 'Status:', 'data-driven-optimizer' ); ?></strong>
            <?php echo $enabled ? esc_html__( 'Ingeschakeld', 'data-driven-optimizer' ) : esc_html__( 'Uitgeschakeld', 'data-driven-optimizer' ); ?>
        </p>

        <nav class="nav-tab-wrapper ddo-section-nav" aria-label="<?php esc_attr_e( 'Dashboardsecties', 'data-driven-optimizer' ); ?>">
            <a href="#ddo-section-instellingen" class="nav-tab"><?php esc_html_e( 'Instellingen', 'data-driven-optimizer' ); ?></a>
            <a href="#ddo-section-scheduler" class="nav-tab"><?php esc_html_e( 'Scheduler', 'data-driven-optimizer' ); ?></a>
            <a href="#ddo-section-feedback" class="nav-tab"><?php esc_html_e( 'Feedback inzichten', 'data-driven-optimizer' ); ?></a>
            <a href="#ddo-section-concept" class="nav-tab"><?php esc_html_e( 'Conceptinvoer', 'data-driven-optimizer' ); ?></a>
        </nav>

        <?php settings_errors( 'ddo_messages' ); ?>
        <?php ddo_render_scheduler_action_notice(); ?>

        <section id="ddo-section-instellingen" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Instellingen', 'data-driven-optimizer' ); ?></h2>
            <h3><?php esc_html_e( 'Pluginconfiguratie', 'data-driven-optimizer' ); ?></h3>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'ddo_settings_group' );
                do_settings_sections( 'ddo_settings_group' );
                submit_button( __( 'Instellingen opslaan', 'data-driven-optimizer' ) );
                ?>
            </form>
        </section>

        <section id="ddo-section-scheduler" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Scheduler', 'data-driven-optimizer' ); ?></h2>
            <h3><?php esc_html_e( 'Scheduler status', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_scheduler_status_block(); ?>
        </section>

        <section id="ddo-section-feedback" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Feedback inzichten', 'data-driven-optimizer' ); ?></h2>
            <h3><?php esc_html_e( 'Samenvatting', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_feedback_summary_cards( $feedback_summary ); ?>
            <h3><?php esc_html_e( 'Eventoverzicht', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_feedback_events_table( $feedback_summary ); ?>
        </section>

        <section id="ddo-section-concept" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Conceptinvoer', 'data-driven-optimizer' ); ?></h2>
            <h3><?php esc_html_e( 'Concept verwerken', 'data-driven-optimizer' ); ?></h3>
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
        </section>
    </div>
    <?php
}

/**
 * Render compacte samenvattingskaarten.
 *
 * @param array $summary Feedbacksamenvatting.
 */
function ddo_render_feedback_summary_cards( $summary ) {
    $total_count   = isset( $summary['totals']['count'] ) ? (int) $summary['totals']['count'] : 0;
    $average_score = isset( $summary['totals']['averageScore'] ) ? (float) $summary['totals']['averageScore'] : 0;
    ?>
    <div class="ddo-feedback-cards">
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Totaal feedback-items', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $total_count ) ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Gemiddelde score', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $average_score, 2 ) ); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Render tabel met eventaggregatie.
 *
 * @param array $summary Feedbacksamenvatting.
 */
function ddo_render_feedback_events_table( $summary ) {
    $events = isset( $summary['events'] ) && is_array( $summary['events'] ) ? $summary['events'] : array();

    if ( empty( $events ) ) {
        echo '<p>' . esc_html__( 'Nog geen feedbackevents opgeslagen.', 'data-driven-optimizer' ) . '</p>';
        return;
    }
    ?>
    <table class="widefat striped ddo-feedback-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Event', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Aantal', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Gem. score', 'data-driven-optimizer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $events as $event_row ) : ?>
                <tr>
                    <td><?php echo esc_html( $event_row['event_name'] ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (int) $event_row['total_items'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $event_row['average_score'], 2 ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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


/**
 * Render eventuele statusmelding na handmatige scheduler-actie.
 */
function ddo_render_scheduler_action_notice() {
    $notice = isset( $_GET['ddo_scheduler_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ddo_scheduler_notice'] ) ) : '';

    if ( '' === $notice ) {
        return;
    }

    $class   = 'updated';
    $message = __( 'Scheduler-actie uitgevoerd.', 'data-driven-optimizer' );

    if ( 'ok' === $notice ) {
        $message = __( 'Scheduler job handmatig uitgevoerd.', 'data-driven-optimizer' );
    } elseif ( 'invalid' === $notice ) {
        $class   = 'notice-warning';
        $message = __( 'Onbekende scheduler job.', 'data-driven-optimizer' );
    } elseif ( 'forbidden' === $notice ) {
        $class   = 'notice-error';
        $message = __( 'Onvoldoende rechten voor scheduler-actie.', 'data-driven-optimizer' );
    }

    printf(
        '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr( $class ),
        esc_html( $message )
    );
}

/**
 * Render scheduler observability tabel met run-now acties.
 */
function ddo_render_scheduler_status_block() {
    $jobs     = ddo_get_scheduler_observability_jobs();
    $metadata = ddo_get_scheduler_job_metadata();
    $now      = time();
    ?>
    <table class="widefat striped ddo-scheduler-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Job', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste start', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste succes', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste foutmelding', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Status', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Actie', 'data-driven-optimizer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $jobs as $job_name => $job_config ) : ?>
                <?php
                $job_meta           = isset( $metadata[ $job_name ] ) && is_array( $metadata[ $job_name ] ) ? $metadata[ $job_name ] : array();
                $last_start         = isset( $job_meta['last_start'] ) ? (int) $job_meta['last_start'] : 0;
                $last_success       = isset( $job_meta['last_success'] ) ? (int) $job_meta['last_success'] : 0;
                $last_error_message = isset( $job_meta['last_error_message'] ) ? (string) $job_meta['last_error_message'] : '';
                $expected_interval  = isset( $job_config['expected_interval'] ) ? (int) $job_config['expected_interval'] : HOUR_IN_SECONDS;
                $stale_threshold    = 2 * $expected_interval;
                $seconds_since_ok   = $last_success > 0 ? $now - $last_success : PHP_INT_MAX;
                $is_stale           = $seconds_since_ok > $stale_threshold;
                $status_label       = $is_stale
                    ? __( 'Stale', 'data-driven-optimizer' )
                    : __( 'OK', 'data-driven-optimizer' );
                $row_class          = $is_stale ? 'ddo-scheduler-row-stale' : 'ddo-scheduler-row-ok';
                ?>
                <tr class="<?php echo esc_attr( $row_class ); ?>">
                    <td><code><?php echo esc_html( $job_name ); ?></code></td>
                    <td><?php echo $last_start > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $last_start ) ) : esc_html__( 'Nooit', 'data-driven-optimizer' ); ?></td>
                    <td><?php echo $last_success > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $last_success ) ) : esc_html__( 'Nooit', 'data-driven-optimizer' ); ?></td>
                    <td><?php echo '' !== $last_error_message ? esc_html( $last_error_message ) : '&mdash;'; ?></td>
                    <td><span class="ddo-scheduler-status-pill <?php echo esc_attr( $is_stale ? 'is-stale' : 'is-ok' ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                    <td>
                        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="ddo-scheduler-run-form">
                            <input type="hidden" name="action" value="ddo_run_scheduler_job" />
                            <input type="hidden" name="job_name" value="<?php echo esc_attr( $job_name ); ?>" />
                            <?php wp_nonce_field( 'ddo_run_scheduler_job_' . $job_name, 'ddo_run_scheduler_job_nonce' ); ?>
                            <?php submit_button( __( 'Run now', 'data-driven-optimizer' ), 'secondary small', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Verwerk handmatige scheduler-run vanuit admin.
 */
function ddo_handle_run_scheduler_job() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ddo-dashboard&ddo_scheduler_notice=forbidden' ) );
        exit;
    }

    $job_name = isset( $_POST['job_name'] ) ? sanitize_text_field( wp_unslash( $_POST['job_name'] ) ) : '';

    $jobs = ddo_get_scheduler_observability_jobs();

    if ( '' === $job_name || ! isset( $jobs[ $job_name ] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ddo-dashboard&ddo_scheduler_notice=invalid' ) );
        exit;
    }

    check_admin_referer( 'ddo_run_scheduler_job_' . $job_name, 'ddo_run_scheduler_job_nonce' );

    do_action( $job_name );

    wp_safe_redirect( admin_url( 'admin.php?page=ddo-dashboard&ddo_scheduler_notice=ok' ) );
    exit;
}
