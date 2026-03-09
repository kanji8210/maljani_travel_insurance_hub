<?php

class Maljani_Roles_Admin {

    /**
     * Every role the plugin needs, keyed by slug.
     * Each entry contains: label, description, caps (capability => true).
     */
    private static function get_defined_roles() {
        return [
            'maljani_super_admin' => [
                'label'       => 'Maljani Super Admin',
                'description' => 'Full system control. Can manage roles, agencies, payments, and all policies.',
                'caps'        => [
                    'read'                     => true,
                    'edit_maljani_policies'    => true,
                    'manage_maljani_agencies'  => true,
                    'manage_maljani_payments'  => true,
                    'activate_maljani_policies'=> true,
                    'manage_maljani_roles'     => true,
                    'manage_options'           => true,
                ],
            ],
            'maljani_admin' => [
                'label'       => 'Maljani Admin',
                'description' => 'Approve policies, upload documents, oversee agencies and payments.',
                'caps'        => [
                    'read'                     => true,
                    'edit_maljani_policies'    => true,
                    'manage_maljani_agencies'  => true,
                    'manage_maljani_payments'  => true,
                    'activate_maljani_policies'=> true,
                ],
            ],
            'maljani_editor' => [
                'label'       => 'Maljani Editor',
                'description' => 'Review agency submissions and forward to insurer.',
                'caps'        => [
                    'read'                  => true,
                    'edit_maljani_policies' => true,
                ],
            ],
            'agent' => [
                'label'       => 'Agency (Agent)',
                'description' => 'Travel agencies. Submit policies, manage clients, track commissions.',
                'caps'        => [
                    'read'                      => true,
                    'maljani_agency_dashboard'  => true,
                ],
            ],
            'insurer' => [
                'label'       => 'Insurer',
                'description' => 'Insurance companies. Review and approve submitted policies.',
                'caps'        => [
                    'read'                     => true,
                    'review_maljani_policies'  => true,
                ],
            ],
            'insured' => [
                'label'       => 'Client (Insured)',
                'description' => 'Policy holders. View their own policies and download documents.',
                'caps'        => [
                    'read'                     => true,
                    'maljani_client_dashboard' => true,
                ],
            ],
        ];
    }

    public static function render_page() {
        if (!current_user_can('manage_maljani_roles') && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $defined_roles = self::get_defined_roles();
        $messages      = [];

        // ── Handle: Create missing role ────────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['maljani_create_role']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'maljani_create_role')
        ) {
            $slug = sanitize_key($_POST['create_role_slug']);
            if (isset($defined_roles[$slug]) && !get_role($slug)) {
                $def    = $defined_roles[$slug];
                $result = add_role($slug, $def['label'], $def['caps']);
                if ($result) {
                    $messages[] = ['type' => 'success', 'text' => "Role <strong>{$def['label']}</strong> created successfully."];
                } else {
                    $messages[] = ['type' => 'error', 'text' => "Failed to create role <strong>{$def['label']}</strong>."];
                }
            }
        }

        // ── Handle: Delete a non-essential custom role ───────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['maljani_delete_role']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'maljani_delete_role')
        ) {
            $slug = sanitize_key($_POST['delete_role_slug']);
            if (isset($defined_roles[$slug])) {
                remove_role($slug);
                $messages[] = ['type' => 'success', 'text' => "Role <strong>{$defined_roles[$slug]['label']}</strong> removed. Re-activate the plugin or click \"Register\" to restore it."];
            }
        }

