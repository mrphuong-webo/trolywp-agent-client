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
        // Enqueue CSS
        echo '<link rel="stylesheet" href="'.plugin_dir_url(__FILE__).'../assets/trolywp-agent-client.css" type="text/css" media="all">';
        $webhook_url = get_option('trolywp_agent_client_n8n_url', '');
        if (empty($webhook_url)) {
            echo '<div style="color:red;padding:12px;">Chưa cấu hình webhook n8n URL!</div>';
        } else {
            echo '<div id="trolywp-chat-icon" class="trolywp-chat-icon" title="TrolyWP Chat">
                <svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>
            </div>';
            echo '<div id="trolywp-chat-popup" class="trolywp-chat-popup">
                <div id="trolywp-chat-resize-handle" class="trolywp-chat-resize-handle"></div>
                <iframe src="' . esc_url($webhook_url) . '" style="width:100%;height:100%;border:none;"></iframe>
            </div>';
            // Inject config for JS loader
            echo '<script>window.TrolywpClientChatConfig = {minWidth:260,maxWidth:600,defaultWidth:350,defaultHeight:420};</script>';
            // Enqueue loader script
            echo '<script type="module" src="'.plugin_dir_url(__FILE__).'../assets/trolywp-agent-client-loader.js"></script>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {}
    public function update($new_instance, $old_instance) { return $instance; }
}
