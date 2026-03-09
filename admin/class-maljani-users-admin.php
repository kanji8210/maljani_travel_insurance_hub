<?php
/**
 * Maljani Users Admin
 * Provides per-role user management pages with add / edit / delete capabilities.
 */
class Maljani_Users_Admin {

    /**
     * Role group definitions — each "group" can span multiple role slugs.
     */
    public static function get_groups() {
        return [
            'maljani_team' => [
                'label'    => 'Maljani Team',
                'roles'    => ['maljani_super_admin', 'maljani_admin', 'maljani_editor'],
                'default'  => 'maljani_admin',
                'icon'     => '🛡️',
                'cap'      => 'manage_options',
                'role_options' => [
                    'maljani_super_admin' => 'Super Admin',
                    'maljani_admin'       => 'Admin',
                    'maljani_editor'      => 'Editor / Moderator',
                ],
            ],
            'agencies' => [
                'label'    => 'Agencies',
                'roles'    => ['agent'],
                'default'  => 'agent',
                'icon'     => '🏢',
                'cap'      => 'manage_options',
                'role_options' => [
                    'agent' => 'Agency (Agent)',
                ],
            ],
            'insurers' => [
                'label'    => 'Insurers',
                'roles'    => ['insurer'],
                'default'  => 'insurer',
                'icon'     => '🏦',
                'cap'      => 'manage_options',
                'role_options' => [
                    'insurer' => 'Insurer',
                ],
            ],
            'clients' => [
                'label'    => 'Clients',
                'roles'    => ['insured'],
                'default'  => 'insured',
                'icon'     => '👤',
                'cap'      => 'manage_options',
                'role_options' => [
                    'insured' => 'Client (Insured)',
                ],
            ],
        ];
    }

