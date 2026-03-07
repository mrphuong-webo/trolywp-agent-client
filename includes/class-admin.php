<?php
// Admin menu and dashboard logic for TrolyWP Agent Client
class TrolyWP_Agent_Client_Admin {
    public static function menu() {
        add_menu_page('TrolyWP Client', 'TrolyWP Client', 'manage_options', 'trolywp-client', [__CLASS__, 'main_page'], 'dashicons-admin-site', 2);
    }

    public static function main_page() {
        if (!current_user_can('manage_options')) return;
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        echo '<div class="wrap"><h1>TrolyWP Agent Client</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=trolywp-client&tab=settings" class="nav-tab'.($tab=='settings'?' nav-tab-active':'').'">Cài đặt</a>';
        echo '<a href="?page=trolywp-client&tab=guide" class="nav-tab'.($tab=='guide'?' nav-tab-active':'').'">Hướng dẫn</a>';
        echo '</h2>';
        if ($tab == 'settings') self::settings_tab();
        elseif ($tab == 'guide') self::guide_tab();
        echo '</div>';
    }

    public static function settings_tab() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $mode = isset($_POST['n8n_mode']) ? sanitize_text_field($_POST['n8n_mode']) : 'trolywp';
            update_option('trolywp_agent_client_n8n_mode', $mode);
            if ($mode === 'trolywp') {
                // Crawl link n8n trolywp.com (giả lập crawl, thực tế dùng wp_remote_get)
                $n8n_url = 'https://trolywp.com/webhook-n8n'; // crawl thực tế
                update_option('trolywp_agent_client_n8n_url', $n8n_url);
            } else if ($mode === 'custom') {
                if (isset($_POST['custom_n8n_url'])) {
                    update_option('trolywp_agent_client_n8n_url', esc_url_raw($_POST['custom_n8n_url']));
                }
            }
            // ...existing code...
            if (isset($_POST['ui_height'])) update_option('trolywp_agent_client_ui_height', sanitize_text_field($_POST['ui_height'] ?? '420px'));
            if (isset($_POST['ui_width'])) update_option('trolywp_agent_client_ui_width', sanitize_text_field($_POST['ui_width'] ?? '350px'));
            if (isset($_POST['ui_color'])) update_option('trolywp_agent_client_ui_color', sanitize_hex_color($_POST['ui_color'] ?? '#222'));
            if (isset($_POST['ui_background'])) update_option('trolywp_agent_client_ui_background', sanitize_hex_color($_POST['ui_background'] ?? '#fff'));
            echo '<div class="updated"><p>Lưu thành công!</p></div>';
        }
        $mode = esc_attr(get_option('trolywp_agent_client_n8n_mode', 'trolywp'));
        // ...existing code...
        $ui_height = esc_attr(get_option('trolywp_agent_client_ui_height', '420px'));
        $ui_width = esc_attr(get_option('trolywp_agent_client_ui_width', '350px'));
        $ui_color = esc_attr(get_option('trolywp_agent_client_ui_color', '#222'));
        $ui_background = esc_attr(get_option('trolywp_agent_client_ui_background', '#fff'));
        echo '<form method="post"><table class="form-table">';
        echo '<tr><th>Chế độ n8n</th><td>';
        echo '<select name="n8n_mode" onchange="this.form.submit()">';
        echo '<option value="trolywp"'.($mode=='trolywp'?' selected':'').'>Dùng trolywp.com (tự động)</option>';
        echo '<option value="custom"'.($mode=='custom'?' selected':'').'>Dùng n8n riêng (tùy chỉnh)</option>';
        echo '</select>';
        echo '</td></tr>';
        if ($mode === 'custom') {
            $custom_n8n_url = esc_attr(get_option('trolywp_agent_client_n8n_url', ''));
            echo '<tr><th>Webhook n8n riêng</th><td><input type="text" name="custom_n8n_url" value="'.$custom_n8n_url.'" style="width:400px" placeholder="https://your-n8n-webhook" /></td></tr>';
        }
            // ...existing code...
        echo '<tr><th>UI Height</th><td><input type="text" name="ui_height" value="'.$ui_height.'" style="width:120px" placeholder="420px" /></td></tr>';
        echo '<tr><th>UI Width</th><td><input type="text" name="ui_width" value="'.$ui_width.'" style="width:120px" placeholder="350px" /></td></tr>';
        echo '<tr><th>Text Color</th><td><input type="color" name="ui_color" value="'.$ui_color.'" /></td></tr>';
        echo '<tr><th>Background</th><td><input type="color" name="ui_background" value="'.$ui_background.'" /></td></tr>';
        echo '</table><p><input type="submit" class="button button-primary" value="Lưu" /></p></form>';
    }

    public static function guide_tab() {
        echo '<h2>Hướng dẫn sử dụng</h2>';
        echo '<ul>';
        echo '<li><b>Chức năng:</b> Chat AI qua site chính (trolywp.vn).</li>';
        echo '<li><b>Cài đặt:</b> Tab "Cài đặt" để nhập URL site chat (mặc định trolywp.vn).</li>';
        echo '<li><b>Widget:</b> Thêm widget "TrolyWP Agent Client Chat" vào sidebar hoặc khu vực mong muốn để hiển thị icon chat.</li>';
        echo '<li><b>Không còn shortcode.</b></li>';
        echo '<li><b>REST API:</b> <br>POST /wp-json/trolywp-client/v1/chat</li>';
        echo '<li><b>Hướng dẫn chi tiết:</b> <ul>';
        echo '<li>1. Cài plugin, vào tab "Cài đặt" nhập URL site chat.</li>';
        echo '<li>2. Thêm widget vào giao diện.</li>';
        echo '</ul></li>';
        echo '</ul>';
        echo '</ul></li>';
        echo '</ul>';
    }
}
