<?php

use PHPUnit\Framework\TestCase;

class RestRoutesTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['registered_routes'] = array();
        $ddo_test_state['current_user_can']  = false;
        $ddo_test_state['transients']        = array();
    }

    public function test_registers_expected_routes_with_permission_callback_per_route(): void {
        global $ddo_test_state;

        ddo_register_rest_routes();

        $routes = $ddo_test_state['registered_routes'];
        $this->assertCount( 3, $routes );

        $callbacks_by_route = array();
        foreach ( $routes as $route ) {
            $this->assertSame( 'ddo/v1', $route['namespace'] );
            $callbacks_by_route[ $route['route'] ] = $route['args']['permission_callback'];
        }

        $this->assertSame( 'ddo_api_manage_options_permission', $callbacks_by_route['/status'] );
        $this->assertSame( 'ddo_api_submit_feedback_permission', $callbacks_by_route['/feedback'] );
        $this->assertSame( 'ddo_api_manage_options_permission', $callbacks_by_route['/feedback/summary'] );
    }

    public function test_manage_options_permission_callback_checks_capability(): void {
        global $ddo_test_state;

        $ddo_test_state['current_user_can'] = true;
        $this->assertTrue( ddo_api_manage_options_permission() );

        $ddo_test_state['current_user_can'] = false;
        $this->assertFalse( ddo_api_manage_options_permission() );
    }

    public function test_submit_feedback_permission_allows_valid_signed_payload_without_manage_options(): void {
        global $ddo_test_state;

        $ddo_test_state['current_user_can'] = false;

        $payload = array(
            'event'       => 'conversion',
            'score'       => 9,
            'client_id'   => 'client-123',
            'campaign_id' => 'campaign-123',
            'ad_id'       => 'ad-123',
        );
        $signed_payload = array(
            'event'       => ddo_api_sanitize_feedback_event( $payload['event'] ),
            'score'       => (string) ddo_api_sanitize_feedback_score( $payload['score'] ),
            'client_id'   => ddo_api_sanitize_feedback_identifier( $payload['client_id'] ),
            'campaign_id' => ddo_api_sanitize_feedback_identifier( $payload['campaign_id'] ),
            'ad_id'       => ddo_api_sanitize_feedback_identifier( $payload['ad_id'] ),
        );
        $nonce = 'feedbacknonce123456';
        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $nonce . '|' . $timestamp . '|' . wp_json_encode( $signed_payload ),
            ddo_api_get_feedback_signature_secret()
        );

        $request = new WP_REST_Request(
            array_merge(
                $payload,
                array(
                    'nonce'     => $nonce,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                )
            )
        );

        $this->assertTrue( ddo_api_submit_feedback_permission( $request ) );
    }
}
