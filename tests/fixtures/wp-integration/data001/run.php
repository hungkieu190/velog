<?php
/**
 * DATA-001 Disposable WordPress Integration Harness.
 *
 * Runs isolated tests on disposable WordPress + isolated MySQL/MariaDB instances.
 * Verifies V1 through V5 storage contracts with negative controls and strict
 * failure detection (rejecting failed assertion lines regardless of exit code).
 *
 * Usage:
 *   php run.php [--wp-version=6.7.2] [--keep-tmp]
 *
 * @package MF\VeLog\Tests\Fixtures
 */

// Parse CLI options.
$options    = getopt( '', array( 'wp-version::', 'keep-tmp' ) );
$wp_version = $options['wp-version'] ?? '6.7.2';
$keep_tmp   = isset( $options['keep-tmp'] );

$valid_versions = array( '6.4.3', '6.7.2' );
if ( ! in_array( $wp_version, $valid_versions, true ) ) {
	fwrite( STDERR, "ERROR: Invalid wp-version '$wp_version'. Valid: " . implode( ', ', $valid_versions ) . "\n" );
	exit( 1 );
}

$plugin_root = dirname( __DIR__, 4 );
if ( ! file_exists( $plugin_root . '/velog.php' ) ) {
	fwrite( STDERR, "ERROR: Plugin root not found at $plugin_root\n" );
	exit( 1 );
}

$evidence_dir = $plugin_root . '/ai-document/evidence/DATA-001/builder-round-3/' . $wp_version;

if ( ! is_dir( $evidence_dir ) ) {
	mkdir( $evidence_dir, 0755, true );
}

$tmp_token    = bin2hex( random_bytes( 8 ) );
$tmp_base     = '/tmp/velog_data001_' . $wp_version . '_' . getmypid() . '_' . $tmp_token;
$mysql_data   = $tmp_base . '/mysql_data';
$mysql_socket = $tmp_base . '/mysql.sock';
$wp_root      = $tmp_base . '/wp';
$log_file     = $evidence_dir . '/run.txt';

// ─── Evidence log ───────────────────────────────────────────────────────────

$log = fopen( $log_file, 'w' );
if ( false === $log ) {
	fwrite( STDERR, "ERROR: Cannot open log file: $log_file\n" );
	exit( 1 );
}

/**
 * Logs a line to evidence file and stdout.
 *
 * @param string $line Line to log.
 */
function log_line( string $line ): void {
	global $log;
	$ts        = gmdate( 'Y-m-d H:i:s' ) . 'Z';
	$formatted = "[$ts] $line\n";
	fwrite( $log, $formatted );
	echo $formatted;
}

/**
 * Runs a shell command, logs it and its output, returns exit code.
 *
 * @param string        $cmd       Command to run.
 * @param string        $label     Log label.
 * @param array<string> $out_lines Output lines buffer.
 * @return int Exit code.
 */
function run_cmd( string $cmd, string $label, array &$out_lines = array() ): int {
	log_line( "CMD [$label]: $cmd" );
	$output = array();
	$exit   = 0;
	exec( $cmd . ' 2>&1', $output, $exit );
	foreach ( $output as $line ) {
		log_line( "  OUT: $line" );
	}
	log_line( "  EXIT: $exit" );
	$out_lines = $output;
	return $exit;
}

/**
 * Runs a fixture via WP-CLI eval-file with strict assertion & error detection.
 *
 * Rejects:
 *  - Nonzero exit codes
 *  - Output containing 'FAIL:'
 *  - Output containing 'WordPress database error' or 'Fatal error'
 *  - Missing expected success summary marker
 *
 * @param string $wp              WP-CLI command prefix.
 * @param string $script_path     Path to PHP fixture script.
 * @param string $label           Log label.
 * @param string $expected_marker Expected summary string in stdout.
 * @return bool True if passed, false if failed.
 */
