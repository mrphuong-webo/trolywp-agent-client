<?php
/*
Plugin Name: TrolyWP Agent Client
Description: Kết nối site với trolywp.com, cung cấp UI chat AI, forward chat qua HMAC.
Version: 1.0.5
Author: TrolyWP
Text Domain: trolywp-agent-client
Requires at least: 6.0
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

add_action('wp_footer', 'trolywp_agent_client_inject_chat');
add_action('admin_footer', 'trolywp_agent_client_inject_chat');

function trolywp_agent_client_inject_chat() {
    $user_id = get_current_user_id();
    if (!$user_id) return;
    $n8n_url = get_option('trolywp_agent_client_n8n_url', '');
    if (empty($n8n_url) || !filter_var($n8n_url, FILTER_VALIDATE_URL)) return;

    $user = wp_get_current_user();
    $metadata = [
        'site_url'     => home_url('/'),
        'site_name'    => get_bloginfo('name'),
        'language'     => get_bloginfo('language'),
        'user_id'      => $user_id,
        'user_email'   => $user->user_email ?? '',
        'display_name' => $user->display_name ?? '',
        'user_roles'   => implode(',', array_values(array_filter((array) $user->roles))),
    ];
    if (is_multisite()) {
        $metadata['blog_id'] = get_current_blog_id();
    }
    $metadata = apply_filters('trolywp_agent_client_chat_metadata', $metadata);

    $config = [
        'n8nUrl'    => $n8n_url,
        'metadata'  => $metadata,
        'authorKey' => get_user_meta($user_id, 'webo_hmac_key_id', true),
        'siteId'    => get_option('trolywp_agent_client_site_id', ''),
        'authorId'  => $user_id,
    ];
    echo '<script type="text/javascript">window.TrolywpClientChatConfig = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    $ver = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.5';
    if (function_exists('get_plugin_data')) {
        $data = get_plugin_data(__FILE__, false, false);
        if (!empty($data['Version'])) $ver = $data['Version'];
    }
    wp_enqueue_script(
        'trolywp-agent-client-loader',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client-loader.js',
        [],
        $ver,
        true
    );
    add_filter('script_loader_tag', function($tag, $handle, $src) {
        if ($handle === 'trolywp-agent-client-loader') {
            return str_replace('<script ', '<script crossorigin="anonymous" ', $tag);
        }
        return $tag;
    }, 10, 3);
    wp_enqueue_style(
        'trolywp-agent-client-css',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client.css',
        [],
        $ver,
        'all'
    );
}

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
    $ver = defined('WP_DEBUG') && WP_DEBUG ? time() : get_plugin_data(__FILE__)['Version'];
    wp_enqueue_style(
        'trolywp-agent-client-css',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client.css',
        [],
        $ver,
        'all'
    );
    wp_enqueue_script(
        'trolywp-agent-client-loader',
        plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client-loader.js',
        [],
        $ver,
        'all'
    );
    // ...existing code...
});
add_action('wp_footer', ['TrolyWP_Agent_Client_Utils', 'enqueue_loader']);
add_filter('script_loader_tag', ['TrolyWP_Agent_Client_Utils', 'add_module_attribute'], 10, 3);

// Chỉ inject frontend chat widget và config HMAC, loại bỏ các chức năng thừa gây xung đột.
// Đảm bảo không preload file JS thủ công, chỉ dùng wp_enqueue_script
// Nếu có theme/plugin nào thêm preload, hãy xóa hoặc sửa lại cho đúng as="script" và crossorigin="anonymous"
