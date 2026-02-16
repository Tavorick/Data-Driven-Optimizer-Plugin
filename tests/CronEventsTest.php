<?php

use PHPUnit\Framework\TestCase;

class CronEventsTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['scheduled']       = array();
        $ddo_test_state['scheduled_calls'] = array();
        $ddo_test_state['cleared_hooks']   = array();
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
}
