<?php
/**
 * Source registry for scheduled fetch orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * No-op source for future integrations.
 */
class DDO_Noop_Source implements DDO_Data_Source_Interface {
    /** @var string */
    private $source_key;

    /**
     * @param string $source_key Source key.
     */
    public function __construct( $source_key ) {
        $this->source_key = sanitize_key( (string) $source_key );
    }

    public function validate_config() {
        return true;
    }

    public function fetch( $date_range ) {
        return array();
    }

    public function normalize( $raw_payload ) {
        return array();
    }

    public function store( $normalized_rows ) {
        return array(
            'inserted' => 0,
            'errors'   => 0,
        );
    }
}

/**
 * Return the registered data sources by key.
 *
 * @return array<string,DDO_Data_Source_Interface>
 */
function ddo_get_data_source_registry() {
    $registry = array(
        'ga4'            => new DDO_GA4_Source(),
        'facebook_ads'   => new DDO_Noop_Source( 'facebook_ads' ),
        'search_console' => new DDO_Noop_Source( 'search_console' ),
    );

    return $registry;
}
