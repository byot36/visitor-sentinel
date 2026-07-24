<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pv-wrap">
	<h1 class="pv-title"><?php esc_html_e( 'History', 'visitor-sentinel' ); ?></h1>
	<p class="pv-subtitle"><?php esc_html_e( 'Permanent record of every unban declaration ever signed — kept forever, independent of the IP data it accompanied.', 'visitor-sentinel' ); ?></p>

	<div class="pv-panel">
		<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'shield', 17 ); ?></span><?php esc_html_e( 'Unban declarations', 'visitor-sentinel' ); ?></h2>

		<?php if ( empty( $records ) ) : ?>
			<p class="pv-empty"><?php esc_html_e( 'No permanent blocks have been lifted yet.', 'visitor-sentinel' ); ?></p>
		<?php else : ?>
			<div class="pv-table-wrap">
				<table class="widefat pv-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'IP', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Original reason', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Signed by', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'visitor-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $record->created_at ) ); ?></td>
								<td><code><?php echo esc_html( $record->ip ); ?></code></td>
								<td><?php echo esc_html( $record->original_reason ); ?></td>
								<td><?php echo esc_html( $record->signature_name ); ?> <span class="pv-list__label">(<?php echo esc_html( $record->admin_login ); ?>)</span></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'visise-history', 'view' => $record->id ), admin_url( 'admin.php' ) ) ); ?>">
										<?php esc_html_e( 'View / Print PDF', 'visitor-sentinel' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