function run_fixture( string $wp, string $script_path, string $label, string $expected_marker ): bool {
	global $overall_exit;

	if ( ! file_exists( $script_path ) ) {
		log_line( "ERROR: Fixture script not found: $script_path" );
		$overall_exit = 1;
		return false;
	}

	$out            = array();
	$exit           = run_cmd( "$wp eval-file \"$script_path\" 2>&1", $label, $out );
	$has_fail_line  = false;
	$has_error_line = false;
	$has_marker     = false;

	foreach ( $out as $line ) {
		if ( str_contains( $line, 'FAIL:' ) ) {
			$has_fail_line = true;
		}
		if ( str_contains( $line, 'WordPress database error' ) || str_contains( $line, 'Fatal error' ) ) {
			$has_error_line = true;
		}
		if ( '' !== $expected_marker && str_contains( $line, $expected_marker ) ) {
			$has_marker = true;
		}
	}

	if ( 0 !== $exit || $has_fail_line || $has_error_line || ( '' !== $expected_marker && ! $has_marker ) ) {
		log_line(
			sprintf(
				'%s FAILED (exit: %d, fail_line: %s, error_line: %s, marker: %s).',
				$label,
				$exit,
				$has_fail_line ? 'yes' : 'no',
				$has_error_line ? 'yes' : 'no',
				$has_marker ? 'yes' : 'no'
			)
		);
		$overall_exit = 1;
		return false;
	}

	log_line( "$label PASSED." );
	return true;
}

$overall_exit = 0;
$mysqld_pid   = null;

// ─── Record environment ─────────────────────────────────────────────────────

log_line( "=== DATA-001 Integration Fixture: WP $wp_version ===" );
log_line( "Plugin root: $plugin_root" );
log_line( "Temp base:   $tmp_base" );
log_line( "Evidence:    $evidence_dir" );

// PHP version.
log_line( 'PHP version: ' . PHP_VERSION );
log_line( 'PHP extensions: ' . implode( ', ', get_loaded_extensions() ) );

// mysqld version.
run_cmd( 'which mysqld || which mariadbd', 'mysqld-path' );
run_cmd( 'mysqld --version 2>&1 || mariadbd --version 2>&1', 'mysqld-version' );

// wp-cli version.
run_cmd( 'wp --version', 'wpcli-version' );

// composer versions.
run_cmd( "php \"$plugin_root/vendor/bin/phpunit\" --version", 'phpunit-version' );

