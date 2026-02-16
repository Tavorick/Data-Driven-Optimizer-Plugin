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
                    'records_fetched' => 17,
                );
            }
        );

        ddo_run_hourly_fetch_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 17, $metadata['ddo_hourly_fetch']['last_result']['records_processed'] );
        $this->assertSame( '', $metadata['ddo_hourly_fetch']['last_result']['error_code'] );
        $this->assertArrayHasKey( 'duration_ms', $metadata['ddo_hourly_fetch']['last_result'] );
        $this->assertArrayHasKey( 'last_success', $metadata['ddo_hourly_fetch'] );
    }


    public function test_hourly_fetch_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_api_data_fetch_service(
            function () {
                return array(
                    'records_fetched' => 9,
                    'error_code'      => 'API_RATE_LIMIT',
                );
            }
        );

        ddo_run_hourly_fetch_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 'API data fetch failed.', $metadata['ddo_hourly_fetch']['last_error_message'] );
        $this->assertArrayNotHasKey( 'last_result', $metadata['ddo_hourly_fetch'] );
    }

    public function test_weekly_retrain_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_ml_feedback_retrain_service(
            function () {
                return array(
                    'trained_samples' => 33,
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
                    'files_scanned' => 41,
                );
            }
        );

        ddo_run_daily_introspect_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 41, $metadata['ddo_daily_introspect']['last_result']['records_processed'] );
        $this->assertSame( '', $metadata['ddo_daily_introspect']['last_result']['error_code'] );
        $this->assertArrayHasKey( 'duration_ms', $metadata['ddo_daily_introspect']['last_result'] );
        $this->assertArrayHasKey( 'last_success', $metadata['ddo_daily_introspect'] );
    }


    public function test_daily_introspect_job_error_is_handled_by_scheduler_executor(): void {
        ddo_set_code_introspection_service(
            function () {
                return array(
                    'files_scanned' => 18,
                    'error_code'    => 'ANALYSIS_PARSER_FAILURE',
                );
            }
        );

        ddo_run_daily_introspect_job();

        $metadata = ddo_get_scheduler_job_metadata();

        $this->assertSame( 'Code introspection failed.', $metadata['ddo_daily_introspect']['last_error_message'] );
        $this->assertArrayNotHasKey( 'last_result', $metadata['ddo_daily_introspect'] );
    }

    public function test_placeholder_actions_are_not_present_anymore(): void {
        $api_handlers = file_get_contents( dirname( __DIR__ ) . '/includes/api-handlers.php' );
        $ml_feedback  = file_get_contents( dirname( __DIR__ ) . '/includes/ml-feedback.php' );
        $introspect   = file_get_contents( dirname( __DIR__ ) . '/includes/code-introspect.php' );

        $this->assertStringNotContainsString( "do_action( 'ddo_api_data_fetch' )", $api_handlers );
        $this->assertStringNotContainsString( "do_action( 'ddo_ml_feedback_retrain' )", $ml_feedback );
        $this->assertStringNotContainsString( "do_action( 'ddo_code_introspection' )", $introspect );
    }
}
