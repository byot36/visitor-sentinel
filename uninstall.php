<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'visise_visits',
	$wpdb->prefix . 'visise_events',
	$wpdb->prefix . 'visise_bans',
	$wpdb->prefix . 'visise_presence',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $table ) . "`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'visise_settings' );
delete_option( 'visise_db_version' );
delete_option( 'visise_honeyfile_slug' );
delete_option( 'visise_honeyfile_rules_version' );
delete_option( 'visise_decoy_api_key' );
delete_option( 'visise_decoy_username' );
delete_option( 'visise_trap_email' );

$timestamp = wp_next_scheduled( 'visise_daily_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'visise_daily_cleanup' );
}
