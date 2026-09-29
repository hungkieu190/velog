<?php
/**
 * WriteCoordinator — bounded, serialized write unit executor.
 *
 * Satisfies contract §Transaction and cache sequence and §Write boundary.
 *
 * Key invariants enforced here:
 *  - Single-site mode, mysqli connection, InnoDB tables verified before first use.
 *  - Lock option row created idempotently OUTSIDE any product write unit.
 *  - Pin current mysqli handle; save and restore session lock-wait timeout.
 *  - InnoDB row-lock via SELECT ... FOR UPDATE on the fixed options row.
 *  - All transactional SQL uses the pinned handle directly (never $wpdb helpers).
 *  - COMMIT only after post/meta/option row-count verification on pinned handle.
 *  - On any pre-commit failure: ROLLBACK.
 *  - In finally: clean_post_cache() for all affected IDs; restore timeout.
 *  - No nested write units.
 *  - No non-database side effects inside the transaction.
 *
 * @package MF\VeLog\Common\Storage
 */

namespace MF\VeLog\Common\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes a bounded, serialized write unit under an InnoDB row-lock.
 *
 * @since 0.2.0
 */
final class WriteCoordinator {

	/**
	 * Lock wait timeout in seconds (candidate from contract §2).
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT_SECONDS = 5;

	/**
	 * Whether the lock option row has been created on this request.
	 *
	 * Resets on each request (static per-process state).
	 *
	 * @var bool
	 */
	private static bool $lock_row_initialized = false;

	/**
	 * Whether a write unit is currently executing (guards nested calls).
	 *
	 * @var bool
	 */
	private static bool $in_unit = false;

