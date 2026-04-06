# Tick — Maljani Travel Insurance Frontend

> Development log for the React (Vite + urql) frontend of the **Travel Insurance Center-Kenya** WordPress plugin.
> Last updated: **April 6, 2026**

---

## Stack

| Layer | Tech |
|---|---|
| Framework | React 19 + Vite 6 |
| GraphQL | urql 5 → WPGraphQL on WordPress backend |
| Styling | Inline styles with CSS custom properties (no Tailwind) |
| Fonts | Syne (display), Plus Jakarta Sans (body) |
| Design | Glassmorphism, dark navy theme, gold accents |
| Auth | Custom JWT (not WP Application Passwords) — `AuthContext.jsx` |
| Navigation | localStorage-based history stack (no React Router) |
| Responsive | Custom `useResponsive()` hook (mobile ≤768, tablet 769-1024, desktop >1024) |

---

## CSS Custom Properties (Design Tokens)

```css
--navy          /* #0f172a — primary background */
--gold          /* #d4af37 — accent / CTA */
--indigo        /* deep indigo for secondary elements */
--slate         /* muted text */
--glass-bg      /* rgba(255,255,255,0.04) */
--glass-border  /* rgba(255,255,255,0.08) */
--font-display  /* Syne */
--font-body     /* Plus Jakarta Sans */
--text-muted    /* subdued text color */
```

---

## File Map

### `src/lib/` — Shared utilities
| File | Purpose |
|---|---|
| `AuthContext.jsx` | JWT auth context, LOGIN/REGISTER/UPDATE_PROFILE mutations, `useAuth()` hook. Stores `{ name, email, phone, role, token }` in localStorage `maljani_auth`. |
| `graphql.js` | urql client — reads `VITE_GRAPHQL_URL` env var, attaches Bearer token + `X-Maljani-App-Secret` header on every request. |
| `useResponsive.js` | Returns `{ mobile, tablet, desktop }` booleans via `matchMedia`. |

### `src/components/` — All 26 components

| Component | Description |
|---|---|
| **App.jsx** | Root — wraps urql `Provider` + `AuthProvider`. Custom history-stack navigation (`handleNavigate`, `handleBack`, `handleForward`). |
| **Header.jsx** | Top nav bar — logo, nav links, auth buttons, mobile hamburger. |
| **Footer.jsx** | Site footer with links, newsletter, socials. |
| **LandingPage.jsx** | Composes Hero, PolicyShowcase, ProcessSection, TrustSection, FAQSection. |
| **Hero.jsx** | Hero section with search form (region, dates, passengers) → wizard. |
| **PolicyShowcase.jsx** | Featured policies carousel/grid from GraphQL `policies` query. |
| **ProcessSection.jsx** | "How it works" 3-step explainer. |
| **TrustSection.jsx** | Trust signals / stats. |
| **TrustedPartners.jsx** | Partner logos. |
| **FAQSection.jsx** | Accordion FAQ. |
| **Catalog.jsx** | Full policy catalog with filters. |
| **CategoryFilters.jsx** | Region/category filter pills for catalog. |
| **PolicyDetail.jsx** | Single policy page — benefits, coverage, premiums, CTA. |
| **QuoteWizard.jsx** | 4-step wizard: Trip Details → Choose Plan → Your Info → Confirmed. Fetches `policies(first:100)` + `regions(first:50)`, computes premium from brackets client-side, calls `submitPolicySale` mutation. |
| **Login.jsx** | Login form → `maljaniLogin` mutation. |
| **Register.jsx** | Registration form → `maljaniRegister` mutation. |
| **Dashboard.jsx** | Smart router — renders `AgentDashboard` or `InsuredDashboard` based on `role`. |
| **AgentDashboard.jsx** | Full agency admin dashboard with 5 tabs (Overview, Clients, Policies, Commissions, Analytics). Real data from `agencyDashboard` + `myPolicySales` GraphQL queries. SVG bar/donut charts. Self-insured policy detection, edit/assign buttons. |
| **InsuredDashboard.jsx** | Client dashboard — policy list, payment status, profile. |
| **ProfileEditModal.jsx** | Glass-card modal for name/email/phone editing → `maljaniUpdateProfile` mutation. |
| **NotificationPanel.jsx** | Bell icon with unread badge, dropdown panel with type-based icons → `myNotifications` query + `markNotificationsRead` mutation. |
| **PolicyEditModal.jsx** | Edit unpaid policies — pre-fills all client + travel fields → `updatePolicySale` mutation (auto-recalculates premium). |
| **AssignClientModal.jsx** | Searchable client picker from agency CRM → assigns client to a policy via `updatePolicySale`. |
| **VerifyPolicy.jsx** | Public policy verification by policy number. |
| **AboutPage.jsx** | About us page. |
| **AgenciesPage.jsx** | Agency listing / become-an-agent page. |
| **Messaging.jsx** | Live chat / messaging interface. |

