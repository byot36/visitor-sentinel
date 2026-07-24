<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays an elegant, real-time visitor counter on the front-end. The
 * "online now" figure refreshes automatically in the browser, without a
 * page reload. Visible to logged-in members by default; can optionally be
 * shown to guests too, via Settings.
 *
 * It also keeps a lightweight "still here, on this page" ping running on
 * every front-end page (independent of the badge), so the admin's Visitors
 * list reflects the page a visitor is currently on in real time, not just
 * the page they last navigated to.
 */
class VISISE_Frontend {

	const AJAX_ACTION = 'visise_live_stats';
	const TRACK_ACTION = 'visise_track_page';
	const OFFLINE_ACTION = 'visise_mark_offline';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_counter' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_get_live_stats' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_get_live_stats' ) );
		add_action( 'wp_ajax_' . self::TRACK_ACTION, array( $this, 'ajax_track_page' ) );
		add_action( 'wp_ajax_nopriv_' . self::TRACK_ACTION, array( $this, 'ajax_track_page' ) );
		add_action( 'wp_ajax_' . self::OFFLINE_ACTION, array( $this, 'ajax_mark_offline' ) );
		add_action( 'wp_ajax_nopriv_' . self::OFFLINE_ACTION, array( $this, 'ajax_mark_offline' ) );
	}

	private function is_visible() {
		$settings = VISISE_Settings::get();

		if ( empty( $settings['frontend_counter_enabled'] ) ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return ! empty( $settings['frontend_counter_show_guests'] );
		}

		return current_user_can( $settings['frontend_counter_role'] );
	}

	public function enqueue_assets() {
		$show_badge = $this->is_visible();

		if ( $show_badge ) {
			wp_enqueue_style( 'visise-frontend', VISISE_PLUGIN_URL . 'assets/css/frontend.css', array(), VISISE_VERSION );
		}

		// The page-tracking ping runs on every front-end page regardless of the
		// badge setting, so "Page visited" in the admin panel stays accurate
		// even when the badge itself is disabled or hidden from this visitor.
		wp_enqueue_script( 'visise-frontend', VISISE_PLUGIN_URL . 'assets/js/frontend.js', array(), VISISE_VERSION, true );
		wp_localize_script(
			'visise-frontend',
			'visiseFrontend',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => self::AJAX_ACTION,
				'nonce'       => wp_create_nonce( self::AJAX_ACTION ),
				'trackAction'   => self::TRACK_ACTION,
				'trackNonce'    => wp_create_nonce( self::TRACK_ACTION ),
				'offlineAction' => self::OFFLINE_ACTION,
				'offlineNonce'  => wp_create_nonce( self::OFFLINE_ACTION ),
				'showBadge'   => $show_badge,
				'fpEnabled'   => ! empty( VISISE_Settings::get()['enable_fingerprinting'] ),
				'intervalMs'  => 20000,
				'onlineText'  => __( '%s online now', 'visitor-sentinel' ),
				'todayText'   => __( '%s visitors today · %s visits in the last 7 days', 'visitor-sentinel' ),
			)
		);
	}

	/**
	 * Returns the current live stats as JSON, for the auto-refreshing badge.
	 * Restricted to logged-in users with the same permission as the badge itself.
	 */
	public function ajax_get_live_stats() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! $this->is_visible() ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success(
			array(
				'online' => VISISE_Logger::count_online(),
				'today'  => VISISE_Logger::count_visits_today(),
				'week'   => VISISE_Logger::count_visits_total( 7 ),
			)
		);
	}

	/**
	 * Records the page a visitor is currently on, sent periodically from the
	 * browser while they stay on the same page (a normal page load already
	 * does this on its own via VISISE_Detector; this keeps it current in between).
	 */
	public function ajax_track_page() {
		check_ajax_referer( self::TRACK_ACTION, 'nonce' );

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) || VISISE_Ban::is_banned( $ip ) ) {
			wp_send_json_success();
		}

		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';

		// Only accept a same-site relative path, never an arbitrary URL.
		if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, '//' ) ) {
			wp_send_json_success();
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$referer    = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		VISISE_Logger::log_visit( $ip, $user_agent, $path, $referer, is_user_logged_in() );
		VISISE_Logger::heartbeat( $ip );

		wp_send_json_success();
	}

	/**
	 * Marks the visitor as offline immediately when they close the tab or
	 * navigate away, sent via the reliable Beacon API — so "online now" drops
	 * right away instead of waiting for the online window to time out.
	 */
	public function ajax_mark_offline() {
		check_ajax_referer( self::OFFLINE_ACTION, 'nonce' );

		$ip = VISISE_IP::get_client_ip();
		if ( ! empty( $ip ) ) {
			VISISE_Logger::mark_offline( $ip );
		}

		wp_send_json_success();
	}

	public function render_counter() {
		if ( ! $this->is_visible() ) {
			return;
		}

		$settings = VISISE_Settings::get();
		$position = 'right' === $settings['frontend_counter_position'] ? 'right' : 'left';

		$online = VISISE_Logger::count_online();
		$today  = VISISE_Logger::count_visits_today();
		$week   = VISISE_Logger::count_visits_total( 7 );

		?>
		<div class="pv-visitor-badge pv-visitor-badge--<?php echo esc_attr( $position ); ?>" role="status" aria-live="polite" tabindex="0">
			<span class="pv-visitor-badge__dot" aria-hidden="true"></span>
			<span class="pv-visitor-badge__icon" aria-hidden="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 5C6.5 5 2.7 8.4 1 12c1.7 3.6 5.5 7 11 7s9.3-3.4 11-7c-1.7-3.6-5.5-7-11-7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
					<circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.6"/>
				</svg>
			</span>
			<span class="pv-visitor-badge__text" data-pv-online-text>
				<?php
				printf(
					/* translators: %s: number of visitors currently on the site. */
					esc_html__( '%s online now', 'visitor-sentinel' ),
					'<strong data-pv-online-value>' . esc_html( number_format_i18n( $online ) ) . '</strong>'
				);
				?>
			</span>
			<span class="pv-visitor-badge__tooltip" data-pv-tooltip-text>
				<?php
				printf(
					/* translators: 1: number of unique visitors today, 2: number of visits in the last 7 days. */
					esc_html__( '%1$s visitors today · %2$s visits in the last 7 days', 'visitor-sentinel' ),
					esc_html( number_format_i18n( $today ) ),
					esc_html( number_format_i18n( $week ) )
				);
				?>
			</span>
		</div>
		<?php
	}
}
