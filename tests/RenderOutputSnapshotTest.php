<?php

use PHPUnit\Framework\TestCase;

class RenderOutputSnapshotTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['options'] = array();
        $ddo_test_state['scheduled'] = array();
        $_GET = array();
    }

    public function test_render_enabled_field_matches_snapshot_like_output(): void {
        update_option( 'ddo_enabled', true );

        ob_start();
        ddo_render_enabled_field();
        $output = $this->normalizeHtml( ob_get_clean() );

        $this->assertStringContainsString( '<label for="ddo_enabled">', $output );
        $this->assertStringContainsString( 'type="checkbox" id="ddo_enabled" name="ddo_enabled" value="1" checked="checked"', $output );
        $this->assertStringContainsString( 'Schakel Data Driven Optimizer in', $output );
        $this->assertStringContainsString( '</label>', $output );
    }

    public function test_render_api_key_primary_field_reflects_masked_state(): void {
        update_option( 'ddo_api_key_primary', ddo_encrypt_secret( 'secret-value' ) );

        ob_start();
        ddo_render_api_key_primary_field();
        $output = $this->normalizeHtml( ob_get_clean() );

        $this->assertStringContainsString( 'id="ddo_api_key_primary"', $output );
        $this->assertStringContainsString( 'type="password"', $output );
        $this->assertStringContainsString( 'placeholder="•••••••• (ongewijzigd)"', $output );
        $this->assertStringContainsString( 'Laat leeg om ongewijzigd te laten.', $output );
    }

    public function test_render_feedback_summary_cards_handles_empty_and_filled_state(): void {
        ob_start();
        ddo_render_feedback_summary_cards( array( 'totals' => array( 'count' => 0 ) ) );
        $emptyOutput = $this->normalizeHtml( ob_get_clean() );

        $this->assertSame( '<p>Geen data in gekozen periode.</p>', $emptyOutput );

        $summary = array(
            'totals' => array(
                'count'        => 12,
                'averageScore' => 7.25,
                'highestScore' => 10,
                'lowestScore'  => 1,
                'unscored'     => 2,
            ),
        );

        ob_start();
        ddo_render_feedback_summary_cards( $summary );
        $filledOutput = $this->normalizeHtml( ob_get_clean() );

        $this->assertStringContainsString( '<h3>Totaal feedback-items</h3><p>12</p>', $filledOutput );
        $this->assertStringContainsString( '<h3>Gemiddelde score</h3><p>7.25</p>', $filledOutput );
        $this->assertStringContainsString( '<h3>Events zonder score</h3><p>2</p>', $filledOutput );
    }

    public function test_render_feedback_events_table_handles_empty_and_filled_state(): void {
        ob_start();
        ddo_render_feedback_events_table( array( 'events' => array() ) );
        $emptyOutput = $this->normalizeHtml( ob_get_clean() );

        $this->assertSame( '<p>Geen data in gekozen periode.</p>', $emptyOutput );

        $summary = array(
            'events' => array(
                array(
                    'event_name'    => 'cta_click',
                    'total_items'   => 4,
                    'average_score' => 8.5,
                ),
            ),
        );

        ob_start();
        ddo_render_feedback_events_table( $summary );
        $filledOutput = $this->normalizeHtml( ob_get_clean() );

        $this->assertStringContainsString( '<th>Event</th>', $filledOutput );
        $this->assertStringContainsString( '<td>cta_click</td>', $filledOutput );
        $this->assertStringContainsString( '<td>4</td>', $filledOutput );
        $this->assertStringContainsString( '<td>8.50</td>', $filledOutput );
    }



    public function test_render_scheduler_action_notice_nonce_invalid_snapshot_like_output(): void {
        $_GET = array(
            'ddo_scheduler_notice' => 'nonce_invalid',
            'ddo_scheduler_job'    => 'ddo_hourly_fetch',
        );

        ob_start();
        ddo_render_scheduler_action_notice();
        $output = $this->normalizeHtml( ob_get_clean() );

        $this->assertSame(
            '<div class="notice notice-error is-dismissible"><p>Nonce-validatie mislukt voor scheduler job: ddo_hourly_fetch.</p></div>',
            $output
        );
    }

    public function test_render_scheduler_status_block_stale_snapshot_like_output(): void {
        update_option(
            'ddo_scheduler_job_metadata',
            array(
                'ddo_hourly_fetch' => array(
                    'last_start'         => time() - 8 * HOUR_IN_SECONDS,
                    'last_success'       => time() - 8 * HOUR_IN_SECONDS,
                    'last_run_duration'  => 0.4,
                    'last_error_message' => 'Timeout bij upstream bron',
                ),
            )
        );

        ob_start();
        ddo_render_scheduler_status_block();
        $output = $this->normalizeHtml( ob_get_clean() );

        $this->assertStringContainsString( '<h4>Stale jobs</h4>', $output );
        $this->assertStringContainsString( '<code>ddo_hourly_fetch</code>', $output );
        $this->assertStringContainsString( 'Scheduler status: Stale', $output );
        $this->assertStringContainsString( 'Laatste fout: Timeout bij upstream bron', $output );
    }

    public function test_format_scheduler_duration_seconds_uses_seconds_label(): void {
        $this->assertSame( '0 sec', ddo_format_scheduler_duration_seconds( 0 ) );
        $this->assertSame( '12 sec', ddo_format_scheduler_duration_seconds( 12 ) );
        $this->assertSame( '1,234 sec', ddo_format_scheduler_duration_seconds( 1234 ) );
    }


    private function normalizeHtml( string $html ): string {
        $html = preg_replace( '/>\s+</', '><', trim( $html ) );

        $normalized = preg_replace( '/\s+/', ' ', $html );

        return is_string( $normalized ) ? trim( $normalized ) : '';
    }
}
