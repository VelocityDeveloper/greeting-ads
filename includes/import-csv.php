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

    $results = [];
    $row_index = 1; // Mulai dari 1 (setelah header)

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
      $kata_kunci = isset($data[0]) ? sanitize_text_field($data[0]) : '';
      $grup_iklan = isset($data[1]) ? sanitize_text_field($data[1]) : '';
      $id_grup_iklan = isset($data[2]) ? sanitize_text_field($data[2]) : '';
      $nomor_kata_kunci = isset($data[3]) ? sanitize_text_field($data[3]) : '';
      $greeting_input = isset($data[4]) ? sanitize_text_field($data[4]) : '';

      // Validasi data minimal
      if (empty($kata_kunci) || empty($nomor_kata_kunci)) {
        $results[] = [
          'row' => $row_index,
          'status' => 'Failed',
          'message' => 'Kata Kunci atau Nomor Kata Kunci kosong',
          'data' => implode(', ', $data)
        ];
        $row_index++;
        continue;
      }

      // Cek apakah data sudah ada
      $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, greeting FROM $table_name WHERE kata_kunci = %s AND grup_iklan = %s AND nomor_kata_kunci = %s",
        $kata_kunci,
        $grup_iklan,
        $nomor_kata_kunci
      ));

      if ($existing) {
        // Data sudah ada, cek validitas greeting (harus vID)
        $expected_greeting = 'v' . $existing->id;

        if ($existing->greeting !== $expected_greeting) {
          // Update greeting agar sesuai format vID
          $wpdb->update(
            $table_name,
            ['greeting' => $expected_greeting],
            ['id' => $existing->id]
          );
          $results[] = [
            'row' => $row_index,
            'status' => 'Updated',
            'message' => "Greeting diperbaiki dari '{$existing->greeting}' menjadi '{$expected_greeting}'",
            'data' => "$kata_kunci (ID: {$existing->id})"
          ];
        } else {
          // Data ada dan greeting sudah benar
          $results[] = [
            'row' => $row_index,
            'status' => 'Skipped',
            'message' => 'Data sudah ada dan valid',
            'data' => "$kata_kunci (ID: {$existing->id})"
          ];
        }
      } else {
        // Data baru, insert
        $result = $wpdb->insert(
          $table_name,
          array(
            'kata_kunci' => $kata_kunci,
            'grup_iklan' => $grup_iklan,
            'id_grup_iklan' => $id_grup_iklan,
            'nomor_kata_kunci' => $nomor_kata_kunci,
            'greeting' => $greeting_input, // Sementara insert apa adanya
          )
        );

        if ($result) {
          $new_id = $wpdb->insert_id;
          $expected_greeting = 'v' . $new_id;

          // Selalu update greeting ke format vID untuk konsistensi
          // (kecuali jika kebetulan input user sudah sama persis, tapi update ulang tidak masalah)
          if ($greeting_input !== $expected_greeting) {
            $wpdb->update(
              $table_name,
              ['greeting' => $expected_greeting],
              ['id' => $new_id]
            );
            $msg = "Berhasil insert. Greeting diset ke $expected_greeting";
          } else {
            $msg = "Berhasil insert.";
          }

          $results[] = [
            'row' => $row_index,
            'status' => 'Inserted',
            'message' => $msg,
            'data' => "$kata_kunci (ID: $new_id)"
          ];
        } else {
          $results[] = [
            'row' => $row_index,
            'status' => 'Failed',
            'message' => 'Gagal insert ke database: ' . $wpdb->last_error,
            'data' => implode(', ', $data)
          ];
        }
      }
      $row_index++;
    }

    fclose($handle);

    // Tampilkan Laporan dalam Modal
    $output = '';

    // Hitung statistik ringkas
    $stats = [
      'Total' => count($results),
      'Inserted' => 0,
      'Updated' => 0,
      'Skipped' => 0,
      'Failed' => 0
    ];
    foreach ($results as $res) {
      if (isset($stats[$res['status']])) {
        $stats[$res['status']]++;
      }
    }

    // CSS untuk Modal
    $output .= '
    <style>
        .ga-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ga-modal-content {
            background: #fff;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .ga-modal-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
        }
        .ga-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        .ga-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .ga-modal-body {
            padding: 24px;
            overflow-y: auto;
        }
        .ga-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .ga-stat-card {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
        }
        .ga-stat-value {
            font-size: 20px;
            font-weight: bold;
            display: block;
        }
        .ga-stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .ga-stat-inserted .ga-stat-value { color: #10b981; }
        .ga-stat-updated .ga-stat-value { color: #3b82f6; }
        .ga-stat-failed .ga-stat-value { color: #ef4444; }
    </style>
    ';

    // HTML Modal
    $output .= '<div id="ga-import-report-modal" class="ga-modal-overlay">';
    $output .= '<div class="ga-modal-content">';

    // Header
    $output .= '<div class="ga-modal-header">';
    $output .= '<h3 class="ga-modal-title">Laporan Hasil Import CSV</h3>';
    $output .= '<button type="button" class="ga-modal-close" onclick="document.getElementById(\'ga-import-report-modal\').remove()">×</button>';
    $output .= '</div>';

    // Body
    $output .= '<div class="ga-modal-body">';

    // Statistik
    $output .= '<div class="ga-stat-grid">';
    $output .= '<div class="ga-stat-card"><span class="ga-stat-value">' . $stats['Total'] . '</span><span class="ga-stat-label">Total Baris</span></div>';
    $output .= '<div class="ga-stat-card ga-stat-inserted"><span class="ga-stat-value">' . $stats['Inserted'] . '</span><span class="ga-stat-label">Sukses Insert</span></div>';
    $output .= '<div class="ga-stat-card ga-stat-updated"><span class="ga-stat-value">' . $stats['Updated'] . '</span><span class="ga-stat-label">Diperbaiki</span></div>';
    $output .= '<div class="ga-stat-card"><span class="ga-stat-value">' . $stats['Skipped'] . '</span><span class="ga-stat-label">Dilewati</span></div>';
    $output .= '<div class="ga-stat-card ga-stat-failed"><span class="ga-stat-value">' . $stats['Failed'] . '</span><span class="ga-stat-label">Gagal</span></div>';
    $output .= '</div>';

    // Tabel Detail
    $output .= '<table class="wp-list-table widefat fixed striped">';
    $output .= '<thead><tr><th style="width:50px;">Baris</th><th style="width:100px;">Status</th><th>Pesan</th><th>Data (Kata Kunci / ID)</th></tr></thead>';
    $output .= '<tbody>';
    foreach ($results as $res) {
      $status_color = 'black';
      $bg_color = '';
      if ($res['status'] === 'Inserted') {
        $status_color = '#059669';
        $bg_color = '#ecfdf5';
      } elseif ($res['status'] === 'Updated') {
        $status_color = '#2563eb';
        $bg_color = '#eff6ff';
      } elseif ($res['status'] === 'Failed') {
        $status_color = '#dc2626';
        $bg_color = '#fef2f2';
      } elseif ($res['status'] === 'Skipped') {
        $status_color = '#6b7280';
      }

      $output .= "<tr>";
      $output .= "<td>{$res['row']}</td>";
      $output .= "<td><span style='color: {$status_color}; font-weight:bold; background: {$bg_color}; padding: 2px 6px; border-radius: 4px; font-size: 11px;'>{$res['status']}</span></td>";
      $output .= "<td>{$res['message']}</td>";
      $output .= "<td>{$res['data']}</td>";
      $output .= "</tr>";
    }
    $output .= '</tbody></table>';

    $output .= '</div>'; // End Body
    $output .= '</div>'; // End Content
    $output .= '</div>'; // End Overlay

    return $output;
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
