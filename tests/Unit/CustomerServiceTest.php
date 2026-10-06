<?php
/**
 * Customer service validation tests.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Customer\CustomerService;
use PHPUnit\Framework\TestCase;

/**
 * Tests customer field validation and projections.
 *
 * @covers \MF\VeLog\Common\Customer\CustomerService
 */
final class CustomerServiceTest extends TestCase {
	/** Validate canonical Unicode fields and optional values. */
	public function test_validators_accept_bounded_customer_fields(): void {
		$this->assertSame( 'Nguyễn Văn An', CustomerService::validate_name( '  Nguyễn Văn An  ' ) );
		$this->assertSame( '+66 81 234 5678', CustomerService::validate_phone( ' +66 81 234 5678 ' ) );
		$this->assertSame( '', CustomerService::validate_phone( '' ) );
		$this->assertSame( 'owner@example.com', CustomerService::validate_email( ' owner@example.com ' ) );
	}

	/** Reject ambiguous or unsafe values rather than silently changing them. */
	public function test_validators_reject_invalid_values(): void {
		$this->assertNull( CustomerService::validate_name( '' ) );
		$this->assertNull( CustomerService::validate_name( '<b>Alice</b>' ) );
		$this->assertNull( CustomerService::validate_phone( array( '123' ) ) );
		$this->assertNull( CustomerService::validate_email( 'not-an-email' ) );
	}

	/** Search projections are normalized and omit empty contacts. */
	public function test_projection_builders(): void {
		$this->assertSame( 'alice', CustomerService::project_name( array( 'name' => 'ALICE' ) ) );
		$this->assertNull( CustomerService::project_phone( array( 'phone' => '' ) ) );
		$this->assertSame( 'a@example.com', CustomerService::project_email( array( 'email' => 'A@EXAMPLE.COM' ) ) );
	}
}
