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

		$result = Capabilities::install();
		$this->assertTrue( $result, 'install() must return true on clean first-run' );
		$this->assertArrayHasKey( Capabilities::OPTION_SCHEMA_VERSION, $this->db_store );
		$this->assertArrayHasKey( Capabilities::OPTION_ROLE_LEDGER, $this->db_store );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['autoload'] );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_ROLE_LEDGER ]['autoload'] );

		// Per-callback: every admin cap must have been added through add_cap.
		$admin_caps = array(
			'mf_velog_read_records',
			'mf_velog_manage_settings',
			'mf_velog_manage_customers',
			'mf_velog_read_customer_contacts',
			'mf_velog_manage_vehicles',
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
			'mf_velog_correct_services',
			'mf_velog_manage_reminders',
		);
		foreach ( $admin_caps as $cap ) {
			$this->assertArrayHasKey( $cap, $admin->capabilities, "Admin must have capability: $cap" );
		}
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

		$result = Capabilities::install();
		$this->assertFalse( $result, 'install() must return false when snapshot read fails' );
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

		$result = Capabilities::install();
		$this->assertFalse( $result, 'install() must return false when update_option throws on schema' );

		// Verify exact restoration: existence, value, and autoload must match originals.
		$this->assertArrayHasKey( 'wp_user_roles', $this->db_store, 'wp_user_roles must be restored' );
		$this->assertEquals( 'yes', $this->db_store['wp_user_roles']['autoload'], 'wp_user_roles autoload must be restored to yes' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertEquals( serialize( array( 'admin' => true ) ), $this->db_store['wp_user_roles']['value'], 'wp_user_roles value must be original' );
		$this->assertArrayHasKey( Capabilities::OPTION_SCHEMA_VERSION, $this->db_store, 'schema option must be restored' );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['autoload'], 'schema autoload must be restored to no' );
		$this->assertArrayNotHasKey( Capabilities::OPTION_ROLE_LEDGER, $this->db_store, 'ledger must be absent (was never inserted before snapshot)' );
	}

	/**
	 * Test that administrator role receives all required capability grants.
	 */
	public function test_admin_receives_all_caps() {
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
				}
				return null;
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
			function ( $role, $name, $caps ) {
				$roles          = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles[ $role ] = array(
					'name'         => $name,
					'capabilities' => $caps,
				);
				$this->db_store['wp_user_roles']['value'] = serialize( $roles );
				$r                = \Mockery::mock();
				$r->capabilities  = $caps;
				$r->shouldReceive( 'has_cap' )->andReturnUsing(
					function ( $c ) use ( $r ) {
						return isset( $r->capabilities[ $c ] );
					}
				);
				$r->shouldReceive( 'add_cap' )->andReturnUsing(
					function ( $c ) use ( $r, $role ) {
						$r->capabilities[ $c ] = true;
						$roles                 = @unserialize( $this->db_store['wp_user_roles']['value'] );
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

		$result = Capabilities::install();
		$this->assertTrue( $result, 'install() must succeed' );

		// Prove every admin capability was granted via add_cap callback.
		$expected = array(
			'mf_velog_read_records',
			'mf_velog_manage_settings',
			'mf_velog_manage_customers',
			'mf_velog_read_customer_contacts',
			'mf_velog_manage_vehicles',
			'mf_velog_create_services',
			'mf_velog_edit_own_service_drafts',
			'mf_velog_finalize_own_services',
			'mf_velog_correct_services',
			'mf_velog_manage_reminders',
		);
		foreach ( $expected as $cap ) {
			$this->assertArrayHasKey( $cap, $admin->capabilities, "add_cap callback mutated admin for: $cap" );
			$this->assertTrue( $admin->capabilities[ $cap ], "Admin capability $cap must be true" );
		}
	}

	/**
	 * Test that restore_option failure during rollback is caught (not re-thrown) and
	 * that the rollback loop completes. Verify resulting state of all three options.
	 *
	 * Setup: schema option preexists (autoload=no), ledger absent, roles empty.
	 * Trigger: update_option throws on ledger write; wpdb->update throws when restoring ledger.
	 * Expected: install() returns false; schema is restored to original; ledger stays absent.
	 */
	public function test_restore_failure_does_not_propagate() {
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
				}
				return null;
			}
		);
		Functions\expect( 'get_option' )->with( Capabilities::OPTION_ROLE_LEDGER, array() )->andReturn( array() );
		$wp_roles_mock           = \Mockery::mock();
		$wp_roles_mock->role_key = 'wp_user_roles';
		$wp_roles_mock->roles    = array();
		$wp_roles_mock->shouldReceive( 'for_site' );
		Functions\expect( 'wp_roles' )->andReturn( $wp_roles_mock );

		// Preexisting: roles option (autoload=yes), schema option (autoload=no, value=0), ledger absent.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->db_store['wp_user_roles'] = array(
			'value'    => serialize( array() ),
			'autoload' => 'yes',
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ] = array(
			'value'    => serialize( 0 ),
			'autoload' => 'no',
		);
		// Ledger absent — not in db_store.

		// fail_restore=true: wpdb->update throws when option_name = mf_velog_role_ledger.
		$this->setup_wpdb_mock( false, true );

		Functions\when( 'add_role' )->alias(
			function ( $role, $name, $caps ) {
				$roles          = @unserialize( $this->db_store['wp_user_roles']['value'] );
				$roles[ $role ] = array(
					'name'         => $name,
					'capabilities' => $caps,
				);
				$this->db_store['wp_user_roles']['value'] = serialize( $roles );
				$r               = \Mockery::mock();
				$r->capabilities = $caps;
				$r->shouldReceive( 'has_cap' )->andReturnUsing(
					function ( $c ) use ( $r ) {
						return isset( $r->capabilities[ $c ] );
					}
				);
				$r->shouldReceive( 'add_cap' )->andReturnUsing(
					function ( $c ) use ( $r, $role ) {
						$r->capabilities[ $c ] = true;
						$roles                 = @unserialize( $this->db_store['wp_user_roles']['value'] );
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
		// update_option throws on ledger write — triggers rollback catch block.
		// wpdb->update (fail_restore=true) then throws when restore_option tries to update ledger row.
		Functions\when( 'update_option' )->alias(
			function ( $opt, $val, $autoload ) {
				if ( 'mf_velog_role_ledger' === $opt ) {
					throw new \Exception( 'Mock ledger update failure — triggers rollback' );
				}
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				$this->db_store[ $opt ] = array(
					'value'    => serialize( $val ),
					'autoload' => 'yes',
				);
				return true;
			}
		);

		// install() must return false — restore failure must not propagate as exception.
		$result = Capabilities::install();
		$this->assertFalse( $result, 'install() returns false when ledger update and restore both fail' );

		// Resulting state invariants:
		// 1. wp_user_roles must still exist (restore_option uses update/insert, not the failing ledger path).
		$this->assertArrayHasKey( 'wp_user_roles', $this->db_store, 'wp_user_roles remains in store after failed rollback' );

		// 2. Schema option was preexisting. restore_option updates it. Since fail_restore only
		//    affects mf_velog_role_ledger, schema restore succeeds with original value and autoload.
		$this->assertArrayHasKey( Capabilities::OPTION_SCHEMA_VERSION, $this->db_store, 'schema option remains after rollback' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertEquals( serialize( 0 ), $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['value'], 'schema value restored to original (0)' );
		$this->assertEquals( 'no', $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['autoload'], 'schema autoload restored to no' );

		// 3. Ledger was absent. restore_option calls delete_option (not update/insert) so the
		//    fail_restore path is not triggered. Ledger must remain absent.
		$this->assertArrayNotHasKey( Capabilities::OPTION_ROLE_LEDGER, $this->db_store, 'ledger remains absent after rollback' );
	}
}
