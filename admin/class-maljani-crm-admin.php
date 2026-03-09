<?php

class Maljani_CRM_Admin {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_maljani_crm_upload_doc', [$this, 'handle_doc_upload']);
    }

    public function admin_menu() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // Count pending
        $pending = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $pending = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE workflow_status IN ('pending_review', 'approved')"));
        }
        
        $badge = $pending > 0 ? " <span class='update-plugins count-$pending'><span class='plugin-count'>$pending</span></span>" : '';
        add_submenu_page('maljani_travel', 'CRM Hub', 'CRM Hub' . $badge, 'edit_maljani_policies', 'maljani-crm', [$this, 'render_page']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_maljani-crm') return;
        wp_enqueue_style('maljani-crm-admin', plugin_dir_url(__FILE__) . 'css/maljani-crm-admin.css', [], time());
        wp_enqueue_script('maljani-crm-admin', plugin_dir_url(__FILE__) . 'js/maljani-crm-admin.js', ['jquery'], time(), true);
        wp_localize_script('maljani-crm-admin', 'maljaniCrmParams', [
            'rest_url' => esc_url_raw(rest_url('maljani-crm/v1')),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'upload_nonce' => wp_create_nonce('maljani_doc_upload')
        ]);
    }

    public function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        
        $policies = $wpdb->get_results("
            SELECT p.*, a.agency_name 
            FROM $table p 
            LEFT JOIN $agencies_table a ON p.agency_id = a.id 
            WHERE p.workflow_status != 'draft' 
            ORDER BY p.updated_at DESC LIMIT 50
        ");

        echo '<div class="wrap maljani-crm-wrap"><h1>Maljani CRM Admin Hub</h1>';
        echo '<p>Review policies submitted by agencies, forward them to insurers, and upload finalized documents.</p>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID / Date</th><th>Agency</th><th>Client Name</th><th>Premium/Comm</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        
        if (empty($policies)) {
            echo '<tr><td colspan="6">No active policies in the workflow.</td></tr>';
        } else {
            foreach ($policies as $p) {
                echo "<tr>";
                echo "<td>#{$p->id}<br/><small>{$p->updated_at}</small></td>";
                echo "<td>" . esc_html($p->agency_name ?: 'Direct/System') . "</td>";
                echo "<td>" . esc_html($p->insured_names) . "<br/><small>" . esc_html($p->insured_email) . "</small></td>";
                echo "<td>$" . esc_html($p->premium) . "<br/><small>Comm: $" . esc_html($p->commission_amount) . "</small></td>";
                
                $status_colors = [
                    'pending_review' => 'orange',
                    'submitted_to_insurer' => 'blue',
                    'approved' => 'purple',
                    'active' => 'green',
                    'verification_ready' => 'darkgreen'
                ];
                $color = $status_colors[$p->workflow_status] ?? 'gray';
                
                echo "<td><span style='background:{$color};color:white;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:bold;'>" . esc_html(strtoupper(str_replace('_', ' ', $p->workflow_status))) . "</span></td>";
                
                echo "<td>";
                // Action buttons based on status
                if ($p->workflow_status === 'pending_review') {
                    echo "<button class='button button-primary maljani-transition-btn' data-id='{$p->id}' data-target='submitted_to_insurer'>Forward to Insurer</button> ";
                    echo "<button class='button maljani-transition-btn' data-id='{$p->id}' data-target='draft'>Return to Agency</button>";
                } elseif ($p->workflow_status === 'approved') {
                    echo "<button class='button button-primary maljani-doc-upload-btn' data-id='{$p->id}'>Upload Final Docs & Activate</button>";
                } elseif ($p->workflow_status === 'active') {
                    echo "<button class='button maljani-transition-btn' data-id='{$p->id}' data-target='verification_ready'>Generate Slips</button>";
                } else {
                    echo "<button class='button' disabled>Waiting...</button>";
                }
                echo "</td>";
                echo "</tr>";
            }
        }
        echo '</tbody></table>';
        
        // Upload Modal
        ?>
        <div id="maljani-doc-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;">
            <div style="background:#fff;width:400px;margin:100px auto;padding:20px;border-radius:5px;">
                <h2>Upload Final Documents</h2>
                <form id="maljani-doc-form" enctype="multipart/form-data">
                    <input type="hidden" id="maljani-upload-policy-id" name="policy_id" value="">
                    <input type="hidden" name="action" value="maljani_crm_upload_doc">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('maljani_doc_upload')); ?>">
                    
                    <p><label>Embassy Letter (PDF)</label><br/>
                    <input type="file" name="embassy_letter" accept=".pdf" required></p>
                    
                    <p><label>Official Policy Doc (PDF, Optional)</label><br/>
                    <input type="file" name="policy_doc" accept=".pdf"></p>
                    
                    <button type="submit" class="button button-primary">Upload & Activate</button>
                    <button type="button" class="button" onclick="document.getElementById('maljani-doc-modal').style.display='none'">Cancel</button>
                </form>
            </div>
        </div>
        <script>
        // Inline script for transition buttons (will move to external later if complex)
        jQuery(document).ready(function($) {
            $('.maljani-transition-btn').on('click', function() {
                if(!confirm('Are you sure you want to change this policy status?')) return;
                var id = $(this).data('id');
                var target = $(this).data('target');
                var btn = $(this);
                btn.prop('disabled', true).text('Working...');
                
                $.ajax({
                    url: maljaniCrmParams.rest_url + '/policies/' + id + '/transition',
                    method: 'POST',
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', maljaniCrmParams.nonce); },
                    data: { target_status: target, notes: 'Admin action' }
                }).done(function(res) {
                    location.reload();
                }).fail(function(err) {
                    alert('Error: ' + (err.responseJSON ? err.responseJSON.message : 'Unknown'));
                    btn.prop('disabled', false).text('Try Again');
                });
            });

            $('.maljani-doc-upload-btn').on('click', function() {
                $('#maljani-upload-policy-id').val($(this).data('id'));
                $('#maljani-doc-modal').show();
            });

            $('#maljani-doc-form').on('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                var btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).text('Uploading...');
                
                $.ajax({
                    url: maljaniCrmParams.ajax_url,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if(res.success) {
                            alert('Uploaded and Activated!');
                            location.reload();
                        } else {
                            alert('Error: ' + res.data);
                            btn.prop('disabled', false).text('Upload & Activate');
                        }
                    }
                });
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public function handle_doc_upload() {
        check_ajax_referer('maljani_doc_upload', 'nonce');
        if (!current_user_can('edit_others_posts')) wp_send_json_error('Unauthorized');

        $policy_id = intval($_POST['policy_id']);
        if (!$policy_id || empty($_FILES['embassy_letter'])) wp_send_json_error('Missing files');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        global $wpdb;

        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/maljani_crm_docs';
        if (!file_exists($crm_dir)) wp_mkdir_p($crm_dir);

        $tables = [
            'docs' => $wpdb->prefix . 'maljani_documents',
            'policy' => $wpdb->prefix . 'policy_sale'
        ];

        // Handle embassy letter
        $file = $_FILES['embassy_letter'];
        $filename = $policy_id . '_embassy_' . time() . '.pdf';
        $dest = $crm_dir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $wpdb->insert($tables['docs'], [
                'policy_id' => $policy_id, 'type' => 'embassy_letter',
                'file_path' => $upload_dir['baseurl'] . '/maljani_crm_docs/' . $filename,
                'uploaded_by' => get_current_user_id()
            ]);
        }

        // Handle optional policy doc
        if (!empty($_FILES['policy_doc']['tmp_name'])) {
            $file2 = $_FILES['policy_doc'];
            $filename2 = $policy_id . '_policy_' . time() . '.pdf';
            $dest2 = $crm_dir . '/' . $filename2;
            if (move_uploaded_file($file2['tmp_name'], $dest2)) {
                $wpdb->insert($tables['docs'], [
                    'policy_id' => $policy_id, 'type' => 'policy_doc',
                    'file_path' => $upload_dir['baseurl'] . '/maljani_crm_docs/' . $filename2,
                    'uploaded_by' => get_current_user_id()
                ]);
            }
        }

        // Transition policy to active
        $wpdb->update($tables['policy'], ['workflow_status' => 'active'], ['id' => $policy_id]);
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('policy', $policy_id, 'docs_uploaded_active', get_current_user_id(), []);
            // Fire transition hook for notifications
            $policy = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['policy']} WHERE id = %d", $policy_id));
            do_action('maljani_workflow_transition', $policy_id, 'approved', 'active', $policy, 'Admin uploaded final documents');
        }

        wp_send_json_success();
    }
}

if (defined('ABSPATH')) { Maljani_CRM_Admin::init(); }
