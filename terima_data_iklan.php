<?php
// nama file: terima_data_iklan.php
// WordPress API endpoint untuk menerima data iklan dari apivdcom

// Load WordPress
require_once('../../../wp-load.php');

$API_KEY = 'hutara000';

if ($_POST['key'] !== $API_KEY) {
    http_response_code(403);
    echo '❌ Unauthorized';
    exit;
}

// Validasi data yang diterima
$requiredFields = ['kata_kunci', 'grup_iklan', 'id_grup_iklan', 'nomor_kata_kunci', 'greeting'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo "❌ Missing field: {$field}";
        exit;
    }
}

$kataKunci = sanitize_text_field($_POST['kata_kunci']);
$grupIklan = sanitize_text_field($_POST['grup_iklan']);
$idGrupIklan = sanitize_text_field($_POST['id_grup_iklan']);
$nomorKataKunci = sanitize_text_field($_POST['nomor_kata_kunci']);
$greeting = sanitize_text_field($_POST['greeting']);
$apivdcomId = $_POST['apivdcom_id'] ?? null; // optional untuk tracking

// Gunakan WordPress database connection
global $wpdb;

try {
    $table_name = $wpdb->prefix . 'greeting_ads_data';
    
    // Cek apakah data sudah ada (berdasarkan nomor_kata_kunci sebagai unique identifier)
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE nomor_kata_kunci = %s",
        $nomorKataKunci
    ));
    
    if ($existing) {
        // Update data yang sudah ada
        $result = $wpdb->update(
            $table_name,
            [
                'kata_kunci' => $kataKunci,
                'grup_iklan' => $grupIklan,
                'id_grup_iklan' => $idGrupIklan,
                'greeting' => $greeting
            ],
            ['nomor_kata_kunci' => $nomorKataKunci],
            ['%s', '%s', '%s', '%s'],
            ['%s']
        );
        
        if ($result !== false) {
            echo "✅ Updated: {$kataKunci} → {$greeting}";
        } else {
            throw new Exception("Update failed");
        }
    } else {
        // Insert data baru
        $result = $wpdb->insert(
            $table_name,
            [
                'kata_kunci' => $kataKunci,
                'grup_iklan' => $grupIklan,
                'id_grup_iklan' => $idGrupIklan,
                'nomor_kata_kunci' => $nomorKataKunci,
                'greeting' => $greeting
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result !== false) {
            echo "✅ Inserted: {$kataKunci} → {$greeting}";
        } else {
            throw new Exception("Insert failed");
        }
    }
    
    // Log untuk debugging (opsional)
    error_log("Greeting-ads WordPress: Received data for keyword '{$kataKunci}' with greeting '{$greeting}'");
    
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Database operation failed: " . $e->getMessage();
    error_log("Greeting-ads WordPress DB Error: " . $e->getMessage() . " | wpdb->last_error: " . $wpdb->last_error);
}