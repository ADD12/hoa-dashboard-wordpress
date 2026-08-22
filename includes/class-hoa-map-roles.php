<?php
/**
 * HOA Map — role/capability gate, centralized in one place on purpose.
 *
 * >>> EDIT THIS FILE to match your existing role/capability names. <<<
 *
 * The plugin already defines three roles: HOA Member, HOA Board Member,
 * and Property Manager. Point the checks below at whatever capability
 * or role slug you actually used when those roles were registered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Map_Roles {

	/**
	 * Anyone who should see the map at all: all three roles, plus
	 * WP admins for testing.
	 */
	public static function can_view_map( $request = null ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		$viewer_roles = apply_filters( 'hoa_map_viewer_roles', array(
			'hoa_member',
			'hoa_board_member',
			'property_manager',
		) );
		return (bool) array_intersect( $viewer_roles, (array) $user->roles );
	}

	/**
	 * Board members & property managers: solar planning, broadcasts,
	 * ticket status changes, full parcel/owner visibility.
	 */
	public static function can_manage_common_areas( $request = null ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		$manager_roles = apply_filters( 'hoa_map_manager_roles', array(
			'hoa_board_member',
			'property_manager',
		) );
		return (bool) array_intersect( $manager_roles, (array) $user->roles );
	}
}
