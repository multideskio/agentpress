<?php

namespace AgentPress\Tools;

class Users_Tool {

    public function handles( string $tool_name ): bool {
        return in_array( $tool_name, [ 'users_list', 'users_get' ], true );
    }

    public function get_required_action( string $tool_name ): string {
        return 'read';
    }

    public function get_definitions( array $key_data ): array {
        return [
            [
                'name'        => 'users_list',
                'description' => 'List WordPress users with role and metadata.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'role' => [
                            'type'        => 'string',
                            'description' => 'Filter by role (administrator, editor, author, subscriber, customer). Default: all',
                        ],
                        'search' => [
                            'type'        => 'string',
                            'description' => 'Search by name or email.',
                        ],
                        'per_page' => [
                            'type'        => 'integer',
                            'description' => 'Results per page. Max 100. Default: 20',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'users_get',
                'description' => 'Get a single user by ID with profile info and meta (passwords are never exposed).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => [ 'type' => 'integer', 'description' => 'User ID.' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ],
        ];
    }

    public function execute( string $tool_name, array $args, array $key_data ): array {
        return match( $tool_name ) {
            'users_list' => $this->list_users( $args ),
            'users_get'  => $this->get_user( $args ),
            default      => [ 'content' => [ [ 'type' => 'text', 'text' => 'Unknown action' ] ], 'isError' => true ],
        };
    }

    private function list_users( array $args ): array {
        $query_args = [
            'number' => min( (int) ( $args['per_page'] ?? 20 ), 100 ),
        ];

        if ( ! empty( $args['role'] ) ) {
            $query_args['role'] = sanitize_text_field( $args['role'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $query_args['search']         = '*' . sanitize_text_field( $args['search'] ) . '*';
            $query_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $users  = get_users( $query_args );
        $result = [];

        foreach ( $users as $user ) {
            $result[] = [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'roles'        => $user->roles,
                'registered'   => $user->user_registered,
            ];
        }

        $text = sprintf( "Found %d users:\n\n%s", count( $result ), wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function get_user( array $args ): array {
        $user = get_user_by( 'id', (int) $args['id'] );
        if ( ! $user ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'User not found' ] ], 'isError' => true ];
        }

        $data = [
            'id'           => $user->ID,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
            'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
            'roles'        => $user->roles,
            'registered'   => $user->user_registered,
            'url'          => $user->user_url,
        ];

        return [ 'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] ] ];
    }
}
