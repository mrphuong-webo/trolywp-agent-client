<?php
/*
Plugin Name: TrolyWP Agent Client
Description: Kết nối site với trolywp.com, cung cấp UI chat AI, forward chat qua HMAC.
Version: 1.0.2
Author: TrolyWP
Text Domain: trolywp-agent-client
Requires at least: 6.0
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    if (is_admin()) return;
    $user_id = get_current_user_id();
    $authorKey = $user_id ? get_user_meta($user_id, 'webo_hmac_key_id', true) : '';
    $config = [
        'n8nUrl' => get_option('trolywp_agent_client_n8n_url', ''),
        'authorKey' => $authorKey,
        'siteId' => get_option('trolywp_agent_client_site_id', ''),
        'authorId' => $user_id,
    ];
    echo '<script type="text/javascript">window.TrolywpClientChatConfig = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    $ver = defined('WP_DEBUG') && WP_DEBUG ? time() : get_plugin_data(__FILE__)['Version'];
    wp_enqueue_script(
        'trolywp-agent-client-loader',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client-loader.js',
        [],
        strtotime('nơw'),
        'all'
    );
    wp_enqueue_style(
        'trolywp-agent-client-css',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client.css',
        [],
        strtotime('nơw'),
        'all'
    );
});

register_activation_hook(__FILE__, function() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('webo-hmac-auth/webo-hmac-auth.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('TrolyWP Agent Client cần cài đặt và kích hoạt plugin <b>webo-hmac-auth</b> để hoạt động.');
    }
    if (!get_option('trolywp_agent_client_site_id')) {
        update_option('trolywp_agent_client_site_id', wp_generate_uuid4());
    }
});

require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-utils.php';
require_once __DIR__ . '/includes/class-widget.php';
require_once __DIR__ . '/includes/class-manager-client.php';
require_once __DIR__ . '/includes/class-rest-chat.php';

add_shortcode('trolywp_ai_chat_client', ['TrolyWP_Agent_Client_Shortcode', 'render']);
add_action('admin_menu', ['TrolyWP_Agent_Client_Admin', 'menu']);
add_action('admin_notices', ['TrolyWP_Agent_Client_Utils', 'dependency_notice']);
add_action('widgets_init', function () {
    register_widget('TrolyWP_Agent_Client_Widget');
});
// REST API chat endpoint cho UI phía client.
add_action('rest_api_init', ['TrolyWP_Agent_Client_REST_Chat', 'register_routes']);
// Hiển thị icon chat ở backend admin cho admin đã đăng nhập
add_action('admin_footer', function() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    echo '<link rel="stylesheet" href="'.plugin_dir_url(__FILE__).'assets/trolywp-agent-client.css" type="text/css" media="all">';
    echo '<div id="trolywp-chat-icon" class="trolywp-chat-icon" title="TrolyWP Chat">
        <svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>
    </div>';
    $webhook_url = get_option('trolywp_agent_client_n8n_url', '');
    echo '<div id="trolywp-chat-popup" class="trolywp-chat-popup" style="top:0;height:100vh;border-radius:12px 0 0 12px;box-shadow:-2px 0 16px rgba(0,0,0,0.2);transition:right 0.3s;">';
    echo '<div style="position:relative;z-index:10000;display:flex;gap:8px;background:#f5f5f5;padding:8px 12px;border-bottom:1px solid #ddd;border-radius:12px 0 0 0;box-shadow:0 2px 8px rgba(0,0,0,0.08);width:100%;align-items:center;">';
    echo '<button id="trolywp-chat-close-admin" style="background:#eee;border:none;border-radius:50%;width:32px;height:32px;font-size:20px;cursor:pointer;box-shadow:0 1px 4px #ccc;">&times;</button>';
    echo '<button id="trolywp-chat-mode-sidebar-admin" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Sidebar</button>';
    echo '<button id="trolywp-chat-mode-popup-admin" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Popup</button>';
    echo '<button id="trolywp-chat-mode-fixed-admin" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Fixed</button>';
    echo '</div>';
    if (empty($webhook_url)) {
        echo '<div style="color:red;padding:16px;text-align:center;">Chưa cấu hình webhook n8n URL!</div>';
    } else {
        echo '<iframe id="trolywp-chat-iframe-admin" src="' . esc_url($webhook_url) . '" style="width:100%;height:calc(100% - 56px);border:none;"></iframe>';
    }
    echo '</div>';
    // Inject config for JS loader
    echo '<script>window.TrolywpClientChatConfig = {minWidth:260,maxWidth:600,defaultWidth:350,defaultHeight:420,admin:true};</script>';
    // Enqueue loader script
    echo '<script type="module" src="'.plugin_dir_url(__FILE__).'assets/trolywp-agent-client-loader.js"></script>';
});
add_action('wp_footer', ['TrolyWP_Agent_Client_Utils', 'enqueue_loader']);
add_filter('script_loader_tag', ['TrolyWP_Agent_Client_Utils', 'add_module_attribute'], 10, 3);

// Chỉ inject frontend chat widget và config HMAC, loại bỏ các chức năng thừa gây xung đột.
// Đảm bảo không preload file JS thủ công, chỉ dùng wp_enqueue_script
// Nếu có theme/plugin nào thêm preload, hãy xóa hoặc sửa lại cho đúng as="script" và crossorigin="anonymous"
