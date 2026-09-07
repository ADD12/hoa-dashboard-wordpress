<?php
/**
 * HOA Multisite — launch a brand-new, separate HOA site from Network Admin.
 *
 * Only loads/registers anything when WordPress Multisite is enabled.
 * Each HOA gets its own site (own tables, own users-per-site roles,
 * own Settings/Map/etc.) — this class just automates what would
 * otherwise be several manual steps: create the site, activate the
 * plugin on it, and pre-fill its name/address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Multisite {

	public static function init() {
		if ( ! is_multisite() ) {
			return;
		}
		add_action( 'network_admin_menu', array( __CLASS__, 'register_network_menu' ) );
		add_shortcode( 'hoa_switcher', array( __CLASS__, 'render_switcher_shortcode' ) );
	}

	/**
	 * [hoa_switcher] — for residents/board members who belong to more than
	 * one HOA site on this network, shows a "My HOAs" dropdown so they can
	 * jump between them without re-logging-in. Add this shortcode near the
	 * top of your HOA Dashboard page, alongside [hoa_dashboard].
	 */
	public static function render_switcher_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user_id = get_current_user_id();
		$sites   = get_blogs_of_user( $user_id );
		if ( count( $sites ) < 2 ) {
			return ''; // nothing to switch between
		}

		$current_site_id = get_current_blog_id();
		$html = '<div class="hoa-site-switcher"><label for="hoa-site-switcher-select">' . esc_html__( 'My HOAs:', 'hoa-dashboard' ) . '</label> <select id="hoa-site-switcher-select" onchange="if(this.value) window.location.href=this.value;">';
		foreach ( $sites as $site ) {
			$site_id = $site->userblog_id;
			// Only list sites where HOA Dashboard is actually configured.
			$hoa_name = get_blog_option( $site_id, 'hoa_dash_hoa_name' );
			if ( ! $hoa_name ) {
				continue;
			}
			$url = get_home_url( $site_id, '/hoa-dashboard/' );
			$selected = ( (int) $site_id === (int) $current_site_id ) ? ' selected' : '';
			$html .= '<option value="' . esc_url( $url ) . '"' . $selected . '>' . esc_html( $hoa_name ) . '</option>';
		}
		$html .= '</select></div>';
		return $html;
	}

	public static function register_network_menu() {
		add_menu_page(
			__( 'Launch New HOA', 'hoa-dashboard' ),
			__( 'Launch New HOA', 'hoa-dashboard' ),
			'manage_network',
			'hoa-launch-new',
			array( __CLASS__, 'render_launch_page' ),
			'dashicons-admin-multisite',
			58
		);
	}

	public static function render_launch_page() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'hoa-dashboard' ) );
		}

		$result = null;
		$error  = null;

		if ( ! empty( $_POST['hoa_launch_nonce'] ) && wp_verify_nonce( $_POST['hoa_launch_nonce'], 'hoa_launch_new' ) ) {
			$result_or_error = self::launch_new_hoa(
				sanitize_text_field( wp_unslash( $_POST['hoa_name'] ?? '' ) ),
				sanitize_title( wp_unslash( $_POST['hoa_slug'] ?? '' ) ),
				sanitize_email( wp_unslash( $_POST['hoa_admin_email'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['hoa_address'] ?? '' ) )
			);
			if ( is_wp_error( $result_or_error ) ) {
				$error = $result_or_error->get_error_message();
			} else {
				$result = $result_or_error;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Launch a New HOA', 'hoa-dashboard' ); ?></h1>
			<p><?php esc_html_e( 'Creates a brand-new, fully separate HOA site on this network — its own database tables, its own residents, its own Settings and Map.', 'hoa-dashboard' ); ?></p>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( $result ) : ?>
				<div class="notice notice-success">
					<p>
						<?php esc_html_e( 'HOA site created:', 'hoa-dashboard' ); ?>
						<a href="<?php echo esc_url( $result['site_url'] ); ?>" target="_blank"><?php echo esc_html( $result['site_url'] ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'hoa_launch_new', 'hoa_launch_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="hoa_name"><?php esc_html_e( 'HOA Name', 'hoa-dashboard' ); ?></label></th>
						<td><input type="text" id="hoa_name" name="hoa_name" class="regular-text" required placeholder="e.g. Woodlands Thousand Oaks"></td>
					</tr>
					<tr>
						<th><label for="hoa_slug"><?php esc_html_e( 'Site Path/Subdomain', 'hoa-dashboard' ); ?></label></th>
						<td><input type="text" id="hoa_slug" name="hoa_slug" class="regular-text" required placeholder="e.g. woodlands"> <p class="description"><?php esc_html_e( 'Letters, numbers, and hyphens only.', 'hoa-dashboard' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="hoa_admin_email"><?php esc_html_e( 'Site Admin Email', 'hoa-dashboard' ); ?></label></th>
						<td><input type="email" id="hoa_admin_email" name="hoa_admin_email" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="hoa_address"><?php esc_html_e( 'HOA Address (optional)', 'hoa-dashboard' ); ?></label></th>
						<td><input type="text" id="hoa_address" name="hoa_address" class="regular-text" placeholder="Pre-fills the map center on the new site"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Launch New HOA', 'hoa-dashboard' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array{site_id:int,site_url:string}|WP_Error
	 */
	public static function launch_new_hoa( $hoa_name, $slug, $admin_email, $address = '' ) {
		if ( empty( $hoa_name ) || empty( $slug ) || empty( $admin_email ) ) {
			return new WP_Error( 'hoa_launch_missing_fields', __( 'HOA name, site path, and admin email are required.', 'hoa-dashboard' ) );
		}
		if ( ! is_email( $admin_email ) ) {
			return new WP_Error( 'hoa_launch_bad_email', __( 'That doesn\u2019t look like a valid email address.', 'hoa-dashboard' ) );
		}

		$network = get_network();
		$domain  = $network->domain;
		$path    = '/';
		$is_subdomain_install = is_subdomain_install();

		if ( $is_subdomain_install ) {
			$new_domain = $slug . '.' . preg_replace( '#^www\.#', '', $domain );
			$new_path   = '/';
		} else {
			$new_domain = $domain;
			$new_path   = '/' . trim( $slug, '/' ) . '/';
		}

		if ( domain_exists( $new_domain, $new_path, $network->id ) ) {
			return new WP_Error( 'hoa_launch_exists', __( 'A site already exists at that address/path — choose a different slug.', 'hoa-dashboard' ) );
		}

		$user_id = email_exists( $admin_email );
		if ( ! $user_id ) {
			$password = wp_generate_password( 16 );
			$user_id  = wpmu_create_user( sanitize_user( $slug . '-admin', true ), $password, $admin_email );
			if ( ! $user_id ) {
				return new WP_Error( 'hoa_launch_user_failed', __( 'Could not create the site admin user.', 'hoa-dashboard' ) );
			}
		}

		$site_id = wpmu_create_blog( $new_domain, $new_path, $hoa_name, $user_id, array( 'public' => 1 ), $network->id );
		if ( is_wp_error( $site_id ) ) {
			return $site_id;
		}

		switch_to_blog( $site_id );

		// Activate HOA Dashboard on the new site and pre-fill its identity.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_basename = 'hoa-dashboard/hoa-dashboard.php';
		if ( ! is_plugin_active( $plugin_basename ) ) {
			activate_plugin( $plugin_basename );
		}
		update_option( 'hoa_dash_hoa_name', $hoa_name );
		if ( $address ) {
			update_option( 'hoa_dash_map_address', $address );
		}

		$site_url = get_site_url( $site_id );
		restore_current_blog();

		do_action( 'hoa_multisite_hoa_launched', $site_id, $hoa_name, $address );

		return array( 'site_id' => $site_id, 'site_url' => $site_url );
	}
}
