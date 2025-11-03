<?php

/**
 * API untuk mengisi database greeting ads dari vdnet
 * Endpoint: /wp-json/greeting/v1/vdnet
 */

add_action('rest_api_init', 'register_vdnet_api');

function register_vdnet_api()
{
  register_rest_route('greeting/v1', '/vdnet', [
    'methods' => 'POST',
    'callback' => 'vdnet_insert_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/vdnet/bulk', [
    'methods' => 'POST',
    'callback' => 'vdnet_bulk_insert_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/vdnet/update', [
    'methods' => 'POST',
    'callback' => 'vdnet_update_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/vdnet/delete', [
    'methods' => 'POST',
    'callback' => 'vdnet_delete_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/vdnet/filter', [
    'methods' => 'GET',
    'callback' => 'vdnet_filter_greeting_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/vdnet/delete-date-range', [
    'methods' => 'POST',
    'callback' => 'vdnet_delete_by_date_range',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/rekap/filter', [
    'methods' => 'GET',
    'callback' => 'vdnet_filter_rekap_data',
    'permission_callback' => 'validate_greeting_token'
  ]);

  register_rest_route('greeting/v1', '/rekap/stats', [
    'methods' => 'GET',
    'callback' => 'vdnet_get_rekap_stats',
    'permission_callback' => 'validate_greeting_token'
  ]);
}

/**
 * Insert single greeting data
 */
function vdnet_insert_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Get JSON data from request body
  $json_data = $request->get_json_params();

  if (empty($json_data)) {
    return new WP_Error('no_data', 'No data provided', ['status' => 400]);
  }

  // Validate required fields
  $required_fields = ['kata_kunci', 'grup_iklan', 'id_grup_iklan', 'nomor_kata_kunci', 'greeting'];
  $missing_fields = [];

  foreach ($required_fields as $field) {
    if (empty($json_data[$field])) {
      $missing_fields[] = $field;
    }
  }

  if (!empty($missing_fields)) {
    return new WP_Error('missing_fields', 'Missing required fields: ' . implode(', ', $missing_fields), ['status' => 400]);
  }

  // Sanitize data
  $data = [
    'kata_kunci' => sanitize_text_field($json_data['kata_kunci']),
    'grup_iklan' => sanitize_text_field($json_data['grup_iklan']),
    'id_grup_iklan' => sanitize_text_field($json_data['id_grup_iklan']),
    'nomor_kata_kunci' => sanitize_text_field($json_data['nomor_kata_kunci']),
    'greeting' => sanitize_textarea_field($json_data['greeting'])
  ];

  // Check for duplicate entry
  $exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table_name WHERE id_grup_iklan = %s AND nomor_kata_kunci = %s",
    $data['id_grup_iklan'],
    $data['nomor_kata_kunci']
  ));

  if ($exists > 0) {
    return new WP_Error('duplicate_entry', 'Data with this id_grup_iklan and nomor_kata_kunci already exists', ['status' => 409]);
  }

  // Insert data
  $result = $wpdb->insert($table_name, $data);

  if ($result === false) {
    return new WP_Error('insert_failed', 'Failed to insert data: ' . $wpdb->last_error, ['status' => 500]);
  }

  return rest_ensure_response([
    'success' => true,
    'message' => 'Data successfully inserted',
    'insert_id' => $wpdb->insert_id,
    'data' => $data
  ]);
}

/**
 * Bulk insert greeting data
 */
function vdnet_bulk_insert_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Get JSON data from request body
  $json_data = $request->get_json_params();

  if (empty($json_data) || !isset($json_data['data']) || !is_array($json_data['data'])) {
    return new WP_Error('no_data', 'No data array provided', ['status' => 400]);
  }

  $data_array = $json_data['data'];
  $max_batch_size = 100; // Limit batch size to prevent memory issues

  if (count($data_array) > $max_batch_size) {
    return new WP_Error('batch_too_large', "Maximum batch size is $max_batch_size records", ['status' => 400]);
  }

  $required_fields = ['kata_kunci', 'grup_iklan', 'id_grup_iklan', 'nomor_kata_kunci', 'greeting'];
  $success_count = 0;
  $error_count = 0;
  $errors = [];
  $inserted_ids = [];

  // Start transaction
  $wpdb->query('START TRANSACTION');

  foreach ($data_array as $index => $item) {
    // Validate required fields
    $missing_fields = [];
    foreach ($required_fields as $field) {
      if (empty($item[$field])) {
        $missing_fields[] = $field;
      }
    }

    if (!empty($missing_fields)) {
      $errors[] = "Row " . ($index + 1) . ": Missing fields - " . implode(', ', $missing_fields);
      $error_count++;
      continue;
    }

    // Sanitize data
    $data = [
      'kata_kunci' => sanitize_text_field($item['kata_kunci']),
      'grup_iklan' => sanitize_text_field($item['grup_iklan']),
      'id_grup_iklan' => sanitize_text_field($item['id_grup_iklan']),
      'nomor_kata_kunci' => sanitize_text_field($item['nomor_kata_kunci']),
      'greeting' => sanitize_textarea_field($item['greeting'])
    ];

    // Check for duplicate (optional - can be skipped for performance)
    if (isset($json_data['skip_duplicates']) && $json_data['skip_duplicates']) {
      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE id_grup_iklan = %s AND nomor_kata_kunci = %s",
        $data['id_grup_iklan'],
        $data['nomor_kata_kunci']
      ));

      if ($exists > 0) {
        continue; // Skip duplicates
      }
    }

    // Insert data
    $result = $wpdb->insert($table_name, $data);

    if ($result === false) {
      $errors[] = "Row " . ($index + 1) . ": Insert failed - " . $wpdb->last_error;
      $error_count++;
    } else {
      $success_count++;
      $inserted_ids[] = $wpdb->insert_id;
    }
  }

  // Commit or rollback
  if ($error_count > 0 && $success_count == 0) {
    $wpdb->query('ROLLBACK');
    return new WP_Error('insert_failed', 'All inserts failed', ['status' => 500, 'errors' => $errors]);
  } else {
    $wpdb->query('COMMIT');
  }

  return rest_ensure_response([
    'success' => true,
    'message' => 'Bulk insert completed',
    'total_records' => count($data_array),
    'success_count' => $success_count,
    'error_count' => $error_count,
    'inserted_ids' => $inserted_ids,
    'errors' => $errors
  ]);
}

