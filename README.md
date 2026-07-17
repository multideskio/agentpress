# 🤖 AgentPress

**MCP server for WordPress with direct database access.**

Give AI agents controlled access to your entire WordPress site — posts, products, orders, users, AND any database table from any plugin. With granular permissions per agent.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0-green)](LICENSE)
[![MCP](https://img.shields.io/badge/MCP-2024--11--05-orange)](https://modelcontextprotocol.io)

---

## Why AgentPress?

Other WordPress MCP plugins only expose standard WordPress APIs. AgentPress goes further:

| Others                  | AgentPress                                     |
| ----------------------- | ---------------------------------------------- |
| Only WordPress REST API | Direct database access to ANY table            |
| Limited to posts/pages  | Contact Form 7, FluentCRM, WPForms, any plugin |
| Basic permissions       | Granular: per-table, per-column, per-operation |
| Single API key          | Multi-agent with different access levels       |
| No audit trail          | Full audit log with IP tracking                |

## Quick Start

### 1. Install

Download the [latest release](https://github.com/multideskio/agentpress/releases) and upload to `wp-content/plugins/`, or:

```bash
cd wp-content/plugins/
git clone https://github.com/multideskio/agentpress.git
```

Activate the plugin in wp-admin.

### 2. Create an API Key

Go to **AgentPress > API Keys** and create a key with the permissions you need.

### 3. Connect your agent

Add to your MCP config (Kiro, Cursor, Claude Code, ChatGPT):

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://yoursite.com/wp-json/agentpress/v1/mcp",
      "headers": {
        "Authorization": "Bearer ap_YOUR_KEY_HERE"
      }
    }
  }
}
```

That's it. Your agent now has access to your WordPress site.

## Endpoints

| Endpoint                        | Method | Auth         | Use                                 |
| ------------------------------- | ------ | ------------ | ----------------------------------- |
| `/wp-json/agentpress/v1/mcp`    | POST   | Bearer token | Main MCP endpoint (Streamable HTTP) |
| `/wp-json/agentpress/v1/sse`    | GET    | Bearer token | SSE transport (requires VPS)        |
| `/wp-json/agentpress/v1/health` | GET    | None         | Health check                        |

> **Shared hosting?** Use `/mcp` (Streamable HTTP). The `/sse` endpoint requires persistent connections (VPS/dedicated server).

## Available Tools

### WordPress Native

| Tool           | Description                         | Permission |
| -------------- | ----------------------------------- | ---------- |
| `posts_list`   | List posts/pages/CPT with filters   | read       |
| `posts_get`    | Get single post with content & meta | read       |
| `posts_create` | Create new post/page                | create     |
| `posts_update` | Update existing post                | write      |
| `posts_delete` | Trash or delete post                | write      |
| `users_list`   | List users with role filter         | read       |
| `users_get`    | Get user profile & meta             | read       |

### WooCommerce (auto-detected)

| Tool                | Description              | Permission |
| ------------------- | ------------------------ | ---------- |
| `woo_orders_list`   | List orders with filters | read       |
| `woo_orders_get`    | Full order details       | read       |
| `woo_orders_update` | Update status, add notes | write      |
| `woo_products_list` | List products            | read       |
| `woo_products_get`  | Full product details     | read       |

### Database Direct Access (the differentiator)

| Tool             | Description                     | Permission |
| ---------------- | ------------------------------- | ---------- |
| `db_list_tables` | List all accessible tables      | read       |
| `db_describe`    | Show table structure            | read       |
| `db_query`       | SELECT with WHERE, ORDER, LIMIT | read       |
| `db_insert`      | Insert row into table           | create     |
| `db_update`      | Update rows with WHERE clause   | write      |

### MCP Resources

| Resource            | Description                             |
| ------------------- | --------------------------------------- |
| `site://info`       | Site name, URL, WP version, PHP version |
| `site://post-types` | Available public post types             |
| `site://taxonomies` | Available public taxonomies             |
| `site://plugins`    | Installed plugins list                  |

## Permission Examples

### Read-only agent (safe for support bots)

```json
{
  "posts": ["read"],
  "users": ["read"],
  "woocommerce": ["read"],
  "database": {
    "tables": ["wp_cf7_submissions", "wp_fluentcrm_subscribers"],
    "operations": ["read"]
  }
}
```

