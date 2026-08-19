<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Activator {

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'hoa_';

		$sql = array();

		// Reserve fund accounts (sourced from audited financial statements)
		$sql[] = "CREATE TABLE {$p}reserve_accounts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			account_name VARCHAR(191) NOT NULL,
			account_category VARCHAR(100) NOT NULL,
			current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
			fully_funded_target DECIMAL(14,2) NOT NULL DEFAULT 0,
			ca_min_required DECIMAL(14,2) NOT NULL DEFAULT 0,
			statement_date DATE NULL,
			source_document VARCHAR(255) NULL,
			notes TEXT NULL,
			updated_by BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Reserve health thresholds (board/PM configurable, funding-percentage based per CA Civ. Code 5570 disclosure norms)
		$sql[] = "CREATE TABLE {$p}reserve_thresholds (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			green_min_pct DECIMAL(5,2) NOT NULL DEFAULT 70.00,
			yellow_min_pct DECIMAL(5,2) NOT NULL DEFAULT 40.00,
			red_below_pct DECIMAL(5,2) NOT NULL DEFAULT 40.00,
			set_by BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Dues / assessments ledger
		$sql[] = "CREATE TABLE {$p}dues_ledger (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			property_unit VARCHAR(100) NULL,
			amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
			amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
			due_date DATE NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) $charset_collate;";

		// Payments / autopay records (gateway-agnostic: stores tokens only, never raw card/bank data)
		$sql[] = "CREATE TABLE {$p}payments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			ledger_id BIGINT UNSIGNED NULL,
			amount DECIMAL(10,2) NOT NULL,
			fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			method VARCHAR(30) NOT NULL,
			gateway VARCHAR(30) NOT NULL,
			gateway_ref VARCHAR(191) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			is_autopay TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) $charset_collate;";

		// Autopay subscriptions
		$sql[] = "CREATE TABLE {$p}autopay (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			gateway VARCHAR(30) NOT NULL,
			gateway_customer_id VARCHAR(191) NULL,
			gateway_payment_method_id VARCHAR(191) NULL,
			funding_type VARCHAR(20) NOT NULL DEFAULT 'bank',
			active TINYINT(1) NOT NULL DEFAULT 1,
			day_of_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) $charset_collate;";

		// Support / trouble tickets
		$sql[] = "CREATE TABLE {$p}tickets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_number VARCHAR(20) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			subject VARCHAR(255) NOT NULL,
			description TEXT NULL,
			category VARCHAR(50) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			assigned_to BIGINT UNSIGNED NULL,
			escalated TINYINT(1) NOT NULL DEFAULT 0,
			escalated_at DATETIME NULL,
			pm_responded_at DATETIME NULL,
			closed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY ticket_number (ticket_number)
		) $charset_collate;";

		// Ticket activity/audit trail
		$sql[] = "CREATE TABLE {$p}ticket_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			actor_id BIGINT UNSIGNED NULL,
			event_type VARCHAR(50) NOT NULL,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY ticket_id (ticket_id)
		) $charset_collate;";

		// Calendar events (board meetings etc.)
		$sql[] = "CREATE TABLE {$p}events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			event_type VARCHAR(50) NOT NULL DEFAULT 'board_meeting',
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NULL,
			location_text VARCHAR(255) NULL,
			zoom_link VARCHAR(255) NULL,
			description TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Property manager audit log (all edits by PM role, board-auditable)
		$sql[] = "CREATE TABLE {$p}pm_audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(100) NOT NULL,
			object_type VARCHAR(50) NULL,
			object_id BIGINT UNSIGNED NULL,
			before_value LONGTEXT NULL,
			after_value LONGTEXT NULL,
			ip_address VARCHAR(45) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY actor_id (actor_id)
		) $charset_collate;";

		// Escalation log (board escalating issues to PM firm)
		$sql[] = "CREATE TABLE {$p}escalations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NULL,
			raised_by BIGINT UNSIGNED NOT NULL,
			reason TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			resolution_note TEXT NULL,
			resolved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// PM performance reviews (board-only visibility)
		$sql[] = "CREATE TABLE {$p}pm_reviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			ticket_id BIGINT UNSIGNED NULL,
			rating TINYINT UNSIGNED NOT NULL,
			comments TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Newsletter issues
		$sql[] = "CREATE TABLE {$p}newsletters (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			period_month DATE NOT NULL,
			subject VARCHAR(255) NOT NULL,
			body_html LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			sent_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// 2FA - phone verification store
		$sql[] = "CREATE TABLE {$p}user_2fa (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			phone_number VARCHAR(30) NOT NULL,
			verified TINYINT(1) NOT NULL DEFAULT 0,
			twilio_sid VARCHAR(191) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		// Seed a default threshold row if none exists
		$table = $p . 'reserve_thresholds';
		$exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( ! $exists ) {
			$wpdb->insert( $table, array(
				'green_min_pct'  => 70.00,
				'yellow_min_pct' => 40.00,
				'red_below_pct'  => 40.00,
			) );
		}

		update_option( 'hoa_dash_db_version', HOA_DASH_DB_VERSION );

		HOA_Dash_Roles::register_roles();

		HOA_Dash_Page_Installer::install();

		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
