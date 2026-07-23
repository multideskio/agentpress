<?php

namespace AgentPress;

class Auth {

    /**
     * Validate API key from request and return key data.
     */
    public static function validate( \WP_REST_Request $request ): ?array {
        $auth_header = $request->get_header( 'authorization' );

        if ( empty( $auth_header ) ) {
            return null;
        }

        // Support "Bearer <key>" format
        if ( stripos( $auth_header, 'Bearer ' ) === 0 ) {
            $api_key = substr( $auth_header, 7 );
        } else {
            $api_key = $auth_header;
        }

        $api_key = sanitize_text_field( trim( $api_key ) );
        if ( empty( $api_key ) ) {
            return null;
        }

        return self::get_key_data( $api_key );
    }

    /**
     * Get key data from database using hash comparison to prevent timing attacks.
     */
    private static function get_key_data( string $api_key ): ?array {
        global $wpdb;

        // Extract prefix for indexed lookup (first 7 chars: "ap_" + 4 hex)
        $prefix = substr( $api_key, 0, 7 );
        if ( strlen( $prefix ) < 7 ) {
            return null;
        }

        // Lookup by hash — constant-time safe
        $key_hash = hash( 'sha256', $api_key );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}agentpress_keys WHERE key_hash = %s AND is_active = 1",
                $key_hash
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            // Fallback for legacy keys stored in plain text (pre-hash migration)
            $all_active = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}agentpress_keys WHERE is_active = 1 AND key_hash = ''",
                ARRAY_A
            );

            foreach ( $all_active as $candidate ) {
                if ( hash_equals( $candidate['api_key'], $api_key ) ) {
                    $row = $candidate;
                    // Migrate: store hash and mask the plain key
                    $wpdb->update(
                        $wpdb->prefix . 'agentpress_keys',
                        [
                            'key_hash' => $key_hash,
                            'api_key'  => substr( $api_key, 0, 12 ) . str_repeat( '*', 36 ),
                        ],
                        [ 'id' => $row['id'] ]
                    );
                    break;
                }
            }

            if ( ! $row ) {
                return null;
            }
        }

        // Check expiration
        if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] ) < time() ) {
            return null;
        }

        // Update last used
        $wpdb->update(
            $wpdb->prefix . 'agentpress_keys',
            [ 'last_used_at' => current_time( 'mysql' ) ],
            [ 'id' => $row['id'] ]
        );

        $row['permissions'] = json_decode( $row['permissions'], true ) ?: [];

        return $row;
    }

    /**
     * Check if key has permission for a specific tool and action.
     */
    public static function can( array $key_data, string $tool, string $action ): bool {
        $permissions = $key_data['permissions'];

        // Check tool-level permission
        if ( ! isset( $permissions[ $tool ] ) ) {
            return false;
        }

        $tool_perms = $permissions[ $tool ];

        // If tool permission is true, allow everything
        if ( $tool_perms === true ) {
            return true;
        }

        // Check specific action
        if ( is_array( $tool_perms ) && in_array( $action, $tool_perms, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if key can access a specific database table.
     * Table name should already be resolved (with prefix).
     */
    public static function can_access_table( array $key_data, string $table, string $operation ): bool {
        $permissions = $key_data['permissions'];

        if ( ! isset( $permissions['database'] ) ) {
            return false;
        }

        $db_perms = $permissions['database'];

        // Check table-level
        if ( ! isset( $db_perms['tables'] ) ) {
            return false;
        }

        // Wildcard
        if ( in_array( '*', $db_perms['tables'], true ) ) {
            return self::check_operation( $db_perms, $operation );
        }

        // Specific table
        if ( is_array( $db_perms['tables'] ) ) {
            // Simple list of table names
            if ( isset( $db_perms['tables'][0] ) && is_string( $db_perms['tables'][0] ) ) {
                if ( ! in_array( $table, $db_perms['tables'], true ) ) {
                    return false;
                }
                return self::check_operation( $db_perms, $operation );
            }

            // Table-specific permissions: { "wp_posts": ["read"], "wp_cf7": ["read", "write"] }
            if ( isset( $db_perms['tables'][ $table ] ) ) {
                $table_ops = $db_perms['tables'][ $table ];
                return in_array( $operation, $table_ops, true );
            }
        }

        return false;
    }

    private static function check_operation( array $db_perms, string $operation ): bool {
        if ( ! isset( $db_perms['operations'] ) ) {
            return $operation === 'read'; // Default to read-only
        }

        return in_array( $operation, $db_perms['operations'], true );
    }

    /**
     * Generate a new API key.
     */
    public static function generate_key(): string {
        return 'ap_' . bin2hex( random_bytes( 24 ) );
    }

    /**
     * Hash an API key for storage.
     */
    public static function hash_key( string $api_key ): string {
        return hash( 'sha256', $api_key );
    }

    /**
     * Check rate limit for a key (POST requests only).
     * Uses a simple sliding window per minute.
     */
    public static function check_rate_limit( array $key_data ): bool {
        $rate_limit = (int) ( $key_data['rate_limit'] ?? 0 );

        // 0 = unlimited
        if ( $rate_limit <= 0 ) {
            return true;
        }

        $transient_key = 'agentpress_rate_' . $key_data['id'];
        $current       = (int) get_transient( $transient_key );

        if ( $current >= $rate_limit ) {
            return false;
        }

        // Increment — if first request, set with 60s TTL
        if ( $current === 0 ) {
            set_transient( $transient_key, 1, 60 );
        } else {
            // Update value keeping existing TTL (WordPress doesn't support this natively,
            // so we set again with 60s — acceptable slight drift)
            set_transient( $transient_key, $current + 1, 60 );
        }

        return true;
    }

    /**
     * Check concurrent SSE connections for a key.
     */
    public static function check_sse_limit( array $key_data ): bool {
        $max = (int) get_option( 'agentpress_sse_max_connections', 3 );

        if ( $max <= 0 ) {
            return true; // 0 = unlimited
        }

        $transient_key = 'agentpress_sse_' . $key_data['id'];
        $count         = (int) get_transient( $transient_key );

        if ( $count >= $max ) {
            return false;
        }

        set_transient( $transient_key, $count + 1, 600 ); // 10min TTL — auto-expire stale slots
        return true;
    }

    /**
     * Release SSE connection slot.
     */
    public static function release_sse_slot( array $key_data ): void {
        $transient_key = 'agentpress_sse_' . $key_data['id'];
        $count         = (int) get_transient( $transient_key );

        if ( $count > 1 ) {
            set_transient( $transient_key, $count - 1, 600 );
        } else {
            delete_transient( $transient_key );
        }
    }

    /**
     * Record activity timestamp for a key (called on POST /mcp).
     */
    public static function touch_activity( array $key_data ): void {
        $transient_key = 'agentpress_activity_' . $key_data['id'];
        set_transient( $transient_key, time(), 600 );
    }

    /**
     * Get seconds since last activity for a key.
     * Returns 0 if never recorded (treat as active).
     */
    public static function get_idle_seconds( array $key_data ): int {
        $transient_key = 'agentpress_activity_' . $key_data['id'];
        $last_activity = get_transient( $transient_key );

        if ( $last_activity === false ) {
            return 0; // No record = just connected, treat as active
        }

        return time() - (int) $last_activity;
    }
}
