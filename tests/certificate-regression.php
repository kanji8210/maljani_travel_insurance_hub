<?php
/**
 * Standalone certificate regression checks: php tests/certificate-regression.php
 * WordPress doubles keep these checks independent of database/customer records.
 */
if ( PHP_SAPI !== 'cli' ) {
    exit;
}

define( 'ABSPATH', __DIR__ );

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code, $message, $data ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    public function get_param( $key ) { return $key === 'sale_id' ? 31 : null; }
}
class WP_REST_Response {
    public $data;
    public $status;
    public function __construct( $data, $status ) {
        $this->data = $data;
        $this->status = $status;
    }
}
class Maljani_PDF_Generator {
    public static function generate_verification_hash( $id, $number, $passport ) { return 'test-token'; }
    public static function generate_qr_code_url( $url ) { return $url; }
}
class Certificate_Test_DB {
    public $prefix = 'wp_';
    public $sale;
    public function prepare( $sql, $id ) { return $sql; }
    public function get_row( $sql ) { return $this->sale; }
}
function add_action( $hook, $callback ) {}
function plugin_dir_path( $path ) { return dirname( $path ) . DIRECTORY_SEPARATOR; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_current_user_id() { return 7; }
function current_user_can( $capability ) { return false; }
function get_post( $id ) {
    $GLOBALS['certificate_post_lookups'][] = $id;
    return $id === 42 ? (object) [ 'post_title' => 'Existing plan' ] : null;
}
function get_option( $key, $default ) { return $default; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function home_url() { return 'https://example.test'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['certificate_meta'][ $id ][ $key ] ?? ''; }
function get_the_title( $id ) { return 'Test Insurance Company'; }
function wp_get_attachment_url( $id ) { return 'https://example.test/insurer-logo.png'; }
function wp_get_attachment_image_url( $id, $size ) { return 'https://example.test/tick-logo.png'; }
function wp_get_post_terms( $id, $taxonomy, $args ) { return $GLOBALS['certificate_regions'] ?? [ 'Schengen' ]; }
function wp_date( $format, $timestamp = null ) { return date( $format, $timestamp ?? time() ); }
function wpautop( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_kses_post( $value ) {
    $GLOBALS['certificate_sanitized'][] = $value;
    return strip_tags( $value, '<p><strong><table><tr><td><th><ul><li>' );
}
function get_theme_mod( $key ) { return false; }
function get_site_icon_url( $size ) { return ''; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function wp_die( $message, $title = '', $args = [] ) {
    throw new RuntimeException( $message, $args['response'] ?? 500 );
}
function certificate_check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

require_once dirname( __DIR__ ) . '/admin/class-maljani-verification-certificate.php';
require_once dirname( __DIR__ ) . '/includes/api/class-maljani-api-endpoints.php';

$wpdb = new Certificate_Test_DB();
$api = new Maljani_API_Endpoints();
$request = new WP_REST_Request();
$sale = (object) [
    'id' => 31,
    'agent_id' => 7,
    'policy_id' => 42,
    'payment_status' => 'confirmed',
    'policy_status' => 'active',
    'policy_number' => 'TEST-POLICY',
    'passport_number' => 'TEST-PASSPORT',
    'insured_names' => 'Test Insured',
    'departure' => '2026-10-23',
    'return' => '2026-10-30',
];

$response = $api->serve_certificate( $request );
certificate_check( $response->status === 404, 'Missing sale must remain HTTP 404.' );
certificate_check(
    Maljani_Verification_Certificate::build_certificate_html( null )->get_error_data()['status'] === 404,
    'Renderer must report a missing sale explicitly.'
);

$wpdb->sale = clone $sale;
$wpdb->sale->agent_id = 8;
certificate_check( $api->serve_certificate( $request )->status === 403, 'Ownership must remain enforced.' );

$wpdb->sale = clone $sale;
$wpdb->sale->payment_status = 'pending';
certificate_check( $api->serve_certificate( $request )->status === 409, 'Payment confirmation must remain required.' );

foreach ( [ 0, -1, 999 ] as $policy_id ) {
    $wpdb->sale = clone $sale;
    $wpdb->sale->policy_id = $policy_id;
    $GLOBALS['certificate_post_lookups'] = [];
    $response = $api->serve_certificate( $request );
    certificate_check( $response->status === 409, 'Missing policy must return HTTP 409.' );
    certificate_check( $response->data['code'] === 'maljani_certificate_policy_missing', 'Missing policy must have a stable code.' );
    certificate_check( strpos( $response->data['error'], 'sale #31' ) !== false, 'Repair guidance must identify the sale.' );
    certificate_check( ! isset( $response->data['html'] ), 'Missing policy must not produce a certificate.' );
    certificate_check(
        $GLOBALS['certificate_post_lookups'] === ( $policy_id > 0 ? [ $policy_id ] : [] ),
        'Invalid IDs must not fall back to the current WordPress post.'
    );
}

$_GET['sale_id'] = 31;
try {
    ( new Maljani_Verification_Certificate() )->generate_certificate();
    throw new RuntimeException( 'Admin print must stop on a missing policy.' );
} catch ( RuntimeException $error ) {
    certificate_check( $error->getCode() === 409, 'Admin print must display the conflict status.' );
    certificate_check( strpos( $error->getMessage(), 'restore or relink' ) !== false, 'Admin print must display repair guidance.' );
}

$wpdb->sale = clone $sale;
$response = $api->serve_certificate( $request );
certificate_check( $response->status === 200, 'Existing paid sale must still render successfully.' );
certificate_check( strpos( $response->data['html'], 'Existing plan' ) !== false, 'Certificate must retain the actual plan title.' );
certificate_check( strpos( $response->data['html'], 'TEST-POLICY' ) !== false, 'Certificate must retain the policy number.' );
certificate_check( strpos( $response->data['html'], '/verify?sale_id=31&amp;token=test-token' ) !== false, 'Certificate must retain its verification link.' );
certificate_check( strpos( $response->data['html'], '23 Oct 2026' ) !== false, 'Certificate must display the departure date.' );
certificate_check( strpos( $response->data['html'], '30 Oct 2026' ) !== false, 'Certificate must display the return date.' );
certificate_check( strpos( $response->data['html'], 'Schengen' ) !== false, 'Certificate must fall back to policy region terms.' );
certificate_check( strpos( $response->data['html'], 'Medical expense limits and benefits are not specified' ) !== false, 'Missing medical limits must be explicit, never invented.' );
certificate_check( strpos( $response->data['html'], 'Insurer not specified' ) !== false, 'TIC-Kenya must not be substituted for a missing insurer.' );

$GLOBALS['certificate_meta'] = [
    42 => [
        '_policy_insurer' => 55,
        '_policy_cover_details' => '<p>Medical expenses abroad: <strong>EUR 50,000</strong></p>',
        '_policy_benefits' => '<table><tr><td>Emergency medical repatriation</td><td>EUR 25,000</td></tr></table>',
    ],
    55 => [ '_insurer_name' => 'Test & Insurance', '_insurer_logo' => 99 ],
];
$GLOBALS['certificate_sanitized'] = [];
$wpdb->sale->region = 'Worldwide excluding USA & Canada';
$wpdb->sale->insured_names = 'Test <script>not executable</script>';
$wpdb->sale->passport_number = '<TEST-PASSPORT>';
$response = $api->serve_certificate( $request );
$html = $response->data['html'];
certificate_check( count( $GLOBALS['certificate_sanitized'] ) === 2, 'Both policy rich-text fields must pass through the WordPress sanitizer.' );
foreach ( [
    'logo-type.png',
    'insurer-logo.png',
    'Test &amp; Insurance',
    'Worldwide excluding USA &amp; Canada',
    '<strong>EUR 50,000</strong>',
    'EUR 25,000',
    'Test &lt;script&gt;not executable&lt;/script&gt;',
    '&lt;TEST-PASSPORT&gt;',
    '@page { size: A4;',
    'Print / Save as PDF',
] as $expected ) {
    certificate_check( strpos( $html, $expected ) !== false, 'Certificate content missing: ' . $expected );
}
certificate_check( strpos( $html, '>Schengen<' ) === false, 'Recorded sale region must take precedence over current plan regions.' );

$wpdb->sale->region = '';
$GLOBALS['certificate_regions'] = new WP_Error( 'term_lookup_failed', 'Database failure', [] );
certificate_check( $api->serve_certificate( $request )->status === 500, 'Taxonomy failures must be explicit.' );
$GLOBALS['certificate_regions'] = [];
$wpdb->sale->departure = '';
$wpdb->sale->return = 'not-a-date';
$wpdb->sale->policy_number = '';
$response = $api->serve_certificate( $request );
certificate_check( strpos( $response->data['html'], 'Not specified - contact support' ) !== false, 'Missing region must be explicit.' );
certificate_check( strpos( $response->data['html'], '01 Jan 1970' ) === false, 'Invalid dates must not be formatted as the epoch.' );
certificate_check( strpos( $response->data['html'], 'Awaiting insurer issuance' ) !== false, 'Unissued requests must not invent policy numbers.' );

if ( in_array( '--preview', $argv, true ) ) {
    echo $html;
} else {
    echo "PASS: certificate content, branding, regions, benefits, dates, missing-policy errors, admin errors, ownership and payment checks.\n";
}
