<?php
/**
 * Proxy chat: chỉ thêm meta (firstEntryJson) + HMAC chuẩn webo, rồi forward sang n8n.
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
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_request'],
                'permission_callback' => '__return_true',
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

    }

    /** Meta gửi kèm mỗi request: site, user, chat_token (để n8n gọi MCP proxy). */
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
            'metadata'   => $metadata,
            'siteId'     => get_option('trolywp_agent_client_site_id', ''),
            'authorId'   => $user_id,
            'authorKey'  => get_user_meta($user_id, 'webo_hmac_key_id', true),
            'chat_token' => self::get_or_create_chat_token($user_id),
        ];
        return apply_filters('trolywp_agent_client_first_entry', $first_entry);
    }

    private static function get_or_create_chat_token($user_id) {
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

    /**
     * Thêm meta + HMAC chuẩn webo, forward sang n8n.
     */
    public static function handle_request(\WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response([], 200);
        }

        $n8n_url = get_option('trolywp_agent_client_n8n_url', '');
        if (empty($n8n_url) || !filter_var($n8n_url, FILTER_VALIDATE_URL)) {
            return new \WP_REST_Response(['error' => 'n8n_url_not_configured'], 400);
        }

        $meta = self::get_first_entry_json();
        $path = wp_parse_url($n8n_url, PHP_URL_PATH) ?: '/';
        $headers = ['Accept' => 'application/json'];
        $key_id = get_current_user_id() ? get_user_meta(get_current_user_id(), 'webo_hmac_key_id', true) : '';

        if ($request->get_method() === 'GET') {
            $url = add_query_arg(array_merge($request->get_query_params(), ['firstEntryJson' => wp_json_encode($meta)]), $n8n_url);
            if (function_exists('webo_hmac_sign_request') && $key_id) {
                $headers = array_merge($headers, (array) webo_hmac_sign_request('GET', $path, '', $key_id));
            }
            $resp = wp_remote_get($url, ['timeout' => 30, 'headers' => $headers]);
        } else {
            $url = add_query_arg($request->get_query_params(), $n8n_url);
            $body = $request->get_body();
            $decoded = json_decode($body, true);
            $payload = is_array($decoded) ? array_merge($decoded, ['firstEntryJson' => $meta]) : ['firstEntryJson' => $meta];
            $body = wp_json_encode($payload);
            if (function_exists('webo_hmac_sign_request') && $key_id) {
                $headers = array_merge($headers, (array) webo_hmac_sign_request('POST', $path, $body, $key_id));
            }
            $headers['Content-Type'] = 'application/json';
            $resp = wp_remote_post($url, ['timeout' => 60, 'headers' => $headers, 'body' => $body]);
        }

        if (is_wp_error($resp)) {
            return new \WP_REST_Response(['error' => $resp->get_error_message()], 502);
        }
        $res = new \WP_REST_Response();
        $res->set_status(wp_remote_retrieve_response_code($resp));
        if ($ct = wp_remote_retrieve_header($resp, 'content-type')) $res->header('Content-Type', $ct);
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $res->set_data($data !== null ? $data : wp_remote_retrieve_body($resp));
        return $res;
    }

    public static function get_proxy_url() {
        return rest_url(self::REST_NAMESPACE . '/' . self::REST_ROUTE);
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
        $key_id = get_user_meta($user_id, 'webo_hmac_key_id', true);
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
