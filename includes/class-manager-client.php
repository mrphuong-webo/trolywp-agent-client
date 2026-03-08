<?php
// HTTP client + HMAC integration from TrolyWP Agent Client to Manager hub.

if (!defined('ABSPATH')) exit;

class TrolyWP_Agent_Client_Manager_Client {

    /**
     * Base URL REST API của manager hub, ví dụ:
     * https://trolywp.com/wp-json/trolywp-manager/v1
     */
    protected static function get_manager_api_base(): string {
        $url = get_option('trolywp_agent_client_manager_url', '');
        if (empty($url)) {
            $url = 'https://trolywp.com/wp-json/trolywp-manager/v1';
        }
        return untrailingslashit($url);
    }

    /**
     * Lấy hoặc tạo site_id (UUID) cho client.
     */
    public static function get_site_id(): string {
        $site_id = get_option('trolywp_agent_client_site_id', '');
        if (!$site_id) {
            $site_id = wp_generate_uuid4();
            update_option('trolywp_agent_client_site_id', $site_id);
        }
        return $site_id;
    }

    /**
     * Ký payload bằng webo-hmac-auth và trả về headers + payload đã thêm timestamp/nonce.
     *
     * @return array{ok:bool, error?:string, headers?:array, payload?:array}
     */
    protected static function sign_payload(array $payload): array {
        if (!function_exists('webo_hmac_sign')) {
            return ['ok' => false, 'error' => 'webo_hmac_sign_not_available'];
        }

        $key_id = get_option('webo_hmac_key_id', '');
        $secret = get_option('webo_hmac_secret', '');

        if (empty($key_id) || empty($secret)) {
            return ['ok' => false, 'error' => 'missing_hmac_credentials'];
        }

        $payload['timestamp'] = time();
        $payload['nonce']     = wp_generate_uuid4();

        $signature = webo_hmac_sign($payload, $secret);

        $headers = [
            'Content-Type'     => 'application/json',
            'X-WEBO-KEY-ID'    => $key_id,
            'X-WEBO-SIGNATURE' => $signature,
            'X-WEBO-TIMESTAMP' => $payload['timestamp'],
            'X-WEBO-NONCE'     => $payload['nonce'],
        ];

        return [
            'ok'      => true,
            'headers' => $headers,
            'payload' => $payload,
        ];
    }

    /**
     * Helper gửi request POST JSON tới manager.
     *
     * @param string $path Ví dụ: '/register-client'
     * @param array  $payload Payload thô (chưa ký HMAC)
     * @return array
     */
    protected static function post(string $path, array $payload): array {
        $signed = self::sign_payload($payload);
        if (!$signed['ok']) {
            return $signed;
        }

        $response = wp_remote_post(
            self::get_manager_api_base() . $path,
            [
                'headers' => $signed['headers'],
                'body'    => wp_json_encode($signed['payload']),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return [
                'ok'    => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return [
                'ok'    => false,
                'error' => 'invalid_manager_response',
            ];
        }

        return $body;
    }

    /**
     * Đăng ký site client với manager hub.
     *
     * @return array
     */
    public static function register_site(): array {
        $site_id     = self::get_site_id();
        $payload = [
            'site_id'     => $site_id,
            'domain'      => get_site_url(),
            'site_name'   => get_bloginfo('name'),
            'admin_email' => get_bloginfo('admin_email'),
        ];

        $result = self::post('/register-client', $payload);

        if (!empty($result['ok']) && $result['ok']) {
            update_option('trolywp_agent_client_site_status', 'active');
        }

        return $result;
    }

    /**
     * Đồng bộ authors (administrator/editor/author) lên manager.
     *
     * @return array
     */
    public static function sync_authors(): array {
        $site_id = self::get_site_id();

        $authors = get_users([
            'role__in' => ['administrator', 'editor', 'author'],
            'fields'   => ['ID', 'display_name', 'user_email', 'roles'],
        ]);

        $author_payload = [];
        foreach ($authors as $user) {
            $author_payload[] = [
                'wp_user_id'   => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'role'         => implode(',', $user->roles),
                'avatar'       => get_avatar_url($user->ID),
            ];
        }

        if (empty($author_payload)) {
            return ['ok' => true, 'imported' => 0];
        }

        $payload = [
            'site_id' => $site_id,
            'authors' => $author_payload,
        ];

        return self::post('/sync-authors', $payload);
    }

    /**
     * Gửi chat message từ client lên manager.
     *
     * @param array $payload
     * @return array
     */
    public static function send_chat(array $payload): array {
        // Bổ sung site_id nếu thiếu.
        if (empty($payload['site_id'])) {
            $payload['site_id'] = self::get_site_id();
        }

        return self::post('/chat', $payload);
    }
}

