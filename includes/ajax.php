<?php

add_action('wp_ajax_rekap_chat_form', 'rekap_chat_form');
add_action('wp_ajax_nopriv_rekap_chat_form', 'rekap_chat_form');
add_action('wp_ajax_vd_async_track_wa_click', 'vd_async_track_wa_click');
add_action('wp_ajax_nopriv_vd_async_track_wa_click', 'vd_async_track_wa_click');

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
  $label = $_COOKIE['label'] ?? '';

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
    . "Sumber: <b>{$sumber}</b>\n"
    . "label: <b>{$label}</b>\n\n"
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
      'gclid' => $gclid ?? null,
      'label' => $label ?? null,
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

function vd_async_track_wa_click()
{
  $nonce_valid = check_ajax_referer('vd_async_wa_click', 'nonce', false);
  if (!$nonce_valid) {
    wp_send_json_error(['message' => 'Invalid nonce'], 403);
  }

  $is_ads = get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads');
  if (!$is_ads) {
    wp_send_json_success(['logged' => false, 'reason' => 'not_ads']);
  }

  $greeting = isset($_POST['greeting']) ? sanitize_text_field(wp_unslash($_POST['greeting'])) : '';
  $greeting = trim($greeting);
  if ($greeting === '') {
    $greeting = 'vx';
  }

  $event_id = isset($_POST['event_id']) ? sanitize_text_field(wp_unslash($_POST['event_id'])) : '';
  $event_id = trim($event_id);
  if ($event_id === '' && function_exists('vd_generate_whatsapp_event_id')) {
    $event_id = vd_generate_whatsapp_event_id();
  }

  $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
  if ($page_url === '' && isset($_SERVER['HTTP_REFERER'])) {
    $page_url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
  }

  $payload = [
    'event_id' => $event_id,
    'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown',
    'referer' => $page_url,
    'greeting' => $greeting,
    'created_at' => current_time('mysql'),
  ];

  if (empty($payload['ip_address'])) {
    $payload['ip_address'] = 'unknown';
  }

  if (empty($payload['user_agent'])) {
    $payload['user_agent'] = 'unknown';
  }

  $scheduled = function_exists('vd_schedule_whatsapp_click_job')
    ? vd_schedule_whatsapp_click_job($payload, 0, 1)
    : false;

  if (!$scheduled) {
    wp_send_json_error(['logged' => false, 'message' => 'Failed to schedule async click log'], 500);
  }

  wp_send_json_success(['logged' => true]);
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

  $responses = [];

  foreach ($results as $row) {
    $input = trim($row->jenis_website);

    $gptReply = validasi_jenis_web($input);

    // Update kolom ai_result
    $wpdb->update($table, ['ai_result' => $gptReply], ['id' => $row->id]);

    $statusLabel = match ($gptReply) {
      'valid'    => '✅',
      'dilarang' => '⚠️',
      default    => '',
    };
    $responses[] = "ID {$row->id}: <strong>{$statusLabel}</strong> ({$input})";
  }

  echo '<div class="updated"><ul><li>' . implode('</li><li>', $responses) . '</li></ul></div>';
  wp_die();
}

add_action('wp_ajax_update_inline_status', 'update_inline_status_handler');
function update_inline_status_handler()
{
  check_ajax_referer('update_inline_status_nonce');

  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  $id = intval($_POST['id']);
  $status = sanitize_text_field($_POST['status']);

  if (empty($id)) {
    wp_send_json_error('ID tidak valid.');
    return;
  }

  // Update status in database
  $result = $wpdb->update(
    $table_name,
    ['status' => $status],
    ['id' => $id],
    ['%s'],
    ['%d']
  );

  if ($result === false) {
    wp_send_json_error('Gagal memperbarui status di database.');
    return;
  }

  wp_send_json_success(['status' => $status]);
}

if (!defined('VD_WA_CLICK_CRON_HOOK')) {
  define('VD_WA_CLICK_CRON_HOOK', 'vd_process_whatsapp_click_job');
}

if (!defined('VD_WA_CLICK_RETRY_DELAYS')) {
  define('VD_WA_CLICK_RETRY_DELAYS', '60,300,900,3600,21600');
}

function vd_get_whatsapp_retry_delays()
{
  $delays = array_map('intval', explode(',', VD_WA_CLICK_RETRY_DELAYS));
  $delays = array_values(array_filter($delays, function ($delay) {
    return $delay > 0;
  }));

  return empty($delays) ? [60, 300, 900, 3600, 21600] : $delays;
}

function vd_generate_whatsapp_event_id()
{
  if (function_exists('wp_generate_uuid4')) {
    return wp_generate_uuid4();
  }

  return uniqid('vdwa_', true);
}

function vd_normalize_whatsapp_click_payload($payload)
{
  $payload = is_array($payload) ? $payload : [];

  $normalized = [
    'event_id' => isset($payload['event_id']) ? sanitize_text_field($payload['event_id']) : '',
    'ip_address' => isset($payload['ip_address']) ? sanitize_text_field($payload['ip_address']) : 'unknown',
    'user_agent' => isset($payload['user_agent']) ? sanitize_text_field($payload['user_agent']) : 'unknown',
    'referer' => isset($payload['referer']) ? esc_url_raw($payload['referer']) : '',
    'greeting' => isset($payload['greeting']) ? sanitize_text_field((string) $payload['greeting']) : '',
    'created_at' => isset($payload['created_at']) ? sanitize_text_field($payload['created_at']) : current_time('mysql'),
    'attempt' => isset($payload['attempt']) ? max(0, intval($payload['attempt'])) : 0,
  ];

  if ($normalized['ip_address'] === '') {
    $normalized['ip_address'] = 'unknown';
  }

  if ($normalized['user_agent'] === '') {
    $normalized['user_agent'] = 'unknown';
  }

  return $normalized;
}

