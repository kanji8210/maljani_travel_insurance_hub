<?php
/**
 * class-maljani-graphql-auth.php
 * Custom JWT-based Authentication Layer for WPGraphQL
 */

if (!defined('ABSPATH')) exit;

class Maljani_GraphQL_Auth {

    public function __construct() {
        // Flush WPGraphQL schema cache when plugin version changes.
        // This ensures new outputFields are immediately visible after plugin updates.
        add_action('plugins_loaded', [$this, 'maybe_flush_graphql_schema'], 1);

        // Global CORS for preflight
        add_action('init', [$this, 'handle_global_cors'], 1);

        // Register Mutations
        add_action('graphql_register_types', [$this, 'register_login_mutation']);
        add_action('graphql_register_types', [$this, 'register_registration_mutation']);
        add_action('graphql_register_types', [$this, 'register_sales_mutation']);

        // Authenticate requests
        add_filter('determine_current_user', [$this, 'authenticate_request'], 20);

        // CORS and App Security
        add_filter('graphql_response_headers_to_send', [$this, 'manage_cors'], 10);
        add_filter('allowed_http_origins', [$this, 'filter_allowed_origins'], 10);
        add_action('graphql_process_http_request', [$this, 'validate_app_request'], 10);
    }

    /**
     * Flush WPGraphQL's cached schema whenever the plugin version changes.
     * Prevents stale schemas after plugin updates that add new outputFields.
     */
    public function maybe_flush_graphql_schema(): void {
        if (!function_exists('WPGraphQL')) return;
        $stored = get_option('maljani_gql_schema_version', '');
        if ($stored !== MALJANI_VERSION) {
            \WPGraphQL::clear_schema();
            update_option('maljani_gql_schema_version', MALJANI_VERSION);
        }
    }

    /**
     * Handle global CORS for GraphQL preflight
     */
    public function handle_global_cors() {
        if (!isset($_SERVER['HTTP_ORIGIN'])) return;

        $origin = $_SERVER['HTTP_ORIGIN'];
        $allowed = $this->get_allowed_origins();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $allowed[] = 'http://localhost:5173';
            $allowed[] = 'http://localhost:5174';
        }
        $allowed = array_unique($allowed);

