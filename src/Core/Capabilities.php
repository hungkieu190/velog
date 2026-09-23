<?php
/**
 * Capabilities class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles capability lifecycle.
 */
class Capabilities {

	const SCHEMA_VERSION = 1;

	const OPTION_SCHEMA_VERSION = 'mf_velog_capability_schema_version';
	const OPTION_ROLE_LEDGER    = 'mf_velog_role_ledger';

	/**
	 * Map of roles to their explicitly owned VeLog capabilities.
	 *
	 * @var array<string, array<string>>
	 */
	private static $role_caps = array(
		'administrator'       => array(
			'mf_velog_read_records',
			'mf_velog_manage_settings',
			'mf_velog_manage_customers',
			'mf_velog_read_customer_contacts',
			'mf_velog_manage_vehicles',
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
			'mf_velog_correct_services',
			'mf_velog_manage_reminders',
		),
		'mf_velog_manager'    => array(
			'mf_velog_read_records',
			'mf_velog_manage_settings',
			'mf_velog_manage_customers',
			'mf_velog_read_customer_contacts',
			'mf_velog_manage_vehicles',
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
			'mf_velog_correct_services',
			'mf_velog_manage_reminders',
		),
		'mf_velog_technician' => array(
			'mf_velog_read_records',
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
		),
	);

	/**
	 * Install capabilities and roles.
	 *
	 * @return bool True on success, false on failure or collision.
	 * @throws \Exception On role creation failure.
	 */
	public static function install(): bool {
		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return false; // Administrator must exist.
		}

		$ledger = get_option( self::OPTION_ROLE_LEDGER, array() );
		if ( ! is_array( $ledger ) ) {
			$ledger = array();
		}

		// Preflight collisions.
		$custom_roles = array( 'mf_velog_manager', 'mf_velog_technician' );
		foreach ( $custom_roles as $role_slug ) {
			if ( get_role( $role_slug ) && empty( $ledger[ $role_slug ] ) ) {
				// Foreign role exists with the same slug.
				return false;
			}
		}

		if ( ! function_exists( 'wp_roles' ) ) {
			require_once ABSPATH . 'wp-includes/capabilities.php';
		}
		wp_roles();

		try {
			$snap_schema = self::get_option_snapshot( self::OPTION_SCHEMA_VERSION );
			$snap_ledger = self::get_option_snapshot( self::OPTION_ROLE_LEDGER );
			$snap_roles  = self::get_option_snapshot( wp_roles()->role_key );
		} catch ( \Exception $e ) {
			return false; // Preflight snapshot read error.
		}

		$expected_roles = maybe_unserialize( $snap_roles['value'] );
		if ( ! is_array( $expected_roles ) ) {
			$expected_roles = array();
		}

