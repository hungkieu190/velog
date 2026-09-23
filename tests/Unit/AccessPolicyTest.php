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
	 * Test test_rejects_settings.
	 */
	public function test_rejects_settings() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturn( true );
		$this->assertFalse(
			AccessPolicy::authorize( $user, 'mf_velog_manage_settings', array( 'type' => 'mf_velog_customer' ) )
		);
	}

	/**
	 * Test test_requires_type.
	 */
	public function test_requires_type() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturn( true );
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_read_records', array() ) );
		$this->assertFalse( AccessPolicy::authorize( $user, 'mf_velog_read_records', array( 'type' => 'post' ) ) );
	}

	/**
	 * Test test_requires_capabilities.
	 */
	public function test_requires_capabilities() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return 'mf_velog_manage_customers' === $cap;
			}
		);

		// Fails because it lacks read_records.
		$this->assertFalse(
			AccessPolicy::authorize( $user, 'mf_velog_manage_customers', array( 'type' => 'mf_velog_customer' ) )
		);

		$user2 = $this->createMock( \WP_User::class );
		$user2->method( 'has_cap' )->willReturnCallback(
			function ( $cap ) {
				return in_array( $cap, array( 'mf_velog_read_records', 'mf_velog_manage_customers' ), true );
			}
		);
		// Passes.
		$this->assertTrue(
			AccessPolicy::authorize( $user2, 'mf_velog_manage_customers', array( 'type' => 'mf_velog_customer' ) )
		);
	}

	/**
	 * Test test_binds_actions.
	 */
	public function test_binds_actions() {
		$user = $this->createMock( \WP_User::class );
		$user->method( 'has_cap' )->willReturn( true );
		// Action with wrong type.
		$this->assertFalse(
			AccessPolicy::authorize( $user, 'mf_velog_manage_customers', array( 'type' => 'mf_velog_vehicle' ) )
		);
		$this->assertFalse(
			AccessPolicy::authorize( $user, 'mf_velog_manage_vehicles', array( 'type' => 'mf_velog_customer' ) )
		);

		$this->assertTrue(
			AccessPolicy::authorize( $user, 'mf_velog_manage_customers', array( 'type' => 'mf_velog_customer' ) )
		);
	}

	/**
	 * Test test_service_mutations.
	 */
	public function test_service_mutations() {
		$tech     = $this->createMock( \WP_User::class );
		$tech->ID = 5;
		$tech->method( 'has_cap' )->willReturn( true );

		$valid_draft = array(
			'type'            => 'mf_velog_service',
			'state'           => 'draft',
			'author'          => 5,
			'vehicle_visible' => true,
		);

		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_create_services', $valid_draft ) );
		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_edit_own_service_drafts', $valid_draft ) );

		// Wrong state.
		$valid_draft['state'] = 'finalized';
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_create_services', $valid_draft ) );

		// Manager correction.
		$manager     = $this->createMock( \WP_User::class );
		$manager->ID = 10;
		$manager->method( 'has_cap' )->willReturn( true );

		$valid_finalized = array(
			'type'            => 'mf_velog_service',
			'state'           => 'finalized',
			'author'          => 5,
			'vehicle_visible' => true,
		);

		$this->assertTrue( AccessPolicy::authorize( $manager, 'mf_velog_correct_services', $valid_finalized ) );
		$this->assertFalse( AccessPolicy::authorize( $manager, 'mf_velog_edit_own_service_drafts', $valid_finalized ) );
	}

	/**
	 * Test test_service_reads.
	 */
	public function test_service_reads() {
		$tech     = $this->createMock( \WP_User::class );
		$tech->ID = 5;
		$tech->method( 'has_cap' )->willReturn( true );

		$valid_service = array(
			'type'            => 'mf_velog_service',
			'state'           => 'finalized',
			'author'          => 10, // Not tech.
			'vehicle_visible' => true,
		);

		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $valid_service ) );

		// Invalid state.
		$invalid_state          = $valid_service;
		$invalid_state['state'] = 'unknown';
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $invalid_state ) );

		// Invalid author.
		$invalid_author           = $valid_service;
		$invalid_author['author'] = -1;
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $invalid_author ) );

		// Invalid vehicle visibility.
		$invalid_vis                    = $valid_service;
		$invalid_vis['vehicle_visible'] = false;
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $invalid_vis ) );
	}
}
