<?php
// includes/class-maljani-support.php

class Maljani_Support {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        add_shortcode('support_chat_form', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_footer', [$this, 'render_floating_button']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_maljani_support_reply', [$this, 'handle_admin_reply']);
        add_action('maljani_process_email_queue', [$this, 'process_email_queue']);
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'maljani-support',
            plugin_dir_url(__FILE__) . 'js/maljani-support.js',
            ['jquery'],
            defined('MALJANI_VERSION') ? MALJANI_VERSION : null,
            true
        );

        wp_enqueue_style('maljani-support-style', plugin_dir_url(__FILE__) . 'css/maljani-support.css', [], defined('MALJANI_VERSION') ? MALJANI_VERSION : null );

        wp_localize_script('maljani-support', 'maljaniSupport', [
            'rest_url' => esc_url_raw( rest_url('support-chat/v1/send') ),
            'nonce' => wp_create_nonce('wp_rest'),
            'unread_url' => esc_url_raw( rest_url('support-chat/v1/unread-count') ),
            'is_admin' => current_user_can('edit_others_posts') ? 1 : 0
        ]);
    }

    public function admin_enqueue_assets() {
        wp_enqueue_script('maljani-support-admin', plugin_dir_url(__FILE__) . 'js/maljani-support-admin.js', ['jquery'], defined('MALJANI_VERSION') ? MALJANI_VERSION : null, true);
        wp_enqueue_style('maljani-support-admin-style', plugin_dir_url(__FILE__) . 'css/maljani-support-admin.css', [], defined('MALJANI_VERSION') ? MALJANI_VERSION : null);
        wp_localize_script('maljani-support-admin', 'maljaniSupportAdmin', [
            'unread_url' => esc_url_raw(rest_url('support-chat/v1/unread-count')),
            'nonce' => wp_create_nonce('wp_rest'),
            'session_url_base' => esc_url_raw(rest_url('support-chat/v1/session/')),
            'session_message_url_base' => esc_url_raw(rest_url('support-chat/v1/session/')) , // append {id}/message
            'ws_url' => esc_url_raw(get_option('maljani_ws_server_url', 'ws://127.0.0.1:8080')),
            'ws_http_broadcast' => esc_url_raw(get_option('maljani_ws_server_http', 'http://127.0.0.1:8080/broadcast'))
        ]);
    }

    public function render_form($atts = []) {
        ob_start();
        ?>
        <div class="maljani-support-form">
            <form id="maljani-support-form">
                <p><label>Email (optional):<br><input type="email" name="email" id="maljani-support-email" style="width:100%"></label></p>
                <p><label>Message:<br><textarea name="message" id="maljani-support-message" rows="6" style="width:100%" required></textarea></label></p>
                <p><button type="submit" class="button">Send</button></p>
                <p id="maljani-support-result" style="display:none"></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_floating_button() {
        // Always render button publicly; admin count populated via REST
        ?>
        <div class="maljani-support-button" id="maljani-support-float">
            <span>💬 Support</span>
            <span class="count" style="display:inline-block;min-width:20px;text-align:center;">0</span>
        </div>

        <div class="maljani-support-modal" id="maljani-support-modal">
            <div class="header">Support</div>
            <div class="body">
                <div class="maljani-public-conversation" id="maljani-public-conversation" style="display:none;margin-bottom:12px;"></div>
                <form id="maljani-support-form-float">
                    <p><input type="email" id="maljani-support-email-float" placeholder="Your email (optional)"></p>
                    <p><textarea id="maljani-support-message-float" placeholder="Message" required></textarea></p>
                    <p class="actions"><button type="button" class="button" id="maljani-support-send-float">Send</button></p>
                    <p id="maljani-support-result-float" style="display:none"></p>
                </form>
            </div>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('maljani-support-float');
            var modal = document.getElementById('maljani-support-modal');
            var send = document.getElementById('maljani-support-send-float');
            var result = document.getElementById('maljani-support-result-float');
            if (!btn || !modal) return;
            btn.addEventListener('click', function(){ modal.style.display = modal.style.display === 'block' ? 'none' : 'block'; });
            if (send) send.addEventListener('click', function(){
                var email = document.getElementById('maljani-support-email-float').value || '';
                var message = document.getElementById('maljani-support-message-float').value || '';
                if (!message.trim()) { result.style.display='block'; result.textContent='Please enter a message.'; return; }
                fetch(maljaniSupport.rest_url, { method: 'POST', headers: { 'Content-Type':'application/json', 'X-WP-Nonce': maljaniSupport.nonce }, body: JSON.stringify({ email: email, message: message }) })
                .then(function(r){ return r.json(); }).then(function(d){ if (d && d.success) { result.style.display='block'; result.textContent='Message sent.'; document.getElementById('maljani-support-message-float').value=''; } else { result.style.display='block'; result.textContent=(d && d.message)?d.message:'Error'; } }).catch(function(){ result.style.display='block'; result.textContent='Network error.'; });
            });
        })();
        </script>
        <?php
    }

    public function register_routes() {
        register_rest_route('support-chat/v1', '/send', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_send'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('support-chat/v1', '/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_unread_count'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); }
        ]);

        register_rest_route('support-chat/v1', '/session/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_session'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); }
        ]);

        register_rest_route('support-chat/v1', '/session/(?P<id>\d+)/message', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_post_message'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); }
        ]);

        register_rest_route('support-chat/v1', '/session/(?P<id>\d+)/public', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_session_public'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function rest_get_session(WP_REST_Request $request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $after_id = $request->get_param('after_id') ? intval($request->get_param('after_id')) : 0;
        $messages_table = $wpdb->prefix . 'support_messages';

        if ($after_id > 0) {
            $msgs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages_table} WHERE session_id = %d AND id > %d ORDER BY created_at ASC", $id, $after_id));
        } else {
            $msgs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages_table} WHERE session_id = %d ORDER BY created_at ASC", $id));
        }

        return new WP_REST_Response(['messages' => $msgs], 200);
    }

    public function rest_post_message(WP_REST_Request $request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $message = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';

        if (empty($message)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Message required'], 400);
        }

        $messages_table = $wpdb->prefix . 'support_messages';
        $sessions_table = $wpdb->prefix . 'support_sessions';

        $inserted = $wpdb->insert($messages_table, [
            'session_id' => $id,
            'sender' => 'agent',
            'user_id' => get_current_user_id(),
            'email' => null,
            'message' => $message,
            'status' => 'new'
        ], ['%d','%s','%d','%s','%s','%s']);

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'message' => $wpdb->last_error], 500);
        }

        $msg_id = $wpdb->insert_id;
        $wpdb->update($sessions_table, ['last_message_at' => current_time('mysql', 1)], ['id' => $id], ['%s'], ['%d']);

        // Notify user via email if session has email
        $sess = $wpdb->get_row($wpdb->prepare("SELECT email FROM {$sessions_table} WHERE id = %d", $id));
        if ($sess && ! empty($sess->email)) {
            $subtpl = get_option('maljani_support_email_response_subject', 'Response to your support message #{session_id}');
            $bodytpl = get_option('maljani_support_email_response_body', "Hello,\n\nA support representative has replied to your message:\n\n{response}\n\nRegards");
            $subject_final = self::format_template($subtpl, ['session_id'=>$id,'email'=>$sess->email,'message'=>'','response'=>$message]);
            $body_final = self::format_template($bodytpl, ['session_id'=>$id,'email'=>$sess->email,'message'=>'','response'=>$message]);
            self::queue_email($sess->email, $subject_final, $body_final);
        }

        // Notify WS server about new agent message
        $payload = [ 'type' => 'message', 'role' => 'agent', 'session_id' => $id, 'message_id' => $msg_id, 'email' => (!empty($sess->email) ? $sess->email : null), 'message' => $message ];
        $this->notify_ws_server($payload);

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$messages_table} WHERE id = %d", $msg_id));
        return new WP_REST_Response(['success' => true, 'message' => $row], 200);
    }

    private function notify_ws_server($payload) {
        $url = get_option('maljani_ws_server_http', 'http://127.0.0.1:8080/broadcast');
        if (empty($url)) return false;
        $args = [ 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode($payload), 'timeout' => 2 ];
        // best-effort, non-fatal
        try {
            wp_remote_post($url, $args);
        } catch (Exception $e) {
            // ignore
        }
        return true;
    }

    public function rest_send(WP_REST_Request $request) {
        global $wpdb;

        $params = $request->get_json_params();
        $email = !empty($params['email']) ? sanitize_email($params['email']) : '';
        $message = !empty($params['message']) ? sanitize_textarea_field($params['message']) : '';

        if (empty($message)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Message required'], 400);
        }

        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        // Ensure sessions/messages tables exist (db tools should create them)
        $sessions_table = $wpdb->prefix . 'support_sessions';
        $messages_table = $wpdb->prefix . 'support_messages';

        // Find existing open session for this user/email, otherwise create one
        $session = null;
        if ($user_id) {
            $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE user_id = %d AND status = %s ORDER BY last_message_at DESC LIMIT 1", $user_id, 'open'));
        }
        if (!$session && ! empty($email)) {
            $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE email = %s AND status = %s ORDER BY last_message_at DESC LIMIT 1", $email, 'open'));
        }

        if (! $session) {
            $created = $wpdb->insert($sessions_table, [
                'user_id' => $user_id ? intval($user_id) : null,
                'email' => $email,
                'subject' => null,
                'status' => 'open',
                'last_message_at' => current_time('mysql', 1)
            ], [$user_id ? '%d' : '%s', '%s', '%s', '%s', '%s']);

            if ($created === false) {
                return new WP_REST_Response(['success' => false, 'message' => $wpdb->last_error], 500);
            }
            $session_id = $wpdb->insert_id;
        } else {
            $session_id = intval($session->id);
            $wpdb->update($sessions_table, ['last_message_at' => current_time('mysql', 1)], ['id' => $session_id], ['%s'], ['%d']);
        }

        $inserted = $wpdb->insert($messages_table, [
            'session_id' => $session_id,
            'sender' => 'user',
            'user_id' => $user_id ? intval($user_id) : null,
            'email' => $email,
            'message' => $message,
            'status' => 'new'
        ], [$user_id ? '%d' : '%s', '%s', '%d', '%s', '%s', '%s']);

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'message' => $wpdb->last_error], 500);
        }

        $id = $wpdb->insert_id; // message id

        // Queue admin notification using template
        $admin_email = get_option('admin_email');
        $subtpl = get_option('maljani_support_email_new_subject', 'New support message: #{id}');
        $bodytpl = get_option('maljani_support_email_new_body', "A new support message (ID: {id})\nFrom: {email}\n\n{message}");
        // Notify WS server about new user message (if configured)
        $payload = [ 'type' => 'message', 'role' => 'user', 'session_id' => $session_id, 'message_id' => $id, 'email' => $email, 'message' => $message ];
        $this->notify_ws_server($payload);

        // Include session reference in notification
        $subject_final = self::format_template($subtpl, ['id'=>$id,'session_id'=>$session_id,'email'=>$email,'message'=>$message,'response'=>'']);
        $body_final = self::format_template($bodytpl, ['id'=>$id,'session_id'=>$session_id,'email'=>$email,'message'=>$message,'response'=>'']);
        self::queue_email($admin_email, $subject_final, $body_final);

        // Set a session cookie so public client can fetch conversation history
        if (! headers_sent()) {
            setcookie('maljani_session_id', $session_id, time() + (30*24*60*60), COOKIEPATH ?: '/');
        }

        return new WP_REST_Response(['success' => true, 'id' => $id, 'session_id' => $session_id], 200);
    }

    // Public session messages endpoint (allows user access via cookie or matching email)
    public function rest_get_session_public(WP_REST_Request $request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $email_param = $request->get_param('email');

        $sessions_table = $wpdb->prefix . 'support_sessions';
        $messages_table = $wpdb->prefix . 'support_messages';

        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE id = %d", $id));
        if (! $session) {
            return new WP_REST_Response(['success' => false, 'message' => 'Session not found'], 404);
        }

        // Allow if current user owns the session
        if (is_user_logged_in() && intval($session->user_id) === get_current_user_id()) {
            $allowed = true;
        } elseif (! empty($_COOKIE['maljani_session_id']) && intval($_COOKIE['maljani_session_id']) === $id) {
            $allowed = true;
        } elseif (! empty($email_param) && sanitize_email($email_param) === $session->email) {
            $allowed = true;
        } else {
            $allowed = false;
        }

        if (! $allowed) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $msgs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages_table} WHERE session_id = %d ORDER BY created_at ASC", $id));
        return new WP_REST_Response(['success' => true, 'messages' => $msgs, 'session' => $session], 200);
    }

    public function rest_unread_count(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'support_chat';
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'new'));
        return new WP_REST_Response(['count' => intval($count)], 200);
    }

    private static function format_template($template, $replacements = []) {
        $search = array_map(function($k){ return '{' . $k . '}'; }, array_keys($replacements));
        $replace = array_values($replacements);
        return str_replace($search, $replace, $template);
    }

    public static function queue_email($to, $subject, $body) {
        global $wpdb;
        $table = $wpdb->prefix . 'support_email_queue';
        $inserted = $wpdb->insert($table, [
            'to_email' => $to,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => current_time('mysql', 1)
        ], ['%s','%s','%s','%s','%d','%s']);
        if (! wp_next_scheduled('maljani_process_email_queue')) {
            wp_schedule_single_event(time() + 5, 'maljani_process_email_queue');
        }
        return $inserted !== false;
    }

    public function process_email_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'support_email_queue';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC LIMIT 50", 'queued'));
        if (empty($rows)) return;
        foreach ($rows as $r) {
            $sent = false;
            if (! empty($r->to_email)) {
                $sent = wp_mail($r->to_email, $r->subject, $r->body);
            }
            if ($sent) {
                $wpdb->update($table, ['status' => 'sent', 'updated_at' => current_time('mysql', 1)], ['id' => $r->id], ['%s'], ['%d']);
            } else {
                $attempts = intval($r->attempts) + 1;
                $status = $attempts >= 5 ? 'failed' : 'queued';
                $wpdb->update($table, ['attempts' => $attempts, 'status' => $status, 'updated_at' => current_time('mysql', 1)], ['id' => $r->id], ['%d','%s','%s'], ['%d']);
            }
        }
        // if there are still queued items, schedule next attempt
        $remaining = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'queued'));
        if (intval($remaining) > 0 && ! wp_next_scheduled('maljani_process_email_queue')) {
            wp_schedule_single_event(time() + 60, 'maljani_process_email_queue');
        }
    }

    public function admin_menu() {
        $cap = 'edit_others_posts'; // available to editors and administrators
        // compute unread count
        global $wpdb;
        $table = $wpdb->prefix . 'support_chat';
        $unread = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'new')));
        $label = 'Support Chat' . ($unread > 0 ? " ({$unread})" : '');
        add_menu_page('Support Chat', $label, $cap, 'maljani-support', [$this, 'render_admin_page'], 'dashicons-testimonial', 58);
    }

    public function render_admin_page() {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'support_sessions';
        $messages_table = $wpdb->prefix . 'support_messages';

        // If a specific session is requested, show conversation
        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        if ($session_id) {
            // Mark user messages as read for admin
            $wpdb->update($messages_table, ['status' => 'read'], ['session_id' => $session_id, 'sender' => 'user'], ['%s'], ['%d', '%s']);
            $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE id = %d", $session_id));
            $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages_table} WHERE session_id = %d ORDER BY created_at ASC", $session_id));
        } else {
            // list recent sessions
            $sessions = $wpdb->get_results("SELECT * FROM {$sessions_table} ORDER BY last_message_at DESC LIMIT 200");
        }

        ?>
        <div class="wrap">
            <h1>Support Chat</h1>
            <?php if ($session_id && isset($session)): ?>
                <h2>Conversation: <?php echo esc_html($session->email ?: 'Session #' . $session->id); ?></h2>
                <div class="maljani-conversation" style="background:#fff;padding:16px;border:1px solid #ddd;max-width:900px;">
                        <?php foreach ($messages as $m): ?>
                            <div class="maljani-message <?php echo esc_attr($m->sender === 'agent' ? 'agent' : 'user'); ?>" data-message-id="<?php echo intval($m->id); ?>" style="margin-bottom:12px;">
                                <div class="meta"><strong><?php echo esc_html($m->sender === 'agent' ? 'Agent' : ($m->email ?: 'User')); ?></strong>
                                <span class="time" style="color:#666;margin-left:8px;"><?php echo esc_html($m->created_at); ?></span></div>
                                <div class="bubble" style="margin-top:6px;padding:8px;background:#f7f7f7;border-radius:4px;max-width:80%;"><?php echo nl2br(esc_html($m->message)); ?></div>
                            </div>
                        <?php endforeach; ?>
                </div>
                <h3>Reply</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:900px;">
                    <?php wp_nonce_field('maljani_support_reply', 'maljani_support_nonce'); ?>
                    <input type="hidden" name="action" value="maljani_support_reply">
                    <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                    <p><textarea name="message" rows="4" style="width:100%"></textarea></p>
                    <p><button class="button button-primary" type="submit">Send Reply</button></p>
                </form>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=maljani-support')); ?>">Back to sessions</a></p>
            <?php else: ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr><th>ID</th><th>Email</th><th>Last Message</th><th>Status</th><th>Last Updated</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s):
                        $sid = intval($s->id);
                        $semail = isset($s->email) ? (string)$s->email : '';
                        $sstatus = isset($s->status) ? (string)$s->status : '';
                        $slast = isset($s->last_message_at) ? (string)$s->last_message_at : '';
                        // fetch last message preview
                        $last_msg = $wpdb->get_var($wpdb->prepare("SELECT message FROM {$messages_table} WHERE session_id = %d ORDER BY created_at DESC LIMIT 1", $sid));
                    ?>
                        <?php $unread_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$messages_table} WHERE session_id = %d AND sender = %s AND status = %s", $sid, 'user', 'new'))); ?>
                        <tr>
                            <td><?php echo esc_html($sid); ?></td>
                            <td><?php echo esc_html($semail); ?><?php if ($unread_count > 0) echo ' <span class="maljani-session-unread">' . esc_html($unread_count) . '</span>'; ?></td>
                            <td><?php echo esc_html(wp_trim_words((string)$last_msg, 20)); ?></td>
                            <td><?php echo esc_html($sstatus); ?></td>
                            <td><?php echo esc_html($slast); ?></td>
                            <td><a class="button" href="<?php echo esc_url(add_query_arg('session_id', $sid, admin_url('admin.php?page=maljani-support'))); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_admin_reply() {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }

        if (! isset($_POST['maljani_support_nonce']) || ! wp_verify_nonce($_POST['maljani_support_nonce'], 'maljani_support_reply')) {
            wp_die('Invalid nonce');
        }

        global $wpdb;

        // If replying to a session (conversational), insert an agent message into messages table
        if (! empty($_POST['session_id'])) {
            $session_id = intval($_POST['session_id']);
            $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
            if (empty($message)) {
                wp_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=maljani-support'));
                exit;
            }

            $messages_table = $wpdb->prefix . 'support_messages';
            $sessions_table = $wpdb->prefix . 'support_sessions';

            $inserted = $wpdb->insert($messages_table, [
                'session_id' => $session_id,
                'sender' => 'agent',
                'user_id' => get_current_user_id(),
                'email' => null,
                'message' => $message,
                'status' => 'new'
            ], ['%d','%s','%d','%s','%s','%s']);

            if ($inserted !== false) {
                // update session last_message_at
                $wpdb->update($sessions_table, ['last_message_at' => current_time('mysql', 1)], ['id' => $session_id], ['%s'], ['%d']);

                // send notification to session email if present
                $sess = $wpdb->get_row($wpdb->prepare("SELECT email FROM {$sessions_table} WHERE id = %d", $session_id));
                if ($sess && ! empty($sess->email)) {
                    $subtpl = get_option('maljani_support_email_response_subject', 'Response to your support message #{session_id}');
                    $bodytpl = get_option('maljani_support_email_response_body', "Hello,\n\nA support representative has replied to your message:\n\n{response}\n\nRegards");
                    $subject_final = self::format_template($subtpl, ['session_id'=>$session_id,'email'=>$sess->email,'message'=>'','response'=>$message]);
                    $body_final = self::format_template($bodytpl, ['session_id'=>$session_id,'email'=>$sess->email,'message'=>'','response'=>$message]);
                    self::queue_email($sess->email, $subject_final, $body_final);
                }
            }

            wp_redirect(add_query_arg('session_id', $session_id, admin_url('admin.php?page=maljani-support')));
            exit;
        }

        // Fallback: legacy single-message support_chat handling
        $id = isset($_POST['support_id']) ? intval($_POST['support_id']) : 0;
        $response = isset($_POST['response']) ? sanitize_textarea_field($_POST['response']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'answered';

        $table = $wpdb->prefix . 'support_chat';

        $updated = $wpdb->update(
            $table,
            [
                'response' => $response,
                'status' => $status,
                'updated_at' => current_time('mysql', 1)
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            // Send notification to user if email exists
            $row = $wpdb->get_row($wpdb->prepare("SELECT email FROM $table WHERE id = %d", $id));
            if ($row && ! empty($row->email)) {
                $subtpl = get_option('maljani_support_email_response_subject', 'Response to your support message #{id}');
                $bodytpl = get_option('maljani_support_email_response_body', "Hello,\n\nA support representative has replied to your message:\n\n{response}\n\nRegards");
                $subject_final = self::format_template($subtpl, ['id'=>$id,'email'=>$row->email,'message'=>'','response'=>$response]);
                $body_final = self::format_template($bodytpl, ['id'=>$id,'email'=>$row->email,'message'=>'','response'=>$response]);
                self::queue_email($row->email, $subject_final, $body_final);
            }
        }

        wp_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=maljani-support'));
        exit;
    }
}

// Initialize
if (defined('ABSPATH')) {
    Maljani_Support::init();
}
