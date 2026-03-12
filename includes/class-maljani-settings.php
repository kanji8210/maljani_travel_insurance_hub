<?php
// includes/class-maljani-settings.php

class Maljani_Settings {
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_maljani_user_registration_page', [$this, 'maybe_add_registration_shortcode'], 10, 2);
        add_action('update_option_maljani_user_dashboard_page',    [$this, 'maybe_add_dashboard_shortcode'],    10, 2);
        add_action('update_option_maljani_policy_sale_page',       [$this, 'maybe_add_policy_sale_shortcode'],  10, 2);
    }

    public function register_settings() {
        // Page assignments
        register_setting('maljani_settings_group', 'maljani_user_registration_page');
        register_setting('maljani_settings_group', 'maljani_user_dashboard_page');
        register_setting('maljani_settings_group', 'maljani_policy_sale_page');
        // Support emails
        register_setting('maljani_settings_group', 'maljani_support_email_new_subject');
        register_setting('maljani_settings_group', 'maljani_support_email_new_body');
        register_setting('maljani_settings_group', 'maljani_support_email_response_subject');
        register_setting('maljani_settings_group', 'maljani_support_email_response_body');
        // Misc
        register_setting('maljani_settings_group', 'maljani_hide_modified_files', [
            'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        // Global fee defaults
        register_setting('maljani_settings_group', 'maljani_fee_service_type',   ['default' => 'percent', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_fee_service_value',  ['default' => 0,          'sanitize_callback' => 'floatval']);
        register_setting('maljani_settings_group', 'maljani_fee_agg_type',       ['default' => 'percent', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_fee_agg_value',      ['default' => 0,          'sanitize_callback' => 'floatval']);
        // Invoice & Compliance settings
        $inv_fields = [
            'maljani_inv_company_name', 'maljani_inv_address1', 'maljani_inv_address2',
            'maljani_inv_city', 'maljani_inv_country', 'maljani_inv_phone', 'maljani_inv_email',
            'maljani_inv_website', 'maljani_inv_kra_pin', 'maljani_inv_etr_number',
            'maljani_inv_payment_inst', 'maljani_inv_footer', 'maljani_inv_currency',
        ];
        foreach ($inv_fields as $f) register_setting('maljani_settings_group', $f, ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_inv_vat_enabled', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean']);
        register_setting('maljani_settings_group', 'maljani_inv_vat_rate',    ['sanitize_callback'=>'floatval']);

        // Pesapal Gateway Settings
        register_setting('maljani_settings_group', 'maljani_pesapal_consumer_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_pesapal_consumer_secret', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_pesapal_mode', ['default' => 'sandbox', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('maljani_settings_group', 'maljani_pesapal_ipn_id', ['sanitize_callback' => 'sanitize_text_field']);
    }

    // ── Shortcode auto-inject helpers ─────────────────────────────────────────
    public function maybe_add_registration_shortcode($new, $old) { self::inject_shortcode($new, '[maljani_user_registration]'); }
    public function maybe_add_dashboard_shortcode($new, $old)    { self::inject_shortcode($new, '[maljani_user_dashboard]'); }
    public function maybe_add_policy_sale_shortcode($new, $old)  { self::inject_shortcode($new, '[maljani_policy_sale]'); }
    private static function inject_shortcode($page_id, $sc) {
        if (!$page_id || get_post_type($page_id) !== 'page') return;
        $content = get_post_field('post_content', $page_id);
        if (strpos($content, $sc) === false) {
            wp_update_post(['ID' => $page_id, 'post_content' => $content . "\n\n" . $sc]);
        }
    }

    // ── Styles ────────────────────────────────────────────────────────────────
    private static function print_styles() {
        ?>
<style>
/* ── Maljani Settings — matches page-generator card style ─────── */
.mj-settings-wrap { font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',sans-serif; max-width:960px; }
.mj-settings-wrap h1 { font-size:22px; font-weight:800; color:#0f172a; margin-bottom:4px; }
.mj-settings-wrap .subtitle { color:#64748b; font-size:13px; margin:0 0 28px; }

/* Section card */
.mj-settings-card {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:12px;
    margin-bottom:20px;
    overflow:hidden;
    box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.mj-settings-card-head {
    display:flex;
    align-items:center;
    gap:12px;
    padding:16px 22px;
    border-bottom:1px solid #f1f5f9;
    background:#f8fafc;
}
.mj-settings-card-head .card-icon {
    width:36px;height:36px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;
}
.mj-settings-card-head h2 {
    margin:0;font-size:14px;font-weight:700;color:#0f172a;
}
.mj-settings-card-head p {
    margin:2px 0 0;font-size:12px;color:#64748b;
}
.mj-settings-card-body { padding:22px 24px; }

/* Field rows */
.mj-sf-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.mj-sf-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.mj-sf { margin-bottom:18px; }
.mj-sf:last-child { margin-bottom:0; }
.mj-sf label {
    display:block;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:#64748b;
    margin-bottom:7px;
}
.mj-sf .hint { font-size:11px;color:#94a3b8;margin-top:5px;display:block;font-weight:400;text-transform:none;letter-spacing:0; }
.mj-in {
    width:100%;padding:9px 13px;border:1px solid #e2e8f0;border-radius:8px;
    background:#fff;font-size:13px;color:#1e293b;
    transition:border-color .15s,box-shadow .15s;box-sizing:border-box;
}
.mj-in:focus { border-color:#4f46e5;outline:none;box-shadow:0 0 0 3px rgba(79,70,229,.1); }
select.mj-in option { color:#1e293b; }
textarea.mj-in { resize:vertical; }

/* Fee toggle */
.mj-toggle {
    display:inline-flex;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:10px;
}
.mj-toggle input[type=radio] { position:absolute;opacity:0;width:0;height:0; }
.mj-toggle label {
    padding:6px 16px;cursor:pointer;font-size:12px;font-weight:600;color:#64748b;
    background:#f8fafc;transition:background .12s,color .12s;margin:0;
    letter-spacing:0;text-transform:none;
}
.mj-toggle input[type=radio]:checked + label { background:#4f46e5;color:#fff; }
.mj-toggle label+input+label,.mj-toggle label:not(:last-child) { border-right:1px solid #e2e8f0; }

/* Fee pair layout */
.mj-fee-pair { display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:end; }

/* Save bar */
.mj-save-bar {
    background:#fff;border:1px solid #e2e8f0;border-radius:12px;
    padding:16px 24px;display:flex;align-items:center;justify-content:space-between;
    margin-top:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.mj-save-bar p { margin:0;color:#64748b;font-size:12px; }
.mj-btn {
    display:inline-flex;align-items:center;gap:5px;
    padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;
    cursor:pointer;border:1px solid;transition:background .15s;
}
.mj-btn-primary { background:#4f46e5;color:#fff;border-color:#4f46e5; }
.mj-btn-primary:hover { background:#4338ca;border-color:#4338ca;color:#fff; }

/* Notice pill */
.mj-notice { border-radius:8px!important; }
</style>
        <?php
    }

    // ── Render ────────────────────────────────────────────────────────────────
    public static function render_settings_page() {
        self::print_styles();
        $all_pages = get_posts(['post_type'=>'page','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);

        // Read fee options
        $svc_type  = get_option('maljani_fee_service_type',  'percent');
        $svc_val   = get_option('maljani_fee_service_value', 0);
        $agg_type  = get_option('maljani_fee_agg_type',      'percent');
        $agg_val   = get_option('maljani_fee_agg_value',     0);
        ?>
        <div class="wrap mj-settings-wrap">
            <h1>⚙️ Maljani Settings</h1>
            <p class="subtitle">Configure pages, global fee defaults, email templates, and system preferences.</p>

            <form method="post" action="options.php">
                <?php settings_fields('maljani_settings_group'); ?>

                <!-- ── Global Fees ───────────────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#fff3cd">💲</div>
                        <div>
                            <h2>Global Fee Defaults</h2>
                            <p>Default service fee applied to all policies. Individual policies may override aggregator &amp; agency commissions.</p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <div class="mj-sf-grid">
                            <!-- Service Fee -->
                            <div class="mj-sf">
                                <label>Client Service Fee</label>
                                <div class="mj-fee-pair">
                                    <div role="group" aria-label="Service fee type">
                                        <div class="mj-toggle">
                                            <input type="radio" id="svc_pct" name="maljani_fee_service_type" value="percent" <?php checked($svc_type,'percent');?>>
                                            <label for="svc_pct">% Percent</label>
                                            <input type="radio" id="svc_fix" name="maljani_fee_service_type" value="fixed"   <?php checked($svc_type,'fixed');?>>
                                            <label for="svc_fix">Fixed $</label>
                                        </div>
                                    </div>
                                    <input type="number" name="maljani_fee_service_value"
                                           value="<?php echo esc_attr($svc_val);?>"
                                           step="0.01" min="0"
                                           class="mj-in"
                                           aria-label="Service fee value"
                                           placeholder="0.00">
                                </div>
                                <span class="hint">Fee added to the client's total. Stays with Maljani.</span>
                            </div>

                            <!-- Default Aggregator Commission -->
                            <div class="mj-sf">
                                <label>Default Aggregator Commission</label>
                                <div class="mj-fee-pair">
                                    <div role="group" aria-label="Aggregator commission type">
                                        <div class="mj-toggle">
                                            <input type="radio" id="agg_pct" name="maljani_fee_agg_type" value="percent" <?php checked($agg_type,'percent');?>>
                                            <label for="agg_pct">% Percent</label>
                                            <input type="radio" id="agg_fix" name="maljani_fee_agg_type" value="fixed"   <?php checked($agg_type,'fixed');?>>
                                            <label for="agg_fix">Fixed $</label>
                                        </div>
                                    </div>
                                    <input type="number" name="maljani_fee_agg_value"
                                           value="<?php echo esc_attr($agg_val);?>"
                                           step="0.01" min="0"
                                           class="mj-in"
                                           aria-label="Aggregator commission value"
                                           placeholder="0.00">
                                </div>
                                <span class="hint">Fallback used when no per-policy aggregator commission is set.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Page Assignments ──────────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#dbeafe">📄</div>
                        <div>
                            <h2>Page Assignments</h2>
                            <p>Map WordPress pages to plugin shortcodes. Use <a href="<?php echo esc_url(admin_url('admin.php?page=maljani_pages_admin'));?>">📄 Page Management</a> to auto-create missing pages.</p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <div class="mj-sf-grid">
                            <?php
                            $page_fields = [
                                ['opt'=>'maljani_user_registration_page', 'label'=>'User Registration Page'],
                                ['opt'=>'maljani_user_dashboard_page',    'label'=>'User Dashboard Page'],
                                ['opt'=>'maljani_policy_sale_page',       'label'=>'Policy Sale Page'],
                            ];
                            foreach ($page_fields as $pf):
                                $sel = intval(get_option($pf['opt']));
                            ?>
                            <div class="mj-sf">
                                <label for="<?php echo esc_attr($pf['opt']);?>"><?php echo esc_html($pf['label']);?></label>
                                <select id="<?php echo esc_attr($pf['opt']);?>" name="<?php echo esc_attr($pf['opt']);?>" class="mj-in">
                                    <option value="">— Select page —</option>
                                    <?php foreach ($all_pages as $p):?>
                                        <option value="<?php echo $p->ID;?>" <?php selected($sel,$p->ID);?>><?php echo esc_html($p->post_title);?> (#<?php echo $p->ID;?>)</option>
                                    <?php endforeach;?>
                                </select>
                            </div>
                            <?php endforeach;?>
                        </div>
                    </div>
                </div>

                <!-- ── Email Templates ───────────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#ede9fe">✉️</div>
                        <div>
                            <h2>Support Email Templates</h2>
                            <p>Placeholders: <code>{id}</code>, <code>{email}</code>, <code>{message}</code>, <code>{response}</code></p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <div class="mj-sf-grid">
                            <div>
                                <h3 style="font-size:13px;font-weight:700;margin:0 0 14px;color:#475569">New Message (admin notification)</h3>
                                <div class="mj-sf">
                                    <label for="new_subj">Subject</label>
                                    <input id="new_subj" type="text" name="maljani_support_email_new_subject" class="mj-in"
                                           value="<?php echo esc_attr(get_option('maljani_support_email_new_subject','New support message: #{id}')); ?>">
                                </div>
                                <div class="mj-sf">
                                    <label for="new_body">Body</label>
                                    <textarea id="new_body" name="maljani_support_email_new_body" class="mj-in" rows="5"><?php echo esc_textarea(get_option('maljani_support_email_new_body',"A new support message (ID: {id})\nFrom: {email}\n\n{message}")); ?></textarea>
                                </div>
                            </div>
                            <div>
                                <h3 style="font-size:13px;font-weight:700;margin:0 0 14px;color:#475569">Response to User</h3>
                                <div class="mj-sf">
                                    <label for="resp_subj">Subject</label>
                                    <input id="resp_subj" type="text" name="maljani_support_email_response_subject" class="mj-in"
                                           value="<?php echo esc_attr(get_option('maljani_support_email_response_subject','Response to your support message #{id}')); ?>">
                                </div>
                                <div class="mj-sf">
                                    <label for="resp_body">Body</label>
                                    <textarea id="resp_body" name="maljani_support_email_response_body" class="mj-in" rows="5"><?php echo esc_textarea(get_option('maljani_support_email_response_body',"Hello,\n\nA support representative has replied to your message:\n\n{response}\n\nRegards")); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Pesapal Integration ────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#eff6ff">💳</div>
                        <div>
                            <h2>Pesapal Payment Gateway</h2>
                            <p>Configure your Pesapal v3.0 API keys for automated premium collection and split payments.</p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <div class="mj-sf-grid">
                            <div class="mj-sf">
                                <label for="pesapal_key">Consumer Key</label>
                                <input id="pesapal_key" type="text" name="maljani_pesapal_consumer_key" class="mj-in" 
                                       value="<?php echo esc_attr(get_option('maljani_pesapal_consumer_key')); ?>" placeholder="e.g. qre4e4... ">
                            </div>
                            <div class="mj-sf">
                                <label for="pesapal_secret">Consumer Secret</label>
                                <input id="pesapal_secret" type="password" name="maljani_pesapal_consumer_secret" class="mj-in" 
                                       value="<?php echo esc_attr(get_option('maljani_pesapal_consumer_secret')); ?>">
                            </div>
                        </div>
                        <div class="mj-sf-grid" style="margin-top:10px">
                            <div class="mj-sf">
                                <label for="pesapal_mode">Environment</label>
                                <select id="pesapal_mode" name="maljani_pesapal_mode" class="mj-in">
                                    <option value="sandbox" <?php selected(get_option('maljani_pesapal_mode'), 'sandbox'); ?>>Sandbox (Testing)</option>
                                    <option value="live" <?php selected(get_option('maljani_pesapal_mode'), 'live'); ?>>Live (Production)</option>
                                </select>
                            </div>
                            <div class="mj-sf">
                                <label>IPN Registration ID</label>
                                <input type="text" name="maljani_pesapal_ipn_id" class="mj-in" readonly 
                                       value="<?php echo esc_attr(get_option('maljani_pesapal_ipn_id')); ?>" placeholder="Automatically registered...">
                                <span class="hint">The system will automatically register this when you save valid keys.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── System Preferences ───────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#f0fdf4">🔧</div>
                        <div>
                            <h2>System Preferences</h2>
                            <p>Admin interface and diagnostic visibility options.</p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                            <input type="checkbox" name="maljani_hide_modified_files" value="1"
                                   <?php checked(get_option('maljani_hide_modified_files', true)); ?>
                                   style="width:16px;height:16px;accent-color:#4f46e5">
                            <span style="font-size:13px;font-weight:600;color:#1e293b">Hide modified-files notices in dashboard</span>
                        </label>
                        <span style="display:block;font-size:11px;color:#94a3b8;margin-top:6px;padding-left:26px">Suppresses the notice that appears when plugin files have been edited manually.</span>
                    </div>
                </div>

                <!-- ── Invoice & Compliance ─────────────────────── -->
                <div class="mj-settings-card">
                    <div class="mj-settings-card-head">
                        <div class="card-icon" style="background:#fef9c3">🧾</div>
                        <div>
                            <h2>Invoice &amp; Compliance</h2>
                            <p>Company details, KRA PIN, ETR number, and VAT configuration printed on invoices &amp; receipts.</p>
                        </div>
                    </div>
                    <div class="mj-settings-card-body">
                        <div class="mj-sf-grid" style="grid-template-columns:1fr 1fr">

                            <div class="mj-sf">
                                <label for="inv_company">Company / Trading Name</label>
                                <input id="inv_company" type="text" name="maljani_inv_company_name" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_company_name', get_bloginfo('name'))); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_email">Company Email (sender)</label>
                                <input id="inv_email" type="email" name="maljani_inv_email" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_email', get_option('admin_email'))); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_addr1">Address Line 1</label>
                                <input id="inv_addr1" type="text" name="maljani_inv_address1" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_address1', '')); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_addr2">Address Line 2 / P.O. Box</label>
                                <input id="inv_addr2" type="text" name="maljani_inv_address2" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_address2', '')); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_city">City</label>
                                <input id="inv_city" type="text" name="maljani_inv_city" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_city', 'Nairobi')); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_phone">Phone</label>
                                <input id="inv_phone" type="text" name="maljani_inv_phone" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_phone', '')); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_kra">KRA PIN</label>
                                <input id="inv_kra" type="text" name="maljani_inv_kra_pin" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_kra_pin', '')); ?>"
                                       placeholder="e.g. P051234567A">
                                <span class="hint">Printed on all invoices &amp; receipts as required by KRA.</span>
                            </div>

                            <div class="mj-sf">
                                <label for="inv_etr">ETR Machine Number <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
                                <input id="inv_etr" type="text" name="maljani_inv_etr_number" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_etr_number', '')); ?>">
                            </div>

                            <div class="mj-sf">
                                <label for="inv_cur">Currency Symbol</label>
                                <input id="inv_cur" type="text" name="maljani_inv_currency" class="mj-in"
                                       value="<?php echo esc_attr(get_option('maljani_inv_currency', 'KSH')); ?>" placeholder="KSH" style="max-width:120px">
                            </div>

                            <div class="mj-sf">
                                <label>VAT / Tax</label>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500">
                                        <input type="checkbox" name="maljani_inv_vat_enabled" value="1"
                                               <?php checked(get_option('maljani_inv_vat_enabled', false)); ?>
                                               id="inv_vat_en" style="width:15px;height:15px;accent-color:#4f46e5">
                                        <span>Enable VAT on invoices</span>
                                    </label>
                                    <span style="font-size:11px;color:#94a3b8">Rate (%):</span>
                                    <input type="number" name="maljani_inv_vat_rate" class="mj-in" step="0.1" min="0"
                                           value="<?php echo esc_attr(get_option('maljani_inv_vat_rate', 16)); ?>"
                                           style="max-width:80px">
                                </div>
                                <span class="hint">Currently <strong>not VAT-registered</strong>. Enable when registered. VAT applies to service fees only (insurance premiums are exempt).</span>
                            </div>

                        </div>

                        <div class="mj-sf" style="margin:16px 22px 0;padding-top:14px;border-top:1px solid #f1f5f9">
                            <label for="inv_pay_inst">Payment Instructions (shown on invoice)</label>
                            <textarea id="inv_pay_inst" name="maljani_inv_payment_inst" class="mj-in" rows="3"
                                      placeholder="e.g. M-Pesa Paybill 123456, Account: Policy Number"><?php echo esc_textarea(get_option('maljani_inv_payment_inst', '')); ?></textarea>
                        </div>

                        <div class="mj-sf" style="margin:10px 22px 22px;">
                            <label for="inv_footer">Invoice / Receipt Footer Text</label>
                            <textarea id="inv_footer" name="maljani_inv_footer" class="mj-in" rows="2"><?php echo esc_textarea(get_option('maljani_inv_footer', 'This is a computer-generated document and requires no signature. Thank you for choosing Maljani Travel Insurance.')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ── Save bar ──────────────────────────────────── -->
                <div class="mj-save-bar">
                    <p>Changes apply immediately after saving.</p>
                    <?php submit_button('Save All Settings', 'primary', 'submit', false, ['class'=>'mj-btn mj-btn-primary', 'style'=>'margin:0']); ?>
                </div>

            </form>
        </div>
        <?php
    }
}
new Maljani_Settings();