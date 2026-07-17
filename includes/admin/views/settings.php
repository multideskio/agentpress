<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php esc_html_e( 'AgentPress — Configurações', 'agentpress' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success"><p><?php esc_html_e( 'Configurações salvas.', 'agentpress' ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'agentpress_save_settings' ); ?>

        <table class="form-table">
            <tr>
                <th><label for="allowed_tables"><?php esc_html_e( 'Tabelas Permitidas Globalmente', 'agentpress' ); ?></label></th>
                <td>
                    <textarea name="allowed_tables" id="allowed_tables" rows="8" class="large-text"><?php echo esc_textarea( implode( "\n", $allowed_tables ) ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'Tabelas que podem ser acessadas pela ferramenta de Banco de Dados (uma por linha).', 'agentpress' ); ?><br>
                        <?php esc_html_e( 'Chaves individuais podem restringir esta lista ainda mais.', 'agentpress' ); ?><br>
                        <?php esc_html_e( 'Deixe vazio para desabilitar o acesso ao banco globalmente.', 'agentpress' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="blocked_columns"><?php esc_html_e( 'Colunas Bloqueadas', 'agentpress' ); ?></label></th>
                <td>
                    <textarea name="blocked_columns" id="blocked_columns" rows="5" class="large-text"><?php echo esc_textarea( implode( "\n", $blocked_columns ) ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'Colunas que NUNCA são expostas aos agentes (uma por linha).', 'agentpress' ); ?><br>
                        <?php esc_html_e( 'Padrão: user_pass, user_activation_key, session_tokens', 'agentpress' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="webhooks"><?php esc_html_e( 'URLs de Webhook', 'agentpress' ); ?></label></th>
                <td>
                    <textarea name="webhooks" id="webhooks" rows="4" class="large-text" placeholder="https://hooks.example.com/agentpress"><?php echo esc_textarea( implode( "\n", $webhook_urls ) ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'URLs que receberão notificações (POST) quando operações de escrita/criação forem realizadas.', 'agentpress' ); ?><br>
                        <?php esc_html_e( 'Uma URL por linha. Deixe vazio para desabilitar webhooks.', 'agentpress' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php if ( ! empty( $detected ) ) : ?>
            <hr>
            <h2><?php esc_html_e( 'Tabelas detectadas', 'agentpress' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Plugins detectados no seu WordPress. Marque as tabelas que deseja adicionar à whitelist global.', 'agentpress' ); ?></p>

            <table class="widefat" style="max-width: 700px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Plugin', 'agentpress' ); ?></th>
                        <th><?php esc_html_e( 'Tabelas sugeridas', 'agentpress' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $detected as $slug => $info ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $info['label'] ); ?></strong></td>
                            <td>
                                <?php foreach ( $info['tables'] as $table ) :
                                    $is_whitelisted = in_array( $table, $allowed_tables, true );
                                ?>
                                    <label style="display: block; margin: 2px 0;">
                                        <input type="checkbox"
                                               name="discovered_tables[]"
                                               value="<?php echo esc_attr( $table ); ?>"
                                               <?php checked( $is_whitelisted ); ?>
                                               onchange="toggleDiscoveredTable(this)">
                                        <code><?php echo esc_html( $table ); ?></code>
                                        <?php if ( $is_whitelisted ) : ?>
                                            <span style="color: #46b450;">✓</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="agentpress_save_settings" class="button-primary" value="<?php esc_attr_e( 'Salvar Configurações', 'agentpress' ); ?>">
        </p>
    </form>

    <!-- Token de Emergência -->
    <hr>
    <h2>🚨 <?php esc_html_e( 'Desativação de Emergência', 'agentpress' ); ?></h2>

    <?php
    $emergency_token_show = get_transient( 'agentpress_emergency_token_show' );
    if ( $emergency_token_show ) :
        delete_transient( 'agentpress_emergency_token_show' );
    ?>
        <div class="notice notice-warning" style="padding: 15px;">
            <p><strong><?php esc_html_e( 'Seu token de emergência foi gerado! Salve-o em lugar seguro — não será exibido novamente.', 'agentpress' ); ?></strong></p>
            <code style="font-size: 14px; padding: 10px; display: block; background: #f0f0f0; margin: 10px 0; word-break: break-all;"><?php echo esc_html( $emergency_token_show ); ?></code>
            <p><?php esc_html_e( 'URL de desativação:', 'agentpress' ); ?></p>
            <code style="font-size: 13px; padding: 8px; display: block; background: #f0f0f0; margin: 5px 0;"><?php echo esc_html( home_url( '/?agentpress_kill=' . $emergency_token_show ) ); ?></code>
        </div>
    <?php endif; ?>

    <p class="description">
        <?php esc_html_e( 'Se o AgentPress causar problemas no seu site e você não conseguir acessar o wp-admin, use a URL de emergência para desativar o plugin remotamente.', 'agentpress' ); ?>
    </p>
    <p class="description">
        <strong><?php esc_html_e( 'Como usar:', 'agentpress' ); ?></strong>
        <?php esc_html_e( 'Acesse a URL abaixo no navegador (substituindo TOKEN pelo seu token):', 'agentpress' ); ?><br>
        <code><?php echo esc_html( home_url( '/?agentpress_kill=SEU_TOKEN_AQUI' ) ); ?></code>
    </p>

    <?php if ( get_option( 'agentpress_emergency_hash' ) ) : ?>
        <p><em><?php esc_html_e( 'Um token de emergência já está configurado.', 'agentpress' ); ?></em></p>
    <?php endif; ?>

    <form method="post" style="margin-top: 10px;">
        <?php wp_nonce_field( 'agentpress_regen_emergency' ); ?>
        <input type="submit" name="agentpress_regen_emergency" class="button" value="<?php esc_attr_e( 'Gerar novo token de emergência', 'agentpress' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Isso invalidará o token anterior. Continuar?', 'agentpress' ); ?>')">
    </form>
</div>

<script>
function toggleDiscoveredTable(checkbox) {
    var textarea = document.getElementById('allowed_tables');
    var table = checkbox.value;
    var lines = textarea.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);

    if (checkbox.checked) {
        if (lines.indexOf(table) === -1) {
            lines.push(table);
        }
    } else {
        lines = lines.filter(l => l !== table);
    }

    textarea.value = lines.join('\n');
}
</script>
