<?php
class Maljani_Policy_Sales_Admin {
    public function __construct() {
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('wp_ajax_maljani_get_policy_premium', [$this, 'ajax_get_policy_premium']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_style']);
    }

    public function enqueue_style() {
        try {
            wp_enqueue_style('maljani-admin-style', plugin_dir_url(__FILE__) . 'css/maljani-admin.css', [], null);
            wp_enqueue_script('maljani-policy-sales', plugin_dir_url(__FILE__) . 'js/maljani-policy-sales.js', ['jquery'], defined('MALJANI_VERSION') ? MALJANI_VERSION : null, true);
            wp_localize_script('maljani-policy-sales', 'maljani_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('maljani_premium_nonce'),
            ]);
        } catch (Exception $e) { error_log('Maljani admin style error: ' . $e->getMessage()); }
    }

    public function handle_actions() {
        // Archive
        if (isset($_GET['action'], $_GET['sale_id']) && $_GET['action'] === 'archive_policy' && current_user_can('manage_options')) {
            check_admin_referer('maljani_archive_' . intval($_GET['sale_id']));
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'policy_sale', ['policy_status' => 'archived'], ['id' => intval($_GET['sale_id'])]);
            wp_redirect(remove_query_arg(['action', 'sale_id', '_wpnonce'])); exit;
        }
        // Quick status update
        if (isset($_POST['maljani_quick_status'], $_POST['sale_id']) && current_user_can('manage_options')) {
            check_admin_referer('maljani_quick_status_' . intval($_POST['sale_id']));
            global $wpdb;
            $up = [];
            if (isset($_POST['policy_status']))           $up['policy_status']          = sanitize_text_field($_POST['policy_status']);
            if (isset($_POST['payment_status']))          $up['payment_status']         = sanitize_text_field($_POST['payment_status']);
            if (isset($_POST['agent_commission_status'])) $up['agent_commission_status']= sanitize_text_field($_POST['agent_commission_status']);
            if ($up) $wpdb->update($wpdb->prefix . 'policy_sale', $up, ['id' => intval($_POST['sale_id'])]);
            wp_redirect(remove_query_arg(['_wpnonce'])); exit;
        }
        // Full edit
        if (isset($_POST['update_policy_sale'], $_POST['sale_id']) && check_admin_referer('update_policy_sale_' . intval($_POST['sale_id']))) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'policy_sale', [
                'policy_id'       => intval($_POST['policy_id']),
                'policy_number'   => sanitize_text_field($_POST['policy_number']),
                'insured_names'   => sanitize_text_field($_POST['insured_names']),
                'insured_email'   => sanitize_email($_POST['insured_email']),
                'insured_phone'   => sanitize_text_field($_POST['insured_phone']),
                'passport_number' => sanitize_text_field($_POST['passport_number'] ?? ''),
                'departure'       => sanitize_text_field($_POST['departure']),
                'return'          => sanitize_text_field($_POST['return']),
                'premium'         => floatval($_POST['premium']),
                'agent_id'        => intval($_POST['agent_id']),
                'policy_status'   => sanitize_text_field($_POST['policy_status'] ?? 'unconfirmed'),
                'payment_status'  => sanitize_text_field($_POST['payment_status'] ?? 'pending'),
            ], ['id' => intval($_POST['sale_id'])]);
            echo '<div class="notice notice-success is-dismissible"><p>Sale updated.</p></div>';
        }
        // CSV export
        if (isset($_GET['maljani_export_sales']) && current_user_can('manage_options')) {
            check_admin_referer('maljani_export_sales');
            self::export_csv(); exit;
        }
    }

    private static function export_csv() {
        global $wpdb;
        $sales = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}policy_sale ORDER BY created_at DESC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="maljani-sales-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Policy Number','Client','Email','Phone','Passport','Policy','Departure','Return','Days','Premium','Service Fee','Net to Insurer','Agency Comm','Client Paid','Policy Status','Payment Status']);
        foreach ($sales as $s) {
            fputcsv($out, [$s->id,$s->policy_number,$s->insured_names,$s->insured_email,$s->insured_phone,
                $s->passport_number??'',get_the_title($s->policy_id),$s->departure,$s->return,$s->days,
                $s->premium,$s->service_fee_amount??0,$s->net_to_insurer??0,$s->agent_commission_amount??0,
                $s->amount_paid??0,$s->policy_status,$s->payment_status]);
        }
        fclose($out);
    }

    public function ajax_get_policy_premium() {
        check_ajax_referer('maljani_premium_nonce', 'security');
        $pid  = intval($_POST['policy_id'] ?? 0);
        $days = intval($_POST['days'] ?? 0);
        if (!$pid || !$days) { wp_send_json_error('Invalid'); return; }
        $p = get_post($pid);
        if (!$p || $p->post_type !== 'policy' || $p->post_status !== 'publish') { wp_send_json_error('Bad policy'); return; }
        $rows = get_post_meta($pid, '_policy_day_premiums', true);
        $premium = '';
        if (is_array($rows)) foreach ($rows as $r) if ($days >= intval($r['from']) && $days <= intval($r['to'])) { $premium = $r['premium']; break; }
        wp_send_json_success($premium);
    }

    private static function badge(string $val, string $type = 'policy'): string {
        $maps = [
            'policy'     => ['active'=>['#d1fae5','#065f46','✅'],'unconfirmed'=>['#fef9c3','#713f12','⏳'],'pending_review'=>['#dbeafe','#1e40af','🔍'],'archived'=>['#f1f5f9','#475569','📦'],'cancelled'=>['#fee2e2','#991b1b','❌']],
            'payment'    => ['paid'=>['#d1fae5','#065f46','💰'],'pending'=>['#fef9c3','#713f12','⏳'],'unconfirmed'=>['#e0e7ff','#3730a3','❓'],'failed'=>['#fee2e2','#991b1b','❌']],
            'commission' => ['unpaid'=>['#fef9c3','#713f12','⏳'],'paid'=>['#d1fae5','#065f46','✅'],'received'=>['#dbeafe','#1e40af','📬'],'disputed'=>['#fee2e2','#991b1b','⚠️']],
        ];
        [$bg,$col,$ic] = $maps[$type][$val] ?? ['#f1f5f9','#475569','?'];
        return "<span style='display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;background:{$bg};color:{$col};'>{$ic} " . strtoupper(str_replace('_',' ',$val)) . "</span>";
    }

    public static function render_sales_table() {
        global $wpdb;
        $tbl = $wpdb->prefix . 'policy_sale';

        $search = sanitize_text_field($_GET['s'] ?? '');
        $fst    = sanitize_text_field($_GET['status'] ?? '');
        $fpt    = sanitize_text_field($_GET['pay_status'] ?? '');
        $dfrom  = sanitize_text_field($_GET['date_from'] ?? '');
        $dto    = sanitize_text_field($_GET['date_to'] ?? '');
        $pp     = 20;
        $paged  = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($paged - 1) * $pp;

        $where = '1=1'; $p = [];
        if ($search) { $where .= " AND (insured_names LIKE %s OR insured_email LIKE %s OR policy_number LIKE %s)"; $p = array_merge($p,["%$search%","%$search%","%$search%"]); }
        if ($fst)   { $where .= " AND policy_status=%s"; $p[] = $fst; }
        if ($fpt)   { $where .= " AND payment_status=%s"; $p[] = $fpt; }
        if ($dfrom) { $where .= " AND DATE(created_at)>=%s"; $p[] = $dfrom; }
        if ($dto)   { $where .= " AND DATE(created_at)<=%s"; $p[] = $dto; }

        $total = $p ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE $where", ...$p)) : $wpdb->get_var("SELECT COUNT(*) FROM $tbl");
        $sales = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tbl WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d", ...array_merge($p,[$pp,$offset])));

        $today_c  = $wpdb->get_var("SELECT COUNT(*) FROM $tbl WHERE DATE(created_at)=CURDATE()");
        $month_c  = $wpdb->get_var("SELECT COUNT(*) FROM $tbl WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
        $tot_prem = $wpdb->get_var("SELECT SUM(premium) FROM $tbl WHERE policy_status!='archived'");
        $pending  = $wpdb->get_var("SELECT COUNT(*) FROM $tbl WHERE policy_status='unconfirmed'");

        $policies = get_posts(['post_type' => 'policy', 'numberposts' => -1]);
        $agents   = get_users(['role' => 'agent']);
        $base_url = admin_url('admin.php?page=policy_sales');
        $export_url = wp_nonce_url($base_url . '&maljani_export_sales=1', 'maljani_export_sales');
        ?>
<style>
.mjps{font-family:'Inter',sans-serif}
.mjps-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:16px 0}
.mjps-stat{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.mjps-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.mjps-val{font-size:26px;font-weight:800;color:#1e293b;line-height:1}
.mjps-lbl{font-size:12px;color:#64748b;margin-top:3px}
.mjps-filters{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px}
.mjps-filters label{font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px}
.mjps-filters input,.mjps-filters select{height:34px;border:1px solid #d1d5db;border-radius:6px;padding:0 10px;font-size:13px}
.mjps-tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
.mjps-tbl th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0}
.mjps-tbl td{padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
.mjps-tbl tr:last-child td{border-bottom:none}
.mjps-tbl tr:hover td{background:#fafafe}
.fin-row{background:#f0f4ff}
.fin-row td{padding:12px 16px}
.fin-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:12px}
.fin-grid .fi label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;display:block}
.fin-grid .fi span{font-size:15px;font-weight:700;color:#1e293b}
.edit-row{background:#f8fafc}
.edit-row td{padding:16px}
.edit-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.edit-grid input,.edit-grid select{width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.edit-grid label{font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px}
.mj-btns{display:flex;gap:5px;flex-wrap:wrap}
.mj-b{padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid;text-decoration:none;display:inline-block;line-height:1.6}
.mj-b-pri{background:#4f46e5;color:#fff;border-color:#4f46e5}
.mj-b-pri:hover{background:#4338ca;color:#fff}
.mj-b-red{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
.mj-b-sec{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.mjps-pag{display:flex;gap:6px;margin-top:14px}
.mjps-pag a,.mjps-pag span{padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;color:#475569}
.mjps-pag .cur{background:#4f46e5;color:#fff;border-color:#4f46e5}
</style>
<div class="mjps">
<div class="mjps-stats">
    <div class="mjps-stat"><div class="mjps-icon" style="background:#ede9fe">📋</div><div><div class="mjps-val"><?php echo intval($today_c);?></div><div class="mjps-lbl">Today's Sales</div></div></div>
    <div class="mjps-stat"><div class="mjps-icon" style="background:#d1fae5">📅</div><div><div class="mjps-val"><?php echo intval($month_c);?></div><div class="mjps-lbl">This Month</div></div></div>
    <div class="mjps-stat"><div class="mjps-icon" style="background:#fef9c3">⏳</div><div><div class="mjps-val"><?php echo intval($pending);?></div><div class="mjps-lbl">Pending Review</div></div></div>
    <div class="mjps-stat"><div class="mjps-icon" style="background:#dbeafe">💰</div><div><div class="mjps-val">$<?php echo number_format(floatval($tot_prem),0);?></div><div class="mjps-lbl">Total Premium</div></div></div>
</div>
<form method="get" class="mjps-filters">
    <input type="hidden" name="page" value="policy_sales">
    <div><label>Search</label><input type="text" name="s" value="<?php echo esc_attr($search);?>" placeholder="Name, email, #…" style="width:180px"></div>
    <div><label>Policy Status</label><select name="status"><option value="">All</option><?php foreach(['unconfirmed','pending_review','active','archived','cancelled'] as $s):?><option value="<?php echo $s;?>" <?php selected($fst,$s);?>><?php echo ucwords(str_replace('_',' ',$s));?></option><?php endforeach;?></select></div>
    <div><label>Payment</label><select name="pay_status"><option value="">All</option><?php foreach(['pending','paid','unconfirmed','failed'] as $s):?><option value="<?php echo $s;?>" <?php selected($fpt,$s);?>><?php echo ucfirst($s);?></option><?php endforeach;?></select></div>
    <div><label>From</label><input type="date" name="date_from" value="<?php echo esc_attr($dfrom);?>"></div>
    <div><label>To</label><input type="date" name="date_to" value="<?php echo esc_attr($dto);?>"></div>
    <div><button type="submit" class="mj-b mj-b-pri" style="padding:7px 14px;font-size:13px;margin-top:18px">🔍 Filter</button></div>
    <?php if($search||$fst||$fpt||$dfrom||$dto):?><div><a href="<?php echo esc_url($base_url);?>" class="mj-b mj-b-sec" style="padding:7px 14px;font-size:13px;margin-top:22px">✕ Clear</a></div><?php endif;?>
</form>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <p style="color:#64748b;font-size:13px;margin:0">Showing <b><?php echo count($sales);?></b> of <b><?php echo intval($total);?></b> records</p>
    <a href="<?php echo esc_url($export_url);?>" class="mj-b mj-b-sec" style="font-size:12px">📥 Export CSV</a>
</div>
<table class="mjps-tbl"><thead><tr>
    <th>ID</th><th>Client</th><th>Policy / #</th><th>Travel Dates</th><th>Premium</th><th>Status</th><th>Payment</th><th>Actions</th>
</tr></thead><tbody>
<?php if(empty($sales)):?><tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">No sales found.</td></tr><?php endif;?>
<?php foreach($sales as $s):
    $pt   = get_the_title($s->policy_id);
    $au   = get_user_by('ID',$s->agent_id);
    $an   = $au ? $au->display_name : ($s->agent_name ?? '—');
    $pst  = $s->policy_status ?: 'unconfirmed';
    $pay  = $s->payment_status ?: 'pending';
    $cst  = $s->agent_commission_status ?? 'unpaid';
    $arc  = wp_nonce_url(add_query_arg(['action'=>'archive_policy','sale_id'=>$s->id],$base_url),'maljani_archive_'.$s->id);
?>
<tr id="sv-<?php echo $s->id;?>" class="sale-vr">
    <td><b>#<?php echo esc_html($s->id);?></b><br><small style="color:#94a3b8"><?php echo esc_html(date('d M Y',strtotime($s->created_at)));?></small></td>
    <td><b><?php echo esc_html($s->insured_names);?></b><br><small><?php echo esc_html($s->insured_email);?></small><br><small style="color:#94a3b8"><?php echo esc_html($s->insured_phone);?></small></td>
    <td><b><?php echo esc_html($pt);?></b><br><code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px"><?php echo esc_html($s->policy_number);?></code></td>
    <td><?php echo esc_html($s->departure);?>&nbsp;→<br><?php echo esc_html($s->return);?><br><small><?php echo intval($s->days);?> days</small></td>
    <td style="font-weight:700">$<?php echo number_format(floatval($s->premium),2);?></td>
    <td><?php echo self::badge($pst,'policy');?><br><small style="color:#64748b"><?php echo esc_html($an);?></small></td>
    <td><?php echo self::badge($pay,'payment');?><?php if(floatval($s->agent_commission_amount??0)>0): echo '<br>'.self::badge($cst,'commission'); endif;?></td>
    <td><div class="mj-btns">
        <button type="button" class="mj-b mj-b-sec tfin" data-id="<?php echo $s->id;?>">💰 Fin</button>
        <button type="button" class="mj-b mj-b-pri tedit" data-id="<?php echo $s->id;?>">✏️ Edit</button>
        <a href="<?php echo esc_url($arc);?>" class="mj-b mj-b-red" onclick="return confirm('Archive?')">📦</a>
    </div></td>
</tr>
<!-- Financial row -->
<tr id="sf-<?php echo $s->id;?>" class="fin-row" style="display:none"><td colspan="8">
    <div class="fin-grid">
        <div class="fi"><label>Base Premium</label><span>$<?php echo number_format(floatval($s->premium),2);?></span></div>
        <div class="fi"><label>Service Fee</label><span>$<?php echo number_format(floatval($s->service_fee_amount??0),2);?></span></div>
        <div class="fi"><label>Client Paid</label><span>$<?php echo number_format(floatval($s->amount_paid??0),2);?></span></div>
        <div class="fi"><label>Net to Insurer</label><span>$<?php echo number_format(floatval($s->net_to_insurer??0),2);?></span></div>
        <div class="fi"><label>Agency Comm</label><span>$<?php echo number_format(floatval($s->agent_commission_amount??0),2);?></span></div>
    </div>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <?php wp_nonce_field('maljani_quick_status_'.$s->id);?>
        <input type="hidden" name="maljani_quick_status" value="1"><input type="hidden" name="sale_id" value="<?php echo esc_attr($s->id);?>">
        <div><label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block">Policy Status</label>
            <select name="policy_status"><?php foreach(['unconfirmed','pending_review','active','archived','cancelled'] as $ss):?><option value="<?php echo $ss;?>" <?php selected($pst,$ss);?>><?php echo ucwords(str_replace('_',' ',$ss));?></option><?php endforeach;?></select></div>
        <div><label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block">Payment</label>
            <select name="payment_status"><?php foreach(['pending','paid','unconfirmed','failed'] as $ss):?><option value="<?php echo $ss;?>" <?php selected($pay,$ss);?>><?php echo ucfirst($ss);?></option><?php endforeach;?></select></div>
        <?php if(floatval($s->agent_commission_amount??0)>0):?>
        <div><label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block">Commission</label>
            <select name="agent_commission_status"><?php foreach(['unpaid','paid','received','disputed'] as $ss):?><option value="<?php echo $ss;?>" <?php selected($cst,$ss);?>><?php echo ucfirst($ss);?></option><?php endforeach;?></select></div>
        <?php endif;?>
        <button type="submit" class="mj-b mj-b-pri" style="padding:7px 14px;font-size:13px">💾 Update</button>
    </form>
</td></tr>
<!-- Full edit row -->
<tr id="se-<?php echo $s->id;?>" class="edit-row" style="display:none"><td colspan="8">
    <form method="post">
        <?php wp_nonce_field('update_policy_sale_'.$s->id);?>
        <input type="hidden" name="sale_id" value="<?php echo esc_attr($s->id);?>">
        <div class="edit-grid">
            <div><label>Full Name</label><input type="text" name="insured_names" value="<?php echo esc_attr($s->insured_names);?>"></div>
            <div><label>Email</label><input type="email" name="insured_email" value="<?php echo esc_attr($s->insured_email);?>"></div>
            <div><label>Phone</label><input type="text" name="insured_phone" value="<?php echo esc_attr($s->insured_phone);?>"></div>
            <div><label>Passport</label><input type="text" name="passport_number" value="<?php echo esc_attr($s->passport_number??'');?>"></div>
            <div><label>Policy</label><select name="policy_id" class="policy-select" data-sale="<?php echo esc_attr($s->id);?>"><?php foreach($policies as $pl):?><option value="<?php echo $pl->ID;?>" <?php selected($s->policy_id,$pl->ID);?>><?php echo esc_html($pl->post_title);?></option><?php endforeach;?></select></div>
            <div><label>Policy Number</label><input type="text" name="policy_number" value="<?php echo esc_attr($s->policy_number);?>"></div>
            <div><label>Departure</label><input type="date" name="departure" class="date-dep" data-sale="<?php echo esc_attr($s->id);?>" value="<?php echo esc_attr($s->departure);?>"></div>
            <div><label>Return</label><input type="date" name="return" class="date-ret" data-sale="<?php echo esc_attr($s->id);?>" value="<?php echo esc_attr($s->return);?>"></div>
            <div><label>Premium (auto)</label><input type="text" name="premium" class="premium-field" data-sale="<?php echo esc_attr($s->id);?>" value="<?php echo esc_attr($s->premium);?>" readonly></div>
            <div><label>Agent</label><select name="agent_id"><option value="">— Direct —</option><?php foreach($agents as $ag):?><option value="<?php echo $ag->ID;?>" <?php selected($s->agent_id,$ag->ID);?>><?php echo esc_html($ag->display_name);?></option><?php endforeach;?></select></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" name="update_policy_sale" class="mj-b mj-b-pri" style="padding:7px 16px;font-size:13px">💾 Save</button>
            <button type="button" class="mj-b mj-b-sec cancel-edit" data-id="<?php echo $s->id;?>" style="padding:7px 16px;font-size:13px">✕ Cancel</button>
            <?php
            // Invoice / Receipt / Email buttons
            if (class_exists('Maljani_Invoice')) {
                $is_paid = $s->payment_status === 'confirmed';
                echo Maljani_Invoice::doc_buttons(intval($s->id), $is_paid, 'sales');
            }
            ?>
        </div>
    </form>
</td></tr>
<?php endforeach;?>
</tbody></table>
<?php
$tp = ceil($total/$pp);
if($tp>1){echo '<div class="mjps-pag">';for($i=1;$i<=$tp;$i++){$u=add_query_arg('paged',$i);if($i==$paged)echo "<span class='cur'>$i</span>";else echo "<a href='".esc_url($u)."'>$i</a>";}echo '</div>';}
?>
</div>
<?php if (class_exists('Maljani_Invoice')) Maljani_Invoice::print_email_js(); ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.tfin').forEach(b=>b.addEventListener('click',function(){
        const r=document.getElementById('sf-'+this.dataset.id);r.style.display=r.style.display==='none'?'':'none';
    }));
    document.querySelectorAll('.tedit').forEach(b=>b.addEventListener('click',function(){
        const id=this.dataset.id;
        const r=document.getElementById('se-'+id);r.style.display=r.style.display==='none'?'':'none';
        document.getElementById('sf-'+id).style.display='none';
    }));
    document.querySelectorAll('.cancel-edit').forEach(b=>b.addEventListener('click',function(){
        document.getElementById('se-'+this.dataset.id).style.display='none';
    }));
    function calcDays(s,e){if(!s||!e)return 0;const d=Math.ceil((new Date(e)-new Date(s))/86400000);return d>0?d:0;}
    function recalc(id){
        const pol=document.querySelector('.policy-select[data-sale="'+id+'"]');
        const dep=document.querySelector('.date-dep[data-sale="'+id+'"]');
        const ret=document.querySelector('.date-ret[data-sale="'+id+'"]');
        const pf=document.querySelector('.premium-field[data-sale="'+id+'"]');
        if(!pol||!dep||!ret||!pf)return;
        const days=calcDays(dep.value,ret.value);
        if(!pol.value||!days){pf.value='';return;}
        pf.value='Calc…';pf.style.background='#fff3cd';
        const fd=new FormData();fd.append('action','maljani_get_policy_premium');fd.append('policy_id',pol.value);fd.append('days',days);
        if(typeof maljani_ajax!=='undefined')fd.append('security',maljani_ajax.security);
        fetch(typeof maljani_ajax!=='undefined'?maljani_ajax.ajax_url:ajaxurl,{method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{pf.value=d.success&&d.data?d.data:'N/A';pf.style.background=d.success?'#d1fae5':'#fee2e2';setTimeout(()=>pf.style.background='',2000);})
            .catch(()=>{pf.value='Error';pf.style.background='#fee2e2';});
    }
    document.querySelectorAll('.policy-select,.date-dep,.date-ret').forEach(e=>e.addEventListener('change',function(){if(this.dataset.sale)recalc(this.dataset.sale);}));
});
</script>
<?php
    }
}
new Maljani_Policy_Sales_Admin();
