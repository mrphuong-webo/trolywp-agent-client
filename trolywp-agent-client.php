<?php
/**
 * Plugin Name: TrolyWP Agent Client
 * Description: Kết nối site với trolywp.com, cung cấp UI chat AI, forward chat qua HMAC.
 * Version: 1.0.0
 * Author: TrolyWP
 * Text Domain: trolywp-agent-client
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-utils.php';
require_once __DIR__ . '/includes/class-widget.php';

add_shortcode('trolywp_ai_chat_client', ['TrolyWP_Agent_Client_Shortcode', 'render']);
add_action('admin_menu', ['TrolyWP_Agent_Client_Admin', 'menu']);
add_action('admin_notices', ['TrolyWP_Agent_Client_Utils', 'dependency_notice']);
add_action('widgets_init', function () {
    register_widget('TrolyWP_Agent_Client_Widget');
});
// Hiển thị icon chat ở backend admin cho admin đã đăng nhập
add_action('admin_footer', function() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    echo '<div id="trolywp-chat-icon" style="cursor:pointer;width:60px;height:60px;background:#222;border-radius:50%;position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);" title="TrolyWP Chat">
        <svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>
    </div>';
    $webhook_url = get_option('trolywp_agent_client_n8n_url', '');
    echo '<div id="trolywp-chat-popup" style="display:none;position:fixed;top:0;right:0;width:350px;height:100vh;background:#fff;border-radius:12px 0 0 12px;box-shadow:-2px 0 16px rgba(0,0,0,0.2);z-index:9999;overflow:hidden;transition:right 0.3s;">';
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
    echo '<script>
    var icon = document.getElementById("trolywp-chat-icon");
    var popup = document.getElementById("trolywp-chat-popup");
    var closeBtn = document.getElementById("trolywp-chat-close-admin");
    var modeSidebar = document.getElementById("trolywp-chat-mode-sidebar-admin");
    var modePopup = document.getElementById("trolywp-chat-mode-popup-admin");
    var modeFixed = document.getElementById("trolywp-chat-mode-fixed-admin");
    var iframe = document.getElementById("trolywp-chat-iframe-admin");
    var currentMode = "sidebar";
    icon.onclick = function(){
        if (popup.style.display === "block") {
            popup.style.display = "none";
            document.body.style.marginRight = "0";
            var adminbar = document.getElementById("wpadminbar");
            if (adminbar) adminbar.style.marginRight = "0";
        } else {
            popup.style.display = "block";
            setMode(currentMode);
            if (currentMode === "sidebar") {
                document.body.style.transition = "margin-right 0.3s";
                document.body.style.marginRight = "350px";
                var adminbar = document.getElementById("wpadminbar");
                if (adminbar) adminbar.style.transition = "margin-right 0.3s";
                if (adminbar) adminbar.style.marginRight = "350px";
            }
        }
    };
    closeBtn.onclick = function(){
        popup.style.display = "none";
        document.body.style.marginRight = "0";
        var adminbar = document.getElementById("wpadminbar");
        if (adminbar) adminbar.style.marginRight = "0";
    };
    function setMode(mode) {
        currentMode = mode;
        var adminbar = document.getElementById("wpadminbar");
        if (mode === "sidebar") {
            popup.style.width = "350px";
            popup.style.height = "calc(100vh - 32px)";
            popup.style.top = "32px";
            popup.style.right = "0";
            popup.style.borderRadius = "12px 0 0 12px";
            popup.style.boxShadow = "-2px 0 16px rgba(0,0,0,0.2)";
            document.body.style.marginRight = "350px";
            if (adminbar) adminbar.style.marginRight = "350px";
        } else if (mode === "popup") {
            popup.style.width = "350px";
            popup.style.height = "420px";
            popup.style.top = "auto";
            popup.style.bottom = "90px";
            popup.style.right = "24px";
            popup.style.borderRadius = "12px";
            popup.style.boxShadow = "0 2px 16px rgba(0,0,0,0.2)";
            document.body.style.marginRight = "0";
            if (adminbar) adminbar.style.marginRight = "0";
        } else if (mode === "fixed") {
            popup.style.width = "100vw";
            popup.style.height = "calc(100vh - 32px)";
            popup.style.top = "32px";
            popup.style.right = "0";
            popup.style.borderRadius = "0";
            popup.style.boxShadow = "none";
            document.body.style.marginRight = "0";
            if (adminbar) adminbar.style.marginRight = "0";
        }
    }
    modeSidebar.onclick = function(){ setMode("sidebar"); };
    modePopup.onclick = function(){ setMode("popup"); };
    modeFixed.onclick = function(){ setMode("fixed"); };
    </script>';
});
add_action('wp_footer', ['TrolyWP_Agent_Client_Utils', 'enqueue_loader']);
add_filter('script_loader_tag', ['TrolyWP_Agent_Client_Utils', 'add_module_attribute'], 10, 3);
