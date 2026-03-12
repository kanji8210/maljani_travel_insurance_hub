<?php
class Maljani_Agencies_Admin {

    public static function render_page() {
        if (!current_user_can('manage_options') && !current_user_can('manage_maljani_agencies')) {
            wp_die('Unauthorized');
        }
        global $wpdb;
        $tbl = $wpdb->prefix . 'maljani_agencies';
        $sales_tbl = $wpdb->prefix . 'policy_sale';

        // ── Handle POST actions ──────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maljani_agency_action']) && wp_verify_nonce($_POST['_wpnonce'], 'maljani_agency_nonce')) {
            $action = sanitize_text_field($_POST['maljani_agency_action']);

            if ($action === 'update_agency') {
                $id = intval($_POST['agency_id']);
                $wpdb->update($tbl, [
                    'name'              => sanitize_text_field($_POST['name']),
                    'contact_name'      => sanitize_text_field($_POST['contact_name']),
                    'contact_email'     => sanitize_email($_POST['contact_email']),
                    'contact_phone'     => sanitize_text_field($_POST['contact_phone'] ?? ''),
                    'commission_rate'   => floatval($_POST['commission_rate']),
                    'notes'             => sanitize_textarea_field($_POST['notes'] ?? ''),
                    'updated_at'        => current_time('mysql', 1),
                ], ['id' => $id]);
                echo '<div class="notice notice-success is-dismissible"><p>Agency updated.</p></div>';
            }

            if ($action === 'create_agency') {
                // Check if linked WP user exists
                $user_id = 0;
                $email = sanitize_email($_POST['contact_email']);
                if ($email) {
                    $existing = get_user_by('email', $email);
                    if ($existing) {
                        $user_id = $existing->ID;
                    } else {
                        $pw = wp_generate_password(12, false);
                        $uid = wp_create_user(sanitize_user($email), $pw, $email);
                        if (!is_wp_error($uid)) {
                            wp_update_user(['ID' => $uid, 'role' => 'agent']);
                            $user_id = $uid;
                        }
                    }
                }
                $wpdb->insert($tbl, [
                    'name'            => sanitize_text_field($_POST['name']),
                    'contact_name'    => sanitize_text_field($_POST['contact_name']),
                    'contact_email'   => $email,
                    'contact_phone'   => sanitize_text_field($_POST['contact_phone'] ?? ''),
                    'commission_rate' => floatval($_POST['commission_rate']),
                    'user_id'         => $user_id,
                    'status'          => 'approved', // Admin creates are auto-approved
                    'notes'           => sanitize_textarea_field($_POST['notes'] ?? ''),
                    'created_at'      => current_time('mysql', 1),
                    'updated_at'      => current_time('mysql', 1),
                ]);
                echo '<div class="notice notice-success is-dismissible"><p>Agency created' . ($user_id ? ' and WP user linked.' : '.') . '</p></div>';
            }

            if ($action === 'approve_agency') {
                $id = intval($_POST['agency_id']);
                $wpdb->update($tbl, ['status' => 'approved', 'updated_at' => current_time('mysql', 1)], ['id' => $id]);
                echo '<div class="notice notice-success is-dismissible"><p>Agency approved.</p></div>';
            }

            if ($action === 'reject_agency') {
                $id = intval($_POST['agency_id']);
                $wpdb->update($tbl, ['status' => 'rejected', 'updated_at' => current_time('mysql', 1)], ['id' => $id]);
                echo '<div class="notice notice-error is-dismissible"><p>Agency rejected.</p></div>';
            }
        }

        // ── Data ─────────────────────────────────────────────────────────────
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'approved';
        $agencies = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tbl WHERE status = %s ORDER BY created_at DESC", $status_filter));
        
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $tbl WHERE status = 'pending'");

