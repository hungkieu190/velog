<?php
/**
 * Native vehicle administration and secured POST boundary.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

// phpcs:disable Generic.Files.LineLength.TooLong -- Native HTML markup follows the existing admin view convention.

use MF\VeLog\Common\Vehicle\OdometerPolicy;
use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Vehicle\VehicleQuery;
use MF\VeLog\Common\Vehicle\VehicleService;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides manager mutations and staff read-only operational views. */
final class VehiclePage {

	/**
	 * Register named handlers through the project loader.
	 *
	 * @param Loader $loader Hook registry.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_mf_velog_vehicle_save', $this, 'handle_post' );
	}

	/** Render authorized vehicle list and optional detail. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_read_records' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$query = new VehicleQuery();
		$actor = wp_get_current_user();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, strictly validated navigation.
		$id_raw = self::navigation_text( 'vehicle_id', '' );
		$id     = self::positive_int( $id_raw );
		$record = '' === $id_raw ? null : ( $id > 0 ? $query->get( $id, $actor ) : new \WP_Error( 'invalid_input' ) );
		$input  = VehicleQuery::normalize_args(
			array(
				'term'  => self::navigation_text( 'term', '' ),
				'state' => self::navigation_text( 'state', 'active' ),
				'page'  => self::positive_int( self::navigation_text( 'paged', '1' ) ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap velog-vehicles"><h1>' . esc_html__( 'Vehicles', 'velog' ) . '</h1>';
		$this->render_notice();
		if ( is_wp_error( $record ) ) {
			$this->notice( 'invalid_input' );
		} elseif ( current_user_can( 'mf_velog_manage_vehicles' ) ) {
			$this->render_form( $record );
		} elseif ( is_array( $record ) ) {
			$this->render_detail( $record );
		}
		$this->render_search( is_wp_error( $input ) ? array() : $input );
		$result = is_wp_error( $input ) ? $input : $query->search( $input, $actor );
		if ( is_wp_error( $result ) ) {
			$this->notice( (string) $result->get_error_code() );
		} else {
			( new VehicleListTable() )->render( $result );
			$this->render_pagination( $result, is_wp_error( $input ) ? array() : $input );
		}
		echo '</div>';
	}

	/** Process a POST with exact actor, action/object nonce, UUID and version. */
	public function handle_post(): void {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! current_user_can( 'mf_velog_manage_vehicles' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$operation = self::posted_text( 'vehicle_action' );
		$raw_id    = self::posted_text( 'vehicle_id' );
		$id        = self::positive_int( $raw_id );
		if ( ! is_string( $operation ) || ! in_array( $operation, array( 'create', 'update', 'archive', 'restore' ), true )
			|| ( 'create' === $operation ? '0' !== $raw_id : $id < 1 ) ) {
			$this->redirect( 'invalid_input' );
		}
		$nonce = self::posted_text( 'mf_velog_vehicle_nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'mf_velog_vehicle_' . $operation . '_' . $id ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$request_id = self::posted_text( 'request_id' );
		$version    = self::positive_int( self::posted_text( 'record_version' ) );
		if ( ! is_string( $request_id ) || ! wp_is_uuid( $request_id, 4 ) || ( 'create' !== $operation && $version < 1 ) ) {
			$this->redirect( 'invalid_input', $id );
		}
		$service = new VehicleService();
		$actor   = wp_get_current_user();
		if ( in_array( $operation, array( 'archive', 'restore' ), true ) ) {
			$result = $service->transition( $id, $version, 'archive' === $operation ? 'archived' : 'active', $actor, $request_id );
		} else {
			$values = array();
			foreach ( array_merge( array_keys( self::field_labels() ), array( 'baseline_value', 'baseline_date', 'reason' ) ) as $key ) {
				$values[ $key ] = self::posted_text( $key );
			}
			$settings = ShopSettings::require_configured();
			$context  = self::posted_text( 'distance_context' );
			if ( is_wp_error( $settings ) ) {
				$this->redirect( 'settings_required', $id );
			}
			if ( ! is_string( $context ) || ! wp_verify_nonce( $context, 'mf_velog_vehicle_unit_' . $settings['distance_unit'] ) ) {
				// Do not relabel an attempted reading after another request changed Settings.
				delete_transient( 'mf_velog_vehicle_attempt_' . $actor->ID );
				$this->redirect( 'settings_changed', $id );
			}
			$existing = 'update' === $operation ? ( new VehicleQuery() )->get( $id, $actor ) : null;
			if ( is_wp_error( $existing ) ) {
				$this->redirect( 'invalid_input', $id );
			}
			$fields = self::parse_fields( $values, self::decimal_separator(), $settings['distance_unit'], $existing['fields']['baseline'] ?? array() );
			if ( is_wp_error( $fields ) ) {
				$result = $fields;
			} elseif ( 'create' === $operation ) {
				$result = $service->create( $fields, $actor, $request_id );
			} else {
				$result = $service->update( $id, $version, $fields, $actor, $request_id, is_string( $values['reason'] ) ? $values['reason'] : '' );
			}
			if ( is_wp_error( $result ) ) {
				// Safe attempted values remain private to this actor and never enter navigation URLs.
				set_transient(
					'mf_velog_vehicle_attempt_' . $actor->ID,
					array(
						'id'     => $id,
						'values' => $values,
						'unit'   => $settings['distance_unit'],
					),
					5 * MINUTE_IN_SECONDS
				);
			}
		}
		$this->redirect( is_wp_error( $result ) ? (string) $result->get_error_code() : 'saved', $id );
	}

	/**
	 * Convert validated scalar form shapes into canonical domain fields.
	 *
	 * @param array<string, mixed>  $input     Submitted form fields.
	 * @param string                $separator Locale decimal separator.
	 * @param string                $unit      Trusted system unit.
	 * @param array<string, string> $baseline  Authorized stored baseline.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_fields( array $input, string $separator, string $unit, array $baseline = array() ): array|\WP_Error {
		if ( null === OdometerPolicy::validate_unit( $unit ) ) {
			return new \WP_Error( 'invalid_input', 'Invalid system distance unit.' );
		}
		foreach ( $input as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 2000 ) {
				return new \WP_Error( 'invalid_input', 'Invalid vehicle form shape.' );
			}
		}
		$fields = array();
		foreach ( array_keys( self::field_labels() ) as $key ) {
			$fields[ $key ] = $input[ $key ] ?? '';
		}
		$owner = $fields['current_customer_id'];
		if ( '' !== $owner && self::positive_int( $owner ) < 1 ) {
			return new \WP_Error( 'invalid_input', 'Invalid customer ID.' );
		}
		$fields['current_customer_id'] = '' === $owner ? '' : self::positive_int( $owner );
		$fields['baseline']            = array();
		$value                         = $input['baseline_value'] ?? '';
		$date                          = $input['baseline_date'] ?? '';
		if ( '' !== $value ) {
			try {
				$fields['baseline'] = OdometerPolicy::parse_reading( $value, $date, $unit, $separator, $baseline );
			} catch ( \InvalidArgumentException $exception ) {
				return new \WP_Error( 'invalid_input', 'Invalid baseline distance.' );
			}
		} elseif ( '' !== $date ) {
			return new \WP_Error( 'invalid_input', 'A baseline date requires a reading.' );
		}
		return $fields;
	}

	/**
	 * Strictly parse a positive canonical integer without coercing malformed values.
	 *
	 * @param mixed $value Submitted scalar.
	 */
	public static function positive_int( mixed $value ): int {
		if ( ! is_string( $value ) || strlen( $value ) > 18 || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return 0;
		}
		return (int) $value;
	}

	/**
	 * Fixed translated field labels; no dynamic gettext calls.
	 *
	 * @return array<string, string>
	 */
	private static function field_labels(): array {
		return array(
			'jurisdiction'        => __( 'Plate jurisdiction', 'velog' ),
			'plate'               => __( 'Plate', 'velog' ),
			'vin'                 => __( 'VIN', 'velog' ),
			'year'                => __( 'Year', 'velog' ),
			'make'                => __( 'Make', 'velog' ),
			'model'               => __( 'Model', 'velog' ),
			'color'               => __( 'Color', 'velog' ),
			'current_customer_id' => __( 'Customer record ID', 'velog' ),
		);
	}

	/**
	 * Render native create/edit form with original baseline meaning.
	 *
	 * @param array<string, mixed>|null $record Existing record or create mode.
	 */
	private function render_form( ?array $record ): void {
		$fields  = $record['fields'] ?? array();
		$id      = (int) ( $record['id'] ?? 0 );
		$version = (int) ( $record['record_version'] ?? 0 );
		$attempt = get_transient( 'mf_velog_vehicle_attempt_' . get_current_user_id() );
		$values  = is_array( $attempt ) && ( $attempt['id'] ?? null ) === $id ? (array) $attempt['values'] : array();
		delete_transient( 'mf_velog_vehicle_attempt_' . get_current_user_id() );
		$baseline = $fields['baseline'] ?? array();
		$settings = ShopSettings::require_configured();
		if ( is_wp_error( $settings ) ) {
			$this->notice( 'settings_required' );
			return;
		}
		$unit = $settings['distance_unit'];
		// A settings switch also invalidates values retained after a failed POST.
		if ( is_array( $attempt ) && ( $attempt['unit'] ?? '' ) !== $unit ) {
			$values = array();
		}
		echo '<form class="velog-vehicle-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::action_fields( $id > 0 ? 'update' : 'create', $id, $version );
		wp_nonce_field( 'mf_velog_vehicle_unit_' . $unit, 'distance_context', false );
		echo '<p id="velog-vehicle-identity-help">' . esc_html__( 'Enter a plate with its jurisdiction, or a VIN. Customer IDs refer to private customer records, not WordPress accounts.', 'velog' ) . '</p>';
		if ( ! empty( $record['customer'] ) ) {
			echo '<p>' . esc_html__( 'Current customer:', 'velog' ) . ' ' . esc_html( (string) $record['customer']['name'] ) . ' (#' . esc_html( (string) $record['customer']['id'] ) . ')</p>';
		}
		echo '<table class="form-table" role="presentation">';
		foreach ( self::field_labels() as $key => $label ) {
			$value = (string) ( $values[ $key ] ?? $fields[ $key ] ?? '' );
			if ( in_array( $key, array( 'year', 'current_customer_id' ), true ) && '0' === $value ) {
				$value = '';
			}
			if ( 'year' === $key ) {
				$this->year_row( $value );
				continue;
			}
			$limit = in_array( $key, array( 'plate', 'vin', 'color' ), true ) ? 64 : 100;
			$this->input_row( $key, $label, $value, 'text', $limit, 'velog-vehicle-identity-help' );
		}
		echo '<tr><th scope="row">' . esc_html__( 'Odometer unit', 'velog' ) . '</th><td>' . esc_html( $unit ) . '<p class="description">' . esc_html__( 'Uses the system distance unit from VeLog Settings for every vehicle.', 'velog' ) . '</p></td></tr>';
		$this->input_row( 'baseline_value', __( 'Initial odometer (blank means unknown)', 'velog' ), (string) ( $values['baseline_value'] ?? str_replace( '.', self::decimal_separator(), OdometerPolicy::display_value( $baseline, $unit ) ) ), 'text', 32 );
		$this->input_row( 'baseline_date', __( 'Initial reading date', 'velog' ), (string) ( $values['baseline_date'] ?? $baseline['date'] ?? '' ), 'date', 10 );
		$this->input_row( 'reason', __( 'Correction reason (required to decrease or clear a known reading)', 'velog' ), (string) ( $values['reason'] ?? '' ), 'text', 500 );
		echo '</table>';
		submit_button( $id > 0 ? __( 'Update Vehicle', 'velog' ) : __( 'Create Vehicle', 'velog' ) );
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-vehicles' ) ) . '">' . esc_html__( 'New vehicle / cancel', 'velog' ) . '</a></form>';
	}

	/**
	 * Output one escaped labelled native field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Translated label.
	 * @param string $value Safe-to-escape display value.
	 * @param string $type  Trusted input type.
	 * @param int    $limit Maximum input length.
	 * @param string $help  Optional description element ID.
	 */
	private function input_row( string $key, string $label, string $value, string $type, int $limit, string $help = '' ): void {
		echo '<tr><th scope="row"><label for="velog-vehicle-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input class="regular-text" id="velog-vehicle-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" maxlength="' . esc_attr( (string) $limit ) . '" value="' . esc_attr( $value ) . '" aria-describedby="' . esc_attr( $help ) . '"></td></tr>';
	}

	/**
	 * Render the optional native year dropdown in descending site-calendar order.
	 *
	 * @param string $value Selected year or empty sentinel.
	 */
	private function year_row( string $value ): void {
		echo '<tr><th scope="row"><label for="velog-vehicle-year">' . esc_html__( 'Year', 'velog' ) . '</label></th><td><select id="velog-vehicle-year" name="year"><option value="">' . esc_html__( 'Not specified', 'velog' ) . '</option>';
		for ( $year = (int) wp_date( 'Y' ) + 1; $year >= 1886; --$year ) {
			echo '<option value="' . esc_attr( (string) $year ) . '"' . selected( $value, (string) $year, false ) . '>' . esc_html( (string) $year ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Format a historical reading using only the current system unit and locale.
	 *
	 * @param array<string, string> $baseline Stored baseline.
	 * @param string                $unit     Trusted system unit, empty if unavailable.
	 */
	public static function baseline_label( array $baseline, string $unit ): string {
		if ( empty( $baseline ) ) {
			return __( 'Unknown', 'velog' );
		}
		if ( null === OdometerPolicy::validate_unit( $unit ) ) {
			return __( 'Unavailable: configure the system distance unit in VeLog Settings.', 'velog' );
		}
		return str_replace( '.', self::decimal_separator(), OdometerPolicy::display_value( $baseline, $unit ) ) . ' ' . $unit . ' / ' . $baseline['date'];
	}

	/**
	 * Render staff-only detail without edit controls or customer contacts.
	 *
	 * @param array<string, mixed> $record Authorized vehicle.
	 */
	private function render_detail( array $record ): void {
		echo '<h2>' . esc_html__( 'Vehicle detail', 'velog' ) . '</h2><dl class="velog-vehicle-detail">';
		foreach ( self::field_labels() as $key => $label ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) ( $record['fields'][ $key ] ?? '' ) ) . '</dd>';
		}
		$baseline = $record['fields']['baseline'] ?? array();
		$settings = ShopSettings::require_configured();
		echo '<dt>' . esc_html__( 'Initial odometer', 'velog' ) . '</dt><dd>' . esc_html( self::baseline_label( $baseline, is_wp_error( $settings ) ? '' : $settings['distance_unit'] ) ) . '</dd>';
		echo '<dt>' . esc_html__( 'Customer', 'velog' ) . '</dt><dd>' . esc_html( (string) ( $record['customer']['name'] ?? '' ) ) . '</dd></dl>';
	}

	/**
	 * Render a native bounded search form.
	 *
	 * @param array<string, mixed> $input Validated criteria.
	 */
	private function render_search( array $input ): void {
		echo '<form method="get" class="velog-vehicle-search" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="velog-vehicles">';
		echo '<label for="velog-vehicle-term">' . esc_html__( 'Search plate, VIN or jurisdiction', 'velog' ) . '</label> <input type="text" id="velog-vehicle-term" name="term" maxlength="100" value="' . esc_attr( (string) ( $input['term'] ?? '' ) ) . '"> ';
		echo '<label for="velog-vehicle-state">' . esc_html__( 'State', 'velog' ) . '</label> <select id="velog-vehicle-state" name="state">';
		foreach ( array(
			''         => __( 'All states', 'velog' ),
			'active'   => __( 'Active', 'velog' ),
			'archived' => __( 'Archived', 'velog' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $input['state'] ?? 'active', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Search', 'velog' ), 'secondary', 'submit', false );
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-vehicles' ) ) . '">' . esc_html__( 'Clear search', 'velog' ) . '</a></form>';
	}

	/**
	 * Output pagination based solely on validated search arguments.
	 *
	 * @param array<string, mixed> $result Query result.
	 * @param array<string, mixed> $input  Validated criteria.
	 */
	private function render_pagination( array $result, array $input ): void {
		$total_pages = (int) ceil( $result['total'] / 50 );
		if ( $total_pages <= 1 ) {
			return;
		}
		$url = add_query_arg(
			array(
				'page'  => 'velog-vehicles',
				'term'  => $input['term'],
				'state' => $input['state'],
				'paged' => '%#%',
			),
			admin_url( 'admin.php' )
		);
		echo '<nav class="tablenav-pages velog-vehicle-pagination" aria-label="' . esc_attr__( 'Vehicle pages', 'velog' ) . '">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => $url,
					'current' => $result['page'],
					'total'   => $total_pages,
				)
			)
		);
		echo '</nav>';
	}

	/**
	 * Output stable nonce and replay fields for one action.
	 *
	 * @param string $operation Allowlisted action.
	 * @param int    $id        Vehicle ID (zero for create).
	 * @param int    $version   Expected version.
	 */
	public static function action_fields( string $operation, int $id, int $version ): void {
		echo '<input type="hidden" name="action" value="mf_velog_vehicle_save"><input type="hidden" name="vehicle_action" value="' . esc_attr( $operation ) . '">';
		echo '<input type="hidden" name="vehicle_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="record_version" value="' . esc_attr( (string) $version ) . '"><input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		wp_nonce_field( 'mf_velog_vehicle_' . $operation . '_' . $id, 'mf_velog_vehicle_nonce' );
	}

	/**
	 * Read an exact unslashed scalar for later domain validation.
	 *
	 * @param string $key Trusted form key.
	 * @return string|array<mixed>
	 */
	private static function posted_text( string $key ): string|array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- action/object nonce and strict domain validation are enforced by handle_post.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
	}

	// phpcs:disable WordPress.Security.NonceVerification -- Helpers below only read validated navigation/message codes.

	/**
	 * Read unslashed navigation text; VehicleQuery validates exact shape/content.
	 *
	 * @param string $key     Trusted query key.
	 * @param string $fallback Default for absent argument.
	 * @return string|array<mixed>
	 */
	private static function navigation_text( string $key, string $fallback ): string|array {
		// phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only domain validation follows immediately.
		return isset( $_GET[ $key ] ) ? ( is_string( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : array() ) : $fallback;
	}

	/** Read the current locale's decimal separator. */
	private static function decimal_separator(): string {
		global $wp_locale;
		return isset( $wp_locale->number_format['decimal_point'] ) ? $wp_locale->number_format['decimal_point'] : '.';
	}

	/** Render only fixed safe notice messages, never raw storage diagnostics. */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification -- read-only allowlisted message code.
		$code = isset( $_GET['velog_notice'] ) && is_string( $_GET['velog_notice'] ) ? sanitize_key( wp_unslash( $_GET['velog_notice'] ) ) : '';
		if ( '' !== $code ) {
			$this->notice( $code );
		}
	}

	// phpcs:enable WordPress.Security.NonceVerification

	/**
	 * Output a fixed translated status message.
	 *
	 * @param string $code Result code (never rendered directly).
	 */
	private function notice( string $code ): void {
		$messages = array(
			'settings_required'      => __( 'Configure the system distance unit in VeLog Settings before editing vehicles.', 'velog' ),
			'settings_changed'       => __( 'The system distance unit changed. Reload the vehicle and enter the reading again in the displayed unit.', 'velog' ),
			'saved'                  => __( 'Vehicle saved.', 'velog' ),
			'invalid_input'          => __( 'Invalid vehicle data or unavailable record. Review the fields and try again.', 'velog' ),
			'conflict'               => __( 'This identifier is already reserved by another vehicle.', 'velog' ),
			'stale_version'          => __( 'This vehicle changed in another request. Reload before editing again.', 'velog' ),
			'invalid_customer'       => __( 'Select an existing active private customer record.', 'velog' ),
			'chronology_conflict'    => __( 'The reading conflicts with service history. Review the date and reading, or enter a manager correction reason.', 'velog' ),
			'reason_required'        => __( 'Enter a correction reason before decreasing or clearing a known reading.', 'velog' ),
			'service_history_locked' => __( 'Finalized service history requires the service correction workflow.', 'velog' ),
		);
		$message  = $messages[ $code ] ?? __( 'The operation could not be completed. Reload and retry, or contact an administrator.', 'velog' );
		echo '<div role="status" class="notice ' . ( 'saved' === $code ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirect without diagnostic text or contact values in the URL.
	 *
	 * @param string $code Result code.
	 * @param int    $id   Optional detail ID.
	 */
	private function redirect( string $code, int $id = 0 ): never {
		$allowed = array( 'settings_required', 'settings_changed', 'saved', 'invalid_input', 'conflict', 'stale_version', 'invalid_customer', 'chronology_conflict', 'reason_required', 'service_history_locked' );
		$args    = array(
			'page'         => 'velog-vehicles',
			'velog_notice' => in_array( $code, $allowed, true ) ? $code : 'unavailable',
		);
		if ( $id > 0 ) {
			$args['vehicle_id'] = $id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