		// Begin mutation.
		try {
			$roles_changed = false;
			foreach ( self::$role_caps as $role_slug => $caps ) {
				$role_obj = get_role( $role_slug );

				if ( ! $role_obj ) {
					if ( in_array( $role_slug, $custom_roles, true ) ) {
						$role_obj = add_role( $role_slug, self::get_role_name( $role_slug ), array( 'read' => true ) );
						if ( ! $role_obj ) {
							throw new \Exception( 'role_creation_failed' );
						}
						$expected_roles[ $role_slug ] = array(
							'name'         => self::get_role_name( $role_slug ),
							'capabilities' => array( 'read' => true ),
						);
						$ledger[ $role_slug ]         = true;
						$roles_changed                = true;
					} else {
						continue;
					}
				} else {
					if ( in_array( $role_slug, $custom_roles, true ) ) {
						$ledger[ $role_slug ] = true;
					}
					// Ensure 'read' is restored for owned roles if removed.
					if ( in_array( $role_slug, $custom_roles, true ) && ! $role_obj->has_cap( 'read' ) ) {
						$role_obj->add_cap( 'read' );
						$expected_roles[ $role_slug ]['capabilities']['read'] = true;
						$roles_changed                                        = true;
					}
				}

				foreach ( $caps as $cap ) {
					// We must check if the role lacks the capability, OR if it has it explicitly set to false.
					if ( ! isset( $role_obj->capabilities[ $cap ] ) || empty( $role_obj->capabilities[ $cap ] ) ) {
						$role_obj->add_cap( $cap );
						$expected_roles[ $role_slug ]['capabilities'][ $cap ] = true;
						$roles_changed                                        = true;
					}
				}
			}

			// Validate roles persistence if changed.
			if ( $roles_changed ) {
				$actual = self::get_option_snapshot( wp_roles()->role_key );
				if ( ! $actual['exists'] ) {
					throw new \Exception( 'role_persistence_failed: missing' );
				}
				$actual_roles = maybe_unserialize( $actual['value'] );

				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( serialize( $actual_roles ) !== serialize( $expected_roles ) ) {
					throw new \Exception( 'role_persistence_failed: structure mismatch' );
				}
			}

			update_option( self::OPTION_ROLE_LEDGER, $ledger, false );
			$actual_ledger      = self::get_option_snapshot( self::OPTION_ROLE_LEDGER );
			$check_ledger_value = maybe_unserialize( $actual_ledger['value'] );
			$ledger_mismatch    = $check_ledger_value !== $ledger;
			// phpcs:ignore Generic.Files.LineLength
			if ( $actual_ledger['exists'] && ! $ledger_mismatch && ! in_array( $actual_ledger['autoload'], array( 'no', 'off', '0', '' ), true ) ) {
				global $wpdb;
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => self::OPTION_ROLE_LEDGER )
				);
				wp_cache_delete( self::OPTION_ROLE_LEDGER, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$actual_ledger = self::get_option_snapshot( self::OPTION_ROLE_LEDGER );
			}
			// phpcs:ignore Generic.Files.LineLength
			if ( ! $actual_ledger['exists'] || $ledger_mismatch || ! in_array( $actual_ledger['autoload'], array( 'no', 'off', '0', '' ), true ) ) {
				throw new \Exception( 'ledger_persistence_failed' );
			}

