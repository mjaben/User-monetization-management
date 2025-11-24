<?php

class UMM_Activator {

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mycred_isp_withdrawals';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            withdrawal_method VARCHAR(20) DEFAULT 'isp',
            phone VARCHAR(20),
            isp VARCHAR(50),
            account_number VARCHAR(50),
            bank_name VARCHAR(50),
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
