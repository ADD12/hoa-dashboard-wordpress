<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Admin_Menu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		add_menu_page(
			'HOA Dashboard', 'HOA Dashboard', 'manage_options', 'hoa-dash-settings',
			array( $this, 'render_settings_page' ), 'dashicons-analytics', 58
		);
		add_submenu_page( 'hoa-dash-settings', 'Settings', 'Settings', 'manage_options', 'hoa-dash-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'hoa-dash-settings', 'Shortcodes & Setup', 'Shortcodes & Setup', 'manage_options', 'hoa-dash-setup', array( $this, 'render_setup_page' ) );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap">
			<h1>HOA Dashboard Settings (v<?php echo esc_html( HOA_DASH_VERSION ); ?>)</h1>
			<p class="description">These settings also configure the companion iOS app automatically (Payment Gateway, Public Key, HOA Name, and fees are fetched by the app at runtime via <code>/wp-json/hoa/v1/config</code> — nothing needs to be hardcoded or rebuilt in the app when you change them here). The Secret Key is never exposed to the app or any public endpoint.</p>
			<form method="post" action="options.php">
				<?php settings_fields( HOA_Dash_Settings::OPT_GROUP ); ?>
				<h2>General</h2>
				<table class="form-table">
					<tr><th>HOA Name</th><td><input type="text" name="hoa_dash_hoa_name" value="<?php echo esc_attr( get_option( 'hoa_dash_hoa_name' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Property Management Firm Name</th><td><input type="text" name="hoa_dash_pm_firm_name" value="<?php echo esc_attr( get_option( 'hoa_dash_pm_firm_name' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Newsletter "From" Email</th><td><input type="email" name="hoa_dash_newsletter_from_email" value="<?php echo esc_attr( get_option( 'hoa_dash_newsletter_from_email' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>CA Disclosure Note</th><td><textarea name="hoa_dash_state_disclosure_note" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'hoa_dash_state_disclosure_note' ) ); ?></textarea>
					<p class="description">e.g. "Reserve figures reflect the association's most recent audited financial statement and reserve study per Civil Code §5300 / §5570." Consult your association's counsel/CPA for exact statutory language.</p></td></tr>
				</table>

				<h2>Twilio 2FA</h2>
				<table class="form-table">
					<tr><th>Account SID</th><td><input type="text" name="hoa_dash_twilio_sid" value="<?php echo esc_attr( get_option( 'hoa_dash_twilio_sid' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Auth Token</th><td><input type="password" name="hoa_dash_twilio_auth_token" value="<?php echo esc_attr( get_option( 'hoa_dash_twilio_auth_token' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Verify Service SID</th><td><input type="text" name="hoa_dash_twilio_verify_service_sid" value="<?php echo esc_attr( get_option( 'hoa_dash_twilio_verify_service_sid' ) ); ?>" class="regular-text"></td></tr>
				</table>

				<h2>Payments</h2>
				<table class="form-table">
					<tr><th>Gateway</th><td>
						<select name="hoa_dash_payment_gateway">
							<?php $gw = get_option( 'hoa_dash_payment_gateway', 'stripe' ); ?>
							<option value="stripe" <?php selected( $gw, 'stripe' ); ?>>Stripe</option>
							<option value="authorizenet" <?php selected( $gw, 'authorizenet' ); ?>>Authorize.Net</option>
							<option value="other" <?php selected( $gw, 'other' ); ?>>Other</option>
						</select>
					</td></tr>
					<tr><th>Public/Publishable Key</th><td><input type="text" name="hoa_dash_payment_public_key" value="<?php echo esc_attr( get_option( 'hoa_dash_payment_public_key' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Secret Key</th><td><input type="password" name="hoa_dash_payment_secret_key" value="<?php echo esc_attr( get_option( 'hoa_dash_payment_secret_key' ) ); ?>" class="regular-text"></td></tr>
					<tr><th>Card Fee %</th><td><input type="number" step="0.01" name="hoa_dash_card_fee_pct" value="<?php echo esc_attr( get_option( 'hoa_dash_card_fee_pct', '2.9' ) ); ?>"></td></tr>
					<tr><th>Card Fee Flat ($)</th><td><input type="number" step="0.01" name="hoa_dash_card_fee_flat" value="<?php echo esc_attr( get_option( 'hoa_dash_card_fee_flat', '0.30' ) ); ?>"></td></tr>
					<tr><th>ACH/Bank Fee Flat ($)</th><td><input type="number" step="0.01" name="hoa_dash_ach_fee_flat" value="<?php echo esc_attr( get_option( 'hoa_dash_ach_fee_flat', '1.00' ) ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$pages = HOA_Dash_Page_Installer::page_definitions();
		?>
		<div class="wrap">
			<h1>HOA Dashboard — Setup</h1>
			<p>The following pages were created automatically when the plugin was activated. If any is missing (e.g. you deleted it), it will be recreated the next time the plugin updates, or you can deactivate/reactivate the plugin.</p>
			<table class="widefat" style="max-width:700px;">
				<thead><tr><th>Page</th><th>Shortcode</th><th>Link</th></tr></thead>
				<tbody>
				<?php foreach ( $pages as $key => $def ) :
					$id = HOA_Dash_Page_Installer::get_page_id( $key );
					$url = $id ? get_permalink( $id ) : '';
					$edit = $id ? get_edit_post_link( $id ) : '';
				?>
					<tr>
						<td><?php echo esc_html( $def['title'] ); ?></td>
						<td><code><?php echo esc_html( $def['shortcode'] ); ?></code></td>
						<td>
							<?php if ( $id ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank">View</a> |
								<a href="<?php echo esc_url( $edit ); ?>">Edit</a>
							<?php else : ?>
								<em>Not created yet</em>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Roles created</h2>
			<ul>
				<li><strong>HOA Member</strong> — standard homeowner access. Documentation: <a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_member' ) ); ?>">HOA Member Guide</a></li>
				<li><strong>HOA Board Member</strong> — full oversight: thresholds, audit log, escalation, PM reviews, newsletter approval. Documentation: <a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_board' ) ); ?>">Board Member Guide</a></li>
				<li><strong>Property Manager</strong> — narrower edit rights, all writes logged to the board-auditable PM Audit Log. Documentation: <a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_pm' ) ); ?>">Property Manager Guide</a></li>
			</ul>
			<h2>Cron</h2>
			<p>Autopay charges run daily via WP-Cron (<code>hoa_dash_daily_cron</code>). For reliability on low-traffic sites, set up a real server cron hitting <code>wp-cron.php</code>.</p>
			<h2>Integration points requiring your credentials</h2>
			<ul>
				<li>Twilio Verify (SMS 2FA) — Settings tab</li>
				<li>Payment gateway (Stripe/Authorize.Net) — Settings tab, plus client-side tokenization JS in <code>assets/js/dashboard.js</code></li>
			</ul>
		</div>
		<?php
	}
}
