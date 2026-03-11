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
        return apply_filters('trolywp_agent_client_first_entry', $first_entry);
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
}
