<?php
// Utility: dependency notice for TrolyWP Agent Client
class TrolyWP_Agent_Client_Utils {

    /** @return bool */
    public static function hmac_dependency_ok() {
        return function_exists( 'trolywp_agent_client_is_webo_hmac_active' ) && trolywp_agent_client_is_webo_hmac_active();
    }

    public static function dependency_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( self::hmac_dependency_ok() ) {
            return;
        }
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo wp_kses_post( sprintf(
            /* translators: %s: anchor link to Plugins admin screen (label webo-hmac-auth). */
            __( 'TrolyWP Agent Client: bật plugin %s để có authorKey đầy đủ và endpoint MCP proxy (chat_token → HMAC). Chat vẫn chạy nếu đã cấu hình n8n; thiếu HMAC thì authorKey có thể trống và proxy trả lỗi cho đến khi bật HMAC.', 'trolywp-agent-client' ),
            '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'webo-hmac-auth', 'trolywp-agent-client' ) . '</a>'
        ) );
        echo '</p></div>';
    }

    /** Inline warning on TrolyWP Client → Cài đặt. */
    public static function settings_hmac_notice_if_needed() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( self::hmac_dependency_ok() ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo wp_kses_post( sprintf(
            /* translators: %s: anchor to Plugins screen (webo-hmac-auth). */
            __( 'Chưa phát hiện %s đang chạy. Bật plugin này để authorKey và MCP proxy hoạt động đúng.', 'trolywp-agent-client' ),
            '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">webo-hmac-auth</a>'
        ) );
        echo '</p></div>';
    }
}
