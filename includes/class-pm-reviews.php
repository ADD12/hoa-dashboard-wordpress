<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Homeowners rate the property management firm's performance (e.g. after a
 * ticket is closed, via the follow-up email link). These reviews are visible
 * ONLY to board members — never to the property manager or other homeowners —
 * so feedback stays honest and is used for board oversight/contract renewal decisions.
 */
class HOA_Dash_PM_Reviews {

	public function __construct() {
		add_action( 'wp_ajax_hoa_submit_pm_review', array( $this, 'ajax_submit_review' ) );
		add_action( 'wp_ajax_hoa_get_pm_reviews', array( $this, 'ajax_get_reviews' ) );
	}

	public function ajax_submit_review() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_submit_pm_review' ) ) { wp_send_json_error( 'forbidden' ); }

		$rating   = min( 5, max( 1, absint( $_POST['rating'] ?? 0 ) ) );
		$comments = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 ) ?: null;
		if ( ! $rating ) { wp_send_json_error( 'invalid_rating' ); }

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_pm_reviews', array(
			'user_id'   => get_current_user_id(),
			'ticket_id' => $ticket_id,
			'rating'    => $rating,
			'comments'  => $comments,
		) );
		wp_send_json_success( array( 'message' => 'Thank you for your feedback.' ) );
	}

	/** Board-only: aggregate + individual reviews. */
	public function ajax_get_reviews() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_view_pm_reviews' ) ) { wp_send_json_error( 'forbidden' ); }

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT r.*, u.display_name, t.ticket_number FROM {$wpdb->prefix}hoa_pm_reviews r
			 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
			 LEFT JOIN {$wpdb->prefix}hoa_tickets t ON t.id = r.ticket_id
			 ORDER BY r.created_at DESC",
			ARRAY_A
		);
		$avg = $wpdb->get_var( "SELECT AVG(rating) FROM {$wpdb->prefix}hoa_pm_reviews" );

		wp_send_json_success( array( 'reviews' => $rows, 'average_rating' => $avg ? round( (float) $avg, 2 ) : null ) );
	}
}
