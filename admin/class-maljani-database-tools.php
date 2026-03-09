<?php

/**
 * Database Management Tools for Maljani Plugin
 *
 * @link       https://kipdevwp.tech
 * @since      1.0.0
 *
 * @package    Maljani
 * @subpackage Maljani/admin
 */

class Maljani_Database_Tools {

    /**
     * Get the database schema for all plugin tables
     *
     * @since    1.0.0
     * @return   array   Array of table schemas
     */
    public static function get_table_schemas() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        return [
            'policy_sale' => "CREATE TABLE {$prefix}policy_sale (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                policy_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NULL,
                agency_id BIGINT UNSIGNED NULL,
                policy_number VARCHAR(64),
                region VARCHAR(191),
                premium DECIMAL(10,2),
                commission_amount DECIMAL(10,2) DEFAULT 0.00,
                days INT,
                departure DATE,
                `return` DATE,
                insured_names VARCHAR(191),
                insured_dob DATE,
                passport_number VARCHAR(64),
                national_id VARCHAR(64),
                insured_phone VARCHAR(32),
                insured_email VARCHAR(191),
                insured_address VARCHAR(191),
                country_of_origin VARCHAR(191),
                agent_name VARCHAR(191),
                amount_paid DECIMAL(10,2),
                payment_reference VARCHAR(191),
                payment_status ENUM('confirmed','failed','pending') DEFAULT 'pending',
                policy_status ENUM('approved','unconfirmed','confirmed','active','claimed','expired') DEFAULT 'unconfirmed',
                workflow_status ENUM('draft','pending_review','submitted_to_insurer','approved','active','verification_ready') DEFAULT 'draft',
                terms LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY policy_id (policy_id),
                KEY client_id (client_id),
                KEY agency_id (agency_id),
                KEY policy_number (policy_number),
                KEY insured_email (insured_email)
            ) $charset_collate;",

            'maljani_api_keys' => "CREATE TABLE {$prefix}maljani_api_keys (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                key_name VARCHAR(191) NOT NULL,
                api_key VARCHAR(64) NOT NULL,
                secret_key VARCHAR(64),
                environment ENUM('sandbox','production') DEFAULT 'sandbox',
                status ENUM('active','inactive') DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY api_key (api_key),
                KEY key_name (key_name)
            ) $charset_collate;"