	/**
	 * Verifies system preconditions: single-site, mysqli, InnoDB.
	 *
	 * Called once before any write operation. Fails with WP_Error when
	 * the environment cannot support the contract.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_environment(): true|\WP_Error {
		if ( is_multisite() ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: multisite is not supported.'
			);
		}

		global $wpdb;

		// Verify mysqli.
		$dbh = $wpdb->dbh;
		if ( ! ( $dbh instanceof \mysqli ) ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: requires a mysqli connection.'
			);
		}

		// Verify InnoDB for posts, postmeta, options.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES'
				. ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s, %s)',
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return new \WP_Error( 'storage_unavailable', 'WriteCoordinator: cannot query table engines.' );
		}

		$found_tables = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row['ENGINE'] ) || 'InnoDB' !== $row['ENGINE'] ) {
				return new \WP_Error(
					'storage_unavailable',
					sprintf(
						'WriteCoordinator: table %s is not InnoDB.',
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- exception message, not HTML.
						$row['TABLE_NAME'] ?? '?'
					)
				);
			}
			if ( isset( $row['TABLE_NAME'] ) ) {
				$found_tables[ (string) $row['TABLE_NAME'] ] = true;
			}
		}

		$required_tables = array( (string) $wpdb->posts, (string) $wpdb->postmeta, (string) $wpdb->options );
		foreach ( $required_tables as $req ) {
			if ( empty( $found_tables[ $req ] ) ) {
				return new \WP_Error(
					'storage_unavailable',
					sprintf(
						'WriteCoordinator: table %s is not present or not InnoDB.',
						$req
					)
				);
			}
		}

		// Verify no pre-existing transaction on this connection using privilege-safe session variable.
		$wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$in_trx = $wpdb->get_var( 'SELECT @@in_transaction' );
		$wpdb->suppress_errors( false );

		if ( ! empty( $wpdb->last_error ) || null === $in_trx ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: unable to verify connection transaction state; failing closed.'
			);
		}

		if ( 1 === (int) $in_trx ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: pre-existing transaction detected; nested transactions not supported.'
			);
		}

		return true;
	}

	/**
	 * Creates the fixed write-lock option row idempotently.
	 *
	 * MUST be called OUTSIDE any write unit (no transaction active).
	 * Idempotent — safe to call once per request or once per process.
	 *
	 * @return true|\WP_Error
	 */
	public static function ensure_lock_row(): true|\WP_Error {
		if ( self::$lock_row_initialized ) {
			return true;
		}

		global $wpdb;

		$option_name = RecordSchema::WRITE_LOCK_OPTION;

		// Use INSERT IGNORE so it is safe to call multiple times.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options}
				 (option_name, option_value, autoload)
				 VALUES (%s, %s, 'no')",
				$option_name,
				'0'
			)
		);

		if ( false === $result ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: failed to initialize lock option row.'
			);
		}

		self::$lock_row_initialized = true;

		return true;
	}

	/**
	 * Runs a trusted write operation inside a serialized transaction.
	 *
	 * The callable receives a WriteUnit and must return array|WP_Error.
	 * Nested write units are rejected with WP_Error('storage_unavailable').
	 *
	 * Sequence (per contract §Transaction and cache sequence):
	 *  1. Reject nested unit.
	 *  2. Ensure lock row exists.
	 *  3. Pin mysqli handle; save session timeout; set 5-second timeout.
	 *  4. START TRANSACTION.
	 *  5. SELECT ... FOR UPDATE on lock row.
	 *  6. Execute $operation with WriteUnit.
	 *  7. On success: COMMIT; on any error: ROLLBACK; return indeterminate on lost ack.
	 *  8. finally: clean_post_cache() for all touched IDs; restore timeout; clear flag.
	 *
	 * @param \WP_User $actor     Authenticated actor (passed to operation for logging).
	 * @param callable $operation Trusted callable: function(WriteUnit $unit): array|WP_Error.
	 * @return array<string, mixed>|\WP_Error Committed result or stable error code.
	 */
	public static function run( \WP_User $actor, callable $operation ): array|\WP_Error {
		if ( self::$in_unit ) {
			return new \WP_Error(
				'storage_unavailable',
				'WriteCoordinator: nested write units are not supported.'
			);
		}

		// Ensure lock row before pinning connection (avoids pinning across potential reconnect).
		$lock_ready = self::ensure_lock_row();
		if ( is_wp_error( $lock_ready ) ) {
			return $lock_ready;
		}

		global $wpdb;
		$dbh = $wpdb->dbh;

		if ( ! ( $dbh instanceof \mysqli ) ) {
			return new \WP_Error( 'storage_unavailable', 'WriteCoordinator: mysqli required.' );
		}

		$pinned_thread_id = $dbh->thread_id;
		$saved_timeout    = self::get_session_timeout( $dbh );

		$unit           = new WriteUnit( $dbh, $wpdb->prefix, $actor );
		$final_result   = null;
		$in_transaction = false;

		self::$in_unit = true;

		try {
			// Set bounded timeout.
			$timeout_sql = sprintf( 'SET innodb_lock_wait_timeout = %d', self::LOCK_TIMEOUT_SECONDS );
			$timeout_set = self::exec_direct( $dbh, $timeout_sql );
			if ( false === $timeout_set ) {
				$final_result = new \WP_Error(
					'storage_unavailable',
					'WriteCoordinator: failed to configure lock timeout.'
				);
				return $final_result;
			}

			// Begin transaction.
			$started = self::exec_direct( $dbh, 'START TRANSACTION' );
			if ( false === $started ) {
				$final_result = new \WP_Error(
					'storage_unavailable',
					'WriteCoordinator: failed to begin transaction.'
				);
				return $final_result;
			}
			$in_transaction = true;

			// Verify connection identity before acquiring lock.
			if ( $dbh->thread_id !== $pinned_thread_id ) {
				self::exec_direct( $dbh, 'ROLLBACK' );
				$in_transaction = false;
				$final_result   = new \WP_Error(
					'indeterminate',
					'WriteCoordinator: connection identity changed before lock; failing closed.'
				);
				return $final_result;
			}

			// Acquire row lock on fixed lock option.
			$lock_option = RecordSchema::WRITE_LOCK_OPTION;
			$pfx_escaped = mysqli_real_escape_string( $dbh, $wpdb->prefix );
			$opt_escaped = mysqli_real_escape_string( $dbh, $lock_option );
			$lock_sql    = sprintf(
				"SELECT option_id FROM `%soptions` WHERE option_name = '%s' FOR UPDATE",
				$pfx_escaped,
				$opt_escaped
			);
			$lock_rows   = self::query_direct( $dbh, $lock_sql );

			if ( false === $lock_rows || 1 !== count( $lock_rows ) ) {
				$errno          = mysqli_errno( $dbh );
				$rb_ok          = self::exec_direct( $dbh, 'ROLLBACK' );
				$in_transaction = false;
				$live           = self::is_live_handle( $dbh, $pinned_thread_id );

				if ( ( 1205 === $errno || 1213 === $errno ) && $rb_ok && $live ) {
					$final_result = new \WP_Error(
						'conflict',
						'WriteCoordinator: write lock timeout or deadlock; retryable conflict.'
					);
					return $final_result;
				}

				if ( false === $rb_ok || ! $live ) {
					$final_result = new \WP_Error(
						'indeterminate',
						'WriteCoordinator: lock query failed and confirmed rollback on pinned handle unavailable.'
					);
					return $final_result;
				}

				$final_result = new \WP_Error(
					'storage_unavailable',
					sprintf( 'WriteCoordinator: could not acquire write lock (errno %d).', $errno )
				);
				return $final_result;
			}

			// Execute trusted operation.
			$op_result = $operation( $unit );

			// Re-verify connection identity.
			if ( ! self::is_live_handle( $dbh, $pinned_thread_id ) ) {
				self::exec_direct( $dbh, 'ROLLBACK' );
				$in_transaction = false;
				$final_result   = new \WP_Error(
					'indeterminate',
					'WriteCoordinator: connection identity changed during write; failing closed.'
				);
				return $final_result;
			}

			if ( is_wp_error( $op_result ) ) {
				$rb_ok          = self::exec_direct( $dbh, 'ROLLBACK' );
				$in_transaction = false;
				if ( false === $rb_ok ) {
					$final_result = new \WP_Error(
						'indeterminate',
						'WriteCoordinator: operation failed and ROLLBACK could not be confirmed.'
					);
					return $final_result;
				}
				$final_result = $op_result;
				return $final_result;
			}

			// Pre-commit test fault seam (disposable fixtures only; inert in production).
			self::maybe_trigger_test_fault( 'pre_commit_disconnect', $dbh );

			// Commit.
			$committed = self::exec_direct( $dbh, 'COMMIT' );

			// Post-commit test fault seam (disposable fixtures only; inert in production).
			self::maybe_trigger_test_fault( 'post_commit_lost_ack', $dbh );

			if ( false === $committed ) {
				// Lost commit acknowledgement — indeterminate.
				$in_transaction = false;
				$final_result   = new \WP_Error(
					'indeterminate',
					'WriteCoordinator: COMMIT acknowledgement lost; reconcile before retry.'
				);
			} else {
				$in_transaction = false;
				$final_result   = $op_result;
			}
		} catch ( \Throwable $e ) {
			// Catch any unexpected throwable; treat as storage failure or indeterminate if rollback fails.
			if ( $in_transaction ) {
				$rb_ok          = self::exec_direct( $dbh, 'ROLLBACK' );
				$in_transaction = false;
				$live           = self::is_live_handle( $dbh, $pinned_thread_id );
				if ( false === $rb_ok || ! $live ) {
					$final_result = new \WP_Error(
						'indeterminate',
						sprintf(
							'WriteCoordinator: error (%s) and confirmed ROLLBACK on pinned handle failed.',
							$e->getMessage()
						)
					);
				} else {
					$final_result = new \WP_Error(
						'storage_unavailable',
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Error message, not HTML.
						sprintf( 'WriteCoordinator: transaction aborted: %s', $e->getMessage() )
					);
				}
			} else {
				$final_result = new \WP_Error(
					'indeterminate',
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Error message, not HTML.
					sprintf( 'WriteCoordinator: post-commit or uncontained failure: %s', $e->getMessage() )
				);
			}
		} finally {
			$cleanup_error = null;
			try {
				// Invalidate post/meta cache for touched post IDs.
				foreach ( $unit->get_affected_post_ids() as $post_id ) {
					wp_cache_delete( $post_id, 'posts' );
					wp_cache_delete( $post_id, 'post_meta' );
					clean_post_cache( $post_id );
				}

				if ( null !== $saved_timeout && self::is_live_handle( $dbh, $pinned_thread_id ) ) {
					$restored = self::exec_direct(
						$dbh,
						sprintf( 'SET innodb_lock_wait_timeout = %d', (int) $saved_timeout )
					);
					if ( false === $restored ) {
						$cleanup_error = new \WP_Error(
							'indeterminate',
							'WriteCoordinator: failed to restore session lock timeout.'
						);
					}
				}
			} catch ( \Throwable $te ) {
				$cleanup_error = new \WP_Error(
					'indeterminate',
					sprintf( 'WriteCoordinator: cleanup failed: %s', $te->getMessage() )
				);
			}

			self::$in_unit = false;

			if ( null !== $cleanup_error ) {
				// Cleanup failure overrides success without masking pre-existing indeterminate.
				if ( ! is_wp_error( $final_result ) || 'indeterminate' !== $final_result->get_error_code() ) {
					$final_result = $cleanup_error;
				}
			}
		}

		return $final_result;
	}

	/**
	 * Active test fault configuration for integration fixture runs.
	 *
	 * Gated strictly to test fixture runs; completely inert and unreachable in production.
	 *
	 * @internal
	 * @var array<string, mixed>|null
	 */
	private static ?array $test_fault_config = null;

	/**
	 * Configures a statement fault point for disposable test fixtures.
	 *
	 * Fails closed with \LogicException if called outside a test fixture run.
	 *
	 * @internal
	 * @param string|null $fault_point Target fault point name.
	 * @param string      $action      'fail_after_statement' | 'close_connection'.
	 * @return void
	 * @throws \LogicException If called outside a test fixture run.
	 */
	public static function configure_test_fault( ?string $fault_point, string $action = 'fail_after_statement' ): void {
		if ( ! defined( 'VELOG_TEST_FIXTURE_RUN' ) || true !== constant( 'VELOG_TEST_FIXTURE_RUN' ) ) {
			throw new \LogicException( 'WriteCoordinator test fault seam is forbidden outside test fixture runs.' );
		}
		if ( null === $fault_point ) {
			self::$test_fault_config = null;
			return;
		}
		self::$test_fault_config = array(
			'point'  => $fault_point,
			'action' => $action,
			'hit'    => false,
		);
	}

	/**
	 * Checks if a named test fault is configured and triggers it.
	 *
	 * @internal
	 * @param string  $point Fault point name.
	 * @param \mysqli $dbh   Pinned handle.
	 * @return void
	 * @throws \RuntimeException If fault point triggers simulation.
	 */
	public static function maybe_trigger_test_fault( string $point, \mysqli $dbh ): void {
		if ( ! defined( 'VELOG_TEST_FIXTURE_RUN' ) || true !== constant( 'VELOG_TEST_FIXTURE_RUN' ) ) {
			return;
		}
		if ( null === self::$test_fault_config || self::$test_fault_config['point'] !== $point ) {
			return;
		}
		self::$test_fault_config['hit'] = true;
		$action                         = self::$test_fault_config['action'] ?? 'fail_after_statement';
		if ( 'close_connection' === $action ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@mysqli_close( $dbh );
			throw new \RuntimeException( sprintf( 'Simulated connection drop at fault point "%s".', $point ) );
		}
		if ( 'fail_after_statement' === $action ) {
			throw new \RuntimeException( sprintf( 'Simulated post-statement failure at fault point "%s".', $point ) );
		}
	}

	/**
	 * Returns whether the configured test fault was hit.
	 *
	 * @internal
	 * @return bool
	 */
	public static function was_test_fault_hit(): bool {
		return (bool) ( self::$test_fault_config['hit'] ?? false );
	}

	/**
	 * Executes a raw SQL statement on the pinned handle.
	 *
	 * Returns false on mysqli error; true on success.
	 * Must only be called with trusted, internally-generated SQL strings —
	 * never with user-supplied data directly interpolated.
	 *
	 * @param \mysqli $dbh Pinned connection handle.
	 * @param string  $sql Trusted SQL statement.
	 * @return bool
	 */
	public static function exec_direct( \mysqli $dbh, string $sql ): bool {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return @mysqli_query( $dbh, $sql ) !== false;
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Executes a query on the pinned handle and returns rows as associative arrays.
	 *
	 * @param \mysqli $dbh Pinned connection handle.
	 * @param string  $sql Trusted SQL query.
	 * @return array<int, array<string, mixed>>|false
	 */
	public static function query_direct( \mysqli $dbh, string $sql ): array|false {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$res = @mysqli_query( $dbh, $sql );
		} catch ( \Throwable ) {
			return false;
		}
		if ( ! ( $res instanceof \mysqli_result ) ) {
			return false;
		}
		$rows = array();
		// Assignment in condition is intentional here for mysqli_fetch_assoc iteration.
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $row = mysqli_fetch_assoc( $res ) ) {
			$rows[] = $row;
		}
		mysqli_free_result( $res );
		return $rows;
	}

	/**
	 * Checks if the handle is still open, belongs to the pinned thread, and responds to ping.
	 *
	 * Safe against Property access / closed handle exceptions in PHP 8.1+.
	 *
	 * @param \mysqli $dbh              Pinned connection handle.
	 * @param int     $pinned_thread_id Pinned thread ID.
	 * @return bool
	 */
	private static function is_live_handle( \mysqli $dbh, int $pinned_thread_id ): bool {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return ( $dbh->thread_id === $pinned_thread_id && @mysqli_ping( $dbh ) );
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Reads the current session innodb_lock_wait_timeout value.
	 *
	 * @param \mysqli $dbh Pinned connection handle.
	 * @return int|null Timeout in seconds, or null on failure.
	 */
	private static function get_session_timeout( \mysqli $dbh ): ?int {
		$rows = self::query_direct( $dbh, "SHOW SESSION VARIABLES LIKE 'innodb_lock_wait_timeout'" );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return null;
		}
		$val = $rows[0]['Value'] ?? null;
		return ( null !== $val ) ? (int) $val : null;
	}

	/**
	 * Resets static state for testing.
	 *
	 * FOR TESTING ONLY — must not be called in production bootstrap paths.
	 *
	 * @internal
	 */
	public static function reset_for_testing(): void {
		self::$lock_row_initialized = false;
		self::$in_unit              = false;
		self::$test_fault_config    = null;
	}
}
