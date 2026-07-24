<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pv-wrap">
	<h1 class="pv-title"><?php esc_html_e( 'Settings', 'visitor-sentinel' ); ?></h1>
	<p class="pv-subtitle"><?php esc_html_e( 'Configure the detection thresholds and the visitor counter display.', 'visitor-sentinel' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="visise-settings-form">
		<?php wp_nonce_field( 'visise_settings_nonce' ); ?>
		<input type="hidden" name="action" value="visise_save_settings" />

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'sliders' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'Automatic detection', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'How aggressively suspicious traffic is scored and blocked.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_rate_limit_requests"><?php esc_html_e( 'Request threshold (rate limiting)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="number" min="1" id="pv_rate_limit_requests" name="visise_settings[rate_limit_requests]" value="<?php echo esc_attr( $settings['rate_limit_requests'] ); ?>" />
						<p class="description"><?php esc_html_e( 'The maximum number of requests allowed within a time window before this is logged as a soft signal. On its own, a high request count never blocks anyone (real visitors can browse many pages) — it only counts toward a block when combined with a genuine attack, bot, or spam signal.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_rate_limit_seconds"><?php esc_html_e( 'Interval (seconds)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="number" min="1" id="pv_rate_limit_seconds" name="visise_settings[rate_limit_seconds]" value="<?php echo esc_attr( $settings['rate_limit_seconds'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_score_threshold"><?php esc_html_e( 'Risk score threshold for blocking', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="number" min="1" id="pv_score_threshold" name="visise_settings[score_threshold]" value="<?php echo esc_attr( $settings['score_threshold'] ); ?>" />
						<p class="description"><?php esc_html_e( 'When the score accumulated by an IP within one hour exceeds this threshold, the IP is automatically blocked — but only if real evidence of hacking, bot, or spam activity was detected (browsing volume alone is never enough).', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_track_404"><?php esc_html_e( 'Track non-existent pages (404)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_track_404" name="visise_settings[track_404]" value="1" <?php checked( ! empty( $settings['track_404'] ) ); ?> />
							<?php esc_html_e( 'Enables content-scanning detection by monitoring 404 pages.', 'visitor-sentinel' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_trust_forwarded"><?php esc_html_e( 'Site behind a proxy/CDN', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_trust_forwarded" name="visise_settings[trust_forwarded_for]" value="1" <?php checked( ! empty( $settings['trust_forwarded_for'] ) ); ?> />
							<?php esc_html_e( 'Reads the real IP address from the X-Forwarded-For header (enable only if you use Cloudflare or another trusted proxy).', 'visitor-sentinel' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_whitelist"><?php esc_html_e( 'IP whitelist', 'visitor-sentinel' ); ?></label></th>
					<td>
						<textarea id="pv_whitelist" name="visise_settings[whitelist_ips]" rows="5" cols="40" class="large-text code" placeholder="203.0.113.5"><?php echo esc_textarea( $settings['whitelist_ips'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP per line. These addresses will never be monitored or blocked.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_retention"><?php esc_html_e( 'Keep data for (days)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="number" min="1" id="pv_retention" name="visise_settings[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Older visits and events are automatically deleted every day.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'globe' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'Country flags', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'Show where blocked traffic is coming from.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_geo_lookup"><?php esc_html_e( 'Show country flags next to IPs', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_geo_lookup" name="visise_settings[enable_geo_lookup]" value="1" <?php checked( ! empty( $settings['enable_geo_lookup'] ) ); ?> />
							<?php esc_html_e( 'Enabled', 'visitor-sentinel' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disabled by default. This plugin does not ship a GeoIP database, so enabling this sends each new IP address to the free, third-party service ip-api.com to determine its country. Results are cached locally for 30 days, so the same IP is never looked up twice. Leave this off if you want zero external requests.', 'visitor-sentinel' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</section>

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'bell' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'Email alerts', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'Get notified the moment something is blocked.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_email_alerts"><?php esc_html_e( 'Notify me when an IP is blocked', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_email_alerts" name="visise_settings[email_notifications_enabled]" value="1" <?php checked( ! empty( $settings['email_notifications_enabled'] ) ); ?> />
							<?php esc_html_e( 'Enabled', 'visitor-sentinel' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Sends you an email every time an IP is automatically blocked or escalated to a permanent block, so you know about attacks as they happen.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_notification_email"><?php esc_html_e( 'Send alerts to', 'visitor-sentinel' ); ?></label></th>
					<td>
						<input type="email" id="pv_notification_email" name="visise_settings[notification_email]" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Leave empty to use the site\'s admin email.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'eye' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'On-site visitor counter', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'The elegant live-visitor badge shown on the front-end.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_counter_enabled"><?php esc_html_e( 'Show the counter', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_counter_enabled" name="visise_settings[frontend_counter_enabled]" value="1" <?php checked( ! empty( $settings['frontend_counter_enabled'] ) ); ?> />
							<?php esc_html_e( 'Displays an elegant badge with the live visitor count.', 'visitor-sentinel' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_counter_show_guests"><?php esc_html_e( 'Show to guests too', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_counter_show_guests" name="visise_settings[frontend_counter_show_guests]" value="1" <?php checked( ! empty( $settings['frontend_counter_show_guests'] ) ); ?> />
							<?php esc_html_e( 'Also displays the badge to visitors who are not logged in, not only to members.', 'visitor-sentinel' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_counter_role"><?php esc_html_e( 'Visible to (logged-in users)', 'visitor-sentinel' ); ?></label></th>
					<td>
						<select id="pv_counter_role" name="visise_settings[frontend_counter_role]">
							<option value="read" <?php selected( $settings['frontend_counter_role'], 'read' ); ?>><?php esc_html_e( 'Any logged-in member', 'visitor-sentinel' ); ?></option>
							<option value="edit_posts" <?php selected( $settings['frontend_counter_role'], 'edit_posts' ); ?>><?php esc_html_e( 'Editors and authors', 'visitor-sentinel' ); ?></option>
							<option value="manage_options" <?php selected( $settings['frontend_counter_role'], 'manage_options' ); ?>><?php esc_html_e( 'Administrators only', 'visitor-sentinel' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'This role requirement only applies to logged-in visitors. Guests are controlled by the option above.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pv_counter_position"><?php esc_html_e( 'Badge position', 'visitor-sentinel' ); ?></label></th>
					<td>
						<select id="pv_counter_position" name="visise_settings[frontend_counter_position]">
							<option value="left" <?php selected( $settings['frontend_counter_position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'visitor-sentinel' ); ?></option>
							<option value="right" <?php selected( $settings['frontend_counter_position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'visitor-sentinel' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Choose bottom left if your site already has a chat/WhatsApp button in the bottom-right corner, so the two never overlap on phones.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'mask' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'Deception layer (honeypots & honeytokens)', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'Fake bait that only an attacker would ever touch.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_honeypot_suite"><?php esc_html_e( 'Enable the deception layer', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_honeypot_suite" name="visise_settings[enable_honeypot_suite]" value="1" <?php checked( ! empty( $settings['enable_honeypot_suite'] ) ); ?> />
							<?php esc_html_e( 'Enabled', 'visitor-sentinel' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Plants a set of fake bait around the site — a decoy backup file, a decoy API key, a decoy admin username, and a hidden spam-trap email address. None of these are ever linked or shown to real visitors. Any interaction with any of them is treated as conclusive proof of malicious intent and results in an immediate permanent block, bypassing the normal risk-score threshold.', 'visitor-sentinel' ); ?></p>
					</td>
				</tr>
			</table>

			<?php if ( ! empty( $settings['enable_honeypot_suite'] ) && class_exists( 'VISISE_Honeypot' ) ) : ?>
				<table class="form-table pv-honeypot-tokens" role="presentation">
					<tr>
						<th scope="row"><span class="pv-th-icon"><?php VISISE_Icons::render( 'file-warning', 15 ); ?></span> <?php esc_html_e( 'Honeyfile (decoy backup file)', 'visitor-sentinel' ); ?></th>
						<td>
							<code><?php echo esc_html( home_url( '/' . VISISE_Honeypot::get_honeyfile_slug() . '.txt' ) ); ?></code>
							<p class="description"><?php esc_html_e( 'Never link to this URL anywhere. It only exists so an attacker who finds it by directory scanning or a leaked reference gives themselves away.', 'visitor-sentinel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><span class="pv-th-icon"><?php VISISE_Icons::render( 'lock', 15 ); ?></span> <?php esc_html_e( 'Honeytoken username (decoy admin account)', 'visitor-sentinel' ); ?></th>
						<td><code><?php echo esc_html( VISISE_Honeypot::get_decoy_username() ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><span class="pv-th-icon"><?php VISISE_Icons::render( 'mail', 15 ); ?></span> <?php esc_html_e( 'Honeytoken email (spam trap)', 'visitor-sentinel' ); ?></th>
						<td><code><?php echo esc_html( VISISE_Honeypot::get_trap_email() ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><span class="pv-th-icon"><?php VISISE_Icons::render( 'key', 15 ); ?></span> <?php esc_html_e( 'Honeytoken API key', 'visitor-sentinel' ); ?></th>
						<td>
							<code><?php echo esc_html( VISISE_Honeypot::get_decoy_api_key() ); ?></code>
							<p class="description">
								<?php
								printf(
									/* translators: %s: the decoy REST API endpoint URL. */
									esc_html__( 'Served publicly at %s so a scanner enumerating REST routes can find it — using this key anywhere is what triggers the ban, not merely reading it.', 'visitor-sentinel' ),
									'<code>' . esc_html( rest_url( 'visise-internal/v1/config' ) ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</section>

		<section class="visise-settings-card">
			<header class="visise-settings-card__header">
				<span class="visise-settings-card__icon"><?php VISISE_Icons::render( 'lock' ); ?></span>
				<div>
					<h2><?php esc_html_e( 'Device recognition (browser fingerprint)', 'visitor-sentinel' ); ?></h2>
					<p><?php esc_html_e( 'Recognizes a previously-blocked browser again after it switches IP address.', 'visitor-sentinel' ); ?></p>
				</div>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pv_fingerprinting"><?php esc_html_e( 'Enable device fingerprinting', 'visitor-sentinel' ); ?></label></th>
					<td>
						<label class="pv-toggle-row">
							<input type="checkbox" id="pv_fingerprinting" name="visise_settings[enable_fingerprinting]" value="1" <?php checked( ! empty( $settings['enable_fingerprinting'] ) ); ?> />
							<?php esc_html_e( 'Enabled', 'visitor-sentinel' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disabled by default. When enabled, the browser computes a lightweight fingerprint (screen, timezone, language, canvas rendering) and keeps it in a cookie, purely to recognize a browser that was already permanently blocked if it later returns from a different IP address (e.g. a new VPN exit or a mobile carrier reassigning the connection) -- exactly like the existing device cookie, but resilient to an IP change.', 'visitor-sentinel' ); ?>
						</p>
						<p class="description">
							<strong><?php esc_html_e( 'It never decides a ban by itself.', 'visitor-sentinel' ); ?></strong>
							<?php esc_html_e( 'The fingerprint is only ever recorded on an IP that a ban was already applied to, using the same evidence-based rules as every other block on this site (a real attack/bot/spam signal, never sheer browsing volume). It is only ever used afterwards to re-match that same already-banned browser; it can never cause a new visitor to be blocked on its own.', 'visitor-sentinel' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Limits to be aware of: it can be cleared by clearing cookies or using a different browser, and in rare cases two different devices with an identical, unmodified browser/OS setup could share the same fingerprint. Because it can identify a specific browser, treat it as personal data in your privacy policy, same as an IP address.', 'visitor-sentinel' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</section>

		<?php submit_button( __( 'Save settings', 'visitor-sentinel' ) ); ?>
	</form>
</div>
