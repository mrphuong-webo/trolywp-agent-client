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
        if (empty($webhook_url)) {
            echo '<div style="color:red;padding:12px;">Chưa cấu hình webhook n8n URL!</div>';
        } else {
            echo '<div id="trolywp-chat-icon" style="cursor:pointer;width:60px;height:60px;background:#222;border-radius:50%;position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);" title="TrolyWP Chat">
                <svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>
            </div>';
            echo '<div id="trolywp-chat-popup" style="display:none;position:fixed;bottom:90px;right:24px;width:350px;height:420px;background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.2);z-index:9999;overflow:hidden;">
                <div id="trolywp-chat-resize-handle" style="position:absolute;left:0;top:0;width:8px;height:100%;cursor:ew-resize;z-index:10000;background:rgba(0,0,0,0.02);"></div>
                <iframe src="' . esc_url($webhook_url) . '" style="width:100%;height:100%;border:none;"></iframe>
            </div>';
            echo '<script>
            (function(){
                var icon = document.getElementById("trolywp-chat-icon");
                var popup = document.getElementById("trolywp-chat-popup");
                var handle = document.getElementById("trolywp-chat-resize-handle");
                var minWidth = 260, maxWidth = 600;
                var isResizing = false, startX = 0, startWidth = 0;
                icon.onclick = function(){
                    popup.style.display = popup.style.display === "none" ? "block" : "none";
                };
                handle.addEventListener("mousedown", function(e){
                    isResizing = true;
                    startX = e.clientX;
                    startWidth = popup.offsetWidth;
                    document.body.style.userSelect = "none";
                });
                document.addEventListener("mousemove", function(e){
                    if (!isResizing) return;
                    var dx = startX - e.clientX;
                    var newWidth = Math.min(Math.max(startWidth + dx, minWidth), maxWidth);
                    popup.style.width = newWidth + "px";
                });
                document.addEventListener("mouseup", function(){
                    if (isResizing) {
                        isResizing = false;
                        document.body.style.userSelect = "";
                    }
                });
                // Touch support
                handle.addEventListener("touchstart", function(e){
                    if (e.touches.length !== 1) return;
                    isResizing = true;
                    startX = e.touches[0].clientX;
                    startWidth = popup.offsetWidth;
                    document.body.style.userSelect = "none";
                });
                document.addEventListener("touchmove", function(e){
                    if (!isResizing || e.touches.length !== 1) return;
                    var dx = startX - e.touches[0].clientX;
                    var newWidth = Math.min(Math.max(startWidth + dx, minWidth), maxWidth);
                    popup.style.width = newWidth + "px";
                });
                document.addEventListener("touchend", function(){
                    if (isResizing) {
                        isResizing = false;
                        document.body.style.userSelect = "";
                    }
                });
            })();
            </script>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {}
    public function update($new_instance, $old_instance) { return $instance; }
}
