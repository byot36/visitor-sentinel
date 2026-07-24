<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deception layer: plants fake bait (a decoy file, a decoy API key, a decoy
 * admin username, a decoy email address) that no real visitor or legitimate
 * integration ever has a reason to touch. Any interaction with any of these
 * is treated as certain malicious intent — not a "soft" signal like a 404 or
 * request volume — and results in an immediate permanent ban, bypassing the
 * usual score threshold entirely.
 */
class VISISE_Honeypot {

	/**
	 * Query var used by the honeyfile rewrite rule.
	 */
	const HONEYFILE_QUERY_VAR = 'visise_honeyfile';

	/**
	 * REST namespace for the decoy "leaked config" endpoint.
	 */
	const REST_NAMESPACE = 'visise-internal/v1';

	public function __construct() {
		add_action( 'init', array( $this, 'register_honeyfile_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_honeyfile' ), 0 );

		add_action( 'rest_api_init', array( $this, 'register_decoy_rest_route' ) );
		add_action( 'rest_api_init', array( $this, 'check_decoy_api_key_usage' ), 999 );

		add_action( 'wp_login_failed', array( $this, 'check_decoy_username' ) );
		add_filter( 'authenticate', array( $this, 'block_decoy_username_early' ), 5, 3 );

		add_action( 'wp_footer', array( $this, 'render_email_trap' ) );
		add_filter( 'preprocess_comment', array( $this, 'check_email_trap_in_comment' ) );
		add_action( 'wp_mail_failed', array( $this, 'noop' ) );
	}

	public function noop() {}

	/* ------------------------------------------------------------------ */
	/* Honeyfile: a decoy file that a real visitor has no reason to fetch */
	/* ------------------------------------------------------------------ */

	/**
	 * The decoy path is randomized once per site (stored in options) so it
	 * can't be guessed from this plugin's public source code — an attacker
	 * has to actually discover it (e.g. via a leaked link, directory
	 * scanning, or a fake reference planted elsewhere) before it fires.
	 */
	public static function get_honeyfile_slug() {
		$slug = get_option( 'visise_honeyfile_slug' );

		if ( empty( $slug ) ) {
			$slug = 'backup-' . wp_generate_password( 8, false, false ) . '-passwords';
			update_option( 'visise_honeyfile_slug', $slug );
		}

		return $slug;
	}

	public function register_honeyfile_rewrite() {
		$slug = self::get_honeyfile_slug();
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '\.(txt|sql|zip|xlsx)$', 'index.php?' . self::HONEYFILE_QUERY_VAR . '=1', 'top' );