        // ── Handle: Assign role to user ───────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['maljani_role_action']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'maljani_assign_role')
        ) {
            $user_id  = intval($_POST['user_id']);
            $new_role = sanitize_text_field($_POST['role']);
            $user     = new WP_User($user_id);
            if ($user && $user->exists()) {
                foreach (array_keys($defined_roles) as $slug) {
                    $user->remove_role($slug);
                }
                if (!empty($new_role)) {
                    $user->add_role($new_role);
                }
                $messages[] = ['type' => 'success', 'text' => 'User role updated successfully.'];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manage Maljani Roles</h1>';
        echo '<hr class="wp-header-end">';

        // Messages
        foreach ($messages as $msg) {
            echo '<div class="notice notice-' . esc_attr($msg['type']) . ' is-dismissible"><p>' . wp_kses_post($msg['text']) . '</p></div>';
        }

        // ── SECTION 1: Role Status Overview ─────────────────────────────────
        echo '<h2>Registered Roles Overview</h2>';
        echo '<p>These are all the roles required by the Maljani CRM plugin, along with their current registration status.</p>';
        echo '<style>
            .maljani-roles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin: 16px 0 30px; }
            .maljani-role-card { border: 1px solid #ddd; border-radius: 6px; background: #fff; padding: 16px; position:relative; }
            .maljani-role-card h3 { margin: 0 0 6px; font-size: 15px; }
            .maljani-role-card .role-desc { color: #666; font-size: 12px; margin-bottom: 12px; }
            .maljani-role-card .role-caps { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px; }
            .maljani-role-card .cap-badge { font-size: 10px; background: #f0f0f1; padding: 2px 7px; border-radius: 10px; color: #444; }
            .maljani-role-card .role-footer { display:flex; justify-content:space-between; align-items:center; }
            .maljani-role-card .user-count { font-size: 12px; color: #555; }
            .status-registered { border-top: 4px solid #00a32a; }
            .status-missing    { border-top: 4px solid #d63638; }
            .status-badge { position:absolute; top:12px; right:12px; font-size:11px; font-weight:600; padding:3px 9px; border-radius:10px; }
            .status-badge.ok  { background:#d1f5e0; color:#007a1f; }
            .status-badge.bad { background:#fde5e5; color:#b32d2e; }
        </style>';

        echo '<div class="maljani-roles-grid">';
        foreach ($defined_roles as $slug => $def) {
            $registered = get_role($slug);
            $user_count = $registered ? count(get_users(['role' => $slug, 'fields' => 'ID'])) : 0;
            $card_class = $registered ? 'status-registered' : 'status-missing';

            echo '<div class="maljani-role-card ' . $card_class . '">';
            echo '<span class="status-badge ' . ($registered ? 'ok' : 'bad') . '">' . ($registered ? '✓ Registered' : '✗ Missing') . '</span>';
            echo '<h3>' . esc_html($def['label']) . '</h3>';
            echo '<p class="role-desc">' . esc_html($def['description']) . '</p>';

            echo '<div class="role-caps">';
            foreach (array_keys($def['caps']) as $cap) {
                echo '<span class="cap-badge">' . esc_html($cap) . '</span>';
            }
            echo '</div>';

            echo '<div class="role-footer">';
            if ($registered) {
                echo '<span class="user-count">👥 ' . $user_count . ' user' . ($user_count !== 1 ? 's' : '') . '</span>';
                // Delete button (only non-essential / custom roles)
                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Remove this role? Users with it will become roleless within the plugin.\');">';
                wp_nonce_field('maljani_delete_role');
                echo '<input type="hidden" name="maljani_delete_role" value="1">';
                echo '<input type="hidden" name="delete_role_slug" value="' . esc_attr($slug) . '">';
                echo '<button type="submit" class="button button-small button-link-delete">Remove Role</button>';
                echo '</form>';
            } else {
                echo '<span class="user-count" style="color:#d63638; font-weight:600;">⚠ Not registered</span>';
                // Create button
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field('maljani_create_role');
                echo '<input type="hidden" name="maljani_create_role" value="1">';
                echo '<input type="hidden" name="create_role_slug" value="' . esc_attr($slug) . '">';
                echo '<button type="submit" class="button button-primary button-small">Register Now</button>';
                echo '</form>';
            }
            echo '</div>'; // role-footer
            echo '</div>'; // maljani-role-card
        }
        echo '</div>'; // maljani-roles-grid

        // ── SECTION 2: Assign Roles to Users ────────────────────────────────
        echo '<h2 style="margin-top:30px;">Assign Roles to Users</h2>';
        echo '<p>Use the dropdowns below to assign or change the Maljani CRM role for each user.</p>';

        // search/filter bar
        $search   = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_r = isset($_GET['filter_role']) ? sanitize_text_field($_GET['filter_role']) : '';

        echo '<form method="get" style="display:flex; gap:8px; margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="maljani_roles_admin">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search by name or email..." class="regular-text">';
        echo '<select name="filter_role">';
        echo '<option value="">All Roles</option>';
        foreach ($defined_roles as $slug => $def) {
            $sel = ($filter_r === $slug) ? ' selected' : '';
            echo '<option value="' . esc_attr($slug) . '"' . $sel . '>' . esc_html($def['label']) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button">Filter</button>';
        echo '</form>';

        // query users
        $query_args = ['number' => 200];
        if (!empty($search)) {
            $query_args['search']         = '*' . $search . '*';
            $query_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }
        if (!empty($filter_r)) {
            $query_args['role'] = $filter_r;
        }
        $users = get_users($query_args);

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>User</th><th>Email</th><th>Current WordPress Roles</th><th style="width:260px;">Assign CRM Role</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($users)) {
            echo '<tr><td colspan="4">No users found.</td></tr>';
        }

        foreach ($users as $u) {
            // Don't allow editing your own account or the WP admin row if it's yourself
            if ($u->ID === get_current_user_id() && in_array('administrator', (array)$u->roles)) {
                continue;
            }

            // Highlight if user has any defined Maljani role
            $maljani_roles     = array_intersect(array_keys($defined_roles), (array)$u->roles);
            $current_maljani   = !empty($maljani_roles) ? reset($maljani_roles) : '';
            $row_style         = !empty($maljani_roles) ? ' style="background:#f0f8ff;"' : '';

            echo '<tr' . $row_style . '>';
            echo '<td><strong>' . esc_html($u->display_name) . '</strong><br><span style="color:#888;font-size:11px;">#' . $u->ID . '</span></td>';
            echo '<td>' . esc_html($u->user_email) . '</td>';
            echo '<td>' . esc_html(implode(', ', $u->roles)) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:flex; gap:5px; align-items:center;">';
            wp_nonce_field('maljani_assign_role');
            echo '<input type="hidden" name="maljani_role_action" value="assign_role">';
            echo '<input type="hidden" name="user_id" value="' . esc_attr($u->ID) . '">';
            echo '<select name="role" style="flex:1;">';
            echo '<option value="">(None / Remove)</option>';
            foreach ($defined_roles as $slug => $def) {
                $sel = ($current_maljani === $slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($slug) . '"' . $sel . '>' . esc_html($def['label']) . '</option>';
            }
            echo '</select>';
            echo ' <button type="submit" class="button button-small">Save</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>'; // wrap
    }
}
