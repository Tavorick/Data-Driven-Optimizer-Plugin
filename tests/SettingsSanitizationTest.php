<?php

use PHPUnit\Framework\TestCase;

class SettingsSanitizationTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['options'] = array();
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

}
