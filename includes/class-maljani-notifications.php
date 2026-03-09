<?php

class Maljani_Notifications {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_action('maljani_workflow_transition', [$this, 'handle_transition_notification'], 10, 5);
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
                        $message = "Your policy application has been returned to drafts. Notes: {$notes}";
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
