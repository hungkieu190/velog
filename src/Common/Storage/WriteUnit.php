<?php
/**
 * WriteUnit — bounded write context object.
 *
 * Passed to trusted operation callables inside WriteCoordinator::run().
 * Exposes only the pinned handle, table prefix, and affected-post-ID tracker.
 * Provides create(), save(), and get() methods operating on the pinned handle
 * within the active transaction for multi-record operations.
 *
 * @package MF\VeLog\Common\Storage
 */

namespace MF\VeLog\Common\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context object for a single bounded write unit execution.
 *
 * @internal Used only by WriteCoordinator and RecordRepository.
 * @since 0.2.0
 */
final class WriteUnit {

	/**
	 * Pinned mysqli connection.
	 *
	 * @var \mysqli
	 */
	private \mysqli $dbh;

	/**
	 * WordPress table prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Authenticated actor bound to this write unit.
	 *
	 * @var \WP_User
	 */
	private \WP_User $actor;

	/**
	 * Post IDs touched during this unit (for cache invalidation).
	 *
	 * @var int[]
	 */
	private array $affected_post_ids = array();

	/**
	 * Initialises the write unit with the pinned connection, table prefix, and optional actor.
	 *
	 * @param \mysqli       $dbh    Pinned connection handle.
	 * @param string        $prefix WordPress table prefix.
	 * @param \WP_User|null $actor  Authenticated actor.
	 */
	public function __construct( \mysqli $dbh, string $prefix, ?\WP_User $actor = null ) {
		$this->dbh    = $dbh;
		$this->prefix = $prefix;
		$this->actor  = $actor ?? new \WP_User( 0 );
	}

	/**
	 * Returns the pinned connection handle for direct SQL execution inside the unit.
	 *
	 * @return \mysqli
	 */
	public function get_dbh(): \mysqli {
		return $this->dbh;
	}

	/**
	 * Returns the WordPress table prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->prefix;
	}

	/**
	 * Returns the actor bound to this unit.
	 *
	 * @return \WP_User
	 */
	public function get_actor(): \WP_User {
		return $this->actor;
	}

	/**
	 * Registers a post ID whose caches must be invalidated in the finally block.
	 *
	 * @param int $post_id WordPress post ID.
	 */
	public function touch( int $post_id ): void {
		$this->affected_post_ids[] = $post_id;
	}

	/**
	 * Returns deduplicated list of post IDs touched during this unit.
	 *
	 * @return int[]
	 */
	public function get_affected_post_ids(): array {
		return array_unique( $this->affected_post_ids );
	}

	/**
	 * Creates a record within the active write unit transaction.
	 *
	 * @param string               $type       Registered CPT slug.
	 * @param array<string, mixed> $fields     Domain fields.
	 * @param string               $request_id Caller UUID for idempotency.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( string $type, array $fields, string $request_id ): array|\WP_Error {
		return RecordRepository::execute_create(
			$this->dbh,
			$this->prefix,
			$type,
			$fields,
			$this->actor,
			$request_id,
			$this
		);
	}

	/**
	 * Saves changes to an existing record within the active write unit transaction.
	 *
	 * @param string               $type             Registered CPT slug.
	 * @param int                  $id               Post ID.
	 * @param int                  $expected_version Optimistic lock version.
	 * @param array<string, mixed> $changes          Fields to update.
	 * @param string               $request_id       Caller UUID.
	 * @param string               $reason           Audit reason.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save(
		string $type,
		int $id,
		int $expected_version,
		array $changes,
		string $request_id,
		string $reason = ''
	): array|\WP_Error {
		return RecordRepository::execute_save(
			$this->dbh,
			$this->prefix,
			$type,
			$id,
			$expected_version,
			$changes,
			$this->actor,
			$request_id,
			$reason,
			$this
		);
	}

	/**
	 * Reads a record snapshot directly within the active write unit transaction.
	 *
	 * @param string $type Registered CPT slug.
	 * @param int    $id   Post ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( string $type, int $id ): array|\WP_Error {
		return RecordRepository::execute_get(
			$this->dbh,
			$this->prefix,
			$type,
			$id,
			$this->actor
		);
	}
}
