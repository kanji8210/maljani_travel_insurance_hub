<?php

/**
 * Fired during plugin activation
 *
 * @link       https://kipdevwp.tech
 * @since      1.0.0
 *
 * @package    Maljani
 * @subpackage Maljani/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Maljani
 * @subpackage Maljani/includes
 * @author     Dennis kip <denisdekemet@gmail.com>
 */
class Maljani_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'policy_sale';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            policy_id BIGINT UNSIGNED NOT NULL,
            policy_number VARCHAR(64),
            region VARCHAR(191),
            premium DECIMAL(10,2),
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
            agent_id BIGINT UNSIGNED,
            agent_name VARCHAR(191),
            amount_paid DECIMAL(10,2),
            service_fee_amount DECIMAL(10,2) DEFAULT 0.00,
            maljani_commission_amount DECIMAL(10,2) DEFAULT 0.00,
            agent_commission_amount DECIMAL(10,2) DEFAULT 0.00,
            agent_commission_status ENUM('unpaid','paid','received','disputed') DEFAULT 'unpaid',
            agency_comm_disputed_note TEXT,
            net_to_insurer DECIMAL(10,2) DEFAULT 0.00,
            payment_reference VARCHAR(191),
            payment_status ENUM('confirmed','failed','pending') DEFAULT 'pending',
            policy_status ENUM('approved','unconfirmed','confirmed','active','claimed','expired') DEFAULT 'unconfirmed',
            workflow_status ENUM('draft','pending_review','submitted_to_insurer','approved','active') DEFAULT 'draft',
            terms LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create agencies table
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        $agency_sql = "CREATE TABLE IF NOT EXISTS $agencies_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            contact_name VARCHAR(191),
            contact_email VARCHAR(191),
            contact_phone VARCHAR(32),
            commission_rate DECIMAL(5,2) DEFAULT 0.00,
            commission_percent DECIMAL(5,2) DEFAULT 0.00,
            user_id BIGINT UNSIGNED DEFAULT 0,
            notes TEXT,
            agency_name VARCHAR(191),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($agency_sql);

        // Migration: add columns that may be missing on existing installs
        self::run_migrations($wpdb, $table_name);
    }

    /**
     * Safely add any missing columns to an existing table.
     * dbDelta does not ALTER existing columns, so we do it manually.
     */
    public static function run_migrations($wpdb, $table_name) {
        $columns = $wpdb->get_col("DESCRIBE `$table_name`", 0);

        if (!in_array('agency_comm_disputed_note', $columns)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `agency_comm_disputed_note` TEXT DEFAULT NULL AFTER `agent_commission_status`");
        }
        // Ensure the status column supports all four values
        $wpdb->query("ALTER TABLE `$table_name` MODIFY COLUMN `agent_commission_status` ENUM('unpaid','paid','received','disputed') DEFAULT 'unpaid'");

        // Ensure policy_sale has agency_id for agency-association
        if (!in_array('agency_id', $columns)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `agency_id` BIGINT UNSIGNED DEFAULT NULL AFTER `agent_id`");
        }
        // Also ensure payment_status and policy_status have all values
        $wpdb->query("ALTER TABLE `$table_name` MODIFY COLUMN `payment_status` ENUM('confirmed','failed','pending','paid','unconfirmed') DEFAULT 'pending'");
        $wpdb->query("ALTER TABLE `$table_name` MODIFY COLUMN `policy_status` ENUM('approved','unconfirmed','confirmed','active','claimed','expired','archived','pending_review','cancelled') DEFAULT 'unconfirmed'");

        // Migrate agencies table columns
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$agencies_table'") === $agencies_table) {
            $ag_cols = $wpdb->get_col("DESCRIBE `$agencies_table`", 0);
            $ag_add = [
                'contact_name'   => "VARCHAR(191) DEFAULT NULL AFTER `name`",
                'contact_email'  => "VARCHAR(191) DEFAULT NULL",
                'contact_phone'  => "VARCHAR(32) DEFAULT NULL",
                'user_id'        => "BIGINT UNSIGNED DEFAULT 0",
                'notes'          => "TEXT DEFAULT NULL",
                'agency_name'    => "VARCHAR(191) DEFAULT NULL",
                'commission_rate'=> "DECIMAL(5,2) DEFAULT 0.00",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                'updated_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ];
            foreach ($ag_add as $col => $def) {
                if (!in_array($col, $ag_cols)) {
                    $wpdb->query("ALTER TABLE `$agencies_table` ADD COLUMN `$col` $def");
                }
            }
        }
    }

}

// Ajoute ce code dans un fichier chargé à chaque page admin (ex : dans class-maljani-admin-menu.php ou dans le fichier principal du plugin)
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'policy_sale';
    if (isset($_GET['maljani_table_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Maljani: Table <strong>policy_sale</strong> created successfully.</p></div>';
    } elseif ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $url = add_query_arg('maljani_create_table', '1');
        echo '<div class="notice notice-error"><p>
            Maljani: Table <strong>policy_sale</strong> is missing. 
            <a href="' . esc_url($url) . '">Click here to create the table</a>.
        </p></div>';
    }
});

// Action pour créer la table manuellement si besoin
add_action('admin_init', function() {
    if (isset($_GET['maljani_create_table']) && current_user_can('manage_options')) {
        require_once plugin_dir_path(__FILE__) . 'class-maljani-activator.php';
        Maljani_Activator::activate();
        wp_redirect(remove_query_arg('maljani_create_table') . '&maljani_table_created=1');
        exit;
    }

    // Always run migrations to ensure new columns exist on existing installs
    if (current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'policy_sale';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-activator.php';
            Maljani_Activator::run_migrations($wpdb, $table_name);
        }
    }
});
