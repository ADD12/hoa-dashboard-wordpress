<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creates the pages the plugin needs (Login, Dashboard, and one documentation
 * page per role) on activation, and re-creates any that a user later deletes
 * whenever the site re-checks (init, low frequency). Page IDs are stored in
 * options so activation is idempotent — running it twice never creates
 * duplicate pages.
 */
class HOA_Dash_Page_Installer {

	const OPT_KEY = 'hoa_dash_page_ids';

	/**
	 * Definitions: option sub-key => [ title, shortcode, description ]
	 */
	public static function page_definitions() {
		return array(
			'login' => array(
				'title'     => 'HOA Login',
				'shortcode' => '[hoa_login]',
			),
			'dashboard' => array(
				'title'     => 'HOA Dashboard',
				'shortcode' => '[hoa_dashboard]',
			),
			'doc_member' => array(
				'title'     => 'HOA Member Guide',
				'shortcode' => '[hoa_doc role="member"]',
			),
			'doc_board' => array(
				'title'     => 'Board Member Guide',
				'shortcode' => '[hoa_doc role="board"]',
			),
			'doc_pm' => array(
				'title'     => 'Property Manager Guide',
				'shortcode' => '[hoa_doc role="pm"]',
			),
		);
	}

	/** Run on plugin activation. */
	public static function install() {
		$stored = get_option( self::OPT_KEY, array() );
		$defs = self::page_definitions();

		foreach ( $defs as $key => $def ) {
			$page_id = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
			$existing = $page_id ? get_post( $page_id ) : null;

			// Page missing or was trashed/deleted — (re)create it.
			if ( ! $existing || $existing->post_status === 'trash' ) {
				$page_id = wp_insert_post( array(
					'post_title'   => $def['title'],
					'post_content' => $def['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				) );
				$stored[ $key ] = $page_id;
			}
		}

		update_option( self::OPT_KEY, $stored );
		update_option( 'hoa_dash_pages_version', HOA_DASH_VERSION );
	}

	public static function get_page_id( $key ) {
		$stored = get_option( self::OPT_KEY, array() );
		return isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
	}

	public static function get_page_url( $key ) {
		$id = self::get_page_id( $key );
		return $id ? get_permalink( $id ) : '';
	}

	/**
	 * Safety net: if the plugin version bumped (new definitions may exist)
	 * or a page was deleted since activation, re-run install() once.
	 * Cheap no-op the rest of the time since it only compares an option string.
	 */
	public static function maybe_reinstall() {
		if ( get_option( 'hoa_dash_pages_version' ) !== HOA_DASH_VERSION ) {
			self::install();
		}
	}
}
