<?php
/**
 * Plugin Name: Visitor Sentinel
 * Plugin URI: https://wordpress.org/plugins/visitor-sentinel/
 * Description: Monitors site visitors, automatically detects suspicious activity (bots, scanners, attacks) and permanently blocks problematic IP addresses — including a full deception layer of decoys (honeyfile, honeytoken login, honeytoken API key, spam-trap email) that turn any interaction into an instant, certain block. Includes a complete control panel and an elegant visitor counter, visible only to logged-in members.
 * Version: 2.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Visitor Sentinel
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: visitor-sentinel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VISISE_VERSION', '2.2.0' );
define( 'VISISE_PLUGIN_FILE', __FILE__ );
define( 'VISISE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VISISE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VISISE_PLUGIN_DIR . 'includes/class-visise-db.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-ip.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-settings.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-logger.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-ban.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-notifications.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-geo.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-ua.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-icons.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-detector.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-honeypot.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-admin.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-frontend.php';
require_once VISISE_PLUGIN_DIR . 'includes/class-visise-cron.php';

/**
 * Plugin activation: creates the database tables, schedules the cleanup
 * task, and registers the honeyfile's rewrite rule immediately so the decoy
 * is live from the first request instead of waiting on WordPress's own
 * delayed rewrite flush.
 */
function visise_activate_plugin() {
	VISISE_DB::create_tables();
	VISISE_Cron::schedule();

	VISISE_Honeypot::get_honeyfile_slug();
	add_rewrite_rule( '^' . preg_quote( VISISE_Honeypot::get_honeyfile_slug(), '/' ) . '\.(txt|sql|zip|xlsx)$', 'index.php?' . VISISE_Honeypot::HONEYFILE_QUERY_VAR . '=1', 'top' );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'visise_activate_plugin' );

/**
 * Plugin deactivation: cancels the scheduled task (data is kept).
 */
function visise_deactivate_plugin() {
	VISISE_Cron::unschedule();
}
register_deactivation_hook( __FILE__, 'visise_deactivate_plugin' );

/**
 * Boots the plugin modules.
 */
function visise_run_plugin() {
	// Keeps the database schema current after a plugin update, without requiring
	// the site owner to manually deactivate/reactivate the plugin.
	if ( get_option( 'visise_db_version' ) !== VISISE_VERSION ) {
		VISISE_DB::create_tables();
	}

	new VISISE_Detector();
	new VISISE_Admin();
	new VISISE_Frontend();
	new VISISE_Cron();

	$settings = VISISE_Settings::get();
	if ( ! empty( $settings['enable_honeypot_suite'] ) ) {
		new VISISE_Honeypot();
	}
}
add_action( 'plugins_loaded', 'visise_run_plugin' );
