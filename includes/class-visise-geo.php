<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional IP-to-network lookup (country, city, ISP, VPN/proxy detection),
 * used only in the admin panel. Disabled by default. When enabled, it sends
 * the visitor's IP address to the free ip-api.com service (no bundled GeoIP
 * database ships with this plugin) and caches the result locally so the same
 * IP is never looked up twice within 30 days.
 */
class VISISE_Geo {

	const CACHE_TTL = 30 * DAY_IN_SECONDS;

	private static function empty_result() {
		return array(
			'countryCode' => '',
			'country'     => '',
			'regionName'  => '',
			'city'        => '',
			'isp'         => '',
			'org'         => '',
			'as'          => '',
			'proxy'       => false,
			'hosting'     => false,
		);
	}

	/**
	 * Returns the full network profile for an IP: country, city, ISP,
	 * organization, ASN, and whether it's a known VPN/proxy or hosting
	 * provider exit point. Returns all-empty values if disabled, the IP is
	 * private/reserved, or the lookup fails.
	 */
	public static function lookup( $ip ) {
		$settings = VISISE_Settings::get();

		if ( empty( $settings['enable_geo_lookup'] ) ) {
			return self::empty_result();
		}

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return self::empty_result();
		}

		$cache_key = 'visise_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return wp_parse_args( $cached, self::empty_result() );
		}

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,countryCode,country,regionName,city,isp,org,as,proxy,hosting',
			array( 'timeout' => 2 )
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, self::empty_result(), HOUR_IN_SECONDS );
			return self::empty_result();
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$result = self::empty_result();

		if ( is_array( $body ) && 'success' === ( $body['status'] ?? '' ) ) {
			$result['countryCode'] = ! empty( $body['countryCode'] ) ? sanitize_key( $body['countryCode'] ) : '';
			$result['country']     = ! empty( $body['country'] ) ? sanitize_text_field( $body['country'] ) : '';
			$result['regionName']  = ! empty( $body['regionName'] ) ? sanitize_text_field( $body['regionName'] ) : '';
			$result['city']        = ! empty( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '';
			$result['isp']         = ! empty( $body['isp'] ) ? sanitize_text_field( $body['isp'] ) : '';
			$result['org']         = ! empty( $body['org'] ) ? sanitize_text_field( $body['org'] ) : '';
			$result['as']          = ! empty( $body['as'] ) ? sanitize_text_field( $body['as'] ) : '';
			$result['proxy']       = ! empty( $body['proxy'] );
			$result['hosting']     = ! empty( $body['hosting'] );
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Convenience shortcut used by the Visitors/Blocked IPs tables, where
	 * only the country code is needed to render the flag badge.
	 */
	public static function get_country_code( $ip ) {
		return self::lookup( $ip )['countryCode'];
	}
}
