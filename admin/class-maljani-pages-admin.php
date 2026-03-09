<?php
/**
 * Maljani Pages Admin
 * Provides a Page Management dashboard under Maljani Settings.
 * Shows all required plugin pages, their status, and lets admins create missing ones with shortcodes.
 */
class Maljani_Pages_Admin {

    /**
     * Define all pages required by the plugin.
     * Each entry:
     *   slug       – used to store the page ID in options (maljani_page_{slug})
     *   title      – WordPress page title
     *   shortcode  – shortcode to embed as page content
     *   role       – who uses this page
     *   description – plain-English description
     *   template   – optional page template file (leave '' for default)
     */
    public static function get_required_pages() {
        return [
            'user_dashboard' => [
                'title'       => 'My Dashboard',
                'shortcode'   => '[maljani_user_dashboard]',
                'role'        => 'All logged-in users',
                'description' => 'The central dashboard for all users. Shows role-specific content.',
                'template'    => '',
            ],
            'agency_dashboard' => [
                'title'       => 'Agency Dashboard',
                'shortcode'   => '[maljani_crm_dashboard]',
                'role'        => 'Agency',
                'description' => 'Agency CRM: add clients, submit policies, track payments.',
                'template'    => '',
            ],
            'client_dashboard' => [
                'title'       => 'My Policies',
                'shortcode'   => '[maljani_client_dashboard]',
                'role'        => 'Client (Insured)',
                'description' => 'Client view: policy status, documents, embassy letters.',
                'template'    => '',
            ],
            'insurer_dashboard' => [
                'title'       => 'Insurer Portal',
                'shortcode'   => '[maljani_insurer_dashboard]',
                'role'        => 'Insurer',
                'description' => 'Insurer portal: review and approve submitted policies.',
                'template'    => '',
            ],
            'policy_catalog' => [
                'title'       => 'Travel Insurance Plans',
                'shortcode'   => '[maljani_policy_catalog]',
                'role'        => 'Public',
                'description' => 'Public page listing all available insurance plans with quote forms.',
                'template'    => '',
            ],
            'policy_quote' => [
                'title'       => 'Get a Quote',
                'shortcode'   => '[maljani_quick_quote]',
                'role'        => 'Public',
                'description' => 'Quick quote form for visitors to estimate premiums.',
                'template'    => '',
            ],
            'login' => [
                'title'       => 'Portal Login',
                'shortcode'   => '[maljani_login_form]',
                'role'        => 'Public',
                'description' => 'Custom login page for agency/client/insurer portal access.',
                'template'    => '',
            ],
            'register_agency' => [
                'title'       => 'Agency Registration',
                'shortcode'   => '[maljani_agency_register]',
                'role'        => 'Public',
                'description' => 'Registration form for new travel agencies.',
                'template'    => '',
            ],
            'policy_verification' => [
                'title'       => 'Policy Verification',
                'shortcode'   => '[maljani_verify_policy]',
                'role'        => 'Public',
                'description' => 'Public verification page — anyone can check a policy number.',
                'template'    => '',
            ],
            'live_chat' => [
                'title'       => 'Live Support',
                'shortcode'   => '[maljani_live_chat]',
                'role'        => 'All users',
                'description' => 'Embeds the live chat widget on a dedicated support page.',
                'template'    => '',
            ],
        ];
    }

