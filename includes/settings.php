<?php
/**
 * Instellingenregistratie voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prefix om versleutelde geheimen te markeren.
 */
const DDO_ENCRYPTED_SECRET_PREFIX = 'ddoenc:v1:';

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
            'sanitize_callback' => 'ddo_sanitize_api_key_primary',
            'default'           => '',
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_api_key_secondary',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_api_key_secondary',
            'default'           => '',
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_feedback_retention_days',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'ddo_sanitize_feedback_retention_days',
            'default'           => 180,
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_ga4_property_id',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_ga4_property_id',
            'default'           => '',
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_ga4_service_account_json',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_ga4_service_account_json',
            'default'           => '',
        )
    );

    register_setting(
        'ddo_settings_group',
        'ddo_ga4_auth_mode',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'ddo_sanitize_ga4_auth_mode',
            'default'           => 'bearer_token',
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

    add_settings_field(
        'ddo_feedback_retention_days',
        __( 'Feedback retentie (dagen)', 'data-driven-optimizer' ),
        'ddo_render_feedback_retention_days_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );

    add_settings_field(
        'ddo_ga4_property_id',
        __( 'GA4 Property ID', 'data-driven-optimizer' ),
        'ddo_render_ga4_property_id_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );

    add_settings_field(
        'ddo_ga4_service_account_json',
        __( 'GA4 service account JSON / token referentie', 'data-driven-optimizer' ),
        'ddo_render_ga4_service_account_json_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );

    add_settings_field(
        'ddo_ga4_auth_mode',
        __( 'GA4 authenticatie modus', 'data-driven-optimizer' ),
        'ddo_render_ga4_auth_mode_field',
        'ddo_settings_group',
        'ddo_general_settings_section'
    );
}

/**
 * Sanitize GA4 authenticatiemodus.
 *
 * @param string $value Ruwe auth-modus.
 * @return string
 */
function ddo_sanitize_ga4_auth_mode( $value ) {
    $value = sanitize_key( (string) $value );

    if ( in_array( $value, array( 'service_account_json', 'bearer_token' ), true ) ) {
        return $value;
    }

    return 'bearer_token';
}

/**
 * Sanitize feedback retentie in dagen.
 *
 * @param mixed $value Ruwe input.
 * @return int
 */
function ddo_sanitize_feedback_retention_days( $value ) {
    $days = absint( $value );

    if ( $days < 7 ) {
        return 180;
    }

    return min( 3650, $days );
}

/**
 * Leid key-materiaal af uit WordPress salts.
 *
 * @return string
 */
function ddo_get_secret_encryption_key() {
    $material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) . wp_salt( 'logged_in' ) . wp_salt( 'nonce' );
    return hash( 'sha256', $material, true );
}

/**
 * Controleer of een waarde al versleuteld is.
 *
 * @param mixed $value Te controleren waarde.
 * @return bool
 */
function ddo_is_encrypted_secret( $value ) {
    return is_string( $value ) && 0 === strpos( $value, DDO_ENCRYPTED_SECRET_PREFIX );
}

/**
 * Versleutel een geheim met WordPress salts.
 *
 * @param string $value Geheim in plaintext.
 * @return string
 */
function ddo_encrypt_secret( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        return '';
    }

    if ( ddo_is_encrypted_secret( $value ) ) {
        return $value;
    }

    if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) {
        return $value;
    }

    $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );

    if ( ! is_int( $iv_length ) || $iv_length <= 0 ) {
        return $value;
    }

    try {
        $iv = random_bytes( $iv_length );
    } catch ( Exception $exception ) {
        return $value;
    }

    $ciphertext = openssl_encrypt(
        $value,
        'aes-256-cbc',
        ddo_get_secret_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv
    );

    if ( false === $ciphertext ) {
        return $value;
    }

    return DDO_ENCRYPTED_SECRET_PREFIX . base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Ontsleutel opgeslagen geheim met veilige plaintext fallback.
 *
 * @param string $value Opgeslagen (mogelijk versleutelde) waarde.
 * @return string
 */
function ddo_decrypt_secret( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        return '';
    }

    if ( ! ddo_is_encrypted_secret( $value ) ) {
        // Legacy fallback: plaintext blijft leesbaar.
        return $value;
    }

    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return '';
    }

    $encoded = substr( $value, strlen( DDO_ENCRYPTED_SECRET_PREFIX ) );
    $raw     = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

    if ( false === $raw ) {
        return '';
    }

    $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );

    if ( ! is_int( $iv_length ) || $iv_length <= 0 || strlen( $raw ) <= $iv_length ) {
        return '';
    }

    $iv         = substr( $raw, 0, $iv_length );
    $ciphertext = substr( $raw, $iv_length );

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-cbc',
        ddo_get_secret_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv
    );

    if ( false === $plaintext ) {
        return '';
    }

    return (string) $plaintext;
}

