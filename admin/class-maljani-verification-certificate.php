<?php
/**
 * Maljani Policy Verification Certificate
 * Generates a printable verification document for a policy sale.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Verification_Certificate {

    public function __construct() {
        add_action( 'admin_post_maljani_print_verification', [ $this, 'generate_certificate' ] );
    }

    /**
     * Generate and output the printable certificate HTML
     */
    public function generate_certificate() {
        $sale_id = isset( $_GET['sale_id'] ) ? intval( $_GET['sale_id'] ) : 0;
        if ( ! $sale_id ) {
            wp_die( 'Invalid sale ID.' );
        }

        $sale = self::get_sale( $sale_id );
        if ( ! $sale ) {
            wp_die( 'Sale record not found.' );
        }

        if ( ! self::user_can_view_sale( $sale ) ) {
            wp_die( 'Unauthorized access.' );
        }

        $html = self::build_certificate_html( $sale );
        if ( is_wp_error( $html ) ) {
            wp_die( esc_html( $html->get_error_message() ), '', [ 'response' => $html->get_error_data()['status'] ] );
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function get_sale( $sale_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $sale_id ) );
    }

    public static function user_can_view_sale( $sale ) {
        if ( ! $sale ) {
            return false;
        }

        $user_id = get_current_user_id();
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return $user_id > 0 && (int) $sale->agent_id === $user_id;
    }

    public static function build_certificate_html( $sale ) {
        if ( ! $sale ) {
            return new WP_Error( 'maljani_certificate_sale_missing', 'Sale record not found.', [ 'status' => 404 ] );
        }

        $policy_id = (int) $sale->policy_id;
        $policy = $policy_id > 0 ? get_post( $policy_id ) : null;
        if ( ! $policy ) {
            error_log( sprintf( 'Maljani certificate unavailable: sale_id=%d, missing policy_id=%d', $sale->id, $policy_id ) );
            return new WP_Error(
                'maljani_certificate_policy_missing',
                sprintf(
                    'Certificate unavailable because the linked insurance plan is missing. Please contact support to restore or relink the correct plan for sale #%d.',
                    $sale->id
                ),
                [ 'status' => 409 ]
            );
        }

        if ( ! class_exists( 'Maljani_PDF_Generator' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-maljani-pdf.php';
        }

        $token = Maljani_PDF_Generator::generate_verification_hash( $sale->id, $sale->policy_number, $sale->passport_number );
        $frontend_url = untrailingslashit( (string) get_option( 'maljani_frontend_app_url', '' ) );
        if ( '' === $frontend_url ) {
            $frontend_url = untrailingslashit( home_url() );
        }
        $verify_url = add_query_arg(
            [
                'sale_id' => $sale->id,
                'token'   => $token,
            ],
            $frontend_url . '/verify'
        );
        $qr_url = Maljani_PDF_Generator::generate_qr_code_url( $verify_url );

        $insurer_id = get_post_meta( $sale->policy_id, '_policy_insurer', true );
        $insurer_name = $insurer_id ? get_post_meta( $insurer_id, '_insurer_name', true ) : '';
        if ( ! $insurer_name && $insurer_id ) {
            $insurer_name = get_the_title( $insurer_id );
        }
        $insurer_name = $insurer_name ?: 'Insurer not specified';
        $insurer_logo_id = $insurer_id ? get_post_meta( $insurer_id, '_insurer_logo', true ) : null;
        $insurer_logo_url = $insurer_logo_id ? wp_get_attachment_url( $insurer_logo_id ) : '';

        $region = trim( (string) ( $sale->region ?? '' ) );
        if ( '' === $region ) {
            $regions = wp_get_post_terms( $policy_id, 'policy_region', [ 'fields' => 'names' ] );
            if ( is_wp_error( $regions ) ) {
                error_log( sprintf( 'Maljani certificate region lookup failed: sale_id=%d, policy_id=%d, code=%s', $sale->id, $policy_id, $regions->get_error_code() ) );
                return new WP_Error(
                    'maljani_certificate_region_error',
                    'Certificate region could not be loaded. Please contact support or try again later.',
                    [ 'status' => 500 ]
                );
            }
            $region = implode( ', ', $regions );
        }

        $coverage_html = wp_kses_post( wpautop( (string) get_post_meta( $policy_id, '_policy_cover_details', true ) ) );
        $benefits_html = wp_kses_post( wpautop( (string) get_post_meta( $policy_id, '_policy_benefits', true ) ) );
        $has_coverage = '' !== trim( wp_strip_all_tags( $coverage_html ) );
        $has_benefits = '' !== trim( wp_strip_all_tags( $benefits_html ) );
        $format_date = static function ( $value ) {
            $timestamp = $value ? strtotime( $value ) : false;
            return false === $timestamp ? 'Not specified' : wp_date( 'd M Y', $timestamp );
        };
        $display_status = ucwords( str_replace( '_', ' ', $sale->policy_status ?: 'unconfirmed' ) );
        $issued = wp_date( 'd M Y' );
        $cert_logo_src = '';
        $site_logo_id = get_theme_mod( 'custom_logo' );
        if ( $site_logo_id ) {
            $cert_logo_src = wp_get_attachment_image_url( $site_logo_id, [ 200, 80 ] );
        }
        if ( ! $cert_logo_src ) {
            $cert_logo_src = 'https://mtj.ivk.mybluehost.me/website_e48ea083/wp-content/uploads/2026/03/logo-type.png';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Travel Insurance Certificate - <?php echo esc_html( $sale->policy_number ?: 'Request #' . $sale->id ); ?></title>
            <style>
                * { box-sizing: border-box; }
                body { margin: 0; background: #e9eeef; color: #142d3d; font-family: 'Trebuchet MS', sans-serif; font-size: 13px; line-height: 1.5; }
                .certificate-container { max-width: 210mm; margin: 32px auto; padding: 13mm 15mm; background: #fff; border-top: 6px solid #147d78; box-shadow: 0 16px 60px #142d3d18; }
                .header { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding-bottom: 24px; border-bottom: 1px solid #cedbde; }
                .brand img { display: block; max-width: 180px; max-height: 62px; object-fit: contain; }
                .brand p { margin: 8px 0 0; color: #526973; font-size: 10px; }
                .insurer-brand { text-align: right; max-width: 45%; }
                .insurer-brand img { margin-left: auto; max-width: 150px; max-height: 54px; }
                .eyebrow, .detail-label { font-size: 9px; letter-spacing: 1.4px; text-transform: uppercase; font-weight: 700; color: #526973; }
                .insurer-name { margin-top: 6px; font-size: 12px; font-weight: 700; overflow-wrap: anywhere; }
                .certificate-title { position: relative; padding: 26px 0 22px; }
                .certificate-title .eyebrow { color: #147d78; }
                h1 { margin: 6px 0 12px; font-family: Georgia, 'Times New Roman', serif; font-size: 36px; font-weight: 400; line-height: 1.12; letter-spacing: -1px; }
                .document-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 10px; color: #526973; }
                .status { border: 1px solid #9bbdb9; padding: 2px 9px; color: #145e5a; font-weight: 700; }
                .policy-band { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 16px 20px; background: #142d3d; color: #fff; }
                .policy-band .detail-label { color: #bcd8da; }
                .policy-band .detail-value { margin-top: 5px; color: #fff; }
                .section-heading { display: flex; gap: 10px; align-items: center; margin: 24px 0 14px; font-size: 12px; }
                .section-heading span { color: #147d78; font-size: 10px; letter-spacing: 1px; }
                .section-heading::after { content: ''; height: 1px; background: #dce5e6; flex: 1; }
                .insured-name { margin: 4px 0 18px; font-family: Georgia, 'Times New Roman', serif; font-size: 26px; line-height: 1.25; overflow-wrap: anywhere; }
                .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 28px; }
                .detail-item { min-width: 0; padding-bottom: 10px; border-bottom: 1px solid #dce5e6; break-inside: avoid; }
                .detail-value { margin-top: 4px; font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }
                .coverage-intro { margin: -5px 0 12px; color: #526973; font-size: 10px; }
                .coverage-block { border-left: 3px solid #147d78; padding: 1px 16px; margin-bottom: 14px; overflow-wrap: anywhere; }
                .coverage-block h3 { margin: 6px 0; font-size: 11px; color: #147d78; }
                .policy-copy { font-size: 11px; }
                .policy-copy p { margin: 6px 0; }
                .policy-copy ul, .policy-copy ol { padding-left: 18px; }
                .policy-copy table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                .policy-copy td, .policy-copy th { border: 1px solid #cedbde; padding: 6px; text-align: left; }
                .policy-copy img { max-width: 100%; height: auto; }
                .policy-copy tr { break-inside: avoid; }
                .missing-data { color: #76501e; font-size: 11px; }
                .verification-section { display: flex; gap: 18px; align-items: center; padding: 16px; margin-top: 24px; border: 1px solid #cedbde; background: #f5f9f8; break-inside: avoid; }
                .qr-box { flex: 0 0 92px; }
                .qr-box img { display: block; width: 92px; height: 92px; }
                .verification-text { min-width: 0; }
                .verification-text h2 { margin: 0 0 5px; font-size: 12px; }
                .verification-text p { margin: 4px 0; color: #526973; font-size: 10px; }
                .verification-link { overflow-wrap: anywhere; font-size: 9px; color: #145e5a; }
                .footer { margin-top: 20px; padding-top: 12px; border-top: 1px solid #cedbde; font-size: 9px; color: #526973; break-inside: avoid; }
                .footer p { margin: 4px 0; }
                .footer-line { display: flex; justify-content: space-between; gap: 15px; margin-top: 12px; font-weight: 700; color: #142d3d; }
                .print-btn { display: block; margin: 0 auto 32px; padding: 12px 24px; border: 0; background: #142d3d; color: #fff; font: inherit; cursor: pointer; }
                .print-btn:hover { background: #145e5a; }
                .print-btn:focus-visible { outline: 3px solid #147d78; outline-offset: 4px; }
                @page { size: A4; margin: 12mm; }
                @media screen and (max-width: 600px) {
                    .certificate-container { margin: 0; padding: 24px 20px; }
                    .brand img { max-width: 130px; }
                    .insurer-brand img { max-width: 110px; }
                    h1 { font-size: 30px; }
                    .policy-band, .details-grid { grid-template-columns: 1fr; }
                    .verification-section { align-items: flex-start; gap: 12px; padding: 12px; }
                    .print-btn { margin-top: 20px; }
                }
                @media print {
                    body { background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                    .certificate-container { max-width: none; margin: 0; padding: 5mm 3mm; box-shadow: none; }
                    .header { padding-bottom: 16px; }
                    .certificate-title { padding: 18px 0; }
                    .section-heading { margin: 18px 0 10px; }
                    .insured-name { margin-bottom: 12px; }
                    .verification-section { margin-top: 18px; padding: 12px; }
                    .header, .certificate-title, .policy-band, .section-heading { break-inside: avoid; }
                    .section-heading { break-after: avoid; }
                    h3 { break-after: avoid; }
                    p { orphans: 3; widows: 3; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="certificate-container">
                <header class="header">
                    <div class="brand">
                        <img src="<?php echo esc_url( $cert_logo_src ); ?>" alt="TIC-Kenya - Travel Insurance Center-Kenya">
                        <p>Travel Insurance Center-Kenya</p>
                    </div>
                    <div class="brand insurer-brand">
                        <div class="eyebrow">Insurance provider</div>
                        <?php if ( $insurer_logo_url ) : ?>
                            <img src="<?php echo esc_url( $insurer_logo_url ); ?>" alt="<?php echo esc_attr( $insurer_name ); ?>">
                        <?php endif; ?>
                        <div class="insurer-name"><?php echo esc_html( $insurer_name ); ?></div>
                    </div>
                </header>

                <div class="certificate-title">
                    <div class="eyebrow">Your journey. Your protection.</div>
                    <h1>Travel insurance<br>certificate</h1>
                    <div class="document-meta">
                        <span>Certificate reference / MLJ-<?php echo esc_html( $sale->id ); ?></span>
                        <span class="status">Policy status: <?php echo esc_html( $display_status ); ?></span>
                    </div>
                </div>

                <div class="policy-band">
                    <div>
                        <div class="detail-label">Insurer-issued policy number</div>
                        <div class="detail-value"><?php echo esc_html( $sale->policy_number ?: 'Awaiting insurer issuance' ); ?></div>
                    </div>
                    <div>
                        <div class="detail-label">Insurance plan</div>
                        <div class="detail-value"><?php echo esc_html( $policy->post_title ); ?></div>
                    </div>
                </div>

                <main class="main-content">
                    <h2 class="section-heading"><span>01</span> Insured &amp; policy validity</h2>
                    <div class="detail-label">Full name of insured</div>
                    <div class="insured-name"><?php echo esc_html( $sale->insured_names ); ?></div>

                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Passport / ID number</div>
                            <div class="detail-value"><?php echo esc_html( $sale->passport_number ?: 'Not specified' ); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Region covered</div>
                            <div class="detail-value"><?php echo esc_html( $region ?: 'Not specified - contact support' ); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Valid from / departure</div>
                            <div class="detail-value"><?php echo esc_html( $format_date( $sale->departure ) ); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Valid until / return</div>
                            <div class="detail-value"><?php echo esc_html( $format_date( $sale->return ) ); ?></div>
                        </div>
                    </div>

                    <h2 class="section-heading"><span>02</span> Medical expenses &amp; policy benefits</h2>
                    <p class="coverage-intro">As recorded in the selected insurance plan. Limits, currency, conditions and exclusions remain those of the insurer.</p>
                    <?php if ( $has_coverage ) : ?>
                        <section class="coverage-block">
                            <h3>Coverage details</h3>
                            <div class="policy-copy"><?php echo $coverage_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </section>
                    <?php endif; ?>
                    <?php if ( $has_benefits ) : ?>
                        <section class="coverage-block">
                            <h3>Included benefits</h3>
                            <div class="policy-copy"><?php echo $benefits_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </section>
                    <?php endif; ?>
                    <?php if ( ! $has_coverage && ! $has_benefits ) : ?>
                        <p class="missing-data">Medical expense limits and benefits are not specified in this plan. Please contact the insurer for the benefit schedule.</p>
                    <?php endif; ?>

                    <div class="verification-section">
                        <div class="qr-box">
                            <img src="<?php echo esc_url( $qr_url ); ?>" alt="Verification QR Code">
                        </div>
                        <div class="verification-text">
                            <h2>Verify this certificate</h2>
                            <p>Scan the QR code or follow the secure link to check the live policy and payment status with TIC-Kenya.</p>
                            <a class="verification-link" href="<?php echo esc_url( $verify_url ); ?>"><?php echo esc_html( $verify_url ); ?></a>
                        </div>
                    </div>
                </main>

                <footer class="footer">
                    <p>This document records the insurance application and its current status. It does not replace the insurer-issued policy schedule. Coverage applies only as confirmed by the insurer and is subject to the full policy terms, exclusions and applicable limits.</p>
                    <div class="footer-line">
                        <span>Prepared by TIC-Kenya</span>
                        <span>Generated / <?php echo esc_html( $issued ); ?></span>
                    </div>
                </footer>
            </div>

            <button type="button" class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
        </body>
        </html>
        <?php

        return (string) ob_get_clean();
    }
}
new Maljani_Verification_Certificate();
