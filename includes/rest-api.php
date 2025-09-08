<?php
/**
 * REST API endpoints for Greeting Ads plugin
 */

// Hook to register REST API routes
add_action('rest_api_init', 'greeting_ads_register_rest_routes');

function greeting_ads_register_rest_routes() {
    register_rest_route('greeting-ads/v1', '/sync-data', array(
        'methods' => 'POST',
        'callback' => 'greeting_ads_sync_data_callback',
        'permission_callback' => 'greeting_ads_api_permission_check',
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

    // GET endpoint untuk debugging/monitoring
    register_rest_route('greeting-ads/v1', '/status', array(
        'methods' => 'GET',
        'callback' => 'greeting_ads_status_callback',
        'permission_callback' => 'greeting_ads_api_permission_check'
    ));
}

/**
 * Permission check for API endpoints
 */
function greeting_ads_api_permission_check($request) {
    $api_key = $request->get_param('api_key') ?: $request->get_header('X-API-Key');
    $expected_key = 'hutara000'; // Sama dengan yang di apivdcom
    
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
        
        $table_name = $wpdb->prefix . 'greeting_ads_data';
        
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
                    'greeting' => $greeting,
                    'apivdcom_id' => $apivdcomId,
                    'updated_at' => current_time('mysql')
                ),
                array('nomor_kata_kunci' => $nomorKataKunci),
                array('%s', '%s', '%s', '%s', '%d', '%s'),
                array('%s')
            );
            
            if ($result !== false) {
                // Log the action
                greeting_ads_log_action('update', $kataKunci, $greeting, $nomorKataKunci);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'action' => 'updated',
                    'message' => "Updated: {$kataKunci} → {$greeting}",
                    'record_id' => $existing->id
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
                    'greeting' => $greeting,
                    'apivdcom_id' => $apivdcomId,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($result !== false) {
                $new_id = $wpdb->insert_id;
                
                // Log the action
                greeting_ads_log_action('insert', $kataKunci, $greeting, $nomorKataKunci);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'action' => 'inserted',
                    'message' => "Inserted: {$kataKunci} → {$greeting}",
                    'record_id' => $new_id
                ), 201);
            } else {
                throw new Exception('Insert failed: ' . $wpdb->last_error);
            }
        }
        
    } catch (Exception $e) {
        // Log error
        error_log('Greeting Ads API Error: ' . $e->getMessage());
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Database operation failed: ' . $e->getMessage()
        ), 500);
    }
}

/**
 * Status endpoint callback
 */
function greeting_ads_status_callback($request) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'greeting_ads_data';
    
    // Get basic stats
    $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $recent_records = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    ));
    
    // Get latest records
    $latest = $wpdb->get_results($wpdb->prepare(
        "SELECT kata_kunci, greeting, created_at FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
        5
    ), ARRAY_A);
    
    return new WP_REST_Response(array(
        'success' => true,
        'stats' => array(
            'total_records' => intval($total_records),
            'last_24h' => intval($recent_records),
            'table_name' => $table_name
        ),
        'latest_records' => $latest,
        'endpoint_info' => array(
            'sync_url' => rest_url('greeting-ads/v1/sync-data'),
            'status_url' => rest_url('greeting-ads/v1/status'),
            'server_time' => current_time('mysql')
        )
    ), 200);
}

/**
 * Log API actions for debugging
 */
function greeting_ads_log_action($action, $keyword, $greeting, $criterion_id) {
    $log_message = sprintf(
        'Greeting Ads API: %s - Keyword: %s, Greeting: %s, Criterion ID: %s',
        strtoupper($action),
        $keyword,
        $greeting,
        $criterion_id
    );
    
    error_log($log_message);
}