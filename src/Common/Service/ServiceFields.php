<?php
/**
 * Service field validation without lossy number coercion.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Vehicle\OdometerPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Closed canonical field validators shared by all service write routes. */
final class ServiceFields {

	/**
	 * Validate a canonical positive ID or zero as incomplete draft sentinel.
	 *
	 * @param mixed $value Candidate ID.
	 */
	public static function id( mixed $value ): ?int {
		return is_int( $value ) && $value >= 0 ? $value : null;
	}

	/**
	 * Validate plain Unicode text with an explicit bound.
	 *
	 * @param mixed $value Candidate text.
	 * @param int   $limit Character limit.
	 */
	public static function text( mixed $value, int $limit = 10000 ): ?string {
		if ( ! is_string( $value ) || strlen( $value ) > $limit * 4
			|| 0 !== preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', $value ) ) {
			return null;
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		return $length <= $limit ? $value : null;
	}

	/**
	 * Validate optional site-local date.
	 *
	 * @param mixed $value Candidate date.
	 */
	public static function date( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( '' === $value ) {
			return '';
		}
		try {
			$date = CalendarDate::parse( $value );
			return $date <= CalendarDate::today( time(), wp_timezone() ) ? $date : null;
		} catch ( \InvalidArgumentException $exception ) {
			return null;
		}
	}

	/**
	 * Validate a closed reading tuple, unknown distinct from zero.
	 *
	 * @param mixed $value Candidate reading.
	 * @return array<string, string>|null
	 */
	public static function reading( mixed $value ): ?array {
		if ( array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || count( $value ) !== 3 ) {
			return null;
		}
		$validated = OdometerPolicy::validate_baseline( array_merge( $value, array( 'date' => '2000-01-01' ) ) );
		if ( null === $validated ) {
			return null;
		}
		unset( $validated['date'] );
		return $validated;
	}

	/**
	 * Validate exact original-currency cost or unknown tuple.
	 *
	 * @param mixed $value Candidate cost.
	 * @return array<string, mixed>|null
	 */
	public static function cost( mixed $value ): ?array {
		if ( array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || count( $value ) !== 5 || ! is_string( $value['original_value'] ?? null )
			|| ! is_string( $value['currency'] ?? null ) ) {
			return null;
		}
		try {
			$parsed = Money::parse( $value['original_value'], $value['currency'], '.' );
		} catch ( \InvalidArgumentException $exception ) {
			return null;
		}
		foreach ( $parsed as $key => $expected ) {
			if ( ! array_key_exists( $key, $value ) || $value[ $key ] !== $expected ) {
				return null;
			}
		}
		return $parsed;
	}

	/**
	 * Validate a name-only immutable historical snapshot.
	 *
	 * @param mixed $value Candidate snapshot.
	 * @return array{id:int,name:string}|array{}|null
	 */
	public static function snapshot( mixed $value ): ?array {
		if ( array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || count( $value ) !== 2 || ! is_int( $value['id'] ?? null )
			|| $value['id'] < 1 || ! is_string( $value['name'] ?? null )
			|| '' === trim( $value['name'] ) || null === self::text( $value['name'], 200 ) ) {
			return null;
		}
		return array(
			'id'   => $value['id'],
			'name' => $value['name'],
		);
	}
}
