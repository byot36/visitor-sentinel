<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small, self-contained set of Lucide-style line icons (no external font
 * or icon library dependency — everything ships inline as SVG), used to give
 * each admin section a consistent, recognizable glyph instead of plain text.
 */
class VISISE_Icons {

	private static $paths = array(
		'gauge'    => '<path d="M12 12 15.5 8.5"/><path d="M20 12a8 8 0 1 0-16 0"/><path d="M4 12h1"/><path d="M19 12h1"/><path d="M12 4v1"/>',
		'shield'   => '<path d="M12 3 4.5 6v6c0 5 3.3 8.4 7.5 9 4.2-.6 7.5-4 7.5-9V6Z"/>',
		'radar'    => '<path d="M12 3v9l6 3"/><circle cx="12" cy="12" r="9"/>',
		'file-warning' => '<path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M6 2h8l6 6v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z"/><path d="M12 11v3"/><path d="M12 17h.01"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
		'mail'     => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 6.5L21 6"/>',
		'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6Z"/><path d="M9.5 18.5a2.5 2.5 0 0 0 5 0"/>',
		'eye'      => '<path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
		'lock'     => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
		'key'      => '<circle cx="8" cy="15" r="4.5"/><path d="M11.3 11.7 20 3M16.5 6.5l3 3M13.7 9.3l2 2"/>',
		'mask'     => '<path d="M3 8s2-2 4-2 3 2 5 2 3-2 5-2 4 2 4 2v3c0 6-4.5 9.5-9 9.5S3 17 3 11Z"/><circle cx="8.5" cy="12" r="1"/><circle cx="15.5" cy="12" r="1"/>',
		'sliders'  => '<path d="M4 6h9M17 6h3M4 12h3M9 12h11M4 18h13M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="7" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
	);

	/**
	 * Returns an inline <svg> for the given icon name, or an empty string if
	 * unknown. Uses currentColor so it inherits whatever text color it sits
	 * next to, and stroke-based line art to match a Feather/Lucide look.
	 */
	public static function get( $name, $size = 18 ) {
		if ( ! isset( self::$paths[ $name ] ) ) {
			return '';
		}

		$size = absint( $size );

		return sprintf(
			'<svg class="pv-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			$size,
			self::$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed, hardcoded SVG path data, no user input.
		);
	}

	/**
	 * Echoes the icon directly, for convenient inline use in views.
	 */
	public static function render( $name, $size = 18 ) {
		echo self::get( $name, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see get().
	}
}
