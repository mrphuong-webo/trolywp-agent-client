<?php
/**
 * Proxy chat qua WordPress: trình duyệt chỉ gọi REST của plugin, plugin forward lên n8n.
 * URL n8n không lộ, metadata bổ sung từ server.
 */
if (!defined('ABSPATH')) exit;

class TrolyWP_Agent_Client_Chat_Proxy {

    const REST_NAMESPACE = 'trolywp-client/v1';
    const REST_ROUTE     = 'n8n-chat';

    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/' . self::REST_ROUTE, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'handle_request'],
                'permission_callback' => [__CLASS__, 'permission_check'],
                'args'                => self::get_request_args(true),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_request'],
                'permission_callback' => [__CLASS__, 'permission_check'],
                'args'                => self::get_request_args(false),
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/mcp-proxy', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_mcp_proxy'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function permission_check(\WP_REST_Request $request) {
        return is_user_logged_in();
    }

    private static function get_request_args($is_get) {
        $args = [];
        if ($is_get) {
            $args['action'] = ['type' => 'string'];
            $args['sessionId'] = ['type' => 'string'];
        }
        return $args;
    }

    private static function get_first_entry_json() {
        $user_id = get_current_user_id();
        if (!$user_id) return [];
        $user = wp_get_current_user();
        $metadata = [
            'site_url'     => home_url('/'),
            'site_name'    => get_bloginfo('name'),
            'language'     => get_bloginfo('language'),
            'user_id'      => $user_id,
            'user_email'   => $user->user_email ?? '',
            'display_name' => $user->display_name ?? '',
            'user_roles'   => implode(',', array_values(array_filter((array) $user->roles))),
        ];
        if (is_multisite()) {
            $metadata['blog_id'] = get_current_blog_id();
        }
        $metadata = apply_filters('trolywp_agent_client_chat_metadata', $metadata);
        $first_entry = [
            'metadata' => $metadata,
            'siteId'   => get_option('trolywp_agent_client_site_id', ''),
            'authorId' => $user_id,
            'authorKey'=> get_user_meta($user_id, 'webo_hmac_key_id', true),
        ];
        $first_entry = apply_filters('trolywp_agent_client_first_entry', $first_entry);
        $first_entry['chat_token'] = self::get_or_create_chat_token($user_id);
        return $first_entry;
    }

    /** Token ngắn hạn để n8n gọi MCP proxy (TTL 15 phút). */
    private static function get_or_create_chat_token($user_id) {
        $key = 'trolywp_chat_token_u' . $user_id;
        $token = get_transient($key);
        if (is_string($token) && $token !== '') return $token;
        $token = bin2hex(random_bytes(24));
        set_transient('trolywp_chat_' . $token, $user_id, 15 * MINUTE_IN_SECONDS);
        set_transient($key, $token, 15 * MINUTE_IN_SECONDS);
        return $token;
    }

    /** Lấy user_id từ chat_token; 0 nếu invalid. */
    public static function get_user_id_by_chat_token($chat_token) {
        if (!is_string($chat_token) || $chat_token === '') return 0;
        $user_id = get_transient('trolywp_chat_' . $chat_token);
        return is_numeric($user_id) ? (int) $user_id : 0;
    }

