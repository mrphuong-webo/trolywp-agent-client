<?php
/*
Plugin Name: TrolyWP Agent Client
Description: Chat AI qua n8n (widget @n8n/chat), gửi metadata site/user. Cần webo-hmac-auth.
Version: 1.0.5
Author: TrolyWP
Text Domain: trolywp-agent-client
Requires at least: 6.0
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	'trolywp_agent_client_load_textdomain'
);

/**
 * Load plugin textdomain.
 *
 * @return void
 */
function trolywp_agent_client_load_textdomain() {
	load_plugin_textdomain(
		'trolywp-agent-client',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

add_action( 'wp_footer', 'trolywp_agent_client_inject_chat' );
add_action( 'admin_footer', 'trolywp_agent_client_inject_chat' );

/**
 * Get or create a persistent chat session ID for the user (stored in user_meta).
 * Same ID on every page load so n8n can restore conversation history by sessionId.
 *
 * @param int $user_id User ID.
 * @return string Session ID (UUID-style).
 */
function trolywp_agent_client_get_or_create_session_id( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return '';
	}
	$key    = 'trolywp_chat_session_id';
	$stored = get_user_meta( $user_id, $key, true );
	if ( is_string( $stored ) && preg_match( '/^[a-f0-9\-]{20,}$/i', $stored ) ) {
		return $stored;
	}
	$session_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
	update_user_meta( $user_id, $key, $session_id );
	return $session_id;
}

/**
 * Inject n8n chat widget for logged-in users.
 *
 * @return void
 */
function trolywp_agent_client_inject_chat() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	$n8n_url = get_option( 'trolywp_agent_client_n8n_url', '' );
	if ( empty( $n8n_url ) || ! filter_var( $n8n_url, FILTER_VALIDATE_URL ) ) {
		return;
	}

	$user = wp_get_current_user();

	$metadata = array(
		'site_url'     => home_url( '/' ),
		'site_name'    => get_bloginfo( 'name' ),
		'language'     => get_bloginfo( 'language' ),
		'user_id'      => $user_id,
		'user_email'   => isset( $user->user_email ) ? $user->user_email : '',
		'display_name' => isset( $user->display_name ) ? $user->display_name : '',
		'user_roles'   => implode( ',', array_values( array_filter( (array) $user->roles ) ) ),
	);

	if ( is_multisite() ) {
		$metadata['blog_id'] = get_current_blog_id();
	}

	$metadata = apply_filters( 'trolywp_agent_client_chat_metadata', $metadata );

	// Persistent session ID per user so n8n can store/load conversation history by sessionId.
	$first_entry = array(
		'metadata'   => $metadata,
		'siteId'     => get_option( 'trolywp_agent_client_site_id', '' ),
		'authorId'   => $user_id,
		'authorKey'  => function_exists( 'webo_hmac_get_key_id_for_user' )
			? webo_hmac_get_key_id_for_user( $user_id )
			: get_user_meta( $user_id, 'webo_hmac_key_id', true ),
		'sessionId' => trolywp_agent_client_get_or_create_session_id( $user_id ),
	);

	$first_entry = apply_filters( 'trolywp_agent_client_first_entry', $first_entry );

	$first_entry['chat_token'] = TrolyWP_Agent_Client_Chat_Proxy::get_or_create_chat_token( $user_id );

	// Per-author AI token + type (like Copilot). Fallback to site default if author has not set their own.
	$ai_token      = get_user_meta( $user_id, 'trolywp_ai_token', true );
	$ai_token_type = get_user_meta( $user_id, 'trolywp_ai_token_type', true );
	if ( ! is_string( $ai_token ) || $ai_token === '' || ! is_string( $ai_token_type ) || $ai_token_type === '' ) {
		$ai_token      = get_option( 'trolywp_agent_client_ai_token', '' );
		$ai_token_type = get_option( 'trolywp_agent_client_ai_token_type', '' );
	}
	if ( is_string( $ai_token ) && $ai_token !== '' && is_string( $ai_token_type ) && $ai_token_type !== '' ) {
		$first_entry['aiToken']     = $ai_token;
		$first_entry['aiTokenType'] = $ai_token_type;
	}

	// Chat gọi thẳng n8n, gửi kèm meta (firstEntryJson). Không proxy.
	$config = array(
		'n8nUrl'        => $n8n_url,
		'firstEntryJson'=> $first_entry,
	);

	$config_json = wp_json_encode(
		$config,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	wp_enqueue_style(
		'n8n-chat-widget',
		'https://cdn.jsdelivr.net/npm/@n8n/chat@1.12.0/dist/style.css',
		array(),
		'1.12.0'
	);

	// Container for chat widget.
	echo '<div id="trolywp-n8n-chat-root" style="position:fixed;bottom:20px;right:20px;z-index:999999;"></div>';

	// Register a lightweight loader script and attach inline module logic.
	wp_register_script(
		'trolywp-agent-client-chat-loader',
		'',
		array(),
		'1.0.5',
		true
	);

	wp_enqueue_script( 'trolywp-agent-client-chat-loader' );

	$inline = "window.TrolywpClientChatConfig = {$config_json};\n";
	$inline .= "(function(){ var c = window.TrolywpClientChatConfig || {}; ";
	$inline .= "if(!c.n8nUrl) return; ";
	$inline .= "import('https://cdn.jsdelivr.net/npm/@n8n/chat@1.12.0/dist/chat.bundle.es.js').then(function(m){ ";
	$inline .= "m.createChat({ webhookUrl: c.n8nUrl, metadata: c.firstEntryJson || {}, target: '#trolywp-n8n-chat-root', loadPreviousSession: true }); ";
	$inline .= "}).catch(function(e){ console.error('TrolyWP n8n chat:', e); }); })();";

	wp_add_inline_script(
		'trolywp-agent-client-chat-loader',
		$inline
	);
}

register_activation_hook(__FILE__, function() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('webo-hmac-auth/webo-hmac-auth.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('TrolyWP Agent Client cần cài đặt và kích hoạt plugin <b>webo-hmac-auth</b> để hoạt động.');
    }
    if (!get_option('trolywp_agent_client_site_id')) {
        update_option('trolywp_agent_client_site_id', wp_generate_uuid4());
    }
});

require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-utils.php';
require_once __DIR__ . '/includes/class-chat-proxy.php';

add_action('admin_menu', ['TrolyWP_Agent_Client_Admin', 'menu']);
add_action('admin_notices', ['TrolyWP_Agent_Client_Utils', 'dependency_notice']);
add_action('rest_api_init', ['TrolyWP_Agent_Client_Chat_Proxy', 'register_routes']);
