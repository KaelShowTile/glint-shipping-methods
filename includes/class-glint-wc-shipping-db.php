<?php
class Glint_WC_Shipping_DB {
    private static $table_name = 'glint_wc_shipping';

    public static function init() {
        // Class initialization if needed
    }

    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::get_table_name();

        $sql = "CREATE TABLE $table_name (
            method_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_name VARCHAR(255) NOT NULL DEFAULT '',
            postcode TEXT NOT NULL,
            method_name VARCHAR(100) NOT NULL,
            method_setting LONGTEXT NOT NULL,
            PRIMARY KEY (method_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    public static function save_methods($methods) {
        global $wpdb;
        $table = self::get_table_name();
        
        // Delete all existing methods
        $wpdb->query("TRUNCATE TABLE $table");
        
        // Insert new methods
        foreach ($methods as $method) {
            $wpdb->insert($table, [
                'setting_name' => sanitize_text_field($method['setting_name']),
                'postcode' => sanitize_textarea_field($method['postcode']),
                'method_name' => sanitize_text_field($method['method_name']),
                'method_setting' => maybe_serialize($method['method_setting'])
            ]);
        }
    }

    public static function get_all_methods() {
        global $wpdb;
        $table = self::get_table_name();
        $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        
        return array_map(function($method) {
            return [
                'method_id' => $method['method_id'],
                'setting_name' => $method['setting_name'],
                'postcode' => $method['postcode'],
                'method_name' => $method['method_name'],
                'method_setting' => maybe_unserialize($method['method_setting'])
            ];
        }, $results);
    }

    public static function cleanup() {
        // Remove table on deactivation
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    public static function get_method_by_id($method_id) {
        global $wpdb;
        $table = self::get_table_name();
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE method_id = %d",
            $method_id
        ), ARRAY_A);
        
        if ($result) {
            return [
                'method_id' => $result['method_id'],
                'setting_name' => $result['setting_name'],
                'postcode' => $result['postcode'],
                'method_name' => $result['method_name'],
                'method_setting' => maybe_unserialize($result['method_setting'])
            ];
        }
        
        return null;
    }
}