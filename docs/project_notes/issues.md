# Work Log

Log completed work and session progress here.

## Template
### YYYY-MM-DD - Brief Description
- **Status**: Completed / In Progress / Blocked
- **Description**: Summary of work done

---

### 2026-03-16 - Initialized Agentic AI Skills
- **Status**: Completed
- **Description**: Added `frontend-design` and `project-memory` skills. Set up the `docs/project_notes/` infrastructure.

### 2026-03-17 - Backend In-App Notification System
- **Status**: Completed
- **Description**: Created `class-maljani-user-notifications.php` with `Maljani_User_Notifications` class. Writes to `wp_maljani_notifications` table on all policy events:
  - Workflow transitions (pending_review, submitted_to_insurer, approved, active, verification_ready, cancelled, draft rejection)
  - New sale creation (via QuoteWizard/GraphQL)
  - Payment confirmed / policy activated (Pesapal IPN)
  - Admin quick-status, full-edit, and archive changes (added `do_action('maljani_admin_status_change')` to `class-maljani-policy-sales.php`)
  - Daily WP-Cron: cover-expiry reminders (7-day & 1-day), payment reminders (unpaid >24h)
  - Cron cleared on plugin deactivation (`class-maljani-deactivator.php`)
  - Loaded in `maljani.php` after API endpoints

### 2026-06-04 - Insurer USD to KSH Exchange Rate Support
- **Status**: Completed
- **Description**: Added insurer-level USD to KSH exchange rate meta field in the insurer profile admin form (`_insurer_usd_to_ksh_rate`) and applied it to premium calculations across:
  - sales flow rendering and checkout insert calculations in `includes/class-maljani-sales-page.php`
  - policy filter card premium display in `includes/class-maljani-filter.php`
  - GraphQL sales create/update mutation premium calculations in `includes/class-maljani-graphql-auth.php`
  - When policy currency is USD and insurer rate is set, displayed and stored premium math is converted to KSH.

### 2026-06-29 - Default Exchange Rate KSH Display
- **Status**: Completed
- **Description**: Added `maljani_default_usd_to_ksh_rate` to Settings as the global USD to KSH exchange rate. When set, USD policy premiums display/calculate in KSH across policy filter display, sales page calculations, WPGraphQL policy fields, GraphQL/Auth premium calculations, and the single-policy frontend calculator/table payload. Insurer `_insurer_usd_to_ksh_rate` is only used if the global default is not set.

### 2026-06-04 - Policy Taxonomy Expansion (Region + Insurance Type)
- **Status**: Completed
- **Description**: Extended policy taxonomy management in backend:
  - kept `policy_region` as taxonomy-driven region management with policy editor quick-add
  - added new `policy_type` taxonomy (insurance types like Basic, Premium) with policy editor quick-add
  - added submenu links for both taxonomies under Maljani admin menu
  - wired save logic to persist selected region and insurance type taxonomy terms on policy save

### 2026-06-04 - Frontend Filter: Insurance Type Under Region
- **Status**: Completed
- **Description**: Updated frontend quote/filter forms to place Insurance Type directly under Region selection and included it in AJAX + URL filtering logic for policy results.

### 2026-07-17 - Pesapal Payment Confirmation Without Auto-Issuance
- **Status**: Completed
- **Description**: Updated Pesapal v3 checkout flow to use configured TIC-Kenya credentials without split-payment routing. Successful IPN callbacks now confirm payment and move the sale to manual insurer processing (`policy_status=pending_review`, `workflow_status=submitted_to_insurer`) instead of activating the policy or triggering insurer API registration. Updated user notification copy and admin Pesapal wording to reflect insurer-issued documents and future insurer-facing workflows.

### 2026-07-19 - Manual Insurer Settlement Cleanup
- **Status**: Completed
- **Description**: Simplified payment operations around confirmed sales: customer payments still collect to TIC-Kenya, `policy_sale` now tracks manual insurer settlement fields (`insurer_payment_status`, reference, date, note), and payment confirmation marks insurer settlement as due while moving workflow to `submitted_to_insurer`. Updated CRM/admin views to use settlement breakdown fields instead of legacy split/payment wording.

### 2026-08-04 - Manual Insurer Policy Issuance Workflow
- **Status**: Completed
- **Description**: Clarified and enforced the admin issuance flow: payment confirmation queues a request for review; admin starts Processing and manually enters customer details on the insurer website; the insurer-issued policy number is required when marking the request Issued; only issued requests can be activated after document upload. New requests use the sale ID as their request reference and no longer receive a provisional `POL-*` policy number.

