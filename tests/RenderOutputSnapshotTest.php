<?php

use PHPUnit\Framework\TestCase;

class RenderOutputSnapshotTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['options'] = array();
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

    private function normalizeHtml( string $html ): string {
        $html = preg_replace( '/>\s+</', '><', trim( $html ) );

        $normalized = preg_replace( '/\s+/', ' ', $html );

        return is_string( $normalized ) ? trim( $normalized ) : '';
    }
}
