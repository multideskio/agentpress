<?php
/**
 * AgentPress Uninstall
 *
 * Removes all plugin data when uninstalled via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agentpress_keys" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agentpress_logs" );

// Delete all options starting with agentpress_
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'agentpress\_%'"
);

// Also clean up transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_agentpress\_%'"
);
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_agentpress\_%'"
);
