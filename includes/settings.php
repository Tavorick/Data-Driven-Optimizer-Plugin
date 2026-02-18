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
 * Opties die altijd als secret behandeld en versleuteld opgeslagen moeten worden.
 *
 * @return string[]
 */
function ddo_get_secret_option_keys() {
    return array(
        'ddo_api_key_primary',
        'ddo_api_key_secondary',
        'ddo_ga4_service_account_json',
        'ddo_ga4_bearer_token',
        'ddo_facebook_ads_app_secret',
        'ddo_facebook_ads_access_token',
        'ddo_search_console_oauth_client_secret',
        'ddo_search_console_oauth_reference',
    );
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

    register_setting( 'ddo_settings_group', 'ddo_ga4_property_id', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_ga4_property_id', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_ga4_auth_mode', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_ga4_auth_mode', 'default' => 'bearer_token' ) );
    register_setting( 'ddo_settings_group', 'ddo_ga4_service_account_json', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_ga4_service_account_json', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_ga4_bearer_token', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_ga4_bearer_token', 'default' => '' ) );

    register_setting( 'ddo_settings_group', 'ddo_facebook_ads_app_id', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_facebook_ads_app_id', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_facebook_ads_app_secret', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_facebook_ads_app_secret', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_facebook_ads_access_token', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_facebook_ads_access_token', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_facebook_ads_ad_account_id', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_facebook_ads_ad_account_id', 'default' => '' ) );

    register_setting( 'ddo_settings_group', 'ddo_search_console_site_url', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_search_console_site_url', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_search_console_oauth_client_id', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_search_console_oauth_client_id', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_search_console_oauth_client_secret', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_search_console_oauth_client_secret', 'default' => '' ) );
    register_setting( 'ddo_settings_group', 'ddo_search_console_oauth_reference', array( 'type' => 'string', 'sanitize_callback' => 'ddo_sanitize_search_console_oauth_reference', 'default' => '' ) );

    add_settings_section( 'ddo_general_settings_section', __( 'Algemene instellingen', 'data-driven-optimizer' ), '__return_false', 'ddo_settings_group' );
    add_settings_section( 'ddo_ga4_settings_section', __( 'Google Analytics 4', 'data-driven-optimizer' ), '__return_false', 'ddo_settings_group' );
    add_settings_section( 'ddo_facebook_ads_settings_section', __( 'Facebook Ads', 'data-driven-optimizer' ), '__return_false', 'ddo_settings_group' );
    add_settings_section( 'ddo_search_console_settings_section', __( 'Search Console', 'data-driven-optimizer' ), '__return_false', 'ddo_settings_group' );

    add_settings_field( 'ddo_enabled', __( 'Plugin ingeschakeld', 'data-driven-optimizer' ), 'ddo_render_enabled_field', 'ddo_settings_group', 'ddo_general_settings_section' );
    add_settings_field( 'ddo_api_key_primary', __( 'Primary API-key', 'data-driven-optimizer' ), 'ddo_render_api_key_primary_field', 'ddo_settings_group', 'ddo_general_settings_section' );
    add_settings_field( 'ddo_api_key_secondary', __( 'Secondary API-key', 'data-driven-optimizer' ), 'ddo_render_api_key_secondary_field', 'ddo_settings_group', 'ddo_general_settings_section' );
    add_settings_field( 'ddo_feedback_retention_days', __( 'Feedback retentie (dagen)', 'data-driven-optimizer' ), 'ddo_render_feedback_retention_days_field', 'ddo_settings_group', 'ddo_general_settings_section' );

    add_settings_field( 'ddo_ga4_property_id', __( 'GA4 Property ID', 'data-driven-optimizer' ), 'ddo_render_ga4_property_id_field', 'ddo_settings_group', 'ddo_ga4_settings_section' );
    add_settings_field( 'ddo_ga4_auth_mode', __( 'GA4 authenticatie modus', 'data-driven-optimizer' ), 'ddo_render_ga4_auth_mode_field', 'ddo_settings_group', 'ddo_ga4_settings_section' );
    add_settings_field( 'ddo_ga4_service_account_json', __( 'GA4 service account JSON / token referentie', 'data-driven-optimizer' ), 'ddo_render_ga4_service_account_json_field', 'ddo_settings_group', 'ddo_ga4_settings_section' );
    add_settings_field( 'ddo_ga4_bearer_token', __( 'GA4 bearer token', 'data-driven-optimizer' ), 'ddo_render_ga4_bearer_token_field', 'ddo_settings_group', 'ddo_ga4_settings_section' );

    add_settings_field( 'ddo_facebook_ads_app_id', __( 'Facebook Ads app ID', 'data-driven-optimizer' ), 'ddo_render_facebook_ads_app_id_field', 'ddo_settings_group', 'ddo_facebook_ads_settings_section' );
    add_settings_field( 'ddo_facebook_ads_app_secret', __( 'Facebook Ads app secret', 'data-driven-optimizer' ), 'ddo_render_facebook_ads_app_secret_field', 'ddo_settings_group', 'ddo_facebook_ads_settings_section' );
    add_settings_field( 'ddo_facebook_ads_access_token', __( 'Facebook Ads access token', 'data-driven-optimizer' ), 'ddo_render_facebook_ads_access_token_field', 'ddo_settings_group', 'ddo_facebook_ads_settings_section' );
    add_settings_field( 'ddo_facebook_ads_ad_account_id', __( 'Facebook Ads ad account ID', 'data-driven-optimizer' ), 'ddo_render_facebook_ads_ad_account_id_field', 'ddo_settings_group', 'ddo_facebook_ads_settings_section' );

    add_settings_field( 'ddo_search_console_site_url', __( 'Search Console site URL', 'data-driven-optimizer' ), 'ddo_render_search_console_site_url_field', 'ddo_settings_group', 'ddo_search_console_settings_section' );
    add_settings_field( 'ddo_search_console_oauth_client_id', __( 'Search Console OAuth client ID', 'data-driven-optimizer' ), 'ddo_render_search_console_oauth_client_id_field', 'ddo_settings_group', 'ddo_search_console_settings_section' );
    add_settings_field( 'ddo_search_console_oauth_client_secret', __( 'Search Console OAuth client secret', 'data-driven-optimizer' ), 'ddo_render_search_console_oauth_client_secret_field', 'ddo_settings_group', 'ddo_search_console_settings_section' );
    add_settings_field( 'ddo_search_console_oauth_reference', __( 'Search Console OAuth tokenreferentie', 'data-driven-optimizer' ), 'ddo_render_search_console_oauth_reference_field', 'ddo_settings_group', 'ddo_search_console_settings_section' );
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

    ddo_add_settings_error(
        'ddo_ga4_auth_mode',
        'ddo_ga4_auth_mode_invalid',
        __( 'GA4 authenticatie modus is ongeldig. Gebruik service_account_json of bearer_token.', 'data-driven-optimizer' ),
        'error'
    );

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
 * Sanitize generiek secret veld met behoud van bestaande waarde wanneer input leeg is.
 *
 * @param string $value           Ingevoerde waarde.
 * @param string $option_name     Option key.
 * @param string $error_setting   Settings API error target.
 * @param string $error_prefix    Prefix voor foutcodes.
 * @param string $missing_message Melding bij ontbrekende waarde.
 * @param string $invalid_message Melding bij ongeldige waarde.
 * @param string $mode            sanitize_text of sanitize_textarea.
 * @return string
 */
function ddo_sanitize_secret_setting( $value, $option_name, $error_setting, $error_prefix, $missing_message, $invalid_message, $mode = 'sanitize_text' ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value ) {
        $existing = get_option( $option_name, '' );
        $existing = is_string( $existing ) ? trim( $existing ) : '';

        if ( '' === $existing ) {
            ddo_add_settings_error( $error_setting, $error_prefix . '_missing', $missing_message, 'error' );
        }

        return $existing;
    }

    $sanitized = 'sanitize_textarea' === $mode ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

    if ( '' === $sanitized ) {
        ddo_add_settings_error( $error_setting, $error_prefix . '_invalid', $invalid_message, 'error' );
        return '';
    }

    return ddo_encrypt_secret( $sanitized );
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
        ddo_add_settings_error( 'ddo_ga4_property_id', 'ddo_ga4_property_id_missing', __( 'GA4 Property ID is verplicht voor de fetch-job.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    if ( ! preg_match( '/^\d{4,20}$/', $value ) ) {
        ddo_add_settings_error( 'ddo_ga4_property_id', 'ddo_ga4_property_id_invalid', __( 'GA4 Property ID moet alleen cijfers bevatten (4-20 tekens).', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    return $value;
}

/**
 * Sanitize en versleutel GA4 service-account configuratie.
 *
 * @param string $value Ruwe GA4 input.
 * @return string
 */
function ddo_sanitize_ga4_service_account_json( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_ga4_service_account_json',
        'ddo_ga4_service_account_json',
        'ddo_ga4_service_account_json',
        __( 'GA4 service account JSON of tokenreferentie ontbreekt.', 'data-driven-optimizer' ),
        __( 'GA4 service account JSON of tokenreferentie is ongeldig.', 'data-driven-optimizer' ),
        'sanitize_textarea'
    );
}

/**
 * Sanitize en versleutel GA4 bearer token.
 *
 * @param string $value Ruwe GA4 bearer token.
 * @return string
 */
function ddo_sanitize_ga4_bearer_token( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_ga4_bearer_token',
        'ddo_ga4_bearer_token',
        'ddo_ga4_bearer_token',
        __( 'GA4 bearer token ontbreekt.', 'data-driven-optimizer' ),
        __( 'GA4 bearer token is ongeldig.', 'data-driven-optimizer' )
    );
}

/**
 * Sanitize Facebook Ads app ID.
 *
 * @param string $value Ruwe app ID.
 * @return string
 */
function ddo_sanitize_facebook_ads_app_id( $value ) {
    $value = sanitize_text_field( (string) $value );

    if ( '' === $value ) {
        ddo_add_settings_error( 'ddo_facebook_ads_app_id', 'ddo_facebook_ads_app_id_missing', __( 'Facebook Ads app ID ontbreekt.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    if ( ! preg_match( '/^\d{6,32}$/', $value ) ) {
        ddo_add_settings_error( 'ddo_facebook_ads_app_id', 'ddo_facebook_ads_app_id_invalid', __( 'Facebook Ads app ID moet numeriek zijn (6-32 cijfers).', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    return $value;
}

/**
 * Sanitize Facebook Ads app secret.
 *
 * @param string $value Ruwe app secret.
 * @return string
 */
function ddo_sanitize_facebook_ads_app_secret( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_facebook_ads_app_secret',
        'ddo_facebook_ads_app_secret',
        'ddo_facebook_ads_app_secret',
        __( 'Facebook Ads app secret ontbreekt.', 'data-driven-optimizer' ),
        __( 'Facebook Ads app secret is ongeldig.', 'data-driven-optimizer' )
    );
}

/**
 * Sanitize Facebook Ads access token.
 *
 * @param string $value Ruwe access token.
 * @return string
 */
function ddo_sanitize_facebook_ads_access_token( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_facebook_ads_access_token',
        'ddo_facebook_ads_access_token',
        'ddo_facebook_ads_access_token',
        __( 'Facebook Ads access token ontbreekt.', 'data-driven-optimizer' ),
        __( 'Facebook Ads access token is ongeldig.', 'data-driven-optimizer' )
    );
}

/**
 * Sanitize Facebook Ads ad account ID.
 *
 * @param string $value Ruwe account ID.
 * @return string
 */
function ddo_sanitize_facebook_ads_ad_account_id( $value ) {
    $value = sanitize_text_field( (string) $value );

    if ( '' === $value ) {
        ddo_add_settings_error( 'ddo_facebook_ads_ad_account_id', 'ddo_facebook_ads_ad_account_id_missing', __( 'Facebook Ads ad account ID ontbreekt.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    if ( ! preg_match( '/^(act_)?\d{6,32}$/', $value ) ) {
        ddo_add_settings_error( 'ddo_facebook_ads_ad_account_id', 'ddo_facebook_ads_ad_account_id_invalid', __( 'Facebook Ads ad account ID moet de vorm act_123... of alleen cijfers hebben.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    return $value;
}

/**
 * Sanitize Search Console site URL.
 *
 * @param string $value Ruwe site URL.
 * @return string
 */
function ddo_sanitize_search_console_site_url( $value ) {
    $value = esc_url_raw( trim( (string) $value ) );

    if ( '' === $value ) {
        ddo_add_settings_error( 'ddo_search_console_site_url', 'ddo_search_console_site_url_missing', __( 'Search Console site URL ontbreekt.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    if ( ! preg_match( '#^https?://#i', $value ) ) {
        ddo_add_settings_error( 'ddo_search_console_site_url', 'ddo_search_console_site_url_invalid', __( 'Search Console site URL moet met http:// of https:// beginnen.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    return rtrim( $value, '/' );
}

/**
 * Sanitize Search Console OAuth client ID.
 *
 * @param string $value Ruwe OAuth client ID.
 * @return string
 */
function ddo_sanitize_search_console_oauth_client_id( $value ) {
    $value = sanitize_text_field( (string) $value );

    if ( '' === $value ) {
        ddo_add_settings_error( 'ddo_search_console_oauth_client_id', 'ddo_search_console_oauth_client_id_missing', __( 'Search Console OAuth client ID ontbreekt.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    if ( ! preg_match( '/^[A-Za-z0-9._-]{10,200}$/', $value ) ) {
        ddo_add_settings_error( 'ddo_search_console_oauth_client_id', 'ddo_search_console_oauth_client_id_invalid', __( 'Search Console OAuth client ID bevat ongeldige tekens.', 'data-driven-optimizer' ), 'error' );
        return '';
    }

    return $value;
}

/**
 * Sanitize Search Console OAuth client secret.
 *
 * @param string $value Ruwe OAuth client secret.
 * @return string
 */
function ddo_sanitize_search_console_oauth_client_secret( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_search_console_oauth_client_secret',
        'ddo_search_console_oauth_client_secret',
        'ddo_search_console_oauth_client_secret',
        __( 'Search Console OAuth client secret ontbreekt.', 'data-driven-optimizer' ),
        __( 'Search Console OAuth client secret is ongeldig.', 'data-driven-optimizer' )
    );
}

/**
 * Sanitize Search Console OAuth tokenreferentie.
 *
 * @param string $value Ruwe tokenreferentie.
 * @return string
 */
function ddo_sanitize_search_console_oauth_reference( $value ) {
    return ddo_sanitize_secret_setting(
        $value,
        'ddo_search_console_oauth_reference',
        'ddo_search_console_oauth_reference',
        'ddo_search_console_oauth_reference',
        __( 'Search Console OAuth tokenreferentie ontbreekt.', 'data-driven-optimizer' ),
        __( 'Search Console OAuth tokenreferentie is ongeldig.', 'data-driven-optimizer' )
    );
}

/**
 * Render input voor secret velden met placeholder en standaard hulptekst.
 */
function ddo_render_secret_input_field( $option_name, $label_new, $description, $is_textarea = false ) {
    $has_secret = '' !== ddo_get_secret_option( $option_name );

    if ( $is_textarea ) {
        ?>
        <textarea
            id="<?php echo esc_attr( $option_name ); ?>"
            name="<?php echo esc_attr( $option_name ); ?>"
            rows="4"
            class="large-text code"
            autocomplete="off"
            placeholder="<?php echo esc_attr( $has_secret ? __( '•••••••• (ongewijzigd)', 'data-driven-optimizer' ) : $label_new ); ?>"
        ></textarea>
        <?php
    } else {
        ?>
        <input
            type="password"
            id="<?php echo esc_attr( $option_name ); ?>"
            name="<?php echo esc_attr( $option_name ); ?>"
            value=""
            class="regular-text"
            autocomplete="off"
            placeholder="<?php echo esc_attr( $has_secret ? __( '•••••••• (ongewijzigd)', 'data-driven-optimizer' ) : $label_new ); ?>"
        />
        <?php
    }

    printf( '<p class="description">%s</p>', esc_html( $description ) );
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
    ddo_render_secret_input_field( 'ddo_api_key_primary', __( 'Voer primary API-key in', 'data-driven-optimizer' ), __( 'Legacy sleutel, versleuteld opgeslagen. Laat leeg om ongewijzigd te laten.', 'data-driven-optimizer' ) );
}

/**
 * Render secondary API-key veld.
 */
function ddo_render_api_key_secondary_field() {
    ddo_render_secret_input_field( 'ddo_api_key_secondary', __( 'Voer secondary API-key in', 'data-driven-optimizer' ), __( 'Legacy fallback sleutel, versleuteld opgeslagen. Laat leeg om ongewijzigd te laten.', 'data-driven-optimizer' ) );
}

/**
 * Render feedback retentieveld.
 */
function ddo_render_feedback_retention_days_field() {
    $retention_days = (int) get_option( 'ddo_feedback_retention_days', 180 );
    ?>
    <input type="number" id="ddo_feedback_retention_days" name="ddo_feedback_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" min="7" max="3650" step="1" class="small-text" />
    <p class="description"><?php esc_html_e( 'Aantal dagen dat feedbackrecords worden bewaard voordat dagelijkse cleanup oude data verwijdert. Kies een waarde tussen 7 en 3650 dagen.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render GA4 Property ID veld. */
function ddo_render_ga4_property_id_field() {
    $property_id = sanitize_text_field( (string) get_option( 'ddo_ga4_property_id', '' ) );
    ?>
    <input type="text" id="ddo_ga4_property_id" name="ddo_ga4_property_id" value="<?php echo esc_attr( $property_id ); ?>" class="regular-text" inputmode="numeric" pattern="[0-9]{4,20}" placeholder="123456789" />
    <p class="description"><?php esc_html_e( 'Numerieke GA4 property ID (verplicht). Foutcodes: ddo_ga4_property_id_missing / ddo_ga4_property_id_invalid.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render GA4 secret/config veld. */
function ddo_render_ga4_service_account_json_field() {
    ddo_render_secret_input_field( 'ddo_ga4_service_account_json', __( 'Plak service-account JSON of tokenreferentie', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen. Vereist in service_account_json-modus. Foutcodes: ddo_ga4_service_account_json_missing / ddo_ga4_service_account_json_invalid.', 'data-driven-optimizer' ), true );
}

/** Render GA4 bearer token veld. */
function ddo_render_ga4_bearer_token_field() {
    ddo_render_secret_input_field( 'ddo_ga4_bearer_token', __( 'Voer GA4 bearer token in', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen. Vereist in bearer_token-modus. Foutcodes: ddo_ga4_bearer_token_missing / ddo_ga4_bearer_token_invalid.', 'data-driven-optimizer' ) );
}

/** Render GA4 authenticatiemodus veld. */
function ddo_render_ga4_auth_mode_field() {
    $auth_mode = ddo_sanitize_ga4_auth_mode( get_option( 'ddo_ga4_auth_mode', 'bearer_token' ) );
    ?>
    <select id="ddo_ga4_auth_mode" name="ddo_ga4_auth_mode">
        <option value="service_account_json" <?php selected( $auth_mode, 'service_account_json' ); ?>><?php esc_html_e( 'Service account JSON (OAuth2)', 'data-driven-optimizer' ); ?></option>
        <option value="bearer_token" <?php selected( $auth_mode, 'bearer_token' ); ?>><?php esc_html_e( 'Bearer token', 'data-driven-optimizer' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( 'Kies expliciet één authenticatiemodus voor GA4. Validatie gebeurt op het corresponderende secretveld.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render Facebook Ads app ID. */
function ddo_render_facebook_ads_app_id_field() {
    $app_id = sanitize_text_field( (string) get_option( 'ddo_facebook_ads_app_id', '' ) );
    ?>
    <input type="text" id="ddo_facebook_ads_app_id" name="ddo_facebook_ads_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text" inputmode="numeric" placeholder="123456789012345" />
    <p class="description"><?php esc_html_e( 'Verplicht Facebook app ID (numeriek). Foutcodes: ddo_facebook_ads_app_id_missing / ddo_facebook_ads_app_id_invalid.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render Facebook Ads app secret. */
function ddo_render_facebook_ads_app_secret_field() {
    ddo_render_secret_input_field( 'ddo_facebook_ads_app_secret', __( 'Voer Facebook app secret in', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen. Foutcodes: ddo_facebook_ads_app_secret_missing / ddo_facebook_ads_app_secret_invalid.', 'data-driven-optimizer' ) );
}

/** Render Facebook Ads access token. */
function ddo_render_facebook_ads_access_token_field() {
    ddo_render_secret_input_field( 'ddo_facebook_ads_access_token', __( 'Voer Facebook access token in', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen. Foutcodes: ddo_facebook_ads_access_token_missing / ddo_facebook_ads_access_token_invalid.', 'data-driven-optimizer' ) );
}

/** Render Facebook Ads ad account ID. */
function ddo_render_facebook_ads_ad_account_id_field() {
    $account_id = sanitize_text_field( (string) get_option( 'ddo_facebook_ads_ad_account_id', '' ) );
    ?>
    <input type="text" id="ddo_facebook_ads_ad_account_id" name="ddo_facebook_ads_ad_account_id" value="<?php echo esc_attr( $account_id ); ?>" class="regular-text" placeholder="act_1234567890" />
    <p class="description"><?php esc_html_e( 'Verplicht advertentieaccount-ID (act_123... of alleen cijfers). Foutcodes: ddo_facebook_ads_ad_account_id_missing / ddo_facebook_ads_ad_account_id_invalid.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render Search Console site URL. */
function ddo_render_search_console_site_url_field() {
    $site_url = sanitize_text_field( (string) get_option( 'ddo_search_console_site_url', '' ) );
    ?>
    <input type="url" id="ddo_search_console_site_url" name="ddo_search_console_site_url" value="<?php echo esc_attr( $site_url ); ?>" class="regular-text" placeholder="https://www.example.com" />
    <p class="description"><?php esc_html_e( 'Verplicht geverifieerde Search Console site URL. Foutcodes: ddo_search_console_site_url_missing / ddo_search_console_site_url_invalid.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render Search Console OAuth client ID. */
function ddo_render_search_console_oauth_client_id_field() {
    $client_id = sanitize_text_field( (string) get_option( 'ddo_search_console_oauth_client_id', '' ) );
    ?>
    <input type="text" id="ddo_search_console_oauth_client_id" name="ddo_search_console_oauth_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text code" placeholder="1234567890-abc.apps.googleusercontent.com" />
    <p class="description"><?php esc_html_e( 'Verplicht OAuth client ID voor Search Console. Foutcodes: ddo_search_console_oauth_client_id_missing / ddo_search_console_oauth_client_id_invalid.', 'data-driven-optimizer' ); ?></p>
    <?php
}

/** Render Search Console OAuth client secret. */
function ddo_render_search_console_oauth_client_secret_field() {
    ddo_render_secret_input_field( 'ddo_search_console_oauth_client_secret', __( 'Voer OAuth client secret in', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen. Foutcodes: ddo_search_console_oauth_client_secret_missing / ddo_search_console_oauth_client_secret_invalid.', 'data-driven-optimizer' ) );
}

/** Render Search Console OAuth tokenreferentie. */
function ddo_render_search_console_oauth_reference_field() {
    ddo_render_secret_input_field( 'ddo_search_console_oauth_reference', __( 'Voer OAuth tokenreferentie in', 'data-driven-optimizer' ), __( 'SECRET: versleuteld opgeslagen (token ID/ref, nooit raw token loggen). Foutcodes: ddo_search_console_oauth_reference_missing / ddo_search_console_oauth_reference_invalid.', 'data-driven-optimizer' ) );
}

/**
 * Migreer legacy bronopties naar nieuwe option keys.
 */
function ddo_maybe_migrate_source_settings() {
    $migrated = (int) get_option( 'ddo_source_settings_migrated_v1', 0 );

    if ( $migrated >= 1 ) {
        return;
    }

    $legacy_map = array(
        'ddo_ga4_token'                         => 'ddo_ga4_bearer_token',
        'ddo_ga4_bearer'                        => 'ddo_ga4_bearer_token',
        'ddo_facebook_app_id'                   => 'ddo_facebook_ads_app_id',
        'ddo_facebook_app_secret'               => 'ddo_facebook_ads_app_secret',
        'ddo_facebook_access_token'             => 'ddo_facebook_ads_access_token',
        'ddo_facebook_ad_account_id'            => 'ddo_facebook_ads_ad_account_id',
        'ddo_search_console_property_url'       => 'ddo_search_console_site_url',
        'ddo_search_console_oauth_token_ref'    => 'ddo_search_console_oauth_reference',
        'ddo_search_console_client_id'          => 'ddo_search_console_oauth_client_id',
        'ddo_search_console_client_secret'      => 'ddo_search_console_oauth_client_secret',
    );

    foreach ( $legacy_map as $legacy_key => $target_key ) {
        $legacy_value = get_option( $legacy_key, null );
        if ( null === $legacy_value || '' === trim( (string) $legacy_value ) ) {
            continue;
        }

        $current = get_option( $target_key, '' );
        if ( '' !== trim( (string) $current ) ) {
            continue;
        }

        if ( in_array( $target_key, ddo_get_secret_option_keys(), true ) ) {
            update_option( $target_key, ddo_encrypt_secret( sanitize_text_field( (string) $legacy_value ) ) );
            continue;
        }

        update_option( $target_key, sanitize_text_field( (string) $legacy_value ) );
    }

    $legacy_options = get_option( 'ddo_options', array() );

    if ( is_array( $legacy_options ) ) {
        $legacy_array_map = array(
            'ga4_property_id'                    => 'ddo_ga4_property_id',
            'ga4_auth_mode'                      => 'ddo_ga4_auth_mode',
            'ga4_service_account_json'           => 'ddo_ga4_service_account_json',
            'ga4_bearer_token'                   => 'ddo_ga4_bearer_token',
            'facebook_ads_app_id'                => 'ddo_facebook_ads_app_id',
            'facebook_ads_app_secret'            => 'ddo_facebook_ads_app_secret',
            'facebook_ads_access_token'          => 'ddo_facebook_ads_access_token',
            'facebook_ads_ad_account_id'         => 'ddo_facebook_ads_ad_account_id',
            'search_console_site_url'            => 'ddo_search_console_site_url',
            'search_console_oauth_client_id'     => 'ddo_search_console_oauth_client_id',
            'search_console_oauth_client_secret' => 'ddo_search_console_oauth_client_secret',
            'search_console_oauth_reference'     => 'ddo_search_console_oauth_reference',
        );

        foreach ( $legacy_array_map as $legacy_key => $target_key ) {
            if ( ! array_key_exists( $legacy_key, $legacy_options ) ) {
                continue;
            }

            $current = get_option( $target_key, '' );
            if ( '' !== trim( (string) $current ) ) {
                continue;
            }

            $legacy_value = trim( (string) $legacy_options[ $legacy_key ] );
            if ( '' === $legacy_value ) {
                continue;
            }

            if ( in_array( $target_key, ddo_get_secret_option_keys(), true ) ) {
                update_option( $target_key, ddo_encrypt_secret( sanitize_text_field( $legacy_value ) ) );
            } else {
                update_option( $target_key, sanitize_text_field( $legacy_value ) );
            }
        }
    }

    update_option( 'ddo_source_settings_migrated_v1', 1 );
}
