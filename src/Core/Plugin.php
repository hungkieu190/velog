<?php
/**
 * VeLog Core Plugin
 *
 * @package MF\VeLog\Core
 * @author  Mamflow <https://mamflow.com>
 * @license GPL-2.0-or-later
 */

namespace MF\VeLog\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — singleton bootstrap.
 *
 * Responsible for:
 * - Instantiating the Loader.
 * - Loading text domain.
 * - Registering all hooks and filters via the Loader.
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether the Loader has already been run.
	 *
	 * Guards against duplicate hook registration on repeated run() calls.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private bool $ran = false;

	/**
	 * Hook/filter registry.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Plugin constructor — private to enforce singleton.
	 */
	private function __construct() {
		$this->version = VELOG_VERSION;
		$this->loader  = new Loader();

		$this->load_dependencies();
		$this->set_locale();
		$this->define_core_hooks();
		$this->define_admin_hooks();
		$this->define_frontend_hooks();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @since  0.1.0
	 * @return Plugin
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevents cloning of the singleton.
	 */
	private function __clone() {}

	/**
	 * Loads required dependencies.
	 *
	 * @since 0.1.0
	 */
	private function load_dependencies(): void {
		// TODO: Instantiate Admin, Frontend, and API modules here.
	}

	/**
	 * Loads the plugin text domain for i18n.
	 *
	 * Queues the text-domain registration on `init` rather than
	 * `plugins_loaded` so that WordPress's locale infrastructure is ready
	 * and a WP >= 6.7 early-translation warning is not triggered.
	 *
	 * @since 0.1.0
	 */
	private function set_locale(): void {
		$this->loader->add_action(
			'init',
			$this,
			'load_plugin_textdomain'
		);
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * Registered on `init` by {@see Plugin::set_locale()}.
	 *
	 * The third argument must be a path relative to WP_PLUGIN_DIR; WordPress
	 * prepends WP_PLUGIN_DIR internally. VELOG_PLUGIN_BASENAME resolves to
	 * `velog/velog.php`, so dirname() yields `velog` and the full path
	 * becomes `<wp-content>/plugins/velog/languages`.
	 *
	 * @since 0.1.0
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			VELOG_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( VELOG_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Registers core hooks.
	 *
	 * @since 0.1.0
	 */
	private function define_core_hooks(): void {
		$post_types = new PostTypes();
		$this->loader->add_action( 'init', $post_types, 'register_post_types' );
	}

	/**
	 * Registers admin-side hooks.
	 *
	 * @since 0.1.0
	 */
	private function define_admin_hooks(): void {
		// TODO: Register admin hooks via $this->loader.
	}

	/**
	 * Registers frontend-side hooks.
	 *
	 * @since 0.1.0
	 */
	private function define_frontend_hooks(): void {
		// TODO: Register frontend hooks via $this->loader.
	}

	/**
	 * Runs the plugin by executing all registered hooks.
	 *
	 * Idempotent: repeated calls after the first are silently ignored so that
	 * external code cannot duplicate hook registration by calling run() more
	 * than once.
	 *
	 * @since 0.1.0
	 */
	public function run(): void {
		if ( $this->ran ) {
			return;
		}
		$this->ran = true;
		$this->loader->run();
	}

	/**
	 * Returns the plugin version.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Returns the Loader instance.
	 *
	 * @since  0.1.0
	 * @return Loader
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}
}
