<?php
/**
 * Negative control fixture to prove the test runner rejects failures.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

$GLOBALS['velog_failures'] = array();

echo "Running deliberate failing negative control...\n";
echo "FAIL: intentional negative control failure to verify harness\n";
$GLOBALS['velog_failures'][] = 'intentional negative control failure';

echo "\n=== NEGATIVE CONTROL SUMMARY ===\n";
echo sprintf( "FAILURES: %d assertion(s) failed:\n", count( $GLOBALS['velog_failures'] ) );
exit( 1 );
