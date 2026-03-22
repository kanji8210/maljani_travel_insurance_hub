<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://kipdevwp.tech
 * @since      1.0.0
 *
 * @package    Maljani
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Drop custom tables ─────────────────────────────────────────────────────
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}policy_sale`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}maljani_agencies`" );

// ── Delete all plugin options ──────────────────────────────────────────────
$maljani_options = [
    'maljani_version',
    'maljani_db_version',
    'maljani_pesapal_consumer_key',
    'maljani_pesapal_consumer_secret',
    'maljani_pesapal_mode',
    'maljani_graphql_app_secret',
    'maljani_security_max_login_retries',
    'maljani_invoice_logo_url',
    'maljani_invoice_settings',
    'maljani_company_name',
    'maljani_company_address',
    'maljani_company_phone',
    'maljani_company_email',
    'maljani_company_kra_pin',
    'maljani_ira_licence',
    'maljani_service_fee',
];
foreach ( $maljani_options as $opt ) {
    delete_option( $opt );
}

// ── Delete all transients (brute-force trackers) ───────────────────────────
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE 'mj_gql_fail_%' OR `option_name` LIKE 'mj_gql_blocked_%' OR `option_name` LIKE '_transient_mj_gql_%' OR `option_name` LIKE '_transient_timeout_mj_gql_%'" );

// ── Remove custom roles ────────────────────────────────────────────────────
$custom_roles = [ 'agent', 'insured', 'insurer', 'maljani_editor', 'maljani_admin', 'maljani_super_admin' ];
foreach ( $custom_roles as $role ) {
    remove_role( $role );
}

// ── Remove capabilities added to administrator ─────────────────────────────
$admin = get_role( 'administrator' );
if ( $admin ) {
    $caps_to_remove = [
        'manage_maljani',
        'manage_maljani_policies',
        'manage_maljani_sales',
        'manage_maljani_agencies',
        'manage_maljani_clients',
        'view_maljani_reports',
    ];
    foreach ( $caps_to_remove as $cap ) {
        $admin->remove_cap( $cap );
    }
}
