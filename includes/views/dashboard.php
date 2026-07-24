<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pv-wrap">
	<div class="pv-brand-header">
		<svg class="pv-brand-header__logo" width="48" height="48" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M24 3 L43 10 V24 C43 35.6 35.2 43.4 24 46 C12.8 43.4 5 35.6 5 24 V10 Z" fill="#132a45" stroke="#5fb0ff" stroke-width="2"/>
			<path d="M15 24.5 L21 30.5 L33 17" fill="none" stroke="#5fb0ff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<div class="pv-brand-header__text">
			<h1 class="pv-title">
				<?php esc_html_e( 'Visitor Sentinel', 'visitor-sentinel' ); ?>
			</h1>
			<p class="pv-subtitle"><?php esc_html_e( 'Traffic monitoring and automatic protection against bots and attackers.', 'visitor-sentinel' ); ?></p>
		</div>
	</div>

	<div class="pv-cards">
		<div class="pv-card pv-card--live">
			<span class="pv-card__label">
				<span class="pv-live-dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Online right now', 'visitor-sentinel' ); ?>
			</span>
			<span class="pv-card__value" id="pv-online-now-value"><?php echo esc_html( number_format_i18n( $online ) ); ?></span>
		</div>
		<div class="pv-card">
			<span class="pv-card__label"><?php esc_html_e( 'Unique visitors today', 'visitor-sentinel' ); ?></span>
			<span class="pv-card__value"><?php echo esc_html( number_format_i18n( $today ) ); ?></span>
		</div>
		<div class="pv-card">
			<span class="pv-card__label"><?php esc_html_e( 'Visits (last 7 days)', 'visitor-sentinel' ); ?></span>
			<span class="pv-card__value"><?php echo esc_html( number_format_i18n( $week_visits ) ); ?></span>
		</div>
		<div class="pv-card pv-card--danger">
			<span class="pv-card__label"><?php esc_html_e( 'Currently blocked IPs', 'visitor-sentinel' ); ?></span>
			<span class="pv-card__value"><?php echo esc_html( number_format_i18n( $active_bans ) ); ?></span>
		</div>
		<div class="pv-card">
			<span class="pv-card__label"><?php esc_html_e( 'Total recorded blocks', 'visitor-sentinel' ); ?></span>
			<span class="pv-card__value"><?php echo esc_html( number_format_i18n( $total_bans ) ); ?></span>
		</div>
	</div>

	<div class="pv-panel">
		<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'gauge', 17 ); ?></span><?php esc_html_e( 'Visits trend (last 14 days)', 'visitor-sentinel' ); ?></h2>
		<?php
		$chart_max = ! empty( $daily_visits ) ? max( max( $daily_visits ), 1 ) : 1;
		?>
		<div class="pv-chart">
			<?php foreach ( $daily_visits as $day => $count ) : ?>
				<div class="pv-chart__bar-wrap">
					<div class="pv-chart__bar" style="height:<?php echo esc_attr( max( 2, round( ( $count / $chart_max ) * 100 ) ) ); ?>%;" title="<?php echo esc_attr( sprintf( '%1$s: %2$d', mysql2date( 'd.m', $day ), $count ) ); ?>"></div>
					<span class="pv-chart__value"><?php echo esc_html( $count ); ?></span>
					<span class="pv-chart__label"><?php echo esc_html( mysql2date( 'd.m', $day ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="pv-grid-2">
		<div class="pv-panel">
			<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'eye', 17 ); ?></span><?php esc_html_e( 'Most visited pages (30 days)', 'visitor-sentinel' ); ?></h2>
			<?php if ( empty( $top_pages ) ) : ?>
				<p class="pv-empty"><?php esc_html_e( 'Not enough data yet.', 'visitor-sentinel' ); ?></p>
			<?php else : ?>
				<ul class="pv-list">
					<?php foreach ( $top_pages as $page ) : ?>
						<li>
							<span class="pv-list__label">
								<?php if ( 0 === strpos( $page->request_uri, '/wp-admin' ) ) : ?>
									<span class="pv-badge pv-badge--admin"><?php esc_html_e( 'Admin', 'visitor-sentinel' ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( $page->request_uri ); ?>
							</span>
							<span class="pv-list__count"><?php echo esc_html( number_format_i18n( $page->total ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="pv-panel">
			<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'globe', 17 ); ?></span><?php esc_html_e( 'Top referrers (30 days)', 'visitor-sentinel' ); ?></h2>
			<?php if ( empty( $top_referrers ) ) : ?>
				<p class="pv-empty"><?php esc_html_e( 'Not enough data yet.', 'visitor-sentinel' ); ?></p>
			<?php else : ?>
				<ul class="pv-list">
					<?php foreach ( $top_referrers as $referrer ) : ?>
						<li>
							<span class="pv-list__label"><?php echo esc_html( $referrer->referer ); ?></span>
							<span class="pv-list__count"><?php echo esc_html( number_format_i18n( $referrer->total ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="pv-grid-2">
		<div class="pv-panel">
			<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'radar', 17 ); ?></span><?php esc_html_e( 'Threat types detected (30 days)', 'visitor-sentinel' ); ?></h2>
			<?php if ( empty( $event_breakdown ) ) : ?>
				<p class="pv-empty"><?php esc_html_e( 'No suspicious activity detected in this period.', 'visitor-sentinel' ); ?></p>
			<?php else : ?>
				<?php $event_max = max( wp_list_pluck( $event_breakdown, 'total' ) ); ?>
				<ul class="pv-bars">
					<?php foreach ( $event_breakdown as $type ) : ?>
						<li>
							<span class="pv-bars__label"><?php echo esc_html( $type->event_type ); ?></span>
							<span class="pv-bars__track"><span class="pv-bars__fill" style="width:<?php echo esc_attr( max( 4, round( ( $type->total / $event_max ) * 100 ) ) ); ?>%;"></span></span>
							<span class="pv-bars__value"><?php echo esc_html( number_format_i18n( $type->total ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="pv-panel">
			<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'sliders', 17 ); ?></span><?php esc_html_e( 'Device types (30 days)', 'visitor-sentinel' ); ?></h2>
			<?php
			$device_labels = array(
				'desktop' => __( 'Desktop', 'visitor-sentinel' ),
				'mobile'  => __( 'Mobile', 'visitor-sentinel' ),
				'tablet'  => __( 'Tablet', 'visitor-sentinel' ),
				'bot'     => __( 'Bots / automated', 'visitor-sentinel' ),
			);
			$device_total = array_sum( $device_stats );
			?>
			<?php if ( ! $device_total ) : ?>
				<p class="pv-empty"><?php esc_html_e( 'Not enough data yet.', 'visitor-sentinel' ); ?></p>
			<?php else : ?>
				<ul class="pv-bars">
					<?php foreach ( $device_labels as $key => $label ) : ?>
						<?php $value = isset( $device_stats[ $key ] ) ? $device_stats[ $key ] : 0; ?>
						<li>
							<span class="pv-bars__label"><?php echo esc_html( $label ); ?></span>
							<span class="pv-bars__track"><span class="pv-bars__fill pv-bars__fill--<?php echo esc_attr( $key ); ?>" style="width:<?php echo esc_attr( max( 4, round( ( $value / $device_total ) * 100 ) ) ); ?>%;"></span></span>
							<span class="pv-bars__value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="pv-panel">
		<h2 class="pv-panel-title"><span class="pv-panel-title__icon"><?php VISISE_Icons::render( 'shield', 17 ); ?></span><?php esc_html_e( 'Recent suspicious activity', 'visitor-sentinel' ); ?></h2>
		<?php if ( empty( $recent_events ) ) : ?>
			<p class="pv-empty"><?php esc_html_e( 'No recent suspicious activity has been detected.', 'visitor-sentinel' ); ?></p>
		<?php else : ?>
			<div class="pv-table-wrap">
				<table class="widefat pv-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'IP', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Event type', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Details', 'visitor-sentinel' ); ?></th>
							<th><?php esc_html_e( 'Score', 'visitor-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_events as $event ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $event->created_at ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'visise-bans', 'ip' => $event->ip ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $event->ip ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $event->event_type ); ?></td>
								<td><?php echo esc_html( $event->description ); ?></td>
								<td><?php echo esc_html( $event->score ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
