<?php

class Maljani_Notifications {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_action('maljani_workflow_transition', [$this, 'handle_transition_notification'], 10, 5);
        add_action('maljani_new_sale',            [$this, 'handle_new_sale'],                10, 2);
    }

    /** Returns HTML email headers */
    private function html_headers(): array {
        return ['Content-Type: text/html; charset=UTF-8'];
    }

    /**
     * Fired immediately after a new sale is created via the QuoteWizard (GraphQL).
     * Sends a confirmation email to the insured and an alert to site admins.
     */
    public function handle_new_sale(int $sale_id, array $data): void {
        $site_name    = get_bloginfo('name');
        $admin_email  = get_option('admin_email');
        $pol_number   = esc_html($data['policy_number'] ?? 'N/A');
        $insured_name = esc_html($data['insured_names'] ?? 'Customer');
        $insured_email= sanitize_email($data['insured_email'] ?? '');
        $amount       = 'KES ' . number_format(floatval($data['amount_paid'] ?? 0), 2);
        $passengers   = intval($data['passengers'] ?? 1);
        $dashboard    = admin_url('admin.php?page=policy_sales');

        // ── Admin notification ────────────────────────────────────────────
        $admin_subject = "[{$site_name}] New Policy Sale #{$pol_number}";
        $admin_message = "<html><body style='font-family:sans-serif;color:#222;'>
            <h2 style='color:#1a3c5e;'>New Policy Sale Recorded</h2>
            <table style='border-collapse:collapse;width:100%;max-width:500px;'>
                <tr><td style='padding:6px 12px;font-weight:bold;'>Policy #</td><td style='padding:6px 12px;'>{$pol_number}</td></tr>
                <tr style='background:#f5f5f5;'><td style='padding:6px 12px;font-weight:bold;'>Insured</td><td style='padding:6px 12px;'>{$insured_name}</td></tr>
                <tr><td style='padding:6px 12px;font-weight:bold;'>Email</td><td style='padding:6px 12px;'>{$insured_email}</td></tr>
                <tr style='background:#f5f5f5;'><td style='padding:6px 12px;font-weight:bold;'>Passengers</td><td style='padding:6px 12px;'>{$passengers}</td></tr>
                <tr><td style='padding:6px 12px;font-weight:bold;'>Amount</td><td style='padding:6px 12px;color:#1a7a4a;font-weight:bold;'>{$amount}</td></tr>
            </table>
            <p style='margin-top:20px;'><a href='{$dashboard}' style='background:#1a3c5e;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;'>View in CRM &rarr;</a></p>
        </body></html>";
        wp_mail($admin_email, $admin_subject, $admin_message, $this->html_headers());

        // ── Client confirmation ───────────────────────────────────────────
        if (empty($insured_email)) return;

        $client_subject = "Your {$site_name} Policy Confirmation — #{$pol_number}";
        $client_message = "<html><body style='font-family:sans-serif;color:#222;'>
            <h2 style='color:#1a3c5e;'>Thank You for Your Purchase!</h2>
            <p>Hi {$insured_name},</p>
            <p>Your travel insurance policy has been recorded. Here are your details:</p>
            <table style='border-collapse:collapse;width:100%;max-width:500px;'>
                <tr><td style='padding:6px 12px;font-weight:bold;'>Policy Number</td><td style='padding:6px 12px;color:#1a7a4a;font-weight:bold;'>{$pol_number}</td></tr>
                <tr style='background:#f5f5f5;'><td style='padding:6px 12px;font-weight:bold;'>Passengers</td><td style='padding:6px 12px;'>{$passengers}</td></tr>
                <tr><td style='padding:6px 12px;font-weight:bold;'>Amount Paid</td><td style='padding:6px 12px;'>{$amount}</td></tr>
            </table>
            <p style='margin-top:16px;'>Your policy documents will be sent to you once the policy is activated by our team. If you have a Maljani account, you can track your policy from your dashboard.</p>
            <p style='color:#888;font-size:12px;margin-top:24px;'>This is an automated confirmation. Please do not reply to this email.</p>
        </body></html>";
        wp_mail($insured_email, $client_subject, $client_message, $this->html_headers());
    }

    public function handle_transition_notification($sale_id, $old_status, $new_status, $policy, $notes = '') {
        $admin_email = get_option('admin_email');
        
        switch ($new_status) {
            case 'pending_review':
                // Notify Maljani Editors that Agency submitted a policy
                $editors = get_users(['role' => 'maljani_editor']);
                foreach ($editors as $editor) {
                    $subject = "New Policy Submission: #" . $policy->id;
                    $message = "Agency ID {$policy->agency_id} has submitted a new policy for review. Please check the CRM Dashboard.";
                    wp_mail($editor->user_email, $subject, $message);
                }
                break;
                
            case 'submitted_to_insurer':
                // Notify Insurers
                $insurers = get_users(['role' => 'insurer']);
                foreach ($insurers as $insurer) {
                    $subject = "Policy Requires Approval: #" . $policy->id;
                    $message = "Maljani has forwarded a policy for your review. Please log in to approve and upload the documents.";
                    wp_mail($insurer->user_email, $subject, $message);
                }
                break;
                
            case 'approved':
                // Notify Maljani Admin that Insurer approved and it's ready for activation
                $admins = get_users(['role' => 'maljani_admin']);
                foreach ($admins as $admin) {
                    $subject = "Insurer Approved Policy: #" . $policy->id;
                    $message = "An insurer has approved policy #{$policy->id}. Please generate final verification documents.";
                    wp_mail($admin->user_email, $subject, $message);
                }
                break;
                
            case 'active':
            case 'verification_ready':
                // Notify Agency & Client
                global $wpdb;
                $client_table = $wpdb->prefix . 'maljani_clients';
                $client = $wpdb->get_row($wpdb->prepare("SELECT email FROM $client_table WHERE id = %d", $policy->client_id));
                
                $agency_table = $wpdb->prefix . 'maljani_agencies';
                $agency = $wpdb->get_row($wpdb->prepare("SELECT contact_email FROM $agency_table WHERE id = %d", $policy->agency_id));
                
                $subject = "Your Policy is Active: #" . $policy->id;
                $message_client = "Good news! Your travel insurance policy (#{$policy->id}) has been activated. Please check your dashboard or contact your agency for documents.";
                $message_agency = "The policy for your client (#{$policy->id}) is now active. You can download the embassy verification letters from the dashboard.";
                
                if ($client && !empty($client->email)) wp_mail($client->email, $subject, $message_client);
                if ($agency && !empty($agency->contact_email)) wp_mail($agency->contact_email, $subject, $message_agency);
                break;
                
            case 'draft':
                // Typically when an admin rejects a pending review
                if ($old_status === 'pending_review') {
                    global $wpdb;
                    $agency_table = $wpdb->prefix . 'maljani_agencies';
                    $agency = $wpdb->get_row($wpdb->prepare("SELECT contact_email FROM $agency_table WHERE id = %d", $policy->agency_id));
                    
                    if ($agency && !empty($agency->contact_email)) {
                        $subject = "Policy Application Returned: #" . $policy->id;
                        $message = "Your policy application has been returned to drafts. Notes: " . wp_strip_all_tags($notes);
                        wp_mail($agency->contact_email, $subject, $message);
                    }
                }
                break;
        }
    }
}

if (defined('ABSPATH')) {
    Maljani_Notifications::init();
}
