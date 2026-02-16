<?php

class DDO_Feedback_Fixtures {
    public static function valid_payload( $overrides = array() ) {
        $base = array(
            'event'       => 'conversion',
            'score'       => 8,
            'client_id'   => 'client-001',
            'campaign_id' => 'campaign-001',
            'ad_id'       => 'ad-001',
        );

        return array_merge( $base, $overrides );
    }

    public static function signed_request( $payload_overrides = array(), $meta_overrides = array() ) {
        $payload = self::valid_payload( $payload_overrides );
        $nonce   = isset( $meta_overrides['nonce'] ) ? (string) $meta_overrides['nonce'] : 'feedbacknonce123456';
        $timestamp = isset( $meta_overrides['timestamp'] ) ? (int) $meta_overrides['timestamp'] : time();

        $signed_payload = array(
            'event'       => ddo_api_sanitize_feedback_event( $payload['event'] ),
            'score'       => (string) ddo_api_sanitize_feedback_score( $payload['score'] ),
            'client_id'   => ddo_api_sanitize_feedback_identifier( $payload['client_id'] ),
            'campaign_id' => ddo_api_sanitize_feedback_identifier( $payload['campaign_id'] ),
            'ad_id'       => ddo_api_sanitize_feedback_identifier( $payload['ad_id'] ),
        );

        $signature = isset( $meta_overrides['signature'] )
            ? (string) $meta_overrides['signature']
            : hash_hmac(
                'sha256',
                $nonce . '|' . $timestamp . '|' . wp_json_encode( $signed_payload ),
                ddo_api_get_feedback_signature_secret()
            );

        return new WP_REST_Request(
            array_merge(
                $payload,
                array(
                    'nonce'     => $nonce,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                )
            )
        );
    }

    public static function summary_rows_dataset() {
        return array(
            array(
                'id'            => 1,
                'event_name'    => 'conversion',
                'score'         => 8,
                'is_scored'     => 1,
                'feedback_date' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'campaign-001',
                'ad_id'         => 'ad-001',
            ),
            array(
                'id'            => 2,
                'event_name'    => 'conversion',
                'score'         => null,
                'is_scored'     => 0,
                'feedback_date' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ),
                'status'        => 'open',
                'campaign_id'   => 'campaign-001',
                'ad_id'         => 'ad-001',
            ),
        );
    }
}
