<?php
/**
 * Regional Settings Page
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Regional\CurrencyCatalog;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and handles submission of regional settings.
 */
class RegionalSettingsPage {

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_velog_save_settings', $this, 'handle_save' );
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'velog' ) );
		}

		$settings = ShopSettings::get_settings();

		$user_id = get_current_user_id();
		$notice  = get_transient( "velog_settings_notice_{$user_id}" );
		if ( false !== $notice ) {
			delete_transient( "velog_settings_notice_{$user_id}" );
		}

		$distance_units = array( 'km', 'mi' );
		$currencies     = array_keys( CurrencyCatalog::all() );
		?>
		<div class="wrap velog-admin-wrap">
			<h1><?php esc_html_e( 'VeLog Regional Settings', 'velog' ); ?></h1>
			
			<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="velog_save_settings">
				<?php wp_nonce_field( 'velog_save_settings', 'velog_settings_nonce' ); ?>
				<input type="hidden" name="record_version" value="<?php echo esc_attr( $settings['record_version'] ); ?>">

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="velog_distance_unit"><?php esc_html_e( 'Distance Unit', 'velog' ); ?></label></th>
							<td>
								<select name="distance_unit" id="velog_distance_unit" required>
									<option value=""><?php esc_html_e( '&mdash; Select &mdash;', 'velog' ); ?></option>
									<?php foreach ( $distance_units as $unit ) : ?>
										<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $settings['distance_unit'], $unit ); ?>>
											<?php echo esc_html( $unit ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Used for vehicle odometers and service intervals. Input policy: up to 3 decimal places.', 'velog' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="velog_currency_code"><?php esc_html_e( 'Currency', 'velog' ); ?></label></th>
							<td>
								<select name="currency_code" id="velog_currency_code" required>
									<option value=""><?php esc_html_e( '&mdash; Select &mdash;', 'velog' ); ?></option>
									<?php foreach ( $currencies as $code ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['currency_code'], $code ); ?>>
											<?php echo esc_html( $code ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Currency for invoices and payments. Input policy: ASCII digits, locale decimal separator, no thousands grouping, max 15 digits.', 'velog' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="velog_region"><?php esc_html_e( 'Region Hint (Optional)', 'velog' ); ?></label></th>
							<td>
								<input type="text" name="region" id="velog_region" value="<?php echo esc_attr( $settings['region'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Locale & Timezone', 'velog' ); ?></th>
							<td>
								<p>
									<?php
									printf(
										/* translators: 1: locale, 2: timezone */
										esc_html__( 'VeLog uses the WordPress locale (%1$s) and timezone (%2$s) as authoritative.', 'velog' ),
										esc_html( get_locale() ),
										esc_html( wp_timezone_string() )
									);
									?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'velog' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle settings save.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'velog' ) );
		}

		if ( ! isset( $_POST['velog_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['velog_settings_nonce'] ) ), 'velog_save_settings' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'velog' ) );
		}

		$expected_version = isset( $_POST['record_version'] ) ? (int) $_POST['record_version'] : 0;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated securely inside ShopSettings.
		$input = wp_unslash( $_POST );

		$result = ShopSettings::save_settings( $input, $expected_version );

		$user_id = get_current_user_id();
		if ( is_wp_error( $result ) ) {
			set_transient(
				"velog_settings_notice_{$user_id}",
				array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				),
				60
			);
		} else {
			set_transient(
				"velog_settings_notice_{$user_id}",
				array(
					'type'    => 'success',
					'message' => __( 'Settings saved successfully.', 'velog' ),
				),
				60
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=velog-settings' ) );
		exit;
	}
}
