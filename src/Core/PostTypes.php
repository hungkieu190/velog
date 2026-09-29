<?php
/**
 * PostTypes class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Core;

use MF\VeLog\Common\Storage\RecordSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles private post type registrations and authoritative meta registration.
 */
class PostTypes {

	/**
	 * Registers custom post types and their authoritative meta key.
	 *
	 * Called on `init`. Must run before any repository operation so that the
	 * meta key is registered with WordPress before REST/REST-adjacent access
	 * is possible.
	 */
	public function register_post_types(): void {
		$types = array(
			'mf_velog_customer' => __( 'VeLog Customer', 'velog' ),
			'mf_velog_vehicle'  => __( 'VeLog Vehicle', 'velog' ),
			'mf_velog_service'  => __( 'VeLog Service', 'velog' ),
			'mf_velog_reminder' => __( 'VeLog Reminder', 'velog' ),
		);

		// Supply a complete native post capability array — all blocked; repository
		// enforces CORE-003 capabilities independently.
		$capabilities = array(
			'edit_post'              => 'do_not_allow',
			'read_post'              => 'do_not_allow',
			'delete_post'            => 'do_not_allow',
			'edit_posts'             => 'do_not_allow',
			'edit_others_posts'      => 'do_not_allow',
			'publish_posts'          => 'do_not_allow',
			'read_private_posts'     => 'do_not_allow',
			'create_posts'           => 'do_not_allow',
			'delete_posts'           => 'do_not_allow',
			'delete_private_posts'   => 'do_not_allow',
			'delete_published_posts' => 'do_not_allow',
			'delete_others_posts'    => 'do_not_allow',
			'edit_private_posts'     => 'do_not_allow',
			'edit_published_posts'   => 'do_not_allow',
		);

		foreach ( $types as $slug => $label ) {
			$args = array(
				'label'               => $label,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'exclude_from_search' => true,
				'supports'            => array(),
				'delete_with_user'    => false,
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
			);
			register_post_type( $slug, $args );
		}

		// Register the authoritative meta key for each private CPT.
		// protected=true, single=true, show_in_rest=false, deny-by-default auth callback.
		// Contract: Meta registration is protected, single, show_in_rest=false,
		// with a deny-by-default native auth callback. Repository authorization is separate.
		$this->register_record_meta( array_keys( $types ) );

		// Register and seal storage schemas.
		RecordSchema::register_core_schemas();
	}

	/**
	 * Registers _mf_velog_record meta for each private CPT.
	 *
	 * The native auth callback always returns false; repository enforces
	 * CORE-003 capabilities independently before any read/write.
	 *
	 * @param string[] $post_types List of CPT slugs.
	 */
	private function register_record_meta( array $post_types ): void {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}
		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				RecordSchema::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => static function (): bool {
						// Deny-by-default: repository is the only authorized writer.
						return false;
					},
					'sanitize_callback' => static function ( mixed $value ): string {
						// Accept only already-serialized strings; raw sanitization
						// is performed by the repository before storage.
						return is_string( $value ) ? $value : '';
					},
				)
			);
		}
	}
}
