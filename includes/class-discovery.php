<?php

namespace AgentPress;

/**
 * Auto-discovery of popular plugin tables.
 *
 * Detects installed plugins and suggests their database tables for whitelist.
 */
class Discovery {

    /**
     * Plugin table mappings: plugin slug/class => suggested tables.
     */
    private const PLUGIN_TABLES = [
        'cfdb7' => [
            'label'     => 'Contact Form 7 (CFDB7)',
            'detect'    => 'cfdb7/cfdb7.php',
            'tables'    => [ 'db7_forms' ],
        ],
        'fluentcrm' => [
            'label'     => 'FluentCRM',
            'detect'    => 'fluent-crm/fluent-crm.php',
            'tables'    => [ 'fc_subscribers', 'fc_campaigns', 'fc_campaign_emails' ],
        ],
        'wpforms' => [
            'label'     => 'WPForms',
            'detect'    => 'wpforms-lite/wpforms.php',
            'tables'    => [ 'wpforms_entries', 'wpforms_entry_fields' ],
        ],
        'gravityforms' => [
            'label'     => 'Gravity Forms',
            'detect'    => 'gravityforms/gravityforms.php',
            'tables'    => [ 'gf_entry', 'gf_entry_meta', 'gf_form_meta' ],
        ],
        'woocommerce' => [
            'label'     => 'WooCommerce',
            'detect'    => 'woocommerce/woocommerce.php',
            'tables'    => [ 'wc_orders', 'wc_order_items', 'wc_order_stats', 'woocommerce_order_items', 'woocommerce_order_itemmeta' ],
        ],
    ];

    /**
     * Get detected plugins with their suggested tables.
     *
     * @return array<string, array{label: string, tables: string[]}>
     */
    public static function get_detected(): array {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        global $wpdb;
        $detected = [];

        foreach ( self::PLUGIN_TABLES as $slug => $config ) {
            if ( ! is_plugin_active( $config['detect'] ) ) {
                // Also try alternate detection for WPForms Pro
                if ( $slug === 'wpforms' && ! is_plugin_active( 'wpforms/wpforms.php' ) ) {
                    continue;
                } elseif ( $slug !== 'wpforms' ) {
                    continue;
                }
            }

            // Build full table names with prefix
            $tables = array_map( function ( string $table ) use ( $wpdb ): string {
                return $wpdb->prefix . $table;
            }, $config['tables'] );

            $detected[ $slug ] = [
                'label'  => $config['label'],
                'tables' => $tables,
            ];
        }

        return $detected;
    }

    /**
     * Get flat list of all suggested table names from detected plugins.
     *
     * @return string[]
     */
    public static function get_all_suggested_tables(): array {
        $tables = [];
        foreach ( self::get_detected() as $plugin ) {
            $tables = array_merge( $tables, $plugin['tables'] );
        }
        return $tables;
    }
}
