<?php
/**
 * Private service taxonomy registration.
 *
 * @package MF\VeLog\Core
 */

namespace MF\VeLog\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native terms without public or alternate native mutation endpoints. */
final class ServiceTypes {

	/** Register a strictly private, service-only taxonomy. */
	public function register(): void {
		register_taxonomy(
			'mf_velog_service_type',
			'mf_velog_service',
			array(
				'label'              => __( 'Service types', 'velog' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_rest'       => false,
				'show_in_nav_menus'  => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capabilities'       => array(
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'do_not_allow',
				),
			)
		);
	}
}
