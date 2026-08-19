<?php
// Only runs when the plugin is deleted from wp-admin, never on deactivation.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

/*
 * Intentionally conservative: financial/audit records should not vanish
 * silently on uninstall. Table drops are commented out by default —
 * uncomment only if you are certain you want to permanently delete all
 * HOA Dashboard data (reserves, dues, tickets, audit log, reviews, etc).
 */

global $wpdb;
$prefix = $wpdb->prefix . 'hoa_';

$tables = array(
	'reserve_accounts', 'reserve_thresholds', 'dues_ledger', 'payments',
	'autopay', 'tickets', 'ticket_events', 'events', 'pm_audit_log',
	'escalations', 'pm_reviews', 'newsletters', 'user_2fa',
);

// foreach ( $tables as $t ) {
// 	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$t}" );
// }

delete_option( 'hoa_dash_db_version' );
delete_option( 'hoa_dash_roles_version' );