    /**
     * Look up a page in the DB by the option key OR by searching for the shortcode in content.
     * Returns WP_Post|null.
     */
    private static function find_page( $slug ) {
        // First: check saved option
        $page_id = get_option( 'maljani_page_' . $slug );
        if ( $page_id ) {
            $page = get_post( $page_id );
            if ( $page && $page->post_status !== 'trash' ) return $page;
        }

        // Fallback: search by shortcode in content
        $def = self::get_required_pages()[ $slug ];
        $sc  = $def['shortcode'];
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft') AND post_content LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like( $sc ) . '%'
        ) );
        if ( $row ) {
            update_option( 'maljani_page_' . $slug, $row->ID );
            return get_post( $row->ID );
        }
        return null;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $defined = self::get_required_pages();
        $messages = [];

        // ── Handle: Create a single page ────────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_create_page'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_create_page' )
        ) {
            $slug = sanitize_key( $_POST['page_slug'] );
            if ( isset( $defined[ $slug ] ) ) {
                $def     = $defined[ $slug ];
                $page_id = wp_insert_post([
                    'post_title'   => $def['title'],
                    'post_content' => $def['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => get_current_user_id(),
                ]);
                if ( ! is_wp_error( $page_id ) ) {
                    update_option( 'maljani_page_' . $slug, $page_id );
                    $messages[] = ['type' => 'success', 'text' => "Page <strong>" . esc_html($def['title']) . "</strong> created with shortcode <code>" . esc_html($def['shortcode']) . "</code>. <a href='" . esc_url(get_edit_post_link($page_id)) . "'>Edit page →</a>"];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to create page: ' . $page_id->get_error_message()];
                }
            }
        }

        // ── Handle: Create ALL missing pages ─────────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_create_all_missing'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_create_all_missing' )
        ) {
            $created = 0;
            foreach ( $defined as $slug => $def ) {
                if ( self::find_page( $slug ) ) continue; // already exists
                $page_id = wp_insert_post([
                    'post_title'   => $def['title'],
                    'post_content' => $def['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => get_current_user_id(),
                ]);
                if ( ! is_wp_error( $page_id ) ) {
                    update_option( 'maljani_page_' . $slug, $page_id );
                    $created++;
                }
            }
            $messages[] = ['type' => 'success', 'text' => "Created <strong>{$created}</strong> missing page(s) — each has its shortcode applied automatically."];
        }

        // ── Handle: Link existing page to a slot ─────────────────────────────
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset( $_POST['maljani_link_page'] ) &&
            wp_verify_nonce( $_POST['_wpnonce'], 'maljani_link_page' )
        ) {
            $slug    = sanitize_key( $_POST['link_slug'] );
            $page_id = intval( $_POST['link_page_id'] );
            if ( $slug && $page_id ) {
                update_option( 'maljani_page_' . $slug, $page_id );
                $messages[] = ['type' => 'success', 'text' => 'Page linked successfully.'];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // Count missing for CTA
        $missing_count = 0;
        $statuses = [];
        foreach ( $defined as $slug => $def ) {
            $page = self::find_page( $slug );
            $statuses[ $slug ] = $page;
            if ( ! $page ) $missing_count++;
        }

        // Build all pages list for the Link dropdown
        $all_pages = get_posts(['post_type' => 'page', 'post_status' => ['publish','draft'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);

        self::print_styles();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">📄 Page Management</h1>';
        echo '<hr class="wp-header-end">';

        foreach ( $messages as $m ) {
            echo '<div class="notice notice-' . esc_attr($m['type']) . ' is-dismissible"><p>' . wp_kses_post($m['text']) . '</p></div>';
        }

        // Summary bar
        $total   = count($defined);
        $present = $total - $missing_count;
        $pct     = $total > 0 ? round(($present / $total) * 100) : 0;

        echo '<div class="maljani-pm-summary">';
        echo '<div class="maljani-pm-stat"><span class="stat-num">' . $total . '</span><span class="stat-lab">Required Pages</span></div>';
        echo '<div class="maljani-pm-stat ok"><span class="stat-num">' . $present . '</span><span class="stat-lab">Found</span></div>';
        echo '<div class="maljani-pm-stat bad"><span class="stat-num">' . $missing_count . '</span><span class="stat-lab">Missing</span></div>';
        echo '<div class="maljani-pm-progress"><div class="maljani-pm-bar" style="width:' . $pct . '%"></div><span>' . $pct . '% set up</span></div>';
        if ( $missing_count > 0 ) {
            echo '<form method="post" style="margin-left:auto;">';
            wp_nonce_field('maljani_create_all_missing');
            echo '<input type="hidden" name="maljani_create_all_missing" value="1">';
            echo '<button type="submit" class="button button-primary">⚡ Create All Missing Pages</button>';
            echo '</form>';
        } else {
            echo '<span style="color:#00a32a;font-weight:600;margin-left:auto;">✓ All pages configured!</span>';
        }
        echo '</div>';

        // Pages grid
        echo '<div class="maljani-pm-grid">';
        foreach ( $defined as $slug => $def ) {
            $page      = $statuses[ $slug ];
            $found     = (bool) $page;
            $card_cls  = $found ? 'found' : 'missing';
            $status_lbl= $found ? '✓ Active' : '✗ Missing';
            $status_cls= $found ? 'ok' : 'bad';

            echo '<div class="maljani-pm-card ' . $card_cls . '">';
            echo '<div class="pm-card-header">';
            echo '<h3>' . esc_html($def['title']) . '</h3>';
            echo '<span class="pm-badge ' . $status_cls . '">' . $status_lbl . '</span>';
            echo '</div>';
            echo '<p class="pm-desc">' . esc_html($def['description']) . '</p>';
            echo '<p class="pm-meta"><strong>Shortcode:</strong> <code>' . esc_html($def['shortcode']) . '</code></p>';
            echo '<p class="pm-meta"><strong>Audience:</strong> ' . esc_html($def['role']) . '</p>';

            // Actions
            echo '<div class="pm-actions">';
            if ( $found ) {
                echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-small" target="_blank">View</a> ';
                echo '<a href="' . esc_url(get_edit_post_link($page->ID)) . '" class="button button-small" target="_blank">Edit</a> ';
                // Show current status
                $status_text = ucfirst($page->post_status);
                echo '<span style="font-size:11px;color:#666;">Status: ' . esc_html($status_text) . ' · ID #' . $page->ID . '</span>';
            } else {
                // Create button
                echo '<form method="post" style="display:inline-block;margin-right:6px;">';
                wp_nonce_field('maljani_create_page');
                echo '<input type="hidden" name="maljani_create_page" value="1">';
                echo '<input type="hidden" name="page_slug" value="' . esc_attr($slug) . '">';
                echo '<button type="submit" class="button button-primary button-small">Create Page</button>';
                echo '</form>';

                // Link to existing page
                echo '<details style="display:inline-block;vertical-align:middle;">';
                echo '<summary style="cursor:pointer;font-size:12px;color:#2271b1;">Link existing page</summary>';
                echo '<form method="post" style="margin-top:6px;display:flex;gap:4px;">';
                wp_nonce_field('maljani_link_page');
                echo '<input type="hidden" name="maljani_link_page" value="1">';
                echo '<input type="hidden" name="link_slug" value="' . esc_attr($slug) . '">';
                echo '<select name="link_page_id" style="font-size:12px;">';
                echo '<option value="">— Select Page —</option>';
                foreach ( $all_pages as $p ) {
                    echo '<option value="' . esc_attr($p->ID) . '">' . esc_html($p->post_title) . ' (#' . $p->ID . ')</option>';
                }
                echo '</select>';
                echo '<button type="submit" class="button button-small">Link</button>';
                echo '</form>';
                echo '</details>';
            }
            echo '</div>'; // pm-actions
            echo '</div>'; // maljani-pm-card
        }
        echo '</div>'; // maljani-pm-grid

        // Footer note about shortcodes
        echo '<div style="background:#f6f7f7;border:1px solid #ddd;border-radius:4px;padding:16px;margin-top:24px;">';
        echo '<h3 style="margin-top:0;">ℹ️ About Shortcodes</h3>';
        echo '<p>When you click <strong>Create Page</strong>, the page is published with the shortcode as its entire content. The shortcode automatically renders the correct interface based on the logged-in user\'s role.</p>';
        echo '<p>You can also paste the shortcode manually into any existing page\'s content using the WordPress editor.</p>';
        echo '</div>';

        echo '</div>'; // wrap
    }

    private static function print_styles() {
        echo '<style>
        .maljani-pm-summary {
            display:flex; align-items:center; gap:16px; flex-wrap:wrap;
            background:#fff; border:1px solid #ddd; border-radius:6px;
            padding:16px 20px; margin:16px 0 24px;
        }
        .maljani-pm-stat { text-align:center; min-width:60px; }
        .maljani-pm-stat .stat-num { display:block; font-size:28px; font-weight:700; line-height:1; }
        .maljani-pm-stat .stat-lab { font-size:11px; color:#888; }
        .maljani-pm-stat.ok .stat-num { color:#00a32a; }
        .maljani-pm-stat.bad .stat-num { color:#d63638; }
        .maljani-pm-progress { display:flex; align-items:center; gap:8px; flex:1; min-width:200px; }
        .maljani-pm-progress > div { height:10px; border-radius:5px; background:#00a32a; min-width:2px; transition:width .4s; }
        .maljani-pm-progress { background:#eee; border-radius:5px; position:relative; height:10px; flex:1; overflow:hidden; }
        .maljani-pm-progress > span { position:absolute; right:-60px; white-space:nowrap; font-size:12px; color:#555; }

        .maljani-pm-grid {
            display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:16px;
        }
        .maljani-pm-card {
            background:#fff; border:1px solid #ddd; border-radius:6px;
            padding:16px; display:flex; flex-direction:column; gap:8px;
        }
        .maljani-pm-card.found { border-top:4px solid #00a32a; }
        .maljani-pm-card.missing { border-top:4px solid #d63638; }
        .pm-card-header { display:flex; justify-content:space-between; align-items:flex-start; }
        .pm-card-header h3 { margin:0; font-size:14px; }
        .pm-badge { font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; white-space:nowrap; }
        .pm-badge.ok  { background:#d1f5e0; color:#007a1f; }
        .pm-badge.bad { background:#fde5e5; color:#b32d2e; }
        .pm-desc { margin:0; font-size:12px; color:#555; }
        .pm-meta { margin:0; font-size:12px; }
        .pm-meta code { font-size:11px; background:#f0f0f0; padding:1px 5px; border-radius:3px; }
        .pm-actions { margin-top:auto; padding-top:8px; border-top:1px solid #f0f0f0; }
        </style>';
    }
}
