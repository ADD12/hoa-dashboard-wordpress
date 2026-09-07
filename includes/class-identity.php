<?php
/**
 * HOA Identity — cross-network resident ID schema.
 *
 * Every resident gets:
 *  - PID  (Person ID)     : generated at registration, one per WordPress user
 *  - AID  (Address ID)    : generated at registration, one per resident/unit address
 *  - HID  (Homeowner ID)  : generated only when the board marks someone as an owner
 *  - B-LAN Community ID   : optional, entered by the resident/board — links this
 *                           person into a wider B-LAN mesh community, if any
 *
 * IDs are UUID-based (not sequential) so they stay collision-safe across
 * independently-hosted HOA sites that may later sync over the B-LAN mesh
 * without a shared central counter.
 *
 * This class does NOT implement mesh sync itself — that lives in the
 * separate B-LAN Mesh Connector plugin. It exposes:
 *   - REST GET /hoa-dashboard/v1/identity        (current user's own IDs)
 *   - REST POST /hoa-dashboard/v1/identity/blan   (set your B-LAN Community ID)
 *   - action  'hoa_identity_generated' ( $user_id, $type, $id_value )
 *     fired whenever a PID/AID/HID is generated, so other plugins
 *     (e.g. B-LAN Mesh Connector) can react — provision a local mesh
 *     record, queue an offline sync, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Identity {

	const META_PID  = 'hoa_pid';
	const META_AID  = 'hoa_aid';
	const META_HID  = 'hoa_hid';
	const META_BLAN = 'hoa_blan_community_id';

	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
	}

	/** New-style prefixed UUID: e.g. PID-7F3A9C21-4B0E-4D8A-9C21-... trimmed for readability. */
	private static function generate_id( $prefix ) {
		$uuid = sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
		return strtoupper( $prefix ) . '-' . strtoupper( $uuid );
	}

	/**
	 * PID + AID are generated automatically for every new resident account.
	 * HID is deliberately NOT generated here — a person isn't necessarily
	 * an owner (could be a renter/tenant) until the board marks them so.
	 */
	public static function on_user_register( $user_id ) {
		self::ensure_pid( $user_id );
		self::ensure_aid( $user_id );
	}

	public static function ensure_pid( $user_id ) {
		$existing = get_user_meta( $user_id, self::META_PID, true );
		if ( $existing ) {
			return $existing;
		}
		$pid = self::generate_id( 'PID' );
		update_user_meta( $user_id, self::META_PID, $pid );
		do_action( 'hoa_identity_generated', $user_id, 'pid', $pid );
		return $pid;
	}

	public static function ensure_aid( $user_id ) {
		$existing = get_user_meta( $user_id, self::META_AID, true );
		if ( $existing ) {
			return $existing;
		}
		$aid = self::generate_id( 'AID' );
		update_user_meta( $user_id, self::META_AID, $aid );
		do_action( 'hoa_identity_generated', $user_id, 'aid', $aid );
		return $aid;
	}

	/** Called when the board/PM marks a resident as an owner. */
	public static function ensure_hid( $user_id ) {
		$existing = get_user_meta( $user_id, self::META_HID, true );
		if ( $existing ) {
			return $existing;
		}
		$hid = self::generate_id( 'HID' );
		update_user_meta( $user_id, self::META_HID, $hid );
		do_action( 'hoa_identity_generated', $user_id, 'hid', $hid );
		return $hid;
	}

	public static function get_identity( $user_id ) {
		return array(
			'pid'                 => get_user_meta( $user_id, self::META_PID, true ),
			'aid'                 => get_user_meta( $user_id, self::META_AID, true ),
			'hid'                 => get_user_meta( $user_id, self::META_HID, true ),
			'blan_community_id'   => get_user_meta( $user_id, self::META_BLAN, true ),
			'is_owner'            => (bool) get_user_meta( $user_id, self::META_HID, true ),
			'site_id'             => get_current_blog_id(),
		);
	}

	// ---------- REST ----------

	public static function register_routes() {
		register_rest_route( 'hoa-dashboard/v1', '/identity', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_get_identity' ),
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		) );

		register_rest_route( 'hoa-dashboard/v1', '/identity/blan', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_set_blan_id' ),
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		) );
	}

	public static function rest_get_identity( WP_REST_Request $request ) {
		return rest_ensure_response( self::get_identity( get_current_user_id() ) );
	}

	public static function rest_set_blan_id( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$blan_id = sanitize_text_field( $params['blan_community_id'] ?? '' );
		update_user_meta( get_current_user_id(), self::META_BLAN, $blan_id );
		return rest_ensure_response( self::get_identity( get_current_user_id() ) );
	}

	// ---------- Profile fields (admin-visible, board can grant HID) ----------

	public static function render_profile_fields( $user ) {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hoa_property_manager' ) ) {
			return;
		}
		$identity = self::get_identity( $user->ID );
		?>
		<h2><?php esc_html_e( 'HOA Identity (B-LAN Network)', 'hoa-dashboard' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label>PID</label></th>
				<td><code><?php echo esc_html( $identity['pid'] ?: '—' ); ?></code></td>
			</tr>
			<tr>
				<th><label>AID</label></th>
				<td><code><?php echo esc_html( $identity['aid'] ?: '—' ); ?></code></td>
			</tr>
			<tr>
				<th><label>HID</label></th>
				<td>
					<code><?php echo esc_html( $identity['hid'] ?: '— (not an owner of record)' ); ?></code>
					<?php if ( ! $identity['hid'] ) : ?>
						<label style="display:block;margin-top:6px;">
							<input type="checkbox" name="hoa_grant_hid" value="1"> <?php esc_html_e( 'Mark as homeowner (generate HID)', 'hoa-dashboard' ); ?>
						</label>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="hoa_blan_community_id"><?php esc_html_e( 'B-LAN Community ID (optional)', 'hoa-dashboard' ); ?></label></th>
				<td><input type="text" name="hoa_blan_community_id" id="hoa_blan_community_id" value="<?php echo esc_attr( $identity['blan_community_id'] ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	public static function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hoa_property_manager' ) ) {
			return;
		}
		if ( ! empty( $_POST['hoa_grant_hid'] ) ) {
			self::ensure_hid( $user_id );
		}
		if ( isset( $_POST['hoa_blan_community_id'] ) ) {
			update_user_meta( $user_id, self::META_BLAN, sanitize_text_field( wp_unslash( $_POST['hoa_blan_community_id'] ) ) );
		}
	}
}
