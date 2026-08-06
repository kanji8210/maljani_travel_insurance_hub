<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Claims_Portal {
    private const SHORTCODE = 'maljani_claims_portal';

    public static function get_portal_url() {
        $page_id = absint( get_option( 'maljani_page_claims_portal' ) );
        $url = $page_id ? get_permalink( $page_id ) : '';

        return $url ?: home_url( '/claims-refunds/' );
    }

    public function __construct() {
        add_shortcode( self::SHORTCODE, [ $this, 'render_portal' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_post_maljani_submit_claim_request', [ $this, 'handle_submission' ] );
        add_action( 'admin_post_nopriv_maljani_submit_claim_request', [ $this, 'handle_submission' ] );
        add_action( 'admin_post_maljani_update_claim_request', [ $this, 'handle_admin_update' ] );
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            wp_enqueue_style(
                'maljani-claims-portal',
                plugin_dir_url( __FILE__ ) . 'css/maljani-claims-portal.css',
                [],
                defined( 'MALJANI_VERSION' ) ? MALJANI_VERSION : '1.0.9'
            );
        }
    }

    public function register_admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_claim_requests';
        $pending = 0;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('approved', 'rejected', 'closed')"
            );
        }

        $badge = $pending > 0
            ? " <span class='awaiting-mod count-{$pending}'><span class='pending-count'>{$pending}</span></span>"
            : '';

        add_submenu_page(
            'maljani_travel',
            __( 'Claims & Refunds', 'maljani' ),
            __( 'Claims & Refunds', 'maljani' ) . $badge,
            'edit_maljani_policies',
            'maljani-claims',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_portal() {
        $fee = max( 0, (float) get_option( 'maljani_claim_assistance_fee', 0 ) );
        $currency = sanitize_text_field( get_option( 'maljani_inv_currency', 'KSH' ) );
        $submitted_reference = isset( $_GET['claim_submitted'] )
            ? sanitize_text_field( wp_unslash( $_GET['claim_submitted'] ) )
            : '';
        $has_error = isset( $_GET['claim_error'] );
        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <section class="mj-claims" aria-labelledby="mj-claims-title">
            <div class="mj-claims-intro">
                <div>
                    <span class="mj-claims-kicker"><?php esc_html_e( 'Claims advocacy desk', 'maljani' ); ?></span>
                    <h2 id="mj-claims-title"><?php esc_html_e( 'We handle the follow-up. You focus on recovery.', 'maljani' ); ?></h2>
                    <p><?php esc_html_e( 'Tell us what happened and our team will review your cover, prepare the request, and follow up with the insurer on your behalf.', 'maljani' ); ?></p>
                </div>
                <aside class="mj-claims-fee" aria-label="Assistance fee">
                    <span><?php esc_html_e( 'Assistance fee', 'maljani' ); ?></span>
                    <strong><?php echo esc_html( $fee > 0 ? $currency . ' ' . number_format_i18n( $fee, 2 ) : __( 'No fee', 'maljani' ) ); ?></strong>
                    <small><?php esc_html_e( 'Charged for our processing and follow-up service, not for the insurer payout.', 'maljani' ); ?></small>
                </aside>
            </div>

            <?php if ( $submitted_reference ) : ?>
                <div class="mj-claims-notice mj-claims-notice--success" role="status">
                    <strong><?php esc_html_e( 'Request received.', 'maljani' ); ?></strong>
                    <?php
                    printf(
                        esc_html__( 'Your reference is %s. Our team will contact you with the required documents and payment instructions.', 'maljani' ),
                        esc_html( $submitted_reference )
                    );
                    ?>
                </div>
            <?php elseif ( $has_error ) : ?>
                <div class="mj-claims-notice mj-claims-notice--error" role="alert">
                    <?php esc_html_e( 'We could not save your request. Check the required fields and try again.', 'maljani' ); ?>
                </div>
            <?php endif; ?>

            <form class="mj-claims-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="maljani_submit_claim_request">
                <?php wp_nonce_field( 'maljani_submit_claim_request', 'maljani_claim_nonce' ); ?>

                <fieldset class="mj-claims-kind">
                    <legend><?php esc_html_e( 'What can we help with?', 'maljani' ); ?></legend>
                    <label>
                        <input type="radio" name="request_type" value="claim" checked>
                        <span><strong><?php esc_html_e( 'Insurance claim', 'maljani' ); ?></strong><small><?php esc_html_e( 'Medical, baggage, delay, cancellation, or another insured event.', 'maljani' ); ?></small></span>
                    </label>
                    <label>
                        <input type="radio" name="request_type" value="refund">
                        <span><strong><?php esc_html_e( 'Premium refund', 'maljani' ); ?></strong><small><?php esc_html_e( 'Request a cancellation or eligible premium refund.', 'maljani' ); ?></small></span>
                    </label>
                </fieldset>

                <div class="mj-claims-section">
                    <div class="mj-claims-section-title"><span>01</span><div><h3><?php esc_html_e( 'Client details', 'maljani' ); ?></h3><p><?php esc_html_e( 'How we can identify and contact you.', 'maljani' ); ?></p></div></div>
                    <div class="mj-claims-grid">
                        <label class="mj-field"><span><?php esc_html_e( 'Full name', 'maljani' ); ?></span><input type="text" name="client_name" autocomplete="name" required value="<?php echo esc_attr( $current_user->exists() ? $current_user->display_name : '' ); ?>"></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Email address', 'maljani' ); ?></span><input type="email" name="client_email" autocomplete="email" required value="<?php echo esc_attr( $current_user->exists() ? $current_user->user_email : '' ); ?>"></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Phone number', 'maljani' ); ?></span><input type="tel" name="client_phone" autocomplete="tel" required></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Policy number', 'maljani' ); ?></span><input type="text" name="policy_number" required></label>
                        <label class="mj-field mj-field--wide"><span><?php esc_html_e( 'Insurer', 'maljani' ); ?></span><input type="text" name="insurer_name" required></label>
                    </div>
                </div>

                <div class="mj-claims-section">
                    <div class="mj-claims-section-title"><span>02</span><div><h3><?php esc_html_e( 'Request details', 'maljani' ); ?></h3><p><?php esc_html_e( 'Give us enough context for the first review. We will request documents privately.', 'maljani' ); ?></p></div></div>
                    <div class="mj-claims-grid">
                        <label class="mj-field"><span><?php esc_html_e( 'Incident or cancellation date', 'maljani' ); ?></span><input type="date" name="incident_date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Reason or incident type', 'maljani' ); ?></span><input type="text" name="incident_type" placeholder="e.g. Medical emergency"></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Amount requested', 'maljani' ); ?></span><input type="number" name="requested_amount" min="0" step="0.01" inputmode="decimal"></label>
                        <label class="mj-field"><span><?php esc_html_e( 'Currency', 'maljani' ); ?></span><select name="currency"><option value="KSH">KSH</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></label>
                        <label class="mj-field mj-field--wide"><span><?php esc_html_e( 'What happened?', 'maljani' ); ?></span><textarea name="description" rows="6" required placeholder="Include the event, location, people involved, and any contact already made with the insurer."></textarea></label>
                    </div>
                </div>

                <div class="mj-claims-consent">
                    <label>
                        <input type="checkbox" name="consent" value="1" required>
                        <span>
                            <?php
                            printf(
                                esc_html__( 'I authorize TIC-Kenya to contact the insurer and act on my behalf for this request. I understand the disclosed assistance fee is %s and insurer approval or payment is not guaranteed.', 'maljani' ),
                                esc_html( $fee > 0 ? $currency . ' ' . number_format_i18n( $fee, 2 ) : __( 'zero', 'maljani' ) )
                            );
                            ?>
                        </span>
                    </label>
                    <button type="submit" class="mj-claims-submit"><?php esc_html_e( 'Submit for review', 'maljani' ); ?></button>
                </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    public function handle_submission() {
        if ( ! isset( $_POST['maljani_claim_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maljani_claim_nonce'] ) ), 'maljani_submit_claim_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'maljani' ), 403 );
        }

        $redirect = wp_get_referer() ?: home_url( '/' );
        $required = [ 'client_name', 'client_email', 'client_phone', 'policy_number', 'insurer_name', 'description' ];
        foreach ( $required as $field ) {
            if ( empty( trim( (string) ( $_POST[ $field ] ?? '' ) ) ) ) {
                wp_safe_redirect( add_query_arg( 'claim_error', 'required', $redirect ) );
                exit;
            }
        }

        $email = sanitize_email( wp_unslash( $_POST['client_email'] ) );
        if ( ! is_email( $email ) || empty( $_POST['consent'] ) ) {
            wp_safe_redirect( add_query_arg( 'claim_error', 'invalid', $redirect ) );
            exit;
        }

        $request_type = sanitize_key( wp_unslash( $_POST['request_type'] ?? 'claim' ) );
        $request_type = in_array( $request_type, [ 'claim', 'refund' ], true ) ? $request_type : 'claim';
        $currency = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'KSH' ) ) );
        $currency = in_array( $currency, [ 'KSH', 'USD', 'EUR', 'GBP' ], true ) ? $currency : 'KSH';
        $fee = max( 0, (float) get_option( 'maljani_claim_assistance_fee', 0 ) );
        $reference = $this->generate_reference();

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'maljani_claim_requests',
            [
                'reference'        => $reference,
                'user_id'          => get_current_user_id(),
                'request_type'     => $request_type,
                'client_name'      => sanitize_text_field( wp_unslash( $_POST['client_name'] ) ),
                'client_email'     => $email,
                'client_phone'     => sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ),
                'policy_number'    => sanitize_text_field( wp_unslash( $_POST['policy_number'] ) ),
                'insurer_name'     => sanitize_text_field( wp_unslash( $_POST['insurer_name'] ) ),
                'incident_date'    => $this->sanitize_date( $_POST['incident_date'] ?? '' ),
                'incident_type'    => sanitize_text_field( wp_unslash( $_POST['incident_type'] ?? '' ) ),
                'requested_amount' => empty( $_POST['requested_amount'] ) ? null : max( 0, (float) $_POST['requested_amount'] ),
                'currency'         => $currency,
                'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ) ),
                'fee_amount'       => $fee,
                'fee_status'       => $fee > 0 ? 'pending' : 'not_required',
                'status'           => $fee > 0 ? 'awaiting_payment' : 'new',
                'consent'          => 1,
            ]
        );

        if ( ! $inserted ) {
            wp_safe_redirect( add_query_arg( 'claim_error', 'save', $redirect ) );
            exit;
        }

        $admin_email = get_option( 'admin_email' );
        wp_mail(
            $admin_email,
            sprintf( '[%s] New %s assistance request', $reference, ucfirst( $request_type ) ),
            sprintf( "A new request was submitted by %s (%s).\n\nReview: %s", sanitize_text_field( wp_unslash( $_POST['client_name'] ) ), $email, admin_url( 'admin.php?page=maljani-claims&request_id=' . $wpdb->insert_id ) )
        );

        wp_safe_redirect( add_query_arg( 'claim_submitted', rawurlencode( $reference ), remove_query_arg( [ 'claim_error', 'claim_submitted' ], $redirect ) ) );
        exit;
    }

    public function handle_admin_update() {
        if ( ! current_user_can( 'edit_maljani_policies' ) ) {
            wp_die( esc_html__( 'You are not allowed to update requests.', 'maljani' ), 403 );
        }

        $request_id = absint( $_POST['request_id'] ?? 0 );
        check_admin_referer( 'maljani_update_claim_' . $request_id );

        $allowed_statuses = [ 'new', 'awaiting_payment', 'documents_required', 'in_review', 'submitted', 'approved', 'rejected', 'closed' ];
        $allowed_fee_statuses = [ 'not_required', 'pending', 'paid', 'waived', 'refunded' ];
        $status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
        $fee_status = sanitize_key( wp_unslash( $_POST['fee_status'] ?? '' ) );

        if ( ! in_array( $status, $allowed_statuses, true ) || ! in_array( $fee_status, $allowed_fee_statuses, true ) ) {
            wp_die( esc_html__( 'Invalid request status.', 'maljani' ), 400 );
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'maljani_claim_requests',
            [
                'status'            => $status,
                'fee_status'        => $fee_status,
                'payment_reference' => sanitize_text_field( wp_unslash( $_POST['payment_reference'] ?? '' ) ),
                'admin_notes'       => sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) ),
            ],
            [ 'id' => $request_id ]
        );

        wp_safe_redirect( admin_url( 'admin.php?page=maljani-claims&request_id=' . $request_id . '&updated=1' ) );
        exit;
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_maljani_policies' ) ) {
            wp_die( esc_html__( 'You are not allowed to view requests.', 'maljani' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'maljani_claim_requests';
        $request_id = absint( $_GET['request_id'] ?? 0 );
        if ( $request_id ) {
            $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id ) );
            $this->render_admin_detail( $request );
            return;
        }

        $status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
        $allowed_statuses = [ 'new', 'awaiting_payment', 'documents_required', 'in_review', 'submitted', 'approved', 'rejected', 'closed' ];
        if ( in_array( $status, $allowed_statuses, true ) ) {
            $requests = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT 200", $status ) );
        } else {
            $requests = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Claims & Refunds', 'maljani' ); ?></h1>
            <p><?php esc_html_e( 'Review client intake, collect the assistance fee, request documents privately, and track insurer follow-up.', 'maljani' ); ?></p>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=maljani_settings' ) ); ?>"><?php esc_html_e( 'Configure assistance fee', 'maljani' ); ?></a></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php esc_html_e( 'Reference / Date', 'maljani' ); ?></th><th><?php esc_html_e( 'Client', 'maljani' ); ?></th><th><?php esc_html_e( 'Request', 'maljani' ); ?></th><th><?php esc_html_e( 'Policy', 'maljani' ); ?></th><th><?php esc_html_e( 'Fee', 'maljani' ); ?></th><th><?php esc_html_e( 'Status', 'maljani' ); ?></th></tr></thead>
                <tbody>
                <?php if ( empty( $requests ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No claims or refund requests found.', 'maljani' ); ?></td></tr>
                <?php else : foreach ( $requests as $request ) : ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=maljani-claims&request_id=' . $request->id ) ); ?>"><?php echo esc_html( $request->reference ); ?></a></strong><br><small><?php echo esc_html( mysql2date( get_option( 'date_format' ), $request->created_at ) ); ?></small></td>
                        <td><?php echo esc_html( $request->client_name ); ?><br><a href="mailto:<?php echo esc_attr( $request->client_email ); ?>"><?php echo esc_html( $request->client_email ); ?></a></td>
                        <td><?php echo esc_html( ucfirst( $request->request_type ) ); ?><br><small><?php echo esc_html( $request->incident_type ?: '—' ); ?></small></td>
                        <td><?php echo esc_html( $request->policy_number ); ?><br><small><?php echo esc_html( $request->insurer_name ); ?></small></td>
                        <td><?php echo esc_html( get_option( 'maljani_inv_currency', 'KSH' ) . ' ' . number_format_i18n( $request->fee_amount, 2 ) ); ?><br><small><?php echo esc_html( $this->labelize( $request->fee_status ) ); ?></small></td>
                        <td><strong><?php echo esc_html( $this->labelize( $request->status ) ); ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_admin_detail( $request ) {
        if ( ! $request ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Request not found', 'maljani' ) . '</h1></div>';
            return;
        }

        $statuses = [ 'new', 'awaiting_payment', 'documents_required', 'in_review', 'submitted', 'approved', 'rejected', 'closed' ];
        $fee_statuses = [ 'not_required', 'pending', 'paid', 'waived', 'refunded' ];
        ?>
        <div class="wrap">
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=maljani-claims' ) ); ?>">&larr; <?php esc_html_e( 'All requests', 'maljani' ); ?></a></p>
            <h1><?php echo esc_html( $request->reference ); ?> <small style="font-size:16px;font-weight:400"><?php echo esc_html( ucfirst( $request->request_type ) ); ?></small></h1>
            <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Request updated.', 'maljani' ); ?></p></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;max-width:1100px">
                <div class="card" style="max-width:none">
                    <h2><?php esc_html_e( 'Client intake', 'maljani' ); ?></h2>
                    <table class="widefat striped"><tbody>
                        <tr><th><?php esc_html_e( 'Client', 'maljani' ); ?></th><td><?php echo esc_html( $request->client_name ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Contact', 'maljani' ); ?></th><td><a href="mailto:<?php echo esc_attr( $request->client_email ); ?>"><?php echo esc_html( $request->client_email ); ?></a><br><?php echo esc_html( $request->client_phone ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Policy', 'maljani' ); ?></th><td><?php echo esc_html( $request->policy_number ); ?> · <?php echo esc_html( $request->insurer_name ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Event', 'maljani' ); ?></th><td><?php echo esc_html( $request->incident_type ?: '—' ); ?><?php if ( $request->incident_date ) : ?><br><?php echo esc_html( mysql2date( get_option( 'date_format' ), $request->incident_date ) ); ?><?php endif; ?></td></tr>
                        <tr><th><?php esc_html_e( 'Requested amount', 'maljani' ); ?></th><td><?php echo $request->requested_amount !== null ? esc_html( $request->currency . ' ' . number_format_i18n( $request->requested_amount, 2 ) ) : '—'; ?></td></tr>
                        <tr><th><?php esc_html_e( 'Description', 'maljani' ); ?></th><td><?php echo nl2br( esc_html( $request->description ) ); ?></td></tr>
                    </tbody></table>
                </div>
                <form class="card" style="max-width:none" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <h2><?php esc_html_e( 'Process request', 'maljani' ); ?></h2>
                    <input type="hidden" name="action" value="maljani_update_claim_request"><input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id ); ?>">
                    <?php wp_nonce_field( 'maljani_update_claim_' . $request->id ); ?>
                    <p><label><strong><?php esc_html_e( 'Workflow status', 'maljani' ); ?></strong><br><select name="status" style="width:100%"><?php foreach ( $statuses as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $request->status, $status ); ?>><?php echo esc_html( $this->labelize( $status ) ); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><strong><?php esc_html_e( 'Fee status', 'maljani' ); ?></strong><br><select name="fee_status" style="width:100%"><?php foreach ( $fee_statuses as $fee_status ) : ?><option value="<?php echo esc_attr( $fee_status ); ?>" <?php selected( $request->fee_status, $fee_status ); ?>><?php echo esc_html( $this->labelize( $fee_status ) ); ?></option><?php endforeach; ?></select></label></p>
                    <p><strong><?php esc_html_e( 'Fee at submission', 'maljani' ); ?></strong><br><?php echo esc_html( get_option( 'maljani_inv_currency', 'KSH' ) . ' ' . number_format_i18n( $request->fee_amount, 2 ) ); ?></p>
                    <p><label><strong><?php esc_html_e( 'Payment reference', 'maljani' ); ?></strong><br><input type="text" name="payment_reference" value="<?php echo esc_attr( $request->payment_reference ); ?>" style="width:100%"></label></p>
                    <p><label><strong><?php esc_html_e( 'Internal notes', 'maljani' ); ?></strong><br><textarea name="admin_notes" rows="7" style="width:100%"><?php echo esc_textarea( $request->admin_notes ); ?></textarea></label></p>
                    <?php submit_button( __( 'Update request', 'maljani' ) ); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function generate_reference() {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_claim_requests';
        do {
            $reference = 'TIC-' . gmdate( 'ym' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reference = %s", $reference ) );
        } while ( $exists );
        return $reference;
    }

    private function sanitize_date( $value ) {
        $value = sanitize_text_field( wp_unslash( $value ) );
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
    }

    private function labelize( $value ) {
        return ucwords( str_replace( '_', ' ', (string) $value ) );
    }
}
