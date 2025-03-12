<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'greeting_ads_data';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
