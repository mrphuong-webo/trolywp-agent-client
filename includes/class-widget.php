<?php
// Widget chat for TrolyWP Agent Client
class TrolyWP_Agent_Client_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('trolywp_agent_client_widget', 'TrolyWP Agent Client Chat', [
            'description' => 'Widget chat AI TrolyWP',
        ]);
    }
    public function widget($args, $instance) {
        // Widget chat đã bị vô hiệu hóa, chỉ dùng admin popup.
        // Không hiển thị gì ở frontend.
    }
    public function form($instance) {}
    public function update($new_instance, $old_instance) { return $instance; }
}