/**
 * Update existing greeting data
 */
function vdnet_update_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Get JSON data from request body
  $json_data = $request->get_json_params();

  if (empty($json_data)) {
    return new WP_Error('no_data', 'No data provided', ['status' => 400]);
  }

  // Check for ID or unique identifier
  $record_id = isset($json_data['id']) ? intval($json_data['id']) : null;
  $id_grup_iklan = $json_data['id_grup_iklan'] ?? null;
  $nomor_kata_kunci = $json_data['nomor_kata_kunci'] ?? null;

  if (!$record_id && (!$id_grup_iklan || !$nomor_kata_kunci)) {
    return new WP_Error('missing_identifier', 'Either id or both id_grup_iklan and nomor_kata_kunci must be provided', ['status' => 400]);
  }

  // Build WHERE clause
  $where = [];
  $where_params = [];

  if ($record_id) {
    $where[] = "id = %d";
    $where_params[] = $record_id;
  } else {
    $where[] = "id_grup_iklan = %s";
    $where[] = "nomor_kata_kunci = %s";
    $where_params[] = sanitize_text_field($id_grup_iklan);
    $where_params[] = sanitize_text_field($nomor_kata_kunci);
  }

  // Build SET clause for updatable fields
  $updatable_fields = ['kata_kunci', 'grup_iklan', 'id_grup_iklan', 'nomor_kata_kunci', 'greeting'];
  $set_clauses = [];
  $set_params = [];

  foreach ($updatable_fields as $field) {
    if (isset($json_data[$field])) {
      $set_clauses[] = "$field = %s";
      if ($field === 'greeting') {
        $set_params[] = sanitize_textarea_field($json_data[$field]);
      } else {
        $set_params[] = sanitize_text_field($json_data[$field]);
      }
    }
  }

  if (empty($set_clauses)) {
    return new WP_Error('no_update_data', 'No fields to update', ['status' => 400]);
  }

  // Execute update
  $sql = "UPDATE $table_name SET " . implode(', ', $set_clauses) . " WHERE " . implode(' AND ', $where);
  $params = array_merge($set_params, $where_params);

  $result = $wpdb->query($wpdb->prepare($sql, $params));

  if ($result === false) {
    return new WP_Error('update_failed', 'Failed to update data: ' . $wpdb->last_error, ['status' => 500]);
  }

  return rest_ensure_response([
    'success' => true,
    'message' => 'Data successfully updated',
    'affected_rows' => $result
  ]);
}

