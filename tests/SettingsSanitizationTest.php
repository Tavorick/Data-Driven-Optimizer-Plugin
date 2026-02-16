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
}
