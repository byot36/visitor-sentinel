<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled maintenance: daily data cleanup (old visits/events, stale
 * presence rows).
 */
class VISISE_Cron {

	const HOOK = 'visise_daily_cleanup';

	public function __construct() {
		add_action( self::HOOK, array( $this, 'run_cleanup' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public function run_cleanup() {
		$settings = VISISE_Settings::get();
		VISISE_Logger::purge_old_data( $settings['retention_days'] );
		VISISE_Logger::purge_stale_presence();
	}
}
