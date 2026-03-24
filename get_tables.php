<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
global $wpdb;
$tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
foreach ($tables as $t) {
    if (strpos($t[0], 'fcom') !== false || strpos($t[0], 'fluent') !== false) {
        echo $t[0] . "\n";
    }
}
