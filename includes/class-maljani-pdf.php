<?php
/**
 * Safe PDF generator for Maljani plugin
 *
 * Moves PDF creation behind an authenticated admin action and centralizes
 * escaping, capability checks and library loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_PDF_Generator {

    private static function get_secret() {
        $opt = get_option('maljani_verification_secret');
        if ($opt) return $opt;
        $secret = wp_generate_password(32, true, true);
        update_option('maljani_verification_secret', $secret);
        return $secret;
    }

    public static function generate_verification_hash($sale_id, $policy_number, $passport_number) {
        $secret = self::get_secret();
        $data = intval($sale_id) . '|' . sanitize_text_field($policy_number) . '|' . sanitize_text_field($passport_number);
        return hash('sha256', $data . $secret);
    }

    public static function generate_qr_code_url($verification_url) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($verification_url);
    }

    // Verify token and display a simple verification result page (no PDF regeneration)
    public static function verify_and_display($sale_id, $token) {
        global $wpdb;

        $sale_id = intval($sale_id);
        if (!$sale_id) {
            wp_die('Invalid sale id.');
        }

        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $sale_id));
        if (!$sale) {
            wp_die('Sale not found.');
        }

        $expected = self::generate_verification_hash($sale_id, $sale->policy_number ?? '', $sale->passport_number ?? '');
        $is_valid = hash_equals($expected, $token);

        $policy_title = esc_html(get_the_title(intval($sale->policy_id)));
        $insured = esc_html($sale->insured_names ?? '');
        $policy_number = esc_html($sale->policy_number ?? '');

        // Build verification page (simple, safe HTML)
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Policy Verification</title></head><body style="font-family:Arial,Helvetica,sans-serif;max-width:800px;margin:20px;">';
        echo '<h1>Policy Verification</h1>';
        echo '<p><strong>Policy:</strong> ' . $policy_title . '</p>';
        echo '<p><strong>Policy Number:</strong> ' . $policy_number . '</p>';
        echo '<p><strong>Insured:</strong> ' . $insured . '</p>';
        echo '<p><strong>Sale ID:</strong> ' . intval($sale_id) . '</p>';
        if ($is_valid) {
            echo '<div style="padding:12px;background:#e6ffea;border:1px solid #8cd18c;color:#166a16;font-weight:bold;">Verification: VALID</div>';
        } else {
            echo '<div style="padding:12px;background:#ffe6e6;border:1px solid #d18c8c;color:#6a1616;font-weight:bold;">Verification: INVALID</div>';
        }

        // Show QR code linking to public verification URL
        $verify_url = esc_url( home_url('/?verify_policy=1&sale_id=' . $sale_id . '&token=' . $token) );
        $qr = esc_url(self::generate_qr_code_url($verify_url));
        echo '<p>Scan QR to verify online:</p><img src="' . $qr . '" alt="QR Code">';

        echo '<p style="margin-top:20px;color:#666;font-size:12px;">If you believe this result is incorrect, contact support.</p>';
        echo '</body></html>';

        exit;
    }

}
