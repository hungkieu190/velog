<?php
/**
 * Pure reminder date/distance rules.
 *
 * @package MF\VeLog\Common\Reminder
 */

namespace MF\VeLog\Common\Reminder;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Regional\DecimalMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Unknown readings never become zero and snooze evaluation never writes. */
final class ReminderPolicy {

	/**
	 * Validate an optional date, including future dates.
	 *
	 * @param mixed $value Candidate calendar date.
	 */
	public static function date( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( '' === $value ) {
			return '';
		}
		try {
			return CalendarDate::parse( $value );
		} catch ( \InvalidArgumentException $exception ) {
			return null;
		}
	}

	/**
	 * Compute effective state using an explicit site-local today.
	 *
	 * @param array<string, mixed> $record        Reminder envelope.
	 * @param string               $vehicle_state Active/archived vehicle.
	 * @param string|null          $current_mm    Known canonical reading or null.
	 * @param string               $today         Site-local calendar date.
	 */
	public static function effective( array $record, string $vehicle_state, ?string $current_mm, string $today ): string {
		$f = $record['fields'];
		if ( 'completed' === $record['state'] ) {
			return 'completed';
		}
		if ( 'active' !== $vehicle_state ) {
			return 'suspended';
		}
		if ( '' !== ( $f['snooze_until'] ?? '' ) && $f['snooze_until'] > $today ) {
			return 'snoozed';
		}
		$date_hit     = '' !== $f['due_date'] && $today >= $f['due_date'];
		$distance_hit = null !== $current_mm && ! empty( $f['threshold'] )
			&& DecimalMath::compare( $current_mm, $f['threshold']['canonical_mm'] ) >= 0;
		return $date_hit || $distance_hit ? 'due' : 'upcoming';
	}
}