/**
 * Delete greeting data
 */
function vdnet_delete_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Get JSON data from request body
  $json_data = $request->get_json_params();

  if (empty($json_data)) {
    return new WP_Error('no_data', 'No data provided', ['status' => 400]);
  }

  // Check for ID or unique identifier
  $record_id = isset($json_data['id']) ? intval($json_data['id']) : null;
  $id_grup_iklan = $json_data['id_grup_iklan'] ?? null;
  $nomor_kata_kunci = $json_data['nomor_kata_kunci'] ?? null;

  if (!$record_id && (!$id_grup_iklan || !$nomor_kata_kunci)) {
    return new WP_Error('missing_identifier', 'Either id or both id_grup_iklan and nomor_kata_kunci must be provided', ['status' => 400]);
  }

  // Build WHERE clause
  $where = [];
  $where_params = [];

  if ($record_id) {
    $where[] = "id = %d";
    $where_params[] = $record_id;
  } else {
    $where[] = "id_grup_iklan = %s";
    $where[] = "nomor_kata_kunci = %s";
    $where_params[] = sanitize_text_field($id_grup_iklan);
    $where_params[] = sanitize_text_field($nomor_kata_kunci);
  }

  // Execute delete
  $sql = "DELETE FROM $table_name WHERE " . implode(' AND ', $where);
  $result = $wpdb->query($wpdb->prepare($sql, $where_params));

  if ($result === false) {
    return new WP_Error('delete_failed', 'Failed to delete data: ' . $wpdb->last_error, ['status' => 500]);
  }

  return rest_ensure_response([
    'success' => true,
    'message' => 'Data successfully deleted',
    'deleted_rows' => $result
  ]);
}

/**
 * Filter greeting data by date range and other criteria
 */
function vdnet_filter_greeting_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Check if created_at column exists, if not add it
  $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'created_at'");
  if (!$column_exists) {
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
  }

  // Base query
  $query = "SELECT * FROM $table_name WHERE 1=1";
  $query_params = [];

  // Filter parameters
  $date_from = $request->get_param('date_from');
  $date_to = $request->get_param('date_to');
  $kata_kunci = $request->get_param('kata_kunci');
  $grup_iklan = $request->get_param('grup_iklan');
  $id_grup_iklan = $request->get_param('id_grup_iklan');
  $nomor_kata_kunci = $request->get_param('nomor_kata_kunci');
  $greeting = $request->get_param('greeting');

  // Pagination parameters
  $page = max(1, (int)$request->get_param('page'));
  $per_page = min(1000, max(1, (int)$request->get_param('per_page') ?: 50));
  $offset = ($page - 1) * $per_page;

  // Search parameter
  $search = $request->get_param('search');

  // Build WHERE conditions
  if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= %s";
    $query_params[] = sanitize_text_field($date_from);
  }

  if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= %s";
    $query_params[] = sanitize_text_field($date_to);
  }

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
  $order_by = $request->get_param('order_by') ?: 'id';
  $order = $request->get_param('order') ?: 'DESC';

  // Validate order_by column
  $allowed_columns = ['id', 'kata_kunci', 'grup_iklan', 'id_grup_iklan', 'nomor_kata_kunci', 'greeting', 'created_at'];
  if (!in_array($order_by, $allowed_columns)) {
    $order_by = 'id';
  }

  // Validate order direction
  if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
    $order = 'DESC';
  }

  $query .= " ORDER BY $order_by $order LIMIT %d OFFSET %d";
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
    'success' => true,
    'data' => $data,
    'pagination' => [
      'total_items' => (int)$total_items,
      'total_pages' => ceil($total_items / $per_page),
      'current_page' => $page,
      'per_page' => $per_page
    ],
    'filters' => [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'kata_kunci' => $kata_kunci,
      'grup_iklan' => $grup_iklan,
      'id_grup_iklan' => $id_grup_iklan,
      'nomor_kata_kunci' => $nomor_kata_kunci,
      'greeting' => $greeting,
      'search' => $search
    ]
  ];

  return rest_ensure_response($response_data);
}

