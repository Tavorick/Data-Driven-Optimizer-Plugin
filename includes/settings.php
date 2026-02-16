<?php
/**
 * Instellingenregistratie voor Data Driven Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registreer plugininstellingen.
 */
function ddo_register_settings() {
    register_setting(
        'ddo_settings_group',
        'ddo_options',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'ddo_sanitize_options',
            'default'           => array(
                'enabled' => true,
            ),
        )
    );
}

/**
 * Sanitize callback voor pluginopties.
 *
 * @param array $options Ruwe opties.
 * @return array
 */
function ddo_sanitize_options( $options ) {
    return array(
        'enabled' => ! empty( $options['enabled'] ),
    );
}
