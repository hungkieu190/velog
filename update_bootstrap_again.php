<?php
$content = file_get_contents('tests/fixtures/bootstrap-entry-verify.php');

if (strpos($content, 'function register_post_type') === false) {
    $stubs = <<<STUBS

if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( \$post_type, \$args = array() ) {
		return new \stdClass();
	}
}

STUBS;
    $content = str_replace(
        "if ( ! function_exists( '__' ) ) {",
        $stubs . "\nif ( ! function_exists( '__' ) ) {",
        $content
    );
    file_put_contents('tests/fixtures/bootstrap-entry-verify.php', $content);
}
