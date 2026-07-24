<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the (temporary or permanent) blocking of IP addresses.
 */
class VISISE_Ban {

	/**
	 * Purges any full-page cache the site might have (LiteSpeed Cache, WP
	 * Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache), so a newly
	 * banned IP is actually blocked on its very next request instead of
	 * being served an old cached page straight from the server/CDN without
	 * WordPress -- and this plugin's own ban check -- ever running.
	 */
	private static function purge_page_cache() {
		// LiteSpeed Cache: extremely common on cPanel/LiteSpeed hosting,
		// where the admin bar's "Purge cache" link comes from this exact plugin.
		if ( has_action( 'litespeed_purge_all' ) || defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		if ( class_exists( 'WpFastestCache' ) ) {
			global $wp_fastest_cache;
			if ( is_object( $wp_fastest_cache ) && method_exists( $wp_fastest_cache, 'deleteCache' ) ) {
				$wp_fastest_cache->deleteCache( true );
			}
		}
	}

	/**
	 * Returns the ban record for an IP, or null.
	 */
	public static function get( $ip ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . VISISE_DB::bans_table() . " WHERE ip = %s", $ip )
		);
	}

	public static function get_all( $limit = 100, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . VISISE_DB::bans_table() . " ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Currently active (permanent, or temporary and not yet expired) bans,
	 * full rows -- what the "Blocked IPs" admin screen shows, so an expired
	 * temporary ban disappears from the list on its own instead of lingering
	 * there (unblockable-looking) until the daily cleanup eventually purges it.
	 */
	public static function get_all_active( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . VISISE_DB::bans_table() . "
				 WHERE ban_type = 'permanent' OR expires_at > %s
				 ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				$now,
				$limit,
				$offset
			)
		);
	}

	/**
	 * All currently active (permanent, or temporary and not yet expired) IP
	 * addresses, used to keep the LiteSpeed cache-bypass block in sync.
	 */
	public static function get_active_ips() {
		global $wpdb;
		$now = current_time( 'mysql' );

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ip FROM " . VISISE_DB::bans_table() . " WHERE ban_type = 'permanent' OR expires_at > %s",
				$now
			)
		);
	}

	public static function count_all() {
		global $wpdb;
		// No user input in this query -- the table name comes only from an
		// internal constant, never from a request -- so there is nothing to
		// bind via prepare().
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VISISE_DB::bans_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function count_active() {
		global $wpdb;
		$now = current_time( 'mysql' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . VISISE_DB::bans_table() . " WHERE ban_type = 'permanent' OR expires_at > %s",
				$now
			)
		);
	}

	/**
	 * Checks whether an IP is currently blocked. Returns the record or false.
	 */
	public static function is_banned( $ip ) {
		$ban = self::get( $ip );

		if ( ! $ban ) {
			return false;
		}

		if ( 'permanent' === $ban->ban_type ) {
			return $ban;
		}

		if ( ! empty( $ban->expires_at ) && strtotime( $ban->expires_at ) > current_time( 'timestamp' ) ) {
			return $ban;
		}

		return false;
	}

	/**
	 * A blocked IP eventually gets a different public address (mobile networks
	 * rotate IPs constantly, and a dual-stack site sees IPv4 on one request and
	 * IPv6 on the next) -- an IP-only check would then quietly stop matching the
	 * same visitor. To close that gap, the exact browser that was shown the
	 * block page is also tagged with a persistent, unguessable cookie (see
	 * set_device_cookie()). This checks the IP first and, if that finds
	 * nothing, falls back to that cookie -- so the same device stays blocked
	 * even after its IP changes, without ever affecting a different visitor who
	 * merely reuses an IP that was once banned.
	 */
	public static function find_active_for_request( $ip ) {
		$ban = self::is_banned( $ip );
		if ( $ban ) {
			return $ban;
		}

		if ( ! empty( $_COOKIE['visise_id'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_COOKIE['visise_id'] ) );
			if ( preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
				$ban = self::get_by_device_token( $token );
				if ( $ban ) {
					return $ban;
				}
			}
		}

		// Opt-in secondary lookup (see Settings -> Device recognition): a
		// best-effort browser fingerprint, used only to re-match a browser that
		// was already permanently banned on real evidence, if it returns from a
		// different IP than the one it was banned under (a plain IP-only check
		// would otherwise miss it, e.g. after a VPN/carrier IP change).
		$settings = VISISE_Settings::get();
		if ( empty( $settings['enable_fingerprinting'] ) ) {
			return false;
		}

		$fingerprint = self::get_fingerprint_from_request();
		if ( empty( $fingerprint ) ) {
			return false;
		}

		return self::get_by_fingerprint( $fingerprint );
	}

	/**
	 * Reads and validates the opt-in browser-fingerprint cookie set by the
	 * front-end script, or an empty string if absent/malformed.
	 */
	public static function get_fingerprint_from_request() {
		if ( empty( $_COOKIE['visise_fp'] ) ) {
			return '';
		}

		$fingerprint = sanitize_text_field( wp_unslash( $_COOKIE['visise_fp'] ) );
		if ( ! preg_match( '/^[a-f0-9]{1,32}$/', $fingerprint ) ) {
			return '';
		}

		return $fingerprint;
	}

	/**
	 * Returns the ban record matching a device-recognition token, or false.
	 */
	public static function get_by_device_token( $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . VISISE_DB::bans_table() . " WHERE device_token = %s", $token )
		);
	}

	/**
	 * Returns the ban record matching a browser fingerprint, or false.
	 */
	public static function get_by_fingerprint( $fingerprint ) {
		global $wpdb;

		if ( empty( $fingerprint ) ) {
			return false;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . VISISE_DB::bans_table() . " WHERE fingerprint = %s", $fingerprint )
		);
	}

	/**
	 * A random, unguessable token used purely to recognize a previously-blocked
	 * browser again later, regardless of its current IP. Not a security secret
	 * in itself (it lives in a cookie), just a stable identifier.
	 */
	private static function generate_device_token() {
		return bin2hex( random_bytes( 20 ) );
	}

	/**
	 * Tags the current visitor's browser with the ban's device-recognition
	 * cookie, so it stays blocked even after this IP changes. Called whenever
	 * the block page is actually shown to a visitor.
	 */
	public static function set_device_cookie( $ban ) {
		if ( empty( $ban->device_token ) || headers_sent() ) {
			return;
		}

		setcookie(
			'visise_id',
			$ban->device_token,
			array(
				'expires'  => time() + 10 * YEAR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Blocks an IP permanently.
	 *
	 * Every block this plugin creates is permanent, by design: a block lasts
	 * until the site owner deliberately lifts it, which requires the signed
	 * declaration in the admin (see unban_with_declaration()).
	 */
	public static function apply_ban( $ip, $reason, $score ) {
		global $wpdb;

		$existing    = self::get( $ip );
		$now         = current_time( 'mysql' );
		$fingerprint = self::get_fingerprint_from_request();

		if ( $existing ) {
			$data   = array(
				'ban_type'   => 'permanent',
				'reason'     => mb_substr( $reason, 0, 500 ),
				'score'      => absint( $score ),
				'expires_at' => null,
				'updated_at' => $now,
			);
			$format = array( '%s', '%s', '%d', '%s', '%s' );

			if ( empty( $existing->device_token ) ) {
				$data['device_token'] = self::generate_device_token();
				$format[]             = '%s';
			}

			if ( ! empty( $fingerprint ) && empty( $existing->fingerprint ) ) {
				$data['fingerprint'] = $fingerprint;
				$format[]            = '%s';
			}

			$wpdb->update(
				VISISE_DB::bans_table(),
				$data,
				array( 'ip' => $ip ),
				$format,
				array( '%s' )
			);

			VISISE_Notifications::notify_ban( $ip, 'permanent', $reason );
			self::purge_page_cache();

			return 'permanent';
		}

		$wpdb->insert(
			VISISE_DB::bans_table(),
			array(
				'ip'                => $ip,
				'ban_type'          => 'permanent',
				'reason'            => mb_substr( $reason, 0, 500 ),
				'score'             => absint( $score ),
				'temp_ban_count'    => 0,
				'hits_while_banned' => 0,
				'device_token'      => self::generate_device_token(),
				'fingerprint'       => $fingerprint,
				'created_at'        => $now,
				'expires_at'        => null,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		VISISE_Notifications::notify_ban( $ip, 'permanent', $reason );
		self::purge_page_cache();

		return 'permanent';
	}

	/**
	 * Records an access attempt made while already blocked, purely for the
	 * record. Blocks are already permanent, so there is nothing left to
	 * escalate to.
	 */
	public static function register_hit_while_banned( $ip ) {
		global $wpdb;

		$ban = self::get( $ip );
		if ( ! $ban ) {
			return;
		}

		$wpdb->update(
			VISISE_DB::bans_table(),
			array(
				'hits_while_banned' => (int) $ban->hits_while_banned + 1,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'ip' => $ip ),
			array( '%d', '%s' ),
			array( '%s' )
		);
	}

	public static function unban( $ip ) {
		global $wpdb;
		$result = $wpdb->delete( VISISE_DB::bans_table(), array( 'ip' => $ip ), array( '%s' ) );
		self::purge_page_cache();
		return $result;
	}

	/**
	 * Records a permanent, tamper-evident audit record ("proces verbal") for
	 * unbanning a formerly permanently blocked IP, then wipes that IP's ban
	 * and suspicious-activity history so it truly starts from zero. The
	 * audit record itself is never deleted by this or any retention cleanup
	 * -- it is the one thing meant to survive, in Visitor Sentinel -> History.
	 *
	 * Returns the new audit record's ID, or false if the IP wasn't banned.
	 */
	public static function unban_with_declaration( $ip, $declaration, $signature_name ) {
		global $wpdb;

		$ban = self::get( $ip );
		if ( ! $ban ) {
			return false;
		}

		$admin       = wp_get_current_user();
		$now         = current_time( 'mysql' );
		$declaration = sanitize_textarea_field( $declaration );
		$signature   = sanitize_text_field( $signature_name );

		$signature_hash = hash(
			'sha256',
			implode(
				'|',
				array(
					$ip,
					$ban->ban_type,
					$ban->reason,
					$ban->score,
					$admin->user_login,
					$declaration,
					$signature,
					$now,
				)
			)
		);

		$wpdb->insert(
			VISISE_DB::unban_log_table(),
			array(
				'ip'                 => $ip,
				'ban_type'           => $ban->ban_type,
				'original_reason'    => $ban->reason,
				'score'              => (int) $ban->score,
				'admin_display_name' => $admin->display_name,
				'admin_login'        => $admin->user_login,
				'declaration'        => $declaration,
				'signature_name'     => $signature,
				'signature_hash'     => $signature_hash,
				'created_at'         => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$record_id = (int) $wpdb->insert_id;

		VISISE_Logger::delete_events_for_ip( $ip );
		$wpdb->delete( VISISE_DB::bans_table(), array( 'ip' => $ip ), array( '%s' ) );
		self::purge_page_cache();

		return $record_id;
	}

	/**
	 * Manually blocks an IP from the admin panel. Always permanent -- the
	 * plugin has no temporary blocks; lifting one requires the signed
	 * declaration (see unban_with_declaration()).
	 */
	/**
	 * Manually blocks an IP typed in by the admin from the dashboard -- this
	 * request's own cookies belong to the admin's browser, not the banned
	 * visitor's, so no fingerprint is captured here (see apply_ban(), which
	 * runs in the actual offending visitor's request instead).
	 */
	public static function manual_ban( $ip, $reason ) {
		global $wpdb;

		$now      = current_time( 'mysql' );
		$existing = self::get( $ip );

		if ( $existing ) {
			$data   = array(
				'ban_type'   => 'permanent',
				'reason'     => mb_substr( $reason, 0, 500 ),
				'expires_at' => null,
				'is_manual'  => 1,
				'updated_at' => $now,
			);
			$format = array( '%s', '%s', '%s', '%d', '%s' );

			if ( empty( $existing->device_token ) ) {
				$data['device_token'] = self::generate_device_token();
				$format[]             = '%s';
			}

			$result = $wpdb->update(
				VISISE_DB::bans_table(),
				$data,
				array( 'ip' => $ip ),
				$format,
				array( '%s' )
			);
			self::purge_page_cache();
			return $result;
		}

		$result = $wpdb->insert(
			VISISE_DB::bans_table(),
			array(
				'ip'           => $ip,
				'ban_type'     => 'permanent',
				'reason'       => mb_substr( $reason, 0, 500 ),
				'score'        => 0,
				'is_manual'    => 1,
				'device_token' => self::generate_device_token(),
				'created_at'   => $now,
				'expires_at'   => null,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		self::purge_page_cache();
		return $result;
	}
}
