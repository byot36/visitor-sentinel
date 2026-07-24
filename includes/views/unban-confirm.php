<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_admin = wp_get_current_user();
?>
<div class="wrap pv-wrap">
	<h1 class="pv-title"><?php esc_html_e( 'Lift permanent block — declaration required', 'visitor-sentinel' ); ?></h1>
	<p class="pv-subtitle"><?php esc_html_e( 'This IP was permanently blocked. Lifting the block requires a signed declaration, kept forever in History, before its ban and activity history are wiped.', 'visitor-sentinel' ); ?></p>

	<div class="pv-panel pv-panel--inspect">
		<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'radar', 17 ); ?></span><?php esc_html_e( 'Record being lifted', 'visitor-sentinel' ); ?></h2>

		<table class="pv-ip-profile__table">
			<tr>
				<th><?php esc_html_e( 'IP address', 'visitor-sentinel' ); ?></th>
				<td><code><?php echo esc_html( $confirm_ip ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Blocked since', 'visitor-sentinel' ); ?></th>
				<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $confirm_ban->created_at ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Risk score', 'visitor-sentinel' ); ?></th>
				<td><?php echo esc_html( $confirm_ban->score ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reason', 'visitor-sentinel' ); ?></th>
				<td><?php echo esc_html( $confirm_ban->reason ); ?></td>
			</tr>
		</table>

		<?php if ( ! empty( $confirm_events ) ) : ?>
			<h3 style="margin-top:20px;"><?php esc_html_e( 'Recorded activity (will be permanently deleted)', 'visitor-sentinel' ); ?></h3>
			<div class="pv-table-wrap">
				<table class="widefat pv-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Type', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Details', 'visitor-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $confirm_events as $event ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $event->created_at ) ); ?></td>
								<td><?php echo esc_html( $event->event_type ); ?></td>
								<td><?php echo esc_html( $event->description ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="pv-panel">
		<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'file-warning', 17 ); ?></span><?php esc_html_e( 'Declaration', 'visitor-sentinel' ); ?></h2>
		<p class="description"><?php esc_html_e( 'This statement, your name, and today\'s date will be permanently recorded in History as an official record of this decision — it is not deleted along with the IP\'s data.', 'visitor-sentinel' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'visise_confirm_unban_nonce' ); ?>
			<input type="hidden" name="action" value="visise_confirm_unban" />
			<input type="hidden" name="ip" value="<?php echo esc_attr( $confirm_ip ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="visise_declaration"><?php esc_html_e( 'Reason for lifting this block', 'visitor-sentinel' ); ?></label></th>
					<td>
						<textarea id="visise_declaration" name="declaration" rows="4" class="large-text" required placeholder="<?php esc_attr_e( 'e.g. Confirmed with the site owner this was a false positive / the issue has been resolved.', 'visitor-sentinel' ); ?>"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="visise_signature"><?php esc_html_e( 'Digital signature (type your full name)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="text" id="visise_signature" name="signature_name" class="regular-text" required placeholder="<?php echo esc_attr( $current_admin->display_name ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: the WordPress account that will be recorded alongside the signature. */
								esc_html__( 'Recorded together with your WordPress account (%s) and a tamper-evident checksum of this declaration.', 'visitor-sentinel' ),
								esc_html( $current_admin->user_login )
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'This will permanently delete this IP\'s ban and activity history. The signed declaration itself will be kept forever in History. Continue?', 'visitor-sentinel' ) ); ?>');">
					<?php esc_html_e( 'Sign & lift the block', 'visitor-sentinel' ); ?>
				</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=visise-bans' ) ); ?>"><?php esc_html_e( 'Cancel', 'visitor-sentinel' ); ?></a>
			</p>
		</form>
	</div>
</div>
