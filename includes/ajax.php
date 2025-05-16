<?php

add_action('wp_ajax_rekap_chat_form', 'rekap_chat_form');
add_action('wp_ajax_nopriv_rekap_chat_form', 'rekap_chat_form');

function rekap_chat_form()
{
  global $wpdb;

  $nama = sanitize_text_field($_POST['nama']);
  $no_whatsapp = sanitize_text_field($_POST['no_whatsapp']);
  $jenis_website = sanitize_text_field($_POST['jenis_website']);
  $via = sanitize_text_field($_POST['via']);

  // Ambil data tambahan dari cookie
  $utm_content = $_COOKIE['utm_content'] ?? '';
  $utm_medium = $_COOKIE['utm_medium'] ?? '';
  $gclid = $_COOKIE['_gcl_aw'] ?? '';
  $greeting = $_COOKIE['greeting'] ?? 'vx';
  $greeting = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $greeting : 'v0';
  $sumber = ($greeting == 'v0') ? 'WA2' : 'WA ADS';
  // Kirim ke Telegram hanya kalau greeting bukan 'v0' - modif by mas toro tanpa cek ai langsung kirim telegram
  $pesan = 'Greeting kosong, pesan tidak dikirim.';
  // if ($greeting !== 'v0') {
  $messageText = "Ada Chat Baru dari: <b>{$nama}</b>\n"
    . "No. WhatsApp: <b>{$no_whatsapp}</b>\n"
    . "Jenis Web: <b>{$jenis_website}</b>\n"
    . "Greeting: <b>{$greeting}</b>\n"
    . "Sumber: <b>{$sumber}</b>\n\n"
    . "gclid: <b>{$gclid}</b>\n";

  $chatIds = [
    // '184441126', //contoh: hp cs
    // '785329499', //contoh: telegram aditya k
    '-944668693'   // contoh: grup
  ];

  // sementara mematikan bot telegram
  $pesan = kirim_telegram($messageText, $chatIds);
  // $pesan = 'Pesan berhasil dikirim!';
  // }

  // Validasi AI terhadap jenis website
  $ai_result = validasi_jenis_web($jenis_website);
  $wa_result = validasi_no_wa($no_whatsapp);

  // Insert ke database
  $wpdb->insert(
    $wpdb->prefix . 'rekap_form',
    [
      'nama' => $nama,
      'no_whatsapp' => $no_whatsapp,
      'jenis_website' => $jenis_website,
      'ai_result' => $ai_result,
      'via' => $via,
      'utm_content' => $utm_content,
      'utm_medium' => $utm_medium,
      'greeting' => $greeting,
      'created_at' => current_time('mysql'),
    ]
  );
  // kirim log ke telegram jika ada mysql error
  if ($wpdb->last_error) {
    $id_reports = [
      '260162734', // mastoro
      '785329499' // aditya k
    ];
    $log_message = "MySQL Error: " . $wpdb->last_error;
    kirim_telegram($log_message, $id_reports);
  }


  // Kirim ke Telegram hanya kalau greeting bukan 'v0' - modif by mas toro setelah cek ai hanya kirim STATUSNYA AJA ke telegram
  $pesan = 'Greeting kosong, pesan tidak dikirim.';
  //if ($greeting !== 'v0') {

  // cek ai status
  $statusText = "";

  if ($ai_result == 'ngawur') {
    $statusText .= "<b style='font-weight: bold;'>❌ Gagal WA</b>\n";
  }
  if ($ai_result == 'dilarang') {
    $statusText .= "<b style='font-weight: bold;'>⚠️ Gagal WA</b>\n";
  }

  $chatIds = [
    // '184441126', //contoh: hp cs
    // '785329499', //contoh: telegram aditya k
    '-944668693'   // contoh: grup
  ];

  $pesan = kirim_telegram($statusText, $chatIds);
  // $pesan = 'Pesan berhasil dikirim!';
  //}


  // Balikan response Ajax
  wp_send_json_success([
    'ai_result' => $ai_result,
    'wa_result' => $wa_result,
    'pesan' => $pesan

  ]);
}

add_action('wp_ajax_cek_jenis_website_ai', 'cek_jenis_website_ai_handler');
function cek_jenis_website_ai_handler()
{
  check_ajax_referer('cek_jenis_website_ai_nonce');

  global $wpdb;
  $table = $wpdb->prefix . 'rekap_form';

  $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

  if (empty($ids)) {
    wp_send_json_error('Tidak ada ID yang dikirim.');
    return;
  }

  $results = $wpdb->get_results("SELECT id, jenis_website FROM $table WHERE id IN (" . implode(',', $ids) . ")");

  if (!$results) {
    wp_send_json_error('Data tidak ditemukan.');
    return;
  }
  $apiKey = get_option('openai_api_key');
  $customPrompt = get_option('prompt_jenis_web') . 'Input: {{INPUT}}';

  $responses = [];

  foreach ($results as $row) {
    $input = trim($row->jenis_website);

    $gptReply = validasi_jenis_web($input);

    // Update kolom ai_result
    $wpdb->update($table, ['ai_result' => $gptReply], ['id' => $row->id]);

    $statusLabel = match ($gptReply) {
      'valid'    => '✅',
      'ngawur'   => '❌',
      'dilarang' => '⚠️',
      default    => '',
    };
    $responses[] = "ID {$row->id}: <strong>{$statusLabel}</strong> ({$input})";

    // curl_close($ch);
  }

  echo '<div class="updated"><ul><li>' . implode('</li><li>', $responses) . '</li></ul></div>';
  wp_die();
}