/**
 * Delete greeting data by date range
 */
function vdnet_delete_by_date_range($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Get JSON data from request body
  $json_data = $request->get_json_params();

  if (empty($json_data)) {
    return new WP_Error('no_data', 'No data provided', ['status' => 400]);
  }

  $date_from = isset($json_data['date_from']) ? sanitize_text_field($json_data['date_from']) : null;
  $date_to = isset($json_data['date_to']) ? sanitize_text_field($json_data['date_to']) : null;

  if (empty($date_from) && empty($date_to)) {
    return new WP_Error('missing_dates', 'At least one of date_from or date_to must be provided', ['status' => 400]);
  }

  // Check if created_at column exists
  $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'created_at'");
  if (!$column_exists) {
    return new WP_Error('column_not_exists', 'created_at column does not exist in table', ['status' => 400]);
  }

  // Build WHERE clause
  $where = [];
  $where_params = [];

  if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= %s";
    $where_params[] = $date_from;
  }

  if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= %s";
    $where_params[] = $date_to;
  }

  // Count records before deletion
  $count_query = "SELECT COUNT(*) FROM $table_name WHERE " . implode(' AND ', $where);
  $count = $wpdb->get_var($wpdb->prepare($count_query, $where_params));

  if ($count == 0) {
    return rest_ensure_response([
      'success' => true,
      'message' => 'No records found in specified date range',
      'deleted_rows' => 0
    ]);
  }

  // Additional filters
  $additional_filters = [];
  if (isset($json_data['kata_kunci']) && !empty($json_data['kata_kunci'])) {
    $where[] = "kata_kunci LIKE %s";
    $where_params[] = '%' . $wpdb->esc_like(sanitize_text_field($json_data['kata_kunci'])) . '%';
    $additional_filters['kata_kunci'] = $json_data['kata_kunci'];
  }

  if (isset($json_data['grup_iklan']) && !empty($json_data['grup_iklan'])) {
    $where[] = "grup_iklan LIKE %s";
    $where_params[] = '%' . $wpdb->esc_like(sanitize_text_field($json_data['grup_iklan'])) . '%';
    $additional_filters['grup_iklan'] = $json_data['grup_iklan'];
  }

  if (isset($json_data['id_grup_iklan']) && !empty($json_data['id_grup_iklan'])) {
    $where[] = "id_grup_iklan = %s";
    $where_params[] = sanitize_text_field($json_data['id_grup_iklan']);
    $additional_filters['id_grup_iklan'] = $json_data['id_grup_iklan'];
  }

  if (isset($json_data['nomor_kata_kunci']) && !empty($json_data['nomor_kata_kunci'])) {
    $where[] = "nomor_kata_kunci = %s";
    $where_params[] = sanitize_text_field($json_data['nomor_kata_kunci']);
    $additional_filters['nomor_kata_kunci'] = $json_data['nomor_kata_kunci'];
  }

  // Recount with additional filters
  $count_query = "SELECT COUNT(*) FROM $table_name WHERE " . implode(' AND ', $where);
  $final_count = $wpdb->get_var($wpdb->prepare($count_query, $where_params));

  if ($final_count == 0) {
    return rest_ensure_response([
      'success' => true,
      'message' => 'No records found with specified filters',
      'deleted_rows' => 0,
      'filters' => [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'additional_filters' => $additional_filters
      ]
    ]);
  }

  // Safety check for dry run
  $dry_run = isset($json_data['dry_run']) ? (bool)$json_data['dry_run'] : false;

  if ($dry_run) {
    return rest_ensure_response([
      'success' => true,
      'message' => 'Dry run - no records deleted',
      'would_delete_rows' => $final_count,
      'filters' => [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'additional_filters' => $additional_filters
      ]
    ]);
  }

  // Execute delete
  $sql = "DELETE FROM $table_name WHERE " . implode(' AND ', $where);
  $result = $wpdb->query($wpdb->prepare($sql, $where_params));

  if ($result === false) {
    return new WP_Error('delete_failed', 'Failed to delete data: ' . $wpdb->last_error, ['status' => 500]);
  }

  return rest_ensure_response([
    'success' => true,
    'message' => 'Data successfully deleted by date range',
    'deleted_rows' => $result,
    'filters' => [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'additional_filters' => $additional_filters
    ]
  ]);
}

