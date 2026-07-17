<?php

namespace AgentPress\Tools;

class WooCommerce_Tool {

    public function handles( string $tool_name ): bool {
        return in_array( $tool_name, [ 'woo_orders_list', 'woo_orders_get', 'woo_orders_update', 'woo_products_list', 'woo_products_get' ], true );
    }

    public function get_required_action( string $tool_name ): string {
        return match( $tool_name ) {
            'woo_orders_update' => 'write',
            default             => 'read',
        };
    }

    public function get_definitions( array $key_data ): array {
        $tools = [
            [
                'name'        => 'woo_orders_list',
                'description' => 'List WooCommerce orders with filters.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status' => [
                            'type'        => 'string',
                            'description' => 'Order status (pending, processing, on-hold, completed, cancelled, refunded, failed, any). Default: any',
                        ],
                        'per_page' => [ 'type' => 'integer', 'description' => 'Results per page. Max 50. Default: 20' ],
                        'page'     => [ 'type' => 'integer', 'description' => 'Page number. Default: 1' ],
                        'customer' => [ 'type' => 'string', 'description' => 'Filter by customer email.' ],
                    ],
                ],
            ],
            [
                'name'        => 'woo_orders_get',
                'description' => 'Get a single WooCommerce order by ID with full details (items, billing, shipping).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => [ 'type' => 'integer', 'description' => 'Order ID.' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ],
            [
                'name'        => 'woo_products_list',
                'description' => 'List WooCommerce products.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'   => [ 'type' => 'string', 'description' => 'Product status (publish, draft, any). Default: publish' ],
                        'category' => [ 'type' => 'string', 'description' => 'Filter by category slug.' ],
                        'search'   => [ 'type' => 'string', 'description' => 'Search by name.' ],
                        'per_page' => [ 'type' => 'integer', 'description' => 'Results per page. Max 50. Default: 20' ],
                    ],
                ],
            ],
            [
                'name'        => 'woo_products_get',
                'description' => 'Get a single WooCommerce product by ID with price, stock, and attributes.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => [ 'type' => 'integer', 'description' => 'Product ID.' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ],
        ];

        if ( \AgentPress\Auth::can( $key_data, 'woocommerce', 'write' ) ) {
            $tools[] = [
                'name'        => 'woo_orders_update',
                'description' => 'Update a WooCommerce order status or add a note.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'     => [ 'type' => 'integer', 'description' => 'Order ID.' ],
                        'status' => [ 'type' => 'string', 'description' => 'New status (processing, completed, on-hold, cancelled).' ],
                        'note'   => [ 'type' => 'string', 'description' => 'Note to add to the order.' ],
                    ],
                    'required' => [ 'id' ],
                ],
            ];
        }

        return $tools;
    }

    public function execute( string $tool_name, array $args, array $key_data ): array {
        return match( $tool_name ) {
            'woo_orders_list'   => $this->list_orders( $args ),
            'woo_orders_get'    => $this->get_order( $args ),
            'woo_orders_update' => $this->update_order( $args ),
            'woo_products_list' => $this->list_products( $args ),
            'woo_products_get'  => $this->get_product( $args ),
            default             => [ 'content' => [ [ 'type' => 'text', 'text' => 'Unknown action' ] ], 'isError' => true ],
        };
    }

    private function list_orders( array $args ): array {
        $query_args = [
            'limit'   => min( (int) ( $args['per_page'] ?? 20 ), 50 ),
            'page'    => max( (int) ( $args['page'] ?? 1 ), 1 ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        if ( ! empty( $args['status'] ) && $args['status'] !== 'any' ) {
            $query_args['status'] = sanitize_text_field( $args['status'] );
        }

        if ( ! empty( $args['customer'] ) ) {
            $query_args['customer'] = sanitize_email( $args['customer'] );
        }

        $orders = wc_get_orders( $query_args );
        $result = [];

        foreach ( $orders as $order ) {
            $result[] = [
                'id'       => $order->get_id(),
                'status'   => $order->get_status(),
                'total'    => $order->get_total(),
                'currency' => $order->get_currency(),
                'customer' => $order->get_billing_email(),
                'date'     => $order->get_date_created()?->format( 'Y-m-d H:i:s' ),
                'items'    => $order->get_item_count(),
            ];
        }

        $text = sprintf( "Found %d orders:\n\n%s", count( $result ), wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function get_order( array $args ): array {
        $order = wc_get_order( (int) $args['id'] );
        if ( ! $order ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Order not found' ] ], 'isError' => true ];
        }

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $items[] = [
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total'    => $item->get_total(),
            ];
        }

        $data = [
            'id'              => $order->get_id(),
            'status'          => $order->get_status(),
            'total'           => $order->get_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method_title(),
            'date_created'    => $order->get_date_created()?->format( 'Y-m-d H:i:s' ),
            'billing'         => [
                'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'city'  => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
            ],
            'items'           => $items,
            'customer_note'   => $order->get_customer_note(),
        ];

        return [ 'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] ] ];
    }

    private function update_order( array $args ): array {
        $order = wc_get_order( (int) $args['id'] );
        if ( ! $order ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Order not found' ] ], 'isError' => true ];
        }

        if ( ! empty( $args['status'] ) ) {
            $allowed_statuses = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
            $status = sanitize_text_field( $args['status'] );
            if ( ! in_array( $status, $allowed_statuses, true ) ) {
                return [ 'content' => [ [ 'type' => 'text', 'text' => "Invalid order status: {$status}. Allowed: " . implode( ', ', $allowed_statuses ) ] ], 'isError' => true ];
            }
            $order->set_status( $status );
        }

        if ( ! empty( $args['note'] ) ) {
            $note = sanitize_text_field( $args['note'] );
            if ( strlen( $note ) > 1000 ) {
                $note = substr( $note, 0, 1000 );
            }
            $order->add_order_note( $note );
        }

        $order->save();

        return [ 'content' => [ [ 'type' => 'text', 'text' => "Order {$args['id']} updated." ] ] ];
    }

    private function list_products( array $args ): array {
        $query_args = [
            'status'   => sanitize_text_field( $args['status'] ?? 'publish' ),
            'limit'    => min( (int) ( $args['per_page'] ?? 20 ), 50 ),
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];

        if ( ! empty( $args['category'] ) ) {
            $query_args['category'] = [ sanitize_text_field( $args['category'] ) ];
        }

        $products = wc_get_products( $query_args );
        $result   = [];

        foreach ( $products as $product ) {
            $result[] = [
                'id'       => $product->get_id(),
                'name'     => $product->get_name(),
                'price'    => $product->get_price(),
                'stock'    => $product->get_stock_quantity(),
                'status'   => $product->get_status(),
                'type'     => $product->get_type(),
                'sku'      => $product->get_sku(),
            ];
        }

        $text = sprintf( "Found %d products:\n\n%s", count( $result ), wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        return [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
    }

    private function get_product( array $args ): array {
        $product = wc_get_product( (int) $args['id'] );
        if ( ! $product ) {
            return [ 'content' => [ [ 'type' => 'text', 'text' => 'Product not found' ] ], 'isError' => true ];
        }

        $data = [
            'id'             => $product->get_id(),
            'name'           => $product->get_name(),
            'description'    => $product->get_description(),
            'short_desc'     => $product->get_short_description(),
            'price'          => $product->get_price(),
            'regular_price'  => $product->get_regular_price(),
            'sale_price'     => $product->get_sale_price(),
            'sku'            => $product->get_sku(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => $product->get_stock_status(),
            'type'           => $product->get_type(),
            'categories'     => wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] ),
            'permalink'      => $product->get_permalink(),
        ];

        return [ 'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] ] ];
    }
}
