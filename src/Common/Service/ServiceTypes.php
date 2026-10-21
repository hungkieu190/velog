<?php
/**
 * Serialized, audited private taxonomy management.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

// phpcs:disable Generic.Files.LineLength.TooLong -- Closed tuples and pinned SQL contracts are kept together for review.

use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.RestrictedFunctions -- Exact escaping on the pinned mysqli handle; no reconnectable database writes.

/** Uses the same write lock as service finalization and customer/vehicle writes. */
final class ServiceTypes {

	/** Private taxonomy identity. */
	public const TAXONOMY = 'mf_velog_service_type';

	/**
	 * Read the bounded canonical catalog without a stale term cache.
	 *
	 * @param \WP_User       $actor Staff actor.
	 * @param WriteUnit|null $unit  Optional active unit.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function all( \WP_User $actor, ?WriteUnit $unit = null ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		global $wpdb;
		$dbh    = null === $unit ? $wpdb->dbh : $unit->get_dbh();
		$prefix = null === $unit ? $wpdb->prefix : $unit->get_prefix();
		if ( ! $dbh instanceof \mysqli ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$rows = WriteCoordinator::query_direct(
			$dbh,
			"SELECT t.term_id, t.name, m.meta_value FROM `{$prefix}terms` t
			INNER JOIN `{$prefix}term_taxonomy` x ON x.term_id = t.term_id AND x.taxonomy = 'mf_velog_service_type'
			LEFT JOIN `{$prefix}termmeta` m ON m.term_id = t.term_id AND m.meta_key = '_mf_velog_service_type'
			ORDER BY t.term_id ASC LIMIT 1001"
		);
		if ( false === $rows || count( $rows ) > 1000 ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$items = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['term_id'];
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
			$record = is_string( $row['meta_value'] ) ? @unserialize( $row['meta_value'], array( 'allowed_classes' => false ) ) : null;
			if ( isset( $items[ $id ] ) || ! is_array( $record ) || count( $record ) !== 6
				|| ! is_string( $record['name'] ?? null ) || $record['name'] !== $row['name']
				|| null === ServiceFields::text( $record['name'], 200 ) || '' === trim( $record['name'] )
				|| ! in_array( $record['state'] ?? null, array( 'active', 'inactive' ), true )
				|| ! is_int( $record['version'] ?? null ) || $record['version'] < 1
				|| ! is_array( $record['audit'] ?? null ) || count( $record['audit'] ) !== $record['version']
				|| ! is_string( $record['request_id'] ?? null ) || ! wp_is_uuid( $record['request_id'], 4 )
				|| ( $record['id'] ?? null ) !== $id ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$prior = array();
			foreach ( $record['audit'] as $index => $entry ) {
				if ( ! is_array( $entry ) || count( $entry ) !== 5 || ! is_int( $entry['actor'] ?? null ) || $entry['actor'] < 1
					|| ! is_string( $entry['request_id'] ?? null ) || ! wp_is_uuid( $entry['request_id'], 4 )
					|| ! is_string( $entry['at_utc'] ?? null ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $entry['at_utc'] )
					|| ( $entry['before'] ?? null ) !== $prior || ! is_array( $entry['after'] ?? null ) || count( $entry['after'] ) !== 2
					|| ! is_string( $entry['after']['name'] ?? null ) || '' === trim( $entry['after']['name'] )
					|| null === ServiceFields::text( $entry['after']['name'], 200 )
					|| ! in_array( $entry['after']['state'] ?? null, array( 'active', 'inactive' ), true )
					|| ( 0 === $index && $entry['request_id'] !== $record['request_id'] ) ) {
					return new \WP_Error( 'storage_unavailable' );
				}
				$prior = $entry['after'];
			}
			if ( array(
				'name'  => $record['name'],
				'state' => $record['state'],
			) !== $prior ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$items[ $id ] = $record;
		}
		return $items;
	}

	/**
	 * Create or rename/activate/deactivate a versioned type; never delete.
	 *
	 * @param int      $id         Zero to create.
	 * @param int      $version    Expected version, zero to create.
	 * @param string   $name       Plain type name.
	 * @param string   $state      Active/inactive.
	 * @param \WP_User $actor      Trusted manager.
	 * @param string   $request_id Request UUID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function save( int $id, int $version, string $name, string $state, \WP_User $actor, string $request_id ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_settings' ) || ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		$name = trim( $name );
		if ( $id < 0 || $version < 0 || ( 0 === $id ? 0 !== $version : $version < 1 )
			|| '' === $name || null === ServiceFields::text( $name, 200 )
			|| ! in_array( $state, array( 'active', 'inactive' ), true ) || ! wp_is_uuid( $request_id, 4 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		// Taxonomy tables join the transaction only when their engines are transactional.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$engines = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s, %s)',
				$wpdb->terms,
				$wpdb->term_taxonomy,
				$wpdb->termmeta
			),
			ARRAY_A
		);
		if ( ! is_array( $engines ) || 3 !== count( $engines ) || array( 'InnoDB' ) !== array_values( array_unique( array_column( $engines, 'ENGINE' ) ) ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$result = WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use ( $id, $version, $name, $state, $actor, $request_id ): array|\WP_Error {
				$catalog = self::all( $actor, $unit );
				if ( is_wp_error( $catalog ) ) {
					return $catalog;
				}
				foreach ( $catalog as $type ) {
					if ( 0 === $id && $type['request_id'] === $request_id ) {
						return $type['name'] === $name && $type['state'] === $state ? $type : new \WP_Error( 'conflict' );
					}
					if ( $type['id'] !== $id && 0 === strcasecmp( $type['name'], $name ) ) {
						return new \WP_Error( 'conflict' );
					}
				}
				if ( ( 0 === $id && count( $catalog ) >= 1000 ) || ( $id > 0 && ! isset( $catalog[ $id ] ) ) ) {
					return new \WP_Error( 'invalid_input' );
				}
				$old = $catalog[ $id ] ?? null;
				if ( null !== $old && $old['version'] !== $version ) {
					return new \WP_Error( 'stale_version' );
				}
				$dbh    = $unit->get_dbh();
				$prefix = $unit->get_prefix();
				$label  = mysqli_real_escape_string( $dbh, $name );
				if ( 0 === $id ) {
					$slug = 'velog-' . $request_id;
					if ( ! WriteCoordinator::exec_direct( $dbh, "INSERT INTO `{$prefix}terms` (name, slug, term_group) VALUES ('{$label}', '{$slug}', 0)" ) ) {
						return new \WP_Error( 'storage_unavailable' );
					}
					$id = (int) mysqli_insert_id( $dbh );
					if ( ! WriteCoordinator::exec_direct( $dbh, "INSERT INTO `{$prefix}term_taxonomy` (term_id, taxonomy, description, parent, count) VALUES ({$id}, 'mf_velog_service_type', '', 0, 0)" ) ) {
						return new \WP_Error( 'storage_unavailable' );
					}
				} elseif ( ! WriteCoordinator::exec_direct( $dbh, "UPDATE `{$prefix}terms` SET name = '{$label}' WHERE term_id = {$id}" ) ) {
					return new \WP_Error( 'storage_unavailable' );
				}
				$record            = array(
					'id'         => $id,
					'name'       => $name,
					'state'      => $state,
					'version'    => $version + 1,
					'request_id' => $old['request_id'] ?? $request_id,
					'audit'      => $old['audit'] ?? array(),
				);
				$record['audit'][] = array(
					'actor'      => (int) $actor->ID,
					'at_utc'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'request_id' => $request_id,
					'before'     => null === $old ? array() : array(
						'name'  => $old['name'],
						'state' => $old['state'],
					),
					'after'      => array(
						'name'  => $name,
						'state' => $state,
					),
				);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				$raw = mysqli_real_escape_string( $dbh, serialize( $record ) );
				$sql = null === $old
				? "INSERT INTO `{$prefix}termmeta` (term_id, meta_key, meta_value) VALUES ({$id}, '_mf_velog_service_type', '{$raw}')"
				: "UPDATE `{$prefix}termmeta` SET meta_value = '{$raw}' WHERE term_id = {$id} AND meta_key = '_mf_velog_service_type'";
				if ( ! WriteCoordinator::exec_direct( $dbh, $sql ) || 1 !== mysqli_affected_rows( $dbh ) ) {
					return new \WP_Error( 'storage_unavailable' );
				}
				$verified = self::all( $actor, $unit );
				return ! is_wp_error( $verified ) && ( $verified[ $id ] ?? null ) === $record ? $record : new \WP_Error( 'storage_unavailable' );
			}
		);
		if ( ! is_wp_error( $result ) ) {
			clean_term_cache( $result['id'], self::TAXONOMY );
		}
		return $result;
	}
}
