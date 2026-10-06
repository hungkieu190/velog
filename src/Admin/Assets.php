<?php
/**
 * Admin Assets
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues admin assets.
 */
class Assets {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Plugin hook loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_styles' );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook_suffix Hook suffix for the current admin page.
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		// Only enqueue on exact VeLog pages (F-003).
		$allowed_hooks = array( 'toplevel_page_velog', 'velog_page_velog-customers', 'velog_page_velog-settings' );
		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'velog-admin',
			plugin_dir_url( VELOG_PLUGIN_FILE ) . 'assets/css/admin.css',
			array(),
			$this->version
		);
	}
}
