<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records visits and suspicious events.
 */
class VISISE_Logger {


	/**
	 * A visitor is considered "online now" if their last recorded activity
	 * was within this many seconds.
	 */
	const ONLINE_WINDOW = 2 * MINUTE_IN_SECONDS;

	/**
	 * Marks this IP as currently active, for the real-time "online now" count.
	 * This is independent of the session-deduped visits log, so it stays
	 * accurate even while a visitor keeps browsing beyond one session.
	 */
	public static function heartbeat( $ip ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO " . VISISE_DB::presence_table() . " (ip, last_seen) VALUES (%s, %s) ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)",
				$ip,
				current_time( 'mysql' )
			)
		);

		// Opportunistically keep the presence table tiny, without needing a
		// dedicated fast cron job (1-in-20 chance per heartbeat is enough).
		if ( 1 === wp_rand( 1, 20 ) ) {
			self::purge_stale_presence();
		}
	}

	/**
	 * Number of distinct IPs currently active on the site (real-time).
	 */
	public static function count_online() {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::ONLINE_WINDOW );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . VISISE_DB::presence_table() . " WHERE last_seen >= %s",
				$since
			)
		);
	}

	/**
	 * Marks this IP as no longer present, for an instant "left the site" update
	 * instead of waiting for the online window to time out. Triggered by the
	 * browser when a visitor closes the tab or navigates away.
	 */
	public static function mark_offline( $ip ) {
		global $wpdb;

		$wpdb->delete( VISISE_DB::presence_table(), array( 'ip' => $ip ), array( '%s' ) );
	}

	/**
	 * Removes presence records older than the online window, keeping the table tiny.
	 */
	public static function purge_stale_presence() {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 2 * self::ONLINE_WINDOW ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM " . VISISE_DB::presence_table() . " WHERE last_seen < %s", $since ) );
	}

	/**
	 * Records a visit. Each IP keeps exactly one row, which is refreshed (page,
	 * user-agent, timestamp) every time that IP is seen again — so the Visitors
	 * list always shows one live, up-to-date entry per visitor (moving to the
	 * top with the current time) instead of piling up duplicate rows for the
	 * same person browsing around.
	 */
	public static function log_visit( $ip, $user_agent, $request_uri, $referer, $is_logged_in, $visitor_role = null ) {
		global $wpdb;

		if ( null === $visitor_role ) {
			$visitor_role = self::current_visitor_role();
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . VISISE_DB::visits_table() . " WHERE ip = %s",
				$ip
			)
		);

		if ( $existing_id ) {
			$wpdb->update(
				VISISE_DB::visits_table(),
				array(
					'user_agent'   => mb_substr( $user_agent, 0, 255 ),
					'request_uri'  => mb_substr( $request_uri, 0, 500 ),
					'referer'      => mb_substr( $referer, 0, 500 ),
					'is_logged_in' => $is_logged_in ? 1 : 0,
					'visitor_role' => $visitor_role,
					'created_at'   => current_time( 'mysql' ),
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			return;
		}

		$wpdb->insert(
			VISISE_DB::visits_table(),
			array(
				'ip'           => $ip,
				'user_agent'   => mb_substr( $user_agent, 0, 255 ),
				'request_uri'  => mb_substr( $request_uri, 0, 500 ),
				'referer'      => mb_substr( $referer, 0, 500 ),
				'is_logged_in' => $is_logged_in ? 1 : 0,
				'visitor_role' => $visitor_role,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Determines the current visitor's role for display purposes: 'guest'
	 * (not logged in), 'admin' (can manage the site), or 'member' (any other
	 * logged-in account).
	 */
	public static function current_visitor_role() {
		if ( ! is_user_logged_in() ) {
			return 'guest';
		}

		return current_user_can( 'manage_options' ) ? 'admin' : 'member';
	}

	public static function log_event( $ip, $event_type, $description, $score ) {
		global $wpdb;

		$wpdb->insert(
			VISISE_DB::events_table(),
			array(
				'ip'          => $ip,
				'event_type'  => sanitize_key( $event_type ),
				'description' => mb_substr( $description, 0, 500 ),
				'score'       => absint( $score ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Total score accumulated by an IP within a time window (minutes).
	 */
	public static function get_score_for_ip( $ip, $minutes = 60 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * MINUTE_IN_SECONDS ) );

		$score = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(score) FROM " . VISISE_DB::events_table() . " WHERE ip = %s AND created_at >= %s",
				$ip,
				$since
			)
		);

		return (int) $score;
	}

	/**
	 * Checks whether an event of the given type was already logged for this IP
	 * within the last N seconds, used to throttle repetitive "soft" signals
	 * (like rate limiting) so a single burst of activity cannot inflate the
	 * risk score on its own.
	 */
	public static function has_recent_event( $ip, $event_type, $seconds ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . VISISE_DB::events_table() . " WHERE ip = %s AND event_type = %s AND created_at >= %s",
				$ip,
				$event_type,
				$since
			)
		);

		return $count > 0;
	}

	/**
	 * Tracks a raw request from this IP and returns how many requests it has
	 * made within the given window. This is independent of the (session-deduped)
	 * visits log, so bursts of requests are still detected for rate limiting
	 * even though a refresh does not create a new visit record.
	 */
	public static function track_request_and_get_count( $ip, $window_seconds ) {
		$key   = 'visise_reqcount_' . md5( $ip );
		$count = (int) get_transient( $key );
		++$count;
		set_transient( $key, $count, $window_seconds );

		return $count;
	}

	/**
	 * Tracks a login attempt for this IP within a short window and returns how
	 * many attempts it has made — used for a dedicated, stricter brute-force
	 * check on wp-login.php, independent of the general page-request rate limit.
	 */
	public static function track_login_attempt( $ip, $window_seconds = 60 ) {
		$key   = 'visise_loginattempts_' . md5( $ip );
		$count = (int) get_transient( $key );
		++$count;
		set_transient( $key, $count, $window_seconds );

		return $count;
	}

	/**
	 * Tracks the usernames tried by this IP within a window and returns how many
	 * distinct usernames were used. A real person mistypes their own username a
	 * few times; trying many different usernames in a row is the signature of
	 * credential-stuffing / brute-force bots, not human error.
	 */
	public static function track_login_username_and_get_distinct_count( $ip, $username, $window_seconds = 600 ) {
		$key       = 'visise_loginusers_' . md5( $ip );
		$usernames = get_transient( $key );

		if ( ! is_array( $usernames ) ) {
			$usernames = array();
		}

		$usernames[] = sanitize_user( $username, true );
		$usernames   = array_slice( array_unique( $usernames ), -20 );

		set_transient( $key, $usernames, $window_seconds );

		return count( $usernames );
	}

	public static function count_404_in_window( $ip, $seconds ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . VISISE_DB::events_table() . " WHERE ip = %s AND event_type = 'not_found' AND created_at >= %s",
				$ip,
				$since
			)
		);
	}

	public static function get_events_for_ip( $ip, $limit = 50 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . VISISE_DB::events_table() . " WHERE ip = %s ORDER BY created_at DESC LIMIT %d",
				$ip,
				$limit
			)
		);
	}

	public static function count_visits_today() {
		global $wpdb;

		$since = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip) FROM " . VISISE_DB::visits_table() . " WHERE created_at >= %s",
				$since
			)
		);
	}

	public static function count_visits_total( $days = 7 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . VISISE_DB::visits_table() . " WHERE created_at >= %s",
				$since
			)
		);
	}

	public static function get_recent_visits( $limit = 20, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . VISISE_DB::visits_table() . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	public static function get_recent_events( $limit = 20 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . VISISE_DB::events_table() . " ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Daily visit counts for the last N days, e.g. array( '2026-07-01' => 12, ... ).
	 * Missing days are filled in with 0 so charts have a continuous scale.
	 */
	public static function get_daily_visit_counts( $days = 14 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM " . VISISE_DB::visits_table() . ' WHERE created_at >= %s GROUP BY DATE(created_at)',
				$since
			)
		);

		$by_day = array();
		foreach ( $rows as $row ) {
			$by_day[ $row->day ] = (int) $row->total;
		}

		$result = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day             = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( $i * DAY_IN_SECONDS ) );
			$result[ $day ]  = isset( $by_day[ $day ] ) ? $by_day[ $day ] : 0;
		}

		return $result;
	}

	/**
	 * Most requested pages within the last N days.
	 */
	public static function get_top_pages( $limit = 5, $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_uri, COUNT(*) AS total FROM " . VISISE_DB::visits_table() . " WHERE created_at >= %s AND request_uri != '' GROUP BY request_uri ORDER BY total DESC LIMIT %d",
				$since,
				$limit
			)
		);
	}

	/**
	 * Most common external referrers within the last N days.
	 */
	public static function get_top_referrers( $limit = 5, $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referer, COUNT(*) AS total FROM " . VISISE_DB::visits_table() . " WHERE created_at >= %s AND referer != '' GROUP BY referer ORDER BY total DESC LIMIT %d",
				$since,
				$limit
			)
		);
	}

	/**
	 * Breakdown of detected suspicious event types within the last N days,
	 * most frequent first — shows the admin what kind of threats are most common.
	 */
	public static function get_event_type_breakdown( $limit = 8, $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) AS total FROM " . VISISE_DB::events_table() . " WHERE created_at >= %s GROUP BY event_type ORDER BY total DESC LIMIT %d",
				$since,
				$limit
			)
		);
	}

	/**
	 * Raw user-agents for visits within the last N days, used to build a
	 * device/browser breakdown without any external lookup service.
	 */
	public static function get_recent_user_agents( $days = 30, $limit = 2000 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_agent FROM " . VISISE_DB::visits_table() . " WHERE created_at >= %s LIMIT %d",
				$since,
				$limit
			)
		);
	}

	public static function purge_old_data( $days ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM " . VISISE_DB::visits_table() . " WHERE created_at < %s", $since ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . VISISE_DB::events_table() . " WHERE created_at < %s", $since ) );
	}

	/**
	 * Wipes all recorded suspicious-activity history for an IP, so it gets a
	 * genuinely clean slate. Used only once an unban declaration has been
	 * accepted for a formerly permanently banned IP -- the audit record of
	 * that decision itself lives on permanently, in a separate table.
	 */
	public static function delete_events_for_ip( $ip ) {
		global $wpdb;

		return $wpdb->delete( VISISE_DB::events_table(), array( 'ip' => $ip ), array( '%s' ) );
	}
}
