<?php
// Load WordPress context
require_once('/home/velocity/domains/velocitydeveloper.com/public_html/wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'rekap_form';

$results = $wpdb->get_results("
  SELECT greeting, COUNT(*) as jumlah
  FROM $table
  WHERE DATE(created_at) = CURDATE()
  GROUP BY greeting
  HAVING jumlah >= 3
");

if ($results) {
  $TOKEN = '7586398632:AAHtBlkuNNqxCcCApjosxBvyqEHJQldlVng';     // Ganti token bot Telegram kamu
  // $CHATID = '260162734'; // Ganti Chat ID kamu
  $CHATID = '785329499'; // id Aditya K

  $pesan = "⚠️ Greeting yang sama muncul >= 3 kali hari ini: ";
  foreach ($results as $row) {
    $pesan .= "\"{$row->greeting}\" ({$row->jumlah}x)\n";
  }

  file_get_contents("https://api.telegram.org/bot$TOKEN/sendMessage?" . http_build_query([
    'chat_id' => $CHATID,
    'text' => $pesan,
    'parse_mode' => 'HTML'
  ]));
}
