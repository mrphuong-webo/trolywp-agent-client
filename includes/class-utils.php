<?php
// Utility: dependency notice for TrolyWP Agent Client
class TrolyWP_Agent_Client_Utils {
    public static function dependency_notice() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!is_plugin_active('webo-hmac-auth/webo-hmac-auth.php')) {
            echo '<div class="notice notice-warning"><p>TrolyWP Agent Client cần cài đặt và kích hoạt plugin <b>webo-hmac-auth</b> để hoạt động.</p></div>';
        }
    }
}