        // Performance stats per agency (join with sales)
        $perf = [];
        if (!empty($agencies)) {
            $ids = implode(',', array_map('intval', wp_list_pluck($agencies, 'id')));
            $rows = $wpdb->get_results("
                SELECT agency_id,
                       COUNT(*) as total_sales,
                       SUM(CASE WHEN policy_status='active' THEN 1 ELSE 0 END) as active,
                       SUM(premium) as total_premium,
                       SUM(agent_commission_amount) as total_comm,
                       SUM(CASE WHEN agent_commission_status='disputed' THEN 1 ELSE 0 END) as disputed
                FROM $sales_tbl WHERE agency_id IN ($ids) GROUP BY agency_id
            ");
            foreach ($rows as $r) $perf[$r->agency_id] = $r;
        }

        $filter_agency = isset($_GET['view_agency']) ? intval($_GET['view_agency']) : 0;
        ?>
        <style>
        .mja{font-family:'Inter',sans-serif}
        .mja-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
        .mja-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px}
        .mja-card-head{padding:14px 20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:space-between}
        .mja-card-head h3{margin:0;font-size:15px;font-weight:700}
        .mja-card-body{padding:20px}
        .mja-tbl{width:100%;border-collapse:collapse}
        .mja-tbl th{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left}
        .mja-tbl td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:top}
        .mja-tbl tr:last-child td{border-bottom:none}
        .mja-tbl tr:hover td{background:#fafafe}
        .perf-grid{display:flex;gap:12px;flex-wrap:wrap}
        .perf-chip{background:#f0f4ff;border-radius:8px;padding:4px 10px;font-size:12px;font-weight:600;color:#4f46e5}
        .perf-chip.red{background:#fee2e2;color:#991b1b}
        .mja-edit-row{background:#f8fafc}
        .mja-edit-row td{padding:16px 14px}
        .mja-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
        .mja-grid label{font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px}
        .mja-grid input,.mja-grid select,.mja-grid textarea{width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
        .mja-grid textarea{height:60px;resize:vertical}
        .mj-b{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid;text-decoration:none;display:inline-block}
        .mj-pri{background:#4f46e5;color:#fff;border-color:#4f46e5}
        .mj-pri:hover{background:#4338ca;color:#fff}
        .mj-sec{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
        .mja-form-card{background:#fff;border:1px solid #c7d2fe;border-radius:12px;padding:20px;margin-bottom:20px}
        .comm-view-tbl{width:100%;border-collapse:collapse;font-size:12px}
        .comm-view-tbl th{background:#f8fafc;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e2e8f0}
        .comm-view-tbl td{padding:8px 10px;border-bottom:1px solid #f1f5f9}
        .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700}
        </style>
        <div class="mja wrap">
            <div class="mja-header">
                <h1 style="margin:0">🏢 Manage Agencies</h1>
                <div style="display:flex; gap:10px;">
                    <a href="<?php echo esc_url(add_query_arg(['status'=>'pending'], admin_url('admin.php?page=maljani_agencies'))); ?>" class="mj-b <?php echo $status_filter==='pending'?'mj-pri':'mj-sec'; ?>">
                        ⏳ Pending Approvals <?php if($pending_count > 0) echo "<span style='background:#f43f5e; color:#fff; border-radius:10px; padding:2px 6px; font-size:10px; margin-left:4px;'>$pending_count</span>"; ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(['status'=>'approved'], admin_url('admin.php?page=maljani_agencies'))); ?>" class="mj-b <?php echo $status_filter==='approved'?'mj-pri':'mj-sec'; ?>">✅ Approved</a>
                    <a href="<?php echo esc_url(add_query_arg(['status'=>'rejected'], admin_url('admin.php?page=maljani_agencies'))); ?>" class="mj-b <?php echo $status_filter==='rejected'?'mj-pri':'mj-sec'; ?>">❌ Rejected</a>
                    <button type="button" class="mj-b mj-pri" onclick="document.getElementById('mja-new-form').style.display=document.getElementById('mja-new-form').style.display==='none'?'':'none'" style="margin-left:10px;">+ Add New Agency</button>
                </div>
            </div>

            <!-- Add New Form -->
            <div id="mja-new-form" style="display:none" class="mja-form-card">
                <h3 style="margin:0 0 16px;color:#4f46e5">➕ New Agency</h3>
                <form method="post">
                    <?php wp_nonce_field('maljani_agency_nonce'); ?>
                    <input type="hidden" name="maljani_agency_action" value="create_agency">
                    <div class="mja-grid" style="margin-bottom:12px">
                        <div><label>Agency Name *</label><input type="text" name="name" required></div>
                        <div><label>Contact Name *</label><input type="text" name="contact_name" required></div>
                        <div><label>Contact Email *</label><input type="email" name="contact_email" required><small style="color:#64748b">A WP agent account will be linked/created.</small></div>
                        <div><label>Phone</label><input type="text" name="contact_phone"></div>
                        <div><label>Commission Rate (%)</label><input type="number" name="commission_rate" value="0" step="0.01" min="0" max="100"></div>
                        <div><label>Notes</label><textarea name="notes" placeholder="Internal notes…"></textarea></div>
                    </div>
                    <button type="submit" class="mj-b mj-pri">Create Agency</button>
                    <button type="button" class="mj-b mj-sec" onclick="document.getElementById('mja-new-form').style.display='none'">Cancel</button>
                </form>
            </div>

            <!-- Agencies Table -->
            <div class="mja-card">
                <div class="mja-card-head"><h3>All Agencies (<?php echo count($agencies); ?>)</h3></div>
                <div class="mja-card-body" style="padding:0">
                    <table class="mja-tbl">
                        <thead><tr>
                            <th>Agency</th><th>Contact</th><th>Commission</th>
                            <th>Performance</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($agencies)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:#94a3b8">No agencies yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($agencies as $a):
                            $pr = $perf[$a->id] ?? null;
                            $disputed = $pr ? intval($pr->disputed) : 0;
                        ?>
                        <tr id="agv-<?php echo $a->id; ?>">
                            <td>
                                <strong><?php echo esc_html($a->name ?? $a->agency_name ?? '—'); ?></strong><br>
                                <?php if (!empty($a->user_id)):?>
                                    <small style="color:#64748b">WP User #<?php echo $a->user_id; ?></small>
                                <?php endif;?>
                            </td>
                            <td>
                                <?php echo esc_html($a->contact_name ?? '—'); ?><br>
                                <a href="mailto:<?php echo esc_attr($a->contact_email ?? ''); ?>"><?php echo esc_html($a->contact_email ?? ''); ?></a><br>
                                <small><?php echo esc_html($a->contact_phone ?? ''); ?></small>
                            </td>
                            <td style="font-weight:700;font-size:16px;color:#4f46e5"><?php echo floatval($a->commission_rate ?? $a->commission_percent ?? 0); ?>%</td>
                            <td>
                                <div class="perf-grid">
                                    <div class="perf-chip">📋 <?php echo $pr ? intval($pr->total_sales) : 0; ?> sales</div>
                                    <div class="perf-chip">✅ <?php echo $pr ? intval($pr->active) : 0; ?> active</div>
                                    <div class="perf-chip">💰 $<?php echo $pr ? number_format(floatval($pr->total_premium), 0) : '0'; ?></div>
                                    <div class="perf-chip">🤝 $<?php echo $pr ? number_format(floatval($pr->total_comm), 0) : '0'; ?> comm</div>
                                    <?php if ($disputed > 0): ?><div class="perf-chip red">⚠️ <?php echo $disputed; ?> disputed</div><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    <?php if ($a->status === 'pending'): ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('maljani_agency_nonce'); ?>
                                            <input type="hidden" name="maljani_agency_action" value="approve_agency">
                                            <input type="hidden" name="agency_id" value="<?php echo esc_attr($a->id); ?>">
                                            <button type="submit" class="mj-b" style="background:#10b981; color:#fff; border:none;">Approve</button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('maljani_agency_nonce'); ?>
                                            <input type="hidden" name="maljani_agency_action" value="reject_agency">
                                            <input type="hidden" name="agency_id" value="<?php echo esc_attr($a->id); ?>">
                                            <button type="submit" class="mj-b" style="background:#f43f5e; color:#fff; border:none;" onclick="return confirm('Reject this application?');">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="mj-b mj-pri tedit-ag" data-id="<?php echo $a->id; ?>">✏️ Edit</button>
                                        <a href="<?php echo esc_url(add_query_arg(['page'=>'policy_sales','status'=>''],admin_url('admin.php')).'&view_agency='.$a->id); ?>" class="mj-b mj-sec">📊 View Sales</a>
                                        <button type="button" class="mj-b mj-sec tcomm" data-id="<?php echo $a->id; ?>">💰 Commissions</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <!-- Edit row -->
                        <tr id="age-<?php echo $a->id; ?>" style="display:none" class="mja-edit-row">
                            <td colspan="5">
                                <form method="post">
                                    <?php wp_nonce_field('maljani_agency_nonce'); ?>
                                    <input type="hidden" name="maljani_agency_action" value="update_agency">
                                    <input type="hidden" name="agency_id" value="<?php echo esc_attr($a->id); ?>">
                                    <div class="mja-grid" style="margin-bottom:12px">
                                        <div><label>Agency Name</label><input type="text" name="name" value="<?php echo esc_attr($a->name ?? $a->agency_name ?? ''); ?>"></div>
                                        <div><label>Contact Name</label><input type="text" name="contact_name" value="<?php echo esc_attr($a->contact_name ?? ''); ?>"></div>
                                        <div><label>Contact Email</label><input type="email" name="contact_email" value="<?php echo esc_attr($a->contact_email ?? ''); ?>"></div>
                                        <div><label>Phone</label><input type="text" name="contact_phone" value="<?php echo esc_attr($a->contact_phone ?? ''); ?>"></div>
                                        <div><label>Commission Rate (%)</label><input type="number" name="commission_rate" value="<?php echo esc_attr($a->commission_rate ?? $a->commission_percent ?? 0); ?>" step="0.01"></div>
                                        <div><label>Notes</label><textarea name="notes"><?php echo esc_textarea($a->notes ?? ''); ?></textarea></div>
                                    </div>
                                    <button type="submit" class="mj-b mj-pri">💾 Save</button>
                                    <button type="button" class="mj-b mj-sec cancel-ag" data-id="<?php echo $a->id; ?>">✕ Cancel</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Commission mini-view row -->
                        <tr id="agc-<?php echo $a->id; ?>" style="display:none">
                            <td colspan="5" style="background:#f0f7ff;padding:16px">
                                <?php
                                $comms = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM $sales_tbl WHERE agency_id=%d AND agent_commission_amount>0 ORDER BY created_at DESC LIMIT 10",
                                    $a->id
                                ));
                                $status_colours = ['unpaid'=>['#fef9c3','#713f12'],'paid'=>['#d1fae5','#065f46'],'received'=>['#dbeafe','#1e40af'],'disputed'=>['#fee2e2','#991b1b']];
                                ?>
                                <h4 style="margin:0 0 12px;color:#1e40af">💰 Commission Ledger — <?php echo esc_html($a->name ?? $a->agency_name ?? ''); ?></h4>
                                <?php if (empty($comms)): ?>
                                    <p style="color:#64748b">No agency sales with commissions yet.</p>
                                <?php else: ?>
                                <table class="comm-view-tbl">
                                    <thead><tr><th>Policy #</th><th>Client</th><th>Premium</th><th>Commission</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($comms as $c):
                                        $cs  = $c->agent_commission_status ?? 'unpaid';
                                        [$bg,$col] = $status_colours[$cs] ?? ['#f1f5f9','#475569'];
                                    ?>
                                    <tr>
                                        <td><code><?php echo esc_html($c->policy_number); ?></code></td>
                                        <td><?php echo esc_html($c->insured_names); ?></td>
                                        <td>$<?php echo number_format(floatval($c->premium), 2); ?></td>
                                        <td><strong>$<?php echo number_format(floatval($c->agent_commission_amount), 2); ?></strong></td>
                                        <td><span class="badge" style="background:<?php echo $bg;?>;color:<?php echo $col;?>"><?php echo strtoupper($cs); ?></span></td>
                                        <td><?php echo esc_html(date('d M Y', strtotime($c->created_at))); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tedit-ag').forEach(b => b.addEventListener('click', function() {
                const id = this.dataset.id;
                const r = document.getElementById('age-' + id);
                r.style.display = r.style.display === 'none' ? '' : 'none';
                document.getElementById('agc-' + id).style.display = 'none';
            }));
            document.querySelectorAll('.tcomm').forEach(b => b.addEventListener('click', function(){
                const id = this.dataset.id;
                const r = document.getElementById('agc-' + id);
                r.style.display = r.style.display === 'none' ? '' : 'none';
                document.getElementById('age-' + id).style.display = 'none';
            }));
            document.querySelectorAll('.cancel-ag').forEach(b => b.addEventListener('click', function(){
                document.getElementById('age-' + this.dataset.id).style.display = 'none';
            }));
        });
        </script>
        <?php
    }
}
