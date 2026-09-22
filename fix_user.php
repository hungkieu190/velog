<?php
$f = 'tests/Unit/AccessPolicyTest.php';
$c = file_get_contents($f);
$c = str_replace("if ( ! class_exists( 'WP_User' ) ) {\n\tclass \\WP_User {", "if ( ! class_exists( '\\WP_User' ) ) {\n\tclass WP_User_Stub {\n\t\tpublic \$ID;\n\t\tpublic function has_cap( \$cap ) { return false; }\n\t}\n\tclass_alias( 'MF\\\\VeLog\\\\Tests\\\\Unit\\\\WP_User_Stub', 'WP_User' );\n}", $c);
file_put_contents($f, $c);
