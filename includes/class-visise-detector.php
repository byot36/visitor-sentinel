<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detection engine: monitors every request, calculates a risk score
 * and decides whether an IP should be temporarily or permanently blocked.
 */
class VISISE_Detector {

	/**
	 * Path patterns commonly used by automated scanners/attackers.
	 */
	private $suspicious_paths = array(
		'wp-config.php',
		'.env',
		'.git/',
		'.svn/',
		'phpmyadmin',
		'/etc/passwd',
		'eval(',
		'base64_decode(',
		'union select',
		'information_schema',
		'/wp-content/uploads/../',
		'<script',
		'..%2f..%2f',
		'xmlrpc.php',
		'wp-json/wp/v2/users',
		'wp-content/debug.log',
		'.sql',
		'.bak',
		'backup.zip',
		'/vendor/phpunit',
		'/wp-includes/wlwmanifest.xml',
		'onerror=',
		'javascript:',
		'/../../',
		'%00',
		'etc/shadow',
		'cmd.exe',
		'/bin/sh',
		'?rest_route=/wp/v2/users',
		'wp-json/oembed',
		'.sql.gz',
		'.tar.gz',
		'id_rsa',
		'.htpasswd',
		'shell.php',
		'c99.php',
		'r57.php',
		'wp-content/uploads/../../wp-config',
		'phpunit/src/util/php/eval-stdin.php',
		'config.json',
		'/.well-known/acme-challenge/../',
		'passwd%00',
		'%2e%2e%2f',
		'select+*+from',
		'insert+into',
		'drop+table',
		'waitfor+delay',
		'sleep(',
		'benchmark(',
		// Web shells and malware droppers commonly left behind after a
		// successful compromise, or probed for by attackers hoping a
		// previous attacker (or a vulnerable plugin) already planted one.
		'wp-vcd.php',
		'wp-tmp.php',
		'adminer.php',
		'debug.php',
		'system(',
		'passthru(',
		'proc_open(',
		'fsockopen(',
		'assert(',
		'.aws/credentials',
		'docker-compose.yml',
		// Typical phishing-kit filenames dropped on compromised sites to
		// impersonate a bank/payment/webmail login page.
		'paypal-secure',
		'paypal-login',
		'verify-account',
		'account-locked',
		'apple-id-verify',
		'signin-update',
		'webmail-update',
		'secure-update-center',
	);

	/**
	 * Query string parameters typical of user enumeration or injection attempts.
	 */
	private $suspicious_query_patterns = array(
		'author=',
		'select%20',
		'1=1',
		'or%201=1',
		'%3cscript',
	);

	/**
	 * User-agent keywords typical of scanning/attack tools or automated/headless
	 * browsers often used for abuse.
	 * Known legitimate bots (Googlebot, Bingbot, etc.) are never penalized.
	 */
	private $suspicious_ua_keywords = array(
		'sqlmap',
		'nikto',
		'nmap',
		'masscan',
		'curl/',
		'python-requests',
		'python-urllib',
		'libwww-perl',
		'httpclient',
		'wpscan',
		'acunetix',
		'nessus',
		'dirbuster',
		'gobuster',
		'zgrab',
		'go-http-client',
		'headlesschrome',
		'phantomjs',
		'selenium',
		'puppeteer',
		'java/',
		'ruby',
		'mechanize',
		'scrapy',
		'okhttp',
		// HTTP libraries commonly embedded in custom desktop programs, mobile
		// apps, and API-testing tools rather than a real web browser — a
		// request/attack script running from Windows or a phone typically
		// identifies itself with one of these instead of a genuine browser UA.
		'axios/',
		'node-fetch',
		'aiohttp/',
		'go-resty',
		'restsharp',
		'unirest',
		'httpx',
		'winhttp',
		'wininet',
		'dart:io',
		'cfnetwork',
		'alamofire',
		'postmanruntime',
		'insomnia',
		'paw/',
		'httpie',
		'apache-httpclient',
	);

