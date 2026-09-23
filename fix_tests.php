<?php
$content = file_get_contents('tests/Unit/LoaderRunTest.php');
$content = str_replace(
    "		Functions\expect( 'add_action' )\n			->twice()\n			->with( 'init', \Mockery::type( 'callable' ), 10, 1 );\n		\$this->addToAssertionCount( 1 );\n\n		\$loader->run();\n	}",
    "		Functions\expect( 'add_action' )\n			->once()\n			->with( 'init', \Mockery::type( 'callable' ), 10, 1 );\n		\$this->addToAssertionCount( 1 );\n\n		\$loader->run();\n	}",
    $content
);
file_put_contents('tests/Unit/LoaderRunTest.php', $content);

$content = file_get_contents('tests/Unit/I18nLifecycleTest.php');
$content = preg_replace(
    '/\$textdomain_init_count = 0;\s*foreach \([^\}]+\}\s*\}\s*\+\+\$textdomain_init_count;\s*\}/s',
    "\$textdomain_init_count = 0;\n\t\tforeach ( \$recorded as \$call ) {\n\t\t\t[ \$hook, \$callable ] = \$call;\n\t\t\tif ( 'init' !== \$hook ) {\n\t\t\t\tcontinue;\n\t\t\t}\n\t\t\tif ( \$callable instanceof \Closure ) {\n\t\t\t\t\$rf = new \ReflectionFunction( \$callable );\n\t\t\t\tif ( \$rf->getClosureThis() instanceof \MF\VeLog\Core\Plugin ) {\n\t\t\t\t\t++\$textdomain_init_count;\n\t\t\t\t}\n\t\t\t} elseif ( is_array( \$callable ) && \$callable[0] instanceof \MF\VeLog\Core\Plugin ) {\n\t\t\t\t++\$textdomain_init_count;\n\t\t\t}\n\t\t}",
    $content
);
file_put_contents('tests/Unit/I18nLifecycleTest.php', $content);
