<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pv-wrap">
	<div class="pv-actions" style="margin-bottom:20px;">
		<button type="button" class="button button-primary" onclick="window.print();">
			<?php esc_html_e( 'Print / Save as PDF', 'visitor-sentinel' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=visise-history' ) ); ?>"><?php esc_html_e( 'Back to History', 'visitor-sentinel' ); ?></a>
	</div>

	<style>
		.visise-record-doc {
			max-width: 760px;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			padding: 48px 56px;
			font-family: Georgia, 'Times New Roman', serif;
			color: #1c2333;
			line-height: 1.7;
		}
		.visise-record-doc h1 {
			font-size: 22px;
			text-align: center;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			margin-bottom: 6px;
		}
		.visise-record-doc .visise-record-subtitle {
			text-align: center;
			color: #646970;
			font-size: 13px;
			margin-bottom: 32px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.visise-record-doc dl {
			display: grid;
			grid-template-columns: 220px 1fr;
			gap: 10px 16px;
			margin: 0 0 28px;
		}
		.visise-record-doc dt {
			font-weight: 700;
		}
		.visise-record-doc dd {
			margin: 0;
		}
		.visise-record-doc .visise-record-declaration {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			padding: 18px 22px;
			margin-bottom: 32px;
			white-space: pre-wrap;
		}
		.visise-record-doc .visise-record-signature {
			border-top: 1px solid #1c2333;
			padding-top: 10px;
			margin-top: 48px;
			display: inline-block;
			min-width: 320px;
			font-style: italic;
		}
		.visise-record-doc .visise-record-hash {
			margin-top: 40px;
			font-family: Consolas, 'SFMono-Regular', Menlo, monospace;
			font-size: 11px;
			color: #787c82;
			word-break: break-all;
			font-style: normal;
		}
		@media print {
			#adminmenumain, #wpadminbar, #wpfooter, .pv-actions, .pv-title, .pv-subtitle, .notice {
				display: none !important;
			}
			#wpcontent, #wpbody-content {
				margin: 0 !important;
				padding: 0 !important;
			}
			.visise-record-doc {
				border: none;
				box-shadow: none;
				max-width: none;
			}
		}
	</style>

	<div class="visise-record-doc">
		<h1><?php esc_html_e( 'IP Block Release — Declaration', 'visitor-sentinel' ); ?></h1>
		<p class="visise-record-subtitle"><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . home_url() ); ?></p>

		<dl>
			<dt><?php esc_html_e( 'Record ID', 'visitor-sentinel' ); ?></dt>
			<dd>#<?php echo esc_html( $record->id ); ?></dd>

			<dt><?php esc_html_e( 'Date', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( mysql2date( 'd F Y, H:i:s', $record->created_at ) ); ?></dd>

			<dt><?php esc_html_e( 'IP address released', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( $record->ip ); ?></dd>

			<dt><?php esc_html_e( 'Original block type', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( ucfirst( $record->ban_type ) ); ?></dd>

			<dt><?php esc_html_e( 'Original reason', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( $record->original_reason ); ?></dd>

			<dt><?php esc_html_e( 'Risk score at time of block', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( $record->score ); ?></dd>

			<dt><?php esc_html_e( 'Released by', 'visitor-sentinel' ); ?></dt>
			<dd><?php echo esc_html( $record->admin_display_name ); ?> (<?php echo esc_html( $record->admin_login ); ?>)</dd>
		</dl>

		<p><strong><?php esc_html_e( 'Declaration:', 'visitor-sentinel' ); ?></strong></p>
		<div class="visise-record-declaration"><?php echo esc_html( $record->declaration ); ?></div>

		<p><?php esc_html_e( 'By signing below, the administrator named confirms this decision and takes responsibility for releasing the above IP address, understanding that its prior activity history has been permanently deleted as a result.', 'visitor-sentinel' ); ?></p>

		<div class="visise-record-signature">
			<?php echo esc_html( $record->signature_name ); ?>
		</div>

		<div class="visise-record-hash">
			<?php esc_html_e( 'Verification checksum (SHA-256):', 'visitor-sentinel' ); ?><br />
			<?php echo esc_html( $record->signature_hash ); ?>
		</div>
	</div>
</div>