/**
 * Filter rekap_form data by date range and other criteria
 * This accesses the actual form submission data from page-rekap.php
 */
function vdnet_filter_rekap_data($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form'; // Same as page-rekap.php

  // Check if created_at column exists, if not add it
  $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'created_at'");
  if (!$column_exists) {
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
  }

  // Base query
  $query = "SELECT * FROM $table_name WHERE 1=1";
  $query_params = [];

  // Filter parameters
  $date_from = $request->get_param('date_from');
  $date_to = $request->get_param('date_to');
  $nama = $request->get_param('nama');
  $no_whatsapp = $request->get_param('no_whatsapp');
  $jenis_website = $request->get_param('jenis_website');
  $via = $request->get_param('via');
  $utm_content = $request->get_param('utm_content');
  $utm_medium = $request->get_param('utm_medium');
  $greeting = $request->get_param('greeting');
  $ai_result = $request->get_param('ai_result');

  // Pagination parameters
  $page = max(1, (int)$request->get_param('page'));
  $per_page = min(1000, max(1, (int)$request->get_param('per_page') ?: 50));
  $offset = ($page - 1) * $per_page;

  // Search parameter - PRIORITAS untuk greeting search
  $search = $request->get_param('search');

  // Build WHERE conditions
  if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= %s";
    $query_params[] = sanitize_text_field($date_from);
  }

  if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= %s";
    $query_params[] = sanitize_text_field($date_to);
  }

  if (!empty($nama)) {
    $query .= " AND nama LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($nama) . '%';
  }

  if (!empty($no_whatsapp)) {
    $query .= " AND no_whatsapp LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($no_whatsapp) . '%';
  }

  if (!empty($jenis_website)) {
    $query .= " AND jenis_website LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($jenis_website) . '%';
  }

  if (!empty($via)) {
    $query .= " AND via LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($via) . '%';
  }

  if (!empty($utm_content)) {
    $query .= " AND utm_content LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($utm_content) . '%';
  }

  if (!empty($utm_medium)) {
    $query .= " AND utm_medium LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($utm_medium) . '%';
  }

  if (!empty($greeting)) {
    $query .= " AND greeting LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($greeting) . '%';
  }

  if (!empty($ai_result)) {
    $query .= " AND ai_result = %s";
    $query_params[] = sanitize_text_field($ai_result);
  }

  // Enhanced global search - FOCUS pada greeting field untuk keyword search
  if (!empty($search)) {
    // Prioritasi greeting field search (untuk keywords seperti v2843)
    $query .= " AND (greeting LIKE %s OR nama LIKE %s OR no_whatsapp LIKE %s OR jenis_website LIKE %s OR via LIKE %s OR utm_content LIKE %s OR utm_medium LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
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
  $order_by = $request->get_param('order_by') ?: 'created_at';
  $order = $request->get_param('order') ?: 'DESC';

  // Validate order_by column
  $allowed_columns = ['id', 'nama', 'no_whatsapp', 'jenis_website', 'via', 'utm_content', 'utm_medium', 'greeting', 'ai_result', 'created_at'];
  if (!in_array($order_by, $allowed_columns)) {
    $order_by = 'created_at';
  }

  // Validate order direction
  if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
    $order = 'DESC';
  }

  $query .= " ORDER BY $order_by $order LIMIT %d OFFSET %d";
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
    'success' => true,
    'data' => $data,
    'pagination' => [
      'total_items' => (int)$total_items,
      'total_pages' => ceil($total_items / $per_page),
      'current_page' => $page,
      'per_page' => $per_page
    ],
    'filters' => [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'nama' => $nama,
      'no_whatsapp' => $no_whatsapp,
      'jenis_website' => $jenis_website,
      'via' => $via,
      'utm_content' => $utm_content,
      'utm_medium' => $utm_medium,
      'greeting' => $greeting,
      'ai_result' => $ai_result,
      'search' => $search
    ]
  ];

  return rest_ensure_response($response_data);
}