		// Flush once after the slug is (re)generated or the plugin is updated,
		// without requiring the site owner to manually visit Permalinks.
		if ( get_option( 'visise_honeyfile_rules_version' ) !== VISISE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'visise_honeyfile_rules_version', VISISE_VERSION );
		}
	}

	public function register_query_var( $vars ) {
		$vars[] = self::HONEYFILE_QUERY_VAR;
		return $vars;
	}

	/**
	 * Serves believable-looking decoy content (so an attacker who caught it
	 * via automated scanning doesn't immediately realize it's a trap and
	 * warn others), then permanently bans the requester on the spot.
	 */
	public function maybe_serve_honeyfile() {
		if ( '1' !== get_query_var( self::HONEYFILE_QUERY_VAR ) ) {
			return;
		}

		$ip = VISISE_IP::get_client_ip();

		if ( ! empty( $ip ) && ! VISISE_IP::is_whitelisted( $ip ) && ! ( is_user_logged_in() && current_user_can( 'manage_options' ) ) ) {
			VISISE_Logger::log_event( $ip, 'honeyfile_accessed', __( 'Accessed a decoy backup/credentials file that is never linked anywhere on the site — conclusive proof of directory scanning or a leaked/guessed URL.', 'visitor-sentinel' ), 100 );
			VISISE_Ban::apply_ban( $ip, __( 'Accessed a hidden honeyfile (decoy backup/credentials file).', 'visitor-sentinel' ), 100 );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		echo "db_host=127.0.0.1\ndb_name=wp_production\ndb_user=admin\ndb_pass=" . esc_html( wp_generate_password( 14, false, false ) ) . "\n";
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Honeytoken: a fake API key that only shows up if someone reads it   */
	/* off a decoy "leaked config" REST endpoint, then tries to use it     */
	/* ------------------------------------------------------------------ */

	public static function get_decoy_api_key() {
		$key = get_option( 'visise_decoy_api_key' );

		if ( empty( $key ) ) {
			$key = 'sk_live_' . wp_generate_password( 32, false, false );
			update_option( 'visise_decoy_api_key', $key );
		}

		return $key;
	}

	/**
	 * A REST endpoint styled to look like an internal/leaked debug route
	 * (e.g. discoverable via a scanner enumerating /wp-json/ namespaces),
	 * returning a fake API key. Reading this route itself is not penalized
	 * (a security researcher or automated crawler merely listing routes is
	 * not proof of intent) — only ever *using* the key it hands out is.
	 */
	public function register_decoy_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/config',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return new WP_REST_Response(
						array(
							'api_key'    => self::get_decoy_api_key(),
							'env'        => 'production',
							'debug'      => false,
						),
						200
					);
				},
			)
		);
	}

	/**
	 * Checked on every REST request: if the decoy key ever appears as a
	 * bearer token, X-Api-Key header, or api_key query/body parameter, the
	 * requester has proven they harvested it from the decoy endpoint above
	 * and are actively trying to use it — an unambiguous attack signal.
	 */
	public function check_decoy_api_key_usage() {
		$decoy = get_option( 'visise_decoy_api_key' );
		if ( empty( $decoy ) ) {
			return;
		}

		$candidates = array();

		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$candidates[] = trim( str_ireplace( 'bearer', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_API_KEY'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) );
		}
		if ( ! empty( $_REQUEST['api_key'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_REQUEST['api_key'] ) );
		}

		foreach ( $candidates as $candidate ) {
			if ( hash_equals( $decoy, $candidate ) ) {
				$ip = VISISE_IP::get_client_ip();
				if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
					return;
				}

				VISISE_Logger::log_event( $ip, 'honeytoken_api_key_used', __( 'Used the decoy API key harvested from the fake internal config endpoint — proof of active exploitation attempt, not accidental discovery.', 'visitor-sentinel' ), 100 );
				VISISE_Ban::apply_ban( $ip, __( 'Used a honeytoken API key.', 'visitor-sentinel' ), 100 );
				return;
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Honeytoken: a decoy admin username no real user of this site has    */
	/* ------------------------------------------------------------------ */

	public static function get_decoy_username() {
		$user = get_option( 'visise_decoy_username' );

		if ( empty( $user ) ) {
			$user = 'sysadmin_' . wp_generate_password( 5, false, false );
			update_option( 'visise_decoy_username', $user );
		}

		return $user;
	}

	/**
	 * Any login attempt against this exact username is certain automated
	 * credential-stuffing/brute-force — it was never registered on the site,
	 * so no genuine user could ever type it by mistake.
	 */
	public function block_decoy_username_early( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		$decoy = get_option( 'visise_decoy_username' );
		if ( ! empty( $decoy ) && sanitize_user( $username, true ) === $decoy ) {
			$ip = VISISE_IP::get_client_ip();
			if ( ! empty( $ip ) && ! VISISE_IP::is_whitelisted( $ip ) ) {
				VISISE_Logger::log_event( $ip, 'honeytoken_username_used', __( 'Attempted login with a decoy admin username that was never a real account on this site.', 'visitor-sentinel' ), 100 );
				VISISE_Ban::apply_ban( $ip, __( 'Attempted login with a honeytoken username.', 'visitor-sentinel' ), 100 );
			}
		}

		return $user;
	}

	public function check_decoy_username( $username ) {
		$decoy = get_option( 'visise_decoy_username' );
		if ( empty( $decoy ) || sanitize_user( $username, true ) !== $decoy ) {
			return;
		}

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return;
		}

		if ( ! VISISE_Ban::is_banned( $ip ) ) {
			VISISE_Ban::apply_ban( $ip, __( 'Attempted login with a honeytoken username.', 'visitor-sentinel' ), 100 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Email spam trap: a decoy address hidden from human eyes, planted    */
	/* only where scrapers/bots read markup, never where people look       */
	/* ------------------------------------------------------------------ */

	public static function get_trap_email() {
		$email = get_option( 'visise_trap_email' );

		if ( empty( $email ) ) {
			$domain = wp_parse_url( home_url(), PHP_URL_HOST );
			$email  = 'contact-' . wp_generate_password( 6, false, false ) . '@' . ( $domain ? $domain : 'example.com' );
			update_option( 'visise_trap_email', $email );
		}

		return $email;
	}

	/**
	 * Renders the trap address off-screen (never display:none, so it still
	 * survives naive "visible element" checks) in every page footer. Address
	 * harvesters that scrape mailto: links pick it up automatically; a human
	 * visitor never sees or interacts with it.
	 */
	public function render_email_trap() {
		$settings = VISISE_Settings::get();
		if ( empty( $settings['enable_honeypot_suite'] ) ) {
			return;
		}

		$email = self::get_trap_email();
		printf(
			'<a href="mailto:%1$s" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1" aria-hidden="true">%1$s</a>',
			esc_attr( $email )
		);
	}

	/**
	 * If the trap address ever shows up as the "from"/content of an inbound
	 * comment or contact submission, whoever is submitting harvested it from
	 * a scrape of this exact site — not a coincidence, and not something a
	 * real visitor could ever type by accident.
	 */
	public function check_email_trap_in_comment( $commentdata ) {
		$trap = get_option( 'visise_trap_email' );
		if ( empty( $trap ) ) {
			return $commentdata;
		}

		$haystack = strtolower( ( $commentdata['comment_author_email'] ?? '' ) . ' ' . ( $commentdata['comment_content'] ?? '' ) );

		if ( false !== strpos( $haystack, strtolower( $trap ) ) ) {
			$ip = VISISE_IP::get_client_ip();
			if ( ! empty( $ip ) && ! VISISE_IP::is_whitelisted( $ip ) ) {
				VISISE_Logger::log_event( $ip, 'honeytoken_email_harvested', __( 'Submission used the hidden spam-trap email address, proving this site was scraped by a bot harvesting addresses.', 'visitor-sentinel' ), 100 );
				VISISE_Ban::apply_ban( $ip, __( 'Used a honeytoken spam-trap email address.', 'visitor-sentinel' ), 100 );
			}
		}

		return $commentdata;
	}
}
