<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
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
function wp_json_encode( $value ) { return json_encode( $value ); }
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
    public $queries = array();
    public $prepared = array();
    public $select_queries = array();

    public function insert( $table, $data ) {
        if ( false !== strpos( $table, 'ddo_feedback' ) ) {
            $this->insert_id++;
            $data['id'] = $this->insert_id;
            $this->feedback_rows[] = $data;
            return 1;
        }

        return false;
    }


    public function query( $sql ) {
        $this->queries[] = $sql;
        return 1;
    }

    public function prepare( $query, ...$args ) {
        if ( 1 === count( $args ) && is_array( $args[0] ) ) {
            $args = $args[0];
        }

        $prepared = $query;

        foreach ( $args as $arg ) {
            $quoted   = "'" . addslashes( (string) $arg ) . "'";
            $prepared = preg_replace( '/%s/', $quoted, $prepared, 1 );
        }

        $this->prepared[] = array(
            'query'    => $query,
            'args'     => $args,
            'prepared' => $prepared,
        );

        return $prepared;
    }

    public function get_row( $query, $output = ARRAY_A ) {
        $this->select_queries[] = $query;
        $rows                   = $this->filter_feedback_rows_from_query( $query );

        $scores   = array();
        $unscored = 0;

        foreach ( $rows as $row ) {
            $has_score = isset( $row['is_scored'] ) ? 1 === (int) $row['is_scored'] : isset( $row['score'] ) && null !== $row['score'];

            if ( ! $has_score ) {
                $unscored++;
                continue;
            }

            $scores[] = (float) $row['score'];
        }

        return array(
            'total_items'     => count( $rows ),
            'average_score'   => ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ), 2 ) : null,
            'highest_score'   => ! empty( $scores ) ? max( $scores ) : null,
            'lowest_score'    => ! empty( $scores ) ? min( $scores ) : null,
            'unscored_items'  => $unscored,
        );
    }

    public function get_results( $query, $output = ARRAY_A ) {
        $this->select_queries[] = $query;

        if ( false !== strpos( $query, 'GROUP BY event_name' ) ) {
            return $this->build_event_results_from_query( $query );
        }

        return $this->build_recent_results_from_query( $query );
    }

    private function build_event_results_from_query( $query ) {
        $rows   = $this->filter_feedback_rows_from_query( $query );
        $events = array();

        foreach ( $rows as $row ) {
            if ( empty( $row['event_name'] ) ) {
                continue;
            }

            $event_name = (string) $row['event_name'];
            if ( ! isset( $events[ $event_name ] ) ) {
                $events[ $event_name ] = array(
                    'event_name'    => $event_name,
                    'total_items'   => 0,
                    'score_sum'     => 0,
                    'scored_items'  => 0,
                    'average_score' => 0,
                );
            }

            $events[ $event_name ]['total_items']++;

            $has_score = isset( $row['is_scored'] ) ? 1 === (int) $row['is_scored'] : isset( $row['score'] ) && null !== $row['score'];
            if ( $has_score ) {
                $events[ $event_name ]['score_sum']    += (float) $row['score'];
                $events[ $event_name ]['scored_items'] += 1;
            }
        }

        foreach ( $events as &$event ) {
            $event['average_score'] = $event['scored_items'] > 0 ? round( $event['score_sum'] / $event['scored_items'], 2 ) : 0;
            unset( $event['score_sum'], $event['scored_items'] );
        }
        unset( $event );

        $events = array_values( $events );

        if ( false !== strpos( $query, 'ORDER BY average_score DESC, total_items DESC' ) ) {
            usort(
                $events,
                function ( $left, $right ) {
                    $score_compare = (float) $right['average_score'] <=> (float) $left['average_score'];
                    if ( 0 !== $score_compare ) {
                        return $score_compare;
                    }

                    return (int) $right['total_items'] <=> (int) $left['total_items'];
                }
            );
        } else {
            usort(
                $events,
                function ( $left, $right ) {
                    return (int) $right['total_items'] <=> (int) $left['total_items'];
                }
            );
        }

        return array_slice( $events, 0, 5 );
    }

    private function build_recent_results_from_query( $query ) {
        $rows = $this->filter_feedback_rows_from_query( $query );

        usort(
            $rows,
            function ( $left, $right ) {
                return (int) $right['id'] <=> (int) $left['id'];
            }
        );

        return array_slice( $rows, 0, 10 );
    }

    private function filter_feedback_rows_from_query( $query ) {
        $cutoff = null;
        if ( preg_match( "/feedback_date\s*>=\s*'([^']+)'/", $query, $matches ) ) {
            $cutoff = $matches[1];
        }

        return array_values(
            array_filter(
                $this->feedback_rows,
                function ( $row ) use ( $cutoff ) {
                    if ( null === $cutoff ) {
                        return true;
                    }

                    return isset( $row['feedback_date'] ) && $row['feedback_date'] >= $cutoff;
                }
            )
        );
    }

    public function reset_feedback() {
        $this->insert_id     = 0;
        $this->feedback_rows = array();
        $this->queries       = array();
        $this->prepared      = array();
        $this->select_queries = array();
    }
}

$wpdb = new DDO_Fake_WPDB();

require_once dirname( __DIR__ ) . '/includes/settings.php';
require_once dirname( __DIR__ ) . '/includes/logger.php';
require_once dirname( __DIR__ ) . '/includes/api-handlers.php';
require_once dirname( __DIR__ ) . '/includes/ml-feedback.php';
require_once dirname( __DIR__ ) . '/includes/code-introspect.php';
require_once dirname( __DIR__ ) . '/includes/cron.php';
require_once dirname( __DIR__ ) . '/includes/admin-dashboard.php';

require_once dirname( __DIR__ ) . '/includes/db-schema.php';
