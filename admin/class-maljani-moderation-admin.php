<?php

class Maljani_Moderation_Admin {

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_sales = $wpdb->prefix . 'policy_sale';
        
        // Handle Quick Status Updates
        if (isset($_POST['mj_action']) && $_POST['mj_action'] === 'update_workflow' && check_admin_referer('mj_moderation_nonce')) {
            $sale_id = intval($_POST['sale_id']);
            $new_status = sanitize_text_field($_POST['workflow_status']);
            $wpdb->update($table_sales, ['workflow_status' => $new_status], ['id' => $sale_id]);
            echo '<div class="notice notice-success is-dismissible"><p>Workflow status updated.</p></div>';
        }

        // Handle Agency Commission Status Update
        if (isset($_POST['mj_action']) && $_POST['mj_action'] === 'update_comm_status' && check_admin_referer('mj_moderation_nonce')) {
            $sale_id = intval($_POST['sale_id']);
            $new_status = sanitize_text_field($_POST['comm_status']);
            $wpdb->update($table_sales, ['agent_commission_status' => $new_status], ['id' => $sale_id]);
            echo '<div class="notice notice-success is-dismissible"><p>Agency commission status updated.</p></div>';
        }

        // Handle Payment Confirmation
        if (isset($_POST['mj_action']) && $_POST['mj_action'] === 'confirm_payment' && check_admin_referer('mj_moderation_nonce')) {
            $sale_id = intval($_POST['sale_id']);
            $wpdb->update($table_sales, ['payment_status' => 'confirmed', 'workflow_status' => 'paid'], ['id' => $sale_id]);
            echo '<div class="notice notice-success is-dismissible"><p>Payment confirmed for Sale #' . $sale_id . '</p></div>';
        }

        // Get Summary Stats
        $stats = $wpdb->get_results("SELECT workflow_status, COUNT(*) as count, SUM(amount_paid) as revenue FROM $table_sales GROUP BY workflow_status");
        $stat_map = [];
        foreach ($stats as $s) { $stat_map[$s->workflow_status] = $s; }

        // Filter and Search
        $workflow_filter = isset($_GET['stage']) ? sanitize_text_field($_GET['stage']) : '';
        $query = "SELECT * FROM $table_sales";
        if ($workflow_filter) {
            $query .= $wpdb->prepare(" WHERE workflow_status = %s", $workflow_filter);
        }
        $query .= " ORDER BY created_at DESC LIMIT 50";
        $sales = $wpdb->get_results($query);

        ?>
        <div class="wrap maljani-moderation-wrap">
            <h1 class="wp-heading-inline">Sales Moderation & Pipeline</h1>
            <hr class="wp-header-end">

            <!-- Pipeline Summary -->
            <div class="mj-pipeline-summary">
                <?php 
                $stages = [
                    'draft' => ['label' => 'Untouched', 'icon' => '📄', 'color' => '#64748b'],
                    'pending_review' => ['label' => 'In Review', 'icon' => '🔍', 'color' => '#f59e0b'],
                    'paid' => ['label' => 'Paid', 'icon' => '💰', 'color' => '#10b981'],
                    'submitted_to_insurer' => ['label' => 'Sent to Insurer', 'icon' => '📩', 'color' => '#3b82f6'],
                    'active' => ['label' => 'Active', 'icon' => '✅', 'color' => '#8b5cf6'],
                ];
                foreach ($stages as $key => $stage) : 
                    $count = isset($stat_map[$key]) ? $stat_map[$key]->count : 0;
                    $is_active = $workflow_filter === $key;
                ?>
                    <a href="<?php echo admin_url('admin.php?page=maljani_moderation&stage=' . $key); ?>" class="mj-stage-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="mj-stage-icon" style="background: <?php echo $stage['color']; ?>20; color: <?php echo $stage['color']; ?>;">
                            <?php echo $stage['icon']; ?>
                        </div>
                        <div class="mj-stage-info">
                            <span class="mj-stage-label"><?php echo $stage['label']; ?></span>
                            <span class="mj-stage-count"><?php echo $count; ?> Sales</span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <a href="<?php echo admin_url('admin.php?page=maljani_moderation'); ?>" class="mj-stage-card <?php echo !$workflow_filter ? 'active' : ''; ?>">
                    <div class="mj-stage-icon" style="background: #eee;">📊</div>
                    <div class="mj-stage-info">
                        <span class="mj-stage-label">All Sales</span>
                    </div>
                </a>
            </div>