try {
	// ─── Create temp directory ───────────────────────────────────────────────

	mkdir( $mysql_data, 0755, true );
	mkdir( $wp_root, 0755, true );
	log_line( 'Created temp dirs.' );

	// ─── Initialize MySQL/MariaDB data directory ─────────────────────────────

	if ( file_exists( '/usr/bin/mariadb-install-db' ) ) {
		$init_cmd = "mariadb-install-db --auth-root-authentication-method=normal --datadir=$mysql_data --user=$(whoami) 2>&1";
	} elseif ( file_exists( '/usr/bin/mysql_install_db' ) ) {
		$init_cmd = "mysql_install_db --datadir=$mysql_data --user=$(whoami) 2>&1";
	} else {
		$init_cmd = "mysqld --initialize-insecure --datadir=$mysql_data --user=$(whoami) 2>&1";
	}

	$exit = run_cmd( $init_cmd, 'db-init' );
	if ( 0 !== $exit ) {
		log_line( 'ERROR: DB init failed.' );
		$overall_exit = 1;
		return;
	}

	// ─── Start DB daemon ──────────────────────────────────────────────────────

	$server_bin = file_exists( '/usr/sbin/mariadbd' ) ? 'mariadbd' : 'mysqld';
	$start_cmd  = "$server_bin --datadir=$mysql_data --socket=$mysql_socket"
		. " --pid-file=$tmp_base/mysql.pid"
		. ' --port=0 --skip-networking'
		. ' --user=$(whoami)'
		. " >$tmp_base/db.log 2>&1 &";

	run_cmd( $start_cmd, 'db-start' );

	$started = false;
	for ( $i = 0; $i < 30; $i++ ) {
		if ( file_exists( $mysql_socket ) ) {
			$started = true;
			break;
		}
		usleep( 200000 );
	}

	if ( ! $started ) {
		log_line( 'ERROR: DB socket not created after 6 seconds.' );
		if ( file_exists( "$tmp_base/db.log" ) ) {
			log_line( 'DB log: ' . file_get_contents( "$tmp_base/db.log" ) );
		}
		$overall_exit = 1;
		return;
	}

	log_line( 'DB started.' );

	// ─── Create database and user (standard restricted WP user without PROCESS) ──

	$mysql_cli = "mysql -S $mysql_socket -u root";
	run_cmd( "$mysql_cli -e \"CREATE DATABASE velog_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"", 'create-db' );
	run_cmd( "$mysql_cli -e \"CREATE USER 'velog_test'@'localhost' IDENTIFIED BY 'velog_test';\"", 'create-user' );
	run_cmd( "$mysql_cli -e \"GRANT ALL ON velog_test.* TO 'velog_test'@'localhost';\"", 'grant' );
	run_cmd( "$mysql_cli -e \"FLUSH PRIVILEGES;\"", 'flush' );

	// ─── Download WordPress ───────────────────────────────────────────────────

	$wp = "wp --path=$wp_root --skip-themes --skip-plugins --allow-root";

	$exit = run_cmd(
		"wp core download --version=$wp_version --path=$wp_root --force --allow-root 2>&1",
		'wp-download'
	);
	if ( 0 !== $exit ) {
		log_line( 'ERROR: wp core download failed.' );
		$overall_exit = 1;
		return;
	}

	// ─── Create wp-config ────────────────────────────────────────────────────

	run_cmd(
		"$wp config create"
		. ' --dbname=velog_test --dbuser=velog_test --dbpass=velog_test'
		. " --dbhost=localhost:$mysql_socket"
		. ' --dbprefix=wp_'
		. ' --extra-php="define( \'VELOG_TEST_FIXTURE_RUN\', true );"'
		. ' --skip-check 2>&1',
		'wp-config'
	);

	// ─── Install WordPress ───────────────────────────────────────────────────

	$exit = run_cmd(
		"$wp core install"
		. ' --url=http://velog-test.local'
		. " --title='VeLog Test'"
		. ' --admin_user=admin'
		. ' --admin_password=admin_pass_test'
		. ' --admin_email=admin@velog-test.local'
		. ' --skip-email 2>&1',
		'wp-install'
	);
	if ( 0 !== $exit ) {
		log_line( 'ERROR: wp core install failed.' );
		$overall_exit = 1;
		return;
	}

	log_line( 'WordPress installed.' );

	// Verify WP version.
	run_cmd( "$wp core version", 'wp-version-check' );

	// Verify DB tables are InnoDB.
	run_cmd(
		"$mysql_cli -e \"SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'velog_test' AND TABLE_NAME IN ('wp_posts', 'wp_postmeta', 'wp_options');\"",
		'verify-innodb'
	);

	// ─── Activate plugin ─────────────────────────────────────────────────────

	// Symlink plugin into WP for CLI use.
	$wp_plugins = $wp_root . '/wp-content/plugins/velog';
	if ( ! is_link( $wp_plugins ) ) {
		symlink( $plugin_root, $wp_plugins );
		log_line( 'Plugin symlinked.' );
	}

	$exit = run_cmd( "$wp plugin activate velog 2>&1", 'plugin-activate' );
	if ( 0 !== $exit ) {
		log_line( 'ERROR: plugin activation failed.' );
		$overall_exit = 1;
		return;
	}

	log_line( 'Plugin activated.' );

	// ─── Negative Control: Proving test harness rejects failures (D1-F-007) ───

	log_line( '--- Negative Control: Testing harness rejection of failures ---' );
	$neg_script   = $plugin_root . '/tests/fixtures/wp-integration/data001/negative_control.php';
	$neg_out      = array();
	$neg_exit     = run_cmd( "$wp eval-file \"$neg_script\" 2>&1", 'negative-control', $neg_out );
	$neg_has_fail = false;
	foreach ( $neg_out as $l ) {
		if ( str_contains( $l, 'FAIL: intentional negative control failure' ) ) {
			$neg_has_fail = true;
		}
	}
	if ( 0 === $neg_exit || ! $neg_has_fail ) {
		log_line( 'FATAL: Test harness failed to detect intentional failure in negative control! Aborting.' );
		$overall_exit = 1;
		return;
	}
	log_line( 'NEGATIVE CONTROL PASS: Runner correctly detected failure and nonzero exit.' );

	// ─── V1: Schema/field rejection via plugin eval ───────────────────────────

	log_line( '--- V1: Rejection tests ---' );
	$v1_script = $plugin_root . '/tests/fixtures/wp-integration/data001/v1_rejection.php';
	run_fixture( $wp, $v1_script, 'v1-rejection', 'ALL V1 ASSERTIONS PASS' );

	// ─── V2: Concurrency/stale/idempotency ───────────────────────────────────

	log_line( '--- V2: Concurrency / idempotency tests ---' );
	$v2_script = $plugin_root . '/tests/fixtures/wp-integration/data001/v2_concurrency.php';
	run_fixture( $wp, $v2_script, 'v2-concurrency', 'ALL V2 ASSERTIONS PASS' );

	// ─── V3: Retention (uninstall/reinstall) ─────────────────────────────────

	log_line( '--- V3: Retention tests ---' );
	$v3_script = $plugin_root . '/tests/fixtures/wp-integration/data001/v3_retention.php';
	run_fixture( $wp, $v3_script, 'v3-retention', 'ALL V3 ASSERTIONS PASS' );

	// ─── V4: Cache invalidation and failure modes ─────────────────────────────

	log_line( '--- V4: Cache / failure mode tests ---' );
	$v4_script = $plugin_root . '/tests/fixtures/wp-integration/data001/v4_cache_failure.php';
	run_fixture( $wp, $v4_script, 'v4-cache', 'ALL V4 ASSERTIONS PASS' );

	// ─── V5: G-08 benchmark generator and query plan (NOT VERIFIED) ───────────

	log_line( '--- V5: G-08 benchmark (NOT VERIFIED — domain queries do not exist yet) ---' );
	$v5_script = $plugin_root . '/tests/fixtures/wp-integration/data001/v5_benchmark.php';
	run_fixture( $wp, $v5_script, 'v5-benchmark', '=== V5 COMPLETE (NOT VERIFIED) ===' );

	// ─── Run unit tests (to confirm no regressions) ───────────────────────────

	log_line( '--- Unit tests (lint + test) ---' );

	$lint_exit = run_cmd( "cd \"$plugin_root\" && composer run lint 2>&1", 'composer-lint' );
	$test_exit = run_cmd( "cd \"$plugin_root\" && composer run test -- --display-skipped 2>&1", 'composer-test' );

	if ( 0 !== $lint_exit ) {
		log_line( "LINT FAIL: exit $lint_exit" );
		$overall_exit = 1;
	} else {
		log_line( 'LINT PASS.' );
	}

	if ( 0 !== $test_exit ) {
		log_line( "TEST FAIL: exit $test_exit" );
		$overall_exit = 1;
	} else {
		log_line( 'TEST PASS.' );
	}

	// ─── git diff check ───────────────────────────────────────────────────────

	run_cmd( "cd \"$plugin_root\" && git diff --check 2>&1", 'git-diff-check' );
	run_cmd( "cd \"$plugin_root\" && git diff --cached --check 2>&1", 'git-diff-cached-check' );
	run_cmd( "cd \"$plugin_root\" && git diff --stat 2>&1", 'git-diff-stat' );

} finally {
	// ─── Cleanup ─────────────────────────────────────────────────────────────

	log_line( '=== CLEANUP ===' );

	// Stop mysqld via direct PID tracking (D1-F-017).
	$pid_file = $tmp_base . '/mysql.pid';
	if ( file_exists( $pid_file ) ) {
		$pid = (int) trim( (string) file_get_contents( $pid_file ) );
		if ( $pid > 0 ) {
			log_line( "Stopping mysqld PID $pid gracefully with SIGTERM..." );
			posix_kill( $pid, SIGTERM );
			$stopped = false;
			for ( $wait = 0; $wait < 20; $wait++ ) {
				if ( ! posix_kill( $pid, 0 ) ) {
					$stopped = true;
					break;
				}
				usleep( 500000 ); // 0.5s wait
			}
			if ( ! $stopped ) {
				log_line( "mysqld PID $pid did not exit after 10s; sending SIGKILL..." );
				posix_kill( $pid, SIGKILL );
			} else {
				log_line( "mysqld PID $pid stopped gracefully." );
			}
		}
	}

	// Remove temp files unless --keep-tmp was specified.
	if ( ! $keep_tmp ) {
		run_cmd( "rm -rf $tmp_base", 'rm-tmp' );
		log_line( 'Temp dir removed. Cleanup complete.' );
	} else {
		log_line( "Kept temp dir: $tmp_base" );
	}

	fclose( $log );

	$result_label = 0 === $overall_exit ? 'PASS' : 'FAIL';
	echo "=== OVERALL RESULT: $result_label ===\n";
	exit( $overall_exit );
}
