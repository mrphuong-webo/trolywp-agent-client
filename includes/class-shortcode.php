<?php
// UI shortcode logic for TrolyWP Agent Client
class TrolyWP_Agent_Client_Shortcode {
    public static function render($atts) {
        $ui_defaults = [
            'height' => get_option('trolywp_agent_client_ui_height', '420px'),
            'width' => get_option('trolywp_agent_client_ui_width', '350px'),
            'color' => get_option('trolywp_agent_client_ui_color', '#222'),
            'background' => get_option('trolywp_agent_client_ui_background', '#fff'),
        ];
        $atts = shortcode_atts([
            'webhook_url' => '',
            'json'        => '',
            'id'          => '',
            'class'       => '',
            'height'      => '',
            'width'       => '',
            'color'       => '',
            'background'  => '',
            'author_key'  => '',
            // ...existing code...
        ], $atts, 'trolywp_ai_chat_client');
        $webhook = $atts['webhook_url'] !== '' ? esc_url_raw($atts['webhook_url']) : get_option('trolywp_agent_client_n8n_url', '');
        $id = $atts['id'] !== '' ? sanitize_html_class($atts['id']) : ('trolywp-client-chat-' . wp_generate_uuid4());
        $class = 'trolywp-client-chat';
        if (!empty($atts['class'])) {
            $class .= ' ' . sanitize_html_class($atts['class']);
        }
        $ui = [
            'height' => $atts['height'] !== '' ? $atts['height'] : $ui_defaults['height'],
            'width' => $atts['width'] !== '' ? $atts['width'] : $ui_defaults['width'],
            'color' => $atts['color'] !== '' ? $atts['color'] : $ui_defaults['color'],
            'background' => $atts['background'] !== '' ? $atts['background'] : $ui_defaults['background'],
        ];
        $json_attr = '';
        $json_config = [];
        if (is_string($atts['json']) && trim($atts['json']) !== '') {
            $json_attr = ' data-json="' . esc_attr($atts['json']) . '"';
            $json_config = json_decode($atts['json'], true);
        }
        $payload = [
            'webhook' => $webhook,
            'ui' => $ui,
        ];
        if ($atts['author_key']) {
            $payload['author_key'] = $atts['author_key'];
        }
        // ...existing code...
        if (!empty($json_config) && is_array($json_config)) {
            $payload = array_merge($payload, $json_config);
        }
        add_action('wp_footer', function() use ($payload) {
            echo '<script>window.TrolywpClientChat = ' . wp_json_encode($payload) . ';</script>' . "\n";
            wp_enqueue_script(
                'trolywp-agent-client-loader',
                plugin_dir_url(__FILE__) . 'assets/trolywp-agent-client-loader.js',
                [],
                time(),
                true
            );
        }, 5);
        add_filter('script_loader_tag', function($tag, $handle, $src) {
            if ($handle !== 'trolywp-agent-client-loader') return $tag;
            return '<script type="module" src="' . esc_url($src) . '" crossorigin="anonymous"></script>' . "\n";
        }, 10, 3);
        $style = sprintf(' style="height:%s;width:%s;color:%s;background:%s;overflow:auto;"', esc_attr($ui['height']), esc_attr($ui['width']), esc_attr($ui['color']), esc_attr($ui['background']));
        return '<div id="' . esc_attr($id) . '" class="' . esc_attr($class) . '" data-trolywp-client-chat="1" data-webhook="' . esc_attr($webhook) . '"' . $json_attr . $style . '></div>';
    }
}
