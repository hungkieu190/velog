<?php
require 'vendor/autoload.php';
$wpdb = \Mockery::mock();
$wpdb->last_error = '';
$wpdb->shouldReceive('get_row')->andReturnUsing(function() use ($wpdb) {
    $wpdb->last_error = 'db error';
    return null;
});
$wpdb->last_error = '';
$wpdb->get_row();
var_dump($wpdb->last_error);
