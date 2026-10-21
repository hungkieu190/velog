<?php
/**
 * Native vehicle table from authorized operational snapshots.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Common\Regional\ShopSettings;

// phpcs:disable Generic.Files.LineLength.TooLong -- Native HTML markup follows the existing admin view convention.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders escaped operational fields without customer contacts. */
final class VehicleListTable {

	/**
	 * Render bounded vehicle results using native list-table markup.
	 *
	 * @param array<string, mixed> $result Authorized query result.
	 */
	public function render( array $result ): void {
		echo '<div class="velog-vehicle-table-wrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Vehicle records', 'velog' ) . '"><table class="widefat striped velog-vehicle-table"><thead><tr>';
		foreach ( array( __( 'ID', 'velog' ), __( 'Plate / jurisdiction', 'velog' ), __( 'VIN', 'velog' ), __( 'Customer', 'velog' ), __( 'Initial odometer', 'velog' ), __( 'State', 'velog' ), __( 'Actions', 'velog' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No vehicles found.', 'velog' ) . '</td></tr>';
		}
		$settings = ShopSettings::require_configured();
		$unit     = is_wp_error( $settings ) ? '' : $settings['distance_unit'];
		foreach ( $result['items'] as $item ) {
			$fields   = $item['fields'];
			$baseline = $fields['baseline'] ?? array();
			$reading  = VehiclePage::baseline_label( $baseline, $unit );
			echo '<tr><td>' . esc_html( (string) $item['id'] ) . '</td><td>' . esc_html( ( $fields['plate'] ?? '' ) . ' / ' . ( $fields['jurisdiction'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $fields['vin'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $item['customer']['name'] ?? '' ) ) . '</td><td>' . esc_html( $reading ) . '</td>';
			echo '<td>' . esc_html( 'active' === $item['state'] ? __( 'Active', 'velog' ) : __( 'Archived', 'velog' ) ) . '</td><td>';
			$url = add_query_arg(
				array(
					'page'       => 'velog-vehicles',
					'vehicle_id' => $item['id'],
				),
				admin_url( 'admin.php' )
			);
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( current_user_can( 'mf_velog_manage_vehicles' ) ? __( 'Edit', 'velog' ) : __( 'View', 'velog' ) ) . '</a>';
			echo ' <a href="' . esc_url(
				add_query_arg(
					array(
						'page'       => 'velog-history',
						'vehicle_id' => $item['id'],
					),
					admin_url( 'admin.php' )
				)
			) . '">' . esc_html__( 'History', 'velog' ) . '</a>';
			if ( current_user_can( 'mf_velog_manage_reminders' ) ) {
				echo ' <a href="' . esc_url(
					add_query_arg(
						array(
							'page'           => 'velog-reminders',
							'filter_vehicle' => $item['id'],
							'state'          => 'all',
						),
						admin_url( 'admin.php' )
					)
				) . '">' . esc_html__( 'Reminders', 'velog' ) . '</a>';
			}
			if ( current_user_can( 'mf_velog_manage_vehicles' ) ) {
				$operation = 'active' === $item['state'] ? 'archive' : 'restore';
				echo '<form method="post" class="velog-vehicle-state-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				VehiclePage::action_fields( $operation, (int) $item['id'], (int) $item['record_version'] );
				submit_button( 'archive' === $operation ? __( 'Archive', 'velog' ) : __( 'Restore', 'velog' ), 'small', 'submit', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