			update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
			$actual_schema      = self::get_option_snapshot( self::OPTION_SCHEMA_VERSION );
			$check_schema_value = (int) maybe_unserialize( $actual_schema['value'] );
			$schema_mismatch    = self::SCHEMA_VERSION !== $check_schema_value;
			// phpcs:ignore Generic.Files.LineLength
			if ( $actual_schema['exists'] && ! $schema_mismatch && ! in_array( $actual_schema['autoload'], array( 'no', 'off', '0', '' ), true ) ) {
				global $wpdb;
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => self::OPTION_SCHEMA_VERSION )
				);
				wp_cache_delete( self::OPTION_SCHEMA_VERSION, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$actual_schema = self::get_option_snapshot( self::OPTION_SCHEMA_VERSION );
			}
			// phpcs:ignore Generic.Files.LineLength
			if ( ! $actual_schema['exists'] || $schema_mismatch || ! in_array( $actual_schema['autoload'], array( 'no', 'off', '0', '' ), true ) ) {
				throw new \Exception( 'schema_persistence_failed' );
			}

			return true;
		} catch ( \Exception $e ) {
			$restoration_failed = false;
			foreach ( array(
				wp_roles()->role_key        => $snap_roles,
				self::OPTION_ROLE_LEDGER    => $snap_ledger,
				self::OPTION_SCHEMA_VERSION => $snap_schema,
			) as $opt => $snap ) {
				try {
					self::restore_option( $opt, $snap );
				} catch ( \Exception $ex ) {
					$restoration_failed = true;
				}
			}

			// Verify restoration.
			try {
				$check_roles  = self::get_option_snapshot( wp_roles()->role_key );
				$check_ledger = self::get_option_snapshot( self::OPTION_ROLE_LEDGER );
				$check_schema = self::get_option_snapshot( self::OPTION_SCHEMA_VERSION );

				$roles_ok  = self::compare_snapshots( $snap_roles, $check_roles );
				$ledger_ok = self::compare_snapshots( $snap_ledger, $check_ledger );
				$schema_ok = self::compare_snapshots( $snap_schema, $check_schema );

				if ( ! $roles_ok || ! $ledger_ok || ! $schema_ok || $restoration_failed ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.Files.LineLength
					error_log( 'VeLog: Critical capabilities rollback failure. State mismatched snapshot.' );
				}
			} catch ( \Exception $ex ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.Files.LineLength
				error_log( 'VeLog: Critical capabilities rollback failure. State mismatched snapshot.' );
			}

			// Rebuild role state in memory.
			wp_roles()->for_site();

			try {
				$expected_reloaded = $snap_roles['exists'] ? maybe_unserialize( $snap_roles['value'] ) : array();
				if ( ! is_array( $expected_reloaded ) ) {
					$expected_reloaded = array();
				}
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( serialize( wp_roles()->roles ) !== serialize( $expected_reloaded ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.Files.LineLength
					error_log( 'VeLog: Critical capabilities rollback failure. State mismatched snapshot.' );
				}
			} catch ( \Exception $ex ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.Files.LineLength
				error_log( 'VeLog: Critical capabilities rollback failure. State mismatched snapshot.' );
			}

			return false;
		}
	}

	/**
	 * Compare two snapshots.
	 *
	 * @param array<string, mixed> $snap1 Snapshot 1.
	 * @param array<string, mixed> $snap2 Snapshot 2.
	 * @return bool
	 */
	private static function compare_snapshots( $snap1, $snap2 ): bool {
		if ( $snap1['exists'] !== $snap2['exists'] ) {
			return false;
		}
		if ( ! $snap1['exists'] ) {
			return true;
		}
		return $snap1['value'] === $snap2['value'] && $snap1['autoload'] === $snap2['autoload'];
	}

	/**
	 * Get human readable role name.
	 *
	 * @param string $slug Role slug.
	 * @return string
	 */
	private static function get_role_name( string $slug ): string {
		switch ( $slug ) {
			case 'mf_velog_manager':
				return __( 'VeLog Manager', 'velog' );
			case 'mf_velog_technician':
				return __( 'VeLog Technician', 'velog' );
			default:
				return $slug;
		}
	}

	/**
	 * Reads a direct snapshot of an option to bypass cache and filters.
	 *
	 * @param string $option_name The option name.
	 * @return array<string, mixed> Snapshot array with keys: exists, value, autoload.
	 * @throws \Exception If the read query fails.
	 */
	private static function get_option_snapshot( $option_name ) {
		global $wpdb;
		$wpdb->last_error = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option_name ) // phpcs:ignore Generic.Files.LineLength
		);
		if ( ! empty( $wpdb->last_error ) ) {
			throw new \Exception( 'snapshot_read_failed' );
		}

		if ( ! $row ) {
			return array(
				'exists'   => false,
				'value'    => null,
				'autoload' => 'yes',
			);
		}
		return array(
			'exists'   => true,
			'value'    => $row->option_value,
			'autoload' => $row->autoload,
		);
	}

	/**
	 * Restores an option exactly to its snapshot state.
	 *
	 * @param string               $option_name The option name.
	 * @param array<string, mixed> $snapshot    The snapshot array.
	 * @return void
	 */
	private static function restore_option( $option_name, $snapshot ) {
		global $wpdb;
		if ( $snapshot['exists'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$option_name
				)
			);
			if ( $exists ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->options,
					array(
						'option_value' => $snapshot['value'],
						'autoload'     => $snapshot['autoload'],
					),
					array( 'option_name' => $option_name )
				);
			} else {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->options,
					array(
						'option_name'  => $option_name,
						'option_value' => $snapshot['value'],
						'autoload'     => $snapshot['autoload'],
					)
				);
			}
		} else {
			delete_option( $option_name );
		}
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
