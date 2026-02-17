<?php

use PHPUnit\Framework\TestCase;

class PageviewsStorageTest extends TestCase {
    protected function setUp(): void {
        global $wpdb;

        $wpdb->queries  = array();
        $wpdb->prepared = array();
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
}
