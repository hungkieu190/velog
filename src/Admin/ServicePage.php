<?php
/**
 * Native service workflow administration.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Service\ServiceQuery;
use MF\VeLog\Common\Service\ServiceService;
use MF\VeLog\Common\Service\ServiceTypes;
use MF\VeLog\Common\Vehicle\OdometerPolicy;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Generic.Files.LineLength.TooLong -- Native WordPress form markup uses the existing admin convention.

/** Secured native draft/finalize/correct forms and operational listing. */
final class ServicePage {

	/**
	 * Register POST handler.
	 *
	 * @param Loader $loader Project hook registry.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_mf_velog_service_save', $this, 'handle_post' );
	}

	/**
	 * Read an unslashed scalar; strict domain validation follows.
	 *
	 * @param string $key  Trusted field key.
	 * @param bool   $post True for POST, false for read-only navigation.
	 */
	public static function value( string $key, bool $post = true ): string {
		// phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Explicit nonce/capability/domain checks apply at each caller.
		$input = $post ? $_POST : $_GET;
		if ( ! isset( $input[ $key ] ) ) {
			return '';
		}
		return is_string( $input[ $key ] ) && strlen( $input[ $key ] ) <= 40000 ? wp_unslash( $input[ $key ] ) : "\x00";
	}

