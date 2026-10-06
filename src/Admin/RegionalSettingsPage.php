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

		// If redirected with error=invalid_input.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url_error = isset( $_GET['error'] ) && 'invalid_input' === $_GET['error'] ? 'invalid_input' : '';

		$notice = get_transient( "velog_settings_notice_{$user_id}" );
		if ( false !== $notice ) {
			delete_transient( "velog_settings_notice_{$user_id}" );
		}

		if ( 'invalid_input' === $url_error ) {
			$notice = array(
				'type'    => 'error',
				'message' => __( 'Invalid input submitted.', 'velog' ),
			);
		}

		$distance_units = array( 'km', 'mi' );
		$currencies     = array_keys( CurrencyCatalog::all() );
		?>
			<div class="wrap velog-admin-wrap">
				<h1><?php esc_html_e( 'VeLog Regional Settings', 'velog' ); ?></h1>

				<?php if ( is_wp_error( $settings ) ) : ?>
					<div class="notice notice-error">
						<p><?php echo esc_html( $settings->get_error_message() ); ?></p>
					</div>
					<p>
						<?php
						esc_html_e(
							'Settings are corrupted and cannot be modified. Please contact support.',
							'velog'
						);
						?>
					</p>
				<?php else : ?>

					<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
					<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
						<p><?php echo esc_html( $notice['message'] ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="velog_save_settings">
					<?php wp_nonce_field( 'velog_save_settings', 'velog_settings_nonce' ); ?>
					<input
						type="hidden"
						name="record_version"
						value="<?php echo esc_attr( (string) $settings['record_version'] ); ?>"
					>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="velog_distance_unit">
										<?php esc_html_e( 'Distance Unit', 'velog' ); ?>
									</label>
								</th>
								<td>
									<select name="distance_unit" id="velog_distance_unit" required>
										<option value="">
											<?php esc_html_e( '&mdash; Select &mdash;', 'velog' ); ?>
										</option>
										<?php foreach ( $distance_units as $unit ) : ?>
											<option
												value="<?php echo esc_attr( $unit ); ?>"
												<?php selected( $settings['distance_unit'], $unit ); ?>
											>
												<?php echo esc_html( $unit ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php
										esc_html_e(
											'Distance input supports up to three decimal places.',
											'velog'
										);
										?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="velog_currency_code">
										<?php esc_html_e( 'Currency', 'velog' ); ?>
									</label>
								</th>
								<td>
									<select name="currency_code" id="velog_currency_code" required>
										<option value="">
											<?php esc_html_e( '&mdash; Select &mdash;', 'velog' ); ?>
										</option>
										<?php foreach ( $currencies as $code ) : ?>
											<option
												value="<?php echo esc_attr( $code ); ?>"
												<?php selected( $settings['currency_code'], $code ); ?>
											>
												<?php echo esc_html( $code ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php
										esc_html_e(
											'Currency input uses ASCII digits, no grouping, and at most 15 digits.',
											'velog'
										);
										?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="velog_region">
										<?php esc_html_e( 'Region Hint (Optional)', 'velog' ); ?>
									</label>
								</th>
								<td>
									<input
										type="text"
										name="region"
										id="velog_region"
										value="<?php echo esc_attr( $settings['region'] ); ?>"
										class="regular-text"
									>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Locale & Timezone', 'velog' ); ?></th>
								<td>
									<p>
										<?php
										printf(
											/* translators: 1: locale, 2: timezone */
											esc_html__(
												'WordPress locale (%1$s) and timezone (%2$s) are authoritative.',
												'velog'
											),
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
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle settings save.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'velog' ), '', array( 'response' => 403 ) );
		}

		if (
			! isset( $_POST['velog_settings_nonce'] ) ||
			! is_string( $_POST['velog_settings_nonce'] )
		) {
			wp_die( esc_html__( 'Invalid nonce type.', 'velog' ), '', array( 'response' => 403 ) );
		}

		$nonce = sanitize_key( wp_unslash( $_POST['velog_settings_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'velog_save_settings' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'velog' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$record_version_raw = isset( $_POST['record_version'] ) ? wp_unslash( $_POST['record_version'] ) : null;

		if ( null === $record_version_raw || ! is_string( $record_version_raw ) ) {
			$url = admin_url( 'admin.php?page=velog-settings&settings-updated=false&error=invalid_input' );
			wp_safe_redirect( $url );
			exit;
		}

		if ( ! preg_match( '/^(0|[1-9][0-9]*)$/', $record_version_raw ) ) {
			$url = admin_url( 'admin.php?page=velog-settings&settings-updated=false&error=invalid_input' );
			wp_safe_redirect( $url );
			exit;
		}

		$expected_version = filter_var( $record_version_raw, FILTER_VALIDATE_INT );
		if ( false === $expected_version ) {
			$url = admin_url( 'admin.php?page=velog-settings&settings-updated=false&error=invalid_input' );
			wp_safe_redirect( $url );
			exit;
		}

		if ( ! isset( $_POST['distance_unit'] ) || ! is_string( $_POST['distance_unit'] ) ||
			! isset( $_POST['currency_code'] ) || ! is_string( $_POST['currency_code'] ) ||
			( isset( $_POST['region'] ) && ! is_string( $_POST['region'] ) ) ) {

			$url = admin_url( 'admin.php?page=velog-settings&settings-updated=false&error=invalid_input' );
			wp_safe_redirect( $url );
			exit;
		}

		// Input shape is checked above; values are validated inside ShopSettings.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
