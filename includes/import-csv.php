<?php
// Fungsi untuk mengimpor data CSV
function greeting_ads_import_csv()
{
  if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');

    global $wpdb;
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

    // Lewati baris header
    fgetcsv($handle);

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
      $wpdb->insert(
        $table_name,
        array(
          'kata_kunci' => sanitize_text_field($data[0]),
          'grup_iklan' => sanitize_text_field($data[1]),
          'id_grup_iklan' => sanitize_text_field($data[2]),
          'nomor_kata_kunci' => sanitize_text_field($data[3]),
          'greeting' => sanitize_text_field($data[4]),
        )
      );
    }

    fclose($handle);
    echo '<div class="notice notice-success"><p>Data berhasil diimpor!</p></div>';
  }
}
