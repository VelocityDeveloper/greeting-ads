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
      // Periksa apakah greeting sudah ada di database
      $existing_greeting = $wpdb->get_var("SELECT greeting FROM $table_name WHERE greeting = '" . sanitize_text_field($data[4]) . "'");
      if (empty($existing_greeting)) {
        // Jika greeting belum ada, insert data
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
    }

    fclose($handle);
    echo '<div class="notice notice-success"><p>Data berhasil diimpor!</p></div>';
  }
}

// Handle download template
if (isset($_GET['action']) && $_GET['action'] == 'download_csv_template') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="greeting_ads_template.csv"');

  $output = fopen('php://output', 'w');
  fputcsv($output, array('Kata Kunci', 'Grup Iklan', 'ID Grup Iklan', 'Nomor Kata Kunci', 'Greeting'));

  // Contoh data (opsional)
  // fputcsv($output, array('Contoh Keyword', 'Contoh Grup', '123456789', '987654321', 'Halo, ini contoh greeting'));

  fclose($output);
  exit;
}
