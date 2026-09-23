<?php
/**
 * AccessPolicy class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure authorization service.
 */
class AccessPolicy {

	/**
	 * Authorize a trusted actor against an action and a trusted object context.
	 *
	 * @param \WP_User             $actor   The user requesting the action.
	 * @param string               $action  The specific capability/primitive.
	 * @param array<string, mixed> $context The object context.
	 * @return bool True if authorized, false otherwise.
	 */
	public static function authorize( \WP_User $actor, string $action, array $context ): bool {
		// Settings is outside object authorizer.
		if ( 'mf_velog_manage_settings' === $action ) {
			return false;
		}

		if ( empty( $context['type'] ) || ! is_string( $context['type'] ) ) {
			return false;
		}
		$type = $context['type'];

		$allowed_types = array( 'mf_velog_customer', 'mf_velog_vehicle', 'mf_velog_service', 'mf_velog_reminder' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return false;
		}

		if ( ! $actor->has_cap( $action ) ) {
			return false;
		}

		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return false;
		}

		// Bind actions to object type.
		if ( 'mf_velog_manage_customers' === $action && 'mf_velog_customer' !== $type ) {
			return false;
		}
		if ( 'mf_velog_read_customer_contacts' === $action ) {
			if ( 'mf_velog_customer' !== $type || ! $actor->has_cap( 'mf_velog_read_customer_contacts' ) ) {
				return false;
			}
		}
		if ( 'mf_velog_manage_vehicles' === $action && 'mf_velog_vehicle' !== $type ) {
			return false;
		}
		if ( 'mf_velog_manage_reminders' === $action && 'mf_velog_reminder' !== $type ) {
			return false;
		}

		// Validate action against known actions.
		$service_actions = array(
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
			'mf_velog_correct_services',
		);
		$known_actions   = array_merge(
			array(
				'mf_velog_read_records',
				'mf_velog_manage_customers',
				'mf_velog_read_customer_contacts',
				'mf_velog_manage_vehicles',
				'mf_velog_manage_reminders',
			),
			$service_actions
		);

		if ( ! in_array( $action, $known_actions, true ) ) {
			return false;
		}

		// Service object constraints apply to ALL actions on services (including read_records).
		if ( 'mf_velog_service' === $type ) {
			if ( empty( $context['state'] ) || ! in_array( $context['state'], array( 'draft', 'finalized' ), true ) ) {
				return false;
			}

			if ( empty( $context['author'] ) || ! is_int( $context['author'] ) || $context['author'] <= 0 ) {
				return false;
			}

			if ( ! isset( $context['vehicle_visible'] ) || true !== $context['vehicle_visible'] ) {
				return false;
			}

			// Service mutation-specific rules.
			if ( in_array( $action, $service_actions, true ) ) {
				if ( 'mf_velog_correct_services' === $action && 'finalized' !== $context['state'] ) {
					return false;
				}

				$own_draft_actions = array( 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' );
				if ( in_array( $action, $own_draft_actions, true ) ) {
					if ( 'draft' !== $context['state'] || (int) $actor->ID !== $context['author'] ) {
						return false;
					}
				}

				if ( 'mf_velog_create_services' === $action ) {
					if ( (int) $actor->ID !== $context['author'] || 'draft' !== $context['state'] ) {
						return false;
					}
				}
			}
		} elseif ( in_array( $action, $service_actions, true ) ) {
			// If it's not a service, then a service-specific action is not allowed.
			return false;
		}

		return true;
	}
}
