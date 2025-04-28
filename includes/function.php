<?php

/**
 * Mengirim pesan ke beberapa chat_id Telegram.
 *
 * @param string $message Isi pesan yang ingin dikirim.
 * @param array $chatIds Daftar chat_id tujuan.
 * @return string Status pengiriman ('sukses' atau pesan error)
 */
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
 * @param string $jenis Deskripsi jenis website.
 * @return string 'valid', 'ngawur', atau 'unknown' tergantung hasil analisis GPT.
 */
function validasi_jenis_web($jenis)
{
  $apiKey = get_option('openai_api_key');
  $customPrompt = get_option('prompt_jenis_web') . 'Input: {{INPUT}}';

  if (empty($apiKey) || empty($customPrompt)) {
    return 'unknown'; // fallback kalau setting kosong
  }

  $prompt = str_replace('{{INPUT}}', trim($jenis), $customPrompt);

  $payload = [
    'model' => 'gpt-4.1',
    'messages' => [
      ['role' => 'system', 'content' => 'You are a helpful assistant that gives concise and clear recommendations.'],
      ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.7,
    'top_p' => 1,
    'max_tokens' => 1024,
    'presence_penalty' => 0,
    'frequency_penalty' => 0
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

  if ($gptReply === 'valid') {
    return 'valid';
  } elseif ($gptReply === 'ngawur') {
    return 'ngawur';
  } else {
    return 'unknown'; // jika jawaban aneh
  }
}

// validasi nomor wa
function validasi_no_wa($no_wa)
{
  // harus diawali dengan 62 atau 08
  if (substr($no_wa, 0, 2) !== '62' && substr($no_wa, 0, 2) !== '08') {
    return 'ngawur';
  }
  // cek panjang nomor antara 10 sampai 13
  if (strlen($no_wa) < 10 || strlen($no_wa) > 13) {
    return 'ngawur';
  }
  // cek apakah hanya angka
  if (!is_numeric($no_wa)) {
    return 'ngawur';
  }
  return 'valid';
}
