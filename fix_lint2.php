<?php
$f = 'src/Core/Capabilities.php';
$c = file_get_contents($f);
$c = str_replace(
"	 * @return bool True on success, false on failure or collision.\n\t */",
"	 * @return bool True on success, false on failure or collision.\n\t * @throws \Exception On role creation failure.\n\t */",
$c
);
file_put_contents($f, $c);

$f = 'tests/Unit/AccessPolicyTest.php';
$c = file_get_contents($f);
$c = str_replace("class AccessPolicyTest extends TestCase {", "/**\n * AccessPolicyTest class.\n */\nclass AccessPolicyTest extends TestCase {", $c);
$c = preg_replace("/\tpublic function (test_[a-zA-Z0-9_]+)\(\) \{/", "\t/**\n\t * Test $1.\n\t */\n\tpublic function $1() {", $c);
file_put_contents($f, $c);

$f = 'tests/bootstrap.php';
$c = file_get_contents($f);
$c = str_replace("class WP_User {", "/**\n\t * WP_User stub.\n\t */\n\tclass WP_User {", $c);
$c = str_replace("public \$ID;", "/**\n\t\t * ID.\n\t\t * @var int\n\t\t */\n\t\tpublic \$ID;", $c);
$c = str_replace("public function has_cap( \$cap ) {", "/**\n\t\t * has_cap.\n\t\t * @param string \$cap Capability.\n\t\t * @return bool\n\t\t */\n\t\tpublic function has_cap( \$cap ) {", $c);
file_put_contents($f, $c);

$f = 'tests/fixtures/core-003-verify.php';
$c = file_get_contents($f);
$c = str_replace(" * Verify C3-V2 and C3-V3\n */", " * Verify C3-V2 and C3-V3\n *\n * @package MF\\VeLog\\Tests\n */", $c);
$c = str_replace("\$errors =", "\$velog_errors =", $c);
$c = str_replace("\$errors++", "++\$velog_errors", $c);
$c = str_replace("\$errors >", "\$velog_errors >", $c);
$c = str_replace("\"\$errors errors found.\\n\"", "\"{\$velog_errors} errors found.\\n\"", $c);
$c = str_replace("global \$errors;", "global \$velog_errors;", $c);
$c = str_replace("function assert_fixture( \$condition, \$message ) {", "/**\n * Assert fixture.\n * @param bool \$condition Condition.\n * @param string \$message Message.\n */\nfunction assert_fixture( \$condition, \$message ) {", $c);
$c = str_replace("echo \"FAIL: \$message\\n\";", "echo esc_html( \"FAIL: \$message\\n\" ); // phpcs:ignore WordPress.Security.EscapeOutput", $c);
$c = str_replace("echo \"PASS: \$message\\n\";", "echo esc_html( \"PASS: \$message\\n\" ); // phpcs:ignore WordPress.Security.EscapeOutput", $c);
$c = str_replace("echo \"\$velog_errors", "echo esc_html( \"\$velog_errors" , $c);
$c = str_replace(" errors found.\\n\";", " errors found.\\n\" ); // phpcs:ignore WordPress.Security.EscapeOutput", $c);
$c = str_replace("echo \"Fixture tests passed.\\n\";", "echo esc_html( \"Fixture tests passed.\\n\" ); // phpcs:ignore WordPress.Security.EscapeOutput", $c);
$c = str_replace("\$manager !== null", "null !== \$manager", $c);
$c = str_replace("\$tech !== null", "null !== \$tech", $c);
$c = str_replace("\$obj !== null", "null !== \$obj", $c);
$c = str_replace("\$obj->public === false", "false === \$obj->public", $c);
$c = str_replace("\$obj->cap->edit_post === 'do_not_allow'", "'do_not_allow' === \$obj->cap->edit_post", $c);
file_put_contents($f, $c);