/**
 * Haal API-key op, met decryptie en migratie van oude plaintext.
 *
 * @param string $option_name Optienaam.
 * @return string
 */
function ddo_get_api_key( $option_name ) {
    return ddo_get_secret_option( $option_name );
}

/**
 * Haal een versleutelde secret-optie op met decryptie en migratie van oude plaintext.
 *
 * @param string $option_name Optienaam.
 * @return string
 */
function ddo_get_secret_option( $option_name ) {
    $stored = get_option( $option_name, '' );
    $stored = is_string( $stored ) ? trim( $stored ) : '';

    if ( '' === $stored ) {
        return '';
    }

    if ( ddo_is_encrypted_secret( $stored ) ) {
        return ddo_decrypt_secret( $stored );
    }

    // Migratie-op-read van legacy plaintext.
    $encrypted = ddo_encrypt_secret( $stored );

    if ( $encrypted !== $stored ) {
        update_option( $option_name, $encrypted );
    }

    return $stored;
}

/**
 * Eenmalige migratie van API keys naar versleutelde opslag.
 */
function ddo_maybe_migrate_api_keys_to_encrypted() {
    $migrated = (int) get_option( 'ddo_api_keys_migrated_to_encrypted', 0 );

    if ( $migrated >= 1 ) {
        return;
    }

    $options = array(
        'ddo_api_key_primary',
        'ddo_api_key_secondary',
    );

    foreach ( $options as $option_name ) {
        $stored = get_option( $option_name, '' );
        $stored = is_string( $stored ) ? trim( $stored ) : '';

        if ( '' === $stored || ddo_is_encrypted_secret( $stored ) ) {
            continue;
        }

        update_option( $option_name, ddo_encrypt_secret( sanitize_text_field( $stored ) ) );
    }

    update_option( 'ddo_api_keys_migrated_to_encrypted', 1 );
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
function ddo_sanitize_api_key( $value, $option_name ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        $existing = get_option( $option_name, '' );
        return is_string( $existing ) ? $existing : '';
    }

    $sanitized = sanitize_text_field( $value );
    return ddo_encrypt_secret( $sanitized );
}

/**
 * Sanitize primary API key input.
 *
 * @param string $value Ruwe API-key.
 * @return string
 */
function ddo_sanitize_api_key_primary( $value ) {
    return ddo_sanitize_api_key( $value, 'ddo_api_key_primary' );
}

/**
 * Sanitize secondary API key input.
 *
 * @param string $value Ruwe API-key.
 * @return string
 */
function ddo_sanitize_api_key_secondary( $value ) {
    return ddo_sanitize_api_key( $value, 'ddo_api_key_secondary' );
}

/**
 * Voeg Settings API foutmelding toe wanneer API beschikbaar is.
 *
 * @param string $setting Setting slug.
 * @param string $code    Unieke foutcode.
 * @param string $message Foutbericht.
 * @param string $type    Meldingstype.
 */
function ddo_add_settings_error( $setting, $code, $message, $type = 'error' ) {
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( $setting, $code, $message, $type );
    }
}

/**
 * Sanitize GA4 property ID.
 *
 * @param string $value Ruwe property ID.
 * @return string
 */
function ddo_sanitize_ga4_property_id( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        ddo_add_settings_error(
            'ddo_ga4_property_id',
            'ddo_ga4_property_id_missing',
            __( 'GA4 Property ID is verplicht voor de fetch-job.', 'data-driven-optimizer' ),
            'error'
        );

        return '';
    }

    if ( ! preg_match( '/^\d{4,20}$/', $value ) ) {
        ddo_add_settings_error(
            'ddo_ga4_property_id',
            'ddo_ga4_property_id_invalid',
            __( 'GA4 Property ID moet alleen cijfers bevatten (4-20 tekens).', 'data-driven-optimizer' ),
            'error'
        );

        return '';
    }

    return $value;
}

/**
 * Sanitize en versleutel GA4 secret/config invoer.
 *
 * @param string $value Ruwe GA4 secret/config input.
 * @return string
 */