            <!-- Financial Insights -->
            <div class="mj-financial-grid">
                <?php
                $totals = $wpdb->get_row("SELECT SUM(premium) as total_premium, SUM(service_fee_amount) as total_fees, SUM(maljani_commission_amount) as total_comm, SUM(net_to_insurer) as total_net FROM $table_sales WHERE payment_status = 'confirmed'");
                ?>
                <div class="mj-stat-box">
                    <span class="label">Total Premiums Sold</span>
                    <span class="value">$<?php echo number_format($totals->total_premium, 2); ?></span>
                </div>
                <div class="mj-stat-box">
                    <span class="label">Net to Insurer (Total Owed)</span>
                    <span class="value">$<?php echo number_format($totals->total_net, 2); ?></span>
                </div>
                <div class="mj-stat-box">
                    <span class="label">Service Fees Earned</span>
                    <span class="value">$<?php echo number_format($totals->total_fees, 2); ?></span>
                </div>
                <div class="mj-stat-box highlight">
                    <span class="label">Maljani Net Revenue</span>
                    <span class="value">$<?php echo number_format($totals->total_fees + $totals->total_comm, 2); ?></span>
                </div>
            </div>

            <!-- Sales List -->
            <div class="mj-moderation-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th>Policy / Numbers</th>
                            <th>Insured Person</th>
                            <th>Financial Breakdown</th>
                            <th>Workflow Stage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)) : ?>
                            <tr><td colspan="6">No sales found in this stage.</td></tr>
                        <?php else : foreach ($sales as $sale) : 
                            $bg_color = '';
                            if ($sale->workflow_status === 'paid') $bg_color = 'background-color: rgba(16, 185, 129, 0.05);';
                        ?>
                            <tr style="<?php echo $bg_color; ?>">
                                <td><strong>#<?php echo $sale->id; ?></strong></td>
                                <td>
                                    <div class="mj-policy-info">
                                        <span class="mj-policy-name"><?php echo esc_html($sale->region); ?> Policy</span>
                                        <code><?php echo esc_html($sale->policy_number); ?></code>
                                        <br>
                                        <small><?php echo date('M d, Y', strtotime($sale->created_at)); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($sale->insured_names); ?></strong><br>
                                    <small><?php echo esc_html($sale->insured_email); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $comm_status   = $sale->agent_commission_status ?: 'unpaid';
                                    $comm_badges   = [
                                        'unpaid'   => ['label' => 'UNPAID',   'color' => '#f59e0b'],
                                        'paid'     => ['label' => 'PAID',     'color' => '#10b981'],
                                        'received' => ['label' => 'RECEIVED', 'color' => '#3b82f6'],
                                        'disputed' => ['label' => 'DISPUTED', 'color' => '#ef4444'],
                                    ];
                                    $badge = $comm_badges[$comm_status] ?? $comm_badges['unpaid'];
                                    ?>
                                    <div class="mj-financial-breakdown">
                                        <div class="mj-fb-row"><span>Premium (Base):</span> <strong>$<?php echo number_format($sale->premium, 2); ?></strong></div>
                                        <div class="mj-fb-row"><small>Net to Insurer:</small> <strong>$<?php echo number_format($sale->net_to_insurer, 2); ?></strong></div>
                                        <div class="mj-fb-row sub"><small>Aggregator Comm:</small> +$<?php echo number_format($sale->maljani_commission_amount, 2); ?></div>
                                        <?php if ($sale->service_fee_amount > 0): ?>
                                        <div class="mj-fb-row sub"><small>Service Fee:</small> +$<?php echo number_format($sale->service_fee_amount, 2); ?></div>
                                        <?php endif; ?>
                                        <div class="mj-fb-row sub"><small>Client Paid:</small> $<?php echo number_format($sale->amount_paid, 2); ?></div>
                                        <?php if ($sale->agent_commission_amount > 0): ?>
                                        <div class="mj-fb-row agency">
                                            <small>Agency Comm:</small>
                                            <strong>$<?php echo number_format($sale->agent_commission_amount, 2); ?></strong>
                                            <span class="comm-status" style="color:<?php echo $badge['color']; ?>">
                                                (<?php echo $badge['label']; ?>)
                                            </span>
                                        </div>
                                        <?php if ($comm_status === 'disputed' && $sale->agency_comm_disputed_note): ?>
                                            <div style="font-size:11px;color:#ef4444;background:#fee2e2;padding:4px 6px;border-radius:4px;margin-top:4px;">
                                                ⚠️ <?php echo esc_html($sale->agency_comm_disputed_note); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="post" style="display:flex; flex-direction: column; gap: 5px;">
                                        <?php wp_nonce_field('mj_moderation_nonce'); ?>
                                        <input type="hidden" name="mj_action" value="update_workflow">
                                        <input type="hidden" name="sale_id" value="<?php echo $sale->id; ?>">
                                        <select name="workflow_status" onchange="this.form.submit()" class="mj-mini-select">
                                            <?php foreach ($stages as $key => $stage) : ?>
                                                <option value="<?php echo $key; ?>" <?php selected($sale->workflow_status, $key); ?>>
                                                    <?php echo $stage['label']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="mj-badge status-<?php echo $sale->payment_status; ?>">
                                            Payment: <?php echo strtoupper($sale->payment_status); ?>
                                        </span>
                                    </form>
                                </td>
                                <td>
                                    <div class="mj-action-stack">
                                        <?php if ($sale->payment_status !== 'confirmed') : ?>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('mj_moderation_nonce'); ?>
                                                <input type="hidden" name="mj_action" value="confirm_payment">
                                                <input type="hidden" name="sale_id" value="<?php echo $sale->id; ?>">
                                                <button type="submit" class="button button-primary button-small">Confirm Paid</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($sale->agent_commission_amount > 0 && in_array($sale->agent_commission_status, ['unpaid'])): ?>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('mj_moderation_nonce'); ?>
                                                <input type="hidden" name="mj_action" value="update_comm_status">
                                                <input type="hidden" name="comm_status" value="paid">
                                                <input type="hidden" name="sale_id" value="<?php echo $sale->id; ?>">
                                                <button type="submit" class="button button-small" style="background:#10b981; color:white; border:none;">Mark Comm Paid</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($sale->agent_commission_status, ['disputed'])): ?>
                                            <span style="font-size:11px;color:#ef4444;font-weight:700;">⚠️ DISPUTED — check breakdown</span>
                                        <?php endif; ?>

                                        <a href="<?php echo admin_url('admin.php?page=maljani_policy_sales&sale_id=' . $sale->id); ?>" class="button button-small">Quick Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .maljani-moderation-wrap { margin-top: 20px; font-family: 'Inter', -apple-system, sans-serif; }
            .mj-pipeline-summary { display: flex; gap: 15px; margin-bottom: 30px; overflow-x: auto; padding: 10px 0; }
            .mj-stage-card { 
                background: white; border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; 
                gap: 15px; min-width: 180px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 2px solid transparent;
                text-decoration: none; color: inherit; transition: all 0.2s;
            }
            .mj-stage-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
            .mj-stage-card.active { border-color: #4f46e5; background: #f8faff; }
            .mj-stage-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
            .mj-stage-label { display: block; font-size: 13px; font-weight: 700; color: #64748b; }
            .mj-stage-count { font-size: 16px; font-weight: 800; color: #1e293b; }

            .mj-financial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
            .mj-stat-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .mj-stat-box .label { display: block; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
            .mj-stat-box .value { font-size: 24px; font-weight: 800; color: #1e293b; }
            .mj-stat-box.highlight { background: #4f46e5; color: white; }
            .mj-stat-box.highlight .label { color: rgba(255,255,255,0.8); }
            .mj-stat-box.highlight .value { color: white; }

            .mj-fb-row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; margin-bottom: 2px; }
            .mj-fb-row.sub { color: #10b981; font-weight: 600; }
            .mj-fb-row.agency { border-top: 1px solid #eee; margin-top: 5px; padding-top: 5px; color: #6366f1; }
            .comm-status { font-size: 9px; font-weight: 800; }
            .comm-status.status-paid { color: #10b981; }
            .comm-status.status-disputed { color: #ef4444; }
            .mj-badge { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-top: 5px; text-align: center; }
            .status-confirmed { background: #dcfce7; color: #166534; }
            .status-pending { background: #fef3c7; color: #92400e; }
            .mj-mini-select { font-size: 12px; padding: 0 5px; height: 26px; border-radius: 5px; }
            .mj-action-stack { display: flex; flex-direction: column; gap: 5px; }
        </style>
        <?php
    }
}
