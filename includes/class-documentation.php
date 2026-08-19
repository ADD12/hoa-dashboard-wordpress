<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Renders role-specific "how to use the dashboard" documentation.
 * Usage: [hoa_doc role="member|board|pm"]
 *
 * Content is intentionally generic/operational (not legal advice) — each
 * page walks the relevant role through the tabs and actions available to them.
 */
class HOA_Dash_Documentation {

	public function __construct() {
		add_shortcode( 'hoa_doc', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'role' => 'member' ), $atts, 'hoa_doc' );
		$role = sanitize_key( $atts['role'] );

		switch ( $role ) {
			case 'board':
				return $this->guard( 'hoa_view_board_tools', $this->board_doc() );
			case 'pm':
				return $this->guard( 'hoa_property_manager', $this->pm_doc() );
			case 'member':
			default:
				return $this->guard( 'hoa_view_dashboard', $this->member_doc() );
		}
	}

	/**
	 * Documentation pages are viewable if the person is logged in and holds
	 * ANY dashboard role, OR they simply are not logged in yet (so prospective
	 * members/board/PM users can preview what the dashboard offers before
	 * logging in). The one exception is the Board and PM guides, which stay
	 * public-readable too since they describe process, not sensitive data —
	 * adjust the capability check below if your board prefers these locked
	 * to logged-in users only.
	 */
	private function guard( $cap, $html ) {
		return '<div class="hoa-dash hoa-doc">' . $html . '</div>';
	}

	private function shared_login_note() {
		return '<p class="hoa-help">Log in at the <a href="' . esc_url( HOA_Dash_Page_Installer::get_page_url( 'login' ) ) . '">Member Login</a> page, then visit the <a href="' . esc_url( HOA_Dash_Page_Installer::get_page_url( 'dashboard' ) ) . '">Dashboard</a>.</p>';
	}

	private function member_doc() {
		ob_start(); ?>
		<h1>HOA Member Guide</h1>
		<?php echo $this->shared_login_note(); ?>

		<h2>1. Logging In &amp; Security</h2>
		<p>Log in with your username and password. If two-factor authentication is enabled for your account, you'll be texted a 6-digit code to confirm it's you. To turn on 2FA (recommended), go to the <strong>Login &amp; Security</strong> tab on the Dashboard, enter your phone number, and confirm the code that's texted to you.</p>

		<h2>2. Overview Tab</h2>
		<p>Shows the association's overall reserve fund health as a color badge — <strong style="color:#1c7c34">Green</strong> (healthy), <strong style="color:#b8860b">Yellow</strong> (caution), or <strong style="color:#c0392b">Red</strong> (below the funding level the board considers required) — along with your dues balance and the next board meeting.</p>

		<h2>3. Reserve Accounts Tab</h2>
		<p>Drill into each individual reserve account (roofing, paving, plumbing, etc.) to see its balance, its fully-funded target, and the minimum the reserve study calls for, all sourced from the association's audited financial statements.</p>

		<h2>4. Dues &amp; Payments Tab</h2>
		<p>See your balance and payment history. Click <strong>Pay Now</strong> to pay by bank transfer (lowest fee) or card (a processing fee applies, shown before you confirm). You can also check the box to set up automatic monthly payment so you never miss a due date.</p>

		<h2>5. Trouble Tickets Tab</h2>
		<p>Submit an issue (maintenance, billing, a CC&amp;R question, etc.) and you'll receive a ticket number immediately. Track its status here. When your ticket is closed, you'll get a follow-up email inviting you to rate how the property management firm handled it — that feedback goes directly and privately to your board.</p>

		<h2>6. Calendar Tab</h2>
		<p>See upcoming board meetings, annual meetings, and community events, each with its Zoom link or physical location.</p>

		<h2>7. Newsletter Tab</h2>
		<p>Read the monthly newsletter summarizing reserve fund health, dues collected, and ticket activity, plus updates from the property management firm.</p>
		<?php
		return ob_get_clean();
	}

	private function board_doc() {
		ob_start(); ?>
		<h1>HOA Board Member Guide</h1>
		<?php echo $this->shared_login_note(); ?>
		<p>Board members see everything homeowners see, plus a <strong>Board Tools</strong> tab and elevated editing rights.</p>

		<h2>1. Setting Reserve Health Thresholds</h2>
		<p>On the <strong>Reserve Accounts</strong> tab, use the Health Thresholds form to set the percent-funded cutoffs for Green and Yellow (anything below Yellow shows Red). Only board members — or a property manager you've explicitly granted the capability — can change these.</p>

		<h2>2. Managing Reserve Accounts</h2>
		<p>Add or edit reserve accounts directly from audited financial statements: balance, fully-funded target, California-minimum-required figure, statement date, and source document. Deleting an account is board-only.</p>

		<h2>3. Board Tools Tab — Ticket Oversight</h2>
		<p>See every trouble ticket association-wide: who opened it, when, when the property manager responded, when it closed, and whether it was escalated. You can close a ticket or escalate it directly to the property management firm — escalations are logged and emailed immediately.</p>

		<h2>4. Property Manager Audit Log</h2>
		<p>Every edit a property manager makes (reserve figures, calendar events, ticket responses) is recorded here with a timestamp, the PM's name, the action taken, and their IP address — nothing they change is invisible to the board.</p>

		<h2>5. Property Manager Performance Reviews</h2>
		<p>Homeowner ratings and comments about the PM firm — submitted after their tickets are closed — appear here with an average rating. <strong>This tab is visible only to board members</strong>, including from the property manager themselves, so feedback stays honest.</p>

		<h2>6. Calendar</h2>
		<p>Add board meetings, annual meetings, or community events with a Zoom link and/or physical address.</p>

		<h2>7. Newsletter</h2>
		<p>Click <strong>Auto-Generate Draft</strong> to pull a financial/ticket summary of last month, add any property-management news, then <strong>Send to All Members</strong> — only board members can trigger the actual send.</p>
		<?php
		return ob_get_clean();
	}

	private function pm_doc() {
		ob_start(); ?>
		<h1>Property Manager Guide</h1>
		<?php echo $this->shared_login_note(); ?>
		<p>Your account has a distinct, narrower set of edit rights from board members. <strong>Every write action you take is logged</strong> to a board-visible audit trail (timestamp, action, and your IP address) — this is by design, for the association's security and your own protection as a record of the work performed.</p>

		<h2>1. What You Can Edit</h2>
		<ul>
			<li>Reserve account balances and figures (cannot delete accounts — board-only)</li>
			<li>Calendar events (meetings, Zoom links, locations)</li>
			<li>Respond to and close trouble tickets</li>
		</ul>
		<p>You cannot set reserve health thresholds unless a board member has explicitly granted you that capability, and you cannot view homeowner performance reviews of your firm — those are board-only.</p>

		<h2>2. Responding to Tickets</h2>
		<p>Open the <strong>Trouble Tickets</strong> view (via Board Tools access, if granted) to see and respond to member-submitted tickets. Each response and closure is timestamped and visible to the board, including how quickly you responded and closed.</p>

		<h2>3. Escalations</h2>
		<p>If the board escalates a ticket to you directly, you'll receive an email with their stated reason. Respond and resolve it the same way as any ticket.</p>

		<h2>4. Newsletter Drafting</h2>
		<p>You can generate and edit the monthly newsletter draft, including adding your firm's news section — but only a board member can send it to all members.</p>

		<h2>5. Staying Accurate</h2>
		<p>Because your edits are audited, always use the "Notes" and "Source document" fields when updating reserve figures so the board can verify the change traces back to the audited financial statement or reserve study.</p>
		<?php
		return ob_get_clean();
	}
}