/**
 * Get statistics from rekap_form table
 */
function vdnet_get_rekap_stats($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Check if created_at column exists, if not add it
  $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'created_at'");
  if (!$column_exists) {
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
  }

  // Get date range parameters
  $date_from = $request->get_param('date_from');
  $date_to = $request->get_param('date_to');
  $greeting_filter = $request->get_param('greeting');
  $search_filter = $request->get_param('search'); // Tambah search parameter

  // Base WHERE clause
  $where = "WHERE 1=1";
  $where_params = [];

  if (!empty($date_from)) {
    $where .= " AND DATE(created_at) >= %s";
    $where_params[] = sanitize_text_field($date_from);
  }

  if (!empty($date_to)) {
    $where .= " AND DATE(created_at) <= %s";
    $where_params[] = sanitize_text_field($date_to);
  }

  if (!empty($greeting_filter)) {
    $where .= " AND greeting LIKE %s";
    $where_params[] = '%' . $wpdb->esc_like($greeting_filter) . '%';
  }

  // Enhanced search for stats - support keyword search
  if (!empty($search_filter)) {
    $where .= " AND greeting LIKE %s";
    $where_params[] = '%' . $wpdb->esc_like($search_filter) . '%';
  }

  // Build queries
  $total_query = "SELECT COUNT(*) FROM $table_name $where";
  $valid_query = "SELECT COUNT(*) FROM $table_name $where AND ai_result = 'valid'";
  $invalid_query = "SELECT COUNT(*) FROM $table_name $where AND ai_result = 'dilarang'";
  $unknown_query = "SELECT COUNT(*) FROM $table_name $where AND (ai_result IS NULL OR ai_result NOT IN ('valid', 'dilarang'))";

  // Execute queries
  if (!empty($where_params)) {
    $total = $wpdb->get_var($wpdb->prepare($total_query, $where_params));
    $valid = $wpdb->get_var($wpdb->prepare($valid_query, $where_params));
    $invalid = $wpdb->get_var($wpdb->prepare($invalid_query, $where_params));
    $unknown = $wpdb->get_var($wpdb->prepare($unknown_query, $where_params));
  } else {
    $total = $wpdb->get_var($total_query);
    $valid = $wpdb->get_var($valid_query);
    $invalid = $wpdb->get_var($invalid_query);
    $unknown = $wpdb->get_var($unknown_query);
  }

  // Get greeting distribution
  $greeting_query = "SELECT greeting, COUNT(*) as count FROM $table_name $where GROUP BY greeting ORDER BY count DESC LIMIT 10";
  if (!empty($where_params)) {
    $greeting_stats = $wpdb->get_results($wpdb->prepare($greeting_query, $where_params), ARRAY_A);
  } else {
    $greeting_stats = $wpdb->get_results($greeting_query, ARRAY_A);
  }

  // Get daily submission trends (last 30 days)
  $trends_query = "SELECT DATE(created_at) as date, COUNT(*) as submissions FROM $table_name WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date DESC";
  $daily_trends = $wpdb->get_results($trends_query, ARRAY_A);

  return rest_ensure_response([
    'success' => true,
    'stats' => [
      'total_submissions' => (int)$total,
      'valid_submissions' => (int)$valid,
      'invalid_submissions' => (int)$invalid,
      'unknown_submissions' => (int)$unknown,
      'validation_rate' => $total > 0 ? round(($valid / $total) * 100, 2) : 0,
      'invalid_rate' => $total > 0 ? round(($invalid / $total) * 100, 2) : 0
    ],
    'greeting_distribution' => $greeting_stats,
    'daily_trends' => $daily_trends,
    'filters_applied' => [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'greeting' => $greeting_filter,
      'search' => $search_filter
    ]
  ]);
}