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
	 * @param \WP_User $actor   The user requesting the action.
	 * @param string   $action  The specific capability/primitive.
	 * @param array    $context The object context.
	 * @return bool True if authorized, false otherwise.
	 */
	public static function authorize( \WP_User $actor, string $action, array $context ): bool {
		if ( ! $actor->has_cap( $action ) ) {
			return false;
		}

		if ( empty( $context['type'] ) ) {
			return false;
		}

		$allowed_types = array( 'mf_velog_customer', 'mf_velog_vehicle', 'mf_velog_service', 'mf_velog_reminder' );
		if ( ! in_array( $context['type'], $allowed_types, true ) ) {
			return false;
		}

		if ( 'mf_velog_read_customer_contacts' === $action ) {
			if ( ! $actor->has_cap( 'mf_velog_read_customer_contacts' ) ) {
				return false;
			}
		}

		// Technician service mutation rules.
		// Technician mutation rules.
		$tech_mutations = array( 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' );
		if ( in_array( $action, $tech_mutations, true ) ) {
			if ( 'mf_velog_service' !== $context['type'] ) {
				return false;
			}
			if ( empty( $context['state'] ) || 'draft' !== $context['state'] ) {
				return false;
			}
			if ( empty( $context['author'] ) || (int) $actor->ID !== (int) $context['author'] ) {
				return false;
			}
		}

		return true;
	}
}
