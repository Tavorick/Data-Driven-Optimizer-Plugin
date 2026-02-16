<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
if ( ! defined( 'OPENSSL_RAW_DATA' ) ) {
    define( 'OPENSSL_RAW_DATA', 1 );
}

global $ddo_test_state;
$ddo_test_state = array(
    'options'           => array(),
    'registered_routes' => array(),
    'current_user_can'  => false,
    'scheduled'         => array(),
    'scheduled_calls'   => array(),
    'cleared_hooks'     => array(),
    'verified_nonces'   => array(),
    'actions_run'       => array(),
);

class WP_Error {
    public $code;
    public $message;
    public $data;

    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
}

class WP_REST_Request {
    private $params;

    public function __construct( $params = array() ) {
        $this->params = $params;
    }

    public function get_params() {
        return $this->params;
    }
}

function __( $text, $domain = null ) {
    return (string) $text;
}

function add_action() {}
function add_filter() {}
function register_setting() {}
function add_settings_section() {}
function add_settings_field() {}
function checked( $checked, $current = true, $display = true ) {
    $result = ( (string) $checked === (string) $current ) ? 'checked="checked"' : '';

    if ( $display ) {
        echo $result;
    }

    return $result;
}

function esc_html_e( $text, $domain = null ) {
    echo (string) $text;
}
function esc_html__( $text, $domain = null ) { return (string) $text; }
function esc_attr( $value ) { return $value; }
function esc_attr_e( $text, $domain = null ) {
    echo (string) $text;
}
function esc_html( $text ) { return (string) $text; }
function number_format_i18n( $number, $decimals = 0 ) {
    return number_format( (float) $number, (int) $decimals, '.', ',' );
}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) {
    $value = strtolower( (string) $value );
    return preg_replace( '/[^a-z0-9_-]/', '', $value );
}

function wp_unslash( $value ) {
    return $value;
}

function wp_verify_nonce( $nonce, $action ) {
    global $ddo_test_state;

    return isset( $ddo_test_state['verified_nonces'][ $action ] )
        && $ddo_test_state['verified_nonces'][ $action ] === $nonce;
}

function do_action( $hook ) {
    global $ddo_test_state;
    $ddo_test_state['actions_run'][] = $hook;
}
function wp_salt( $scheme = '' ) { return 'salt-' . $scheme; }
function wp_hash( $value ) { return hash( 'sha256', (string) $value ); }
function rest_ensure_response( $value ) { return $value; }

function get_option( $name, $default = false ) {
    global $ddo_test_state;
    return array_key_exists( $name, $ddo_test_state['options'] ) ? $ddo_test_state['options'][ $name ] : $default;
}

function update_option( $name, $value ) {
    global $ddo_test_state;
    $ddo_test_state['options'][ $name ] = $value;
    return true;
}

function register_rest_route( $namespace, $route, $args ) {
    global $ddo_test_state;
    $ddo_test_state['registered_routes'][] = array(
        'namespace' => $namespace,
        'route'     => $route,
        'args'      => $args,
    );
}

function current_user_can() {
    global $ddo_test_state;
    return (bool) $ddo_test_state['current_user_can'];
}

function wp_next_scheduled( $hook ) {
    global $ddo_test_state;
    return $ddo_test_state['scheduled'][ $hook ] ?? false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
    global $ddo_test_state;
    $ddo_test_state['scheduled'][ $hook ]       = $timestamp;
    $ddo_test_state['scheduled_calls'][ $hook ] = compact( 'timestamp', 'recurrence', 'hook' );
    return true;
}

function wp_clear_scheduled_hook( $hook ) {
    global $ddo_test_state;
    $ddo_test_state['cleared_hooks'][] = $hook;
    unset( $ddo_test_state['scheduled'][ $hook ] );
}

class DDO_Fake_WPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $feedback_rows = array();

    public function insert( $table, $data ) {
        if ( false !== strpos( $table, 'ddo_feedback' ) ) {
            $this->insert_id++;
            $data['id'] = $this->insert_id;
            $this->feedback_rows[] = $data;
            return 1;
        }

        return false;
    }

    public function reset_feedback() {
        $this->insert_id     = 0;
        $this->feedback_rows = array();
    }
}

$wpdb = new DDO_Fake_WPDB();

require_once dirname( __DIR__ ) . '/includes/settings.php';
require_once dirname( __DIR__ ) . '/includes/api-handlers.php';
require_once dirname( __DIR__ ) . '/includes/ml-feedback.php';
require_once dirname( __DIR__ ) . '/includes/cron.php';
require_once dirname( __DIR__ ) . '/includes/admin-dashboard.php';