	/** Process one POST through the same authoritative domain boundary. */
	public function handle_post(): void {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! current_user_can( 'mf_velog_create_services' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$command   = explode( ':', self::value( 'service_command' ), 2 );
		$operation = '' !== $command[0] ? $command[0] : self::value( 'service_action' );
		$nonce     = $command[1] ?? self::value( 'service_nonce' );
		$raw_id    = self::value( 'service_id' );
		$id        = VehiclePage::positive_int( $raw_id );
		$version   = VehiclePage::positive_int( self::value( 'record_version' ) );
		if ( ! in_array( $operation, array( 'create', 'save', 'finalize', 'correct' ), true ) || ( 'create' === $operation ? '0' !== $raw_id : $id < 1 || $version < 1 ) ) {
			$this->redirect( 'invalid_input' );
		}
		if ( ! wp_verify_nonce( $nonce, 'mf_velog_service_' . $operation . '_' . $id ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$request_id = self::value( 'request_id' );
		if ( ! wp_is_uuid( $request_id, 4 ) ) {
			$this->redirect( 'invalid_input', $id );
		}
		$settings = ShopSettings::require_configured();
		if ( is_wp_error( $settings ) ) {
			$this->redirect( 'settings_required', $id );
		}
		if ( ! wp_verify_nonce( self::value( 'settings_context' ), 'mf_velog_service_settings_' . $settings['distance_unit'] . '_' . $settings['currency_code'] ) ) {
			$this->redirect( 'settings_changed', $id );
		}
		$actor  = wp_get_current_user();
		$before = $id > 0 ? ( new ServiceQuery() )->get( $id, $actor ) : null;
		if ( is_wp_error( $before ) ) {
			$this->redirect( 'forbidden' );
		}
		$input = array();
		foreach ( array( 'vehicle_id', 'service_date', 'reading', 'service_type_id', 'notes', 'cost' ) as $key ) {
			$input[ $key ] = self::value( $key );
		}
		$fields  = self::parse_fields( $input, $settings, $before['fields'] ?? array(), self::separator() );
		$service = new ServiceService();
		$result  = is_wp_error( $fields ) ? $fields : ( 'create' === $operation
			? $service->create( $fields, $actor, $request_id )
			: $service->change( $id, $version, $fields, $operation, $actor, $request_id, self::value( 'reason' ) ) );
		$this->redirect( is_wp_error( $result ) ? (string) $result->get_error_code() : 'saved', is_wp_error( $result ) ? $id : $result['id'] );
	}

	/**
	 * Parse a global-unit form without reinterpreting historical tuples.
	 *
	 * @param array<string, string> $input    Submitted fields.
	 * @param array<string, mixed>  $settings Trusted shop snapshot.
	 * @param array<string, mixed>  $before   Authorized prior fields.
	 * @param string                $separator Locale separator.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_fields( array $input, array $settings, array $before, string $separator ): array|\WP_Error {
		$vehicle = VehiclePage::positive_int( $input['vehicle_id'] ?? '' );
		$type    = '' === ( $input['service_type_id'] ?? '' ) ? 0 : VehiclePage::positive_int( $input['service_type_id'] );
		if ( $vehicle < 1 || ( '' !== ( $input['service_type_id'] ?? '' ) && $type < 1 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$fields = array(
			'vehicle_id'      => $vehicle,
			'service_type_id' => $type,
			'service_date'    => $input['service_date'] ?? '',
			'notes'           => $input['notes'] ?? '',
			'reading'         => array(),
			'cost'            => array(),
		);
		try {
			if ( '' !== ( $input['reading'] ?? '' ) ) {
				$old               = $before['reading'] ?? array();
				$baseline          = empty( $old ) ? array() : array_merge( $old, array( 'date' => $before['service_date'] ?? '' ) );
				$fields['reading'] = OdometerPolicy::parse_reading( $input['reading'], $fields['service_date'], $settings['distance_unit'], $separator, $baseline );
				unset( $fields['reading']['date'] );
			}
			if ( '' !== ( $input['cost'] ?? '' ) ) {
				$fields['cost'] = Money::parse( $input['cost'], $before['cost']['currency'] ?? $settings['currency_code'], $separator );
			}
		} catch ( \InvalidArgumentException $exception ) {
			return new \WP_Error( 'invalid_input' );
		}
		return $fields;
	}

	/** Render staff-only native list, detail and permitted actions. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_read_records' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap velog-services"><h1>' . esc_html__( 'Services', 'velog' ) . '</h1>';
		$notice = self::value( 'service_notice', false );
		if ( '' !== $notice ) {
			self::notice( $notice );
		}
		$query    = new ServiceQuery();
		$actor    = wp_get_current_user();
		$raw      = self::value( 'service_id', false );
		$id       = VehiclePage::positive_int( $raw );
		$item     = '' === $raw ? null : ( $id > 0 ? $query->get( $id, $actor ) : new \WP_Error( 'invalid_input' ) );
		$settings = ShopSettings::require_configured();
		if ( is_wp_error( $item ) ) {
			self::notice( 'forbidden' );
		} elseif ( is_wp_error( $settings ) ) {
			self::notice( 'settings_required' );
		} elseif ( null !== $item && 'finalized' === $item['state'] && ! current_user_can( 'mf_velog_correct_services' ) ) {
			self::detail( $item, $settings );
		} elseif ( current_user_can( 'mf_velog_create_services' ) ) {
			$this->form( $item, $settings );
		}
		$filter  = self::value( 'vehicle_id', false );
		$vehicle = '' === $filter ? 0 : VehiclePage::positive_int( $filter );
		$state   = self::value( 'state', false );
		$page    = self::value( 'paged', false );
		echo '<form method="get"><input type="hidden" name="page" value="velog-services">';
		echo '<p><label for="service-filter-vehicle">' . esc_html__( 'Filter vehicle ID', 'velog' ) . '</label> <input id="service-filter-vehicle" class="small-text" name="vehicle_id" value="' . esc_attr( $filter ) . '"></p>';
		echo '<label for="service-filter-state">' . esc_html__( 'State', 'velog' ) . '</label> <select id="service-filter-state" name="state">';
		foreach ( array(
			''          => __( 'All states', 'velog' ),
			'draft'     => __( 'Draft', 'velog' ),
			'finalized' => __( 'Finalized', 'velog' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $state, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'velog' ), 'secondary', '', false );
		echo '</form>';
		$result = '' !== $filter && 0 === $vehicle ? new \WP_Error( 'invalid_input' ) : $query->search( $actor, $state, $vehicle, '' === $page ? 1 : VehiclePage::positive_int( $page ) );
		if ( is_wp_error( $result ) ) {
			self::notice( 'invalid_input' );
		} else {
			$this->table( $result, is_wp_error( $settings ) ? '' : $settings['distance_unit'] );
			$pages = (int) ceil( $result['total'] / 50 );
			if ( $pages > 1 ) {
				echo '<nav aria-label="' . esc_attr__( 'Service pages', 'velog' ) . '">' . wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg(
								array(
									'page'       => 'velog-services',
									'vehicle_id' => $vehicle,
									'state'      => $state,
									'paged'      => '%#%',
								),
								admin_url( 'admin.php' )
							),
							'current' => $result['page'],
							'total'   => $pages,
						)
					)
				) . '</nav>';
			}
		}
		if ( $vehicle > 0 ) {
			echo '<p><a href="' . esc_url(
				add_query_arg(
					array(
						'page'       => 'velog-history',
						'vehicle_id' => $vehicle,
					),
					admin_url( 'admin.php' )
				)
			) . '">' . esc_html__( 'View finalized history and corrections', 'velog' ) . '</a></p>';
		}
		if ( $vehicle > 0 && ! is_wp_error( $settings ) ) {
			$current = $query->current( $vehicle, $actor );
			if ( is_wp_error( $current ) ) {
				self::notice( 'storage_unavailable' );
			} else {
				echo '<p>' . esc_html__( 'Current odometer:', 'velog' ) . ' ' . esc_html( empty( $current ) ? __( 'Unknown', 'velog' ) : self::reading_label( $current['reading'], $current['date'], $settings['distance_unit'] ) ) . '</p>';
			}
		}
		echo '</div>';
	}

	/**
	 * Render one native labeled input in a paragraph.
	 *
	 * @param string $key   Field name.
	 * @param string $label Translated label.
	 * @param string $value Display value.
	 * @param string $type  Trusted HTML type.
	 */
	public static function input( string $key, string $label, string $value, string $type = 'text' ): void {
		echo '<p><label for="velog-service-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><input class="regular-text" id="velog-service-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"></p>';
	}

	/**
	 * Render create/draft/correction fields and action-specific submit tokens.
	 *
	 * @param array<string, mixed>|null $item     Authorized service or create mode.
	 * @param array<string, mixed>      $settings Trusted settings snapshot.
	 */
	private function form( ?array $item, array $settings ): void {
		$fields = $item['fields'] ?? array();
		$id     = $item['id'] ?? 0;
		$final  = 'finalized' === ( $item['state'] ?? '' );
		if ( $final && null !== $item ) {
			self::detail( $item, $settings );
		}
		$catalog = ServiceTypes::all( wp_get_current_user() );
		if ( is_wp_error( $catalog ) ) {
			self::notice( 'storage_unavailable' );
			return;
		}
		echo '<h2>' . esc_html( $final ? __( 'Correct finalized service', 'velog' ) : ( $id > 0 ? __( 'Edit draft', 'velog' ) : __( 'New service draft', 'velog' ) ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mf_velog_service_save"><input type="hidden" name="service_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="record_version" value="' . esc_attr( (string) ( $item['record_version'] ?? 0 ) ) . '"><input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		wp_nonce_field( 'mf_velog_service_settings_' . $settings['distance_unit'] . '_' . $settings['currency_code'], 'settings_context', false );
		self::input( 'vehicle_id', __( 'Vehicle record ID', 'velog' ), (string) ( $fields['vehicle_id'] ?? '' ) );
		self::input( 'service_date', __( 'Service date', 'velog' ), $fields['service_date'] ?? '', 'date' );
		self::input( 'reading', sprintf( /* translators: %s: system distance unit. */ __( 'Odometer (%s, system setting)', 'velog' ), $settings['distance_unit'] ), '' === OdometerPolicy::display_value( $fields['reading'] ?? array(), $settings['distance_unit'] ) ? '' : str_replace( '.', self::separator(), OdometerPolicy::display_value( $fields['reading'], $settings['distance_unit'] ) ) );
		echo '<p><label for="velog-service-type">' . esc_html__( 'Service type', 'velog' ) . '</label><br><select id="velog-service-type" name="service_type_id"><option value="">' . esc_html__( 'Select an active service type', 'velog' ) . '</option>';
		foreach ( $catalog as $type ) {
			if ( 'active' === $type['state'] || ( $fields['service_type_id'] ?? 0 ) === $type['id'] ) {
				echo '<option value="' . esc_attr( (string) $type['id'] ) . '"' . selected( $fields['service_type_id'] ?? 0, $type['id'], false ) . '>' . esc_html( $final && $type['id'] === $fields['service_type_id'] ? $fields['type_snapshot']['name'] : $type['name'] ) . '</option>';
			}
		}
		echo '</select></p><p class="description">' . esc_html__( 'A type must be active when finalizing. Blank readings remain unknown; a date and reading are required to finalize.', 'velog' ) . '</p>';
		self::input( 'cost', sprintf( /* translators: %s: preserved or current currency. */ __( 'Optional cost (%s)', 'velog' ), $fields['cost']['currency'] ?? $settings['currency_code'] ), str_replace( '.', self::separator(), $fields['cost']['original_value'] ?? '' ) );
		echo '<p class="description">' . esc_html__( 'Existing costs retain their original currency when Settings changes. No currency conversion is performed.', 'velog' ) . '</p><p><label for="velog-service-notes">' . esc_html__( 'Notes', 'velog' ) . '</label><br><textarea class="large-text" id="velog-service-notes" name="notes" rows="4" maxlength="10000">' . esc_textarea( $fields['notes'] ?? '' ) . '</textarea></p>';
		self::input( 'reason', __( 'Manager correction / odometer exception reason', 'velog' ), '' );
		$operations = $final ? array( 'correct' => __( 'Save correction', 'velog' ) ) : ( $id > 0 ? array(
			'save'     => __( 'Save draft', 'velog' ),
			'finalize' => __( 'Finalize service', 'velog' ),
		) : array( 'create' => __( 'Create draft', 'velog' ) ) );
		echo '<p class="description">' . esc_html__( 'Finalization records the current customer and service type permanently. Later changes require a manager correction and reason.', 'velog' ) . '</p>';
		foreach ( $operations as $operation => $label ) {
			// Button value carries the action and its separate object-bound nonce without JavaScript.
			echo '<button class="button button-primary" type="submit" name="service_command" value="' . esc_attr( $operation . ':' . wp_create_nonce( 'mf_velog_service_' . $operation . '_' . $id ) ) . '">' . esc_html( $label ) . '</button> ';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-services' ) ) . '">' . esc_html__( 'New draft / cancel', 'velog' ) . '</a></form>';
	}

	/**
	 * Render a historical name-only service snapshot.
	 *
	 * @param array<string, mixed> $item     Authorized service.
	 * @param array<string, mixed> $settings Trusted settings.
	 */
	private static function detail( array $item, array $settings ): void {
		$fields = $item['fields'];
		echo '<h2>' . esc_html__( 'Finalized service', 'velog' ) . '</h2><p>' . esc_html( (string) $item['id'] . ' / ' . ( $fields['customer_snapshot']['name'] ?? '' ) . ' / ' . ( $fields['type_snapshot']['name'] ?? '' ) ) . '</p><p>' . esc_html( self::reading_label( $fields['reading'] ?? array(), $fields['service_date'] ?? '', $settings['distance_unit'] ) ) . '</p><p>' . esc_html( ( empty( $fields['cost'] ) ? __( 'Cost not recorded', 'velog' ) : str_replace( '.', self::separator(), $fields['cost']['original_value'] ) . ' ' . $fields['cost']['currency'] ) ) . '</p><p>' . nl2br( esc_html( $fields['notes'] ?? '' ) ) . '</p>';
		if ( current_user_can( 'mf_velog_correct_services' ) ) {
			echo '<details><summary>' . esc_html__( 'Audit history', 'velog' ) . '</summary><pre>' . esc_html( (string) wp_json_encode( $item['audit'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
		}
	}

	/**
	 * Render native operational table, keeping every row action permission checked.
	 *
	 * @param array<string, mixed> $result Authorized page.
	 * @param string               $unit   System unit, empty if unavailable.
	 */
	private function table( array $result, string $unit ): void {
		echo '<div role="region" tabindex="0" class="velog-service-table-wrap" aria-label="' . esc_attr__( 'Service records', 'velog' ) . '"><table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'ID', 'velog' ), __( 'Vehicle', 'velog' ), __( 'State', 'velog' ), __( 'Date / odometer', 'velog' ), __( 'Historical customer / type', 'velog' ), __( 'Action', 'velog' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No services found.', 'velog' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			$f = $item['fields'];
			echo '<tr><td>' . esc_html( (string) $item['id'] ) . '</td><td>' . esc_html( (string) $f['vehicle_id'] ) . '</td><td>' . esc_html( 'draft' === $item['state'] ? __( 'Draft', 'velog' ) : __( 'Finalized', 'velog' ) ) . '</td><td>' . esc_html( self::reading_label( $f['reading'] ?? array(), $f['service_date'] ?? '', $unit ) ) . '</td><td>' . esc_html( ( $f['customer_snapshot']['name'] ?? '' ) . ' / ' . ( $f['type_snapshot']['name'] ?? '' ) ) . '</td><td><a href="' . esc_url(
				add_query_arg(
					array(
						'page'       => 'velog-services',
						'service_id' => $item['id'],
					),
					admin_url( 'admin.php' )
				)
			) . '">' . esc_html__( 'Open', 'velog' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Format a reading in the current system unit.
	 *
	 * @param array<string, string> $reading Canonical reading.
	 * @param string                $date    Reading date.
	 * @param string                $unit    System unit.
	 */
	public static function reading_label( array $reading, string $date, string $unit ): string {
		return VehiclePage::baseline_label( empty( $reading ) ? array() : array_merge( $reading, array( 'date' => $date ) ), $unit );
	}

	/** Current WordPress decimal separator. */
	private static function separator(): string {
		global $wp_locale;
		return $wp_locale->number_format['decimal_point'] ?? '.';
	}

	/**
	 * Fixed translated notices without diagnostic information.
	 *
	 * @param string $code Allowlisted outcome.
	 */
	public static function notice( string $code ): void {
		$messages = array(
			'saved'               => __( 'Service data saved.', 'velog' ),
			'stale_version'       => __( 'This record changed. Reload before editing again.', 'velog' ),
			'settings_required'   => __( 'Configure VeLog Settings before entering service data.', 'velog' ),
			'settings_changed'    => __( 'Settings changed. Reload and enter amounts in the displayed units.', 'velog' ),
			'reason_required'     => __( 'A manager and correction reason are required.', 'velog' ),
			'chronology_conflict' => __( 'The reading conflicts with chronological neighbours. A manager must supply an exception reason.', 'velog' ),
			'incomplete_service'  => __( 'Finalization requires a date, reading, active service type and active customer.', 'velog' ),
			'invalid_customer'    => __( 'The vehicle must link to an active private customer before finalization.', 'velog' ),
			'invalid_vehicle'     => __( 'Choose an active vehicle. Archived vehicles cannot receive draft changes.', 'velog' ),
			'invalid_type'        => __( 'Select an active service type before finalization or type correction.', 'velog' ),
		);
		echo '<div role="status" class="notice ' . ( 'saved' === $code ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $messages[ $code ] ?? __( 'The request was rejected or the record is unavailable. Reload, check the fields and permissions, then retry.', 'velog' ) ) . '</p></div>';
	}

	/**
	 * Redirect only fixed codes and IDs.
	 *
	 * @param string $code Outcome code.
	 * @param int    $id   Optional detail ID.
	 */
	private function redirect( string $code, int $id = 0 ): never {
		$codes = array( 'saved', 'stale_version', 'settings_required', 'settings_changed', 'reason_required', 'chronology_conflict', 'incomplete_service', 'invalid_customer', 'invalid_vehicle', 'invalid_type' );
		$args  = array(
			'page'           => 'velog-services',
			'service_notice' => in_array( $code, $codes, true ) ? $code : 'invalid_input',
		);
		if ( $id > 0 ) {
			$args['service_id'] = $id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
