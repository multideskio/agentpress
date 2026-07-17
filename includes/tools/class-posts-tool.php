<?php

namespace AgentPress\Tools;

class Posts_Tool {

    /** Post types that should never be accessible via agents. */
    private const BLOCKED_POST_TYPES = [
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'wp_font_family',
        'wp_font_face',
    ];

    /** Meta keys that should never be writable by agents. */
    private const BLOCKED_META_KEYS_PREFIX = [
        '_edit_',
        '_wp_old_',
        '_wp_trash_',
        '_wp_page_template', // Can affect rendering/security
    ];

    public function handles( string $tool_name ): bool {
        return in_array( $tool_name, [ 'posts_list', 'posts_get', 'posts_create', 'posts_update', 'posts_delete' ], true );
    }

    public function get_required_action( string $tool_name ): string {
        return match( $tool_name ) {
            'posts_create' => 'create',
            'posts_update' => 'write',
            'posts_delete' => 'write',
            default        => 'read',
        };
    }

    private function is_meta_key_allowed( string $key ): bool {
        foreach ( self::BLOCKED_META_KEYS_PREFIX as $prefix ) {
            if ( str_starts_with( $key, $prefix ) ) {
                return false;
            }
        }
        return true;
    }

    private function is_post_type_allowed( string $post_type ): bool {
        if ( in_array( $post_type, self::BLOCKED_POST_TYPES, true ) ) {
            return false;
        }

        // Must be a registered post type
        $obj = get_post_type_object( $post_type );
        return $obj !== null;
    }

