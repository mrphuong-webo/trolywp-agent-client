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
            } elseif ($mode === 'custom') {
                $custom_n8n_url = isset($_POST['custom_n8n_url']) ? esc_url_raw($_POST['custom_n8n_url']) : '';
                update_option('trolywp_agent_client_n8n_url', $custom_n8n_url);
            }
            echo '<div class="updated"><p>Lưu thành công!</p></div>';
        }
        $mode = esc_attr(get_option('trolywp_agent_client_n8n_mode', 'trolywp'));
        echo '<form method="post"><table class="form-table">';
        echo '<tr><th>Chế độ n8n</th><td>';
        echo '<select name="n8n_mode" onchange="this.form.submit()">';
        echo '<option value="trolywp"'.($mode=='trolywp'?' selected':'').'>Dùng trolywp.com (tự động)</option>';
        echo '<option value="custom"'.($mode=='custom'?' selected':'').'>Dùng n8n riêng (tùy chỉnh)</option>';
        echo '</select>';
        echo '</td></tr>';
        if ($mode === 'custom') {
            $custom_n8n_url = esc_attr(get_option('trolywp_agent_client_n8n_url', ''));
            echo '<tr><th>Chat URL</th><td>';
            echo '<input type="url" name="custom_n8n_url" value="'.$custom_n8n_url.'" style="width:100%;max-width:500px" placeholder="https://n8n.webo.vn/webhook/.../chat" />';
            echo '<p class="description">URL từ node <strong>Chat Trigger</strong> trong n8n. Chat gọi <strong>thẳng n8n</strong> (không proxy); WordPress chỉ cung cấp meta (firstEntryJson: site, user, chat_token, authorKey) để widget gửi kèm. Trong n8n Allowed Origins thêm domain site. <a href="https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-langchain.chattrigger/" target="_blank" rel="noopener">Chat Trigger docs</a></p>';
            echo '</td></tr>';
        }
        echo '</table><p><input type="submit" class="button button-primary" value="Lưu" /></p></form>';
    }

    public static function guide_tab() {
        echo '<h2>Hướng dẫn sử dụng</h2>';
        echo '<ul>';
        echo '<li><b>Chức năng:</b> Chat AI qua site (user đăng nhập). Chat gọi <strong>thẳng n8n</strong> (webhookUrl = URL n8n), WordPress chỉ inject meta (firstEntryJson: site, user, chat_token, authorKey) để widget gửi kèm. MCP proxy: n8n gửi chat_token + body → WordPress ký HMAC và gọi MCP.</li>';
        echo '<li><b>Cài đặt:</b> Tab "Cài đặt" → Chế độ n8n: Dùng n8n riêng → nhập <strong>Chat URL</strong> (từ n8n Chat Trigger).</li>';
        echo '<li><b>Tích hợp 4 plugin:</b> <strong>webo-wordpress-mcp</strong> (MCP router), <strong>webo-hmac-auth</strong> (HMAC), <strong>trolywp-agent-client</strong> (chat + MCP proxy), <strong>n8n-nodes-webo-mcp</strong> (node n8n). Cùng chuẩn HMAC; chat dùng <code>chat_token</code> + mcp-proxy để n8n gọi MCP không cần lưu secret.</li>';
        echo '<li><b>MCP proxy:</b> firstEntryJson có <code>chat_token</code> (TTL 15 phút). n8n gọi POST <code>/wp-json/trolywp-client/v1/mcp-proxy</code> với body <code>{ "chat_token": "...", "body": &lt;JSON-RPC&gt; }</code> — WordPress tự ký HMAC và forward tới MCP.</li>';
        echo '</ul>';
    }
}
