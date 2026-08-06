<?php
/**
 * Plugin Unit Test
 *
 * @package MF\VeLog\Tests\Unit
 * @author  Mamflow <https://mamflow.com>
 */

namespace MF\VeLog\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 *
 * Tests that core plugin constants are correctly defined.
 */
class PluginTest extends TestCase {

	/**
	 * Test that plugin version constant is defined.
	 */
	public function test_plugin_version_constant_is_defined(): void {
		$this->assertTrue( defined( 'VELOG_VERSION' ) );
		$this->assertSame( '0.1.0', VELOG_VERSION );
	}

	/**
	 * Test that text domain constant is defined.
	 */
	public function test_plugin_text_domain_constant_is_defined(): void {
		$this->assertTrue( defined( 'VELOG_TEXT_DOMAIN' ) );
		$this->assertSame( 'velog', VELOG_TEXT_DOMAIN );
	}

	/**
	 * Test that minimum PHP version constant is defined.
	 */
	public function test_minimum_php_version_constant_is_defined(): void {
		$this->assertTrue( defined( 'VELOG_MINIMUM_PHP_VERSION' ) );
		$this->assertSame( '8.1', VELOG_MINIMUM_PHP_VERSION );
	}

	/**
	 * Test that minimum WP version constant is defined.
	 */
	public function test_minimum_wp_version_constant_is_defined(): void {
		$this->assertTrue( defined( 'VELOG_MINIMUM_WP_VERSION' ) );
		$this->assertSame( '6.4', VELOG_MINIMUM_WP_VERSION );
	}
}
