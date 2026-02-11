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

    // Dapatkan nilai counter greeting format 'vXXX' tertinggi saat ini
    $max_v_number = $wpdb->get_var("
      SELECT MAX(CAST(SUBSTRING(greeting, 2) AS UNSIGNED)) 
      FROM $table_name 
      WHERE greeting REGEXP '^v[0-9]+$'
    ");
    $current_v_counter = intval($max_v_number); // Akan 0 jika null

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
      $greeting_input = isset($data[4]) ? sanitize_text_field($data[4]) : '';

      // Logika auto-increment greeting vXXX jika kosong
      if (empty($greeting_input)) {
        $current_v_counter++;
        $greeting_input = 'v' . $current_v_counter;
      } else {
        // Jika user mengisi greeting dengan format vXXX, update counter agar tidak bentrok
        if (preg_match('/^v(\d+)$/', $greeting_input, $matches)) {
          $val = intval($matches[1]);
          if ($val > $current_v_counter) {
            $current_v_counter = $val;
          }
        }
      }

      // Periksa apakah greeting sudah ada di database
      $existing_greeting = $wpdb->get_var("SELECT greeting FROM $table_name WHERE kata_kunci = '" . sanitize_text_field($data[0]) . "' AND grup_iklan = '" . sanitize_text_field($data[1]) . "' AND nomor_kata_kunci = '" . sanitize_text_field($data[3]) . "' ");
      if (empty($existing_greeting)) {

        // Jika greeting belum ada, insert data
        $wpdb->insert(
          $table_name,
          array(
            'kata_kunci' => sanitize_text_field($data[0]),
            'grup_iklan' => sanitize_text_field($data[1]),
            'id_grup_iklan' => sanitize_text_field($data[2]),
            'nomor_kata_kunci' => sanitize_text_field($data[3]),
            'greeting' => $greeting_input,
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
