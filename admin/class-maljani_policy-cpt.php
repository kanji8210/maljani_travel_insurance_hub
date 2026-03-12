<?php
class Policy_CPT {

    public function register_Insurance_Policy() {
        $labels = [
            'name'               => 'Policies',
            'singular_name'      => 'Policy',
            'menu_name'          => 'Policies',
            'name_admin_bar'     => 'Policy',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Policy',
            'new_item'           => 'New Policy',
            'edit_item'          => 'Edit Policy',
            'view_item'          => 'View Policy',
            'all_items'          => 'All Policies',
            'search_items'       => 'Search Policies',
            'not_found'          => 'No policies found.',
            'not_found_in_trash' => 'No policies found in Trash.',
        ];
        $args = [
            'labels'        => $labels,
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => ['slug' => 'policy'],
            'supports'      => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest'  => true,
            'show_in_menu'  => 'maljani_travel',
        ];
        register_post_type('policy', $args);

        register_taxonomy('policy_region', 'policy', [
            'label'             => 'Regions',
            'rewrite'           => ['slug' => 'policy-region'],
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);

        add_action('add_meta_boxes',       [$this, 'add_meta_boxes']);
        add_action('save_post',            [$this, 'save_meta_boxes']);
        add_action('admin_enqueue_scripts',[$this, 'enqueue_admin_scripts']);
        if (is_admin()) {
            add_action('wp_ajax_add_policy_region', [$this, 'ajax_add_policy_region']);
        }
    }

    public function add_meta_boxes() {
        add_meta_box('policy_details', 'Policy Details', [$this, 'render_meta_box'], 'policy', 'normal', 'default');
    }

    // ── Meta box render ──────────────────────────────────────────────────────
    public function render_meta_box($post) {
        wp_nonce_field('policy_meta_box', 'policy_meta_box_nonce');

        $insurer_id      = get_post_meta($post->ID, '_policy_insurer',        true);
        $description     = get_post_meta($post->ID, '_policy_description',    true);
        $cover_details   = get_post_meta($post->ID, '_policy_cover_details',  true);
        $benefits        = get_post_meta($post->ID, '_policy_benefits',       true);
        $not_covered     = get_post_meta($post->ID, '_policy_not_covered',    true);
        $day_premiums    = get_post_meta($post->ID, '_policy_day_premiums',   true);
        $feature_img_id  = get_post_meta($post->ID, '_policy_feature_img',   true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';
        $currency        = get_post_meta($post->ID, '_policy_currency',       true) ?: 'KSH';
        $payment_details = get_post_meta($post->ID, '_policy_payment_details',true);

        // Flexible fee fields (aggregator comm + agency comm only — service fee is global)
        $agg_type = get_post_meta($post->ID, '_policy_aggregator_comm_type',  true) ?: 'percent';
        $agg_val  = get_post_meta($post->ID, '_policy_aggregator_comm_value', true);
        if ($agg_val === '') $agg_val = get_post_meta($post->ID, '_policy_aggregator_comm_pct', true) ?: '0';

        $agn_type = get_post_meta($post->ID, '_policy_agency_comm_type',  true) ?: 'percent';
        $agn_val  = get_post_meta($post->ID, '_policy_agency_comm_value', true);
        if ($agn_val === '') $agn_val = get_post_meta($post->ID, '_policy_agency_comm_pct', true) ?: '0';

        $insurers = get_posts(['post_type' => 'insurer_profile', 'numberposts' => -1]);
        $regions  = get_terms(['taxonomy' => 'policy_region', 'hide_empty' => false]);
        $current_regions = wp_get_post_terms($post->ID, 'policy_region', ['fields' => 'ids']);

        ob_start();
        ?>
<style>
/* ── Policy CPT — modern minimalist metabox ─────────────────── */
#policy_details { padding: 0; }
#policy_details .inside { padding: 0; margin: 0; }

.mj-cpt-shell {
    display: flex;
    min-height: 520px;
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
    font-size: 13px;
    color: #1e293b;
}

/* ── Sidebar ── */
.mj-cpt-nav {
    width: 180px;
    flex-shrink: 0;
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.mj-cpt-nav-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 18px;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    border-left: 3px solid transparent;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    border-radius: 0;
    line-height: 1.4;
}
.mj-cpt-nav-btn:hover {
    background: rgba(79,70,229,.06);
    color: #4f46e5;
}
.mj-cpt-nav-btn.active {
    background: rgba(79,70,229,.08);
    color: #4f46e5;
    font-weight: 700;
    border-left-color: #4f46e5;
}
.mj-cpt-nav-btn .nav-icon {
    font-size: 16px;
    line-height: 1;
    opacity: .75;
}
.mj-cpt-nav-btn.active .nav-icon { opacity: 1; }

/* ── Content panels ── */
.mj-cpt-panels {
    flex: 1;
    padding: 24px 28px;
    overflow: auto;
}
.mj-panel { display: none; }
.mj-panel.active {
    display: block;
    animation: mjSlide .2s ease;
}
@keyframes mjSlide {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Panel header ── */
.mj-panel-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.mj-panel-title::before {
    content: attr(data-icon);
    font-size: 18px;
    line-height: 1;
}

/* ── Field groups ── */
.mj-field { margin-bottom: 20px; }
.mj-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #64748b;
    margin-bottom: 7px;
}
.mj-field .hint {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
}

.mj-in {
    width: 100%;
    padding: 9px 13px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    color: #1e293b;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
}
.mj-in:focus {
    border-color: #4f46e5;
    outline: none;
    box-shadow: 0 0 0 3px rgba(79,70,229,.1);
}
select.mj-in { padding-right: 30px; }
textarea.mj-in { resize: vertical; }

/* ── 2-col grid ── */
.mj-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.mj-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }

/* ── Region inline add ── */
.mj-region-wrap { display: flex; gap: 8px; }
.mj-region-wrap .mj-in { flex: 1; }
.mj-region-wrap button { flex-shrink: 0; }

/* ── Fee toggle ── */
.mj-fee-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
}
.mj-fee-card h4 {
    margin: 0 0 12px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #475569;
}
.mj-toggle {
    display: inline-flex;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
}
.mj-toggle input[type=radio] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.mj-toggle label {
    padding: 6px 14px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    background: #f8fafc;
    transition: background .12s, color .12s;
    margin: 0;
    text-transform: none;
    letter-spacing: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}
.mj-toggle input[type=radio]:checked + label {
    background: #4f46e5;
    color: #fff;
}
.mj-toggle label:not(:last-child) { border-right: 1px solid #e2e8f0; }

/* ── Premium bracket table ── */
.mj-bracket-tbl {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
}
.mj-bracket-tbl thead { background: #f8fafc; }
.mj-bracket-tbl th {
    padding: 10px 12px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}
.mj-bracket-tbl td {
    padding: 8px 10px;
    border-top: 1px solid #f1f5f9;
}
.mj-bracket-tbl td input.mj-in { padding: 7px 10px; font-size: 13px; }
.mj-bracket-tbl tr:first-child td { border-top: none; }

/* ── Buttons ── */
.mj-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid;
    transition: background .15s, transform .1s;
    text-decoration: none;
}
.mj-btn:active { transform: translateY(1px); }
.mj-btn-primary { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.mj-btn-primary:hover { background: #4338ca; color: #fff; border-color: #4338ca; }
.mj-btn-ghost { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.mj-btn-ghost:hover { background: #e2e8f0; }
.mj-btn-icon { padding: 6px 10px; font-size: 14px; }

/* ── Image uploader ── */
.mj-img-box {
    width: 110px;
    height: 150px;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    overflow: hidden;
    margin-bottom: 10px;
    cursor: pointer;
    transition: border-color .15s;
}
.mj-img-box:hover { border-color: #4f46e5; }
.mj-img-box img { width: 100%; height: 100%; object-fit: cover; }
.mj-img-box .placeholder { font-size: 32px; color: #cbd5e1; }

/* ── Accessibility focus ── */
.mj-cpt-nav-btn:focus-visible,
.mj-btn:focus-visible,
.mj-in:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: 2px;
}
</style>

<div class="mj-cpt-shell" role="main">

    <!-- Sidebar navigation -->
    <nav class="mj-cpt-nav" aria-label="Policy sections">
        <?php
        $tabs = [
            ['id' => 'basic',    'icon' => '🗂️',  'label' => 'Basic Info'],
            ['id' => 'coverage', 'icon' => '🛡️',  'label' => 'Coverage'],
            ['id' => 'pricing',  'icon' => '💲',  'label' => 'Pricing'],
            ['id' => 'media',    'icon' => '🖼️',  'label' => 'Media & Notes'],
        ];
        foreach ($tabs as $i => $t):
        ?>
        <button type="button"
                class="mj-cpt-nav-btn<?php echo $i === 0 ? ' active' : ''; ?>"
                data-panel="<?php echo esc_attr($t['id']); ?>"
                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                aria-controls="mjp-<?php echo esc_attr($t['id']); ?>"
                role="tab">
            <span class="nav-icon" aria-hidden="true"><?php echo $t['icon']; ?></span>
            <?php echo esc_html($t['label']); ?>
        </button>
        <?php endforeach; ?>
    </nav>

    <!-- Panels -->
    <div class="mj-cpt-panels">

        <!-- ── Basic Info ────────────────────────────────────── -->
        <div id="mjp-basic" class="mj-panel active" role="tabpanel" aria-labelledby="tab-basic">
            <h2 class="mj-panel-title" data-icon="🗂️">Basic Information</h2>

            <div class="mj-field">
                <label for="policy_insurer">Assigned Insurer</label>
                <select id="policy_insurer" name="policy_insurer" class="mj-in" aria-required="true">
                    <option value="">— Select Insurer —</option>
                    <?php foreach ($insurers as $ins):?>
                        <option value="<?php echo $ins->ID;?>" <?php selected($insurer_id, $ins->ID);?>><?php echo esc_html($ins->post_title);?></option>
                    <?php endforeach;?>
                </select>
            </div>

            <div class="mj-field">
                <label for="policy_description">Marketing Hook</label>
                <input id="policy_description" type="text" name="policy_description"
                       value="<?php echo esc_attr($description);?>"
                       class="mj-in"
                       placeholder="e.g. Best coverage for European travel"
                       aria-describedby="desc-hint">
                <span id="desc-hint" class="hint">Short tagline shown on the policy card.</span>
            </div>

            <div class="mj-field">
                <label for="policy_region_select">Primary Region</label>
                <div class="mj-region-wrap">
                    <select id="policy_region_select" name="policy_region" class="mj-in">
                        <option value="">— Select Region —</option>
                        <?php foreach ($regions as $reg):?>
                            <option value="<?php echo $reg->term_id;?>" <?php echo in_array($reg->term_id, $current_regions) ? 'selected' : '';?>>
                                <?php echo esc_html($reg->name);?>
                            </option>
                        <?php endforeach;?>
                    </select>
                    <input type="text" id="new_policy_region"
                           placeholder="Quick-add region…"
                           class="mj-in"
                           aria-label="New region name"
                           style="max-width:180px">
                    <button type="button" id="add_policy_region" class="mj-btn mj-btn-primary">Add</button>
                </div>
            </div>
        </div>

        <!-- ── Coverage ──────────────────────────────────────── -->
        <div id="mjp-coverage" class="mj-panel" role="tabpanel" aria-labelledby="tab-coverage">
            <h2 class="mj-panel-title" data-icon="🛡️">Coverage &amp; Benefits</h2>

            <div class="mj-field">
                <label>Coverage Details</label>
                <?php wp_editor($cover_details, 'policy_cover_details', ['textarea_rows' => 8, 'media_buttons' => false]);?>
            </div>
            <div class="mj-field" style="margin-top:24px">
                <label>Included Benefits</label>
                <?php wp_editor($benefits, 'policy_benefits', ['textarea_rows' => 8, 'media_buttons' => false]);?>
            </div>
            <div class="mj-field" style="margin-top:24px">
                <label>What is NOT Covered</label>
                <?php wp_editor($not_covered, 'policy_not_covered', ['textarea_rows' => 5, 'media_buttons' => false]);?>
            </div>
        </div>

        <!-- ── Pricing ───────────────────────────────────────── -->
        <div id="mjp-pricing" class="mj-panel" role="tabpanel" aria-labelledby="tab-pricing">
            <h2 class="mj-panel-title" data-icon="💲">Pricing Structure</h2>

            <div class="mj-field">
                <label for="policy_currency">Display Currency</label>
                <select id="policy_currency" name="policy_currency" class="mj-in" style="max-width:200px">
                    <option value="KSH" <?php selected($currency,'KSH');?>>KSH — Kenyan Shilling</option>
                    <option value="USD" <?php selected($currency,'USD');?>>USD — US Dollar</option>
                    <option value="EUR" <?php selected($currency,'EUR');?>>EUR — Euro</option>
                </select>
            </div>

            <!-- Commission settings — only aggregator and agency, NO service fee -->
            <div class="mj-row-2" style="margin-bottom:22px">
                <?php
                $fees = [
                    ['id'=>'agg', 'type_name'=>'aggregator_comm_type', 'val_name'=>'aggregator_comm_value',
                     'type'=>$agg_type, 'val'=>$agg_val, 'label'=>'Aggregator Commission',
                     'hint'=>'What the insurer pays Maljani.'],
                    ['id'=>'agn', 'type_name'=>'agency_comm_type',      'val_name'=>'agency_comm_value',
                     'type'=>$agn_type, 'val'=>$agn_val, 'label'=>'Agency Commission',
                     'hint'=>'Paid to agency by insurer (tracked only).'],
                ];
                foreach ($fees as $f):
                    $pct_id = $f['id'].'_pct';
                    $fix_id = $f['id'].'_fix';
                ?>
                <div class="mj-fee-card">
                    <h4><?php echo esc_html($f['label']);?></h4>
                    <div role="group" aria-label="<?php echo esc_attr($f['label'].' type');?>">
                        <div class="mj-toggle">
                            <input type="radio" id="<?php echo $pct_id;?>"
                                   name="<?php echo $f['type_name'];?>"
                                   value="percent"
                                   <?php checked($f['type'],'percent');?>>
                            <label for="<?php echo $pct_id;?>">% Percent</label>
                            <input type="radio" id="<?php echo $fix_id;?>"
                                   name="<?php echo $f['type_name'];?>"
                                   value="fixed"
                                   <?php checked($f['type'],'fixed');?>>
                            <label for="<?php echo $fix_id;?>">Fixed $</label>
                        </div>
                    </div>
                    <input type="number"
                           name="<?php echo $f['val_name'];?>"
                           value="<?php echo esc_attr($f['val']);?>"
                           step="0.01" min="0"
                           class="mj-in"
                           placeholder="0.00"
                           aria-label="<?php echo esc_attr($f['label'].' value');?>">
                    <span class="hint" style="display:block;margin-top:6px"><?php echo esc_html($f['hint']);?></span>
                    <p style="font-size:11px;color:#94a3b8;margin:8px 0 0;background:#fff;border-radius:6px;padding:6px 8px;border:1px solid #e2e8f0">
                        ℹ️ <em>Client service fee is configured globally in <a href="<?php echo esc_url(admin_url('admin.php?page=maljani_settings'));?>">Settings → Global Fees</a>.</em>
                    </p>
                </div>
                <?php endforeach;?>
            </div>

            <!-- Day-premium brackets -->
            <div class="mj-field">
                <label>Pricing Rules <span style="font-weight:400;color:#94a3b8">(by trip duration)</span></label>
                <table class="mj-bracket-tbl" id="day-premium-table">
                    <thead>
                        <tr>
                            <th scope="col">Min Days</th>
                            <th scope="col">Max Days</th>
                            <th scope="col">Premium Amount</th>
                            <th scope="col"><span class="screen-reader-text">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($day_premiums)): foreach ($day_premiums as $row):?>
                        <tr>
                            <td><input type="number" name="day_premium_from[]"   value="<?php echo esc_attr($row['from']);?>"    class="mj-in" aria-label="Min days"></td>
                            <td><input type="number" name="day_premium_to[]"     value="<?php echo esc_attr($row['to']);?>"      class="mj-in" aria-label="Max days"></td>
                            <td><input type="number" name="day_premium_amount[]" value="<?php echo esc_attr($row['premium']);?>" class="mj-in" step="0.01" aria-label="Premium amount"></td>
                            <td><button type="button" class="remove-row mj-btn mj-btn-ghost mj-btn-icon" aria-label="Remove row">×</button></td>
                        </tr>
                    <?php endforeach; else:?>
                        <tr>
                            <td><input type="number" name="day_premium_from[]"   value="1"    class="mj-in" aria-label="Min days"></td>
                            <td><input type="number" name="day_premium_to[]"     value="30"   class="mj-in" aria-label="Max days"></td>
                            <td><input type="number" name="day_premium_amount[]" value="0.00" class="mj-in" step="0.01" aria-label="Premium amount"></td>
                            <td><button type="button" class="remove-row mj-btn mj-btn-ghost mj-btn-icon" aria-label="Remove row">×</button></td>
                        </tr>
                    <?php endif;?>
                    </tbody>
                </table>
                <button type="button" id="add-day-premium-row" class="mj-btn mj-btn-primary" style="margin-top:12px;width:100%;justify-content:center">
                    + Add Duration Bracket
                </button>
            </div>
        </div>

        <!-- ── Media & Notes ─────────────────────────────────── -->
        <div id="mjp-media" class="mj-panel" role="tabpanel" aria-labelledby="tab-media">
            <h2 class="mj-panel-title" data-icon="🖼️">Media &amp; Notes</h2>

            <div class="mj-field">
                <label>Policy Feature Image</label>
                <div class="mj-img-box" id="policy_feature_img_preview_container"
                     role="button" aria-label="Upload feature image" tabindex="0">
                    <?php if ($feature_img_url):?>
                        <img src="<?php echo esc_url($feature_img_url);?>"
                             id="policy_feature_img_preview"
                             alt="Policy feature image">
                    <?php else:?>
                        <span class="placeholder" aria-hidden="true">🖼️</span>
                    <?php endif;?>
                </div>
                <input type="hidden" name="policy_feature_img" id="policy_feature_img" value="<?php echo esc_attr($feature_img_id);?>">
                <div style="display:flex;gap:8px">
                    <button type="button" class="mj-btn mj-btn-primary" id="upload_policy_feature_img">Select Image</button>
                    <button type="button" class="mj-btn mj-btn-ghost" id="remove_policy_feature_img">Remove</button>
                </div>
            </div>

            <div class="mj-field" style="margin-top:20px">
                <label for="policy_payment_details">Internal Notes <span style="font-weight:400;color:#94a3b8">(private)</span></label>
                <textarea id="policy_payment_details"
                          name="policy_payment_details"
                          class="mj-in"
                          rows="5"
                          placeholder="Underwriting notes, payment instructions…"><?php echo esc_textarea($payment_details);?></textarea>
            </div>
        </div>

    </div><!-- /panels -->
</div><!-- /shell -->

<script>
(function() {
    // Tab navigation
    document.querySelectorAll('.mj-cpt-nav-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.panel;
            document.querySelectorAll('.mj-cpt-nav-btn').forEach(function(b) {
                b.classList.remove('active');
                b.setAttribute('aria-selected','false');
            });
            document.querySelectorAll('.mj-panel').forEach(function(p) {
                p.classList.remove('active');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected','true');
            var panel = document.getElementById('mjp-' + id);
            if (panel) panel.classList.add('active');
        });
        // Keyboard: arrow keys
        btn.addEventListener('keydown', function(e) {
            var btns = Array.from(document.querySelectorAll('.mj-cpt-nav-btn'));
            var idx  = btns.indexOf(this);
            if (e.key === 'ArrowDown' && idx < btns.length - 1) { e.preventDefault(); btns[idx+1].focus(); btns[idx+1].click(); }
            if (e.key === 'ArrowUp'   && idx > 0)               { e.preventDefault(); btns[idx-1].focus(); btns[idx-1].click(); }
        });
    });
    // Click on image preview box opens uploader
    var imgBox = document.getElementById('policy_feature_img_preview_container');
    if (imgBox) {
        imgBox.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                document.getElementById('upload_policy_feature_img').click();
            }
        });
    }
})();
</script>
        <?php
        echo ob_get_clean();
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['policy_meta_box_nonce']) || !wp_verify_nonce($_POST['policy_meta_box_nonce'], 'policy_meta_box')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $text_fields = [
            'policy_insurer'     => ['meta' => '_policy_insurer',       'fn' => 'intval'],
            'policy_description' => ['meta' => '_policy_description',   'fn' => 'sanitize_text_field'],
            'policy_currency'    => ['meta' => '_policy_currency',      'fn' => 'sanitize_text_field'],
        ];
        foreach ($text_fields as $post_key => $cfg) {
            if (isset($_POST[$post_key])) {
                $val = $cfg['fn'] === 'intval' ? intval($_POST[$post_key]) : sanitize_text_field($_POST[$post_key]);
                update_post_meta($post_id, $cfg['meta'], $val);
            }
        }
        foreach (['policy_cover_details','policy_benefits','policy_not_covered','policy_payment_details'] as $key) {
            if (isset($_POST[$key])) update_post_meta($post_id, '_'.$key, wp_kses_post($_POST[$key]));
        }

        // Day premium brackets
        $premiums = [];
        if (isset($_POST['day_premium_from'], $_POST['day_premium_to'], $_POST['day_premium_amount'])) {
            foreach ($_POST['day_premium_from'] as $i => $from) {
                $to  = $_POST['day_premium_to'][$i]     ?? '';
                $amt = $_POST['day_premium_amount'][$i] ?? '';
                if ($from !== '' && $to !== '' && $amt !== '') {
                    $premiums[] = ['from' => intval($from), 'to' => intval($to), 'premium' => floatval($amt)];
                }
            }
        }
        update_post_meta($post_id, '_policy_day_premiums', $premiums);

        // Flexible fee fields (aggregator + agency only)
        $fee_fields = [
            ['type_key'=>'_policy_aggregator_comm_type','val_key'=>'_policy_aggregator_comm_value','type_post'=>'aggregator_comm_type','val_post'=>'aggregator_comm_value'],
            ['type_key'=>'_policy_agency_comm_type',    'val_key'=>'_policy_agency_comm_value',    'type_post'=>'agency_comm_type',    'val_post'=>'agency_comm_value'],
        ];
        foreach ($fee_fields as $f) {
            if (isset($_POST[$f['type_post']])) {
                $type = in_array($_POST[$f['type_post']], ['percent','fixed']) ? $_POST[$f['type_post']] : 'percent';
                update_post_meta($post_id, $f['type_key'], $type);
            }
            if (isset($_POST[$f['val_post']])) {
                update_post_meta($post_id, $f['val_key'], floatval($_POST[$f['val_post']]));
            }
        }

        if (isset($_POST['policy_feature_img'])) {
            update_post_meta($post_id, '_policy_feature_img', intval($_POST['policy_feature_img']));
        }
        if (isset($_POST['policy_region'])) {
            wp_set_post_terms($post_id, [intval($_POST['policy_region'])], 'policy_region');
            update_post_meta($post_id, '_policy_region', intval($_POST['policy_region']));
        }
    }

    // ── Scripts ───────────────────────────────────────────────────────────────
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        wp_enqueue_media();
        wp_enqueue_script(
            'policy-admin-js',
            plugin_dir_url(__FILE__) . 'js/policy-admin.js',
            ['jquery'],
            null,
            true
        );
        wp_localize_script('policy-admin-js', 'policyAdmin', [
            'nonce'    => wp_create_nonce('add_policy_region_nonce'),
            'security' => wp_create_nonce('add_policy_region_nonce'),
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    // ── AJAX: add region ──────────────────────────────────────────────────────
    public function ajax_add_policy_region() {
        if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions');
        if (!wp_verify_nonce($_POST['security'], 'add_policy_region_nonce')) wp_send_json_error('Invalid nonce');
        $region = sanitize_text_field($_POST['region'] ?? '');
        if (!$region) wp_send_json_error('Region name is required');
        $term = wp_insert_term($region, 'policy_region');
        if (!is_wp_error($term)) {
            wp_send_json_success(['term_id' => $term['term_id'], 'name' => $region]);
        } else {
            wp_send_json_error('Error: ' . $term->get_error_message());
        }
    }
}
