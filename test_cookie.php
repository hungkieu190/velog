<?php
require_once "wp/wp-load.php";
$uid = wp_insert_user(array('user_login' => 'testuser', 'user_pass' => 'password', 'role' => 'editor'));
var_dump($uid);
$cookie_auth = wp_generate_auth_cookie( $uid, time() + 3600, 'auth' );
var_dump($cookie_auth);
