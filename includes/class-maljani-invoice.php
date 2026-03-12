<?php
/**
 * Maljani Invoice & Receipt Engine
 * Generates Kenya-compliant TAX INVOICE and OFFICIAL RECEIPT documents.
 * Documents are served as print-ready HTML (browser PDF via window.print()).
 * Email sending is done via wp_mail from Maljani's configured email address.
 */
class Maljani_Invoice {

    public function __construct() {
        add_action('wp_ajax_maljani_print_doc',  [$this, 'ajax_print_doc']);
        add_action('wp_ajax_maljani_email_doc',  [$this, 'ajax_email_doc']);
    }

    // ── Settings helpers ──────────────────────────────────────────────────────
    public static function get_settings(): array {
        return [
            'company_name'    => get_option('maljani_inv_company_name',   get_bloginfo('name')),
            'address_line1'   => get_option('maljani_inv_address1',        ''),
            'address_line2'   => get_option('maljani_inv_address2',        ''),
            'city'            => get_option('maljani_inv_city',            'Nairobi'),
            'country'         => get_option('maljani_inv_country',         'Kenya'),
            'phone'           => get_option('maljani_inv_phone',           ''),
            'email'           => get_option('maljani_inv_email',           get_option('admin_email')),
            'website'         => get_option('maljani_inv_website',         get_home_url()),
            'kra_pin'         => get_option('maljani_inv_kra_pin',         ''),
            'etr_number'      => get_option('maljani_inv_etr_number',      ''),
            'vat_enabled'     => (bool) get_option('maljani_inv_vat_enabled', false),
            'vat_rate'        => floatval(get_option('maljani_inv_vat_rate', 16)),
            'payment_instructions' => get_option('maljani_inv_payment_inst', ''),
            'invoice_footer'  => get_option('maljani_inv_footer', 'This is a computer-generated document and requires no signature. Thank you for choosing Maljani Travel Insurance.'),
            'currency_symbol' => get_option('maljani_inv_currency', 'KSH'),
        ];
    }

    // ── DB helpers ────────────────────────────────────────────────────────────
    public static function get_sale(int $id): ?object {
        global $wpdb;
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}policy_sale WHERE id = %d LIMIT 1",
            $id
        ));
        if (!$sale) return null;
        // Enrich with policy title
        $sale->policy_title = $sale->policy_id ? get_the_title($sale->policy_id) : 'Travel Insurance';
        // Enrich with region from policy_region meta
        if (!$sale->region && $sale->policy_id) {
            $terms = get_the_terms($sale->policy_id, 'policy_region');
            $sale->region = (!is_wp_error($terms) && $terms) ? $terms[0]->name : '';
        }
        // Agent display name
        $sale->agent_display = '';
        if ($sale->agent_id) {
            $u = get_userdata($sale->agent_id);
            $sale->agent_display = $u ? $u->display_name : '';
        }
        return $sale;
    }

    // ── Logo helper ───────────────────────────────────────────────────────────
    private static function get_logo_html(): string {
        // 1. Custom logo
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $src = wp_get_attachment_image_url($custom_logo_id, [180, 70]);
            if ($src) return '<img src="' . esc_url($src) . '" alt="Logo" style="max-height:70px;max-width:180px;object-fit:contain;">';
        }
        // 2. Site icon
        $icon = get_site_icon_url(128);
        if ($icon) return '<img src="' . esc_url($icon) . '" alt="Logo" style="max-height:70px;max-width:180px;object-fit:contain;">';
        // 3. Text fallback
        $cfg = self::get_settings();
        return '<span style="font-size:22px;font-weight:900;color:#1e3a5f;">' . esc_html($cfg['company_name']) . '</span>';
    }

    // ── Document number generators ────────────────────────────────────────────
    private static function inv_number(int $sale_id): string {
        return 'INV-' . date('Y') . '-' . str_pad($sale_id, 5, '0', STR_PAD_LEFT);
    }
    private static function rec_number(int $sale_id): string {
        return 'REC-' . date('Y') . '-' . str_pad($sale_id, 5, '0', STR_PAD_LEFT);
    }

    // ── SHARED CSS ────────────────────────────────────────────────────────────
    private static function print_css(): string {
        return <<<'CSS'
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;font-size:13px;color:#1a202c;background:#f0f4f8;-webkit-print-color-adjust:exact;print-color-adjust:exact}

.doc-page{
    width:210mm;min-height:297mm;
    background:#fff;
    margin:20px auto;
    padding:0;
    box-shadow:0 4px 32px rgba(0,0,0,.12);
    page-break-after:always;
}

/* ── Header band ── */
.doc-header{
    background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%);
    color:#fff;
    padding:28px 36px 22px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
}
.doc-header-logo{display:flex;align-items:center;gap:12px}
.doc-header-logo img{filter:brightness(10) saturate(0) opacity(.95)}
.doc-type{text-align:right}
.doc-type h1{font-size:24px;font-weight:900;letter-spacing:.5px;margin-bottom:4px}
.doc-type .doc-num{font-size:13px;opacity:.85;font-family:monospace;letter-spacing:1px}
.doc-type .doc-date{font-size:12px;opacity:.7;margin-top:4px}

