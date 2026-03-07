<?php
// Widget chat for TrolyWP Agent Client
class TrolyWP_Agent_Client_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('trolywp_agent_client_widget', 'TrolyWP Agent Client Chat', [
            'description' => 'Widget chat AI TrolyWP',
        ]);
    }
    public function widget($args, $instance) {
        if (!is_user_logged_in() || !current_user_can('manage_options')) return;
        echo $args['before_widget'];
        $webhook_url = get_option('trolywp_agent_client_n8n_url', '');
        echo '<div style="padding:8px;font-size:12px;background:#f9f9f9;border-bottom:1px solid #eee;">';
        echo 'Webhook URL: <b>' . esc_html($webhook_url) . '</b><br>';
        echo 'Option trạng thái: ' . (empty($webhook_url) ? '<span style="color:red">Rỗng</span>' : '<span style="color:green">Đã có</span>');
        echo '</div>';
        if (empty($webhook_url)) {
            echo '<div style="color:red;padding:12px;">Chưa cấu hình webhook n8n URL!</div>';
        } else {
            echo '<div id="trolywp-chat-icon" style="cursor:pointer;width:60px;height:60px;background:#222;border-radius:50%;position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);" title="TrolyWP Chat">
                <svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>
            </div>';
            echo '<div id="trolywp-chat-panel" style="display:none;position:fixed;top:0;right:0;width:350px;height:100vh;background:#fff;border-radius:12px 0 0 12px;box-shadow:-2px 0 16px rgba(0,0,0,0.2);z-index:9999;overflow:hidden;transition:right 0.3s;">
                <div style="position:relative;z-index:10000;display:flex;gap:8px;background:#f5f5f5;padding:8px 12px;border-bottom:1px solid #ddd;border-radius:12px 0 0 0;box-shadow:0 2px 8px rgba(0,0,0,0.08);width:100%;align-items:center;">
                    <button id="trolywp-chat-close" style="background:#eee;border:none;border-radius:50%;width:32px;height:32px;font-size:20px;cursor:pointer;box-shadow:0 1px 4px #ccc;">&times;</button>
                    <button id="trolywp-chat-mode-sidebar" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Sidebar</button>
                    <button id="trolywp-chat-mode-popup" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Popup</button>
                    <button id="trolywp-chat-mode-fixed" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:0 12px;height:32px;font-weight:bold;cursor:pointer;box-shadow:0 1px 4px #ccc;">Fixed</button>
                </div>
                <iframe id="trolywp-chat-iframe" src="' . esc_url($webhook_url) . '" style="width:100%;height:calc(100% - 56px);border:none;"></iframe>
            </div>';
            echo '<script>
            var icon = document.getElementById("trolywp-chat-icon");
            var panel = document.getElementById("trolywp-chat-panel");
            var closeBtn = document.getElementById("trolywp-chat-close");
            var modeSidebar = document.getElementById("trolywp-chat-mode-sidebar");
            var modePopup = document.getElementById("trolywp-chat-mode-popup");
            var modeFixed = document.getElementById("trolywp-chat-mode-fixed");
            var iframe = document.getElementById("trolywp-chat-iframe");
            var currentMode = "sidebar";
            function setMode(mode) {
                currentMode = mode;
                if (mode === "sidebar") {
                    panel.style.width = "350px";
                    panel.style.height = "calc(100vh - 32px)";
                    panel.style.top = "32px";
                    panel.style.right = "0";
                    panel.style.borderRadius = "12px 0 0 12px";
                    panel.style.boxShadow = "-2px 0 16px rgba(0,0,0,0.2)";
                    document.body.style.marginRight = "350px";
                } else if (mode === "popup") {
                    panel.style.width = "350px";
                    panel.style.height = "420px";
                    panel.style.top = "auto";
                    panel.style.bottom = "90px";
                    panel.style.right = "24px";
                    panel.style.borderRadius = "12px";
                    panel.style.boxShadow = "0 2px 16px rgba(0,0,0,0.2)";
                    document.body.style.marginRight = "0";
                } else if (mode === "fixed") {
                    panel.style.width = "100vw";
                    panel.style.height = "calc(100vh - 32px)";
                    panel.style.top = "32px";
                    panel.style.right = "0";
                    panel.style.borderRadius = "0";
                    panel.style.boxShadow = "none";
                    document.body.style.marginRight = "0";
                }
            }
            icon.onclick = function(){
                if (panel.style.display === "block") {
                    panel.style.display = "none";
                    document.body.style.marginRight = "0";
                } else {
                    panel.style.display = "block";
                    setMode(currentMode);
                }
            };
            closeBtn.onclick = function(){
                panel.style.display = "none";
                document.body.style.marginRight = "0";
            };
            modeSidebar.onclick = function(){ setMode("sidebar"); };
            modePopup.onclick = function(){ setMode("popup"); };
            modeFixed.onclick = function(){ setMode("fixed"); };
            </script>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {}
    public function update($new_instance, $old_instance) { return $instance; }
}
