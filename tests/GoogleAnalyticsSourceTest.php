<?php

use PHPUnit\Framework\TestCase;

class GoogleAnalyticsSourceTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;

        $ddo_test_state['options']           = array();
        $ddo_test_state['transients']        = array();
        $ddo_test_state['remote_post_queue'] = array();
        $ddo_test_state['remote_post_calls'] = array();

        update_option( 'ddo_api_key_primary', 'ga-access-token' );
        update_option( 'ddo_ga4_property_id', '123456' );
        update_option( 'ddo_ga4_auth_mode', 'bearer_token' );
    }

    private function getServiceAccountSecretJson(): string {
        return wp_json_encode(
            array(
                'client_email' => 'analytics@example.iam.gserviceaccount.com',
                'private_key'  => "-----BEGIN PRIVATE KEY-----\nMIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJ0mm9OcvoZyWmbC\n9oT9tMfPrhAjt7YfIK96F4Qd9sNQyorW61iV7WFriEh/bio2G1ndz2elt9MKYaWS\nXHKB/uRuNxetV8msmmC7ii8KdMybQxSBTbIVgrYsk8pucFtZd0j6ktVWKmuIvkoz\nK/WsB2H9MPeucBdc2yl1WImdLaL9AgMBAAECgYA8dx59zVGBaX5fC6TOhs+IEeBn\nVVbaPB/XZKKnst+/RtanlQn4i7dKRJWrT1yT4T2k1jN3LcwM53GqwyXO6TWpFGTk\noE3eQ78pd3l1NJf/xzn4r/lfPZ2rvGuMmcOjKv5rsgYkeeaIq73qiYW1wCOAEjG/\nDHPaILI20d3HBw8QAQJBAMnd546sLS/7Godl4sMLuizTv9qipCpY3ecZxrWZTcom\nCQPOBOSLi11NKKfnvJCr4TRmddsUEfeDFmGUZwiTZ0kCQQDHSvbjrjmL6tXkhKgT\nS/OTiKvw4XqEiPAMEAOrB9Co91rC7tG+LcsxOQbUMq/zd3ClMvDzVHtw6gdXIXIg\nEtoVAkAELHVcKs0oX821nPKqS7TGtn4R/Cjew0WbQJouKQRFuLGZBYpuW0A/Zpf/\nmLf6WcNnPPMU235fmrM8wz+6GqoZAkALamXeANrXAuqhnl+qS012g/ulXqUP9nAZ\noMk1AMuZAiI2zEtDY4giF6wmd4jQn2TacaKPraUsgJtPCGFrKOlJAkEApQZLn2Re\ng//gNgQk9vvifnjuR7svLZo88d5UQZlzf2JoWDyTLJA244CPyNda4kUVGvKUTiMO\n/J7U1YdQknL1ZQ==\n-----END PRIVATE KEY-----\n",
                'token_uri'    => 'https://oauth2.googleapis.com/token',
            )
        );
    }

    public function test_map_ga4_response_to_rows_returns_uniform_structure(): void {
        $rows = ddo_map_ga4_pageviews_response_to_rows(
            array(
                'rows' => array(
                    array(
                        'dimensionValues' => array(
                            array( 'value' => '20260109' ),
                            array( 'value' => '/home' ),
                        ),
                        'metricValues'    => array(
                            array( 'value' => '42' ),
                        ),
                    ),
                ),
            )
        );

        $this->assertSame(
            array(
                array(
                    'metric_date' => '2026-01-09',
                    'page_path'   => '/home',
                    'pageviews'   => 42,
                    'source'      => 'ga4',
                ),
            ),
            $rows
        );
    }

    public function test_fetch_google_pageviews_uses_sessions_fallback_when_primary_metric_returns_empty(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'rows' => array() ) ),
            ),
            array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'rows' => array(
                            array(
                                'dimensionValues' => array(
                                    array( 'value' => '20260110' ),
                                    array( 'value' => '/pricing' ),
                                ),
                                'metricValues'    => array(
                                    array( 'value' => '9' ),
                                ),
                            ),
                        ),
                    )
                ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-09', '2026-01-10' );

        $this->assertIsArray( $result );
        $this->assertSame( 1, $result['fetched'] );
        $this->assertSame( '/pricing', $result['rows'][0]['page_path'] );
        $this->assertCount( 2, $ddo_test_state['remote_post_calls'] );
    }

    public function test_fetch_google_pageviews_returns_wp_error_on_auth_failure(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 401 ),
                'body'     => wp_json_encode( array( 'error' => array( 'message' => 'unauthorized' ) ) ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-09', '2026-01-10' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'ddo_ga4_auth_failed', $result->get_error_code() );
    }

    public function test_fetch_google_pageviews_maps_rows_to_internal_structure(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'rows' => array(
                            array(
                                'dimensionValues' => array(
                                    array( 'value' => '20260111' ),
                                    array( 'value' => '/docs' ),
                                ),
                                'metricValues'    => array(
                                    array( 'value' => '14' ),
                                ),
                            ),
                        ),
                    )
                ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-10', '2026-01-11' );

        $this->assertSame( 1, $result['fetched'] );
        $this->assertSame( '2026-01-11', $result['rows'][0]['metric_date'] );
        $this->assertSame( '/docs', $result['rows'][0]['page_path'] );
        $this->assertSame( 14, $result['rows'][0]['pageviews'] );
        $this->assertSame( 'ga4', $result['rows'][0]['source'] );
    }


    public function test_fetch_google_pageviews_classifies_invalid_request_from_google_status(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 400 ),
                'body'     => wp_json_encode(
                    array(
                        'error' => array(
                            'status'  => 'INVALID_ARGUMENT',
                            'message' => 'Invalid date range format',
                        ),
                    )
                ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-09', '2026-01-10' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'ddo_ga4_invalid_request', $result->get_error_code() );
    }

    public function test_fetch_google_pageviews_logs_retry_context_for_quota_errors(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 429 ),
                'body'     => wp_json_encode(
                    array(
                        'error' => array(
                            'status'  => 'RESOURCE_EXHAUSTED',
                            'message' => 'Quota exceeded',
                        ),
                    )
                ),
            ),
            array(
                'response' => array( 'code' => 429 ),
                'body'     => wp_json_encode(
                    array(
                        'error' => array(
                            'status'  => 'RESOURCE_EXHAUSTED',
                            'message' => 'Quota exceeded',
                        ),
                    )
                ),
            ),
            array(
                'response' => array( 'code' => 429 ),
                'body'     => wp_json_encode(
                    array(
                        'error' => array(
                            'status'  => 'RESOURCE_EXHAUSTED',
                            'message' => 'Quota exceeded',
                        ),
                    )
                ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-09', '2026-01-10' );
        $events = ddo_get_recent_scheduler_events( 1 );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'ddo_ga4_quota_exceeded', $result->get_error_code() );
        $this->assertCount( 3, $ddo_test_state['remote_post_calls'] );
        $this->assertSame( 429, $events[0]['context']['response_code'] );
        $this->assertSame( 'resource_exhausted', $events[0]['context']['google_status'] );
        $this->assertSame( 'Quota exceeded', $events[0]['context']['google_message'] );
        $this->assertTrue( $events[0]['context']['retryable'] );
        $this->assertSame( 1, $events[0]['context']['suggested_retry_after'] );
    }


    public function test_fetch_google_pageviews_retries_twice_for_transient_http_errors(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 429 ),
                'body'     => wp_json_encode( array( 'error' => array( 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded' ) ) ),
            ),
            array(
                'response' => array( 'code' => 503 ),
                'body'     => wp_json_encode( array( 'error' => array( 'status' => 'UNAVAILABLE', 'message' => 'Temporary unavailable' ) ) ),
            ),
            array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'rows' => array(
                            array(
                                'dimensionValues' => array(
                                    array( 'value' => '20260111' ),
                                    array( 'value' => '/retry-ok' ),
                                ),
                                'metricValues'    => array(
                                    array( 'value' => '21' ),
                                ),
                            ),
                        ),
                    )
                ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-10', '2026-01-11' );
        $events = ddo_get_recent_scheduler_events( 5 );

        $this->assertIsArray( $result );
        $this->assertSame( 1, $result['fetched'] );
        $this->assertCount( 3, $ddo_test_state['remote_post_calls'] );
        $this->assertSame( 'ga4-retry-scheduled', $events[1]['message'] );
        $this->assertSame( 1, $events[1]['context']['retry_attempt'] );
        $this->assertSame( 1, $events[1]['context']['retry_in'] );
        $this->assertSame( 'ga4-retry-scheduled', $events[0]['message'] );
        $this->assertSame( 2, $events[0]['context']['retry_attempt'] );
        $this->assertSame( 3, $events[0]['context']['retry_in'] );
    }

    public function test_fetch_google_pageviews_stops_after_max_retries_on_5xx(): void {
        global $ddo_test_state;

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array( 'error' => array( 'status' => 'INTERNAL', 'message' => 'Internal failure' ) ) ),
            ),
            array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array( 'error' => array( 'status' => 'INTERNAL', 'message' => 'Internal failure' ) ) ),
            ),
            array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array( 'error' => array( 'status' => 'INTERNAL', 'message' => 'Internal failure' ) ) ),
            ),
        );

        $result = ddo_fetch_google_pageviews( '2026-01-09', '2026-01-10' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'ddo_ga4_upstream_transient', $result->get_error_code() );
        $this->assertCount( 3, $ddo_test_state['remote_post_calls'] );
    }

    public function test_get_ga4_access_token_uses_bearer_mode_without_service_account_fallback(): void {
        update_option( 'ddo_ga4_auth_mode', 'bearer_token' );

        $result = ddo_get_ga4_access_token( $this->getServiceAccountSecretJson(), 'bearer-token-xyz' );

        $this->assertSame( 'bearer-token-xyz', $result );
    }

    public function test_get_ga4_access_token_uses_service_account_mode_without_bearer_fallback(): void {
        update_option( 'ddo_ga4_auth_mode', 'service_account_json' );

        $result = ddo_get_ga4_access_token( 'not-json-at-all', 'bearer-token-xyz' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'ddo_ga4_service_account_json_invalid', $result->get_error_code() );
    }

    public function test_service_account_access_token_is_cached_in_transient(): void {
        global $ddo_test_state;

        update_option( 'ddo_ga4_auth_mode', 'service_account_json' );

        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'access_token' => 'service-token-123',
                        'expires_in'   => 1200,
                    )
                ),
            ),
        );

        $first  = ddo_get_ga4_access_token( $this->getServiceAccountSecretJson(), 'bearer-token-xyz' );
        $second = ddo_get_ga4_access_token( $this->getServiceAccountSecretJson(), 'bearer-token-xyz' );

        $this->assertSame( 'service-token-123', $first );
        $this->assertSame( 'service-token-123', $second );
        $this->assertCount( 1, $ddo_test_state['remote_post_calls'] );
    }

    public function test_service_account_token_failure_logs_auth_mode_token_uri_and_classifier(): void {
        global $ddo_test_state;

        update_option( 'ddo_ga4_auth_mode', 'service_account_json' );
        $ddo_test_state['remote_post_queue'] = array(
            array(
                'response' => array( 'code' => 401 ),
                'body'     => wp_json_encode( array( 'error' => 'invalid_client' ) ),
            ),
        );

        $result = ddo_get_ga4_access_token( $this->getServiceAccountSecretJson(), '' );
        $events = ddo_get_recent_scheduler_events( 1 );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'service_account_json', $events[0]['context']['auth_mode'] );
        $this->assertSame( 'https://oauth2.googleapis.com/token', $events[0]['context']['token_uri'] );
        $this->assertSame( 'auth', $events[0]['context']['classifier'] );
    }

}
