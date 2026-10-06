<?php
/**
 * Customer query input tests.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Customer\CustomerQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tests strict customer query argument normalization.
 *
 * @covers \MF\VeLog\Common\Customer\CustomerQuery
 */
final class CustomerQueryTest extends TestCase {
	/** Normalize an allowlisted customer query. */
	public function test_normalize_args_accepts_allowlisted_values(): void {
		$result = CustomerQuery::normalize_args(
			array(
				'term'      => ' Nguyễn ',
				'state'     => 'active',
				'sort'      => 'id',
				'direction' => 'desc',
				'page'      => 2,
			)
		);
		$this->assertSame(
			array(
				'term'      => 'Nguyễn',
				'state'     => 'active',
				'sort'      => 'id',
				'direction' => 'desc',
				'page'      => 2,
			),
			$result
		);
	}

	/** Reject unknown, non-scalar, and out-of-range inputs. */
	public function test_normalize_args_rejects_invalid_values(): void {
		$this->assertInstanceOf( \WP_Error::class, CustomerQuery::normalize_args( array( 'unknown' => 'x' ) ) );
		$this->assertInstanceOf( \WP_Error::class, CustomerQuery::normalize_args( array( 'term' => array() ) ) );
		$this->assertInstanceOf( \WP_Error::class, CustomerQuery::normalize_args( array( 'page' => 0 ) ) );
	}
}
