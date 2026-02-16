<?php

use PHPUnit\Framework\TestCase;

class CronEventsTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['scheduled']        = array();
        $ddo_test_state['scheduled_calls']  = array();
        $ddo_test_state['cleared_hooks']    = array();
        $ddo_test_state['verified_nonces']  = array();
        $ddo_test_state['actions_run']      = array();
        $ddo_test_state['current_user_can'] = true;
    }

    public function test_register_cron_events_schedules_all_expected_hooks(): void {
        global $ddo_test_state;

        ddo_register_cron_events();

        $this->assertArrayHasKey( 'ddo_hourly_fetch', $ddo_test_state['scheduled_calls'] );
        $this->assertArrayHasKey( 'ddo_weekly_retrain', $ddo_test_state['scheduled_calls'] );
        $this->assertArrayHasKey( 'ddo_daily_introspect', $ddo_test_state['scheduled_calls'] );
        $this->assertArrayHasKey( 'ddo_daily_feedback_cleanup', $ddo_test_state['scheduled_calls'] );
    }

    public function test_clear_cron_events_clears_all_expected_hooks(): void {
        global $ddo_test_state;

        ddo_clear_cron_events();

        $this->assertSame(
            array(
                'ddo_hourly_fetch',
                'ddo_weekly_retrain',
                'ddo_daily_introspect',
                'ddo_daily_feedback_cleanup',
            ),
            $ddo_test_state['cleared_hooks']
        );
    }

    public function test_run_scheduler_job_request_runs_single_job_with_valid_nonce(): void {
        global $ddo_test_state;

        $ddo_test_state['verified_nonces']['ddo_run_scheduler_job_ddo_hourly_fetch'] = 'valid-single';

        $result = ddo_process_run_scheduler_job_request(
            array(
                'job_name'                    => 'ddo_hourly_fetch',
                'ddo_run_scheduler_job_nonce' => 'valid-single',
            )
        );

        $this->assertSame( 'ok', $result['notice'] );
        $this->assertSame( 'ddo_hourly_fetch', $result['job'] );
        $this->assertSame( array( 'ddo_hourly_fetch' ), $ddo_test_state['actions_run'] );
    }

    public function test_run_scheduler_job_request_rejects_invalid_nonce_for_single_job(): void {
        $result = ddo_process_run_scheduler_job_request(
            array(
                'job_name'                    => 'ddo_hourly_fetch',
                'ddo_run_scheduler_job_nonce' => 'invalid',
            )
        );

        $this->assertSame( 'nonce_invalid', $result['notice'] );
        $this->assertSame( 'ddo_hourly_fetch', $result['job'] );
    }

    public function test_run_scheduler_job_request_runs_bulk_safe_jobs_with_valid_nonce(): void {
        global $ddo_test_state;

        $ddo_test_state['verified_nonces']['ddo_run_scheduler_job_all_safe'] = 'valid-bulk';

        $result = ddo_process_run_scheduler_job_request(
            array(
                'job_name'                    => '__all_safe__',
                'ddo_run_scheduler_job_nonce' => 'valid-bulk',
            )
        );

        $this->assertSame( 'ok_bulk', $result['notice'] );
        $this->assertSame(
            array( 'ddo_hourly_fetch', 'ddo_weekly_retrain', 'ddo_daily_introspect' ),
            $result['executedJobs']
        );
        $this->assertSame(
            array( 'ddo_hourly_fetch', 'ddo_weekly_retrain', 'ddo_daily_introspect' ),
            $ddo_test_state['actions_run']
        );
    }

    public function test_execute_scheduled_job_updates_success_metadata_with_duration(): void {
        global $ddo_test_state;

        ddo_execute_scheduled_job(
            'ddo_hourly_fetch',
            function () {
                // no-op
            }
        );

        $metadata = $ddo_test_state['options']['ddo_scheduler_job_metadata']['ddo_hourly_fetch'] ?? array();

        $this->assertArrayHasKey( 'last_start', $metadata );
        $this->assertArrayHasKey( 'last_success', $metadata );
        $this->assertArrayHasKey( 'last_run_duration', $metadata );
        $this->assertSame( '', $metadata['last_error_message'] );
        $this->assertIsFloat( $metadata['last_run_duration'] );
        $this->assertGreaterThanOrEqual( 0.0, $metadata['last_run_duration'] );
    }

    public function test_execute_scheduled_job_updates_error_metadata_with_duration(): void {
        global $ddo_test_state;

        ddo_execute_scheduled_job(
            'ddo_hourly_fetch',
            function () {
                throw new Exception( 'kapot' );
            }
        );

        $metadata = $ddo_test_state['options']['ddo_scheduler_job_metadata']['ddo_hourly_fetch'] ?? array();

        $this->assertArrayHasKey( 'last_start', $metadata );
        $this->assertArrayHasKey( 'last_error_at', $metadata );
        $this->assertArrayHasKey( 'last_run_duration', $metadata );
        $this->assertSame( 'kapot', $metadata['last_error_message'] );
        $this->assertIsFloat( $metadata['last_run_duration'] );
        $this->assertGreaterThanOrEqual( 0.0, $metadata['last_run_duration'] );
    }


    public function test_run_scheduler_job_request_rejects_bulk_when_nonce_invalid(): void {
        $result = ddo_process_run_scheduler_job_request(
            array(
                'job_name'                    => '__all_safe__',
                'ddo_run_scheduler_job_nonce' => 'invalid-bulk',
            )
        );

        $this->assertSame( 'nonce_invalid', $result['notice'] );
        $this->assertSame( '__all_safe__', $result['job'] );
    }
}
