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
