<?php
// Fix tests/fixtures/bootstrap-entry-verify.php
$content = file_get_contents('tests/fixtures/bootstrap-entry-verify.php');
$content = str_replace(
"if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( \$post_type, \$args = array() ) {",
"if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * Stub: register_post_type
	 * @param string \$post_type
	 * @param array \$args
	 * @return \stdClass
	 */
	function register_post_type( \$post_type, \$args = array() ) {",
$content);
$content = str_replace(
"if ( ! function_exists( '__' ) ) {
	function __( string \$text, string \$domain = 'default' ): string {",
"if ( ! function_exists( '__' ) ) {
	/**
	 * Stub: __
	 * @param string \$text
	 * @param string \$domain
	 * @return string
	 */
	function __( string \$text, string \$domain = 'default' ): string {",
$content);
$content = str_replace(
"if ( ! function_exists( '_x' ) ) {
	function _x( string \$text, string \$context, string \$domain = 'default' ): string {",
"if ( ! function_exists( '_x' ) ) {
	/**
	 * Stub: _x
	 * @param string \$text
	 * @param string \$context
	 * @param string \$domain
	 * @return string
	 */
	function _x( string \$text, string \$context, string \$domain = 'default' ): string {",
$content);
file_put_contents('tests/fixtures/bootstrap-entry-verify.php', $content);

// Fix tests/fixtures/core-003-verify.php
$content = file_get_contents('tests/fixtures/core-003-verify.php');
$content = preg_replace(
	'/function velog_assert\(\s*\$condition,\s*\$message\s*\)/',
	"/**\n * Assert a condition.\n *\n * @param bool   \$condition Condition to check.\n * @param string \$message   Message to display.\n */\nfunction velog_assert( \$condition, \$message )",
	$content
);
$content = preg_replace(
	'/function do_request\(\s*\$url,\s*\$uid\s*=\s*0,\s*\$method\s*=\s*\'GET\',\s*\$body\s*=\s*null\s*\)/',
	"/**\n * Make an HTTP request.\n *\n * @param string \$url    URL to request.\n * @param int    \$uid    User ID for cookies.\n * @param string \$method HTTP method.\n * @param mixed  \$body   Request body.\n * @return array{0: int, 1: string}\n */\nfunction do_request( \$url, \$uid = 0, \$method = 'GET', \$body = null )",
	$content
);
// Replace Yoda condition: `$velog_errors > 0`
$content = str_replace(
	"if ( ! empty(\$GLOBALS['velog_errors']) && \$GLOBALS['velog_errors'] > 0 )",
	"if ( ! empty(\$GLOBALS['velog_errors']) && 0 < \$GLOBALS['velog_errors'] )",
	$content
);
file_put_contents('tests/fixtures/core-003-verify.php', $content);

// Fix inline comments in core-003-verify.php
$lines = file('tests/fixtures/core-003-verify.php');
foreach ($lines as &$line) {
	if (preg_match('/^\s*\/\/ [a-zA-Z0-9].*[^.\!\?]\s*$/', $line) && strpos($line, 'phpcs:') === false) {
		$line = rtrim($line) . ".\n";
	}
}
file_put_contents('tests/fixtures/core-003-verify.php', implode('', $lines));

// Fix inline comments in CapabilitiesTest.php
$lines = file('tests/Unit/CapabilitiesTest.php');
foreach ($lines as &$line) {
	if (preg_match('/^\s*\/\/ [a-zA-Z0-9].*[^.\!\?]\s*$/', $line) && strpos($line, 'phpcs:') === false) {
		$line = rtrim($line) . ".\n";
	}
}
file_put_contents('tests/Unit/CapabilitiesTest.php', implode('', $lines));
