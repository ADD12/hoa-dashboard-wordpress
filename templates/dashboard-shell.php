<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$current_user = wp_get_current_user();
$is_board = current_user_can( 'hoa_view_board_tools' );
$is_pm    = current_user_can( 'hoa_property_manager' );
?>
<div class="hoa-dash" id="hoa-dashboard-app">
	<p class="hoa-help hoa-doc-links">
		<?php esc_html_e( 'New here?', 'hoa-dashboard' ); ?>
		<a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_member' ) ); ?>"><?php esc_html_e( 'Member Guide', 'hoa-dashboard' ); ?></a>
		<?php if ( $is_board ) : ?> | <a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_board' ) ); ?>"><?php esc_html_e( 'Board Guide', 'hoa-dashboard' ); ?></a><?php endif; ?>
		<?php if ( $is_pm ) : ?> | <a href="<?php echo esc_url( HOA_Dash_Page_Installer::get_page_url( 'doc_pm' ) ); ?>"><?php esc_html_e( 'Property Manager Guide', 'hoa-dashboard' ); ?></a><?php endif; ?>
	</p>
	<nav class="hoa-tabs">
		<button class="hoa-tab-btn active" data-tab="overview"><?php esc_html_e( 'Overview', 'hoa-dashboard' ); ?></button>
		<button class="hoa-tab-btn" data-tab="reserves"><?php esc_html_e( 'Reserve Accounts', 'hoa-dashboard' ); ?></button>
		<button class="hoa-tab-btn" data-tab="dues"><?php esc_html_e( 'Dues & Payments', 'hoa-dashboard' ); ?></button>
		<button class="hoa-tab-btn" data-tab="tickets"><?php esc_html_e( 'Trouble Tickets', 'hoa-dashboard' ); ?></button>
		<button class="hoa-tab-btn" data-tab="calendar"><?php esc_html_e( 'Calendar', 'hoa-dashboard' ); ?></button>
		<button class="hoa-tab-btn" data-tab="newsletter"><?php esc_html_e( 'Newsletter', 'hoa-dashboard' ); ?></button>
		<?php if ( $is_board ) : ?>
		<button class="hoa-tab-btn" data-tab="board"><?php esc_html_e( 'Board Tools', 'hoa-dashboard' ); ?></button>
		<?php endif; ?>
		<button class="hoa-tab-btn" data-tab="security"><?php esc_html_e( 'Login & Security', 'hoa-dashboard' ); ?></button>
	</nav>

	<!-- OVERVIEW -->
	<section class="hoa-tab-panel" id="tab-overview">
		<h2><?php esc_html_e( 'Reserve Fund Health', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-health-badge" class="hoa-health-badge">…</div>
		<div class="hoa-chart-wrap"><canvas id="hoa-health-chart" width="320" height="220"></canvas></div>
		<p class="hoa-disclosure"><?php echo esc_html( get_option( 'hoa_dash_state_disclosure_note' ) ); ?></p>

		<h2><?php esc_html_e( 'Dues Owed', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-dues-summary">…</div>

		<h2><?php esc_html_e( 'Next Board Meeting', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-next-event">…</div>
	</section>

	<!-- RESERVE ACCOUNTS DETAIL -->
	<section class="hoa-tab-panel" id="tab-reserves" style="display:none;">
		<h2><?php esc_html_e( 'Reserve Account Balances', 'hoa-dashboard' ); ?></h2>
		<p class="hoa-help"><?php esc_html_e( 'Figures are drawn from the association\'s audited financial statements / reserve study.', 'hoa-dashboard' ); ?></p>
		<table class="hoa-table" id="hoa-reserve-table">
			<thead><tr>
				<th><?php esc_html_e( 'Account', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Category', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Balance', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Fully Funded Target', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'CA Min. Required', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( '% Funded', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Statement Date', 'hoa-dashboard' ); ?></th>
				<?php if ( $is_board || $is_pm ) : ?><th><?php esc_html_e( 'Actions', 'hoa-dashboard' ); ?></th><?php endif; ?>
			</tr></thead>
			<tbody></tbody>
		</table>

		<?php if ( $is_board || $is_pm ) : ?>
		<h3><?php esc_html_e( 'Add / Edit Reserve Account', 'hoa-dashboard' ); ?></h3>
		<form id="hoa-reserve-form" class="hoa-form">
			<input type="hidden" name="id" value="">
			<input type="text" name="account_name" placeholder="<?php esc_attr_e( 'Account name', 'hoa-dashboard' ); ?>" required>
			<input type="text" name="account_category" placeholder="<?php esc_attr_e( 'Category (Roofing, Paving, Plumbing…)', 'hoa-dashboard' ); ?>" required>
			<input type="number" step="0.01" name="current_balance" placeholder="<?php esc_attr_e( 'Current balance', 'hoa-dashboard' ); ?>" required>
			<input type="number" step="0.01" name="fully_funded_target" placeholder="<?php esc_attr_e( 'Fully funded target', 'hoa-dashboard' ); ?>" required>
			<input type="number" step="0.01" name="ca_min_required" placeholder="<?php esc_attr_e( 'CA minimum required', 'hoa-dashboard' ); ?>">
			<input type="date" name="statement_date">
			<input type="text" name="source_document" placeholder="<?php esc_attr_e( 'Source document (e.g. FY2025 Audit)', 'hoa-dashboard' ); ?>">
			<textarea name="notes" placeholder="<?php esc_attr_e( 'Notes', 'hoa-dashboard' ); ?>"></textarea>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Save Account', 'hoa-dashboard' ); ?></button>
		</form>
		<?php if ( current_user_can( 'hoa_set_thresholds' ) ) : ?>
		<h3><?php esc_html_e( 'Health Thresholds', 'hoa-dashboard' ); ?></h3>
		<form id="hoa-threshold-form" class="hoa-form">
			<label><?php esc_html_e( 'Green at ≥', 'hoa-dashboard' ); ?> <input type="number" step="0.1" name="green_min_pct"> %</label>
			<label><?php esc_html_e( 'Yellow at ≥', 'hoa-dashboard' ); ?> <input type="number" step="0.1" name="yellow_min_pct"> %</label>
			<p class="hoa-help"><?php esc_html_e( 'Below "Yellow" threshold is Red (below state/reserve-study required funding).', 'hoa-dashboard' ); ?></p>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Save Thresholds', 'hoa-dashboard' ); ?></button>
		</form>
		<?php endif; ?>
		<?php endif; ?>
	</section>

	<!-- DUES & PAYMENTS -->
	<section class="hoa-tab-panel" id="tab-dues" style="display:none;">
		<h2><?php esc_html_e( 'Your Dues', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-dues-balance" class="hoa-balance-box">…</div>
		<button id="hoa-pay-now-btn" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Pay Now', 'hoa-dashboard' ); ?></button>

		<div id="hoa-pay-modal" class="hoa-modal" style="display:none;">
			<div class="hoa-modal-inner">
				<h3><?php esc_html_e( 'Make a Payment', 'hoa-dashboard' ); ?></h3>
				<form id="hoa-pay-form">
					<label><?php esc_html_e( 'Amount', 'hoa-dashboard' ); ?> <input type="number" step="0.01" name="amount" required></label>
					<label><input type="radio" name="method" value="bank" checked> <?php esc_html_e( 'Bank transfer (ACH) — lowest fee', 'hoa-dashboard' ); ?></label>
					<label><input type="radio" name="method" value="card"> <?php esc_html_e( 'Credit/Debit Card — processing fee applies', 'hoa-dashboard' ); ?></label>
					<div id="hoa-fee-preview" class="hoa-help"></div>
					<div id="hoa-card-element" class="hoa-payment-el">
						<!-- Client-side gateway tokenization mounts here (e.g. Stripe Elements). -->
						<input type="text" name="mock_token_input" placeholder="<?php esc_attr_e( 'Payment token (test mode placeholder)', 'hoa-dashboard' ); ?>">
					</div>
					<label><input type="checkbox" id="hoa-autopay-checkbox"> <?php esc_html_e( 'Set up automatic monthly payment with this method', 'hoa-dashboard' ); ?></label>
					<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Submit Payment', 'hoa-dashboard' ); ?></button>
					<button type="button" class="hoa-btn hoa-modal-close"><?php esc_html_e( 'Cancel', 'hoa-dashboard' ); ?></button>
				</form>
			</div>
		</div>

		<h3><?php esc_html_e( 'Payment History', 'hoa-dashboard' ); ?></h3>
		<table class="hoa-table" id="hoa-dues-ledger-table">
			<thead><tr><th><?php esc_html_e( 'Due Date', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Amount Due', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Amount Paid', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Status', 'hoa-dashboard' ); ?></th></tr></thead>
			<tbody></tbody>
		</table>
	</section>

	<!-- TICKETS -->
	<section class="hoa-tab-panel" id="tab-tickets" style="display:none;">
		<h2><?php esc_html_e( 'Trouble Tickets', 'hoa-dashboard' ); ?></h2>
		<form id="hoa-ticket-form" class="hoa-form">
			<input type="text" name="subject" placeholder="<?php esc_attr_e( 'Subject', 'hoa-dashboard' ); ?>" required>
			<select name="category">
				<option value="general"><?php esc_html_e( 'General', 'hoa-dashboard' ); ?></option>
				<option value="maintenance"><?php esc_html_e( 'Maintenance', 'hoa-dashboard' ); ?></option>
				<option value="billing"><?php esc_html_e( 'Billing', 'hoa-dashboard' ); ?></option>
				<option value="violation"><?php esc_html_e( 'CC&R Violation', 'hoa-dashboard' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other', 'hoa-dashboard' ); ?></option>
			</select>
			<textarea name="description" placeholder="<?php esc_attr_e( 'Describe the issue', 'hoa-dashboard' ); ?>" required></textarea>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Submit Ticket', 'hoa-dashboard' ); ?></button>
		</form>
		<div id="hoa-ticket-confirmation" class="hoa-help" style="display:none;"></div>

		<h3><?php esc_html_e( 'My Tickets', 'hoa-dashboard' ); ?></h3>
		<table class="hoa-table" id="hoa-my-tickets-table">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Subject', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Status', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Opened', 'hoa-dashboard' ); ?></th></tr></thead>
			<tbody></tbody>
		</table>
	</section>

	<!-- CALENDAR -->
	<section class="hoa-tab-panel" id="tab-calendar" style="display:none;">
		<h2><?php esc_html_e( 'Upcoming Events', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-events-list">…</div>
		<?php if ( $is_board || $is_pm ) : ?>
		<h3><?php esc_html_e( 'Add Event', 'hoa-dashboard' ); ?></h3>
		<form id="hoa-event-form" class="hoa-form">
			<input type="text" name="title" placeholder="<?php esc_attr_e( 'Title', 'hoa-dashboard' ); ?>" required>
			<select name="event_type">
				<option value="board_meeting"><?php esc_html_e( 'Board Meeting', 'hoa-dashboard' ); ?></option>
				<option value="annual_meeting"><?php esc_html_e( 'Annual Meeting', 'hoa-dashboard' ); ?></option>
				<option value="social"><?php esc_html_e( 'Community Event', 'hoa-dashboard' ); ?></option>
			</select>
			<label><?php esc_html_e( 'Start', 'hoa-dashboard' ); ?> <input type="datetime-local" name="start_datetime" required></label>
			<label><?php esc_html_e( 'End', 'hoa-dashboard' ); ?> <input type="datetime-local" name="end_datetime"></label>
			<input type="text" name="location_text" placeholder="<?php esc_attr_e( 'Physical location (optional)', 'hoa-dashboard' ); ?>">
			<input type="url" name="zoom_link" placeholder="<?php esc_attr_e( 'Zoom link (optional)', 'hoa-dashboard' ); ?>">
			<textarea name="description" placeholder="<?php esc_attr_e( 'Agenda / notes', 'hoa-dashboard' ); ?>"></textarea>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Save Event', 'hoa-dashboard' ); ?></button>
		</form>
		<?php endif; ?>
	</section>

	<!-- NEWSLETTER -->
	<section class="hoa-tab-panel" id="tab-newsletter" style="display:none;">
		<h2><?php esc_html_e( 'Newsletter', 'hoa-dashboard' ); ?></h2>
		<div id="hoa-newsletter-list">…</div>
		<?php if ( $is_board || $is_pm ) : ?>
		<h3><?php esc_html_e( 'Draft This Month\'s Newsletter', 'hoa-dashboard' ); ?></h3>
		<button id="hoa-generate-newsletter-btn" class="hoa-btn"><?php esc_html_e( 'Auto-Generate Draft from Data', 'hoa-dashboard' ); ?></button>
		<form id="hoa-newsletter-form" class="hoa-form">
			<input type="hidden" name="id" value="">
			<input type="month" name="period_month_picker">
			<input type="text" name="subject" placeholder="<?php esc_attr_e( 'Subject line', 'hoa-dashboard' ); ?>" required>
			<textarea name="body_html" rows="10" placeholder="<?php esc_attr_e( 'Newsletter body (HTML allowed)', 'hoa-dashboard' ); ?>"></textarea>
			<button type="submit" class="hoa-btn"><?php esc_html_e( 'Save Draft', 'hoa-dashboard' ); ?></button>
			<?php if ( current_user_can( 'hoa_manage_newsletter' ) ) : ?>
			<button type="button" id="hoa-send-newsletter-btn" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Send to All Members', 'hoa-dashboard' ); ?></button>
			<?php endif; ?>
		</form>
		<?php endif; ?>
	</section>

	<!-- BOARD TOOLS -->
	<?php if ( $is_board ) : ?>
	<section class="hoa-tab-panel" id="tab-board" style="display:none;">
		<h2><?php esc_html_e( 'Board Oversight', 'hoa-dashboard' ); ?></h2>

		<h3><?php esc_html_e( 'All Trouble Tickets', 'hoa-dashboard' ); ?></h3>
		<div id="hoa-ticket-summary" class="hoa-summary-row"></div>
		<table class="hoa-table" id="hoa-all-tickets-table">
			<thead><tr>
				<th>#</th><th><?php esc_html_e( 'Member', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Subject', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Opened', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'PM Responded', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Closed', 'hoa-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Escalated', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Actions', 'hoa-dashboard' ); ?></th>
			</tr></thead>
			<tbody></tbody>
		</table>

		<h3><?php esc_html_e( 'Property Manager Audit Log', 'hoa-dashboard' ); ?></h3>
		<table class="hoa-table" id="hoa-audit-log-table">
			<thead><tr><th><?php esc_html_e( 'When', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Who', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Action', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Object', 'hoa-dashboard' ); ?></th><th>IP</th></tr></thead>
			<tbody></tbody>
		</table>

		<h3><?php esc_html_e( 'Property Manager Performance Reviews (Board Only)', 'hoa-dashboard' ); ?></h3>
		<div id="hoa-pm-review-avg" class="hoa-balance-box"></div>
		<table class="hoa-table" id="hoa-pm-reviews-table">
			<thead><tr><th><?php esc_html_e( 'Member', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Ticket', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Rating', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Comments', 'hoa-dashboard' ); ?></th><th><?php esc_html_e( 'Date', 'hoa-dashboard' ); ?></th></tr></thead>
			<tbody></tbody>
		</table>
	</section>
	<?php endif; ?>

	<!-- SECURITY / 2FA ENROLLMENT -->
	<section class="hoa-tab-panel" id="tab-security" style="display:none;">
		<h2><?php esc_html_e( 'Login & Security', 'hoa-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Enroll or update your phone number for SMS two-factor authentication.', 'hoa-dashboard' ); ?></p>
		<form id="hoa-2fa-enroll-form" class="hoa-form">
			<input type="tel" name="phone" placeholder="<?php esc_attr_e( '(555) 555-5555', 'hoa-dashboard' ); ?>" required>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Send Verification Code', 'hoa-dashboard' ); ?></button>
		</form>
		<form id="hoa-2fa-confirm-form" class="hoa-form" style="display:none;">
			<input type="text" name="code" placeholder="<?php esc_attr_e( '6-digit code', 'hoa-dashboard' ); ?>" maxlength="6" required>
			<button type="submit" class="hoa-btn hoa-btn-primary"><?php esc_html_e( 'Confirm', 'hoa-dashboard' ); ?></button>
		</form>
		<div id="hoa-2fa-status" class="hoa-help"></div>
	</section>
</div>
