<?php
/**
 * Plugin Name: AgentPress
 * Plugin URI: https://github.com/SEU-USUARIO/agentpress
 * Description: MCP (Model Context Protocol) server for WordPress with granular database access. Give AI agents controlled access to posts, products, orders, and any database table.
 * Version: 0.1.0
 * Author: 
 * Author URI: 
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: agentpress
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// EMERGENCY KILL SWITCH
// Este bloco roda ANTES de qualquer classe/autoload do plugin.
// Se o plugin quebrar o WordPress, acesse:
//   https://seusite.com/?agentpress_kill=SEU_TOKEN_DE_EMERGENCIA
// Isso desativa o plugin imediatamente.
// ─────────────────────────────────────────────────────────────────────────────
if ( isset( $_GET['agentpress_kill'] ) ) {
    $kill_token = sanitize_text_field( $_GET['agentpress_kill'] );

    if ( strlen( $kill_token ) >= 20 ) {
        global $wpdb;

        // Busca o hash do token de emergência diretamente no banco (sem usar classes)
        $stored_hash = $wpdb->get_var(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'agentpress_emergency_hash' LIMIT 1"
        );

        if ( $stored_hash && hash_equals( $stored_hash, hash( 'sha256', $kill_token ) ) ) {
            // Token válido — desativar o plugin
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins( plugin_basename( __FILE__ ) );

            // Limpar flag de erro se existir
            if ( function_exists( 'wp_redirect' ) ) {
                wp_redirect( home_url( '/?agentpress_deactivated=1' ) );
                exit;
            } else {
                header( 'Location: ' . home_url( '/?agentpress_deactivated=1' ) );
                exit;
            }
        }
    }

    // Token inválido — não revelar nada, simplesmente continua
}

// Mostrar confirmação de desativação no front (uma vez)
if ( isset( $_GET['agentpress_deactivated'] ) && $_GET['agentpress_deactivated'] === '1' ) {
    add_action( 'wp_head', function () {
        echo '<style>#agentpress-kill-notice{position:fixed;top:0;left:0;right:0;background:#dc3232;color:#fff;padding:15px;text-align:center;z-index:999999;font-family:sans-serif;font-size:16px;}</style>';
    });
    add_action( 'wp_body_open', function () {
        echo '<div id="agentpress-kill-notice">⚠️ AgentPress foi desativado via token de emergência. Acesse o wp-admin para reativar.</div>';
    });
}
// ─────────────────────────────────────────────────────────────────────────────

define( 'AGENTPRESS_VERSION', '0.1.0' );
define( 'AGENTPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix = 'AgentPress\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $parts    = explode( '\\', $relative );
    $filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

    $subdir = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';
    $file   = AGENTPRESS_PLUGIN_DIR . 'includes/' . $subdir . $filename;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

// Load translations
add_action( 'init', function () {
    load_plugin_textdomain( 'agentpress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});

// Boot plugin
add_action( 'plugins_loaded', function () {
    AgentPress\Plugin::instance();
});

// Activation — gera token de emergência
register_activation_hook( __FILE__, function () {
    AgentPress\Installer::activate();

    // Gerar token de emergência se não existir
    if ( get_option( 'agentpress_emergency_hash' ) === false ) {
        $token = 'apk_' . bin2hex( random_bytes( 24 ) );
        update_option( 'agentpress_emergency_hash', hash( 'sha256', $token ) );
        // Guarda temporariamente para mostrar ao admin uma vez
        set_transient( 'agentpress_emergency_token_show', $token, 300 );
    }
});

// Deactivation
register_deactivation_hook( __FILE__, function () {
    AgentPress\Installer::deactivate();
});
