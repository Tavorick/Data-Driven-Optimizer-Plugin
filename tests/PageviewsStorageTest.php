<?php

use PHPUnit\Framework\TestCase;

class PageviewsStorageTest extends TestCase {
    protected function setUp(): void {
        global $wpdb, $ddo_test_state;

        $wpdb->queries        = array();
        $wpdb->prepared       = array();
        $wpdb->pageviews_rows = array();
        $ddo_test_state['options']['ddo_source_retention_days'] = array();
    }

    public function test_store_pageviews_rows_batches_and_counts_inserted_rows(): void {
        global $wpdb;

        $result = ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => '2026-03-01',
                    'page_path'   => '/home',
                    'pageviews'   => 10,
                    'source'      => 'ga4',
                ),
                array(
                    'metric_date' => '2026-03-01',
                    'page_path'   => '/pricing',
                    'pageviews'   => 4,
                ),
            )
        );

        $this->assertSame(
            array(
                'inserted' => 2,
                'skipped'  => 0,
                'errors'   => 0,
            ),
            $result
        );

        $this->assertCount( 1, $wpdb->queries );
        $this->assertCount( 1, $wpdb->prepared );
    }

    public function test_store_pageviews_rows_skips_invalid_rows(): void {
        global $wpdb;

        $result = ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => '2026-02-30',
                    'page_path'   => '/invalid-date',
                    'pageviews'   => 8,
                ),
                array(
                    'metric_date' => 'not-a-date',
                    'page_path'   => '/invalid-format',
                    'pageviews'   => 8,
                ),
                array(
                    'metric_date' => '2026-02-28',
                    'page_path'   => '',
                    'pageviews'   => 8,
                ),
                array(
                    'metric_date' => '2026-02-28',
                    'page_path'   => '/ok',
                    'pageviews'   => 8,
                ),
            )
        );

        $this->assertSame( 1, $result['inserted'] );
        $this->assertSame( 3, $result['skipped'] );
        $this->assertSame( 0, $result['errors'] );
        $this->assertCount( 1, $wpdb->queries );
    }


    public function test_store_pageviews_rows_upserts_duplicate_fact_keys(): void {
        global $wpdb;

        ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => '2026-03-02',
                    'page_path'   => '/home',
                    'pageviews'   => 10,
                    'source'      => 'ga4',
                ),
            )
        );

        $result = ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => '2026-03-02',
                    'page_path'   => '/home',
                    'pageviews'   => 25,
                    'source'      => 'ga4',
                ),
            )
        );

        $this->assertSame( 1, $result['inserted'] );
        $this->assertCount( 1, $wpdb->pageviews_rows );
        $this->assertSame( 25, (int) $wpdb->pageviews_rows[0]['pageviews'] );
        $this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE pageviews = VALUES(pageviews)', $wpdb->queries[1] );
    }

    public function test_store_pageviews_rows_deduplicates_duplicate_rows_within_batch(): void {
        global $wpdb;

        $result = ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => '2026-03-03',
                    'page_path'   => '/pricing',
                    'pageviews'   => 4,
                    'source'      => 'ga4',
                ),
                array(
                    'metric_date' => '2026-03-03',
                    'page_path'   => '/pricing',
                    'pageviews'   => 8,
                    'source'      => 'ga4',
                ),
            )
        );

        $this->assertSame( 1, $result['inserted'] );
        $this->assertCount( 1, $wpdb->pageviews_rows );
        $this->assertSame( 8, (int) $wpdb->pageviews_rows[0]['pageviews'] );
    }

    public function test_cleanup_pageviews_data_for_source_respects_retention_days(): void {
        global $wpdb;

        update_option( 'ddo_source_retention_days', array( 'ga4' => 30 ) );

        ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-31 days' ) ),
                    'page_path'   => '/legacy',
                    'pageviews'   => 5,
                    'source'      => 'ga4',
                ),
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-5 days' ) ),
                    'page_path'   => '/recent',
                    'pageviews'   => 9,
                    'source'      => 'ga4',
                ),
            )
        );

        $deleted = ddo_cleanup_pageviews_data_for_source( 'ga4' );

        $this->assertSame( 1, $deleted );
        $this->assertCount( 1, $wpdb->pageviews_rows );
        $this->assertSame( '/recent', $wpdb->pageviews_rows[0]['page_path'] );
    }

    public function test_get_pageviews_summary_aggregates_total_and_top_paths(): void {
        ddo_store_pageviews_rows(
            array(
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
                    'page_path'   => '/home',
                    'pageviews'   => 25,
                ),
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-1 days' ) ),
                    'page_path'   => '/pricing',
                    'pageviews'   => 11,
                ),
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-1 days' ) ),
                    'page_path'   => '/home',
                    'pageviews'   => 5,
                ),
                array(
                    'metric_date' => gmdate( 'Y-m-d', strtotime( '-20 days' ) ),
                    'page_path'   => '/legacy',
                    'pageviews'   => 50,
                ),
            )
        );

        $summary = ddo_get_pageviews_summary( 7 );

        $this->assertSame( 7, $summary['days'] );
        $this->assertSame( 41, $summary['totalPageviews'] );
        $this->assertSame( '/home', $summary['topPages'][0]['page_path'] );
        $this->assertSame( 30, (int) $summary['topPages'][0]['total_pageviews'] );
        $this->assertSame( '/pricing', $summary['topPages'][1]['page_path'] );
    }

}
