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
                $n8n_url = 'https://trolywp.com/webhook-n8n';
                update_option('trolywp_agent_client_n8n_url', $n8n_url);
            } else             if ($mode === 'custom') {
                $custom_n8n_url = isset($_POST['custom_n8n_url']) ? esc_url_raw($_POST['custom_n8n_url']) : '';
                update_option('trolywp_agent_client_n8n_url', $custom_n8n_url);
                $chat_display = isset($_POST['chat_display']) && $_POST['chat_display'] === 'hosted' ? 'hosted' : 'embedded';
                update_option('trolywp_agent_client_chat_display', $chat_display);
            }
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
            $chat_display = get_option('trolywp_agent_client_chat_display', 'embedded');
            echo '<tr><th>Chat URL</th><td>';
            echo '<input type="url" name="custom_n8n_url" value="'.$custom_n8n_url.'" style="width:100%;max-width:500px" placeholder="https://n8n.webo.vn/webhook/.../chat" />';
            echo '<p class="description">URL từ node <strong>Chat Trigger</strong> trong n8n (Chat URL), hoặc từ <a href="https://n8nchat.com/" target="_blank" rel="noopener">n8nchat.com</a>. Plugin gửi kèm <code>firstEntryJson</code> (metadata site/user). <a href="https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-langchain.chattrigger/" target="_blank" rel="noopener">Chat Trigger docs</a></p>';
            echo '</td></tr>';
            echo '<tr><th>Chat hiển thị</th><td>';
            echo '<select name="chat_display">';
            echo '<option value="embedded"'.($chat_display==='embedded'?' selected':'').'>Embedded Chat – iframe trong popup (n8n: Mode = Embedded Chat)</option>';
            echo '<option value="hosted"'.($chat_display==='hosted'?' selected':'').'>Hosted Chat – mở tab mới (n8n: Mode = Hosted Chat). Metadata gửi qua firstEntryJson trên URL.</option>';
            echo '</select>';
            echo '<p class="description">Khớp với <strong>Mode</strong> trong Chat Trigger. Hosted: mở trang n8n trong tab mới, vẫn có <code>firstEntryJson</code> trên URL. <a href="https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-langchain.chattrigger/" target="_blank" rel="noopener">Chat Trigger docs</a></p>';
            echo '</td></tr>';
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
        echo '<li><b>Chức năng:</b> Chat AI qua site (icon chat cho user đã đăng nhập, gửi metadata site/user tới n8n).</li>';
        echo '<li><b>Cài đặt:</b> Tab "Cài đặt" → Chế độ n8n: Dùng n8n riêng → nhập <strong>Chat URL</strong> (từ n8n Chat Trigger hoặc n8nchat.com).</li>';
        echo '<li><b>Tích hợp n8n / n8nchat.com:</b> Dùng Chat URL từ workflow n8n (node Chat Trigger) hoặc từ <a href="https://n8nchat.com/" target="_blank" rel="noopener">n8nchat.com</a> (Create n8n Workflows with AI). Plugin gửi kèm <code>firstEntryJson</code> trên URL để workflow nhận metadata (site_url, user_id, user_email, …).</li>';
        echo '<li><b>Widget:</b> Icon chat tự hiện ở frontend và admin khi user đã đăng nhập (không cần thêm widget sidebar).</li>';
        echo '<li><b>REST API:</b> POST /wp-json/trolywp-client/v1/chat</li>';
        echo '</ul>';
    }
}
