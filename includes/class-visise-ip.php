<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utilities for identifying the visitor's IP address.
 */
class VISISE_IP {

	/**
	 * Returns the visitor's current, validated IP address.
	 */
	public static function get_client_ip() {
		$settings = VISISE_Settings::get();
		$ip       = '';

		if ( ! empty( $settings['trust_forwarded_for'] ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts     = explode( ',', $forwarded );
			$candidate = trim( $parts[0] );
			if ( self::is_valid_ip( $candidate ) ) {
				$ip = $candidate;
			}
		}

		if ( empty( $ip ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( self::is_valid_ip( $candidate ) ) {
				$ip = $candidate;
			}
		}

		return $ip;
	}

	public static function is_valid_ip( $ip ) {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Checks whether the IP is on the whitelist from settings.
	 */
	public static function is_whitelisted( $ip ) {
		$settings = VISISE_Settings::get();
		$list     = isset( $settings['whitelist_ips'] ) ? $settings['whitelist_ips'] : '';
		$list     = array_filter( array_map( 'trim', explode( "\n", $list ) ) );

		return in_array( $ip, $list, true );
	}
}
