<?php
/**
 * MCP proxy: n8n gửi chat_token + body → WordPress ký HMAC và gọi MCP.
 * Chat không qua proxy: widget gọi thẳng n8n, gửi kèm meta (firstEntryJson) từ server.
 */
if (!defined('ABSPATH')) exit;

class TrolyWP_Agent_Client_Chat_Proxy {

    const REST_NAMESPACE = 'trolywp-client/v1';

    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/mcp-proxy', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_mcp_proxy'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function get_or_create_chat_token($user_id) {
        $key = 'trolywp_chat_token_u' . $user_id;
        $token = get_transient($key);
        if (is_string($token) && $token !== '') return $token;
        $token = bin2hex(random_bytes(24));
        set_transient('trolywp_chat_' . $token, $user_id, 15 * MINUTE_IN_SECONDS);
        set_transient($key, $token, 15 * MINUTE_IN_SECONDS);
        return $token;
    }

    public static function get_user_id_by_chat_token($chat_token) {
        if (!is_string($chat_token) || $chat_token === '') return 0;
        $user_id = get_transient('trolywp_chat_' . $chat_token);
        return is_numeric($user_id) ? (int) $user_id : 0;
    }

    /** n8n gửi chat_token + body → WordPress ký HMAC và gọi MCP. */
    public static function handle_mcp_proxy(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) return new \WP_REST_Response(['error' => 'invalid_json'], 400);
        $chat_token = isset($params['chat_token']) ? trim((string) $params['chat_token']) : '';
        $user_id = self::get_user_id_by_chat_token($chat_token);
        if ($user_id <= 0) return new \WP_REST_Response(['error' => 'invalid_or_expired_chat_token'], 401);
        $body = isset($params['body']) ? $params['body'] : null;
        if ($body === null) return new \WP_REST_Response(['error' => 'missing_body'], 400);
        $body_raw = is_string($body) ? $body : wp_json_encode($body);
        wp_set_current_user($user_id);

        if (!function_exists('webo_hmac_sign_request')) return new \WP_REST_Response(['error' => 'webo_hmac_auth_required'], 503);
        $key_id = function_exists('webo_hmac_get_key_id_for_user') ? webo_hmac_get_key_id_for_user($user_id) : get_user_meta($user_id, 'webo_hmac_key_id', true);
        if (empty($key_id)) return new \WP_REST_Response(['error' => 'user_has_no_hmac_key'], 403);
        $hmac = webo_hmac_sign_request('POST', '/wp-json/mcp/v1/router', $body_raw, $key_id);
        if (!is_array($hmac)) return new \WP_REST_Response(['error' => 'hmac_sign_failed'], 503);

        $resp = wp_remote_post(home_url('/wp-json/mcp/v1/router'), [
            'timeout' => 30,
            'headers' => array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $hmac),
            'body'    => $body_raw,
        ]);
        if (is_wp_error($resp)) return new \WP_REST_Response(['error' => $resp->get_error_message()], 502);
        $res = new \WP_REST_Response();
        $res->set_status(wp_remote_retrieve_response_code($resp));
        if ($ct = wp_remote_retrieve_header($resp, 'content-type')) $res->header('Content-Type', $ct);
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $res->set_data($data !== null ? $data : wp_remote_retrieve_body($resp));
        return $res;
    }
}
