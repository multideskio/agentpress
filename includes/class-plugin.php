<?php

namespace AgentPress;

class Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // Auto-create tables if missing (fallback for manual installs)
        add_action( 'admin_init', [ $this, 'maybe_install' ] );

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );

        if ( is_admin() ) {
            new Admin\Admin_Page();
        }

        // Allow custom tools to be registered via action
        add_action( 'init', [ $this, 'fire_custom_tools_hook' ], 20 );
    }

    /**
     * Check if tables exist and create them if not.
     */
    public function maybe_install(): void {
        $installed_version = get_option( 'agentpress_version' );
        if ( $installed_version !== AGENTPRESS_VERSION ) {
            Installer::activate();
        }
    }

    public function register_routes(): void {
        $server = new MCP_Server();
        $server->register();
    }

    /**
     * Fire action for other plugins to register custom tools.
     */
    public function fire_custom_tools_hook(): void {
        do_action( 'agentpress_register_tools' );
    }
}
