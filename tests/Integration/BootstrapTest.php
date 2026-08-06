<?php
/**
 * Plugin Integration Test
 *
 * @package MF\VeLog\Tests\Integration
 * @author  Mamflow <https://mamflow.com>
 */

namespace MF\VeLog\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MF\VeLog\Core\Plugin;

/**
 * Class BootstrapTest
 *
 * Tests singleton instance instantiation.
 */
class BootstrapTest extends TestCase {

	/**
	 * Test that Plugin::get_instance() returns a Plugin instance.
	 */
	public function test_plugin_instance_can_be_retrieved(): void {
		$instance = Plugin::get_instance();
		$this->assertInstanceOf( Plugin::class, $instance );
		$this->assertSame( VELOG_VERSION, $instance->get_version() );
	}
}
