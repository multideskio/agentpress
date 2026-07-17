<?php

namespace AgentPress;

/**
 * Custom Tools API.
 *
 * Allows third-party plugins/themes to register custom MCP tools.
 *
 * Usage example:
 *
 *   add_action( 'plugins_loaded', function() {
 *       \AgentPress\Custom_Tools::register(
 *           'my_custom_tool',
 *           [
 *               'name'        => 'my_custom_tool',
 *               'description' => 'Does something custom',
 *               'inputSchema' => [
 *                   'type'       => 'object',
 *                   'properties' => [
 *                       'param1' => [ 'type' => 'string', 'description' => 'First param' ],
 *                   ],
 *                   'required' => [ 'param1' ],
 *               ],
 *           ],
 *           function( array $arguments, array $key_data ): array {
 *               return [
 *                   'content' => [
 *                       [ 'type' => 'text', 'text' => 'Result: ' . $arguments['param1'] ],
 *                   ],
 *               ];
 *           }
 *       );
 *   });
 */
class Custom_Tools {

    /**
     * Registered custom tools.
     * @var array<string, array{definition: array, callback: callable}>
     */
    private static array $tools = [];

    /**
     * Register a custom tool.
     *
     * @param string   $name       Unique tool name.
     * @param array    $definition MCP tool definition (name, description, inputSchema).
     * @param callable $callback   Callback receiving (array $arguments, array $key_data) => array.
     */
    public static function register( string $name, array $definition, callable $callback ): void {
        self::$tools[ $name ] = [
            'definition' => $definition,
            'callback'   => $callback,
        ];
    }

    /**
     * Get all registered custom tools.
     *
     * @return array<string, array{definition: array, callback: callable}>
     */
    public static function get_all(): array {
        return self::$tools;
    }

    /**
     * Check if a custom tool is registered.
     */
    public static function has( string $name ): bool {
        return isset( self::$tools[ $name ] );
    }

    /**
     * Execute a custom tool.
     *
     * @param string $name      Tool name.
     * @param array  $arguments Tool arguments.
     * @param array  $key_data  Authenticated key data.
     * @return array MCP result format.
     */
    public static function execute( string $name, array $arguments, array $key_data ): array {
        if ( ! isset( self::$tools[ $name ] ) ) {
            return [
                'content' => [
                    [ 'type' => 'text', 'text' => "Custom tool not found: {$name}" ],
                ],
                'isError' => true,
            ];
        }

        try {
            $result = call_user_func( self::$tools[ $name ]['callback'], $arguments, $key_data );

            if ( ! is_array( $result ) || ! isset( $result['content'] ) ) {
                return [
                    'content' => [
                        [ 'type' => 'text', 'text' => is_string( $result ) ? $result : wp_json_encode( $result ) ],
                    ],
                ];
            }

            return $result;
        } catch ( \Throwable $e ) {
            return [
                'content' => [
                    [ 'type' => 'text', 'text' => "Custom tool error: {$e->getMessage()}" ],
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * Get definitions for all custom tools (for tools/list).
     *
     * @return array
     */
    public static function get_definitions(): array {
        $definitions = [];

        foreach ( self::$tools as $name => $tool ) {
            $definitions[] = $tool['definition'];
        }

        return $definitions;
    }
}
