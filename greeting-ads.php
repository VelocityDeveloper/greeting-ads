<?php

/**
 * Plugin Name: Greeting Ads
 * Description: Plugin untuk mengimpor dan mengelola data CSV dengan fitur CRUD.
 * Version: 1.0
 * Author: Velocity Developer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Konstanta untuk nama tabel database
define('GREETING_ADS_TABLE', 'greeting_ads_data');

// Memuat file tambahan
require_once plugin_dir_path(__FILE__) . 'includes/import-csv.php';
require_once plugin_dir_path(__FILE__) . 'includes/crud-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/function.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/form-chat.php';
// require_once plugin_dir_path(__FILE__) . 'includes/table-display.php';

// Hook untuk menambahkan menu admin
add_action('admin_menu', 'greeting_ads_add_menu');
function greeting_ads_add_menu()
{
    add_menu_page(
        'Greeting Ads',
        'Greeting Ads',
        'manage_options',
        'greeting-ads',
        'greeting_ads_admin_page',
        'dashicons-admin-generic',
        6
    );
}

// Halaman admin plugin
function greeting_ads_admin_page()
{
?>
    <div class="wrap">
        <h1>Greeting Ads</h1>
        <?php include plugin_dir_path(__FILE__) . 'includes/table-display.php'; ?>
    </div>
<?php
}

// Hook untuk membuat tabel database saat plugin diaktifkan
register_activation_hook(__FILE__, 'greeting_ads_create_table');
function greeting_ads_create_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        kata_kunci text NOT NULL,
        grup_iklan text NOT NULL,
        id_grup_iklan text NOT NULL,
        nomor_kata_kunci text NOT NULL,
        greeting text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook untuk menghapus tabel saat plugin dihapus
register_uninstall_hook(__FILE__, 'greeting_ads_uninstall');
function greeting_ads_uninstall()
{
    global $wpdb;
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
