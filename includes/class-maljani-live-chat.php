<?php

class Maljani_Live_Chat {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_footer', [$this, 'render_widget']);
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'maljani-live-chat',
            plugin_dir_url(__FILE__) . 'js/maljani-live-chat.js',
            ['jquery'],
            defined('MALJANI_VERSION') ? MALJANI_VERSION : time(),
            true
        );

        wp_enqueue_style(
            'maljani-live-chat-style',
            plugin_dir_url(__FILE__) . 'css/maljani-live-chat.css',
            [],
            defined('MALJANI_VERSION') ? MALJANI_VERSION : time()
        );

        wp_localize_script('maljani-live-chat', 'maljaniChatParams', [
            'rest_url' => esc_url_raw( rest_url('maljani-chat/v1') ),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    public function render_widget() {
        ?>
        <div id="maljani-live-chat-widget" class="maljani-chat-closed">
            <div class="maljani-chat-header" id="maljani-chat-header">
                <span class="maljani-chat-title">Live Support</span>
                <button id="maljani-chat-toggle" class="maljani-chat-btn">-</button>
            </div>
            <div class="maljani-chat-body" id="maljani-chat-body" style="display:none;">
                <div class="maljani-chat-messages" id="maljani-chat-messages">
                    <div class="maljani-chat-msg system">
                        <div class="bubble">Welcome to our support! Enter your email to start.</div>
                    </div>
                </div>
                <div id="maljani-chat-start-form" class="maljani-chat-form">
                    <input type="email" id="maljani-chat-email" placeholder="Email address..." required />
                    <button id="maljani-chat-start-btn">Start Chat</button>
                </div>
                <div id="maljani-chat-input-area" class="maljani-chat-form" style="display:none;">
                    <textarea id="maljani-chat-input" placeholder="Type a message..."></textarea>
                    <button id="maljani-chat-send-btn">Send</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_routes() {
        register_rest_route('maljani-chat/v1', '/start', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_start_chat'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('maljani-chat/v1', '/message', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_send_message'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('maljani-chat/v1', '/poll', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_poll_messages'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function rest_start_chat(WP_REST_Request $request) {
        global $wpdb;
        $email = sanitize_email($request->get_param('email'));
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        if (empty($email) && !$user_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Email is required.'], 400);
        }

        $table = $wpdb->prefix . 'maljani_chat_conversations';

        $conv = null;
        if ($user_id) {
            $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d AND status = 'active' LIMIT 1", $user_id));
        }
        if (!$conv && $email) {
            $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s AND status = 'active' LIMIT 1", $email));
        }

        if (!$conv) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'email' => $email,
                'status' => 'active',
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1)
            ], ['%d', '%s', '%s', '%s', '%s']);
            $conv_id = $wpdb->insert_id;
        } else {
            $conv_id = $conv->id;
        }
        
        $token = $this->generate_token($conv_id);

        return new WP_REST_Response([
            'success' => true,
            'conversation_id' => $conv_id,
            'token' => $token
        ], 200);
    }

    public function rest_send_message(WP_REST_Request $request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $token = sanitize_text_field($request->get_param('token'));
        $message = sanitize_textarea_field($request->get_param('message'));

        if (!$this->verify_token($token, $conv_id) && !current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (empty($message)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Message is empty'], 400);
        }

        $sender_type = current_user_can('edit_others_posts') && isset($_POST['is_agent']) ? 'agent' : 'user';
        // Check if explicit agent sender is passed (for admin replies)
        if ($request->get_param('is_agent') && current_user_can('edit_others_posts')) {
            $sender_type = 'agent';
        }
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        $msg_table = $wpdb->prefix . 'maljani_chat_messages';
        $wpdb->insert($msg_table, [
            'conversation_id' => $conv_id,
            'sender_type' => $sender_type,
            'user_id' => $user_id,
            'message' => $message,
            'created_at' => current_time('mysql', 1)
        ], ['%d', '%s', '%d', '%s', '%s']);
        
        $wpdb->update(
            $wpdb->prefix . 'maljani_chat_conversations',
            ['updated_at' => current_time('mysql', 1)],
            ['id' => $conv_id],
            ['%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true, 'message_id' => $wpdb->insert_id], 200);
    }

    public function rest_poll_messages(WP_REST_Request $request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $token = sanitize_text_field($request->get_param('token'));
        $last_id = intval($request->get_param('last_id'));

        if (!$this->verify_token($token, $conv_id) && !current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $msg_table = $wpdb->prefix . 'maljani_chat_messages';
        
        if (!current_user_can('edit_others_posts') || !$request->get_param('is_agent')) {
             $wpdb->query($wpdb->prepare("UPDATE $msg_table SET is_read = 1 WHERE conversation_id = %d AND sender_type = 'agent' AND is_read = 0", $conv_id));
        } else {
             $wpdb->query($wpdb->prepare("UPDATE $msg_table SET is_read = 1 WHERE conversation_id = %d AND sender_type = 'user' AND is_read = 0", $conv_id));
        }

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, sender_type, message, created_at FROM $msg_table 
             WHERE conversation_id = %d AND id > %d ORDER BY id ASC", 
            $conv_id, $last_id
        ));

        return new WP_REST_Response(['success' => true, 'messages' => $messages], 200);
    }

    private function generate_token($conv_id) {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'maljani-secret';
        return hash_hmac('sha256', (string)$conv_id, $key);
    }

    private function verify_token($token, $conv_id) {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'maljani-secret';
        $expected = hash_hmac('sha256', (string)$conv_id, $key);
        return hash_equals($expected, $token);
    }
}

if (defined('ABSPATH')) {
    Maljani_Live_Chat::init();
}
