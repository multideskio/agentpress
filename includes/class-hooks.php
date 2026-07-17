<?php

namespace AgentPress;

/**
 * Hooks system for extensibility.
 *
 * Fires WordPress actions at key lifecycle points to allow third-party integration.
 */
class Hooks {

    /**
     * Fired before a tool call is executed.
     *
     * @param string $tool_name  Tool being called.
     * @param array  $arguments  Arguments passed to the tool.
     * @param array  $key_data   Authenticated key data.
     */
    public static function before_tool_call( string $tool_name, array $arguments, array $key_data ): void {
        do_action( 'agentpress_before_tool_call', $tool_name, $arguments, $key_data );
    }

    /**
     * Fired after a tool call is executed.
     *
     * @param string $tool_name  Tool that was called.
     * @param array  $arguments  Arguments passed to the tool.
     * @param array  $result     Tool execution result.
     * @param array  $key_data   Authenticated key data.
     */
    public static function after_tool_call( string $tool_name, array $arguments, array $result, array $key_data ): void {
        do_action( 'agentpress_after_tool_call', $tool_name, $arguments, $result, $key_data );
    }

    /**
     * Fired when a key is successfully authenticated.
     *
     * @param array $key_data Authenticated key data.
     */
    public static function key_authenticated( array $key_data ): void {
        do_action( 'agentpress_key_authenticated', $key_data );
    }

    /**
     * Fired when a key hits the rate limit.
     *
     * @param array $key_data Key data that was rate limited.
     */
    public static function rate_limited( array $key_data ): void {
        do_action( 'agentpress_rate_limited', $key_data );
    }
}
