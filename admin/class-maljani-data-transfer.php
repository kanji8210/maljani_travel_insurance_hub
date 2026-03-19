<?php
/**
 * Maljani Data Transfer Class
 * Handles Export and Import of plugin data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Data_Transfer {

    public function __construct() {
        add_action('admin_post_maljani_export_data', [$this, 'handle_export']);
        add_action('admin_post_maljani_import_data', [$this, 'handle_import']);
    }

    /**
     * Render the Export/Import page
     */
    public static function render_page() {
        ?>
        <div class="wrap mj-dt-wrap">
            <h1>📥 Maljani Data Transfer</h1>
            <p class="description">Backup, Move, or Sync your Maljani data across environments.</p>

            <div class="mj-dt-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                
                <!-- Export Card -->
                <div class="card">
                    <h2>📤 Export All Data</h2>
                    <p>This will generate a JSON file containing all Maljani policies, insurers, sales, settings, and related records.</p>
                    <ul style="list-style: disc; margin-left: 20px; color: #64748b;">
                        <li>CPTs: Policies & Insurers</li>
                        <li>Taxonomies: Regions</li>
                        <li>Custom Tables: Sales, Payments, etc.</li>
                        <li>Settings: All Maljani options</li>
                    </ul>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('maljani_export_nonce', 'mj_nonce'); ?>
                        <input type="hidden" name="action" value="maljani_export_data">
                        <button type="submit" class="button button-primary button-hero">Export JSON Package</button>
                    </form>
                </div>

                <!-- Import Card -->
                <div class="card">
                    <h2>📥 Import Data</h2>
                    <p>Upload a Maljani JSON export file to restore or migrate data.</p>
                    <div class="notice notice-warning inline" style="margin-bottom: 15px;">
                        <p><strong>Warning:</strong> Importing data may create duplicates if the data already exists. It is recommended to perform a backup first.</p>
                    </div>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('maljani_import_nonce', 'mj_nonce'); ?>
                        <input type="hidden" name="action" value="maljani_import_data">
                        <div style="margin-bottom: 15px;">
                            <input type="file" name="maljani_import_file" accept=".json" required>
                        </div>
                        <button type="submit" class="button button-secondary button-hero">Upload & Import</button>
                    </form>
                </div>

            </div>
        </div>

        <style>
            .mj-dt-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 1000px; }
            .mj-dt-wrap .card { background: #fff; border: 1px solid #ccd0d4; padding: 24px; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 8px; }
            .mj-dt-wrap h2 { margin-top: 0; font-size: 1.5em; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
            .mj-dt-wrap .button-hero { padding: 10px 30px !important; height: auto !important; line-height: 1.5 !important; font-weight: 700 !important; }
        </style>
        <?php
    }

    /**
     * Handle Data Export
     */
    public function handle_export() {
        if (!current_user_can('manage_options')) wp_die('No access.');
        check_admin_referer('maljani_export_nonce', 'mj_nonce');

        global $wpdb;

        $export_data = [
            'version'   => MALJANI_VERSION,
            'export_at' => current_time('mysql'),
            'options'   => [],
            'taxonomies' => [],
            'posts'     => [],
            'tables'    => [],
            'users'     => []
        ];

        // 1. Export Options
        $options_to_export = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'maljani_%'");
        foreach ($options_to_export as $opt) {
            $export_data['options'][$opt->option_name] = maybe_unserialize($opt->option_value);
        }

        // 2. Export Taxonomies (policy_region)
        $regions = get_terms(['taxonomy' => 'policy_region', 'hide_empty' => false]);
        if (!is_wp_error($regions)) {
            foreach ($regions as $term) {
                $export_data['taxonomies']['policy_region'][] = [
                    'term_id' => $term->term_id,
                    'name'    => $term->name,
                    'slug'    => $term->slug,
                    'parent'  => $term->parent,
                ];
            }
        }

        // 3. Export CPTs (policy, insurer_profile)
        $post_types = ['policy', 'insurer_profile'];
        foreach ($post_types as $pt) {
            $posts = get_posts(['post_type' => $pt, 'numberposts' => -1, 'post_status' => 'any']);
            foreach ($posts as $p) {
                $meta = get_post_meta($p->ID);
                // Clean up meta (remove leading underscores from keys if needed, or keep for consistency)
                $export_data['posts'][] = [
                    'ID'         => $p->ID,
                    'post_type'  => $p->post_type,
                    'post_title' => $p->post_title,
                    'post_content' => $p->post_content,
                    'post_excerpt' => $p->post_excerpt,
                    'post_status'  => $p->post_status,
                    'post_name'    => $p->post_name,
                    'menu_order'   => $p->menu_order,
                    'meta'         => $meta
                ];

                // Collect users associated with these posts (if any)
                $this->collect_user_data($p->post_author, $export_data);
            }
        }

        // 4. Export Custom Tables
        $tables = [
            'policy_sale',
            'maljani_api_keys',
            'maljani_chat_conversations',
            'maljani_chat_messages',
            'maljani_chat_agents',
            'maljani_agencies',
            'maljani_clients',
            'maljani_payments',
            'maljani_documents',
            'maljani_audit_trail'
        ];

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
                $rows = $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
                $export_data['tables'][$table] = $rows;

                // Collect user IDs from relevant columns
                if ($table === 'policy_sale') foreach ($rows as $r) $this->collect_user_data($r['agent_id'] ?? null, $export_data);
                if ($table === 'maljani_agencies') foreach ($rows as $r) $this->collect_user_data($r['user_id'] ?? null, $export_data);
            }
        }

        // Trigger Download
        $filename = 'maljani-export-' . date('Y-m-d_H-i') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Collect minimal user data for export (mapping)
     */
    private function collect_user_data($user_id, &$export_data) {
        if (!$user_id) return;
        if (isset($export_data['users'][$user_id])) return;

        $user = get_userdata($user_id);
        if ($user) {
            $export_data['users'][$user_id] = [
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
                'roles'      => $user->roles
            ];
        }
    }

    /**
     * Handle Data Import
     */
    public function handle_import() {
        if (!current_user_can('manage_options')) wp_die('No access.');
        check_admin_referer('maljani_import_nonce', 'mj_nonce');

        if (!isset($_FILES['maljani_import_file']) || $_FILES['maljani_import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die('Error uploading file.');
        }

        $file_path = $_FILES['maljani_import_file']['tmp_name'];
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);

        if (!$data || !isset($data['version'])) {
            wp_die('Invalid Maljani export package.');
        }

        global $wpdb;
        $id_maps = [
            'users' => [],
            'terms' => [],
            'posts' => []
        ];

        // 1. Process Users
        if (!empty($data['users'])) {
            foreach ($data['users'] as $old_id => $u) {
                $user = get_user_by('email', $u['user_email']);
                if ($user) {
                    $id_maps['users'][$old_id] = $user->ID;
                } else {
                    // Create minimal user or skip? For safety, we skip or use admin if not critical.
                    // But for agencies, we might need to create them.
                    $new_user_id = wp_create_user($u['user_login'], wp_generate_password(), $u['user_email']);
                    if (!is_wp_error($new_user_id)) {
                        $user_obj = new WP_User($new_user_id);
                        foreach ($u['roles'] as $role) $user_obj->add_role($role);
                        $id_maps['users'][$old_id] = $new_user_id;
                    }
                }
            }
        }

        // 2. Process Options
        if (!empty($data['options'])) {
            foreach ($data['options'] as $key => $val) {
                update_option($key, $val);
            }
        }

        // 3. Process Taxonomies
        if (!empty($data['taxonomies']['policy_region'])) {
            foreach ($data['taxonomies']['policy_region'] as $term) {
                $existing = get_term_by('slug', $term['slug'], 'policy_region');
                if ($existing) {
                    $id_maps['terms'][$term['term_id']] = $existing->term_id;
                } else {
                    $new_term = wp_insert_term($term['name'], 'policy_region', ['slug' => $term['slug']]);
                    if (!is_wp_error($new_term)) {
                        $id_maps['terms'][$term['term_id']] = $new_term['term_id'];
                    }
                }
            }
        }

        // 4. Process Posts (Insurers first, then Policies)
        // Sort posts so insurer_profile comes first
        usort($data['posts'], function($a, $b) {
            if ($a['post_type'] === $b['post_type']) return 0;
            return ($a['post_type'] === 'insurer_profile') ? -1 : 1;
        });

        foreach ($data['posts'] as $p_data) {
            $post_arr = [
                'post_type'    => $p_data['post_type'],
                'post_title'   => $p_data['post_title'],
                'post_content' => $p_data['post_content'],
                'post_excerpt' => $p_data['post_excerpt'],
                'post_status'  => $p_data['post_status'],
                'post_name'    => $p_data['post_name'],
                'menu_order'   => $p_data['menu_order'],
            ];
            
            // Check for existing by name/slug to avoid duplicates if re-importing
            $existing_post = get_page_by_path($p_data['post_name'], OBJECT, $p_data['post_type']);
            if ($existing_post) {
                $new_post_id = $existing_post->ID;
            } else {
                $new_post_id = wp_insert_post($post_arr);
            }

            if ($new_post_id && !is_wp_error($new_post_id)) {
                $id_maps['posts'][$p_data['ID']] = $new_post_id;
                
                // Import Meta
                foreach ($p_data['meta'] as $m_key => $m_vals) {
                    foreach ($m_vals as $m_val) {
                        $m_val = maybe_unserialize($m_val);
                        
                        // Remap IDs in meta
                        if ($m_key === '_policy_insurer') {
                            $m_val = $id_maps['posts'][$m_val] ?? $m_val;
                        }
                        if ($m_key === '_policy_region') {
                            $m_val = $id_maps['terms'][$m_val] ?? $m_val;
                        }
                        
                        update_post_meta($new_post_id, $m_key, $m_val);
                    }
                }
            }
        }

        // 5. Process Custom Tables (Ordered by dependency)
        $table_order = [
            'maljani_api_keys',
            'maljani_chat_conversations',
            'maljani_chat_messages',
            'maljani_chat_agents',
            'maljani_agencies',
            'maljani_clients',
            'policy_sale',
            'maljani_payments',
            'maljani_documents',
            'maljani_audit_trail'
        ];

        foreach ($table_order as $table_key) {
            if (empty($data['tables'][$table_key])) continue;
            
            $rows = $data['tables'][$table_key];
            $table_name = $wpdb->prefix . $table_key;
            
            foreach ($rows as $row) {
                $old_id = $row['id'];
                unset($row['id']); // Let target DB auto-generate

                // Remap IDs
                if (isset($row['policy_id']))   $row['policy_id'] = $id_maps['posts'][$row['policy_id']] ?? $row['policy_id'];
                if (isset($row['agent_id']))    $row['agent_id']  = $id_maps['users'][$row['agent_id']]   ?? $row['agent_id'];
                if (isset($row['user_id']))     $row['user_id']   = $id_maps['users'][$row['user_id']]    ?? $row['user_id'];
                
                // Mappings for specific tables
                if (isset($row['agency_id']))   $row['agency_id'] = $id_maps['agencies'][$row['agency_id']] ?? $row['agency_id'];
                if (isset($row['client_id']))   $row['client_id'] = $id_maps['clients'][$row['client_id']]   ?? $row['client_id'];
                
                // Audit Trail entity mapping
                if ($table_key === 'maljani_audit_trail') {
                    $entity_type = $row['entity_type'] ?? '';
                    if ($entity_type === 'policy') $row['entity_id'] = $id_maps['posts'][$row['entity_id']] ?? $row['entity_id'];
                    if ($entity_type === 'agency') $row['entity_id'] = $id_maps['agencies'][$row['entity_id']] ?? $row['entity_id'];
                    if ($entity_type === 'client') $row['entity_id'] = $id_maps['clients'][$row['entity_id']] ?? $row['entity_id'];
                }

                $wpdb->insert($table_name, $row);
                $new_id = $wpdb->insert_id;
                
                // Store maps for newly created table rows
                if ($table_key === 'maljani_agencies') $id_maps['agencies'][$old_id] = $new_id;
                if ($table_key === 'maljani_clients')  $id_maps['clients'][$old_id]  = $new_id;
            }
        }

        set_transient('maljani_import_result', 'Data imported successfully!', 30);
        wp_redirect(admin_url('admin.php?page=maljani_data_transfer&import_status=success'));
        exit;
    }
}
