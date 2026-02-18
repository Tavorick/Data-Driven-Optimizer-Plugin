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
    add_action( 'admin_notices', 'ddo_render_ga4_runtime_config_notice' );
}

/**
 * Toon runtime-configuratiefout voor GA4 als admin notice.
 */
function ddo_render_ga4_runtime_config_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! is_object( $screen ) || empty( $screen->id ) || 'toplevel_page_ddo-dashboard' !== $screen->id ) {
        return;
    }

    $notice_context = get_transient( 'ddo_ga4_runtime_config_notice' );

    if ( ! is_array( $notice_context ) ) {
        return;
    }

    delete_transient( 'ddo_ga4_runtime_config_notice' );

    $details = sprintf(
        ' (%1$s: %2$s, %3$s: %4$s, %5$s: %6$s)',
        'property_id_present',
        ! empty( $notice_context['property_id_present'] ) ? 'yes' : 'no',
        'secret_present',
        ! empty( $notice_context['secret_present'] ) ? 'yes' : 'no',
        'mode',
        isset( $notice_context['mode'] ) ? sanitize_text_field( (string) $notice_context['mode'] ) : 'unknown'
    );

    echo '<div class="notice notice-error is-dismissible"><p>';
    echo esc_html__( 'GA4-configuratie ontbreekt of is ongeldig. Vul property ID + service account JSON in.', 'data-driven-optimizer' );
    echo esc_html( $details );
    echo '</p></div>';
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
                'previewFailed' => __( 'Preview ophalen mislukt. Probeer opnieuw of vernieuw de pagina.', 'data-driven-optimizer' ),
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
    $feedback_filters = ddo_get_feedback_filters_from_request();
    $feedback_summary = ddo_get_feedback_summary( $feedback_filters );
    $pageviews_summary = ddo_get_pageviews_summary( 7 );

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
            <a href="#ddo-section-pageviews" class="nav-tab"><?php esc_html_e( 'Pageviews', 'data-driven-optimizer' ); ?></a>
            <a href="#ddo-section-concept" class="nav-tab"><?php esc_html_e( 'Conceptinvoer', 'data-driven-optimizer' ); ?></a>
        </nav>

        <?php if ( function_exists( 'settings_errors' ) ) : ?>
            <?php settings_errors( 'ddo_messages' ); ?>
            <?php settings_errors( 'ddo_ga4_property_id' ); ?>
            <?php settings_errors( 'ddo_ga4_auth_mode' ); ?>
            <?php settings_errors( 'ddo_ga4_service_account_json' ); ?>
            <?php settings_errors( 'ddo_ga4_bearer_token' ); ?>
            <?php settings_errors( 'ddo_facebook_ads_app_id' ); ?>
            <?php settings_errors( 'ddo_facebook_ads_app_secret' ); ?>
            <?php settings_errors( 'ddo_facebook_ads_access_token' ); ?>
            <?php settings_errors( 'ddo_facebook_ads_ad_account_id' ); ?>
            <?php settings_errors( 'ddo_search_console_site_url' ); ?>
            <?php settings_errors( 'ddo_search_console_oauth_client_id' ); ?>
            <?php settings_errors( 'ddo_search_console_oauth_client_secret' ); ?>
            <?php settings_errors( 'ddo_search_console_oauth_reference' ); ?>
        <?php endif; ?>
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
            <h3><?php esc_html_e( "Operationele KPI's", 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_scheduler_kpi_block(); ?>
            <h3><?php esc_html_e( 'Recente scheduler events', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_recent_scheduler_events_block(); ?>
        </section>

        <section id="ddo-section-feedback" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Feedback inzichten', 'data-driven-optimizer' ); ?></h2>
            <?php ddo_render_feedback_filters_form( $feedback_summary ); ?>
            <h3><?php esc_html_e( 'Samenvatting', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_feedback_summary_cards( $feedback_summary ); ?>
            <h3><?php esc_html_e( 'Eventoverzicht', 'data-driven-optimizer' ); ?></h3>
            <?php ddo_render_feedback_events_table( $feedback_summary ); ?>
        </section>


        <section id="ddo-section-pageviews" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Pageviews', 'data-driven-optimizer' ); ?></h2>
            <?php ddo_render_pageviews_summary_card( $pageviews_summary ); ?>
        </section>

        <section id="ddo-section-concept" class="ddo-admin-section">
            <h2><?php esc_html_e( 'Conceptinvoer', 'data-driven-optimizer' ); ?></h2>
            <h3><?php esc_html_e( 'Concept verwerken', 'data-driven-optimizer' ); ?></h3>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="ddo-concept-form">
                <input type="hidden" name="action" value="ddo_submit_concept" />
                <?php wp_nonce_field( 'ddo_submit_concept', 'ddo_submit_concept_nonce' ); ?>
                <label for="ddo-concept-input"><?php esc_html_e( 'Concepttekst', 'data-driven-optimizer' ); ?></label>
                <textarea id="ddo-concept-input" name="ddo_concept_input" rows="5" class="large-text" aria-describedby="ddo-concept-input-help" required></textarea>
                <p id="ddo-concept-input-help" class="description"><?php esc_html_e( 'Voer de ruwe concepttekst in. We gebruiken deze voor verwerking en voor de AJAX-preview.', 'data-driven-optimizer' ); ?></p>
                <?php submit_button( __( 'Verwerk concept', 'data-driven-optimizer' ), 'secondary', 'submit', false ); ?>
                <button type="button" class="button" id="ddo-preview-concept" aria-controls="ddo-ajax-preview-response" aria-describedby="ddo-concept-input-help"><?php esc_html_e( 'Preview via AJAX', 'data-driven-optimizer' ); ?></button>
            </form>

            <?php if ( is_array( $concept_result ) ) : ?>
                <h3><?php esc_html_e( 'Laatste resultaat', 'data-driven-optimizer' ); ?></h3>
                <p><strong><?php esc_html_e( 'Invoer:', 'data-driven-optimizer' ); ?></strong> <?php echo esc_html( $concept_result['input'] ); ?></p>
                <p><strong><?php esc_html_e( 'Samenvatting:', 'data-driven-optimizer' ); ?></strong> <?php echo esc_html( $concept_result['summary'] ); ?></p>
            <?php endif; ?>

            <div id="ddo-ajax-preview-response" role="status" aria-live="polite" aria-atomic="true"></div>
        </section>
    </div>
    <?php
}

/**
 * Lees en normaliseer feedbackfilters uit request.
 *
 * @return array
 */
function ddo_get_feedback_filters_from_request() {
    $days = isset( $_GET['ddo_days'] ) ? absint( wp_unslash( $_GET['ddo_days'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $sort = isset( $_GET['ddo_sort'] ) ? sanitize_key( wp_unslash( $_GET['ddo_sort'] ) ) : 'count_desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    return ddo_normalize_feedback_filters(
        array(
            'days' => $days,
            'sort' => $sort,
        )
    );
}

/**
 * Render filterformulier voor feedbackinzichten.
 *
 * @param array $summary Feedbacksamenvatting met filtermeta.
 */
function ddo_render_feedback_filters_form( $summary ) {
    $filters = isset( $summary['filters'] ) && is_array( $summary['filters'] ) ? $summary['filters'] : ddo_normalize_feedback_filters();
    ?>
    <form method="get" class="ddo-feedback-filters">
        <input type="hidden" name="page" value="ddo-dashboard" />
        <label for="ddo-days-filter"><?php esc_html_e( 'Periode', 'data-driven-optimizer' ); ?></label>
        <select id="ddo-days-filter" name="ddo_days">
            <option value="7" <?php selected( 7, (int) $filters['days'] ); ?>><?php esc_html_e( 'Laatste 7 dagen', 'data-driven-optimizer' ); ?></option>
            <option value="30" <?php selected( 30, (int) $filters['days'] ); ?>><?php esc_html_e( 'Laatste 30 dagen', 'data-driven-optimizer' ); ?></option>
            <option value="0" <?php selected( 0, (int) $filters['days'] ); ?>><?php esc_html_e( 'Alles', 'data-driven-optimizer' ); ?></option>
        </select>

        <label for="ddo-sort-filter"><?php esc_html_e( 'Sorteer events op', 'data-driven-optimizer' ); ?></label>
        <select id="ddo-sort-filter" name="ddo_sort">
            <option value="count_desc" <?php selected( 'count_desc', $filters['sort'] ); ?>><?php esc_html_e( 'Aantal (hoog naar laag)', 'data-driven-optimizer' ); ?></option>
            <option value="score_desc" <?php selected( 'score_desc', $filters['sort'] ); ?>><?php esc_html_e( 'Score (hoog naar laag)', 'data-driven-optimizer' ); ?></option>
        </select>

        <?php submit_button( __( 'Toepassen', 'data-driven-optimizer' ), 'secondary', '', false ); ?>
    </form>
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
    $highest_score = isset( $summary['totals']['highestScore'] ) ? (float) $summary['totals']['highestScore'] : 0;
    $lowest_score  = isset( $summary['totals']['lowestScore'] ) ? (float) $summary['totals']['lowestScore'] : 0;
    $unscored      = isset( $summary['totals']['unscored'] ) ? (int) $summary['totals']['unscored'] : 0;

    if ( 0 === $total_count ) {
        echo '<p>' . esc_html__( 'Geen data in gekozen periode.', 'data-driven-optimizer' ) . '</p>';
        return;
    }
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
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Hoogste eventscore', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $highest_score, 2 ) ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Laagste eventscore', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $lowest_score, 2 ) ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Events zonder score', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $unscored ) ); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Render compacte pageviewskaart voor de laatste periode.
 *
 * @param array $summary Pageviews samenvatting.
 */
function ddo_render_pageviews_summary_card( $summary ) {
    $total_pageviews = isset( $summary['totalPageviews'] ) ? (int) $summary['totalPageviews'] : 0;
    $top_pages       = isset( $summary['topPages'] ) && is_array( $summary['topPages'] ) ? $summary['topPages'] : array();

    ?>
    <div class="ddo-feedback-cards">
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Pageviews laatste 7 dagen', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( $total_pageviews ) ); ?></p>
            <h4><?php esc_html_e( 'Top 5 page paths', 'data-driven-optimizer' ); ?></h4>
            <?php if ( empty( $top_pages ) ) : ?>
                <p><?php esc_html_e( 'Nog geen pageviews-data beschikbaar.', 'data-driven-optimizer' ); ?></p>
            <?php else : ?>
                <ol>
                    <?php foreach ( $top_pages as $top_page ) : ?>
                        <li>
                            <code><?php echo esc_html( (string) $top_page['page_path'] ); ?></code>
                            — <?php echo esc_html( number_format_i18n( (int) $top_page['total_pageviews'] ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
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
        echo '<p>' . esc_html__( 'Geen data in gekozen periode.', 'data-driven-optimizer' ) . '</p>';
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
    $job    = isset( $_GET['ddo_scheduler_job'] ) ? sanitize_text_field( wp_unslash( $_GET['ddo_scheduler_job'] ) ) : '';
    $jobs   = isset( $_GET['ddo_scheduler_jobs'] ) ? sanitize_text_field( wp_unslash( $_GET['ddo_scheduler_jobs'] ) ) : '';

    if ( '' === $notice ) {
        return;
    }

    $class   = 'updated';
    $message = __( 'Scheduler-actie uitgevoerd.', 'data-driven-optimizer' );

    if ( 'ok' === $notice ) {
        $message = '' !== $job
            ? sprintf(
                /* translators: %s: scheduler jobnaam. */
                __( 'Scheduler job "%s" handmatig uitgevoerd.', 'data-driven-optimizer' ),
                $job
            )
            : __( 'Scheduler job handmatig uitgevoerd.', 'data-driven-optimizer' );
    } elseif ( 'ok_bulk' === $notice ) {
        $message = '' !== $jobs
            ? sprintf(
                /* translators: %s: lijst van scheduler jobs. */
                __( 'Bulk run uitgevoerd voor veilige jobs: %s.', 'data-driven-optimizer' ),
                $jobs
            )
            : __( 'Bulk run uitgevoerd voor alle veilige jobs.', 'data-driven-optimizer' );
    } elseif ( 'invalid' === $notice ) {
        $class   = 'notice-warning';
        $message = '' !== $job
            ? sprintf(
                /* translators: %s: ongeldige scheduler jobnaam. */
                __( 'Onbekende scheduler job: %s.', 'data-driven-optimizer' ),
                $job
            )
            : __( 'Onbekende scheduler job.', 'data-driven-optimizer' );
    } elseif ( 'nonce_invalid' === $notice ) {
        $class   = 'notice-error';
        $message = '' !== $job
            ? sprintf(
                /* translators: %s: scheduler jobnaam. */
                __( 'Nonce-validatie mislukt voor scheduler job: %s.', 'data-driven-optimizer' ),
                $job
            )
            : __( 'Nonce-validatie voor scheduler-actie mislukt.', 'data-driven-optimizer' );
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
 * Bouw uitlegregel voor stale status.
 *
 * @param int    $last_success      Laatste succesvolle timestamp.
 * @param int    $seconds_since_ok  Seconden sinds laatste succes.
 * @param int    $stale_threshold   Drempel in seconden.
 * @param string $last_error_message Laatste foutmelding.
 * @return string
 */
function ddo_get_scheduler_stale_cause_text( $last_success, $seconds_since_ok, $stale_threshold, $last_error_message, $expected_interval = HOUR_IN_SECONDS ) {
    if ( $last_success <= 0 ) {
        return sprintf(
            /* translators: 1: expected interval, 2: stale threshold. */
            __( 'Nog geen succesvolle run geregistreerd. Verwachte interval: %1$s, stale na %2$s.', 'data-driven-optimizer' ),
            human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $expected_interval ) ),
            human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $stale_threshold ) )
        );
    }

    if ( '' !== $last_error_message ) {
        return sprintf(
            /* translators: 1: foutmelding, 2: expected interval, 3: stale threshold. */
            __( 'Laatste fout: %1$s. Verwachte interval: %2$s, stale na %3$s.', 'data-driven-optimizer' ),
            $last_error_message,
            human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $expected_interval ) ),
            human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $stale_threshold ) )
        );
    }

    return sprintf(
        /* translators: 1: verstreken tijd, 2: expected interval, 3: stale threshold. */
        __( 'Geen succesvolle run in %1$s. Verwachte interval: %2$s, stale na %3$s.', 'data-driven-optimizer' ),
        human_time_diff( time() - $seconds_since_ok, time() ),
        human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $expected_interval ) ),
        human_time_diff( 0, max( HOUR_IN_SECONDS, (int) $stale_threshold ) )
    );
}

