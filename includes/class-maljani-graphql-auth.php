<?php
/**
 * class-maljani-graphql-auth.php
 * Custom JWT-based Authentication Layer for WPGraphQL
 */

if (!defined('ABSPATH')) exit;

class Maljani_GraphQL_Auth {

    public function __construct() {
        // Flush WPGraphQL schema cache when plugin version changes.
        // Hooked on both plugins_loaded AND graphql_init so it fires
        // even if the first admin page hasn't been visited yet.
        add_action('plugins_loaded', [$this, 'maybe_flush_graphql_schema'], 1);
        add_action('graphql_init',   [$this, 'maybe_flush_graphql_schema'], 1);

        // Global CORS for preflight
        add_action('init', [$this, 'handle_global_cors'], 1);

        // Register all types/mutations/queries with error isolation.
        // A failure in one registration must not crash the entire schema.
        $registrations = [
            'register_login_mutation',
            'register_registration_mutation',
            'register_sales_mutation',
            'register_update_sale_mutation',
            'register_update_profile_mutation',
            'register_mark_notifications_read_mutation',
            'register_my_policy_sales_query',
            'register_my_notifications_query',
            'register_agency_dashboard_query',
            'register_agent_display_settings',
        ];
        foreach ($registrations as $method) {
            add_action('graphql_register_types', function () use ($method) {
                try {
                    $this->$method();
                } catch (\Throwable $e) {
                    error_log('[Maljani GQL] FATAL in ' . $method . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                }
            });
        }

        // Diagnostic REST endpoint for debugging 502s
        add_action('rest_api_init', [$this, 'register_health_check']);

        // Authenticate requests
        add_filter('determine_current_user', [$this, 'authenticate_request'], 20);

        // CORS and App Security
        add_filter('graphql_response_headers_to_send', [$this, 'manage_cors'], 10);

        // Temporary debug endpoint — remove after issue resolved
        add_action('rest_api_init', [$this, 'register_debug_endpoint']);
        add_filter('allowed_http_origins', [$this, 'filter_allowed_origins'], 10);
        add_action('graphql_process_http_request', [$this, 'validate_app_request'], 10);
    }

    /**
     * Flush WPGraphQL's cached schema whenever the plugin version changes.
     * Prevents stale schemas after plugin updates that add new outputFields.
     */
    public function maybe_flush_graphql_schema(): void {
        // WPGraphQL is a class — function_exists() always returns false for it.
        if ( ! class_exists( 'WPGraphQL' ) ) return;
        $stored = get_option( 'maljani_gql_schema_version', '' );
        if ( $stored !== MALJANI_VERSION ) {
            // Clear the in-memory schema instance
            \WPGraphQL::clear_schema();
            // Also delete persisted schema transients (WPGraphQL stores schema as transients)
            delete_transient( 'wpgraphql_schema' );
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options}
                  WHERE option_name LIKE '_transient_wpgraphql%'
                     OR option_name LIKE '_transient_timeout_wpgraphql%'"
            );
            update_option( 'maljani_gql_schema_version', MALJANI_VERSION );
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
                'userEmail' => ['type' => 'String'],
                'userPhone' => ['type' => 'String'],
                'userRole'  => ['type' => 'String'],
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                ob_start();
                if (empty($input['username']) || empty($input['password'])) {
                    ob_end_clean();
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

                $res = [
                    'authToken' => $token,
                    'user'      => $user,
                    'userName'  => $user->display_name,
                    'userEmail' => $user->user_email,
                    'userPhone' => $phone ?: '',
                    'userRole'  => $user_role,
                ];

                $stray = ob_get_clean();
                if (!empty($stray)) {
                    error_log('[Maljani GQL] Stray output during login: ' . $stray);
                }
                return $res;
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
                ob_start();
                $email = sanitize_email($input['email']);
                if (email_exists($email)) {
                    ob_end_clean();
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
                $res = [
                    'authToken' => $token,
                    'user'      => get_user_by('id', $user_id),
                    'userName'  => sanitize_text_field($input['fullName']),
                    'userEmail' => sanitize_email($input['email']),
                    'userRole'  => $account_type,
                ];

                $stray = ob_get_clean();
                if (!empty($stray)) {
                    error_log('[Maljani GQL] Stray output during registration: ' . $stray);
                }
                return $res;
            }
        ]);
    }

    /**
     * Temporary debug endpoint — shows DB table state so we can diagnose insert failures.
     * URL: /wp-json/maljani/v1/debug-db
     * Protected by admin nonce: ?nonce=<wp_create_nonce('maljani_debug')>
     * Remove this method once the purchase error is resolved.
     */
    public function register_debug_endpoint() {
        register_rest_route('maljani/v1', '/debug-db', [
            'methods'  => 'GET',
            'callback' => function(\WP_REST_Request $request) {
                // Only admins can call this
                if (!current_user_can('manage_options')) {
                    // Also allow if a valid nonce is supplied via ?nonce=
                    $nonce = $request->get_param('nonce');
                    if (!wp_verify_nonce($nonce, 'maljani_debug')) {
                        return new \WP_Error('forbidden', 'Admins only. Pass ?nonce=<your_nonce>.', ['status' => 403]);
                    }
                }

                global $wpdb;
                $table = $wpdb->prefix . 'policy_sale';

                $table_exists  = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                $columns       = $table_exists ? $wpdb->get_results("DESCRIBE `$table`") : [];
                $db_version    = get_option('maljani_db_version', 'not set');
                $plugin_version = defined('MALJANI_VERSION') ? MALJANI_VERSION : 'undefined';
                $row_count     = $table_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`") : 0;

                return rest_ensure_response([
                    'plugin_version'  => $plugin_version,
                    'db_version'      => $db_version,
                    'table'           => $table,
                    'table_exists'    => $table_exists,
                    'row_count'       => $row_count,
                    'columns'         => array_column($columns, 'Field'),
                    'column_detail'   => $columns,
                    'missing_critical'=> array_diff(['passengers', 'days', 'policy_id', 'departure', 'return'], array_column($columns, 'Field')),
                ]);
            },
            'permission_callback' => '__return_true', // auth handled inside callback
        ]);
    }

    /**
     * Health-check endpoint to diagnose 502 Bad Gateway issues.
     * URL: /wp-json/maljani/v1/health
     * Returns plugin load status, WPGraphQL availability, table existence, and PHP info.
     */
    public function register_health_check() {
        register_rest_route('maljani/v1', '/health', [
            'methods'  => 'GET',
            'callback' => function () {
                global $wpdb;
                $checks = [];

                // PHP info
                $checks['php_version']    = PHP_VERSION;
                $checks['memory_limit']   = ini_get('memory_limit');
                $checks['memory_used_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
                $checks['plugin_version'] = defined('MALJANI_VERSION') ? MALJANI_VERSION : 'undefined';

                // WPGraphQL
                $checks['wpgraphql_active'] = class_exists('WPGraphQL');
                $checks['wpgraphql_version'] = defined('WPGRAPHQL_VERSION') ? WPGRAPHQL_VERSION : 'unknown';

                // Tables
                $tables = ['policy_sale', 'maljani_agencies', 'maljani_notifications'];
                foreach ($tables as $t) {
                    $full = $wpdb->prefix . $t;
                    $checks['tables'][$t] = $wpdb->get_var("SHOW TABLES LIKE '$full'") === $full;
                }

                // Check if graphql_register_types callbacks ran without error
                $checks['schema_version'] = get_option('maljani_gql_schema_version', 'not set');
                $checks['db_version']     = get_option('maljani_db_version', 'not set');

                // Check PHP error log for recent Maljani errors
                $log_file = ini_get('error_log');
                $checks['error_log_path'] = $log_file ?: 'default';
                $recent_errors = [];
                if ($log_file && file_exists($log_file) && is_readable($log_file)) {
                    $lines = array_slice(file($log_file), -100);
                    foreach ($lines as $line) {
                        if (stripos($line, 'maljani') !== false || stripos($line, 'graphql') !== false) {
                            $recent_errors[] = trim($line);
                        }
                    }
                }
                $checks['recent_errors'] = array_slice($recent_errors, -10);

                return rest_ensure_response($checks);
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
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
                // START OUTPUT BUFFERING to catch any stray warnings/notices
                ob_start();

                if (!is_user_logged_in()) {
                    ob_end_clean(); // Clean before throwing
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

                try {
                    $d1 = new DateTime($input['departure']);
                    $d2 = new DateTime($input['return']);
                } catch (\Exception $e) {
                    ob_end_clean();
                    throw new \GraphQL\Error\UserError(__('Invalid dates provided.', 'maljani'));
                }

                if ($d2 < $d1) {
                    ob_end_clean();
                    throw new \GraphQL\Error\UserError(__('Return date must be on or after departure date.', 'maljani'));
                }

                // +1 to match frontend inclusive count (departure day + all intermediate days + return day)
                $days = max(1, $d1->diff($d2)->days + 1);

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
                    ob_end_clean();
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
                    ob_end_clean();
                    throw new \GraphQL\Error\UserError(__('The selected policy is not available.', 'maljani'));
                }

                $dob      = !empty($input['insuredDob']) ? sanitize_text_field($input['insuredDob']) : null;
                $departure = !empty($input['departure'])  ? sanitize_text_field($input['departure'])  : null;
                $return    = !empty($input['return'])     ? sanitize_text_field($input['return'])     : null;

                $data = [
                    'policy_id' => $policy_id,
                    'policy_number' => $policy_number,
                    'region' => $region_name,
                    'premium' => $premium,
                    'days' => $days,
                    'passengers' => $passengers,
                    'departure' => $departure,
                    'return' => $return,
                    'insured_names' => sanitize_text_field($input['insuredNames'] ?? ''),
                    'insured_dob' => $dob,
                    'passport_number' => sanitize_text_field($input['passportNumber'] ?? ''),
                    'national_id' => sanitize_text_field($input['nationalId'] ?? ($input['passportNumber'] ?? '')),
                    'insured_phone' => sanitize_text_field($input['insuredPhone'] ?? ''),
                    'insured_email' => sanitize_email($input['insuredEmail'] ?? ''),
                    'insured_address' => sanitize_text_field($input['insuredAddress'] ?? ''),
                    'country_of_origin' => sanitize_text_field($input['countryOfOrigin'] ?? ''),
                    'agent_id' => get_current_user_id() ?: 0,
                    'agent_name' => $current_user->display_name ?: 'System',
                    'agency_id' => $is_agent ? $this->resolve_agency_id(get_current_user_id()) : null,
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
                ];

                $inserted = $wpdb->insert($table, $data);
                $sale_id = $wpdb->insert_id;

                // CATCH ANY STRAY OUTPUT
                $stray_output = ob_get_clean();
                if (!empty($stray_output)) {
                    error_log('[Maljani GQL] Caught stray output during sales mutation: ' . $stray_output);
                }

                if (!$inserted || !$sale_id) {
                    $db_err   = $wpdb->last_error;
                    error_log('[Maljani DEBUG] INSERT failed. WPDB error: ' . $db_err);
                    
                    error_log('[Maljani DEBUG] Last SQL: ' . $wpdb->last_query);
                    $wpdb->print_error();

                    // Surface a helpful error message — if table is missing, try to trigger activator
                    if (strpos($db_err, "doesn't exist") !== false) {
                        if (class_exists('Maljani_Activator')) {
                            Maljani_Activator::activate();
                            throw new \GraphQL\Error\UserError('Database tables were missing but have been re-created. Please try clicking submit again.');
                        }
                    }

                    $last_query = $wpdb->last_query;
                    throw new \GraphQL\Error\UserError(
                        "DATABASE INSERTION FAILED! DB Error: " . ($db_err ?: 'No error recorded') . " | SQL: " . substr($last_query, 0, 100)
                    );
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
                    'saleId' => (int) $sale_id,
                    'policyNumber' => $policy_number,
                    'amountPaid' => (float) $amount_tot_client,
                ];
            }
        ]);
    }

    /**
     * Register GraphQL query: myPolicySales
     * Returns all policy sales belonging to the current authenticated user.
     */
    public function register_my_policy_sales_query() {
        if (!function_exists('register_graphql_object_type') || !function_exists('register_graphql_field')) return;

        // Define the PolicySale type
        register_graphql_object_type('MaljaniPolicySale', [
            'description' => 'A policy sale record',
            'fields' => [
                'id'            => ['type' => 'Int',    'description' => 'Sale row ID'],
                'policyId'      => ['type' => 'Int',    'description' => 'WordPress post ID of the policy'],
                'policyTitle'   => ['type' => 'String', 'description' => 'Title of the policy plan'],
                'policyNumber'  => ['type' => 'String', 'description' => 'Generated policy number'],
                'region'        => ['type' => 'String', 'description' => 'Travel destination region'],
                'premium'       => ['type' => 'Float',  'description' => 'Daily premium rate'],
                'days'          => ['type' => 'Int',    'description' => 'Trip duration in days'],
                'departure'     => ['type' => 'String', 'description' => 'Travel start date'],
                'returnDate'    => ['type' => 'String', 'description' => 'Travel end date'],
                'passengers'    => ['type' => 'Int',    'description' => 'Number of insured persons'],
                'insuredNames'  => ['type' => 'String', 'description' => 'Full name of insured'],
                'insuredEmail'  => ['type' => 'String', 'description' => 'Contact email'],
                'insuredPhone'  => ['type' => 'String', 'description' => 'Contact phone number'],
                'passportNumber'=> ['type' => 'String', 'description' => 'Passport number'],
                'insuredDob'    => ['type' => 'String', 'description' => 'Date of birth'],
                'nationalId'    => ['type' => 'String', 'description' => 'National ID number'],
                'insuredAddress' => ['type' => 'String', 'description' => 'Address'],
                'countryOfOrigin'=> ['type' => 'String', 'description' => 'Country of origin'],
                'amountPaid'    => ['type' => 'Float',  'description' => 'Total amount paid'],
                'paymentStatus' => ['type' => 'String', 'description' => 'Payment status'],
                'policyStatus'  => ['type' => 'String', 'description' => 'Policy status'],
                'createdAt'     => ['type' => 'String', 'description' => 'Sale creation timestamp'],
                'workflowStatus'          => ['type' => 'String', 'description' => 'Workflow status'],
                'agentCommissionAmount'   => ['type' => 'Float',  'description' => 'Agent commission earned'],
                'agentCommissionStatus'   => ['type' => 'String', 'description' => 'Commission payout status'],
                'serviceFeeAmount'        => ['type' => 'Float',  'description' => 'Service fee charged'],
                'maljaniCommissionAmount' => ['type' => 'Float',  'description' => 'Platform commission'],
                'netToInsurer'            => ['type' => 'Float',  'description' => 'Net amount to insurer'],
            ],
        ]);

        // Register the root query field
        register_graphql_field('RootQuery', 'myPolicySales', [
            'type'        => ['list_of' => 'MaljaniPolicySale'],
            'description' => 'Get all policy sales for the currently authenticated user',
            'resolve'     => function () {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError('You must be logged in to view your policies.');
                }

                global $wpdb;
                $table = $wpdb->prefix . 'policy_sale';
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE agent_id = %d ORDER BY created_at DESC",
                    $user_id
                ));

                if (!$results) return [];

                $sales = [];
                foreach ($results as $row) {
                    $sales[] = [
                        'id'            => (int) $row->id,
                        'policyId'      => (int) $row->policy_id,
                        'policyTitle'   => get_the_title($row->policy_id),
                        'policyNumber'  => $row->policy_number,
                        'region'        => $row->region,
                        'premium'       => (float) $row->premium,
                        'days'          => (int) $row->days,
                        'departure'     => $row->departure,
                        'returnDate'    => $row->return,
                        'passengers'    => (int) ($row->passengers ?? 1),
                        'insuredNames'  => $row->insured_names,
                        'insuredEmail'  => $row->insured_email,
                        'insuredPhone'  => $row->insured_phone,
                        'passportNumber'=> $row->passport_number,
                        'insuredDob'    => $row->insured_dob ?? '',
                        'nationalId'    => $row->national_id ?? '',
                        'insuredAddress' => $row->insured_address ?? '',
                        'countryOfOrigin'=> $row->country_of_origin ?? '',
                        'amountPaid'    => (float) $row->amount_paid,
                        'paymentStatus' => $row->payment_status,
                        'policyStatus'  => $row->policy_status,
                        'createdAt'     => $row->created_at,
                        'workflowStatus'          => $row->workflow_status ?? '',
                        'agentCommissionAmount'   => (float) ($row->agent_commission_amount ?? 0),
                        'agentCommissionStatus'   => $row->agent_commission_status ?? 'unpaid',
                        'serviceFeeAmount'        => (float) ($row->service_fee_amount ?? 0),
                        'maljaniCommissionAmount' => (float) ($row->maljani_commission_amount ?? 0),
                        'netToInsurer'            => (float) ($row->net_to_insurer ?? 0),
                    ];
                }
                return $sales;
            },
        ]);
    }

    /**
     * Register GraphQL mutation: updatePolicySale
     * Allows editing an unpaid policy sale (departure, return, passengers, personal info).
     */
    public function register_update_sale_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('updatePolicySale', [
            'inputFields' => [
                'saleId'          => ['type' => 'Int',    'description' => 'ID of the sale to update'],
                'departure'       => ['type' => 'String', 'description' => 'New departure date'],
                'return'          => ['type' => 'String', 'description' => 'New return date'],
                'passengers'      => ['type' => 'Int',    'description' => 'Number of passengers'],
                'insuredNames'    => ['type' => 'String'],
                'insuredDob'      => ['type' => 'String'],
                'passportNumber'  => ['type' => 'String'],
                'nationalId'      => ['type' => 'String'],
                'insuredPhone'    => ['type' => 'String'],
                'insuredEmail'    => ['type' => 'String'],
                'insuredAddress'  => ['type' => 'String'],
                'countryOfOrigin' => ['type' => 'String'],
            ],
            'outputFields' => [
                'saleId'      => ['type' => 'Int'],
                'amountPaid'  => ['type' => 'Float'],
                'success'     => ['type' => 'Boolean'],
            ],
            'mutateAndGetPayload' => function ($input) {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError('You must be logged in.');
                }

                $sale_id = intval($input['saleId'] ?? 0);
                if (!$sale_id) {
                    throw new \GraphQL\Error\UserError('Missing sale ID.');
                }

                global $wpdb;
                $table = $wpdb->prefix . 'policy_sale';
                $sale  = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $sale_id
                ));

                if (!$sale) {
                    throw new \GraphQL\Error\UserError('Sale not found.');
                }
                if ((int) $sale->agent_id !== $user_id) {
                    throw new \GraphQL\Error\UserError('You can only edit your own policies.');
                }
                if ($sale->payment_status === 'confirmed') {
                    throw new \GraphQL\Error\UserError('Cannot edit a paid policy.');
                }

                // Build update data from provided fields
                $update = [];
                $field_map = [
                    'insuredNames'    => 'insured_names',
                    'insuredDob'      => 'insured_dob',
                    'passportNumber'  => 'passport_number',
                    'nationalId'      => 'national_id',
                    'insuredPhone'    => 'insured_phone',
                    'insuredEmail'    => 'insured_email',
                    'insuredAddress'  => 'insured_address',
                    'countryOfOrigin' => 'country_of_origin',
                ];

                foreach ($field_map as $gql_key => $db_col) {
                    if (isset($input[$gql_key]) && $input[$gql_key] !== '') {
                        $update[$db_col] = sanitize_text_field($input[$gql_key]);
                    }
                }
                if (isset($input['insuredEmail']) && $input['insuredEmail'] !== '') {
                    $update['insured_email'] = sanitize_email($input['insuredEmail']);
                }

                // Handle date and passenger changes — recalculate premium
                $departure  = isset($input['departure']) ? sanitize_text_field($input['departure']) : $sale->departure;
                $return_d   = isset($input['return'])     ? sanitize_text_field($input['return'])    : $sale->return;
                $passengers = isset($input['passengers']) ? intval($input['passengers'])              : (int) $sale->passengers;

                $dep_ts = strtotime($departure);
                $ret_ts = strtotime($return_d);
                if (!$dep_ts || !$ret_ts || $ret_ts < $dep_ts) {
                    throw new \GraphQL\Error\UserError('Invalid date range.');
                }

                $days = (int)(($ret_ts - $dep_ts) / 86400) + 1;
                $passengers = max(1, $passengers);

                // Recalculate premium from policy brackets
                $policy_id = (int) $sale->policy_id;
                $brackets  = get_post_meta($policy_id, '_policy_day_premiums', true);
                $premium   = 0;
                if (is_array($brackets)) {
                    foreach ($brackets as $b) {
                        if ($days >= intval($b['min_days']) && $days <= intval($b['max_days'])) {
                            $premium = floatval($b['premium_per_day']);
                            break;
                        }
                    }
                }
                if ($premium <= 0) {
                    throw new \GraphQL\Error\UserError('No premium bracket found for ' . $days . ' days.');
                }

                $total_premium = $premium * $passengers;

                // Service fee
                $fee_type  = get_option('maljani_fee_service_type', 'fixed');
                $fee_value = floatval(get_option('maljani_fee_service_value', 0));
                $service_fee = ($fee_type === 'percentage')
                    ? round($total_premium * $fee_value / 100, 2)
                    : $fee_value;

                $amount_paid = $total_premium + $service_fee;

                // Commissions
                $comm_type  = get_post_meta($policy_id, '_policy_aggregator_comm_type', true) ?: 'percentage';
                $comm_value = floatval(get_post_meta($policy_id, '_policy_aggregator_comm_value', true));
                $maljani_comm = ($comm_type === 'percentage')
                    ? round($total_premium * $comm_value / 100, 2)
                    : $comm_value;

                $update['departure']                 = $departure;
                $update['return']                    = $return_d;
                $update['days']                      = $days;
                $update['passengers']                = $passengers;
                $update['premium']                   = $premium;
                $update['amount_paid']               = $amount_paid;
                $update['service_fee_amount']         = $service_fee;
                $update['maljani_commission_amount']  = $maljani_comm;
                $update['net_to_insurer']            = $total_premium - $maljani_comm;

                $wpdb->update($table, $update, ['id' => $sale_id]);

                return [
                    'saleId'     => $sale_id,
                    'amountPaid' => (float) $amount_paid,
                    'success'    => true,
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
        // Always force JSON content-type so urql/fetch clients don't see text/plain.
        $headers['Content-Type'] = 'application/json; charset=UTF-8';

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
     * Register the updateProfile mutation
     */
    public function register_update_profile_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('maljaniUpdateProfile', [
            'inputFields' => [
                'name'  => ['type' => 'String', 'description' => 'Display name'],
                'email' => ['type' => 'String', 'description' => 'Email address'],
                'phone' => ['type' => 'String', 'description' => 'Phone number'],
            ],
            'outputFields' => [
                'success'   => ['type' => 'Boolean'],
                'userName'  => ['type' => 'String'],
                'userEmail' => ['type' => 'String'],
                'userPhone' => ['type' => 'String'],
            ],
            'mutateAndGetPayload' => function($input) {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError(__('You must be logged in.', 'maljani'));
                }

                $update = ['ID' => $user_id];
                if (!empty($input['name'])) {
                    $name = sanitize_text_field($input['name']);
                    $update['display_name'] = $name;
                    $update['first_name']   = $name;
                }
                if (!empty($input['email'])) {
                    $email = sanitize_email($input['email']);
                    if (!is_email($email)) {
                        throw new \GraphQL\Error\UserError(__('Invalid email address.', 'maljani'));
                    }
                    $existing = email_exists($email);
                    if ($existing && $existing !== $user_id) {
                        throw new \GraphQL\Error\UserError(__('This email is already in use.', 'maljani'));
                    }
                    $update['user_email'] = $email;
                }
                $result = wp_update_user($update);
                if (is_wp_error($result)) {
                    throw new \GraphQL\Error\UserError($result->get_error_message());
                }
                if (isset($input['phone'])) {
                    update_user_meta($user_id, 'phone', sanitize_text_field($input['phone']));
                }

                $user  = get_user_by('id', $user_id);
                $phone = get_user_meta($user_id, 'phone', true);
                return [
                    'success'   => true,
                    'userName'  => $user->display_name,
                    'userEmail' => $user->user_email,
                    'userPhone' => $phone ?: '',
                ];
            },
        ]);
    }

    /**
     * Register the myNotifications query.
     * Reads from wp_maljani_notifications table.
     */
    public function register_my_notifications_query() {
        if (!function_exists('register_graphql_object_type') || !function_exists('register_graphql_field')) return;

        register_graphql_object_type('MaljaniNotification', [
            'fields' => [
                'id'        => ['type' => 'Int'],
                'type'      => ['type' => 'String'],
                'title'     => ['type' => 'String'],
                'message'   => ['type' => 'String'],
                'isRead'    => ['type' => 'Boolean'],
                'policyId'  => ['type' => 'Int'],
                'createdAt' => ['type' => 'String'],
            ],
        ]);

        register_graphql_field('RootQuery', 'myNotifications', [
            'type'        => ['list_of' => 'MaljaniNotification'],
            'description' => 'Notifications for the current user',
            'resolve'     => function() {
                $user_id = get_current_user_id();
                if (!$user_id) return [];

                global $wpdb;
                $table = $wpdb->prefix . 'maljani_notifications';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return [];

                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM `$table` WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
                    $user_id
                ));

                return array_map(function($r) {
                    return [
                        'id'        => (int) $r->id,
                        'type'      => $r->type,
                        'title'     => $r->title,
                        'message'   => $r->message,
                        'isRead'    => (bool) $r->is_read,
                        'policyId'  => $r->policy_id ? (int) $r->policy_id : null,
                        'createdAt' => $r->created_at,
                    ];
                }, $rows ?: []);
            },
        ]);
    }

    /**
     * Register the markNotificationsRead mutation
     */
    public function register_mark_notifications_read_mutation() {
        if (!function_exists('register_graphql_mutation')) return;

        register_graphql_mutation('maljaniMarkNotificationsRead', [
            'inputFields' => [
                'ids' => ['type' => ['list_of' => 'Int'], 'description' => 'Notification IDs to mark read. Empty = mark all.'],
            ],
            'outputFields' => [
                'success' => ['type' => 'Boolean'],
                'count'   => ['type' => 'Int'],
            ],
            'mutateAndGetPayload' => function($input) {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError(__('You must be logged in.', 'maljani'));
                }
                global $wpdb;
                $table = $wpdb->prefix . 'maljani_notifications';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    return ['success' => true, 'count' => 0];
                }

                $ids = $input['ids'] ?? [];
                if (!empty($ids)) {
                    $ids = array_map('intval', $ids);
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $count = $wpdb->query($wpdb->prepare(
                        "UPDATE `$table` SET is_read = 1 WHERE user_id = %d AND id IN ($placeholders)",
                        array_merge([$user_id], $ids)
                    ));
                } else {
                    $count = $wpdb->query($wpdb->prepare(
                        "UPDATE `$table` SET is_read = 1 WHERE user_id = %d AND is_read = 0",
                        $user_id
                    ));
                }
                return ['success' => true, 'count' => (int) $count];
            },
        ]);
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

    /* ──────────────────────────────────────────────────────────────────
     *  Agency helpers
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Resolve agency ID from a WP user ID.
     * Checks wp_maljani_agencies.user_id first, then user_meta('agency_id').
     */
    private function resolve_agency_id(int $user_id): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_agencies';
        $agency_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id
        ));
        if ($agency_id) return (int) $agency_id;

        $meta = get_user_meta($user_id, 'agency_id', true);
        return $meta ? (int) $meta : null;
    }

    /**
     * Register the agencyDashboard query — returns stats, clients, commissions, analytics.
     * Only accessible by users with 'agent' role.
     */
    public function register_agency_dashboard_query() {
        if (!function_exists('register_graphql_object_type') || !function_exists('register_graphql_field')) return;

        // Agency profile type
        register_graphql_object_type('MaljaniAgencyProfile', [
            'fields' => [
                'id'             => ['type' => 'Int'],
                'name'           => ['type' => 'String'],
                'contactName'    => ['type' => 'String'],
                'contactEmail'   => ['type' => 'String'],
                'contactPhone'   => ['type' => 'String'],
                'commissionRate' => ['type' => 'Float'],
                'status'         => ['type' => 'String'],
                'createdAt'      => ['type' => 'String'],
            ],
        ]);

        // KPI stats type
        register_graphql_object_type('MaljaniAgencyStats', [
            'fields' => [
                'totalPolicies'        => ['type' => 'Int'],
                'activePolicies'       => ['type' => 'Int'],
                'pendingPolicies'      => ['type' => 'Int'],
                'totalPremiumVolume'   => ['type' => 'Float'],
                'totalCommissionEarned'=> ['type' => 'Float'],
                'pendingCommission'    => ['type' => 'Float'],
                'disputedCommission'   => ['type' => 'Float'],
                'totalClients'         => ['type' => 'Int'],
                'conversionRate'       => ['type' => 'Float'],
            ],
        ]);

        // Client type (aggregated from sales)
        register_graphql_object_type('MaljaniAgencyClient', [
            'fields' => [
                'email'        => ['type' => 'String'],
                'name'         => ['type' => 'String'],
                'phone'        => ['type' => 'String'],
                'policiesCount'=> ['type' => 'Int'],
                'totalPremium' => ['type' => 'Float'],
                'lastActivity' => ['type' => 'String'],
                'hasActive'    => ['type' => 'Boolean'],
            ],
        ]);

        // Monthly analytics point
        register_graphql_object_type('MaljaniMonthlyPoint', [
            'fields' => [
                'month'      => ['type' => 'String'],
                'premium'    => ['type' => 'Float'],
                'commission' => ['type' => 'Float'],
                'policies'   => ['type' => 'Int'],
                'clients'    => ['type' => 'Int'],
            ],
        ]);

        // Status distribution point
        register_graphql_object_type('MaljaniStatusPoint', [
            'fields' => [
                'status' => ['type' => 'String'],
                'count'  => ['type' => 'Int'],
            ],
        ]);

        // Top product
        register_graphql_object_type('MaljaniTopProduct', [
            'fields' => [
                'policyId'    => ['type' => 'Int'],
                'policyTitle' => ['type' => 'String'],
                'soldCount'   => ['type' => 'Int'],
                'totalPremium'=> ['type' => 'Float'],
            ],
        ]);

        // Dashboard root type
        register_graphql_object_type('MaljaniAgencyDashboard', [
            'fields' => [
                'agency'            => ['type' => 'MaljaniAgencyProfile'],
                'stats'             => ['type' => 'MaljaniAgencyStats'],
                'clients'           => ['type' => ['list_of' => 'MaljaniAgencyClient']],
                'monthlyAnalytics'  => ['type' => ['list_of' => 'MaljaniMonthlyPoint']],
                'statusDistribution'=> ['type' => ['list_of' => 'MaljaniStatusPoint']],
                'topProducts'       => ['type' => ['list_of' => 'MaljaniTopProduct']],
            ],
        ]);

        register_graphql_field('RootQuery', 'agencyDashboard', [
            'type'        => 'MaljaniAgencyDashboard',
            'description' => 'Full agency dashboard data for the current agent user',
            'resolve'     => function () {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError('You must be logged in.');
                }
                $user = wp_get_current_user();
                if (!in_array('agent', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
                    throw new \GraphQL\Error\UserError('Agency access required.');
                }

                global $wpdb;
                $sale_table   = $wpdb->prefix . 'policy_sale';
                $agency_table = $wpdb->prefix . 'maljani_agencies';

                // Resolve agency
                $agency = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$agency_table} WHERE user_id = %d LIMIT 1", $user_id
                ));
                if (!$agency) {
                    // Sub-agent — check user_meta
                    $agency_id = get_user_meta($user_id, 'agency_id', true);
                    if ($agency_id) {
                        $agency = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$agency_table} WHERE id = %d LIMIT 1", $agency_id
                        ));
                    }
                }

                $agency_profile = $agency ? [
                    'id'             => (int) $agency->id,
                    'name'           => $agency->name ?: $agency->agency_name ?: '',
                    'contactName'    => $agency->contact_name ?: '',
                    'contactEmail'   => $agency->contact_email ?: '',
                    'contactPhone'   => $agency->contact_phone ?: '',
                    'commissionRate' => (float) ($agency->commission_rate ?: $agency->commission_percent ?: 0),
                    'status'         => $agency->status ?: 'pending',
                    'createdAt'      => $agency->created_at ?: '',
                ] : null;

                // All sales for this agent
                $sales = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$sale_table} WHERE agent_id = %d ORDER BY created_at DESC",
                    $user_id
                ));

                // === KPI Stats ===
                $total = count($sales);
                $active = 0; $pending = 0;
                $premium_vol = 0; $earned = 0; $pending_comm = 0; $disputed = 0;
                $unique_clients = [];

                foreach ($sales as $s) {
                    $premium_vol += (float) $s->amount_paid;
                    $email = strtolower(trim($s->insured_email));
                    if ($email) $unique_clients[$email] = true;

                    if (in_array($s->policy_status, ['active', 'verification_ready'])) $active++;
                    if (in_array($s->policy_status, ['unconfirmed', 'pending_review'])) $pending++;

                    $comm = (float) ($s->agent_commission_amount ?? 0);
                    $comm_status = $s->agent_commission_status ?? 'unpaid';
                    if ($comm_status === 'paid' || $comm_status === 'received') $earned += $comm;
                    elseif ($comm_status === 'disputed') $disputed += $comm;
                    else $pending_comm += $comm;
                }

                $stats = [
                    'totalPolicies'         => $total,
                    'activePolicies'        => $active,
                    'pendingPolicies'       => $pending,
                    'totalPremiumVolume'    => round($premium_vol, 2),
                    'totalCommissionEarned' => round($earned, 2),
                    'pendingCommission'     => round($pending_comm, 2),
                    'disputedCommission'    => round($disputed, 2),
                    'totalClients'          => count($unique_clients),
                    'conversionRate'        => $total > 0 ? round(($active / $total) * 100, 1) : 0,
                ];

                // === Clients (aggregated) ===
                $client_map = [];
                foreach ($sales as $s) {
                    $email = strtolower(trim($s->insured_email));
                    if (!$email) continue;
                    if (!isset($client_map[$email])) {
                        $client_map[$email] = [
                            'email'        => $s->insured_email,
                            'name'         => $s->insured_names,
                            'phone'        => $s->insured_phone ?: '',
                            'policiesCount'=> 0,
                            'totalPremium' => 0,
                            'lastActivity' => $s->created_at,
                            'hasActive'    => false,
                        ];
                    }
                    $client_map[$email]['policiesCount']++;
                    $client_map[$email]['totalPremium'] += (float) $s->amount_paid;
                    if ($s->created_at > $client_map[$email]['lastActivity']) {
                        $client_map[$email]['lastActivity'] = $s->created_at;
                    }
                    if (in_array($s->policy_status, ['active', 'verification_ready'])) {
                        $client_map[$email]['hasActive'] = true;
                    }
                    // Keep the latest name/phone
                    if (!empty($s->insured_names)) $client_map[$email]['name'] = $s->insured_names;
                    if (!empty($s->insured_phone)) $client_map[$email]['phone'] = $s->insured_phone;
                }
                // Sort clients by lastActivity desc
                usort($client_map, function ($a, $b) {
                    return strcmp($b['lastActivity'], $a['lastActivity']);
                });
                $clients = array_values($client_map);

                // === Monthly Analytics (last 12 months) ===
                $monthly = [];
                foreach ($sales as $s) {
                    $month = substr($s->created_at, 0, 7); // YYYY-MM
                    if (!isset($monthly[$month])) {
                        $monthly[$month] = ['premium' => 0, 'commission' => 0, 'policies' => 0, 'clients' => []];
                    }
                    $monthly[$month]['premium'] += (float) $s->amount_paid;
                    $monthly[$month]['commission'] += (float) ($s->agent_commission_amount ?? 0);
                    $monthly[$month]['policies']++;
                    $email = strtolower(trim($s->insured_email));
                    if ($email) $monthly[$month]['clients'][$email] = true;
                }
                ksort($monthly);
                $monthly_points = [];
                $months_to_show = array_slice(array_keys($monthly), -12);
                foreach ($months_to_show as $m) {
                    $monthly_points[] = [
                        'month'      => $m,
                        'premium'    => round($monthly[$m]['premium'], 2),
                        'commission' => round($monthly[$m]['commission'], 2),
                        'policies'   => $monthly[$m]['policies'],
                        'clients'    => count($monthly[$m]['clients']),
                    ];
                }

                // === Status Distribution ===
                $status_counts = [];
                foreach ($sales as $s) {
                    $st = $s->policy_status ?: 'unknown';
                    $status_counts[$st] = ($status_counts[$st] ?? 0) + 1;
                }
                $status_dist = [];
                foreach ($status_counts as $st => $cnt) {
                    $status_dist[] = ['status' => $st, 'count' => $cnt];
                }

                // === Top Products ===
                $product_map = [];
                foreach ($sales as $s) {
                    $pid = (int) $s->policy_id;
                    if (!isset($product_map[$pid])) {
                        $product_map[$pid] = ['policyId' => $pid, 'policyTitle' => get_the_title($pid), 'soldCount' => 0, 'totalPremium' => 0];
                    }
                    $product_map[$pid]['soldCount']++;
                    $product_map[$pid]['totalPremium'] += (float) $s->amount_paid;
                }
                usort($product_map, function ($a, $b) { return $b['soldCount'] - $a['soldCount']; });
                $top_products = array_slice(array_values($product_map), 0, 5);

                return [
                    'agency'             => $agency_profile,
                    'stats'              => $stats,
                    'clients'            => $clients,
                    'monthlyAnalytics'   => $monthly_points,
                    'statusDistribution' => $status_dist,
                    'topProducts'        => $top_products,
                ];
            },
        ]);
    }

    /**
     * Register agent display settings query + mutation.
     * These settings are presentation-only and never affect backend settlement math.
     */
    public function register_agent_display_settings() {
        if (!function_exists('register_graphql_object_type') || !function_exists('register_graphql_field') || !function_exists('register_graphql_mutation')) {
            return;
        }

        register_graphql_object_type('MaljaniAgentDisplaySettings', [
            'fields' => [
                'additionalFeeType'    => ['type' => 'String'],
                'additionalFeeValue'   => ['type' => 'Float'],
                'receiptIssuerName'    => ['type' => 'String'],
                'showProcessedByTick'  => ['type' => 'Boolean'],
            ],
        ]);

        register_graphql_field('RootQuery', 'agentDisplaySettings', [
            'type'        => 'MaljaniAgentDisplaySettings',
            'description' => 'Display-only fee and receipt branding settings for the current agent',
            'resolve'     => function () {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError('You must be logged in.');
                }

                $user = wp_get_current_user();
                if (!in_array('agent', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
                    throw new \GraphQL\Error\UserError('Agent access required.');
                }

                $fee_type = get_user_meta($user_id, 'maljani_agent_display_fee_type', true);
                if (!in_array($fee_type, ['fixed', 'percent'], true)) {
                    $fee_type = 'fixed';
                }

                return [
                    'additionalFeeType'   => $fee_type,
                    'additionalFeeValue'  => (float) get_user_meta($user_id, 'maljani_agent_display_fee_value', true),
                    'receiptIssuerName'   => (string) get_user_meta($user_id, 'maljani_agent_receipt_issuer_name', true),
                    'showProcessedByTick' => get_user_meta($user_id, 'maljani_agent_show_processed_by_tick', true) !== '0',
                ];
            },
        ]);

        register_graphql_mutation('maljaniUpdateAgentDisplaySettings', [
            'inputFields' => [
                'additionalFeeType'   => ['type' => 'String'],
                'additionalFeeValue'  => ['type' => 'Float'],
                'receiptIssuerName'   => ['type' => 'String'],
                'showProcessedByTick' => ['type' => 'Boolean'],
            ],
            'outputFields' => [
                'success'             => ['type' => 'Boolean'],
                'message'             => ['type' => 'String'],
                'additionalFeeType'   => ['type' => 'String'],
                'additionalFeeValue'  => ['type' => 'Float'],
                'receiptIssuerName'   => ['type' => 'String'],
                'showProcessedByTick' => ['type' => 'Boolean'],
            ],
            'mutateAndGetPayload' => function ($input) {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    throw new \GraphQL\Error\UserError('You must be logged in.');
                }

                $user = wp_get_current_user();
                if (!in_array('agent', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
                    throw new \GraphQL\Error\UserError('Agent access required.');
                }

                $fee_type = sanitize_text_field($input['additionalFeeType'] ?? 'fixed');
                if (!in_array($fee_type, ['fixed', 'percent'], true)) {
                    $fee_type = 'fixed';
                }

                $fee_value = isset($input['additionalFeeValue']) ? (float) $input['additionalFeeValue'] : 0;
                if ($fee_value < 0) $fee_value = 0;
                if ($fee_type === 'percent' && $fee_value > 100) $fee_value = 100;
                if ($fee_type === 'fixed' && $fee_value > 10000000) $fee_value = 10000000;

                $issuer = sanitize_text_field($input['receiptIssuerName'] ?? '');
                $issuer = substr($issuer, 0, 120);
                $show_processed = !empty($input['showProcessedByTick']);

                update_user_meta($user_id, 'maljani_agent_display_fee_type', $fee_type);
                update_user_meta($user_id, 'maljani_agent_display_fee_value', $fee_value);
                update_user_meta($user_id, 'maljani_agent_receipt_issuer_name', $issuer);
                update_user_meta($user_id, 'maljani_agent_show_processed_by_tick', $show_processed ? '1' : '0');

                return [
                    'success'             => true,
                    'message'             => 'Agent settings saved.',
                    'additionalFeeType'   => $fee_type,
                    'additionalFeeValue'  => (float) $fee_value,
                    'receiptIssuerName'   => $issuer,
                    'showProcessedByTick' => $show_processed,
                ];
            },
        ]);
    }
}

new Maljani_GraphQL_Auth();
