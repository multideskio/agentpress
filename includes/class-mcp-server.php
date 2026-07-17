<?php

namespace AgentPress;

class MCP_Server {

    private const NAMESPACE = 'agentpress/v1';

    public function register(): void {
        // SSE endpoint for MCP transport
        register_rest_route( self::NAMESPACE, '/sse', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_sse' ],
            'permission_callback' => '__return_true',
        ]);

        // Messages endpoint (POST)
        register_rest_route( self::NAMESPACE, '/message', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_message' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'OPTIONS',
                'callback'            => [ $this, 'handle_options' ],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Health check endpoint (no auth)
        register_rest_route( self::NAMESPACE, '/health', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_health' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Send CORS headers.
     */
    private function send_cors_headers(): void {
        $origin = apply_filters( 'agentpress_cors_origin', '*' );

        header( "Access-Control-Allow-Origin: {$origin}" );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
    }

    /**
     * Handle OPTIONS preflight request.
     */
    public function handle_options( \WP_REST_Request $request ): \WP_REST_Response {
        $this->send_cors_headers();
        return new \WP_REST_Response( null, 200 );
    }

    /**
     * Handle health check — no auth required.
     */
    public function handle_health( \WP_REST_Request $request ): \WP_REST_Response {
        $this->send_cors_headers();

        $registry    = new Tool_Registry();
        $tools_count = count( $registry->get_all_tool_names() );

        return new \WP_REST_Response( [
            'status'      => 'ok',
            'version'     => AGENTPRESS_VERSION,
            'tools_count' => $tools_count,
            'timestamp'   => current_time( 'c' ),
        ], 200 );
    }

    /**
     * Handle SSE connection — MCP transport layer.
     */
    public function handle_sse( \WP_REST_Request $request ): void {
        $this->send_cors_headers();

        $key_data = Auth::validate( $request );
        if ( ! $key_data ) {
            wp_send_json_error( [ 'message' => 'Invalid or missing API key' ], 401 );
            return;
        }

        Hooks::key_authenticated( $key_data );

        if ( ! Auth::check_rate_limit( $key_data ) ) {
            Hooks::rate_limited( $key_data );
            wp_send_json_error( [ 'message' => 'Rate limit exceeded' ], 429 );
            return;
        }

        // Check concurrent SSE connections (prevent resource exhaustion)
        if ( ! Auth::check_sse_limit( $key_data ) ) {
            wp_send_json_error( [ 'message' => 'Too many concurrent SSE connections for this key' ], 429 );
            return;
        }

        // Set SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' );

        // Disable output buffering
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        // Send endpoint info
        $message_url = rest_url( self::NAMESPACE . '/message' );
        $this->send_event( 'endpoint', $message_url );

        // Keep connection alive
        $start   = time();
        $timeout = 300; // 5 minutes max

        while ( ( time() - $start ) < $timeout ) {
            if ( connection_aborted() ) {
                break;
            }

            $this->send_event( 'ping', '' );
            sleep( 10 );
        }

        // Release SSE slot on disconnect
        Auth::release_sse_slot( $key_data );
        exit;
    }

    /**
     * Handle MCP JSON-RPC message.
     */
    public function handle_message( \WP_REST_Request $request ): \WP_REST_Response {
        $this->send_cors_headers();

        $key_data = Auth::validate( $request );
        if ( ! $key_data ) {
            return new \WP_REST_Response( [
                'jsonrpc' => '2.0',
                'error'   => [ 'code' => -32000, 'message' => 'Invalid or missing API key' ],
                'id'      => null,
            ], 401 );
        }

        Hooks::key_authenticated( $key_data );

        if ( ! Auth::check_rate_limit( $key_data ) ) {
            Hooks::rate_limited( $key_data );
            return new \WP_REST_Response( [
                'jsonrpc' => '2.0',
                'error'   => [ 'code' => -32000, 'message' => 'Rate limit exceeded' ],
                'id'      => null,
            ], 429 );
        }

        $body = $request->get_json_params();

        if ( empty( $body['method'] ) || ! is_string( $body['method'] ) ) {
            return new \WP_REST_Response( [
                'jsonrpc' => '2.0',
                'error'   => [ 'code' => -32600, 'message' => 'Invalid request' ],
                'id'      => $body['id'] ?? null,
            ], 400 );
        }

        $method = sanitize_text_field( $body['method'] );
        $params = is_array( $body['params'] ?? null ) ? $body['params'] : [];
        $id     = $body['id'] ?? null;

        $result = $this->dispatch( $method, $params, $key_data );

        if ( isset( $result['error'] ) ) {
            return new \WP_REST_Response( [
                'jsonrpc' => '2.0',
                'error'   => $result['error'],
                'id'      => $id,
            ], 200 );
        }

        return new \WP_REST_Response( [
            'jsonrpc' => '2.0',
            'result'  => $result,
            'id'      => $id,
        ], 200 );
    }

    /**
     * Dispatch MCP method.
     */
    private function dispatch( string $method, array $params, array $key_data ): array {
        switch ( $method ) {
            case 'initialize':
                return $this->handle_initialize();

            case 'tools/list':
                return $this->handle_tools_list( $key_data );

            case 'tools/call':
                return $this->handle_tool_call( $params, $key_data );

            case 'resources/list':
                return $this->handle_resources_list();

            default:
                return [ 'error' => [ 'code' => -32601, 'message' => 'Method not found' ] ];
        }
    }

    private function handle_initialize(): array {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => [
                'tools'     => [ 'listChanged' => false ],
                'resources' => [ 'listChanged' => false ],
            ],
            'serverInfo'      => [
                'name'    => 'AgentPress',
                'version' => AGENTPRESS_VERSION,
            ],
        ];
    }

    private function handle_tools_list( array $key_data ): array {
        $registry = new Tool_Registry();
        $tools    = $registry->get_available_tools( $key_data );

        return [ 'tools' => $tools ];
    }

    private function handle_tool_call( array $params, array $key_data ): array {
        $tool_name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if ( empty( $tool_name ) || ! is_string( $tool_name ) ) {
            return [ 'error' => [ 'code' => -32602, 'message' => 'Missing tool name' ] ];
        }

        if ( ! is_array( $arguments ) ) {
            return [ 'error' => [ 'code' => -32602, 'message' => 'Arguments must be an object' ] ];
        }

        $tool_name = sanitize_text_field( $tool_name );

        // Fire before hook
        Hooks::before_tool_call( $tool_name, $arguments, $key_data );

        $registry = new Tool_Registry();
        $result   = $registry->execute( $tool_name, $arguments, $key_data );

        // Fire after hook
        Hooks::after_tool_call( $tool_name, $arguments, $result, $key_data );

        // Webhook notifications
        Webhooks::maybe_fire( $tool_name, $arguments, $result, $key_data );

        // Log the call
        $log_params = $arguments;
        $log_json   = wp_json_encode( $log_params );
        if ( strlen( $log_json ) > 1024 ) {
            $log_params = [ '_truncated' => true, '_size' => strlen( $log_json ) ];
        }

        Audit_Log::log( $key_data['id'], $tool_name, $log_params, $result );

        return $result;
    }

    /**
     * Handle resources/list MCP method.
     * Returns site info, post types, taxonomies, and installed plugins.
     */
    private function handle_resources_list(): array {
        global $wp_version;

        $resources = [];

        // Site info
        $resources[] = [
            'uri'         => 'site://info',
            'name'        => 'Site Information',
            'description' => 'Basic WordPress site information',
            'mimeType'    => 'application/json',
            'metadata'    => [
                'name'       => get_bloginfo( 'name' ),
                'url'        => home_url(),
                'wp_version' => $wp_version,
                'php_version' => PHP_VERSION,
                'timezone'   => wp_timezone_string(),
            ],
        ];

        // Post types
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_list    = [];
        foreach ( $post_types as $pt ) {
            $pt_list[] = [
                'name'   => $pt->name,
                'label'  => $pt->label,
                'public' => $pt->public,
            ];
        }
        $resources[] = [
            'uri'         => 'site://post-types',
            'name'        => 'Post Types',
            'description' => 'Available public post types',
            'mimeType'    => 'application/json',
            'metadata'    => $pt_list,
        ];

        // Taxonomies
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        $tax_list   = [];
        foreach ( $taxonomies as $tax ) {
            $tax_list[] = [
                'name'   => $tax->name,
                'label'  => $tax->label,
                'public' => $tax->public,
            ];
        }
        $resources[] = [
            'uri'         => 'site://taxonomies',
            'name'        => 'Taxonomies',
            'description' => 'Available public taxonomies',
            'mimeType'    => 'application/json',
            'metadata'    => $tax_list,
        ];

        // Installed plugins
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins     = get_plugins();
        $plugin_list = [];
        foreach ( $plugins as $file => $data ) {
            $plugin_list[] = [
                'name'    => $data['Name'],
                'version' => $data['Version'],
                'active'  => is_plugin_active( $file ),
            ];
        }
        $resources[] = [
            'uri'         => 'site://plugins',
            'name'        => 'Installed Plugins',
            'description' => 'List of installed WordPress plugins',
            'mimeType'    => 'application/json',
            'metadata'    => $plugin_list,
        ];

        return [ 'resources' => $resources ];
    }

    private function send_event( string $event, string $data ): void {
        // Escape newlines to prevent SSE event injection
        $event = str_replace( [ "\r", "\n" ], '', $event );
        $data  = str_replace( [ "\r\n", "\r", "\n" ], '\\n', $data );

        echo "event: {$event}\n";
        echo "data: {$data}\n\n";

        if ( ob_get_level() ) {
            ob_flush();
        }
        flush();
    }
}