/**
 * Bouw operationele KPI's uit recente scheduler-events.
 *
 * @param int $window_days Analyseperiode in dagen.
 * @return array
 */
function ddo_get_scheduler_operational_kpis( $window_days = 30 ) {
    $events       = ddo_get_recent_scheduler_events( 200 );
    $window_days  = max( 1, (int) $window_days );
    $window_start = time() - ( $window_days * DAY_IN_SECONDS );
    $filtered     = array_filter(
        $events,
        function ( $event ) use ( $window_start ) {
            $timestamp = isset( $event['timestamp'] ) ? (int) $event['timestamp'] : 0;
            return $timestamp >= $window_start;
        }
    );

    $total_runs    = 0;
    $success_runs  = 0;
    $durations     = array();
    $ingest_perday = array();

    foreach ( $filtered as $event ) {
        $message = isset( $event['message'] ) ? (string) $event['message'] : '';
        $level   = isset( $event['level'] ) ? (string) $event['level'] : 'info';
        $context = isset( $event['context'] ) && is_array( $event['context'] ) ? $event['context'] : array();

        if ( 'job-end' === $message || 'job-error' === $message ) {
            $total_runs++;
            if ( 'job-end' === $message && 'error' !== $level ) {
                $success_runs++;
            }

            if ( isset( $context['duration'] ) ) {
                $durations[] = max( 0, (float) $context['duration'] );
            }

            $day_key = gmdate( 'Y-m-d', isset( $event['timestamp'] ) ? (int) $event['timestamp'] : time() );
            if ( ! isset( $ingest_perday[ $day_key ] ) ) {
                $ingest_perday[ $day_key ] = 0;
            }
            $ingest_perday[ $day_key ] += isset( $context['result_count'] ) ? max( 0, (int) $context['result_count'] ) : 0;
        }
    }

    sort( $durations );
    $median_duration = 0;
    $duration_count  = count( $durations );

    if ( $duration_count > 0 ) {
        $middle = (int) floor( $duration_count / 2 );
        if ( 0 === $duration_count % 2 ) {
            $median_duration = ( $durations[ $middle - 1 ] + $durations[ $middle ] ) / 2;
        } else {
            $median_duration = $durations[ $middle ];
        }
    }

    ksort( $ingest_perday );

    return array(
        'window_days'     => $window_days,
        'total_runs'      => $total_runs,
        'success_runs'    => $success_runs,
        'success_rate'    => $total_runs > 0 ? ( $success_runs / $total_runs ) * 100 : 0,
        'median_duration' => $median_duration,
        'ingest_per_day'  => $ingest_perday,
    );
}

