<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
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
    'transients'        => array(),
    'json_response'     => null,
    'remote_post_queue' => array(),
    'remote_post_calls' => array(),
);

class DDO_Test_Json_Response_Exception extends RuntimeException {
    public $payload;
    public $status;

    public function __construct( $payload, $status ) {
        parent::__construct( 'JSON response emitted.' );
        $this->payload = $payload;
        $this->status  = $status;
    }
}

class WP_Error {
    public $code;
    public $message;
    public $data;

    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_message() {
        return $this->message;
    }

    public function get_error_data( $code = '' ) {
        if ( '' !== (string) $code && (string) $code !== (string) $this->code ) {
            return null;
        }

        return $this->data;
    }

    public function add_data( $data, $code = '' ) {
        if ( '' !== (string) $code ) {
            $this->code = (string) $code;
        }

        $this->data = $data;
    }
}

function is_wp_error( $value ) {
    return $value instanceof WP_Error;
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
function selected( $selected, $current = true, $display = true ) {
    $result = ( (string) $selected === (string) $current ) ? 'selected="selected"' : '';

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
function esc_url( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return (string) $value; }
function number_format_i18n( $number, $decimals = 0 ) {
    return number_format( (float) $number, (int) $decimals, '.', ',' );
}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) {
    $value = strtolower( (string) $value );
    return preg_replace( '/[^a-z0-9_-]/', '', $value );
}
function absint( $value ) { return abs( (int) $value ); }

function wp_unslash( $value ) {
    return $value;
}

function wp_verify_nonce( $nonce, $action ) {
    global $ddo_test_state;

    return isset( $ddo_test_state['verified_nonces'][ $action ] )
        && $ddo_test_state['verified_nonces'][ $action ] === $nonce;
}

function check_ajax_referer( $action, $query_arg = false ) {
    $query_arg = $query_arg ? $query_arg : '_ajax_nonce';
    $nonce     = isset( $_POST[ $query_arg ] ) ? sanitize_text_field( wp_unslash( $_POST[ $query_arg ] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, $action ) ) {
        throw new DDO_Test_Json_Response_Exception(
            array(
                'success' => false,
                'data'    => array( 'message' => 'nonce_invalid' ),
            ),
            403
        );
    }

    return true;
}

function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
    $nonce = isset( $_POST[ $query_arg ] ) ? sanitize_text_field( wp_unslash( $_POST[ $query_arg ] ) ) : '';

    return wp_verify_nonce( $nonce, $action );
}

function do_action( $hook ) {
    global $ddo_test_state;
    $ddo_test_state['actions_run'][] = $hook;
}
function wp_salt( $scheme = '' ) { return 'salt-' . $scheme; }
function wp_hash( $value ) { return hash( 'sha256', (string) $value ); }

function wp_remote_post( $url, $args = array() ) {
    global $ddo_test_state;

    $ddo_test_state['remote_post_calls'][] = array(
        'url'  => $url,
        'args' => $args,
    );

    if ( ! empty( $ddo_test_state['remote_post_queue'] ) ) {
        return array_shift( $ddo_test_state['remote_post_queue'] );
    }

    return array(
        'response' => array( 'code' => 200 ),
        'body'     => wp_json_encode( array( 'rows' => array() ) ),
    );
}

function wp_remote_retrieve_response_code( $response ) {
    return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
    return isset( $response['body'] ) ? (string) $response['body'] : '';
}

