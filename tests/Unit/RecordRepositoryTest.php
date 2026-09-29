<?php
/**
 * RecordRepositoryTest: unit tests for RecordRepository authorization, snapshot, and idempotency logic.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\RecordSchema;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RecordRepository logic.
 *
 * @covers \MF\VeLog\Common\Storage\RecordRepository
 */
class RecordRepositoryTest extends TestCase {

	/**
	 * Reset schema before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		RecordSchema::reset_for_testing();
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'capability'      => 'mf_velog_manage_customers',
				'read_capability' => 'mf_velog_read_records',
				'states'          => array( 'active', 'archived' ),
				'field_policies'  => array(
					'name'         => 'public',
					'phone'        => 'contact',
					'email'        => 'contact',
					'address'      => 'contact',
					'contact_name' => 'contact',
				),
				'fields'          => array(
					'name'         => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
					'phone'        => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
					'email'        => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
					'address'      => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
					'contact_name' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
				),
			)
		);
		RecordSchema::register(
			'mf_velog_service',
			array(
				'capability'      => 'mf_velog_create_services',
				'read_capability' => 'mf_velog_read_records',
				'states'          => array( 'draft', 'finalized' ),
				'field_policies'  => array(
					'vehicle_visible' => 'public',
				),
				'fields'          => array(
					'vehicle_visible' => static fn ( mixed $v ) => is_bool( $v ) ? $v : null,
				),
				'context_builder' => static function ( array $envelope, \WP_User $actor ): array {
					unset( $actor );
					return array(
						'type'            => 'mf_velog_service',
						'state'           => $envelope['state'] ?? 'draft',
						'author'          => (int) ( $envelope['created_by'] ?? 0 ),
						'vehicle_visible' => (bool) ( $envelope['fields']['vehicle_visible'] ?? false ),
					);
				},
			)
		);
		RecordSchema::seal();
	}

	/**
	 * Reset schema after each test.
	 */
	protected function tearDown(): void {
		RecordSchema::reset_for_testing();
		parent::tearDown();
	}

	/**
	 * Technician without customer contact capability gets contact fields redacted in snapshot.
	 */
	public function test_customer_contact_fields_redacted_for_technician(): void {
		$actor     = new class() extends \WP_User {
			/**
			 * Capability map.
			 *
			 * @var array<string, bool>
			 */
			public array $caps = array(
				'mf_velog_read_records' => true,
			);

			/**
			 * Checks capability.
			 *
			 * @param string $cap Cap name.
			 * @return bool
			 */
			public function has_cap( $cap ): bool {
				return ! empty( $this->caps[ $cap ] );
			}
		};
		$actor->ID = 42;

		$envelope = array(
			'schema_version' => 1,
			'record_version' => 1,
			'state'          => 'active',
			'fields'         => array(
				'name'  => 'John Doe',
				'phone' => '0901234567',
				'email' => 'john@example.com',
			),
			'created_by'     => 1,
			'created_at_utc' => '2026-09-29T10:00:00Z',
			'updated_by'     => 1,
			'updated_at_utc' => '2026-09-29T10:00:00Z',
			'audit'          => array(),
		);

		$ref_method = new \ReflectionMethod( RecordRepository::class, 'build_snapshot' );
		$ref_method->setAccessible( true );

		$snapshot = $ref_method->invoke( null, 10, null, $envelope, $actor, 'mf_velog_customer' );

		$this->assertIsArray( $snapshot );
		$this->assertSame( 'John Doe', $snapshot['fields']['name'] );
		$this->assertArrayNotHasKey( 'phone', $snapshot['fields'] );
		$this->assertArrayNotHasKey( 'email', $snapshot['fields'] );
	}

	/**
	 * Actor with customer contact capability receives all contact fields.
	 */
	public function test_customer_contact_fields_preserved_when_authorized(): void {
		$actor     = new class() extends \WP_User {
			/**
			 * Checks capability.
			 *
			 * @param string $cap Cap name.
			 * @return bool
			 */
			public function has_cap( $cap ): bool {
				return in_array( $cap, array( 'mf_velog_read_records', 'mf_velog_read_customer_contacts' ), true );
			}
		};
		$actor->ID = 1;

		$envelope = array(
			'schema_version' => 1,
			'record_version' => 1,
			'state'          => 'active',
			'fields'         => array(
				'name'  => 'Jane Doe',
				'phone' => '0909999999',
				'email' => 'jane@example.com',
			),
			'created_by'     => 1,
			'created_at_utc' => '2026-09-29T10:00:00Z',
			'updated_by'     => 1,
			'updated_at_utc' => '2026-09-29T10:00:00Z',
			'audit'          => array(),
		);

		$ref_method = new \ReflectionMethod( RecordRepository::class, 'build_snapshot' );
		$ref_method->setAccessible( true );

		$snapshot = $ref_method->invoke( null, 10, null, $envelope, $actor, 'mf_velog_customer' );

		$this->assertSame( '0909999999', $snapshot['fields']['phone'] );
		$this->assertSame( 'jane@example.com', $snapshot['fields']['email'] );
	}

