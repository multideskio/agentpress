<?php

namespace AgentPress;

class Audit_Log {

    /**
     * Log an MCP tool call.
     */
    public static function log( int $key_id, string $tool, array $params, array $result ): void {
        global $wpdb;

        $summary = '';
        if ( isset( $result['isError'] ) && $result['isError'] ) {
            $summary = 'ERROR: ' . ( $result['content'][0]['text'] ?? 'Unknown error' );
        } else {
            $summary = 'OK';
            if ( isset( $result['content'][0]['text'] ) ) {
                $summary = mb_substr( $result['content'][0]['text'], 0, 200 );
            }
        }

        // Truncate params to prevent log bloat
        $params_json = wp_json_encode( $params );
        if ( strlen( $params_json ) > 1024 ) {
            $params_json = wp_json_encode( [
                '_truncated' => true,
                '_original_size' => strlen( $params_json ),
                '_keys' => array_keys( $params ),
            ] );
        }

        $ip = self::get_client_ip();

        $wpdb->insert(
            $wpdb->prefix . 'agentpress_logs',
            [
                'key_id'         => $key_id,
                'tool'           => sanitize_text_field( $tool ),
                'action'         => sanitize_text_field( $params['action'] ?? 'call' ),
                'params'         => $params_json,
                'result_summary' => sanitize_text_field( $summary ),
                'ip_address'     => sanitize_text_field( $ip ),
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get client IP address safely.
     */
    private static function get_client_ip(): string {
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    }

    /**
     * Get recent logs for admin display (legacy method kept for compatibility).
     */
    public static function get_recent( int $limit = 50, int $key_id = 0 ): array {
        global $wpdb;

        $limit = min( max( $limit, 1 ), 500 );

        if ( $key_id > 0 ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, k.name as key_name
                     FROM {$wpdb->prefix}agentpress_logs l
                     LEFT JOIN {$wpdb->prefix}agentpress_keys k ON l.key_id = k.id
                     WHERE l.key_id = %d
                     ORDER BY l.created_at DESC
                     LIMIT %d",
                    $key_id,
                    $limit
                ),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, k.name as key_name
                 FROM {$wpdb->prefix}agentpress_logs l
                 LEFT JOIN {$wpdb->prefix}agentpress_keys k ON l.key_id = k.id
                 ORDER BY l.created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get filtered logs with pagination.
     *
     * @param array $filters {key_id, tool, date_from, date_to, per_page, offset}
     * @return array
     */
    public static function get_filtered( array $filters ): array {
        global $wpdb;

        $where = self::build_where_clause( $filters );

        $per_page = min( max( (int) ( $filters['per_page'] ?? 20 ), 1 ), 100 );
        $offset   = max( 0, (int) ( $filters['offset'] ?? 0 ) );

        $sql = "SELECT l.*, k.name as key_name
                FROM {$wpdb->prefix}agentpress_logs l
                LEFT JOIN {$wpdb->prefix}agentpress_keys k ON l.key_id = k.id
                {$where}
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare( $sql, $per_page, $offset ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get total count of filtered logs (for pagination).
     */
    public static function get_filtered_count( array $filters ): int {
        global $wpdb;

        $where = self::build_where_clause( $filters );

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}agentpress_logs l {$where}"
        );
    }

    /**
     * Build WHERE clause for filtered queries.
     */
    private static function build_where_clause( array $filters ): string {
        global $wpdb;

        $conditions = [];

        if ( ! empty( $filters['key_id'] ) ) {
            $conditions[] = $wpdb->prepare( 'l.key_id = %d', (int) $filters['key_id'] );
        }

        if ( ! empty( $filters['tool'] ) ) {
            $conditions[] = $wpdb->prepare( 'l.tool LIKE %s', '%' . $wpdb->esc_like( $filters['tool'] ) . '%' );
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $conditions[] = $wpdb->prepare( 'l.created_at >= %s', $filters['date_from'] . ' 00:00:00' );
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $conditions[] = $wpdb->prepare( 'l.created_at <= %s', $filters['date_to'] . ' 23:59:59' );
        }

        if ( empty( $conditions ) ) {
            return '';
        }

        return 'WHERE ' . implode( ' AND ', $conditions );
    }

    // ─── Dashboard Metrics ─────────────────────────────────────────────

    /**
     * Get request count for a period.
     *
     * @param string $period 'today', 'week', 'month'
     */
    public static function get_request_count( string $period = 'today' ): int {
        global $wpdb;

        $date_condition = match ( $period ) {
            'today' => "DATE(created_at) = CURDATE()",
            'week'  => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "1=1",
        };

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}agentpress_logs WHERE {$date_condition}"
        );
    }

    /**
     * Get requests per day for the last N days.
     *
     * @param int $days Number of days.
     * @return array<string, int> date => count
     */
    public static function get_requests_per_day( int $days = 7 ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as day, COUNT(*) as total
                 FROM {$wpdb->prefix}agentpress_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC",
                $days
            ),
            ARRAY_A
        ) ?: [];

        $data = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $data[ $date ] = 0;
        }

        foreach ( $results as $row ) {
            $data[ $row['day'] ] = (int) $row['total'];
        }

        return $data;
    }

    /**
     * Get top N tools by usage.
     *
     * @param int $limit Number of results.
     * @return array<int, array{tool: string, total: int}>
     */
    public static function get_top_tools( int $limit = 5 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tool, COUNT(*) as total
                 FROM {$wpdb->prefix}agentpress_logs
                 GROUP BY tool
                 ORDER BY total DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get top N keys by usage.
     *
     * @param int $limit Number of results.
     * @return array<int, array{key_name: string, total: int}>
     */
    public static function get_top_keys( int $limit = 5 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT k.name as key_name, COUNT(*) as total
                 FROM {$wpdb->prefix}agentpress_logs l
                 LEFT JOIN {$wpdb->prefix}agentpress_keys k ON l.key_id = k.id
                 GROUP BY l.key_id
                 ORDER BY total DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get last N errors.
     *
     * @param int $limit Number of results.
     * @return array
     */
    public static function get_last_errors( int $limit = 5 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, k.name as key_name
                 FROM {$wpdb->prefix}agentpress_logs l
                 LEFT JOIN {$wpdb->prefix}agentpress_keys k ON l.key_id = k.id
                 WHERE l.result_summary LIKE 'ERROR:%%'
                 ORDER BY l.created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Purge old logs (keep last N days).
     */
    public static function purge( int $days = 30 ): int {
        global $wpdb;

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}agentpress_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
