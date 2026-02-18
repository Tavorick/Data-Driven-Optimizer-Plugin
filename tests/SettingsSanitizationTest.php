<?php

use PHPUnit\Framework\TestCase;

class SettingsSanitizationTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['options'] = array();
        $ddo_test_state['settings_errors'] = array();
    }

    public function test_enabled_sanitization_is_booleanish(): void {
        $this->assertTrue( ddo_sanitize_enabled( '1' ) );
        $this->assertFalse( ddo_sanitize_enabled( '' ) );
        $this->assertFalse( ddo_sanitize_enabled( 0 ) );
    }

    public function test_api_key_empty_keeps_existing_value(): void {
        update_option( 'ddo_api_key_primary', 'existing-encrypted' );

        $result = ddo_sanitize_api_key( '', 'ddo_api_key_primary' );

        $this->assertSame( 'existing-encrypted', $result );
    }

    public function test_api_key_is_sanitized_and_encrypted(): void {
        $result = ddo_sanitize_api_key( "  <b>api-key</b>\n", 'ddo_api_key_primary' );

        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $result );
        $this->assertSame( 'api-key', ddo_decrypt_secret( $result ) );
    }

    public function test_ga4_property_id_sanitization_accepts_digits_and_rejects_other_input(): void {
        $this->assertSame( '123456789', ddo_sanitize_ga4_property_id( '123456789' ) );
        $this->assertSame( '', ddo_sanitize_ga4_property_id( 'abc-123' ) );
    }

    public function test_ga4_service_account_json_sanitization_encrypts_and_reuses_existing_value_when_empty(): void {
        $encrypted = ddo_sanitize_ga4_service_account_json( "  token-reference-123  " );
        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $encrypted );
        $this->assertSame( 'token-reference-123', ddo_decrypt_secret( $encrypted ) );

        update_option( 'ddo_ga4_service_account_json', $encrypted );
        $this->assertSame( $encrypted, ddo_sanitize_ga4_service_account_json( '' ) );
    }


    public function test_ga4_auth_mode_invalid_value_falls_back_and_registers_error(): void {
        $this->assertSame( 'bearer_token', ddo_sanitize_ga4_auth_mode( 'INVALID!' ) );

        global $ddo_test_state;
        $last_error = end( $ddo_test_state['settings_errors'] );

        $this->assertSame( 'ddo_ga4_auth_mode_invalid', $last_error['code'] );
        $this->assertSame( 'ddo_ga4_auth_mode', $last_error['setting'] );
    }


    public function test_source_retention_days_sanitization_keeps_valid_numeric_values_per_source(): void {
        $sanitized = ddo_sanitize_source_retention_days(
            array(
                'ga4'          => '90',
                'facebook_ads' => '5',
                'search-console' => 3651,
                ''             => 40,
            )
        );

        $this->assertSame(
            array(
                'ga4'            => 90,
                'search-console' => 3650,
            ),
            $sanitized
        );
    }

    public function test_facebook_ads_secret_fields_are_encrypted(): void {
        $app_secret = ddo_sanitize_facebook_ads_app_secret( ' app-secret-123 ' );
        $token      = ddo_sanitize_facebook_ads_access_token( ' access-token-xyz ' );

        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $app_secret );
        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $token );
        $this->assertSame( 'app-secret-123', ddo_decrypt_secret( $app_secret ) );
        $this->assertSame( 'access-token-xyz', ddo_decrypt_secret( $token ) );
    }

    public function test_search_console_site_url_is_normalized_and_rejects_invalid_schema(): void {
        $this->assertSame( 'https://example.com', ddo_sanitize_search_console_site_url( 'https://example.com/' ) );
        $this->assertSame( '', ddo_sanitize_search_console_site_url( 'ftp://example.com' ) );
    }

    public function test_redaction_masks_bearer_and_private_keys_in_logs(): void {
        $message  = 'authorization=Bearer abc123xyz private_key=my-secret-key token=super-token';
        $redacted = ddo_redact_sensitive_log_message( $message );

        $this->assertStringNotContainsString( 'abc123xyz', $redacted );
        $this->assertStringNotContainsString( 'my-secret-key', $redacted );
        $this->assertStringNotContainsString( 'super-token', $redacted );
    }


    public function test_search_console_oauth_reference_is_encrypted_and_reused_when_empty(): void {
        $encrypted = ddo_sanitize_search_console_oauth_reference( ' oauth-ref-123 ' );

        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $encrypted );
        $this->assertSame( 'oauth-ref-123', ddo_decrypt_secret( $encrypted ) );

        update_option( 'ddo_search_console_oauth_reference', $encrypted );
        $this->assertSame( $encrypted, ddo_sanitize_search_console_oauth_reference( '' ) );
    }

    public function test_migrate_source_settings_moves_legacy_search_console_token_ref_to_new_key(): void {
        update_option( 'ddo_search_console_oauth_token_ref', 'legacy-token-ref' );

        ddo_maybe_migrate_source_settings();

        $migrated = get_option( 'ddo_search_console_oauth_reference', '' );

        $this->assertStringStartsWith( DDO_ENCRYPTED_SECRET_PREFIX, $migrated );
        $this->assertSame( 'legacy-token-ref', ddo_decrypt_secret( $migrated ) );
    }


}
