<?php
/**
 * Exact reminder queue predicates before SQL pagination/count.
 *
 * @package MF\VeLog\Common\Reminder
 */

namespace MF\VeLog\Common\Reminder;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Storage\RecordRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Queue SQL uses transactionally maintained canonical odometer projections. */
final class ReminderQuery {

	/**
	 * Search a stable bounded page of manager-only reminder records.
	 *
	 * @param \WP_User $actor      Trusted manager.
	 * @param string   $state      Effective state filter.
	 * @param int      $vehicle_id Optional vehicle filter.
	 * @param int      $page       Positive page.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function search( \WP_User $actor, string $state = 'due', int $vehicle_id = 0, int $page = 1 ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_reminders' ) || ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		if ( ! in_array( $state, array( '', 'due', 'upcoming', 'snoozed', 'completed', 'suspended' ), true )
			|| $vehicle_id < 0 || $page < 1 || $page > 1000000 ) {
			return new \WP_Error( 'invalid_input' );
		}
		global $wpdb;
		$today = CalendarDate::today( time(), wp_timezone() );
		$joins = " FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_mf_velog_state'
			LEFT JOIN {$wpdb->postmeta} v ON v.post_id=p.ID AND v.meta_key='_mf_velog_reminder_vehicle_id'
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id=p.ID AND d.meta_key='_mf_velog_reminder_due_date'
			LEFT JOIN {$wpdb->postmeta} t ON t.post_id=p.ID AND t.meta_key='_mf_velog_reminder_mm'
			LEFT JOIN {$wpdb->postmeta} z ON z.post_id=p.ID AND z.meta_key='_mf_velog_reminder_snooze'
			LEFT JOIN {$wpdb->posts} vp ON vp.ID=v.meta_value AND vp.post_type='mf_velog_vehicle' AND vp.post_status='private'
			LEFT JOIN {$wpdb->postmeta} vs ON vs.post_id=vp.ID AND vs.meta_key='_mf_velog_state'
			LEFT JOIN {$wpdb->postmeta} c ON c.post_id=vp.ID AND c.meta_key='_mf_velog_current_mm'
			WHERE p.post_type='mf_velog_reminder' AND p.post_status='private'";
		// Structural failure is not an empty or upcoming queue.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$invalid = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Closed SQL fragments contain no request values.
			'SELECT p.ID' . $joins
			. " AND (s.meta_id IS NULL OR v.meta_id IS NULL OR d.meta_id IS NULL OR t.meta_id IS NULL OR z.meta_id IS NULL OR vp.ID IS NULL OR vs.meta_id IS NULL OR c.meta_id IS NULL OR s.meta_value NOT IN ('active','snoozed','completed') OR vs.meta_value NOT IN ('active','archived') OR (c.meta_value <> '' AND (c.meta_value NOT REGEXP '^(0|[1-9][0-9]{0,15})$' OR CAST(c.meta_value AS DECIMAL(20,0))>1609343999998391)) OR (t.meta_value <> '' AND (t.meta_value NOT REGEXP '^(0|[1-9][0-9]{0,15})$' OR CAST(t.meta_value AS DECIMAL(20,0))>1609343999998391))) LIMIT 1"
		);
		if ( ! empty( $wpdb->last_error ) || null !== $invalid ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		// Required projected rows must be unique before joins can affect totals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$duplicates = $wpdb->get_var(
			"SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id
			WHERE p.post_status='private' AND ((p.post_type='mf_velog_reminder' AND m.meta_key IN ('_mf_velog_state','_mf_velog_record','_mf_velog_reminder_vehicle_id','_mf_velog_reminder_due_date','_mf_velog_reminder_mm','_mf_velog_reminder_snooze'))
			OR (p.post_type='mf_velog_vehicle' AND m.meta_key IN ('_mf_velog_current_mm','_mf_velog_state')))
			GROUP BY m.post_id,m.meta_key HAVING COUNT(*)>1 LIMIT 1"
		);
		if ( ! empty( $wpdb->last_error ) || null !== $duplicates ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$source = "SELECT p.ID, v.meta_value AS vehicle_id, vs.meta_value AS vehicle_state, c.meta_value AS current_mm,
			s.meta_value AS stored_state, d.meta_value AS due_date, t.meta_value AS threshold_mm, z.meta_value AS snooze_until,
			CASE WHEN s.meta_value='completed' THEN 'completed' WHEN vs.meta_value='archived' THEN 'suspended'
			WHEN z.meta_value<>'' AND z.meta_value>%s THEN 'snoozed'
			WHEN (d.meta_value<>'' AND d.meta_value<=%s) OR (t.meta_value<>'' AND c.meta_value<>'' AND CAST(c.meta_value AS DECIMAL(20,0))>=CAST(t.meta_value AS DECIMAL(20,0))) THEN 'due'
			ELSE 'upcoming' END AS effective" . $joins;
		$where  = " WHERE (%s='' OR q.effective=%s) AND (%d=0 OR q.vehicle_id=%s)";
		$params = array( $today, $today, $state, $state, $vehicle_id, (string) $vehicle_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM (' . $source . ') q' . $where, ...$params ) );
		if ( null === $total || ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT q.ID,q.vehicle_id,q.vehicle_state,q.current_mm,q.stored_state,q.due_date,q.threshold_mm,q.snooze_until,q.effective FROM (' . $source . ') q' . $where . " ORDER BY CASE WHEN q.due_date='' THEN '9999-12-31' ELSE q.due_date END ASC,q.ID DESC LIMIT 50 OFFSET %d",
				...array_merge( $params, array( ( $page - 1 ) * 50 ) )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$records = RecordRepository::get_many(
			'mf_velog_reminder',
			array_map( static fn ( array $r ): int => (int) $r['ID'], $rows ),
			$actor
		);
		if ( is_wp_error( $records ) ) {
			return $records;
		}
		foreach ( $records as $i => &$record ) {
			$f         = $record['fields'];
			$row       = $rows[ $i ];
			$mm        = '' === $row['current_mm'] ? null : $row['current_mm'];
			$effective = ReminderPolicy::effective( $record, $row['vehicle_state'], $mm, $today );
			if ( $record['state'] !== $row['stored_state'] || (string) $f['vehicle_id'] !== $row['vehicle_id']
				|| $f['due_date'] !== $row['due_date'] || ( $f['threshold']['canonical_mm'] ?? '' ) !== $row['threshold_mm']
				|| $f['snooze_until'] !== $row['snooze_until'] || $effective !== $row['effective'] ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$record['effective']  = $effective;
			$record['current_mm'] = $mm;
			unset( $record['audit'] );
		}
		unset( $record );
		return array(
			'items' => $records,
			'total' => (int) $total,
			'page'  => $page,
		);
	}
}
