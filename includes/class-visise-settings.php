<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin settings.
 */
class VISISE_Settings {

	const OPTION_KEY = 'visise_settings';

	public static function defaults() {
		return array(
			'rate_limit_requests'       => 60,
			'rate_limit_seconds'        => 10,
			'score_threshold'           => 50,
			'whitelist_ips'             => '',
			'trust_forwarded_for'       => 0,
			'track_404'                 => 1,
			'retention_days'            => 30,
			'frontend_counter_enabled'      => 1,
			'frontend_counter_role'         => 'read',
			'frontend_counter_position'     => 'left',
			'frontend_counter_show_guests'  => 1,
			'enable_geo_lookup'             => 0,
			'email_notifications_enabled'   => 0,
			'notification_email'            => '',
			'enable_honeypot_suite'         => 1,
			'enable_fingerprinting'         => 0,
		);
	}

	public static function get() {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $settings, self::defaults() );
	}

	public static function update( array $settings ) {
		update_option( self::OPTION_KEY, $settings );
	}

	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$clean    = array();

		$clean['rate_limit_requests']       = max( 1, absint( $input['rate_limit_requests'] ?? $defaults['rate_limit_requests'] ) );
		$clean['rate_limit_seconds']        = max( 1, absint( $input['rate_limit_seconds'] ?? $defaults['rate_limit_seconds'] ) );
		$clean['score_threshold']           = max( 1, absint( $input['score_threshold'] ?? $defaults['score_threshold'] ) );
		$clean['retention_days']            = max( 1, absint( $input['retention_days'] ?? $defaults['retention_days'] ) );
		$clean['trust_forwarded_for']       = ! empty( $input['trust_forwarded_for'] ) ? 1 : 0;
		$clean['track_404']                 = ! empty( $input['track_404'] ) ? 1 : 0;
		$clean['frontend_counter_enabled']      = ! empty( $input['frontend_counter_enabled'] ) ? 1 : 0;
		$clean['frontend_counter_show_guests']  = ! empty( $input['frontend_counter_show_guests'] ) ? 1 : 0;
		$clean['enable_geo_lookup']              = ! empty( $input['enable_geo_lookup'] ) ? 1 : 0;
		$clean['email_notifications_enabled']    = ! empty( $input['email_notifications_enabled'] ) ? 1 : 0;
		$clean['enable_honeypot_suite']           = ! empty( $input['enable_honeypot_suite'] ) ? 1 : 0;
		$clean['enable_fingerprinting']           = ! empty( $input['enable_fingerprinting'] ) ? 1 : 0;

		$notification_email = isset( $input['notification_email'] ) ? sanitize_email( wp_unslash( $input['notification_email'] ) ) : '';
		$clean['notification_email'] = is_email( $notification_email ) ? $notification_email : '';

		$allowed_roles = array( 'read', 'edit_posts', 'manage_options' );
		$role          = isset( $input['frontend_counter_role'] ) ? sanitize_key( $input['frontend_counter_role'] ) : $defaults['frontend_counter_role'];
		$clean['frontend_counter_role']     = in_array( $role, $allowed_roles, true ) ? $role : $defaults['frontend_counter_role'];

		$allowed_positions = array( 'left', 'right' );
		$position           = isset( $input['frontend_counter_position'] ) ? sanitize_key( $input['frontend_counter_position'] ) : $defaults['frontend_counter_position'];
		$clean['frontend_counter_position'] = in_array( $position, $allowed_positions, true ) ? $position : $defaults['frontend_counter_position'];

		$raw_ips           = isset( $input['whitelist_ips'] ) ? (string) wp_unslash( $input['whitelist_ips'] ) : '';
		$lines             = array_filter( array_map( 'trim', explode( "\n", $raw_ips ) ) );
		$valid_ips         = array_filter( $lines, array( 'VISISE_IP', 'is_valid_ip' ) );
		$clean['whitelist_ips'] = implode( "\n", $valid_ips );

		return $clean;
	}
}
