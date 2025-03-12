<?php
// Fungsi untuk menambah data
function greeting_ads_add_data($data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  return $wpdb->insert($table_name, $data);
}

// Fungsi untuk mengupdate data
function greeting_ads_update_data($id, $data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  return $wpdb->update($table_name, $data, array('id' => $id));
}

// Fungsi untuk menghapus data
function greeting_ads_delete_data($id)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  return $wpdb->delete($table_name, array('id' => $id));
}