### Content editor agent

```json
{
  "posts": ["read", "create", "write"],
  "users": ["read"]
}
```

### Full CRM access agent

```json
{
  "posts": ["read"],
  "woocommerce": ["read", "write"],
  "database": {
    "tables": ["wp_fc_subscribers", "wp_fc_campaigns", "wp_cf7_submissions"],
    "operations": ["read", "create", "write"]
  }
}
```

## Features

- **Streamable HTTP + SSE** — works on shared hosting and VPS
- **Direct database access** — query ANY whitelisted table
- **Granular permissions** — per-key control over tables, columns, and operations
- **Blocked columns** — passwords, tokens, keys are NEVER exposed
- **Rate limiting** — per-key request limits (atomic, race-condition safe)
- **Audit log** — every tool call logged with IP, params, and result
- **Webhooks** — notify external services on write operations
- **Multi-agent** — multiple API keys with different permission levels
- **Auto-discovery** — detects CF7, FluentCRM, WPForms, GravityForms tables
- **Custom Tools API** — register your own MCP tools via PHP
- **Hooks system** — WordPress actions for extensibility
- **Emergency kill switch** — disable via URL if plugin breaks the site
- **Dashboard** — visual metrics (requests/day, top tools, errors)
- **i18n** — Portuguese (BR) included, translation-ready

## Custom Tools API

Register your own tools from any plugin or theme:

```php
add_action( 'agentpress_register_tools', function() {
    \AgentPress\Custom_Tools::register(
        'my_custom_tool',
        [
            'name'        => 'my_custom_tool',
            'description' => 'Does something custom',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'param1' => [ 'type' => 'string', 'description' => 'Input' ],
                ],
                'required' => [ 'param1' ],
            ],
        ],
        function( array $args, array $key_data ): array {
            return [
                'content' => [
                    [ 'type' => 'text', 'text' => 'Result: ' . $args['param1'] ],
                ],
            ];
        }
    );
});
```

## Hooks

```php
// Before any tool call
add_action( 'agentpress_before_tool_call', function( $tool, $args, $key_data ) {
    // Custom logic, logging, validation
}, 10, 3 );

// After any tool call
add_action( 'agentpress_after_tool_call', function( $tool, $args, $result, $key_data ) {
    // Post-processing, notifications
}, 10, 4 );

// When a key authenticates
add_action( 'agentpress_key_authenticated', function( $key_data ) {
    // Track agent activity
}, 10, 1 );
```

## Security

- **Hashed API keys** — keys stored as SHA-256 hash, never plain text
- **Constant-time comparison** — prevents timing attacks
- **Table whitelist** — only explicitly allowed tables accessible
- **Column blacklist** — sensitive columns never exposed
- **Parameterized queries** — all DB queries use prepared statements
- **Identifier validation** — regex validation on all table/column names (no SQL injection)
- **Rate limiting** — atomic increment prevents race conditions
- **Update safety** — max 50 rows per UPDATE, WHERE required
- **Input limits** — max field count, value size, LIKE pattern length
- **Post type restrictions** — internal types blocked
- **Meta key protection** — internal WP meta keys not writable
- **Audit log** — full trail with IP address
- **Emergency kill switch** — disable via secret URL without wp-admin access

## Emergency Kill Switch

If AgentPress breaks your site and you can't access wp-admin:

```
https://yoursite.com/?agentpress_kill=YOUR_EMERGENCY_TOKEN
```

Generate/view your token in **AgentPress > Settings**.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (optional, for order/product tools)

## Compatibility

| Client         | Transport                | Status          |
| -------------- | ------------------------ | --------------- |
| Kiro           | Streamable HTTP (`/mcp`) | ✅ Tested       |
| Cursor         | Streamable HTTP (`/mcp`) | ✅ Compatible   |
| Claude Code    | Streamable HTTP (`/mcp`) | ✅ Compatible   |
| Claude Desktop | SSE (`/sse`)             | ⚠️ Requires VPS |
| ChatGPT        | Streamable HTTP (`/mcp`) | ✅ Compatible   |
| Custom clients | Both transports          | ✅              |

## Contributing

PRs welcome! See issues tagged `good-first-issue`.

```bash
git clone https://github.com/multideskio/agentpress.git
cd agentpress
# Install in a local WordPress for development
```

## License

GPL-2.0-or-later