        if (in_array($origin, $allowed)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Maljani-App-Secret");
            header("Access-Control-Allow-Credentials: true");

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                status_header(200);
                exit;
            }
        }
    }

    /**
     * Register the login mutation
     */
    public function register_login_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('maljaniLogin', [
            'inputFields' => [
                'username' => [
                    'type' => 'String',
                    'description' => __('The username or email.', 'maljani'),
                ],
                'password' => [
                    'type' => 'String',
                    'description' => __('The password.', 'maljani'),
                ],
            ],
            'outputFields' => [
                'authToken' => ['type' => 'String'],
                'user'      => ['type' => 'User'],
                // Scalar fields bypass WPGraphQL User type permission gates
                'userName'  => ['type' => 'String'],
                'userPhone' => ['type' => 'String'],
                'userRole'  => ['type' => 'String'],
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                if (empty($input['username']) || empty($input['password'])) {
                    throw new \GraphQL\Error\UserError(__('Username and password are required.', 'maljani'));
                }

                $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                // Block check BEFORE authentication to prevent wasted DB calls
                if ($this->is_ip_blocked($ip)) {
                    throw new \GraphQL\Error\UserError(__('Your IP has been temporarily blocked due to too many failed login attempts. Please try again in 1 hour.', 'maljani'));
                }

                $user = wp_authenticate($input['username'], $input['password']);

                if (is_wp_error($user)) {
                    $this->track_failed_login($ip);
                    throw new \GraphQL\Error\UserError($user->get_error_message());
                }

                $token = $this->generate_token($user->ID);
                $this->reset_failed_login($ip);

                $roles     = $user->roles;
                $user_role = !empty($roles) ? $roles[0] : 'insured';
                $phone     = get_user_meta($user->ID, 'phone', true);

                return [
                    'authToken' => $token,
                    'user'      => $user,
                    'userName'  => $user->display_name,
                    'userPhone' => $phone ?: '',
                    'userRole'  => $user_role,
                ];
            }
        ]);
    }

    /**
     * Register the registration mutation
     */
    public function register_registration_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('maljaniRegister', [
            'inputFields' => [
                'fullName' => ['type' => 'String'],
                'email' => ['type' => 'String'],
                'password' => ['type' => 'String'],
                'accountType' => ['type' => 'String'],
                'phone' => ['type' => 'String'],
                'agencyName' => ['type' => 'String'],
            ],
            'outputFields' => [
                'authToken' => ['type' => 'String'],
                'user'      => ['type' => 'User'],
                // Scalar fields avoid WPGraphQL User type permission gate on unauthenticated requests
                'userName'  => ['type' => 'String'],
                'userRole'  => ['type' => 'String'],
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                $email = sanitize_email($input['email']);
                if (email_exists($email)) {
                    throw new \GraphQL\Error\UserError(__('An account with this email already exists.', 'maljani'));
                }

                if (empty($input['password']) || strlen($input['password']) < 8) {
                    throw new \GraphQL\Error\UserError(__('Password must be at least 8 characters.', 'maljani'));
                }

                // Allowlist account types — prevents privilege escalation
                $account_type = in_array($input['accountType'] ?? '', ['agent', 'insured'], true)
                    ? $input['accountType']
                    : 'insured';

                $user_id = wp_create_user($email, $input['password'], $email);
                if (is_wp_error($user_id)) {
                    throw new \GraphQL\Error\UserError($user_id->get_error_message());
                }

                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => sanitize_text_field($input['fullName']),
                    'display_name' => sanitize_text_field($input['fullName']),
                    'role' => ($account_type === 'agent' ? 'agent' : 'insured'),
                ]);

                // Send welcome email — wrapped in try/catch as wp_mail() may be
                // unconfigured in local/staging environments and would throw a fatal.
                try {
                    wp_new_user_notification($user_id, null, 'user');
                } catch (\Exception $e) {
                    // Non-fatal: user was created successfully, email delivery is best-effort
                }

                if ($account_type === 'agent') {
                    global $wpdb;
                    $wpdb->insert($wpdb->prefix . 'maljani_agencies', [
                        'name' => sanitize_text_field($input['agencyName'] ?: $input['fullName'] . " Agency"),
                        'contact_name' => sanitize_text_field($input['fullName']),
                        'contact_email' => $email,
                        'contact_phone' => sanitize_text_field($input['phone']),
                        'user_id' => $user_id,
                        'commission_rate' => 10.00,
                        'status' => 'pending'
                    ]);
                } else {
                    update_user_meta($user_id, 'phone', sanitize_text_field($input['phone']));
                }

                $token = $this->generate_token($user_id);
                return [
                    'authToken' => $token,
                    'user'      => get_user_by('id', $user_id),
                    'userName'  => sanitize_text_field($input['fullName']),
                    'userRole'  => $account_type,
                ];
            }
        ]);
    }

    /**
     * Register the sales mutation
     */
    public function register_sales_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('submitPolicySale', [
            'inputFields' => [
                'policyId'        => ['type' => 'Int'],
                'departure'       => ['type' => 'String'],
                'return'          => ['type' => 'String'],
                'passengers'      => ['type' => 'Int'],
                'insuredNames'    => ['type' => 'String'],
                'insuredDob'      => ['type' => 'String'],
                'passportNumber'  => ['type' => 'String'],
                'nationalId'      => ['type' => 'String'],
                'insuredPhone'    => ['type' => 'String'],
                'insuredEmail'    => ['type' => 'String'],
                'insuredAddress'  => ['type' => 'String'],
                'countryOfOrigin' => ['type' => 'String'],
                'paymentReference'=> ['type' => 'String'],
            ],
            'outputFields' => [
                'saleId' => ['type' => 'Int'],
                'policyNumber' => ['type' => 'String'],
                'amountPaid' => ['type' => 'Float'],
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                if (!is_user_logged_in()) {
                    throw new \GraphQL\Error\UserError(__('You must be logged in to purchase a policy.', 'maljani'));
                }

                global $wpdb;
                $table = $wpdb->prefix . 'policy_sale';

                $policy_id = intval($input['policyId']);
                
                // Region calculation
                $region_name = '';
                $regions = get_the_terms($policy_id, 'policy_region');
                if ($regions && !is_wp_error($regions)) {
                    $region_name = $regions[0]->name;
                }

                // Passengers
                $passengers = max(1, intval($input['passengers'] ?? 1));

                // Days calculation (same-day trip = 1 day, matching the frontend)
                $d1 = new DateTime($input['departure']);
                $d2 = new DateTime($input['return']);

                if ($d2 < $d1) {
                    throw new \GraphQL\Error\UserError(__('Return date must be on or after departure date.', 'maljani'));
                }

                $days = max(1, $d1->diff($d2)->days);

                // Premium calculation
                $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
                $premium = 0;
                if (is_array($premiums)) {
                    foreach ($premiums as $row) {
                        if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                            $premium = floatval($row['premium']);
                            break;
                        }
                    }
                }

                if ($premium <= 0) {
                    throw new \GraphQL\Error\UserError(__('No premium found for these dates.', 'maljani'));
                }

                // Fee logic mirror
                $calc_fee = function($type_key, $val_key, $legacy_pct_key, $base) use ($policy_id) {
                    $type = get_post_meta($policy_id, $type_key, true) ?: 'percent';
                    $val  = get_post_meta($policy_id, $val_key, true);
                    if ($val === '' || $val === false) {
                        $val  = floatval(get_post_meta($policy_id, $legacy_pct_key, true) ?: 0);
                        $type = 'percent';
                    } else {
                        $val = floatval($val);
                    }
                    if ($type === 'fixed') return round($val, 2);
                    return round(($base * $val) / 100, 2);
                };

                $current_user = wp_get_current_user();
                $is_agent = in_array('agent', (array)$current_user->roles);

                $maljani_comm_amount = $calc_fee('_policy_aggregator_comm_type', '_policy_aggregator_comm_value', '_policy_aggregator_comm_pct', $premium);
                $net_to_insurer = round($premium - $maljani_comm_amount, 2);

                $global_svc_type = get_option('maljani_fee_service_type', 'percent');
                $global_svc_val = floatval(get_option('maljani_fee_service_value', 0));
                $service_fee_amount = ($global_svc_type === 'fixed') ? round($global_svc_val, 2) : round(($premium * $global_svc_val) / 100, 2);
                
                // Multiply by passenger count — premium and commission are per-person, service fee is per transaction
                $amount_tot_client = $is_agent
                    ? round($premium * $passengers, 2)
                    : round(($premium * $passengers) + $service_fee_amount, 2);

                $agent_comm_amount = 0;
                if ($is_agent) {
                    $agent_comm_amount = $calc_fee('_policy_agency_comm_type', '_policy_agency_comm_value', '_policy_agency_comm_pct', $premium);
                }

                $policy_number = 'POL-GQL-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

                // Verify the policy post is published before creating a sale record
                if (get_post_status($policy_id) !== 'publish') {
                    throw new \GraphQL\Error\UserError(__('The selected policy is not available.', 'maljani'));
                }

                $wpdb->insert($table, [
                    'policy_id' => $policy_id,
                    'policy_number' => $policy_number,
                    'region' => $region_name,
                    'premium' => $premium,
                    'days' => $days,
                    'passengers' => $passengers,
                    'departure' => sanitize_text_field($input['departure']),
                    'return' => sanitize_text_field($input['return']),
                    'insured_names' => sanitize_text_field($input['insuredNames']),
                    'insured_dob' => sanitize_text_field($input['insuredDob']),
                    'passport_number' => sanitize_text_field($input['passportNumber']),
                    'national_id' => sanitize_text_field($input['nationalId']),
                    'insured_phone' => sanitize_text_field($input['insuredPhone']),
                    'insured_email' => sanitize_email($input['insuredEmail']),
                    'insured_address' => sanitize_text_field($input['insuredAddress']),
                    'country_of_origin' => sanitize_text_field($input['countryOfOrigin']),
                    'agent_id' => get_current_user_id(),
                    'agent_name' => $current_user->display_name,
                    'amount_paid' => $amount_tot_client,
                    'service_fee_amount' => $service_fee_amount,
                    'maljani_commission_amount' => $maljani_comm_amount,
                    'agent_commission_amount' => $agent_comm_amount,
                    'agent_commission_status' => 'unpaid',
                    'net_to_insurer' => $net_to_insurer,
                    'payment_reference' => sanitize_text_field($input['paymentReference'] ?? ''),
                    'payment_status' => 'pending',
                    'policy_status' => 'unconfirmed',
                    'workflow_status' => 'draft',
                    'terms' => 1
                ]);

                $sale_id = $wpdb->insert_id;

                if (!$sale_id) {
                    throw new \GraphQL\Error\UserError(__('Failed to record the sale. Please try again.', 'maljani'));
                }

                // Fire action so notifications and other integrations can react
                do_action('maljani_new_sale', $sale_id, [
                    'policy_number' => $policy_number,
                    'insured_names' => sanitize_text_field($input['insuredNames'] ?? ''),
                    'insured_email' => sanitize_email($input['insuredEmail'] ?? ''),
                    'amount_paid'   => $amount_tot_client,
                    'passengers'    => $passengers,
                ]);

                return [
                    'saleId' => $sale_id,
                    'policyNumber' => $policy_number,
                    'amountPaid' => $amount_tot_client,
                ];
            }
        ]);
    }

    /**
     * Generate a minimalist JWT token using URL-safe base64
     */
    private function generate_token($user_id) {
        $header = $this->base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64url_encode(json_encode([
            'iss'  => get_bloginfo('url'),
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => time() + (DAY_IN_SECONDS * 7), // 7 days
            'data' => [
                'user' => [
                    'id' => $user_id
                ]
            ]
        ]));

        $secret = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : AUTH_KEY;
        $signature = hash_hmac('sha256', "$header.$payload", $secret, true);
        $base64_signature = $this->base64url_encode($signature);

        return "$header.$payload.$base64_signature";
    }

    /**
     * Authenticate request based on JWT in Authorization header
     */
    public function authenticate_request($user_id) {
        // If already authenticated by WP, return it
        if ($user_id) return $user_id;

        // Get Authorization header
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '');

        if (empty($header) || strpos($header, 'Bearer ') !== 0) {
            return $user_id;
        }

        $token = substr($header, 7);
        $decoded_user_id = $this->validate_token($token);

        if ($decoded_user_id) {
            return $decoded_user_id;
        }

        return $user_id;
    }

    /**
     * Validate the JWT token and return user ID
     */
    private function validate_token($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return false;

            list($header_b64, $payload_b64, $signature_b64) = $parts;

            $secret = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : AUTH_KEY;
            $expected_signature = hash_hmac('sha256', "$header_b64.$payload_b64", $secret, true);
            $expected_signature_b64 = $this->base64url_encode($expected_signature);

            // Constant-time comparison prevents timing attacks
            if (!hash_equals($expected_signature_b64, $signature_b64)) {
                return false;
            }

            $payload = json_decode($this->base64url_decode($payload_b64), true);
            if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
                return false;
            }

            if (isset($payload['data']['user']['id'])) {
                return intval($payload['data']['user']['id']);
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Manage CORS headers for GraphQL
     */
    public function manage_cors($headers) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $this->get_allowed_origins();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $allowed[] = 'http://localhost:5173';
            $allowed[] = 'http://localhost:5174';
        }
        $allowed = array_unique($allowed);

        if (in_array($origin, $allowed) || empty($origin)) {
            $headers['Access-Control-Allow-Origin'] = $origin ?: '*';
            $headers['Access-Control-Allow-Methods'] = 'POST, GET, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Maljani-App-Secret';
            $headers['Access-Control-Allow-Credentials'] = 'true';

            // Handle preflight properly
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                foreach ($headers as $key => $value) {
                    header("$key: $value");
                }
                status_header(200);
                exit;
            }
        }

        return $headers;
    }

    /**
     * Filter allowed HTTP origins
     */
    public function filter_allowed_origins($origins) {
        $allowed = $this->get_allowed_origins();
        return !empty($allowed) ? array_merge($origins, $allowed) : $origins;
    }

    private function get_allowed_origins() {
        $raw = get_option('maljani_graphql_allowed_origins', '');
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * Validate the X-Maljani-App-Secret header
     */
    public function validate_app_request() {
        $secret = get_option('maljani_graphql_app_secret', '');
        if (empty($secret)) return; // Security layer disabled if no secret set

        $client_secret = $_SERVER['HTTP_X_MALJANI_APP_SECRET'] ?? '';
        if (!hash_equals($secret, $client_secret)) {
            wp_send_json_error([
                'errors' => [['message' => __('Invalid or missing Application Secret Key.', 'maljani')]]
            ], 403);
            exit;
        }
    }

    /**
     * Brute Force Protection Helpers
     */
    private function track_failed_login($ip) {
        if (empty($ip)) return;
        $count = intval(get_transient("mj_gql_fail_$ip") ?: 0) + 1;
        $max = intval(get_option('maljani_security_max_login_retries', 5));
        
        set_transient("mj_gql_fail_$ip", $count, HOUR_IN_SECONDS);
        
        if ($count >= $max) {
            set_transient("mj_gql_blocked_$ip", true, HOUR_IN_SECONDS);
        }
    }

    private function is_ip_blocked($ip) {
        return !empty($ip) && get_transient("mj_gql_blocked_$ip");
    }

    private function reset_failed_login($ip) {
        delete_transient("mj_gql_fail_$ip");
        delete_transient("mj_gql_blocked_$ip");
    }

    /**
     * Base64 URL Safe encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL Safe decode
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

new Maljani_GraphQL_Auth();
