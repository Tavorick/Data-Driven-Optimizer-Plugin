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
        $this->assertTrue( $payload['isScored'] );
        $this->assertNotSame( 'client-123', $payload['clientHash'] );
        $this->assertSame( 'general', $payload['campaignId'] );
        $this->assertSame( 'general', $payload['adId'] );
    }

    public function test_prepare_feedback_payload_marks_missing_score_as_unscored(): void {
        $payload = ddo_prepare_feedback_payload(
            array(
                'event'       => 'cta_click',
                'client_id'   => 'client-abc',
                'campaign_id' => 'cmp-1',
                'ad_id'       => 'ad-1',
            )
        );

        $this->assertNull( $payload['score'] );
        $this->assertFalse( $payload['isScored'] );
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

    public function test_store_feedback_payload_persists_unscored_flag(): void {
        global $wpdb;

        ddo_store_feedback_payload(
            array(
                'event'       => 'view',
                'campaign_id' => 'c1',
                'ad_id'       => 'a1',
            )
        );

        $this->assertSame( 0, $wpdb->feedback_rows[0]['is_scored'] );
        $this->assertNull( $wpdb->feedback_rows[0]['score'] );
    }

    public function test_feedback_score_semantics_migration_updates_legacy_zero_rows(): void {
        global $wpdb;

        ddo_migrate_feedback_score_semantics( $wpdb->prefix . 'ddo_feedback' );

        $this->assertNotEmpty( $wpdb->queries );
        $this->assertStringContainsString( 'SET is_scored = 0, score = NULL', $wpdb->queries[0] );
        $this->assertStringContainsString( 'WHERE score = 0', $wpdb->queries[0] );
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
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a1',
            ),
            array(
                'id'            => 2,
                'event_name'    => 'purchase',
                'score'         => 0,
                'is_scored'     => 0,
                'feedback_date' => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c2',
                'ad_id'         => 'a2',
            ),
            array(
                'id'            => 3,
                'event_name'    => 'click',
                'score'         => 9,
                'is_scored'     => 1,
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

    public function test_calculate_feedback_totals_distinguishes_zero_score_from_unscored(): void {
        $rows = array(
            array(
                'id'            => 1,
                'score'         => 0,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d' ),
            ),
            array(
                'id'            => 2,
                'score'         => 0,
                'is_scored'     => 0,
                'feedback_date' => gmdate( 'Y-m-d' ),
            ),
        );

        $totals = ddo_calculate_feedback_totals( $rows );

        $this->assertSame( 2, $totals['count'] );
        $this->assertSame( 0.0, $totals['averageScore'] );
        $this->assertSame( 0.0, $totals['highestScore'] );
        $this->assertSame( 0.0, $totals['lowestScore'] );
        $this->assertSame( 1, $totals['unscored'] );
    }

    public function test_get_feedback_summary_sql_route_applies_days_filter_and_count_sort(): void {
        global $wpdb;

        $wpdb->feedback_rows = array(
            array(
                'id'            => 1,
                'event_name'    => 'purchase',
                'score'         => 8,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a1',
            ),
            array(
                'id'            => 2,
                'event_name'    => 'purchase',
                'score'         => 6,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a2',
            ),
            array(
                'id'            => 3,
                'event_name'    => 'view',
                'score'         => null,
                'is_scored'     => 0,
                'feedback_date' => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c2',
                'ad_id'         => 'a3',
            ),
            array(
                'id'            => 4,
                'event_name'    => 'old',
                'score'         => 10,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'c3',
                'ad_id'         => 'a4',
            ),
        );

        $summary = ddo_get_feedback_summary(
            array(
                'days' => 7,
                'sort' => 'count_desc',
            )
        );

        $this->assertCount( 3, $wpdb->prepared );
        $this->assertStringContainsString( 'WHERE feedback_date >=', $wpdb->prepared[0]['query'] );
        $this->assertStringContainsString( "ORDER BY total_items DESC", $wpdb->select_queries[1] );
        $this->assertSame( 3, $summary['totals']['count'] );
        $this->assertSame( 7.0, $summary['totals']['averageScore'] );
        $this->assertSame( 8.0, $summary['totals']['highestScore'] );
        $this->assertSame( 6.0, $summary['totals']['lowestScore'] );
        $this->assertSame( 1, $summary['totals']['unscored'] );
        $this->assertSame( 'purchase', $summary['events'][0]['event_name'] );
        $this->assertSame( 2, (int) $summary['events'][0]['total_items'] );
        $this->assertSame( 3, $summary['recent'][0]['id'] );
        $this->assertSame( array( 'days' => 7, 'sort' => 'count_desc' ), $summary['filters'] );
    }

    public function test_get_feedback_summary_sql_route_applies_score_sort(): void {
        global $wpdb;

        $wpdb->feedback_rows = array(
            array(
                'id'            => 1,
                'event_name'    => 'bulk',
                'score'         => 6,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d' ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a1',
            ),
            array(
                'id'            => 2,
                'event_name'    => 'bulk',
                'score'         => 6,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d' ),
                'status'        => 'open',
                'campaign_id'   => 'c1',
                'ad_id'         => 'a2',
            ),
            array(
                'id'            => 3,
                'event_name'    => 'premium',
                'score'         => 10,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d' ),
                'status'        => 'open',
                'campaign_id'   => 'c2',
                'ad_id'         => 'a3',
            ),
        );

        $summary = ddo_get_feedback_summary(
            array(
                'days' => 0,
                'sort' => 'score_desc',
            )
        );

        $this->assertCount( 0, $wpdb->prepared );
        $this->assertStringContainsString( 'ORDER BY average_score DESC, total_items DESC', $wpdb->select_queries[1] );
        $this->assertSame( 'premium', $summary['events'][0]['event_name'] );
        $this->assertSame( 10.0, (float) $summary['events'][0]['average_score'] );
        $this->assertSame( 'bulk', $summary['events'][1]['event_name'] );
        $this->assertSame( 6.0, (float) $summary['events'][1]['average_score'] );
        $this->assertSame( array( 'days' => 0, 'sort' => 'score_desc' ), $summary['filters'] );
    }

    public function test_get_feedback_summary_sql_route_empty_dataset_keeps_contract(): void {
        $summary = ddo_get_feedback_summary(
            array(
                'days' => 30,
                'sort' => 'count_desc',
            )
        );

        $this->assertSame(
            array(
                'count'        => 0,
                'averageScore' => 0,
                'highestScore' => 0,
                'lowestScore'  => 0,
                'unscored'     => 0,
            ),
            $summary['totals']
        );
        $this->assertSame( array(), $summary['events'] );
        $this->assertSame( array(), $summary['recent'] );
        $this->assertSame( array( 'days' => 30, 'sort' => 'count_desc' ), $summary['filters'] );
    }

    public function test_get_feedback_summary_sql_route_limits_recent_and_events_results(): void {
        global $wpdb;

        for ( $id = 1; $id <= 12; $id++ ) {
            $wpdb->feedback_rows[] = array(
                'id'            => $id,
                'event_name'    => 'event_' . $id,
                'score'         => $id % 2,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d' ),
                'status'        => 'open',
                'campaign_id'   => 'c' . $id,
                'ad_id'         => 'a' . $id,
            );
        }

        $summary = ddo_get_feedback_summary(
            array(
                'days' => 0,
                'sort' => 'count_desc',
            )
        );

        $this->assertCount( 5, $summary['events'] );
        $this->assertCount( 10, $summary['recent'] );
        $this->assertSame( 12, $summary['totals']['count'] );
        $this->assertSame( 12, $summary['recent'][0]['id'] );
        $this->assertSame( 3, $summary['recent'][9]['id'] );
    }
}
