<?php
/**
 * Tests for AccessPolicy.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MF\VeLog\Common\AccessPolicy;

/**
 * AccessPolicyTest class.
 */
class AccessPolicyTest extends TestCase {

	/**
	 * Test test_primitives_require_exact_capability.
	 */
	public function test_primitives_require_exact_capability() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return 'mf_velog_read_records' === $cap;
			}
		);

		$context = array(
			'type' => 'mf_velog_service',
		);

		// Has capability.
		$this->assertTrue( AccessPolicy::authorize( $user, 'mf_velog_read_records', $context ) );
		// Does not have capability.
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_create_services', $context ) );
	}

	/**
	 * Test test_rejects_unknown_foreign_context.
	 */
	public function test_rejects_unknown_foreign_context() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturn( true );

		// Absent context.
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_read_records', array() ) );
		// Unknown context type.
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_read_records', array( 'type' => 'mf_velog_unknown' ) ) );
		// Foreign post type.
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_read_records', array( 'type' => 'post' ) ) );
	}

	/**
	 * Test test_customer_contacts_separation.
	 */
	public function test_customer_contacts_separation() {
		$manager = $this->createMock( \WP_User::class );
		$manager->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return in_array( $cap, array( 'mf_velog_read_records', 'mf_velog_read_customer_contacts' ), true ); // phpcs:ignore Generic.Files.LineLength.TooLong
			}
		);

		$tech = $this->createMock( \WP_User::class );
		$tech->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return 'mf_velog_read_records' === $cap; // No contact cap.
			}
		);

		$context = array( 'type' => 'mf_velog_customer' );

		$this->assertTrue( AccessPolicy::authorize( $manager, 'mf_velog_read_customer_contacts', $context ) );
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_read_customer_contacts', $context ) );
	}

	/**
	 * Test test_technician_own_draft_boundaries.
	 */
	public function test_technician_own_draft_boundaries() {
		$tech     = $this->createMock( \WP_User::class );
		$tech->ID = 5;
		$tech->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return in_array( $cap, array( 'mf_velog_read_records', 'mf_velog_create_services', 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' ), true ); // phpcs:ignore Generic.Files.LineLength.TooLong
			}
		);

		// Valid own draft.
		$context_valid = array(
			'type'   => 'mf_velog_service',
			'state'  => 'draft',
			'author' => 5,
		);
		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_edit_own_service_drafts', $context_valid ) );
		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_finalize_own_services', $context_valid ) );

		// Invalid: foreign author.
		$context_foreign_author = array(
			'type'   => 'mf_velog_service',
			'state'  => 'draft',
			'author' => 99,
		);
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_edit_own_service_drafts', $context_foreign_author ) );

		// Invalid: not draft.
		$context_finalized = array(
			'type'   => 'mf_velog_service',
			'state'  => 'publish',
			'author' => 5,
		);
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_edit_own_service_drafts', $context_finalized ) );

		// Manager correcting a service does not need own-draft rules, but requires correct primitive.
		$manager     = $this->createMock( \WP_User::class );
		$manager->ID = 2;
		$manager->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return 'mf_velog_correct_services' === $cap;
			}
		);
		// Manager correcting a published service by another author.
		$this->assertTrue( AccessPolicy::authorize( $manager, 'mf_velog_correct_services', $context_finalized ) );
	}

	/**
	 * Test test_object_matrix.
	 */
	public function test_object_matrix() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturn( true );

		$valid_types = array( 'mf_velog_customer', 'mf_velog_vehicle', 'mf_velog_service', 'mf_velog_reminder' );
		foreach ( $valid_types as $type ) {
			$this->assertTrue( AccessPolicy::authorize( $user, 'mf_velog_read_records', array( 'type' => $type ) ) );
		}
	}
}