function vd_schedule_whatsapp_click_job($payload, $attempt = 0, $delay_seconds = 1)
{
  $payload = vd_normalize_whatsapp_click_payload($payload);
  if (empty($payload['event_id'])) {
    return false;
  }

  $payload['attempt'] = max(0, intval($attempt));
  $timestamp = time() + max(1, intval($delay_seconds));

  // Prevent accidental duplicate scheduling for the same exact payload.
  if (wp_next_scheduled(VD_WA_CLICK_CRON_HOOK, [$payload])) {
    return true;
  }

  return (bool) wp_schedule_single_event($timestamp, VD_WA_CLICK_CRON_HOOK, [$payload]);
}

function vd_upsert_whatsapp_click_status($payload, $status, $retry_count, $error_message = '')
{
  global $wpdb;

  $payload = vd_normalize_whatsapp_click_payload($payload);
  if (empty($payload['event_id'])) {
    return false;
  }

  $table_name = $wpdb->prefix . VD_WA_CLICKS_TABLE;
  $status = sanitize_text_field($status);
  $retry_count = max(0, intval($retry_count));
  $error_message = sanitize_text_field($error_message);

  $existing = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT id, status FROM $table_name WHERE event_id = %s LIMIT 1",
      $payload['event_id']
    )
  );

  if ($existing && $existing->status === 'success' && $status !== 'success') {
    return true;
  }

  $data = [
    'event_id' => $payload['event_id'],
    'ip_address' => $payload['ip_address'],
    'user_agent' => $payload['user_agent'],
    'referer' => $payload['referer'],
    'greeting' => $payload['greeting'],
    'status' => $status,
    'retry_count' => $retry_count,
    'last_error' => $error_message,
    'created_at' => $payload['created_at'],
  ];

  if ($existing) {
    $update_data = [
      'greeting' => $payload['greeting'],
      'status' => $status,
      'retry_count' => $retry_count,
      'last_error' => $error_message,
    ];

    $result = $wpdb->update(
      $table_name,
      $update_data,
      ['event_id' => $payload['event_id']],
      ['%s', '%s', '%d', '%s'],
      ['%s']
    );

    return $result !== false;
  }

  $insert_result = $wpdb->insert(
    $table_name,
    $data,
    ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
  );

  return $insert_result !== false;
}

function vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message)
{
  $payload = vd_normalize_whatsapp_click_payload($payload);
  $delays = vd_get_whatsapp_retry_delays();
  $error_message = sanitize_text_field($error_message);
  $next_attempt = max(0, intval($attempt)) + 1;

  // Attempt index starts from 0; retries use configured delays sequentially.
  if ($next_attempt <= count($delays)) {
    $retry_delay = $delays[$next_attempt - 1];
    vd_upsert_whatsapp_click_status($payload, 'pending', $next_attempt, $error_message);
    $scheduled = vd_schedule_whatsapp_click_job($payload, $next_attempt, $retry_delay);

    if (!$scheduled) {
      vd_upsert_whatsapp_click_status($payload, 'failed', $next_attempt, 'Failed to schedule retry job');
    }

    return $scheduled;
  }

  return vd_upsert_whatsapp_click_status($payload, 'failed', $next_attempt, $error_message);
}

add_action(VD_WA_CLICK_CRON_HOOK, 'vd_process_whatsapp_click_job', 10, 1);
function vd_process_whatsapp_click_job($payload = [])
{
  global $wpdb;

  if (function_exists('vd_maybe_upgrade_whatsapp_clicks_table')) {
    vd_maybe_upgrade_whatsapp_clicks_table();
  }

  $payload = vd_normalize_whatsapp_click_payload($payload);
  $attempt = max(0, intval($payload['attempt']));
  if (empty($payload['event_id'])) {
    return;
  }

  $table_name = $wpdb->prefix . VD_WA_CLICKS_TABLE;
  $existing = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT id, status FROM $table_name WHERE event_id = %s LIMIT 1",
      $payload['event_id']
    )
  );

  if ($existing && $existing->status === 'success') {
    return;
  }

  if (!$existing) {
    $created_pending = vd_upsert_whatsapp_click_status($payload, 'pending', $attempt, '');
    if (!$created_pending) {
      $error_message = $wpdb->last_error ?: 'Failed to create pending WA click row';
      vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);
      return;
    }
  } else {
    $marked_pending = vd_upsert_whatsapp_click_status($payload, 'pending', $attempt, '');
    if (!$marked_pending) {
      $error_message = $wpdb->last_error ?: 'Failed to mark pending WA click row';
      vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);
      return;
    }
  }

  $mark_success = vd_upsert_whatsapp_click_status($payload, 'success', $attempt, '');
  if (!$mark_success) {
    $error_message = $wpdb->last_error ?: 'Failed to finalize WA click row';
    vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);
  }
}
