<?php

namespace AgentPress\Tools;

use AgentPress\Auth;

/**
 * Direct database access tool — the main differentiator.
 * Allows agents to query ANY table with proper permissions and security.
 */
class Database_Tool {

    private const MAX_ROWS = 100;
    private const MAX_UPDATE_ROWS = 50;

    /**
     * Validate a SQL identifier (table name, column name).
     * Only allows alphanumeric + underscores. Prevents injection via backtick escaping.
     */
    private static function is_valid_identifier( string $name ): bool {
        return (bool) preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $name );
    }

    /**
     * Get the merged list of blocked columns (hardcoded + configured).
     */
    private function get_blocked_columns(): array {
        $hardcoded = [
            'user_pass',
            'user_activation_key',
            'session_tokens',
        ];

        $configured = get_option( 'agentpress_blocked_columns', [] );
        if ( ! is_array( $configured ) ) {
            $configured = [];
        }

        return array_unique( array_merge( $hardcoded, $configured ) );
    }

    /**
     * Validate that a table actually exists in the database.
     */
    private function table_exists( string $table ): bool {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        return $result !== null;
    }

    public function handles( string $tool_name ): bool {
        return in_array( $tool_name, [ 'db_query', 'db_list_tables', 'db_describe', 'db_insert', 'db_update' ], true );
    }

    public function get_required_action( string $tool_name ): string {
        return match( $tool_name ) {
            'db_insert'      => 'create',
            'db_update'      => 'write',
            default          => 'read',
        };
    }

    public function get_definitions( array $key_data ): array {
        $tools = [];

        $tools[] = [
            'name'        => 'db_list_tables',
            'description' => 'List all database tables available (based on your permissions). Shows table names and row counts.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'filter' => [
                        'type'        => 'string',
                        'description' => 'Optional filter — only show tables containing this string (e.g. "woo", "cf7", "crm")',
                    ],
                ],
            ],
        ];

        $tools[] = [
            'name'        => 'db_describe',
            'description' => 'Describe a table structure — show columns, types, and keys.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'table' => [
                        'type'        => 'string',
                        'description' => 'Table name (with or without wp_ prefix).',
                    ],
                ],
                'required' => [ 'table' ],
            ],
        ];

        $tools[] = [
            'name'        => 'db_query',
            'description' => 'Run a SELECT query on a permitted table. Supports WHERE, ORDER BY, LIMIT. No JOINs or subqueries for security.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'table' => [
                        'type'        => 'string',
                        'description' => 'Table name to query.',
                    ],
                    'columns' => [
                        'type'        => 'array',
                        'items'       => [ 'type' => 'string' ],
                        'description' => 'Columns to select. Default: all (*)',
                    ],
                    'where' => [
                        'type'        => 'object',
                        'description' => 'WHERE conditions as key-value pairs. Example: {"status": "active", "email": "test@example.com"}',
                    ],
                    'where_like' => [
                        'type'        => 'object',
                        'description' => 'LIKE conditions. Example: {"email": "%@gmail.com"}',
                    ],
                    'order_by' => [
                        'type'        => 'string',
                        'description' => 'Column to order by.',
                    ],
                    'order' => [
                        'type'        => 'string',
                        'description' => 'ASC or DESC. Default: DESC',
                    ],
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Max rows to return. Max 100. Default: 20',
                    ],
                    'offset' => [
                        'type'        => 'integer',
                        'description' => 'Offset for pagination. Default: 0',
                    ],
                ],
                'required' => [ 'table' ],
            ],
        ];

        if ( Auth::can( $key_data, 'database', 'create' ) ) {
            $tools[] = [
                'name'        => 'db_insert',
                'description' => 'Insert a row into a permitted table.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table' => [
                            'type'        => 'string',
                            'description' => 'Table name to insert into.',
                        ],
                        'data' => [
                            'type'        => 'object',
                            'description' => 'Column-value pairs to insert. Example: {"name": "John", "email": "john@test.com"}',
                        ],
                    ],
                    'required' => [ 'table', 'data' ],
                ],
            ];
        }

        if ( Auth::can( $key_data, 'database', 'write' ) ) {
            $tools[] = [
                'name'        => 'db_update',
                'description' => 'Update rows in a permitted table. Max 50 rows affected per call. WHERE clause with specific ID recommended.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table' => [
                            'type'        => 'string',
                            'description' => 'Table name to update.',
                        ],
                        'data' => [
                            'type'        => 'object',
                            'description' => 'Column-value pairs to set. Example: {"status": "completed"}',
                        ],
                        'where' => [
                            'type'        => 'object',
                            'description' => 'WHERE conditions to match rows. Example: {"id": 42}',
                        ],
                    ],
                    'required' => [ 'table', 'data', 'where' ],
                ],
            ];
        }

        return $tools;
    }

    public function execute( string $tool_name, array $args, array $key_data ): array {
        return match( $tool_name ) {
            'db_list_tables' => $this->list_tables( $args, $key_data ),
            'db_describe'    => $this->describe_table( $args, $key_data ),
            'db_query'       => $this->query( $args, $key_data ),
            'db_insert'      => $this->insert( $args, $key_data ),
            'db_update'      => $this->update( $args, $key_data ),
            default          => [ 'content' => [ [ 'type' => 'text', 'text' => 'Unknown action' ] ], 'isError' => true ],
        };
    }

    private function list_tables( array $args, array $key_data ): array {
        global $wpdb;

        $tables = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
        $result = [];

        foreach ( $tables as $table ) {
            $name = $table['Name'];

            // Filter by permission
            if ( ! Auth::can_access_table( $key_data, $name, 'read' ) ) {
                continue;
            }

            // Filter by search term
            if ( ! empty( $args['filter'] ) && stripos( $name, sanitize_text_field( $args['filter'] ) ) === false ) {
                continue;
            }

            $result[] = [
                'table'   => $name,
                'rows'    => (int) $table['Rows'],
                'engine'  => $table['Engine'],
                'size_kb' => round( ( (int) $table['Data_length'] + (int) $table['Index_length'] ) / 1024, 1 ),
            ];
        }

        $text = sprintf( "Found %d accessible tables:\n\n%s", count( $result ), wp_json_encode( $result, JSON_PRETTY_PRINT ) );
        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function describe_table( array $args, array $key_data ): array {
        global $wpdb;

        $table = $this->resolve_table_name( $args['table'] ?? '' );

        if ( ! self::is_valid_identifier( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Invalid table name format' ] ], 'isError' => true ];
        }

        if ( ! Auth::can_access_table( $key_data, $table, 'read' ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Permission denied for this table' ] ], 'isError' => true ];
        }

        if ( ! $this->table_exists( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Table not found' ] ], 'isError' => true ];
        }

        // Safe: table name validated via regex + existence check
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );

        if ( ! $columns ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Could not describe table' ] ], 'isError' => true ];
        }

        // Filter out blocked columns
        $blocked = $this->get_blocked_columns();
        $columns = array_filter( $columns, fn( $col ) => ! in_array( $col['Field'], $blocked, true ) );
        $columns = array_values( $columns );

        $text = sprintf( "Table: %s\nColumns:\n\n%s", $table, wp_json_encode( $columns, JSON_PRETTY_PRINT ) );
        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function query( array $args, array $key_data ): array {
        global $wpdb;

        $table = $this->resolve_table_name( $args['table'] ?? '' );
        $blocked = $this->get_blocked_columns();

        // Validate table name
        if ( ! self::is_valid_identifier( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Invalid table name format' ] ], 'isError' => true ];
        }

        if ( ! Auth::can_access_table( $key_data, $table, 'read' ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Permission denied for this table' ] ], 'isError' => true ];
        }

        if ( ! $this->table_exists( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Table not found' ] ], 'isError' => true ];
        }

        $columns = $args['columns'] ?? [ '*' ];
        $limit   = min( (int) ( $args['limit'] ?? 20 ), self::MAX_ROWS );
        $offset  = max( (int) ( $args['offset'] ?? 0 ), 0 );
        $order   = in_array( strtoupper( $args['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ] ) ? strtoupper( $args['order'] ) : 'DESC';

        // Validate and filter columns
        if ( $columns !== [ '*' ] ) {
            if ( count( $columns ) > 30 ) {
                return [ 'content' => [ [ 'type' => 'text', 'text' => 'Too many columns requested (max 30)' ] ], 'isError' => true ];
            }
            foreach ( $columns as $col ) {
                if ( ! is_string( $col ) || ! self::is_valid_identifier( $col ) ) {
                    return [ 'content' => [ [ 'type' => 'text', 'text' => "Invalid column name: " . substr( (string) $col, 0, 30 ) ] ], 'isError' => true ];
                }
            }
            $columns = array_filter( $columns, fn( $col ) => ! in_array( $col, $blocked, true ) );
            $cols_sql = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );
        } else {
            $cols_sql = '*';
        }

        $sql = "SELECT {$cols_sql} FROM `{$table}`";

        // WHERE conditions (max 10 conditions)
        $where_parts = [];
        $values      = [];
        $max_conditions = 10;

        if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
            foreach ( $args['where'] as $col => $val ) {
                if ( count( $where_parts ) >= $max_conditions ) break;
                if ( ! is_string( $col ) || ! self::is_valid_identifier( $col ) ) continue;
                if ( in_array( $col, $blocked, true ) ) continue;
                if ( ! is_scalar( $val ) && $val !== null ) continue;
                $where_parts[] = "`{$col}` = %s";
                $values[]      = (string) $val;
            }
        }

        if ( ! empty( $args['where_like'] ) && is_array( $args['where_like'] ) ) {
            foreach ( $args['where_like'] as $col => $val ) {
                if ( count( $where_parts ) >= $max_conditions ) break;
                if ( ! is_string( $col ) || ! self::is_valid_identifier( $col ) ) continue;
                if ( in_array( $col, $blocked, true ) ) continue;
                if ( ! is_string( $val ) ) continue;
                if ( strlen( $val ) > 255 ) continue; // Prevent overly broad LIKE patterns
                $where_parts[] = "`{$col}` LIKE %s";
                $values[]      = $val;
            }
        }

        if ( ! empty( $where_parts ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_parts );
        }

        // ORDER BY — validated identifier
        if ( ! empty( $args['order_by'] ) && is_string( $args['order_by'] ) ) {
            if ( ! self::is_valid_identifier( $args['order_by'] ) ) {
                return [ 'content' => [ [ 'type' => 'text', 'text' => 'Invalid order_by column name' ] ], 'isError' => true ];
            }
            $sql .= " ORDER BY `{$args['order_by']}` {$order}";
        }

        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        // Execute with prepared statement
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        $results = $wpdb->get_results( $sql, ARRAY_A );

        if ( $wpdb->last_error ) {
            // Don't expose SQL error details to the agent
            error_log( '[AgentPress] Query error: ' . $wpdb->last_error );
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Database query failed. Check server logs for details.' ] ], 'isError' => true ];
        }

        // Remove blocked columns from results (for SELECT *)
        $results = array_map( function( $row ) use ( $blocked ) {
            foreach ( $blocked as $col ) {
                unset( $row[ $col ] );
            }
            return $row;
        }, $results );

        $count = count( $results );
        $text  = sprintf( "Query returned %d rows:\n\n%s", $count, wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function insert( array $args, array $key_data ): array {
        global $wpdb;

        $table   = $this->resolve_table_name( $args['table'] ?? '' );
        $data    = $args['data'] ?? [];
        $blocked = $this->get_blocked_columns();

        // Validate table
        if ( ! self::is_valid_identifier( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Invalid table name format' ] ], 'isError' => true ];
        }

        if ( ! Auth::can_access_table( $key_data, $table, 'create' ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Permission denied for this table' ] ], 'isError' => true ];
        }

        if ( ! $this->table_exists( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Table not found' ] ], 'isError' => true ];
        }

        if ( empty( $data ) || ! is_array( $data ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No data provided for insert' ] ], 'isError' => true ];
        }

        // Limit number of fields to prevent abuse
        if ( count( $data ) > 50 ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Too many fields (max 50)' ] ], 'isError' => true ];
        }

        // Validate and sanitize data
        $clean_data = [];
        foreach ( $data as $key => $value ) {
            if ( ! is_string( $key ) || ! self::is_valid_identifier( $key ) ) continue;
            if ( in_array( $key, $blocked, true ) ) continue;
            if ( ! is_scalar( $value ) && $value !== null ) continue; // Only scalar values
            if ( is_string( $value ) && strlen( $value ) > 65535 ) continue; // Max TEXT field size
            $clean_data[ $key ] = $value;
        }

        if ( empty( $clean_data ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No valid data after validation' ] ], 'isError' => true ];
        }

        $result = $wpdb->insert( $table, $clean_data );

        if ( $result === false ) {
            error_log( '[AgentPress] Insert error: ' . $wpdb->last_error );
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Insert failed. Check server logs for details.' ] ], 'isError' => true ];
        }

        $id = $wpdb->insert_id;
        return [ 'content' => [ [ 'type' => 'text', 'text' => "Row inserted successfully. ID: {$id}" ] ] ];
    }

    private function update( array $args, array $key_data ): array {
        global $wpdb;

        $table   = $this->resolve_table_name( $args['table'] ?? '' );
        $data    = $args['data'] ?? [];
        $where   = $args['where'] ?? [];
        $blocked = $this->get_blocked_columns();

        // Validate table
        if ( ! self::is_valid_identifier( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Invalid table name format' ] ], 'isError' => true ];
        }

        if ( ! Auth::can_access_table( $key_data, $table, 'write' ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Permission denied for this table' ] ], 'isError' => true ];
        }

        if ( ! $this->table_exists( $table ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Table not found' ] ], 'isError' => true ];
        }

        if ( empty( $data ) || ! is_array( $data ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No data provided for update' ] ], 'isError' => true ];
        }

        if ( empty( $where ) || ! is_array( $where ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'WHERE clause is required for updates (safety)' ] ], 'isError' => true ];
        }

        // Safety check: count affected rows BEFORE updating
        $count_where_parts = [];
        $count_values      = [];
        foreach ( $where as $col => $val ) {
            if ( ! is_string( $col ) || ! self::is_valid_identifier( $col ) ) continue;
            if ( in_array( $col, $blocked, true ) ) continue;
            if ( ! is_scalar( $val ) && $val !== null ) continue;
            $count_where_parts[] = "`{$col}` = %s";
            $count_values[]      = (string) $val;
        }

        if ( empty( $count_where_parts ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No valid WHERE conditions after validation' ] ], 'isError' => true ];
        }

        $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE " . implode( ' AND ', $count_where_parts );
        $affected  = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_values ) );

        if ( $affected > self::MAX_UPDATE_ROWS ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => "Safety limit: this would affect {$affected} rows (max " . self::MAX_UPDATE_ROWS . "). Use more specific WHERE conditions." ] ], 'isError' => true ];
        }

        // Validate data
        $clean_data = [];
        foreach ( $data as $key => $value ) {
            if ( ! is_string( $key ) || ! self::is_valid_identifier( $key ) ) continue;
            if ( in_array( $key, $blocked, true ) ) continue;
            if ( ! is_scalar( $value ) && $value !== null ) continue;
            $clean_data[ $key ] = $value;
        }

        if ( empty( $clean_data ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No valid data after validation' ] ], 'isError' => true ];
        }

        // Validate where
        $clean_where = [];
        foreach ( $where as $key => $value ) {
            if ( ! is_string( $key ) || ! self::is_valid_identifier( $key ) ) continue;
            if ( in_array( $key, $blocked, true ) ) continue;
            if ( ! is_scalar( $value ) && $value !== null ) continue;
            $clean_where[ $key ] = $value;
        }

        if ( empty( $clean_where ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'No valid WHERE conditions' ] ], 'isError' => true ];
        }

        $result = $wpdb->update( $table, $clean_data, $clean_where );

        if ( $result === false ) {
            error_log( '[AgentPress] Update error: ' . $wpdb->last_error );
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Update failed. Check server logs for details.' ] ], 'isError' => true ];
        }

        return [ 'content' => [ [ 'type' => 'text', 'text' => "Updated {$result} row(s) in {$table}." ] ] ];
    }

    /**
     * Resolve table name — adds wp_ prefix if missing.
     * Also validates format.
     */
    private function resolve_table_name( string $table ): string {
        global $wpdb;

        $table = sanitize_text_field( $table );

        // Remove any backticks or special chars that shouldn't be there
        $table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );

        // If already has the prefix, return as-is
        if ( str_starts_with( $table, $wpdb->prefix ) ) {
            return $table;
        }

        // Add prefix
        return $wpdb->prefix . $table;
    }
}
