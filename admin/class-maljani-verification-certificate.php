<?php
/**
 * Maljani Policy Verification Certificate
 * Generates a printable verification document for a policy sale.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Verification_Certificate {

    public function __construct() {
        add_action('admin_post_maljani_print_verification', [$this, 'generate_certificate']);
    }

    /**
     * Generate and output the printable certificate HTML
     */
    public function generate_certificate() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }

        $sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
        if (!$sale_id) {
            wp_die('Invalid sale ID.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sale_id));

        if (!$sale) {
            wp_die('Sale record not found.');
        }

        $policy = get_post($sale->policy_id);
        if (!$policy) {
            wp_die('Linked policy not found.');
        }

        // Generate verification URL and Token
        if (!class_exists('Maljani_PDF_Generator')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-maljani-pdf.php';
        }

        $token = Maljani_PDF_Generator::generate_verification_hash($sale->id, $sale->policy_number, $sale->passport_number);
        $verify_url = home_url('/?verify_policy=1&sale_id=' . $sale->id . '&token=' . $token);
        $qr_url = Maljani_PDF_Generator::generate_qr_code_url($verify_url);

        // Get Insurer Info
        $insurer_id = get_post_meta($sale->policy_id, '_policy_insurer', true);
        $insurer_name = $insurer_id ? get_post_meta($insurer_id, '_insurer_name', true) : 'Maljani Travel Insurance';
        $insurer_logo_id = $insurer_id ? get_post_meta($insurer_id, '_insurer_logo', true) : null;
        $insurer_logo_url = $insurer_logo_id ? wp_get_attachment_url($insurer_logo_id) : '';

        // Status mapping for user-friendly display
        $display_status = strtoupper($sale->policy_status ?: 'UNCONFIRMED');
        $status_color = ($sale->policy_status === 'active') ? '#059669' : (($sale->policy_status === 'verified') ? '#2563eb' : '#64748b');

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verification Certificate - #<?php echo esc_html($sale->policy_number ?: $sale->id); ?></title>
            <style>
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; margin: 0; padding: 0; background: #f8fafc; }
                .certificate-container { max-width: 800px; margin: 40px auto; background: #fff; padding: 60px; border-radius: 4px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); position: relative; border-top: 10px solid <?php echo $status_color; ?>; }
                .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; }
                .logo-box img { max-height: 80px; }
                .certificate-title { text-align: right; }
                .certificate-title h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; color: #64748b; }
                .certificate-title p { margin: 5px 0 0; font-size: 14px; font-weight: 600; color: <?php echo $status_color; ?>; }
                
                .main-content { margin-bottom: 50px; }
                .greeting { font-size: 18px; margin-bottom: 30px; }
                .greeting strong { font-size: 22px; color: #0f172a; }
                
                .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; }
                .detail-item { border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
                .detail-label { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b; margin-bottom: 5px; }
                .detail-value { font-size: 15px; font-weight: 600; color: #1e293b; }
                
                .verification-section { display: flex; gap: 40px; align-items: center; padding: 30px; background: #f1f5f9; border-radius: 8px; }
                .qr-box img { display: block; width: 150px; height: 150px; background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e2e8f0; }
                .verification-text h2 { margin: 0 0 10px; font-size: 18px; }
                .verification-text p { margin: 0; font-size: 13px; line-height: 1.6; color: #475569; }
                
                .footer { margin-top: 60px; font-size: 12px; color: #94a3b8; display: flex; justify-content: space-between; align-items: flex-end; }
                .footer-text { max-width: 60%; }
                .stamp-box { text-align: center; }
                .stamp { width: 120px; height: 120px; border: 4px double #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; opacity: 0.5; margin-bottom: 10px; }

                @media print {
                    body { background: #fff; color: #000; }
                    .certificate-container { margin: 0; padding: 40px; box-shadow: none; max-width: 100%; border: 1px solid #e2e8f0; border-top: 10px solid <?php echo $status_color; ?>; }
                    .no-print { display: none; }
                }
                
                .print-btn { position: fixed; bottom: 20px; right: 20px; background: #4f46e5; color: #fff; padding: 12px 24px; border-radius: 30px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
                .print-btn:hover { transform: translateY(-2px); }
            </style>
        </head>
        <body>
            <div class="certificate-container">
                <div class="header">
                    <div class="logo-box">
                        <?php if ($insurer_logo_url): ?>
                            <img src="<?php echo esc_url($insurer_logo_url); ?>" alt="Insurer Logo">
                        <?php else: ?>
                            <div style="font-weight: 800; font-size: 24px; color: #4f46e5;">MALJANI HUB</div>
                        <?php endif; ?>
                    </div>
                    <div class="certificate-title">
                        <h1>Verification Certificate</h1>
                        <p>Status: <?php echo esc_html($display_status); ?></p>
                    </div>
                </div>

                <div class="main-content">
                    <div class="greeting">
                        This document serves to confirm the insurance application details for:<br>
                        <strong><?php echo esc_html($sale->insured_names); ?></strong>
                    </div>

                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Policy Number</div>
                            <div class="detail-value"><?php echo esc_html($sale->policy_number ?: 'MAL-PENDING'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Certificate ID</div>
                            <div class="detail-value">#MLJ-<?php echo esc_html($sale->id . '-' . date('Y')); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Insurance Provider</div>
                            <div class="detail-value"><?php echo esc_html($insurer_name); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Plan Type</div>
                            <div class="detail-value"><?php echo esc_html($policy->post_title); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Coverage Period</div>
                            <div class="detail-value"><?php echo esc_html(date('d M Y', strtotime($sale->departure))); ?> to <?php echo esc_html(date('d M Y', strtotime($sale->return))); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Passport / ID</div>
                            <div class="detail-value"><?php echo esc_html($sale->passport_number); ?></div>
                        </div>
                    </div>

                    <div class="verification-section">
                        <div class="qr-box">
                            <img src="<?php echo esc_url($qr_url); ?>" alt="Verification QR Code">
                        </div>
                        <div class="verification-text">
                            <h2>Scan to Verify Validity</h2>
                            <p>This certificate is digitally signed and linked to Maljani Travel Insurance Hub's secure database. Scan the QR code to view the live status of this policy or visit:</p>
                            <p style="margin-top: 10px; font-family: monospace; font-size: 11px; word-break: break-all;">
                                <a href="<?php echo esc_url($verify_url); ?>" style="color: #4f46e5; text-decoration: none;"><?php echo esc_url($verify_url); ?></a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <div class="footer-text">
                        <p>Disclaimer: This certificate confirms the registration of the insurance application. Validity depends on the payment status shown in the official database. For any discrepancies, please contact Maljani Hub Support.</p>
                        <p>Issued on: <?php echo date('F j, Y, g:i a'); ?></p>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp">Official<br>Verification<br>Document</div>
                        <div style="font-weight: 700; font-size: 10px;">AUTHORIZED BY MALJANI</div>
                    </div>
                </div>
            </div>

            <a href="#" class="print-btn no-print" onclick="window.print(); return false;">🖨️ Print Certificate</a>
        </body>
        </html>
        <?php
        exit;
    }
}
new Maljani_Verification_Certificate();