    /**
     * Render a group's admin page.
     * @param string $group_key  Key from get_groups()
     */
    public static function render_page( $group_key ) {
        $groups = self::get_groups();
        if ( ! isset( $groups[ $group_key ] ) ) {
            echo '<div class="wrap"><p>Invalid group.</p></div>';
            return;
        }

        $group    = $groups[ $group_key ];
        $messages = [];
        $show_add = false;

        // ── Handle: Create User ──────────────────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_create_user'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_create_user_' . $group_key )
        ) {
            $username  = sanitize_user( $_POST['new_username'] ?? '' );
            $email     = sanitize_email( $_POST['new_email'] ?? '' );
            $password  = $_POST['new_password'] ?? wp_generate_password();
            $role      = sanitize_key( $_POST['new_role'] ?? $group['default'] );
            $first     = sanitize_text_field( $_POST['new_first_name'] ?? '' );
            $last      = sanitize_text_field( $_POST['new_last_name'] ?? '' );
            $send_mail = isset( $_POST['send_user_notification'] );

            if ( empty( $username ) || empty( $email ) ) {
                $messages[] = ['type' => 'error', 'text' => 'Username and email are required.'];
                $show_add   = true;
            } elseif ( username_exists( $username ) ) {
                $messages[] = ['type' => 'error', 'text' => "Username <strong>{$username}</strong> is already taken."];
                $show_add   = true;
            } elseif ( email_exists( $email ) ) {
                $messages[] = ['type' => 'error', 'text' => "Email <strong>{$email}</strong> is already registered."];
                $show_add   = true;
            } else {
                $user_id = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $user_id ) ) {
                    $messages[] = ['type' => 'error', 'text' => $user_id->get_error_message()];
                    $show_add   = true;
                } else {
                    $user = new WP_User( $user_id );
                    // Remove default subscriber role then add group role
                    $user->set_role( $role );
                    if ( $first ) wp_update_user(['ID' => $user_id, 'first_name' => $first]);
                    if ( $last )  wp_update_user(['ID' => $user_id, 'last_name'  => $last]);
                    if ( $send_mail ) {
                        wp_new_user_notification( $user_id, null, 'user' );
                    }
                    $messages[] = ['type' => 'success', 'text' => "User <strong>{$username}</strong> created and assigned role <strong>" . esc_html( $group['role_options'][ $role ] ?? $role ) . "</strong>."];
                }
            }
        }

        // ── Handle: Delete User ──────────────────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_delete_user'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_delete_user' )
        ) {
            $del_id = intval( $_POST['delete_user_id'] );
            if ( $del_id && $del_id !== get_current_user_id() ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user( $del_id );
                $messages[] = ['type' => 'success', 'text' => 'User deleted.'];
            }
        }

        // ── Handle: Update User Role ─────────────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_update_user_role'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_update_user_role' )
        ) {
            $uid  = intval( $_POST['edit_user_id'] );
            $role = sanitize_key( $_POST['edit_role'] );
            if ( $uid && $role && isset( $group['role_options'][ $role ] ) ) {
                $user = new WP_User( $uid );
                if ( $user->exists() ) {
                    // Strip all group roles first
                    foreach ( $group['roles'] as $r ) $user->remove_role( $r );
                    $user->add_role( $role );
                    $messages[] = ['type' => 'success', 'text' => 'User role updated.'];
                }
            }
        }

        // ── Handle: show-add-form toggle ─────────────────────────────────────
        if ( isset( $_GET['add_user'] ) ) {
            $show_add = true;
        }

        // ── Fetch users ──────────────────────────────────────────────────────
        $query_args = [ 'role__in' => $group['roles'], 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC' ];
        $search     = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        if ( $search ) {
            $query_args['search']         = '*' . $search . '*';
            $query_args['search_columns'] = ['user_login','user_email','display_name'];
        }
        $users  = get_users( $query_args );
        $page_url = admin_url( 'admin.php?page=maljani_users_' . $group_key );

        // ── OUTPUT ───────────────────────────────────────────────────────────
        self::print_styles();
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html( $group['icon'] . ' ' . $group['label'] ) . '</h1>';
        echo ' <a href="' . esc_url( $page_url . '&add_user=1' ) . '" class="page-title-action">+ Add New User</a>';
        echo '<hr class="wp-header-end">';

        // Messages
        foreach ( $messages as $m ) {
            echo '<div class="notice notice-' . esc_attr($m['type']) . ' is-dismissible"><p>' . wp_kses_post($m['text']) . '</p></div>';
        }

        // ── Add User Form ────────────────────────────────────────────────────
        if ( $show_add ) {
            echo '<div class="maljani-card" style="max-width:600px; margin-bottom:24px;">';
            echo '<h2>Create New User</h2>';
            echo '<form method="post">';
            wp_nonce_field( 'maljani_create_user_' . $group_key );
            echo '<input type="hidden" name="maljani_create_user" value="1">';

            echo '<table class="form-table" role="presentation"><tbody>';

            // First / Last name
            echo '<tr><th><label for="new_first_name">First Name</label></th>';
            echo '<td><input type="text" id="new_first_name" name="new_first_name" class="regular-text" value="' . esc_attr($_POST['new_first_name'] ?? '') . '"></td></tr>';

            echo '<tr><th><label for="new_last_name">Last Name</label></th>';
            echo '<td><input type="text" id="new_last_name" name="new_last_name" class="regular-text" value="' . esc_attr($_POST['new_last_name'] ?? '') . '"></td></tr>';

            // Username
            echo '<tr><th><label for="new_username">Username <span style="color:red">*</span></label></th>';
            echo '<td><input type="text" id="new_username" name="new_username" class="regular-text" required value="' . esc_attr($_POST['new_username'] ?? '') . '"></td></tr>';

            // Email
            echo '<tr><th><label for="new_email">Email <span style="color:red">*</span></label></th>';
            echo '<td><input type="email" id="new_email" name="new_email" class="regular-text" required value="' . esc_attr($_POST['new_email'] ?? '') . '"></td></tr>';

            // Password
            echo '<tr><th><label for="new_password">Password</label></th>';
            echo '<td><input type="text" id="new_password" name="new_password" class="regular-text" placeholder="Leave blank to auto-generate"></td></tr>';

            // Role selector (only shown when group has multiple role options)
            if ( count( $group['role_options'] ) > 1 ) {
                echo '<tr><th><label for="new_role">Role</label></th><td>';
                echo '<select id="new_role" name="new_role">';
                foreach ( $group['role_options'] as $slug => $rlabel ) {
                    echo '<option value="' . esc_attr($slug) . '">' . esc_html($rlabel) . '</option>';
                }
                echo '</select></td></tr>';
            } else {
                $only_role = array_key_first( $group['role_options'] );
                echo '<input type="hidden" name="new_role" value="' . esc_attr($only_role) . '">';
            }

            // Send notification
            echo '<tr><th>Send Notification</th>';
            echo '<td><label><input type="checkbox" name="send_user_notification" value="1" checked> Send login credentials to user via email</label></td></tr>';

            echo '</tbody></table>';
            echo '<p class="submit">';
            echo '<button type="submit" class="button button-primary">Create User</button> ';
            echo '<a href="' . esc_url($page_url) . '" class="button">Cancel</a>';
            echo '</p>';
            echo '</form>';
            echo '</div>';
        }

        // ── Search Bar ───────────────────────────────────────────────────────
        echo '<form method="get" style="margin-bottom:12px;display:flex;gap:8px;">';
        echo '<input type="hidden" name="page" value="maljani_users_' . esc_attr($group_key) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search by name or email…" class="regular-text">';
        echo '<button type="submit" class="button">Search</button>';
        if ($search) echo ' <a href="' . esc_url($page_url) . '" class="button">Clear</a>';
        echo '</form>';

        // ── Users Table ──────────────────────────────────────────────────────
        $total = count($users);
        echo '<p style="color:#666;font-size:13px;">' . $total . ' user' . ($total !== 1 ? 's' : '') . ' found.</p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Registered</th><th style="width:200px;">Actions</th>';
        echo '</tr></thead><tbody>';

        if ( empty($users) ) {
            echo '<tr><td colspan="6" style="text-align:center;padding:30px;">No users found. <a href="' . esc_url($page_url . '&add_user=1') . '">Add the first one →</a></td></tr>';
        }

        foreach ( $users as $u ) {
            $current_group_role = '';
            foreach ( $group['roles'] as $r ) {
                if ( in_array($r, (array)$u->roles) ) { $current_group_role = $r; break; }
            }
            $role_label = $group['role_options'][$current_group_role] ?? $current_group_role;

            echo '<tr>';
            echo '<td><strong>' . esc_html($u->display_name) . '</strong></td>';
            echo '<td>' . esc_html($u->user_login) . '</td>';
            echo '<td><a href="mailto:' . esc_attr($u->user_email) . '">' . esc_html($u->user_email) . '</a></td>';
            echo '<td><span class="maljani-role-pill">' . esc_html($role_label) . '</span></td>';
            echo '<td>' . esc_html(date('M j, Y', strtotime($u->user_registered))) . '</td>';
            echo '<td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';

            // Edit role (shown inline if multiple options)
            if ( count($group['role_options']) > 1 ) {
                echo '<form method="post" style="display:flex;gap:4px;">';
                wp_nonce_field('maljani_update_user_role');
                echo '<input type="hidden" name="maljani_update_user_role" value="1">';
                echo '<input type="hidden" name="edit_user_id" value="' . esc_attr($u->ID) . '">';
                echo '<select name="edit_role" style="font-size:12px;">';
                foreach ($group['role_options'] as $slug => $rl) {
                    $sel = ($slug === $current_group_role) ? ' selected' : '';
                    echo '<option value="' . esc_attr($slug) . '"' . $sel . '>' . esc_html($rl) . '</option>';
                }
                echo '</select>';
                echo '<button type="submit" class="button button-small">Update</button>';
                echo '</form>';
            }

            // WP Edit User link
            echo '<a href="' . esc_url(get_edit_user_link($u->ID)) . '" class="button button-small" target="_blank">Edit Profile</a>';

            // Delete (prevent self-delete)
            if ($u->ID !== get_current_user_id()) {
                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this user? This cannot be undone.\');">';
                wp_nonce_field('maljani_delete_user');
                echo '<input type="hidden" name="maljani_delete_user" value="1">';
                echo '<input type="hidden" name="delete_user_id" value="' . esc_attr($u->ID) . '">';
                echo '<button type="submit" class="button button-small button-link-delete">Delete</button>';
                echo '</form>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function print_styles() {
        static $printed = false;
        if ($printed) return;
        $printed = true;
        echo '<style>
            .maljani-card { background:#fff; border:1px solid #ddd; border-radius:6px; padding:20px; }
            .maljani-card h2 { margin-top:0; font-size:16px; }
            .maljani-role-pill { display:inline-block; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600;
                background:#e8f0fe; color:#1a56db; }
        </style>';
    }
}
