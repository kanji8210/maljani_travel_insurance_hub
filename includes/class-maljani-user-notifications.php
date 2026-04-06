<?php
/**
 * In-app notification system — writes to wp_maljani_notifications table.
 *
 * Types: status_change | cancellation | cover_ending | payment_reminder | info_request | info
 *
 * Works alongside Maljani_Notifications (email) by hooking into the same
 * WordPress actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_User_Notifications {

    /* ──────────────────────────────────────────────────────────────────
     *  Bootstrap
     * ──────────────────────────────────────────────────────────────── */

    public static function init() {
        $instance = new self();

        // Workflow transitions (status changes)
        add_action( 'maljani_workflow_transition', [ $instance, 'on_workflow_transition' ], 10, 5 );

        // New sale created
        add_action( 'maljani_new_sale', [ $instance, 'on_new_sale' ], 10, 2 );

        // Payment confirmed & policy activated (Pesapal IPN)
        add_action( 'maljani_policy_activated', [ $instance, 'on_policy_activated' ], 10, 1 );

        // Admin quick-status / full-edit changes
        add_action( 'maljani_admin_status_change', [ $instance, 'on_admin_status_change' ], 10, 3 );

        // Cron: cover-expiry reminders & payment reminders
        add_action( 'maljani_daily_notification_cron', [ $instance, 'cron_cover_expiry' ] );
        add_action( 'maljani_daily_notification_cron', [ $instance, 'cron_payment_reminders' ] );

        if ( ! wp_next_scheduled( 'maljani_daily_notification_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'maljani_daily_notification_cron' );
        }

        return $instance;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Core helper — push a notification row
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Insert an in-app notification for a specific user.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $type      One of: status_change, cancellation, cover_ending, payment_reminder, info_request, info.
     * @param string $title     Short notification title (≤191 chars).
     * @param string $message   Longer body text.
     * @param int    $policy_id Optional policy_sale row ID.
     */
    public static function push( int $user_id, string $type, string $title, string $message, int $policy_id = 0 ): bool {
        if ( ! $user_id ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'maljani_notifications';

        $result = $wpdb->insert( $table, [
            'user_id'    => $user_id,
            'type'       => sanitize_text_field( $type ),
            'title'      => mb_substr( sanitize_text_field( $title ), 0, 191 ),
            'message'    => sanitize_textarea_field( $message ),
            'policy_id'  => $policy_id ?: null,
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%d', '%d', '%s' ] );

        return $result !== false;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Resolve WP user_id from a policy_sale row
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Returns the WP user_id of the insured person linked to a sale.
     * Falls back to looking up user by insured_email.
     */
    private static function resolve_insured_user_id( $sale ): int {
        // If the buyer is also the logged-in user who created the sale
        if ( ! empty( $sale->agent_id ) ) {
            $user = get_user_by( 'id', $sale->agent_id );
            if ( $user && in_array( 'insured', (array) $user->roles, true ) ) {
                return (int) $user->ID;
            }
        }

        // Otherwise look up by insured_email
        if ( ! empty( $sale->insured_email ) ) {
            $user = get_user_by( 'email', $sale->insured_email );
            if ( $user ) {
                return (int) $user->ID;
            }
        }

        return 0;
    }

    /**
     * Fetch a policy_sale row by ID.
     */
    private static function get_sale( int $sale_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}policy_sale WHERE id = %d LIMIT 1", $sale_id )
        );
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Event handlers
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Workflow transition — fires for every status change via the workflow engine.
     */
    public function on_workflow_transition( $sale_id, $old_status, $new_status, $policy, $notes = '' ) {
        $sale = self::get_sale( (int) $sale_id );
        if ( ! $sale ) {
            return;
        }

        $user_id = self::resolve_insured_user_id( $sale );
        if ( ! $user_id ) {
            return;
        }

        $pol_num = $sale->policy_number ?: '#' . $sale_id;
        $readable = ucwords( str_replace( '_', ' ', $new_status ) );

        switch ( $new_status ) {
            case 'pending_review':
                self::push( $user_id, 'status_change', 'Policy Under Review',
                    "Your policy {$pol_num} is now being reviewed by our team.", (int) $sale_id );
                break;

            case 'submitted_to_insurer':
                self::push( $user_id, 'status_change', 'Submitted to Insurer',
                    "Your policy {$pol_num} has been forwarded to the insurer for approval.", (int) $sale_id );
                break;

            case 'approved':
                self::push( $user_id, 'status_change', 'Policy Approved',
                    "Great news! Your policy {$pol_num} has been approved by the insurer.", (int) $sale_id );
                break;

            case 'active':
                self::push( $user_id, 'status_change', 'Policy Activated',
                    "Your travel insurance policy {$pol_num} is now active. Safe travels!", (int) $sale_id );
                break;

            case 'verification_ready':
                self::push( $user_id, 'status_change', 'Documents Ready',
                    "Your verification documents for policy {$pol_num} are ready for download.", (int) $sale_id );
                break;

            case 'cancelled':
                self::push( $user_id, 'cancellation', 'Policy Cancelled',
                    "Your policy {$pol_num} has been cancelled." . ( $notes ? " Reason: {$notes}" : '' ), (int) $sale_id );
                break;

            case 'draft':
                if ( $old_status === 'pending_review' ) {
                    self::push( $user_id, 'info_request', 'Policy Returned',
                        "Your policy {$pol_num} has been returned for corrections." . ( $notes ? " Notes: {$notes}" : '' ), (int) $sale_id );
                }
                break;

            default:
                self::push( $user_id, 'status_change', "Status: {$readable}",
                    "Your policy {$pol_num} status has changed to {$readable}.", (int) $sale_id );
                break;
        }
    }

    /**
     * New sale created (QuoteWizard / GraphQL).
     */
    public function on_new_sale( int $sale_id, array $data ): void {
        $pol_num  = $data['policy_number'] ?? '#' . $sale_id;
        $email    = $data['insured_email'] ?? '';
        $user     = $email ? get_user_by( 'email', $email ) : null;
        $user_id  = $user ? (int) $user->ID : 0;

        if ( ! $user_id && ! empty( $data['agent_id'] ) ) {
            $user_id = (int) $data['agent_id'];
        }

        if ( ! $user_id ) {
            return;
        }

        self::push( $user_id, 'info', 'Policy Created',
            "Your travel insurance policy {$pol_num} has been recorded. We'll notify you as it progresses.", $sale_id );
    }

    /**
     * Payment confirmed via Pesapal IPN → policy activated.
     */
    public function on_policy_activated( $sale_id ): void {
        $sale = self::get_sale( (int) $sale_id );
        if ( ! $sale ) {
            return;
        }

        $user_id = self::resolve_insured_user_id( $sale );
        if ( ! $user_id ) {
            return;
        }

        $pol_num = $sale->policy_number ?: '#' . $sale_id;

        self::push( $user_id, 'status_change', 'Payment Confirmed',
            "Payment for policy {$pol_num} has been confirmed and your policy is now active!", (int) $sale_id );
    }

    /**
     * Admin changed status via quick-update or full-edit.
     * Fired by do_action('maljani_admin_status_change', $sale_id, $old_status, $new_status).
     */
    public function on_admin_status_change( int $sale_id, string $old_status, string $new_status ): void {
        if ( $old_status === $new_status ) {
            return;
        }

        $sale = self::get_sale( $sale_id );
        if ( ! $sale ) {
            return;
        }

        $user_id = self::resolve_insured_user_id( $sale );
        if ( ! $user_id ) {
            return;
        }

        $pol_num  = $sale->policy_number ?: '#' . $sale_id;
        $readable = ucwords( str_replace( '_', ' ', $new_status ) );

        if ( in_array( $new_status, [ 'cancelled', 'archived' ], true ) ) {
            self::push( $user_id, 'cancellation', 'Policy Cancelled',
                "Your policy {$pol_num} has been cancelled by an administrator.", $sale_id );
        } else {
            self::push( $user_id, 'status_change', "Policy Update: {$readable}",
                "Your policy {$pol_num} status has been updated to {$readable}.", $sale_id );
        }
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Cron jobs
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Notify insured users whose cover ends in 7 days or 1 day.
     * Runs once daily via WP-Cron.
     */
    public function cron_cover_expiry(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $notif = $wpdb->prefix . 'maljani_notifications';

        // Policies whose return date is exactly 7 days or 1 day from now
        foreach ( [ 7, 1 ] as $days_ahead ) {
            $target_date = gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
            $label = $days_ahead === 1 ? 'tomorrow' : "in {$days_ahead} days";

            $sales = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE DATE(`return`) = %s
                   AND policy_status IN ('active','verification_ready')
                 ORDER BY id ASC",
                $target_date
            ) );

            foreach ( $sales as $sale ) {
                $user_id = self::resolve_insured_user_id( $sale );
                if ( ! $user_id ) {
                    continue;
                }

                // Avoid duplicate notifications for the same sale + type + day
                $already = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$notif}
                     WHERE user_id = %d AND policy_id = %d AND type = 'cover_ending'
                       AND DATE(created_at) = %s",
                    $user_id, $sale->id, gmdate( 'Y-m-d' )
                ) );

                if ( $already ) {
                    continue;
                }

                $pol_num = $sale->policy_number ?: '#' . $sale->id;
                self::push( $user_id, 'cover_ending', 'Cover Ending Soon',
                    "Your travel insurance policy {$pol_num} expires {$label}. Make sure your plans are covered!", (int) $sale->id );
            }
        }
    }

    /**
     * Remind users with unpaid policies older than 24 hours.
     * Only reminds once every 24 hours per sale.
     */
    public function cron_payment_reminders(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $notif = $wpdb->prefix . 'maljani_notifications';

        // Policies created more than 24h ago that are still unpaid
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

        $sales = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE payment_status IN ('pending','unconfirmed')
               AND policy_status NOT IN ('cancelled','archived')
               AND created_at < %s
             ORDER BY id ASC",
            $cutoff
        ) );

        foreach ( $sales as $sale ) {
            $user_id = self::resolve_insured_user_id( $sale );
            if ( ! $user_id ) {
                continue;
            }

            // Only one payment reminder per day per sale
            $already = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$notif}
                 WHERE user_id = %d AND policy_id = %d AND type = 'payment_reminder'
                   AND DATE(created_at) = %s",
                $user_id, $sale->id, gmdate( 'Y-m-d' )
            ) );

            if ( $already ) {
                continue;
            }

            $pol_num = $sale->policy_number ?: '#' . $sale->id;
            self::push( $user_id, 'payment_reminder', 'Payment Pending',
                "Your policy {$pol_num} is awaiting payment. Complete your payment to activate coverage.", (int) $sale->id );
        }
    }
}
