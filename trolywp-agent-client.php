<?php
/*
Plugin Name: TrolyWP Agent Client
Description: Chat AI qua n8n (widget @n8n/chat), gửi metadata site/user. Cần webo-hmac-auth.
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

    $first_entry = [
        'metadata' => $metadata,
        'siteId'   => get_option('trolywp_agent_client_site_id', ''),
        'authorId' => $user_id,
        'authorKey'=> get_user_meta($user_id, 'webo_hmac_key_id', true),
    ];
    $first_entry = apply_filters('trolywp_agent_client_first_entry', $first_entry);

    $config = [
        'n8nUrl'        => $n8n_url,
        'firstEntryJson'=> $first_entry,
    ];
    echo '<script type="text/javascript">window.TrolywpClientChatConfig = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';

    wp_enqueue_style(
        'n8n-chat-widget',
        'https://cdn.jsdelivr.net/npm/@n8n/chat@1.12.0/dist/style.css',
        [],
        '1.12.0'
    );
    echo '<div id="trolywp-n8n-chat-root"></div>';

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

require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-utils.php';

add_action('admin_menu', ['TrolyWP_Agent_Client_Admin', 'menu']);
add_action('admin_notices', ['TrolyWP_Agent_Client_Utils', 'dependency_notice']);
