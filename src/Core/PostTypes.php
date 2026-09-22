<?php
/**
 * PostTypes class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles private post type registrations.
 */
class PostTypes {

	/**
	 * Registers custom post types.
	 */
	public function register_post_types(): void {
		$types = array(
			'mf_velog_customer' => __( 'VeLog Customer', 'velog' ),
			'mf_velog_vehicle'  => __( 'VeLog Vehicle', 'velog' ),
			'mf_velog_service'  => __( 'VeLog Service', 'velog' ),
			'mf_velog_reminder' => __( 'VeLog Reminder', 'velog' ),
		);

		// Supply a complete native post capability array.
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
	}
}
