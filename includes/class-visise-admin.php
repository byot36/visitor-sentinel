<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin control panel.
 */
class VISISE_Admin {

	const CAPABILITY = 'manage_options';
	const AJAX_ACTION = 'visise_admin_online_count';
	const AJAX_VISITORS_ACTION = 'visise_admin_visitors_refresh';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_visise_ban_action', array( $this, 'handle_ban_action' ) );
		add_action( 'admin_post_visise_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_visise_export_bans', array( $this, 'handle_export_bans' ) );
		add_action( 'admin_post_visise_confirm_unban', array( $this, 'handle_confirm_unban' ) );
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_get_online_count' ) );
		add_action( 'wp_ajax_' . self::AJAX_VISITORS_ACTION, array( $this, 'ajax_get_visitors_rows' ) );
	}

	/**
	 * Returns the real-time "online now" count as JSON, for the dashboard's
	 * auto-refreshing card.
	 */
	public function ajax_get_online_count() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		// This poll itself proves the admin is still here watching the dashboard —
		// refresh their own presence so they don't drop out of "online now" while
		// simply sitting on the page (a normal page reload already does this too,
		// but this keeps it current without needing one).
		$ip = VISISE_IP::get_client_ip();
		if ( ! empty( $ip ) ) {
			VISISE_Logger::heartbeat( $ip );
		}

		wp_send_json_success( array( 'online' => VISISE_Logger::count_online() ) );
	}

	/**
	 * Returns the freshly rendered Visitors table rows as HTML, for the
	 * auto-refreshing Visitors page (so new visits, and the page each
	 * visitor is currently on, appear automatically without a reload).
	 */
	public function ajax_get_visitors_rows() {
		check_ajax_referer( self::AJAX_VISITORS_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		$ip = VISISE_IP::get_client_ip();
		if ( ! empty( $ip ) ) {
			VISISE_Logger::heartbeat( $ip );
		}

		$visits = VISISE_Logger::get_recent_visits( 30, 0 );

		ob_start();
		include VISISE_PLUGIN_DIR . 'includes/views/partials/visitors-rows.php';
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'  => $html,
				'empty' => empty( $visits ),
			)
		);
	}

	public function register_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad"><path d="M12 2 3 6v6c0 5.2 3.6 9.7 9 11 5.4-1.3 9-5.8 9-11V6l-9-4Zm0 9.6a2.4 2.4 0 1 1 0-4.8 2.4 2.4 0 0 1 0 4.8ZM7.5 16.5c.4-2.1 2.3-3.5 4.5-3.5s4.1 1.4 4.5 3.5H7.5Z"/></svg>'
		);

		add_menu_page(
			__( 'Visitor Sentinel', 'visitor-sentinel' ),
			__( 'Visitor Sentinel', 'visitor-sentinel' ),
			self::CAPABILITY,
			'visitor-sentinel',
			array( $this, 'render_dashboard' ),
			$icon,
			26
		);

		add_submenu_page( 'visitor-sentinel', __( 'Overview', 'visitor-sentinel' ), __( 'Overview', 'visitor-sentinel' ), self::CAPABILITY, 'visitor-sentinel', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'visitor-sentinel', __( 'Visitors', 'visitor-sentinel' ), __( 'Visitors', 'visitor-sentinel' ), self::CAPABILITY, 'visise-visitors', array( $this, 'render_visitors' ) );
		add_submenu_page( 'visitor-sentinel', __( 'Blocked IPs', 'visitor-sentinel' ), __( 'Blocked IPs', 'visitor-sentinel' ), self::CAPABILITY, 'visise-bans', array( $this, 'render_bans' ) );
		add_submenu_page( 'visitor-sentinel', __( 'History', 'visitor-sentinel' ), __( 'History', 'visitor-sentinel' ), self::CAPABILITY, 'visise-history', array( $this, 'render_history' ) );
		add_submenu_page( 'visitor-sentinel', __( 'Settings', 'visitor-sentinel' ), __( 'Settings', 'visitor-sentinel' ), self::CAPABILITY, 'visise-settings', array( $this, 'render_settings' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'visitor-sentinel' ) === false && strpos( $hook, 'pv-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'visise-admin', VISISE_PLUGIN_URL . 'assets/css/admin.css', array(), VISISE_VERSION );
		wp_enqueue_script( 'visise-admin', VISISE_PLUGIN_URL . 'assets/js/admin.js', array(), VISISE_VERSION, true );
		wp_localize_script(
			'visise-admin',
			'visiseAdmin',
			array(
				'confirmUnban'   => esc_html__( 'Are you sure you want to unblock this IP?', 'visitor-sentinel' ),
				'confirmDelete'  => esc_html__( 'Are you sure you want to delete this record?', 'visitor-sentinel' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'onlineAction'     => self::AJAX_ACTION,
				'onlineNonce'      => wp_create_nonce( self::AJAX_ACTION ),
				'onlineInterval'   => 15000,
				'visitorsAction'   => self::AJAX_VISITORS_ACTION,
				'visitorsNonce'    => wp_create_nonce( self::AJAX_VISITORS_ACTION ),
				'visitorsInterval' => 10000,
			)
		);
	}

	public function show_notices() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( empty( $_GET['visise_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['visise_notice'] ) );
		$messages = array(
			'unbanned'  => __( 'The IP was successfully unblocked.', 'visitor-sentinel' ),
			'banned'    => __( 'The IP was blocked.', 'visitor-sentinel' ),
			'saved'     => __( 'Settings saved.', 'visitor-sentinel' ),
			'whitelisted' => __( 'The IP was added to the whitelist and unblocked.', 'visitor-sentinel' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $notice ] ) );
		}
	}

	/** ---------- Dashboard ---------- */

	public function render_dashboard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$online         = VISISE_Logger::count_online();
		$today          = VISISE_Logger::count_visits_today();
		$week_visits    = VISISE_Logger::count_visits_total( 7 );
		$active_bans    = VISISE_Ban::count_active();
		$total_bans     = VISISE_Ban::count_all();
		$recent_events  = VISISE_Logger::get_recent_events( 15 );

		$daily_visits    = VISISE_Logger::get_daily_visit_counts( 14 );
		$top_pages       = VISISE_Logger::get_top_pages( 5, 30 );
		$top_referrers   = VISISE_Logger::get_top_referrers( 5, 30 );
		$event_breakdown = VISISE_Logger::get_event_type_breakdown( 8, 30 );
		$device_stats    = self::get_device_breakdown();

		include VISISE_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	/**
	 * Categorizes recent visits into Desktop / Mobile / Tablet / Bot based on
	 * their user-agent string, entirely from locally stored data — no
	 * external lookup service is used.
	 */
	private static function get_device_breakdown() {
		$user_agents = VISISE_Logger::get_recent_user_agents( 30 );

		$counts = array(
			'bot'     => 0,
			'mobile'  => 0,
			'tablet'  => 0,
			'desktop' => 0,
		);

		foreach ( $user_agents as $ua ) {
			$ua_lower = strtolower( (string) $ua );

			if ( '' === $ua_lower || preg_match( '/bot|crawl|spider|slurp|curl|wget|python|scrapy|headless|phantomjs|selenium|puppeteer/', $ua_lower ) ) {
				++$counts['bot'];
			} elseif ( preg_match( '/ipad|tablet/', $ua_lower ) ) {
				++$counts['tablet'];
			} elseif ( preg_match( '/mobile|iphone|android/', $ua_lower ) ) {
				++$counts['mobile'];
			} else {
				++$counts['desktop'];
			}
		}

		return $counts;
	}

	/** ---------- Visitors ---------- */

	public function render_visitors() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 30;
		$visits = VISISE_Logger::get_recent_visits( $per_page, ( $paged - 1 ) * $per_page );

		include VISISE_PLUGIN_DIR . 'includes/views/visitors.php';
	}

	/** ---------- Blocked IPs ---------- */

	public function render_bans() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// A block is only ever lifted through the "proces verbal" confirmation
		// screen below -- never instantly -- so a deliberate, signed decision
		// is always on record before that IP's history is wiped.
		if ( isset( $_GET['visise_view'] ) && 'confirm_unban' === $_GET['visise_view'] ) {
			$confirm_ip = isset( $_GET['ip'] ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '';

			if ( $confirm_ip && VISISE_IP::is_valid_ip( $confirm_ip ) ) {
				$confirm_ban = VISISE_Ban::get( $confirm_ip );

				if ( $confirm_ban ) {
					$confirm_events = VISISE_Logger::get_events_for_ip( $confirm_ip, 20 );
					include VISISE_PLUGIN_DIR . 'includes/views/unban-confirm.php';
					return;
				}
			}
		}

		$inspect_ip = isset( $_GET['ip'] ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '';
		$inspect_ban = $inspect_events = null;

		if ( $inspect_ip && VISISE_IP::is_valid_ip( $inspect_ip ) ) {
			$inspect_ban    = VISISE_Ban::get( $inspect_ip );
			$inspect_events = VISISE_Logger::get_events_for_ip( $inspect_ip, 50 );
		}

		$bans = VISISE_Ban::get_all_active( 100, 0 );

		include VISISE_PLUGIN_DIR . 'includes/views/bans.php';
	}

	/**
	 * Processes the accepted unban declaration: writes the permanent audit
	 * record, then wipes the IP's ban and history so it starts clean.
	 */
	public function handle_confirm_unban() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have the required permission.', 'visitor-sentinel' ) );
		}

		check_admin_referer( 'visise_confirm_unban_nonce' );

		$ip             = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		$declaration    = isset( $_POST['declaration'] ) ? sanitize_textarea_field( wp_unslash( $_POST['declaration'] ) ) : '';
		$signature_name = isset( $_POST['signature_name'] ) ? sanitize_text_field( wp_unslash( $_POST['signature_name'] ) ) : '';

		if ( empty( $ip ) || ! VISISE_IP::is_valid_ip( $ip ) || empty( $declaration ) || empty( $signature_name ) ) {
			wp_die( esc_html__( 'The declaration and signature name are required to lift a permanent block.', 'visitor-sentinel' ) );
		}

		$record_id = VISISE_Ban::unban_with_declaration( $ip, $declaration, $signature_name );

		$redirect = $record_id
			? add_query_arg( array( 'page' => 'visise-history', 'view' => $record_id, 'visise_notice' => 'unbanned' ), admin_url( 'admin.php' ) )
			: add_query_arg( array( 'page' => 'visise-bans', 'visise_notice' => 'unbanned' ), admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/** ---------- History ---------- */

	public function render_history() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		global $wpdb;

		$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

		if ( $view_id ) {
			$record = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM " . VISISE_DB::unban_log_table() . " WHERE id = %d", $view_id )
			);

			if ( $record ) {
				include VISISE_PLUGIN_DIR . 'includes/views/unban-record-print.php';
				return;
			}
		}

		$records = $wpdb->get_results(
			"SELECT * FROM " . VISISE_DB::unban_log_table() . " ORDER BY created_at DESC LIMIT 200" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, fixed query.
		);

		include VISISE_PLUGIN_DIR . 'includes/views/history.php';
	}

	/**
	 * Streams the full list of blocked IPs as a downloadable CSV file.
	 */
	public function handle_export_bans() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have the required permission.', 'visitor-sentinel' ) );
		}

		check_admin_referer( 'visise_export_nonce' );

		$bans = VISISE_Ban::get_all( 10000, 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=visitor-sentinel-blocked-ips-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'IP', 'Type', 'Reason', 'Score', 'Temp bans', 'Created', 'Expires', 'Updated' ) );

		foreach ( $bans as $ban ) {
			fputcsv(
				$output,
				array(
					$ban->ip,
					$ban->ban_type,
					$ban->reason,
					$ban->score,
					$ban->temp_ban_count,
					$ban->created_at,
					$ban->expires_at ? $ban->expires_at : '',
					$ban->updated_at,
				)
			);
		}

		fclose( $output );
		exit;
	}

	public function handle_ban_action() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have the required permission.', 'visitor-sentinel' ) );
		}

		check_admin_referer( 'visise_ban_nonce' );

		$ip     = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		$action = isset( $_POST['visise_action'] ) ? sanitize_key( wp_unslash( $_POST['visise_action'] ) ) : '';

		if ( empty( $ip ) || ! VISISE_IP::is_valid_ip( $ip ) ) {
			wp_die( esc_html__( 'Invalid IP address.', 'visitor-sentinel' ) );
		}

		$notice = '';

		switch ( $action ) {
			case 'manual_ban':
				$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
				if ( '' === $reason ) {
					$reason = __( 'Manually blocked by administrator.', 'visitor-sentinel' );
				}
				VISISE_Ban::manual_ban( $ip, $reason );
				$notice = 'banned';
				break;

			case 'whitelist':
				// Whitelisting is the one way to release an IP without a signed
				// declaration, because it is an explicit statement that this
				// address is trusted -- and it must stay available so the owner
				// can recover from blocking their own address by mistake.
				self::add_to_whitelist( $ip );
				VISISE_Ban::unban( $ip );
				$notice = 'whitelisted';
				break;
		}

		$redirect = add_query_arg(
			array(
				'page'      => 'visise-bans',
				'visise_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Adds an IP to the whitelist in the settings, if not already present.
	 */
	private static function add_to_whitelist( $ip ) {
		$settings = VISISE_Settings::get();
		$list     = array_filter( array_map( 'trim', explode( "\n", $settings['whitelist_ips'] ) ) );

		if ( in_array( $ip, $list, true ) ) {
			return;
		}

		$list[] = $ip;
		$settings['whitelist_ips'] = implode( "\n", $list );
		VISISE_Settings::update( $settings );
	}

	/** ---------- Settings ---------- */

	public function render_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = VISISE_Settings::get();

		include VISISE_PLUGIN_DIR . 'includes/views/settings.php';
	}

	public function handle_save_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have the required permission.', 'visitor-sentinel' ) );
		}

		check_admin_referer( 'visise_settings_nonce' );

		$input    = isset( $_POST['visise_settings'] ) ? wp_unslash( $_POST['visise_settings'] ) : array();
		$sanitized = VISISE_Settings::sanitize( is_array( $input ) ? $input : array() );

		VISISE_Settings::update( $sanitized );

		$redirect = add_query_arg(
			array(
				'page'      => 'visise-settings',
				'visise_notice' => 'saved',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
