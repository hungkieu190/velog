<?php
/**
 * Native private service-type management.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Common\Service\ServiceTypes;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Generic.Files.LineLength.TooLong -- Native escaped table/form markup follows admin convention.

/** Manager-only active/inactive type editor with no deletion route. */
final class ServiceTypePage {

	/**
	 * Register a distinct secured POST handler.
	 *
	 * @param Loader $loader Hook registry.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_mf_velog_service_type_save', $this, 'handle_post' );
	}

	/** Handle one versioned manager write. */
	public function handle_post(): void {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! current_user_can( 'mf_velog_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$raw     = ServicePage::value( 'type_id' );
		$id      = VehiclePage::positive_int( $raw );
		$version = VehiclePage::positive_int( ServicePage::value( 'record_version' ) );
		if ( ( 0 === $id && '0' !== $raw ) || ( $id > 0 && $version < 1 ) ) {
			wp_die( esc_html__( 'Invalid service type.', 'velog' ), '', array( 'response' => 400 ) );
		}
		if ( ! wp_verify_nonce( ServicePage::value( 'type_nonce' ), 'mf_velog_service_type_' . $id ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$result = ServiceTypes::save( $id, $version, ServicePage::value( 'type_name' ), ServicePage::value( 'type_state' ), wp_get_current_user(), ServicePage::value( 'request_id' ) );
		$code   = is_wp_error( $result ) ? 'stale_version' === $result->get_error_code() ? 'stale_version' : 'invalid_input' : 'saved';
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'velog-service-types',
					'service_notice' => $code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Render the private type list and single native editor. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap velog-service-types"><h1>' . esc_html__( 'Service types', 'velog' ) . '</h1>';
		$notice = ServicePage::value( 'service_notice', false );
		if ( '' !== $notice ) {
			ServicePage::notice( $notice );
		}
		$catalog = ServiceTypes::all( wp_get_current_user() );
		$id      = VehiclePage::positive_int( ServicePage::value( 'type_id', false ) );
		if ( is_wp_error( $catalog ) || ( '' !== ServicePage::value( 'type_id', false ) && ! isset( $catalog[ $id ] ) ) ) {
			ServicePage::notice( 'invalid_input' );
			echo '</div>';
			return;
		}
		$item = $catalog[ $id ] ?? array();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mf_velog_service_type_save"><input type="hidden" name="type_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="record_version" value="' . esc_attr( (string) ( $item['version'] ?? 0 ) ) . '"><input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		wp_nonce_field( 'mf_velog_service_type_' . $id, 'type_nonce', false );
		ServicePage::input( 'type_name', __( 'Service type name', 'velog' ), $item['name'] ?? '' );
		echo '<p><label for="velog-type-state">' . esc_html__( 'State', 'velog' ) . '</label><br><select id="velog-type-state" name="type_state">';
		foreach ( array(
			'active'   => __( 'Active', 'velog' ),
			'inactive' => __( 'Inactive', 'velog' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $item['state'] ?? 'active', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p><p class="description">' . esc_html__( 'Deactivate instead of deleting. Renaming or deactivating does not change finalized service snapshots.', 'velog' ) . '</p>';
		submit_button( $id > 0 ? __( 'Update service type', 'velog' ) : __( 'Create service type', 'velog' ) );
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-service-types' ) ) . '">' . esc_html__( 'New type / cancel', 'velog' ) . '</a></form><div class="velog-service-table-wrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Service types', 'velog' ) . '"><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Name', 'velog' ) . '</th><th scope="col">' . esc_html__( 'State', 'velog' ) . '</th><th scope="col">' . esc_html__( 'Version', 'velog' ) . '</th><th scope="col">' . esc_html__( 'Action', 'velog' ) . '</th></tr></thead><tbody>';
		if ( empty( $catalog ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No service types. Create an active type before finalizing a service.', 'velog' ) . '</td></tr>';
		}
		foreach ( $catalog as $type ) {
			echo '<tr><td>' . esc_html( $type['name'] ) . '</td><td>' . esc_html( 'active' === $type['state'] ? __( 'Active', 'velog' ) : __( 'Inactive', 'velog' ) ) . '</td><td>' . esc_html( (string) $type['version'] ) . '</td><td><a href="' . esc_url(
				add_query_arg(
					array(
						'page'    => 'velog-service-types',
						'type_id' => $type['id'],
					),
					admin_url( 'admin.php' )
				)
			) . '">' . esc_html__( 'Edit', 'velog' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
