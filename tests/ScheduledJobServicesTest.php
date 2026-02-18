<?php

use PHPUnit\Framework\TestCase;

class ScheduledJobServicesTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;

        $ddo_test_state['options'] = array();

        ddo_set_api_data_fetch_service( null );
        ddo_set_ml_feedback_retrain_service( null );
        ddo_set_code_introspection_service( null );
    }

    public function test_hourly_fetch_job_stores_structured_success_result(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'processed_count' => 17,
                );
            }
        );

        ddo_run_hourly_fetch_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 17, $metadata['ddo_hourly_fetch']['last_result']['processed_count'] );
        $this->assertSame( 0, $metadata['ddo_hourly_fetch']['last_result']['errors_count'] );
        $this->assertSame( '', $metadata['ddo_hourly_fetch']['last_result']['error_code'] );
        $this->assertArrayHasKey( 'duration_ms', $metadata['ddo_hourly_fetch']['last_result'] );
        $this->assertArrayHasKey( 'last_success', $metadata['ddo_hourly_fetch'] );
    }


    public function test_hourly_fetch_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'processed_count' => 9,
                    'errors_count'    => 2,
                    'error_code'      => 'API_RATE_LIMIT',
                );
            }
        );

        ddo_run_hourly_fetch_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 'API data fetch failed.', $metadata['ddo_hourly_fetch']['last_error_message'] );
        $this->assertArrayNotHasKey( 'last_result', $metadata['ddo_hourly_fetch'] );
    }


    public function test_weekly_retrain_job_stores_structured_success_result(): void {
        ddo_set_ml_feedback_retrain_service(
            function () {
                return array(
                    'processed_count' => 33,
                );
            }
        );

        ddo_run_weekly_retrain_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 33, $metadata['ddo_weekly_retrain']['last_result']['processed_count'] );
        $this->assertSame( 0, $metadata['ddo_weekly_retrain']['last_result']['errors_count'] );
        $this->assertSame( '', $metadata['ddo_weekly_retrain']['last_result']['error_code'] );
        $this->assertArrayHasKey( 'duration_ms', $metadata['ddo_weekly_retrain']['last_result'] );
        $this->assertArrayHasKey( 'last_success', $metadata['ddo_weekly_retrain'] );
    }

    public function test_weekly_retrain_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_ml_feedback_retrain_service(
            function () {
                return array(
                    'processed_count' => 33,
                    'errors_count'    => 1,
                    'error_code'      => 'TRAINING_TIMEOUT',
                );
            }
        );

        ddo_run_weekly_retrain_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertArrayHasKey( 'last_error_message', $metadata['ddo_weekly_retrain'] );
        $this->assertSame( 'ML feedback retrain failed.', $metadata['ddo_weekly_retrain']['last_error_message'] );
        $this->assertArrayNotHasKey( 'last_result', $metadata['ddo_weekly_retrain'] );
    }

    public function test_daily_introspect_job_stores_structured_success_result(): void {
        ddo_set_code_introspection_service(
            function () {
                return array(
                    'processed_count' => 41,
                );
            }
        );

        ddo_run_daily_introspect_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 41, $metadata['ddo_daily_introspect']['last_result']['processed_count'] );
        $this->assertSame( 0, $metadata['ddo_daily_introspect']['last_result']['errors_count'] );
        $this->assertSame( '', $metadata['ddo_daily_introspect']['last_result']['error_code'] );
        $this->assertArrayHasKey( 'duration_ms', $metadata['ddo_daily_introspect']['last_result'] );
        $this->assertArrayHasKey( 'last_success', $metadata['ddo_daily_introspect'] );
    }


    public function test_daily_introspect_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_code_introspection_service(
            function () {
                return array(
                    'processed_count' => 18,
                    'errors_count'    => 1,
                    'error_code'      => 'ANALYSIS_PARSER_FAILURE',
                );
            }
        );

        ddo_run_daily_introspect_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 'Code introspection failed.', $metadata['ddo_daily_introspect']['last_error_message'] );
        $this->assertArrayNotHasKey( 'last_result', $metadata['ddo_daily_introspect'] );
    }


    public function test_scheduler_log_event_adds_consistent_context_fields(): void {
        ddo_log_scheduler_event(
            'ddo_hourly_fetch',
            'custom-event',
            'info',
            array(
                'processed_count' => 5,
                'duration_ms'     => 250,
            )
        );

        $events = ddo_get_recent_scheduler_events( 1 );

        $this->assertCount( 1, $events );
        $this->assertSame( 'ddo_hourly_fetch', $events[0]['context']['job'] );
        $this->assertSame( 5, $events[0]['context']['result_count'] );
        $this->assertSame( 0.25, $events[0]['context']['duration'] );
        $this->assertSame( '', $events[0]['context']['error_code'] );
    }



    public function test_scheduler_log_event_redacts_sensitive_context_and_message(): void {
        ddo_log_scheduler_event(
            'ddo_hourly_fetch',
            'upstream failed with api_key=sk-live-123 and signature=abc',
            'error',
            array(
                'api_key_primary' => 'sk-live-123',
                'clientHash'      => 'f7d95f2f4f2f4dbe6d6508e6ccf84387bb5ef1dc9f21f80d697f4f6a33277603',
                'meta'            => array(
                    'token' => 'secret-token',
                ),
            )
        );

        $events = ddo_get_recent_scheduler_events( 1 );

        $this->assertCount( 1, $events );
        $this->assertStringNotContainsString( 'sk-live-123', $events[0]['message'] );
        $this->assertSame( '[redacted]', $events[0]['context']['api_key_primary'] );
        $this->assertSame( '[redacted]', $events[0]['context']['clientHash'] );
        $this->assertSame( '[redacted]', $events[0]['context']['meta']['token'] );
    }


    public function test_scheduler_metadata_keeps_rolling_window_for_last_10_runs(): void {
        for ( $i = 0; $i < 12; $i++ ) {
            ddo_record_scheduler_run_outcome( 'ddo_hourly_fetch', $i % 2 === 0 );
        }

        $metadata = ddo_get_scheduler_job_metadata();
        $history  = $metadata['ddo_hourly_fetch']['run_history'];

        $this->assertCount( 10, $history );
        $this->assertTrue( $history[0]['success'] );
        $this->assertFalse( $history[1]['success'] );
    }

    public function test_scheduler_health_kpis_calculate_statuses_correctly(): void {
        $healthy = ddo_get_scheduler_job_health_kpis(
            array(
                'last_success' => 1735689600,
                'run_history'  => array(
                    array( 'success' => true ),
                    array( 'success' => true ),
                    array( 'success' => true ),
                    array( 'success' => false ),
                    array( 'success' => true ),
                ),
            )
        );

        $degraded = ddo_get_scheduler_job_health_kpis(
            array(
                'run_history' => array(
                    array( 'success' => true ),
                    array( 'success' => false ),
                    array( 'success' => false ),
                    array( 'success' => true ),
                    array( 'success' => false ),
                ),
            )
        );

        $down = ddo_get_scheduler_job_health_kpis(
            array(
                'run_history' => array(
                    array( 'success' => false ),
                    array( 'success' => false ),
                    array( 'success' => false ),
                    array( 'success' => true ),
                    array( 'success' => false ),
                ),
            )
        );

        $this->assertSame( 'healthy', $healthy['status'] );
        $this->assertSame( 80.0, $healthy['success_rate'] );
        $this->assertSame( 1735689600, $healthy['last_success'] );

        $this->assertSame( 'degraded', $degraded['status'] );
        $this->assertSame( 40.0, $degraded['success_rate'] );

        $this->assertSame( 'down', $down['status'] );
        $this->assertSame( 20.0, $down['success_rate'] );
    }

    public function test_placeholder_actions_are_not_present_anymore(): void {
        $api_handlers = file_get_contents( dirname( __DIR__ ) . '/includes/api-handlers.php' );
        $ml_feedback  = file_get_contents( dirname( __DIR__ ) . '/includes/ml-feedback.php' );
        $introspect   = file_get_contents( dirname( __DIR__ ) . '/includes/code-introspect.php' );

        $this->assertStringNotContainsString( "do_action( 'ddo_api_data_fetch' )", $api_handlers );
        $this->assertStringNotContainsString( "do_action( 'ddo_ml_feedback_retrain' )", $ml_feedback );
        $this->assertStringNotContainsString( "do_action( 'ddo_code_introspection' )", $introspect );
    }

    public function test_process_api_data_fetch_returns_structured_result(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'processed_count' => 12,
                    'errors_count'    => 0,
                );
            }
        );

        $result = ddo_process_api_data_fetch();

        $this->assertSame( 12, $result['processed_count'] );
        $this->assertSame( 0, $result['errors_count'] );
        $this->assertArrayHasKey( 'duration_ms', $result );
    }

    public function test_process_api_data_fetch_bubbles_error_exception(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'processed_count' => 12,
                    'errors_count'    => 1,
                );
            }
        );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'API data fetch failed.' );

        ddo_process_api_data_fetch();
    }

    public function test_process_ml_feedback_retrain_returns_structured_result(): void {
        ddo_set_ml_feedback_retrain_service(
            function () {
                return array(
                    'processed_count' => 7,
                    'errors_count'    => 0,
                );
            }
        );

        $result = ddo_process_ml_feedback_retrain();

        $this->assertSame( 7, $result['processed_count'] );
        $this->assertSame( 0, $result['errors_count'] );
        $this->assertArrayHasKey( 'duration_ms', $result );
    }

    public function test_process_ml_feedback_retrain_bubbles_error_exception(): void {
        ddo_set_ml_feedback_retrain_service(
            function () {
                return array(
                    'processed_count' => 7,
                    'errors_count'    => 1,
                );
            }
        );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'ML feedback retrain failed.' );

        ddo_process_ml_feedback_retrain();
    }

    public function test_process_code_introspection_returns_structured_result(): void {
        ddo_set_code_introspection_service(
            function () {
                return array(
                    'processed_count' => 21,
                    'errors_count'    => 0,
                );
            }
        );

        $result = ddo_process_code_introspection();

        $this->assertSame( 21, $result['processed_count'] );
        $this->assertSame( 0, $result['errors_count'] );
        $this->assertArrayHasKey( 'duration_ms', $result );
    }

    public function test_process_code_introspection_bubbles_error_exception(): void {
        ddo_set_code_introspection_service(
            function () {
                return array(
                    'processed_count' => 21,
                    'errors_count'    => 1,
                );
            }
        );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'Code introspection failed.' );

        ddo_process_code_introspection();
    }

    public function test_default_api_data_fetch_service_iterates_registered_sources(): void {
        global $ddo_test_state;

        $ddo_test_state['options']['ddo_ga4_auth_mode'] = 'bearer_token';
        $ddo_test_state['options']['ddo_api_key_primary'] = 'token-123';
        $ddo_test_state['options']['ddo_ga4_property_id'] = '12345678';

        $result = ddo_default_api_data_fetch_service();

        $this->assertArrayHasKey( 'sources', $result );
        $this->assertArrayHasKey( 'ga4', $result['sources'] );
        $this->assertArrayHasKey( 'facebook_ads', $result['sources'] );
        $this->assertArrayHasKey( 'search_console', $result['sources'] );
        $this->assertArrayHasKey( 'result_count', $result['sources']['ga4'] );
        $this->assertArrayHasKey( 'duration_ms', $result['sources']['ga4'] );
    }

    public function test_process_api_data_fetch_normalizes_source_metrics_shape(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'result_count' => 5,
                    'errors_count' => 0,
                    'source'       => 'registry',
                    'sources'      => array(
                        'ga4' => array(
                            'result_count' => 5,
                            'errors_count' => 0,
                            'error_code'   => '',
                            'duration_ms'  => 12,
                            'source'       => 'ga4',
                        ),
                    ),
                );
            }
        );

        $result = ddo_process_api_data_fetch();

        $this->assertSame( 5, $result['result_count'] );
        $this->assertSame( 5, $result['processed_count'] );
        $this->assertSame( 'registry', $result['source'] );
        $this->assertSame( 'ga4', $result['sources']['ga4']['source'] );
        $this->assertArrayHasKey( 'duration_ms', $result['sources']['ga4'] );
    }

}