    /**
     * Forward request tới n8n, bổ sung firstEntryJson từ server.
     */
    public static function handle_request(\WP_REST_Request $request) {
        $n8n_url = get_option('trolywp_agent_client_n8n_url', '');
        if (empty($n8n_url) || !filter_var($n8n_url, FILTER_VALIDATE_URL)) {
            return new \WP_REST_Response(['error' => 'n8n_url_not_configured'], 400);
        }

        $method = $request->get_method();
        $first_entry = self::get_first_entry_json();
        $query_params = $request->get_query_params();
        $n8n_path = wp_parse_url($n8n_url, PHP_URL_PATH);
        if (!is_string($n8n_path)) {
            $n8n_path = '/';
        }
        $headers = ['Accept' => 'application/json'];
        $key_id = null;
        if (function_exists('webo_hmac_sign_request') && get_current_user_id()) {
            $key_id = get_user_meta(get_current_user_id(), 'webo_hmac_key_id', true);
        }

        if ($method === 'GET') {
            $query_params['firstEntryJson'] = wp_json_encode($first_entry);
            $url = add_query_arg($query_params, $n8n_url);
            if (function_exists('webo_hmac_sign_request') && $key_id) {
                $hmac = webo_hmac_sign_request('GET', $n8n_path, '', $key_id);
                if (is_array($hmac)) {
                    $headers = array_merge($headers, $hmac);
                }
            }
            $resp = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => $headers,
            ]);
        } else {
            if (!empty($query_params)) {
                $n8n_url = add_query_arg($query_params, $n8n_url);
            }
            $body = $request->get_body();
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $decoded['firstEntryJson'] = $first_entry;
                $body = wp_json_encode($decoded);
            } else {
                $body = wp_json_encode(['firstEntryJson' => $first_entry]);
            }
            if (function_exists('webo_hmac_sign_request') && $key_id) {
                $hmac = webo_hmac_sign_request('POST', $n8n_path, $body, $key_id);
                if (is_array($hmac)) {
                    $headers = array_merge($headers, $hmac);
                }
            }
            $headers['Content-Type'] = 'application/json';
            $resp = wp_remote_post($n8n_url, [
                'timeout' => 60,
                'headers' => $headers,
                'body'    => $body,
            ]);
        }

        if (is_wp_error($resp)) {
            return new \WP_REST_Response(['error' => $resp->get_error_message()], 502);
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $content_type = wp_remote_retrieve_header($resp, 'content-type');

        $response = new \WP_REST_Response();
        $response->set_status($code);
        if ($content_type) {
            $response->header('Content-Type', $content_type);
        }
        $decoded = json_decode($body, true);
        $response->set_data($decoded !== null ? $decoded : $body);
        return $response;
    }

    /**
     * Trả về URL proxy để frontend dùng (không lộ n8n URL).
     */
    public static function get_proxy_url() {
        return rest_url(self::REST_NAMESPACE . '/' . self::REST_ROUTE);
    }

    /**
     * Proxy MCP: n8n gửi chat_token + body JSON-RPC, WordPress tự ký HMAC và gọi MCP.
     * Dùng khi n8n cần gọi WEBO MCP (vd: tạo bài viết) mà không cần biết secret.
     */
    public static function handle_mcp_proxy(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new \WP_REST_Response(['error' => 'invalid_json'], 400);
        }
        $chat_token = isset($params['chat_token']) ? trim((string) $params['chat_token']) : '';
        $user_id = self::get_user_id_by_chat_token($chat_token);
        if ($user_id <= 0) {
            return new \WP_REST_Response(['error' => 'invalid_or_expired_chat_token'], 401);
        }
        $body = isset($params['body']) ? $params['body'] : null;
        if ($body === null) {
            return new \WP_REST_Response(['error' => 'missing_body'], 400);
        }
        $body_raw = is_string($body) ? $body : wp_json_encode($body);
        wp_set_current_user($user_id);

        $mcp_path = '/wp-json/mcp/v1/router';
        $mcp_url = home_url($mcp_path);
        if (!function_exists('webo_hmac_sign_request')) {
            return new \WP_REST_Response(['error' => 'webo_hmac_auth_required'], 503);
        }
        $key_id = get_user_meta($user_id, 'webo_hmac_key_id', true);
        if (empty($key_id)) {
            return new \WP_REST_Response(['error' => 'user_has_no_hmac_key'], 403);
        }
        $hmac = webo_hmac_sign_request('POST', $mcp_path, $body_raw, $key_id);
        if (!is_array($hmac)) {
            return new \WP_REST_Response(['error' => 'hmac_sign_failed'], 503);
        }
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
        $headers = array_merge($headers, $hmac);
        $resp = wp_remote_post($mcp_url, [
            'timeout' => 30,
            'headers' => $headers,
            'body'    => $body_raw,
        ]);
        if (is_wp_error($resp)) {
            return new \WP_REST_Response(['error' => $resp->get_error_message()], 502);
        }
        $code = wp_remote_retrieve_response_code($resp);
        $resp_body = wp_remote_retrieve_body($resp);
        $content_type = wp_remote_retrieve_header($resp, 'content-type');
        $response = new \WP_REST_Response();
        $response->set_status($code);
        if ($content_type) $response->header('Content-Type', $content_type);
        $decoded = json_decode($resp_body, true);
        $response->set_data($decoded !== null ? $decoded : $resp_body);
        return $response;
    }
}
