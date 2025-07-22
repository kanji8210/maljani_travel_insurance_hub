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
            payment_reference VARCHAR(191),
            payment_status ENUM('confirmed','failed','pending') DEFAULT 'pending',
            policy_status ENUM('approved','unconfirmed','confirmed','active','claimed','expired') DEFAULT 'unconfirmed',
            terms LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
});
