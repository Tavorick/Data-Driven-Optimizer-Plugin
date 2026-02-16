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

    public function test_normalize_feedback_filters_rejects_invalid_values(): void {
        $filters = ddo_normalize_feedback_filters(
            array(
                'days' => 12,
                'sort' => 'random',
            )
        );

        $this->assertSame( 30, $filters['days'] );
        $this->assertSame( 'count_desc', $filters['sort'] );
    }

    public function test_build_feedback_summary_from_rows_applies_range_and_kpis(): void {
        $rows = array(
            array(
                'id'            => 1,
                'event_name'    => 'purchase',
                'score'         => 4,
                'feedback_date' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a1',
            ),
            array(
                'id'            => 2,
                'event_name'    => 'purchase',
                'score'         => '',
                'feedback_date' => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c2',
                'ad_id'         => 'a2',
            ),
            array(
                'id'            => 3,
                'event_name'    => 'click',
                'score'         => 9,
                'feedback_date' => gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c3',
                'ad_id'         => 'a3',
            ),
        );

        $summary = ddo_build_feedback_summary_from_rows(
            $rows,
            array(
                'days' => 7,
                'sort' => 'score_desc',
            )
        );

        $this->assertSame( 2, $summary['totals']['count'] );
        $this->assertSame( 4.0, $summary['totals']['averageScore'] );
        $this->assertSame( 4.0, $summary['totals']['highestScore'] );
        $this->assertSame( 4.0, $summary['totals']['lowestScore'] );
        $this->assertSame( 1, $summary['totals']['unscored'] );
        $this->assertCount( 1, $summary['events'] );
        $this->assertSame( 'purchase', $summary['events'][0]['event_name'] );
    }
}
