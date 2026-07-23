<?php

namespace AgentPress\Admin;

use AgentPress\Auth;
use AgentPress\Audit_Log;
use AgentPress\Discovery;
use AgentPress\Webhooks;

class Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            'AgentPress',
            'AgentPress',
            'manage_options',
            'agentpress',
            [ $this, 'render_dashboard_page' ],
            'dashicons-rest-api',
            80
        );

        add_submenu_page(
            'agentpress',
            __( 'Dashboard', 'agentpress' ),
            __( 'Dashboard', 'agentpress' ),
            'manage_options',
            'agentpress',
            [ $this, 'render_dashboard_page' ]
        );

        add_submenu_page(
            'agentpress',
            __( 'Chaves de API', 'agentpress' ),
            __( 'Chaves de API', 'agentpress' ),
            'manage_options',
            'agentpress-keys',
            [ $this, 'render_keys_page' ]
        );

        add_submenu_page(
            'agentpress',
            __( 'Log de Auditoria', 'agentpress' ),
            __( 'Log de Auditoria', 'agentpress' ),
            'manage_options',
            'agentpress-logs',
            [ $this, 'render_logs_page' ]
        );

        add_submenu_page(
            'agentpress',
            __( 'Configurações', 'agentpress' ),
            __( 'Configurações', 'agentpress' ),
            'manage_options',
            'agentpress-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function handle_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Create new key
        if ( isset( $_POST['agentpress_create_key'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'agentpress_create_key' ) ) {
            $this->create_key();
        }

        // Delete key
        if ( isset( $_GET['agentpress_delete_key'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'agentpress_delete_key' ) ) {
            $this->delete_key( (int) $_GET['agentpress_delete_key'] );
        }

        // Toggle key active/inactive
        if ( isset( $_GET['agentpress_toggle_key'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'agentpress_toggle_key' ) ) {
            $this->toggle_key( (int) $_GET['agentpress_toggle_key'] );
        }

        // Save settings
        if ( isset( $_POST['agentpress_save_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'agentpress_save_settings' ) ) {
            $this->save_settings();
        }

        // Regenerate emergency token
        if ( isset( $_POST['agentpress_regen_emergency'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'agentpress_regen_emergency' ) ) {
            $this->regenerate_emergency_token();
        }
    }

    private function create_key(): void {
        global $wpdb;

        $name        = sanitize_text_field( $_POST['key_name'] ?? 'Unnamed Key' );
        $rate_limit  = min( max( (int) ( $_POST['rate_limit'] ?? 60 ), 1 ), 1000 );
        $permissions = $this->parse_permissions_from_post();
        $expires_at  = ! empty( $_POST['expires_at'] ) ? sanitize_text_field( $_POST['expires_at'] ) . ' 23:59:59' : null;

        $api_key  = Auth::generate_key();
        $key_hash = Auth::hash_key( $api_key );

        // Store key partially masked in DB + full hash for lookup
        $masked_key = substr( $api_key, 0, 12 ) . str_repeat( '*', 36 );

        $wpdb->insert(
            $wpdb->prefix . 'agentpress_keys',
            [
                'name'        => $name,
                'api_key'     => $masked_key,
                'key_hash'    => $key_hash,
                'permissions' => wp_json_encode( $permissions ),
                'rate_limit'  => $rate_limit,
                'is_active'   => 1,
                'expires_at'  => $expires_at,
                'created_at'  => current_time( 'mysql' ),
            ]
        );

        // Store key temporarily with unique ID
        $transient_id = 'agentpress_new_key_' . get_current_user_id() . '_' . time();
        set_transient( $transient_id, $api_key, 120 );

        wp_redirect( admin_url( 'admin.php?page=agentpress-keys&created=1&tkid=' . urlencode( $transient_id ) ) );
        exit;
    }

    private function delete_key( int $id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'agentpress_keys', [ 'id' => $id ] );
        wp_redirect( admin_url( 'admin.php?page=agentpress-keys&deleted=1' ) );
        exit;
    }

    private function toggle_key( int $id ): void {
        global $wpdb;

        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active FROM {$wpdb->prefix}agentpress_keys WHERE id = %d",
                $id
            )
        );

        if ( $current === null ) {
            wp_redirect( admin_url( 'admin.php?page=agentpress-keys' ) );
            exit;
        }

        $new_status = (int) $current === 1 ? 0 : 1;

        $wpdb->update(
            $wpdb->prefix . 'agentpress_keys',
            [ 'is_active' => $new_status ],
            [ 'id' => $id ]
        );

        wp_redirect( admin_url( 'admin.php?page=agentpress-keys&toggled=1' ) );
        exit;
    }

    private function save_settings(): void {
        $allowed_tables = array_filter(
            array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $_POST['allowed_tables'] ?? '' ) ) )
        );
        update_option( 'agentpress_allowed_tables', $allowed_tables );

        $blocked_columns = array_filter(
            array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $_POST['blocked_columns'] ?? '' ) ) )
        );
        update_option( 'agentpress_blocked_columns', $blocked_columns );

        // Webhooks
        if ( isset( $_POST['webhooks'] ) ) {
            $webhook_urls = array_filter(
                array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", $_POST['webhooks'] ) ) )
            );
            Webhooks::save_urls( $webhook_urls );
        }

        // SSE & Rate Limit settings
        $sse_max = absint( $_POST['sse_max_connections'] ?? 3 );
        update_option( 'agentpress_sse_max_connections', $sse_max );

        $sse_idle = absint( $_POST['sse_idle_timeout'] ?? 300 );
        if ( $sse_idle < 60 ) $sse_idle = 60; // minimum 1 minute
        update_option( 'agentpress_sse_idle_timeout', $sse_idle );

        wp_redirect( admin_url( 'admin.php?page=agentpress-settings&saved=1' ) );
        exit;
    }

    private function regenerate_emergency_token(): void {
        $token = 'apk_' . bin2hex( random_bytes( 24 ) );
        update_option( 'agentpress_emergency_hash', hash( 'sha256', $token ) );
        set_transient( 'agentpress_emergency_token_show', $token, 300 );

        wp_redirect( admin_url( 'admin.php?page=agentpress-settings' ) );
        exit;
    }

    private function parse_permissions_from_post(): array {
        $permissions = [];

        // Posts permissions
        if ( ! empty( $_POST['perm_posts'] ) ) {
            $permissions['posts'] = array_map( 'sanitize_text_field', (array) $_POST['perm_posts'] );
        }

        // Users permissions
        if ( ! empty( $_POST['perm_users'] ) ) {
            $permissions['users'] = array_map( 'sanitize_text_field', (array) $_POST['perm_users'] );
        }

        // WooCommerce permissions
        if ( ! empty( $_POST['perm_woocommerce'] ) ) {
            $permissions['woocommerce'] = array_map( 'sanitize_text_field', (array) $_POST['perm_woocommerce'] );
        }

        // Custom tools permissions
        if ( ! empty( $_POST['perm_custom'] ) ) {
            $permissions['custom'] = array_map( 'sanitize_text_field', (array) $_POST['perm_custom'] );
        }

        // Database permissions
        if ( ! empty( $_POST['perm_database'] ) ) {
            $db_tables = array_filter(
                array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $_POST['perm_db_tables'] ?? '' ) ) )
            );
            $db_ops = array_map( 'sanitize_text_field', (array) ( $_POST['perm_db_ops'] ?? [] ) );

            $valid_ops = [ 'read', 'create', 'write' ];
            $db_ops    = array_intersect( $db_ops, $valid_ops );

            $permissions['database'] = [
                'tables'     => $db_tables,
                'operations' => array_values( $db_ops ),
            ];
        }

        return $permissions;
    }

    public function render_dashboard_page(): void {
        include AGENTPRESS_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }

    public function render_keys_page(): void {
        global $wpdb;

        $keys = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}agentpress_keys ORDER BY created_at DESC", ARRAY_A );

        // Get new key from transient
        $new_key = null;
        if ( isset( $_GET['tkid'] ) ) {
            $transient_id = sanitize_text_field( $_GET['tkid'] );
            $new_key      = get_transient( $transient_id );
            if ( $new_key ) {
                delete_transient( $transient_id );
            }
        }

        $sse_url = rest_url( 'agentpress/v1/sse' );

        include AGENTPRESS_PLUGIN_DIR . 'includes/admin/views/keys.php';
    }

    public function render_logs_page(): void {
        global $wpdb;

        // Get filter params
        $filter_key_id   = isset( $_GET['filter_key_id'] ) ? (int) $_GET['filter_key_id'] : 0;
        $filter_tool     = isset( $_GET['filter_tool'] ) ? sanitize_text_field( $_GET['filter_tool'] ) : '';
        $filter_date_from = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
        $filter_date_to   = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';

        // Pagination
        $per_page     = 20;
        $current_page = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
        $offset       = ( $current_page - 1 ) * $per_page;

        $filters = [
            'key_id'    => $filter_key_id,
            'tool'      => $filter_tool,
            'date_from' => $filter_date_from,
            'date_to'   => $filter_date_to,
            'per_page'  => $per_page,
            'offset'    => $offset,
        ];

        $logs        = Audit_Log::get_filtered( $filters );
        $total_count = Audit_Log::get_filtered_count( $filters );
        $total_pages = (int) ceil( $total_count / $per_page );

        // Get keys for dropdown
        $all_keys = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}agentpress_keys ORDER BY name ASC", ARRAY_A );

        include AGENTPRESS_PLUGIN_DIR . 'includes/admin/views/logs.php';
    }

    public function render_settings_page(): void {
        $allowed_tables  = get_option( 'agentpress_allowed_tables', [] );
        $blocked_columns = get_option( 'agentpress_blocked_columns', [ 'user_pass', 'user_activation_key', 'session_tokens' ] );
        $webhook_urls    = Webhooks::get_urls();
        $detected        = Discovery::get_detected();

        // SSE settings
        $sse_max_connections = (int) get_option( 'agentpress_sse_max_connections', 3 );
        $sse_idle_timeout    = (int) get_option( 'agentpress_sse_idle_timeout', 300 );

        include AGENTPRESS_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }
}
