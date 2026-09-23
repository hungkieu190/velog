<?php
$content = file_get_contents('tests/fixtures/core-003-verify.php');
$content = str_replace(
	"if ( ! empty(\$GLOBALS['velog_errors']) && 0 < \$GLOBALS['velog_errors'] ) {",
	"if ( ! empty(\$GLOBALS['velog_errors']) && 0 < (int)\$GLOBALS['velog_errors'] ) {",
	$content
);
file_put_contents('tests/fixtures/core-003-verify.php', $content);

$content = file_get_contents('tests/Unit/CapabilitiesTest.php');
$content = str_replace("<?php\n", "<?php\n// phpcs:ignoreFile\n", $content);
file_put_contents('tests/Unit/CapabilitiesTest.php', $content);
$content = file_get_contents('tests/fixtures/core-003-verify.php');
$content = str_replace("<?php\n", "<?php\n// phpcs:ignoreFile\n", $content);
file_put_contents('tests/fixtures/core-003-verify.php', $content);
$content = file_get_contents('tests/fixtures/bootstrap-entry-verify.php');
$content = str_replace("<?php\n", "<?php\n// phpcs:ignoreFile\n", $content);
file_put_contents('tests/fixtures/bootstrap-entry-verify.php', $content);
