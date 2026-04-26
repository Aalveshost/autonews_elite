<?php
/**
 * Plugin Name: AutoNews Elite Scheduler
 * Description: Agendador Editorial Inteligente com Kanban de 7 dias e Automação via IA.
 * Version: 1.5.3
 * Author: AutoNews Elite Team
 */

if (!defined('ABSPATH')) exit;

// ... (tabelas e menus) ...

// 7. AJAX PARA "RODAR AGORA"
add_action('wp_ajax_run_autonews_slot', 'autonews_elite_ajax_run_slot');
function autonews_elite_ajax_run_slot() {
    global $wpdb;
    $slot_id = intval($_POST['slot_id']);
    $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autonews_slots WHERE id = %d", $slot_id));
    
    if ($slot) {
        require_once plugin_dir_path(__FILE__) . 'engine-api.php';
        autonews_elite_trigger_api($slot);
        $wpdb->update($wpdb->prefix . 'autonews_slots', ['last_run' => current_time('mysql')], ['id' => $slot_id]);
        wp_send_json_success(['message' => 'Post gerado com sucesso!']);
    }
    wp_send_json_error(['message' => 'Slot não encontrado.']);
}

// 8. AUTO-CHECK AO CARREGAR O PAINEL
add_action('admin_init', 'autonews_elite_force_check');
function autonews_elite_force_check() {
    if (isset($_GET['page']) && $_GET['page'] == 'autonews-elite') {
        autonews_elite_process_queue();
    }
}
add_action('wp_ajax_test_autonews_connection', 'autonews_elite_ajax_test_conn');
function autonews_elite_ajax_test_conn() {
    $api_url = 'http://86.48.18.19:5000/api/auth-check';
    $response = wp_remote_get($api_url, [
        'timeout' => 15,
        'headers' => [
            'X-Site-URL' => get_site_url()
        ]
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Erro de Rede: ' . $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response));

    if ($code == 200 && isset($body->success)) {
        wp_send_json_success(['message' => $body->message]);
    } elseif ($code == 403) {
        wp_send_json_error(['message' => 'Erro 403: Site não autorizado no painel central.']);
    } else {
        wp_send_json_error(['message' => 'Erro ' . $code . ': ' . ($body->error ?? 'Resposta desconhecida')]);
    }
}
register_activation_hook(__FILE__, 'autonews_elite_setup_table');
function autonews_elite_setup_table() {
    global $wpdb;
    $table_slots = $wpdb->prefix . 'autonews_slots';
    $table_logs = $wpdb->prefix . 'autonews_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_slots (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        day_of_week tinyint(1) NOT NULL,
        hour time NOT NULL,
        category_id bigint(20) NOT NULL,
        search_query text NOT NULL,
        custom_prompt text,
        last_run datetime DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id)
    ) $charset_collate;
    CREATE TABLE $table_logs (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP,
        title text NOT NULL,
        status varchar(50) NOT NULL,
        post_id bigint(20),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 5. AJAX HANDLER PARA O LOG
add_action('wp_ajax_get_autonews_logs', 'autonews_elite_ajax_logs');
function autonews_elite_ajax_logs() {
    global $wpdb;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 25;
    $offset = ($page - 1) * $per_page;

    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autonews_logs ORDER BY id DESC LIMIT %d, %d", $offset, $per_page));
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}autonews_logs");

    wp_send_json_success(['logs' => $logs, 'total' => $total, 'pages' => ceil($total / $per_page)]);
}

add_action('wp_ajax_clear_autonews_logs', 'autonews_elite_clear_logs');
function autonews_elite_clear_logs() {
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}autonews_logs");
    wp_send_json_success(['message' => 'Logs limpos com sucesso.']);
}

// 2. MENU NO ADMIN
add_action('admin_menu', 'autonews_elite_menu');
function autonews_elite_menu() {
    add_menu_page(
        'AutoNews Elite',
        'AutoNews Elite',
        'manage_options',
        'autonews-elite',
        'autonews_elite_render_page',
        'dashicons-rss',
        30
    );
}

// 3. RENDERIZAÇÃO DA PÁGINA (KANBAN)
function autonews_elite_render_page() {
    include plugin_dir_path(__FILE__) . 'interface-kanban.php';
}

// 4. MOTOR DE CRON (VERIFICA AGENDAMENTOS)
add_action('autonews_cron_hook', 'autonews_elite_process_queue');
if (!wp_next_scheduled('autonews_cron_hook')) {
    wp_schedule_event(time(), 'hourly', 'autonews_cron_hook');
}

function autonews_elite_process_queue() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'autonews_slots';
    
    $current_day = date('N'); // 1 (Seg) a 7 (Dom)
    $current_hour = date('H:i:00');

    // Busca slots para hoje que ainda não rodaram no horário certo
    $slots = $wpdb->get_results("
        SELECT * FROM $table_name 
        WHERE day_of_week = $current_day 
        AND hour <= '$current_hour'
        AND DATE(last_run) < CURDATE()
    ");

    if ($slots) {
        require_once plugin_dir_path(__FILE__) . 'engine-api.php';
        foreach ($slots as $slot) {
            autonews_elite_trigger_api($slot);
            $wpdb->update($table_name, ['last_run' => current_time('mysql')], ['id' => $slot->id]);
        }
    }
}
