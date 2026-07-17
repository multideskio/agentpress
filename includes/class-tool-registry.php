<?php

namespace AgentPress;

use AgentPress\Tools\Posts_Tool;
use AgentPress\Tools\Database_Tool;
use AgentPress\Tools\Users_Tool;
use AgentPress\Tools\WooCommerce_Tool;

class Tool_Registry {

    private array $tools = [];

    public function __construct() {
        $this->register_core_tools();
    }

    private function register_core_tools(): void {
        $this->tools['posts']    = new Posts_Tool();
        $this->tools['users']    = new Users_Tool();
        $this->tools['database'] = new Database_Tool();

        // WooCommerce tools only if active
        if ( class_exists( 'WooCommerce' ) ) {
            $this->tools['woocommerce'] = new WooCommerce_Tool();
        }
    }

    /**
     * Get all tool names (core + custom) for health check.
     *
     * @return string[]
     */
    public function get_all_tool_names(): array {
        $names = array_keys( $this->tools );

        foreach ( Custom_Tools::get_all() as $name => $tool ) {
            $names[] = $name;
        }

        return $names;
    }

    /**
     * Get all tools available for a given key.
     */
    public function get_available_tools( array $key_data ): array {
        $available = [];

        // Core tools
        foreach ( $this->tools as $name => $tool ) {
            if ( Auth::can( $key_data, $name, 'read' ) || Auth::can( $key_data, $name, 'write' ) || Auth::can( $key_data, $name, 'create' ) ) {
                $tool_definitions = $tool->get_definitions( $key_data );
                foreach ( $tool_definitions as $def ) {
                    $available[] = $def;
                }
            }
        }

        // Custom tools (use 'custom' permission group)
        if ( Auth::can( $key_data, 'custom', 'read' ) || Auth::can( $key_data, 'custom', 'write' ) || Auth::can( $key_data, 'custom', 'create' ) ) {
            $custom_defs = Custom_Tools::get_definitions();
            foreach ( $custom_defs as $def ) {
                $available[] = $def;
            }
        }

        return $available;
    }

    /**
     * Execute a tool by name.
     * Permission checks happen INSIDE each tool after table name resolution,
     * ensuring the resolved name is used for authorization (not raw user input).
     */
    public function execute( string $tool_name, array $arguments, array $key_data ): array {
        // Check custom tools first
        if ( Custom_Tools::has( $tool_name ) ) {
            // Check 'custom' permission group
            if ( ! Auth::can( $key_data, 'custom', 'read' ) && ! Auth::can( $key_data, 'custom', 'write' ) && ! Auth::can( $key_data, 'custom', 'create' ) ) {
                return [
                    'content' => [
                        [ 'type' => 'text', 'text' => "Permission denied: you don't have access to custom tools" ],
                    ],
                    'isError' => true,
                ];
            }

            return Custom_Tools::execute( $tool_name, $arguments, $key_data );
        }

        // Core tools
        foreach ( $this->tools as $group_name => $tool ) {
            if ( $tool->handles( $tool_name ) ) {
                // Check group-level permission
                $action = $tool->get_required_action( $tool_name );
                if ( ! Auth::can( $key_data, $group_name, $action ) ) {
                    return [
                        'content' => [
                            [ 'type' => 'text', 'text' => "Permission denied: you don't have '{$action}' access to '{$group_name}'" ],
                        ],
                        'isError' => true,
                    ];
                }

                return $tool->execute( $tool_name, $arguments, $key_data );
            }
        }

        return [
            'content' => [
                [ 'type' => 'text', 'text' => "Unknown tool: {$tool_name}" ],
            ],
            'isError' => true,
        ];
    }
}
