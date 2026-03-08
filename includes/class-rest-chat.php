<?php
// REST API endpoint cho chat từ UI client → forward sang manager hub.

if (!defined('ABSPATH')) exit;

class TrolyWP_Agent_Client_REST_Chat {

    /**
     * Đăng ký routes REST.
     */
    public static function register_routes() {
        register_rest_route(
            'trolywp-client/v1',
            '/chat',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_chat'],
                'permission_callback' => [__CLASS__, 'permission_check'],
            ]
        );
    }

    /**
     * Chỉ cho phép user đã đăng nhập (author/editor/admin) chat.
     */
    public static function permission_check(WP_REST_Request $request) {
        return is_user_logged_in();
    }

    /**
     * Nhận message từ UI → thêm metadata → gửi sang manager bằng HMAC.
     */
    public static function handle_chat(WP_REST_Request $request) {
        if (!class_exists('TrolyWP_Agent_Client_Manager_Client')) {
            return new WP_REST_Response(
                ['ok' => false, 'error' => 'manager_client_not_available'],
                500
            );
        }

        $params = $request->get_json_params();

        $agent_id        = isset($params['agent_id']) ? sanitize_text_field($params['agent_id']) : '';
        $message         = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';
        $conversation_id = isset($params['conversation_id']) ? sanitize_text_field($params['conversation_id']) : '';
        $context         = isset($params['context']) && is_array($params['context']) ? $params['context'] : [];

        if (empty($agent_id) || empty($message)) {
            return new WP_REST_Response(
                ['ok' => false, 'error' => 'missing_agent_or_message'],
                400
            );
        }

        $current_user = wp_get_current_user();

        $payload = [
            'site_id'        => TrolyWP_Agent_Client_Manager_Client::get_site_id(),
            'domain'         => get_site_url(),
            'wp_user_id'     => $current_user ? $current_user->ID : 0,
            'author_id'      => $current_user ? $current_user->ID : 0,
            'agent_id'       => $agent_id,
            'conversation_id'=> $conversation_id,
            'message'        => $message,
            'context'        => $context,
        ];

        $result = TrolyWP_Agent_Client_Manager_Client::send_chat($payload);

        $code = !empty($result['ok']) ? 200 : 400;

        return new WP_REST_Response($result, $code);
    }
}

