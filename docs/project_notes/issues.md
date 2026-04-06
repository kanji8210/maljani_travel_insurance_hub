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
