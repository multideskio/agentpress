<?php

namespace AgentPress;

/**
 * Webhook notifications.
 *
 * Fires async POST requests to configured webhook URLs when write/create operations succeed.
 */
class Webhooks {

    /**
     * Get configured webhook URLs.
     *
     * @return string[]
     */
    public static function get_urls(): array {
        return (array) get_option( 'agentpress_webhooks', [] );
    }

    /**
     * Save webhook URLs.
     *
     * @param string[] $urls Array of webhook URLs.
     */
    public static function save_urls( array $urls ): void {
        $urls = array_filter( array_map( 'esc_url_raw', $urls ) );
        update_option( 'agentpress_webhooks', array_values( $urls ) );
    }

    /**
     * Fire webhook for a successful write/create operation.
     *
     * @param string $tool_name Tool that was called.
     * @param array  $arguments Tool arguments.
     * @param array  $result    Tool result.
     * @param array  $key_data  Authenticated key data.
     */
    public static function maybe_fire( string $tool_name, array $arguments, array $result, array $key_data ): void {
        // Only fire on successful write/create operations
        if ( ! empty( $result['isError'] ) ) {
            return;
        }

        $action = $arguments['action'] ?? '';
        if ( ! in_array( $action, [ 'create', 'update', 'delete', 'insert', 'write' ], true ) ) {
            return;
        }

        $urls = self::get_urls();
        if ( empty( $urls ) ) {
            return;
        }

        $summary = '';
        if ( isset( $result['content'][0]['text'] ) ) {
            $summary = mb_substr( $result['content'][0]['text'], 0, 200 );
        }

        $payload = [
            'event'     => 'tool_call_success',
            'tool'      => $tool_name,
            'action'    => $action,
            'key_name'  => $key_data['name'] ?? 'unknown',
            'timestamp' => current_time( 'c' ),
            'summary'   => $summary,
        ];

        foreach ( $urls as $url ) {
            wp_remote_post( $url, [
                'body'     => wp_json_encode( $payload ),
                'headers'  => [ 'Content-Type' => 'application/json' ],
                'blocking' => false,
                'timeout'  => 5,
            ] );
        }
    }
}