function wp_json_encode( $value ) { return json_encode( $value ); }
function rest_ensure_response( $value ) { return $value; }
function wp_send_json_success( $data = null, $status_code = 200 ) {
    throw new DDO_Test_Json_Response_Exception(
        array(
            'success' => true,
            'data'    => $data,
        ),
        $status_code
    );
}
function wp_send_json_error( $data = null, $status_code = 400 ) {
    throw new DDO_Test_Json_Response_Exception(
        array(
            'success' => false,
            'data'    => $data,
        ),
        $status_code
    );
}
function wp_die( $message = '' ) {
    throw new RuntimeException( (string) $message );
}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function wp_safe_redirect( $url ) { return $url; }
function get_current_user_id() { return 1; }
function human_time_diff( $from, $to = null ) {
    $to      = null === $to ? time() : (int) $to;
    $seconds = abs( (int) $to - (int) $from );

    if ( $seconds < MINUTE_IN_SECONDS ) {
        return $seconds . ' seconds';
    }

    $minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );
    return $minutes . ' minutes';
}
function wp_date( $format, $timestamp = null ) {
    $timestamp = null === $timestamp ? time() : (int) $timestamp;
    return gmdate( $format, $timestamp );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce' ) {
    printf(
        '<input type="hidden" name="%1$s" value="nonce-for-%2$s" />',
        esc_attr( (string) $name ),
        esc_attr( (string) $action )
    );
}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true ) {
    $button = sprintf(
        '<button class="button %1$s" name="%2$s" type="submit">%3$s</button>',
        esc_attr( (string) $type ),
        esc_attr( (string) $name ),
        esc_html( (string) $text )
    );

    if ( $wrap ) {
        echo '<p class="submit">' . $button . '</p>';
        return;
    }

    echo $button;
}


function get_transient( $name ) {
    global $ddo_test_state;
    return array_key_exists( $name, $ddo_test_state['transients'] ) ? $ddo_test_state['transients'][ $name ] : false;
}

function set_transient( $name, $value, $expiration = 0 ) {
    global $ddo_test_state;
    $ddo_test_state['transients'][ $name ] = $value;
    return true;
}

