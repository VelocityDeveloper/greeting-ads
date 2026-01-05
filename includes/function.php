<?php

/**
 * Mengirim pesan ke beberapa chat_id Telegram.
 *
 * @param string $message Isi pesan yang ingin dikirim.
 * @param array $chatIds Daftar chat_id tujuan.
 * @return string Status pengiriman ('sukses' atau pesan error)
 */

function get_ads_logic()
{
  if (
    !is_admin() && (
      isset($_COOKIE['_gcl_aw']) ||
      isset($_COOKIE['greeting']) ||
      isset($_GET['gclid']) ||
      (isset($_GET['utm_source']) && $_GET['utm_source'] == 'google' &&  isset($_GET['utm_medium'])) ||
      (isset($_GET['utm_source']) && $_GET['utm_source'] == 'google' &&  isset($_GET['utm_content']))
    )
  ) {
    return true;
  }
}

function save_utm_cookies()
{

  // Pastikan berjalan di frontend dan parameter utama ada
  if (get_ads_logic()) {
    // Konfigurasi cookie
    $expiration = time() + 30 * DAY_IN_SECONDS; // 30 hari
    $path = '/';
    $domain = parse_url(get_site_url(), PHP_URL_HOST); // Ambil domain dari site URL
    $secure = is_ssl(); // Aktifkan secure flag jika HTTPS
    $httponly = true; // Cegah akses JavaScript

    setcookie('traffic', 'ads', [
      'expires' => $expiration,
      'path' => $path,
      'domain' => $domain,
      'secure' => $secure,
      'httponly' => $httponly,
      'samesite' => 'Lax'
    ]);

    // Sanitasi nilai parameter
    $utm_medium = sanitize_text_field($_GET['utm_medium']);
    $utm_content = sanitize_text_field($_GET['utm_content']);

    // Ekstrak angka dari utm_medium menggunakan regex
    $utm_medium = trim($utm_medium);
    if (preg_match('/kwd-(\d+)/', $utm_medium, $matches)) {
      $utm_medium = $matches[1];
    } else {
      $utm_medium = preg_replace('/[^0-9]/', '', $utm_medium);
    }

    // Query database untuk mencocokkan utm_content dan nomor kata kunci
    global $wpdb;
    $table_name = $wpdb->prefix . 'greeting_ads_data';

    $query = $wpdb->prepare(
      "SELECT greeting FROM $table_name WHERE id_grup_iklan = '%s' AND nomor_kata_kunci = '%d'",
      $utm_content,
      $utm_medium
    );

    $result = $wpdb->get_var($query);
    // echo '<pre>' . print_r($result, true) . '</pre>';

    // Jika ada hasil yang cocok, simpan kolom greeting ke cookie
    if ($result) {
      $greeting = sanitize_text_field($result);

      // set utm_content ke cookie
      setcookie('utm_content', $utm_content, [
        'expires' => $expiration,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
      ]);

      // set utm_medium ke cookie
      setcookie('utm_medium', $utm_medium, [
        'expires' => $expiration,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
      ]);

      // Set cookie greeting
      setcookie('greeting', $greeting, [
        'expires' => $expiration,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
      ]);
    }
  }
}
add_action('init', 'save_utm_cookies');


function check_greeting_langsung()
{
  // Sanitasi nilai parameter
  $utm_medium = sanitize_text_field($_GET['utm_medium']);
  $utm_content = sanitize_text_field($_GET['utm_content']);

  // Ekstrak angka dari utm_medium menggunakan regex
  $utm_medium = trim($utm_medium);
  if (preg_match('/kwd-(\d+)/', $utm_medium, $matches)) {
    $utm_medium = $matches[1];
  } else {
    $utm_medium = preg_replace('/[^0-9]/', '', $utm_medium);
  }

  // Query database untuk mencocokkan utm_content dan nomor kata kunci
  global $wpdb;
  $table_name = $wpdb->prefix . 'greeting_ads_data';

  $query = $wpdb->prepare(
    "SELECT greeting FROM $table_name WHERE id_grup_iklan = '%s' AND nomor_kata_kunci = '%d'",
    $utm_content,
    $utm_medium
  );

  $result = $wpdb->get_var($query);
  // echo '<pre>' . print_r($result, true) . '</pre>';

  // Jika ada hasil yang cocok, simpan kolom greeting ke cookie
  if ($result) {
    $greeting = sanitize_text_field($result);
    return $greeting;
  }
}
function kirim_telegram($message, array $chatIds)
{
  if (empty($chatIds)) {
    return 'Tidak ada chat ID tujuan.';
  }

  $botToken = TOKEN_TELEGRAM_BOT; // Pastikan ini didefinisikan di wp-config.php atau functions.php
  $url = "https://api.telegram.org/bot$botToken/sendMessage";

  $pesanStatus = '';

  foreach ($chatIds as $chatId) {
    $data = [
      'chat_id' => $chatId,
      'text' => $message,
      'parse_mode' => 'HTML', // Bisa juga 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => $data,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
      $pesanStatus = 'Curl error: ' . curl_error($ch);
    } else {
      $result = json_decode($response, true);
      if (!empty($result['ok'])) {
        $pesanStatus = 'Pesan berhasil dikirim!';
      } else {
        $pesanStatus = 'Gagal mengirim pesan: ' . ($result['description'] ?? 'Unknown error');
      }
    }
    curl_close($ch);
  }

  return $pesanStatus;
}

/**
 * Melakukan validasi jenis website menggunakan OpenAI GPT API.
 *
 * @param string $input Deskripsi jenis website.
 * @return string 'valid' atau 'dilarang' tergantung hasil analisis GPT.
 */
function validasi_jenis_web($input)
{
  // TOGGLE: Ubah ke true jika ingin mengaktifkan validasi AI kembali
  $gunakan_validasi_ai = false;

  if (!$gunakan_validasi_ai) {
    return 'valid';
  }

  $apiKey = get_option('openai_api_key');
  //skrip asli>> $prompt = get_option('prompt_jenis_web');
  // skrip toro>>
  $prompt = stripslashes(get_option('prompt_jenis_web'));

  if (empty($apiKey) || empty($prompt)) {
    return 'unknown'; // fallback kalau setting kosong
  }

  $payload = [
    'model' => 'gpt-5',
    'messages' => [
      ['role' => 'system', 'content' => $prompt],
      ['role' => 'user', 'content' => $input]
    ]
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
  ]);

  $response = curl_exec($ch);
  if (curl_errno($ch)) {
    curl_close($ch);
    return 'unknown'; // gagal koneksi
  }

  $result = json_decode($response, true);
  curl_close($ch);

  $gptReply = strtolower(trim($result['choices'][0]['message']['content'] ?? ''));

  return in_array($gptReply, ['valid', 'dilarang']) ? $gptReply : 'unknown';
}

// validasi nomor wa
function validasi_no_wa($no_wa)
{
  // harus diawali dengan 62 atau 08
  if (substr($no_wa, 0, 2) !== '62' && substr($no_wa, 0, 2) !== '08') {
    return 'invalid';
  }
  // cek panjang nomor antara 10 sampai 13
  if (strlen($no_wa) < 10 || strlen($no_wa) > 13) {
    return 'invalid';
  }
  // cek apakah hanya angka
  if (!is_numeric($no_wa)) {
    return 'invalid';
  }
  return 'valid';
}