	private $known_good_bots = array(
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'facebookexternalhit',
		'applebot',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'handle_request' ), 1 );
		add_action( 'wp_login_failed', array( $this, 'handle_login_failed' ) );
		add_action( 'template_redirect', array( $this, 'handle_404' ) );
		add_filter( 'preprocess_comment', array( $this, 'handle_comment_submission' ) );
		add_action( 'login_form', array( $this, 'render_login_honeypot' ) );
		add_filter( 'authenticate', array( $this, 'check_login_honeypot' ), 30, 1 );
		add_action( 'comment_form', array( $this, 'render_comment_honeypot' ) );
		add_action( 'xmlrpc_call', array( $this, 'handle_xmlrpc_call' ) );
	}

	/**
	 * Outputs an invisible "honeypot" field on the login form. Real visitors
	 * never see or fill it (hidden off-screen, not display:none, so even bots
	 * that check computed visibility are still caught); automated login bots
	 * that blindly fill every field give themselves away instantly.
	 */
	public function render_login_honeypot() {
		?>
		<p style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label for="visise_website"><?php esc_html_e( 'Website', 'visitor-sentinel' ); ?></label>
			<input type="text" name="visise_website" id="visise_website" tabindex="-1" autocomplete="off" value="" />
		</p>
		<input type="hidden" name="visise_ts" value="<?php echo esc_attr( current_time( 'timestamp' ) ); ?>" />
		<?php
	}

	/**
	 * Checks the login honeypot and submission timing on every login attempt.
	 * A filled honeypot, or a submission faster than a human could possibly
	 * type a password, is treated as certain bot activity.
	 */
	public function check_login_honeypot( $user ) {
		if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return $user;
		}

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return $user;
		}

		$login_hp = isset( $_POST['visise_website'] ) ? sanitize_text_field( wp_unslash( $_POST['visise_website'] ) ) : '';
		$login_ts = isset( $_POST['visise_ts'] ) ? absint( wp_unslash( $_POST['visise_ts'] ) ) : 0;

		if ( ! empty( $login_hp ) ) {
			VISISE_Logger::log_event( $ip, 'honeypot_triggered', __( 'Login form honeypot field was filled in — a strong signal of an automated bot, not a human.', 'visitor-sentinel' ), 50 );
			$this->maybe_ban( $ip );
		} elseif ( ! empty( $login_ts ) ) {
			$elapsed = current_time( 'timestamp' ) - $login_ts;
			if ( $elapsed >= 0 && $elapsed < 2 ) {
				VISISE_Logger::log_event( $ip, 'fast_submit_bot', __( 'Login form submitted in under 2 seconds — faster than a human can type.', 'visitor-sentinel' ), 35 );
				$this->maybe_ban( $ip );
			}
		}

		return $user;
	}

	/**
	 * Outputs an invisible honeypot field on the comment form, alongside a
	 * render-timestamp used to catch instant, scripted comment submissions.
	 */
	public function render_comment_honeypot() {
		?>
		<p style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label for="visise_comment_hp"><?php esc_html_e( 'Leave this field empty', 'visitor-sentinel' ); ?></label>
			<input type="text" name="visise_comment_hp" id="visise_comment_hp" tabindex="-1" autocomplete="off" value="" />
		</p>
		<input type="hidden" name="visise_comment_ts" value="<?php echo esc_attr( current_time( 'timestamp' ) ); ?>" />
		<?php
	}

	/**
	 * Flags XML-RPC calls to methods best known for abuse: pingback.ping (used
	 * to fingerprint internal networks and for reflected DDoS) and
	 * system.multicall (used to brute-force hundreds of password guesses
	 * inside a single HTTP request, bypassing simple rate limiting).
	 */
	public function handle_xmlrpc_call( $method ) {
		$abused_methods = array( 'pingback.ping', 'pingback.extensions.getPingbacks', 'system.multicall' );

		if ( ! in_array( $method, $abused_methods, true ) ) {
			return;
		}

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return;
		}

		/* translators: %s: the XML-RPC method that was called. */
		VISISE_Logger::log_event( $ip, 'xmlrpc_abuse', sprintf( __( 'XML-RPC method associated with abuse (pingback reflection or brute-force multicall): %s', 'visitor-sentinel' ), $method ), 35 );
		$this->maybe_ban( $ip );
	}

	public function handle_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( wp_doing_cron() ) {
			return;
		}

		// admin-ajax.php requests are never a "page" a visitor is looking at — they
		// are always background/internal mechanics (this plugin's own live ping,
		// WordPress's Heartbeat API, Action Scheduler, WooCommerce, contact forms,
		// etc.), never a real page view, and are never tracked as a visit here.
		// Real-time "current page" tracking is handled separately and reliably by
		// the explicit, validated ping in VISISE_Frontend::ajax_track_page().
		if ( wp_doing_ajax() ) {
			return;
		}

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) ) {
			return;
		}

		// A ban applies to the IP address itself, unconditionally -- logged-in
		// administrators are NOT exempt. A banned IP is blocked everywhere on
		// the site, on every single request, with no exceptions for anyone.
		// (If you ever ban your own IP by mistake, recover via the whitelist
		// setting or by removing the row directly from the database --
		// there is deliberately no built-in bypass for logged-in admins.)
		$ban = VISISE_Ban::find_active_for_request( $ip );
		if ( $ban ) {
			VISISE_Ban::register_hit_while_banned( $ban->ip );
			$this->block_visitor( $ban );
			return;
		}

		$is_trusted_admin = is_user_logged_in() && current_user_can( 'manage_options' );

		// wp-admin pages are tracked for any logged-in user (admin, editor, author,
		// etc.) so the Visitors list also shows their navigation inside the
		// dashboard — but not for anonymous requests (WordPress redirects those to
		// the login page anyway).
		if ( is_admin() && ! is_user_logged_in() ) {
			return;
		}

		if ( VISISE_IP::is_whitelisted( $ip ) ) {
			return;
		}

		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$referer     = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		// Logged-in administrators are still exempt from *new* detection/scoring
		// (so their own normal admin activity can never accidentally trigger a
		// fresh ban against themselves) -- but this no longer skips the ban
		// check above, which always applies first, to everyone.
		if ( $is_trusted_admin ) {
			VISISE_Logger::log_visit( $ip, $user_agent, $request_uri, $referer, true );
			VISISE_Logger::heartbeat( $ip );
			return;
		}

		VISISE_Logger::log_visit( $ip, $user_agent, $request_uri, $referer, is_user_logged_in() );
		VISISE_Logger::heartbeat( $ip );

		$this->analyze( $ip, $user_agent, $request_uri );
	}

	/**
	 * Runs the detection heuristics and accumulates a risk score for the IP.
	 */
	private function analyze( $ip, $user_agent, $request_uri ) {
		$settings = VISISE_Settings::get();

		// 1. Suspicious user-agent (known scanning/attack tools).
		$ua_lower = strtolower( $user_agent );
		if ( empty( $user_agent ) ) {
			VISISE_Logger::log_event( $ip, 'empty_user_agent', __( 'Request with no User-Agent header (typical of automated scripts).', 'visitor-sentinel' ), 15 );
		} else {
			$is_good_bot = false;
			foreach ( $this->known_good_bots as $good ) {
				if ( false !== strpos( $ua_lower, $good ) ) {
					$is_good_bot = true;
					break;
				}
			}

			if ( ! $is_good_bot ) {
				foreach ( $this->suspicious_ua_keywords as $keyword ) {
					if ( false !== strpos( $ua_lower, $keyword ) ) {
						/* translators: %s: user-agent fragment identified as a scanning tool. */
						VISISE_Logger::log_event( $ip, 'suspicious_user_agent', sprintf( __( 'User-Agent associated with scanning/attack tools: %s', 'visitor-sentinel' ), $keyword ), 25 );
						break;
					}
				}
			}
		}

		// 1b. Missing standard browser headers. A real web browser (on a
		// desktop or a phone) always sends an Accept header describing what
		// content types it wants, and almost always an Accept-Language.
		// Custom programs, scripts, and mobile apps making raw HTTP requests
		// (as opposed to someone actually browsing the site in a phone/PC
		// browser) very often skip these entirely. This is only ever a soft
		// signal on its own — some legitimate lightweight tools also omit
		// them — but combined with any other signal it helps confirm
		// automated, non-browser traffic.
		$accept_header = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( ! empty( $user_agent ) && empty( $accept_header ) && ! VISISE_Logger::has_recent_event( $ip, 'non_browser_client', 300 ) ) {
			VISISE_Logger::log_event( $ip, 'non_browser_client', __( 'Request with no Accept header — typical of a program, script, or app making raw HTTP requests rather than an actual browser.', 'visitor-sentinel' ), 10 );
		}

		// 2. Suspicious request patterns (vulnerability scanning, injections).
		$uri_lower = strtolower( $request_uri );
		foreach ( $this->suspicious_paths as $pattern ) {
			if ( false !== strpos( $uri_lower, $pattern ) ) {
				/* translators: %s: suspicious pattern detected in the URL. */
				VISISE_Logger::log_event( $ip, 'suspicious_request', sprintf( __( 'Request to a sensitive resource or attack pattern: %s', 'visitor-sentinel' ), $pattern ), 40 );
				break;
			}
		}

		// 2b. Suspicious query parameters (user enumeration, SQL/XSS injection).
		foreach ( $this->suspicious_query_patterns as $pattern ) {
			if ( false !== strpos( $uri_lower, $pattern ) ) {
				/* translators: %s: suspicious parameter detected in the URL. */
				VISISE_Logger::log_event( $ip, 'suspicious_query', sprintf( __( 'Suspicious query parameter (possible enumeration or injection): %s', 'visitor-sentinel' ), $pattern ), 20 );
				break;
			}
		}

		// 3. Rate limiting (possible aggressive bot / brute-force attack).
		// A high request count on its own is not proof of malicious intent (a real
		// visitor can easily browse 50-100 pages) — it is only logged as a soft
		// signal, throttled to once per minute, and by itself can never reach the
		// ban threshold (see maybe_ban()).
		$requests = VISISE_Logger::track_request_and_get_count( $ip, $settings['rate_limit_seconds'] );
		if ( $requests > $settings['rate_limit_requests'] && ! VISISE_Logger::has_recent_event( $ip, 'rate_limit', 60 ) ) {
			/* translators: 1: number of requests, 2: interval in seconds. */
			VISISE_Logger::log_event( $ip, 'rate_limit', sprintf( __( 'High request rate: %1$d requests in %2$d seconds.', 'visitor-sentinel' ), $requests, $settings['rate_limit_seconds'] ), 15 );
		}

		// 3b. Traffic flood (DDoS-style burst): a short, extremely fast burst of
		// requests, well beyond anything a human clicking links could produce
		// (no real visitor can request 40+ pages in 8 seconds). Unlike the soft
		// rate-limit signal above, this alone is treated as high-confidence
		// evidence of an automated attack, not just heavy browsing.
		$burst = VISISE_Logger::track_request_and_get_count( $ip . '_burst', 8 );
		if ( $burst > 40 && ! VISISE_Logger::has_recent_event( $ip, 'traffic_flood', 30 ) ) {
			/* translators: %d: number of requests received within an 8-second burst window. */
			VISISE_Logger::log_event( $ip, 'traffic_flood', sprintf( __( 'Traffic flood: %d requests within 8 seconds — far beyond human browsing speed, consistent with a DDoS-style attack.', 'visitor-sentinel' ), $burst ), 60 );
		}

		$this->maybe_ban( $ip );
	}

	public function handle_login_failed( $username ) {
		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return;
		}

		VISISE_Logger::log_event( $ip, 'login_failed', __( 'Failed login attempt in the admin area.', 'visitor-sentinel' ), 15 );

		// Dedicated brute-force check: many failed attempts in a short window,
		// independent of the site's general page-request rate limit.
		$attempts = VISISE_Logger::track_login_attempt( $ip, 60 );
		if ( $attempts > 5 && ! VISISE_Logger::has_recent_event( $ip, 'brute_force_login', 60 ) ) {
			/* translators: %d: number of failed login attempts in the last 60 seconds. */
			VISISE_Logger::log_event( $ip, 'brute_force_login', sprintf( __( 'Brute-force pattern: %d failed login attempts within 60 seconds.', 'visitor-sentinel' ), $attempts ), 40 );
		}

		// Credential-stuffing check: a real person mistypes their own username a
		// couple of times; trying many *different* usernames is a bot signature.
		if ( ! empty( $username ) ) {
			$distinct = VISISE_Logger::track_login_username_and_get_distinct_count( $ip, $username, 600 );
			if ( $distinct > 3 && ! VISISE_Logger::has_recent_event( $ip, 'credential_stuffing', 300 ) ) {
				/* translators: %d: number of distinct usernames tried from this IP. */
				VISISE_Logger::log_event( $ip, 'credential_stuffing', sprintf( __( 'Credential-stuffing pattern: %d different usernames tried from the same IP.', 'visitor-sentinel' ), $distinct ), 40 );
			}
		}

		$this->maybe_ban( $ip );
	}

	public function handle_404() {
		$settings = VISISE_Settings::get();
		if ( empty( $settings['track_404'] ) || ! is_404() ) {
			return;
		}

		$ip = VISISE_IP::get_client_ip();
		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		VISISE_Logger::log_event( $ip, 'not_found', __( 'Access to a non-existent page (possible content scanning).', 'visitor-sentinel' ), 5 );

		// A generous threshold: broken images, old bookmarks, or a site with a
		// few dead links can easily rack up a dozen 404s from one real visitor.
		// This alone is only ever a "soft" signal (see high_confidence_event_types
		// below) — it can never ban someone on its own, only add supporting
		// weight alongside a genuine attack/bot signal.
		$count = VISISE_Logger::count_404_in_window( $ip, 300 );
		if ( $count > 30 && ! VISISE_Logger::has_recent_event( $ip, 'not_found_flood', 300 ) ) {
			/* translators: %d: number of non-existent pages accessed. */
			VISISE_Logger::log_event( $ip, 'not_found_flood', sprintf( __( 'High volume of non-existent pages accessed in 5 minutes: %d.', 'visitor-sentinel' ), $count ), 10 );
		}

		$this->maybe_ban( $ip );
	}

	/**
	 * Analyzes submitted comments (from members or guests) for typical spam patterns:
	 * high link count, frequent spam keywords.
	 */
	public function handle_comment_submission( $commentdata ) {
		$ip = VISISE_IP::get_client_ip();

		if ( empty( $ip ) || VISISE_IP::is_whitelisted( $ip ) ) {
			return $commentdata;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return $commentdata;
		}

		$comment_hp = isset( $_POST['visise_comment_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['visise_comment_hp'] ) ) : '';
		$comment_ts = isset( $_POST['visise_comment_ts'] ) ? absint( wp_unslash( $_POST['visise_comment_ts'] ) ) : 0;

		if ( ! empty( $comment_hp ) ) {
			VISISE_Logger::log_event( $ip, 'honeypot_triggered', __( 'Comment form honeypot field was filled in — a strong signal of an automated bot, not a human.', 'visitor-sentinel' ), 50 );
		} elseif ( ! empty( $comment_ts ) ) {
			$elapsed = current_time( 'timestamp' ) - $comment_ts;
			if ( $elapsed >= 0 && $elapsed < 2 ) {
				VISISE_Logger::log_event( $ip, 'fast_submit_bot', __( 'Comment submitted in under 2 seconds — faster than a human can type.', 'visitor-sentinel' ), 35 );
			}
		}

		$content    = isset( $commentdata['comment_content'] ) ? (string) $commentdata['comment_content'] : '';
		$link_count = preg_match_all( '#https?://#i', $content, $matches );

		if ( $link_count >= 3 ) {
			/* translators: %d: number of links detected in the comment. */
			VISISE_Logger::log_event( $ip, 'comment_spam', sprintf( __( 'Comment with an unusually high number of links (%d), typical of spam.', 'visitor-sentinel' ), $link_count ), 25 );
		}

		$spam_keywords = array(
			'viagra',
			'casino',
			'porn',
			'xxx',
			'crypto airdrop',
			'loan approved',
			'bitcoin doubler',
			// Typical phishing/social-engineering phrasing planted by spam bots.
			'verify your account',
			'account has been suspended',
			'click here to confirm',
			'urgent action required',
			'wire transfer',
			'gift card codes',
			'your account will be closed',
			'claim your prize',
		);
		$content_lower = strtolower( $content );
		foreach ( $spam_keywords as $keyword ) {
			if ( false !== strpos( $content_lower, $keyword ) ) {
				/* translators: %s: detected spam keyword. */
				VISISE_Logger::log_event( $ip, 'comment_spam_keyword', sprintf( __( 'Comment contains a typical spam term: %s', 'visitor-sentinel' ), $keyword ), 25 );
				break;
			}
		}

		$this->maybe_ban( $ip );

		if ( VISISE_Ban::is_banned( $ip ) ) {
			wp_die(
				esc_html__( 'This comment was rejected for security reasons.', 'visitor-sentinel' ),
				esc_html__( 'Comment rejected', 'visitor-sentinel' ),
				array( 'response' => 403 )
			);
		}

		return $commentdata;
	}

	/**
	 * Event types that are, on their own, hard evidence of malicious intent
	 * (an actual attack pattern, hacking tool, or spam content) — as opposed
	 * to "soft" signals like request volume or a single 404, which only
	 * describe behaviour that real visitors can also produce.
	 */
	private $high_confidence_event_types = array(
		'suspicious_user_agent',
		'suspicious_request',
		'suspicious_query',
		'comment_spam',
		'comment_spam_keyword',
		'login_failed',
		'honeypot_triggered',
		'fast_submit_bot',
		'xmlrpc_abuse',
		'brute_force_login',
		'credential_stuffing',
		'traffic_flood',
	);

	/**
	 * Checks the accumulated score and applies a ban if the threshold is exceeded
	 * AND there is genuine evidence of malicious intent — never for sheer
	 * browsing volume alone. A visitor who simply opens 100 pages will never be
	 * blocked unless at least one real attack/bot/spam signal was detected, or
	 * several different kinds of suspicious behaviour occurred together.
	 */
	/**
	 * Signals that describe behaviour a real visitor can also produce (high
	 * traffic, some 404s, a missing header) -- never proof of malicious
	 * intent on their own. Crucially, seeing several of these together is
	 * NOT the same as seeing several genuinely different kinds of evidence:
	 * e.g. 'not_found' and 'not_found_flood' are just two sizes of the exact
	 * same soft signal (browsing dead links), so they must never be able to
	 * satisfy the "multiple distinct signal types" requirement between them.
	 */
	private $soft_event_types = array(
		'not_found',
		'not_found_flood',
		'rate_limit',
		'empty_user_agent',
		'non_browser_client',
		'anonymized_source',
	);

	private function maybe_ban( $ip ) {
		$settings = VISISE_Settings::get();
		$score    = VISISE_Logger::get_score_for_ip( $ip, 60 );

		if ( $score < $settings['score_threshold'] ) {
			return;
		}

		$recent_events = VISISE_Logger::get_events_for_ip( $ip, 20 );
		$event_types   = array_unique( wp_list_pluck( $recent_events, 'event_type' ) );

		$has_high_confidence_signal = (bool) array_intersect( $event_types, $this->high_confidence_event_types );

		// Only distinct *meaningful* signal types count toward the "multiple
		// signals" quorum -- stacking up soft signals (e.g. several 404-related
		// events) can never substitute for genuine attack evidence.
		$meaningful_types          = array_diff( $event_types, $this->soft_event_types );
		$has_multiple_signal_types = count( $meaningful_types ) >= 2;

		if ( ! $has_high_confidence_signal && ! $has_multiple_signal_types ) {
			// Only soft signals (e.g. request volume or 404 browsing) were seen —
			// not enough evidence of hacking/bot/spam behaviour to justify a block.
			return;
		}

		$reasons = wp_list_pluck( array_slice( $recent_events, 0, 5 ), 'description' );
		$reason  = implode( ' | ', array_unique( $reasons ) );

		if ( empty( $reason ) ) {
			$reason = __( 'High risk score based on recent activity.', 'visitor-sentinel' );
		}

		VISISE_Ban::apply_ban( $ip, $reason, $score );
	}

	/**
	 * Displays a detailed block page for a banned IP, explaining why it was
	 * blocked and listing the specific suspicious activity detected
	 * (including any attack pattern, e.g. SQL injection, that was caught),
	 * then stops execution.
	 */
	private function block_visitor( $ban ) {
		if ( ! headers_sent() ) {
			status_header( 403 );
			nocache_headers();
		}

		VISISE_Ban::set_device_cookie( $ban );

		wp_die(
			self::build_block_page_html( $ban ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html() on every dynamic value.
			esc_html( self::block_page_title( $ban ) ),
			array( 'response' => 403 )
		);
	}

	/**
	 * The <title> used for the block page.
	 */
	public static function block_page_title( $ban ) {
		return 'permanent' === $ban->ban_type
			? __( 'Access permanently blocked', 'visitor-sentinel' )
			: __( 'Access temporarily blocked', 'visitor-sentinel' );
	}

	/**
	 * Builds the block page HTML shown to a banned visitor.
	 */
	public static function build_block_page_html( $ban ) {
		$is_permanent = 'permanent' === $ban->ban_type;
		$events       = VISISE_Logger::get_events_for_ip( $ban->ip, 10 );

		ob_start();
		?>
		<style>
			html,body{height:100%;margin:0;}
			body{background:linear-gradient(160deg,#0d1526 0%,#101d33 55%,#132a45 100%);}
			.visise-block-overlay{
				position:fixed;
				inset:0;
				display:flex;
				align-items:flex-start;
				justify-content:center;
				background:linear-gradient(160deg,#0d1526 0%,#101d33 55%,#132a45 100%);
				font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
				padding:48px 16px;
				box-sizing:border-box;
				overflow-y:auto;
				z-index:2147483647;
			}
			.visise-block-card{
				width:100%;
				max-width:640px;
				background:#131f36;
				border:1px solid #26364f;
				border-radius:16px;
				box-shadow:0 20px 60px rgba(0,0,0,.35);
				padding:40px 36px;
				box-sizing:border-box;
			}
			.visise-block-icon{
				width:60px;height:60px;border-radius:50%;
				display:flex;align-items:center;justify-content:center;
				background:<?php echo $is_permanent ? 'rgba(224,48,63,.15)' : 'rgba(217,119,6,.15)'; ?>;
				margin-bottom:20px;
			}
			.visise-block-title{
				color:#fff;font-size:26px;font-weight:800;letter-spacing:-.01em;margin:0 0 10px;
			}
			.visise-block-lead{
				color:#a9b8ce;font-size:15px;line-height:1.65;margin:0 0 22px;
			}
			.visise-block-row{
				display:flex;gap:8px;font-size:14.5px;color:#c7d6e8;margin:0 0 10px;
			}
			.visise-block-row strong{color:#fff;font-weight:600;min-width:150px;flex-shrink:0;}
			.visise-block-divider{height:1px;background:#26364f;margin:22px 0;}
			.visise-block-code-label{
				color:#8b93a7;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin:0 0 10px;
			}
			.visise-block-code{
				background:#0d1526;border:1px solid #26364f;border-radius:10px;
				padding:16px 18px;font-family:Consolas,'SFMono-Regular',Menlo,monospace;
				font-size:12.5px;line-height:1.9;color:#c7d6e8;overflow-x:auto;
			}
			.visise-block-code .type{color:#7fc4ff;}
			.visise-block-code .time{color:#67799a;}
			.visise-block-footer{
				color:#67799a;font-size:12.5px;margin-top:22px;
			}
		</style>
		<div class="visise-block-overlay">
		<div class="visise-block-card">
			<div class="visise-block-icon">
				<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="<?php echo $is_permanent ? '#ff6b76' : '#f0a83c'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 3 4.5 6v6c0 5 3.3 8.4 7.5 9 4.2-.6 7.5-4 7.5-9V6Z"/>
					<path d="M9.5 12.2 11 13.7l3.5-4"/>
				</svg>
			</div>

			<h1 class="visise-block-title">
				<?php echo $is_permanent ? esc_html__( 'Access Permanently Banned', 'visitor-sentinel' ) : esc_html__( 'Access Temporarily Banned', 'visitor-sentinel' ); ?>
			</h1>
			<p class="visise-block-lead">
				<?php
				echo $is_permanent
					? esc_html__( 'This IP address has been permanently blocked. Our automated security system repeatedly detected activity consistent with hacking, scanning, or spam attempts from this address.', 'visitor-sentinel' )
					: esc_html__( 'This IP address has been temporarily blocked. Our automated security system detected activity consistent with hacking, scanning, or spam attempts from this address.', 'visitor-sentinel' );
				?>
			</p>

			<?php if ( ! $is_permanent && ! empty( $ban->expires_at ) ) : ?>
				<div class="visise-block-row"><strong><?php esc_html_e( 'Block lifts at', 'visitor-sentinel' ); ?></strong> <?php echo esc_html( mysql2date( 'd.m.Y H:i', $ban->expires_at ) ); ?></div>
			<?php endif; ?>
			<div class="visise-block-row"><strong><?php esc_html_e( 'Main reason', 'visitor-sentinel' ); ?></strong> <?php echo esc_html( $ban->reason ); ?></div>

			<?php if ( ! empty( $events ) ) : ?>
				<div class="visise-block-divider"></div>
				<p class="visise-block-code-label"><?php esc_html_e( 'Precise reason (technical log)', 'visitor-sentinel' ); ?></p>
				<div class="visise-block-code">
					<?php foreach ( $events as $event ) : ?>
						<div>[<span class="time"><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $event->created_at ) ); ?></span>] <span class="type"><?php echo esc_html( $event->event_type ); ?></span>: <?php echo esc_html( $event->description ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="visise-block-footer">
				<?php esc_html_e( 'If you believe this is a mistake, please contact the site owner.', 'visitor-sentinel' ); ?>
			</p>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
