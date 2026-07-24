<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pv-wrap">
	<h1 class="pv-title">
		<?php esc_html_e( 'Visitors', 'visitor-sentinel' ); ?>
	</h1>
	<p class="pv-subtitle">
		<?php esc_html_e( 'The most recent visits recorded on the site.', 'visitor-sentinel' ); ?>
		<span class="pv-live-badge"><span class="pv-live-dot" aria-hidden="true"></span><?php esc_html_e( 'Live — updates automatically', 'visitor-sentinel' ); ?></span>
	</p>

	<div class="pv-panel">
		<div id="visise-visitors-live">
			<?php if ( empty( $visits ) ) : ?>
				<p class="pv-empty" id="visise-visitors-empty"><?php esc_html_e( 'No visits have been recorded yet.', 'visitor-sentinel' ); ?></p>
			<?php endif; ?>
			<div class="pv-table-wrap" id="visise-visitors-table-wrap" <?php echo empty( $visits ) ? 'style="display:none;"' : ''; ?>>
				<table class="widefat pv-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'IP', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Page visited', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Platform', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'User-Agent', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Account', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'visitor-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody id="visise-visitors-tbody">
						<?php include VISISE_PLUGIN_DIR . 'includes/views/partials/visitors-rows.php'; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
