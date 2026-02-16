<?php
/**
 * Instellingenregistratie voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registreer plugininstellingen en velden.
 */
function ddo_register_settings() {
    add_action( 'admin_init', 'ddo_register_settings_fields' );
}

/**
 * Registreer Settings API velden.
 */
function ddo_register_settings_fields() {
    register_setting(
        'ddo_settings_group',
        'ddo_enabled',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'ddo_sanitize_enabled',
            'default'           => true,
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_api_key_primary',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_api_key',
            'default'           => '',
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_api_key_secondary',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_api_key',
            'default'           => '',
        )
    );

    add_settings_section(
        'ddo_general_settings_section',
        __( 'Algemene instellingen', 'data-driven-optimizer' ),
        '__return_false',
        'ddo_settings_group'
    );

    add_settings_field(
        'ddo_enabled',
        __( 'Plugin ingeschakeld', 'data-driven-optimizer' ),
        'ddo_render_enabled_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );

    add_settings_field(
        'ddo_api_key_primary',
        __( 'Primary API-key', 'data-driven-optimizer' ),
        'ddo_render_api_key_primary_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );

    add_settings_field(
        'ddo_api_key_secondary',
        __( 'Secondary API-key', 'data-driven-optimizer' ),
        'ddo_render_api_key_secondary_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );
}

/**
 * Sanitize enabled checkbox.
 *
 * @param mixed $value Ruwe input.
 * @return bool
 */
function ddo_sanitize_enabled( $value ) {
    return ! empty( $value );
}

/**
 * Sanitize API key input.
 *
 * @param string $value Ruwe API-key.
 * @return string
 */
function ddo_sanitize_api_key( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    return sanitize_text_field( $value );
}

/**
 * Render checkbox voor enabled-vlag.
 */
function ddo_render_enabled_field() {
    $enabled = (bool) get_option( 'ddo_enabled', true );
    ?>
    <label for="ddo_enabled">
        <input type="checkbox" id="ddo_enabled" name="ddo_enabled" value="1" <?php checked( $enabled ); ?> />
        <?php esc_html_e( 'Schakel Data Driven Optimizer in', 'data-driven-optimizer' ); ?>
    </label>
    <?php
}

/**
 * Render primary API-key veld.
 */
function ddo_render_api_key_primary_field() {
    $api_key = (string) get_option( 'ddo_api_key_primary', '' );
    ?>
    <input
        type="password"
        id="ddo_api_key_primary"
        name="ddo_api_key_primary"
        value="<?php echo esc_attr( $api_key ); ?>"
        class="regular-text"
        autocomplete="off"
    />
    <p class="description"><?php esc_html_e( 'Wordt gebruikt voor de primaire provider.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render secondary API-key veld.
 */
function ddo_render_api_key_secondary_field() {
    $api_key = (string) get_option( 'ddo_api_key_secondary', '' );
    ?>
    <input
        type="password"
        id="ddo_api_key_secondary"
        name="ddo_api_key_secondary"
        value="<?php echo esc_attr( $api_key ); ?>"
        class="regular-text"
        autocomplete="off"
    />
    <p class="description"><?php esc_html_e( 'Wordt gebruikt voor fallback of analytics-provider.', 'data-driven-optimizer' ); ?></p>
    <?php
}
