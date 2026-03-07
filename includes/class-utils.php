<?php
// Utility functions for TrolyWP Agent Client
class TrolyWP_Agent_Client_Utils {
    public static function dependency_notice() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!is_plugin_active('webo-wordpress-mcp/webo-wordpress-mcp.php') || !is_plugin_active('webo-hmac-auth/webo-hmac-auth.php')) {
            echo '<div class="notice notice-error"><p>TrolyWP Agent Client cần cài đặt <b>webo-wordpress-mcp</b> và <b>webo-hmac-auth</b> để hoạt động.</p></div>';
        }
    }
    public static function enqueue_loader() {
        if (is_admin()) return;
        wp_enqueue_script(
            'trolywp-agent-client-loader',
            plugin_dir_url(__FILE__) . '../assets/trolywp-agent-client-loader.js',
            [],
            time(),
            true
        );
    }
    public static function add_module_attribute($tag, $handle, $src) {
        if ($handle !== 'trolywp-agent-client-loader') return $tag;
        return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
    }
    // Removed auto_git_push. No git push logic in plugin.
}
