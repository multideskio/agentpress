<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php esc_html_e( 'AgentPress — Chaves de API', 'agentpress' ); ?></h1>

    <?php if ( $new_key ) : ?>
        <div class="notice notice-success">
            <p><strong><?php esc_html_e( 'Nova chave criada!', 'agentpress' ); ?></strong> <?php esc_html_e( 'Copie agora — ela não será exibida novamente:', 'agentpress' ); ?></p>
            <code style="font-size: 14px; padding: 8px; display: block; background: #f0f0f0; margin: 8px 0;"><?php echo esc_html( $new_key ); ?></code>
            <p><strong><?php esc_html_e( 'Endpoint SSE:', 'agentpress' ); ?></strong> <code><?php echo esc_html( $sse_url ); ?></code></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e( 'Chave excluída.', 'agentpress' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['toggled'] ) ) : ?>
        <div class="notice notice-success"><p><?php esc_html_e( 'Status da chave atualizado.', 'agentpress' ); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Chaves existentes', 'agentpress' ); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Nome', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Chave (parcial)', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Permissões', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Limite de taxa', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Expiração', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Último uso', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Status', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Ações', 'agentpress' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $keys ) ) : ?>
                <tr><td colspan="8"><?php esc_html_e( 'Nenhuma chave de API ainda. Crie uma abaixo.', 'agentpress' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $keys as $key ) : ?>
                    <?php
                    $is_expired = ! empty( $key['expires_at'] ) && strtotime( $key['expires_at'] ) < time();
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $key['name'] ); ?></strong></td>
                        <td><code><?php echo esc_html( substr( $key['api_key'], 0, 12 ) . '...' ); ?></code></td>
                        <td><?php echo esc_html( implode( ', ', array_keys( json_decode( $key['permissions'], true ) ?: [] ) ) ); ?></td>
                        <td><?php echo esc_html( $key['rate_limit'] ); ?>/min</td>
                        <td>
                            <?php if ( empty( $key['expires_at'] ) ) : ?>
                                <em><?php esc_html_e( 'Nunca', 'agentpress' ); ?></em>
                            <?php elseif ( $is_expired ) : ?>
                                <span style="color: #dc3232;">⚠️ <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $key['expires_at'] ) ) ); ?></span>
                            <?php else : ?>
                                <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $key['expires_at'] ) ) ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $key['last_used_at'] ? esc_html( $key['last_used_at'] ) : '<em>' . esc_html__( 'Nunca', 'agentpress' ) . '</em>'; ?></td>
                        <td>
                            <?php if ( $is_expired ) : ?>
                                🟡 <?php esc_html_e( 'Expirada', 'agentpress' ); ?>
                            <?php elseif ( (int) $key['is_active'] === 1 ) : ?>
                                🟢 <?php esc_html_e( 'Ativa', 'agentpress' ); ?>
                            <?php else : ?>
                                🔴 <?php esc_html_e( 'Inativa', 'agentpress' ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=agentpress-keys&agentpress_toggle_key=' . $key['id'] ), 'agentpress_toggle_key' ); ?>"
                               class="button button-small">
                                <?php echo (int) $key['is_active'] === 1 ? esc_html__( 'Desativar', 'agentpress' ) : esc_html__( 'Ativar', 'agentpress' ); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=agentpress-keys&agentpress_delete_key=' . $key['id'] ), 'agentpress_delete_key' ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Excluir esta chave?', 'agentpress' ); ?>')"
                               class="button button-small" style="color: #dc3232;"><?php esc_html_e( 'Excluir', 'agentpress' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Criar nova chave de API', 'agentpress' ); ?></h2>

    <form method="post">
        <?php wp_nonce_field( 'agentpress_create_key' ); ?>

        <table class="form-table">
            <tr>
                <th><label for="key_name"><?php esc_html_e( 'Nome da chave', 'agentpress' ); ?></label></th>
                <td><input type="text" name="key_name" id="key_name" class="regular-text" placeholder="<?php esc_attr_e( 'Ex: Agente Claude, Bot de suporte', 'agentpress' ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="rate_limit"><?php esc_html_e( 'Limite de taxa (req/min)', 'agentpress' ); ?></label></th>
                <td><input type="number" name="rate_limit" id="rate_limit" value="60" min="1" max="1000"></td>
            </tr>
            <tr>
                <th><label for="expires_at"><?php esc_html_e( 'Data de expiração', 'agentpress' ); ?></label></th>
                <td>
                    <input type="date" name="expires_at" id="expires_at" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Opcional. Deixe vazio para chave sem expiração.', 'agentpress' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Acesso a Posts', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_posts[]" value="read"> <?php esc_html_e( 'Ler', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_posts[]" value="create"> <?php esc_html_e( 'Criar', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_posts[]" value="write"> <?php esc_html_e( 'Editar', 'agentpress' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Acesso a Usuários', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_users[]" value="read"> <?php esc_html_e( 'Ler', 'agentpress' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Acesso ao WooCommerce', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_woocommerce[]" value="read"> <?php esc_html_e( 'Ler', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_woocommerce[]" value="write"> <?php esc_html_e( 'Editar Pedidos', 'agentpress' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Ferramentas Customizadas', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_custom[]" value="read"> <?php esc_html_e( 'Ler', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_custom[]" value="write"> <?php esc_html_e( 'Escrever', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_custom[]" value="create"> <?php esc_html_e( 'Criar', 'agentpress' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Acesso Direto ao Banco', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_database" value="1" id="perm_database"> <?php esc_html_e( 'Habilitar', 'agentpress' ); ?></label>
                    <p class="description"><?php esc_html_e( '⚠️ Permite consultas SQL diretas em tabelas permitidas.', 'agentpress' ); ?></p>
                </td>
            </tr>
            <tr class="db-options" style="display:none;">
                <th><label for="perm_db_tables"><?php esc_html_e( 'Tabelas permitidas', 'agentpress' ); ?></label></th>
                <td>
                    <textarea name="perm_db_tables" id="perm_db_tables" rows="4" class="large-text" placeholder="wp_cf7_submissions&#10;wp_fluentcrm_subscribers&#10;wp_custom_table"></textarea>
                    <p class="description"><?php esc_html_e( 'Uma tabela por linha. Use * para todas as tabelas (não recomendado).', 'agentpress' ); ?></p>
                </td>
            </tr>
            <tr class="db-options" style="display:none;">
                <th><?php esc_html_e( 'Operações no Banco', 'agentpress' ); ?></th>
                <td>
                    <label><input type="checkbox" name="perm_db_ops[]" value="read"> <?php esc_html_e( 'SELECT (ler)', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_db_ops[]" value="create"> <?php esc_html_e( 'INSERT (criar)', 'agentpress' ); ?></label>
                    <label><input type="checkbox" name="perm_db_ops[]" value="write"> <?php esc_html_e( 'UPDATE (editar)', 'agentpress' ); ?></label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="agentpress_create_key" class="button-primary" value="<?php esc_attr_e( 'Criar Chave de API', 'agentpress' ); ?>">
        </p>
    </form>

    <hr>
    <h3><?php esc_html_e( 'Como conectar', 'agentpress' ); ?></h3>
    <p><?php esc_html_e( 'Seu endpoint MCP SSE:', 'agentpress' ); ?> <code><?php echo esc_html( $sse_url ); ?></code></p>
    <p><?php esc_html_e( 'Adicione o header Authorization: Bearer SUA_CHAVE_API para conectar seu agente.', 'agentpress' ); ?></p>
</div>

<script>
document.getElementById('perm_database').addEventListener('change', function() {
    document.querySelectorAll('.db-options').forEach(el => {
        el.style.display = this.checked ? '' : 'none';
    });
});
</script>
