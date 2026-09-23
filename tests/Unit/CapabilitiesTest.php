<?php
// phpcs:ignoreFile

/**
 * CapabilitiesTest class file.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Core\Capabilities;

/**
 * Tests for Capabilities.
 */
class CapabilitiesTest extends TestCase {

	/**
	 * In-memory storage for wpdb.
	 *
	 * @var array<string, mixed>
	 */
	private $db_store = array();

	/**
	 * Setup.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->db_store = array();
	}

	/**
	 * Teardown.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that install fails without admin.
	 */
	public function test_install_fails_without_admin() {
		Functions\expect( 'get_role' )->with( 'administrator' )->andReturn( null );
		$this->assertFalse( Capabilities::install() );
	}

	/**
	 * Setup wpdb and option mocks.
	 *
	 * @param bool $fail_snapshot Fail snapshot read.
	 * @param bool $fail_restore  Fail restore.
	 * @return \Mockery\MockInterface
	 */
	private function setup_wpdb_mock( $fail_snapshot = false, $fail_restore = false ) {
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb          = \Mockery::mock();
		$wpdb->options = 'wp_options';

		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, $opt ) {
				return array(
					'q'   => $query,
					'opt' => $opt,
				);
			}
		);

		$wpdb->shouldReceive( 'get_row' )->andReturnUsing(
			function ( $args ) use ( $wpdb, $fail_snapshot ) {
				if ( $fail_snapshot ) {
					$wpdb->last_error = 'db error';
					return null;
				}
				$wpdb->last_error = '';
				$opt              = $args['opt'];
				if ( isset( $this->db_store[ $opt ] ) ) {
					return (object) array(
						'option_value' => $this->db_store[ $opt ]['value'],
						'autoload'     => $this->db_store[ $opt ]['autoload'],
					);
				}
				return null;
			}
		);

		$wpdb->shouldReceive( 'get_var' )->andReturnUsing(
			function ( $args ) {
				$opt = $args['opt'];
				return isset( $this->db_store[ $opt ] ) ? $opt : null;
			}
		);

		$wpdb->shouldReceive( 'update' )->andReturnUsing(
			function ( $table, $data, $where ) use ( $fail_restore ) {
				if ( $fail_restore && 'mf_velog_role_ledger' === $where['option_name'] ) {
					throw new \Exception( 'DB Update Failed' );
				}
				$opt = $where['option_name'];
				if ( isset( $this->db_store[ $opt ] ) ) {
					if ( isset( $data['option_value'] ) ) {
						$this->db_store[ $opt ]['value'] = $data['option_value'];
					}
					if ( isset( $data['autoload'] ) ) {
						$this->db_store[ $opt ]['autoload'] = $data['autoload'];
					}
				}
				return 1;
			}
		);

		$wpdb->shouldReceive( 'insert' )->andReturnUsing(
			function ( $table, $data ) use ( $fail_restore ) {
				if ( $fail_restore && 'mf_velog_role_ledger' === $data['option_name'] ) {
					throw new \Exception( 'DB Insert Failed' );
				}
				$this->db_store[ $data['option_name'] ] = array(
					'value'    => $data['option_value'],
					'autoload' => $data['autoload'],
				);
				return 1;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $opt, $val, $autoload ) {
				if ( 'mf_velog_capability_schema_version' === $opt && -1 === $val ) {
					return false; // Simulate update failure for rollback test
				}
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				$this->db_store[ $opt ] = array(
					'value'    => serialize( $val ),
					'autoload' => 'yes',
				);
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( $opt ) {
				unset( $this->db_store[ $opt ] );
				return true;
			}
		);

		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'maybe_unserialize' )->alias(
			function ( $val ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				return @unserialize( $val );
			}
		);

		return $wpdb;
	}

	/**
	 * Test that install succeeds.
	 */
	public function test_install_success() {
		$admin               = \Mockery::mock();
		$admin->capabilities = array();
		$admin->shouldReceive( 'has_cap' )->andReturn( false );
		$admin->shouldReceive( 'add_cap' )->andReturnUsing(
			function ( $cap ) use ( &$admin ) {
				$admin->capabilities[ $cap ] = true;
				$roles                       = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles['administrator']['capabilities'][ $cap ] = true;
				$this->db_store['wp_user_roles']['value']       = serialize( $roles );
			}
		);

		Functions\when( 'get_role' )->alias(
			function ( $role ) use ( $admin ) {
				if ( 'administrator' === $role ) {
					return $admin;
				} return null;
			}
		);
		Functions\expect( 'get_option' )->with( Capabilities::OPTION_ROLE_LEDGER, array() )->andReturn( array() );
		$wp_roles_mock           = \Mockery::mock();
		$wp_roles_mock->role_key = 'wp_user_roles';
		$wp_roles_mock->roles    = array();
		$wp_roles_mock->shouldReceive( 'for_site' );
		Functions\expect( 'wp_roles' )->andReturn( $wp_roles_mock );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->db_store['wp_user_roles'] = array(
			'value'    => serialize( array() ),
			'autoload' => 'yes',
		);

		$this->setup_wpdb_mock();

		Functions\when( 'add_role' )->alias(
			function ( $role, $name, $caps ) use ( &$admin ) {
				$roles                                    = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles[ $role ]                           = array(
					'name'         => $name,
					'capabilities' => $caps,
				);
				$this->db_store['wp_user_roles']['value'] = serialize( $roles );
				$r                                        = \Mockery::mock();
				$r->capabilities                          = $caps;
				$r->shouldReceive( 'has_cap' )->andReturnUsing(
					function ( $c ) use ( $r ) {
						return isset( $r->capabilities[ $c ] );
					}
				);
				$r->shouldReceive( 'add_cap' )->andReturnUsing(
					function ( $c ) use ( $r, $role ) {
						$r->capabilities[ $c ]                    = true;
						$roles                                    = @unserialize( $this->db_store['wp_user_roles']['value'] );
						$roles[ $role ]['capabilities'][ $c ]     = true;
						$this->db_store['wp_user_roles']['value'] = serialize( $roles );
					}
				);
				return $r;
			}
		);
		Functions\expect( '__' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);

		$result = Capabilities::install(); if ( ! $result ) {
			echo 'Failed install
'; }
		$this->assertTrue( $result );
		$this->assertArrayHasKey( Capabilities::OPTION_SCHEMA_VERSION, $this->db_store );
		$this->assertArrayHasKey( Capabilities::OPTION_ROLE_LEDGER, $this->db_store );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['autoload'] );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_ROLE_LEDGER ]['autoload'] );
	}

	/**
	 * Test install rollback on DB error.
	 */
	public function test_install_rollback_on_db_error() {
		$admin               = \Mockery::mock();
		$admin->capabilities = array();
		$admin->shouldReceive( 'has_cap' )->andReturn( false );
		$admin->shouldReceive( 'add_cap' )->andReturnUsing(
			function ( $cap ) use ( &$admin ) {
				$admin->capabilities[ $cap ] = true;
				$roles                       = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles['administrator']['capabilities'][ $cap ] = true;
				$this->db_store['wp_user_roles']['value']       = serialize( $roles );
			}
		);

		Functions\when( 'get_role' )->alias(
			function ( $role ) use ( $admin ) {
				if ( 'administrator' === $role ) {
					return $admin;
				} return null;
			}
		);
		Functions\expect( 'get_option' )->with( Capabilities::OPTION_ROLE_LEDGER, array() )->andReturn( array() );
		$wp_roles_mock           = \Mockery::mock();
		$wp_roles_mock->role_key = 'wp_user_roles';
		$wp_roles_mock->roles    = array();
		$wp_roles_mock->shouldReceive( 'for_site' );
		Functions\expect( 'wp_roles' )->andReturn( $wp_roles_mock );

		// Simulate snapshot read failure.
		$this->setup_wpdb_mock( true );

		$result = Capabilities::install(); if ( ! $result ) {
			echo 'Failed install
'; }
		$this->assertFalse( $result );
	}

	/**
	 * Test exact restoration on mutation failure.
	 */
	public function test_install_exact_restoration() {
		$admin               = \Mockery::mock();
		$admin->capabilities = array();
		$admin->shouldReceive( 'has_cap' )->andReturn( false );
		$admin->shouldReceive( 'add_cap' )->andReturnUsing(
			function ( $cap ) use ( &$admin ) {
				$admin->capabilities[ $cap ] = true;
				$roles                       = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles['administrator']['capabilities'][ $cap ] = true;
				$this->db_store['wp_user_roles']['value']       = serialize( $roles );
			}
		);

		Functions\when( 'get_role' )->alias(
			function ( $role ) use ( $admin ) {
				if ( 'administrator' === $role ) {
					return $admin;
				} return null;
			}
		);
		Functions\expect( 'get_option' )->with( Capabilities::OPTION_ROLE_LEDGER, array() )->andReturn( array() );
		$wp_roles_mock           = \Mockery::mock();
		$wp_roles_mock->role_key = 'wp_user_roles';
		$wp_roles_mock->roles    = array( 'admin' => true );
		$wp_roles_mock->shouldReceive( 'for_site' );
		Functions\expect( 'wp_roles' )->andReturn( $wp_roles_mock );

		// Setup preexisting options with specific autoloads.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->db_store['wp_user_roles'] = array(
			'value'    => serialize( array( 'admin' => true ) ),
			'autoload' => 'yes',
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ] = array(
			'value'    => serialize( 0 ),
			'autoload' => 'no',
		);
		// Ledger is missing.

		$this->setup_wpdb_mock();

		Functions\when( 'add_role' )->alias(
			function ( $role, $name, $caps ) use ( &$admin ) {
				$roles                                    = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles[ $role ]                           = array(
					'name'         => $name,
					'capabilities' => $caps,
				);
				$this->db_store['wp_user_roles']['value'] = serialize( $roles );
				$r                                        = \Mockery::mock();
				$r->capabilities                          = $caps;
				$r->shouldReceive( 'has_cap' )->andReturnUsing(
					function ( $c ) use ( $r ) {
						return isset( $r->capabilities[ $c ] );
					}
				);
				$r->shouldReceive( 'add_cap' )->andReturnUsing(
					function ( $c ) use ( $r, $role ) {
						$r->capabilities[ $c ]                    = true;
						$roles                                    = @unserialize( $this->db_store['wp_user_roles']['value'] );
						$roles[ $role ]['capabilities'][ $c ]     = true;
						$this->db_store['wp_user_roles']['value'] = serialize( $roles );
					}
				);
				return $r;
			}
		);
		Functions\expect( '__' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);
		// Functions\expect( 'error_log' );.

		// Trigger failure by making update_option return false/throw when setting schema..
		// Actually, if we just set SCHEMA_VERSION to -1 in update_option alias it will fail..
		// Let's modify the Capabilities class schema constant to -1 just for this run? No, we mapped -1 to failure..
		// Wait, schema value is SCHEMA_VERSION (1)..
		// Let's make update_option fail for SCHEMA_VERSION..
		Functions\when( 'update_option' )->alias(
			function ( $opt, $val, $autoload ) {
				if ( 'mf_velog_capability_schema_version' === $opt ) {
					throw new \Exception( 'Mock update failure' );
				}
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				$this->db_store[ $opt ] = array(
					'value'    => serialize( $val ),
					'autoload' => 'yes',
				);
				return true;
			}
		);

		$result = Capabilities::install(); if ( ! $result ) {
			echo 'Failed install
'; }
		$this->assertFalse( $result );

		// Verify restoration.
		$this->assertArrayHasKey( 'wp_user_roles', $this->db_store );
		$this->assertEquals( 'yes', $this->db_store['wp_user_roles']['autoload'] );
		$this->assertArrayHasKey( Capabilities::OPTION_SCHEMA_VERSION, $this->db_store );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['autoload'] );
		$this->assertArrayNotHasKey( Capabilities::OPTION_ROLE_LEDGER, $this->db_store );
	}
}
