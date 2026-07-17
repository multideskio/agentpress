# Changelog

Todas as mudanças relevantes do AgentPress são documentadas aqui.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

---

## [0.1.0] - 2026-07-17

### Adicionado

- MCP server com transporte Streamable HTTP (`/mcp`) e SSE (`/sse`)
- Endpoint de health check (`/health`) sem autenticação
- CRUD completo de posts, pages e custom post types
- Listagem e consulta de usuários
- Integração WooCommerce (pedidos e produtos)
- Acesso direto ao banco de dados com permissões granulares
- Sistema de API keys com hash SHA-256
- Permissões por key: posts, users, woocommerce, database, custom
- Permissões de banco por tabela e por operação (read/create/write)
- Rate limiting atômico por key
- Audit log com IP, params truncados e resultado
- Dashboard com métricas (requests/dia, top tools, top keys, erros)
- Filtros e paginação no audit log
- Webhooks para notificação em operações de escrita
- Auto-discovery de tabelas de plugins populares (CF7, FluentCRM, WPForms, GravityForms, WooCommerce)
- Custom Tools API (`Custom_Tools::register()`)
- Hooks WordPress para extensibilidade (`agentpress_before_tool_call`, `agentpress_after_tool_call`, etc)
- MCP Resources (site info, post types, taxonomies, plugins)
- Kill switch de emergência via URL
- Toggle ativar/desativar keys
- Expiração de keys com data configurável
- Colunas bloqueadas (hardcoded + configurável)
- Post types internos bloqueados
- Meta keys sensíveis protegidos
- Validação de identificadores SQL via regex
- Limite de 50 rows por UPDATE
- Limite de campos, colunas e condições WHERE
- Tradução PT-BR completa no admin
- Guia de conexão no admin (Kiro, Cursor, Claude Desktop, cURL)
- Uninstall hook (drop tables + cleanup)
- CORS headers em todos os endpoints

### Segurança

- API keys armazenadas como hash SHA-256
- Comparação constant-time (`hash_equals`)
- Queries parametrizadas em todo acesso ao banco
- Validação regex em nomes de tabela/coluna (previne SQL injection via backtick)
- Verificação de existência da tabela antes de queries
- Blocked columns nunca expostas (user_pass, session_tokens, etc)
- Rate limit race-condition safe (atomic increment)
- Input length limits em todos os campos
- Erros SQL não expostos ao agente (vão pro error_log)
- `session_write_close()` no SSE para evitar deadlock
- LIKE patterns limitados a 255 chars
- WHERE obrigatório para UPDATEs
- Post types internos e meta keys sensíveis bloqueados

### Compatibilidade

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (opcional)
- Testado: Kiro (Streamable HTTP)
- Compatível: Cursor, Claude Code, Claude Desktop, ChatGPT

---

## [Unreleased]

### Adicionado

- URL da imagem destacada (thumbnail) nos retornos de `posts_list` e `posts_get`
- Opção `format: "clean"` no `posts_get` — retorna texto puro sem blocos Gutenberg/HTML
- Descrição explícita de suporte a pages no `posts_list`
- Endpoint renomeado de `/message` para `/mcp` (backward compatible)
