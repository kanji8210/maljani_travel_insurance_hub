<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Claims_Portal {
    private const SHORTCODE = 'maljani_claims_portal';
    private const MAX_CLAIM_SUPPORTING_DOCUMENTS = 16;

    public static function get_portal_url() {
        $page_id = absint( get_option( 'maljani_page_claims_portal' ) );
        $url = $page_id ? get_permalink( $page_id ) : '';

        return $url ?: home_url( '/claims-refunds/' );
    }

    public function __construct() {
        add_shortcode( self::SHORTCODE, [ $this, 'render_portal' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_post_maljani_submit_claim_request', [ $this, 'handle_submission' ] );
        add_action( 'admin_post_nopriv_maljani_submit_claim_request', [ $this, 'handle_submission' ] );
        add_action( 'admin_post_maljani_update_claim_request', [ $this, 'handle_admin_update' ] );
        add_action( 'admin_post_maljani_download_claim_proof', [ $this, 'download_proof_document' ] );
        add_action( 'admin_post_maljani_download_claim_document', [ $this, 'download_supporting_document' ] );
        add_action( 'update_option_maljani_claim_assistance_fee', [ $this, 'sync_claim_fee_configuration' ], 10, 2 );
        add_action( 'update_option_maljani_inv_currency', [ $this, 'sync_claim_fee_currency' ], 10, 2 );
    }

    public function register_rest_routes() {
        register_rest_route( 'maljani/v1', '/claims/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_config' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'maljani/v1', '/admin/claim-fees', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_config' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'maljani/v1', '/claims', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_submit_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'maljani/v1', '/claims/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_submit_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'maljani/v1', '/refunds/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_submit_request' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_get_config( WP_REST_Request $request ) {
        $configuration = $this->get_active_claim_fee();
        $claim_form = $this->get_insurer_claim_form(
            sanitize_text_field( (string) $request->get_param( 'insurer_name' ) )
        );
        return new WP_REST_Response( [
            'id'             => $configuration['id'],
            'insurance_type' => 'TRAVEL',
            'fee'            => $configuration['fee_amount'],
            'fee_amount'     => $configuration['fee_amount'],
            'currency'       => $configuration['currency'],
            'is_active'      => true,
            'updated_at'     => $configuration['updated_at'],
            'nonce'          => wp_create_nonce( 'maljani_submit_claim_request' ),
            'claim_form_url' => $claim_form['url'] ?? null,
            'claim_form_label' => $claim_form['label'] ?? null,
            'claim_documents_max' => self::MAX_CLAIM_SUPPORTING_DOCUMENTS,
            'insurers'       => $this->get_available_insurers(),
        ] );
    }

    public function sync_claim_fee_configuration( $old_value, $new_value ) {
        $this->insert_claim_fee_configuration(
            max( 0, (float) $new_value ),
            sanitize_text_field( get_option( 'maljani_inv_currency', 'KES' ) )
        );
    }

    public function sync_claim_fee_currency( $old_value, $new_value ) {
        $this->insert_claim_fee_configuration(
            max( 0, (float) get_option( 'maljani_claim_assistance_fee', 0 ) ),
            sanitize_text_field( $new_value )
        );
    }

    public function rest_submit_request( WP_REST_Request $request ) {
        $nonce = sanitize_text_field( $request->get_header( 'X-Maljani-Nonce' ) );
        if ( ! wp_verify_nonce( $nonce, 'maljani_submit_claim_request' ) ) {
            return new WP_Error( 'maljani_invalid_claim_nonce', __( 'Invalid request.', 'maljani' ), [ 'status' => 403 ] );
        }

        if ( ! $this->check_submission_rate_limit() ) {
            return new WP_Error( 'maljani_claim_rate_limit', __( 'Too many requests. Please try again later.', 'maljani' ), [ 'status' => 429 ] );
        }

        $data = (array) $request->get_body_params();
        if ( empty( $data ) ) {
            $data = (array) $request->get_json_params();
        }
        $result = $this->create_request( $data, (array) $request->get_file_params() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success'   => true,
            'reference' => $result['reference'],
            'checkout'  => $result['checkout'] ?? null,
        ], 201 );
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            wp_enqueue_style(
                'maljani-claims-portal',
                plugin_dir_url( __FILE__ ) . 'assets/css/claims-portal.css',
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
                "SELECT COUNT(*) FROM {$table} WHERE LOWER(status) IN ('pending','pending_review','pending_fee','processing','documents_required','in_review','submitted','new','awaiting_payment')"
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
        $result = $this->create_request( wp_unslash( $_POST ) );
        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'claim_error', $result->get_error_code(), $redirect ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'claim_submitted', rawurlencode( $result['reference'] ), remove_query_arg( [ 'claim_error', 'claim_submitted' ], $redirect ) ) );
        exit;
    }

    private function create_request( array $data, array $files = [] ) {
        $request_type = sanitize_key( $data['request_type'] ?? 'claim' );
        $request_type = in_array( $request_type, [ 'claim', 'refund' ], true ) ? $request_type : 'claim';
        $required = [ 'client_name', 'client_email', 'client_phone', 'policy_number', 'insurer_name' ];
        if ( 'claim' === $request_type ) {
            $required[] = 'description';
        }
        foreach ( $required as $field ) {
            if ( empty( trim( (string) ( $data[ $field ] ?? '' ) ) ) ) {
                return new WP_Error( 'required', __( 'Complete all required fields.', 'maljani' ), [ 'status' => 400 ] );
            }
        }

        $email = sanitize_email( $data['client_email'] );
        if ( ! is_email( $email ) || empty( $data['consent'] ) ) {
            return new WP_Error( 'invalid', __( 'Enter a valid email and accept the authorization.', 'maljani' ), [ 'status' => 400 ] );
        }

        $refund_reason = null;
        $payout_method = null;
        $payout_details = null;
        $proof_document_url = null;
        $supporting_documents = [];
        $parsed_payout = $this->parse_payout_details( $data );
        if ( is_wp_error( $parsed_payout ) ) {
            return $parsed_payout;
        }
        $payout_method = $parsed_payout['method'];
        $payout_details = $parsed_payout['details'];
        if ( 'refund' === $request_type ) {
            $allowed_reasons = [ 'VISA_REJECTION', 'TRIP_CANCELLATION', 'DUPLICATE_PURCHASE', 'OTHER' ];
            $refund_reason = strtoupper( sanitize_key( $data['reason'] ?? '' ) );
            if ( ! in_array( $refund_reason, $allowed_reasons, true ) ) {
                return new WP_Error( 'invalid_reason', __( 'Select a valid cancellation reason.', 'maljani' ), [ 'status' => 400 ] );
            }

            $proof_file = $files['proof_document'] ?? null;
            if ( 'VISA_REJECTION' === $refund_reason && empty( $proof_file['tmp_name'] ) ) {
                return new WP_Error( 'proof_required', __( 'Upload the visa rejection letter or passport stamp.', 'maljani' ), [ 'status' => 400 ] );
            }
            if ( ! empty( $proof_file['tmp_name'] ) ) {
                $upload = $this->upload_proof_document( $proof_file );
                if ( is_wp_error( $upload ) ) {
                    return $upload;
                }
                $proof_document_url = $upload;
            }
        }

        if ( 'claim' === $request_type ) {
            $allowed_claim_types = [ 'MEDICAL_EXPENSE', 'LOST_LUGGAGE', 'FLIGHT_DELAY', 'TRIP_INTERRUPTION', 'PERSONAL_ACCIDENT', 'OTHER' ];
            $claim_type = strtoupper( sanitize_key( $data['claim_type'] ?? $data['incident_type'] ?? '' ) );
            if ( ! in_array( $claim_type, $allowed_claim_types, true ) ) {
                return new WP_Error( 'invalid_claim_type', __( 'Select a valid claim incident type.', 'maljani' ), [ 'status' => 400 ] );
            }
            if ( strlen( trim( (string) $data['description'] ) ) < 20 ) {
                return new WP_Error( 'claim_description_short', __( 'Describe the incident in at least 20 characters.', 'maljani' ), [ 'status' => 400 ] );
            }

            $claim_files = $this->normalize_uploaded_files( $files['supporting_documents'] ?? [] );
            if ( empty( $claim_files ) ) {
                return new WP_Error( 'claim_documents_required', __( 'Upload at least one supporting document.', 'maljani' ), [ 'status' => 400 ] );
            }
            if ( count( $claim_files ) > self::MAX_CLAIM_SUPPORTING_DOCUMENTS ) {
                return new WP_Error( 'claim_documents_limit', sprintf( __( 'Upload no more than %d supporting documents.', 'maljani' ), self::MAX_CLAIM_SUPPORTING_DOCUMENTS ), [ 'status' => 400 ] );
            }
            foreach ( $claim_files as $claim_file ) {
                $uploaded_document = $this->upload_proof_document( $claim_file );
                if ( is_wp_error( $uploaded_document ) ) {
                    $this->delete_private_documents( $supporting_documents );
                    return $uploaded_document;
                }
                $supporting_documents[] = $uploaded_document;
            }
        }

        $currency = strtoupper( sanitize_text_field( $data['currency'] ?? 'KSH' ) );
        $currency = in_array( $currency, [ 'KSH', 'USD', 'EUR', 'GBP' ], true ) ? $currency : 'KSH';
        $fee_configuration = $this->get_active_claim_fee();
        $fee = $fee_configuration['fee_amount'];
        $reference = $this->generate_reference();

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'maljani_claim_requests',
            [
                'reference'        => $reference,
                'user_id'          => get_current_user_id(),
                'request_type'     => $request_type,
                'client_name'      => sanitize_text_field( $data['client_name'] ),
                'client_email'     => $email,
                'client_phone'     => sanitize_text_field( $data['client_phone'] ),
                'policy_number'    => sanitize_text_field( $data['policy_number'] ),
                'insurer_name'     => sanitize_text_field( $data['insurer_name'] ),
                'incident_date'    => $this->sanitize_date( $data['cancellation_date'] ?? $data['incident_date'] ?? '' ),
                'incident_type'    => sanitize_text_field( $claim_type ?? $data['incident_type'] ?? '' ),
                'refund_reason'    => $refund_reason,
                'payout_method'    => $payout_method,
                'payout_account_details' => $payout_details ? wp_json_encode( $payout_details ) : null,
                'proof_document_url' => $proof_document_url,
                'supporting_docs_urls' => $supporting_documents ? wp_json_encode( $supporting_documents ) : null,
                'requested_amount' => empty( $data['requested_amount'] ) ? null : max( 0, (float) $data['requested_amount'] ),
                'currency'         => $currency,
                'description'      => sanitize_textarea_field( $data['description'] ?? $refund_reason ?? '' ),
                'fee_amount'       => $fee,
                'fee_status'       => $fee > 0 ? 'pending' : 'not_required',
                'payment_status'   => $fee > 0 ? 'PENDING' : 'PAID',
                'status'           => 'refund' === $request_type ? 'PENDING_REVIEW' : ( $fee > 0 ? 'PENDING_FEE' : 'PROCESSING' ),
                'consent'          => 1,
            ]
        );

        if ( ! $inserted ) {
            $failed_uploads = $supporting_documents;
            if ( $proof_document_url ) {
                $failed_uploads[] = $proof_document_url;
            }
            $this->delete_private_documents( $failed_uploads );
            return new WP_Error( 'save', __( 'The request could not be saved.', 'maljani' ), [ 'status' => 500 ] );
        }

        $admin_email = get_option( 'admin_email' );
        wp_mail(
            $admin_email,
            sprintf( '[%s] New %s assistance request', $reference, ucfirst( $request_type ) ),
            sprintf( "A new request was submitted by %s (%s).\n\nReview: %s", sanitize_text_field( $data['client_name'] ), $email, admin_url( 'admin.php?page=maljani-claims&request_id=' . $wpdb->insert_id ) )
        );

        return [
            'reference' => $reference,
            'id'        => (int) $wpdb->insert_id,
            'checkout'  => 'claim' === $request_type ? [
                'amount'      => $fee,
                'currency'    => $fee_configuration['currency'],
                'status'      => $fee > 0 ? 'PENDING' : 'NOT_REQUIRED',
                'reference'   => $reference,
                'payment_url' => null,
            ] : null,
        ];
    }

    public function handle_admin_update() {
        if ( ! current_user_can( 'edit_maljani_policies' ) ) {
            wp_die( esc_html__( 'You are not allowed to update requests.', 'maljani' ), 403 );
        }

        $request_id = absint( $_POST['request_id'] ?? 0 );
        check_admin_referer( 'maljani_update_claim_' . $request_id );

        $allowed_statuses = [ 'new', 'awaiting_payment', 'documents_required', 'in_review', 'submitted', 'approved', 'rejected', 'closed', 'PENDING_FEE', 'PROCESSING', 'SUBMITTED_TO_UNDERWRITER', 'APPROVED', 'REJECTED', 'PENDING_REVIEW', 'APPROVED_BY_INSURER', 'PROCESSING_PAYOUT', 'COMPLETED' ];
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
        $allowed_statuses = [ 'new', 'awaiting_payment', 'documents_required', 'in_review', 'submitted', 'approved', 'rejected', 'closed', 'PENDING_FEE', 'PROCESSING', 'SUBMITTED_TO_UNDERWRITER', 'APPROVED', 'REJECTED', 'PENDING_REVIEW', 'APPROVED_BY_INSURER', 'PROCESSING_PAYOUT', 'COMPLETED' ];
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

        $statuses = 'refund' === $request->request_type
            ? [ 'PENDING_REVIEW', 'APPROVED_BY_INSURER', 'PROCESSING_PAYOUT', 'COMPLETED', 'REJECTED' ]
            : [ 'PENDING_FEE', 'PROCESSING', 'SUBMITTED_TO_UNDERWRITER', 'APPROVED', 'REJECTED' ];
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
                        <?php if ( $request->payout_method ) : ?>
                            <tr><th><?php esc_html_e( 'Payout method', 'maljani' ); ?></th><td><?php echo esc_html( $this->labelize( $request->payout_method ) ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Payout details', 'maljani' ); ?></th><td><pre style="white-space:pre-wrap;margin:0"><?php echo esc_html( wp_json_encode( json_decode( $request->payout_account_details, true ), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
                        <?php endif; ?>
                        <?php if ( 'refund' === $request->request_type ) : ?>
                            <tr><th><?php esc_html_e( 'Refund reason', 'maljani' ); ?></th><td><?php echo esc_html( $this->labelize( $request->refund_reason ) ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Proof document', 'maljani' ); ?></th><td><?php if ( $request->proof_document_url ) : ?><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=maljani_download_claim_proof&request_id=' . $request->id ), 'maljani_download_claim_proof_' . $request->id ) ); ?>"><?php esc_html_e( 'Download document', 'maljani' ); ?></a><?php else : ?>—<?php endif; ?></td></tr>
                        <?php else : ?>
                            <tr><th><?php esc_html_e( 'Supporting documents', 'maljani' ); ?></th><td>
                                <?php $documents = json_decode( $request->supporting_docs_urls ?: '[]', true ); ?>
                                <?php if ( $documents ) : foreach ( $documents as $index => $document ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=maljani_download_claim_document&request_id=' . $request->id . '&document=' . $index ), 'maljani_download_claim_document_' . $request->id . '_' . $index ) ); ?>"><?php echo esc_html( sprintf( __( 'Document %d', 'maljani' ), $index + 1 ) ); ?></a><br>
                                <?php endforeach; else : ?>—<?php endif; ?>
                            </td></tr>
                        <?php endif; ?>
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

    private function get_active_claim_fee() {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_claim_fee_configurations';
        $configuration = null;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $configuration = $wpdb->get_row(
                "SELECT id, fee_amount, currency, updated_at FROM {$table} WHERE insurance_type = 'TRAVEL' AND is_active = 1 ORDER BY id DESC LIMIT 1",
                ARRAY_A
            );
        }

        return [
            'id'         => isset( $configuration['id'] ) ? (int) $configuration['id'] : 0,
            'fee_amount' => max( 0, (float) ( $configuration['fee_amount'] ?? get_option( 'maljani_claim_assistance_fee', 0 ) ) ),
            'currency'   => sanitize_text_field( $configuration['currency'] ?? get_option( 'maljani_inv_currency', 'KES' ) ),
            'updated_at' => $configuration['updated_at'] ?? current_time( 'mysql' ),
        ];
    }

    private function insert_claim_fee_configuration( $fee, $currency ) {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_claim_fee_configurations';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return;
        }

        $wpdb->query( "UPDATE {$table} SET is_active = 0 WHERE insurance_type = 'TRAVEL' AND is_active = 1" );
        $wpdb->insert( $table, [
            'insurance_type' => 'TRAVEL',
            'fee_amount'     => max( 0, (float) $fee ),
            'currency'       => strtoupper( sanitize_text_field( $currency ?: 'KES' ) ),
            'is_active'      => 1,
        ] );
    }

    private function normalize_uploaded_files( array $files ) {
        if ( empty( $files['name'] ) ) {
            return [];
        }
        if ( ! is_array( $files['name'] ) ) {
            return [ $files ];
        }

        $normalized = [];
        foreach ( array_keys( $files['name'] ) as $index ) {
            if ( UPLOAD_ERR_NO_FILE === (int) ( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ) ) {
                continue;
            }
            $normalized[] = [
                'name'     => $files['name'][ $index ] ?? '',
                'type'     => $files['type'][ $index ] ?? '',
                'tmp_name' => $files['tmp_name'][ $index ] ?? '',
                'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][ $index ] ?? 0,
            ];
        }
        return $normalized;
    }

    private function delete_private_documents( array $urls ) {
        $upload_dir = wp_upload_dir();
        $private_root = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'maljani-private/' );
        foreach ( $urls as $url ) {
            $relative = ltrim( str_replace( $upload_dir['baseurl'], '', $url ), '/' );
            $path = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $relative );
            if ( 0 === strpos( $path, $private_root ) && is_file( $path ) ) {
                wp_delete_file( $path );
            }
        }
    }

    private function upload_proof_document( array $file ) {
        if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'upload_failed', __( 'The proof document could not be uploaded.', 'maljani' ), [ 'status' => 400 ] );
        }
        if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
            return new WP_Error( 'upload_too_large', __( 'The proof document must be no larger than 5 MB.', 'maljani' ), [ 'status' => 400 ] );
        }

        $allowed_mimes = [ 'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ];
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
        if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed_mimes, true ) ) {
            return new WP_Error( 'upload_type', __( 'Upload a PDF, JPG, or PNG proof document.', 'maljani' ), [ 'status' => 400 ] );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload( $file, [ 'test_form' => false, 'mimes' => $allowed_mimes ] );
        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'upload_failed', sanitize_text_field( $uploaded['error'] ), [ 'status' => 400 ] );
        }

        $upload_dir = wp_upload_dir();
        $private_dir = trailingslashit( $upload_dir['basedir'] ) . 'maljani-private';
        if ( ! wp_mkdir_p( $private_dir ) ) {
            wp_delete_file( $uploaded['file'] );
            return new WP_Error( 'upload_storage', __( 'Secure document storage is unavailable.', 'maljani' ), [ 'status' => 500 ] );
        }
        if ( ! file_exists( $private_dir . '/.htaccess' ) ) {
            file_put_contents( $private_dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        }
        if ( ! file_exists( $private_dir . '/index.php' ) ) {
            file_put_contents( $private_dir . '/index.php', "<?php\nhttp_response_code( 404 );\nexit;\n" );
        }

        $private_name = wp_generate_uuid4() . '.' . $checked['ext'];
        $private_path = trailingslashit( $private_dir ) . $private_name;
        if ( ! rename( $uploaded['file'], $private_path ) ) {
            wp_delete_file( $uploaded['file'] );
            return new WP_Error( 'upload_storage', __( 'The proof document could not be secured.', 'maljani' ), [ 'status' => 500 ] );
        }
        return esc_url_raw( trailingslashit( $upload_dir['baseurl'] ) . 'maljani-private/' . $private_name );
    }

    public function download_proof_document() {
        if ( ! current_user_can( 'edit_maljani_policies' ) ) {
            wp_die( esc_html__( 'You are not allowed to download this document.', 'maljani' ), 403 );
        }
        $request_id = absint( $_GET['request_id'] ?? 0 );
        check_admin_referer( 'maljani_download_claim_proof_' . $request_id );

        global $wpdb;
        $url = $wpdb->get_var( $wpdb->prepare(
            "SELECT proof_document_url FROM {$wpdb->prefix}maljani_claim_requests WHERE id = %d",
            $request_id
        ) );
        $upload_dir = wp_upload_dir();
        $relative = $url ? ltrim( str_replace( $upload_dir['baseurl'], '', $url ), '/' ) : '';
        $path = $relative ? wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $relative ) : '';
        $private_root = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'maljani-private/' );
        if ( ! $path || 0 !== strpos( $path, $private_root ) || ! is_file( $path ) ) {
            wp_die( esc_html__( 'Document not found.', 'maljani' ), 404 );
        }

        $mime = wp_check_filetype( $path )['type'] ?: 'application/octet-stream';
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="refund-proof-' . $request_id . '.' . pathinfo( $path, PATHINFO_EXTENSION ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }

    public function download_supporting_document() {
        if ( ! current_user_can( 'edit_maljani_policies' ) ) {
            wp_die( esc_html__( 'You are not allowed to download this document.', 'maljani' ), 403 );
        }
        $request_id = absint( $_GET['request_id'] ?? 0 );
        $document_index = absint( $_GET['document'] ?? 0 );
        check_admin_referer( 'maljani_download_claim_document_' . $request_id . '_' . $document_index );

        global $wpdb;
        $encoded_documents = $wpdb->get_var( $wpdb->prepare(
            "SELECT supporting_docs_urls FROM {$wpdb->prefix}maljani_claim_requests WHERE id = %d AND request_type = 'claim'",
            $request_id
        ) );
        $documents = json_decode( $encoded_documents ?: '[]', true );
        $url = is_array( $documents ) ? ( $documents[ $document_index ] ?? '' ) : '';
        $this->stream_private_document( $url, 'claim-document-' . $request_id . '-' . ( $document_index + 1 ) );
    }

    private function stream_private_document( $url, $download_name ) {
        $upload_dir = wp_upload_dir();
        $relative = $url ? ltrim( str_replace( $upload_dir['baseurl'], '', $url ), '/' ) : '';
        $path = $relative ? wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $relative ) : '';
        $private_root = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'maljani-private/' );
        if ( ! $path || 0 !== strpos( $path, $private_root ) || ! is_file( $path ) ) {
            wp_die( esc_html__( 'Document not found.', 'maljani' ), 404 );
        }

        $mime = wp_check_filetype( $path )['type'] ?: 'application/octet-stream';
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_name ) . '.' . pathinfo( $path, PATHINFO_EXTENSION ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }

    private function check_submission_rate_limit() {
        $address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
        $key = 'maljani_refund_' . md5( $address );
        $attempts = (int) get_transient( $key );
        if ( $attempts >= 5 ) {
            return false;
        }
        set_transient( $key, $attempts + 1, HOUR_IN_SECONDS );
        return true;
    }

    private function sanitize_date( $value ) {
        $value = sanitize_text_field( wp_unslash( $value ) );
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
    }

    private function parse_payout_details( array $data ) {
        $payout_method = strtoupper( sanitize_key( $data['payout_method'] ?? '' ) );
        if ( 'MPESA' === $payout_method ) {
            $mpesa_phone = preg_replace( '/[^0-9+]/', '', (string) ( $data['mpesa_phone'] ?? '' ) );
            if ( ! preg_match( '/^(?:\+?254|0)[17]\d{8}$/', $mpesa_phone ) ) {
                return new WP_Error( 'invalid_mpesa', __( 'Enter a valid Safaricom mobile number.', 'maljani' ), [ 'status' => 400 ] );
            }

            return [
                'method'  => 'MPESA',
                'details' => [ 'phone' => $mpesa_phone ],
            ];
        }

        if ( 'BANK_TRANSFER' === $payout_method ) {
            $bank_fields = [ 'bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch' ];
            foreach ( $bank_fields as $bank_field ) {
                if ( empty( trim( (string) ( $data[ $bank_field ] ?? '' ) ) ) ) {
                    return new WP_Error( 'invalid_bank', __( 'Complete all bank payout fields.', 'maljani' ), [ 'status' => 400 ] );
                }
            }

            return [
                'method'  => 'BANK_TRANSFER',
                'details' => [
                    'bank_name'      => sanitize_text_field( $data['bank_name'] ),
                    'account_name'   => sanitize_text_field( $data['bank_account_name'] ),
                    'account_number' => sanitize_text_field( $data['bank_account_number'] ),
                    'branch'         => sanitize_text_field( $data['bank_branch'] ),
                ],
            ];
        }

        return new WP_Error( 'invalid_payout', __( 'Select a valid payout method.', 'maljani' ), [ 'status' => 400 ] );
    }

    private function get_insurer_claim_form( $insurer_name ) {
        if ( '' === trim( (string) $insurer_name ) ) {
            return null;
        }

        $insurer_id = $this->find_insurer_profile_by_name( $insurer_name );
        if ( $insurer_id <= 0 ) {
            return null;
        }

        $attachment_id = (int) get_post_meta( $insurer_id, '_insurer_claim_form_attachment_id', true );
        if ( $attachment_id <= 0 ) {
            return null;
        }

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            return null;
        }

        $path = get_attached_file( $attachment_id );
        return [
            'url'   => esc_url_raw( $url ),
            'label' => $path ? wp_basename( $path ) : wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ),
        ];
    }

    private function find_insurer_profile_by_name( $insurer_name ) {
        $normalized_target = strtolower( trim( (string) $insurer_name ) );
        if ( '' === $normalized_target ) {
            return 0;
        }

        $insurers = $this->get_insurer_profile_ids();

        foreach ( $insurers as $insurer_id ) {
            $title = strtolower( trim( get_the_title( $insurer_id ) ) );
            $official_name = strtolower( trim( (string) get_post_meta( $insurer_id, '_insurer_name', true ) ) );
            if ( $normalized_target === $title || $normalized_target === $official_name ) {
                return (int) $insurer_id;
            }
        }

        return 0;
    }

    private function get_available_insurers() {
        $insurer_names = [];
        foreach ( $this->get_insurer_profile_ids() as $insurer_id ) {
            $name = trim( (string) get_post_meta( $insurer_id, '_insurer_name', true ) );
            if ( '' === $name ) {
                $name = trim( get_the_title( $insurer_id ) );
            }
            if ( '' !== $name ) {
                $insurer_names[] = $name;
            }
        }

        $insurer_names = array_values( array_unique( $insurer_names ) );
        natcasesort( $insurer_names );

        return array_values( $insurer_names );
    }

    private function get_insurer_profile_ids() {
        return get_posts( [
            'post_type'      => 'insurer_profile',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    private function labelize( $value ) {
        return ucwords( str_replace( '_', ' ', (string) $value ) );
    }
}
