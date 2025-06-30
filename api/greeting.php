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