/**
 * Render operationele KPI kaarten en ingest-volume per dag.
 */
function ddo_render_scheduler_kpi_block() {
    $kpis            = ddo_get_scheduler_operational_kpis( 30 );
    $metadata        = ddo_get_scheduler_job_metadata();
    $fetch_meta      = isset( $metadata['ddo_hourly_fetch'] ) && is_array( $metadata['ddo_hourly_fetch'] ) ? $metadata['ddo_hourly_fetch'] : array();
    $fetch_health    = ddo_get_scheduler_job_health_kpis( $fetch_meta );
    $status          = isset( $fetch_health['status'] ) ? (string) $fetch_health['status'] : 'down';
    $status_labels   = array(
        'healthy'  => __( 'Healthy', 'data-driven-optimizer' ),
        'degraded' => __( 'Degraded', 'data-driven-optimizer' ),
        'down'     => __( 'Down', 'data-driven-optimizer' ),
    );
    $status_label    = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status_labels['down'];
    $last_success_ts = isset( $fetch_health['last_success'] ) ? (int) $fetch_health['last_success'] : 0;
    ?>
    <div class="ddo-feedback-cards">
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Fetch success rate (last 10 runs)', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( (float) $fetch_health['success_rate'], 2 ) ); ?>%</p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Laatst succesvolle fetch', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo $last_success_ts > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $last_success_ts ) ) : esc_html__( 'Nooit', 'data-driven-optimizer' ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Huidige fetch status', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( $status_label ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Run success rate (30d)', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( (float) $kpis['success_rate'], 2 ) ); ?>%</p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Median duration (30d)', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( ddo_format_scheduler_duration_seconds( (float) $kpis['median_duration'] ) ); ?></p>
        </div>
        <div class="ddo-feedback-card">
            <h3><?php esc_html_e( 'Runs geanalyseerd (30d)', 'data-driven-optimizer' ); ?></h3>
            <p><?php echo esc_html( number_format_i18n( (int) $kpis['total_runs'] ) ); ?></p>
        </div>
    </div>
    <table class="widefat striped ddo-scheduler-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Dag', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Ingest volume', 'data-driven-optimizer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $kpis['ingest_per_day'] ) ) : ?>
                <tr>
                    <td colspan="2"><?php esc_html_e( 'Nog geen scheduler run-data beschikbaar.', 'data-driven-optimizer' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $kpis['ingest_per_day'] as $day => $volume ) : ?>
                    <tr>
                        <td><?php echo esc_html( $day ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (int) $volume ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render laatste scheduler events (runs en fouten).
 */
function ddo_render_recent_scheduler_events_block() {
    $events = ddo_get_recent_scheduler_events( 20 );

    if ( empty( $events ) ) {
        echo '<p>' . esc_html__( 'Nog geen scheduler events geregistreerd.', 'data-driven-optimizer' ) . '</p>';
        return;
    }
    ?>
    <table class="widefat striped ddo-scheduler-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Tijd', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Job', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Event', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Status', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Duur', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Resultaat', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Error code', 'data-driven-optimizer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $events as $event ) : ?>
                <?php
                $context = isset( $event['context'] ) && is_array( $event['context'] ) ? $event['context'] : array();
                ?>
                <tr>
                    <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', isset( $event['timestamp'] ) ? (int) $event['timestamp'] : 0 ) ); ?></td>
                    <td><code><?php echo esc_html( isset( $event['job_name'] ) ? (string) $event['job_name'] : '' ); ?></code></td>
                    <td><?php echo esc_html( isset( $event['message'] ) ? (string) $event['message'] : '' ); ?></td>
                    <td><?php echo esc_html( isset( $event['level'] ) ? strtoupper( (string) $event['level'] ) : 'INFO' ); ?></td>
                    <td><?php echo esc_html( ddo_format_scheduler_duration_seconds( isset( $context['duration'] ) ? (float) $context['duration'] : 0 ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( isset( $context['result_count'] ) ? (int) $context['result_count'] : 0 ) ); ?></td>
                    <td><?php echo '' !== ( isset( $context['error_code'] ) ? (string) $context['error_code'] : '' ) ? esc_html( (string) $context['error_code'] ) : '&mdash;'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Formatteer run-duur altijd in seconden voor consistente weergave.
 *
 * @param int|float $duration_seconds Duur in seconden.
 * @return string
 */
function ddo_format_scheduler_duration_seconds( $duration_seconds ) {
    $duration_seconds = max( 0, (float) $duration_seconds );

    return sprintf(
        /* translators: %s: duur in seconden. */
        __( '%s sec', 'data-driven-optimizer' ),
        number_format_i18n( (int) round( $duration_seconds ) )
    );
}

/**
 * Render scheduler observability tabel met run-now acties.
 */
function ddo_render_scheduler_status_block() {
    $jobs     = ddo_get_scheduler_observability_jobs();
    $metadata = ddo_get_scheduler_job_metadata();
    $now      = time();
    $groups   = array(
        'stale'   => array(),
        'healthy' => array(),
    );

    foreach ( $jobs as $job_name => $job_config ) {
        $job_meta           = isset( $metadata[ $job_name ] ) && is_array( $metadata[ $job_name ] ) ? $metadata[ $job_name ] : array();
        $last_start         = isset( $job_meta['last_start'] ) ? (int) $job_meta['last_start'] : 0;
        $last_success       = isset( $job_meta['last_success'] ) ? (int) $job_meta['last_success'] : 0;
        $last_error_message = isset( $job_meta['last_error_message'] ) ? (string) $job_meta['last_error_message'] : '';
        $last_error_code    = isset( $job_meta['last_error_code'] ) ? (string) $job_meta['last_error_code'] : '';
        $last_duration      = isset( $job_meta['last_run_duration'] ) ? (float) $job_meta['last_run_duration'] : 0;
        $next_run           = wp_next_scheduled( $job_name );
        $expected_interval  = isset( $job_config['expected_interval'] ) ? (int) $job_config['expected_interval'] : HOUR_IN_SECONDS;
        $stale_threshold    = 2 * $expected_interval;
        $seconds_since_ok   = $last_success > 0 ? $now - $last_success : PHP_INT_MAX;
        $is_stale           = $seconds_since_ok > $stale_threshold;

        $groups[ $is_stale ? 'stale' : 'healthy' ][] = compact(
            'job_name',
            'last_start',
            'last_success',
            'last_error_message',
            'last_error_code',
            'last_duration',
            'next_run',
            'expected_interval',
            'stale_threshold',
            'seconds_since_ok',
            'is_stale'
        );
    }
    ?>
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="ddo-scheduler-bulk-form">
        <input type="hidden" name="action" value="ddo_run_scheduler_job" />
        <input type="hidden" name="job_name" value="__all_safe__" />
        <?php wp_nonce_field( 'ddo_run_scheduler_job_all_safe', 'ddo_run_scheduler_job_nonce' ); ?>
        <?php submit_button( __( 'Run all safe jobs now', 'data-driven-optimizer' ), 'secondary', 'submit', false ); ?>
    </form>

    <?php foreach ( array( 'stale', 'healthy' ) as $group_key ) : ?>
        <?php
        $items       = $groups[ $group_key ];
        $is_stale    = 'stale' === $group_key;
        $group_class = $is_stale ? 'ddo-scheduler-group-stale' : 'ddo-scheduler-group-healthy';
        ?>
        <section class="ddo-scheduler-group <?php echo esc_attr( $group_class ); ?>">
            <h4>
                <?php
                echo $is_stale
                    ? esc_html__( 'Stale jobs', 'data-driven-optimizer' )
                    : esc_html__( 'Healthy jobs', 'data-driven-optimizer' );
                ?>
            </h4>
            <?php if ( empty( $items ) ) : ?>
                <p><?php echo $is_stale ? esc_html__( 'Geen stale jobs gevonden.', 'data-driven-optimizer' ) : esc_html__( 'Geen healthy jobs gevonden.', 'data-driven-optimizer' ); ?></p>
                <?php continue; ?>
            <?php endif; ?>
    <table class="widefat striped ddo-scheduler-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Job', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste start', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste succes', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste run duur', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Volgende geplande run', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Laatste foutmelding', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Error code', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Status', 'data-driven-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Actie', 'data-driven-optimizer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) : ?>
                <?php
                $status_label = $item['is_stale']
                    ? __( 'Stale', 'data-driven-optimizer' )
                    : __( 'OK', 'data-driven-optimizer' );
                $row_class    = $item['is_stale'] ? 'ddo-scheduler-row-stale' : 'ddo-scheduler-row-ok';
                ?>
                <tr class="<?php echo esc_attr( $row_class ); ?>">
                    <td><code><?php echo esc_html( $item['job_name'] ); ?></code></td>
                    <td><?php echo $item['last_start'] > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $item['last_start'] ) ) : esc_html__( 'Nooit', 'data-driven-optimizer' ); ?></td>
                    <td><?php echo $item['last_success'] > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $item['last_success'] ) ) : esc_html__( 'Nooit', 'data-driven-optimizer' ); ?></td>
                    <td><?php echo $item['last_duration'] > 0 ? esc_html( ddo_format_scheduler_duration_seconds( $item['last_duration'] ) ) : '&mdash;'; ?></td>
                    <td><?php echo $item['next_run'] ? esc_html( wp_date( 'Y-m-d H:i:s', (int) $item['next_run'] ) ) : esc_html__( 'Niet gepland', 'data-driven-optimizer' ); ?></td>
                    <td><?php echo '' !== $item['last_error_message'] ? esc_html( $item['last_error_message'] ) : '&mdash;'; ?></td>
                    <td><?php echo '' !== $item['last_error_code'] ? esc_html( $item['last_error_code'] ) : '&mdash;'; ?></td>
                    <td>
                        <span class="ddo-scheduler-status-pill <?php echo esc_attr( $item['is_stale'] ? 'is-stale' : 'is-ok' ); ?>">
                            <span aria-hidden="true"><?php echo esc_html( $status_label ); ?></span>
                            <span class="screen-reader-text">
                                <?php
                                printf(
                                    /* translators: %s: statuslabel voor scheduler job. */
                                    esc_html__( 'Scheduler status: %s', 'data-driven-optimizer' ),
                                    esc_html( $status_label )
                                );
                                ?>
                            </span>
                        </span>
                        <?php if ( $item['is_stale'] ) : ?>
                            <p class="description">
                                <?php echo esc_html( ddo_get_scheduler_stale_cause_text( $item['last_success'], $item['seconds_since_ok'], $item['stale_threshold'], $item['last_error_message'], $item['expected_interval'] ) ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="ddo-scheduler-run-form">
                            <input type="hidden" name="action" value="ddo_run_scheduler_job" />
                            <input type="hidden" name="job_name" value="<?php echo esc_attr( $item['job_name'] ); ?>" />
                            <?php if ( 'ddo_hourly_fetch' === $item['job_name'] ) : ?>
                                <fieldset class="ddo-inline-source-selector">
                                    <legend class="screen-reader-text"><?php esc_html_e( 'Selecteer bronnen voor handmatige fetch', 'data-driven-optimizer' ); ?></legend>
                                    <?php foreach ( array_keys( ddo_get_data_source_registry() ) as $run_source_key ) : ?>
                                        <label>
                                            <input type="checkbox" name="source_keys[]" value="<?php echo esc_attr( $run_source_key ); ?>" />
                                            <span><?php echo esc_html( $run_source_key ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>
                            <?php wp_nonce_field( 'ddo_run_scheduler_job_' . $item['job_name'], 'ddo_run_scheduler_job_nonce' ); ?>
                            <?php submit_button( __( 'Run now', 'data-driven-optimizer' ), 'secondary small', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </section>
    <?php endforeach; ?>
    <?php
}

/**
 * Verwerk scheduler-run request data.
 *
 * @param array $request Requestdata.
 * @return array
 */
function ddo_process_run_scheduler_job_request( $request ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return array(
            'notice' => 'forbidden',
        );
    }

    $job_name = isset( $request['job_name'] ) ? sanitize_key( wp_unslash( $request['job_name'] ) ) : '';
    $jobs     = ddo_get_scheduler_observability_jobs();

    if ( '__all_safe__' === $job_name ) {
        $nonce = isset( $request['ddo_run_scheduler_job_nonce'] ) ? sanitize_text_field( wp_unslash( $request['ddo_run_scheduler_job_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'ddo_run_scheduler_job_all_safe' ) ) {
            return array(
                'notice' => 'nonce_invalid',
                'job'    => $job_name,
            );
        }

        $executed_jobs = array();

        foreach ( array_keys( $jobs ) as $safe_job_name ) {
            do_action( $safe_job_name );
            $executed_jobs[] = $safe_job_name;
        }

        return array(
            'notice'       => 'ok_bulk',
            'job'          => $job_name,
            'executedJobs' => $executed_jobs,
        );
    }

    if ( '' === $job_name || ! isset( $jobs[ $job_name ] ) ) {
        return array(
            'notice' => 'invalid',
            'job'    => $job_name,
        );
    }

    $nonce = isset( $request['ddo_run_scheduler_job_nonce'] ) ? sanitize_text_field( wp_unslash( $request['ddo_run_scheduler_job_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'ddo_run_scheduler_job_' . $job_name ) ) {
        return array(
            'notice' => 'nonce_invalid',
            'job'    => $job_name,
        );
    }

    if ( 'ddo_hourly_fetch' === $job_name ) {
        $source_keys = isset( $request['source_keys'] ) && is_array( $request['source_keys'] ) ? $request['source_keys'] : array();
        $source_keys = array_map( 'sanitize_key', $source_keys );
        $source_keys = array_values( array_filter( array_unique( $source_keys ) ) );

        ddo_run_hourly_fetch_job( $source_keys );

        return array(
            'notice'       => 'ok',
            'job'          => $job_name,
            'executedJobs' => array( $job_name ),
        );
    }

    do_action( $job_name );

    return array(
        'notice' => 'ok',
        'job'    => $job_name,
    );
}

/**
 * Verwerk handmatige scheduler-run vanuit admin.
 */
function ddo_handle_run_scheduler_job() {
    $result       = ddo_process_run_scheduler_job_request( wp_unslash( $_POST ) );
    $notice       = isset( $result['notice'] ) ? $result['notice'] : 'invalid';
    $redirect_url = admin_url( 'admin.php?page=ddo-dashboard&ddo_scheduler_notice=' . rawurlencode( $notice ) );

    if ( isset( $result['job'] ) && '' !== $result['job'] ) {
        $redirect_url .= '&ddo_scheduler_job=' . rawurlencode( $result['job'] );
    }

    if ( isset( $result['executedJobs'] ) && is_array( $result['executedJobs'] ) && ! empty( $result['executedJobs'] ) ) {
        $redirect_url .= '&ddo_scheduler_jobs=' . rawurlencode( implode( ', ', $result['executedJobs'] ) );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
