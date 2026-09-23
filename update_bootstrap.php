<?php
$content = file_get_contents('tests/fixtures/bootstrap-entry-verify.php');

if (strpos($content, 'function __') === false) {
    $stubs = <<<STUBS

if ( ! function_exists( '__' ) ) {
	function __( string \$text, string \$domain = 'default' ): string {
		return \$text;
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( string \$text, string \$context, string \$domain = 'default' ): string {
		return \$text;
	}
}

STUBS;
    $content = str_replace(
        "// Minimal WordPress function stubs — no site bootstrap, no database.\n// -------------------------------------------------------------------------",
        "// Minimal WordPress function stubs — no site bootstrap, no database.\n// -------------------------------------------------------------------------\n" . $stubs,
        $content
    );
}

$id_assertions = <<<ASSERT

	\$found_i18n = false;
	\$found_post_types = false;
	foreach ( \$wp_hooks['init'] ?? array() as \$cb ) {
		if ( \$cb instanceof \Closure ) {
			\$rf = new \ReflectionFunction( \$cb );
			if (\$rf->getClosureThis() instanceof \MF\VeLog\Core\Plugin) {
				\$found_i18n = true;
			} elseif (\$rf->getClosureThis() instanceof \MF\VeLog\Core\PostTypes || strpos((string)\$rf->getClosureScopeClass()?->getName(), 'PostTypes') !== false) {
				\$found_post_types = true;
			}
		} elseif ( is_array( \$cb ) ) {
			if ( \$cb[0] instanceof \MF\VeLog\Core\Plugin ) {
				\$found_i18n = true;
			} elseif ( \$cb[0] instanceof \MF\VeLog\Core\PostTypes || (is_string(\$cb[0]) && strpos(\$cb[0], 'PostTypes') !== false) ) {
				\$found_post_types = true;
			}
		}
	}
	
	if ( ! \$found_i18n || ! \$found_post_types ) {
		fwrite( STDERR, "FAIL: Missing expected identity callbacks on init.\\n" );
		exit( 1 );
	}
ASSERT;

if (strpos($content, '$found_i18n = false;') === false) {
    $content = str_replace(
        "\$init_count_before_second_run = count( \$wp_hooks['init'] ?? array() );\ndo_action( 'plugins_loaded' );",
        "\$init_count_before_second_run = count( \$wp_hooks['init'] ?? array() );\n" . $id_assertions . "\ndo_action( 'plugins_loaded' );",
        $content
    );
}

file_put_contents('tests/fixtures/bootstrap-entry-verify.php', $content);
