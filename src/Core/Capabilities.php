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
	 * @var array
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

		// Snapshot state for rollback.
		if ( ! function_exists( 'wp_roles' ) ) {
			require_once ABSPATH . 'wp-includes/capabilities.php';
		}
		wp_roles();

		$snapshot          = array(
			'roles'  => array(), // Copies of roles before mutation.
			'ledger' => $ledger,
		);
		$roles_to_snapshot = array_merge( array( 'administrator' ), $custom_roles );
		foreach ( $roles_to_snapshot as $r_slug ) {
			$r_obj = get_role( $r_slug );
			if ( $r_obj ) {
				// Save an exact copy of the role's capabilities.
				$snapshot['roles'][ $r_slug ] = $r_obj->capabilities;
			} else {
				$snapshot['roles'][ $r_slug ] = false; // Didn't exist.
			}
		}

		// Begin mutation.
		try {
			foreach ( self::$role_caps as $role_slug => $caps ) {
				$role_obj = get_role( $role_slug );

				if ( ! $role_obj ) {
					if ( in_array( $role_slug, $custom_roles, true ) ) {
						// Create role with 'read' capability for basic dashboard access.
						$role_obj = add_role( $role_slug, self::get_role_name( $role_slug ), array( 'read' => true ) );
						if ( ! $role_obj ) {
							throw new \Exception( 'failed_to_create_role' );
						}
						$ledger[ $role_slug ] = true;
					} else {
						// Should never happen for admin since we checked.
						continue;
					}
				}

				foreach ( $caps as $cap ) {
					if ( ! $role_obj->has_cap( $cap ) ) {
						$role_obj->add_cap( $cap );
					}
				}
			}

			// Persist ledger.
			update_option( self::OPTION_ROLE_LEDGER, $ledger, false );

			// Persist schema version last.
			update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );

			return true;
		} catch ( \Exception $e ) {
			// Rollback.
			foreach ( $snapshot['roles'] as $r_slug => $old_caps ) {
				if ( false === $old_caps ) {
					// Role was created during this attempt, remove it.
					remove_role( $r_slug );
				} else {
					$r_obj = get_role( $r_slug );
					if ( $r_obj ) {
						// We must restore exact capabilities. The WP API only has add_cap/remove_cap.
						// Remove any newly added caps that were not in old_caps.
						$current_caps = $r_obj->capabilities;
						foreach ( $current_caps as $cap => $granted ) {
							if ( ! array_key_exists( $cap, $old_caps ) ) {
								$r_obj->remove_cap( $cap );
							}
						}
						// Technically we should restore granted flags if they changed, but add_cap only adds true.
					}
				}
			}

			// Re-save original ledger if it was changed (update_option might have run or not).
			if ( empty( $snapshot['ledger'] ) ) {
				delete_option( self::OPTION_ROLE_LEDGER );
			} else {
				update_option( self::OPTION_ROLE_LEDGER, $snapshot['ledger'], false );
			}

			return false;
		}
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
}