---

## GraphQL Queries & Mutations (Frontend → Backend)

### Queries
| Name | Used In | Description |
|---|---|---|
| `GetRegions` | QuoteWizard | Fetches region taxonomy terms (name, slug). |
| `GetPoliciesForQuote` | QuoteWizard | All published policies with premiums, regions, insurer info. |
| `MyPolicySales` | AgentDashboard, InsuredDashboard | All sales for the authenticated user with commission/workflow fields. |
| `AgencyDashboard` | AgentDashboard | Full stats, clients CRM, monthly analytics, status distribution, top products. |
| `MyNotifications` | NotificationPanel | Last 50 notifications for current user. |

### Mutations
| Name | Used In | Description |
|---|---|---|
| `maljaniLogin` | Login, QuoteWizard | JWT login → returns token, name, email, phone, role. |
| `maljaniRegister` | Register, QuoteWizard | Create account → returns token + user data. |
| `submitPolicySale` | QuoteWizard | Create a new policy sale (premium calculated server-side from brackets). |
| `updatePolicySale` | PolicyEditModal, AssignClientModal | Edit insured details / travel dates on unpaid policies. Recalculates premium + commissions. |
| `maljaniUpdateProfile` | ProfileEditModal | Update display name, email, phone. |
| `markNotificationsRead` | NotificationPanel | Mark notification IDs as read, or mark all read. |

---

## Backend GraphQL (PHP — WPGraphQL)

All custom types/mutations live in two files:

### `includes/class-maljani-graphql-auth.php`
- JWT auth (`generate_token` / `authenticate_request` via `determine_current_user` filter)
- CORS management
- App secret validation (`X-Maljani-App-Secret` header)
- Schema cache flush on version bump
- **Custom types**: `MaljaniPolicySale`, `MaljaniNotification`, `MaljaniAgencyProfile`, `MaljaniAgencyStats`, `MaljaniAgencyClient`, `MaljaniMonthlyPoint`, `MaljaniStatusPoint`, `MaljaniTopProduct`, `MaljaniAgencyDashboard`
- **Mutations**: `maljaniLogin`, `maljaniRegister`, `submitPolicySale`, `updatePolicySale`, `maljaniUpdateProfile`, `markNotificationsRead`
- **Queries**: `myPolicySales`, `myNotifications`, `agencyDashboard`
- All `graphql_register_types` callbacks wrapped in try/catch for error isolation
- Health-check endpoint: `/wp-json/maljani/v1/health`

### `admin/class-maljani_policy-cpt.php`
- CPT `policy` with `show_in_graphql => true` (auto-handles `policies` query)
- Taxonomy `policy_region` with `show_in_graphql => true` (auto-handles `regions` query)
- Custom fields: `policyDescription`, `policyCoverDetails`, `policyBenefits`, `policyNotCovered`, `policyCurrency`, `policyPaymentDetails`, `policyFeatureTags`, `policyDayPremiums` (bracket list), `policyInsurerName`, `policyInsurerLogo`, `policyCountries`
- `User.phone` GraphQL field

---

## Database Tables

