<?php
if (!defined('ABSPATH')) exit;

function autonews_elite_trigger_api($slot) {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'autonews_logs';

    $api_url = 'http://86.48.18.19:5000/api/process';

    $body = [
        'q' => $slot->search_query,
        'prompt' => $slot->custom_prompt,
        'category' => get_cat_name($slot->category_id)
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Site-URL'   => get_site_url()
        ],
        'body'    => json_encode($body),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        $wpdb->insert($table_logs, ['title' => 'ERRO: ' . $response->get_error_message(), 'status' => 'error']);
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body);
    
    if ($data && isset($data->success) && $data->success) {
        $post_id = wp_insert_post([
            'post_title'    => $data->title,
            'post_content'  => $data->content,
            'post_status'   => 'draft',
            'post_category' => [$slot->category_id],
            'post_author'   => 1
        ]);

        if ($post_id && !empty($data->featured_image)) {
            autonews_elite_sideload_image($data->featured_image, $post_id, $data->caption);
        }

        $wpdb->insert($table_logs, ['title' => $data->title, 'status' => 'success', 'post_id' => $post_id]);
    } else {
        $error_msg = 'ERRO: Resposta Inválida';
        if ($data && isset($data->error)) {
            $error_msg = 'ERRO API: ' . $data->error;
        } elseif (!empty($response_body)) {
            $error_msg = 'ERRO BRUTO: ' . substr(strip_tags($response_body), 0, 200);
        }
        $wpdb->insert($table_logs, ['title' => $error_msg, 'status' => 'error']);
    }
}

function autonews_elite_sideload_image($url, $post_id, $desc = '') {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $id = media_sideload_image($url, $post_id, $desc, 'id');
    if (!is_wp_error($id)) set_post_thumbnail($post_id, $id);
}
