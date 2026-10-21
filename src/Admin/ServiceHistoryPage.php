<?php
/**
 * Native private per-vehicle finalized timeline.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Service\ServiceHistoryQuery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read-only history with explicit filters and accessible correction disclosure. */
final class ServiceHistoryPage {

	/** Render authorized historical snapshots, never draft mutations. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_read_records' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap velog-history"><h1>' . esc_html__( 'Service history', 'velog' ) . '</h1>';
		$vehicle = VehiclePage::positive_int( ServicePage::value( 'vehicle_id', false ) );
		$type    = ServicePage::value( 'type', false );
		$page    = ServicePage::value( 'paged', false );
		$filters = array(
			'from' => ServicePage::value( 'from', false ),
			'to'   => ServicePage::value( 'to', false ),
			'term' => ServicePage::value( 'term', false ),
			'type' => '' === $type ? 0 : VehiclePage::positive_int( $type ),
			'page' => '' === $page ? 1 : VehiclePage::positive_int( $page ),
		);
		$result  = $vehicle > 0 && ( '' === $type
			|| $filters['type'] > 0 ) ? ( new ServiceHistoryQuery() )->search(
				$vehicle,
				$filters,
				wp_get_current_user()
			) : null;
		echo '<form method="get"><input type="hidden" name="page" value="velog-history">';
		ServicePage::input( 'vehicle_id', __( 'Vehicle record ID', 'velog' ), ServicePage::value( 'vehicle_id', false ) );
		ServicePage::input( 'from', __( 'From date (inclusive)', 'velog' ), $filters['from'], 'date' );
		ServicePage::input( 'to', __( 'To date (inclusive)', 'velog' ), $filters['to'], 'date' );
		ServicePage::input( 'term', __( 'Search notes or historical service type', 'velog' ), $filters['term'] );
		echo '<p><label for="history-type">' . esc_html__( 'Historical service type', 'velog' )
			. '</label> <select id="history-type" name="type"><option value="">' . esc_html__( 'All types', 'velog' )
			. '</option>';
		$options = is_array( $result ) ? $result['types'] : array();
		if ( $filters['type'] > 0 && ! isset( $options[ $filters['type'] ] ) ) {
			$options[ $filters['type'] ] = '#' . $filters['type'];
		}
		foreach ( $options as $id => $label ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $filters['type'], $id, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		submit_button( __( 'Filter history', 'velog' ), 'secondary', '', false );
		echo '</form>';
		if ( null === $result || is_wp_error( $result ) ) {
			if ( '' === ServicePage::value( 'vehicle_id', false ) ) {
				echo '<p>' . esc_html__( 'Enter a vehicle ID to view its finalized service history.', 'velog' ) . '</p>';
			} else {
				ServicePage::notice( 'invalid_input' );
			}
			echo '</div>';
			return;
		}
		$settings = ShopSettings::require_configured();
		$unit     = is_wp_error( $settings ) ? '' : $settings['distance_unit'];
		$current  = $result['current'];
		echo '<p>' . esc_html__( 'Current odometer:', 'velog' ) . ' ' . esc_html(
			empty( $current ) ? __(
				'Unknown',
				'velog'
			) : ServicePage::reading_label(
				$current['reading'],
				$current['date'],
				$unit
			)
		) . '</p>';
		if ( ! empty( $current ) ) {
			echo '<p>' . esc_html(
				0 === $current['service_id'] ? __(
					'Source: initial vehicle reading.',
					'velog'
				) : sprintf(
					/* translators: %d: source service ID. */                    __(
						'Source: finalized service #%d.',
						'velog'
					),
					$current['service_id']
				)
			) . '</p>';
		}
		echo '<div class="velog-service-table-wrap" role="region" tabindex="0" aria-label="'
			. esc_attr__( 'Finalized service history', 'velog' ) . '"><table class="widefat striped"><thead><tr>';
		foreach ( array(
			__( 'Service / date', 'velog' ),
			__( 'Odometer', 'velog' ),
			__( 'Historical customer / type', 'velog' ),
			__( 'Cost', 'velog' ),
			__(
				'Notes / corrections',
				'velog'
			),
		) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No finalized services match these filters.', 'velog' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			$f   = $item['fields'];
			$url = add_query_arg(
				array(
					'page'       => 'velog-services',
					'service_id' => $item['id'],
				),
				admin_url( 'admin.php' )
			);
			echo '<tr><td><a href="' . esc_url( $url ) . '">#' . esc_html( (string) $item['id'] ) . '</a><br>'
				. esc_html( $f['service_date'] ) . '<br>' . esc_html(
					$result['actors'][ $item['created_by'] ] ?? '#'
					. $item['created_by']
				) . '</td><td>' . esc_html(
					ServicePage::reading_label(
						$f['reading'],
						$f['service_date'],
						$unit
					)
				) . '</td><td>' . esc_html(
					$f['customer_snapshot']['name'] . ' / '
						. $f['type_snapshot']['name']
				) . '</td><td>' . esc_html(
					empty( $f['cost'] ) ? __(
						'Cost not recorded',
						'velog'
					) : self::cost( $f['cost'] )
				) . '</td><td>' . nl2br( esc_html( $f['notes'] ?? '' ) );
			foreach ( $item['audit'] as $entry ) {
				if ( 'finalized' !== ( $entry['before']['state'] ?? '' ) ) {
					continue;
				}
				echo '<details><summary>' . esc_html__( 'Correction', 'velog' ) . ' — ' . esc_html(
					$entry['at_utc']
					. ' / ' . ( $result['actors'][ $entry['actor_id'] ] ?? '#' . $entry['actor_id'] )
				) . '</summary><p>'
					. esc_html( $entry['reason'] ) . '</p>';
				foreach ( array(
					'before' => __( 'Before', 'velog' ),
					'after'  => __( 'After', 'velog' ),
				) as $side => $label ) {
					$snapshot = $entry[ $side ];
					echo '<p><strong>' . esc_html( $label ) . '</strong>: '
						. esc_html(
							ServicePage::reading_label(
								$snapshot['reading'] ?? array(),
								$snapshot['service_date'] ?? '',
								$unit
							) . ' / ' . ( $snapshot['type_snapshot']['name'] ?? '' ) . ' / '
							. ( $snapshot['customer_snapshot']['name'] ?? '' ) . ' / '
							. ( empty( $snapshot['cost'] ) ? '' : self::cost( $snapshot['cost'] ) )
						) . '</p><p>'
						. nl2br( esc_html( $snapshot['notes'] ?? '' ) ) . '</p>';
				}
				echo '</details>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $result['total'] / 50 );
		if ( $pages > 1 ) {
			echo '<nav aria-label="' . esc_attr__( 'History pages', 'velog' ) . '">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg(
							array_merge(
								$filters,
								array(
									'page'       => 'velog-history',
									'vehicle_id' => $vehicle,
									'paged'      => '%#%',
								)
							),
							admin_url( 'admin.php' )
						),
						'current' => $filters['page'],
						'total'   => $pages,
					)
				)
			) . '</nav>';
		}
		echo '</div>';
	}

	/**
	 * Format original currency using the current locale without conversion.
	 *
	 * @param array<string, mixed> $cost Historical cost tuple.
	 */
	private static function cost( array $cost ): string {
		global $wp_locale;
		return str_replace( '.', $wp_locale->number_format['decimal_point'] ?? '.', $cost['original_value'] ) . ' '
			. $cost['currency'];
	}
}
