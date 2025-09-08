<?php
/**
 * REST API endpoints for syncing data from apivdcom
 */

// Hook to register REST API routes
add_action('rest_api_init', 'greeting_ads_register_sync_routes');

function greeting_ads_register_sync_routes() {
    // Sync data endpoint
    register_rest_route('greeting-ads/v1', '/sync-data', array(
        'methods' => 'POST',
        'callback' => 'greeting_ads_sync_data_callback',
        'permission_callback' => 'greeting_ads_sync_permission_check',
        'args' => array(
            'api_key' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'API authentication key'
            ),
            'kata_kunci' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Keyword'
            ),
            'grup_iklan' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Ad group name'
            ),
            'id_grup_iklan' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Ad group ID'
            ),
            'nomor_kata_kunci' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Keyword criterion ID'
            ),
            'greeting' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Greeting variation'
            ),
            'apivdcom_id' => array(
                'required' => false,
                'type' => 'integer',
                'description' => 'Source record ID for tracking'
            )
        )
    ));

    // Status check endpoint
    register_rest_route('greeting-ads/v1', '/sync-status', array(
        'methods' => 'GET',
        'callback' => 'greeting_ads_sync_status_callback',
        'permission_callback' => 'greeting_ads_sync_permission_check'
    ));
}

/**
 * Permission check for sync API endpoints
 */
function greeting_ads_sync_permission_check($request) {
    $api_key = $request->get_param('api_key') ?: $request->get_header('X-API-Key');
    $expected_key = 'hutara000'; // API key yang sama dengan apivdcom
    
    if ($api_key !== $expected_key) {
        return new WP_Error('unauthorized', 'Invalid API key', array('status' => 401));
    }
    
    return true;
}

/**
 * Main sync data callback
 */
function greeting_ads_sync_data_callback($request) {
    global $wpdb;
    
    try {
        $params = $request->get_params();
        
        // Sanitize input data
        $kataKunci = sanitize_text_field($params['kata_kunci']);
        $grupIklan = sanitize_text_field($params['grup_iklan']);
        $idGrupIklan = sanitize_text_field($params['id_grup_iklan']);
        $nomorKataKunci = sanitize_text_field($params['nomor_kata_kunci']);
        $greeting = sanitize_text_field($params['greeting']);
        $apivdcomId = isset($params['apivdcom_id']) ? intval($params['apivdcom_id']) : null;
        
        // Use existing table name constant
        $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
        
        // Check if record exists based on nomor_kata_kunci
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE nomor_kata_kunci = %s",
            $nomorKataKunci
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                array(
                    'kata_kunci' => $kataKunci,
                    'grup_iklan' => $grupIklan,
                    'id_grup_iklan' => $idGrupIklan,
                    'greeting' => $greeting
                ),
                array('nomor_kata_kunci' => $nomorKataKunci),
                array('%s', '%s', '%s', '%s'),
                array('%s')
            );
            
            if ($result !== false) {
                // Log the action
                greeting_ads_log_sync_action('update', $kataKunci, $greeting, $nomorKataKunci);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'action' => 'updated',
                    'message' => "Updated: {$kataKunci} → {$greeting}",
                    'record_id' => $existing->id,
                    'data' => array(
                        'kata_kunci' => $kataKunci,
                        'greeting' => $greeting,
                        'nomor_kata_kunci' => $nomorKataKunci
                    )
                ), 200);
            } else {
                throw new Exception('Update failed: ' . $wpdb->last_error);
            }
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                array(
                    'kata_kunci' => $kataKunci,
                    'grup_iklan' => $grupIklan,
                    'id_grup_iklan' => $idGrupIklan,
                    'nomor_kata_kunci' => $nomorKataKunci,
                    'greeting' => $greeting
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $new_id = $wpdb->insert_id;
                
                // Log the action
                greeting_ads_log_sync_action('insert', $kataKunci, $greeting, $nomorKataKunci);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'action' => 'inserted',
                    'message' => "Inserted: {$kataKunci} → {$greeting}",
                    'record_id' => $new_id,
                    'data' => array(
                        'kata_kunci' => $kataKunci,
                        'greeting' => $greeting,
                        'nomor_kata_kunci' => $nomorKataKunci
                    )
                ), 201);
            } else {
                throw new Exception('Insert failed: ' . $wpdb->last_error);
            }
        }
        
    } catch (Exception $e) {
        // Log error
        error_log('Greeting Ads Sync API Error: ' . $e->getMessage());
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Database operation failed: ' . $e->getMessage(),
            'error_code' => 'database_error'
        ), 500);
    }
}

/**
 * Status endpoint callback
 */
function greeting_ads_sync_status_callback($request) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
    
    // Get basic stats
    $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    
    // Get latest records (if table has timestamp fields, adjust query)
    $latest = $wpdb->get_results($wpdb->prepare(
        "SELECT kata_kunci, greeting, id FROM {$table_name} ORDER BY id DESC LIMIT %d",
        5
    ), ARRAY_A);
    
    // Test database connection
    $db_status = $wpdb->get_var("SELECT 1") ? 'connected' : 'failed';
    
    return new WP_REST_Response(array(
        'success' => true,
        'status' => 'active',
        'database' => $db_status,
        'stats' => array(
            'total_records' => intval($total_records),
            'table_name' => $table_name
        ),
        'latest_records' => $latest,
        'endpoints' => array(
            'sync_url' => rest_url('greeting-ads/v1/sync-data'),
            'status_url' => rest_url('greeting-ads/v1/sync-status')
        ),
        'server_info' => array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'server_time' => current_time('mysql')
        )
    ), 200);
}

/**
 * Log sync API actions for debugging
 */
function greeting_ads_log_sync_action($action, $keyword, $greeting, $criterion_id) {
    $log_message = sprintf(
        '[Greeting Ads Sync] %s - Keyword: "%s", Greeting: "%s", Criterion: %s',
        strtoupper($action),
        $keyword,
        $greeting,
        $criterion_id
    );
    
    error_log($log_message);
    
    // Optionally store in WordPress options for admin display
    $recent_logs = get_option('greeting_ads_sync_logs', array());
    $recent_logs[] = array(
        'timestamp' => current_time('mysql'),
        'action' => $action,
        'keyword' => $keyword,
        'greeting' => $greeting,
        'criterion_id' => $criterion_id
    );
    
    // Keep only last 50 logs
    $recent_logs = array_slice($recent_logs, -50);
    update_option('greeting_ads_sync_logs', $recent_logs);
}