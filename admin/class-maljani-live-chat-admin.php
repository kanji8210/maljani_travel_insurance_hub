<?php
class Maljani_Live_Chat_Admin {
    public static function init() {
        return new self();
    }
    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    public function admin_menu() {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_chat_messages';
        // count unread user messages
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $unread = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE sender_type = 'user' AND is_read = 0"));
            $label = 'Live Chat' . ($unread > 0 ? " <span class='update-plugins count-$unread'><span class='plugin-count'>$unread</span></span>" : '');
        } else {
            $label = 'Live Chat';
        }
        add_submenu_page('maljani_travel', 'Live Support Chat', $label, 'edit_others_posts', 'maljani-live-chat', [$this, 'render_page']);
    }
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_maljani-live-chat') return;
    }
    public function render_page() {
        global $wpdb;
        $conv_table = $wpdb->prefix . 'maljani_chat_conversations';
        $msg_table = $wpdb->prefix . 'maljani_chat_messages';

        $conv_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
        $policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
        
        echo '<div class="wrap"><h1>Live Support Chat Dashboard</h1>';
        
        if ($policy_id && !$conv_id) {
            // Find conversation for this policy
            $conv_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $conv_table WHERE policy_id = %d AND status = 'active' LIMIT 1", $policy_id));
            if (!$conv_id) {
                // If not exists, we might need to create it or show a message
                // For now, let's just show the list or a "Start New" if we have client info
                $policy = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "policy_sale WHERE id = %d", $policy_id));
                if ($policy) {
                    $wpdb->insert($conv_table, [
                        'user_id' => $policy->agent_id,
                        'email' => $policy->insured_email,
                        'policy_id' => $policy_id,
                        'status' => 'active',
                        'created_at' => current_time('mysql', 1),
                        'updated_at' => current_time('mysql', 1)
                    ], ['%d', '%s', '%d', '%s', '%s', '%s']);
                    $conv_id = $wpdb->insert_id;
                }
            }
        }

        if ($conv_id) {
            // mark as read
            $wpdb->query($wpdb->prepare("UPDATE $msg_table SET is_read = 1 WHERE conversation_id = %d AND sender_type = 'user'", $conv_id));
            $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $conv_table WHERE id = %d", $conv_id));
            
            // Single view
            ?>
            <div id="maljani-admin-chat" data-id="<?php echo esc_attr($conv_id); ?>" data-token="<?php echo esc_attr(hash_hmac('sha256', (string)$conv_id, defined('AUTH_KEY') ? AUTH_KEY : 'maljani-secret')); ?>">
                <h2>Conversation with <?php echo esc_html($conv->email ?: 'Guest User'); ?> 
                    <?php if ($conv->policy_id): ?>
                        <span style="font-size:14px;color:#888;">(Policy #<?php 
                            echo esc_html($wpdb->get_var($wpdb->prepare("SELECT policy_number FROM " . $wpdb->prefix . "policy_sale WHERE id = %d", $conv->policy_id))); 
                        ?>)</span>
                    <?php endif; ?>
                </h2>
                <div id="maljani-admin-messages" style="background:#fff;border:1px solid #ccc;padding:15px;height:400px;overflow-y:auto;margin-bottom:15px;display:flex;flex-direction:column;">
                    <!-- loaded via JS -->
                </div>
                <div id="maljani-admin-reply" style="display:flex;gap:10px;">
                    <textarea id="maljani-admin-input" style="flex:1" rows="3" placeholder="Type response..."></textarea>
                    <button class="button button-primary" id="maljani-admin-send">Send Reply</button>
                </div>
                <p><br/><a href="<?php echo esc_url(admin_url('admin.php?page=maljani-live-chat')); ?>">&laquo; Back to all chats</a></p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                var convId = $('#maljani-admin-chat').data('id');
                var token = $('#maljani-admin-chat').data('token');
                var lastId = 0;
                
                function fetchMessages() {
                    $.get('<?php echo esc_url_raw(rest_url('maljani-chat/v1/poll')); ?>', {
                        conversation_id: convId, token: token, last_id: lastId, is_agent: 1
                    }, function(res) {
                        if(res.success && res.messages.length > 0) {
                            res.messages.forEach(function(m) {
                                var align = m.sender_type === 'agent' ? 'right' : 'left';
                                var bg = m.sender_type === 'agent' ? '#e3f2fd' : '#f1f1f1';
                                var html = '<div style="align-self:'+ (m.sender_type === 'agent' ? 'flex-end' : 'flex-start') +';margin-bottom:10px;">';
                                html += '<div style="padding:10px 15px;border-radius:15px;background:'+bg+';max-width:300px;word-wrap:break-word;">';
                                html += $('<div>').text(m.message).html().replace(/\n/g, '<br/>');
                                html += '<div style="color:#888;font-size:10px;margin-top:4px;text-align:right;">' + m.created_at + '</div></div></div>';
                                $('#maljani-admin-messages').append(html);
                                lastId = m.id;
                            });
                            $('#maljani-admin-messages').scrollTop($('#maljani-admin-messages')[0].scrollHeight);
                        }
                    });
                }
                
                $('#maljani-admin-send').click(function() {
                    var msg = $('#maljani-admin-input').val();
                    if(!msg) return;
                    $.post('<?php echo esc_url_raw(rest_url('maljani-chat/v1/message')); ?>', {
                        conversation_id: convId, token: token, message: msg, is_agent: 1,
                        _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                    }, function(res) {
                        if (res.success) {
                            $('#maljani-admin-input').val('');
                            fetchMessages();
                        }
                    });
                });
                
                fetchMessages();
                setInterval(fetchMessages, 4000); // poll every 4 sec
            });
            </script>
            <?php
            
        } else {
            // list view
            $convs = $wpdb->get_results("SELECT * FROM $conv_table ORDER BY updated_at DESC LIMIT 100");
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Email</th><th>Policy</th><th>Status</th><th>Last Active</th><th>Action</th></tr></thead><tbody>';
            foreach($convs as $c) {
                $unread = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $msg_table WHERE conversation_id = %d AND sender_type = 'user' AND is_read = 0", $c->id));
                $badge = $unread > 0 ? " <span style='background:red;color:white;padding:2px 6px;border-radius:10px;font-size:11px;'>$unread new</span>" : '';
                
                $policy_no = '—';
                if ($c->policy_id) {
                    $policy_no = $wpdb->get_var($wpdb->prepare("SELECT policy_number FROM " . $wpdb->prefix . "policy_sale WHERE id = %d", $c->policy_id));
                }

                echo "<tr>";
                echo "<td>#{$c->id}</td>";
                echo "<td>" . esc_html($c->email ?: 'Guest') . $badge . "</td>";
                echo "<td>" . esc_html($policy_no) . "</td>";
                echo "<td>" . esc_html(ucfirst($c->status)) . "</td>";
                echo "<td>" . esc_html($c->updated_at) . "</td>";
                echo "<td><a class='button' href='" . esc_url(admin_url("admin.php?page=maljani-live-chat&conversation_id={$c->id}")) . "'>View Chat</a></td>";
                echo "</tr>";
            }
            if (empty($convs)) {
                echo '<tr><td colspan="6">No conversations yet.</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}
if (defined('ABSPATH')) { Maljani_Live_Chat_Admin::init(); }
