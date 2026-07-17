<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php esc_html_e( 'AgentPress — Log de Auditoria', 'agentpress' ); ?></h1>

    <!-- Filtros -->
    <form method="get" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
        <input type="hidden" name="page" value="agentpress-logs">

        <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label for="filter_key_id"><strong><?php esc_html_e( 'Chave', 'agentpress' ); ?></strong></label><br>
                <select name="filter_key_id" id="filter_key_id">
                    <option value=""><?php esc_html_e( 'Todas', 'agentpress' ); ?></option>
                    <?php foreach ( $all_keys as $k ) : ?>
                        <option value="<?php echo esc_attr( $k['id'] ); ?>" <?php selected( $filter_key_id, (int) $k['id'] ); ?>>
                            <?php echo esc_html( $k['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_tool"><strong><?php esc_html_e( 'Ferramenta', 'agentpress' ); ?></strong></label><br>
                <input type="text" name="filter_tool" id="filter_tool" value="<?php echo esc_attr( $filter_tool ); ?>" placeholder="<?php esc_attr_e( 'Ex: posts_list', 'agentpress' ); ?>" style="width: 160px;">
            </div>

            <div>
                <label for="filter_date_from"><strong><?php esc_html_e( 'De', 'agentpress' ); ?></strong></label><br>
                <input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
            </div>

            <div>
                <label for="filter_date_to"><strong><?php esc_html_e( 'Até', 'agentpress' ); ?></strong></label><br>
                <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
            </div>

            <div>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filtrar', 'agentpress' ); ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=agentpress-logs' ) ); ?>" class="button"><?php esc_html_e( 'Limpar', 'agentpress' ); ?></a>
            </div>
        </div>
    </form>

    <p class="description">
        <?php
        printf(
            /* translators: %d: total count */
            esc_html__( 'Exibindo página %1$d de %2$d (%3$d registros)', 'agentpress' ),
            $current_page,
            $total_pages ?: 1,
            $total_count
        );
        ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Data', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Chave', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Ferramenta', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Ação', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'Resultado', 'agentpress' ); ?></th>
                <th><?php esc_html_e( 'IP', 'agentpress' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'Nenhum log encontrado.', 'agentpress' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['created_at'] ); ?></td>
                        <td><?php echo esc_html( $log['key_name'] ?? '#' . $log['key_id'] ); ?></td>
                        <td><code><?php echo esc_html( $log['tool'] ); ?></code></td>
                        <td><?php echo esc_html( $log['action'] ); ?></td>
                        <td><?php echo esc_html( mb_substr( $log['result_summary'], 0, 80 ) ); ?></td>
                        <td><?php echo esc_html( $log['ip_address'] ?? '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url( 'admin.php?page=agentpress-logs' );
                $query_args = [];
                if ( $filter_key_id ) $query_args['filter_key_id'] = $filter_key_id;
                if ( $filter_tool ) $query_args['filter_tool'] = $filter_tool;
                if ( $filter_date_from ) $query_args['filter_date_from'] = $filter_date_from;
                if ( $filter_date_to ) $query_args['filter_date_to'] = $filter_date_to;

                $page_links = paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%', add_query_arg( $query_args, $base_url ) ),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ] );

                if ( $page_links ) {
                    echo wp_kses_post( $page_links );
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