### 2026-08-05 - Claims & Refunds Assistance Portal
- **Status**: Completed
- **Description**: Added the public `[maljani_claims_portal]` intake form for claim and premium refund assistance, a dedicated request ledger with fee snapshots and workflow/payment states, and a staff processing queue under TIC-Kenya. The fixed assistance fee is managed in Global Fee Defaults, and Page Management can create the public portal page. Sensitive evidence uploads are intentionally deferred; staff request documents through a private channel after intake review.

### 2026-08-06 - Refund & Cancellation Intake
- **Status**: Completed
- **Description**: Extended the headless refund flow with active-policy selection, cancellation reasons, M-Pesa or bank payout details, conditional visa rejection proof, multipart submission, and insurer refund statuses. Proof files are MIME/size validated, stored in a protected uploads directory, and available only through a capability-checked admin download. Added `POST /wp-json/maljani/v1/refunds/submit`, migration support for existing claim ledgers, and the `/refunds/new` frontend route while preserving `/claims-refunds` as an alias.

### 2026-08-11 - Claims Feature Folder Organization
- **Status**: Completed
- **Description**: Moved Tick claim/refund screens into `src/components/claims/` behind a feature entry point, and moved the WordPress claims portal plus its stylesheet into `includes/claims/`. Updated loader and asset paths without changing routes, shortcodes, REST endpoints, or behavior.

### 2026-08-11 - CRM Final Document Upload Reliability

- **Status**: Completed
- **Description**: Replaced silent CRM document moves with WordPress upload handling and checked file, URL, database insert, and policy transition results. Failed uploads now roll back stored files and rows, return actionable admin errors, and cannot activate a policy with an empty document location.

### 2026-09-12 - Pesapal Amount-Limit Response Handling
- **Status**: Completed
- **Description**: Updated SubmitOrderRequest handling to follow Pesapal API 3.0 semantics: an order is successful only when a non-empty payment `redirect_url` is returned. HTTP 200 amount-limit rejections now produce a dedicated `pesapal_amount_limit` error with HTTP 422, while server logs capture the sale ID, original amount, normalized ISO currency, environment, and provider response for support diagnosis. Invalid non-positive amounts are rejected before submission, and payment amounts are never reduced or split automatically.

### 2026-09-12 - Pesapal Sandbox KES 100 Test Charge
- **Status**: Completed
- **Description**: At the user's explicit request, all Pesapal orders now submit a forced KES 100 charge for end-to-end testing, including orders using Live mode, while retaining the full policy value on the sale. A completed payment uses the normal IPN confirmation workflow and marks the sale fully paid. The settings screen displays a prominent warning while this temporary override is active.

### 2026-09-12 - Pesapal Return Page and Customer Documents
- **Status**: Completed
- **Description**: Added a dedicated `/payment/return` frontend page that verifies Pesapal callback identifiers through an authenticated WordPress endpoint before showing payment success. Payment confirmation is idempotent and bound to the sale's stored tracking ID. Confirmed dashboard policies expose the official receipt and verification certificate; the insurer-uploaded policy PDF appears only after manual issuance and activation. Invoice access remains available before payment.

### 2026-09-12 - Dynamic Invoice and Receipt Payment Details
- **Status**: Completed
- **Description**: Replaced the generic invoice payment instruction with structured provider, M-Pesa Paybill, and policy account-template settings. Policy invoices now resolve per-sale tokens such as `POL-{sale_id}`. Completed Pesapal status checks persist the actual method, masked account, confirmation code, currency, received amount, and payment date for receipts. Receipts distinguish the amount actually collected from the full invoiced value during the temporary KES 100 test override and correctly describe the policy as awaiting insurer processing.

### 2026-09-13 - Agent-Issued Client Documents
- **Status**: Completed
- **Description**: Agent-generated invoices and receipts now identify the authenticated selling agent in the client-facing “From” section. The document uses the agent's configured document issuer name, linked agency name, or WordPress display name, plus linked agency email, phone, and IRA licence where available. TICK remains an optional processor attribution. Admin-generated documents continue to resolve the agent assigned to the sale.

### 2026-09-13 - Agent Document Printing and Frontend Certificate Verification
- **Status**: Completed
- **Description**: Exposed invoice printing for every agent-owned sale and receipt plus verification-certificate printing after payment confirmation in both agent policy views. Certificate QR codes now use a configurable front-end application URL and open `/verify` with a signed sale token. Added a public read-only token verification endpoint and automatic QR verification in the React portal without placing passport details in the URL.

