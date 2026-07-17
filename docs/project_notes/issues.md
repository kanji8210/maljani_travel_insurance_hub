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
