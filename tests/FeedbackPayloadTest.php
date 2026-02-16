<?php

use PHPUnit\Framework\TestCase;

class FeedbackPayloadTest extends TestCase {
    protected function setUp(): void {
        global $wpdb;
        $wpdb->reset_feedback();
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->reset_feedback();
    }

    public function test_prepare_feedback_payload_sanitizes_and_clamps_score(): void {
        $payload = ddo_prepare_feedback_payload(
            array(
                'event'       => 'CTA_Click!',
                'score'       => 999,
                'client_id'   => 'client-123',
                'campaign_id' => '',
                'ad_id'       => '',
            )
        );

        $this->assertSame( 'cta_click', $payload['event'] );
        $this->assertSame( 10, $payload['score'] );
        $this->assertNotSame( 'client-123', $payload['clientHash'] );
        $this->assertSame( 'general', $payload['campaignId'] );
        $this->assertSame( 'general', $payload['adId'] );
    }

    public function test_ddo_feedback_test_data_isolation_is_explicit(): void {
        global $wpdb;

        ddo_store_feedback_payload(
            array(
                'event'       => 'purchase',
                'score'       => 5,
                'client_id'   => 'alpha',
                'campaign_id' => 'c1',
                'ad_id'       => 'a1',
            )
        );

        $this->assertCount( 1, $wpdb->feedback_rows );

        $wpdb->reset_feedback();
        $this->assertCount( 0, $wpdb->feedback_rows );
    }
}
