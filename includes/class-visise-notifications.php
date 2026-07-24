<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends an email alert to the site owner whenever the automatic protection
 * blocks or escalates an IP, so they know about attacks without needing to
 * check the admin panel constantly.
 */
class VISISE_Notifications {

	/**
	 * Notifies the configured recipient about a new or escalated block. Silently
	 * does nothing if email notifications are disabled in Settings.
	 */
	public static function notify_ban( $ip, $ban_type, $reason ) {
		$settings = VISISE_Settings::get();

		if ( empty( $settings['email_notifications_enabled'] ) ) {
			return;
		}

		$to = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( 'permanent' === $ban_type ) {
			/* translators: %s: site name. */
			$subject = sprintf( __( '[%s] An IP was permanently blocked', 'visitor-sentinel' ), $site_name );
		} else {
			/* translators: %s: site name. */
			$subject = sprintf( __( '[%s] An IP was temporarily blocked', 'visitor-sentinel' ), $site_name );
		}

		$body  = sprintf( "%s\n\n", __( 'Visitor Sentinel automatically blocked a visitor on your site.', 'visitor-sentinel' ) );
		$body .= sprintf( "%s %s\n", __( 'IP address:', 'visitor-sentinel' ), $ip );
		$body .= sprintf( "%s %s\n", __( 'Block type:', 'visitor-sentinel' ), 'permanent' === $ban_type ? __( 'Permanent', 'visitor-sentinel' ) : __( 'Temporary', 'visitor-sentinel' ) );
		$body .= sprintf( "%s %s\n\n", __( 'Reason:', 'visitor-sentinel' ), wp_strip_all_tags( $reason ) );
		$body .= sprintf( "%s %s\n", __( 'Review it here:', 'visitor-sentinel' ), admin_url( 'admin.php?page=visise-bans&ip=' . rawurlencode( $ip ) ) );

		wp_mail( $to, $subject, $body );
	}
}
