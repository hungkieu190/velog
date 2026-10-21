<?php
/**
 * Native manager-only manual maintenance queue.
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Reminder\ReminderQuery;
use MF\VeLog\Common\Reminder\ReminderService;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Vehicle\OdometerPolicy;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Date/distance OR reminders without scheduling or delivery side effects. */
final class ReminderPage {

	/**
	 * Register the secured POST handler.
	 *
	 * @param Loader $loader Hook registry.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_mf_velog_reminder_save', $this, 'handle_post' );
	}

	/** Process an allowlisted versioned manager action. */
	public function handle_post(): void {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! current_user_can( 'mf_velog_manage_reminders' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$command   = explode( ':', ServicePage::value( 'reminder_command' ), 2 );
		$operation = $command[0];
		$raw_id    = ServicePage::value( 'reminder_id' );
		$id        = VehiclePage::positive_int( $raw_id );
		$version   = VehiclePage::positive_int( ServicePage::value( 'record_version' ) );
		if ( ! in_array( $operation, array( 'create', 'edit', 'snooze', 'resume', 'complete' ), true ) || ( 'create' === $operation ? '0' !== $raw_id : $id < 1 || $version < 1 ) ) {
			$this->redirect( 'invalid_input' );
		}
		if ( ! wp_verify_nonce( $command[1] ?? '', 'mf_velog_reminder_' . $operation . '_' . $id ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$request_id = ServicePage::value( 'request_id' );
		if ( ! wp_is_uuid( $request_id, 4 ) ) {
			$this->redirect( 'invalid_input', $id );
		}
		$actor  = wp_get_current_user();
		$before = $id > 0 ? RecordRepository::get( 'mf_velog_reminder', $id, $actor ) : null;
		if ( is_wp_error( $before ) ) {
			$this->redirect( 'invalid_input' );
		}
		$service = new ReminderService();
		$fields  = array();
		if ( in_array( $operation, array( 'create', 'edit' ), true ) ) {
			$settings = ShopSettings::require_configured();
			if ( is_wp_error( $settings ) || ! wp_verify_nonce( ServicePage::value( 'settings_context' ), 'mf_velog_reminder_unit_' . $settings['distance_unit'] ) ) {
				$this->redirect( 'settings_changed', $id );
			}
			$vehicle = VehiclePage::positive_int( ServicePage::value( 'vehicle_id' ) );
			if ( $vehicle < 1 ) {
				$this->redirect( 'invalid_input', $id );
			}
			$fields = array(
				'vehicle_id' => $vehicle,
				'title'      => ServicePage::value( 'title' ),
				'due_date'   => ServicePage::value( 'due_date' ),
				'threshold'  => array(),
			);
			$value  = ServicePage::value( 'threshold' );
			if ( '' !== $value ) {
				$old = $before['fields']['threshold'] ?? array();
				try {
					$threshold = OdometerPolicy::parse_reading( $value, '2000-01-01', $settings['distance_unit'], self::separator(), empty( $old ) ? array() : array_merge( $old, array( 'date' => '2000-01-01' ) ) );
					unset( $threshold['date'] );
					$fields['threshold'] = $threshold;
				} catch ( \InvalidArgumentException $exception ) {
					$this->redirect( 'invalid_input', $id );
				}
			}
		} elseif ( 'snooze' === $operation ) {
			$fields['snooze_until'] = ServicePage::value( 'snooze_until' );
		}
		$result = 'create' === $operation ? $service->create( $fields, $actor, $request_id ) : $service->change( $id, $version, $operation, $fields, $actor, $request_id );
		$this->redirect( is_wp_error( $result ) ? (string) $result->get_error_code() : 'saved', is_wp_error( $result ) ? $id : $result['id'] );
	}

	/** Render permitted reminder inputs, effective filters and a bounded queue. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_manage_reminders' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap velog-reminders"><h1>' . esc_html__( 'Maintenance reminders', 'velog' ) . '</h1>';
		$notice = ServicePage::value( 'reminder_notice', false );
		if ( '' !== $notice ) {
			self::notice( $notice );
		}
		$settings = ShopSettings::require_configured();
		$unit     = is_wp_error( $settings ) ? '' : $settings['distance_unit'];
		$raw_id   = ServicePage::value( 'reminder_id', false );
		$id       = VehiclePage::positive_int( $raw_id );
		$item     = '' === $raw_id ? null : ( $id > 0 ? RecordRepository::get( 'mf_velog_reminder', $id, wp_get_current_user() ) : new \WP_Error( 'invalid_input' ) );
		if ( is_wp_error( $item ) ) {
			self::notice( 'invalid_input' );
		} elseif ( is_wp_error( $settings ) ) {
			self::notice( 'settings_changed' );
		} else {
			$this->form( $item, $unit );
		}
		$state = ServicePage::value( 'state', false );
		// No filter means due; explicit "all" selects all effective states.
		$state       = '' === $state ? 'due' : ( 'all' === $state ? '' : $state );
		$raw_vehicle = ServicePage::value( 'filter_vehicle', false );
		$vehicle     = '' === $raw_vehicle ? 0 : VehiclePage::positive_int( $raw_vehicle );
		$raw_page    = ServicePage::value( 'paged', false );
		$page        = '' === $raw_page ? 1 : VehiclePage::positive_int( $raw_page );
		echo '<form method="get"><input type="hidden" name="page" value="velog-reminders"><p><label for="reminder-state">' . esc_html__( 'Queue state', 'velog' ) . '</label> <select id="reminder-state" name="state">';
		foreach ( array(
			'all'       => __( 'All states', 'velog' ),
			'due'       => __( 'Due', 'velog' ),
			'upcoming'  => __( 'Upcoming', 'velog' ),
			'snoozed'   => __( 'Snoozed', 'velog' ),
			'suspended' => __( 'Suspended (archived vehicle)', 'velog' ),
			'completed' => __( 'Completed', 'velog' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( '' === $state ? 'all' : $state, $key, false )
				. '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		ServicePage::input( 'filter_vehicle', __( 'Filter vehicle ID', 'velog' ), $raw_vehicle );
		submit_button( __( 'Filter queue', 'velog' ), 'secondary', '', false );
		echo '</form><p class="description">'
			. esc_html__( 'A reminder is due when either its date or recorded odometer threshold is reached. Unknown odometers cannot satisfy distance thresholds. Snooze suppresses the reminder until its date. No notifications are sent.', 'velog' ) . '</p>';
		$result = '' !== $raw_vehicle
			&& 0 === $vehicle ? new \WP_Error( 'invalid_input' ) : ( new ReminderQuery() )->search( wp_get_current_user(), $state, $vehicle, $page );
		if ( is_wp_error( $result ) ) {
			self::notice( 'storage_unavailable' );
		} else {
			$this->table( $result, $unit );
			$pages = (int) ceil( $result['total'] / 50 );
			if ( $pages > 1 ) {
				echo '<nav aria-label="' . esc_attr__( 'Reminder pages', 'velog' ) . '">' . wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg(
								array(
									'page'           => 'velog-reminders',
									'state'          => '' === $state ? 'all' : $state,
									'filter_vehicle' => $vehicle,
									'paged'          => '%#%',
								),
								admin_url( 'admin.php' )
							),
							'current' => $page,
							'total'   => $pages,
						)
					)
				) . '</nav>';
			}
		}
		echo '</div>';
	}

	/**
	 * Render create/edit/snooze/complete controls with independent action tokens.
	 *
	 * @param array<string, mixed>|null $item Authorized reminder or create mode.
	 * @param string                    $unit System distance unit.
	 */
	private function form( ?array $item, string $unit ): void {
		$id = $item['id'] ?? 0;
		$f  = $item['fields'] ?? array();
		if ( 'completed' === ( $item['state'] ?? '' ) ) {
			echo '<p>' . esc_html__( 'This reminder is completed and cannot be reopened. Create another reminder if needed.', 'velog' ) . '</p><p>' . esc_html( $f['title'] . ' / #' . $f['completed_by'] . ' / ' . $f['completed_at_utc'] ) . '</p>';
			return;
		}
		echo '<h2>' . esc_html( $id > 0 ? __( 'Edit reminder', 'velog' ) : __( 'New reminder', 'velog' ) )
			. '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) )
			. '"><input type="hidden" name="action" value="mf_velog_reminder_save"><input type="hidden" name="reminder_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="record_version" value="' . esc_attr( (string) ( $item['record_version'] ?? 0 ) ) . '"><input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		wp_nonce_field( 'mf_velog_reminder_unit_' . $unit, 'settings_context', false );
		ServicePage::input( 'vehicle_id', __( 'Vehicle record ID (unchanged after creation)', 'velog' ), (string) ( $f['vehicle_id'] ?? '' ) );
		ServicePage::input( 'title', __( 'Reminder title', 'velog' ), $f['title'] ?? '' );
		ServicePage::input( 'due_date', __( 'Due date (optional)', 'velog' ), $f['due_date'] ?? '', 'date' );
		ServicePage::input( 'threshold', sprintf( /* translators: %s: system distance unit. */ __( 'Odometer threshold (%s, optional)', 'velog' ), $unit ), str_replace( '.', self::separator(), OdometerPolicy::display_value( $f['threshold'] ?? array(), $unit ) ) );
		echo '<p class="description">'
			. esc_html__( 'Enter at least one trigger. Past dates and thresholds already reached are allowed and appear as due. A blank threshold is unknown, not zero.', 'velog' ) . '</p>';
		if ( $id > 0 ) {
			ServicePage::input( 'snooze_until', __( 'Snooze until a future date', 'velog' ), $f['snooze_until'] ?? '', 'date' );
		}
		$operations = $id > 0 ? array(
			'edit'     => __( 'Save reminder', 'velog' ),
			'snooze'   => __( 'Snooze', 'velog' ),
			'resume'   => __( 'Resume', 'velog' ),
			'complete' => __( 'Complete reminder', 'velog' ),
		) : array( 'create' => __( 'Create reminder', 'velog' ) );
		foreach ( $operations as $operation => $label ) {
			echo '<button class="button ' . ( in_array( $operation, array( 'create', 'edit' ), true ) ? 'button-primary' : '' ) . '" type="submit" name="reminder_command" value="'
				. esc_attr( $operation . ':' . wp_create_nonce( 'mf_velog_reminder_' . $operation . '_' . $id ) ) . '">'
				. esc_html( $label ) . '</button> ';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-reminders' ) ) . '">'
			. esc_html__( 'New reminder / cancel', 'velog' ) . '</a></form>';
	}

	/**
	 * Output effective-state rows without per-reminder vehicle queries.
	 *
	 * @param array<string, mixed> $result Authorized queue.
	 * @param string               $unit   System unit, empty if not configured.
	 */
	private function table( array $result, string $unit ): void {
		echo '<div class="velog-service-table-wrap" role="region" tabindex="0" aria-label="'
			. esc_attr__( 'Maintenance queue', 'velog' ) . '"><table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Reminder', 'velog' ), __( 'Vehicle', 'velog' ), __( 'Due date', 'velog' ), __( 'Threshold / current reading', 'velog' ), __( 'State / action', 'velog' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No reminders match this queue.', 'velog' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			$f         = $item['fields'];
			$threshold = empty( $f['threshold'] ) ? __( 'Not set', 'velog' ) : ( '' === $unit ? __( 'Configure distance unit', 'velog' ) : str_replace( '.', self::separator(), OdometerPolicy::display_value( $f['threshold'], $unit ) ) . ' ' . $unit );
			$current   = null === $item['current_mm'] ? __( 'Unknown odometer', 'velog' ) : ( '' === $unit ? __( 'Configure distance unit', 'velog' ) : str_replace( '.', self::separator(), Distance::to_decimal( $item['current_mm'], $unit ) ) . ' ' . $unit );
			$states    = array(
				'due'       => __( 'Due', 'velog' ),
				'upcoming'  => __( 'Upcoming', 'velog' ),
				'snoozed'   => __( 'Snoozed', 'velog' ),
				'completed' => __( 'Completed', 'velog' ),
				'suspended' => __( 'Suspended', 'velog' ),
			);
			echo '<tr><td>' . esc_html( $f['title'] ) . '</td><td>#' . esc_html( (string) $f['vehicle_id'] )
				. '</td><td>' . esc_html( $f['due_date'] ) . '</td><td>' . esc_html( $threshold . ' / ' . $current )
				. '</td><td>' . esc_html( $states[ $item['effective'] ] ) . '<br><a href="' . esc_url(
					add_query_arg(
						array(
							'page'        => 'velog-reminders',
							'reminder_id' => $item['id'],
							'state'       => 'all',
						),
						admin_url( 'admin.php' )
					)
				) . '">' . esc_html__( 'Open', 'velog' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div><p>'
			. esc_html( sprintf( /* translators: %d: number of matching reminders. */ __( '%d matching reminders', 'velog' ), $result['total'] ) ) . '</p>';
	}

	/** Current WordPress decimal separator. */
	private static function separator(): string {
		global $wp_locale;
		return $wp_locale->number_format['decimal_point'] ?? '.';
	}

	/**
	 * Display only fixed, translated messages.
	 *
	 * @param string $code Outcome code.
	 */
	private static function notice( string $code ): void {
		$messages = array(
			'saved'               => __( 'Reminder saved. A trigger already reached appears in the due queue.', 'velog' ),
			'stale_version'       => __( 'This reminder changed. Reload before editing again.', 'velog' ),
			'invalid_state'       => __( 'Completed reminders cannot be changed, or the requested transition is unavailable.', 'velog' ),
			'settings_changed'    => __( 'Configure Settings or reload after a distance-unit change before entering a threshold.', 'velog' ),
			'storage_unavailable' => __( 'The queue could not be read consistently. No empty result is assumed; reload or ask an administrator.', 'velog' ),
			'invalid_input'       => __( 'Review the vehicle, title and triggers. Snooze requires a future date; completed reminders stay read-only.', 'velog' ),
		);
		echo '<div role="status" class="notice ' . ( 'saved' === $code ? 'notice-success' : 'notice-error' )
			. '"><p>' . esc_html( $messages[ $code ] ?? $messages['invalid_input'] ) . '</p></div>';
	}

	/**
	 * Redirect with fixed codes only and no sensitive input in the URL.
	 *
	 * @param string $code Outcome code.
	 * @param int    $id   Optional reminder identity.
	 */
	private function redirect( string $code, int $id = 0 ): never {
		$codes = array( 'saved', 'stale_version', 'invalid_state', 'settings_changed', 'storage_unavailable', 'invalid_input' );
		$args  = array(
			'page'            => 'velog-reminders',
			'state'           => 'all',
			'reminder_notice' => in_array( $code, $codes, true ) ? $code : 'invalid_input',
		);
		if ( $id > 0 ) {
			$args['reminder_id'] = $id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
