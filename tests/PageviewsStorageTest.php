<?php

use PHPUnit\Framework\TestCase;

class PageviewsStorageTest extends TestCase {
    protected function setUp(): void {
        global $wpdb;

        $wpdb->queries        = array();
        $wpdb->prepared       = array();
        $wpdb->pageviews_rows = array();
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
