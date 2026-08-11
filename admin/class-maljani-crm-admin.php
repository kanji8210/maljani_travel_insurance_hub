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
        echo '<p>Review paid requests, manually enter the customer details on the insurer website, record the insurer-issued policy number, then activate the policy.</p>';
        echo '<div class="notice notice-info inline"><p><strong>Manual issuance workflow:</strong> 1. Start Processing &rarr; 2. Enter the customer information on the insurer website &rarr; 3. Enter the issued policy number here &rarr; 4. Mark Issued &rarr; 5. Upload final documents and activate.</p></div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID / Date</th><th>Agency</th><th>Client Name</th><th>Settlement Breakdown</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        
        if (empty($policies)) {
            echo '<tr><td colspan="6">No active policies in the workflow.</td></tr>';
        } else {
            foreach ($policies as $p) {
                echo "<tr>";
                echo "<td>#{$p->id}<br/><small>{$p->updated_at}</small></td>";
                $tic_revenue = floatval($p->service_fee_amount ?? 0) + floatval($p->maljani_commission_amount ?? 0);

                echo "<td>" . esc_html($p->agency_name ?: 'Direct/System') . "</td>";
                echo "<td>" . esc_html($p->insured_names) . "<br/><small>" . esc_html($p->insured_email) . "</small></td>";
                echo "<td>";
                echo "<strong>Client Paid:</strong> $" . esc_html(number_format(floatval($p->amount_paid ?? 0), 2)) . "<br/>";
                echo "<small>Net to Insurer: $" . esc_html(number_format(floatval($p->net_to_insurer ?? 0), 2)) . "</small><br/>";
                echo "<small>TIC Revenue: $" . esc_html(number_format($tic_revenue, 2)) . "</small>";
                if (floatval($p->agent_commission_amount ?? 0) > 0) {
                    echo "<br/><small>Agency Comm: $" . esc_html(number_format(floatval($p->agent_commission_amount), 2)) . "</small>";
                }
                echo "</td>";
                
                $status_colors = [
                    'pending_review' => 'orange',
                    'submitted_to_insurer' => 'blue',
                    'approved' => 'purple',
                    'active' => 'green',
                    'verification_ready' => 'darkgreen'
                ];
                $color = $status_colors[$p->workflow_status] ?? 'gray';
                $status_labels = [
                    'pending_review' => 'Pending Review',
                    'submitted_to_insurer' => 'Processing',
                    'approved' => 'Issued',
                    'active' => 'Active',
                    'verification_ready' => 'Verification Ready',
                ];
                $status_label = $status_labels[$p->workflow_status] ?? ucwords(str_replace('_', ' ', $p->workflow_status));
                
                echo "<td><span style='background:{$color};color:white;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:bold;'>" . esc_html(strtoupper($status_label)) . "</span></td>";
                
                echo "<td>";
                // Action buttons based on status
                if ($p->workflow_status === 'pending_review') {
                    echo "<button class='button button-primary maljani-transition-btn' data-id='{$p->id}' data-target='submitted_to_insurer'>Start Processing</button> ";
                    echo "<button class='button maljani-transition-btn' data-id='{$p->id}' data-target='draft'>Return to Agency</button>";
                } elseif ($p->workflow_status === 'submitted_to_insurer') {
                    echo "<div style='margin-bottom:6px;font-size:12px;max-width:280px;'>Enter the customer details on the insurer website, then record the policy number it issues.</div>";
                    echo "<input type='text' class='maljani-issued-policy-number' data-id='{$p->id}' value='' placeholder='Insurer policy number' style='width:180px;margin-right:4px;' aria-label='Insurer-issued policy number'>";
                    echo "<button class='button button-primary maljani-transition-btn' data-id='{$p->id}' data-target='approved'>Mark Issued</button> ";
                    echo "<button class='button maljani-transition-btn' data-id='{$p->id}' data-target='pending_review'>Needs Clarification</button>";
                } elseif ($p->workflow_status === 'approved') {
                    echo "<button class='button button-primary maljani-doc-upload-btn' data-id='{$p->id}'>Upload Final Docs & Activate</button>";
                } elseif ($p->workflow_status === 'active') {
                    echo "<button class='button maljani-transition-btn' data-id='{$p->id}' data-target='verification_ready'>Generate Slips</button>";
                } else {
                    echo "<button class='button' disabled>Waiting...</button>";
                }
                echo " <a href='" . esc_url(admin_url('admin.php?page=maljani-live-chat&policy_id=' . $p->id)) . "' class='button' title='Message Client'>Message</a>";
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
                <p>The insurer-issued policy number has been recorded. Upload the documents received from the insurer to activate the policy.</p>
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
                var id = $(this).data('id');
                var target = $(this).data('target');
                var btn = $(this);
                var policyNumber = '';
                var confirmation = 'Are you sure you want to change this policy status?';

                if (target === 'submitted_to_insurer') {
                    confirmation = 'Start manual processing? You will need to enter the customer details on the insurer website.';
                } else if (target === 'approved') {
                    policyNumber = $('.maljani-issued-policy-number[data-id="' + id + '"]').val().trim();
                    if (!policyNumber) {
                        alert('Enter the policy number issued by the insurer first.');
                        return;
                    }
                    confirmation = 'Confirm that ' + policyNumber + ' is the policy number issued by the insurer?';
                }

                if(!confirm(confirmation)) return;
                btn.prop('disabled', true).text('Working...');
                
                $.ajax({
                    url: maljaniCrmParams.rest_url + '/policies/' + id + '/transition',
                    method: 'POST',
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', maljaniCrmParams.nonce); },
                    data: { target_status: target, policy_number: policyNumber, notes: 'Admin manual insurer processing' }
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
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.data
                            ? xhr.responseJSON.data
                            : 'The upload request failed. Check the server upload limits and error log.';
                        alert('Error: ' + message);
                        btn.prop('disabled', false).text('Upload & Activate');
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
        if (!current_user_can('edit_maljani_policies')) wp_send_json_error('Unauthorized');

        $policy_id = intval($_POST['policy_id']);
        if (!$policy_id || empty($_FILES['embassy_letter'])) wp_send_json_error('Missing files');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        global $wpdb;

        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT policy_number, workflow_status FROM {$wpdb->prefix}policy_sale WHERE id = %d",
            $policy_id
        ));
        if (!$sale || empty($sale->policy_number)) {
            wp_send_json_error('Enter the insurer-issued policy number and mark the request as issued before activation.');
        }
        if ($sale->workflow_status !== 'approved') {
            wp_send_json_error('Only an issued policy can be activated.');
        }

        $tables = [
            'docs' => $wpdb->prefix . 'maljani_documents',
            'policy' => $wpdb->prefix . 'policy_sale'
        ];
        $stored_documents = [];

        $embassy_document = $this->store_document($_FILES['embassy_letter'], $policy_id, 'embassy_letter', $tables['docs']);
        if (is_wp_error($embassy_document)) {
            wp_send_json_error($embassy_document->get_error_message());
        }
        $stored_documents[] = $embassy_document;

        if (!empty($_FILES['policy_doc']['tmp_name'])) {
            $policy_document = $this->store_document($_FILES['policy_doc'], $policy_id, 'policy_doc', $tables['docs']);
            if (is_wp_error($policy_document)) {
                $this->delete_stored_documents($stored_documents, $tables['docs']);
                wp_send_json_error($policy_document->get_error_message());
            }
            $stored_documents[] = $policy_document;
        }

        $updated = $wpdb->update($tables['policy'], [
            'workflow_status' => 'active',
            'policy_status' => 'active',
        ], ['id' => $policy_id]);
        if ($updated === false) {
            $this->delete_stored_documents($stored_documents, $tables['docs']);
            wp_send_json_error('The documents were uploaded, but the policy could not be activated. Please try again.');
        }

        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('policy', $policy_id, 'docs_uploaded_active', get_current_user_id(), []);
            $policy = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['policy']} WHERE id = %d", $policy_id));
            do_action('maljani_workflow_transition', $policy_id, 'approved', 'active', $policy, 'Admin uploaded final documents');
        }

        wp_send_json_success(['documents' => array_column($stored_documents, 'url')]);
    }

    private function store_document($file, $policy_id, $type, $documents_table) {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return new WP_Error('maljani_upload_failed', 'The selected document could not be uploaded.');
        }

        $allowed_mimes = ['pdf' => 'application/pdf'];
        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => $allowed_mimes,
        ]);
        if (!empty($uploaded['error'])) {
            return new WP_Error('maljani_upload_failed', sanitize_text_field($uploaded['error']));
        }
        if (empty($uploaded['file']) || empty($uploaded['url']) || !is_file($uploaded['file'])) {
            return new WP_Error('maljani_upload_location', 'WordPress did not return a valid file location for the uploaded document.');
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $documents_table,
            [
                'policy_id' => $policy_id,
                'type' => $type,
                'file_path' => esc_url_raw($uploaded['url']),
                'uploaded_by' => get_current_user_id(),
            ],
            ['%d', '%s', '%s', '%d']
        );
        if ($inserted === false) {
            wp_delete_file($uploaded['file']);
            return new WP_Error('maljani_upload_database', 'The file was uploaded, but its location could not be saved. Please check the documents table.');
        }

        return [
            'id' => (int) $wpdb->insert_id,
            'file' => $uploaded['file'],
            'url' => esc_url_raw($uploaded['url']),
        ];
    }

    private function delete_stored_documents($documents, $documents_table) {
        global $wpdb;
        foreach ($documents as $document) {
            $wpdb->delete($documents_table, ['id' => $document['id']], ['%d']);
            wp_delete_file($document['file']);
        }
    }
}

if (defined('ABSPATH')) { Maljani_CRM_Admin::init(); }
