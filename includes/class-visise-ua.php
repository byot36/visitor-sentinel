<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a raw User-Agent string into a short, human-readable platform label
 * (operating system + browser, or the bot/tool name), entirely from local
 * pattern matching — no external service involved.
 */
class VISISE_UA {

	public static function describe( $user_agent ) {
		$ua = (string) $user_agent;

		if ( '' === trim( $ua ) ) {
			return __( 'Unknown', 'visitor-sentinel' );
		}

		$ua_lower = strtolower( $ua );

		$bots = array(
			'googlebot'           => 'Googlebot',
			'bingbot'             => 'Bingbot',
			'slurp'               => 'Yahoo Slurp',
			'duckduckbot'         => 'DuckDuckBot',
			'baiduspider'         => 'Baidu Spider',
			'yandexbot'           => 'YandexBot',
			'facebookexternalhit' => 'Facebook Bot',
			'applebot'            => 'Applebot',
			'sqlmap'              => 'sqlmap',
			'nikto'               => 'Nikto',
			'nmap'                => 'Nmap',
			'masscan'             => 'Masscan',
			'wpscan'              => 'WPScan',
			'acunetix'            => 'Acunetix',
			'nessus'              => 'Nessus',
			'dirbuster'           => 'DirBuster',
			'gobuster'            => 'Gobuster',
			'zgrab'               => 'Zgrab',
			'headlesschrome'      => 'Headless Chrome',
			'phantomjs'           => 'PhantomJS',
			'selenium'            => 'Selenium',
			'puppeteer'           => 'Puppeteer',
			'scrapy'              => 'Scrapy',
			'curl/'               => 'curl',
			'wget'                => 'Wget',
			'python-requests'     => 'Python script',
			'python-urllib'       => 'Python script',
			'go-http-client'      => 'Go script',
			'okhttp'              => 'okhttp client',
		);

		foreach ( $bots as $needle => $label ) {
			if ( false !== strpos( $ua_lower, $needle ) ) {
				return $label;
			}
		}

		$os = self::detect_os( $ua_lower );
		$browser = self::detect_browser( $ua_lower );

		if ( $os && $browser ) {
			return $os . ' · ' . $browser;
		}

		return $os ? $os : ( $browser ? $browser : __( 'Unknown', 'visitor-sentinel' ) );
	}

	private static function detect_os( $ua_lower ) {
		if ( false !== strpos( $ua_lower, 'iphone' ) ) {
			return 'iPhone';
		}
		if ( false !== strpos( $ua_lower, 'ipad' ) ) {
			return 'iPad';
		}
		if ( false !== strpos( $ua_lower, 'android' ) ) {
			return false !== strpos( $ua_lower, 'mobile' ) ? 'Android phone' : 'Android tablet';
		}
		if ( false !== strpos( $ua_lower, 'mac os x' ) ) {
			return 'Mac';
		}
		if ( false !== strpos( $ua_lower, 'windows' ) ) {
			return 'Windows';
		}
		if ( false !== strpos( $ua_lower, 'linux' ) ) {
			return 'Linux';
		}

		return '';
	}

	private static function detect_browser( $ua_lower ) {
		if ( false !== strpos( $ua_lower, 'edg/' ) || false !== strpos( $ua_lower, 'edga/' ) || false !== strpos( $ua_lower, 'edgios/' ) ) {
			return 'Edge';
		}
		if ( false !== strpos( $ua_lower, 'opr/' ) || false !== strpos( $ua_lower, 'opera' ) ) {
			return 'Opera';
		}
		if ( false !== strpos( $ua_lower, 'firefox' ) ) {
			return 'Firefox';
		}
		if ( false !== strpos( $ua_lower, 'crios' ) || false !== strpos( $ua_lower, 'chrome' ) ) {
			return 'Chrome';
		}
		if ( false !== strpos( $ua_lower, 'safari' ) ) {
			return 'Safari';
		}

		return '';
	}
}
