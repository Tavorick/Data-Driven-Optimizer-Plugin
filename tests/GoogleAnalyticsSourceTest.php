<?php

use PHPUnit\Framework\TestCase;

class GoogleAnalyticsSourceTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;

        $ddo_test_state['options']           = array();
        $ddo_test_state['remote_post_queue'] = array();
        $ddo_test_state['remote_post_calls'] = array();

        update_option( 'ddo_api_key_primary', 'ga-access-token' );
        update_option( 'ddo_ga4_property_id', '123456' );
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

}
