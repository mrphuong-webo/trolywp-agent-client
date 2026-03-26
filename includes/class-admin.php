<?php
// Admin menu and dashboard logic for TrolyWP Agent Client
class TrolyWP_Agent_Client_Admin {

    /** Option values for AI token type (author choice, like Copilot). */
    public static function get_ai_token_types() {
        return array(
            '' => '— Không dùng / Mặc định site —',
            'openai' => 'OpenAI (GPT)',
            'gemini' => 'Google Gemini',
            'anthropic' => 'Anthropic (Claude)',
            'groq' => 'Groq',
            'together' => 'Together AI',
            'custom' => 'Custom',
        );
    }

    public static function menu() {
        add_menu_page('TrolyWP Client', 'TrolyWP Client', 'manage_options', 'trolywp-client', [__CLASS__, 'main_page'], 'dashicons-admin-site', 2);
        add_action('show_user_profile', [__CLASS__, 'user_profile_ai_fields']);
        add_action('edit_user_profile', [__CLASS__, 'user_profile_ai_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_user_ai_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_user_ai_fields']);
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
        TrolyWP_Agent_Client_Utils::settings_hmac_notice_if_needed();
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
            $ai_token = isset($_POST['ai_token']) ? sanitize_text_field($_POST['ai_token']) : '';
            $ai_token_type = isset($_POST['ai_token_type']) ? sanitize_text_field($_POST['ai_token_type']) : '';
            update_option('trolywp_agent_client_ai_token', $ai_token);
            update_option('trolywp_agent_client_ai_token_type', $ai_token_type);
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
        $ai_token = get_option('trolywp_agent_client_ai_token', '');
        $ai_token_type = esc_attr(get_option('trolywp_agent_client_ai_token_type', ''));
        $token_types = self::get_ai_token_types();
        echo '<tr><th>Token AI mặc định (n8n)</th><td>';
        echo '<input type="password" name="ai_token" value="'.esc_attr($ai_token).'" class="regular-text" autocomplete="off" placeholder="sk-... hoặc API key" /> ';
        echo '<select name="ai_token_type">';
        foreach ($token_types as $val => $label) {
            if ($val === '') $label = '— Không gửi —';
            echo '<option value="'.esc_attr($val).'"'.($ai_token_type === $val ? ' selected' : '').'>'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '<p class="description">Mặc định cho mọi author khi họ chưa cấu hình riêng (mỗi author có thể đặt token + chọn AI tại <strong>Hồ sơ</strong>). Token + <code>aiTokenType</code> gửi trong <code>firstEntryJson</code>; n8n dùng <code>$json.metadata.aiToken</code> / <code>$json.metadata.aiTokenType</code>.</p>';
        echo '</td></tr>';
        echo '</table><p><input type="submit" class="button button-primary" value="Lưu" /></p></form>';
    }

    /** Show AI token + type on user profile (each author their own, like Copilot). */
    public static function user_profile_ai_fields( $user ) {
        if ( ! is_a( $user, 'WP_User' ) ) {
            return;
        }
        $stored = get_user_meta( $user->ID, 'trolywp_ai_token', true );
        $type   = get_user_meta( $user->ID, 'trolywp_ai_token_type', true );
        $types  = self::get_ai_token_types();
        ?>
        <h3><?php esc_html_e( 'TrolyWP Chat AI', 'trolywp-agent-client' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Mỗi author dùng token và loại AI riêng (gửi sang n8n trong metadata). Để trống = dùng mặc định site.', 'trolywp-agent-client' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="trolywp_ai_token"><?php esc_html_e( 'API token AI', 'trolywp-agent-client' ); ?></label></th>
                <td>
                    <input type="password" name="trolywp_ai_token" id="trolywp_ai_token" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Để trống giữ nguyên', 'trolywp-agent-client' ); ?>" />
                    <?php if ( ! empty( $stored ) ) : ?>
                        <br><label><input type="checkbox" name="trolywp_ai_token_clear" value="1" /> <?php esc_html_e( 'Xóa token', 'trolywp-agent-client' ); ?></label>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="trolywp_ai_token_type"><?php esc_html_e( 'Loại AI', 'trolywp-agent-client' ); ?></label></th>
                <td>
                    <select name="trolywp_ai_token_type" id="trolywp_ai_token_type">
                        <?php foreach ( $types as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Save per-author AI token and type. */
    public static function save_user_ai_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return;
        }
        if ( isset( $_POST['trolywp_ai_token_clear'] ) && $_POST['trolywp_ai_token_clear'] === '1' ) {
            delete_user_meta( $user_id, 'trolywp_ai_token' );
            delete_user_meta( $user_id, 'trolywp_ai_token_type' );
            return;
        }
        if ( isset( $_POST['trolywp_ai_token'] ) && $_POST['trolywp_ai_token'] !== '' ) {
            update_user_meta( $user_id, 'trolywp_ai_token', sanitize_text_field( wp_unslash( $_POST['trolywp_ai_token'] ) ) );
        }
        if ( isset( $_POST['trolywp_ai_token_type'] ) ) {
            update_user_meta( $user_id, 'trolywp_ai_token_type', sanitize_text_field( wp_unslash( $_POST['trolywp_ai_token_type'] ) ) );
        }
    }

    public static function guide_tab() {
        echo '<h2>Hướng dẫn sử dụng</h2>';
        echo '<ul>';
        echo '<li><b>Chức năng:</b> Chat AI qua site (user đăng nhập). Chat gọi <strong>thẳng n8n</strong> (webhookUrl = URL n8n), WordPress chỉ inject meta (firstEntryJson: site, user, chat_token, authorKey) để widget gửi kèm. MCP proxy: n8n gửi chat_token + body → WordPress ký HMAC và gọi MCP.</li>';
        echo '<li><b>Cài đặt:</b> Tab "Cài đặt" → Chế độ n8n: Dùng n8n riêng → nhập <strong>Chat URL</strong> (từ n8n Chat Trigger).</li>';
        echo '<li><b>Tích hợp 4 plugin:</b> <strong>webo-mcp</strong> (WEBO MCP — MCP router), <strong>webo-hmac-auth</strong> (HMAC), <strong>trolywp-agent-client</strong> (chat + MCP proxy), <strong>n8n-nodes-webo-mcp</strong> (node n8n). Cùng chuẩn HMAC; chat dùng <code>chat_token</code> + mcp-proxy để n8n gọi MCP không cần lưu secret.</li>';
        echo '<li><b>MCP proxy:</b> firstEntryJson có <code>chat_token</code> (TTL 24h, gia hạn mỗi lần gọi proxy). n8n gọi POST <code>/wp-json/trolywp-client/v1/mcp-proxy</code> với body <code>{ "chat_token": "...", "body": &lt;JSON-RPC&gt; }</code> — WordPress tự ký HMAC và forward tới MCP.</li>';
        echo '<li><b>Lịch sử chat / phiên bền vững:</b> firstEntryJson gửi <code>sessionId</code> cố định theo user (lưu user_meta). Trong n8n: Chat Trigger → <strong>Load Previous Session</strong> = <strong>From Memory</strong>; kết nối Chat Trigger và Agent với cùng một Memory node (vd Window Buffer Memory). Session ID dùng key từ metadata — reload trang vẫn giữ lịch sử.</li>';
        echo '</ul>';
    }
}