| Table | Purpose |
|---|---|
| `wp_policy_sale` | All policy sales — insured details, agent, agency, premium, commissions, workflow status, payment. |
| `wp_maljani_agencies` | Agency profiles — name, contact, commission rate, status. |
| `wp_maljani_notifications` | In-app notifications — type, title, message, read flag. |

Key columns in `wp_policy_sale`: `id`, `policy_id`, `policy_number`, `region`, `premium`, `days`, `passengers`, `departure`, `return`, `insured_names`, `insured_dob`, `passport_number`, `national_id`, `insured_phone`, `insured_email`, `insured_address`, `country_of_origin`, `agent_id`, `agency_id`, `amount_paid`, `service_fee_amount`, `maljani_commission_amount`, `agent_commission_amount`, `agent_commission_status`, `net_to_insurer`, `payment_status`, `policy_status`, `workflow_status`, `created_at`.

---

## Responsive Design

All 26 components use `useResponsive()` hook with inline styles. Breakpoints:
- **Mobile**: ≤768px — single column, smaller fonts, stacked layouts
- **Tablet**: 769–1024px — 2-column where appropriate
- **Desktop**: >1024px — full multi-column layouts

---

## Agent Workflow (Key Business Logic)

1. **Agent creates policy via QuoteWizard** on behalf of a client — enters all client details (name, DOB, passport, phone, email, address, country).
2. **Self-insured detection**: If `insuredEmail === agent.email`, the policy is flagged with `⚑ SELF` badge in the Policies tab.
3. **Edit unpaid policies**: Agent can edit any unpaid policy (all client fields + travel dates + passengers). Premium recalculates server-side.
4. **Assign to client**: Self-insured unpaid policies show an "→ Assign" button → opens `AssignClientModal` with searchable CRM client list → updates insured details.
5. **Payment**: Client pays anytime before departure date via Pesapal gateway.
6. **Workflow**: `draft` → `pending_review` → `submitted_to_insurer` → `approved` → `active` → `verification_ready`

---

## Notification System

### Backend (`includes/class-maljani-user-notifications.php`)
- `Maljani_User_Notifications::push(user_id, type, title, message, policy_id)` — inserts into `wp_maljani_notifications`.
- Hooks: `maljani_workflow_transition`, `maljani_new_sale`, `maljani_policy_activated`, `maljani_admin_status_change`.
- Cron (daily): cover-expiry reminders (7-day + 1-day), payment reminders (unpaid >24h).

### Frontend (`NotificationPanel.jsx`)
- Bell icon with unread count badge.
- Dropdown list with type-based icons (status_change, cancellation, cover_ending, payment_reminder, info).
- "Mark all read" button.

---

## Environment Variables

```env
VITE_GRAPHQL_URL=https://mtj.ivk.mybluehost.me/website_e48ea083/graphql
VITE_APP_SECRET=          # Optional — validated by backend if set
```

For local dev: `VITE_GRAPHQL_URL=http://localhost/graphql` or use Vite proxy.

---

## Build & Deploy

```bash
cd tick
npm install
npm run dev          # dev server at localhost:5173
npm run build        # outputs to tick/dist/
```

The built `dist/` is loaded by the WordPress plugin via shortcode `[maljani_policy_sale]` which enqueues the Vite bundle.

---

## Known Issues / Notes

- `class-maljani-admin.php` references `class-policy-cpt.php` (non-existent file) — this code path isn't triggered in production because the CPT is registered directly in `maljani.php`.
- `policies(first: 100)` fetches all policies on mount — works for current catalog size but won't scale past ~100 policies. Consider server-side region filtering with `where: { taxQuery }`.
- The "Get Another Quote" reset in QuoteWizard has a stale-key bug (`days`, `countryOfOrigin` keys that don't exist in form shape).
- Plugin version is `1.0.8` (bumped from `1.0.7` for schema cache flush).
- All `graphql_register_types` callbacks are wrapped in try/catch since v1.0.8 to prevent one failing registration from crashing the entire schema (fixes 502 Bad Gateway).