/* ── Status ribbon ── */
.doc-ribbon{
    font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:2px;
    padding:8px 36px;
    display:flex;align-items:center;gap:6px;
}
.doc-ribbon.invoice{background:#fff7ed;color:#c2410c;border-bottom:2px solid #fed7aa}
.doc-ribbon.receipt{background:#f0fdf4;color:#166534;border-bottom:2px solid #bbf7d0}

/* ── Address row ── */
.doc-parties{
    display:grid;grid-template-columns:1fr 1fr;
    gap:0;
    padding:0;
    border-bottom:1px solid #e2e8f0;
}
.doc-party{padding:22px 36px}
.doc-party:first-child{border-right:1px solid #e2e8f0}
.doc-party h3{
    font-size:10px;font-weight:800;text-transform:uppercase;
    letter-spacing:1px;color:#64748b;margin-bottom:10px;
}
.doc-party p{font-size:12px;line-height:1.7;color:#374151}
.doc-party strong{color:#1e293b}
.kra-badge{
    display:inline-block;margin-top:8px;
    background:#f1f5f9;border:1px solid #e2e8f0;
    border-radius:4px;padding:3px 10px;
    font-size:10px;font-weight:700;color:#475569;letter-spacing:.5px;
}

/* ── Line items ── */
.doc-items{padding:24px 36px}
.doc-items h3{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin-bottom:14px}
table.items-table{width:100%;border-collapse:collapse}
table.items-table th{
    background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;
    letter-spacing:.5px;padding:10px 14px;text-align:left;
}
table.items-table th:last-child,table.items-table td:last-child{text-align:right}
table.items-table td{
    padding:10px 14px;border-bottom:1px solid #f1f5f9;
    font-size:12px;color:#374151;line-height:1.5;
}
table.items-table tr:last-child td{border-bottom:none}
table.items-table tr:nth-child(even) td{background:#f8fafc}

.items-total-row td{
    background:#1e3a5f!important;color:#fff!important;
    font-weight:800!important;font-size:13px!important;
    border-bottom:none!important;padding:12px 14px!important;
}

/* ── Sub totals block ── */
.doc-subtotals{
    margin:0 36px 0 auto;width:260px;
    border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:24px;
}
.st-row{display:flex;justify-content:space-between;padding:8px 16px;font-size:12px;border-bottom:1px solid #f1f5f9}
.st-row:last-child{border-bottom:none;background:#1e3a5f;color:#fff;font-weight:800;font-size:13px;padding:11px 16px}

/* ── Payment details band ── */
.doc-payment{
    background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;
    padding:18px 36px;display:grid;grid-template-columns:1fr 1fr;gap:16px;
}
.pay-block h4{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin-bottom:6px}
.pay-block p{font-size:12px;color:#374151;line-height:1.6}

/* ── Receipt confirmed banner ── */
.receipt-confirmed{
    margin:0 36px 24px;padding:16px 22px;
    background:#f0fdf4;border:2px solid #bbf7d0;border-radius:10px;
    display:flex;align-items:center;gap:14px;
}
.receipt-confirmed .rc-icon{font-size:28px}
.receipt-confirmed h3{font-size:14px;font-weight:800;color:#166534;margin-bottom:2px}
.receipt-confirmed p{font-size:11px;color:#4ade80}

/* ── Footer ── */
.doc-footer{
    padding:18px 36px;border-top:1px solid #e2e8f0;
    display:flex;justify-content:space-between;align-items:flex-end;gap:16px;
}
.doc-footer-text{font-size:10px;color:#94a3b8;line-height:1.6;max-width:380px}
.doc-footer-stamp{text-align:right}
.doc-footer-stamp .stamp-label{font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.doc-footer-stamp .stamp-val{font-size:11px;font-weight:700;color:#475569;font-family:monospace}

/* Print action bar (screen only) */
.print-bar{
    position:fixed;top:0;left:0;right:0;z-index:999;
    background:#1e3a5f;color:#fff;
    display:flex;align-items:center;gap:12px;padding:10px 24px;
    box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.print-bar button{
    background:#2563eb;color:#fff;border:none;border-radius:6px;
    padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;
}
.print-bar button:hover{background:#1d4ed8}
.print-bar .pb-title{font-size:14px;font-weight:700;flex:1}

@media print{
    body{background:#fff!important}
    .print-bar{display:none!important}
    .doc-page{margin:0!important;box-shadow:none!important;width:100%!important}
    @page{size:A4 portrait;margin:0}
}
</style>
CSS;
    }

    // ── Shared header HTML ─────────────────────────────────────────────────────
    private static function doc_html_open(string $title, string $doc_num, string $doc_date, array $cfg): string {
        return '<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . esc_html($title) . '</title>'
        . self::print_css()
        . '</head><body>
<div class="print-bar">
  <span class="pb-title">📄 ' . esc_html($title) . '</span>
  <button onclick="window.print()">🖨️ Print / Save PDF</button>
  <button onclick="window.close()">✕ Close</button>
</div>
<div class="doc-page">';
    }

    private static function header_band(string $doc_label, string $doc_num, string $doc_date, array $cfg): string {
        $logo = self::get_logo_html();
        return '
<div class="doc-header">
  <div class="doc-header-logo">' . $logo . '</div>
  <div class="doc-type">
    <h1>' . esc_html($doc_label) . '</h1>
    <div class="doc-num">' . esc_html($doc_num) . '</div>
    <div class="doc-date">Date: ' . esc_html($doc_date) . '</div>
  </div>
</div>';
    }

    private static function parties_section(object $sale, array $cfg, bool $is_receipt = false): string {
        $kra = $cfg['kra_pin'] ? '<span class="kra-badge">KRA PIN: ' . esc_html($cfg['kra_pin']) . '</span>' : '';
        $etr = $cfg['etr_number'] ? '<span class="kra-badge">ETR No: ' . esc_html($cfg['etr_number']) . '</span>' : '';
        $vat_no = $cfg['vat_enabled'] ? '<span class="kra-badge">VAT Reg.</span>' : '<span class="kra-badge">VAT NOT APPLICABLE</span>';

        $paid_date = $is_receipt && isset($sale->updated_at) ? date('d M Y', strtotime($sale->updated_at)) : date('d M Y');

        return '
<div class="doc-parties">
  <div class="doc-party">
    <h3>From</h3>
    <p>
      <strong>' . esc_html($cfg['company_name']) . '</strong><br>
      ' . esc_html($cfg['address_line1']) . ($cfg['address_line2'] ? '<br>' . esc_html($cfg['address_line2']) : '') . '<br>
      ' . esc_html($cfg['city']) . ', ' . esc_html($cfg['country']) . '<br>
      ' . ($cfg['phone'] ? 'Tel: ' . esc_html($cfg['phone']) . '<br>' : '') . '
      ' . esc_html($cfg['email']) . '
    </p>
    ' . $kra . ' ' . $etr . ' ' . $vat_no . '
  </div>
  <div class="doc-party">
    <h3>Billed To</h3>
    <p>
      <strong>' . esc_html($sale->insured_names) . '</strong><br>
      ' . ($sale->insured_email    ? esc_html($sale->insured_email)  . '<br>' : '') . '
      ' . ($sale->insured_phone    ? 'Tel: ' . esc_html($sale->insured_phone) . '<br>' : '') . '
      ' . ($sale->passport_number  ? 'Passport/ID: ' . esc_html($sale->passport_number) . '<br>' : '') . '
      ' . ($sale->insured_address  ? esc_html($sale->insured_address) : '') . '
    </p>
  </div>
</div>';
    }

    private static function line_items(object $sale, array $cfg): array {
        $cur = $cfg['currency_symbol'];
        $rows = [];

        // Base premium line
        $rows[] = [
            'description' => '<strong>Travel Insurance Premium</strong><br>
              <small style="color:#64748b">' . esc_html($sale->policy_title) . ' — ' . esc_html($sale->region) . ' Region<br>
              Departure: ' . esc_html($sale->departure) . ' → Return: ' . esc_html($sale->return) . ' (' . intval($sale->days) . ' days)<br>
              Policy No: <strong>' . esc_html($sale->policy_number) . '</strong></small>',
            'qty'   => 1,
            'unit'  => number_format($sale->premium, 2),
            'total' => floatval($sale->premium),
        ];

        // Service fee (if any)
        $svc = floatval($sale->service_fee_amount ?? 0);
        if ($svc > 0) {
            $rows[] = [
                'description' => 'Maljani Service Fee',
                'qty'   => 1,
                'unit'  => number_format($svc, 2),
                'total' => $svc,
            ];
        }

        return $rows;
    }

    private static function items_table(array $rows, object $sale, array $cfg): string {
        $cur = $cfg['currency_symbol'];
        $subtotal = floatval($sale->amount_paid);

        // VAT calculation
        $vat_amount = 0;
        if ($cfg['vat_enabled'] && $cfg['vat_rate'] > 0) {
            // VAT applies to service fee only (insurance premium is VAT-exempt in Kenya)
            $svc = floatval($sale->service_fee_amount ?? 0);
            $vat_amount = round($svc * $cfg['vat_rate'] / 100, 2);
        }
        $grand_total = $subtotal + $vat_amount;

        $html = '
<div class="doc-items">
  <h3>Items &amp; Description</h3>
  <table class="items-table">
    <thead><tr>
      <th style="width:55%">Description</th>
      <th>Qty</th>
      <th style="text-align:right">Unit Price (' . esc_html($cur) . ')</th>
      <th style="text-align:right">Amount (' . esc_html($cur) . ')</th>
    </tr></thead>
    <tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>
      <td>' . $r['description'] . '</td>
      <td>' . $r['qty'] . '</td>
      <td style="text-align:right;font-family:monospace">' . $r['unit'] . '</td>
      <td style="text-align:right;font-family:monospace">' . number_format($r['total'], 2) . '</td>
    </tr>';
        }
        $html .= '</tbody></table></div>';

        // Sub-totals
        $html .= '<div class="doc-subtotals">';
        $html .= '<div class="st-row"><span>Subtotal</span><span>' . esc_html($cur) . ' ' . number_format($subtotal, 2) . '</span></div>';
        if ($cfg['vat_enabled'] && $vat_amount > 0) {
            $html .= '<div class="st-row"><span>VAT (' . $cfg['vat_rate'] . '% on Service Fee)</span><span>' . esc_html($cur) . ' ' . number_format($vat_amount, 2) . '</span></div>';
        } else {
            $html .= '<div class="st-row"><span style="font-size:10px;color:#94a3b8">VAT (Not Applicable)</span><span style="font-size:10px;color:#94a3b8">—</span></div>';
        }
        $html .= '<div class="st-row"><span>TOTAL DUE</span><span>' . esc_html($cur) . ' ' . number_format($grand_total, 2) . '</span></div>';
        $html .= '</div>';

        return $html;
    }

    private static function payment_section(array $cfg, bool $is_receipt = false, ?object $sale = null): string {
        $method = $is_receipt && $sale ? (strtoupper($sale->payment_status) === 'CONFIRMED' ? 'Payment received' : '') : '';
        $inst = nl2br(esc_html($cfg['payment_instructions']));
        if (!$inst && !$is_receipt) $inst = 'Please make payment via M-Pesa, Bank Transfer, or Cash. Quote your Policy Number as payment reference.';

        return '
<div class="doc-payment">
  <div class="pay-block">
    <h4>Payment Instructions</h4>
    <p>' . ($is_receipt ? 'Payment has been received and confirmed. No further action required.' : $inst) . '</p>
  </div>
  <div class="pay-block">
    <h4>Policy Reference</h4>
    <p>
      <strong>Policy No:</strong> ' . esc_html($sale->policy_number ?? '—') . '<br>
      ' . ($sale->agent_display ? '<strong>Issued by:</strong> ' . esc_html($sale->agent_display) . '<br>' : '') . '
      <strong>Issued date:</strong> ' . date('d M Y, H:i') . '
    </p>
  </div>
</div>';
    }

    private static function footer_section(string $doc_num, array $cfg): string {
        return '
<div class="doc-footer">
  <div class="doc-footer-text">' . esc_html($cfg['invoice_footer']) . '</div>
  <div class="doc-footer-stamp">
    <div class="stamp-label">Document Ref</div>
    <div class="stamp-val">' . esc_html($doc_num) . '</div>
    <div class="stamp-label" style="margin-top:6px">Generated</div>
    <div class="stamp-val">' . date('Y-m-d H:i') . '</div>
  </div>
</div>';
    }

    // ── BUILD INVOICE HTML ─────────────────────────────────────────────────────
    public static function build_invoice_html(object $sale): string {
        $cfg     = self::get_settings();
        $doc_num = self::inv_number($sale->id);
        $doc_date= date('d F Y');

        $html  = self::doc_html_open('Tax Invoice — ' . $sale->policy_number, $doc_num, $doc_date, $cfg);
        $html .= self::header_band('TAX INVOICE', $doc_num, $doc_date, $cfg);
        $html .= '<div class="doc-ribbon invoice">⏳ &nbsp;AWAITING PAYMENT — Please pay as per instructions below</div>';
        $html .= self::parties_section($sale, $cfg, false);
        $rows  = self::line_items($sale, $cfg);
        $html .= self::items_table($rows, $sale, $cfg);
        $html .= self::payment_section($cfg, false, $sale);
        $html .= self::footer_section($doc_num, $cfg);
        $html .= '</div></body></html>';
        return $html;
    }

    // ── BUILD RECEIPT HTML ─────────────────────────────────────────────────────
    public static function build_receipt_html(object $sale): string {
        $cfg     = self::get_settings();
        $doc_num = self::rec_number($sale->id);
        $doc_date= date('d F Y');

        $html  = self::doc_html_open('Official Receipt — ' . $sale->policy_number, $doc_num, $doc_date, $cfg);
        $html .= self::header_band('OFFICIAL RECEIPT', $doc_num, $doc_date, $cfg);
        $html .= '<div class="doc-ribbon receipt">✅ &nbsp;PAYMENT CONFIRMED — Policy is now active</div>';
        $html .= self::parties_section($sale, $cfg, true);

        // Confirmed banner
        $html .= '
<div class="receipt-confirmed" style="margin-top:20px">
  <div class="rc-icon">✅</div>
  <div>
    <h3>Payment Received &amp; Confirmed</h3>
    <p style="color:#166534;font-size:12px">Policy Number: <strong>' . esc_html($sale->policy_number) . '</strong> &nbsp;·&nbsp; Amount: <strong>' . esc_html($cfg['currency_symbol']) . ' ' . number_format($sale->amount_paid, 2) . '</strong></p>
  </div>
</div>';

        $rows  = self::line_items($sale, $cfg);
        $html .= self::items_table($rows, $sale, $cfg);
        $html .= self::payment_section($cfg, true, $sale);
        $html .= self::footer_section($doc_num, $cfg);
        $html .= '</div></body></html>';
        return $html;
    }

    // ── EMAIL helpers ──────────────────────────────────────────────────────────
    private static function html_to_email_body(string $full_html): string {
        // Strip print bar and heavy CSS, keep content legible in email
        $body = preg_replace('/<div class="print-bar">.*?<\/div>/s', '', $full_html);
        // Replace gradient header with solid colour for email clients
        $body = str_replace('linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%)', '#1e3a5f', $body);
        // Remove fixed/absolute positioning CSS
        $body = preg_replace('/position:\s*fixed[^;]*;/i', '', $body);
        return $body;
    }

    public static function send_invoice_email(int $sale_id): bool {
        $sale = self::get_sale($sale_id);
        if (!$sale || !$sale->insured_email) return false;
        $cfg  = self::get_settings();
        $html = self::build_invoice_html($sale);
        $to   = sanitize_email($sale->insured_email);
        $subj = 'Your Travel Insurance Invoice — ' . $sale->policy_number . ' | ' . $cfg['company_name'];
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $cfg['company_name'] . ' <' . $cfg['email'] . '>',
            'Reply-To: ' . $cfg['email'],
        ];
        return wp_mail($to, $subj, self::html_to_email_body($html), $headers);
    }

    public static function send_receipt_email(int $sale_id): bool {
        $sale = self::get_sale($sale_id);
        if (!$sale || !$sale->insured_email) return false;
        $cfg  = self::get_settings();
        $html = self::build_receipt_html($sale);
        $to   = sanitize_email($sale->insured_email);
        $subj = 'Payment Confirmed — Official Receipt ' . self::rec_number($sale_id) . ' | ' . $cfg['company_name'];
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $cfg['company_name'] . ' <' . $cfg['email'] . '>',
            'Reply-To: ' . $cfg['email'],
        ];
        return wp_mail($to, $subj, self::html_to_email_body($html), $headers);
    }

    // ── AJAX: serve print document ────────────────────────────────────────────
    public function ajax_print_doc() {
        if (!current_user_can('read')) wp_die('Unauthorized');
        $sale_id = intval($_GET['sale_id'] ?? 0);
        $type    = sanitize_key($_GET['doc_type'] ?? 'invoice');
        if (!$sale_id) wp_die('Missing sale ID');

        // Agency users: only their own sales
        $sale = self::get_sale($sale_id);
        if (!$sale) wp_die('Sale not found');

        if (!current_user_can('manage_options')) {
            // Must be an agent who owns this sale
            if (intval($sale->agent_id) !== get_current_user_id()) {
                // Also check agency match
                global $wpdb;
                $uid = get_current_user_id();
                $agency_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}maljani_agencies WHERE user_id = %d LIMIT 1", $uid
                ));
                if (!$agency_id) wp_die('Unauthorized');
            }
        }

        if ($type === 'receipt') {
            echo self::build_receipt_html($sale);
        } else {
            echo self::build_invoice_html($sale);
        }
        exit;
    }

    // ── AJAX: email document ───────────────────────────────────────────────────
    public function ajax_email_doc() {
        check_ajax_referer('maljani_doc_nonce', 'nonce');
        if (!current_user_can('read')) wp_send_json_error('Unauthorized');
        $sale_id = intval($_POST['sale_id'] ?? 0);
        $type    = sanitize_key($_POST['doc_type'] ?? 'invoice');
        if (!$sale_id) wp_send_json_error('Missing sale ID');

        $ok = $type === 'receipt'
            ? self::send_receipt_email($sale_id)
            : self::send_invoice_email($sale_id);

        if ($ok) {
            wp_send_json_success(['message' => 'Email sent successfully.']);
        } else {
            wp_send_json_error('Failed to send email. Check server mail configuration.');
        }
    }

    // ── Utility: print button HTML (reusable across admin pages) ──────────────
    public static function doc_buttons(int $sale_id, bool $is_paid = false, string $context = 'admin'): string {
        $nonce       = wp_create_nonce('maljani_doc_nonce');
        $ajax_url    = admin_url('admin-ajax.php');
        $inv_url     = esc_url(add_query_arg(['action'=>'maljani_print_doc','sale_id'=>$sale_id,'doc_type'=>'invoice'], $ajax_url));
        $rec_url     = esc_url(add_query_arg(['action'=>'maljani_print_doc','sale_id'=>$sale_id,'doc_type'=>'receipt'], $ajax_url));

        $btn_base    = 'display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;border-radius:5px;padding:5px 10px;cursor:pointer;text-decoration:none;border:1px solid;transition:opacity .15s;';
        $btn_inv     = $btn_base . 'background:#fff7ed;color:#c2410c;border-color:#fed7aa';
        $btn_rec     = $btn_base . 'background:#f0fdf4;color:#166534;border-color:#bbf7d0';
        $btn_mail_i  = $btn_base . 'background:#f0f4ff;color:#3730a3;border-color:#c7d2fe';
        $btn_mail_r  = $btn_base . 'background:#f0fdf4;color:#166534;border-color:#bbf7d0';

        $out  = '<div class="mj-doc-btns" data-nonce="' . esc_attr($nonce) . '" data-ajax="' . esc_attr($ajax_url) . '" data-sale="' . esc_attr($sale_id) . '" style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px">';
        // Print invoice
        $out .= '<a href="' . $inv_url . '" target="_blank" style="' . $btn_inv . '" title="Print Invoice">🧾 Invoice</a>';
        // Email invoice
        $out .= '<button type="button" class="mj-email-doc" data-type="invoice" style="' . $btn_mail_i . '" title="Email Invoice to client">✉️ Send INV</button>';
        if ($is_paid) {
            $out .= '<a href="' . $rec_url . '" target="_blank" style="' . $btn_rec . '" title="Print Receipt">🟢 Receipt</a>';
            $out .= '<button type="button" class="mj-email-doc" data-type="receipt" style="' . $btn_mail_r . '" title="Email Receipt to client">✉️ Send REC</button>';
        }
        $out .= '</div>';
        return $out;
    }

    // ── Global JS for email buttons ────────────────────────────────────────────
    public static function print_email_js(): void {
        ?>
<script>
(function(){
  // Delegated handler for all mj-email-doc buttons
  document.body.addEventListener('click', function(e){
    var btn = e.target.closest('.mj-email-doc');
    if (!btn) return;
    e.preventDefault();
    var wrap   = btn.closest('.mj-doc-btns');
    var nonce  = wrap ? wrap.dataset.nonce  : '';
    var ajax   = wrap ? wrap.dataset.ajax   : '';
    var saleId = wrap ? wrap.dataset.sale   : '';
    var type   = btn.dataset.type || 'invoice';

    btn.disabled = true;
    var orig = btn.innerHTML;
    btn.innerHTML = '⏳ Sending…';

    var fd = new FormData();
    fd.append('action',   'maljani_email_doc');
    fd.append('nonce',    nonce);
    fd.append('sale_id',  saleId);
    fd.append('doc_type', type);

    fetch(ajax, {method:'POST', body:fd})
      .then(function(r){ return r.json(); })
      .then(function(d){
        btn.innerHTML = d.success ? '✅ Sent!' : '❌ Failed';
        btn.style.background = d.success ? '#f0fdf4' : '#fee2e2';
        btn.style.color      = d.success ? '#166534' : '#991b1b';
        setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; btn.style = ''; }, 3500);
      })
      .catch(function(){
        btn.innerHTML = '❌ Error';
        setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; }, 3500);
      });
  });
})();
</script>
        <?php
    }
}

// ── Bootstrap ──────────────────────────────────────────────────────────────────
add_action('init', function() {
    new Maljani_Invoice();
});
