<?php
require_once "wp/wp-load.php";
$uid = wp_insert_user(array('user_login' => 'testuser', 'user_pass' => 'password', 'role' => 'editor'));
var_dump($uid);
if (is_wp_error($uid)) {
    echo $uid->get_error_message();
}