function ddo_sanitize_ga4_service_account_json( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        $existing = get_option( 'ddo_ga4_service_account_json', '' );

        if ( '' === trim( (string) $existing ) ) {
            ddo_add_settings_error(
                'ddo_ga4_service_account_json',
                'ddo_ga4_service_account_json_missing',
                __( 'GA4 service account JSON of tokenreferentie ontbreekt.', 'data-driven-optimizer' ),
                'error'
            );
        }

        return is_string( $existing ) ? $existing : '';
    }

    $sanitized = sanitize_textarea_field( $value );

    if ( '' === $sanitized ) {
        ddo_add_settings_error(
            'ddo_ga4_service_account_json',
            'ddo_ga4_service_account_json_invalid',
            __( 'GA4 service account JSON of tokenreferentie is ongeldig.', 'data-driven-optimizer' ),
            'error'
        );

        return '';
    }

    return ddo_encrypt_secret( $sanitized );
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
    $has_api_key = '' !== ddo_get_api_key( 'ddo_api_key_primary' );
    ?>
    <input
        type="password"
        id="ddo_api_key_primary"
        name="ddo_api_key_primary"
        value=""
        class="regular-text"
        autocomplete="off"
        placeholder="<?php echo esc_attr( $has_api_key ? __( '•••••••• (ongewijzigd)', 'data-driven-optimizer' ) : __( 'Voer primary API-key in', 'data-driven-optimizer' ) ); ?>"
    />
    <p class="description"><?php esc_html_e( 'Wordt gebruikt voor de primaire provider. Laat leeg om ongewijzigd te laten.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render secondary API-key veld.
 */
function ddo_render_api_key_secondary_field() {
    $has_api_key = '' !== ddo_get_api_key( 'ddo_api_key_secondary' );
    ?>
    <input
        type="password"
        id="ddo_api_key_secondary"
        name="ddo_api_key_secondary"
        value=""
        class="regular-text"
        autocomplete="off"
        placeholder="<?php echo esc_attr( $has_api_key ? __( '•••••••• (ongewijzigd)', 'data-driven-optimizer' ) : __( 'Voer secondary API-key in', 'data-driven-optimizer' ) ); ?>"
    />
    <p class="description"><?php esc_html_e( 'Wordt gebruikt voor fallback of analytics-provider. Laat leeg om ongewijzigd te laten.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render feedback retentieveld.
 */
function ddo_render_feedback_retention_days_field() {
    $retention_days = (int) get_option( 'ddo_feedback_retention_days', 180 );
    ?>
    <input
        type="number"
        id="ddo_feedback_retention_days"
        name="ddo_feedback_retention_days"
        value="<?php echo esc_attr( $retention_days ); ?>"
        min="7"
        max="3650"
        step="1"
        class="small-text"
    />
    <p class="description"><?php esc_html_e( 'Aantal dagen dat feedbackrecords worden bewaard voordat dagelijkse cleanup oude data verwijdert. Kies een waarde tussen 7 en 3650 dagen.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render GA4 Property ID veld.
 */
function ddo_render_ga4_property_id_field() {
    $property_id = sanitize_text_field( (string) get_option( 'ddo_ga4_property_id', '' ) );
    ?>
    <input
        type="text"
        id="ddo_ga4_property_id"
        name="ddo_ga4_property_id"
        value="<?php echo esc_attr( $property_id ); ?>"
        class="regular-text"
        inputmode="numeric"
        pattern="[0-9]{4,20}"
        placeholder="123456789"
    />
    <p class="description"><?php esc_html_e( 'Numerieke GA4 property ID uit Google Analytics (bijv. 123456789). Verplicht voor geplande fetch-jobs.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render GA4 secret/config veld.
 */
function ddo_render_ga4_service_account_json_field() {
    $has_secret = '' !== ddo_get_secret_option( 'ddo_ga4_service_account_json' );
    ?>
    <textarea
        id="ddo_ga4_service_account_json"
        name="ddo_ga4_service_account_json"
        rows="4"
        class="large-text code"
        autocomplete="off"
        placeholder="<?php echo esc_attr( $has_secret ? __( '•••••••• (ongewijzigd)', 'data-driven-optimizer' ) : __( 'Plak service-account JSON, pad of tokenreferentie', 'data-driven-optimizer' ) ); ?>"
    ></textarea>
    <p class="description"><?php esc_html_e( 'Opslag gebeurt versleuteld. Gebruik dit veld voor service-account JSON, een pad naar een secret-bestand of een tokenreferentie. Laat leeg om bestaande waarde te behouden.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/**
 * Render GA4 authenticatiemodus veld.
 */
function ddo_render_ga4_auth_mode_field() {
    $auth_mode = ddo_sanitize_ga4_auth_mode( get_option( 'ddo_ga4_auth_mode', 'bearer_token' ) );
    ?>
    <select id="ddo_ga4_auth_mode" name="ddo_ga4_auth_mode">
        <option value="service_account_json" <?php selected( $auth_mode, 'service_account_json' ); ?>><?php esc_html_e( 'Service account JSON (OAuth2)', 'data-driven-optimizer' ); ?></option>
        <option value="bearer_token" <?php selected( $auth_mode, 'bearer_token' ); ?>><?php esc_html_e( 'Bearer token', 'data-driven-optimizer' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( 'Kies expliciet één authenticatiemodus. Er wordt niet stil teruggevallen op een andere modus.', 'data-driven-optimizer' ); ?></p>
    <?php
}