function get_option( $name, $default = false ) {
    global $ddo_test_state;
    return array_key_exists( $name, $ddo_test_state['options'] ) ? $ddo_test_state['options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
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

function current_user_can( $capability = null ) {
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
    public $pageviews_rows = array();

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

        if ( false !== strpos( $sql, 'INSERT INTO' ) && false !== strpos( $sql, 'ddo_pageviews_data' ) ) {
            $this->ingest_pageviews_rows_from_insert_sql( $sql );
        }

        return 1;
    }

    public function prepare( $query, ...$args ) {
        if ( 1 === count( $args ) && is_array( $args[0] ) ) {
            $args = $args[0];
        }

        $prepared = $query;

        foreach ( $args as $arg ) {
            $prepared = preg_replace_callback(
                '/%(?:\d+\$)?[sdf]/',
                static function ( $matches ) use ( $arg ) {
                    $placeholder = $matches[0];
                    $type        = substr( $placeholder, -1 );

                    if ( 'd' === $type ) {
                        return (string) (int) $arg;
                    }

                    if ( 'f' === $type ) {
                        return (string) (float) $arg;
                    }

                    return "'" . addslashes( (string) $arg ) . "'";
                },
                $prepared,
                1
            );
        }

        $this->prepared[] = array(
            'query'    => $query,
            'args'     => $args,
            'prepared' => $prepared,
        );

        return $prepared;
    }

    public function get_var( $query ) {
        $this->select_queries[] = $query;

        if ( false === strpos( $query, 'SUM(pageviews)' ) ) {
            return 0;
        }

        $rows = $this->filter_pageviews_rows_from_query( $query );

        $total = 0;
        foreach ( $rows as $row ) {
            $total += isset( $row['pageviews'] ) ? (int) $row['pageviews'] : 0;
        }

        return $total;
    }

    public function get_row( $query, $output = ARRAY_A ) {
        $this->select_queries[] = $query;
        $rows                   = $this->filter_feedback_rows_from_query( $query );

        $scores   = array();
        $unscored = 0;

        foreach ( $rows as $row ) {
            $has_score = isset( $row['is_scored'] ) ? ( 1 === (int) $row['is_scored'] && isset( $row['score'] ) && null !== $row['score'] ) : isset( $row['score'] ) && null !== $row['score'];

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

        if ( false !== strpos( $query, 'SUM(pageviews) AS total_pageviews' ) ) {
            return $this->build_pageviews_results_from_query( $query );
        }

        if ( false !== strpos( $query, 'GROUP BY event_name' ) ) {
            return $this->build_event_results_from_query( $query );
        }

        return $this->build_recent_results_from_query( $query );
    }

    private function build_pageviews_results_from_query( $query ) {
        $rows = $this->filter_pageviews_rows_from_query( $query );
        $paths = array();

        foreach ( $rows as $row ) {
            $path = isset( $row['page_path'] ) ? (string) $row['page_path'] : '';
            if ( '' === $path ) {
                continue;
            }

            if ( ! isset( $paths[ $path ] ) ) {
                $paths[ $path ] = 0;
            }

            $paths[ $path ] += isset( $row['pageviews'] ) ? (int) $row['pageviews'] : 0;
        }

        $results = array();
        foreach ( $paths as $path => $total_pageviews ) {
            $results[] = array(
                'page_path' => $path,
                'total_pageviews' => $total_pageviews,
            );
        }

        usort(
            $results,
            static function ( $left, $right ) {
                $cmp = (int) $right['total_pageviews'] <=> (int) $left['total_pageviews'];
                if ( 0 !== $cmp ) {
                    return $cmp;
                }

                return strcmp( (string) $left['page_path'], (string) $right['page_path'] );
            }
        );

        return array_slice( $results, 0, 5 );
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

            $has_score = isset( $row['is_scored'] ) ? ( 1 === (int) $row['is_scored'] && isset( $row['score'] ) && null !== $row['score'] ) : isset( $row['score'] ) && null !== $row['score'];
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

    private function filter_pageviews_rows_from_query( $query ) {
        $cutoff = null;
        if ( preg_match( "/metric_date\s*>=\s*'([^']+)'/", $query, $matches ) ) {
            $cutoff = $matches[1];
        }

        return array_values(
            array_filter(
                $this->pageviews_rows,
                static function ( $row ) use ( $cutoff ) {
                    if ( null === $cutoff ) {
                        return true;
                    }

                    return isset( $row['metric_date'] ) && $row['metric_date'] >= $cutoff;
                }
            )
        );
    }

    private function ingest_pageviews_rows_from_insert_sql( $sql ) {
        if ( ! preg_match_all( "/\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*'([^']+)'\s*,\s*NOW\(\)\s*,\s*NOW\(\)\s*\)/", $sql, $matches, PREG_SET_ORDER ) ) {
            return;
        }

        foreach ( $matches as $match ) {
            $this->pageviews_rows[] = array(
                'metric_date' => stripslashes( $match[1] ),
                'page_path'   => stripslashes( $match[2] ),
                'pageviews'   => (int) $match[3],
                'source'      => stripslashes( $match[4] ),
            );
        }
    }

    public function reset_feedback() {
        $this->insert_id     = 0;
        $this->feedback_rows = array();
        $this->queries       = array();
        $this->prepared      = array();
        $this->select_queries = array();
        $this->pageviews_rows = array();
    }
}

$wpdb = new DDO_Fake_WPDB();

require_once dirname( __DIR__ ) . '/includes/settings.php';
require_once dirname( __DIR__ ) . '/includes/logger.php';
require_once dirname( __DIR__ ) . '/includes/sources/google-analytics.php';
require_once dirname( __DIR__ ) . '/includes/api-handlers.php';
require_once dirname( __DIR__ ) . '/includes/ml-feedback.php';
require_once dirname( __DIR__ ) . '/includes/code-introspect.php';
require_once dirname( __DIR__ ) . '/includes/cron.php';
require_once dirname( __DIR__ ) . '/includes/admin-dashboard.php';

require_once dirname( __DIR__ ) . '/includes/db-schema.php';
