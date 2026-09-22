<?php
/**
 * CalendarDate class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling calendar dates.
 */
class CalendarDate {

	/**
	 * Parse and validate a Gregorian date string.
	 *
	 * @param string $input Date string in YYYY-MM-DD format.
	 * @return string
	 * @throws \InvalidArgumentException On invalid date.
	 */
	public static function parse( string $input ): string {
		if ( ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $input, $matches ) ) {
			throw new \InvalidArgumentException( 'invalid_date' );
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];

		if ( $year < 1 || $year > 9999 ) {
			throw new \InvalidArgumentException( 'invalid_date' );
		}

		if ( ! checkdate( $month, $day, $year ) ) {
			throw new \InvalidArgumentException( 'invalid_date' );
		}

		return $input;
	}

	/**
	 * Get the current date in a specific timezone based on unix seconds.
	 *
	 * @param int           $unix_seconds Unix timestamp.
	 * @param \DateTimeZone $zone         Timezone object.
	 * @return string
	 */
	public static function today( int $unix_seconds, \DateTimeZone $zone ): string {
		$dt = new \DateTimeImmutable( '@' . $unix_seconds );
		$dt = $dt->setTimezone( $zone );
		return $dt->format( 'Y-m-d' );
	}
}
