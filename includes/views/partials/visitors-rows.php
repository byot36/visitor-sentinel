<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the <tr> rows for the Visitors table. Expects $visits (array of
 * row objects) to be set by the including file. Shared between the initial
 * page render and the AJAX live-refresh, so both always look identical.
 */
foreach ( $visits as $visit ) :
	$country_code = VISISE_Geo::get_country_code( $visit->ip );
	?>
	<tr>
		<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $visit->created_at ) ); ?></td>
		<td>
			<?php if ( $country_code ) : ?>
				<span class="pv-flag"><?php echo esc_html( strtoupper( $country_code ) ); ?></span>
			<?php endif; ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'visise-bans', 'ip' => $visit->ip ), admin_url( 'admin.php' ) ) ); ?>">
				<?php echo esc_html( $visit->ip ); ?>
			</a>
		</td>
		<td><?php echo esc_html( $visit->request_uri ); ?></td>
		<td><?php echo esc_html( VISISE_UA::describe( $visit->user_agent ) ); ?></td>
		<td class="pv-ua"><?php echo esc_html( $visit->user_agent ); ?></td>
		<td>
			<?php
			$role        = isset( $visit->visitor_role ) ? $visit->visitor_role : ( $visit->is_logged_in ? 'member' : 'guest' );
			$role_labels = array(
				'guest'  => __( 'Guest', 'visitor-sentinel' ),
				'member' => __( 'Member', 'visitor-sentinel' ),
				'admin'  => __( 'Admin', 'visitor-sentinel' ),
			);
			$role_label = isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : $role_labels['guest'];
			?>
			<span class="pv-badge pv-badge--role-<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $role_label ); ?></span>
		</td>
		<td>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pv-inline-form">
				<?php wp_nonce_field( 'visise_ban_nonce' ); ?>
				<input type="hidden" name="action" value="visise_ban_action" />
				<input type="hidden" name="visise_action" value="manual_ban" />
				<input type="hidden" name="ban_type" value="temporary" />
				<input type="hidden" name="ip" value="<?php echo esc_attr( $visit->ip ); ?>" />
				<input type="hidden" name="reason" value="<?php echo esc_attr__( 'Manually blocked from the visitors list.', 'visitor-sentinel' ); ?>" />
				<button type="submit" class="button button-small"><?php esc_html_e( 'Block', 'visitor-sentinel' ); ?></button>
			</form>
		</td>
	</tr>
	<?php
endforeach;
