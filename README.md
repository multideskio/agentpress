# 🤖 AgentPress

**MCP server for WordPress with direct database access.**

Give AI agents controlled access to your WordPress site — posts, products, orders, users, AND any database table from any plugin. With granular permissions per agent.

## The Problem

Existing WordPress MCP plugins only expose standard WordPress APIs. But what about:

- Contact form submissions (Contact Form 7, WPForms, Gravity Forms)?
- CRM data (FluentCRM, Jetpack CRM, custom tables)?
- Custom plugin tables your business relies on?
- Legacy data in non-standard tables?

**AgentPress solves this** by giving agents direct, permission-controlled database access.

## Features

- **MCP over SSE** — standard Model Context Protocol via Server-Sent Events
- **Direct database access** — agents can query ANY whitelisted table
- **Granular permissions** — per-key control over tables, columns, and operations (read/create/edit)
- **WordPress native** — posts, pages, custom post types, users
- **WooCommerce** — orders, products, customers
- **Blocked columns** — sensitive data (passwords, tokens) is NEVER exposed
- **Rate limiting** — per-key request limits
- **Audit log** — every tool call is logged with params and result
- **Multi-agent** — multiple API keys with different permission levels

## Quick Start

1. Upload `agentpress` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **AgentPress > API Keys** in wp-admin
4. Create a key with desired permissions
5. Connect your agent to: `https://yoursite.com/wp-json/agentpress/v1/sse`

## Available Tools

### WordPress Native

| Tool           | Description                         | Permissions |
| -------------- | ----------------------------------- | ----------- |
| `posts_list`   | List posts/pages/CPT with filters   | read        |
| `posts_get`    | Get single post with content & meta | read        |
| `posts_create` | Create new post/page                | create      |
| `posts_update` | Update existing post                | write       |
| `posts_delete` | Trash or delete post                | write       |
| `users_list`   | List users with role filter         | read        |
| `users_get`    | Get user profile & meta             | read        |

### WooCommerce

| Tool                | Description              | Permissions |
| ------------------- | ------------------------ | ----------- |
| `woo_orders_list`   | List orders with filters | read        |
| `woo_orders_get`    | Full order details       | read        |
| `woo_orders_update` | Update status, add notes | write       |
| `woo_products_list` | List products            | read        |
| `woo_products_get`  | Full product details     | read        |

### Database (the differentiator)

| Tool             | Description                     | Permissions |
| ---------------- | ------------------------------- | ----------- |
| `db_list_tables` | List all accessible tables      | read        |
| `db_describe`    | Show table structure            | read        |
| `db_query`       | SELECT with WHERE, ORDER, LIMIT | read        |
| `db_insert`      | Insert row into table           | create      |
| `db_update`      | Update rows with WHERE clause   | write       |

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
    "tables": [
      "wp_fluentcrm_subscribers",
      "wp_fluentcrm_campaigns",
      "wp_cf7_submissions"
    ],
    "operations": ["read", "create", "write"]
  }
}
```

## Security

- **API key authentication** via Bearer token
- **Rate limiting** per key (configurable)
- **Table whitelist** — only explicitly allowed tables are accessible
- **Column blacklist** — passwords, tokens, keys are never exposed
- **Parameterized queries** — all DB queries use prepared statements
- **No JOINs/subqueries** — prevents complex attack vectors
- **WHERE required** for UPDATEs — no accidental mass updates
- **Full audit log** — every operation is tracked

## Connecting Agents

### Claude Desktop / Claude.ai

Add to your MCP config:

```json
{
  "mcpServers": {
    "my-wordpress": {
      "url": "https://yoursite.com/wp-json/agentpress/v1/sse",
      "headers": {
        "Authorization": "Bearer ap_your_key_here"
      }
    }
  }
}
```

### Any MCP Client

- **SSE Endpoint:** `GET /wp-json/agentpress/v1/sse`
- **Message Endpoint:** `POST /wp-json/agentpress/v1/message`
- **Auth:** `Authorization: Bearer ap_xxxxx`

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (optional, for order/product tools)

## Roadmap

- [ ] Web dashboard with real-time activity
- [ ] Webhook notifications on specific events
- [ ] Media upload tool
- [ ] Taxonomy management tool
- [ ] Custom field frameworks support (ACF, MetaBox)
- [ ] Import/export permission templates
- [ ] Multi-site support

## Contributing

PRs welcome! This is a GPL-2.0 project.

## License

GPL-2.0-or-later