	/**
	 * Service visibility constraint: vehicle_visible = false fails authorize_read.
	 */
	public function test_service_visibility_false_denies_read(): void {
		$actor     = new class() extends \WP_User {
			/**
			 * Checks capability.
			 *
			 * @param string $cap Cap name.
			 * @return bool
			 */
			public function has_cap( $cap ): bool {
				return 'mf_velog_read_records' === $cap;
			}
		};
		$actor->ID = 5;

		$definition = RecordSchema::get( 'mf_velog_service' );

		$invisible_envelope = array(
			'schema_version' => 1,
			'record_version' => 1,
			'state'          => 'draft',
			'created_by'     => 5,
			'fields'         => array(
				'vehicle_visible' => false,
			),
		);

		$ref_auth = new \ReflectionMethod( RecordRepository::class, 'authorize_read' );
		$ref_auth->setAccessible( true );

		$result = $ref_auth->invoke( null, $actor, 'mf_velog_service', $definition, $invisible_envelope );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'forbidden', $result->get_error_code() );

		$visible_envelope = array(
			'schema_version' => 1,
			'record_version' => 1,
			'state'          => 'draft',
			'created_by'     => 5,
			'fields'         => array(
				'vehicle_visible' => true,
			),
		);

		$pass_result = $ref_auth->invoke( null, $actor, 'mf_velog_service', $definition, $visible_envelope );
		$this->assertTrue( $pass_result );
	}

	/**
	 * D1-F-009: Contact data in audit entries is redacted for technician without capability.
	 */
	public function test_audit_entries_have_contact_fields_redacted_for_technician(): void {
		$actor     = new class() extends \WP_User {
			/**
			 * Checks capability.
			 *
			 * @param string $cap Cap name.
			 * @return bool
			 */
			public function has_cap( $cap ): bool {
				return 'mf_velog_read_records' === $cap;
			}
		};
		$actor->ID = 42;

		$envelope = array(
			'schema_version' => 1,
			'record_version' => 2,
			'state'          => 'active',
			'fields'         => array(
				'name'  => 'John Doe',
				'phone' => '0901234567',
			),
			'created_by'     => 1,
			'created_at_utc' => '2026-09-29T10:00:00Z',
			'updated_by'     => 1,
			'updated_at_utc' => '2026-09-29T10:05:00Z',
			'audit'          => array(
				array(
					'action'     => 'create',
					'actor_id'   => 1,
					'at_utc'     => '2026-09-29T10:00:00Z',
					'request_id' => 'req-1',
					'before'     => array(),
					'after'      => array(
						'name'  => 'John Doe',
						'phone' => '0901234567',
						'email' => 'john@example.com',
					),
				),
				array(
					'action'     => 'save',
					'actor_id'   => 1,
					'at_utc'     => '2026-09-29T10:05:00Z',
					'request_id' => 'req-2',
					'before'     => array(
						'name'  => 'John Doe',
						'phone' => '0901234567',
					),
					'after'      => array(
						'name'  => 'John Doe Jr.',
						'phone' => '0908888888',
					),
				),
			),
		);

		$ref_method = new \ReflectionMethod( RecordRepository::class, 'build_snapshot' );
		$ref_method->setAccessible( true );

		$snapshot = $ref_method->invoke( null, 10, null, $envelope, $actor, 'mf_velog_customer' );

		$this->assertArrayNotHasKey( 'phone', $snapshot['fields'] );
		$this->assertCount( 2, $snapshot['audit'] );

		// Entry 1.
		$this->assertArrayNotHasKey( 'phone', $snapshot['audit'][0]['after'] );
		$this->assertArrayNotHasKey( 'email', $snapshot['audit'][0]['after'] );
		$this->assertSame( 'John Doe', $snapshot['audit'][0]['after']['name'] );

		// Entry 2.
		$this->assertArrayNotHasKey( 'phone', $snapshot['audit'][1]['before'] );
		$this->assertArrayNotHasKey( 'phone', $snapshot['audit'][1]['after'] );
		$this->assertSame( 'John Doe Jr.', $snapshot['audit'][1]['after']['name'] );
	}

	/**
	 * D1-F-009: Contact data in audit entries is preserved for manager with capability.
	 */
	public function test_audit_entries_preserve_contact_fields_when_authorized(): void {
		$actor     = new class() extends \WP_User {
			/**
			 * Checks capability.
			 *
			 * @param string $cap Cap name.
			 * @return bool
			 */
			public function has_cap( $cap ): bool {
				return in_array( $cap, array( 'mf_velog_read_records', 'mf_velog_read_customer_contacts' ), true );
			}
		};
		$actor->ID = 1;

		$envelope = array(
			'schema_version' => 1,
			'record_version' => 1,
			'state'          => 'active',
			'fields'         => array(
				'name'  => 'Jane Doe',
				'phone' => '0909999999',
			),
			'created_by'     => 1,
			'created_at_utc' => '2026-09-29T10:00:00Z',
			'updated_by'     => 1,
			'updated_at_utc' => '2026-09-29T10:00:00Z',
			'audit'          => array(
				array(
					'action'     => 'create',
					'actor_id'   => 1,
					'at_utc'     => '2026-09-29T10:00:00Z',
					'request_id' => 'req-1',
					'before'     => array(),
					'after'      => array(
						'name'  => 'Jane Doe',
						'phone' => '0909999999',
					),
				),
			),
		);

		$ref_method = new \ReflectionMethod( RecordRepository::class, 'build_snapshot' );
		$ref_method->setAccessible( true );

		$snapshot = $ref_method->invoke( null, 10, null, $envelope, $actor, 'mf_velog_customer' );

		$this->assertSame( '0909999999', $snapshot['fields']['phone'] );
		$this->assertSame( '0909999999', $snapshot['audit'][0]['after']['phone'] );
	}
}
