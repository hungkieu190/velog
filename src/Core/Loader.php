<?php
/**
 * VeLog Hook/Filter Loader
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
 * Maintains and registers all hooks and filters for the plugin.
 *
 * Maintains a list of all hooks and filters registered with WordPress
 * and registers them with the WordPress API once run() is called.
 *
 * @since 0.1.0
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $filters = array();

	/**
	 * Registers an action hook.
	 *
	 * @since  0.1.0
	 * @param  string $hook          The name of the WordPress action hook.
	 * @param  object $component     The component that handles the hook.
	 * @param  string $callback      The callback method name.
	 * @param  int    $priority      The hook priority. Default 10.
	 * @param  int    $accepted_args Number of accepted arguments. Default 1.
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add(
			$this->actions,
			$hook,
			$component,
			$callback,
			$priority,
			$accepted_args
		);
	}

	/**
	 * Registers a filter hook.
	 *
	 * @since  0.1.0
	 * @param  string $hook          The name of the WordPress filter hook.
	 * @param  object $component     The component that handles the hook.
	 * @param  string $callback      The callback method name.
	 * @param  int    $priority      The hook priority. Default 10.
	 * @param  int    $accepted_args Number of accepted arguments. Default 1.
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add(
			$this->filters,
			$hook,
			$component,
			$callback,
			$priority,
			$accepted_args
		);
	}

	/**
	 * Stores a hook entry in the registry.
	 *
	 * @since  0.1.0
	 * @param  array<int, array<string, mixed>> $hooks         The existing hooks.
	 * @param  string                           $hook          Hook name.
	 * @param  object                           $component     Component object.
	 * @param  string                           $callback      Callback method.
	 * @param  int                              $priority      Priority.
	 * @param  int                              $accepted_args Argument count.
	 * @return array<int, array<string, mixed>>
	 */
	private function add(
		array $hooks,
		string $hook,
		object $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Registers all hooks and filters with WordPress.
	 *
	 * @since 0.1.0
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			$cb       = array( $hook['component'], $hook['callback'] );
			$callable = \Closure::fromCallable( $cb ); // @phpstan-ignore-line
			add_filter(
				$hook['hook'],
				$callable,
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			$cb       = array( $hook['component'], $hook['callback'] );
			$callable = \Closure::fromCallable( $cb ); // @phpstan-ignore-line
			add_action(
				$hook['hook'],
				$callable,
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
