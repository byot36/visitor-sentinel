<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin's own database tables.
 */
class VISISE_DB {

	public static function visits_table() {
		global $wpdb;
		return $wpdb->prefix . 'visise_visits';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'visise_events';
	}

	public static function bans_table() {
		global $wpdb;
		return $wpdb->prefix . 'visise_bans';
	}

	public static function presence_table() {
		global $wpdb;
		return $wpdb->prefix . 'visise_presence';
	}

	public static function unban_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'visise_unban_log';
	}

	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$visits          = self::visits_table();
		$events          = self::events_table();
		$bans            = self::bans_table();
		$presence        = self::presence_table();
		$unban_log       = self::unban_log_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_visits = "CREATE TABLE $visits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(45) NOT NULL,
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			request_uri VARCHAR(500) NOT NULL DEFAULT '',
			referer VARCHAR(500) NOT NULL DEFAULT '',
			is_logged_in TINYINT(1) NOT NULL DEFAULT 0,
			visitor_role VARCHAR(20) NOT NULL DEFAULT 'guest',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ip (ip),
			KEY created_at (created_at)
		) $charset_collate;";

		$sql_events = "CREATE TABLE $events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(45) NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			description VARCHAR(500) NOT NULL DEFAULT '',
			score INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ip (ip),
			KEY created_at (created_at)
		) $charset_collate;";

		$sql_bans = "CREATE TABLE $bans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(45) NOT NULL,
			ban_type VARCHAR(20) NOT NULL DEFAULT 'temporary',
			reason VARCHAR(500) NOT NULL DEFAULT '',
			score INT NOT NULL DEFAULT 0,
			temp_ban_count INT NOT NULL DEFAULT 0,
			hits_while_banned INT NOT NULL DEFAULT 0,
			is_manual TINYINT(1) NOT NULL DEFAULT 0,
			device_token VARCHAR(64) NOT NULL DEFAULT '',
			fingerprint VARCHAR(32) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ip (ip),
			KEY device_token (device_token),
			KEY fingerprint (fingerprint)
		) $charset_collate;";

		$sql_presence = "CREATE TABLE $presence (
			ip VARCHAR(45) NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (ip),
			KEY last_seen (last_seen)
		) $charset_collate;";

		// Permanent audit trail of unban decisions on formerly permanently
		// banned IPs: kept forever (never purged by retention cleanup),
		// independent of the ban/events rows it accompanies, which are wiped
		// once the declaration below is accepted.
		$sql_unban_log = "CREATE TABLE $unban_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(45) NOT NULL,
			ban_type VARCHAR(20) NOT NULL DEFAULT '',
			original_reason VARCHAR(500) NOT NULL DEFAULT '',
			score INT NOT NULL DEFAULT 0,
			admin_display_name VARCHAR(200) NOT NULL DEFAULT '',
			admin_login VARCHAR(200) NOT NULL DEFAULT '',
			declaration TEXT NOT NULL,
			signature_name VARCHAR(200) NOT NULL DEFAULT '',
			signature_hash VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ip (ip)
		) $charset_collate;";

		dbDelta( $sql_visits );
		dbDelta( $sql_events );
		dbDelta( $sql_bans );
		dbDelta( $sql_presence );
		dbDelta( $sql_unban_log );

		// One-time cleanup: collapse any older duplicate rows per IP left over
		// from before visits were deduplicated to a single row per visitor,
		// keeping only the most recent one. $visits is never user input --
		// it is always $wpdb->prefix concatenated with a fixed table name
		// defined above -- so there is nothing here to bind via prepare().
		$wpdb->query(
			"DELETE v1 FROM $visits v1 INNER JOIN $visits v2 ON v1.ip = v2.ip AND v1.id < v2.id" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( false === get_option( 'visise_settings' ) ) {
			add_option( 'visise_settings', VISISE_Settings::defaults() );
		}
		add_option( 'visise_db_version', VISISE_VERSION );
	}
}