,
            'maljani_chat_conversations' => "CREATE TABLE {$prefix}maljani_chat_conversations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                email VARCHAR(191) NULL,
                status ENUM('active','closed') DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY email (email),
                KEY status (status)
            ) $charset_collate;",

            'maljani_chat_messages' => "CREATE TABLE {$prefix}maljani_chat_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_type ENUM('user','agent') DEFAULT 'user',
                user_id BIGINT UNSIGNED NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id),
                KEY sender_type (sender_type),
                KEY is_read (is_read)
            ) $charset_collate;",

            'maljani_chat_agents' => "CREATE TABLE {$prefix}maljani_chat_agents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                is_online BOOLEAN DEFAULT FALSE,
                last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY is_online (is_online)
            ) $charset_collate;",

            'maljani_agencies' => "CREATE TABLE {$prefix}maljani_agencies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                agency_name VARCHAR(191) NOT NULL,
                commission_percent DECIMAL(5,2) DEFAULT 0.00,
                contact_email VARCHAR(191),
                contact_phone VARCHAR(32),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY contact_email (contact_email)
            ) $charset_collate;",

            'maljani_clients' => "CREATE TABLE {$prefix}maljani_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                agency_id BIGINT UNSIGNED NULL,
                first_name VARCHAR(191) NOT NULL,
                last_name VARCHAR(191) NOT NULL,
                email VARCHAR(191),
                phone VARCHAR(32),
                dob DATE,
                passport_number VARCHAR(64),
                national_id VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY agency_id (agency_id),
                KEY email (email)
            ) $charset_collate;",

            'maljani_payments' => "CREATE TABLE {$prefix}maljani_payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                agency_id BIGINT UNSIGNED NULL,
                policy_id BIGINT UNSIGNED NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                reference VARCHAR(191),
                type ENUM('agency_to_maljani','maljani_to_insurer') DEFAULT 'agency_to_maljani',
                status ENUM('pending','completed','failed') DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY agency_id (agency_id),
                KEY policy_id (policy_id),
                KEY status (status)
            ) $charset_collate;",

            'maljani_documents' => "CREATE TABLE {$prefix}maljani_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                policy_id BIGINT UNSIGNED NOT NULL,
                type ENUM('policy_doc','embassy_letter','verification') NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                uploaded_by BIGINT UNSIGNED NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY policy_id (policy_id),
                KEY type (type)
            ) $charset_collate;",

            'maljani_audit_trail' => "CREATE TABLE {$prefix}maljani_audit_trail (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type ENUM('policy','payment','client','agency') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                action_name VARCHAR(191) NOT NULL,
                performed_by BIGINT UNSIGNED NULL,
                details JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY entity_type_id (entity_type, entity_id)
            ) $charset_collate;"
        ];
    }

    /**
     * Check which tables exist and which are missing
     *
     * @since    1.0.0
     * @return   array   Status of all tables
     */
    public static function check_tables_status() {
        global $wpdb;
        $schemas = self::get_table_schemas();
        $status = [];

        foreach ($schemas as $table_key => $schema) {
            $table_name = $wpdb->prefix . $table_key;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            $row_count = 0;
            $structure_ok = false;
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $structure_ok = self::verify_table_structure($table_key);
            }

            $status[$table_key] = [
                'name' => $table_name,
                'exists' => $exists,
                'row_count' => $row_count,
                'structure_ok' => $structure_ok,
                'needs_update' => $exists && !$structure_ok
            ];
        }

        return $status;
    }

    /**
     * Verify table structure matches schema
     *
     * @since    1.0.0
     * @param    string  $table_key  Table key from schema
     * @return   bool    True if structure is correct
     */
    private static function verify_table_structure($table_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table_key;
        
        // Get current columns
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        
        // Basic verification - check if table has columns
        if (empty($columns)) {
            return false;
        }

        // Define required columns for each table
        $required_columns = [
            'policy_sale' => ['id', 'policy_id', 'client_id', 'agency_id', 'policy_number', 'premium', 'commission_amount', 'workflow_status', 'insured_names', 'insured_email'],
            'maljani_api_keys' => ['id', 'key_name', 'api_key', 'status'],
            'maljani_chat_conversations' => ['id', 'email', 'status'],
            'maljani_chat_messages' => ['id', 'conversation_id', 'message'],
            'maljani_chat_agents' => ['id', 'user_id', 'is_online'],
            'maljani_agencies' => ['id', 'agency_name', 'commission_percent'],
            'maljani_clients' => ['id', 'first_name', 'last_name', 'email'],
            'maljani_payments' => ['id', 'policy_id', 'amount', 'type', 'status'],
            'maljani_documents' => ['id', 'policy_id', 'type', 'file_path'],
            'maljani_audit_trail' => ['id', 'entity_type', 'entity_id', 'action_name']
        ];

        if (!isset($required_columns[$table_key])) {
            return true; // Unknown table, assume OK
        }

        $existing_columns = array_column($columns, 'Field');
        
        foreach ($required_columns[$table_key] as $required_col) {
            if (!in_array($required_col, $existing_columns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create missing tables
     *
     * @since    1.0.0
     * @param    string|null  $table_key  Specific table to create, or null for all
     * @return   array        Results of table creation
     */
    public static function create_missing_tables($table_key = null) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $schemas = self::get_table_schemas();
        $results = [];

        if ($table_key !== null) {
            // Create specific table
            if (isset($schemas[$table_key])) {
                $results[$table_key] = dbDelta($schemas[$table_key]);
            }
        } else {
            // Create all tables
            foreach ($schemas as $key => $sql) {
                $results[$key] = dbDelta($sql);
            }
        }

        return $results;
    }

    /**
     * Update existing tables to match current schema
     *
     * @since    1.0.0
     * @return   array   Results of table updates
     */
    public static function update_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $schemas = self::get_table_schemas();
        $results = [];

        foreach ($schemas as $key => $sql) {
            $results[$key] = dbDelta($sql);
        }

        return $results;
    }

    /**
     * Generate a new API key
     *
     * @since    1.0.0
     * @param    string  $key_name     Name for the key
     * @param    string  $environment  'sandbox' or 'production'
     * @return   array   Generated keys
     */
    public static function generate_api_key($key_name, $environment = 'sandbox') {
        global $wpdb;
        
        // Generate secure random keys
        $api_key = 'mlj_' . bin2hex(random_bytes(24));
        $secret_key = 'mljs_' . bin2hex(random_bytes(32));

        $table_name = $wpdb->prefix . 'maljani_api_keys';
        
        $inserted = $wpdb->insert(
            $table_name,
            [
                'key_name' => sanitize_text_field($key_name),
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'environment' => $environment,
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted) {
            return [
                'success' => true,
                'id' => $wpdb->insert_id,
                'api_key' => $api_key,
                'secret_key' => $secret_key
            ];
        }

        return [
            'success' => false,
            'error' => $wpdb->last_error
        ];
    }

    /**
     * Get all API keys
     *
     * @since    1.0.0
     * @return   array   List of API keys
     */
    public static function get_api_keys() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'maljani_api_keys';
        
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return [];
        }

        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }

    /**
     * Delete an API key
     *
     * @since    1.0.0
     * @param    int  $key_id  ID of the key to delete
     * @return   bool
     */
    public static function delete_api_key($key_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'maljani_api_keys';
        
        return $wpdb->delete($table_name, ['id' => $key_id], ['%d']) !== false;
    }

    /**
     * Toggle API key status
     *
     * @since    1.0.0
     * @param    int     $key_id     ID of the key
     * @param    string  $status     'active' or 'inactive'
     * @return   bool
     */
    public static function toggle_api_key_status($key_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'maljani_api_keys';
        
        return $wpdb->update(
            $table_name,
            ['status' => $status],
            ['id' => $key_id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Render the database tools admin page
     *
     * @since    1.0.0
     */
    public static function render_database_tools_page() {
        // Handle form submissions
        if (isset($_POST['maljani_db_action']) && check_admin_referer('maljani_db_tools', 'maljani_db_nonce')) {
            $action = sanitize_text_field($_POST['maljani_db_action']);
            
            switch ($action) {
                case 'create_tables':
                    $results = self::create_missing_tables();
                    echo '<div class="notice notice-success"><p>Tables created/updated successfully!</p></div>';
                    break;

                case 'update_tables':
                    $results = self::update_tables();
                    echo '<div class="notice notice-success"><p>Tables updated successfully!</p></div>';
                    break;

                case 'generate_key':
                    if (!empty($_POST['key_name'])) {
                        $key_name = sanitize_text_field($_POST['key_name']);
                        $environment = sanitize_text_field($_POST['environment']);
                        $result = self::generate_api_key($key_name, $environment);
                        
                        if ($result['success']) {
                            echo '<div class="notice notice-success"><p>API Key generated successfully!</p>';
                            echo '<p><strong>API Key:</strong> <code>' . esc_html($result['api_key']) . '</code></p>';
                            echo '<p><strong>Secret Key:</strong> <code>' . esc_html($result['secret_key']) . '</code></p>';
                            echo '<p class="description">Please save these keys securely. The secret key will not be shown again in full.</p>';
                            echo '</div>';
                        } else {
                            echo '<div class="notice notice-error"><p>Error generating key: ' . esc_html($result['error']) . '</p></div>';
                        }
                    }
                    break;

                case 'delete_key':
                    if (!empty($_POST['key_id'])) {
                        self::delete_api_key(intval($_POST['key_id']));
                        echo '<div class="notice notice-success"><p>API Key deleted successfully!</p></div>';
                    }
                    break;

                case 'toggle_key':
                    if (!empty($_POST['key_id']) && !empty($_POST['status'])) {
                        self::toggle_api_key_status(intval($_POST['key_id']), sanitize_text_field($_POST['status']));
                        echo '<div class="notice notice-success"><p>API Key status updated!</p></div>';
                    }
                    break;
            }
        }

        // Get table status
        $table_status = self::check_tables_status();
        $api_keys = self::get_api_keys();

        ?>
        <div class="wrap">
            <h1>Maljani Database Management Tools</h1>

            <!-- Database Tables Status -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>Database Tables Status</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Structure</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_status as $key => $status): ?>
                        <tr>
                            <td><code><?php echo esc_html($status['name']); ?></code></td>
                            <td>
                                <?php if ($status['exists']): ?>
                                    <span style="color: green;">✓ Exists</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status['exists'] ? number_format($status['row_count']) : 'N/A'; ?></td>
                            <td>
                                <?php if ($status['exists']): ?>
                                    <?php if ($status['structure_ok']): ?>
                                        <span style="color: green;">✓ OK</span>
                                    <?php else: ?>
                                        <span style="color: orange;">⚠ Needs Update</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$status['exists'] || $status['needs_update']): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                                        <input type="hidden" name="maljani_db_action" value="create_tables">
                                        <button type="submit" class="button button-small">
                                            <?php echo $status['exists'] ? 'Update' : 'Create'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                        <input type="hidden" name="maljani_db_action" value="create_tables">
                        <button type="submit" class="button button-primary">Create/Update All Tables</button>
                    </form>
                </div>
            </div>

            <!-- API Keys Management -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>API Keys Management</h2>
                
                <?php if ($table_status['maljani_api_keys']['exists']): ?>
                    
                    <!-- Generate New Key Form -->
                    <div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <h3>Generate New API Key</h3>
                        <form method="post">
                            <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                            <input type="hidden" name="maljani_db_action" value="generate_key">
                            
                            <table class="form-table">
                                <tr>
                                    <th><label for="key_name">Key Name</label></th>
                                    <td>
                                        <input type="text" name="key_name" id="key_name" class="regular-text" required>
                                        <p class="description">A descriptive name for this API key (e.g., "Production API", "Mobile App")</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="environment">Environment</label></th>
                                    <td>
                                        <select name="environment" id="environment">
                                            <option value="sandbox">Sandbox</option>
                                            <option value="production">Production</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <button type="submit" class="button button-primary">Generate API Key</button>
                        </form>
                    </div>

                    <!-- Existing Keys Table -->
                    <?php if (!empty($api_keys)): ?>
                        <h3>Existing API Keys</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Key Name</th>
                                    <th>API Key</th>
                                    <th>Secret Key</th>
                                    <th>Environment</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($api_keys as $key): ?>
                                <tr>
                                    <td><?php echo esc_html($key->id); ?></td>
                                    <td><strong><?php echo esc_html($key->key_name); ?></strong></td>
                                    <td><code><?php echo esc_html($key->api_key); ?></code></td>
                                    <td><code><?php echo esc_html(substr($key->secret_key, 0, 12)) . '...'; ?></code></td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $key->environment === 'production' ? '#d63638' : '#2271b1'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                            <?php echo esc_html(strtoupper($key->environment)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($key->status === 'active'): ?>
                                            <span style="color: green;">●</span> Active
                                        <?php else: ?>
                                            <span style="color: #999;">●</span> Inactive
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($key->created_at))); ?></td>
                                    <td>
                                        <!-- Toggle Status -->
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                                            <input type="hidden" name="maljani_db_action" value="toggle_key">
                                            <input type="hidden" name="key_id" value="<?php echo esc_attr($key->id); ?>">
                                            <input type="hidden" name="status" value="<?php echo $key->status === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="button button-small">
                                                <?php echo $key->status === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete -->
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this API key? This action cannot be undone.');">
                                            <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                                            <input type="hidden" name="maljani_db_action" value="delete_key">
                                            <input type="hidden" name="key_id" value="<?php echo esc_attr($key->id); ?>">
                                            <button type="submit" class="button button-small button-link-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><em>No API keys found. Generate one above to get started.</em></p>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p>The API keys table doesn't exist yet. Please create it using the button above.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card" style="margin-top: 20px;">
                <h2>Quick Actions</h2>
                <p>Use these tools to diagnose and fix database issues.</p>
                
                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline; margin-right: 10px;">
                        <?php wp_nonce_field('maljani_db_tools', 'maljani_db_nonce'); ?>
                        <input type="hidden" name="maljani_db_action" value="update_tables">
                        <button type="submit" class="button">Repair/Update All Tables</button>
                    </form>

                    <a href="<?php echo admin_url('admin.php?page=maljani_settings'); ?>" class="button">Go to Settings</a>
                </div>
            </div>
        </div>

        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
        </style>
        <?php
    }
}
