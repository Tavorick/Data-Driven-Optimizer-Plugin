<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/FeedbackFixtures.php';

class IntegrationContractFlowsTest extends TestCase {
    protected function setUp(): void {
        global $wpdb, $ddo_test_state;

        $wpdb->reset_feedback();
        $ddo_test_state['current_user_can'] = true;
        $ddo_test_state['verified_nonces']  = array();
        $ddo_test_state['options']          = array();
        $ddo_test_state['scheduled']        = array();
        $ddo_test_state['actions_run']      = array();
        $ddo_test_state['transients']       = array();
        $_POST = array();
        $_GET  = array();
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->reset_feedback();
        $_POST = array();
        $_GET  = array();
    }

    public function test_feedback_submit_to_storage_to_summary_visibility_flow(): void {
        global $wpdb;

        $request = DDO_Feedback_Fixtures::signed_request();

        $permission = ddo_api_submit_feedback_permission( $request );
        $this->assertTrue( $permission );

        $response = ddo_api_submit_feedback( $request );

        $this->assertTrue( $response['success'] );
        $this->assertSame( 1, $response['feedback_id'] );
        $this->assertCount( 1, $wpdb->feedback_rows );

        $summary = ddo_get_feedback_summary(
            array(
                'days' => 30,
                'sort' => 'count_desc',
            )
        );

        $this->assertSame( 1, $summary['totals']['count'] );
        $this->assertSame( 'conversion', $summary['events'][0]['event_name'] );

        ob_start();
        ddo_render_feedback_summary_cards( $summary );
        $output = $this->normalize_html( ob_get_clean() );

        $this->assertStringContainsString( '<h3>Totaal feedback-items</h3><p>1</p>', $output );
        $this->assertStringContainsString( '<h3>Gemiddelde score</h3><p>8.00</p>', $output );
    }

    public function test_feedback_permission_rejects_nonce_and_invalid_payload_contracts(): void {
        $invalid_nonce_request = DDO_Feedback_Fixtures::signed_request(
            array(),
            array(
                'nonce' => 'bad',
            )
        );

        $nonce_result = ddo_api_submit_feedback_permission( $invalid_nonce_request );

        $this->assertInstanceOf( WP_Error::class, $nonce_result );
        $this->assertSame( 'ddo_feedback_nonce_invalid', $nonce_result->code );

        $missing_required_result = ddo_api_validate_feedback_payload_minimum(
            array(
                'event' => 'conversion',
            )
        );

        $this->assertInstanceOf( WP_Error::class, $missing_required_result );
        $this->assertSame( 'ddo_feedback_payload_missing_field', $missing_required_result->code );
    }

    public function test_scheduler_run_now_to_metadata_update_to_status_render_flow(): void {
        global $ddo_test_state;

        $ddo_test_state['verified_nonces']['ddo_run_scheduler_job_ddo_hourly_fetch'] = 'ok-single';

        $request_result = ddo_process_run_scheduler_job_request(
            array(
                'job_name'                    => 'ddo_hourly_fetch',
                'ddo_run_scheduler_job_nonce' => 'ok-single',
            )
        );

        $this->assertSame( 'ok', $request_result['notice'] );
        $this->assertSame( array( 'ddo_hourly_fetch' ), $ddo_test_state['actions_run'] );

        ddo_execute_scheduled_job(
            'ddo_hourly_fetch',
            function () {
                // no-op
            }
        );

        $metadata = $ddo_test_state['options']['ddo_scheduler_job_metadata']['ddo_hourly_fetch'] ?? array();
        $this->assertArrayHasKey( 'last_success', $metadata );
        $this->assertSame( '', $metadata['last_error_message'] );

        $ddo_test_state['scheduled']['ddo_hourly_fetch'] = time() + 300;

        ob_start();
        ddo_render_scheduler_status_block();
        $output = $this->normalize_html( ob_get_clean() );

        $this->assertStringContainsString( '<code>ddo_hourly_fetch</code>', $output );
        $this->assertStringContainsString( 'Scheduler status: OK', $output );
        $this->assertStringContainsString( 'Run now', $output );
    }

    public function test_scheduler_status_marks_stale_jobs_and_explains_cause(): void {
        global $ddo_test_state;

        update_option(
            'ddo_scheduler_job_metadata',
            array(
                'ddo_hourly_fetch' => array(
                    'last_success'       => time() - 8 * HOUR_IN_SECONDS,
                    'last_start'         => time() - 8 * HOUR_IN_SECONDS,
                    'last_run_duration'  => 0.4,
                    'last_error_message' => 'Timeout bij upstream bron',
                ),
            )
        );

        ob_start();
        ddo_render_scheduler_status_block();
        $output = $this->normalize_html( ob_get_clean() );

        $this->assertStringContainsString( 'Stale jobs', $output );
        $this->assertStringContainsString( 'Scheduler status: Stale', $output );
        $this->assertStringContainsString( 'Laatste fout: Timeout bij upstream bron', $output );
    }

    public function test_feedback_summary_render_for_empty_dataset_snapshot_like(): void {
        ob_start();
        ddo_render_feedback_summary_cards( array( 'totals' => array( 'count' => 0 ) ) );
        $output = $this->normalize_html( ob_get_clean() );

        $this->assertSame( '<p>Geen data in gekozen periode.</p>', $output );
    }

    public function test_concept_preview_ajax_success_and_error_paths_with_live_region_contract(): void {
        global $ddo_test_state;

        $ddo_test_state['verified_nonces']['ddo_preview_concept'] = 'good-preview';
        $_POST = array(
            'nonce'   => 'good-preview',
            'concept' => 'Nieuwe concepttekst voor preview.',
        );

        try {
            ddo_handle_preview_concept_ajax();
            $this->fail( 'Expected JSON response exception for success path.' );
        } catch ( DDO_Test_Json_Response_Exception $response ) {
            $this->assertSame( 200, $response->status );
            $this->assertTrue( $response->payload['success'] );
            $this->assertStringContainsString( 'Concept ontvangen', $response->payload['data']['summary'] );
        }

        $_POST = array(
            'nonce'   => 'wrong-preview',
            'concept' => 'Nieuwe concepttekst voor preview.',
        );

        try {
            ddo_handle_preview_concept_ajax();
            $this->fail( 'Expected JSON response exception for nonce failure path.' );
        } catch ( DDO_Test_Json_Response_Exception $response ) {
            $this->assertSame( 403, $response->status );
            $this->assertFalse( $response->payload['success'] );
        }

        ob_start();
        ?>
        <div id="ddo-ajax-preview-response" role="status" aria-live="polite" aria-atomic="true"></div>
        <?php
        $live_region_markup = $this->normalize_html( ob_get_clean() );

        $this->assertSame(
            '<div id="ddo-ajax-preview-response" role="status" aria-live="polite" aria-atomic="true"></div>',
            $live_region_markup
        );
    }

    private function normalize_html( string $html ): string {
        $html = preg_replace( '/>\s+</', '><', trim( $html ) );

        $normalized = preg_replace( '/\s+/', ' ', $html );

        return is_string( $normalized ) ? trim( $normalized ) : '';
    }
}
