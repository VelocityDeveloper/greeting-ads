<?php

/**
 * API untuk mengirimkan data JSON ke tabel greeting ads
 */

add_action('rest_api_init', 'register_greeting_api');

function register_greeting_api()
{
  register_rest_route('greeting/v1', '/get', [
    'methods' => 'GET',
    'callback' => 'get_greeting',
    'permission_callback' => 'validate_greeting_token'
  ]);
  
  register_rest_route('greeting/v1', '/all', [
    'methods' => 'GET',
    'callback' => 'get_all_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);
  
  register_rest_route('greeting/v1', '/sync', [
    'methods' => 'GET',
    'callback' => 'get_sync_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/validation-stats', [
    'methods' => 'GET',
    'callback' => 'get_greeting_validation_stats',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/test-validation', [
    'methods' => 'GET',
    'callback' => 'test_validation_stats',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/form-data', [
    'methods' => 'GET',
    'callback' => 'get_form_data',
    'permission_callback' => 'validate_greeting_token'
  ]);
}

function validate_greeting_token()
{
  // Ganti dengan token rahasia kamu
  $expected_token = 'c2e1a7f62f8147e48a1c3f960bdcb176';

  // Ambil header Authorization (Bearer token)
  $headers = getallheaders();

  if (!isset($headers['Authorization'])) {
    return new WP_Error('no_auth_header', 'Authorization header missing', ['status' => 403]);
  }

  $auth_header = trim($headers['Authorization']);

  // Cocokkan format: Bearer <token>
  if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $provided_token = $matches[1];

    if ($provided_token === $expected_token) {
      return true;
    }
  }

  return new WP_Error('invalid_token', 'Invalid API token', ['status' => 403]);
}

function get_greeting()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'greeting_ads_data';
  $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
  return rest_ensure_response($data);
}

function get_all_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  
  // Base query
  $query = "SELECT * FROM $table_name WHERE 1=1";
  $query_params = [];
  
  // Filter parameters
  $kata_kunci = $request->get_param('kata_kunci');
  $grup_iklan = $request->get_param('grup_iklan');
  $id_grup_iklan = $request->get_param('id_grup_iklan');
  $nomor_kata_kunci = $request->get_param('nomor_kata_kunci');
  $greeting = $request->get_param('greeting');
  
  // Pagination parameters
  $page = max(1, (int)$request->get_param('page'));
  $per_page = min(100, max(1, (int)$request->get_param('per_page') ?: 50));
  $offset = ($page - 1) * $per_page;
  
  // Search parameter
  $search = $request->get_param('search');
  
  // Build WHERE conditions
  if (!empty($kata_kunci)) {
    $query .= " AND kata_kunci LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($kata_kunci) . '%';
  }
  
  if (!empty($grup_iklan)) {
    $query .= " AND grup_iklan LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($grup_iklan) . '%';
  }
  
  if (!empty($id_grup_iklan)) {
    $query .= " AND id_grup_iklan = %s";
    $query_params[] = $id_grup_iklan;
  }
  
  if (!empty($nomor_kata_kunci)) {
    $query .= " AND nomor_kata_kunci = %s";
    $query_params[] = $nomor_kata_kunci;
  }
  
  if (!empty($greeting)) {
    $query .= " AND greeting LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($greeting) . '%';
  }
  
  // Global search across all text fields
  if (!empty($search)) {
    $query .= " AND (kata_kunci LIKE %s OR grup_iklan LIKE %s OR id_grup_iklan LIKE %s OR nomor_kata_kunci LIKE %s OR greeting LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
  }
  
  // Count total records for pagination
  $count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
  $total_items = 0;
  if (!empty($query_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
  } else {
    $total_items = $wpdb->get_var($count_query);
  }
  
  // Add ORDER BY and LIMIT
  $query .= " ORDER BY id DESC LIMIT %d OFFSET %d";
  $query_params[] = $per_page;
  $query_params[] = $offset;
  
  // Execute main query
  if (!empty($query_params)) {
    $data = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
  } else {
    $data = $wpdb->get_results($query, ARRAY_A);
  }
  
  // Prepare response with pagination info
  $response_data = [
    'data' => $data,
    'pagination' => [
      'total_items' => (int)$total_items,
      'total_pages' => ceil($total_items / $per_page),
      'current_page' => $page,
      'per_page' => $per_page
    ]
  ];
  
  return rest_ensure_response($response_data);
}

function get_sync_greeting_data($request)
{
  global $wpdb;
  $greeting_table = $wpdb->prefix . GREETING_ADS_TABLE;
  $rekap_table = $wpdb->prefix . 'rekap_form';

  // Format parameter - json, csv, xml (default: json)
  $format = $request->get_param('format') ?: 'json';

  // Limit parameter untuk mencegah overload (default: no limit, max: 10000)
  $limit = $request->get_param('limit');
  if ($limit && $limit > 10000) {
    $limit = 10000;
  }

  // Use LEFT JOIN to get data from rekap_form table with device info
  // Base query - prioritize rekap_form data since it has device info
  $query = "SELECT
    r.id, r.nama, r.no_whatsapp, r.jenis_website, r.via as perangkat,
    r.utm_content, r.utm_medium, r.greeting, r.status, r.ai_result, r.created_at,
    g.kata_kunci, g.grup_iklan, g.id_grup_iklan, g.nomor_kata_kunci
    FROM $rekap_table r
    LEFT JOIN $greeting_table g ON r.greeting = g.greeting
    WHERE 1=1";

  $query_params = [];

  // Filter berdasarkan ID range untuk chunked sync
  $id_from = $request->get_param('id_from');
  $id_to = $request->get_param('id_to');

  if ($id_from) {
    $query .= " AND r.id >= %d";
    $query_params[] = (int)$id_from;
  }

  if ($id_to) {
    $query .= " AND r.id <= %d";
    $query_params[] = (int)$id_to;
  }

  // Order by ID untuk konsistensi
  $query .= " ORDER BY r.id DESC";

  // Add limit if specified
  if ($limit) {
    $query .= " LIMIT %d";
    $query_params[] = (int)$limit;
  }

  // Execute query
  if (!empty($query_params)) {
    $data = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
  } else {
    $data = $wpdb->get_results($query, ARRAY_A);
  }

  // Get total count
  $count_query = "SELECT COUNT(*) FROM $rekap_table WHERE 1=1";
  $count_params = [];

  if ($id_from) {
    $count_query .= " AND id >= %d";
    $count_params[] = (int)$id_from;
  }

  if ($id_to) {
    $count_query .= " AND id <= %d";
    $count_params[] = (int)$id_to;
  }

  if (!empty($count_params)) {
    $total_records = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
  } else {
    $total_records = $wpdb->get_var($count_query);
  }
  
  $response_data = [
    'success' => true,
    'total_records' => (int)$total_records,
    'returned_records' => count($data),
    'data' => $data,
    'sync_info' => [
      'timestamp' => current_time('mysql'),
      'format' => $format,
      'id_range' => [
        'from' => $id_from ?: 'start',
        'to' => $id_to ?: 'end'
      ]
    ]
  ];
  
  // Return data based on format
  switch ($format) {
    case 'csv':
      return generate_csv_response($data);
    case 'xml':
      return generate_xml_response($response_data);
    default:
      return rest_ensure_response($response_data);
  }
}

function generate_csv_response($data)
{
  if (empty($data)) {
    return new WP_Error('no_data', 'No data available for CSV export', ['status' => 404]);
  }
  
  $csv_output = '';
  
  // Header
  $headers = array_keys($data[0]);
  $csv_output .= implode(',', $headers) . "\n";
  
  // Data rows
  foreach ($data as $row) {
    $csv_row = [];
    foreach ($row as $value) {
      $csv_row[] = '"' . str_replace('"', '""', $value) . '"';
    }
    $csv_output .= implode(',', $csv_row) . "\n";
  }
  
  $response = rest_ensure_response($csv_output);
  $response->header('Content-Type', 'text/csv');
  $response->header('Content-Disposition', 'attachment; filename="greeting_ads_data.csv"');
  
  return $response;
}

function generate_xml_response($data)
{
  $xml = new SimpleXMLElement('<greeting_ads_sync/>');
  
  $xml->addChild('success', $data['success'] ? 'true' : 'false');
  $xml->addChild('total_records', $data['total_records']);
  $xml->addChild('returned_records', $data['returned_records']);
  
  $sync_info = $xml->addChild('sync_info');
  $sync_info->addChild('timestamp', $data['sync_info']['timestamp']);
  $sync_info->addChild('format', $data['sync_info']['format']);
  
  $records = $xml->addChild('records');
  foreach ($data['data'] as $record) {
    $record_xml = $records->addChild('record');
    foreach ($record as $key => $value) {
      $record_xml->addChild($key, htmlspecialchars($value));
    }
  }
  
  $response = rest_ensure_response($xml->asXML());
  $response->header('Content-Type', 'application/xml');

  return $response;
}

function get_greeting_validation_stats($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Get keyword parameter from request
  $keyword = $request->get_param('keyword');

  if (empty($keyword)) {
    return new WP_Error('missing_parameter', 'Keyword parameter is required', ['status' => 400]);
  }

  // Query to get all records with matching greeting (LIKE query like in rekap chat)
  $query = $wpdb->prepare(
    "SELECT * FROM $table_name WHERE greeting LIKE %s",
    '%' . $wpdb->esc_like($keyword) . '%'
  );

  $records = $wpdb->get_results($query, ARRAY_A);

  if (empty($records)) {
    return rest_ensure_response([
      'success' => true,
      'keyword' => $keyword,
      'valid' => 0,
      'invalid' => 0,
      'total' => 0,
      'message' => 'No records found for this keyword'
    ]);
  }

  // Count valid and invalid based on ai_result field
  $valid_count = 0;
  $invalid_count = 0;

  foreach ($records as $record) {
    $ai_result = strtolower(trim($record['ai_result'] ?? ''));
    if ($ai_result === 'valid') {
      $valid_count++;
    } elseif (in_array($ai_result, ['dilarang', 'invalid', 'blocked'])) {
      $invalid_count++;
    }
    // Other values are ignored (not counted as valid or invalid)
  }

  return rest_ensure_response([
    'success' => true,
    'keyword' => $keyword,
    'valid' => $valid_count,
    'invalid' => $invalid_count,
    'total' => count($records),
    'data' => $records
  ]);
}

function get_form_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Pagination parameters
  $page = max(1, (int)$request->get_param('page'));
  $per_page = min(100, max(1, (int)$request->get_param('per_page') ?: 50));
  $offset = ($page - 1) * $per_page;

  // Filter parameters
  $greeting = $request->get_param('greeting');
  $search = $request->get_param('search');

  // Base query
  $query = "SELECT * FROM $table_name WHERE 1=1";
  $query_params = [];

  // Build WHERE conditions
  if (!empty($greeting)) {
    $query .= " AND greeting LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($greeting) . '%';
  }

  // Global search across relevant fields
  if (!empty($search)) {
    $query .= " AND (nama LIKE %s OR no_whatsapp LIKE %s OR jenis_website LIKE %s OR greeting LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term, $search_term]);
  }

  // Count total records for pagination
  $count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
  $total_items = 0;
  if (!empty($query_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
  } else {
    $total_items = $wpdb->get_var($count_query);
  }

  // Add ORDER BY and LIMIT
  $query .= " ORDER BY id DESC LIMIT %d OFFSET %d";
  $query_params[] = $per_page;
  $query_params[] = $offset;

  // Execute main query
  if (!empty($query_params)) {
    $data = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
  } else {
    $data = $wpdb->get_results($query, ARRAY_A);
  }

  // Prepare response with pagination info
  $response_data = [
    'data' => $data,
    'pagination' => [
      'total_items' => (int)$total_items,
      'total_pages' => ceil($total_items / $per_page),
      'current_page' => $page,
      'per_page' => $per_page
    ]
  ];

  return rest_ensure_response($response_data);
}

function test_validation_stats($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Get keyword parameter from request
  $keyword = $request->get_param('keyword');

  if (empty($keyword)) {
    return rest_ensure_response([
      'success' => true,
      'message' => 'Test endpoint - no keyword provided',
      'table_name' => $table_name
    ]);
  }

  // Test query that will be used
  $query = $wpdb->prepare(
    "SELECT * FROM $table_name WHERE greeting LIKE %s",
    '%' . $wpdb->esc_like($keyword) . '%'
  );

  $records = $wpdb->get_results($query, ARRAY_A);

  return rest_ensure_response([
    'success' => true,
    'keyword' => $keyword,
    'table_name' => $table_name,
    'query' => str_replace('%s', "'%" . $keyword . "%'", $query),
    'records_found' => count($records),
    'sample_records' => array_slice($records, 0, 3), // First 3 records
    'total_records_in_table' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name")
  ]);
}
