<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use AgentPress\Audit_Log;

$today_count = Audit_Log::get_request_count( 'today' );
$week_count  = Audit_Log::get_request_count( 'week' );
$month_count = Audit_Log::get_request_count( 'month' );

$per_day    = Audit_Log::get_requests_per_day( 7 );
$top_tools  = Audit_Log::get_top_tools( 5 );
$top_keys   = Audit_Log::get_top_keys( 5 );
$last_errors = Audit_Log::get_last_errors( 5 );

$max_day_count = max( 1, max( $per_day ) ?: 1 );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'AgentPress — Dashboard', 'agentpress' ); ?></h1>

    <div style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
        <div class="card" style="flex: 1; min-width: 200px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Hoje', 'agentpress' ); ?></h3>
            <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html( number_format_i18n( $today_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'requisições', 'agentpress' ); ?></p>
        </div>
        <div class="card" style="flex: 1; min-width: 200px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Últimos 7 dias', 'agentpress' ); ?></h3>
            <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html( number_format_i18n( $week_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'requisições', 'agentpress' ); ?></p>
        </div>
        <div class="card" style="flex: 1; min-width: 200px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Últimos 30 dias', 'agentpress' ); ?></h3>
            <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html( number_format_i18n( $month_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'requisições', 'agentpress' ); ?></p>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <!-- Requests per day chart -->
        <div class="card" style="flex: 2; min-width: 400px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Requisições por dia (últimos 7 dias)', 'agentpress' ); ?></h3>
            <table class="widefat" style="border: none;">
                <tbody>
                    <?php foreach ( $per_day as $date => $count ) :
                        $bar_width = $max_day_count > 0 ? round( ( $count / $max_day_count ) * 100 ) : 0;
                        $short_date = wp_date( 'd/m', strtotime( $date ) );
                    ?>
                        <tr>
                            <td style="width: 60px; padding: 4px 8px; white-space: nowrap;"><?php echo esc_html( $short_date ); ?></td>
                            <td style="padding: 4px 8px;">
                                <div style="background: #2271b1; height: 20px; width: <?php echo esc_attr( $bar_width ); ?>%; min-width: 2px; border-radius: 3px;"></div>
                            </td>
                            <td style="width: 50px; padding: 4px 8px; text-align: right;"><?php echo esc_html( $count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top tools -->
        <div class="card" style="flex: 1; min-width: 250px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Top 5 ferramentas', 'agentpress' ); ?></h3>
            <?php if ( empty( $top_tools ) ) : ?>
                <p class="description"><?php esc_html_e( 'Nenhum dado ainda.', 'agentpress' ); ?></p>
            <?php else : ?>
                <ol style="margin: 0; padding-left: 20px;">
                    <?php foreach ( $top_tools as $tool ) : ?>
                        <li><code><?php echo esc_html( $tool['tool'] ); ?></code> — <?php echo esc_html( $tool['total'] ); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap;">
        <!-- Top keys -->
        <div class="card" style="flex: 1; min-width: 250px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Top 5 chaves por uso', 'agentpress' ); ?></h3>
            <?php if ( empty( $top_keys ) ) : ?>
                <p class="description"><?php esc_html_e( 'Nenhum dado ainda.', 'agentpress' ); ?></p>
            <?php else : ?>
                <ol style="margin: 0; padding-left: 20px;">
                    <?php foreach ( $top_keys as $k ) : ?>
                        <li><strong><?php echo esc_html( $k['key_name'] ?? __( 'Desconhecida', 'agentpress' ) ); ?></strong> — <?php echo esc_html( $k['total'] ); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <!-- Last errors -->
        <div class="card" style="flex: 2; min-width: 400px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Últimos 5 erros', 'agentpress' ); ?></h3>
            <?php if ( empty( $last_errors ) ) : ?>
                <p class="description"><?php esc_html_e( 'Nenhum erro registrado. 🎉', 'agentpress' ); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="border: none;">
                    <tbody>
                        <?php foreach ( $last_errors as $err ) : ?>
                            <tr>
                                <td style="width: 130px;"><?php echo esc_html( $err['created_at'] ); ?></td>
                                <td><code><?php echo esc_html( $err['tool'] ); ?></code></td>
                                <td><?php echo esc_html( mb_substr( $err['result_summary'], 0, 80 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
