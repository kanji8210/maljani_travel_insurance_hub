<?php
/**
 * Maljani Plugin Diagnostics
 * A comprehensive health-check dashboard for the Maljani Travel Insurance plugin.
 */
class Maljani_Diagnostics {

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;

        // ── Gather All Data ──────────────────────────────────────────────────
        $checks = self::run_all_checks();

        $pass  = 0; $warn = 0; $fail = 0;
        foreach ( $checks as $section ) {
            foreach ( $section['items'] as $item ) {
                if     ( $item['status'] === 'ok'   ) $pass++;
                elseif ( $item['status'] === 'warn' ) $warn++;
                else                                   $fail++;
            }
        }
        $total       = $pass + $warn + $fail;
        $score       = $total > 0 ? round( ($pass / $total) * 100 ) : 0;
        $score_color = $score >= 80 ? '#00a32a' : ( $score >= 50 ? '#dba617' : '#d63638' );

        self::print_styles();
        ?>
        <div class="wrap">
        <h1 class="wp-heading-inline">🔍 Plugin Diagnostics</h1>
        <a href="<?php echo esc_url( add_query_arg('_diag_refresh', time()) ); ?>" class="page-title-action">↺ Refresh</a>
        <hr class="wp-header-end">

        <!-- Score bar -->
        <div class="diag-score-bar">
            <div class="diag-score-circle" style="--score:<?php echo $score; ?>;--color:<?php echo $score_color; ?>">
                <span class="score-num" style="color:<?php echo $score_color; ?>"><?php echo $score; ?>%</span>
                <span class="score-lab">Health Score</span>
            </div>
            <div class="diag-score-chips">
                <span class="chip ok">✓ <?php echo $pass; ?> Passed</span>
                <span class="chip warn">⚠ <?php echo $warn; ?> Warnings</span>
                <span class="chip fail">✗ <?php echo $fail; ?> Failed</span>
            </div>
            <div class="diag-score-meta">
                <p>Last checked: <strong><?php echo esc_html( current_time('M j, Y H:i:s') ); ?></strong></p>
                <p>WordPress: <strong><?php echo get_bloginfo('version'); ?></strong> &bull;
                   PHP: <strong><?php echo PHP_VERSION; ?></strong> &bull;
                   Plugin: <strong><?php echo defined('MALJANI_VERSION') ? MALJANI_VERSION : '1.0.0'; ?></strong></p>
            </div>
        </div>