    public function get_definitions( array $key_data ): array {
        $tools = [];

        $tools[] = [
            'name'        => 'posts_list',
            'description' => 'List WordPress posts, pages, or custom post types with filters. Use post_type "page" to list pages.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [
                        'type'        => 'string',
                        'description' => 'Post type to query (post, page, product, or any CPT). Default: post',
                    ],
                    'status' => [
                        'type'        => 'string',
                        'description' => 'Post status filter (publish, draft, pending, private, any). Default: any',
                    ],
                    'search' => [
                        'type'        => 'string',
                        'description' => 'Search term to filter by title/content.',
                    ],
                    'per_page' => [
                        'type'        => 'integer',
                        'description' => 'Number of results per page. Max 100. Default: 20',
                    ],
                    'page' => [
                        'type'        => 'integer',
                        'description' => 'Page number. Default: 1',
                    ],
                    'orderby' => [
                        'type'        => 'string',
                        'description' => 'Order by field (date, title, modified, ID). Default: date',
                    ],
                    'order' => [
                        'type'        => 'string',
                        'description' => 'Sort order (ASC, DESC). Default: DESC',
                    ],
                ],
            ],
        ];

        $tools[] = [
            'name'        => 'posts_get',
            'description' => 'Get a single post or page by ID with full content and meta.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'id' => [
                        'type'        => 'integer',
                        'description' => 'Post or page ID to retrieve.',
                    ],
                    'format' => [
                        'type'        => 'string',
                        'description' => 'Content format: "raw" (default, includes Gutenberg blocks/HTML) or "clean" (plain text, no HTML/blocks).',
                    ],
                ],
                'required' => [ 'id' ],
            ],
        ];

        if ( \AgentPress\Auth::can( $key_data, 'posts', 'create' ) ) {
            $tools[] = [
                'name'        => 'posts_create',
                'description' => 'Create a new post, page, or custom post type.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'title'     => [ 'type' => 'string', 'description' => 'Post title.' ],
                        'content'   => [ 'type' => 'string', 'description' => 'Post content (HTML).' ],
                        'status'    => [ 'type' => 'string', 'description' => 'Post status (draft, publish, pending). Default: draft' ],
                        'post_type' => [ 'type' => 'string', 'description' => 'Post type. Default: post' ],
                        'meta'      => [ 'type' => 'object', 'description' => 'Post meta as key-value pairs.' ],
                    ],
                    'required' => [ 'title' ],
                ],
            ];
        }

        if ( \AgentPress\Auth::can( $key_data, 'posts', 'write' ) ) {
            $tools[] = [
                'name'        => 'posts_update',
                'description' => 'Update an existing post/page by ID.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'      => [ 'type' => 'integer', 'description' => 'Post ID to update.' ],
                        'title'   => [ 'type' => 'string', 'description' => 'New title.' ],
                        'content' => [ 'type' => 'string', 'description' => 'New content (HTML).' ],
                        'status'  => [ 'type' => 'string', 'description' => 'New status.' ],
                        'meta'    => [ 'type' => 'object', 'description' => 'Meta to update as key-value pairs.' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ];

            $tools[] = [
                'name'        => 'posts_delete',
                'description' => 'Delete a post by ID (moves to trash).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'    => [ 'type' => 'integer', 'description' => 'Post ID to delete.' ],
                        'force' => [ 'type' => 'boolean', 'description' => 'Skip trash and delete permanently. Default: false' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ];
        }

        return $tools;
    }

    public function execute( string $tool_name, array $args, array $key_data ): array {
        return match( $tool_name ) {
            'posts_list'   => $this->list_posts( $args ),
            'posts_get'    => $this->get_post( $args ),
            'posts_create' => $this->create_post( $args ),
            'posts_update' => $this->update_post( $args ),
            'posts_delete' => $this->delete_post( $args ),
            default        => [ 'content' => [ [ 'type' => 'text', 'text' => 'Unknown action' ] ], 'isError' => true ],
        };
    }

    private function list_posts( array $args ): array {
        $post_type = sanitize_text_field( $args['post_type'] ?? 'post' );

        if ( ! $this->is_post_type_allowed( $post_type ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => "Post type '{$post_type}' is not accessible" ] ], 'isError' => true ];
        }

        // Validate orderby against allowed values
        $allowed_orderby = [ 'date', 'title', 'modified', 'ID', 'author', 'name', 'rand', 'menu_order' ];
        $orderby = sanitize_text_field( $args['orderby'] ?? 'date' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'date';
        }

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => sanitize_text_field( $args['status'] ?? 'any' ),
            'posts_per_page' => min( (int) ( $args['per_page'] ?? 20 ), 100 ),
            'paged'          => max( (int) ( $args['page'] ?? 1 ), 1 ),
            'orderby'        => $orderby,
            'order'          => in_array( strtoupper( $args['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ] ) ? strtoupper( $args['order'] ) : 'DESC',
        ];

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        $query = new \WP_Query( $query_args );
        $posts = [];

        foreach ( $query->posts as $post ) {
            $posts[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'status'    => $post->post_status,
                'type'      => $post->post_type,
                'date'      => $post->post_date,
                'modified'  => $post->post_modified,
                'excerpt'   => wp_trim_words( $post->post_content, 30 ),
                'permalink' => get_permalink( $post->ID ),
            ];
        }

        $text = sprintf(
            "Found %d posts (page %d of %d):\n\n%s",
            $query->found_posts,
            $query_args['paged'],
            $query->max_num_pages,
            wp_json_encode( $posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
        );

        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function get_post( array $args ): array {
        $post = get_post( (int) ( $args['id'] ?? 0 ) );
        if ( ! $post ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Post not found' ] ], 'isError' => true ];
        }

        if ( ! $this->is_post_type_allowed( $post->post_type ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'This post type is not accessible' ] ], 'isError' => true ];
        }

        $format = sanitize_text_field( $args['format'] ?? 'raw' );

        // Process content based on format
        if ( $format === 'clean' ) {
            $content = $post->post_content;
            // Remove Gutenberg block comments
            $content = preg_replace( '/<!--\s*\/?wp:\S.*?-->/s', '', $content );
            // Apply WordPress content filters (shortcodes, embeds)
            $content = apply_filters( 'the_content', $content );
            // Strip all HTML tags
            $content = wp_strip_all_tags( $content );
            // Clean up whitespace
            $content = preg_replace( '/\n{3,}/', "\n\n", $content );
            $content = trim( $content );
        } else {
            $content = $post->post_content;
        }

        // Filter meta — remove internal/sensitive keys
        $raw_meta = get_post_meta( $post->ID );
        $safe_meta = [];
        foreach ( $raw_meta as $key => $values ) {
            if ( $this->is_meta_key_allowed( $key ) ) {
                $safe_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
            }
        }

        $data = [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'content'   => $content,
            'excerpt'   => $post->post_excerpt,
            'status'    => $post->post_status,
            'type'      => $post->post_type,
            'date'      => $post->post_date,
            'modified'  => $post->post_modified,
            'author'    => get_the_author_meta( 'display_name', $post->post_author ),
            'permalink' => get_permalink( $post->ID ),
            'meta'      => $safe_meta,
        ];

        return [ 'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] ] ];
    }

    private function create_post( array $args ): array {
        $post_type = sanitize_text_field( $args['post_type'] ?? 'post' );

        if ( ! $this->is_post_type_allowed( $post_type ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => "Post type '{$post_type}' is not allowed" ] ], 'isError' => true ];
        }

        // Input length limits
        if ( isset( $args['title'] ) && strlen( $args['title'] ) > 1000 ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Title too long (max 1000 chars)' ] ], 'isError' => true ];
        }
        if ( isset( $args['content'] ) && strlen( $args['content'] ) > 500000 ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Content too long (max 500KB)' ] ], 'isError' => true ];
        }

        // Validate status — only safe statuses allowed
        $allowed_statuses = [ 'draft', 'pending', 'publish', 'private' ];
        $status = sanitize_text_field( $args['status'] ?? 'draft' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'draft';
        }

        $post_data = [
            'post_title'   => sanitize_text_field( $args['title'] ?? '' ),
            'post_content' => wp_kses_post( $args['content'] ?? '' ),
            'post_status'  => $status,
            'post_type'    => $post_type,
        ];

        if ( empty( $post_data['post_title'] ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Title is required' ] ], 'isError' => true ];
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Error: ' . $post_id->get_error_message() ] ], 'isError' => true ];
        }

        // Set meta — with validation
        if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
            foreach ( $args['meta'] as $key => $value ) {
                $key = sanitize_key( $key );
                if ( ! $this->is_meta_key_allowed( $key ) ) continue;
                if ( ! is_scalar( $value ) ) continue;
                update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) );
            }
        }

        return [ 'content' => [ [ 'type' => 'text', 'text' => "Post created successfully. ID: {$post_id}" ] ] ];
    }

    private function update_post( array $args ): array {
        $post_id = (int) ( $args['id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Post not found' ] ], 'isError' => true ];
        }

        if ( ! $this->is_post_type_allowed( $post->post_type ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'This post type is not accessible' ] ], 'isError' => true ];
        }

        $post_data = [ 'ID' => $post_id ];

        if ( isset( $args['title'] ) ) $post_data['post_title'] = sanitize_text_field( $args['title'] );
        if ( isset( $args['content'] ) ) $post_data['post_content'] = wp_kses_post( $args['content'] );

        if ( isset( $args['status'] ) ) {
            $allowed_statuses = [ 'draft', 'pending', 'publish', 'private', 'trash' ];
            $status = sanitize_text_field( $args['status'] );
            if ( in_array( $status, $allowed_statuses, true ) ) {
                $post_data['post_status'] = $status;
            }
        }

        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Error: ' . $result->get_error_message() ] ], 'isError' => true ];
        }

        // Update meta — with validation
        if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
            foreach ( $args['meta'] as $key => $value ) {
                $key = sanitize_key( $key );
                if ( ! $this->is_meta_key_allowed( $key ) ) continue;
                if ( ! is_scalar( $value ) ) continue;
                update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) );
            }
        }

        return [ 'content' => [ [ 'type' => 'text', 'text' => "Post {$post_id} updated successfully." ] ] ];
    }

    private function delete_post( array $args ): array {
        $post_id = (int) ( $args['id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Post not found' ] ], 'isError' => true ];
        }

        if ( ! $this->is_post_type_allowed( $post->post_type ) ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'This post type is not accessible' ] ], 'isError' => true ];
        }

        $force  = ! empty( $args['force'] );
        $result = wp_delete_post( $post_id, $force );

        if ( ! $result ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Error: Could not delete post' ] ], 'isError' => true ];
        }

        $action = $force ? 'permanently deleted' : 'moved to trash';
        return [ 'content' => [ [ 'type' => 'text', 'text' => "Post {$post_id} {$action}." ] ] ];
    }
}
