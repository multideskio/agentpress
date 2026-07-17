<?php

namespace AgentPress;

class Installer {

    public static function activate(): void {
        self::create_tables();
        self::set_default_options();
    }

    public static function deactivate(): void {
        delete_transient( 'agentpress_activation' );
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentpress_keys (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                api_key VARCHAR(64) NOT NULL,
                key_hash VARCHAR(64) NOT NULL DEFAULT '',
                permissions LONGTEXT NOT NULL,
                rate_limit INT UNSIGNED NOT NULL DEFAULT 60,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY api_key (api_key),
                KEY key_hash (key_hash),
                KEY is_active (is_active)
            ) {$charset};

            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentpress_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                key_id BIGINT UNSIGNED NOT NULL,
                tool VARCHAR(100) NOT NULL,
                action VARCHAR(50) NOT NULL,
                params LONGTEXT DEFAULT NULL,
                result_summary VARCHAR(500) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY key_id (key_id),
                KEY created_at (created_at)
            ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function set_default_options(): void {
        $defaults = [
            'agentpress_version'         => AGENTPRESS_VERSION,
            'agentpress_rate_limit'      => 60,
            'agentpress_allowed_tables'  => [],
            'agentpress_blocked_columns' => [
                'user_pass',
                'user_activation_key',
                'session_tokens',
                'meta_value', // wp_usermeta can contain session data
            ],
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }
}
