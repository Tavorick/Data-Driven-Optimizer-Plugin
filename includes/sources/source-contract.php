<?php
/**
 * Shared data-source contract and helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contract for external data sources.
 */
interface DDO_Data_Source_Interface {
    /**
     * Validate source configuration.
     *
     * @return true|WP_Error
     */
    public function validate_config();

    /**
     * Fetch source data for a date-range.
     *
     * @param array $date_range Date range with start_date/end_date keys.
     * @return array|WP_Error
     */
    public function fetch( $date_range );

    /**
     * Normalize source payload to canonical rows.
     *
     * @param mixed $raw_payload Raw source payload.
     * @return array|WP_Error
     */
    public function normalize( $raw_payload );

    /**
     * Persist normalized rows.
     *
     * @param array $normalized_rows Canonical rows.
     * @return array|WP_Error
     */
    public function store( $normalized_rows );
}

/**
 * Build a standardized source result payload.
 *
 * @param string $source Source key.
 * @return array
 */
function ddo_get_standard_source_result( $source ) {
    return array(
        'result_count' => 0,
        'errors_count' => 0,
        'error_code'   => '',
        'duration_ms'  => 0,
        'fetch_attempts' => 1,
        'source'       => sanitize_key( (string) $source ),
    );
}

/**
 * Normalize source result payload according to shared contract.
 *
 * @param string $source Source key.
 * @param array  $result Raw source result.
 * @return array
 */
function ddo_normalize_source_result( $source, $result ) {
    $normalized = ddo_get_standard_source_result( $source );
    $result     = is_array( $result ) ? $result : array();

    if ( isset( $result['result_count'] ) ) {
        $normalized['result_count'] = max( 0, (int) $result['result_count'] );
    } elseif ( isset( $result['processed_count'] ) ) {
        $normalized['result_count'] = max( 0, (int) $result['processed_count'] );
    }

    if ( isset( $result['errors_count'] ) ) {
        $normalized['errors_count'] = max( 0, (int) $result['errors_count'] );
    }

    if ( isset( $result['error_code'] ) ) {
        $normalized['error_code'] = sanitize_key( (string) $result['error_code'] );
    }

    if ( isset( $result['duration_ms'] ) ) {
        $normalized['duration_ms'] = max( 0, (int) $result['duration_ms'] );
    }

    if ( isset( $result['fetch_attempts'] ) ) {
        $normalized['fetch_attempts'] = max( 1, (int) $result['fetch_attempts'] );
    }

    return $normalized;
}