        <!-- Sections -->
        <?php foreach ( $checks as $section_key => $section ): ?>
        <div class="diag-section">
            <h2><?php echo esc_html($section['label']); ?></h2>
            <table class="wp-list-table widefat fixed striped diag-table">
                <thead><tr>
                    <th style="width:30px"></th>
                    <th>Check</th>
                    <th>Result</th>
                    <th>Notes / Fix</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $section['items'] as $item ): ?>
                <tr class="diag-row diag-<?php echo esc_attr($item['status']); ?>">
                    <td class="diag-icon"><?php echo self::status_icon($item['status']); ?></td>
                    <td><strong><?php echo esc_html($item['label']); ?></strong></td>
                    <td><?php echo wp_kses_post($item['value']); ?></td>
                    <td class="diag-note"><?php echo wp_kses_post($item['note'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        </div>
        <?php
    }

    // ────────────────────────────────────────────────────────────────────────
    private static function run_all_checks() {
        return [
            'environment'  => self::check_environment(),
            'database'     => self::check_database(),
            'roles'        => self::check_roles(),
            'pages'        => self::check_pages(),
            'files'        => self::check_files(),
            'email'        => self::check_email(),
            'urls'         => self::check_urls(),
        ];
    }

    // ── 1. Environment ───────────────────────────────────────────────────────
    private static function check_environment() {
        $items = [];

        // PHP version
        $php = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $items[] = [
            'label'  => 'PHP Version',
            'status' => version_compare($php, '7.4', '>=') ? 'ok' : 'fail',
            'value'  => PHP_VERSION,
            'note'   => version_compare($php, '7.4', '>=') ? '' : 'PHP 7.4+ required. Please upgrade.',
        ];

        // WP version
        $wp = get_bloginfo('version');
        $items[] = [
            'label'  => 'WordPress Version',
            'status' => version_compare($wp, '5.8', '>=') ? 'ok' : 'warn',
            'value'  => $wp,
            'note'   => version_compare($wp, '5.8', '>=') ? '' : 'WordPress 5.8+ recommended.',
        ];

        // HTTPS
        $items[] = [
            'label'  => 'HTTPS',
            'status' => is_ssl() ? 'ok' : 'warn',
            'value'  => is_ssl() ? 'Enabled' : 'Not detected',
            'note'   => is_ssl() ? '' : 'HTTPS is recommended for secure data transmission.',
        ];

        // Memory
        $mem  = WP_MEMORY_LIMIT;
        $mem_mb = (int)$mem;
        $items[] = [
            'label'  => 'PHP Memory Limit',
            'status' => $mem_mb >= 128 ? 'ok' : 'warn',
            'value'  => $mem,
            'note'   => $mem_mb >= 128 ? '' : 'At least 128MB recommended. Update in php.ini or wp-config.php.',
        ];

        // Upload max filesize
        $umax = ini_get('upload_max_filesize');
        $items[] = [
            'label'  => 'Upload Max Filesize',
            'status' => (int)$umax >= 8 ? 'ok' : 'warn',
            'value'  => $umax,
            'note'   => (int)$umax >= 8 ? '' : '8MB+ recommended for policy document uploads.',
        ];

        // WP debug
        $items[] = [
            'label'  => 'WP_DEBUG',
            'status' => defined('WP_DEBUG') && WP_DEBUG ? 'warn' : 'ok',
            'value'  => (defined('WP_DEBUG') && WP_DEBUG) ? 'ON' : 'Off',
            'note'   => (defined('WP_DEBUG') && WP_DEBUG) ? 'Debug mode is enabled. Disable on production.' : '',
        ];

        // Permalink structure
        $perma = get_option('permalink_structure');
        $items[] = [
            'label'  => 'Permalink Structure',
            'status' => !empty($perma) ? 'ok' : 'warn',
            'value'  => !empty($perma) ? esc_html($perma) : 'Plain (default)',
            'note'   => !empty($perma) ? '' : 'A custom permalink structure is required for REST API routes. <a href="' . admin_url('options-permalink.php') . '">Fix →</a>',
        ];

        // REST API
        $rest_url  = rest_url('maljani/v1');
        $items[] = [
            'label'  => 'REST API Namespace',
            'status' => 'ok',
            'value'  => '<a href="' . esc_url($rest_url) . '" target="_blank">' . esc_html($rest_url) . '</a>',
            'note'   => '(Click to test in browser)',
        ];

        return ['label' => '⚙️ Environment & Configuration', 'items' => $items];
    }

    // ── 2. Database Tables ───────────────────────────────────────────────────
    private static function check_database() {
        global $wpdb;

        $required_tables = [
            'policy_sale'              => 'Policy sales / CRM',
            'maljani_agencies'         => 'Agencies',
            'maljani_clients'          => 'Clients',
            'maljani_payments'         => 'Payments',
            'maljani_documents'        => 'Documents',
            'maljani_audit_trail'      => 'Audit trail',
            'maljani_chat_conversations' => 'Chat conversations',
            'maljani_chat_messages'    => 'Chat messages',
            'maljani_chat_agents'      => 'Chat agents',
            'maljani_api_keys'         => 'API keys',
        ];

        $items = [];
        $fix_url = admin_url('admin.php?page=maljani_database_tools');

        foreach ( $required_tables as $table_key => $description ) {
            $table_name = $wpdb->prefix . $table_key;
            $exists     = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $rows       = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;

            $items[] = [
                'label'  => '<code>' . esc_html($table_name) . '</code>',
                'status' => $exists ? 'ok' : 'fail',
                'value'  => $exists ? number_format($rows) . ' rows' : '✗ Missing',
                'note'   => $exists
                    ? esc_html($description)
                    : 'Table missing! <a href="' . esc_url($fix_url) . '">Create via Database Tools →</a>',
            ];
        }

        return ['label' => '🗄️ Database Tables', 'items' => $items];
    }

    // ── 3. User Roles ────────────────────────────────────────────────────────
    private static function check_roles() {
        $required_roles = [
            'maljani_super_admin' => 'Maljani Super Admin',
            'maljani_admin'       => 'Maljani Admin',
            'maljani_editor'      => 'Maljani Editor',
            'agent'               => 'Agency (Agent)',
            'insurer'             => 'Insurer',
            'insured'             => 'Client (Insured)',
        ];

        $items = [];
        $roles_url = admin_url('admin.php?page=maljani_roles_admin');

        foreach ( $required_roles as $slug => $label ) {
            $role  = get_role($slug);
            $count = $role ? count(get_users(['role' => $slug, 'fields' => 'ID'])) : 0;

            $items[] = [
                'label'  => esc_html($label),
                'status' => $role ? 'ok' : 'fail',
                'value'  => $role ? $count . ' user' . ($count !== 1 ? 's' : '') . ' assigned' : '✗ Not registered',
                'note'   => $role ? '' : 'Role not registered. <a href="' . esc_url($roles_url) . '">Register via Manage Roles →</a>',
            ];
        }

        // Admin caps check
        $admin_role = get_role('administrator');
        $required_caps = ['edit_maljani_policies','manage_maljani_agencies','manage_maljani_payments','activate_maljani_policies','manage_maljani_roles'];
        $missing_caps = [];
        foreach ($required_caps as $cap) {
            if (!$admin_role || !isset($admin_role->capabilities[$cap])) $missing_caps[] = $cap;
        }
        $items[] = [
            'label'  => 'WP Administrator Capabilities',
            'status' => empty($missing_caps) ? 'ok' : 'warn',
            'value'  => empty($missing_caps) ? 'All custom caps granted' : count($missing_caps) . ' missing',
            'note'   => empty($missing_caps) ? '' : 'Missing: ' . implode(', ', array_map('esc_html', $missing_caps)) . '. Re-save your settings or reactivate the plugin.',
        ];

        return ['label' => '👥 User Roles & Capabilities', 'items' => $items];
    }

    // ── 4. Required Pages ────────────────────────────────────────────────────
    private static function check_pages() {
        $required = [
            'user_dashboard'      => ['My Dashboard',            '[maljani_user_dashboard]'],
            'agency_dashboard'    => ['Agency Dashboard',        '[maljani_crm_dashboard]'],
            'client_dashboard'    => ['My Policies',             '[maljani_client_dashboard]'],
            'insurer_dashboard'   => ['Insurer Portal',          '[maljani_insurer_dashboard]'],
            'policy_catalog'      => ['Travel Insurance Plans',  '[maljani_policy_catalog]'],
            'login'               => ['Portal Login',            '[maljani_login_form]'],
            'policy_verification' => ['Policy Verification',     '[maljani_verify_policy]'],
        ];

        global $wpdb;
        $items    = [];
        $pages_url = admin_url('admin.php?page=maljani_pages_admin');

        foreach ( $required as $slug => [$title, $sc] ) {
            $page_id = get_option('maljani_page_' . $slug);
            $page    = null;
            if ($page_id) {
                $page = get_post($page_id);
                if ($page && $page->post_status === 'trash') $page = null;
            }
            if (!$page) {
                // search by shortcode
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_status FROM {$wpdb->posts} WHERE post_status IN ('publish','draft') AND post_content LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($sc) . '%'
                ));
                if ($row) $page = get_post($row->ID);
            }

            $status = 'fail';
            $value  = '✗ Missing';
            $note   = 'Page not found. <a href="' . esc_url($pages_url) . '">Create via Page Management →</a>';

            if ($page) {
                $published = ($page->post_status === 'publish');
                $status = $published ? 'ok' : 'warn';
                $value  = '<a href="' . esc_url(get_permalink($page->ID)) . '" target="_blank">' . esc_html(get_the_title($page->ID)) . '</a> (#' . $page->ID . ')';
                $note   = $published ? '' : '⚠ Page exists but is not published. <a href="' . esc_url(get_edit_post_link($page->ID)) . '">Edit →</a>';
            }

            $items[] = [
                'label'  => esc_html($title),
                'status' => $status,
                'value'  => $value,
                'note'   => $note,
            ];
        }

        return ['label' => '📄 Required Plugin Pages', 'items' => $items];
    }

    // ── 5. Key Plugin Files ───────────────────────────────────────────────────
    private static function check_files() {
        $plugin_dir = WP_PLUGIN_DIR . '/maljani_travel_insurance_hub/';
        $required_files = [
            'maljani.php'                          => 'Main plugin file',
            'admin/class-maljani-admin-menu.php'   => 'Admin menu registration',
            'admin/class-maljani-roles-admin.php'  => 'Role management',
            'admin/class-maljani-pages-admin.php'  => 'Page management',
            'admin/class-maljani-users-admin.php'  => 'User management',
            'admin/class-maljani-crm-admin.php'    => 'CRM admin',
            'includes/class-maljani-crm.php'       => 'CRM REST API',
            'includes/class-maljani-workflow.php'  => 'Policy workflow engine',
            'includes/class-maljani-notifications.php' => 'Email notifications',
            'includes/class-maljani-live-chat.php' => 'Live chat backend',
        ];

        $items = [];
        foreach ( $required_files as $file => $desc ) {
            $exists = file_exists($plugin_dir . $file);
            $items[] = [
                'label'  => '<code>' . esc_html($file) . '</code>',
                'status' => $exists ? 'ok' : 'fail',
                'value'  => $exists ? 'Found' : '✗ Missing',
                'note'   => esc_html($desc) . ($exists ? '' : ' — File not found!'),
            ];
        }

        return ['label' => '📁 Plugin Files', 'items' => $items];
    }

    // ── 6. Email / Notifications ──────────────────────────────────────────────
    private static function check_email() {
        $admin_email = get_option('admin_email');
        $items       = [];

        $items[] = [
            'label'  => 'Admin Email',
            'status' => !empty($admin_email) ? 'ok' : 'fail',
            'value'  => esc_html($admin_email ?: '(not set)'),
            'note'   => '',
        ];

        $editors_count = count(get_users(['role' => 'maljani_editor', 'fields' => 'ID']));
        $items[] = [
            'label'  => 'Notification Recipients (Editors)',
            'status' => $editors_count > 0 ? 'ok' : 'warn',
            'value'  => $editors_count . ' editor' . ($editors_count !== 1 ? 's' : ''),
            'note'   => $editors_count > 0 ? '' : 'No Maljani Editors assigned. New policy submissions will not trigger notifications.',
        ];

        $admins_count = count(get_users(['role' => 'maljani_admin', 'fields' => 'ID']));
        $items[] = [
            'label'  => 'Notification Recipients (Admins)',
            'status' => $admins_count > 0 ? 'ok' : 'warn',
            'value'  => $admins_count . ' admin' . ($admins_count !== 1 ? 's' : ''),
            'note'   => $admins_count > 0 ? '' : 'No Maljani Admins assigned. Approval notifications will not be sent.',
        ];

        $mailer = ini_get('sendmail_path') ?: get_option('maljani_smtp_host', '');
        $items[] = [
            'label'  => 'Mail Configuration',
            'status' => 'ok',
            'value'  => !empty($mailer) ? esc_html($mailer) : 'PHP mail() (default)',
            'note'   => 'For reliable delivery consider an SMTP plugin like WP Mail SMTP.',
        ];

        return ['label' => '📧 Email & Notifications', 'items' => $items];
    }

    // ── 7. Important URLs ─────────────────────────────────────────────────────
    private static function check_urls() {
        $items = [];

        $items[] = [
            'label'  => 'Site URL',
            'status' => 'ok',
            'value'  => '<a href="' . esc_url(site_url()) . '" target="_blank">' . esc_html(site_url()) . '</a>',
            'note'   => '',
        ];
        $items[] = [
            'label'  => 'Admin URL',
            'status' => 'ok',
            'value'  => '<a href="' . esc_url(admin_url()) . '" target="_blank">' . esc_html(admin_url()) . '</a>',
            'note'   => '',
        ];
        $items[] = [
            'label'  => 'REST API Root',
            'status' => 'ok',
            'value'  => '<a href="' . esc_url(rest_url()) . '" target="_blank">' . esc_html(rest_url()) . '</a>',
            'note'   => '',
        ];
        $items[] = [
            'label'  => 'Uploads Directory',
            'status' => wp_is_writable(wp_upload_dir()['basedir']) ? 'ok' : 'fail',
            'value'  => esc_html(wp_upload_dir()['basedir']),
            'note'   => wp_is_writable(wp_upload_dir()['basedir']) ? 'Writable ✓' : '✗ Not writable — document uploads will fail.',
        ];

        return ['label' => '🌐 URLs & Paths', 'items' => $items];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private static function status_icon( $status ) {
        return match($status) {
            'ok'   => '<span class="diag-icon-ok">✓</span>',
            'warn' => '<span class="diag-icon-warn">⚠</span>',
            default=> '<span class="diag-icon-fail">✗</span>',
        };
    }

    private static function print_styles() {
        echo '<style>
        .diag-score-bar {display:flex;align-items:center;gap:20px;flex-wrap:wrap;background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:16px 0 24px;}
        .diag-score-circle {text-align:center;min-width:80px;}
        .score-num {display:block;font-size:32px;font-weight:800;line-height:1;}
        .score-lab {font-size:11px;color:#888;}
        .diag-score-chips {display:flex;gap:8px;flex-wrap:wrap;}
        .chip {font-size:12px;font-weight:600;padding:4px 12px;border-radius:12px;}
        .chip.ok   {background:#d1f5e0;color:#007a1f;}
        .chip.warn {background:#fef3cd;color:#996b00;}
        .chip.fail {background:#fde5e5;color:#b32d2e;}
        .diag-score-meta {font-size:12px;color:#555;line-height:1.6;}
        .diag-score-meta p {margin:0;}
        .diag-section {margin-bottom:24px;}
        .diag-section h2 {font-size:15px;margin-bottom:4px;}
        .diag-table {font-size:13px;}
        .diag-table th {font-weight:600;}
        .diag-row.diag-ok   {background:#f0fdf4 !important;}
        .diag-row.diag-warn {background:#fffbeb !important;}
        .diag-row.diag-fail {background:#fef2f2 !important;}
        .diag-icon {text-align:center;font-size:16px;}
        .diag-icon-ok   {color:#00a32a;font-weight:700;}
        .diag-icon-warn {color:#dba617;font-weight:700;}
        .diag-icon-fail {color:#d63638;font-weight:700;}
        .diag-note {color:#555;font-size:12px;}
        </style>';
    }
}
