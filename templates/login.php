<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div id="hoa-login-wrap" class="hoa-dash">
	<form id="hoa-login-form-step1">
		<h2><?php esc_html_e( 'Member Login', 'hoa-dashboard' ); ?></h2>
		<p><label><?php esc_html_e( 'Username or Email', 'hoa-dashboard' ); ?><br>
			<input type="text" name="username" required></label></p>
		<p><label><?php esc_html_e( 'Password', 'hoa-dashboard' ); ?><br>
			<input type="password" name="password" required></label></p>
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'hoa_dash_login_nonce' ) ); ?>">
		<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Log In', 'hoa-dashboard' ); ?></button>
		<div class="hoa-login-error" style="display:none;color:#b00;margin-top:8px;"></div>
	</form>

	<form id="hoa-login-form-step2" style="display:none;">
		<h2><?php esc_html_e( 'Enter Verification Code', 'hoa-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'We texted a 6-digit code to your phone on file.', 'hoa-dashboard' ); ?></p>
		<input type="hidden" name="pending_uid" value="">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'hoa_dash_login_nonce' ) ); ?>">
		<p><label><?php esc_html_e( 'Code', 'hoa-dashboard' ); ?><br>
			<input type="text" name="code" inputmode="numeric" maxlength="6" required></label></p>
		<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Verify', 'hoa-dashboard' ); ?></button>
		<div class="hoa-login-error" style="display:none;color:#b00;margin-top:8px;"></div>
	</form>
</div>
